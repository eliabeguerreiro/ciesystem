<?php
// Arquivo: c:\laragon\www\ciesytem\public\logistica_entregas.php
session_start();

// Verificação de acesso
require_once __DIR__ . '/../app/controllers/AuthController.php';
$auth = new AuthController();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    die("Acesso negado.");
}

// Carrega dependências
require_once __DIR__ . '/../app/models/LogisticaEntrega.php';
require_once __DIR__ . '/../app/models/Inscricao.php';
require_once __DIR__ . '/../app/models/Instituicao.php';

$database = new Database();
$db = $database->getConnection();
$logisticaModel = new LogisticaEntrega($db);
$inscricaoModel = new Inscricao($db);
$instituicaoModel = new Instituicao($db); // Para carregar nomes de instituições

$erro = '';
$sucesso = '';

// ================================
// AÇÕES: Registrar Saída ou Entrega
// ================================

if ($_POST) {
    if (isset($_POST['acao']) && $_POST['acao'] === 'registrar_saida') {
        $inscricaoId = (int)($_POST['inscricao_id'] ?? 0);
        if ($inscricaoId > 0) {
            // Obter dados da inscrição e estudante para preencher a logística
            $inscricao = $inscricaoModel->buscarPorId($inscricaoId);
            if ($inscricao) {
                $estudanteId = $inscricao['estudante_id'];
                $stmt = $db->prepare("SELECT instituicao_id FROM estudantes WHERE id = :estudante_id");
                $stmt->bindParam(':estudante_id', $estudanteId, PDO::PARAM_INT);
                $stmt->execute();
                $estudante = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($estudante) {
                    $logistica = new LogisticaEntrega($db);
                    $logistica->inscricao_id = $inscricaoId;
                    $logistica->instituicao_id = $estudante['instituicao_id'];
                    $logistica->responsavel_saida = $_POST['responsavel_saida'] ?? 'Administrador';
                    $logistica->observacoes = $_POST['observacoes_saida'] ?? '';
                    $logistica->registrado_por = $_SESSION['user_id'];

                    if ($logistica->registrarSaida()) {
                        // Opcional: Atualizar status da inscrição novamente para garantir consistência se necessário
                        // Neste fluxo, o status da inscrição já está como 'cie_emitida_aguardando_entrega'
                        require_once __DIR__ . '/../app/models/Log.php';
                        $log = new Log($db);
                        $log->registrar(
                            $_SESSION['user_id'],
                            'logistica_registro_saida',
                            "Inscrição ID: {$inscricaoId}, Instituição ID: {$logistica->instituicao_id}",
                            $logistica->id, // ID do registro de logística
                            'logistica_entregas'
                        );
                        $sucesso = "Saída registrada com sucesso para a inscrição {$inscricaoId}.";
                    } else {
                        $erro = "Erro ao registrar saída.";
                    }
                } else {
                    $erro = "Erro: Não foi possível encontrar a instituição do estudante.";
                }
            } else {
                $erro = "Erro: Inscrição não encontrada.";
            }
        } else {
            $erro = "ID da inscrição inválido.";
        }
    }

    if (isset($_POST['acao']) && $_POST['acao'] === 'confirmar_entrega') {
        $inscricaoId = (int)($_POST['inscricao_id_entrega'] ?? 0);
        if ($inscricaoId > 0) {
            // Buscar o registro de logística ativo para esta inscrição
            $eventosLog = $logisticaModel->buscarPorInscricao($inscricaoId);
            $registroAtivo = null;
            foreach ($eventosLog as $evento) {
                if ($evento['status'] === 'saida_para_entrega') {
                    $registroAtivo = $evento;
                    break;
                }
            }

            if ($registroAtivo) {
                $logistica = new LogisticaEntrega($db);
                $logistica->id = $registroAtivo['id']; // ID do registro a ser atualizado
                $logistica->responsavel_entrega = $_POST['responsavel_entrega'] ?? 'Responsável na Instituição';
                $logistica->data_entrega_instituicao = null; // Deixar como null para usar NOW() no modelo
                $logistica->inscricao_id = $inscricaoId; // Necessário para a condição no modelo

                if ($logistica->confirmarEntregaNaInstituicao()) {
                    // Atualizar status da inscrição para 'cie_entregue_na_instituicao'
                    $inscricao = new Inscricao($db);
                    $inscricao->id = $inscricaoId;
                    if ($inscricao->atualizarSituacao('cie_entregue_na_instituicao')) {
                        require_once __DIR__ . '/../app/models/Log.php';
                        $log = new Log($db);
                        $log->registrar(
                            $_SESSION['user_id'],
                            'logistica_confirmou_entrega',
                            "Inscrição ID: {$inscricaoId}, Entregue por: {$logistica->responsavel_entrega}",
                            $logistica->id, // ID do registro de logística
                            'logistica_entregas'
                        );
                        $sucesso = "Entrega confirmada com sucesso para a inscrição {$inscricaoId}.";
                    } else {
                         $erro = "Erro ao atualizar status da inscrição para 'cie_entregue_na_instituicao'.";
                    }
                } else {
                    $erro = "Erro ao confirmar entrega. Pode já ter sido confirmada.";
                }
            } else {
                $erro = "Nenhum registro de saída pendente encontrado para esta inscrição.";
            }
        } else {
            $erro = "ID da inscrição inválido.";
        }
    }
}


