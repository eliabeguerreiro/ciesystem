<?php
require_once __DIR__ . '/../config/database.php';

class Carteirinha {
    private $conn;
    private $table = 'carteirinhas';

    public $id;
    public $estudante_id;
    public $cie_codigo;
    public $data_validade;
    public $situacao;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Gera um código único não sequencial (UUID v4)
    private function gerarCodigoUnico() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    // Cria uma nova CIE (sem documentos — eles são salvos separadamente)
    public function criar() {
        $this->cie_codigo = $this->gerarCodigoUnico();
        $this->data_validade = '2027-03-03'; // data fixa definida por você
        $this->situacao = $this->situacao ?? 'ativa';
        
        $query = "INSERT INTO {$this->table} (
            estudante_id, cie_codigo, data_validade, situacao
        ) VALUES (
            :estudante_id, :cie_codigo, :data_validade, :situacao
        )";

        $stmt = $this->conn->prepare($query);

        $this->estudante_id = (int)$this->estudante_id;

        $stmt->bindParam(':estudante_id', $this->estudante_id);
        $stmt->bindParam(':cie_codigo', $this->cie_codigo);
        $stmt->bindParam(':data_validade', $this->data_validade);
        $stmt->bindParam(':situacao', $this->situacao);

        return $stmt->execute();
    }

    // Salva múltiplos documentos vinculados a esta CIE
    public function salvarDocumentos($documentos, $tipo) {
        if (empty($documentos['name'][0])) return true;

        // Verifica se a CIE foi criada (tem ID)
        if (empty($this->id)) {
            return false;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $query = "INSERT INTO documentos_cie (carteirinha_id, tipo, caminho_arquivo, descricao) 
                  VALUES (:carteirinha_id, :tipo, :caminho_arquivo, :descricao)";
        $stmt = $this->conn->prepare($query);

        foreach ($documentos['name'] as $index => $nomeOriginal) {
            if ($documentos['error'][$index] !== UPLOAD_ERR_OK) continue;

            $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;

            $nomeUnico = "doc_{$tipo}_" . uniqid() . '_' . time() . '.' . $ext;
            $subdir = $tipo === 'pagamento' ? 'pagamento' : 'matricula';
            $caminhoAbsoluto = __DIR__ . "/../../public/uploads/comprovantes/{$subdir}/{$nomeUnico}";

            // Cria diretório se não existir
            if (!is_dir(dirname($caminhoAbsoluto))) {
                mkdir(dirname($caminhoAbsoluto), 0777, true);
            }

            if (move_uploaded_file($documentos['tmp_name'][$index], $caminhoAbsoluto)) {
                $caminhoRelativo = "uploads/comprovantes/{$subdir}/{$nomeUnico}";
                $stmt->bindParam(':carteirinha_id', $this->id);
                $stmt->bindParam(':tipo', $tipo);
                $stmt->bindParam(':caminho_arquivo', $caminhoRelativo);
                $stmt->bindParam(':descricao', $nomeOriginal);
                $stmt->execute();
            }
        }
        return true;
    }

    // Busca todos os documentos de uma CIE
    public function getDocumentos() {
        if (empty($this->id)) return [];

        $query = "SELECT * FROM documentos_cie WHERE carteirinha_id = :carteirinha_id ORDER BY tipo, criado_em";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':carteirinha_id', $this->id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Busca CIE por ID
    public function buscarPorId($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Lista todas as CIEs com dados do estudante
    public function listarComEstudantes() {
        $query = "
            SELECT 
                c.*, 
                e.nome AS estudante_nome, 
                e.matricula AS estudante_matricula,
                e.curso AS estudante_curso
            FROM carteirinhas c
            INNER JOIN estudantes e ON c.estudante_id = e.id
            ORDER BY c.criado_em DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Atualiza situação da CIE (ex: ativa → vencida)
    public function atualizarSituacao($novaSituacao) {
        $query = "UPDATE {$this->table} SET situacao = :situacao WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':situacao', $novaSituacao);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }
}
?>