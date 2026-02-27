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
$qrcode = null;
$qrtext = null; // Chave Pix copia-e-cola
$valor = VALOR_PAGAMENTO_CIE; // Usa a constante definida no config

// ================================
// VALIDAÇÃO DOS DADOS DE ENTRADA
// ================================
$codigo = trim($_GET['codigo'] ?? '');
$dataNascimento = $_GET['data_nascimento'] ?? '';

if (!empty($codigo) && !empty($dataNascimento)) {
    $query = "SELECT i.*, e.nome, e.matricula, e.data_nascimento as estudante_data_nascimento
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
    $descricao = "Pagamento anuidade CIE - Inscrição: {$resultado['codigo_inscricao']} - {$resultado['nome']}";

    // ================================
    // INTEGRACAO COM ABACATEPAY (PIX)
    // ================================
    $urlAbacatePay = ABACATEPAY_API_BASE_URL . '/payments'; // Endpoint para criar pagamento
    $apiKey = ABACATEPAY_API_KEY;

    $payload = [
        "amount" => (int)($valor * 100), // Valor em centavos (25.00 -> 2500)
        "description" => $descricao,
        "customer" => [
            "name" => $resultado['nome'],
            "email" => $resultado['email'] ?? '' // Opcional
        ],
        "methods" => ["PIX"] // Solicita apenas PIX
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
            // A resposta da AbacatePay contém uma URL para o checkout
            // Para PIX, você pode redirecionar o usuário para esta URL ou tentar extrair o QR Code
            // A documentação oficial da AbacatePay deve indicar como obter o QR Code e a chave copia-e-cola
            // Exemplo de como pode ser a resposta:
            // {
            //   "data": {
            //     "id": "...",
            //     "url": "https://abacatepay.com/pay/...",
            //     "amount": 2500,
            //     "status": "PENDING",
            //     "methods": ["PIX"],
            //     "qrCode": "data:image/png;base64,...", // Opcional, depende da API
            //     "pixCopiaECola": "000201010212..."
            //   },
            //   "error": null
            // }

            // --- HIPÓTESE BASEADA NA DOCUMENTAÇÃO ---
            // A API pode não retornar diretamente o QR Code e a chave copia-e-cola
            // Neste caso, a melhor prática é redirecionar o usuário para a URL de checkout da AbacatePay
            // que contém o QR Code e os detalhes.
            $checkoutUrl = $cobranca['data']['url'];
            header("Location: $checkoutUrl");
            exit; // Encerra o script após o redirect

            // --- SE A API RETORNAR QR CODE E CHAVE COPIA-E-COLA ---
            // if (isset($cobranca['data']['qrCode'], $cobranca['data']['pixCopiaECola'])) {
            //     $qrcode = $cobranca['data']['qrCode']; // Pode ser uma URL ou dados base64
            //     $qrtext = $cobranca['data']['pixCopiaECola'];
            // } else {
            //     $erro = "Erro: API da AbacatePay não retornou QR Code ou chave copia-e-cola.";
            // }
            // --- FIM HIPÓTESE ---

        } else {
            $erro = "Erro na resposta da API da AbacatePay (dados ausentes ou malformados).";
        }
    } else {
        $erro = "Erro na comunicação com a AbacatePay (HTTP {$httpCode}): " . $response;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Processando Pagamento via PIX - CIE</title>
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
        <h2>Processando Pagamento via PIX - Inscrição #<?= htmlspecialchars($codigo ?? 'N/A') ?></h2>

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