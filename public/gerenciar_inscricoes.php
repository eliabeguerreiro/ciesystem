<?php
session_start();

// Verificação de acesso
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/models/Inscricao.php';
require_once __DIR__ . '/../app/models/Log.php'; // Adicionado para logs

$auth = new AuthController();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    die("Acesso negado.");
}

// Carrega dependências
$database = new Database();
$db = $database->getConnection();
$inscricao = new Inscricao($db);

$erro = '';
$sucesso = '';

// ================================
// AÇÕES: Upload de documentos (PAGAMENTO SOMENTE)
// ================================

if ($_POST) {
    // --- REMOVIDO: Bloco para upload_matricula ---
    /*
    if (isset($_POST['acao']) && $_POST['acao'] === 'upload_matricula') {
        $inscricaoId = (int)$_POST['inscricao_id'];
        if (!empty($_FILES['comprovante_matricula']['name'])) {
            $inscTemp = new Inscricao($db);
            $inscTemp->id = $inscricaoId;
            if ($inscTemp->salvarDocumentos($_FILES['comprovante_matricula'], 'matricula')) {
                // Após anexar a matrícula, verificamos se o estudante tem status_validacao = 'dados_aprovados'
                // Se sim, validamos automaticamente a matrícula.
                $inscTemp->id = $inscricaoId;
                $inscDados = $inscTemp->buscarPorId($inscricaoId);
                if ($inscDados) {
                    $estudanteId = $inscDados['estudante_id'];
                    $queryEstudante = "SELECT status_validacao FROM estudantes WHERE id = :estudante_id";
                    $stmtEstudante = $db->prepare($queryEstudante);
                    $stmtEstudante->bindParam(':estudante_id', $estudanteId, PDO::PARAM_INT);
                    $stmtEstudante->execute();
                    $dadosEstudante = $stmtEstudante->fetch(PDO::FETCH_ASSOC);

                    if ($dadosEstudante && $dadosEstudante['status_validacao'] === 'dados_aprovados') {
                        $inscTemp->atualizarMatriculaValidada(true); // Valida automaticamente
                        
                        // === LOG: Matrícula anexada e validada automaticamente ===
                        $log = new Log($db);
                        $log->registrar(
                            $_SESSION['user_id'],
                            'anexou_e_validou_comprovante_matricula',
                            "Inscrição ID: {$inscricaoId}, Estudante ID: {$estudanteId}",
                            $inscricaoId,
                            'inscricoes'
                        );
                        
                        $sucesso = "Comprovante de matrícula anexado e validado automaticamente.";
                    } else {
                        // === LOG: Matrícula anexada (aguardando validação) ===
                        $log = new Log($db);
                        $log->registrar(
                            $_SESSION['user_id'],
                            'anexou_comprovante_matricula',
                            "Inscrição ID: {$inscricaoId}, Estudante ID: {$estudanteId}",
                            $inscricaoId,
                            'inscricoes'
                        );
                        
                        $sucesso = "Comprovante de matrícula anexado. Validação pendente.";
                    }
                } else {
                    // === LOG: Matrícula anexada (falha na busca do estudante) ===
                    $log = new Log($db);
                    $log->registrar(
                        $_SESSION['user_id'],
                        'anexou_comprovante_matricula',
                        "Inscrição ID: {$inscricaoId} (Erro: dados do estudante não encontrados)",
                        $inscricaoId,
                        'inscricoes'
                    );
                    
                    $sucesso = "Comprovante de matrícula anexado.";
                }
            } else {
                $erro = "Erro ao anexar comprovante de matrícula.";
            }
        } else {
            $erro = "Selecione um arquivo.";
        }
    }
    */

    if (isset($_POST['acao']) && $_POST['acao'] === 'upload_pagamento') {
        $inscricaoId = (int)$_POST['inscricao_id'];
        if (!empty($_FILES['comprovante_pagamento']['name'])) {
            $inscTemp = new Inscricao($db);
            $inscTemp->id = $inscricaoId;
            if ($inscTemp->salvarDocumentos($_FILES['comprovante_pagamento'], 'pagamento')) {
                // ✅ Ao anexar o comprovante de pagamento, marcamos como confirmado automaticamente.
                $inscTemp->atualizarPagamentoConfirmado(true);
                
                // === LOG: Pagamento anexado e confirmado automaticamente ===
                $log = new Log($db);
                $log->registrar(
                    $_SESSION['user_id'],
                    'anexou_e_confirmou_comprovante_pagamento',
                    "Inscrição ID: {$inscricaoId}",
                    $inscricaoId,
                    'inscricoes'
                );
                
                $sucesso = "Comprovante de pagamento anexado e confirmado.";
            } else {
                $erro = "Erro ao anexar comprovante de pagamento.";
            }
        } else {
            $erro = "Selecione um arquivo.";
        }
    }
}

