<?php
session_start();

// Verificação de acesso
require_once __DIR__ . '/../app/controllers/AuthController.php';
$auth = new AuthController();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    die("Acesso negado.");
}

// Carrega dependências
require_once __DIR__ . '/../app/models/Inscricao.php';

$database = new Database();
$db = $database->getConnection();
$inscricao = new Inscricao($db);

$erro = '';
$sucesso = '';

// ================================
// AÇÕES: Upload de documentos
// ================================

if ($_POST) {
    if (isset($_POST['acao']) && $_POST['acao'] === 'upload_matricula') {
        $inscricaoId = (int)$_POST['inscricao_id'];
        if (!empty($_FILES['comprovante_matricula']['name'])) {
            $inscTemp = new Inscricao($db);
            $inscTemp->id = $inscricaoId;
            if ($inscTemp->salvarDocumentos($_FILES['comprovante_matricula'], 'matricula')) {
                // Verifica se já tem pagamento → muda status para 'documentos_anexados'
                $docs = $inscTemp->getDocumentos();
                $temPagamento = false;
                foreach ($docs as $doc) {
                    if ($doc['tipo'] === 'pagamento') {
                        $temPagamento = true;
                        break;
                    }
                }
                if ($temPagamento) {
                    $inscTemp->atualizarSituacao('documentos_anexados');
                }
                $sucesso = "Comprovante de matrícula anexado.";
            } else {
                $erro = "Erro ao anexar comprovante de matrícula.";
            }
        } else {
            $erro = "Selecione um arquivo.";
        }
    }

    if (isset($_POST['acao']) && $_POST['acao'] === 'upload_pagamento') {
        $inscricaoId = (int)$_POST['inscricao_id'];
        if (!empty($_FILES['comprovante_pagamento']['name'])) {
            $inscTemp = new Inscricao($db);
            $inscTemp->id = $inscricaoId;
            if ($inscTemp->salvarDocumentos($_FILES['comprovante_pagamento'], 'pagamento')) {
                // Verifica se já tem matrícula → muda status para 'documentos_anexados'
                $docs = $inscTemp->getDocumentos();
                $temMatricula = false;
                foreach ($docs as $doc) {
                    if ($doc['tipo'] === 'matricula') {
                        $temMatricula = true;
                        break;
                    }
                }
                if ($temMatricula) {
                    $inscTemp->atualizarSituacao('documentos_anexados');
                }
                $sucesso = "Comprovante de pagamento anexado.";
            } else {
                $erro = "Erro ao anexar comprovante de pagamento.";
            }
        } else {
            $erro = "Selecione um arquivo.";
        }
    }
}

// ================================
// AÇÕES: Confirmar pagamento ou marcar CIE
// ================================

if ($_GET) {
    if (isset($_GET['confirmar_pagamento'])) {
        $inscricao->id = (int)$_GET['confirmar_pagamento'];
        // Verifica se ambos os documentos existem
        $inscTemp = new Inscricao($db);
        $inscTemp->id = $inscricao->id;
        $docs = $inscTemp->getDocumentos();
        $temMatricula = false;
        $temPagamento = false;
        foreach ($docs as $doc) {
            if ($doc['tipo'] === 'matricula') $temMatricula = true;
            if ($doc['tipo'] === 'pagamento') $temPagamento = true;
        }
        if ($temMatricula && $temPagamento) {
            if ($inscricao->atualizarSituacao('pago')) {
                require_once __DIR__ . '/../app/models/Log.php';
                $log = new Log($db);
                $log->registrar(
                    $_SESSION['user_id'],
                    'confirmou_pagamento',
                    "Inscrição ID: {$inscricao->id}",
                    $inscricao->id,
                    'inscricoes'
                );
                $sucesso = "Pagamento confirmado.";
            } else {
                $erro = "Erro ao confirmar pagamento.";
            }
        } else {
            $erro = "É necessário anexar ambos os comprovantes (matrícula e pagamento) antes de confirmar.";
        }
    }

    if (isset($_GET['cie_emitida'])) {
        $inscricao->id = (int)$_GET['cie_emitida'];
        if ($inscricao->atualizarSituacao('cie_emitida')) {
            require_once __DIR__ . '/../app/models/Log.php';
            $log = new Log($db);
            $log->registrar(
                $_SESSION['user_id'],
                'marcou_cie_emitida',
                "Inscrição ID: {$inscricao->id}",
                $inscricao->id,
                'inscricoes'
            );
            $sucesso = "CIE marcada como emitida.";
        } else {
            $erro = "Erro ao marcar CIE como emitida.";
        }
    }
}

