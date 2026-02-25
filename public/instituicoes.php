<?php
session_start();

// Verificação de acesso
require_once __DIR__ . '/../app/controllers/AuthController.php';
$auth = new AuthController();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    die("Acesso negado.");
}

// Carrega dependências
require_once __DIR__ . '/../app/models/Instituicao.php';

$database = new Database();
$db = $database->getConnection();
$instituicao = new Instituicao($db);

$erro = '';
$sucesso = '';

// ================================
// TRATAMENTO DE FORMULÁRIO (CADASTRO/EDIÇÃO)
// ================================

if ($_POST) {
    $instituicao->nome = $_POST['nome'] ?? '';
    $instituicao->endereco = $_POST['endereco'] ?? '';
    $instituicao->cidade = $_POST['cidade'] ?? '';
    $instituicao->estado = $_POST['estado'] ?? '';
    $instituicao->cep = $_POST['cep'] ?? '';
    $instituicao->status = $_POST['status'] ?? 'ativa'; // Padrão para ativa

    // Validações básicas
    if (empty($instituicao->nome)) {
        $erro = "O nome da instituição é obrigatório.";
    }

    // ================================
    // EDIÇÃO
    // ================================
    if (isset($_POST['id'])) {
        $instituicao->id = $_POST['id'];
        if (empty($erro)) {
            if ($instituicao->atualizar()) {
                // === LOG: Instituição editada ===
                require_once __DIR__ . '/../app/models/Log.php';
                $log = new Log($db);
                $log->registrar(
                    $_SESSION['user_id'],
                    'editou_instituicao',
                    "ID: {$instituicao->id}, Nome: {$instituicao->nome}",
                    $instituicao->id,
                    'instituicoes'
                );
                $sucesso = "Instituição atualizada com sucesso!";
                foreach ($_POST as $key => $value) $_POST[$key] = ''; // Limpa o formulário
            } else {
                $erro = "Erro ao atualizar instituição.";
            }
        }
    }
    // ================================
    // CADASTRO
    // ================================
    else {
        if (empty($erro)) {
            if ($instituicao->criar()) {
                // === LOG: Instituição criada ===
                require_once __DIR__ . '/../app/models/Log.php';
                $log = new Log($db);
                $novoId = $db->lastInsertId();
                $log->registrar(
                    $_SESSION['user_id'],
                    'criou_instituicao',
                    "Nome: {$instituicao->nome}",
                    $novoId,
                    'instituicoes'
                );
                $sucesso = "Instituição cadastrada com sucesso!";
                foreach ($_POST as $key => $value) $_POST[$key] = ''; // Limpa o formulário
            } else {
                $erro = "Erro ao cadastrar instituição.";
            }
        }
    }
}

// ================================
// AÇÕES VIA GET (Ativar/Desativar)
// ================================

if ($_GET) {
    if (isset($_GET['ativar']) || isset($_GET['desativar'])) {
        $id = (int)(isset($_GET['ativar']) ? $_GET['ativar'] : $_GET['desativar']);
        $acao = isset($_GET['ativar']) ? 'ativar' : 'desativar';
        $novoStatus = $acao === 'ativar' ? 'ativa' : 'inativa';

        $instituicao->id = $id;
        $registro = $instituicao->buscarPorId($id); // Busca para log

        if ($instituicao->atualizarStatus($novoStatus)) {
            // === LOG: Status da instituição atualizado ===
            require_once __DIR__ . '/../app/models/Log.php';
            $log = new Log($db);
            $log->registrar(
                $_SESSION['user_id'],
                $acao === 'ativar' ? 'ativou_instituicao' : 'desativou_instituicao',
                "ID: {$id}, Nome: {$registro['nome']}", // Usa o nome do registro buscado
                $id,
                'instituicoes'
            );
            $sucesso = "Status da instituição atualizado com sucesso.";
        } else {
            $erro = "Erro ao atualizar status da instituição.";
        }
    }
}

