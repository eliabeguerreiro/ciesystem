<?php
session_start();
require_once __DIR__ . '/../app/controllers/AuthController.php';

$auth = new AuthController();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Dados do usuário logado
$nomeUsuario = htmlspecialchars($_SESSION['user_nome']);
$tipoUsuario = htmlspecialchars($_SESSION['user_tipo']);
$isAdmin = ($tipoUsuario === 'admin');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema CIE</title>
    <!-- Fonte Google -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1976d2;
            --primary-dark: #1565c0;
            --secondary-color: #f5f7fa;
            --text-color: #333;
            --light-text: #666;
            --white: #ffffff;
            --shadow: 0 4px 6px rgba(0,0,0,0.05);
            --shadow-hover: 0 10px 15px rgba(0,0,0,0.1);
            --radius: 12px;
            
            /* Cores dos Módulos */
            --color-estudantes: #4caf50;
            --color-inscricoes: #ff9800;
            --color-instituicoes: #9c27b0;
            --color-logistica: #03a9f4;
            --color-usuarios: #607d8b;
            --color-sair: #f44336;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--secondary-color);
            color: var(--text-color);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Topbar */
        .topbar {
            background: var(--white);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .user-details {
            text-align: right;
        }

        .user-name { font-weight: 600; font-size: 0.95rem; }
        .user-role { font-size: 0.8rem; color: var(--light-text); text-transform: uppercase; letter-spacing: 0.5px; }

        .btn-logout {
            background: transparent;
            color: var(--color-sair);
            border: 1px solid var(--color-sair);
            padding: 6px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
            margin-left: 15px;
        }

        .btn-logout:hover {
            background: var(--color-sair);
            color: white;
        }

        /* Conteúdo Principal */
        .main-content {
            flex: 1;
            padding: 40px 30px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .welcome-section {
            margin-bottom: 40px;
        }

        .welcome-section h1 {
            font-size: 2rem;
            color: var(--text-color);
            margin-bottom: 5px;
        }

        .welcome-section p {
            color: var(--light-text);
            font-size: 1.1rem;
        }

        /* Grid de Cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 30px;
            text-decoration: none;
            color: var(--text-color);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            position: relative;
            overflow: hidden;
            border-top: 4px solid transparent;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
        }

        /* Cores específicas por card */
        .card.estudantes { border-top-color: var(--color-estudantes); }
        .card.estudantes .icon { background: rgba(76, 175, 80, 0.1); color: var(--color-estudantes); }
        
        .card.inscricoes { border-top-color: var(--color-inscricoes); }
        .card.inscricoes .icon { background: rgba(255, 152, 0, 0.1); color: var(--color-inscricoes); }

        .card.instituicoes { border-top-color: var(--color-instituicoes); }
        .card.instituicoes .icon { background: rgba(156, 39, 176, 0.1); color: var(--color-instituicoes); }

        .card.logistica { border-top-color: var(--color-logistica); }
        .card.logistica .icon { background: rgba(3, 169, 244, 0.1); color: var(--color-logistica); }

        .card.usuarios { border-top-color: var(--color-usuarios); }
        .card.usuarios .icon { background: rgba(96, 125, 139, 0.1); color: var(--color-usuarios); }

        .icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 1.8rem;
            transition: transform 0.3s;
        }

        .card:hover .icon {
            transform: scale(1.1);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-color);
        }

        .card-desc {
            font-size: 0.9rem;
            color: var(--light-text);
            line-height: 1.5;
        }

        .card-arrow {
            margin-top: auto;
            align-self: flex-end;
            font-size: 1.5rem;
            opacity: 0;
            transform: translateX(-10px);
            transition: all 0.3s;
            color: var(--primary-color);
        }

        .card:hover .card-arrow {
            opacity: 1;
            transform: translateX(0);
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .topbar { padding: 15px 20px; }
            .user-details { display: none; } /* Esconde nome no mobile, mostra só avatar */
            .main-content { padding: 20px; }
            .dashboard-grid { grid-template-columns: 1fr; }
            .welcome-section h1 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>

    <!-- Barra Superior -->
    <div class="topbar">
        <div class="brand">
            <span>🎓</span> Sistema CIE
        </div>
        <div class="user-info">
            <div class="user-details">
                <div class="user-name"><?= $nomeUsuario ?></div>
                <div class="user-role"><?= $isAdmin ? 'Administrador' : 'Usuário' ?></div>
            </div>
            <div class="user-avatar">
                <?= strtoupper(substr($nomeUsuario, 0, 1)) ?>
            </div>
            <a href="logout.php" class="btn-logout">Sair</a>
        </div>
    </div>

    <!-- Conteúdo Principal -->
    <div class="main-content">
        <div class="welcome-section">
            <h1>Olá, <?= $nomeUsuario ?>! 👋</h1>
            <p>Bem-vindo ao painel de gestão da Carteira de Identificação Estudantil.</p>
        </div>

        <div class="dashboard-grid">
            <!-- Card: Gerenciar Inscrições -->
            <a href="gerenciar_inscricoes.php" class="card inscricoes">
                <div class="icon">📝</div>
                <div class="card-title">Inscrições</div>
                <div class="card-desc">Valide documentos, aprove dados e gerencie o fluxo de novas solicitações.</div>
                <div class="card-arrow">→</div>
            </a>

            <!-- Card: Gerenciar Estudantes -->
            <a href="estudantes.php" class="card estudantes">
                <div class="icon">👨‍🎓</div>
                <div class="card-title">Estudantes</div>
                <div class="card-desc">Cadastre, edite e visualize os dados acadêmicos e pessoais dos alunos.</div>
                <div class="card-arrow">→</div>
            </a>

            <!-- Card: Logística de Entregas -->
            <a href="logistica_entregas.php" class="card logistica">
                <div class="icon">🚚</div>
                <div class="card-title">Logística</div>
                <div class="card-desc">Controle a saída das carteirinhas e confirme a entrega nas instituições.</div>
                <div class="card-arrow">→</div>
            </a>

            <!-- Card: Gerenciar Instituições -->
            <a href="instituicoes.php" class="card instituicoes">
                <div class="icon">🏫</div>
                <div class="card-title">Instituições</div>
                <div class="card-desc">Gerencie as escolas e faculdades conveniadas ao sistema.</div>
                <div class="card-arrow">→</div>
            </a>

            <?php if ($isAdmin): ?>
            <!-- Card: Gerenciar Usuários (Apenas Admin) -->
            <a href="usuarios.php" class="card usuarios">
                <div class="icon">👥</div>
                <div class="card-title">Usuários</div>
                <div class="card-desc">Crie contas de acesso, defina perfis e gerencie a equipe do sistema.</div>
                <div class="card-arrow">→</div>
            </a>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>