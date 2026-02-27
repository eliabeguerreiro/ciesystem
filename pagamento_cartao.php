<?php
// Inclui a configuração da API
require_once __DIR__ . '/../app/config/abacatepay_config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Estudante.php';
require_once __DIR__ . '/../app/models/Inscricao.php';

$database = new Database();
$db = $database->getConnection();
$inscricaoModel = new Inscricao($db);

$resultado = null;
$erro = '';
$checkoutUrl = null; // URL para o checkout da AbacatePay
$valor = VALOR_PAGAMENTO_CIE; // Usa a constante definida no config

// ================================
// VALIDAÇÃO DOS DADOS DE ENTRADA
// ================================
$codigo = trim($_GET['codigo'] ?? '');
$dataNascimento = $_GET['data_nascimento'] ?? '';

if (!empty($codigo) && !empty($dataNascimento)) {
    $query = "SELECT i.*, e.nome, e.matricula, e.data_nascimento as estudante_data_nascimento, e.email, e.cpf
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
} else {
    $erro = "Dados de inscrição incompletos.";
}

if ($resultado && empty($erro)) {
    $descricao = "CIE 2026 - {$resultado['nome']}"; // Descrição para o produto
    $cpf = preg_replace('/[^0-9]/', '', $resultado['cpf'] ?? ''); // Limpa CPF

    // ================================
    // INTEGRACAO COM ABACATEPAY (CARTÃO - CRIAÇÃO DE COBRANÇA)
    // ================================
    $urlAbacatePay = ABACATEPAY_API_BASE_URL . '/billing/create'; // Endpoint para criar cobrança
    $apiKey = ABACATEPAY_API_KEY;

    $payload = [
        "frequency" => "ONE_TIME",
        "methods" => ["CARD"], // Apenas Cartão
        "products" => [
            [
                "externalId" => "cie_anuidade_2026", // ID único para o produto
                "name" => "Anuidade CIE 2026",
                "description" => $descricao,
                "quantity" => 1,
                "price" => (int)($valor * 100) // Preço em centavos
            ]
        ],
        "returnUrl" => "https://seudominio.com/acompanhar.php", // Onde voltar se cancelar
        "completionUrl" => "https://seudominio.com/acompanhar.php", // Onde ir após concluir
        "customer" => [
            "name" => $resultado['nome'],
            "email" => $resultado['email'] ?? '',
            "taxId" => $cpf, // CPF ou CNPJ
            "cellphone" => $resultado['telefone'] ?? '' // Opcional, mas útil
        ],
        // "metadata" => [ // Dados adicionais, como o código da inscrição
        //     "codigo_inscricao" => $resultado['codigo_inscricao']
        // ]
    ];

    $curl = curl_init($urlAbacatePay);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode === 200) {
        $cobranca = json_decode($response, true);
        if ($cobranca && isset($cobranca['data']['url'])) {
            // A resposta da AbacatePay contém a URL para o checkout
            $checkoutUrl = $cobranca['data']['url'];
            // Redirecionar para o checkout da AbacatePay
            header("Location: $checkoutUrl");
            exit; // Encerra o script após o redirect
        } else {
            $erro = "Erro na resposta da API da AbacatePay (dados ausentes ou malformados).";
            error_log("Erro Cartão API: " . print_r($cobranca, true)); // Log para debug
        }
    } else {
        $erro = "Erro na comunicação com a AbacatePay (HTTP {$httpCode}): " . $response;
        error_log("Erro Cartão API HTTP: " . $response); // Log para debug
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Processando Pagamento via Cartão - CIE</title>
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
        <h2>Processando Pagamento via Cartão - Inscrição #<?= htmlspecialchars($codigo ?? 'N/A') ?></h2>

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
            <p>Redirecionando para o checkout da AbacatePay...</p>
            <!-- O redirecionamento já foi feito via header() acima -->
        <?php else: ?>
            <div class="erro">Erro ao carregar os dados de pagamento.</div>
            <a href="acompanhar.php">← Voltar ao Acompanhamento</a>
        <?php endif; ?>
    </div>
</body>
</html>