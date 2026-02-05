<?php
session_start(); // Certifique-se de iniciar a sessão para ter acesso às variáveis

require_once '../app/controllers/AuthController.php';
require_once '../app/models/Log.php'; // Adicione esta linha

$auth = new AuthController();

// === LOG: Logout realizado (antes de destruir a sessão) ===
if (isset($_SESSION['user_id'])) {
    $database = new \Database(); // Assumindo que Database está no namespace global ou corretamente referenciado
    $db = $database->getConnection();
    $log = new Log($db);
    $log->registrar(
        $_SESSION['user_id'], // ID do usuário que está saindo
        'logout_realizado', // Ação
        "Usuário '{$_SESSION['user_nome']}' realizou logout.", // Descrição
        null, // Registro ID (não se aplica aqui)
        'sessoes' // Tabela (pode ser uma tabela genérica de sessões ou eventos)
    );
}
// === FIM LOG ===

$auth->logout(); // Isso chama session_destroy()
?>