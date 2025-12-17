<?php
session_start();
require_once __DIR__ . '/../app/controllers/AuthController.php';
$auth = new AuthController();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Painel</title>
    <style>body{font-family:sans-serif;margin:40px;}</style>
</head>
<body>
    <h1>Bem-vindo, <?= htmlspecialchars($_SESSION['user_nome']) ?>!</h1>
    <p>Tipo: <?= $_SESSION['user_tipo'] ?></p>
    <p><a href="usuarios.php">Gerenciar Usuários</a> (apenas admin)</p>
    <p><a href="logout.php">Sair</a></p>
</body>
</html>