<?php
session_start();

// Verificação de acesso
require_once __DIR__ . '/../app/controllers/AuthController.php';
$auth = new AuthController();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

// Carrega dependências
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Inscricao.php';
require_once __DIR__ . '/../app/models/Log.php';

$database = new Database();
$db = $database->getConnection();

$erro = '';
$dados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $docId = (int)($input['doc_id'] ?? 0);
    $acao = $input['acao'] ?? '';
    $observacao = trim($input['observacao'] ?? '');

    if ($docId <= 0 || !in_array($acao, ['validar', 'reenviar'])) {
        $erro = "Dados inválidos.";
    } else {
        $novoStatus = ($acao === 'validar') ? 'validado' : 'invalido';
        $observacaoFinal = $acao === 'reenviar' 
            ? ($observacao ?: 'Reenvio solicitado.') 
            : ($observacao ?: 'Validado pelo administrador.');

        // 1. Atualizar o status e observação do documento específico
        $queryUpdate = "UPDATE documentos_anexados 
                        SET validado = :validado, 
                            observacoes_validacao = :observacao,
                            atualizado_em = NOW() 
                        WHERE id = :id";
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

                // 3. Verificar se TODOS os documentos da inscrição estão 'validado'
                $queryCheckAll = "SELECT DISTINCT validado 
                                  FROM documentos_anexados 
                                  WHERE entidade_tipo = 'inscricao' 
                                    AND entidade_id = :entidade_id";
                $stmtCheckAll = $db->prepare($queryCheckAll);
                $stmtCheckAll->bindParam(':entidade_id', $inscricaoId, PDO::PARAM_INT);
                $stmtCheckAll->execute();
                $statusUnicos = $stmtCheckAll->fetchAll(PDO::FETCH_COLUMN);

                // Se houver APENAS 'validado' no array, todos os docs estão OK
                $todosValidados = (count($statusUnicos) === 1 && in_array('validado', $statusUnicos));

                // 4. Atualizar o campo matricula_validada da inscrição
                $inscricaoModel = new Inscricao($db);
                $inscricaoModel->id = $inscricaoId;
                
                if ($inscricaoModel->atualizarMatriculaValidada($todosValidados)) {
                    
                    // === NOVO: Atualizar status_validacao do estudante quando todos os docs estiverem validados ===
                    $estudanteAprovado = false;
                    
                    if ($todosValidados) {
                        // Buscar o ID do estudante vinculado a esta inscrição
                        $queryEstudante = "SELECT estudante_id FROM inscricoes WHERE id = :inscricao_id LIMIT 1";
                        $stmtEstudante = $db->prepare($queryEstudante);
                        $stmtEstudante->bindParam(':inscricao_id', $inscricaoId, PDO::PARAM_INT);
                        $stmtEstudante->execute();
                        $rowEstudante = $stmtEstudante->fetch(PDO::FETCH_ASSOC);

                        if ($rowEstudante) {
                            $estudanteId = $rowEstudante['estudante_id'];

                            // Atualizar status_validacao para 'dados_aprovados'
                            $queryUpdateEstudante = "UPDATE estudantes 
                                                     SET status_validacao = 'dados_aprovados', 
                                                         atualizado_em = NOW() 
                                                     WHERE id = :estudante_id";
                            $stmtUpdateEstudante = $db->prepare($queryUpdateEstudante);
                            $stmtUpdateEstudante->bindParam(':estudante_id', $estudanteId, PDO::PARAM_INT);
                            
                            if ($stmtUpdateEstudante->execute()) {
                                $estudanteAprovado = true;
                                
                                // Registrar no log a aprovação automática do estudante
                                $log = new Log($db);
                                $log->registrar(
                                    $_SESSION['user_id'] ?? null,
                                    'estudante_aprovado_automatico',
                                    "Estudante ID: {$estudanteId} teve status_validacao atualizado para 'dados_aprovados' após validação de todos os documentos da inscrição {$inscricaoId}",
                                    $estudanteId,
                                    'estudantes'
                                );
                            }
                        }
                    }
                    // === FIM NOVO ===

                    // Mensagem de retorno
                    $msgInscricao = $todosValidados 
                        ? " (inscrição marcada como 'documentos validados')" 
                        : " (inscrição ainda possui documentos pendentes)";
                    
                    $dados['success'] = true;
                    $dados['message'] = $acao === 'validar' 
                        ? "Documento validado." . $msgInscricao 
                        : "Solicitação de reenvio registrada.";
                    $dados['novo_status'] = ucfirst($novoStatus);
                    $dados['nova_obs'] = htmlspecialchars($observacaoFinal);
                    $dados['inscricao_atualizada'] = $todosValidados;
                    $dados['estudante_aprovado'] = $estudanteAprovado; // ← Novo campo para o frontend
                    
                } else {
                    // Atualizou o doc, mas falhou ao atualizar status da inscrição
                    $dados['success'] = true;
                    $dados['message'] = ($acao === 'validar' ? "Documento validado." : "Solicitação de reenvio registrada.") . 
                                        " Erro ao atualizar status geral da inscrição.";
                    $dados['novo_status'] = ucfirst($novoStatus);
                    $dados['nova_obs'] = htmlspecialchars($observacaoFinal);
                    $dados['inscricao_atualizada'] = false;
                    $dados['estudante_aprovado'] = false;
                }
            } else {
                $erro = "Erro ao obter ID da inscrição associada ao documento.";
            }
        } else {
            $errorInfo = $stmtUpdate->errorInfo();
            $erro = "Erro ao atualizar documento: " . $errorInfo[2];
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