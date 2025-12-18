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
require_once __DIR__ . '/../app/controllers/EstudanteController.php';

$database = new Database();
$db = $database->getConnection();
$estudanteCtrl = new EstudanteController($db);
$estudante = new Estudante($db);

$erro = '';
$sucesso = '';

// ================================
// TRATAMENTO DE FORMULÁRIO (CADASTRO/EDIÇÃO)
// ================================

if ($_POST) {
    // Sanitiza dados simples
    $estudante->nome = $_POST['nome'] ?? '';
    $estudante->data_nascimento = $_POST['data_nascimento'] ?? '';
    $estudante->cpf = $_POST['cpf'] ?? '';
    $estudante->documento_tipo = $_POST['documento_tipo'] ?? 'RG';
    $estudante->documento_numero = $_POST['documento_numero'] ?? '';
    $estudante->documento_orgao = $_POST['documento_orgao'] ?? '';
    $estudante->instituicao = $_POST['instituicao'] ?? '';
    $estudante->campus = $_POST['campus'] ?? '';
    $estudante->curso = $_POST['curso'] ?? '';
    $estudante->nivel = $_POST['nivel'] ?? '';
    $estudante->matricula = $_POST['matricula'] ?? '';
    $estudante->situacao_academica = $_POST['situacao_academica'] ?? 'Matriculado';
    $estudante->email = $_POST['email'] ?? '';
    $estudante->telefone = $_POST['telefone'] ?? '';

    $uploadFoto = null;
    if (!empty($_FILES['foto']['name'])) {
        $uploadFoto = $estudanteCtrl->uploadFoto($_FILES['foto']);
        if ($uploadFoto === null) {
            $erro = "Erro ao fazer upload da foto. Use JPG ou PNG.";
        }
    }

    // ===== EDIÇÃO =====
    if (isset($_POST['id'])) {
        $estudante->id = $_POST['id'];
        $registroAtual = $estudante->buscarPorId($estudante->id);

        // Se foi feito upload de nova foto → deleta a antiga
        if ($uploadFoto !== null) {
            if (!empty($registroAtual['foto'])) {
                $estudanteCtrl->deletarFotoAntiga($registroAtual['foto']);
            }
            $estudante->foto = $uploadFoto;
        } else {
            // Mantém a foto existente
            $estudante->foto = $registroAtual['foto'] ?? null;
        }

        if (empty($erro)) {
            if ($estudante->atualizar()) {
                $sucesso = "Estudante atualizado com sucesso!";
            } else {
                $erro = "Erro ao atualizar estudante.";
            }
        }
    } else {
        // Novo cadastro
        $estudante->foto = $uploadFoto;
        if (empty($erro)) {
            if ($estudante->criar()) {
                $sucesso = "Estudante cadastrado com sucesso!";
                // Limpa os campos após cadastro
                foreach ($_POST as $key => $value) $_POST[$key] = '';
            } else {
                $erro = "Erro ao cadastrar estudante. Verifique se a matrícula ou CPF já existem.";
            }
        }
    }
}

// ================================
// EXCLUSÃO
// ================================

if (isset($_GET['deletar'])) {
    $estudante->id = (int)$_GET['deletar'];
    if ($estudante->deletar()) {
        $sucesso = "Estudante excluído com sucesso.";
    } else {
        $erro = "Erro ao excluir estudante.";
    }
}

// ================================
// EDIÇÃO (carrega dados)
// ================================

$editar = null;
if (isset($_GET['editar'])) {
    $editar = $estudante->buscarPorId((int)$_GET['editar']);
}

// ================================
// LISTAGEM
// ================================

