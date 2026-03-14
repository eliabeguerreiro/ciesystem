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
// CARREGAR DADOS PARA EXIBIÇÃO
// ================================
$documentos = [];

if ($inscricaoId) {
    $inscricao = $inscricaoModel->buscarPorId($inscricaoId);
    if ($inscricao) {
        $estudanteId = $inscricao['estudante_id'];
        $estudante = $estudanteModel->buscarPorId($estudanteId);
        $inscricaoModel->id = $inscricaoId;
        $documentos = $inscricaoModel->getDocumentos();
        
        // === FILTRAR: Remover comprovante de pagamento da lista de validação ===
        $documentos = array_filter($documentos, function($doc) {
            return $doc['tipo'] !== 'pagamento';
        });
        // === FIM FILTRAR ===
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validar Documentos - Inscrição #<?= htmlspecialchars($inscricaoId) ?></title>
    <!-- Fonte Google -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1976d2;
            --primary-dark: #1565c0;
            --success-color: #2e7d32;
            --error-color: #c62828;
            --warning-color: #f57c00;
            --bg-color: #f4f6f8;
            --card-bg: #ffffff;
            --text-color: #333;
            --light-text: #666;
            --border-color: #ddd;
            --shadow: 0 4px 6px rgba(0,0,0,0.05);
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
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

        /* Card de Dados do Estudante */
        .student-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            border-left: 5px solid var(--primary-color);
            animation: fadeInDown 0.5s ease-out;
        }

        .student-info h4 {
            font-size: 0.85rem;
            color: var(--light-text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .student-info p {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--text-color);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-pendente { background-color: #fff3e0; color: var(--warning-color); }
        .status-aprovado { background-color: #e8f5e9; color: var(--success-color); }

        /* Mensagens */
        .mensagem {
            padding: 15px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            border-left: 5px solid;
            animation: slideDown 0.4s ease-out;
        }
        .sucesso { background-color: #e8f5e9; color: var(--success-color); border-left-color: var(--success-color); }
        .erro { background-color: #ffebee; color: var(--error-color); border-left-color: var(--error-color); }

        /* Tabela de Documentos */
        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            color: var(--light-text);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #fcfcfc; }
        tr.validated-row { background-color: #f1f8e9; }

        .doc-preview-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #ddd;
            vertical-align: middle;
            margin-right: 10px;
        }

        .status-label {
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .status-validado { color: var(--success-color); background: rgba(46, 125, 50, 0.1); }
        .status-invalido { color: var(--error-color); background: rgba(198, 40, 40, 0.1); }
        .status-pendente { color: var(--warning-color); background: rgba(245, 124, 0, 0.1); }

        .btn-view {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.3s;
        }
        .btn-view:hover { color: var(--primary-dark); text-decoration: underline; }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s;
            color: white;
        }

        .btn-validate { background-color: var(--success-color); }
        .btn-validate:hover { background-color: #1b5e20; transform: translateY(-1px); }

        .btn-reject { background-color: var(--error-color); }
        .btn-reject:hover { background-color: #b71c1c; transform: translateY(-1px); }

        .btn-approved-static {
            color: var(--success-color);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            position: relative;
            animation: slideDown 0.4s;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }
        .close:hover { color: #000; }

        textarea#obs_reenvio {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            font-size: 0.95rem;
        }
        textarea#obs_reenvio:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-modal {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-confirm { background-color: var(--error-color); color: white; }
        .btn-confirm:hover { background-color: #b71c1c; }

        .btn-cancel { background-color: #f5f5f5; color: #555; }
        .btn-cancel:hover { background-color: #e0e0e0; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* Responsividade */
        @media (max-width: 768px) {
            .student-card { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .btn-action { width: 100%; text-align: center; }
            th, td { padding: 10px; font-size: 0.9rem; }
        }
    </style>
</head>
<body>

    <div class="main-container">
        
        <div class="header-section">
            <h2>📋 Validar Documentos <small style="font-size: 0.6em; color: var(--light-text);">#<?= htmlspecialchars($inscricaoId) ?></small></h2>
            <a href="gerenciar_inscricoes.php" class="btn-back">← Voltar à Lista</a>
        </div>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><strong>✅ Sucesso:</strong> <?= nl2br(htmlspecialchars($sucesso)) ?></div>
        <?php endif; ?>
        
        <?php if ($erro && empty($documentos)): ?>
            <div class="mensagem erro"><strong>⚠️ Erro:</strong> <?= nl2br(htmlspecialchars($erro)) ?></div>
        <?php endif; ?>

        <?php if ($estudante): ?>
            <div class="student-card">
                <div class="student-info">
                    <h4>Estudante</h4>
                    <p><?= htmlspecialchars($estudante['nome']) ?></p>
                </div>
                <div class="student-info">
                    <h4>Matrícula</h4>
                    <p><?= htmlspecialchars($estudante['matricula']) ?></p>
                </div>
                <div class="student-info">
                    <h4>Curso / Campus</h4>
                    <p><?= htmlspecialchars($estudante['curso'] ?? 'N/A') ?> <span style="color:#ccc">|</span> <?= htmlspecialchars($estudante['campus'] ?? 'N/A') ?></p>
                </div>
                <div class="student-info">
                    <h4>Instituição</h4>
                    <p><?= htmlspecialchars($estudante['instituicao_nome'] ?? 'N/A') ?></p>
                </div>
                <div class="student-info">
                    <h4>Status Atual</h4>
                    <span class="status-badge <?= $estudante['status_validacao'] === 'dados_aprovados' ? 'status-aprovado' : 'status-pendente' ?>">
                        <?= ucfirst(str_replace('_', ' ', $estudante['status_validacao'])) ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($documentos)): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 30%;">Documento</th>
                            <th style="width: 15%;">Tipo</th>
                            <th style="width: 15%;">Status</th>
                            <th style="width: 20%;">Observação</th>
                            <th style="width: 10%;">Visualizar</th>
                            <th style="width: 15%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documentos as $doc): ?>
                        <?php
                        $safeDocId = $doc['id'];
                        $isInvalid = ($doc['validado'] ?? 'pendente') === 'invalido';
                        $isValidated = ($doc['validado'] ?? 'pendente') === 'validado';
                        ?>
                        <tr id="linha_doc_<?= $safeDocId ?>" class="<?= $isValidated ? 'validated-row' : '' ?>">
                            <td>
                                <?php if ($doc['tipo'] === 'foto_3x4'): ?>
                                    <img src="../public/<?= htmlspecialchars($doc['caminho_arquivo']) ?>" class="doc-preview-thumb" alt="Foto">
                                <?php endif; ?>
                                <span style="font-weight: 500;"><?= htmlspecialchars($doc['descricao']) ?></span>
                            </td>
                            <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['tipo']))) ?></td>
                            <td id="status_doc_<?= $safeDocId ?>">
                                <span class="status-label status-<?= $doc['validado'] ?? 'pendente' ?>">
                                    <?= ucfirst($doc['validado'] ?? 'pendente') ?>
                                </span>
                            </td>
                            <td id="obs_doc_<?= $safeDocId ?>" style="font-size: 0.9rem; color: var(--light-text);">
                                <?= $isInvalid ? '<strong style="color:var(--error-color)">⚠️</strong> ' : '' ?>
                                <?= htmlspecialchars($doc['observacoes_validacao'] ?? '—') ?>
                            </td>
                            <td>
                                <a href="../public/<?= htmlspecialchars($doc['caminho_arquivo']) ?>" target="_blank" class="btn-view">👁️ Ver</a>
                            </td>
                            <td class="celula_acoes_<?= $safeDocId ?>">
                                <?php if (!$isValidated): ?>
                                    <div class="action-buttons">
                                        <button class="btn-action btn-validate" onclick="validarDocumento('<?= $safeDocId ?>')" title="Aprovar documento">
                                            ✅ Validar
                                        </button>
                                        <button class="btn-action btn-reject" onclick="abrirModalReenvio('<?= $safeDocId ?>', '<?= addslashes(htmlspecialchars($doc['descricao'])) ?>')" title="Solicitar correção">
                                            ⚠️ Reenviar
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="btn-approved-static">
                                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        Aprovado
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="table-container" style="padding: 40px; text-align: center;">
                <p style="font-size: 1.2rem; color: var(--light-text);">Nenhum documento pendente de validação para esta inscrição.</p>
                <?php if ($estudante && $estudante['status_validacao'] === 'dados_aprovados'): ?>
                    <p style="color: var(--success-color); font-weight: bold; margin-top: 10px;">✅ Todos os documentos já foram validados!</p>
                <?php endif; ?>
                <div style="margin-top: 20px;">
                    <a href="gerenciar_inscricoes.php" class="btn-back">← Voltar ao Gerenciamento</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Reenvio -->
    <div id="modal_reenvio" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalReenvio()">&times;</span>
            <h3 style="color: var(--error-color); margin-bottom: 10px;">⚠️ Solicitar Reenvio</h3>
            <p style="color: var(--light-text); margin-bottom: 15px;">
                Documento: <strong id="nome_doc_reenvio" style="color: var(--text-color);"></strong>
            </p>
            <p style="font-size: 0.9rem; color: #666;">Informe o motivo da reprovação para que o estudante saiba o que corrigir:</p>
            
            <textarea id="obs_reenvio" placeholder="Ex: A foto está borrada. Por favor, envie uma imagem nítida do documento frente e verso."></textarea>
            
            <div class="modal-actions">
                <button class="btn-modal btn-cancel" onclick="fecharModalReenvio()">Cancelar</button>
                <button class="btn-modal btn-confirm" onclick="confirmarReenvio()">Confirmar Reprovação</button>
            </div>
        </div>
    </div>

    <script>
        let docIdReenvio = null;

        function validarDocumento(docId) {
            if (!docId) return;
            if(!confirm('Tem certeza que deseja VALIDAR este documento?')) return;

            const urlEndpoint = 'atualizar_documento.php';
            const dados = { doc_id: docId, acao: 'validar', observacao: '' };
            
            fetch(urlEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dados)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    atualizarLinhaTabela(docId, 'validado', data.nova_obs, true);
                    
                    if (data.estudante_aprovado) {
                        alert('✅ Sucesso!\n\nTodos os documentos validados.\nO status do estudante foi atualizado automaticamente para "Dados Aprovados".');
                        location.reload();
                    } else {
                        // Pequena animação de sucesso na linha
                        const row = document.getElementById(`linha_doc_${docId}`);
                        row.style.transition = 'background 0.5s';
                        row.style.background = '#dcedc8';
                        setTimeout(() => row.style.background = '', 1000);
                    }
                } else {
                    alert('❌ Erro: ' + (data.message || 'Falha ao validar documento.'));
                }
            })
            .catch(() => alert('❌ Erro de conexão com o servidor.'));
        }

        function abrirModalReenvio(id, nome) {
            docIdReenvio = id;
            document.getElementById('nome_doc_reenvio').textContent = nome;
            document.getElementById('obs_reenvio').value = '';
            document.getElementById('modal_reenvio').style.display = 'block';
            document.getElementById('obs_reenvio').focus();
        }

        function fecharModalReenvio() {
            document.getElementById('modal_reenvio').style.display = 'none';
        }

        function confirmarReenvio() {
            if (!docIdReenvio) return;

            const obsReenvio = document.getElementById('obs_reenvio');
            const observacao = obsReenvio.value.trim();
            
            if (!observacao) {
                alert('⚠️ Por favor, informe um motivo para a solicitação de reenvio.');
                obsReenvio.focus();
                return;
            }

            const urlEndpoint = 'atualizar_documento.php';
            const dados = { doc_id: docIdReenvio, acao: 'reenviar', observacao: observacao };
            
            fetch(urlEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dados)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    atualizarLinhaTabela(docIdReenvio, 'invalido', data.nova_obs, false);
                    fecharModalReenvio();
                    alert('✅ Solicitação de reenvio registrada com sucesso.\nO estudante será notificado sobre a necessidade de correção.');
                } else {
                    alert('❌ Erro: ' + (data.message || 'Falha ao solicitar reenvio.'));
                }
            })
            .catch(() => alert('❌ Erro de conexão com o servidor.'));
        }

        function atualizarLinhaTabela(docId, novoStatus, novaObs, isValidado) {
            const statusCell = document.getElementById(`status_doc_${docId}`);
            const obsCell = document.getElementById(`obs_doc_${docId}`);
            const celulaAcoes = document.querySelector(`.celula_acoes_${docId}`);
            const row = document.getElementById(`linha_doc_${docId}`);
            
            // Atualiza Badge de Status
            if (statusCell) {
                const labelClass = novoStatus === 'validado' ? 'status-validado' : 'status-invalido';
                const labelText = novoStatus === 'validado' ? 'Validado' : 'Inválido';
                statusCell.innerHTML = `<span class="status-label ${labelClass}">${labelText}</span>`;
            }
            
            // Atualiza Observação
            if (obsCell) {
                const icon = novoStatus === 'invalido' ? '<strong style="color:var(--error-color)">⚠️</strong> ' : '';
                obsCell.innerHTML = icon + novaObs;
            }
            
            // Atualiza Ações
            if (celulaAcoes) {
                if (isValidado) {
                    celulaAcoes.innerHTML = `
                        <span class="btn-approved-static">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Aprovado
                        </span>`;
                    row.classList.add('validated-row');
                } else {
                    // Mantém os botões mas destaca que foi alterado
                    row.style.background = '#ffebee';
                    setTimeout(() => row.style.background = '', 1000);
                }
            }
        }

        // Fecha modal se clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('modal_reenvio');
            if (event.target === modal) {
                fecharModalReenvio();
            }
        };
    </script>
</body>
</html>