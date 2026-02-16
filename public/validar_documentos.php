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
require_once __DIR__ . '/../app/models/Estudante.php';
require_once __DIR__ . '/../app/models/Log.php';

$database = new Database();
$db = $database->getConnection();
$inscricaoModel = new Inscricao($db);
$estudanteModel = new Estudante($db);

$erro = '';
$sucesso = '';
$inscricaoId = null;
$estudante = null;

// ================================
// RECEBIMENTO DO ID DA INSCRIÇÃO
// ================================
if (isset($_GET['id'])) {
    $inscricaoId = (int)$_GET['id'];
} else {
    $erro = "ID da inscrição não fornecido.";
}

// ================================
// AÇÃO: Validar ou Solicitar Reenvio
// ================================
if ($_POST && $inscricaoId) {
    $documentosParaReenvio = $_POST['reenviar'] ?? [];
    $observacaoGeral = trim($_POST['observacao_geral'] ?? '');

    // Buscar a inscrição
    $inscricao = $inscricaoModel->buscarPorId($inscricaoId);
    if (!$inscricao) {
        $erro = "Inscrição não encontrada.";
    } else {
        $estudanteId = $inscricao['estudante_id'];

        // Carregar dados do estudante
        $estudante = $estudanteModel->buscarPorId($estudanteId);
        $fotoEstudante = $estudante['foto'] ?? null;

        // Obter documentos da inscrição
        $docsInscricao = $inscricaoModel->getDocumentos();

        // Obter documentos do estudante (consulta manual)
        $queryEstudanteDocs = "SELECT * FROM documentos_anexados WHERE entidade_tipo = 'estudante' AND entidade_id = :estudante_id ORDER BY tipo, criado_em";
        $stmtEstudanteDocs = $db->prepare($queryEstudanteDocs);
        $stmtEstudanteDocs->bindParam(':estudante_id', $estudanteId, PDO::PARAM_INT);
        $stmtEstudanteDocs->execute();
        $docsEstudante = $stmtEstudanteDocs->fetchAll(PDO::FETCH_ASSOC);

        // --- NOVA LÓGICA: Criar lista de todos os documentos ---
        $todosDocumentos = array_merge($docsInscricao, $docsEstudante);

        // Adicionar Foto como item especial (apenas para visualização)
        if ($fotoEstudante) {
            $todosDocumentos[] = [
                'id' => 'foto_' . $estudanteId,
                'entidade_tipo' => 'estudante',
                'entidade_id' => $estudanteId,
                'tipo' => 'foto_3x4',
                'caminho_arquivo' => $fotoEstudante,
                'descricao' => 'Foto 3x4',
                'validado' => 'n/a',
                'observacoes_validacao' => null
            ];
        }
        // --- FIM NOVA LÓGICA ---

        if (empty($todosDocumentos)) {
            $erro = "Nenhum documento encontrado para esta inscrição.";
        } else {
            $logMensagens = [];

            // Processar cada documento
            foreach ($todosDocumentos as $doc) {
                $docId = $doc['id'];
                $tipo = $doc['tipo'];
                $descricao = $doc['descricao'];

                // Verifica se o documento (ou ID especial da foto) está na lista de reenvio
                $deveReenviar = in_array($docId, $documentosParaReenvio);

                if ($deveReenviar) {
                    // Se for a foto, apenas registra a ação (não atualiza banco)
                    if ($tipo === 'foto_3x4') {
                        $sucesso .= "Foto 3x4 marcada para reenvio (verifique manualmente).<br>";
                    } else {
                        // Documento anexado comum
                        $novoStatus = 'invalido';
                        $observacao = $observacaoGeral ?: "Reenvio solicitado.";
                        $sucesso .= "Documento '{$descricao}' ({$tipo}) marcado para reenvio.<br>";

                        // Atualizar o status e observação no banco
                        $queryUpdate = "UPDATE documentos_anexados SET validado = :validado, observacoes_validacao = :observacao WHERE id = :id";
                        $stmtUpdate = $db->prepare($queryUpdate);
                        $stmtUpdate->bindParam(':validado', $novoStatus);
                        $stmtUpdate->bindParam(':observacao', $observacao);
                        $stmtUpdate->bindParam(':id', $docId, PDO::PARAM_INT);
                        $stmtUpdate->execute();
                    }
                } else {
                    // Se não for marcado para reenvio, valida (exceto para foto)
                    if ($tipo !== 'foto_3x4') {
                        $novoStatus = 'validado';
                        $observacao = $observacaoGeral ?: "Validado pelo administrador.";
                        $sucesso .= "Documento '{$descricao}' ({$tipo}) validado.<br>";

                        // Atualizar o status e observação no banco
                        $queryUpdate = "UPDATE documentos_anexados SET validado = :validado, observacoes_validacao = :observacao WHERE id = :id";
                        $stmtUpdate = $db->prepare($queryUpdate);
                        $stmtUpdate->bindParam(':validado', $novoStatus);
                        $stmtUpdate->bindParam(':observacao', $observacao);
                        $stmtUpdate->bindParam(':id', $docId, PDO::PARAM_INT);
                        $stmtUpdate->execute();
                    }
                }
            }

            // Verificar se nenhum documento *comum* foi marcado para reenvio
            $nenhumDocumentoComumMarcado = true;
            foreach ($todosDocumentos as $doc) {
                if ($doc['tipo'] !== 'foto_3x4' && in_array($doc['id'], $documentosParaReenvio)) {
                    $nenhumDocumentoComumMarcado = false;
                    break;
                }
            }

            // Se nenhum documento comum foi marcado, atualizar o status da inscrição
            if ($nenhumDocumentoComumMarcado) {
                if ($inscricaoModel->atualizarMatriculaValidada(true)) {
                    $sucesso .= "Status da inscrição atualizado para 'documentos validados'.";
                    // Registrar log
                    $log = new Log($db);
                    $log->registrar(
                        $_SESSION['user_id'],
                        'validacao_documentos_concluida',
                        "Inscrição ID: {$inscricaoId}, Todos os documentos (exceto foto) validados.",
                        $inscricaoId,
                        'inscricoes'
                    );
                } else {
                    $erro .= "Erro ao atualizar o status geral da inscrição.";
                }
            } else {
                $sucesso .= "Solicitação de reenvio processada.";
                // Registrar log
                $log = new Log($db);
                $log->registrar(
                    $_SESSION['user_id'],
                    'solicitou_reenvio_documentos',
                    "Inscrição ID: {$inscricaoId}, Documentos para reenvio: " . implode(', ', $documentosParaReenvio),
                    $inscricaoId,
                    'inscricoes'
                );
            }
        }
    }
}

