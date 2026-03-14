<?php
session_start();

// Verificação de acesso
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/models/Inscricao.php';
require_once __DIR__ . '/../app/models/Log.php';

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
    if (isset($_POST['acao']) && $_POST['acao'] === 'upload_pagamento') {
        $inscricaoId = (int)$_POST['inscricao_id'];
        if (!empty($_FILES['comprovante_pagamento']['name'])) {
            $inscTemp = new Inscricao($db);
            $inscTemp->id = $inscricaoId;
            if ($inscTemp->salvarDocumentos($_FILES['comprovante_pagamento'], 'pagamento')) {
                $inscTemp->atualizarPagamentoConfirmado(true);
                
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
        $inscTemp = $inscricao->buscarPorId($inscricao->id);
        if ($inscTemp && $inscTemp['pagamento_confirmado'] == 1 && $inscTemp['matricula_validada'] == 1) {
            if ($inscricao->atualizarSituacao('cie_emitida_aguardando_entrega')) {
                require_once __DIR__ . '/../app/models/Log.php';
                $log = new Log($db);
                $log->registrar(
                    $_SESSION['user_id'],
                    'cie_pronta_para_entrega',
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
            $docs = $inscTemp->getDocumentos();
            $temMatricula = false;
            foreach ($docs as $doc) {
                if ($doc['tipo'] === 'matricula') { $temMatricula = true; break; }
            }

            if ($temMatricula && !$dadosInscricao['matricula_validada']) {
                if ($inscTemp->atualizarMatriculaValidada(true)) {
                    require_once __DIR__ . '/../app/models/Log.php';
                    $log = new Log($db);
                    $log->registrar(
                        $_SESSION['user_id'],
                        'admin_validou_matricula_manualmente',
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

        header("Location: ?pagina={$pagina}&filtro_situacao=" . urlencode($filtroSituacao) . "&filtro_status_validacao=" . urlencode($filtroStatusValidacao));
        exit;
    }
}

// ================================
// FILTRAGEM E PAGINAÇÃO
// ================================
$filtroSituacao = $_GET['filtro_situacao'] ?? '';
$filtroStatusValidacao = $_GET['filtro_status_validacao'] ?? '';
$pagina = (int)($_GET['pagina'] ?? 1);
$registrosPorPagina = 10;
$offset = ($pagina - 1) * $registrosPorPagina;

// Contar total de registros
$totalQuery = "SELECT COUNT(*) as total FROM inscricoes i INNER JOIN estudantes e ON i.estudante_id = e.id";
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

// Aplicar filtros e paginação
$inscricoesList = $inscricao->listarComEstudantesFiltrada($filtroSituacao, $filtroStatusValidacao, $offset, $registrosPorPagina);

// Obter todos os status possíveis para o filtro
$possiveisSituacoes = ['aguardando_validacao', 'dados_aprovados', 'pagamento_pendente', 'documentos_anexados', 'pago', 'cie_emitida_aguardando_entrega', 'cie_entregue_na_instituicao'];
$possiveisStatusValidacao = ['pendente', 'dados_aprovados'];

// --- NOVO: Contadores para Dashboard Rápido ---
// (Opcional: Pode ser removido se quiser apenas a tabela, mas ajuda muito na gestão)
$countQuery = "SELECT situacao, COUNT(*) as qtd FROM inscricoes GROUP BY situacao";
$countStmt = $db->query($countQuery);
$contagens = [];
while($row = $countStmt->fetch()) { $contagens[$row['situacao']] = $row['qtd']; }
$pipelineTotal = array_sum($contagens);
$prontasEntrega = $contagens['cie_emitida_aguardando_entrega'] ?? 0;
$pendentesValidacao = ($contagens['aguardando_validacao'] ?? 0) + ($contagens['dados_aprovados'] ?? 0);
// --- FIM CONTADORES ---
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Inscrições - CIE</title>
    <!-- Fonte Google -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1976d2;
            --primary-dark: #1565c0;
            --success-color: #2e7d32;
            --warning-color: #f57c00;
            --error-color: #c62828;
            --info-color: #0288d1;
            --bg-color: #f4f6f8;
            --card-bg: #ffffff;
            --text-color: #333;
            --light-text: #666;
            --border-color: #e0e0e0;
            --shadow: 0 2px 4px rgba(0,0,0,0.05);
            --radius: 8px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 20px;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header & Stats */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-section h2 {
            font-size: 1.8rem;
            color: var(--text-color);
            font-weight: 700;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            color: var(--light-text);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }
        .btn-back:hover { color: var(--primary-color); transform: translateY(-2px); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-color);
            display: flex;
            flex-direction: column;
        }
        .stat-card.warning { border-left-color: var(--warning-color); }
        .stat-card.success { border-left-color: var(--success-color); }

        .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--text-color); }
        .stat-label { font-size: 0.85rem; color: var(--light-text); text-transform: uppercase; letter-spacing: 0.5px; }

        /* Mensagens */
        .mensagem {
            padding: 15px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            border-left: 5px solid;
            display: flex;
            align-items: center;
            animation: slideDown 0.4s ease-out;
        }
        .sucesso { background-color: #e8f5e9; color: var(--success-color); border-left-color: var(--success-color); }
        .erro { background-color: #ffebee; color: var(--error-color); border-left-color: var(--error-color); }

        /* Filtros */
        .filter-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 200px; }
        .filter-group label { font-size: 0.85rem; font-weight: 600; color: var(--light-text); }
        
        select, button.btn-filter {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.95rem;
            background: #fafafa;
            font-family: inherit;
        }
        select:focus { border-color: var(--primary-color); outline: none; }

        .btn-filter {
            background: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
            min-width: 120px;
        }
        .btn-filter:hover { background: var(--primary-dark); }
        .btn-clear { background: transparent; color: var(--light-text); border: 1px solid var(--border-color); }
        .btn-clear:hover { background: #f5f5f5; color: var(--text-color); }

        /* Tabela */
        .table-responsive {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px; /* Garante scroll em telas pequenas */
        }

        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: #f8f9fa;
            color: var(--light-text);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #fcfcfc; }

        /* Badges e Status */
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }
        .badge-situacao { background-color: #e3f2fd; color: var(--primary-color); }
        .badge-status-validacao { background-color: #fff3e0; color: var(--warning-color); }
        
        .status-boolean { font-weight: 600; font-size: 0.9rem; }
        .status-true { color: var(--success-color); }
        .status-false { color: var(--error-color); opacity: 0.7; }

        /* Ações na Tabela */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s;
            margin-right: 4px;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
        }
        .btn-validate { background: rgba(25, 118, 210, 0.1); color: var(--primary-color); }
        .btn-validate:hover { background: var(--primary-color); color: white; }
        
        .btn-upload { background: rgba(46, 125, 50, 0.1); color: var(--success-color); }
        .btn-upload:hover { background: var(--success-color); color: white; }

        .btn-logistics { background: rgba(245, 124, 0, 0.1); color: var(--warning-color); }
        .btn-logistics:hover { background: var(--warning-color); color: white; }

        .upload-form-inline { display: inline-block; position: relative; }
        .upload-form-inline input[type="file"] {
            position: absolute; left: 0; top: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer;
        }

        /* Paginação */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-color);
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .pagination a:hover { background: #f5f5f5; border-color: #ccc; }
        .pagination .current {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            font-weight: 600;
        }

        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* Responsividade */
        @media (max-width: 768px) {
            .header-section { flex-direction: column; align-items: flex-start; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-card { flex-direction: column; }
            .filter-group { width: 100%; }
            .btn-filter, .btn-clear { width: 100%; }
        }
    </style>
</head>
<body>

    <div class="main-container">
        
        <div class="header-section">
            <h2>Gerenciar Inscrições</h2>
            <a href="dashboard.php" class="btn-back">← Voltar ao Dashboard</a>
        </div>

        <!-- Cards de Resumo (Dashboard Rápido) -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-value"><?= $pipelineTotal ?></span>
                <span class="stat-label">Total de Inscrições</span>
            </div>
            <div class="stat-card warning">
                <span class="stat-value"><?= $pendentesValidacao ?></span>
                <span class="stat-label">Pendentes de Validação</span>
            </div>
            <div class="stat-card success">
                <span class="stat-value"><?= $prontasEntrega ?></span>
                <span class="stat-label">Prontas para Entrega</span>
            </div>
        </div>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><strong>✅ Sucesso:</strong> <?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>
        <?php if ($erro): ?>
            <div class="mensagem erro"><strong>⚠️ Erro:</strong> <?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filter-card">
            <form method="GET" style="display: contents;">
                <input type="hidden" name="pagina" value="1">
                
                <div class="filter-group">
                    <label for="filtro_situacao">Situação da Inscrição</label>
                    <select name="filtro_situacao" id="filtro_situacao">
                        <option value="">Todas as Situações</option>
                        <?php foreach ($possiveisSituacoes as $sit): ?>
                            <option value="<?= $sit ?>" <?= $filtroSituacao === $sit ? 'selected' : '' ?>>
                                <?= ucfirst(str_replace('_', ' ', $sit)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filtro_status_validacao">Status de Validação</label>
                    <select name="filtro_status_validacao" id="filtro_status_validacao">
                        <option value="">Todos os Status</option>
                        <?php foreach ($possiveisStatusValidacao as $stat): ?>
                            <option value="<?= $stat ?>" <?= $filtroStatusValidacao === $stat ? 'selected' : '' ?>>
                                <?= ucfirst(str_replace('_', ' ', $stat)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group" style="flex-direction: row; gap: 10px; align-items: flex-end;">
                    <button type="submit" class="btn-filter">Filtrar</button>
                    <?php if ($filtroSituacao || $filtroStatusValidacao): ?>
                        <a href="?" class="btn-filter btn-clear" style="text-align:center; text-decoration:none; line-height: 40px;">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (empty($inscricoesList)): ?>
            <div style="text-align: center; padding: 40px; background: white; border-radius: var(--radius); color: var(--light-text);">
                <p style="font-size: 1.2rem;">Nenhuma inscrição encontrada com os critérios atuais.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Estudante</th>
                            <th>Curso</th>
                            <th>Data</th>
                            <th>Situação</th>
                            <th>Validação</th>
                            <th>Matrícula</th>
                            <th>Pagamento</th>
                            <th style="text-align: center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inscricoesList as $insc): ?>
                        <?php
                            // Pré-calcula variáveis para limpar o HTML
                            $inscTemp = new Inscricao($db);
                            $inscTemp->id = $insc['id'];
                            $docs = $inscTemp->getDocumentos();
                            
                            $temMatricula = false;
                            $temPagamento = false;
                            foreach ($docs as $doc) {
                                if ($doc['tipo'] === 'matricula') $temMatricula = true;
                                if ($doc['tipo'] === 'pagamento') $temPagamento = true;
                            }

                            $situacaoLabel = ucfirst(str_replace('_', ' ', $insc['situacao']));
                            $validacaoLabel = ucfirst(str_replace('_', ' ', $insc['estudante_status_validacao'] ?? 'Desconhecido'));
                        ?>
                        <tr>
                            <td><code style="background: #f5f5f5; padding: 2px 6px; border-radius: 4px; font-size: 0.85rem;"><?= htmlspecialchars($insc['codigo_inscricao']) ?></code></td>
                            <td>
                                <div style="font-weight: 600;"><?= htmlspecialchars($insc['estudante_nome']) ?></div>
                                <div style="font-size: 0.85rem; color: var(--light-text);">Mat: <?= htmlspecialchars($insc['estudante_matricula']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($insc['estudante_curso']) ?></td>
                            <td style="white-space: nowrap;"><?= date('d/m/Y', strtotime($insc['criado_em'])) ?></td>
                            <td><span class="badge badge-situacao"><?= $situacaoLabel ?></span></td>
                            <td><span class="badge badge-status-validacao"><?= $validacaoLabel ?></span></td>
                            <td>
                                <span class="status-boolean <?= $temMatricula ? 'status-true' : 'status-false' ?>">
                                    <?= $temMatricula ? '✔ Anexada' : '✖ Pendente' ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-boolean <?= $temPagamento ? 'status-true' : 'status-false' ?>">
                                    <?= $temPagamento ? '✔ Confirmado' : '✖ Pendente' ?>
                                </span>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <!-- Ação: Validar Documentos -->
                                <a href="validar_documentos.php?id=<?= $insc['id'] ?>" class="action-btn btn-validate" title="Validar Documentos">
                                    📋
                                </a>

                                <!-- Ação: Anexar Pagamento -->
                                <?php if (!$temPagamento && in_array($insc['situacao'], ['aguardando_validacao', 'pagamento_pendente', 'dados_aprovados'])): ?>
                                    <div class="upload-form-inline action-btn btn-upload" title="Anexar Comprovante de Pagamento">
                                        📎
                                        <form method="POST" enctype="multipart/form-data" style="margin:0; padding:0;">
                                            <input type="hidden" name="acao" value="upload_pagamento">
                                            <input type="hidden" name="inscricao_id" value="<?= $insc['id'] ?>">
                                            <input type="file" name="comprovante_pagamento" accept=".jpg,.jpeg,.png,.pdf" onchange="this.form.submit()">
                                        </form>
                                    </div>
                                <?php elseif ($temPagamento): ?>
                                    <span style="color: var(--success-color); font-size: 1.2rem;" title="Pagamento Confirmado">✅</span>
                                <?php else: ?>
                                    <span style="color: #ccc;">—</span>
                                <?php endif; ?>

                                <!-- Ação: Logística / Emitir -->
                                <?php if ($insc['situacao'] === 'cie_emitida_aguardando_entrega'): ?>
                                    <a href="logistica_entregas.php?inscricao_id=<?= $insc['id'] ?>" class="action-btn btn-logistics" title="Gerenciar Entrega">
                                        🚚
                                    </a>
                                <?php elseif ($insc['estudante_status_validacao'] === 'dados_aprovados' && $insc['pagamento_confirmado'] && $insc['matricula_validada']): ?>
                                    <a href="?cie_emitida=<?= $insc['id'] ?>&pagina=<?= $pagina ?>&filtro_situacao=<?= urlencode($filtroSituacao) ?>&filtro_status_validacao=<?= urlencode($filtroStatusValidacao) ?>" 
                                       class="action-btn btn-logistics" 
                                       onclick="return confirm('Confirmar que todos os dados estão corretos e enviar para logística?')" 
                                       title="Enviar para Logística">
                                        📦
                                    </a>
                                <?php else: ?>
                                    <span style="color: #eee;">⬜</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <?php if ($totalPaginas > 1): ?>
            <div class="pagination">
                <?php if ($pagina > 1): ?>
                    <a href="?pagina=<?= $pagina - 1 ?>&filtro_situacao=<?= urlencode($filtroSituacao) ?>&filtro_status_validacao=<?= urlencode($filtroStatusValidacao) ?>">Anterior</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <?php if ($i == $pagina): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?pagina=<?= $i ?>&filtro_situacao=<?= urlencode($filtroSituacao) ?>&filtro_status_validacao=<?= urlencode($filtroStatusValidacao) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($pagina < $totalPaginas): ?>
                    <a href="?pagina=<?= $pagina + 1 ?>&filtro_situacao=<?= urlencode($filtroSituacao) ?>&filtro_status_validacao=<?= urlencode($filtroStatusValidacao) ?>">Próxima</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

</body>
</html>