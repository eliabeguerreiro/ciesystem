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

$database = new Database();
$db = $database->getConnection();

$erro = '';
$dados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $docId = (int)($input['doc_id'] ?? 0);
    $acao = $input['acao'] ?? ''; // 'validar' ou 'reenviar'
    $observacao = trim($input['observacao'] ?? '');

    if ($docId <= 0 || !in_array($acao, ['validar', 'reenviar'])) {
        $erro = "Dados inválidos.";
    } else {
        $novoStatus = ($acao === 'validar') ? 'validado' : 'invalido';
        $observacaoFinal = $acao === 'reenviar' ? ($observacao ?: 'Reenvio solicitado.') : ($observacao ?: 'Validado pelo administrador.');

        // Atualizar o status e observação no banco
        $queryUpdate = "UPDATE documentos_anexados SET validado = :validado, observacoes_validacao = :observacao WHERE id = :id";
        $stmtUpdate = $db->prepare($queryUpdate);
        $stmtUpdate->bindParam(':validado', $novoStatus);
        $stmtUpdate->bindParam(':observacao', $observacaoFinal);
        $stmtUpdate->bindParam(':id', $docId, PDO::PARAM_INT);

        if ($stmtUpdate->execute()) {
            $dados['success'] = true;
            $dados['message'] = $acao === 'validar' ? "Documento validado." : "Solicitação de reenvio registrada.";
            $dados['novo_status'] = ucfirst($novoStatus);
            $dados['nova_obs'] = htmlspecialchars($observacaoFinal);
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