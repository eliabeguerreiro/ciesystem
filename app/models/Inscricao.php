<?php
require_once __DIR__ . '/../config/database.php';

class Inscricao {
    private $conn;
    private $table = 'inscricoes';

    public $id;
    public $estudante_id;
    public $codigo_inscricao;
    public $data_validade;
    public $situacao;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Gera código único (UUID v4)
    private function gerarCodigoUnico() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    // Cria nova inscrição
    public function criar() {
        $this->codigo_inscricao = $this->gerarCodigoUnico();
        $this->data_validade = (date('Y') + 1) . '-03-31';
        $this->situacao = 'pagamento_pendente'; // ← ajustado para novo fluxo
        
        $query = "INSERT INTO {$this->table} (
            estudante_id, codigo_inscricao, data_validade, situacao
        ) VALUES (
            :estudante_id, :codigo_inscricao, :data_validade, :situacao
        )";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':estudante_id', $this->estudante_id, PDO::PARAM_INT);
        $stmt->bindParam(':codigo_inscricao', $this->codigo_inscricao);
        $stmt->bindParam(':data_validade', $this->data_validade);
        $stmt->bindParam(':situacao', $this->situacao);

        return $stmt->execute();
    }

    // Salva múltiplos documentos vinculados à inscrição
    public function salvarDocumentos($documentos, $tipo) {
        // Valida inputs mínimos
        if (empty($documentos) || empty($this->id)) return true;

        // Normaliza para suportar input único ou múltiplo (a tag <input> sem [] traz strings)
        if (!isset($documentos['name'])) return true;
        if (!is_array($documentos['name'])) {
            $documentos = [
                'name' => [$documentos['name']],
                'type' => isset($documentos['type']) ? [$documentos['type']] : [''],
                'tmp_name' => isset($documentos['tmp_name']) ? [$documentos['tmp_name']] : [''],
                'error' => isset($documentos['error']) ? [$documentos['error']] : [UPLOAD_ERR_NO_FILE],
                'size' => isset($documentos['size']) ? [$documentos['size']] : [0],
            ];
        }

        if (empty($documentos['name'][0])) return true;

        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $query = "INSERT INTO documentos_inscricao (inscricao_id, tipo, caminho_arquivo, descricao) 
                  VALUES (:inscricao_id, :tipo, :caminho_arquivo, :descricao)";
        $stmt = $this->conn->prepare($query);

        foreach ($documentos['name'] as $index => $nomeOriginal) {
            if (!isset($documentos['error'][$index]) || $documentos['error'][$index] !== UPLOAD_ERR_OK) continue;
            if (!isset($documentos['tmp_name'][$index]) || $documentos['tmp_name'][$index] === '') continue;

            $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;

            $nomeUnico = "doc_{$tipo}_" . uniqid() . '.' . $ext;
            $subdir = $tipo === 'pagamento' ? 'pagamento' : 'matricula';
            $caminhoAbsoluto = __DIR__ . "/../../public/uploads/comprovantes/{$subdir}/{$nomeUnico}";

            if (!is_dir(dirname($caminhoAbsoluto))) {
                mkdir(dirname($caminhoAbsoluto), 0777, true);
            }

            if (move_uploaded_file($documentos['tmp_name'][$index], $caminhoAbsoluto)) {
                $caminhoRelativo = "uploads/comprovantes/{$subdir}/{$nomeUnico}";
                // Usa bindValue para evitar referência entre execuções
                $stmt->bindValue(':inscricao_id', $this->id, PDO::PARAM_INT);
                $stmt->bindValue(':tipo', $tipo);
                $stmt->bindValue(':caminho_arquivo', $caminhoRelativo);
                $stmt->bindValue(':descricao', $nomeOriginal);
                $stmt->execute();
            }
        }
        return true;
    }

    // Busca documentos da inscrição — SEMPRE retorna array
    public function getDocumentos() {
        if (empty($this->id)) {
            return [];
        }

        $query = "SELECT * FROM documentos_inscricao WHERE inscricao_id = :inscricao_id ORDER BY tipo, criado_em";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':inscricao_id', $this->id, PDO::PARAM_INT);
        
        try {
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    // Lista inscrições com dados do estudante
    public function listarComEstudantes() {
        $query = "
            SELECT 
                i.*, 
                e.nome AS estudante_nome, 
                e.matricula AS estudante_matricula,
                e.curso AS estudante_curso
            FROM inscricoes i
            INNER JOIN estudantes e ON i.estudante_id = e.id
            ORDER BY i.id DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Atualiza situação
    public function atualizarSituacao($novaSituacao) {
        $query = "UPDATE {$this->table} SET situacao = :situacao WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':situacao', $novaSituacao);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Busca por ID
    public function buscarPorId($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>