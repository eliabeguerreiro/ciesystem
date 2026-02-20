<?php
session_start();

// Verificação de acesso (ajuste conforme sua lógica de autenticação)
require_once __DIR__ . '/../app/controllers/AuthController.php';
$auth = new AuthController();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

// Carrega dependências
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Inscricao.php'; // Adicionado para atualizar o status da inscrição

$database = new Database();
$db = $database->getConnection();

$erro = '';
$dados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $docId = $input['doc_id'] ?? null; // Pode ser numérico ou string como 'foto_estudante_XX'
    $acao = $input['acao'] ?? ''; // 'validar' ou 'reenviar'
    $observacao = trim($input['observacao'] ?? '');

    if (empty($docId) || !in_array($acao, ['validar', 'reenviar'])) {
        $erro = "Dados inválidos.";
    } else {
        // --- LÓGICA PARA DOCUMENTO DA INSCRIÇÃO (ID NUMÉRICO) ---
        if (is_numeric($docId)) {
            $novoStatus = ($acao === 'validar') ? 'validado' : 'invalido';
            $observacaoFinal = $acao === 'reenviar' ? ($observacao ?: 'Reenvio solicitado.') : ($observacao ?: 'Validado pelo administrador.');

            // 1. Atualizar o status e observação do documento específico no banco
            $queryUpdate = "UPDATE documentos_anexados SET validado = :validado, observacoes_validacao = :observacao WHERE id = :id";
            $stmtUpdate = $db->prepare($queryUpdate);
            $stmtUpdate->bindParam(':validado', $novoStatus);
            $stmtUpdate->bindParam(':observacao', $observacaoFinal);
            $stmtUpdate->bindParam(':id', $docId, PDO::PARAM_INT);

            if ($stmtUpdate->execute()) {
                // 2. Obter o entidade_id (ID da inscrição) do documento atualizado
                $queryGetEntidade = "SELECT entidade_id FROM documentos_anexados WHERE id = :id";
                $stmtGetEntidade = $db->prepare($queryGetEntidade);
                $stmtGetEntidade->bindParam(':id', $docId, PDO::PARAM_INT);
                $stmtGetEntidade->execute();
                $rowEntidade = $stmtGetEntidade->fetch(PDO::FETCH_ASSOC);

                if ($rowEntidade) {
                    $inscricaoId = $rowEntidade['entidade_id'];

                    // 3. Verificar se TODOS os documentos da inscrição (entidade_tipo = 'inscricao', entidade_id = $inscricaoId) estão 'validado'
                    // Esta verificação ignora a foto do estudante, pois ela não está na tabela documentos_anexados com entidade_tipo = 'inscricao'.
                    $queryCheckAll = "SELECT DISTINCT validado FROM documentos_anexados WHERE entidade_tipo = 'inscricao' AND entidade_id = :entidade_id";
                    $stmtCheckAll = $db->prepare($queryCheckAll);
                    $stmtCheckAll->bindParam(':entidade_id', $inscricaoId, PDO::PARAM_INT);
                    $stmtCheckAll->execute();
                    $statusUnicos = $stmtCheckAll->fetchAll(PDO::FETCH_COLUMN);

                    // Se o array de status únicos contiver APENAS 'validado', todos os docs da inscrição estão ok
                    $todosValidados = (count($statusUnicos) === 1 && in_array('validado', $statusUnicos));

                    // 4. Atualizar o campo matricula_validada da inscrição
                    $inscricaoModel = new Inscricao($db);
                    $inscricaoModel->id = $inscricaoId;
                    if ($inscricaoModel->atualizarMatriculaValidada($todosValidados)) {
                        // Mensagem opcional sobre o status da inscrição
                        $msgInscricao = $todosValidados ? " (e inscrição marcada como 'documentos validados')." : " (mas inscrição ainda tem documentos pendentes ou inválidos).";
                        $dados['success'] = true;
                        $dados['message'] = $acao === 'validar' ? "Documento validado." . $msgInscricao : "Solicitação de reenvio registrada.";
                        $dados['novo_status'] = ucfirst($novoStatus);
                        $dados['nova_obs'] = htmlspecialchars($observacaoFinal);
                        $dados['inscricao_atualizada'] = $todosValidados; // Indica se o status geral da inscrição mudou
                    } else {
                        // Mesmo atualizando o doc, falhou ao atualizar o status da inscrição
                        $dados['success'] = true; // A ação no documento foi um sucesso
                        $dados['message'] = ($acao === 'validar' ? "Documento validado." : "Solicitação de reenvio registrada.") . " Erro ao atualizar status geral da inscrição.";
                        $dados['novo_status'] = ucfirst($novoStatus);
                        $dados['nova_obs'] = htmlspecialchars($observacaoFinal);
                        $dados['inscricao_atualizada'] = false;
                    }
                } else {
                     $erro = "Erro ao obter ID da inscrição associada ao documento.";
                }
            } else {
                $errorInfo = $stmtUpdate->errorInfo();
                $erro = "Erro ao atualizar documento: " . $errorInfo[2];
            }

        // --- LÓGICA PARA FOTO DO ESTUDANTE (ID EM FORMATO 'foto_estudante_XX') ---
        } else if (is_string($docId) && strpos($docId, 'foto_estudante_') === 0) {
            // Neste caso, a foto do estudante é tratada como um item visual na tela,
            // mas NÃO afeta o status matricula_validada da inscrição.
            // Podemos apenas responder que a ação foi reconhecida, ou simplesmente ignorar.
            // Para manter a consistência com a interface (botões aparecem), vamos responder com sucesso.
            // A lógica de validação da foto deve ser tratada de forma diferente, se necessário,
            // talvez apenas registrando a ação ou atualizando um campo específico no futuro.
            // Por enquanto, apenas enviamos uma resposta de sucesso para a interface.
            $dados['success'] = true;
            $dados['message'] = $acao === 'validar' ? "Foto do estudante marcada como validada." : "Solicitação de reenvio da foto registrada.";
            $dados['novo_status'] = ucfirst(($acao === 'validar') ? 'validado' : 'invalido');
            $dados['nova_obs'] = htmlspecialchars($observacao);
            $dados['inscricao_atualizada'] = false; // A inscrição não muda de status por causa da foto aqui
        } else {
             $erro = "Erro: Formato de ID do documento desconhecido.";
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