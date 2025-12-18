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
    
    <?php if ($_SESSION['user_tipo'] === 'admin'): ?>
        <p><a href="usuarios.php">Gerenciar Usuários</a></p>
        <p><a href="emitir_cie.php">Emitir CIE</a></p>
    <?php endif; ?>
    <p><a href="estudantes.php">Gerenciar Estudantes</a></p>
    <p><a href="cie_listagem.php">Visualizar CIEs Emitidas</a></p>
    <p><a href="logout.php">Sair</a></p>
</body>
</html>