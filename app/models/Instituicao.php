<?php
require_once __DIR__ . '/../config/database.php';

class Instituicao {
    private $conn;
    private $table = 'instituicoes';

    public $id;
    public $nome;
    public $endereco;
    public $cidade;
    public $estado;
    public $cep;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Cria nova instituição
    public function criar() {
        $query = "INSERT INTO {$this->table} (
            nome, endereco, cidade, estado, cep, status
        ) VALUES (
            :nome, :endereco, :cidade, :estado, :cep, :status
        )";
        $stmt = $this->conn->prepare($query);

        $this->nome = htmlspecialchars(strip_tags(trim($this->nome)));
        $this->endereco = htmlspecialchars(strip_tags(trim($this->endereco)));
        $this->cidade = htmlspecialchars(strip_tags(trim($this->cidade)));
        $this->estado = strtoupper(substr(htmlspecialchars(strip_tags(trim($this->estado))), 0, 2)); // Sanitiza para 2 chars maiúsculos
        $this->cep = preg_replace('/[^0-9]/', '', $this->cep);
        // status já deve vir como 'ativa' ou 'inativa'

        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':endereco', $this->endereco);
        $stmt->bindParam(':cidade', $this->cidade);
        $stmt->bindParam(':estado', $this->estado);
        $stmt->bindParam(':cep', $this->cep);
        $stmt->bindParam(':status', $this->status);

        return $stmt->execute();
    }

    // Lista instituições (ativas e inativas)
    public function listar() {
        $query = "SELECT * FROM {$this->table} ORDER BY nome ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lista apenas instituições ativas
    public function listarAtivas() {
        $query = "SELECT id, nome FROM {$this->table} WHERE status = 'ativa' ORDER BY nome ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Buscar por ID
    public function buscarPorId($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Atualizar
    public function atualizar() {
        $query = "UPDATE {$this->table} SET
            nome = :nome,
            endereco = :endereco,
            cidade = :cidade,
            estado = :estado,
            cep = :cep,
            telefone = :telefone,
            email = :email,
            status = :status
        WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $this->nome = htmlspecialchars(strip_tags(trim($this->nome)));
        $this->endereco = htmlspecialchars(strip_tags(trim($this->endereco)));
        $this->cidade = htmlspecialchars(strip_tags(trim($this->cidade)));
        $this->estado = strtoupper(substr(htmlspecialchars(strip_tags(trim($this->estado))), 0, 2));
        $this->cep = preg_replace('/[^0-9]/', '', $this->cep);

        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':endereco', $this->endereco);
        $stmt->bindParam(':cidade', $this->cidade);
        $stmt->bindParam(':estado', $this->estado);
        $stmt->bindParam(':cep', $this->cep);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Deletar (não recomendado, melhor desativar)
    // public function deletar() { ... }

    // Atualizar status (ativar ou desativar)
    public function atualizarStatus($novoStatus) {
        if (!in_array($novoStatus, ['ativa', 'inativa'])) {
            return false; // Status inválido
        }
        $query = "UPDATE {$this->table} SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $novoStatus);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?> 