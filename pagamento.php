<?php
// Acesso público — sem autenticação direta, mas validação é feita via código e data de nascimento
require_once __DIR__ . '../app/config/abacatepay_config.php';
require_once __DIR__ . '../app/config/database.php';
require_once __DIR__ . '../app/models/Estudante.php';
require_once __DIR__ . '../app/models/Inscricao.php';
$database = new Database();
$db = $database->getConnection();
$inscricaoModel = new Inscricao($db); // Correção: Nome completo da classe e passagem do $db
$estudanteModel = new Estudante($db);

$resultado = null;
$erro = '';
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
        $erro = "Pagamento já foi confirmado para esta inscrição. Se acha que é um erro, entre em contato com a administração.";
    }
    // Nenhuma verificação de status é feita aqui, conforme regra final.
} else {
    $erro = "Dados de inscrição incompletos.";
}

if ($resultado && empty($erro)) {
    // ================================
    // VALOR DO PAGAMENTO (FIXO OU DINÂMICO)
    // ================================
    // Por enquanto, vamos definir um valor fixo. Pode vir de uma configuração ou tabela futuramente.
    $valor = 25.00; // Exemplo: R$ 25,00
    $descricao = "Pagamento anuidade CIE - Inscrição: {$resultado['codigo_inscricao']} - {$resultado['nome']}"; // Correção: Adicionado ; e definido $descricao após $valor
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Escolha a Forma de Pagamento - CIE</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 20px; background: #f9f9f9; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #2e7d32; text-align: center; margin-bottom: 30px; }
        .info-box { background: #e8f5e9; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .payment-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .payment-option { background: #f5f5f5; padding: 20px; border-radius: 8px; text-align: center; cursor: pointer; transition: background-color 0.3s; }
        .payment-option:hover { background-color: #e0e0e0; }
        .payment-option h3 { margin: 0 0 10px 0; color: #333; }
        .payment-option img { width: 60px; height: 60px; object-fit: contain; margin-bottom: 10px; }
        .status-pendente { color: #f57c00; font-weight: bold; }
        a { color: #1976d2; text-decoration: none; display: inline-block; margin-top: 20px; }
        a:hover { text-decoration: underline; }
        .erro { background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Escolha a Forma de Pagamento - Inscrição #<?= htmlspecialchars($codigo ?? 'N/A') ?></h2>

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

            <h3>Selecione a Forma de Pagamento:</h3>
            <div class="payment-options">
                <!-- Opção PIX -->
                <a href="pagamento_pix.php?codigo=<?= urlencode($resultado['codigo_inscricao']) ?>&data_nascimento=<?= urlencode($resultado['data_nascimento']) ?>" class="payment-option"> <!-- Correção: URL completa e correta -->
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23009688' d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.94-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z'/%3E%3C/svg%3E" alt="PIX">
                    <h3>Pix</h3>
                    <pamente com QR Code ou chave Pix.</p>
                </a>

                <!-- Opção Cartão de Crédito -->
                <a href="pagamento_cartao.php?codigo=<?= urlencode($resultado['codigo_inscricao']) ?>&data_nascimento=<?= urlencode($resultado['data_nascimento']) ?>" class="payment-option">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%234CAF50' d='M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z'/%3E%3C/svg%3E" alt="Cartão de Crédito">
                    <h3>Cartão de Crédito</h3>
                    <p>Pague com seu cartão de crédito.</p>
                </a>

                <!-- Opção Boleto Bancário (Placeholder) -->
                <div class="payment-option" onclick="alert('Forma de pagamento Boleto Bancário em desenvolvimento.')">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23FF9800' d='M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z'/%3E%3C/svg%3E" alt="Boleto Bancário">
                    <h3>Boleto Bancário</h3>
                    <p>Gere um boleto para pagar em qualquer banco (em breve).</p>
                </div>
            </div>

            <p><small>O status da inscrição será atualizado automaticamente pelo sistema após a confirmação do pagamento pelo gateway.</small></p>

            <a href="acompanhar.php">← Voltar ao Acompanhamento</a>

        <?php else: ?>
            <!-- Este caso só deve ocorrer se houver um erro não capturado acima -->
            <div class="erro">Erro ao car pagamento.</div>
            <a href="acompanhar.php">← Voltar ao Acompanhamento</a>
        <?php endif; ?>
    </div>
</body>
</html>