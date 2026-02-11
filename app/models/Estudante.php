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
    // --- MUDANÇA AQUI ---
    // Mantido: public $instituicao; (compatibilidade com banco de dados)
    public $instituicao;
    public $instituicao_id; // <- Adicionado novo campo
    // --- FIM MUDANÇA ---
    public $campus;
    public $curso;
    public $nivel;
    public $matricula;
    public $situacao_academica;
    public $email;
    public $telefone;
    public $status_validacao;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Cria novo estudante
    public function criar() {
        // --- MUDANÇA AQUI ---
        // Atualizado para usar 'instituicao_id' em vez de 'instituicao'
        $query = "INSERT INTO {$this->table} (
            nome, data_nascimento, cpf, documento_tipo, documento_numero, documento_orgao, foto,
            instituicao, instituicao_id, campus, curso, nivel, matricula, situacao_academica, status_validacao,
            email, telefone
        ) VALUES (
            :nome, :data_nascimento, :cpf, :documento_tipo, :documento_numero, :documento_orgao, :foto,
            :instituicao, :instituicao_id, :campus, :curso, :nivel, :matricula, :situacao_academica, :status_validacao,
            :email, :telefone
        )";
        // --- FIM MUDANÇA ---
        $stmt = $this->conn->prepare($query);

        // Sanitização
        $this->nome = htmlspecialchars(strip_tags(trim($this->nome)));
        $this->cpf = preg_replace('/[^0-9]/', '', $this->cpf);
        $this->documento_numero = htmlspecialchars(strip_tags(trim($this->documento_numero)));
        $this->documento_orgao = htmlspecialchars(strip_tags(trim($this->documento_orgao)));
        // --- MUDANÇA AQUI ---
        // Preenchendo instituicao com valor padrão vazio (compatibilidade com banco de dados)
        $this->instituicao = '';
        $this->instituicao_id = (int)$this->instituicao_id; // Sanitiza como inteiro
        // --- FIM MUDANÇA ---
        $this->campus = htmlspecialchars(strip_tags(trim($this->campus)));
        $this->curso = htmlspecialchars(strip_tags(trim($this->curso)));
        $this->nivel = htmlspecialchars(strip_tags(trim($this->nivel)));
        $this->matricula = htmlspecialchars(strip_tags(trim($this->matricula)));
        $this->situacao_academica = htmlspecialchars(strip_tags(trim($this->situacao_academica)));
        $this->status_validacao = htmlspecialchars(strip_tags(trim($this->status_validacao)));
        $this->email = filter_var(trim($this->email), FILTER_SANITIZE_EMAIL);
        $this->telefone = preg_replace('/[^0-9() -+]/', '', $this->telefone);

        $stmt->bindParam(':nome', $this->nome);
        $stmt->bindParam(':data_nascimento', $this->data_nascimento);
        $stmt->bindParam(':cpf', $this->cpf);
        $stmt->bindParam(':documento_tipo', $this->documento_tipo);
        $stmt->bindParam(':documento_numero', $this->documento_numero);
        $stmt->bindParam(':documento_orgao', $this->documento_orgao);
        $stmt->bindParam(':foto', $this->foto);
        // --- MUDANÇA AQUI ---
        // Adicionado bindParam para :instituicao (valor padrão vazio)
        $stmt->bindParam(':instituicao', $this->instituicao);
        $stmt->bindParam(':instituicao_id', $this->instituicao_id, PDO::PARAM_INT); // <- Adicionado bindParam para :instituicao_id
        // --- FIM MUDANÇA ---
        $stmt->bindParam(':campus', $this->campus);
        $stmt->bindParam(':curso', $this->curso);
        $stmt->bindParam(':nivel', $this->nivel);
        $stmt->bindParam(':matricula', $this->matricula);
        $stmt->bindParam(':situacao_academica', $this->situacao_academica);
        $stmt->bindParam(':status_validacao', $this->status_validacao);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':telefone', $this->telefone);

        return $stmt->execute();
    }

    // Listar todos (atualizado para incluir nome da instituição via JOIN)
    public function listar() {
        // JOIN com a tabela de instituições para obter o nome
        // --- MUDANÇA AQUI ---
        $query = "SELECT e.*, i.nome AS instituicao_nome FROM {$this->table} e LEFT JOIN instituicoes i ON e.instituicao_id = i.id ORDER BY e.nome";
        // --- FIM MUDANÇA ---
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Buscar por ID (atualizado para incluir nome da instituição via JOIN)
    public function buscarPorId($id) {
        // --- MUDANÇA AQUI ---
        $query = "SELECT e.*, i.nome AS instituicao_nome FROM {$this->table} e LEFT JOIN instituicoes i ON e.instituicao_id = i.id WHERE e.id = :id";
        // --- FIM MUDANÇA ---
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Atualizar (atualizado para incluir instituicao_id)
    public function atualizar() {
        // --- MUDANÇA AQUI ---
        // Atualizado para usar 'instituicao_id' em vez de 'instituicao'
        $query = "UPDATE {$this->table} SET
            nome = :nome,
            data_nascimento = :data_nascimento,
            cpf = :cpf,
            documento_tipo = :documento_tipo,
            documento_numero = :documento_numero,
            documento_orgao = :documento_orgao,
            foto = :foto,
            instituicao = :instituicao,
            instituicao_id = :instituicao_id, -- <- Atualização do novo campo
            campus = :campus,
            curso = :curso,
            nivel = :nivel,
            matricula = :matricula,
            situacao_academica = :situacao_academica,
            status_validacao = :status_validacao,
            email = :email,
            telefone = :telefone,
            atualizado_em = NOW()
        WHERE id = :id";
        // --- FIM MUDANÇA ---
        $stmt = $this->conn->prepare($query);

        // Mesma sanitização do criar()
        $this->nome = htmlspecialchars(strip_tags(trim($this->nome)));
        $this->cpf = preg_replace('/[^0-9]/', '', $this->cpf);
        $this->documento_numero = htmlspecialchars(strip_tags(trim($this->documento_numero)));
        $this->documento_orgao = htmlspecialchars(strip_tags(trim($this->documento_orgao)));
        // --- MUDANÇA AQUI ---
        // Preenchendo instituicao com valor padrão vazio (compatibilidade com banco de dados)
        $this->instituicao = '';
        $this->instituicao_id = (int)$this->instituicao_id; // Sanitiza como inteiro
        // --- FIM MUDANÇA ---
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
        // --- MUDANÇA AQUI ---
        // Adicionado bindParam para :instituicao (valor padrão vazio)
        $stmt->bindParam(':instituicao', $this->instituicao);
        $stmt->bindParam(':instituicao_id', $this->instituicao_id, PDO::PARAM_INT);
        // --- FIM MUDANÇA ---
        $stmt->bindParam(':campus', $this->campus);
        $stmt->bindParam(':curso', $this->curso);
        $stmt->bindParam(':nivel', $this->nivel);
        $stmt->bindParam(':matricula', $this->matricula);
        $stmt->bindParam(':situacao_academica', $this->situacao_academica);
        $stmt->bindParam(':status_validacao', $this->status_validacao);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':telefone', $this->telefone);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Deletar (mantido)
    public function deletar() {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }
}
?>