// ================================
// AÇÕES: Marcar CIE e Validar Matrícula Manualmente
// ================================

if ($_GET) {
    if (isset($_GET['cie_emitida'])) {
        $inscricao->id = (int)$_GET['cie_emitida'];
        // Verifica se pagamento está confirmado E matrícula está validada
        $inscTemp = $inscricao->buscarPorId($inscricao->id);
        if ($inscTemp && $inscTemp['pagamento_confirmado'] == 1 && $inscTemp['matricula_validada'] == 1) {
            if ($inscricao->atualizarSituacao('cie_emitida_aguardando_entrega')) { // <- MUDANÇA: Novo status
                require_once __DIR__ . '/../app/models/Log.php';
                $log = new Log($db);
                $log->registrar(
                    $_SESSION['user_id'],
                    'cie_pronta_para_entrega', // <- MUDANÇA: Nova ação de log
                    "Inscrição ID: {$inscricao->id}",
                    $inscricao->id,
                    'inscricoes'
                );
                $sucesso = "CIE marcada como pronta para entrega.";
            } else {
                $erro = "Erro ao marcar CIE como pronta para entrega.";
            }
        } else {
            $erro = "Erro: Para prosseguir para logística, o pagamento deve estar confirmado e a matrícula validada.";
        }
    }

    // --- MANTIDO: AÇÃO DE VALIDAR MATRÍCULA MANUALMENTE ---
    if (isset($_GET['validar_matricula_manualmente'])) {
        $inscricaoId = (int)$_GET['validar_matricula_manualmente'];
        $pagina = (int)($_GET['pagina'] ?? 1);
        $filtroSituacao = $_GET['filtro_situacao'] ?? '';
        $filtroStatusValidacao = $_GET['filtro_status_validacao'] ?? '';

        $inscTemp = new Inscricao($db);
        $inscTemp->id = $inscricaoId;
        $dadosInscricao = $inscTemp->buscarPorId($inscricaoId);

        if ($dadosInscricao) {
            $estudanteId = $dadosInscricao['estudante_id'];

            // Verificar se o comprovante de matrícula foi anexado
            // --- ATUALIZADO: Agora busca da nova tabela documentos_anexados ---
            $docs = $inscTemp->getDocumentos(); // O modelo Inscricao.php atualizado já faz isso
            $temMatricula = false;
            foreach ($docs as $doc) {
                if ($doc['tipo'] === 'matricula') { // Procura por 'matricula' na nova estrutura
                    $temMatricula = true;
                    break;
                }
            }
            // --- FIM ATUALIZADO ---

            if ($temMatricula && !$dadosInscricao['matricula_validada']) { // Só prosseguir se anexado e ainda não validado
                if ($inscTemp->atualizarMatriculaValidada(true)) {
                    require_once __DIR__ . '/../app/models/Log.php';
                    $log = new Log($db);
                    $log->registrar(
                        $_SESSION['user_id'],
                        'admin_validou_matricula_manualmente', // Nova ação de log
                        "Inscrição ID: {$inscricaoId}, Estudante ID: {$estudanteId}",
                        $inscricaoId,
                        'inscricoes'
                    );
                    $sucesso = "Matrícula validada manualmente com sucesso.";
                } else {
                    $erro = "Erro ao validar matrícula manualmente.";
                }
            } else {
                $erro = "Erro: Comprovante de matrícula não encontrado ou já validado.";
            }
        } else {
            $erro = "Erro: Inscrição não encontrada.";
        }

        // Redirecionar para manter os filtros e a página atual
        header("Location: ?pagina={$pagina}&filtro_situacao=" . urlencode($filtroSituacao) . "&filtro_status_validacao=" . urlencode($filtroStatusValidacao));
        exit; // Importante sair após o redirect
    }
    // --- FIM MANTIDO ---
}

