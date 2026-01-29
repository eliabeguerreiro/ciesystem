<?php
require_once __DIR__ . '/../config/database.php';

class DocumentoEstudante {
    private $conn;
    private $table = 'documentos_estudante';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function salvar($estudante_id, $file, $tipo) {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) return null;

        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) return null;

        $nome = "doc_identidade_{$tipo}_" . uniqid() . '.' . $ext;
        $caminhoAbsoluto = __DIR__ . "/../../public/uploads/documentos_estudante/{$nome}";

        if (!is_dir(dirname($caminhoAbsoluto))) {
            mkdir(dirname($caminhoAbsoluto), 0777, true);
        }

        if (move_uploaded_file($file['tmp_name'], $caminhoAbsoluto)) {
            $query = "INSERT INTO {$this->table} (estudante_id, tipo, caminho_arquivo, descricao) 
                    VALUES (:estudante_id, :tipo, :caminho_arquivo, :descricao)";
            $stmt = $this->conn->prepare($query);
            
            // Use bindValue() para valores literais
            $stmt->bindValue(':estudante_id', $estudante_id, PDO::PARAM_INT);
            $stmt->bindValue(':tipo', $tipo);
            $stmt->bindValue(':caminho_arquivo', "uploads/documentos_estudante/{$nome}");
            $stmt->bindValue(':descricao', $file['name']);
            
            return $stmt->execute();
        }
        return false;
    }
}