// ================================
// LISTAGEM DE INSCRIÇÕES
// ================================
$inscricoesList = $inscricao->listarComEstudantes();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Inscrições</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f9f9f9; }
        .container { max-width: 1200px; margin: 0 auto; }
        h2 { color: #333; }
        .mensagem { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .erro { background: #ffebee; color: #c62828; }
        .sucesso { background: #e8f5e9; color: #2e7d32; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f5f5f5; }
        .acoes { white-space: nowrap; }
        .status-pendente,
        .status-aguardando_validacao,
        .status-dados_aprovados,
        .status-pagamento_pendente { color: #f57c00; }
        .status-documentos_anexados { color: #5d4037; font-weight: bold; }
        .status-pago { color: #2e7d32; font-weight: bold; }
        .status-cie_emitida { color: #1565c0; }
        a { color: #1976d2; text-decoration: none; margin-right: 10px; }
        a:hover { text-decoration: underline; }
        .voltar { display: inline-block; margin-bottom: 20px; color: #555; }
        .doc-link { font-size: 0.9em; color: #555; }
        .doc-link:hover { color: #1976d2; }
        .upload-form { display: inline-block; margin-right: 10px; }
        button { padding: 4px 8px; font-size: 0.9em; }
        .required { color: #d32f2f; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="voltar">← Voltar ao Dashboard</a>
        <h2>Gerenciar Inscrições</h2>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>
        <?php if ($erro): ?>
            <div class="mensagem erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if (empty($inscricoesList)): ?>
            <p>Nenhuma inscrição registrada ainda.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Código Inscrição</th>
                        <th>Estudante</th>
                        <th>Matrícula</th>
                        <th>Curso</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th>Documentos</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inscricoesList as $insc): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($insc['codigo_inscricao']) ?></code></td>
                        <td><?= htmlspecialchars($insc['estudante_nome']) ?></td>
                        <td><?= htmlspecialchars($insc['estudante_matricula']) ?></td>
                        <td><?= htmlspecialchars($insc['estudante_curso']) ?></td>
                        <td><?= date('d/m/Y', strtotime($insc['criado_em'])) ?></td>
                        <td>
                            <?php 
                            $statusClass = 'status-' . $insc['situacao'];
                            echo '<span class="' . $statusClass . '">' . ucfirst(str_replace('_', ' ', $insc['situacao'])) . '</span>';
                            ?>
                        </td>
                        <td>
                            <?php
                            $inscTemp = new Inscricao($db);
                            $inscTemp->id = $insc['id'];
                            $docs = $inscTemp->getDocumentos();
                            if (empty($docs)) {
                                echo "—";
                            } else {
                                foreach ($docs as $doc) {
                                    echo '<div><a href="../' . htmlspecialchars($doc['caminho_arquivo']) . '" target="_blank" class="doc-link">'
                                        . htmlspecialchars($doc['descricao']) . '</a></div>';
                                }
                            }
                            ?>
                        </td>
                        <td class="acoes">
                            <!-- Anexar Comprovante de Matrícula -->
                            <?php if (in_array($insc['situacao'], ['pagamento_pendente', 'dados_aprovados'])): ?>
                                <form method="POST" enctype="multipart/form-data" class="upload-form" style="display:inline;">
                                    <input type="hidden" name="acao" value="upload_matricula">
                                    <input type="hidden" name="inscricao_id" value="<?= $insc['id'] ?>">
                                    <input type="file" name="comprovante_matricula" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="this.form.submit()" id="mat_<?= $insc['id'] ?>">
                                    <label for="mat_<?= $insc['id'] ?>" style="cursor:pointer; color:#1976d2;">Matrícula <span class="required">*</span></label>
                                </form>
                            <?php endif; ?>

                            <!-- Anexar Comprovante de Pagamento -->
                            <?php if (in_array($insc['situacao'], ['pagamento_pendente', 'dados_aprovados'])): ?>
                                <form method="POST" enctype="multipart/form-data" class="upload-form" style="display:inline;">
                                    <input type="hidden" name="acao" value="upload_pagamento">
                                    <input type="hidden" name="inscricao_id" value="<?= $insc['id'] ?>">
                                    <input type="file" name="comprovante_pagamento" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="this.form.submit()" id="pag_<?= $insc['id'] ?>">
                                    <label for="pag_<?= $insc['id'] ?>" style="cursor:pointer; color:#1976d2;">Pagamento <span class="required">*</span></label>
                                </form>
                            <?php endif; ?>

                            <!-- Confirmar Pagamento (só se tiver ambos os docs) -->
                            <?php if ($insc['situacao'] === 'documentos_anexados'): ?>
                                <a href="?confirmar_pagamento=<?= $insc['id'] ?>" onclick="return confirm('Confirmar pagamento?')">Confirmar Pagamento</a>
                            <?php endif; ?>

                            <!-- Marcar CIE Emitida -->
                            <?php if ($insc['situacao'] === 'pago'): ?>
                                <a href="?cie_emitida=<?= $insc['id'] ?>" onclick="return confirm('Marcar CIE como emitida?')">CIE Emitida</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>