// ================================
// FILTRAGEM E PAGINAÇÃO
// ================================
$filtroSituacao = $_GET['filtro_situacao'] ?? '';
$filtroStatusValidacao = $_GET['filtro_status_validacao'] ?? '';
$pagina = (int)($_GET['pagina'] ?? 1);
$registrosPorPagina = 10;
$offset = ($pagina - 1) * $registrosPorPagina;

// Contar total de registros para paginação
$totalQuery = "
    SELECT COUNT(*) as total
    FROM inscricoes i
    INNER JOIN estudantes e ON i.estudante_id = e.id
";
$totalParams = [];
if ($filtroSituacao) {
    $totalQuery .= " WHERE i.situacao = :filtro_situacao ";
    $totalParams[':filtro_situacao'] = $filtroSituacao;
}
if ($filtroStatusValidacao) {
    $totalQuery .= (strpos($totalQuery, 'WHERE') === false ? " WHERE " : " AND ") . " e.status_validacao = :filtro_status_validacao ";
    $totalParams[':filtro_status_validacao'] = $filtroStatusValidacao;
}
$totalStmt = $db->prepare($totalQuery);
$totalStmt->execute($totalParams);
$totalRegistros = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Aplicar filtros e paginação na listagem
$inscricoesList = $inscricao->listarComEstudantesFiltrada($filtroSituacao, $filtroStatusValidacao, $offset, $registrosPorPagina);

