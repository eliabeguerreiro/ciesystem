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
require_once __DIR__ . '/../app/models/Estudante.php';

$database = new Database();
$db = $database->getConnection();

$erro = '';
$dados = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $docId = $input['doc_id'] ?? ''; // Espera-se algo como 'foto_estudante_44'
    $acao = $input['acao'] ?? '';
    $observacao = trim($input['observacao'] ?? '');

    if (empty($docId) || !in_array($acao, ['validar', 'reenviar'])) {
        $erro = "Dados inválidos.";
    } else {
        // Extrair o ID do estudante do docId
        if (strpos($docId, 'foto_estudante_') !== 0) {
            $erro = "Formato de ID da foto inválido.";
        } else {
            $estudanteId = (int)substr($docId, strlen('foto_estudante_'));
            if ($estudanteId <= 0) {
                $erro = "ID do estudante inválido.";
            } else {
                $novoStatus = ($acao === 'validar') ? 'validado' : 'invalido';
                $observacaoFinal = $acao === 'reenviar' ? ($observacao ?: 'Reenvio solicitado.') : ($observacao ?: 'Validado pelo administrador.');

                // Atualizar o status da foto no modelo Estudante
                $estudanteModel = new Estudante($db);
                $estudanteModel->id = $estudanteId;
                if ($estudanteModel->atualizarStatusFoto($novoStatus)) {
                    // Opcional: Aqui você poderia atualizar o status da inscrição se quiser,
                    // mas vamos manter a lógica separada para clareza.
                    $dados['success'] = true;
                    $dados['message'] = $acao === 'validar' ? "Foto do estudante validada." : "Solicitação de reenvio da foto registrada.";
                    $dados['novo_status'] = ucfirst($novoStatus);
                    $dados['nova_obs'] = htmlspecialchars($observacaoFinal);
                    $dados['inscricao_atualizada'] = false; // A inscrição não é atualizada aqui
                } else {
                    $erro = "Erro ao atualizar o status da foto no banco de dados.";
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