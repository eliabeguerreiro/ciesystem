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
    public $matricula_validada; // <-- Mantido por enquanto para compatibilidade, mas sua lógica de validação mudará
    public $origem; 
    // *** CAMPO ADICIONAL ***
    public $documentos; // Novo campo para armazenar JSON de documentos (opcional, dependendo da estratégia)


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
        $this->matricula_validada = 0; // FALSE - Inicialmente 0, a validação será controlada pela nova lógica de documentos
        // A origem deve ser definida ANTES de chamar criar()
        if (empty($this->origem) || !in_array($this->origem, ['estudante', 'administrador'])) {
             // Pode lançar um erro ou definir um padrão, mas é melhor garantir que seja definido antes
             // throw new Exception("Campo 'origem' deve ser definido antes de criar a inscrição.");
             $this->origem = 'estudante'; // Padrão, mas ideal definir explicitamente
        }
        // Inicializar o campo documentos como um objeto JSON vazio (opcional)
        $this->documentos = json_encode([]);
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
        $stmt->bindParam(':matricula_validada', $this->matricula_validada, PDO::PARAM_BOOL); // <-- Pode ser alterado futuramente pela nova lógica
        $stmt->bindParam(':origem', $this->origem);
        // *** FIM BIND DOS NOVOS CAMPOS ***
        return $stmt->execute();
    }

    // Salva múltiplos documentos vinculados à inscrição na nova tabela
    // *** ATUALIZADO: Agora salva na tabela documentos_anexados ***
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

        // *** MUDANÇA: Query para a nova tabela documentos_anexados ***
        $query = "INSERT INTO documentos_anexados (entidade_tipo, entidade_id, tipo, caminho_arquivo, descricao, validado)
                  VALUES (:entidade_tipo, :entidade_id, :tipo, :caminho_arquivo, :descricao, :validado)";
        $stmt = $this->conn->prepare($query);

        $origemDaInscricao = $this->getOrigemInscricao(); // Obter origem para definir estado inicial
        $estadoInicial = ($origemDaInscricao === 'administrador') ? 'validado' : 'pendente';

        foreach ($documentos['name'] as $index => $nomeOriginal) {
            if (!isset($documentos['error'][$index]) || $documentos['error'][$index] !== UPLOAD_ERR_OK) continue;
            if (!isset($documentos['tmp_name'][$index]) || $documentos['tmp_name'][$index] === '') continue;

            $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;

            $nomeUnico = "doc_{$tipo}_" . uniqid() . '.' . $ext;
            // Pasta unificada para todos os documentos
            $caminhoAbsoluto = __DIR__ . "/../../public/uploads/documentos/{$nomeUnico}";

            if (!is_dir(dirname($caminhoAbsoluto))) {
                mkdir(dirname($caminhoAbsoluto), 0777, true);
            }

            if (move_uploaded_file($documentos['tmp_name'][$index], $caminhoAbsoluto)) {
                $caminhoRelativo = "uploads/documentos/{$nomeUnico}";

                // Usa bindValue para evitar referência entre execuções
                $stmt->bindValue(':entidade_tipo', 'inscricao', PDO::PARAM_STR); // <-- Novo campo
                $stmt->bindValue(':entidade_id', $this->id, PDO::PARAM_INT); // <-- ID da inscrição
                $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
                $stmt->bindValue(':caminho_arquivo', $caminhoRelativo);
                $stmt->bindValue(':descricao', $nomeOriginal);
                $stmt->bindValue(':validado', $estadoInicial, PDO::PARAM_STR); // <-- Novo campo
                $stmt->execute();
            }
        }
        return true;
    }

    // *** NOVO MÉTODO: Obter origem da inscrição ***
    private function getOrigemInscricao() {
        $query = "SELECT origem FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['origem'] : null;
    }
    // *** FIM NOVO MÉTODO ***


    // Busca documentos da inscrição na nova tabela
    // *** ATUALIZADO: Retorna do documentos_anexados ***
    public function getDocumentos() {
        if (empty($this->id)) {
            return [];
        }
        // *** MUDANÇA: Query para a nova tabela documentos_anexados ***
        $query = "SELECT * FROM documentos_anexados WHERE entidade_tipo = :entidade_tipo AND entidade_id = :entidade_id ORDER BY tipo, criado_em";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':entidade_tipo', 'inscricao', PDO::PARAM_STR); // <-- Filtra por inscrição
        $stmt->bindParam(':entidade_id', $this->id, PDO::PARAM_INT);
        try {
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result ?: [];
        } catch (Exception $e) {
            // Log do erro pode ser útil
            error_log("Erro ao buscar documentos na nova tabela: " . $e->getMessage());
            return [];
        }
    }
    // *** FIM ATUALIZADO ***


    // Lista inscrições com dados do estudante
    // *** ATUALIZADO: Inclui o campo documentos ***
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
    // *** FIM ATUALIZADO ***


    // *** NOVO MÉTODO: Lista inscrições com dados do estudante, com filtros e paginação (inclui documentos) ***
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

    // *** ATUALIZADO: Atualizar Matrícula Validada ***
    // Este método agora pode ser usado para marcar a inscrição como "pronta" (todos os docs validados) ou para fins de compatibilidade.
    // A lógica de validação individual de documentos será feita na nova tela e atualizará o campo `validado` na tabela `documentos_anexados`.
    // Este campo `matricula_validada` pode ser atualizado com base no estado de *todos* os documentos obrigatórios da inscrição.
    public function atualizarMatriculaValidada($valor = true) {
        // *** MUDANÇA: Atualiza o campo matricula_validada ***
        $query = "UPDATE {$this->table} SET matricula_validada = :valor WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':valor', $valor, PDO::PARAM_BOOL);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    // *** FIM ATUALIZADO ***


    // Busca por ID
    public function buscarPorId($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // *** MANTIDO: Método para marcar como aguardando entrega ***
    public function marcarComoAguardandoEntrega() {
        $novaSituacao = 'cie_emitida_aguardando_entrega';
        $query = "UPDATE {$this->table} SET situacao = :situacao WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':situacao', $novaSituacao);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    // *** FIM MANTIDO ***

    // *** MANTIDO: método para listar inscrições prontas para logística ***
    public function listarProntasParaLogistica() {
        $query = "
        SELECT
            i.*,
            e.nome AS estudante_nome,
            e.matricula AS estudante_matricula,
            e.curso AS estudante_curso,
            inst.nome AS instituicao_nome -- Inclui o nome da instituição
        FROM inscricoes i
        INNER JOIN estudantes e ON i.estudante_id = e.id
        INNER JOIN instituicoes inst ON e.instituicao_id = inst.id -- JOIN com instituicoes
        WHERE i.situacao = 'cie_emitida_aguardando_entrega'
        ORDER BY i.id DESC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // *** FIM MANTIDO ***

}   
?>