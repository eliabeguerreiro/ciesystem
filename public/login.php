<?php
session_start();

require_once  '../app/controllers/AuthController.php';
require_once  '../app/models/Log.php';

$auth = new AuthController();
$erro = '';

if ($_POST) {
    if ($auth->login($_POST['email'], $_POST['senha'])) {
        // === LOG: Login realizado ===
        $database = new Database();
        $db = $database->getConnection();
        $log = new Log($db);
        $log->registrar(
            $_SESSION['user_id'],
            'login_realizado',
            "Usuário '{$_SESSION['user_nome']}' realizou login.",
            null,
            'sessoes'
        );
        // === FIM LOG ===
        header('Location: dashboard.php');
        exit;
    } else {
        $erro = "E-mail ou senha inválidos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema CIE</title>
    <!-- Fonte Google -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1976d2;
            --primary-dark: #1565c0;
            --error-color: #c62828;
            --bg-gradient-start: #f5f7fa;
            --bg-gradient-end: #c3cfe2;
            --text-color: #333;
            --light-text: #666;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--bg-gradient-start) 0%, var(--bg-gradient-end) 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
        }

        .login-container {
            background: #ffffff;
            width: 100%;
            max-width: 420px;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            margin: 20px;
            animation: fadeInUp 0.6s ease-out;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-icon {
            width: 60px;
            height: 60px;
            background: rgba(25, 118, 210, 0.1);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .login-icon svg {
            width: 32px;
            height: 32px;
            fill: currentColor;
        }

        .login-header h2 {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .login-header p {
            color: var(--light-text);
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #fafafa;
            font-family: inherit;
        }

        input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 4px rgba(25, 118, 210, 0.15);
            background-color: #fff;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px;
            box-shadow: 0 4px 6px rgba(25, 118, 210, 0.2);
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(25, 118, 210, 0.3);
        }

        .error-message {
            background-color: #ffebee;
            color: var(--error-color);
            padding: 12px 15px;
            border-radius: 6px;
            border-left: 4px solid var(--error-color);
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            animation: shake 0.4s ease-in-out;
        }

        .error-message svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .footer-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.85rem;
            color: var(--light-text);
        }

        .footer-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .footer-link a:hover {
            text-decoration: underline;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Responsividade */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 15px;
            }
            .login-header h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">
                <!-- Ícone de Cadeado (SVG Inline) -->
                <svg viewBox="0 0 24 24">
                    <path d="M12 17c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm6-9h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM8.9 6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2H8.9V6z"/>
                </svg>
            </div>
            <h2>Acesso ao Sistema</h2>
            <p>Gestão de Carteirinhas Estudantis (CIE)</p>
        </div>

        <?php if ($erro): ?>
            <div class="error-message">
                <!-- Ícone de Alerta (SVG Inline) -->
                <svg viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="seu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-submit">Entrar</button>
        </form>

        <div class="footer-link">
            <p>&copy; <?= date('Y') ?> Sistema CIE. Todos os direitos reservados.</p>
            <p style="margin-top: 5px;"><a href="../index.php">← Voltar ao início</a></p>
        </div>
    </div>

</body>
</html>