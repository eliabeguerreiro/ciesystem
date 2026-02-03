<?php
// Sem sessão, sem autenticação — acesso público
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/Estudante.php';
require_once __DIR__ . '/app/models/Inscricao.php';

$database = new Database();
$db = $database->getConnection();

$estudanteModel = new Estudante($db);
$inscricaoModel = new Inscricao($db);

$erro = '';
$sucesso = '';
$codigoGerado = '';

// ================================
// PROCESSAMENTO DO FORMULÁRIO
// ================================
if ($_POST) {
    // Valida campos obrigatórios
    $camposObrigatorios = ['nome', 'data_nascimento', 'cpf', 'documento_tipo', 'documento_numero', 
                          'instituicao', 'curso', 'nivel', 'matricula'];
    foreach ($camposObrigatorios as $campo) {
        if (empty($_POST[$campo])) {
            $erro = "O campo '$campo' é obrigatório.";
            break;
        }
    }

    // Verifica se comprovante de matrícula foi enviado
    if (empty($_FILES['comprovante_matricula']['name'])) {
        $erro = "Comprovante de matrícula é obrigatório.";
    }

    if (empty($erro)) {
        // Verifica se CPF ou matrícula já existem
        $stmt = $db->prepare("SELECT id FROM estudantes WHERE cpf = ? OR matricula = ?");
        $stmt->execute([$_POST['cpf'], $_POST['matricula']]);
        if ($stmt->fetch()) {
            $erro = "CPF ou matrícula já cadastrados. Entre em contato com a administração.";
        } else {
            // Cria estudante
            $estudante = new Estudante($db);
            $estudante->nome = $_POST['nome'];
            $estudante->data_nascimento = $_POST['data_nascimento'];
            $estudante->cpf = $_POST['cpf'];
            $estudante->documento_tipo = $_POST['documento_tipo'];
            $estudante->documento_numero = $_POST['documento_numero'];
            $estudante->documento_orgao = $_POST['documento_orgao'] ?? '';
            $estudante->instituicao = $_POST['instituicao'];
            $estudante->campus = $_POST['campus'] ?? '';
            $estudante->curso = $_POST['curso'];
            $estudante->nivel = $_POST['nivel'];
            $estudante->matricula = $_POST['matricula'];
            $estudante->situacao_academica = $_POST['situacao_academica'] ?? 'Matriculado';
            $estudante->email = $_POST['email'] ?? '';
            $estudante->telefone = $_POST['telefone'] ?? '';
            $estudante->status_validacao = 'pendente'; // ← diferente do cadastro manual

            if ($estudante->criar()) {
                $estudanteId = $db->lastInsertId();

                // Cria inscrição
                $inscricao = new Inscricao($db);
                $inscricao->estudante_id = $estudanteId;
                $inscricao->criar(); // cria com status 'aguardando_validacao'

                // Obter ID da inscrição
                $stmt = $db->prepare("SELECT id, codigo_inscricao FROM inscricoes WHERE estudante_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$estudanteId]);
                $resultado = $stmt->fetch();
                if ($resultado) {
                    $inscricaoId = $resultado['id'];
                    $codigoGerado = $resultado['codigo_inscricao'];

                    // Salvar comprovante de matrícula
                    if (!empty($_FILES['comprovante_matricula']['name'])) {
                        $inscTemp = new Inscricao($db);
                        $inscTemp->id = $inscricaoId;
                        $inscTemp->salvarDocumentos($_FILES['comprovante_matricula'], 'matricula');
                    }

                    $sucesso = "Inscrição realizada com sucesso!";
                } else {
                    $erro = "Erro ao gerar inscrição.";
                }
            } else {
                $erro = "Erro ao cadastrar seus dados.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Inscrição para CIE</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 20px; background: #f9f9f9; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h2 { color: #1976d2; text-align: center; }
        .mensagem { padding: 10px; margin: 15px 0; border-radius: 4px; }
        .erro { background: #ffebee; color: #c62828; }
        .sucesso { background: #e8f5e9; color: #2e7d32; }
        .form-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; min-width: 200px; }
        label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 0.9em; }
        input, select { width: 10; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #1976d2; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; margin-top: 10px; }
        button:hover { background: #1565c0; }
        a { color: #1976d2; text-decoration: none; display: inline-block; margin-top: 20px; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Inscrição para Carteira Estudantil (CIE)</h2>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso">
                <?= htmlspecialchars($sucesso) ?><br>
                <strong>Seu código de inscrição:</strong> <?= htmlspecialchars($codigoGerado) ?><br>
                <small>Guarde este código para acompanhar seu status.</small>
            </div>
            <a href="acompanhar.php">Acompanhar minha inscrição</a>
        <?php else: ?>
            <?php if ($erro): ?>
                <div class="mensagem erro"><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <!-- Dados Civis -->
                <h3>Dados Pessoais</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome Completo *</label>
                        <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Data de Nascimento *</label>
                        <input type="date" name="data_nascimento" value="<?= htmlspecialchars($_POST['data_nascimento'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>CPF *</label>
                        <input type="text" name="cpf" value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>" placeholder="000.000.000-00" required>
                    </div>
                    <div class="form-group">
                        <label>Tipo de Documento *</label>
                        <select name="documento_tipo" required>
                            <option value="RG" <?= ($_POST['documento_tipo'] ?? '') === 'RG' ? 'selected' : '' ?>>RG</option>
                            <option value="CNH" <?= ($_POST['documento_tipo'] ?? '') === 'CNH' ? 'selected' : '' ?>>CNH</option>
                            <option value="PASSAPORTE" <?= ($_POST['documento_tipo'] ?? '') === 'PASSAPORTE' ? 'selected' : '' ?>>Passaporte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Número do Documento *</label>
                        <input type="text" name="documento_numero" value="<?= htmlspecialchars($_POST['documento_numero'] ?? '') ?>" required>
                    </div>
                </div>

                <!-- Dados Acadêmicos -->
                <h3>Dados Acadêmicos</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Instituição *</label>
                        <input type="text" name="instituicao" value="<?= htmlspecialchars($_POST['instituicao'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Campus</label>
                        <input type="text" name="campus" value="<?= htmlspecialchars($_POST['campus'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Curso *</label>
                        <input type="text" name="curso" value="<?= htmlspecialchars($_POST['curso'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nível *</label>
                        <input type="text" name="nivel" value="<?= htmlspecialchars($_POST['nivel'] ?? '') ?>" placeholder="Ex: Técnico, Superior" required>
                    </div>
                    <div class="form-group">
                        <label>Matrícula *</label>
                        <input type="text" name="matricula" value="<?= htmlspecialchars($_POST['matricula'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Situação Acadêmica</label>
                    <select name="situacao_academica">
                        <option value="Matriculado" <?= ($_POST['situacao_academica'] ?? 'Matriculado') === 'Matriculado' ? 'selected' : '' ?>>Matriculado</option>
                        <option value="Trancado" <?= ($_POST['situacao_academica'] ?? '') === 'Trancado' ? 'selected' : '' ?>>Trancado</option>
                        <option value="Formado" <?= ($_POST['situacao_academica'] ?? '') === 'Formado' ? 'selected' : '' ?>>Formado</option>
                    </select>
                </div>

                <!-- Contato -->
                <h3>Contato (Opcional)</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" placeholder="(00) 00000-0000">
                    </div>
                </div>

                <!-- Comprovante de Matrícula -->
                <h3>Comprovante de Matrícula *</h3>
                <div class="form-group">
                    <label>Anexe seu comprovante (JPG, PNG ou PDF)</label>
                    <input type="file" name="comprovante_matricula" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>

                <button type="submit">Enviar Inscrição</button>
            </form>
            <a href="index.php">← Voltar</a>
        <?php endif; ?>
    </div>
</body>
</html>