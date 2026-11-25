<?php
/**
 * Página de login com autenticação real
 */

// Iniciar sessão ANTES de qualquer coisa
require __DIR__ . '/../app/core/helpers.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();

// Processar formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        $_SESSION['login_error'] = 'Sessão inválida. Tente novamente.';
        header('Location: ' . url_base() . 'login.php');
        exit;
    }

    $utilizador = $_POST['utilizador'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!is_string($utilizador) || !is_string($password)) {
        $_SESSION['login_error'] = 'Utilizador ou palavra-passe incorretos.';
        header('Location: ' . url_base() . 'login.php');
        exit;
    }

    $utilizador = trim($utilizador);

    // Verificar tentativas falhas antes do login
    if (verificar_tentativas_login($utilizador)) {
        $_SESSION['login_error'] = 'Conta temporariamente bloqueada. Tente novamente mais tarde.';
        header('Location: ' . url_base() . 'login.php');
        exit;
    }

    // Tentar login
    $manterSessao = isset($_POST['manter'])
        && is_string($_POST['manter'])
        && $_POST['manter'] === 'on';
    $resultado = login($utilizador, $password, $manterSessao);

    if ($resultado['sucesso']) {
        // Redirecionar para painel ou página original
        $redirect = $_GET['redirect'] ?? url_base() . 'painel.php';
        $redirect_valido = is_string($redirect)
            && strpos($redirect, '/') === 0
            && strpos($redirect, '//') !== 0
            && stripos($redirect, 'http:') === false
            && stripos($redirect, 'https:') === false
            && strpos($redirect, '\\') === false;

        if (!$redirect_valido) {
            $redirect = url_base() . 'painel.php';
        }

        if (!empty($resultado['deve_mudar_password'])) {
            $redirect = url_base() . 'alterar_password.php';
        }

        header('Location: ' . $redirect);
        exit;
    } else {
        // Mensagens genéricas de erro
        if (!empty($resultado['bloqueado'])) {
            $_SESSION['login_error'] = 'Conta temporariamente bloqueada. Tente novamente mais tarde.';
        } elseif (!empty($resultado['senha_incorreta'])) {
            $_SESSION['login_error'] = 'Utilizador ou palavra-passe incorretos.';
        } else {
            $_SESSION['login_error'] = 'Utilizador ou palavra-passe incorretos.';
        }

        header('Location: ' . url_base() . 'login.php');
        exit;
    }
}

// Guardar a mensagem antes de a remover da sessão
$erro = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

$login_action = url_base() . 'login.php';
if (isset($_GET['redirect']) && is_string($_GET['redirect'])) {
    $login_action .= '?redirect=' . urlencode($_GET['redirect']);
}
?>
<?php require __DIR__ . '/../app/views/partials/head.php'; ?>

<main class="login" id="conteudo">
    <div class="login-caixa">
        <div class="login-selo" aria-hidden="true">
            <?php echo icone('user'); ?>
        </div>
        
        <section class="login-painel" aria-labelledby="titulo-login">
            <h1 id="titulo-login">Entrar</h1>
            <p class="login-nome"><?php echo e(nome_posto()); ?></p>
            
            <form class="login-form" novalidate method="post" action="<?php echo e($login_action); ?>">
                <?php // Token CSRF para segurança ?>
                <?php echo csrf_field(); ?>
                
                <div class="campo">
                    <label for="utilizador" class="so-leitor">Utilizador</label>
                    <div class="entrada-hex">
                        <?php echo icone('user'); ?>
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
                        <?php echo icone('lock'); ?>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            autocomplete="current-password" 
                            required 
                            placeholder="Palavra-passe"
                        >
                        <button type="button" class="botao-olho" aria-label="Mostrar palavra-passe" aria-pressed="false">
                            <?php echo icone('eye'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="mensagem mensagem-erro" role="alert" <?php echo $erro === '' ? 'hidden' : ''; ?>>
                    <?php echo e($erro); ?>
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

<script src="<?php echo e(url_base() . 'assets/js/login.js'); ?>"></script>

<?php require __DIR__ . '/../app/views/partials/foot.php'; ?>