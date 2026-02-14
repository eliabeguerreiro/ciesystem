<?php
// Arquivo: c:\laragon\www\ciesytem\app\models\LogisticaEntrega.php
require_once __DIR__ . '/../config/database.php';

class LogisticaEntrega {
    private $conn;
    private $table = 'logistica_entregas';

    public $id;
    public $inscricao_id;
    public $instituicao_id;
    public $status;
    public $responsavel_saida;
    public $data_saida;
    public $responsavel_entrega;
    public $data_entrega_instituicao;
    public $observacoes;
    public $registrado_por;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Registrar saída para entrega
    public function registrarSaida() {
        $query = "INSERT INTO {$this->table} (
            inscricao_id, instituicao_id, status, responsavel_saida, data_saida, observacoes, registrado_por
        ) VALUES (
            :inscricao_id, :instituicao_id, :status, :responsavel_saida, :data_saida, :observacoes, :registrado_por
        )";
        $stmt = $this->conn->prepare($query);

        // Sanitização
        $this->inscricao_id = (int)$this->inscricao_id;
        $this->instituicao_id = (int)$this->instituicao_id;
        $this->status = 'saida_para_entrega'; // Status inicial
        $this->responsavel_saida = htmlspecialchars(strip_tags(trim($this->responsavel_saida)));
        $this->data_saida = $this->data_saida ?: date('Y-m-d H:i:s'); // Usa a data/hora atual se não for fornecida
        $this->observacoes = htmlspecialchars(strip_tags(trim($this->observacoes)));
        $this->registrado_por = (int)$this->registrado_por; // Sanitiza o ID do usuário registrado

        $stmt->bindParam(':inscricao_id', $this->inscricao_id, PDO::PARAM_INT);
        $stmt->bindParam(':instituicao_id', $this->instituicao_id, PDO::PARAM_INT);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':responsavel_saida', $this->responsavel_saida);
        $stmt->bindParam(':data_saida', $this->data_saida);
        $stmt->bindParam(':observacoes', $this->observacoes);
        $stmt->bindParam(':registrado_por', $this->registrado_por, PDO::PARAM_INT); // Adiciona o bind

        return $stmt->execute();
    }

    // Confirmar entrega na instituição
    public function confirmarEntregaNaInstituicao() {
        $query = "UPDATE {$this->table} SET
            status = :status,
            responsavel_entrega = :responsavel_entrega,
            data_entrega_instituicao = :data_entrega_instituicao,
            atualizado_em = NOW()
        WHERE inscricao_id = :inscricao_id AND status = 'saida_para_entrega'";
        // Garante que só atualiza se o status anterior for 'saida_para_entrega'
        $stmt = $this->conn->prepare($query);

        // Sanitização
        $this->status = 'entregue_na_instituicao';
        $this->responsavel_entrega = htmlspecialchars(strip_tags(trim($this->responsavel_entrega)));
        $this->data_entrega_instituicao = $this->data_entrega_instituicao ?: date('Y-m-d H:i:s'); // Usa a data/hora atual se não for fornecida
        $this->inscricao_id = (int)$this->inscricao_id;

        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':responsavel_entrega', $this->responsavel_entrega);
        $stmt->bindParam(':data_entrega_instituicao', $this->data_entrega_instituicao);
        $stmt->bindParam(':inscricao_id', $this->inscricao_id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // Buscar registros de logística por inscrição
    public function buscarPorInscricao($inscricaoId) {
        $query = "SELECT le.*, u.nome AS nome_registrador FROM {$this->table} le
                  LEFT JOIN usuarios u ON le.registrado_por = u.id
                  WHERE le.inscricao_id = :inscricao_id
                  ORDER BY le.criado_em DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':inscricao_id', $inscricaoId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Buscar registros de logística por instituição e status (opcional)
    public function buscarPorInstituicao($instituicaoId, $status = null) {
        $query = "SELECT le.*, u.nome AS nome_registrador, i.codigo_inscricao AS codigo_inscricao, e.nome AS nome_estudante
                  FROM {$this->table} le
                  LEFT JOIN usuarios u ON le.registrado_por = u.id
                  LEFT JOIN inscricoes i ON le.inscricao_id = i.id
                  LEFT JOIN estudantes e ON i.estudante_id = e.id
                  WHERE le.instituicao_id = :instituicao_id";
        if ($status) {
            $query .= " AND le.status = :status";
        }
        $query .= " ORDER BY le.criado_em DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':instituicao_id', $instituicaoId, PDO::PARAM_INT);
        if ($status) {
            $stmt->bindParam(':status', $status);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Listar todas as entregas (com possibilidade de filtros futuros)
    public function listarTodas() {
        $query = "SELECT le.*, u.nome AS nome_registrador, i.codigo_inscricao AS codigo_inscricao, e.nome AS nome_estudante, inst.nome AS nome_instituicao
                  FROM {$this->table} le
                  LEFT JOIN usuarios u ON le.registrado_por = u.id
                  LEFT JOIN inscricoes i ON le.inscricao_id = i.id
                  LEFT JOIN estudantes e ON i.estudante_id = e.id
                  LEFT JOIN instituicoes inst ON le.instituicao_id = inst.id
                  ORDER BY le.criado_em DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- NOVO MÉTODO: Listar com filtros e paginação ---
    public function listarComFiltrosEPaginacao($filtroInstituicao = '', $filtroStatus = '', $offset = 0, $limit = 10) {
        $query = "SELECT le.*, u.nome AS nome_registrador, i.codigo_inscricao AS codigo_inscricao, e.nome AS nome_estudante, inst.nome AS nome_instituicao
                  FROM {$this->table} le
                  LEFT JOIN usuarios u ON le.registrado_por = u.id
                  LEFT JOIN inscricoes i ON le.inscricao_id = i.id
                  LEFT JOIN estudantes e ON i.estudante_id = e.id
                  LEFT JOIN instituicoes inst ON le.instituicao_id = inst.id
                  WHERE 1=1 "; // Condição neutra para facilitar adição de filtros

        $params = [];
        if ($filtroInstituicao) {
            $query .= " AND le.instituicao_id = :filtro_instituicao ";
            $params[':filtro_instituicao'] = $filtroInstituicao;
        }
        if ($filtroStatus) {
            $query .= " AND le.status = :filtro_status ";
            $params[':filtro_status'] = $filtroStatus;
        }

        $query .= " ORDER BY le.criado_em DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // --- FIM NOVO MÉTODO ---
}
?>