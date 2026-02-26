<?php
/**
 * Sistema Vivenciar - Gestão Clínica Integrada
 * Arquivo único com todas as páginas (Home, Contato, Acesso)
 */

// Configurações
define('APP_NAME', 'Sistema Vivenciar');
define('APP_DESCRIPTION', 'Gestão Clínica Integrada para Clínicas e Serviços de Saúde');
define('CONTACT_EMAIL', 'contato@sistemavivenciar.com.br');
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST']);

// Função auxiliar para sanitizar entrada
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Função auxiliar para validar email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Obter página atual
$page = isset($_GET['page']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['page']) : 'home';
$validPages = ['home', 'contato', 'acesso'];
if (!in_array($page, $validPages)) {
    $page = 'home';
}

// Processar formulários
$successMessage = '';
$errorMessage = '';

// Formulário de Contato
if ($page === 'contato' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitizeInput($_POST['nome'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $telefone = sanitizeInput($_POST['telefone'] ?? '');
    $mensagem = sanitizeInput($_POST['mensagem'] ?? '');
    
    if (empty($nome) || empty($email) || empty($mensagem)) {
        $errorMessage = 'Por favor, preencha todos os campos obrigatórios.';
    } elseif (!isValidEmail($email)) {
        $errorMessage = 'Por favor, insira um email válido.';
    } else {
        $successMessage = 'Obrigado pelo contato! Em breve retornaremos sua mensagem.';
        $nome = $email = $telefone = $mensagem = '';
    }
}

// Formulário de Login
$loginError = '';
$loginSuccess = '';
$validUsers = [
    ['email' => 'demo@sistemavivenciar.com.br', 'senha' => 'demo123'],
    ['email' => 'clinica@exemplo.com.br', 'senha' => 'senha123'],
];

if ($page === 'acesso' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    
    if (empty($email) || empty($senha)) {
        $loginError = 'Por favor, preencha email e senha.';
    } elseif (!isValidEmail($email)) {
        $loginError = 'Email inválido.';
    } else {
        $userFound = false;
        foreach ($validUsers as $user) {
            if ($user['email'] === $email && $user['senha'] === $senha) {
                $userFound = true;
                break;
            }
        }
        
        if ($userFound) {
            $loginSuccess = 'Login realizado com sucesso! Redirecionando...';
        } else {
            $loginError = 'Email ou senha incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo APP_DESCRIPTION; ?>">
    <title><?php echo APP_NAME; ?> - Gestão Clínica Integrada</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/style.css">
</head>
<body>
    <!-- NAVEGAÇÃO -->
    <header class="navbar">
        <div class="container">
            <div class="navbar-content">
                <div class="navbar-brand">
                    <a href="<?php echo BASE_URL; ?>" class="logo">
                        <span class="logo-icon">🏥</span>
                        <span class="logo-text"><?php echo APP_NAME; ?></span>
                    </a>
                </div>
                <nav class="navbar-menu">
                    <ul class="nav-list">
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>/?page=home" class="nav-link <?php echo $page === 'home' ? 'active' : ''; ?>">
                                Início
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>/?page=contato" class="nav-link <?php echo $page === 'contato' ? 'active' : ''; ?>">
                                Contato
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>/?page=acesso" class="nav-link nav-link-cta <?php echo $page === 'acesso' ? 'active' : ''; ?>">
                                Acesso de Clientes
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main class="main-content">
        <?php if ($page === 'home'): ?>
            <!-- ===== PÁGINA HOME ===== -->
            
            <!-- Seção Hero -->
            <section class="hero">
                <div class="container">
                    <div class="hero-content">
                        <h1 class="hero-title">Chega de Papel e Planilhas!</h1>
                        <p class="hero-subtitle">Transforme a Gestão da sua Clínica com o Sistema Vivenciar</p>
                        <p class="hero-description">
                            Digitalize e integre todos os seus processos clínicos e administrativos em uma única plataforma moderna, segura e conforme aos padrões do SUS.
                        </p>
                        <div class="hero-cta">
                            <a href="<?php echo BASE_URL; ?>/?page=contato" class="btn btn-primary btn-lg">Solicitar Demonstração</a>
                            <a href="<?php echo BASE_URL; ?>/?page=acesso" class="btn btn-secondary btn-lg">Acessar Sistema</a>
                        </div>
                    </div>
                    <div class="hero-image">
                        <div class="hero-illustration">📊</div>
                    </div>
                </div>
            </section>

            <!-- Seção de Dor -->
            <section class="pain-section">
                <div class="container">
                    <h2 class="section-title">Seus Dados Estão Seguros?</h2>
                    <p class="section-subtitle">Os Riscos da Gestão em Papel e Excel</p>
                    
                    <div class="pain-grid">
                        <div class="pain-card">
                            <div class="pain-icon">⚠️</div>
                            <h3 class="pain-title">Erros e Inconsistências</h3>
                            <p class="pain-text">Planilhas manuais estão propensas a erros, duplicação de dados e informações desatualizadas.</p>
                        </div>
                        <div class="pain-card">
                            <div class="pain-icon">🔓</div>
                            <h3 class="pain-title">Falta de Segurança</h3>
                            <p class="pain-text">Dados de pacientes em papel ou compartilhados em email não oferecem proteção adequada.</p>
                        </div>
                        <div class="pain-card">
                            <div class="pain-icon">⏱️</div>
                            <h3 class="pain-title">Lentidão Operacional</h3>
                            <p class="pain-text">Processos manuais consomem tempo precioso que poderia ser dedicado ao atendimento.</p>
                        </div>
                        <div class="pain-card">
                            <div class="pain-icon">📋</div>
                            <h3 class="pain-title">Falta de Conformidade</h3>
                            <p class="pain-text">Dificuldade em atender aos requisitos do SUS e gerar relatórios obrigatórios (BPA-I).</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Seção de Produto -->
            <section class="product-section">
                <div class="container">
                    <h2 class="section-title">Sistema Vivenciar</h2>
                    <p class="section-subtitle">A Gestão Clínica Integrada que o Brasil Precisa</p>
                    
                    <p class="product-description">
                        O <strong>Sistema Vivenciar</strong> é uma plataforma web desenvolvida especialmente para clínicas, centros de referência e serviços de saúde que buscam digitalizar e integrar seus processos clínicos e administrativos com foco na atenção especializada e no acompanhamento terapêutico.
                    </p>
                    
                    <div class="product-features">
                        <div class="feature-item">
                            <span class="feature-icon">✅</span>
                            <span class="feature-text">Desenvolvido em PHP puro com arquitetura modular e robusta</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon">✅</span>
                            <span class="feature-text">Interface moderna, responsiva e intuitiva</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon">✅</span>
                            <span class="feature-text">Compatível com ambientes de hospedagem comuns (VPS, cPanel, etc.)</span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon">✅</span>
                            <span class="feature-text">Banco de dados MySQL seguro e escalável</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Seção de Funcionalidades -->
            <section class="features-section">
                <div class="container">
                    <h2 class="section-title">Digitalize e Simplifique</h2>
                    <p class="section-subtitle">O que o Vivenciar Faz pela Sua Equipe</p>
                    
                    <div class="features-grid">
                        <div class="feature-card">
                            <div class="feature-card-icon">👥</div>
                            <h3 class="feature-card-title">Gestão de Pacientes</h3>
                            <p class="feature-card-text">Cadastro completo com dados sociodemográficos, CNS, endereço e situação de rua, conforme padrões do SUS.</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-card-icon">📝</div>
                            <h3 class="feature-card-title">Registro de Atendimentos</h3>
                            <p class="feature-card-text">Controle de procedimentos ambulatoriais com vinculação a profissionais, clínicas e competência (BPA-I).</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-card-icon">📊</div>
                            <h3 class="feature-card-title">Evoluções Clínicas Dinâmicas</h3>
                            <p class="feature-card-text">Formulários personalizáveis por especialidade (fonoaudiologia, psicologia, fisioterapia) com histórico auditável.</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-card-icon">🔐</div>
                            <h3 class="feature-card-title">Gestão de Usuários</h3>
                            <p class="feature-card-text">Controle de acesso com perfis administrativos e operacionais para maior segurança.</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-card-icon">🏥</div>
                            <h3 class="feature-card-title">Integração com Padrões do SUS</h3>
                            <p class="feature-card-text">Suporte a CNES, CNS, CBO, CID-10 e geração de dados compatíveis com o Boletim de Produção Ambulatorial.</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-card-icon">📈</div>
                            <h3 class="feature-card-title">Relatórios e Análises</h3>
                            <p class="feature-card-text">Geração de relatórios customizados para análise de produtividade e conformidade regulatória.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Seção de Tecnologia -->
            <section class="tech-section">
                <div class="container">
                    <h2 class="section-title">Construído para o Ambiente de Saúde Brasileiro</h2>
                    <p class="section-subtitle">Tecnologia Robusta e Segura</p>
                    
                    <div class="tech-grid">
                        <div class="tech-item">
                            <h4 class="tech-title">💻 Tecnologia Moderna</h4>
                            <p class="tech-text">PHP puro com arquitetura MVC leve, separação clara de responsabilidades e código limpo e manutenível.</p>
                        </div>
                        <div class="tech-item">
                            <h4 class="tech-title">🔒 Segurança de Dados</h4>
                            <p class="tech-text">Criptografia de dados sensíveis, validação rigorosa de entrada e conformidade com padrões de proteção de dados.</p>
                        </div>
                        <div class="tech-item">
                            <h4 class="tech-title">📱 Responsividade</h4>
                            <p class="tech-text">Interface que funciona perfeitamente em desktops, tablets e smartphones para acesso em qualquer lugar.</p>
                        </div>
                        <div class="tech-item">
                            <h4 class="tech-title">⚡ Performance</h4>
                            <p class="tech-text">Otimizado para velocidade e eficiência, garantindo resposta rápida mesmo com grande volume de dados.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA Final -->
            <section class="cta-section">
                <div class="container">
                    <div class="cta-content">
                        <h2 class="cta-title">Pronto para a Transformação Digital?</h2>
                        <p class="cta-subtitle">Deixe sua clínica mais eficiente, segura e conforme aos padrões do SUS</p>
                        <div class="cta-buttons">
                            <a href="<?php echo BASE_URL; ?>/?page=contato" class="btn btn-primary btn-lg">Solicitar Demonstração Gratuita</a>
                            <a href="<?php echo BASE_URL; ?>/?page=acesso" class="btn btn-outline btn-lg">Acessar Sistema</a>
                        </div>
                    </div>
                </div>
            </section>

        <?php elseif ($page === 'contato'): ?>
            <!-- ===== PÁGINA CONTATO ===== -->
            
            <section class="contact-hero">
                <div class="container">
                    <h1 class="page-title">Entre em Contato</h1>
                    <p class="page-subtitle">Tire suas dúvidas e solicite uma demonstração do Sistema Vivenciar</p>
                </div>
            </section>

            <section class="contact-section">
                <div class="container">
                    <div class="contact-grid">
                        <!-- Formulário -->
                        <div class="contact-form-container">
                            <h2 class="contact-form-title">Envie sua Mensagem</h2>
                            
                            <?php if (!empty($successMessage)): ?>
                                <div class="alert alert-success">
                                    <?php echo $successMessage; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($errorMessage)): ?>
                                <div class="alert alert-error">
                                    <?php echo $errorMessage; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" class="contact-form">
                                <div class="form-group">
                                    <label for="nome" class="form-label">Nome Completo *</label>
                                    <input type="text" id="nome" name="nome" class="form-input" value="<?php echo $nome ?? ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" id="email" name="email" class="form-input" value="<?php echo $email ?? ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="telefone" class="form-label">Telefone</label>
                                    <input type="tel" id="telefone" name="telefone" class="form-input" value="<?php echo $telefone ?? ''; ?>" placeholder="(11) 9999-9999">
                                </div>
                                
                                <div class="form-group">
                                    <label for="mensagem" class="form-label">Mensagem *</label>
                                    <textarea id="mensagem" name="mensagem" class="form-textarea" rows="6" required><?php echo $mensagem ?? ''; ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg">Enviar Mensagem</button>
                            </form>
                        </div>
                        
                        <!-- Informações -->
                        <div class="contact-info-container">
                            <h2 class="contact-info-title">Informações de Contato</h2>
                            
                            <div class="contact-info-item">
                                <div class="contact-info-icon">📧</div>
                                <div class="contact-info-content">
                                    <h4 class="contact-info-label">Email</h4>
                                    <p class="contact-info-text">
                                        <a href="mailto:<?php echo CONTACT_EMAIL; ?>">
                                            <?php echo CONTACT_EMAIL; ?>
                                        </a>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="contact-info-item">
                                <div class="contact-info-icon">📞</div>
                                <div class="contact-info-content">
                                    <h4 class="contact-info-label">Telefone</h4>
                                    <p class="contact-info-text">(11) 3000-0000</p>
                                </div>
                            </div>
                            
                            <div class="contact-info-item">
                                <div class="contact-info-icon">📍</div>
                                <div class="contact-info-content">
                                    <h4 class="contact-info-label">Localização</h4>
                                    <p class="contact-info-text">São Paulo, SP - Brasil</p>
                                </div>
                            </div>
                            
                            <div class="contact-info-item">
                                <div class="contact-info-icon">⏰</div>
                                <div class="contact-info-content">
                                    <h4 class="contact-info-label">Horário de Atendimento</h4>
                                    <p class="contact-info-text">
                                        Segunda a Sexta: 09:00 - 18:00<br>
                                        Sábado: 09:00 - 13:00
                                    </p>
                                </div>
                            </div>
                            
                            <div class="contact-benefits">
                                <h4 class="contact-benefits-title">Por que nos Contatar?</h4>
                                <ul class="contact-benefits-list">
                                    <li>✓ Demonstração gratuita do sistema</li>
                                    <li>✓ Consultoria sobre implementação</li>
                                    <li>✓ Suporte técnico especializado</li>
                                    <li>✓ Planos customizados para sua clínica</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        <?php elseif ($page === 'acesso'): ?>
            <!-- ===== PÁGINA ACESSO ===== -->
            
            <section class="login-section">
                <div class="login-container">
                    <div class="login-box">
                        <div class="login-header">
                            <div class="login-logo">🏥</div>
                            <h1 class="login-title">Sistema Vivenciar</h1>
                            <p class="login-subtitle">Acesso de Clientes</p>
                        </div>
                        
                        <?php if (!empty($loginSuccess)): ?>
                            <div class="alert alert-success">
                                <p><?php echo $loginSuccess; ?></p>
                                <p style="font-size: 0.9em; margin-top: 0.5rem;">Você será redirecionado em breve...</p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($loginError)): ?>
                            <div class="alert alert-error">
                                <?php echo $loginError; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" class="login-form">
                            <div class="form-group">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" class="form-input" placeholder="seu@email.com" value="<?php echo $email ?? ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="senha" class="form-label">Senha</label>
                                <input type="password" id="senha" name="senha" class="form-input" placeholder="••••••••" required>
                            </div>
                            
                            <div class="form-group form-remember">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="remember" class="checkbox-input">
                                    <span>Lembrar-me neste computador</span>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg btn-block">Entrar</button>
                        </form>
                        
                        <div class="login-footer">
                            <p class="login-footer-text">
                                <a href="#" class="link">Esqueceu sua senha?</a>
                            </p>
                        </div>
                        
                        <!-- Credenciais de Teste -->
                        <div class="demo-credentials">
                            <h4 class="demo-title">🔓 Credenciais de Demonstração</h4>
                            <div class="demo-item">
                                <p><strong>Email:</strong> demo@sistemavivenciar.com.br</p>
                                <p><strong>Senha:</strong> demo123</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informações -->
                    <div class="login-info">
                        <div class="info-card">
                            <div class="info-icon">🔐</div>
                            <h3 class="info-title">Segurança Garantida</h3>
                            <p class="info-text">Seus dados são protegidos com criptografia de ponta a ponta e conformidade com padrões de segurança internacionais.</p>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">⚡</div>
                            <h3 class="info-title">Acesso Rápido</h3>
                            <p class="info-text">Faça login e acesse todos os seus dados de pacientes, atendimentos e relatórios instantaneamente.</p>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">📱</div>
                            <h3 class="info-title">Funciona em Qualquer Lugar</h3>
                            <p class="info-text">Acesse o sistema de qualquer dispositivo com internet - desktop, tablet ou smartphone.</p>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">💬</div>
                            <h3 class="info-title">Suporte Disponível</h3>
                            <p class="info-text">Equipe de suporte técnico pronta para ajudar você 24/7 em caso de dúvidas ou problemas.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Benefícios -->
            <section class="benefits-section">
                <div class="container">
                    <h2 class="section-title">Por que Escolher o Sistema Vivenciar?</h2>
                    <p class="section-subtitle">Confira os benefícios de usar nossa plataforma</p>
                    
                    <div class="benefits-grid">
                        <div class="benefit-item">
                            <span class="benefit-number">1</span>
                            <h4 class="benefit-title">Gestão Centralizada</h4>
                            <p class="benefit-text">Todos os dados em um único lugar, fácil de acessar e gerenciar.</p>
                        </div>
                        <div class="benefit-item">
                            <span class="benefit-number">2</span>
                            <h4 class="benefit-title">Conformidade SUS</h4>
                            <p class="benefit-text">Atende aos requisitos e padrões do Sistema Único de Saúde.</p>
                        </div>
                        <div class="benefit-item">
                            <span class="benefit-number">3</span>
                            <h4 class="benefit-title">Redução de Custos</h4>
                            <p class="benefit-text">Elimine gastos com papel, impressoras e armazenamento físico.</p>
                        </div>
                        <div class="benefit-item">
                            <span class="benefit-number">4</span>
                            <h4 class="benefit-title">Produtividade</h4>
                            <p class="benefit-text">Automatize tarefas e aumente a eficiência da sua equipe.</p>
                        </div>
                    </div>
                </div>
            </section>

        <?php endif; ?>
    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3 class="footer-title">Sistema Vivenciar</h3>
                    <p class="footer-text">Gestão Clínica Integrada para clínicas e serviços de saúde do Brasil.</p>
                </div>
                <div class="footer-section">
                    <h4 class="footer-subtitle">Links Rápidos</h4>
                    <ul class="footer-links">
                        <li><a href="<?php echo BASE_URL; ?>/?page=home">Início</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/?page=contato">Contato</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/?page=acesso">Acesso de Clientes</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4 class="footer-subtitle">Contato</h4>
                    <p class="footer-text">
                        Email: <a href="mailto:<?php echo CONTACT_EMAIL; ?>"><?php echo CONTACT_EMAIL; ?></a><br>
                        Telefone: (11) 3000-0000
                    </p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="<?php echo BASE_URL; ?>/public/js/script.js"></script>
</body>
</html>
