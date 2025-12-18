<?php
require_once __DIR__ . '/../config/database.php';

class Log {
    private $conn;
    private $table = 'logs';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function registrar($user_id, $acao, $descricao = null, $registro_id = null, $tabela = null) {
        $query = "INSERT INTO {$this->table} (user_id, acao, descricao, registro_id, tabela) 
                  VALUES (:user_id, :acao, :descricao, :registro_id, :tabela)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':acao', $acao);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':registro_id', $registro_id, PDO::PARAM_INT);
        $stmt->bindParam(':tabela', $tabela);

        return $stmt->execute();
    }
}
?>