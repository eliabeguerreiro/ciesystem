<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Modelo antigo para documentos do estudante.
 * Este modelo foi atualizado para delegar suas operações para a nova tabela 'documentos_anexados'.
 * Ele age como uma camada de compatibilidade para código legado que ainda chama este modelo.
 */
class DocumentoEstudante {
    private $conn;
    // A referência à tabela antiga é mantida apenas para fins de log ou migração futura, se necessário.
    // private $table = 'documentos_estudante'; 
    private $tableNova = 'documentos_anexados'; // Nova tabela unificada

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Salva dois arquivos (frente e verso) para o mesmo tipo de documento, associando ao estudante.
     * @param int $estudante_id ID do estudante
     * @param array $frente Arquivo da frente ($_FILES['campo_frente'])
     * @param array $verso Arquivo do verso ($_FILES['campo_verso'])
     * @param string $tipo Tipo do documento ('rg', 'cnh', 'passaporte', 'cpf')
     * @param string $statusValidacaoDoEstudante O status de validação do estudante ('pendente', 'dados_aprovados')
     * @return bool True se ambos forem salvos com sucesso na nova tabela, False caso contrário
     */
    public function salvarFrenteVerso($estudante_id, $frente, $verso, $tipo, $statusValidacaoDoEstudante = 'pendente') { // Adicionado parâmetro
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
        $stmt->bindValue(':entidade_tipo', 'estudante', PDO::PARAM_STR); // Fixo para este modelo
        $stmt->bindValue(':entidade_id', $estudante_id, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipoFrente, PDO::PARAM_STR);
        $stmt->bindValue(':caminho_arquivo', "uploads/documentos/{$nomeFrente}"); // Caminho unificado
        $stmt->bindValue(':descricao', $frente['name']);
        $stmt->bindValue(':validado', $estadoInicial, PDO::PARAM_STR); // <-- Usar estado determinado

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
            $this->deletarUltimoInseridoNovaTabela($estudante_id, $tipoFrente, $nomeFrente); // Tenta apagar o registro da frente
            return false; // Falha ao mover o arquivo do verso
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':entidade_tipo', 'estudante', PDO::PARAM_STR); // Fixo para este modelo
        $stmt->bindValue(':entidade_id', $estudante_id, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipoVerso, PDO::PARAM_STR);
        $stmt->bindValue(':caminho_arquivo', "uploads/documentos/{$nomeVerso}"); // Caminho unificado
        $stmt->bindValue(':descricao', $verso['name']);
        $stmt->bindValue(':validado', $estadoInicial, PDO::PARAM_STR); // <-- Usar estado determinado

        if (!$stmt->execute()) {
            // Se falhar ao inserir no BD o verso, apaga os arquivos e tenta apagar o registro da frente
            unlink($caminhoAbsolutoFrente);
            unlink($caminhoAbsolutoVerso);
            $this->deletarUltimoInseridoNovaTabela($estudante_id, $tipoFrente, $nomeFrente); // Tenta apagar o registro da frente
            return false;
        }

        return true; // Ambos salvos com sucesso na nova tabela
    }

    /**
     * Salva um único arquivo (como a selfie) para um estudante, associando à nova tabela.
     * @param int $estudante_id ID do estudante
     * @param array $file Arquivo ($_FILES['campo_do_formulario'])
     * @param string $tipo Tipo do documento ('selfie_documento', etc.) - Deve estar no ENUM da nova tabela.
     * @param string $statusValidacaoDoEstudante O status de validação do estudante ('pendente', 'dados_aprovados')
     * @return bool True se salvo com sucesso na nova tabela, False caso contrário
     */
    public function salvarUnicoArquivo($estudante_id, $file, $tipo, $statusValidacaoDoEstudante = 'pendente') { // Adicionado parâmetro
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Tipos permitidos devem corresponder ao ENUM da nova tabela
        // Exemplo: 'selfie_documento', 'comprovante_residencia', etc.
        // Por enquanto, assumimos que o tipo é válido se passar pelo ENUM do DB.
        // $tiposPermitidos = ['selfie_documento', 'comprovante_residencia']; // Exemplo fixo
        // if (!in_array(strtolower($tipo), $tiposPermitidos)) {
        //     return false;
        // }

        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            return false;
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
                      VALUES (:entidade_tipo, :entidade_id, :tipo, :caminho_arquivo, :descricao, :validado)"; // Adicionado 'validado'
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':entidade_tipo', 'estudante', PDO::PARAM_STR); // Fixo para este modelo
            $stmt->bindValue(':entidade_id', $estudante_id, PDO::PARAM_INT);
            $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
            $stmt->bindValue(':caminho_arquivo', "uploads/documentos/{$nome}"); // Caminho unificado
            $stmt->bindValue(':descricao', $file['name']);
            $stmt->bindValue(':validado', $estadoInicial, PDO::PARAM_STR); // <-- Usar estado determinado
            return $stmt->execute();
        }
        return false;
    }


    /**
     * Busca documentos de um estudante por tipo na nova tabela.
     * @param int $estudante_id ID do estudante
     * @param string $tipo Tipo do documento (ex: 'rg_frente', 'selfie_documento')
     * @return array Lista de documentos do tipo especificado associados ao estudante
     */
    public function buscarPorEstudanteETipo($estudante_id, $tipo) {
        $query = "SELECT * FROM {$this->tableNova} WHERE entidade_tipo = :entidade_tipo AND entidade_id = :entidade_id AND tipo = :tipo ORDER BY criado_em ASC";
        $stmt = $this->conn->prepare($query);
        // --- CORREÇÃO: Usar bindValue para valor literal ---
        $stmt->bindValue(':entidade_tipo', 'estudante', PDO::PARAM_STR); // Fixo para este modelo
        // --- FIM CORREÇÃO ---
        $stmt->bindParam(':entidade_id', $estudante_id, PDO::PARAM_INT);
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
     * Deleta arquivos físicos e registros na nova tabela associados a um estudante e tipo.
     * @param int $estudante_id ID do estudante
     * @param string $tipo Tipo do documento (ex: 'rg', 'selfie_documento')
     * @return bool
     */
    public function deletarPorEstudanteETipo($estudante_id, $tipo) {
        // Mapear tipo antigo para novos tipos (frente/verso) se necessário
        // Exemplo: se $tipo for 'rg', precisamos deletar 'rg_frente' e 'rg_verso'
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
            $documentos = $this->buscarPorEstudanteETipo($estudante_id, $tipoIndividual);
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

    // Função auxiliar para tentar limpar inconsistência na nova tabela (opicional, pode ser removida se deletarPorEstudanteETipo for usada)
    private function deletarUltimoInseridoNovaTabela($estudante_id, $tipo, $nome_arquivo) {
         $query = "DELETE FROM {$this->tableNova} WHERE entidade_tipo = :entidade_tipo AND entidade_id = :entidade_id AND tipo = :tipo AND caminho_arquivo LIKE :caminho ORDER BY criado_em DESC LIMIT 1";
         $stmt = $this->conn->prepare($query);
         // --- CORREÇÃO: Usar bindValue para valor literal ---
         $stmt->bindValue(':entidade_tipo', 'estudante', PDO::PARAM_STR); // Fixo para este modelo
         // --- FIM CORREÇÃO ---
         $stmt->bindParam(':entidade_id', $estudante_id, PDO::PARAM_INT);
         $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
         $stmt->bindValue(':caminho', '%'.$nome_arquivo.'%'); // Pesquisa aproximada pelo nome do arquivo
         return $stmt->execute();
    }
}
?>