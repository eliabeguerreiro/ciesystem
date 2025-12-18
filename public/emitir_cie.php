<?php
session_start();

// Verificação de acesso
require_once __DIR__ . '/../app/controllers/AuthController.php';
$auth = new AuthController();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    die("Acesso negado.");
}

// Carrega dependências
require_once __DIR__ . '/../app/models/Estudante.php';
require_once __DIR__ . '/../app/models/Carteirinha.php';
require_once __DIR__ . '/../app/controllers/CarteirinhaController.php';

$database = new Database();
$db = $database->getConnection();

$estudanteModel = new Estudante($db);
$carteirinhaCtrl = new CarteirinhaController($db);

$erro = '';
$sucesso = '';
$codigoGerado = '';

// ================================
// LISTA ESTUDANTES PARA SELEÇÃO
// ================================
$estudantes = $estudanteModel->listar();

// ================================
// PROCESSAMENTO DO FORMULÁRIO
// ================================
if ($_POST) {
    $estudante_id = (int)($_POST['estudante_id'] ?? 0);
    if (!$estudante_id) {
        $erro = "Selecione um estudante.";
    } else {
        // Verifica se já existe CIE ativa para esse estudante
        $stmt = $db->prepare("SELECT id FROM carteirinhas WHERE estudante_id = ? AND situacao = 'ativa'");
        $stmt->execute([$estudante_id]);
        if ($stmt->fetch()) {
            $erro = "Este estudante já possui uma CIE ativa.";
        } else {
            // Cria instância da carteirinha
            $carteirinha = new Carteirinha($db);
            $carteirinha->estudante_id = $estudante_id;

            if ($carteirinha->criar()) {
                // Obter o ID e código da CIE recém-criada
                $stmt = $db->prepare("SELECT id, cie_codigo FROM carteirinhas WHERE estudante_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$estudante_id]);
                $resultado = $stmt->fetch();
                if ($resultado) {
                    $carteirinha->id = $resultado['id'];
                    $codigoGerado = $resultado['cie_codigo'];

                    // Salvar documentos de matrícula (múltiplos)
                    if (!empty($_POST['anexar_matricula']) && !empty($_FILES['comprovante_matricula']['name'][0])) {
                        $carteirinha->salvarDocumentos($_FILES['comprovante_matricula'], 'matricula');
                    }

                    // Salvar documentos de pagamento (múltiplos, opcional)
                    if (!empty($_POST['anexar_pagamento']) && !empty($_FILES['comprovante_pagamento']['name'][0])) {
                        $carteirinha->salvarDocumentos($_FILES['comprovante_pagamento'], 'pagamento');
                    }

                    // === LOG: CIE emitida ===
                    require_once __DIR__ . '/../app/models/Log.php';
                    $log = new Log($db);
                    $log->registrar(
                        $_SESSION['user_id'],
                        'emitiu_cie',
                        "Estudante ID: {$estudante_id}, Código CIE: {$codigoGerado}",
                        $carteirinha->id,
                        'carteirinhas'
                    );

                    $sucesso = "CIE emitida com sucesso!";
                } else {
                    $erro = "Erro ao recuperar dados da CIE emitida.";
                }
            } else {
                $erro = "Erro ao emitir CIE.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Emitir Carteira Estudantil (CIE)</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f9f9f9; }
        .container { max-width: 800px; margin: 0 auto; }
        h2 { color: #333; }
        .mensagem { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .erro { background: #ffebee; color: #c62828; }
        .sucesso { background: #e8f5e9; color: #2e7d32; }
        form { background: white; padding: 20px; border-radius: 6px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 0.95em; }
        select, input[type="file"] { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; margin: 10px 0; }
        .checkbox-group input { width: auto; }
        button { background: #2e7d32; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #1b5e20; }
        a { color: #1976d2; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .voltar { display: inline-block; margin-bottom: 20px; color: #555; }
        .codigo-gerado { 
            background: #e3f2fd; padding: 12px; border-radius: 4px; 
            font-family: monospace; font-size: 1.1em; 
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="voltar">← Voltar ao Dashboard</a>
        <h2>Emitir Nova Carteira Estudantil (CIE)</h2>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><?= htmlspecialchars($sucesso) ?></div>
            <?php if ($codigoGerado): ?>
                <div class="codigo-gerado">
                    <strong>Código da CIE gerado:</strong><br>
                    <?= htmlspecialchars($codigoGerado) ?>
                </div>
                <p style="margin-top: 10px; color: #555; font-size: 0.9em;">
                    Data de validade: <strong>03/03/2027</strong>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Selecione o Estudante *</label>
                <select name="estudante_id" required>
                    <option value="">-- Escolha um estudante --</option>
                    <?php foreach ($estudantes as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= (isset($_POST['estudante_id']) && $_POST['estudante_id'] == $e['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nome']) ?> (Matrícula: <?= htmlspecialchars($e['matricula']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Comprovante de Matrícula (MÚLTIPLOS ARQUIVOS) -->
            <div class="checkbox-group">
                <input type="checkbox" name="anexar_matricula" id="anexar_matricula">
                <label for="anexar_matricula" style="display:inline; font-weight:normal;">
                    Anexar comprovante(s) de matrícula (JPG, PNG ou PDF - vários arquivos permitidos)
                </label>
            </div>
            <div id="upload_matricula" style="display:none; margin-left: 24px; margin-bottom: 15px;">
                <input type="file" name="comprovante_matricula[]" multiple accept=".jpg,.jpeg,.png,.pdf">
            </div>

            <!-- Comprovante de Pagamento (MÚLTIPLOS ARQUIVOS, OPCIONAL) -->
            <div class="checkbox-group">
                <input type="checkbox" name="anexar_pagamento" id="anexar_pagamento">
                <label for="anexar_pagamento" style="display:inline; font-weight:normal;">
                    Anexar comprovante(s) de pagamento (opcional, vários arquivos)
                </label>
            </div>
            <div id="upload_pagamento" style="display:none; margin-left: 24px; margin-bottom: 15px;">
                <input type="file" name="comprovante_pagamento[]" multiple accept=".jpg,.jpeg,.png,.pdf">
            </div>

            <button type="submit">Emitir CIE</button>
        </form>

        <!-- Script para mostrar/esconder uploads -->
        <script>
            document.getElementById('anexar_matricula').addEventListener('change', function() {
                document.getElementById('upload_matricula').style.display = this.checked ? 'block' : 'none';
            });
            document.getElementById('anexar_pagamento').addEventListener('change', function() {
                document.getElementById('upload_pagamento').style.display = this.checked ? 'block' : 'none';
            });
        </script>
    </div>
</body>
</html>