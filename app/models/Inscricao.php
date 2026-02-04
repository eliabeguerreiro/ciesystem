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
    // *** NOVOS CAMPOS ***
    public $pagamento_confirmado;
    public $matricula_validada;
    public $origem; 


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
        $this->situacao = 'aguardando_validacao';
        // *** INICIALIZAR NOVOS CAMPOS ***
        $this->pagamento_confirmado = 0; // FALSE
        $this->matricula_validada = 0; // FALSE
        // A origem deve ser definida ANTES de chamar criar()
        if (empty($this->origem) || !in_array($this->origem, ['estudante', 'administrador'])) {
             // Pode lançar um erro ou definir um padrão, mas é melhor garantir que seja definido antes
             // throw new Exception("Campo 'origem' deve ser definido antes de criar a inscrição.");
             $this->origem = 'estudante'; // Padrão, mas ideal definir explicitamente
        }
        // *** FIM INICIALIZAR NOVOS CAMPOS ***
        $query = "INSERT INTO {$this->table} (
            estudante_id, codigo_inscricao, data_validade, situacao,
            pagamento_confirmado, matricula_validada, origem -- Incluir novo campo
        ) VALUES (
            :estudante_id, :codigo_inscricao, :data_validade, :situacao,
            :pagamento_confirmado, :matricula_validada, :origem -- Incluir novo campo
        )";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':estudante_id', $this->estudante_id, PDO::PARAM_INT);
        $stmt->bindParam(':codigo_inscricao', $this->codigo_inscricao);
        $stmt->bindParam(':data_validade', $this->data_validade);
        $stmt->bindParam(':situacao', $this->situacao);
        // *** BIND DOS NOVOS CAMPOS ***
        $stmt->bindParam(':pagamento_confirmado', $this->pagamento_confirmado, PDO::PARAM_BOOL);
        $stmt->bindParam(':matricula_validada', $this->matricula_validada, PDO::PARAM_BOOL);
        $stmt->bindParam(':origem', $this->origem);
        // *** FIM BIND DOS NOVOS CAMPOS ***
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

    // *** NOVO MÉTODO: Lista inscrições com dados do estudante, com filtros e paginação ***
    public function listarComEstudantesFiltrada($filtroSituacao = '', $filtroStatusValidacao = '', $offset = 0, $limit = 10) {
        $query = "
        SELECT
            i.*,
            e.nome AS estudante_nome,
            e.matricula AS estudante_matricula,
            e.curso AS estudante_curso,
            e.status_validacao AS estudante_status_validacao -- Inclui o status de validação
        FROM inscricoes i
        INNER JOIN estudantes e ON i.estudante_id = e.id
        ";

        $params = [];
        $whereConditions = [];

        if ($filtroSituacao) {
            $whereConditions[] = "i.situacao = :filtro_situacao";
            $params[':filtro_situacao'] = $filtroSituacao;
        }
        if ($filtroStatusValidacao) {
            $whereConditions[] = "e.status_validacao = :filtro_status_validacao";
            $params[':filtro_status_validacao'] = $filtroStatusValidacao;
        }

        if (!empty($whereConditions)) {
            $query .= " WHERE " . implode(' AND ', $whereConditions);
        }

        $query .= " ORDER BY i.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // *** FIM NOVO MÉTODO ***


    // Atualiza situação (mantido por compatibilidade, mas a lógica será revisada)
    public function atualizarSituacao($novaSituacao) {
        $query = "UPDATE {$this->table} SET situacao = :situacao WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':situacao', $novaSituacao);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // *** NOVOS MÉTODOS PARA ATUALIZAR STATUS BOOLEANOS ***
    public function atualizarPagamentoConfirmado($valor = true) {
        $query = "UPDATE {$this->table} SET pagamento_confirmado = :valor WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':valor', $valor, PDO::PARAM_BOOL);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function atualizarMatriculaValidada($valor = true) {
        $query = "UPDATE {$this->table} SET matricula_validada = :valor WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':valor', $valor, PDO::PARAM_BOOL);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    // *** FIM NOVOS MÉTODOS ***


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