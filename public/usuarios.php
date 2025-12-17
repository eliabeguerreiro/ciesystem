<?php
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/models/usuario.php';
require_once __DIR__ . '/../config/database.php';

$auth = new AuthController();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    die("Acesso negado.");
}

$database = new Database();
$db = $database->getConnection();
$usuario = new Usuario($db);

// Tratamento de formulários
if ($_POST) {
    $usuario->nome = $_POST['nome'];
    $usuario->email = $_POST['email'];
    $usuario->tipo = $_POST['tipo'];
    if (!empty($_POST['senha'])) {
        $usuario->senha = $_POST['senha'];
    }

    if (isset($_POST['id'])) {
        // Editar
        $usuario->id = $_POST['id'];
        $usuario->atualizar();
    } else {
        // Criar
        $usuario->criar();
    }
    header('Location: usuarios.php');
    exit;
}

if (isset($_GET['deletar'])) {
    $usuario->id = $_GET['deletar'];
    $usuario->deletar();
    header('Location: usuarios.php');
    exit;
}

$usuarios = $usuario->listar();
$editar = null;
if (isset($_GET['editar'])) {
    $editar = $usuario->buscarPorId($_GET['editar']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Usuários</title>
    <style>
        body { font-family: sans-serif; margin: 40px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        form { margin: 20px 0; }
        input, select, button { padding: 6px; margin: 4px; }
    </style>
</head>
<body>
    <h2>Gerenciar Usuários</h2>
    <a href="dashboard.php">← Voltar</a>

    <h3><?= $editar ? 'Editar Usuário' : 'Novo Usuário' ?></h3>
    <form method="POST">
        <?php if ($editar): ?>
            <input type="hidden" name="id" value="<?= $editar['id'] ?>">
        <?php endif; ?>
        <p>
            <input type="text" name="nome" placeholder="Nome" value="<?= $editar['nome'] ?? '' ?>" required>
        </p>
        <p>
            <input type="email" name="email" placeholder="E-mail" value="<?= $editar['email'] ?? '' ?>" required>
        </p>
        <p>
            <input type="password" name="senha" placeholder="<?= $editar ? 'Deixe em branco para não alterar' : 'Senha' ?>">
        </p>
        <p>
            <select name="tipo" required>
                <option value="user" <?= ($editar && $editar['tipo'] === 'user') ? 'selected' : '' ?>>Usuário</option>
                <option value="admin" <?= ($editar && $editar['tipo'] === 'admin') ? 'selected' : '' ?>>Admin</option>
            </select>
        </p>
        <p>
            <button type="submit"><?= $editar ? 'Atualizar' : 'Criar' ?></button>
            <?php if ($editar): ?>
                <a href="usuarios.php">Cancelar</a>
            <?php endif; ?>
        </p>
    </form>

    <h3>Lista de Usuários</h3>
    <table>
        <thead>
            <tr><th>Nome</th><th>E-mail</th><th>Tipo</th><th>Ações</th></tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['nome']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= $u['tipo'] ?></td>
                <td>
                    <a href="?editar=<?= $u['id'] ?>">Editar</a> |
                    <a href="?deletar=<?= $u['id'] ?>" onclick="return confirm('Tem certeza?')">Excluir</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>