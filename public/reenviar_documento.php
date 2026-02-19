<?php
// Este é um endpoint para o estudante reenviar um documento.
// Não requer autenticação, pois é para o público (estudante).
// Mas pode ser protegido por um token se necessário.

require_once __DIR__ . '/../app/config/database.php';

$database = new Database();
$db = $database->getConnection();

$erro = '';
$dados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $docId = (int)($input['doc_id'] ?? 0);
    $novoArquivo = $input['arquivo'] ?? null; // Base64 ou dados do arquivo

    if ($docId <= 0 || !$novoArquivo) {
        $erro = "Dados inválidos.";
    } else {
        // 1. Buscar o documento atual para obter o caminho antigo e o tipo
        $queryBusca = "SELECT caminho_arquivo, tipo FROM documentos_anexados WHERE id = :id";
        $stmtBusca = $db->prepare($queryBusca);
        $stmtBusca->bindParam(':id', $docId, PDO::PARAM_INT);
        $stmtBusca->execute();
        $docAtual = $stmtBusca->fetch(PDO::FETCH_ASSOC);

        if (!$docAtual) {
            $erro = "Documento não encontrado.";
        } else {
            $caminhoAntigo = $docAtual['caminho_arquivo'];
            $tipo = $docAtual['tipo'];

            // 2. Processar o novo arquivo (base64)
            // Supondo que o arquivo venha em base64, como "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABg..."
            $parts = explode(',', $novoArquivo);
            if (count($parts) < 2) {
                $erro = "Formato de arquivo inválido.";
            } else {
                $tipoArquivo = explode('/', explode(':', $parts[0])[1])[1];
                $data = base64_decode($parts[1]);

                // Gerar nome único
                $nomeUnico = "doc_{$tipo}_" . uniqid() . '.' . $tipoArquivo;
                $caminhoAbsoluto = __DIR__ . "/uploads/documentos/{$nomeUnico}";

                // Criar pasta se não existir
                if (!is_dir(dirname($caminhoAbsoluto))) {
                    mkdir(dirname($caminhoAbsoluto), 0777, true);
                }

                // Salvar o novo arquivo
                if (file_put_contents($caminhoAbsoluto, $data)) {
                    $caminhoRelativo = "uploads/documentos/{$nomeUnico}";

                    // 3. Deletar o arquivo antigo (opcional, mas recomendado)
                    $caminhoAntigoCompleto = __DIR__ . "/" . $caminhoAntigo;
                    if (file_exists($caminhoAntigoCompleto)) {
                        unlink($caminhoAntigoCompleto);
                    }

                    // 4. Atualizar o registro no banco de dados
                    $queryUpdate = "UPDATE documentos_anexados SET caminho_arquivo = :caminho, validado = 'pendente', observacoes_validacao = NULL WHERE id = :id";
                    $stmtUpdate = $db->prepare($queryUpdate);
                    $stmtUpdate->bindParam(':caminho', $caminhoRelativo);
                    $stmtUpdate->bindParam(':id', $docId, PDO::PARAM_INT);

                    if ($stmtUpdate->execute()) {
                        $dados['success'] = true;
                        $dados['message'] = "Documento reenviado com sucesso.";
                        $dados['novo_caminho'] = $caminhoRelativo;
                    } else {
                        $errorInfo = $stmtUpdate->errorInfo();
                        $erro = "Erro ao atualizar o documento: " . $errorInfo[2];
                    }
                } else {
                    $erro = "Erro ao salvar o novo arquivo.";
                }
            }
        }
    }
} else {
    $erro = "Método de requisição inválido.";
}

if ($erro) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $erro]);
} else {
    echo json_encode($dados);
}
?>