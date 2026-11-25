/**
 * Interface básica sem frameworks
 */

// Função para mostrar/ocultar palavra-passe em campos de login
function togglePasswordVisibility(passwordFieldId, buttonId) {
    const passwordField = document.getElementById(passwordFieldId);
    const button = document.getElementById(buttonId);
    
    if (!passwordField || !button) return;
    
    button.addEventListener('click', function() {
        const isVisible = passwordField.type === 'password';
        passwordField.type = isVisible ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(!isVisible));
        button.setAttribute('aria-label', isVisible ? 'Mostrar palavra-passe' : 'Ocultar palavra-passe');
        
        // Trocar ícone
        const icon = button.querySelector('svg');
        if (isVisible) {
            // Ícone de olho fechado
            icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="1" y1="1" x2="23" y2="23" stroke="currentColor" stroke-width="2"/>';
        } else {
            // Ícone de olho aberto
            icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>';
        }
    });
}

// Função para alternar menu lateral responsivo
function toggleSidebar(sidebarSelector, menuButtonSelector) {
    const sidebar = document.querySelector(sidebarSelector);
    const menuButton = document.querySelector(menuButtonSelector);
    
    if (!sidebar || !menuButton) return;
    
    menuButton.addEventListener('click', function() {
        const isOpen = sidebar.dataset.menu === 'aberto';
        const newState = isOpen ? 'fechado' : 'aberto';
        sidebar.dataset.menu = newState;
        
        menuButton.setAttribute('aria-label', newState === 'aberto' ? 'Fechar navegação' : 'Abrir menu');
    });
    
    // Fechar menu ao clicar fora
    document.addEventListener('click', function(e) {
        if (e.target === sidebar || e.target.closest?.(`${sidebarSelector} .menu a`)) {
            sidebar.dataset.menu = 'fechado';
            menuButton.setAttribute('aria-label', 'Abrir menu');
        }
    });
    
    // Fechar menu com Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            sidebar.dataset.menu = 'fechado';
            menuButton.setAttribute('aria-label', 'Abrir menu');
        }
    });
}

// Inicializar funcionalidades quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility para campos de login
    togglePasswordVisibility('password', 'password-toggle');
    
    // Toggle menu lateral se existir
    toggleSidebar('.barra-lateral', '.botao-menu');
});