// ================================
// EDIÇÃO (carrega dados)
// ================================

$editar = null;
if (isset($_GET['editar'])) {
    $editar = $instituicao->buscarPorId((int)$_GET['editar']);
}

// ================================
// LISTAGEM
// ================================

$instituicoes = $instituicao->listar();

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Instituições</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f9f9f9; }
        .container { max-width: 1200px; margin: 0 auto; }
        h2 { color: #333; }
        .mensagem { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .erro { background: #ffebee; color: #c62828; }
        .sucesso { background: #e8f5e9; color: #2e7d32; }
        form { background: white; padding: 20px; border-radius: 6px; margin-bottom: 30px; }
        .form-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; min-width: 200px; }
        label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 0.9em; }
        input, select, textarea { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #1976d2; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #1565c0; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f5f5f5; }
        .acoes { white-space: nowrap; }
        a { color: #1976d2; text-decoration: none; margin-right: 10px; }
        a:hover { text-decoration: underline; }
        .voltar { display: inline-block; margin-bottom: 20px; color: #555; }
        .status-ativa { color: #2e7d32; font-weight: bold; }
        .status-inativa { color: #c62828; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="voltar">← Voltar ao Dashboard</a>
        <h2><?= $editar ? 'Editar Instituição' : 'Cadastrar Nova Instituição' ?></h2>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="mensagem sucesso"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php if ($editar): ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($editar['id']) ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($editar['nome'] ?? ($_POST['nome'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" required>
                        <option value="ativa" <?= ($editar && $editar['status'] === 'ativa') ? 'selected' : '' ?>>Ativa</option>
                        <option value="inativa" <?= ($editar && $editar['status'] === 'inativa') ? 'selected' : '' ?>>Inativa</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Endereço</label>
                    <textarea name="endereco" rows="2"><?= htmlspecialchars($editar['endereco'] ?? ($_POST['endereco'] ?? '')) ?></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Cidade</label>
                    <input type="text" name="cidade" value="<?= htmlspecialchars($editar['cidade'] ?? ($_POST['cidade'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label>Estado (UF)</label>
                    <input type="text" name="estado" value="<?= htmlspecialchars($editar['estado'] ?? ($_POST['estado'] ?? '')) ?>" maxlength="2" placeholder="XX">
                </div>
                <div class="form-group">
                    <label>CEP</label>
                    <input type="text" name="cep" value="<?= htmlspecialchars($editar['cep'] ?? ($_POST['cep'] ?? '')) ?>" placeholder="00000-000">
                </div>
            </div>

            <button type="submit"><?= $editar ? 'Atualizar Instituição' : 'Cadastrar Instituição' ?></button>
            <?php if ($editar): ?>
                <a href="instituicoes.php" style="margin-left: 10px;">Cancelar</a>
            <?php endif; ?>
        </form>

        <!-- Listagem -->
        <h3>Lista de Instituições</h3>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Status</th>
                    <th>Cidade</th>
                    <th>Estado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($instituicoes as $inst): ?>
                <tr>
                    <td><?= htmlspecialchars($inst['nome']) ?></td>
                    <td><span class="status-<?= $inst['status'] ?>"><?= ucfirst($inst['status']) ?></span></td>
                    <td><?= htmlspecialchars($inst['cidade']) ?></td>
                    <td><?= htmlspecialchars($inst['estado']) ?></td>
                    <td class="acoes">
                        <a href="?editar=<?= $inst['id'] ?>">Editar</a> |
                        <?php if ($inst['status'] === 'ativa'): ?>
                            <a href="?desativar=<?= $inst['id'] ?>" onclick="return confirm('Desativar esta instituição?')">Desativar</a>
                        <?php else: ?>
                            <a href="?ativar=<?= $inst['id'] ?>" onclick="return confirm('Ativar esta instituição?')">Ativar</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>