// ================================
// FILTRAGEM E LISTAGEM
// ================================
$filtroInstituicao = $_GET['filtro_instituicao'] ?? '';
$filtroStatusLogistica = $_GET['filtro_status_logistica'] ?? '';
$pagina = (int)($_GET['pagina'] ?? 1);
$registrosPorPagina = 20; // Ajuste conforme necessário
$offset = ($pagina - 1) * $registrosPorPagina;

// Contar total de registros para paginação
$totalQuery = "SELECT COUNT(*) as total FROM logistica_entregas le ";
$totalParams = [];

if ($filtroInstituicao || $filtroStatusLogistica) {
    $totalQuery .= "WHERE ";
    $whereConditions = [];
    if ($filtroInstituicao) {
        $whereConditions[] = "le.instituicao_id = :filtro_instituicao";
        $totalParams[':filtro_instituicao'] = $filtroInstituicao;
    }
    if ($filtroStatusLogistica) {
        $whereConditions[] = "le.status = :filtro_status_logistica";
        $totalParams[':filtro_status_logistica'] = $filtroStatusLogistica;
    }
    $totalQuery .= implode(' AND ', $whereConditions);
}
$totalStmt = $db->prepare($totalQuery);
if (!empty($totalParams)) {
    $totalStmt->execute($totalParams);
} else {
    $totalStmt->execute(); // Se não houver filtros, execute sem parâmetros
}
$totalRegistros = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Aplicar filtros e paginação na listagem
$entregasList = $logisticaModel->listarTodas(); // Este método atualmente lista todos, sem paginação nem filtro direto. Vamos ajustar isso.
// OU BUSCAR APENAS OS IDS DAS INSCRIÇÕES NECESSÁRIAS E DEPOIS USAR OUTRO MÉTODO
// OU CRIAR UM MÉTODO ESPECÍFICO NO MODELO COM FILTROS E PAGINAÇÃO
// Vamos optar por criar um método específico com paginação e filtro.
// Primeiro, atualizamos o modelo:

// Em LogisticaEntrega.php, adicione este método:
/*
public function listarComFiltrosEPaginacao($filtroInstituicao = '', $filtroStatus = '', $offset = 0, $limit = 10) {
    $query = "SELECT le.*, u.nome AS nome_registrador, i.codigo_inscricao AS codigo_inscricao, e.nome AS nome_estudante, inst.nome AS nome_instituicao
              FROM {$this->table} le
              LEFT JOIN usuarios u ON le.registrado_por = u.id
              LEFT JOIN inscricoes i ON le.inscricao_id = i.id
              LEFT JOIN estudantes e ON i.estudante_id = e.id
              LEFT JOIN instituicoes inst ON le.instituicao_id = inst.id
              WHERE 1=1 "; // Condição neutra para facilitar adição de filtros

    $params = [];
    if ($filtroInstituicao) {
        $query .= " AND le.instituicao_id = :filtro_instituicao ";
        $params[':filtro_instituicao'] = $filtroInstituicao;
    }
    if ($filtroStatus) {
        $query .= " AND le.status = :filtro_status ";
        $params[':filtro_status'] = $filtroStatus;
    }

    $query .= " ORDER BY le.criado_em DESC LIMIT :limit OFFSET :offset";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
*/

