<?php
session_start();
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/models/Log.php'; // Adicione esta linha

$auth = new AuthController();
$erro = '';

if ($_POST) {
    if ($auth->login($_POST['email'], $_POST['senha'])) {
        // === LOG: Login realizado ===
        $database = new \Database(); // Assumindo que Database está no namespace global ou corretamente referenciado
        $db = $database->getConnection();
        $log = new Log($db);
        $log->registrar(
            $_SESSION['user_id'], // ID do usuário logado
            'login_realizado', // Ação
            "Usuário '{$_SESSION['user_nome']}' realizou login.", // Descrição
            null, // Registro ID (não se aplica aqui)
            'sessoes' // Tabela (pode ser uma tabela genérica de sessões ou eventos)
        );
        // === FIM LOG ===
        header('Location: dashboard.php');
        exit;
    } else {
        $erro = "E-mail ou senha inválidos.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Login</title>
<style>body{font-family:sans-serif;max-width:400px;margin:100px auto;padding:20px;}</style>
</head>
<body>
<h2>Login</h2>
<?php if ($erro): ?>
<p style="color:red;"><?= htmlspecialchars($erro) ?></p>
<?php endif; ?>
<form method="POST">
<p><input type="email" name="email" placeholder="E-mail" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required></p>
<p><input type="password" name="senha" placeholder="Senha" required></p>
<p><button type="submit">Entrar</button></p>
</form>
</body>
</html>