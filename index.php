<?php
// Página pública — sem sessão, sem login
// Sistema de Gestão de Carteirinhas Estudantis (CIE)
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Inscrição - CIE</title>
    
    <!-- Fonte Google (Roboto para modernidade e legibilidade) -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1976d2;
            --primary-dark: #1565c0;
            --secondary-color: #f5f5f5;
            --text-color: #333;
            --light-text: #666;
            --white: #ffffff;
            --shadow: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-hover: 0 10px 15px rgba(0,0,0,0.15);
            --radius: 8px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
            flex: 1;
            text-align: center;
        }

        /* Header Section */
        header {
            margin-bottom: 40px;
            animation: fadeInDown 0.8s ease-out;
        }

        h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .subtitle {
            font-size: 1.2rem;
            color: var(--light-text);
            font-weight: 300;
        }

        /* Action Buttons Area */
        .actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 50px;
            animation: fadeInUp 0.8s ease-out 0.2s backwards;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 15px 30px;
            background: var(--primary-color);
            color: var(--white);
            text-decoration: none;
            border-radius: 50px; /* Botões arredondados modernos */
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            min-width: 200px;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .btn svg {
            margin-right: 10px;
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        /* Info Card Section */
        .info-card {
            background: var(--white);
            padding: 40px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            text-align: left;
            animation: fadeInUp 0.8s ease-out 0.4s backwards;
            max-width: 800px;
            margin: 0 auto;
        }

        .info-card h3 {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.8rem;
            position: relative;
        }

        .info-card h3::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: var(--primary-color);
            margin: 10px auto 0;
            border-radius: 2px;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .step-item {
            text-align: center;
            padding: 20px;
            background: #f9fbfd;
            border-radius: var(--radius);
            border: 1px solid #eee;
            transition: transform 0.3s ease;
        }

        .step-item:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
        }

        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: var(--white);
            border-radius: 50%;
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 15px;
        }

        .step-title {
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-color);
        }

        .step-desc {
            font-size: 0.9rem;
            color: var(--light-text);
        }

        /* Footer */
        footer {
            text-align: center;
            padding: 20px;
            color: var(--light-text);
            font-size: 0.9rem;
            margin-top: auto;
        }

        /* Animations */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Mobile Adjustments */
        @media (max-width: 600px) {
            h1 { font-size: 2rem; }
            .actions { flex-direction: column; align-items: center; }
            .btn { width: 100%; }
            .info-card { padding: 20px; }
            .steps-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <h1>Carteira de Identificação Estudantil</h1>
            <p class="subtitle">Sistema oficial de emissão e gestão de CIE</p>
        </header>
        
        <div class="actions">
            <a href="inscricao.php" class="btn">
                <!-- Ícone SVG de Usuário/Plus -->
                <svg viewBox="0 0 24 24"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                Quero minha CIE
            </a>
            <a href="acompanhar.php" class="btn" style="background-color: #fff; color: var(--primary-color); border: 2px solid var(--primary-color);">
                <!-- Ícone SVG de Busca/Relógio -->
                <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                Acompanhar Inscrição
            </a>
        </div>

        <div class="info-card">
            <h3>Como funciona?</h3>
            <div class="steps-grid">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div class="step-title">Inscrição</div>
                    <div class="step-desc">Preencha seus dados pessoais e acadêmicos e anexe o comprovante de matrícula.</div>
                </div>
                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-title">Validação</div>
                    <div class="step-desc">Nossa equipe analisará seus documentos. Você será notificado sobre a aprovação.</div>
                </div>
                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-title">Pagamento</div>
                    <div class="step-desc">Com os dados aprovados, realize o pagamento da taxa de emissão de forma segura.</div>
                </div>
                <div class="step-item">
                    <div class="step-number">4</div>
                    <div class="step-title">Emissão</div>
                    <div class="step-desc">Sua CIE será gerada e enviada para retirada na sua instituição de ensino.</div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> Sistema de Gestão de Carteirinhas Estudantis. Todos os direitos reservados.</p>
    </footer>

</body>
</html>