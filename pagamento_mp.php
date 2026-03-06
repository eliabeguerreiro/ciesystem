<?php
// --- PASSO 1: Declarações 'use' (logo após <?php e antes de qualquer código executável) ---
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;

// --- PASSO 2: Inclui as dependências do Composer (SDK do Mercado Pago) ---
// Caminho relativo a partir de public/pagamento_mp.php -> ciesytem/public/../vendor/autoload.php
require_once __DIR__ . '../vendor/autoload.php';

// --- PASSO 3: Inclui a configuração do gateway de pagamento (contendo as credenciais do Mercado Pago) ---
// Caminho relativo a partir de public/pagamento_mp.php -> ciesytem/public/../app/config/payment_gateway_config.php
require_once __DIR__ . '../app/config/payment_gateway_config.php';

// --- PASSO 4: Inclui as dependências do sistema ---
// Caminhos relativos a partir de public/pagamento_mp.php -> ciesytem/public/../app/models/
require_once __DIR__ . '../app/config/database.php';
require_once __DIR__ . '../app/models/Estudante.php';
require_once __DIR__ . '../app/models/Inscricao.php';

// --- PASSO 5: Configura o SDK do Mercado Pago ---
// Certifique-se de que MERCADOPAGO_ACCESS_TOKEN esteja definida no payment_gateway_config.php
if (!defined('MERCADOPAGO_ACCESS_TOKEN') || empty(MERCADOPAGO_ACCESS_TOKEN)) {
    die("Erro Crítico: MERCADOPAGO_ACCESS_TOKEN não está definida ou está vazia em payment_gateway_config.php.");
}
MercadoPagoConfig::setAccessToken(MERCADOPAGO_ACCESS_TOKEN);

// Opcional: Definir ambiente de runtime (LOCAL para testes locais, SERVER para produção)
// Para testes: MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
// Para produção: Comente ou remova a linha abaixo
// MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::SERVER); // Padrão é SERVER

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
    // Busca por código de inscrição e data de nascimento (mesma lógica de acompanhar.php)
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

    // URLs de retorno após o pagamento (IMPORTANTE: Devem ser URLs completas!)
    // Estas URLs são para onde o *usuário* é redirecionado após finalizar o pagamento no checkout do MP.
    // Substitua 'seudominio.com' pelo seu domínio real (ex: 'localhost/ciesytem' para testes locais).
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
        "auto_return" => "approved", // Redireciona automaticamente após aprovação para 'success'
        "notification_url" => $baseUrl . "/webhook_mp.php", // URL do seu webhook (onde o MP envia notificações)
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
            error_log("Erro MP (pagamento_mp.php): Preferência criada, mas init_point ausente. Dados: " . print_r($preference, true));
        }
    } catch (MPApiException $e) {
        // Captura a resposta bruta da API do Mercado Pago
        $apiResponse = $e->getApiResponse();
        $statusCode = $apiResponse->getStatusCode();
        $content = $apiResponse->getContent(); // Este é o JSON de erro que você precisa ver!

        // Monta uma mensagem de erro detalhada
        $erro = "Erro na API do Mercado Pago (Status {$statusCode}): ";
        $erro .= json_encode($content, JSON_PRETTY_PRINT); // Formata o JSON para facilitar a leitura

        // Log detalhado no arquivo de erro do PHP
        error_log("=== ERRO MERCADO PAGO - DETALHES ===");
        error_log("Status Code: " . $statusCode);
        error_log("Resposta Bruta: " . print_r($content, true));
        error_log("Mensagem do SDK: " . $e->getMessage());
        error_log("Request Enviado: " . print_r($request, true)); // Log do request enviado
        error_log("Token usado (últimos 5 chars): " . substr(MERCADOPAGO_ACCESS_TOKEN, -5)); // Verifica se o token é o esperado
        error_log("=== FIM ERRO ===");

        // Exibe o erro na tela para depuração (só em ambiente local!)
        // NUNCA FAÇA ISSO EM PRODUÇÃO!
        if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || $_SERVER['REMOTE_ADDR'] === '127.0.0.1') {
            echo "<h3>Erro ao se comunicar com o Mercado Pago:</h3>";
            echo "<pre style='background-color: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; white-space: pre-wrap;'>";
            echo "Status HTTP: " . htmlspecialchars($statusCode) . "\n\n";
            echo "Detalhes do Erro:\n" . htmlspecialchars($erro) . "\n\n";
            echo "</pre>";
            echo "<p>Verifique o log do PHP para mais detalhes.</p>";
        }
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