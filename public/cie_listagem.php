<?php
session_start();

// Verificação de acesso
require_once __DIR__ . '/../app/controllers/AuthController.php';
$auth = new AuthController();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    die("Acesso negado.");
}

// Carrega dependências
require_once __DIR__ . '/../app/models/Carteirinha.php';

$database = new Database();
$db = $database->getConnection();
$carteirinha = new Carteirinha($db);

$erro = '';
$sucesso = '';

// ================================
// AÇÕES: Marcar como vencida ou cancelar
// ================================
if ($_GET) {
    if (isset($_GET['vencida'])) {
        $carteirinha->id = (int)$_GET['vencida'];
        if ($carteirinha->atualizarSituacao('vencida')) {
            // === LOG: CIE marcada como vencida ===
            require_once __DIR__ . '/../app/models/Log.php';
            $log = new Log($db);
            $log->registrar(
                $_SESSION['user_id'],
                'venceu_cie',
                "CIE ID: {$carteirinha->id}",
                $carteirinha->id,
                'carteirinhas'
            );
            $sucesso = "CIE marcada como vencida.";
        } else {
            $erro = "Erro ao atualizar CIE.";
        }
    } elseif (isset($_GET['cancelar'])) {
        $carteirinha->id = (int)$_GET['cancelar'];
        if ($carteirinha->atualizarSituacao('cancelada')) {
            // === LOG: CIE cancelada ===
            require_once __DIR__ . '/../app/models/Log.php';
            $log = new Log($db);
            $log->registrar(
                $_SESSION['user_id'],
                'cancelou_cie',
                "CIE ID: {$carteirinha->id}",
                $carteirinha->id,
                'carteirinhas'
            );
            $sucesso = "CIE cancelada.";
        } else {
            $erro = "Erro ao cancelar CIE.";
        }
    }
}

// ================================
// LISTAGEM DE CIEs
// ================================
$cieList = $carteirinha->listarComEstudantes();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Carteiras Estudantis (CIE) Emitidas</title>
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
        .status-ativa { color: #2e7d32; font-weight: bold; }
        .status-vencida { color: #f57c00; }
        .status-cancelada { color: #d32f2f; }
        a { color: #1976d2; text-decoration: none; margin-right: 10px; }
        a:hover { text-decoration: underline; }
        .voltar { display: inline-block; margin-bottom: 20px; color: #555; }
        .doc-link { font-size: 0.9em; color: #555; }
        .doc-link:hover { color: #1976d2; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="voltar">← Voltar ao Dashboard</a>
        <h2>Carteiras Estudantis (CIE) Emitidas</h2>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>
        <?php if ($erro): ?>
            <div class="mensagem erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if (empty($cieList)): ?>
            <p>Nenhuma CIE emitida ainda.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Código CIE</th>
                        <th>Estudante</th>
                        <th>Matrícula</th>
                        <th>Curso</th>
                        <th>Emissão</th>
                        <th>Validade</th>
                        <th>Situação</th>
                        <th>Documentos</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cieList as $cie): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($cie['cie_codigo']) ?></code></td>
                        <td><?= htmlspecialchars($cie['estudante_nome']) ?></td>
                        <td><?= htmlspecialchars($cie['estudante_matricula']) ?></td>
                        <td><?= htmlspecialchars($cie['estudante_curso']) ?></td>
                        <td><?= date('d/m/Y', strtotime($cie['criado_em'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($cie['data_validade'])) ?></td>
                        <td>
                            <?php 
                            $statusClass = '';
                            switch($cie['situacao']) {
                                case 'ativa': $statusClass = 'status-ativa'; break;
                                case 'vencida': $statusClass = 'status-vencida'; break;
                                case 'cancelada': $statusClass = 'status-cancelada'; break;
                            }
                            ?>
                            <span class="<?= $statusClass ?>"><?= ucfirst($cie['situacao']) ?></span>
                        </td>
                        <td>
                            <?php
                            // Conta documentos da CIE
                            $carteirinhaTemp = new Carteirinha($db);
                            $carteirinhaTemp->id = $cie['id'];
                            $docs = $carteirinhaTemp->getDocumentos();
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
                            <?php if ($cie['situacao'] === 'ativa'): ?>
                                <a href="?vencida=<?= $cie['id'] ?>" 
                                   onclick="return confirm('Marcar esta CIE como vencida?')">Vencida</a>
                                <a href="?cancelar=<?= $cie['id'] ?>" 
                                   onclick="return confirm('Cancelar esta CIE?')">Cancelar</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p style="margin-top: 30px;">
            <a href="emitir_cie.php" style="background: #2e7d32; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; display: inline-block;">
                Emitir Nova CIE
            </a>
        </p>
    </div>
</body>
</html>