// ================================
// CARREGAR DADOS PARA EXIBIÇÃO
// ================================
$documentos = [];
$fotoEstudante = null;

if ($inscricaoId) {
    $inscricao = $inscricaoModel->buscarPorId($inscricaoId);
    if ($inscricao) {
        $estudanteId = $inscricao['estudante_id'];

        // Carregar dados do estudante
        $estudante = $estudanteModel->buscarPorId($estudanteId);
        $fotoEstudante = $estudante['foto'] ?? null;

        // Obter documentos da inscrição
        $docsInscricao = $inscricaoModel->getDocumentos();

        // Obter documentos do estudante
        $queryEstudanteDocs = "SELECT * FROM documentos_anexados WHERE entidade_tipo = 'estudante' AND entidade_id = :estudante_id ORDER BY tipo, criado_em";
        $stmtEstudanteDocs = $db->prepare($queryEstudanteDocs);
        $stmtEstudanteDocs->bindParam(':estudante_id', $estudanteId, PDO::PARAM_INT);
        $stmtEstudanteDocs->execute();
        $docsEstudante = $stmtEstudanteDocs->fetchAll(PDO::FETCH_ASSOC);

        // Combina documentos
        $documentos = array_merge($docsInscricao, $docsEstudante);

        // Adiciona Foto como item especial
        if ($fotoEstudante) {
            $documentos[] = [
                'id' => 'foto_' . $estudanteId,
                'entidade_tipo' => 'estudante',
                'entidade_id' => $estudanteId,
                'tipo' => 'foto_3x4',
                'caminho_arquivo' => $fotoEstudante,
                'descricao' => 'Foto 3x4',
                'validado' => 'n/a',
                'observacoes_validacao' => null
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Validar Documentos - Inscrição #<?= htmlspecialchars($inscricaoId) ?></title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f9f9f9; }
        .container { max-width: 1000px; margin: 0 auto; }
        h2 { color: #333; }
        .mensagem { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .erro { background: #ffebee; color: #c62828; }
        .sucesso { background: #e8f5e9; color: #2e7d32; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f5f5f5; }
        .acoes { white-space: nowrap; }
        a { color: #1976d2; text-decoration: none; margin-right: 10px; }
        a:hover { text-decoration: underline; }
        .voltar { display: inline-block; margin-bottom: 20px; color: #555; }
        .doc-link { font-size: 0.9em; color: #555; }
        .doc-link:hover { color: #1976d2; }
        .checkbox-container { text-align: center; }
        .observacao { margin-top: 10px; }
        button { padding: 8px 16px; font-size: 0.9em; }
        .btn-validar { background-color: #2e7d32; color: white; border: none; cursor: pointer; }
        .btn-validar:hover { background-color: #255a20; }
        .btn-cancelar { background-color: #555; color: white; border: none; cursor: pointer; }
        .btn-cancelar:hover { background-color: #333; }
        .status-n_a { color: #555; font-style: italic; }
    </style>
</head>
<body>
    <div class="container">
        <a href="gerenciar_inscricoes.php" class="voltar">← Voltar à Lista de Inscrições</a>
        <h2>Validar Documentos da Inscrição #<?= htmlspecialchars($inscricaoId) ?></h2>
        <?php if ($estudante): ?>
            <p><strong>Estudante:</strong> <?= htmlspecialchars($estudante['nome']) ?></p>
            <p><strong>Matrícula:</strong> <?= htmlspecialchars($estudante['matricula']) ?></p>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><?= nl2br(htmlspecialchars($sucesso)) ?></div>
        <?php endif; ?>
        <?php if ($erro): ?>
            <div class="mensagem erro"><?= nl2br(htmlspecialchars($erro)) ?></div>
        <?php endif; ?>

        <?php if (!empty($documentos)): ?>
            <form method="POST">
                <table>
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Visualizar</th>
                            <th>Solicitar Reenvio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documentos as $doc): ?>
                        <tr>
                            <td><?= htmlspecialchars($doc['descricao']) ?></td>
                            <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['tipo']))) ?></td>
                            <td>
                                <span class="status-<?= $doc['validado'] ?? 'n_a' ?>">
                                    <?= ucfirst($doc['validado'] ?? 'n/a') ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($doc['tipo'] === 'foto_3x4'): ?>
                                    <a href="../public/<?= htmlspecialchars($doc['caminho_arquivo']) ?>" target="_blank" class="doc-link">Ver</a>
                                <?php else: ?>
                                    <a href="../public/<?= htmlspecialchars($doc['caminho_arquivo']) ?>" target="_blank" class="doc-link">Ver</a>
                                <?php endif; ?>
                            </td>
                            <td class="checkbox-container">
                                <input type="checkbox" name="reenviar[]" value="<?= htmlspecialchars($doc['id']) ?>" id="reenviar_<?= htmlspecialchars($doc['id']) ?>">
                                <label for="reenviar_<?= htmlspecialchars($doc['id']) ?>">Reenvio</label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="observacao">
                    <label for="observacao_geral">Observação Geral (opcional):</label><br>
                    <textarea id="observacao_geral" name="observacao_geral" rows="3" cols="50"></textarea>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn-validar">Finalizar Validação</button>
                    <button type="button" onclick="window.location.href='gerenciar_inscricoes.php'" class="btn-cancelar" style="margin-left: 10px;">Cancelar</button>
                </div>
            </form>
        <?php else: ?>
            <p>Nenhum documento encontrado para esta inscrição.</p>
            <a href="gerenciar_inscricoes.php">← Voltar</a>
        <?php endif; ?>
    </div>
</body>
</html>