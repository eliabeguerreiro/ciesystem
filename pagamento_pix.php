<?php
// Inclui a configuração da API
require_once __DIR__ . '../app/config/abacatepay_config.php';
require_once __DIR__ . '../app/config/database.php';
require_once __DIR__ . '../app/models/Estudante.php';
require_once __DIR__ . '../app/models/Inscricao.php';

$database = new Database();
$db = $database->getConnection();
$inscricaoModel = new Inscricao($db);

$resultado = null;
$erro = '';
$brCode = null; // Chave Pix copia-e-cola
$brCodeBase64 = null; // Imagem do QR Code em Base64
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
    $descricao = "CIE 2026 - {$resultado['nome']}"; // Descrição curta (max 37 chars?)
    $cpf = preg_replace('/[^0-9]/', '', $resultado['cpf'] ?? ''); // Limpa CPF

    // ================================
    // INTEGRACAO COM ABACATEPAY (PIX QR CODE)
    // ================================
    $urlAbacatePay = ABACATEPAY_API_BASE_URL . '/pixQrCode/create'; // Endpoint para criar PIX
    $apiKey = ABACATEPAY_API_KEY;

    $payload = [
        "amount" => (int)($valor * 100), // Valor em centavos (25.00 -> 2500)
        "description" => $descricao,
        "expiresIn" => 3600, // Expira em 1 hora (em segundos)
        // "customer" => [ // Opcional, talvez não seja necessário para PIX direto
        //     "name" => $resultado['nome'],
        //     "email" => $resultado['email'] ?? '',
        //     "taxId" => $cpf, // CPF ou CNPJ
        //     "cellphone" => "" // Opcional
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
try {
    curl_close($curl); // <-- Chamada encapsulada para evitar o aviso
} catch (\Error $e) {
    // Opcional: Log do erro se necessário
    error_log("Erro ao fechar cURL em pagamento_pix.php: " . $e->getMessage());
}
// 

    if ($httpCode === 200) {
        $pixResponse = json_decode($response, true);
        if ($pixResponse && isset($pixResponse['data']['brCode'], $pixResponse['data']['brCodeBase64'])) {
            $brCode = $pixResponse['data']['brCode'];
            $brCodeBase64 = $pixResponse['data']['brCodeBase64'];
            // QR Code e chave copia-e-cola obtidos com sucesso
        } else {
            $erro = "Erro: API da AbacatePay não retornou QR Code ou chave copia-e-cola (dados ausentes).";
            error_log("Erro PIX API: " . print_r($pixResponse, true)); // Log para debug
        }
    } else {
        $erro = "Erro na comunicação com a AbacatePay (HTTP {$httpCode}): " . $response;
        error_log("Erro PIX API HTTP: " . $response); // Log para debug
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Pagamento via PIX - CIE</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 20px; background: #f9f9f9; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #2e7d32; text-align: center; margin-bottom: 30px; }
        .info-box { background: #e8f5e9; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .qrcode-container { text-align: center; margin: 20px 0; }
        .qrcode-img { max-width: 200px; max-height: 200px; border: 1px solid #ddd; padding: 5px; background: white; }
        .pix-text { background: #f5f5f5; padding: 10px; border-radius: 4px; font-family: monospace; word-break: break-all; margin: 10px 0; }
        .copy-btn { background: #1976d2; color: white; border: none; padding: 8px 16px; cursor: pointer; border-radius: 4px; }
        .copy-btn:hover { background: #1565c0; }
        .status-pendente { color: #f57c00; font-weight: bold; }
        a { color: #1976d2; text-decoration: none; display: inline-block; margin-top: 20px; }
        a:hover { text-decoration: underline; }
        .erro { background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Pagamento via PIX - Inscrição #<?= htmlspecialchars($codigo ?? 'N/A') ?></h2>

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

            <?php if ($brCodeBase64 && $brCode): ?>
                <h3>Como pagar?</h3>
                <p>Escaneie o QR Code abaixo ou copie a chave Pix para realizar o pagamento.</p>

                <div class="qrcode-container">
                    <img src="data:image/png;base64,<?= htmlspecialchars($brCodeBase64) ?>" alt="QR Code PIX" class="qrcode-img" />
                </div>

                <div class="pix-text">
                    <pre id="pix-key"><?= htmlspecialchars($brCode) ?></pre>
                    <button class="copy-btn" onclick="copiarChave()">Copiar Chave Pix</button>
                </div>

                <p><small>Após o pagamento, o status será atualizado automaticamente pelo sistema quando o gateway confirmar o pagamento. Aguarde alguns minutos e atualize a página de acompanhamento.</small></p>

            <?php else: ?>
                <div class="erro">Erro ao gerar o QR Code ou a chave Pix.</div>
            <?php endif; ?>

            <a href="acompanhar.php">← Voltar ao Acompanhamento</a>

        <?php else: ?>
            <div class="erro">Erro ao carregar os dados de pagamento.</div>
            <a href="acompanhar.php">← Voltar ao Acompanhamento</a>
        <?php endif; ?>
    </div>

    <script>
        function copiarChave() {
            const chaveElement = document.getElementById('pix-key');
            const chaveTexto = chaveElement.textContent || chaveElement.innerText;

            navigator.clipboard.writeText(chaveTexto).then(function() {
                alert('Chave Pix copiada para a área de transferência!');
            }).catch(function(err) {
                // Fallback para navegadores antigos
                const textArea = document.createElement("textarea");
                textArea.value = chaveTexto;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Chave Pix copiada para a área de transferência!');
            });
        }
    </script>
</body>
</html>