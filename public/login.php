<?php
/**
 * Página de login
 */

// Incluir helpers
require __DIR__ . '/../app/core/helpers.php';

// Limpar mensagens anteriores
unset($_SESSION['login_error']);

// Verificar modo pré-visualização
$modoPrevia = is_preview_mode();
$mensagemDesenvolvimento = '';

if (!$modoPrevia) {
    $mensagemDesenvolvimento = '<p class="mensagem mensagem-aviso">Sistema ainda em desenvolvimento</p>';
}
?>
<?php $pagina_css = 'login'; require __DIR__ . '/../app/views/partials/head.php'; ?>

<main class="login" id="conteudo">
    <div class="login-caixa">
        <div class="login-selo" aria-hidden="true">
            <!-- Ícone de usuário SVG -->
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
            </svg>
        </div>
        
        <section class="login-painel" aria-labelledby="titulo-login">
            <h1 id="titulo-login">Entrar</h1>
            <p class="login-nome"><?php echo e(config('nome_posto')); ?></p>
            
            <?php echo $mensagemDesenvolvimento; ?>
            
            <form class="login-form" novalidate method="post" action="<?php echo e(url_base() . 'login.php'); ?>">
                <?php // Token CSRF (REMOVER NA ETAPA 4: login real) ?>
                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                
                <div class="campo">
                    <label for="utilizador" class="so-leitor">Utilizador</label>
                    <div class="entrada-hex">
                        <!-- Ícone de usuário SVG -->
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <input 
                            type="text" 
                            id="utilizador" 
                            name="utilizador" 
                            autocomplete="username" 
                            required 
                            autocapitalize="none" 
                            spellcheck="false" 
                            placeholder="Utilizador"
                        >
                    </div>
                </div>
                
                <div class="campo">
                    <label for="password" class="so-leitor">Palavra-passe</label>
                    <div class="entrada-hex">
                        <!-- Ícone de cadeado SVG -->
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            autocomplete="current-password" 
                            required 
                            placeholder="Palavra-passe"
                        >
                        <button type="button" class="botao-olho" aria-label="Mostrar palavra-passe" aria-pressed="false">
                            <!-- Ícone de olho SVG -->
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2"/>
                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="mensagem mensagem-erro" role="alert" hidden>
                    <?php if (isset($_SESSION['login_error'])): ?>
                        <?php echo e($_SESSION['login_error']); ?>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="login-botao">Entrar</button>
                
                <div class="login-extras">
                    <label for="manter">
                        <input type="checkbox" id="manter" name="manter">
                        Manter sessão
                    </label>
                    <button type="button" class="link-botao" aria-expanded="false">
                        Esqueci a palavra-passe
                    </button>
                </div>
            </form>
            
            <p class="login-rodape">Controlo de vacinação infantil</p>
        </section>
    </div>
</main>

<?php require __DIR__ . '/../app/views/partials/foot.php'; ?>

<script>
// REMOVER NA ETAPA 4: login real - lógica de pré-visualização
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.login-form');
    const olhoBtn = document.querySelector('.botao-olho');
    const passwordInput = document.getElementById('password');
    const esqueciBtn = document.querySelector('.link-botao');
    const ajudaEsqueci = document.createElement('div');
    
    // Função para mostrar/ocultar palavra-passe
    olhoBtn.addEventListener('click', function() {
        const visivel = passwordInput.type === 'password';
        passwordInput.type = visivel ? 'text' : 'password';
        olhoBtn.setAttribute('aria-pressed', String(!visivel));
        olhoBtn.setAttribute('aria-label', visivel ? 'Ocultar palavra-passe' : 'Mostrar palavra-passe');
        olhoBtn.innerHTML = visivel ? 
            '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="1" y1="1" x2="23" y2="23" stroke="currentColor" stroke-width="2"/></svg>' :
            '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>';
    });
    
    // Função para alternar ajuda "Esqueci a palavra-passe"
    esqueciBtn.addEventListener('click', function() {
        ajudaEsqueci.hidden = !ajudaEsqueci.hidden;
        esqueciBtn.setAttribute('aria-expanded', String(!ajudaEsqueci.hidden));
    });
    
    // Adicionar ajuda esqueci a palavra-passe
    ajudaEsqueci.className = 'mensagem mensagem-aviso';
    ajudaEsqueci.hidden = true;
    ajudaEsqueci.textContent = 'Peça ao administrador do seu posto para repor a palavra-passe. No próximo início de sessão terá de escolher uma nova.';
    form.appendChild(ajudaEsqueci);
    
    // Lógica de pré-visualização - não validar formulário
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const utilizador = document.getElementById('utilizador').value.trim();
        const password = document.getElementById('password').value;
        
        if (!utilizador || !password) {
            const erro = document.querySelector('.mensagem-erro');
            erro.textContent = 'Indique o utilizador e a palavra-passe.';
            erro.hidden = false;
            (utilizador ? passwordInput : document.getElementById('utilizador')).focus();
            return;
        }
        
        // Pré-visualização: redirecionar para painel
        const botao = document.querySelector('.login-botao');
        botao.disabled = true;
        botao.textContent = 'A entrar…';
        
        setTimeout(function() {
            // Criar sessão fictícia para pré-visualização
            fetch('<?php echo e(url_base()); ?>painel.php', {
                method: 'POST',
                body: JSON.stringify({
                    utilizador: utilizador,
                    perfil: 'administrador',
                    nome: 'Utilizador de pré-visualização'
                }),
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then(() => {
                window.location.href = '<?php echo e(url_base()); ?>painel.php';
            });
        }, 1000);
    });
});
</script>