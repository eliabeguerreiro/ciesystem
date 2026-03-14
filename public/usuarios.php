<?php
session_start();
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/models/usuario.php';

$auth = new AuthController();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    die("Acesso negado.");
}

$database = new Database();
$db = $database->getConnection();
$usuario = new Usuario($db);

$erro = '';
$sucesso = '';

// ================================
// TRATAMENTO DE FORMULÁRIO (CADASTRO/EDIÇÃO)
// ================================

if ($_POST) {
    $usuario->nome = $_POST['nome'];
    $usuario->email = $_POST['email'];
    $usuario->tipo = $_POST['tipo'];
    if (!empty($_POST['senha'])) {
        $usuario->senha = $_POST['senha'];
    }

    if (isset($_POST['id'])) {
        // Editar
        $usuario->id = $_POST['id'];
        if ($usuario->atualizar()) {
            // === LOG: Usuário editado ===
            require_once __DIR__ . '/../app/models/Log.php';
            $log = new Log($db);
            $log->registrar(
                $_SESSION['user_id'],
                'editou_usuario',
                "ID: {$usuario->id}, Nome: {$usuario->nome}, Email: {$usuario->email}, Tipo: {$usuario->tipo}",
                $usuario->id,
                'usuarios'
            );
            header('Location: usuarios.php?sucesso=editado');
            exit;
        } else {
            $erro = "Erro ao atualizar usuário.";
        }
    } else {
        // Criar
        if ($usuario->criar()) {
            // === LOG: Usuário criado ===
            require_once __DIR__ . '/../app/models/Log.php';
            $log = new Log($db);
            $novoId = $db->lastInsertId();
            $log->registrar(
                $_SESSION['user_id'],
                'criou_usuario',
                "Nome: {$usuario->nome}, Email: {$usuario->email}, Tipo: {$usuario->tipo}",
                $novoId,
                'usuarios'
            );
            header('Location: usuarios.php?sucesso=criado');
            exit;
        } else {
            $erro = "Erro ao criar usuário. E-mail já existe.";
        }
    }
}

// ================================
// EXCLUSÃO
// ================================

if (isset($_GET['deletar'])) {
    $usuario->id = $_GET['deletar'];
    
    // Impedir autoexclusão
    if ($usuario->id == $_SESSION['user_id']) {
        $erro = "Você não pode excluir a si mesmo.";
    } else {
        if ($usuario->deletar()) {
            // === LOG: Usuário excluído ===
            require_once __DIR__ . '/../app/models/Log.php';
            $log = new Log($db);
            $log->registrar(
                $_SESSION['user_id'],
                'excluiu_usuario',
                "ID: {$usuario->id}",
                $usuario->id,
                'usuarios'
            );
            header('Location: usuarios.php?sucesso=excluido');
            exit;
        } else {
            $erro = "Erro ao excluir usuário.";
        }
    }
}

