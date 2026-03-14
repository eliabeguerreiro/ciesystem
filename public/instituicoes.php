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
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Instituições - CIE</title>
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
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-section h2 {
            font-size: 1.8rem;
            color: var(--text-color);
            font-weight: 700;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            color: var(--light-text);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }
        .btn-back:hover { color: var(--primary-color); transform: translateY(-2px); }

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
        textarea,
        select {
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #fafafa;
            font-family: inherit;
        }

        textarea { resize: vertical; min-height: 80px; }

        input:focus, textarea:focus, select:focus {
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
        .btn-cancel:hover { color: var(--text-color); text-decoration: underline; }

        /* Mensagens */
        .mensagem {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid;
            display: flex;
            align-items: center;
            animation: slideDown 0.4s ease-out;
        }
        .sucesso { background-color: #e8f5e9; color: var(--success-color); border-left-color: var(--success-color); }
        .erro { background-color: #ffebee; color: var(--error-color); border-left-color: var(--error-color); }

        /* Tabela */
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
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
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #fcfcfc; }

        /* Badges de Status */
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }
        .badge-ativa {
            background-color: #e8f5e9;
            color: var(--success-color);
            border: 1px solid #c8e6c9;
        }
        .badge-inativa {
            background-color: #ffebee;
            color: var(--error-color);
            border: 1px solid #ffcdd2;
        }

        /* Ações na Tabela */
        .table-actions {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-block;
        }

        .btn-edit {
            background-color: rgba(25, 118, 210, 0.1);
            color: var(--primary-color);
        }
        .btn-edit:hover { background-color: var(--primary-color); color: white; }

        .btn-deactivate {
            background-color: rgba(198, 40, 40, 0.1);
            color: var(--error-color);
        }
        .btn-deactivate:hover { background-color: var(--error-color); color: white; }

        .btn-activate {
            background-color: rgba(46, 125, 50, 0.1);
            color: var(--success-color);
        }
        .btn-activate:hover { background-color: var(--success-color); color: white; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .header-section { flex-direction: column; align-items: flex-start; }
            .form-grid { grid-template-columns: 1fr; }
            .btn-back { margin-bottom: 10px; }
            .form-actions { flex-direction: column; align-items: stretch; }
            .btn-submit, .btn-cancel { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

    <div class="main-container">
        
        <div class="header-section">
            <h2><?= $editar ? 'Editar Instituição' : 'Cadastrar Nova Instituição' ?></h2>
            <a href="dashboard.php" class="btn-back">← Voltar ao Dashboard</a>
        </div>

        <?php if ($erro): ?>
            <div class="mensagem erro">
                <strong>⚠️ Erro:</strong> <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="mensagem sucesso">
                <strong>✅ Sucesso:</strong> <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <!-- Card de Formulário -->
        <div class="card">
            <h3 class="card-title"><?= $editar ? '✏️ Editar Dados' : '➕ Nova Instituição' ?></h3>
            
            <form method="POST">
                <?php if ($editar): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($editar['id']) ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="nome">Nome da Instituição *</label>
                        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($editar['nome'] ?? ($_POST['nome'] ?? '')) ?>" placeholder="Ex: Universidade Federal..." required>
                    </div>

                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select id="status" name="status" required>
                            <option value="ativa" <?= ($editar && $editar['status'] === 'ativa') ? 'selected' : '' ?>>🟢 Ativa</option>
                            <option value="inativa" <?= ($editar && $editar['status'] === 'inativa') ? 'selected' : '' ?>>🔴 Inativa</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label for="endereco">Endereço</label>
                        <textarea id="endereco" name="endereco" placeholder="Rua, número, bairro..."><?= htmlspecialchars($editar['endereco'] ?? ($_POST['endereco'] ?? '')) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="cidade">Cidade</label>
                        <input type="text" id="cidade" name="cidade" value="<?= htmlspecialchars($editar['cidade'] ?? ($_POST['cidade'] ?? '')) ?>" placeholder="Ex: João Pessoa">
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado (UF)</label>
                        <input type="text" id="estado" name="estado" value="<?= htmlspecialchars($editar['estado'] ?? ($_POST['estado'] ?? '')) ?>" maxlength="2" placeholder="Ex: PB" style="text-transform: uppercase;">
                    </div>

                    <div class="form-group">
                        <label for="cep">CEP</label>
                        <input type="text" id="cep" name="cep" value="<?= htmlspecialchars($editar['cep'] ?? ($_POST['cep'] ?? '')) ?>" placeholder="00000-000">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <?= $editar ? '💾 Atualizar Instituição' : '➕ Cadastrar Instituição' ?>
                    </button>
                    <?php if ($editar): ?>
                        <a href="instituicoes.php" class="btn-cancel">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Card de Listagem -->
        <div class="card">
            <h3 class="card-title">🏫 Instituições Cadastradas</h3>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Status</th>
                            <th>Cidade</th>
                            <th>Estado</th>
                            <th style="text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($instituicoes)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 30px; color: var(--light-text);">
                                    Nenhuma instituição encontrada.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($instituicoes as $inst): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($inst['nome']) ?></td>
                                <td>
                                    <span class="badge-status badge-<?= $inst['status'] ?>">
                                        <?= $inst['status'] === 'ativa' ? '🟢 Ativa' : '🔴 Inativa' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($inst['cidade'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($inst['estado'] ?: '—') ?></td>
                                <td style="text-align: right;">
                                    <div class="table-actions" style="justify-content: flex-end;">
                                        <a href="?editar=<?= $inst['id'] ?>" class="btn-action btn-edit" title="Editar">
                                            ✏️ Editar
                                        </a>
                                        
                                        <?php if ($inst['status'] === 'ativa'): ?>
                                            <a href="?desativar=<?= $inst['id'] ?>" 
                                               class="btn-action btn-deactivate" 
                                               title="Desativar"
                                               onclick="return confirm('Tem certeza que deseja DESATIVAR a instituição \'<?= htmlspecialchars($inst['nome']) ?>\'?\n\nIsso impedirá novas inscrições vinculadas a ela.')">
                                                🚫 Desativar
                                            </a>
                                        <?php else: ?>
                                            <a href="?ativar=<?= $inst['id'] ?>" 
                                               class="btn-action btn-activate" 
                                               title="Ativar"
                                               onclick="return confirm('Tem certeza que deseja ATIVAR a instituição \'<?= htmlspecialchars($inst['nome']) ?>\'?')">
                                                ✅ Ativar
                                            </a>
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