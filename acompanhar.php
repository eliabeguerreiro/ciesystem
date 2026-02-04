<?php
// Acesso público — sem autenticação
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/Estudante.php';
require_once __DIR__ . '/app/models/Inscricao.php';

$database = new Database();
$db = $database->getConnection();
$inscricaoModel = new Inscricao($db);

$resultado = null;
$erro = '';

// ================================
// BUSCA POR CÓDIGO DE INSCRIÇÃO E DATA DE NASCIMENTO
// ================================
if ($_POST) {
    $codigo = trim($_POST['codigo'] ?? '');
    $dataNascimento = $_POST['data_nascimento'] ?? ''; // Novo campo

    if (!empty($codigo) && !empty($dataNascimento)) { // Agora exige ambos
        // Busca por código de inscrição e data de nascimento
        $query = "SELECT i.*, e.nome, e.matricula, e.data_nascimento -- Inclui a data para verificação extra
                  FROM inscricoes i
                  INNER JOIN estudantes e ON i.estudante_id = e.id
                  WHERE i.codigo_inscricao = :codigo AND e.data_nascimento = :data_nascimento"; // Condição adicionada

        $stmt = $db->prepare($query);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':data_nascimento', $dataNascimento); // Novo bind
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $erro = "Informe o código de inscrição e sua data de nascimento.";
    }

    // Se não encontrou, mostra mensagem genérica (evita enumeração)
    if (!$resultado && empty($erro)) {
        $erro = "Inscrição não encontrada ou dados incorretos. Verifique os dados ou entre em contato com a administração.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Acompanhar Inscrição</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 20px; background: #f9f9f9; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h2 { color: #1976d2; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 4px; font-weight: bold; }
        input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #1976d2; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; }
        button:hover { background: #1565c0; }
        .status-box { background: #f5f5f5; padding: 15px; border-radius: 6px; margin-top: 20px; }
        .status-pendente,
        .status-aguardando_validacao { color: #f57c00; }
        .status-dados_aprovados,
        .status-pagamento_pendente { color: #f57c00; font-weight: bold; }
        .status-documentos_anexados { color: #5d4037; font-weight: bold; }
        .status-pago { color: #2e7d32; font-weight: bold; }
        .status-cie_emitida { color: #1565c0; font-weight: bold; }
        a { color: #1976d2; text-decoration: none; display: inline-block; margin-top: 20px; }
        a:hover { text-decoration: underline; }
        .erro { background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Acompanhar Minha Inscrição</h2>

        <?php if ($resultado): ?>
            <div class="status-box">
                <h3>Olá, <?= htmlspecialchars($resultado['nome']) ?>!</h3>
                <p><strong>Código da Inscrição:</strong> <?= htmlspecialchars($resultado['codigo_inscricao']) ?></p>
                <p><strong>Matrícula:</strong> <?= htmlspecialchars($resultado['matricula']) ?></p>
                <p><strong>Status Atual:</strong>
                    <span class="status-<?= $resultado['situacao'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $resultado['situacao'])) ?>
                    </span>
                </p>
                <!-- Mensagens orientativas -->
                <?php
                switch ($resultado['situacao']) {
                    case 'aguardando_validacao':
                        echo '<p style="color:#f57c00;">Sua inscrição está em análise. Aguarde a validação dos documentos.</p>';
                        break;
                    case 'dados_aprovados':
                    case 'pagamento_pendente':
                        echo '<p style="color:#f57c00;">Seus dados foram Registrados Aguarde aprovação dos dados de matricula e instruções para pagamento.</p>';
                        break;
                    case 'documentos_anexados':
                        echo '<p style="color:#5d4037;">Documentos recebidos. Aguardando confirmação de pagamento.</p>';
                        break;
                    case 'pago':
                        echo '<p style="color:#2e7d32;">Pagamento confirmado! Sua CIE será emitida em breve.</p>';
                        break;
                    case 'cie_emitida':
                        echo '<p style="color:#1565c0;"><strong>Sua CIE já foi emitida!</strong> Entre em contato para retirada.</p>';
                        break;
                    default:
                        echo '<p>Em processamento...</p>';
                }
                ?>
            </div>
            <a href="index.php">← Voltar ao início</a>
        <?php else: ?>
            <?php if ($erro): ?>
                <div class="erro"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Código de Inscrição *</label>
                    <input type="text" name="codigo" placeholder="Ex: a1b2c3d4-e5f6..." value="<?= htmlspecialchars($_POST['codigo'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Data de Nascimento *</label>
                    <input type="date" name="data_nascimento" value="<?= htmlspecialchars($_POST['data_nascimento'] ?? '') ?>" required>
                </div>
                <!-- Removido o campo CPF -->
                <button type="submit">Consultar Status</button>
            </form>
            <a href="index.php">← Voltar ao início</a>
        <?php endif; ?>
    </div>
</body>
</html>