$estudantes = $estudante->listar();

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Estudantes</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f9f9f9; }
        .container { max-width: 1200px; margin: 0 auto; }
        h2 { color: #333; }
        .mensagem { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .erro { background: #ffebee; color: #c62828; }
        .sucesso { background: #e8f5e9; color: #2e7d32; }
        form { background: white; padding: 20px; border-radius: 6px; margin-bottom: 30px; }
        .form-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; min-width: 200px; }
        label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 0.9em; }
        input, select { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #1976d2; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #1565c0; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f5f5f5; }
        .acoes { white-space: nowrap; }
        .foto-preview { width: 60px; height: 60px; object-fit: cover; border: 1px solid #ddd; }
        a { color: #1976d2; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .voltar { display: inline-block; margin-bottom: 20px; color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="voltar">← Voltar ao Dashboard</a>
        <h2><?= $editar ? 'Editar Estudante' : 'Cadastrar Novo Estudante' ?></h2>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?php if ($editar): ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($editar['id']) ?>">
            <?php endif; ?>

            <!-- Dados Civis -->
            <h3>Dados Civis</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Nome Completo *</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($editar['nome'] ?? ($_POST['nome'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                    <label>Data de Nascimento *</label>
                    <input type="date" name="data_nascimento" value="<?= htmlspecialchars($editar['data_nascimento'] ?? ($_POST['data_nascimento'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                    <label>CPF</label>
                    <input type="text" name="cpf" value="<?= htmlspecialchars($editar['cpf'] ?? ($_POST['cpf'] ?? '')) ?>" placeholder="000.000.000-00">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tipo de Documento *</label>
                    <select name="documento_tipo" required>
                        <option value="RG" <?= ($editar && $editar['documento_tipo'] === 'RG') ? 'selected' : '' ?>>RG</option>
                        <option value="CNH" <?= ($editar && $editar['documento_tipo'] === 'CNH') ? 'selected' : '' ?>>CNH</option>
                        <option value="PASSAPORTE" <?= ($editar && $editar['documento_tipo'] === 'PASSAPORTE') ? 'selected' : '' ?>>Passaporte</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Número do Documento *</label>
                    <input type="text" name="documento_numero" value="<?= htmlspecialchars($editar['documento_numero'] ?? ($_POST['documento_numero'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                    <label>Órgão Expedidor</label>
                    <input type="text" name="documento_orgao" value="<?= htmlspecialchars($editar['documento_orgao'] ?? ($_POST['documento_orgao'] ?? '')) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Foto 3x4 (JPG/PNG)</label>
                    <input type="file" name="foto" accept="image/jpeg,image/png">
                    <?php if ($editar && !empty($editar['foto'])): ?>
                        <br><img src="<?= htmlspecialchars($editar['foto']) ?>" class="foto-preview" alt="Foto">
                    <?php endif; ?>
                </div>
            </div>

            <hr>

            <!-- Dados Acadêmicos -->
            <h3>Dados Acadêmicos</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Instituição *</label>
                    <input type="text" name="instituicao" value="<?= htmlspecialchars($editar['instituicao'] ?? ($_POST['instituicao'] ?? '')) ?>" required placeholder="Ex: IFPB">
                </div>
                <div class="form-group">
                    <label>Campus</label>
                    <input type="text" name="campus" value="<?= htmlspecialchars($editar['campus'] ?? ($_POST['campus'] ?? '')) ?>" placeholder="Ex: João Pessoa">
                </div>
                <div class="form-group">
                    <label>Curso *</label>
                    <input type="text" name="curso" value="<?= htmlspecialchars($editar['curso'] ?? ($_POST['curso'] ?? '')) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Nível/Modalidade *</label>
                    <input type="text" name="nivel" value="<?= htmlspecialchars($editar['nivel'] ?? ($_POST['nivel'] ?? '')) ?>" required placeholder="Ex: Técnico, Superior">
                </div>
                <div class="form-group">
                    <label>Matrícula *</label>
                    <input type="text" name="matricula" value="<?= htmlspecialchars($editar['matricula'] ?? ($_POST['matricula'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                    <label>Situação Acadêmica *</label>
                    <select name="situacao_academica" required>
                        <option value="Matriculado" <?= ($editar && $editar['situacao_academica'] === 'Matriculado') ? 'selected' : '' ?>>Matriculado</option>
                        <option value="Trancado" <?= ($editar && $editar['situacao_academica'] === 'Trancado') ? 'selected' : '' ?>>Trancado</option>
                        <option value="Formado" <?= ($editar && $editar['situacao_academica'] === 'Formado') ? 'selected' : '' ?>>Formado</option>
                        <option value="Cancelado" <?= ($editar && $editar['situacao_academica'] === 'Cancelado') ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
            </div>

            <!-- Contato -->
            <h3>Contato (Opcional)</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($editar['email'] ?? ($_POST['email'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone" value="<?= htmlspecialchars($editar['telefone'] ?? ($_POST['telefone'] ?? '')) ?>" placeholder="(00) 00000-0000">
                </div>
            </div>

            <button type="submit"><?= $editar ? 'Atualizar Estudante' : 'Cadastrar Estudante' ?></button>
            <?php if ($editar): ?>
                <a href="estudantes.php" style="margin-left: 10px;">Cancelar</a>
            <?php endif; ?>
        </form>

        <!-- Listagem -->
        <h3>Lista de Estudantes</h3>
        <table>
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Nome</th>
                    <th>Matrícula</th>
                    <th>Curso</th>
                    <th>Instituição</th>
                    <th>Situação</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($estudantes as $e): ?>
                <tr>
                    <td>
                        <?php if (!empty($e['foto'])): ?>
                            <img src="<?= htmlspecialchars($e['foto']) ?>" class="foto-preview" alt="Foto">
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($e['nome']) ?></td>
                    <td><?= htmlspecialchars($e['matricula']) ?></td>
                    <td><?= htmlspecialchars($e['curso']) ?></td>
                    <td><?= htmlspecialchars($e['instituicao']) ?></td>
                    <td><?= htmlspecialchars($e['situacao_academica']) ?></td>
                    <td class="acoes">
                        <a href="?editar=<?= $e['id'] ?>">Editar</a> |
                        <a href="?deletar=<?= $e['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir este estudante?')">Excluir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>