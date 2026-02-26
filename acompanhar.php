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
<html>
<head>
    <meta charset="UTF-8">
    <title>Acompanhar Inscrição</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 20px; background: #f9f9f9; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h2 { color: #1976d2; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 4px; font-weight: bold; }
        input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #1976d2; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; }
        button:hover { background: #1565c0; }
        .status-box { background: #f5f5f5; padding: 15px; border-radius: 6px; margin-top: 20px; }
        .status-pendente,
        .status-aguardando_validacao { color: #f57c00; }
        .status-dados_aprovados { color: #f57c00; font-weight: bold; }
        .status-pagamento_pendente { color: #f57c00; font-weight: bold; }
        .status-documentos_anexados { color: #5d4037; font-weight: bold; }
        .status-pago { color: #2e7d32; font-weight: bold; }
        .status-cie_emitida_aguardando_entrega { color: #5d4037; font-weight: bold; }
        .status-cie_entregue_na_instituicao { color: #2e7d32; font-weight: bold; }
        a { color: #1976d2; text-decoration: none; display: inline-block; margin-top: 20px; }
        a:hover { text-decoration: underline; }
        .erro { background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f5f5f5; }
        .status-invalido { color: #c62828; font-weight: bold; }
        .btn-reenviar { background-color: #c62828; color: white; border: none; padding: 4px 8px; cursor: pointer; margin-left: 5px; }
        .btn-reenviar:hover { background-color: #a51b1b; }

        /* Estilo da Modal de Reenvio */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
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

        #fileInput { display: none; }
        .upload-btn {
            background-color: #1976d2;
            color: white;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            margin: 5px 0;
        }
        .upload-btn:hover { background-color: #1565c0; }

        /* --- NOVO: Estilo para o botão PIX --- */
        .btn-pix {
            display: inline-block;
            background-color: #2e7d32; /* Verde Escuro */
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
            font-weight: bold;
            transition: background-color 0.3s;
            width: auto; /* Permite que o botão cresça com o texto */
        }
        .btn-pix:hover {
            background-color: #1b5e20; /* Verde mais escuro no hover */
        }
        /* --- FIM NOVO --- */
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
                        echo '<p style="color:#f57c00;">Sua inscrição foi recebida e está em análise. Aguarde a validação dos dados e documentos.</p>';
                        break;
                    case 'dados_aprovados':
                        echo '<p style="color:#f57c00;">Seus dados foram aprovados. Aguarde instruções para pagamento.</p>';
                        break;
                    case 'pagamento_pendente':
                        echo '<p style="color:#f57c00;">Seus dados foram aprovados. Aguarde confirmação do pagamento.</p>';
                        // --- BOTÃO PARA REALIZAR PAGAMENTO ---
                        // Verificar se o pagamento ainda não foi confirmado
                        if (!$resultado['pagamento_confirmado']) {
                             echo '<a href="pagamento_pix.php?codigo=' . urlencode($resultado['codigo_inscricao']) . '&data_nascimento=' . urlencode($resultado['data_nascimento']) . '" class="btn-pix">Realizar Pagamento via PIX</a>';
                        } else {
                             echo '<p style="color:#2e7d32;">Pagamento confirmado (aguardando logística).</p>';
                        }
                        // --- FIM BOTÃO ---
                        break;
                    case 'documentos_anexados':
                        echo '<p style="color:#5d4037;">Documentos recebidos. Aguardando confirmação de pagamento.</p>';
                        // --- BOTÃO PARA REALIZAR PAGAMENTO ---
                        // Verificar se o pagamento ainda não foi confirmado
                        if (!$resultado['pagamento_confirmado']) {
                             echo '<a href="pagamento_pix.php?codigo=' . urlencode($resultado['codigo_inscricao']) . '&data_nascimento=' . urlencode($resultado['data_nascimento']) . '" class="btn-pix">Realizar Pagamento via PIX</a>';
                        } else {
                             echo '<p style="color:#2e7d32;">Pagamento confirmado (aguardando logística).</p>';
                        }
                        // --- FIM BOTÃO ---
                        break;
                    case 'pago':
                        echo '<p style="color:#2e7d32;">Pagamento confirmado! Sua CIE está sendo preparada para emissão.</p>';
                        break;
                    case 'cie_emitida_aguardando_entrega':
                        echo '<p style="color:#5d4037;"><strong>Sua CIE foi emitida!</strong> Agora está aguardando logística de entrega para a instituição.</p>';
                        break;
                    case 'cie_entregue_na_instituicao':
                        echo '<p style="color:#2e7d32;"><strong>Sua CIE foi entregue na instituição!</strong> Entre em contato com a secretaria para retirada.</p>';
                        break;
                    default:
                        echo '<p>Em processamento...</p>';
                }
                ?>
            </div>

            <!-- Seção de Documentos -->
            <?php if (!empty($documentos)): ?>
                <h3>Seus Documentos</h3>
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
                                <span class="status-<?= $doc['validado'] ?>">
                                    <?= ucfirst($doc['validado']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($doc['validado'] === 'invalido' && !empty($doc['observacoes_validacao'])): ?>
                                    <strong style="color: #c62828;">Solicitação de Reenvio:</strong><br>
                                    <?= htmlspecialchars($doc['observacoes_validacao']) ?>
                                <?php elseif (!empty($doc['observacoes_validacao'])): ?>
                                    <?= htmlspecialchars($doc['observacoes_validacao']) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="public/<?= htmlspecialchars($doc['caminho_arquivo']) ?>" target="_blank">Ver</a>
                            </td>
                            <td>
                                <?php if ($doc['validado'] === 'invalido'): ?>
                                    <button class="btn-reenviar" onclick="abrirModalReenvio(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['descricao'])) ?>')">Reenviar</button>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><small>Se algum documento tiver status <span class="status-invalido">Inválido</span>, você pode reenviá-lo usando o botão "Reenviar".</small></p>
            <?php else: ?>
                <h3>Seus Documentos</h3>
                <p>Nenhum documento foi anexado à sua inscrição ainda.</p>
            <?php endif; ?>
            <!-- Fim Seção de Documentos -->

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
                <button type="submit">Consultar Status</button>
            </form>
            <a href="index.php">← Voltar ao início</a>
        <?php endif; ?>
    </div>

    <!-- Modal de Reenvio -->
    <div id="modal_reenvio" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal()">×</span>
            <h3 id="titulo_modal_reenvio">Reenviar: <span id="nome_doc_reenvio"></span></h3>
            <p>Selecione um novo arquivo para este documento.</p>
            <input type="file" id="fileInput" accept=".jpg,.jpeg,.png,.pdf" onchange="previewFile()">
            <button class="upload-btn" onclick="document.getElementById('fileInput').click()">Escolher Arquivo</button>
            <div id="previewContainer" style="margin-top: 10px;"></div>
            <button class="upload-btn" onclick="enviarReenvio()" style="background-color: #2e7d32;">Enviar Reenvio</button>
            <button class="upload-btn" onclick="fecharModal()" style="background-color: #555; margin-top: 10px;">Cancelar</button>
        </div>
    </div>

    <script>
        let docIdReenvio = null;

        function abrirModalReenvio(id, nome) {
            docIdReenvio = id;
            document.getElementById('nome_doc_reenvio').textContent = nome;
            document.getElementById('modal_reenvio').style.display = 'block';
        }

        function fecharModal() {
            document.getElementById('modal_reenvio').style.display = 'none';
            document.getElementById('fileInput').value = '';
            document.getElementById('previewContainer').innerHTML = '';
        }

        function previewFile() {
            const fileInput = document.getElementById('fileInput');
            const file = fileInput.files[0];
            const container = document.getElementById('previewContainer');

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (file.type.startsWith('image/')) {
                        container.innerHTML = `<img src="${e.target.result}" style="max-width: 100px; max-height: 100px;">`;
                    } else {
                        container.innerHTML = `<span>${file.name}</span>`;
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
                alert('Por favor, selecione um arquivo.');
                return;
            }

            // Converter o arquivo para base64
            const reader = new FileReader();
            reader.onload = function(e) {
                const base64Data = e.target.result.split(',')[1]; // Remove o cabeçalho "data:..."
                const dados = {
                    doc_id: docIdReenvio,
                    arquivo: e.target.result // Enviar o data URL completo
                };

                fetch('public/reenviar_documento.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(dados)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        fecharModal();
                        // Opcional: Recarregar a página para atualizar a lista de documentos
                        // location.reload();
                    } else {
                        alert(data.message || 'Erro ao reenviar documento.');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro de rede ou servidor.');
                });
            };
            reader.readAsDataURL(file);
        }

        // Fecha modal se clicar fora dela
        window.onclick = function(event) {
            const modal = document.getElementById('modal_reenvio');
            if (event.target === modal) {
                fecharModal();
            }
        };
    </script>
</body>
</html>