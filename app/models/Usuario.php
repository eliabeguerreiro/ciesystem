<?php
// app/models/Usuario.php
require_once __DIR__ . '/../config/database.php';

class Usuario {
    private $conn;
    private $table = 'usuarios';

    public $id;
    public $nome;
    public $email;
    public $senha;
    public $tipo;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Verifica login
    public function login($email) {
        $query = "SELECT id, nome, email, senha, tipo FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Lista todos os usuários (exceto senhas)
    public function listar() {
        $query = "SELECT id, nome, email, tipo FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Cria novo usuário
    public function criar() {
        $query = "INSERT INTO " . $this->table . " (nome, email, senha, tipo) VALUES (:nome, :email, :senha, :tipo)";
        $stmt = $this->conn->prepare($query);

        $this->nome = htmlspecialchars(strip_tags($this->nome));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->senha = password_hash($this->senha, PASSWORD_DEFAULT);
        $this->tipo = htmlspecialchars(strip_tags($this->tipo));

        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':senha', $this->senha);
        $stmt->bindParam(':tipo', $this->tipo);

        return $stmt->execute();
    }

    // Atualiza usuário (sem expor a senha)
    public function atualizar() {
        $query = "UPDATE " . $this->table . " SET nome = :nome, email = :email, tipo = :tipo";
        $params = [':nome' => $this->nome, ':email' => $this->email, ':tipo' => $this->tipo];

        if (!empty($this->senha)) {
            $query .= ", senha = :senha";
            $params[':senha'] = password_hash($this->senha, PASSWORD_DEFAULT);
        }

        $query .= " WHERE id = :id";
        $params[':id'] = $this->id;

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        return $stmt->execute();
    }

    // Deleta usuário
    public function deletar() {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    // Busca usuário por ID
    public function buscarPorId($id) {
        $query = "SELECT id, nome, email, tipo FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }
}