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
        $inscricaoModel->id = $inscricaoId; // Garante que o ID está definido no modelo
        $documentos = $inscricaoModel->getDocumentos(); // Retorna documentos com entidade_tipo = 'inscricao' e entidade_id = $inscricaoId
        // --- REMOVIDO: Lógica para adicionar a foto do estudante ---
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
        .btn-acao { background-color: #1976d2; color: white; border: none; padding: 4px 8px; cursor: pointer; }
        .btn-acao:hover { background-color: #1565c0; }
        .status-n_a { color: #555; font-style: italic; }
        .status-invalido { color: #c62828; font-weight: bold; } /* Estilo para status inválido */
        .foto-preview { width: 60px; height: 60px; object-fit: cover; border: 1px solid #ddd; border-radius: 4px; }

        /* Estilo da Modal */
        .modal {
            display: none; /* Hidden by default */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .acao-btn {
            display: block;
            width: 100%;
            padding: 10px;
            margin: 5px 0;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
        }
        .btn-validar-modal { background-color: #2e7d32; color: white; }
        .btn-reenviar-modal { background-color: #c62828; color: white; }
        .btn-cancelar-modal { background-color: #555; color: white; }

        textarea#obs_reenvio { width: 100%; padding: 8px; margin-top: 10px; }
        #resultado_acao { margin-top: 10px; padding: 10px; border-radius: 4px; display: none; }
        #resultado_acao.success { background-color: #e8f5e9; color: #2e7d32; }
        #resultado_acao.error { background-color: #ffebee; color: #c62828; }
    </style>
</head>
<body>
    <div class="container">
        <a href="gerenciar_inscricoes.php" class="voltar">← Voltar à Lista de Inscrições</a>
        <h2>Validar Documentos da Inscrição #<?= htmlspecialchars($inscricaoId) ?></h2>
        <?php if ($estudante): ?>
            <p><strong>Estudante:</strong> <?= htmlspecialchars($estudante['nome']) ?></p>
            <p><strong>Matrícula:</strong> <?= htmlspecialchars($estudante['matricula']) ?></p>
            <!-- Adicionando mais informações do estudante -->
            <p><strong>Curso:</strong> <?= htmlspecialchars($estudante['curso'] ?? 'N/A') ?></p>
            <p><strong>Campus:</strong> <?= htmlspecialchars($estudante['campus'] ?? 'N/A') ?></p>
            <p><strong>Instituição:</strong> <?= htmlspecialchars($estudante['instituicao_nome'] ?? 'N/A') ?></p>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><?= nl2br(htmlspecialchars($sucesso)) ?></div>
        <?php endif; ?>
        <?php if ($erro && empty($documentos)): ?>
            <div class="mensagem erro"><?= nl2br(htmlspecialchars($erro)) ?></div>
        <?php endif; ?>

        <?php if (!empty($documentos)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Observação</th>
                        <th>Visualizar</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documentos as $doc): ?>
                    <?php
                    // O ID do documento agora é sempre numérico (da tabela documentos_anexados)
                    $safeDocId = $doc['id'];
                    ?>
                    <tr id="linha_doc_<?= $safeDocId ?>"> <!-- ID para atualizar a linha -->
                        <td>
                            <?php if ($doc['tipo'] === 'foto_3x4'): ?>
                                <img src="../public/<?= htmlspecialchars($doc['caminho_arquivo']) ?>" class="foto-preview" alt="Foto 3x4">
                            <?php endif; ?>
                            <?= htmlspecialchars($doc['descricao']) ?>
                        </td>
                        <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['tipo']))) ?></td>
                        <td id="status_doc_<?= $safeDocId ?>"> <!-- ID para atualizar o status -->
                            <span class="status-<?= $doc['validado'] ?? 'n_a' ?>">
                                <?= ucfirst($doc['validado'] ?? 'n/a') ?>
                            </span>
                        </td>
                        <td id="obs_doc_<?= $safeDocId ?>"> <!-- ID para atualizar a observação -->
                            <?= htmlspecialchars($doc['observacoes_validacao'] ?? '—') ?>
                        </td>
                        <td>
                            <a href="../public/<?= htmlspecialchars($doc['caminho_arquivo']) ?>" target="_blank" class="doc-link">Ver</a>
                        </td>
                        <td>
                            <button class="btn-acao btn-validar-modal" onclick="validarDocumento('<?= $safeDocId ?>')">Validar</button>
                            <button class="btn-acao btn-reenviar-modal" onclick="abrirModalReenvio('<?= $safeDocId ?>', '<?= addslashes(htmlspecialchars($doc['descricao'])) ?>')">Solicitar Reenvio</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>
            <p>Nenhum documento encontrado para esta inscrição.</p>
            <a href="gerenciar_inscricoes.php">← Voltar</a>
        <?php endif; ?>
    </div>

    <!-- Modal de Reenvio -->
    <div id="modal_reenvio" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalReenvio()">&times;</span>
            <h3 id="titulo_modal_reenvio">Solicitar Reenvio para: <span id="nome_doc_reenvio"></span></h3>
            <div id="resultado_reenvio"></div>
            <textarea id="obs_reenvio" placeholder="Digite a observação para o estudante..."></textarea>
            <button class="acao-btn btn-reenviar-modal" onclick="confirmarReenvio()">Solicitar</button>
            <button class="acao-btn btn-cancelar-modal" onclick="fecharModalReenvio()">Cancelar</button>
        </div>
    </div>

    <script>
        // Variáveis globais para modal de reenvio
        let docIdReenvio = null;
        let nomeDocReenvio = '';

        function validarDocumento(docId) {
            if (!docId) return;

            // Agora, todos os documentos (inclusive foto_3x4) são tratados como documentos da inscrição
            // Portanto, sempre envia para atualizar_documento.php
            const urlEndpoint = 'atualizar_documento.php';

            const dados = {
                doc_id: docId,
                acao: 'validar',
                observacao: ''
            };
            fetch(urlEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dados)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const statusCell = document.getElementById(`status_doc_${docId}`);
                    const obsCell = document.getElementById(`obs_doc_${docId}`);
                    if (statusCell) {
                        statusCell.innerHTML = `<span class="status-validado">${data.novo_status}</span>`;
                    }
                    if (obsCell) {
                        obsCell.textContent = data.nova_obs;
                    }
                } else {
                    alert(data.message || 'Erro ao validar documento.');
                }
            })
            .catch(() => {
                alert('Erro de rede ou servidor.');
            });
        }

        function abrirModalReenvio(id, nome) {
            docIdReenvio = id;
            nomeDocReenvio = nome;
            const nomeDocElement = document.getElementById('nome_doc_reenvio');
            const modalElement = document.getElementById('modal_reenvio');
            const obsReenvio = document.getElementById('obs_reenvio');
            const resultadoDiv = document.getElementById('resultado_reenvio');
            if (!nomeDocElement || !modalElement || !obsReenvio || !resultadoDiv) {
                alert('Erro ao abrir modal.');
                return;
            }
            nomeDocElement.textContent = nome;
            obsReenvio.value = '';
            resultadoDiv.textContent = '';
            resultadoDiv.style.display = 'none';
            modalElement.style.display = 'block';
        }

        function fecharModalReenvio() {
            const modalElement = document.getElementById('modal_reenvio');
            if (modalElement) {
                modalElement.style.display = 'none';
            }
        }

        function confirmarReenvio() {
            if (!docIdReenvio) return;

            // Semelhante a validarDocumento, sempre envia para atualizar_documento.php
            const urlEndpoint = 'atualizar_documento.php';

            const obsReenvio = document.getElementById('obs_reenvio');
            const resultadoDiv = document.getElementById('resultado_reenvio');
            const observacao = obsReenvio ? obsReenvio.value.trim() : '';
            const dados = {
                doc_id: docIdReenvio,
                acao: 'reenviar',
                observacao: observacao
            };
            fetch(urlEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dados)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const statusCell = document.getElementById(`status_doc_${docIdReenvio}`);
                    const obsCell = document.getElementById(`obs_doc_${docIdReenvio}`);
                    if (statusCell) {
                        statusCell.innerHTML = `<span class="status-invalido">${data.novo_status}</span>`;
                    }
                    if (obsCell) {
                        obsCell.textContent = data.nova_obs;
                    }
                    resultadoDiv.className = 'success';
                    resultadoDiv.textContent = data.message;
                    resultadoDiv.style.display = 'block';
                    setTimeout(() => { fecharModalReenvio(); }, 1200);
                } else {
                    resultadoDiv.className = 'error';
                    resultadoDiv.textContent = data.message || 'Erro ao solicitar reenvio.';
                    resultadoDiv.style.display = 'block';
                }
            })
            .catch(() => {
                resultadoDiv.className = 'error';
                resultadoDiv.textContent = 'Erro de rede ou servidor.';
                resultadoDiv.style.display = 'block';
            });
        }

        // Fecha modal se clicar fora dela
        window.onclick = function(event) {
            const modal = document.getElementById('modal_reenvio');
            if (event.target === modal) {
                fecharModalReenvio();
            }
        };
    </script>
</body>
</html>