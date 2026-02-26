<?php
// Acesso público — sem autenticação direta, mas validação é feita via código e data de nascimento
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Inscricao.php';
require_once __DIR__ . '/../app/models/Estudante.php';

$database = new Database();
$db = $database->getConnection();
$inscricaoModel = new Inscricao($db);
$estudanteModel = new Estudante($db);

$resultado = null;
$erro = '';
$qrcode = null;
$qrtext = null; // Chave Pix copia-e-cola
$valor = null; // Valor do pagamento

// ================================
// VALIDAÇÃO DOS DADOS DE ENTRADA
// ================================
$codigo = trim($_GET['codigo'] ?? '');
$dataNascimento = $_GET['data_nascimento'] ?? ''; // Deve vir da sessão ou GET

if (!empty($codigo) && !empty($dataNascimento)) {
    // Busca por código de inscrição e data de nascimento (mesma lógica de acompanhar.php)
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
        $erro = "Inscrição não encontrada ou dados incorretos para esta página.";
    } elseif ($resultado['pagamento_confirmado']) {
        $erro = "Pagamento já foi confirmado para esta inscrição.";
    } elseif ($resultado['situacao'] !== 'pagamento_pendente' && $resultado['situacao'] !== 'documentos_anexados') {
        $erro = "Pagamento não disponível no status atual da inscrição.";
    }
} else {
    $erro = "Dados de inscrição incompletos.";
}

if ($resultado && empty($erro)) {
    // ================================
    // VALOR DO PAGAMENTO (FIXO OU DINÂMICO)
    // ================================
    // Por enquanto, vamos definir um valor fixo. Pode vir de uma configuração ou tabela futuramente.
    $valor = 25.00; // Exemplo: R$ 25,00
    $descricao = "Pagamento anuidade CIE - Inscrição: {$resultado['codigo_inscricao']} - {$resultado['nome']}";

    // ================================
    // INTEGRACAO COM ABACATEPAY (SIMULADA)
    // ================================
    // Esta parte é crucial e dependerá da API do AbacatePay.
    // Por enquanto, simularemos a geração de QR Code e texto Pix.

    // --- SIMULACAO (SUBSTITUIR PELO CÓDIGO REAL DA API DO ABACATEPAY) ---
    $chavePix = "sua_chave_pix@exemplo.com"; // Substituir pela chave real
    $idTransacao = uniqid(); // ID único para a transação
    $qrcode = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=SPD*1.0*1***{$chavePix}*{$valor}*{$descricao}*TXID:{$idTransacao}"; // QR Code Genérico para demonstração
    $qrtext = "00020101021226920014BR.GOV.BCB.PIX2570{$chavePix}52040000530398654{$valor}5802BR5925{$descricao}6008BRASILIA62070503***6304A72D"; // Texto Pix Genérico para demonstração
    // --- FIM SIMULACAO ---

    // --- IMPLEMENTACAO REAL (Exemplo Básico com cURL) ---
    /*
    $urlAbacatePay = 'https://api.abacatepay.com.br/v1/cobranca'; // URL da API (exemplo)
    $token = 'SEU_TOKEN_DE_ACESSO'; // Token fornecido pelo AbacatePay

    $payload = [
        "calendario" => [
            "expiracao" => 3600 // Expira em 1 hora (em segundos)
        ],
        "devedor" => [
            "nome" => $resultado['nome'],
            "cpf" => "" // Opcional, se tiver
        ],
        "valor" => [
            "original" => number_format($valor, 2, '.', '')
        ],
        "chave" => $chavePix,
        "solicitacaoPagador" => $descricao
    ];

    $curl = curl_init($urlAbacatePay);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token // ou outro tipo de autenticação exigido
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode === 200) {
        $cobranca = json_decode($response, true);
        if ($cobranca && isset($cobranca['pixCopiaECola'], $cobranca['qrCode'])) {
            $qrtext = $cobranca['pixCopiaECola'];
            $qrcode = $cobranca['qrCode']; // Pode ser uma URL para a imagem ou o próprio código binário
        } else {
            $erro = "Erro na resposta da API do AbacatePay (dados ausentes).";
        }
    } else {
        $erro = "Erro na comunicação com o AbacatePay (HTTP {$httpCode}): " . $response;
    }
    */
    // --- FIM IMPLEMENTACAO REAL ---
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

            <?php if ($qrcode && $qrtext): ?>
                <h3>Como pagar?</h3>
                <p>Escaneie o QR Code abaixo ou copie a chave Pix para realizar o pagamento.</p>

                <div class="qrcode-container">
                    <img src="<?= htmlspecialchars($qrcode) ?>" alt="QR Code PIX" class="qrcode-img" />
                </div>

                <div class="pix-text">
                    <pre id="pix-key"><?= htmlspecialchars($qrtext) ?></pre>
                    <button class="copy-btn" onclick="copiarChave()">Copiar Chave Pix</button>
                </div>

                <p><small>Após o pagamento, o status será atualizado automaticamente. Aguarde alguns minutos e atualize a página de acompanhamento.</small></p>

            <?php else: ?>
                <div class="erro">Erro ao gerar o QR Code ou a chave Pix.</div>
            <?php endif; ?>

            <a href="acompanhar.php">← Voltar ao Acompanhamento</a>

        <?php else: ?>
            <!-- Este caso só deve ocorrer se houver um erro não capturado acima -->
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