// Obter todos os status possíveis para o filtro (opcional, para o frontend)
// --- MUDANÇA AQUI ---
$possiveisSituacoes = ['aguardando_validacao', 'dados_aprovados', 'pagamento_pendente', 'documentos_anexados', 'pago', 'cie_emitida_aguardando_entrega', 'cie_entregue_na_instituicao'];
// --- FIM MUDANÇA ---
$possiveisStatusValidacao = ['pendente', 'dados_aprovados'];
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
        /* --- NOVOS ESTILOS ADICIONADOS --- */
        .status-cie_emitida_aguardando_entrega { color: #5d4037; font-weight: bold; } /* Marrom */
        .status-cie_entregue_na_instituicao { color: #2e7d32; font-weight: bold; } /* Verde */
        /* --- FIM NOVOS ESTILOS --- */
        a { color: #1976d2; text-decoration: none; margin-right: 10px; }
        a:hover { text-decoration: underline; }
        .voltar { display: inline-block; margin-bottom: 20px; color: #555; }
        .doc-link { font-size: 0.9em; color: #555; }
        .doc-link:hover { color: #1976d2; }
        .upload-form { display: inline-block; margin-right: 10px; }
        button { padding: 4px 8px; font-size: 0.9em; }
        .required { color: #d32f2f; }
        .filtro-container { margin-bottom: 20px; }
        .filtro-container label { display: inline-block; margin-right: 10px; }
        .filtro-container input, .filtro-container select { margin-right: 10px; padding: 4px; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a, .pagination span { display: inline-block; padding: 8px 16px; text-decoration: none; border: 1px solid #ddd; margin: 0 4px; }
        .pagination a:hover { background-color: #f0f0f0; }
        .pagination .current { background-color: #1976d2; color: white; }
        /* Estilo para status booleanos */
        .status-boolean { font-weight: bold; }
        .status-true { color: #2e7d32; } /* Verde para TRUE */
        .status-false { color: #c62828; } /* Vermelho para FALSE */
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

        <!-- Formulário de Filtragem -->
        <div class="filtro-container">
            <form method="GET">
                <input type="hidden" name="pagina" value="1"> <!-- Reiniciar para página 1 ao filtrar -->
                <label for="filtro_situacao">Filtrar por Situação da Inscrição:</label>
                <select name="filtro_situacao" id="filtro_situacao">
                    <option value="">Todas</option>
                    <?php foreach ($possiveisSituacoes as $sit): ?>
                        <option value="<?= $sit ?>" <?= $filtroSituacao === $sit ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_', ' ', $sit)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="filtro_status_validacao">Filtrar por Status de Validação do Estudante:</label>
                <select name="filtro_status_validacao" id="filtro_status_validacao">
                    <option value="">Todos</option>
                    <?php foreach ($possiveisStatusValidacao as $stat): ?>
                        <option value="<?= $stat ?>" <?= $filtroStatusValidacao === $stat ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_', ' ', $stat)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Aplicar Filtros</button>
                <?php if ($filtroSituacao || $filtroStatusValidacao): ?>
                    <a href="?">Limpar Filtros</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($inscricoesList)): ?>
            <p>Nenhuma inscrição encontrada com os critérios atuais.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Código Inscrição</th>
                        <th>Estudante</th>
                        <th>Matrícula</th>
                        <th>Curso</th>
                        <th>Data</th>
                        <th>Status Inscrição</th>
                        <th>Status Validação</th>
                        <th>Matrícula Anexada</th>
                        <th>Pagamento Anexado</th>
                        <th>Documentos Validados</th> <!-- Renomeada coluna -->
                        <th>Pagamento Confirmado</th>
                        <th>Documentos</th>
                        <!-- REMOVIDO: <th>Anexar Matrícula</th> -->
                        <th>Anexar Pagamento</th>
                        <th>CIE Emitida</th>
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
                            $statusValidacao = $insc['estudante_status_validacao'] ?? 'Desconhecido';
                            echo '<span>' . ucfirst(str_replace('_', ' ', $statusValidacao)) . '</span>';
                            ?>
                        </td>
                        <!-- Colunas booleanas -->
                        <td>
                            <?php
                            $inscTemp = new Inscricao($db);
                            $inscTemp->id = $insc['id'];
                            // --- ATUALIZADO: Agora busca da nova tabela documentos_anexados ---
                            $docs = $inscTemp->getDocumentos(); // O modelo Inscricao.php atualizado já faz isso
                            $temMatricula = false;
                            foreach ($docs as $doc) {
                                // Procura por 'matricula' ou 'matricula_frente', 'matricula_verso' se for dividido
                                // Assumindo que o tipo é 'matricula' para o comprovante principal
                                if ($doc['tipo'] === 'matricula') { $temMatricula = true; break; }
                            }
                            // --- FIM ATUALIZADO ---
                            $statusClass = 'status-boolean ' . ($temMatricula ? 'status-true' : 'status-false');
                            echo '<span class="' . $statusClass . '">' . ($temMatricula ? 'Sim' : 'Não') . '</span>';
                            ?>
                        </td>
                        <td>
                            <?php
                            $temPagamento = false;
                            // --- ATUALIZADO: Agora busca da nova tabela documentos_anexados ---
                            foreach ($docs as $doc) { // $docs já foi buscado acima
                                if ($doc['tipo'] === 'pagamento') { $temPagamento = true; break; }
                            }
                            // --- FIM ATUALIZADO ---
                            $statusClass = 'status-boolean ' . ($temPagamento ? 'status-true' : 'status-false');
                            echo '<span class="' . $statusClass . '">' . ($temPagamento ? 'Sim' : 'Não') . '</span>';
                            ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = 'status-boolean ' . ($insc['matricula_validada'] ? 'status-true' : 'status-false');
                            // A coluna 'Documentos Validados' agora reflete o campo 'matricula_validada'
                            // Este campo será usado para indicar se *todos* os documentos relevantes para a inscrição (matrícula, identidade, etc.) foram validados.
                            // Por enquanto, ele reflete apenas a validação da matrícula, como era antes.
                            echo '<span class="' . $statusClass . '">' . ($insc['matricula_validada'] ? 'Sim' : 'Não') . '</span>';
                            ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = 'status-boolean ' . ($insc['pagamento_confirmado'] ? 'status-true' : 'status-false');
                            echo '<span class="' . $statusClass . '">' . ($insc['pagamento_confirmado'] ? 'Sim' : 'Não') . '</span>';
                            ?>
                        </td>
                        <td>
                            <?php
                            if (empty($docs)) {
                                echo "—";
                            } else {
                                foreach ($docs as $doc) {
                                    // --- ATUALIZADO: Tipos de documentos podem vir da nova estrutura ---
                                    switch ($doc['tipo'] ?? '') {
                                        case 'matricula':
                                            $nomeAmigavel = 'Comprovante de Matrícula';
                                            break;
                                        case 'pagamento':
                                            $nomeAmigavel = 'Comprovante de Pagamento';
                                            break;
                                        // Exemplos de novos tipos que podem aparecer
                                        case 'rg_frente':
                                            $nomeAmigavel = 'RG (Frente)';
                                            break;
                                        case 'rg_verso':
                                            $nomeAmigavel = 'RG (Verso)';
                                            break;
                                        case 'cpf_frente':
                                            $nomeAmigavel = 'CPF (Frente)';
                                            break;
                                        case 'cpf_verso':
                                            $nomeAmigavel = 'CPF (Verso)';
                                            break;
                                        // ... outros tipos
                                        default:
                                            $nomeAmigavel = 'Documento (' . htmlspecialchars($doc['tipo']) . ')';
                                    }
                                    // --- FIM ATUALIZADO ---
                                    echo '<div><a href="' . htmlspecialchars($doc['caminho_arquivo']) . '" target="_blank" class="doc-link">'
                                        . $nomeAmigavel . '</a></div>';
                                }
                            }
                            ?>
                        </td>
                        <!-- REMOVIDO: Célula da coluna "Anexar Matrícula" -->
                        <td class="acoes">
                            <?php if (in_array($insc['situacao'], ['aguardando_validacao', 'pagamento_pendente', 'dados_aprovados']) && !$temPagamento): ?>
                                <form method="POST" enctype="multipart/form-data" class="upload-form" style="display:inline;">
                                    <input type="hidden" name="acao" value="upload_pagamento">
                                    <input type="hidden" name="inscricao_id" value="<?= $insc['id'] ?>">
                                    <input type="file" name="comprovante_pagamento" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="this.form.submit()" id="pag_<?= $insc['id'] ?>">
                                    <label for="pag_<?= $insc['id'] ?>" style="cursor:pointer; color:#1976d2;">📎</label>
                                </form>
                            <?php elseif ($temPagamento): ?>
                                <span title="Já anexado">✅</span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="acoes">
                            <?php if ($insc['situacao'] === 'cie_emitida_aguardando_entrega'): ?>
                                <a href="logistica_entregas.php?inscricao_id=<?= $insc['id'] ?>" title="Gerenciar logística de entrega">
                                    🚚
                                </a>
                                <span title="Pronta para entrega">📦 Aguardando Entrega</span>
                            <?php elseif ($insc['situacao'] === 'cie_entregue_na_instituicao'): ?>
                                <span title="CIE entregue na instituição">✅ Entregue</span>
                            <?php elseif ($insc['estudante_status_validacao'] === 'dados_aprovados' && $insc['pagamento_confirmado'] && $insc['matricula_validada']): ?>
                                <a href="?cie_emitida=<?= $insc['id'] ?>&pagina=<?= $pagina ?>&filtro_situacao=<?= urlencode($filtroSituacao) ?>&filtro_status_validacao=<?= urlencode($filtroStatusValidacao) ?>" onclick="return confirm('Marcar CIE como pronta para entrega?')" title="Registrar saída para logística">
                                    📦
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Paginação -->
            <div class="pagination">
                <?php if ($totalPaginas > 1): ?>
                    <?php if ($pagina > 1): ?>
                        <a href="?pagina=<?= $pagina - 1 ?>&filtro_situacao=<?= urlencode($filtroSituacao) ?>&filtro_status_validacao=<?= urlencode($filtroStatusValidacao) ?>">← Anterior</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <?php if ($i == $pagina): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?pagina=<?= $i ?>&filtro_situacao=<?= urlencode($filtroSituacao) ?>&filtro_status_validacao=<?= urlencode($filtroStatusValidacao) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($pagina < $totalPaginas): ?>
                        <a href="?pagina=<?= $pagina + 1 ?>&filtro_situacao=<?= urlencode($filtroSituacao) ?>&filtro_status_validacao=<?= urlencode($filtroStatusValidacao) ?>">Próxima →</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>