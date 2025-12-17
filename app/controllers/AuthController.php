<?php
// app/controllers/AuthController.php
session_start();
require_once '../models/Usuario.php';
require_once '../config/database.php';

class AuthController {
    private $usuario;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->usuario = new Usuario($db);
    }

    public function login($email, $senha) {
        $user = $this->usuario->login($email);
        if ($user && password_verify($senha, $user['senha'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nome'] = $user['nome'];
            $_SESSION['user_tipo'] = $user['tipo'];
            return true;
        }
        return false;
    }

    public function logout() {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin() {
        return isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] === 'admin';
    }
}
?>