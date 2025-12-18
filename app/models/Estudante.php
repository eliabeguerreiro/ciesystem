<?php
require_once __DIR__ . '/../config/database.php';

class Estudante {
    private $conn;
    private $table = 'estudantes';

    public $id;
    public $nome;
    public $data_nascimento;
    public $cpf;
    public $documento_tipo;
    public $documento_numero;
    public $documento_orgao;
    public $foto;
    public $instituicao;
    public $campus;
    public $curso;
    public $nivel;
    public $matricula;
    public $situacao_academica;
    public $email;
    public $telefone;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Cria novo estudante
    public function criar() {
        $query = "INSERT INTO {$this->table} (
            nome, data_nascimento, cpf, documento_tipo, documento_numero, documento_orgao, foto,
            instituicao, campus, curso, nivel, matricula, situacao_academica,
            email, telefone
        ) VALUES (
            :nome, :data_nascimento, :cpf, :documento_tipo, :documento_numero, :documento_orgao, :foto,
            :instituicao, :campus, :curso, :nivel, :matricula, :situacao_academica,
            :email, :telefone
        )";

        $stmt = $this->conn->prepare($query);

        // Sanitização
        $this->nome = htmlspecialchars(strip_tags(trim($this->nome)));
        $this->cpf = preg_replace('/[^0-9]/', '', $this->cpf);
        $this->documento_numero = htmlspecialchars(strip_tags(trim($this->documento_numero)));
        $this->documento_orgao = htmlspecialchars(strip_tags(trim($this->documento_orgao)));
        $this->instituicao = htmlspecialchars(strip_tags(trim($this->instituicao)));
        $this->campus = htmlspecialchars(strip_tags(trim($this->campus)));
        $this->curso = htmlspecialchars(strip_tags(trim($this->curso)));
        $this->nivel = htmlspecialchars(strip_tags(trim($this->nivel)));
        $this->matricula = htmlspecialchars(strip_tags(trim($this->matricula)));
        $this->situacao_academica = htmlspecialchars(strip_tags(trim($this->situacao_academica)));
        $this->email = filter_var(trim($this->email), FILTER_SANITIZE_EMAIL);
        $this->telefone = preg_replace('/[^0-9() -+]/', '', $this->telefone);

        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':data_nascimento', $this->data_nascimento);
        $stmt->bindParam(':cpf', $this->cpf);
        $stmt->bindParam(':documento_tipo', $this->documento_tipo);
        $stmt->bindParam(':documento_numero', $this->documento_numero);
        $stmt->bindParam(':documento_orgao', $this->documento_orgao);
        $stmt->bindParam(':foto', $this->foto);
        $stmt->bindParam(':instituicao', $this->instituicao);
        $stmt->bindParam(':campus', $this->campus);
        $stmt->bindParam(':curso', $this->curso);
        $stmt->bindParam(':nivel', $this->nivel);
        $stmt->bindParam(':matricula', $this->matricula);
        $stmt->bindParam(':situacao_academica', $this->situacao_academica);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':telefone', $this->telefone);

        return $stmt->execute();
    }

    // Listar todos
    public function listar() {
        $query = "SELECT * FROM {$this->table} ORDER BY nome";
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
            data_nascimento = :data_nascimento,
            cpf = :cpf,
            documento_tipo = :documento_tipo,
            documento_numero = :documento_numero,
            documento_orgao = :documento_orgao,
            foto = :foto,
            instituicao = :instituicao,
            campus = :campus,
            curso = :curso,
            nivel = :nivel,
            matricula = :matricula,
            situacao_academica = :situacao_academica,
            email = :email,
            telefone = :telefone,
            atualizado_em = NOW()
        WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Mesma sanitização do criar()
        $this->nome = htmlspecialchars(strip_tags(trim($this->nome)));
        $this->cpf = preg_replace('/[^0-9]/', '', $this->cpf);
        $this->documento_numero = htmlspecialchars(strip_tags(trim($this->documento_numero)));
        $this->documento_orgao = htmlspecialchars(strip_tags(trim($this->documento_orgao)));
        $this->instituicao = htmlspecialchars(strip_tags(trim($this->instituicao)));
        $this->campus = htmlspecialchars(strip_tags(trim($this->campus)));
        $this->curso = htmlspecialchars(strip_tags(trim($this->curso)));
        $this->nivel = htmlspecialchars(strip_tags(trim($this->nivel)));
        $this->matricula = htmlspecialchars(strip_tags(trim($this->matricula)));
        $this->situacao_academica = htmlspecialchars(strip_tags(trim($this->situacao_academica)));
        $this->email = filter_var(trim($this->email), FILTER_SANITIZE_EMAIL);
        $this->telefone = preg_replace('/[^0-9() -+]/', '', $this->telefone);

        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':data_nascimento', $this->data_nascimento);
        $stmt->bindParam(':cpf', $this->cpf);
        $stmt->bindParam(':documento_tipo', $this->documento_tipo);
        $stmt->bindParam(':documento_numero', $this->documento_numero);
        $stmt->bindParam(':documento_orgao', $this->documento_orgao);
        $stmt->bindParam(':foto', $this->foto);
        $stmt->bindParam(':instituicao', $this->instituicao);
        $stmt->bindParam(':campus', $this->campus);
        $stmt->bindParam(':curso', $this->curso);
        $stmt->bindParam(':nivel', $this->nivel);
        $stmt->bindParam(':matricula', $this->matricula);
        $stmt->bindParam(':situacao_academica', $this->situacao_academica);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':telefone', $this->telefone);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    // Deletar
    public function deletar() {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }
}