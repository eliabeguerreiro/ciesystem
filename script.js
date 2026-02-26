/**
 * Sistema Vivenciar - JavaScript
 * Interações, validações e efeitos
 */

// Validação de Formulário de Contato
function validateContactForm(form) {
    const nome = form.querySelector('#nome').value.trim();
    const email = form.querySelector('#email').value.trim();
    const mensagem = form.querySelector('#mensagem').value.trim();
    
    if (!nome || !email || !mensagem) {
        alert('Por favor, preencha todos os campos obrigatórios.');
        return false;
    }
    
    if (!isValidEmail(email)) {
        alert('Por favor, insira um email válido.');
        return false;
    }
    
    return true;
}

// Validação de Email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Validação de Formulário de Login
function validateLoginForm(form) {
    const email = form.querySelector('#email').value.trim();
    const senha = form.querySelector('#senha').value;
    
    if (!email || !senha) {
        alert('Por favor, preencha email e senha.');
        return false;
    }
    
    if (!isValidEmail(email)) {
        alert('Por favor, insira um email válido.');
        return false;
    }
    
    return true;
}

// Efeito de Scroll Suave para Links Internos
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar efeito de fade-in aos elementos
    const elements = document.querySelectorAll('.pain-card, .feature-card, .tech-item');
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, {
        threshold: 0.1
    });
    
    elements.forEach(function(element) {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(element);
    });
    
    // Validar formulários ao enviar
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            // Validação básica (o servidor também deve validar)
            if (form.classList.contains('contact-form')) {
                if (!validateContactForm(form)) {
                    e.preventDefault();
                }
            }
            
            if (form.classList.contains('login-form')) {
                if (!validateLoginForm(form)) {
                    e.preventDefault();
                }
            }
        });
    });
    
    // Adicionar classe ativa ao link de navegação atual
    const currentPage = getCurrentPageFromUrl();
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(function(link) {
        link.classList.remove('active');
        if (link.href.includes('page=' + currentPage)) {
            link.classList.add('active');
        }
    });
});

// Obter página atual da URL
function getCurrentPageFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('page') || 'home';
}

// Função para animar números (contador)
function animateCounter(element, target, duration = 2000) {
    let current = 0;
    const increment = target / (duration / 16);
    
    const timer = setInterval(function() {
        current += increment;
        if (current >= target) {
            element.textContent = target;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current);
        }
    }, 16);
}

// Função para copiar texto para clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('Copiado para a área de transferência!');
    }).catch(function(err) {
        console.error('Erro ao copiar:', err);
    });
}

// Função para mostrar/ocultar senha
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

// Efeito de scroll parallax (opcional)
window.addEventListener('scroll', function() {
    const scrollPosition = window.scrollY;
    const parallaxElements = document.querySelectorAll('.hero-illustration');
    
    parallaxElements.forEach(function(element) {
        element.style.transform = 'translateY(' + (scrollPosition * 0.5) + 'px)';
    });
});

// Log para debug
console.log('Sistema Vivenciar - JavaScript carregado com sucesso');
