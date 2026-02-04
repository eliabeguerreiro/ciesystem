<?php
require_once __DIR__ . '/../config/database.php';

class DocumentoEstudante {
    private $conn;
    private $table = 'documentos_estudante';

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Salva dois arquivos (frente e verso) para o mesmo tipo de documento.
     * @param int $estudante_id ID do estudante
     * @param array $frente Arquivo da frente ($_FILES['campo_frente'])
     * @param array $verso Arquivo do verso ($_FILES['campo_verso'])
     * @param string $tipo Tipo do documento ('rg', 'cnh', 'passaporte', 'cpf')
     * @return bool True se ambos forem salvos com sucesso, False caso contrário
     */
    public function salvarFrenteVerso($estudante_id, $frente, $verso, $tipo) {
        // Validação dos arquivos
        if (!$frente || $frente['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        if (!$verso || $verso['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Tipos permitidos devem corresponder ao ENUM
        $tiposPermitidos = ['rg', 'cnh', 'passaporte', 'cpf'];
        if (!in_array(strtolower($tipo), $tiposPermitidos)) {
            return false;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

        // Validação da extensão para frente e verso
        $extFrente = strtolower(pathinfo($frente['name'], PATHINFO_EXTENSION));
        $extVerso = strtolower(pathinfo($verso['name'], PATHINFO_EXTENSION));

        if (!in_array($extFrente, $allowed) || !in_array($extVerso, $allowed)) {
            return false;
        }

        // Salvar arquivo da frente
        $nomeFrente = "doc_{$tipo}_frente_" . uniqid() . '.' . $extFrente;
        $caminhoAbsolutoFrente = __DIR__ . "/../../public/uploads/documentos_estudante/{$nomeFrente}";

        if (!is_dir(dirname($caminhoAbsolutoFrente))) {
            mkdir(dirname($caminhoAbsolutoFrente), 0777, true);
        }

        if (!move_uploaded_file($frente['tmp_name'], $caminhoAbsolutoFrente)) {
            return false; // Falha ao mover o arquivo da frente
        }

        $query = "INSERT INTO {$this->table} (estudante_id, tipo, caminho_arquivo, descricao)
                  VALUES (:estudante_id, :tipo, :caminho_arquivo, :descricao)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':estudante_id', $estudante_id, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':caminho_arquivo', "uploads/documentos_estudante/{$nomeFrente}");
        $stmt->bindValue(':descricao', $frente['name']);

        if (!$stmt->execute()) {
            // Se falhar, tentar apagar o arquivo recém-criado
            unlink($caminhoAbsolutoFrente);
            return false;
        }

        // Salvar arquivo do verso
        $nomeVerso = "doc_{$tipo}_verso_" . uniqid() . '.' . $extVerso;
        $caminhoAbsolutoVerso = __DIR__ . "/../../public/uploads/documentos_estudante/{$nomeVerso}";

        if (!is_dir(dirname($caminhoAbsolutoVerso))) {
            mkdir(dirname($caminhoAbsolutoVerso), 0777, true);
        }

        if (!move_uploaded_file($verso['tmp_name'], $caminhoAbsolutoVerso)) {
            // Se falhar ao mover o verso, apaga o da frente também para manter consistência
            unlink($caminhoAbsolutoFrente);
            $this->deletarUltimoInserido($estudante_id, $tipo, $nomeFrente); // Tenta apagar o registro da frente
            return false; // Falha ao mover o arquivo do verso
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':estudante_id', $estudante_id, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':caminho_arquivo', "uploads/documentos_estudante/{$nomeVerso}");
        $stmt->bindValue(':descricao', $verso['name']);

        if (!$stmt->execute()) {
            // Se falhar ao inserir no BD o verso, apaga os arquivos e tenta apagar o registro da frente
            unlink($caminhoAbsolutoFrente);
            unlink($caminhoAbsolutoVerso);
            $this->deletarUltimoInserido($estudante_id, $tipo, $nomeFrente); // Tenta apagar o registro da frente
            return false;
        }

        return true; // Ambos salvos com sucesso
    }

    /**
     * Busca documentos de um estudante por tipo.
     * @param int $estudante_id ID do estudante
     * @param string $tipo Tipo do documento
     * @return array Lista de documentos do tipo especificado
     */
    public function buscarPorEstudanteETipo($estudante_id, $tipo) {
        $query = "SELECT * FROM {$this->table} WHERE estudante_id = :estudante_id AND tipo = :tipo ORDER BY criado_em ASC"; // Ordem pode ajudar a distinguir frente/verso
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':estudante_id', $estudante_id, PDO::PARAM_INT);
        $stmt->bindParam(':tipo', $tipo);
        try {
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result ?: [];
        } catch (Exception $e) {
            // Log do erro pode ser útil
            error_log("Erro ao buscar documentos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Deleta arquivos físicos e registros no banco associados a um estudante e tipo.
     * @param int $estudante_id ID do estudante
     * @param string $tipo Tipo do documento
     * @return bool
     */
    public function deletarPorEstudanteETipo($estudante_id, $tipo) {
        $documentos = $this->buscarPorEstudanteETipo($estudante_id, $tipo);
        $success = true;

        foreach ($documentos as $doc) {
            if (!$this->deletarArquivo($doc['caminho_arquivo']) || !$this->deletarRegistro($doc['id'])) {
                $success = false; // Marca falha, mas tenta apagar os demais
            }
        }
        return $success;
    }

    /**
     * Deleta um arquivo físico.
     * @param string $caminhoRelativo Caminho relativo do arquivo
     * @return bool
     */
    public function deletarArquivo($caminhoRelativo) {
        if (empty($caminhoRelativo)) return true; // Nada para deletar
        $caminhoAbsoluto = __DIR__ . '/../../public/' . ltrim($caminhoRelativo, '/');
        if (file_exists($caminhoAbsoluto)) {
            return unlink($caminhoAbsoluto);
        }
        return true; // Arquivo já não existe
    }

    /**
     * Deleta um registro do banco de dados.
     * @param int $id ID do registro
     * @return bool
     */
    public function deletarRegistro($id) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Função auxiliar para tentar limpar inconsistência (opicional, pode ser removida se deletarPorEstudanteETipo for usada)
    private function deletarUltimoInserido($estudante_id, $tipo, $nome_arquivo) {
         $query = "DELETE FROM {$this->table} WHERE estudante_id = :estudante_id AND tipo = :tipo AND caminho_arquivo LIKE :caminho ORDER BY criado_em DESC LIMIT 1";
         $stmt = $this->conn->prepare($query);
         $stmt->bindParam(':estudante_id', $estudante_id, PDO::PARAM_INT);
         $stmt->bindParam(':tipo', $tipo);
         $stmt->bindValue(':caminho', '%'.$nome_arquivo.'%'); // Pesquisa aproximada pelo nome do arquivo
         return $stmt->execute();
    }
}

?>