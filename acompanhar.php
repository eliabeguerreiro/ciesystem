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
$documentos = []; // Array para armazenar os documentos

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

        // Se encontrou a inscrição, buscar os documentos associados
        if ($resultado) {
            $inscricaoId = $resultado['id'];
            // Criar uma instância temporária do modelo Inscricao para buscar os docs
            $tempInscricaoModel = new Inscricao($db);
            $tempInscricaoModel->id = $inscricaoId; // Define o ID
            $documentos = $tempInscricaoModel->getDocumentos(); // Obtém os documentos
        }
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
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhar Inscrição - CIE</title>
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
            --border-color: #ddd;
            --shadow: 0 4px 6px rgba(0,0,0,0.05);
            --shadow-hover: 0 8px 15px rgba(0,0,0,0.1);
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
            max-width: 900px;
            margin: 40px auto;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        .header h2 {
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .content {
            padding: 40px;
        }

        /* Formulário de Consulta */
        .search-form {
            max-width: 500px;
            margin: 0 auto;
            text-align: left;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-color);
        }

        input[type="text"],
        input[type="date"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #fafafa;
            font-family: inherit;
        }

        input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 4px rgba(25, 118, 210, 0.15);
            background-color: #fff;
        }

        .btn-submit {
            display: block;
            width: 100%;
            padding: 14px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px;
            box-shadow: 0 4px 6px rgba(25, 118, 210, 0.2);
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(25, 118, 210, 0.3);
        }

        /* Mensagens de Erro */
        .erro {
            background-color: #ffebee;
            color: var(--error-color);
            padding: 15px;
            border-radius: 8px;
            border-left: 5px solid var(--error-color);
            margin-bottom: 25px;
            animation: fadeIn 0.5s ease-in-out;
        }

        /* Card de Status */
        .status-card {
            background: #f9fbfd;
            border: 1px solid #e3f2fd;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
            animation: fadeInUp 0.6s ease-out;
        }

        .status-card h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.6rem;
        }

        .status-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            text-align: left;
        }

        .status-info-item strong {
            display: block;
            color: var(--light-text);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .status-info-item span {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--text-color);
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Cores de Status Dinâmicas */
        .status-aguardando_validacao, .status-dados_aprovados, .status-pagamento_pendente {
            background-color: #fff3e0; color: var(--warning-color); border: 1px solid #ffe0b2;
        }
        .status-documentos_anexados, .status-cie_emitida_aguardando_entrega {
            background-color: #efebe9; color: #5d4037; border: 1px solid #d7ccc8;
        }
        .status-pago, .status-cie_entregue_na_instituicao {
            background-color: #e8f5e9; color: var(--success-color); border: 1px solid #c8e6c9;
        }

        .status-message {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 6px;
            border-left: 4px solid var(--primary-color);
            color: var(--light-text);
            font-style: italic;
        }

        /* Botão de Pagamento */
        .btn-pagamento {
            display: inline-block;
            background: linear-gradient(135deg, var(--success-color), #1b5e20);
            color: white;
            padding: 14px 28px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1.1rem;
            margin-top: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(46, 125, 50, 0.25);
            text-transform: uppercase;
        }

        .btn-pagamento:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(46, 125, 50, 0.35);
            filter: brightness(1.1);
        }

        /* Tabela de Documentos */
        .docs-section {
            margin-top: 40px;
            animation: fadeInUp 0.6s ease-out 0.2s backwards;
        }

        .docs-section h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 1.4rem;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #fcfcfc; }

        .doc-status-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-validado { background-color: #e8f5e9; color: var(--success-color); }
        .status-pendente { background-color: #fff3e0; color: var(--warning-color); }
        .status-invalido { background-color: #ffebee; color: var(--error-color); }

        .btn-ver {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        .btn-ver:hover { color: var(--primary-dark); text-decoration: underline; }

        .btn-reenviar {
            background-color: var(--error-color);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.3s;
        }
        .btn-reenviar:hover { background-color: #b71c1c; }

        .back-link {
            display: inline-block;
            margin-top: 30px;
            color: var(--light-text);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        .back-link:hover { color: var(--primary-color); }

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
        @keyframes slideDown { from {transform: translateY(-50px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }
        .close:hover { color: #000; }

        .upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
            transition: all 0.3s;
            background: #fafafa;
        }
        .upload-area:hover { border-color: var(--primary-color); background: #f0f7ff; }

        .btn-modal {
            display: block;
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s;
        }
        .btn-upload { background-color: var(--primary-color); color: white; }
        .btn-upload:hover { background-color: var(--primary-dark); }
        .btn-send { background-color: var(--success-color); color: white; margin-top: 15px; }
        .btn-send:hover { background-color: #1b5e20; }
        .btn-cancel { background-color: #f5f5f5; color: #555; margin-top: 10px; }
        .btn-cancel:hover { background-color: #e0e0e0; }

        #previewContainer { margin-top: 15px; text-align: center; }
        #previewContainer img { max-width: 150px; max-height: 150px; border-radius: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Responsividade */
        @media (max-width: 600px) {
            .content { padding: 20px; }
            .header { padding: 30px 15px; }
            .header h2 { font-size: 1.6rem; }
            .status-info-grid { grid-template-columns: 1fr; }
            th, td { padding: 10px; font-size: 0.9rem; }
            .btn-pagamento { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

    <div class="main-container">
        <div class="header">
            <h2>Acompanhar Minha Inscrição</h2>
            <p>Consulte o status da sua Carteira Estudantil (CIE)</p>
        </div>

        <div class="content">
            <?php if ($resultado): ?>
                <!-- Card de Status -->
                <div class="status-card">
                    <h3>Olá, <?= htmlspecialchars($resultado['nome']) ?>!</h3>
                    
                    <div class="status-info-grid">
                        <div class="status-info-item">
                            <strong>Código da Inscrição</strong>
                            <span><?= htmlspecialchars($resultado['codigo_inscricao']) ?></span>
                        </div>
                        <div class="status-info-item">
                            <strong>Matrícula</strong>
                            <span><?= htmlspecialchars($resultado['matricula']) ?></span>
                        </div>
                        <div class="status-info-item">
                            <strong>Status Atual</strong>
                            <br>
                            <span class="status-badge status-<?= $resultado['situacao'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $resultado['situacao'])) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Mensagens Orientativas -->
                    <div class="status-message">
                        <?php
                        switch ($resultado['situacao']) {
                            case 'aguardando_validacao':
                                echo 'Sua inscrição foi recebida e está em análise. Aguarde a validação dos dados e documentos.';
                                break;
                            case 'dados_aprovados':
                                echo 'Seus dados foram aprovados. Aguarde instruções para pagamento.';
                                break;
                            case 'pagamento_pendente':
                                echo 'Seus dados foram aprovados. Realize o pagamento para continuar.';
                                break;
                            case 'documentos_anexados':
                                echo 'Documentos recebidos. Aguardando confirmação de pagamento.';
                                break;
                            case 'pago':
                                echo '<strong>Pagamento confirmado!</strong> Sua CIE está sendo preparada para emissão.';
                                break;
                            case 'cie_emitida_aguardando_entrega':
                                echo '<strong>Sua CIE foi emitida!</strong> Agora está aguardando logística de entrega para a instituição.';
                                break;
                            case 'cie_entregue_na_instituicao':
                                echo '<strong>Sua CIE foi entregue na instituição!</strong> Entre em contato com a secretaria para retirada.';
                                break;
                            default:
                                echo 'Em processamento...';
                        }
                        ?>
                    </div>

                    <!-- Botão de Pagamento -->
                    <?php if (!$resultado['pagamento_confirmado'] && !in_array($resultado['situacao'], ['pago', 'cie_emitida_aguardando_entrega', 'cie_entregue_na_instituicao'])): ?>
                        <a href="pagamento_mp.php?codigo=<?= urlencode($resultado['codigo_inscricao']) ?>&data_nascimento=<?= urlencode($resultado['data_nascimento']) ?>" class="btn-pagamento">
                            💳 Realizar Pagamento
                        </a>
                    <?php elseif ($resultado['pagamento_confirmado'] && in_array($resultado['situacao'], ['aguardando_validacao', 'dados_aprovados', 'pagamento_pendente', 'documentos_anexados'])): ?>
                        <p style="color: var(--success-color); font-weight: 600; margin-top: 15px;">
                            ✅ Pagamento confirmado. Aguarde a atualização automática do status.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Seção de Documentos -->
                <div class="docs-section">
                    <h3>📄 Seus Documentos</h3>
                    <?php if (!empty($documentos)): ?>
                        <div class="table-responsive">
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
                                    <tr>
                                        <td><?= htmlspecialchars($doc['descricao']) ?></td>
                                        <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['tipo']))) ?></td>
                                        <td>
                                            <span class="doc-status-badge status-<?= $doc['validado'] ?>">
                                                <?= ucfirst($doc['validado']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($doc['validado'] === 'invalido' && !empty($doc['observacoes_validacao'])): ?>
                                                <span style="color: var(--error-color); font-size: 0.9em;">
                                                    <strong>⚠️ Reenvio:</strong><br>
                                                    <?= htmlspecialchars($doc['observacoes_validacao']) ?>
                                                </span>
                                            <?php elseif (!empty($doc['observacoes_validacao'])): ?>
                                                <span style="font-size: 0.9em; color: #666;"><?= htmlspecialchars($doc['observacoes_validacao']) ?></span>
                                            <?php else: ?>
                                                <span style="color: #ccc;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="public/<?= htmlspecialchars($doc['caminho_arquivo']) ?>" target="_blank" class="btn-ver">️ Ver</a>
                                        </td>
                                        <td>
                                            <?php if ($doc['validado'] === 'invalido'): ?>
                                                <button class="btn-reenviar" onclick="abrirModalReenvio(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['descricao'])) ?>')">
                                                    🔄 Reenviar
                                                </button>
                                            <?php else: ?>
                                                <span style="color: #ccc;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p style="margin-top: 15px; font-size: 0.9em; color: var(--light-text);">
                            ℹ️ Se algum documento estiver marcado como <strong style="color: var(--error-color);">Inválido</strong>, utilize o botão "Reenviar" para enviar um novo arquivo.
                        </p>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--light-text); padding: 20px; background: #f9f9f9; border-radius: 8px;">
                            Nenhum documento foi anexado à sua inscrição ainda.
                        </p>
                    <?php endif; ?>
                </div>

                <div style="text-align: center;">
                    <a href="index.php" class="back-link">← Voltar para a página inicial</a>
                </div>

            <?php else: ?>
                <!-- Formulário de Consulta -->
                <?php if ($erro): ?>
                    <div class="erro">
                        <strong>⚠️ Atenção:</strong> <?= htmlspecialchars($erro) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="search-form">
                    <div class="form-group">
                        <label for="codigo">Código de Inscrição *</label>
                        <input type="text" id="codigo" name="codigo" placeholder="Ex: a1b2c3d4-e5f6..." value="<?= htmlspecialchars($_POST['codigo'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="data_nascimento">Data de Nascimento *</label>
                        <input type="date" id="data_nascimento" name="data_nascimento" value="<?= htmlspecialchars($_POST['data_nascimento'] ?? '') ?>" required>
                    </div>
                    <button type="submit" class="btn-submit">Consultar Status</button>
                </form>

                <div style="text-align: center;">
                    <a href="index.php" class="back-link">← Voltar para a página inicial</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Reenvio -->
    <div id="modal_reenvio" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal()">&times;</span>
            <h3 id="titulo_modal_reenvio" style="color: var(--primary-color); margin-bottom: 10px;">Reenviar Documento</h3>
            <p style="color: var(--light-text);">Documento: <strong id="nome_doc_reenvio"></strong></p>
            
            <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                <p style="margin-bottom: 10px; font-weight: 500;">Clique para selecionar o arquivo</p>
                <input type="file" id="fileInput" accept=".jpg,.jpeg,.png,.pdf" onchange="previewFile()" style="display: none;">
                <div id="previewContainer"></div>
            </div>
            
            <button class="btn-modal btn-upload" onclick="document.getElementById('fileInput').click()">
                📁 Escolher Arquivo
            </button>
            <button class="btn-modal btn-send" onclick="enviarReenvio()">
                ✅ Enviar Reenvio
            </button>
            <button class="btn-modal btn-cancel" onclick="fecharModal()">
                Cancelar
            </button>
        </div>
    </div>

    <script>
        let docIdReenvio = null;

        function abrirModalReenvio(id, nome) {
            docIdReenvio = id;
            document.getElementById('nome_doc_reenvio').textContent = nome;
            document.getElementById('modal_reenvio').style.display = 'block';
            document.getElementById('previewContainer').innerHTML = '';
            document.getElementById('fileInput').value = '';
        }

        function fecharModal() {
            document.getElementById('modal_reenvio').style.display = 'none';
        }

        function previewFile() {
            const fileInput = document.getElementById('fileInput');
            const file = fileInput.files[0];
            const container = document.getElementById('previewContainer');

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (file.type.startsWith('image/')) {
                        container.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    } else {
                        container.innerHTML = `<p style="font-weight:500; color:var(--primary-color);">📄 ${file.name}</p>`;
                    }
                };
                reader.readAsDataURL(file);
            } else {
                container.innerHTML = '';
            }
        }

        function enviarReenvio() {
            const fileInput = document.getElementById('fileInput');
            const file = fileInput.files[0];

            if (!file) {
                alert('Por favor, selecione um arquivo antes de enviar.');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const dados = {
                    doc_id: docIdReenvio,
                    arquivo: e.target.result
                };

                // Feedback visual de envio
                const btnSend = document.querySelector('.btn-send');
                const originalText = btnSend.textContent;
                btnSend.textContent = '⏳ Enviando...';
                btnSend.disabled = true;

                fetch('public/reenviar_documento.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dados)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message);
                        fecharModal();
                        location.reload(); // Recarrega para mostrar o novo status
                    } else {
                        alert('❌ Erro: ' + (data.message || 'Falha ao reenviar documento.'));
                        btnSend.textContent = originalText;
                        btnSend.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('❌ Erro de conexão. Tente novamente.');
                    btnSend.textContent = originalText;
                    btnSend.disabled = false;
                });
            };
            reader.readAsDataURL(file);
        }

        // Fecha modal se clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('modal_reenvio');
            if (event.target === modal) {
                fecharModal();
            }
        };
    </script>
</body>
</html>