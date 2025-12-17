<?php
require_once __DIR__ . '/../app/controllers/AuthController.php';
$auth = new AuthController();

if ($_POST) {
    if ($auth->login($_POST['email'], $_POST['senha'])) {
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
    <?php if (isset($erro)) echo "<p style='color:red;'>$erro</p>"; ?>
    <form method="POST">
        <p><input type="email" name="email" placeholder="E-mail" required></p>
        <p><input type="password" name="senha" placeholder="Senha" required></p>
        <p><button type="submit">Entrar</button></p>
    </form>
</body>
</html>