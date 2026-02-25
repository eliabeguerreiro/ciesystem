<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Modelo antigo para documentos do estudante.
 * Este modelo foi atualizado para delegar suas operações para a nova tabela 'documentos_anexados'.
 * Ele agora associa documentos à INSERÇÃO, não mais diretamente ao estudante.
 */
class DocumentoEstudante {
    private $conn;
    private $tableNova = 'documentos_anexados'; // Nova tabela unificada

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Salva dois arquivos (frente e verso) para o mesmo tipo de documento, associando à inscrição.
     * @param int $estudante_id ID do estudante (necessário para buscar a inscrição)
     * @param array $frente Arquivo da frente ($_FILES['campo_frente'])
     * @param array $verso Arquivo do verso ($_FILES['campo_verso'])
     * @param string $tipo Tipo do documento ('rg', 'cnh', 'passaporte', 'cpf')
     * @param string $statusValidacaoDoEstudante O status de validação do estudante ('pendente', 'dados_aprovados')
     * @return bool True se ambos forem salvos com sucesso na nova tabela, False caso contrário
     */
    public function salvarFrenteVerso($estudante_id, $frente, $verso, $tipo, $statusValidacaoDoEstudante = 'pendente') {
        // Validação dos arquivos
        if (!$frente || $frente['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        if (!$verso || $verso['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Tipos permitidos devem corresponder ao ENUM antigo e serem mapeados para o novo
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

        // Obter o ID da inscrição mais recente do estudante
        $id_inscricao = $this->getIdInscricaoMaisRecente($estudante_id);
        if (!$id_inscricao) {
            error_log("Erro: Não foi possível encontrar uma inscrição para o estudante ID: " . $estudante_id);
            return false; // Falha se não encontrar a inscrição
        }

        // Mapear tipo antigo para tipos novos (frente/verso)
        $tipoFrente = $tipo . '_frente';
        $tipoVerso = $tipo . '_verso';

        // --- LÓGICA DE VALIDAÇÃO AUTOMÁTICA ---
        $estadoInicial = ($statusValidacaoDoEstudante === 'dados_aprovados') ? 'validado' : 'pendente';
        // --- FIM LÓGICA DE VALIDAÇÃO AUTOMÁTICA ---

        // Salvar arquivo da frente na nova tabela
        $nomeFrente = "doc_{$tipoFrente}_" . uniqid() . '.' . $extFrente;
        $caminhoAbsolutoFrente = __DIR__ . "/../../public/uploads/documentos/{$nomeFrente}"; // Pasta unificada

        if (!is_dir(dirname($caminhoAbsolutoFrente))) {
            mkdir(dirname($caminhoAbsolutoFrente), 0777, true);
        }

        if (!move_uploaded_file($frente['tmp_name'], $caminhoAbsolutoFrente)) {
            return false; // Falha ao mover o arquivo da frente
        }

        $query = "INSERT INTO {$this->tableNova} (entidade_tipo, entidade_id, tipo, caminho_arquivo, descricao, validado)
                  VALUES (:entidade_tipo, :entidade_id, :tipo, :caminho_arquivo, :descricao, :validado)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':entidade_tipo', 'inscricao', PDO::PARAM_STR); // <-- Agora é 'inscricao'
        $stmt->bindValue(':entidade_id', $id_inscricao, PDO::PARAM_INT); // <-- ID da inscrição
        $stmt->bindValue(':tipo', $tipoFrente, PDO::PARAM_STR);
        $stmt->bindValue(':caminho_arquivo', "uploads/documentos/{$nomeFrente}"); // Caminho unificado
        $stmt->bindValue(':descricao', $frente['name']);
        $stmt->bindValue(':validado', $estadoInicial, PDO::PARAM_STR);

        if (!$stmt->execute()) {
            // Se falhar, tentar apagar o arquivo recém-criado
            unlink($caminhoAbsolutoFrente);
            return false;
        }

        // Salvar arquivo do verso na nova tabela
        $nomeVerso = "doc_{$tipoVerso}_" . uniqid() . '.' . $extVerso;
        $caminhoAbsolutoVerso = __DIR__ . "/../../public/uploads/documentos/{$nomeVerso}"; // Pasta unificada

        if (!is_dir(dirname($caminhoAbsolutoVerso))) {
            mkdir(dirname($caminhoAbsolutoVerso), 0777, true);
        }

        if (!move_uploaded_file($verso['tmp_name'], $caminhoAbsolutoVerso)) {
            // Se falhar ao mover o verso, apaga o da frente também para manter consistência
            unlink($caminhoAbsolutoFrente);
            $this->deletarUltimoInseridoNovaTabela($id_inscricao, $tipoFrente, $nomeFrente, 'inscricao'); // Atualizado
            return false; // Falha ao mover o arquivo do verso
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':entidade_tipo', 'inscricao', PDO::PARAM_STR); // <-- Agora é 'inscricao'
        $stmt->bindValue(':entidade_id', $id_inscricao, PDO::PARAM_INT); // <-- ID da inscrição
        $stmt->bindValue(':tipo', $tipoVerso, PDO::PARAM_STR);
        $stmt->bindValue(':caminho_arquivo', "uploads/documentos/{$nomeVerso}"); // Caminho unificado
        $stmt->bindValue(':descricao', $verso['name']);
        $stmt->bindValue(':validado', $estadoInicial, PDO::PARAM_STR);

        if (!$stmt->execute()) {
            // Se falhar ao inserir no BD o verso, apaga os arquivos e tenta apagar o registro da frente
            unlink($caminhoAbsolutoFrente);
            unlink($caminhoAbsolutoVerso);
            $this->deletarUltimoInseridoNovaTabela($id_inscricao, $tipoFrente, $nomeFrente, 'inscricao'); // Atualizado
            return false;
        }

        return true; // Ambos salvos com sucesso na nova tabela
    }

    /**
     * Salva um único arquivo (como a selfie ou foto 3x4) para um estudante, associando à inscrição.
     * @param int $estudante_id ID do estudante (necessário para buscar a inscrição)
     * @param array $file Arquivo ($_FILES['campo_do_formulario'])
     * @param string $tipo Tipo do documento ('selfie_documento', 'foto_3x4', etc.) - Deve estar no ENUM da nova tabela.
     * @param string $statusValidacaoDoEstudante O status de validação do estudante ('pendente', 'dados_aprovados')
     * @return bool True se salvo com sucesso na nova tabela, False caso contrário
     */
    public function salvarUnicoArquivo($estudante_id, $file, $tipo, $statusValidacaoDoEstudante = 'pendente') {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Tipos permitidos devem corresponder ao ENUM da nova tabela
        // Exemplo: 'selfie_documento', 'comprovante_residencia', 'foto_3x4', etc.
        // Por enquanto, assume-se que o tipo é válido se passar pelo ENUM do DB.
        // $tiposPermitidos = ['selfie_documento', 'comprovante_residencia', 'foto_3x4']; // Exemplo fixo
        // if (!in_array(strtolower($tipo), $tiposPermitidos)) {
        //     return false;
        // }

        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            return false;
        }

        // Obter o ID da inscrição mais recente do estudante
        $id_inscricao = $this->getIdInscricaoMaisRecente($estudante_id);
        if (!$id_inscricao) {
            error_log("Erro: Não foi possível encontrar uma inscrição para o estudante ID: " . $estudante_id);
            return false; // Falha se não encontrar a inscrição
        }

        $nome = "doc_{$tipo}_" . uniqid() . '.' . $ext;
        $caminhoAbsoluto = __DIR__ . "/../../public/uploads/documentos/{$nome}"; // Pasta unificada

        if (!is_dir(dirname($caminhoAbsoluto))) {
            mkdir(dirname($caminhoAbsoluto), 0777, true);
        }

        // --- LÓGICA DE VALIDAÇÃO AUTOMÁTICA ---
        $estadoInicial = ($statusValidacaoDoEstudante === 'dados_aprovados') ? 'validado' : 'pendente';
        // --- FIM LÓGICA DE VALIDAÇÃO AUTOMÁTICA ---

        if (move_uploaded_file($file['tmp_name'], $caminhoAbsoluto)) {
            $query = "INSERT INTO {$this->tableNova} (entidade_tipo, entidade_id, tipo, caminho_arquivo, descricao, validado)
                      VALUES (:entidade_tipo, :entidade_id, :tipo, :caminho_arquivo, :descricao, :validado)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':entidade_tipo', 'inscricao', PDO::PARAM_STR); // <-- Agora é 'inscricao'
            $stmt->bindValue(':entidade_id', $id_inscricao, PDO::PARAM_INT); // <-- ID da inscrição
            $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
            $stmt->bindValue(':caminho_arquivo', "uploads/documentos/{$nome}"); // Caminho unificado
            $stmt->bindValue(':descricao', $file['name']);
            $stmt->bindValue(':validado', $estadoInicial, PDO::PARAM_STR);
            return $stmt->execute();
        }
        return false;
    }


    /**
     * Busca documentos de um estudante por tipo, associados à sua inscrição mais recente.
     * @param int $estudante_id ID do estudante
     * @param string $tipo Tipo do documento (ex: 'rg_frente', 'selfie_documento', 'foto_3x4')
     * @return array Lista de documentos do tipo especificado associados à inscrição do estudante
     */
    public function buscarPorEstudanteETipo($estudante_id, $tipo) {
        // Obter o ID da inscrição mais recente do estudante
        $id_inscricao = $this->getIdInscricaoMaisRecente($estudante_id);
        if (!$id_inscricao) {
            return []; // Retorna vazio se não encontrar a inscrição
        }

        $query = "SELECT * FROM {$this->tableNova} WHERE entidade_tipo = :entidade_tipo AND entidade_id = :entidade_id AND tipo = :tipo ORDER BY criado_em ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':entidade_tipo', 'inscricao', PDO::PARAM_STR); // <-- Agora é 'inscricao'
        $stmt->bindParam(':entidade_id', $id_inscricao, PDO::PARAM_INT); // <-- ID da inscrição
        $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
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

    /**
     * Deleta arquivos físicos e registros na nova tabela associados à inscrição de um estudante e tipo.
     * @param int $estudante_id ID do estudante (necessário para buscar a inscrição)
     * @param string $tipo Tipo do documento (ex: 'rg', 'selfie_documento', 'foto_3x4')
     * @return bool
     */
    public function deletarPorEstudanteETipo($estudante_id, $tipo) {
        // Obter o ID da inscrição mais recente do estudante
        $id_inscricao = $this->getIdInscricaoMaisRecente($estudante_id);
        if (!$id_inscricao) {
            return false; // Retorna falso se não encontrar a inscrição
        }

        // Mapear tipo antigo para novos tipos (frente/verso) se necessário
        $tiposParaDeletar = [];
        $tiposBase = ['rg', 'cnh', 'passaporte', 'cpf']; // Tipos que tem frente/verso
        if (in_array($tipo, $tiposBase)) {
            $tiposParaDeletar = [$tipo . '_frente', $tipo . '_verso'];
        } else {
            // Se não for um tipo base com frente/verso, assume que é um tipo único
            $tiposParaDeletar = [$tipo];
        }

        $success = true;
        foreach ($tiposParaDeletar as $tipoIndividual) {
            $documentos = $this->buscarPorEstudanteETipo($estudante_id, $tipoIndividual); // Chama o método atualizado
            foreach ($documentos as $doc) {
                if (!$this->deletarArquivo($doc['caminho_arquivo']) || !$this->deletarRegistroNovaTabela($doc['id'])) {
                    $success = false; // Marca falha, mas tenta apagar os demais
                }
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
     * Deleta um registro do banco de dados na nova tabela.
     * @param int $id ID do registro
     * @return bool
     */
    public function deletarRegistroNovaTabela($id) {
        $query = "DELETE FROM {$this->tableNova} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Função auxiliar para tentar limpar inconsistência na nova tabela (opicional)
    private function deletarUltimoInseridoNovaTabela($entidade_id, $tipo, $nome_arquivo, $entidade_tipo = 'inscricao') { // Atualizado
         $query = "DELETE FROM {$this->tableNova} WHERE entidade_tipo = :entidade_tipo AND entidade_id = :entidade_id AND tipo = :tipo AND caminho_arquivo LIKE :caminho ORDER BY criado_em DESC LIMIT 1";
         $stmt = $this->conn->prepare($query);
         $stmt->bindValue(':entidade_tipo', $entidade_tipo, PDO::PARAM_STR); // <-- Agora é dinâmico, padrão 'inscricao'
         $stmt->bindParam(':entidade_id', $entidade_id, PDO::PARAM_INT);
         $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
         $stmt->bindValue(':caminho', '%'.$nome_arquivo.'%'); // Pesquisa aproximada pelo nome do arquivo
         return $stmt->execute();
    }

    // --- NOVO MÉTODO: Obter ID da inscrição mais recente ---
    private function getIdInscricaoMaisRecente($estudante_id) {
        $query = "SELECT id FROM inscricoes WHERE estudante_id = :estudante_id ORDER BY id DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':estudante_id', $estudante_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['id'] : null;
    }
    // --- FIM NOVO MÉTODO ---
}
?>