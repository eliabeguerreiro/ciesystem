<?php
// Inclui a configuração do gateway de pagamento (contendo as credenciais do Mercado Pago)
require_once __DIR__ . '../app/config/payment_gateway_config.php';

// Inclui as dependências do Composer (SDK do Mercado Pago)
require_once __DIR__ . '../vendor/autoload.php';

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;

// Inclui as dependências do sistema
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Estudante.php';
require_once __DIR__ . '/../app/models/Inscricao.php';

// Configura o SDK do Mercado Pago
MercadoPagoConfig::setAccessToken(MERCADOPAGO_ACCESS_TOKEN);
// Opcional: Definir ambiente de runtime (LOCAL para testes locais, SERVER para produção)
// MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);

$database = new Database();
$db = $database->getConnection();
$inscricaoModel = new Inscricao($db);

$resultado = null;
$erro = '';
$preference = null; // Objeto da preferência do Mercado Pago

// ================================
// VALIDAÇÃO DOS DADOS DE ENTRADA
// ================================
$codigo = trim($_GET['codigo'] ?? '');
$dataNascimento = $_GET['data_nascimento'] ?? '';

if (!empty($codigo) && !empty($dataNascimento)) {
    // Busca por código de inscrição e data de nascimento
    $query = "SELECT i.*, e.nome, e.matricula, e.data_nascimento as estudante_data_nascimento, e.email
              FROM inscricoes i
              INNER JOIN estudantes e ON i.estudante_id = e.id
              WHERE i.codigo_inscricao = :codigo AND e.data_nascimento = :data_nascimento";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':codigo', $codigo);
    $stmt->bindParam(':data_nascimento', $dataNascimento);
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resultado) {
        $erro = "Inscrição não encontrada ou dados incorretos.";
    } elseif ($resultado['pagamento_confirmado']) {
        $erro = "Pagamento já foi confirmado para esta inscrição.";
    }
    // Nenhuma verificação de status é feita aqui, conforme regra final.
} else {
    $erro = "Dados de inscrição incompletos.";
}

if ($resultado && empty($erro)) {
    // ================================
    // VALOR DO PAGAMENTO (FIXO OU DINÂMICO)
    // ================================
    $valor = 25.00; // Exemplo: R$ 25,00
    $descricao = "Pagamento anuidade CIE - Inscrição: {$resultado['codigo_inscricao']} - {$resultado['nome']}";

    // ================================
    // INTEGRACAO COM MERCADO PAGO (CRIAÇÃO DE PREFERÊNCIA)
    // ================================
    $client = new PreferenceClient();

    // Itens do carrinho (no caso, apenas um item para a anuidade)
    $items = [
        [
            "title" => "Anuidade CIE 2026",
            "description" => $descricao,
            "quantity" => 1,
            "unit_price" => (float)$valor, // O SDK espera um float
            "currency_id" => "BRL" // Opcional, mas recomendado
        ]
    ];

    // Dados do pagador (estudante)
    $payer = [
        "name" => $resultado['nome'],
        "email" => $resultado['email'] ?? '', // Opcional, mas útil para identificação
        // "phone" => ["area_code" => "", "number" => ""], // Opcional
        // "identification" => ["type" => "CPF", "number" => $cpf], // Opcional
        // "address" => [...], // Opcional
    ];

    // URLs de retorno após o pagamento
    $backUrls = [
        "success" => "https://seudominio.com/retorno_sucesso.php", // Substitua pelo seu endpoint
        "failure" => "https://seudominio.com/retorno_falha.php",   // Substitua pelo seu endpoint
        "pending" => "https://seudominio.com/retorno_pendente.php" // Substitua pelo seu endpoint
    ];

    // Configurações da preferência
    $request = [
        "items" => $items,
        "payer" => $payer,
        "back_urls" => $backUrls,
        "auto_return" => "approved", // Redireciona automaticamente após aprovação
        "notification_url" => "https://seudominio.com/webhook_mp.php", // URL do seu webhook (substitua!)
        "external_reference" => $resultado['codigo_inscricao'], // ID único da inscrição para vincular o pagamento
        "statement_descriptor" => "CIE 2026", // Nome que aparecerá na fatura do cartão
    ];

    try {
        // Cria a preferência no Mercado Pago
        $preference = $client->create($request);

        if ($preference && $preference->init_point) {
            // Redireciona o usuário para o checkout do Mercado Pago
            header("Location: " . $preference->init_point);
            exit; // Encerra o script após o redirect
        } else {
            $erro = "Erro ao criar preferência de pagamento no Mercado Pago (sem init_point).";
        }
    } catch (MPApiException $e) {
        $erro = "Erro na API do Mercado Pago: " . $e->getMessage();
        error_log("Erro MP API (pagamento_mp.php): " . $e->getMessage()); // Log para debug
    } catch (Exception $e) {
        $erro = "Erro geral: " . $e->getMessage();
        error_log("Erro Geral (pagamento_mp.php): " . $e->getMessage()); // Log para debug
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Processando Pagamento - CIE</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 20px; background: #f9f9f9; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #2e7d32; text-align: center; margin-bottom: 30px; }
        .info-box { background: #e8f5e9; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .status-pendente { color: #f57c00; font-weight: bold; }
        a { color: #1976d2; text-decoration: none; display: inline-block; margin-top: 20px; }
        a:hover { text-decoration: underline; }
        .erro { background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Processando Pagamento - Inscrição #<?= htmlspecialchars($codigo ?? 'N/A') ?></h2>

        <?php if ($erro): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
            <a href="acompanhar.php">← Voltar ao Acompanhamento</a>
        <?php elseif ($resultado): ?>
            <div class="info-box">
                <h3>Olá, <?= htmlspecialchars($resultado['nome']) ?>!</h3>
                <p><strong>Inscrição:</strong> <?= htmlspecialchars($resultado['codigo_inscricao']) ?></p>
                <p><strong>Matrícula:</strong> <?= htmlspecialchars($resultado['matricula']) ?></p>
                <p><strong>Status Atual:</strong> <span class="status-pendente"><?= ucfirst(str_replace('_', ' ', $resultado['situacao'])) ?></span></p>
                <p><strong>Valor a Pagar:</strong> R$ <?= number_format($valor, 2, ',', '.') ?></p>
                <p><strong>Descrição:</strong> <?= htmlspecialchars($descricao) ?></p>
            </div>
            <p>Redirecionando para o checkout do Mercado Pago...</p>
            <!-- O redirecionamento já foi feito via header() acima se a preferência for criada com sucesso -->
        <?php else: ?>
            <div class="erro">Erro ao carregar os dados de pagamento.</div>
            <a href="acompanhar.php">← Voltar ao Acompanhamento</a>
        <?php endif; ?>
    </div>
</body>
</html>