// Agora, no PHP desta página:
// $entregasList = $logisticaModel->listarComFiltrosEPaginacao($filtroInstituicao, $filtroStatusLogistica, $offset, $registrosPorPagina);

// PORÉM, PARA SIMPLIFICAR A DEMONSTRAÇÃO, VAMOS FILTRAR APÓS CARREGAR TUDO (menos eficiente para grandes volumes)
$entregasList = $logisticaModel->listarTodas();

// Aplicar filtros manualmente
if ($filtroInstituicao) {
    $entregasList = array_filter($entregasList, function($item) use ($filtroInstituicao) {
        return $item['instituicao_id'] == $filtroInstituicao;
    });
}
if ($filtroStatusLogistica) {
    $entregasList = array_filter($entregasList, function($item) use ($filtroStatusLogistica) {
        return $item['status'] == $filtroStatusLogistica;
    });
}

// Paginação manual (menos eficiente)
$totalRegistros = count($entregasList);
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);
$offset = ($pagina - 1) * $registrosPorPagina;
$entregasList = array_slice($entregasList, $offset, $registrosPorPagina);

// Obter todas as instituições para o filtro
$instituicoes = $instituicaoModel->listar();

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Logística de Entregas</title>
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
        a { color: #1976d2; text-decoration: none; margin-right: 10px; }
        a:hover { text-decoration: underline; }
        .voltar { display: inline-block; margin-bottom: 20px; color: #555; }
        .filtro-container { margin-bottom: 20px; }
        .filtro-container label { display: inline-block; margin-right: 10px; }
        .filtro-container input, .filtro-container select { margin-right: 10px; padding: 4px; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a, .pagination span { display: inline-block; padding: 8px 16px; text-decoration: none; border: 1px solid #ddd; margin: 0 4px; }
        .pagination a:hover { background-color: #f0f0f0; }
        .pagination .current { background-color: #1976d2; color: white; }
        .status-saida_para_entrega { color: #5d4037; font-weight: bold; } /* Marrom */
        .status-entregue_na_instituicao { color: #2e7d32; font-weight: bold; } /* Verde */
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="voltar">← Voltar ao Dashboard</a>
        <h2>Logística de Entregas de CIE</h2>

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
                <label for="filtro_instituicao">Filtrar por Instituição:</label>
                <select name="filtro_instituicao" id="filtro_instituicao">
                    <option value="">Todas</option>
                    <?php foreach ($instituicoes as $inst): ?>
                        <option value="<?= $inst['id'] ?>" <?= $filtroInstituicao == $inst['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($inst['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="filtro_status_logistica">Filtrar por Status Logística:</label>
                <select name="filtro_status_logistica" id="filtro_status_logistica">
                    <option value="">Todos</option>
                    <option value="saida_para_entrega" <?= $filtroStatusLogistica === 'saida_para_entrega' ? 'selected' : '' ?>>Saída para Entrega</option>
                    <option value="entregue_na_instituicao" <?= $filtroStatusLogistica === 'entregue_na_instituicao' ? 'selected' : '' ?>>Entregue na Instituição</option>
                </select>

                <button type="submit">Aplicar Filtros</button>
                <?php if ($filtroInstituicao || $filtroStatusLogistica): ?>
                    <a href="?">Limpar Filtros</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($entregasList)): ?>
            <p>Nenhum registro de logística encontrado com os critérios atuais.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Código Inscrição</th>
                        <th>Estudante</th>
                        <th>Instituição</th>
                        <th>Status Logística</th>
                        <th>Data Saída</th>
                        <th>Responsável Saída</th>
                        <th>Data Entrega Instituição</th>
                        <th>Responsável Entrega</th>
                        <th>Registrado Por</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entregasList as $ent): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($ent['codigo_inscricao']) ?></code></td>
                        <td><?= htmlspecialchars($ent['nome_estudante']) ?></td>
                        <td><?= htmlspecialchars($ent['nome_instituicao']) ?></td>
                        <td>
                            <span class="status-<?= $ent['status'] ?>"><?= ucfirst(str_replace('_', ' ', $ent['status'])) ?></span>
                        </td>
                        <td><?= $ent['data_saida'] ? date('d/m/Y H:i', strtotime($ent['data_saida'])) : '—' ?></td>
                        <td><?= htmlspecialchars($ent['responsavel_saida']) ?></td>
                        <td><?= $ent['data_entrega_instituicao'] ? date('d/m/Y H:i', strtotime($ent['data_entrega_instituicao'])) : '—' ?></td>
                        <td><?= htmlspecialchars($ent['responsavel_entrega']) ?></td>
                        <td><?= htmlspecialchars($ent['nome_registrador']) ?></td>
                        <td class="acoes">
                            <?php if ($ent['status'] === 'saida_para_entrega'): ?>
                                <!-- Formulário para confirmar entrega -->
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="acao" value="confirmar_entrega">
                                    <input type="hidden" name="inscricao_id_entrega" value="<?= $ent['inscricao_id'] ?>">
                                    <label for="resp_ent_<?= $ent['id'] ?>">Resp. Entrega:</label>
                                    <input type="text" name="responsavel_entrega" id="resp_ent_<?= $ent['id'] ?>" placeholder="Nome do responsável" required style="width:150px;">
                                    <button type="submit" onclick="return confirm('Confirmar entrega para a inscrição <?= $ent['inscricao_id'] ?>?')">Confirmar Entrega</button>
                                </form>
                            <?php elseif ($ent['status'] === 'entregue_na_instituicao'): ?>
                                <span title="Entrega já confirmada">✅ Confirmada</span>
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
                        <a href="?pagina=<?= $pagina - 1 ?>&filtro_instituicao=<?= urlencode($filtroInstituicao) ?>&filtro_status_logistica=<?= urlencode($filtroStatusLogistica) ?>">&laquo; Anterior</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <?php if ($i == $pagina): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?pagina=<?= $i ?>&filtro_instituicao=<?= urlencode($filtroInstituicao) ?>&filtro_status_logistica=<?= urlencode($filtroStatusLogistica) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($pagina < $totalPaginas): ?>
                        <a href="?pagina=<?= $pagina + 1 ?>&filtro_instituicao=<?= urlencode($filtroInstituicao) ?>&filtro_status_logistica=<?= urlencode($filtroStatusLogistica) ?>">Próxima &raquo;</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        <?php endif; ?>

        <!-- Área para registrar saída de inscrições prontas -->
        <h3>Registrar Saída para Entrega</h3>
        <p>Selecione uma inscrição pronta para sair e registre os detalhes.</p>

        <?php
        // Carregar inscrições prontas para logística (status = cie_emitida_aguardando_entrega)
        $inscricoesProntas = $inscricaoModel->listarProntasParaLogistica();
        $inscricoesComLogistica = []; // Pegar IDs que já têm registro de logística ativo
        foreach ($entregasList as $ent) { // Usando a lista filtrada para verificação local
            if ($ent['status'] === 'saida_para_entrega') {
                $inscricoesComLogistica[] = $ent['inscricao_id'];
            }
        }
        $inscricoesDisponiveis = array_filter($inscricoesProntas, function($item) use ($inscricoesComLogistica) {
            return !in_array($item['id'], $inscricoesComLogistica);
        });
        ?>

        <?php if (empty($inscricoesDisponiveis)): ?>
            <p>Nenhuma inscrição disponível para saída no momento.</p>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="acao" value="registrar_saida">
                <label for="inscricao_id">Selecionar Inscrição Pronta:</label>
                <select name="inscricao_id" id="inscricao_id" required>
                    <option value="">Escolha uma inscrição...</option>
                    <?php foreach ($inscricoesDisponiveis as $inscDisp): ?>
                        <option value="<?= $inscDisp['id'] ?>">
                            <?= htmlspecialchars($inscDisp['codigo_inscricao']) ?> - <?= htmlspecialchars($inscDisp['estudante_nome']) ?> (<?= htmlspecialchars($inscDisp['instituicao_nome']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="responsavel_saida">Responsável pela Saída:</label>
                <input type="text" name="responsavel_saida" id="responsavel_saida" placeholder="Nome do responsável" required>
                <label for="observacoes_saida">Observações (Opcional):</label>
                <input type="text" name="observacoes_saida" id="observacoes_saida" placeholder="Detalhes da entrega...">
                <button type="submit">Registrar Saída</button>
            </form>
        <?php endif; ?>

    </div>
</body>
</html>