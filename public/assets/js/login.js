document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.login-form');
    if (!form) return;

    const olhoBtn = document.querySelector('.botao-olho');
    const passwordInput = document.getElementById('password');
    const esqueciBtn = document.querySelector('.link-botao');
    
    // Função para mostrar/ocultar palavra-passe
    if (olhoBtn && passwordInput) {
        olhoBtn.addEventListener('click', function() {
            const visivel = passwordInput.type === 'password';
            passwordInput.type = visivel ? 'text' : 'password';
            olhoBtn.setAttribute('aria-pressed', String(visivel));
            olhoBtn.setAttribute('aria-label', visivel ? 'Ocultar palavra-passe' : 'Mostrar palavra-passe');
            olhoBtn.innerHTML = visivel ? 
                '<span class="icone icone-eye-off" aria-hidden="true"></span>' :
                '<span class="icone icone-eye" aria-hidden="true"></span>';
        });
    }

    // A ajuda só existe no login principal, não no login restrito da gestão.
    if (esqueciBtn) {
        const ajudaEsqueci = document.createElement('div');
        esqueciBtn.addEventListener('click', function() {
            ajudaEsqueci.hidden = !ajudaEsqueci.hidden;
            esqueciBtn.setAttribute('aria-expanded', String(!ajudaEsqueci.hidden));
        });
        ajudaEsqueci.className = 'mensagem mensagem-aviso';
        ajudaEsqueci.hidden = true;
        ajudaEsqueci.textContent = 'Peça ao administrador do seu posto para repor a palavra-passe. No próximo início de sessão terá de escolher uma nova.';
        form.appendChild(ajudaEsqueci);
    }
    
    // Validação do formulário com mensagens genéricas
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const utilizadorInput = document.getElementById('utilizador');
        if (!utilizadorInput || !passwordInput) return;

        const utilizador = utilizadorInput.value.trim();
        const password = passwordInput.value;
        
        if (!utilizador || !password) {
            const erro = document.querySelector('.mensagem-erro');
            if (erro) {
                erro.textContent = 'Indique o utilizador e a palavra-passe.';
                erro.hidden = false;
            }
            (utilizador ? passwordInput : utilizadorInput).focus();
            return;
        }
        
        // Submeter formulário real
        const botao = document.querySelector('.login-botao');
        botao.disabled = true;
        botao.textContent = 'A entrar…';
        
        form.submit();
    });
});