$usuarios = $usuario->listar();
$editar = null;
if (isset($_GET['editar'])) {
    $editar = $usuario->buscarPorId($_GET['editar']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Sistema CIE</title>
    <!-- Fonte Google -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1976d2;
            --primary-dark: #1565c0;
            --success-color: #2e7d32;
            --error-color: #c62828;
            --warning-color: #f57c00;
            --bg-color: #f4f6f8;
            --card-bg: #ffffff;
            --text-color: #333;
            --light-text: #666;
            --border-color: #ddd;
            --shadow: 0 4px 6px rgba(0,0,0,0.05);
            --radius: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 20px;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-section h2 {
            color: var(--text-color);
            font-size: 1.8rem;
            font-weight: 700;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            color: var(--light-text);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            padding: 8px 16px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        .btn-back:hover {
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            animation: fadeInUp 0.5s ease-out;
        }

        .card-title {
            font-size: 1.4rem;
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            font-weight: 600;
        }

        /* Formulário */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-color);
            font-size: 0.95rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #fafafa;
            font-family: inherit;
        }

        input:focus, select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 4px rgba(25, 118, 210, 0.1);
            background-color: #fff;
        }

        .form-actions {
            margin-top: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-submit {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(25, 118, 210, 0.2);
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(25, 118, 210, 0.3);
        }

        .btn-cancel {
            color: var(--light-text);
            text-decoration: none;
            font-weight: 500;
            padding: 12px 20px;
            transition: color 0.3s;
        }

        .btn-cancel:hover {
            color: var(--text-color);
            text-decoration: underline;
        }

        /* Mensagens */
        .mensagem {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid;
            display: flex;
            align-items: center;
            animation: fadeIn 0.4s ease-in-out;
        }

        .sucesso {
            background-color: #e8f5e9;
            color: var(--success-color);
            border-left-color: var(--success-color);
        }

        .erro {
            background-color: #ffebee;
            color: var(--error-color);
            border-left-color: var(--error-color);
        }

        /* Tabela */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            color: var(--light-text);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #fcfcfc; }

        /* Badges de Tipo */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .badge-admin {
            background-color: rgba(25, 118, 210, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(25, 118, 210, 0.2);
        }

        .badge-user {
            background-color: rgba(96, 125, 139, 0.1);
            color: #607d8b;
            border: 1px solid rgba(96, 125, 139, 0.2);
        }

        /* Ações na Tabela */
        .table-actions {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-edit {
            background-color: rgba(25, 118, 210, 0.1);
            color: var(--primary-color);
        }
        .btn-edit:hover { background-color: var(--primary-color); color: white; }

        .btn-delete {
            background-color: rgba(198, 40, 40, 0.1);
            color: var(--error-color);
        }
        .btn-delete:hover { background-color: var(--error-color); color: white; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .header-section { flex-direction: column; align-items: flex-start; }
            .form-grid { grid-template-columns: 1fr; }
            .btn-back { margin-bottom: 10px; }
        }
    </style>
</head>
<body>

    <div class="main-container">
        
        <div class="header-section">
            <h2>Gerenciar Usuários</h2>
            <a href="dashboard.php" class="btn-back">← Voltar ao Dashboard</a>
        </div>

        <?php if (isset($_GET['sucesso'])): ?>
            <?php
            $msgs = [
                'criado' => 'Usuário cadastrado com sucesso!',
                'editado' => 'Usuário atualizado com sucesso!',
                'excluido' => 'Usuário excluído com sucesso!'
            ];
            $msg = $msgs[$_GET['sucesso']] ?? 'Ação realizada com sucesso.';
            ?>
            <div class="mensagem sucesso">
                <strong>✅ Sucesso:</strong> &nbsp; <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mensagem erro">
                <strong>⚠️ Erro:</strong> &nbsp; <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <!-- Card de Formulário -->
        <div class="card">
            <h3 class="card-title"><?= $editar ? 'Editar Usuário' : 'Novo Usuário' ?></h3>
            
            <form method="POST">
                <?php if ($editar): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($editar['id']) ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="nome">Nome Completo</label>
                        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($editar['nome'] ?? '') ?>" placeholder="Digite o nome completo" required>
                    </div>

                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($editar['email'] ?? '') ?>" placeholder="exemplo@email.com" required>
                    </div>

                    <div class="form-group">
                        <label for="senha">Senha</label>
                        <input type="password" id="senha" name="senha" placeholder="<?= $editar ? 'Deixe em branco para manter a atual' : 'Digite uma senha forte' ?>">
                    </div>

                    <div class="form-group">
                        <label for="tipo">Tipo de Acesso</label>
                        <select id="tipo" name="tipo" required>
                            <option value="user" <?= ($editar && $editar['tipo'] === 'user') ? 'selected' : '' ?>>Usuário Comum</option>
                            <option value="admin" <?= ($editar && $editar['tipo'] === 'admin') ? 'selected' : '' ?>>Administrador</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <?= $editar ? '💾 Atualizar Usuário' : '➕ Criar Usuário' ?>
                    </button>
                    <?php if ($editar): ?>
                        <a href="usuarios.php" class="btn-cancel">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Card de Lista -->
        <div class="card">
            <h3 class="card-title">Usuários Cadastrados</h3>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Tipo</th>
                            <th style="text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 30px; color: var(--light-text);">
                                    Nenhum usuário encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td style="font-weight: 500;"><?= htmlspecialchars($u['nome']) ?></td>
                                <td style="color: var(--light-text);"><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <span class="badge <?= $u['tipo'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                                        <?= $u['tipo'] === 'admin' ? '👑 Admin' : '👤 Usuário' ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div class="table-actions" style="justify-content: flex-end;">
                                        <a href="?editar=<?= $u['id'] ?>" class="btn-action btn-edit" title="Editar">
                                            ✏️ Editar
                                        </a>
                                        
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                            <a href="?deletar=<?= $u['id'] ?>" 
                                               class="btn-action btn-delete" 
                                               title="Excluir"
                                               onclick="return confirm('Tem certeza que deseja excluir o usuário <?= htmlspecialchars($u['nome']) ?>?\n\nEsta ação não pode ser desfeita.')">
                                                🗑️ Excluir
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #ccc; font-size: 0.8rem;" title="Você não pode excluir a si mesmo">—</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>