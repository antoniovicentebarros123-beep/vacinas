<?php
/**
 * Login independente da área de gestão.
 */

require __DIR__ . '/inicio.php';

if (sessao_ativa_e_valida() && tem_perfil('administrador')) {
    redirecionar_gestao(!empty($_SESSION['deve_mudar_password']) ? 'alterar_password.php' : 'funcionarios.php');
}

$erro = (string) ($_SESSION['gestao_erro'] ?? '');
unset($_SESSION['gestao_erro']);
foreach (mensagens_modulo() as $mensagem) {
    if ($mensagem['tipo'] === 'erro') {
        $erro = (string) $mensagem['texto'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $utilizador = $_POST['utilizador'] ?? '';
    $password = $_POST['password'] ?? '';
    if (!is_string($utilizador) || !is_string($password)) {
        redirecionar_gestao('index.php', 'erro', 'Utilizador ou palavra-passe incorretos.');
    }
    $utilizador = trim($utilizador);
    if (verificar_tentativas_login($utilizador)) {
        redirecionar_gestao('index.php', 'erro', 'Conta temporariamente bloqueada. Tente novamente mais tarde.');
    }
    $resultado = login($utilizador, $password);
    if (!$resultado['sucesso']) {
        $mensagem = !empty($resultado['bloqueado'])
            ? 'Conta temporariamente bloqueada. Tente novamente mais tarde.'
            : 'Utilizador ou palavra-passe incorretos.';
        redirecionar_gestao('index.php', 'erro', $mensagem);
    }
    $pdo = conexao_app();
    $stmt = $pdo->prepare('SELECT perfil FROM utilizadores WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['utilizador_id']]);
    $conta = $stmt->fetch();
    if (!$conta || $conta['perfil'] !== 'administrador') {
        logout();
        redirecionar_gestao('index.php', 'erro', 'Esta área é exclusiva a administradores.');
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    redirecionar_gestao(!empty($resultado['deve_mudar_password']) ? 'alterar_password.php' : 'funcionarios.php');
}

$baseCabecalho = url_publica();
$tituloDocumento = 'Gestão de funcionários — Vacinação infantil';
require __DIR__ . '/../../app/views/partials/head.php';
?>
<main class="login" id="conteudo">
    <div class="login-caixa">
        <div class="login-selo" aria-hidden="true"><?php echo icone('shield-check'); ?></div>
        <section class="login-painel" aria-labelledby="titulo-login-gestao">
            <h1 id="titulo-login-gestao">Gestão restrita</h1>
            <p class="login-nome">Funcionários do posto</p>
            <p class="login-nome"><?php echo e(nome_posto()); ?></p>
            <p class="login-nome">Entre com uma conta de administrador.</p>
            <form class="login-form" method="post" action="<?php echo e(url_gestao() . 'index.php'); ?>">
                <?php echo csrf_field(); ?>
                <div class="campo">
                    <label for="utilizador" class="so-leitor">Utilizador</label>
                    <div class="entrada-hex">
                        <?php echo icone('user'); ?>
                        <input type="text" id="utilizador" name="utilizador" autocomplete="username" required autocapitalize="none" spellcheck="false" placeholder="Utilizador">
                    </div>
                </div>
                <div class="campo">
                    <label for="password" class="so-leitor">Palavra-passe</label>
                    <div class="entrada-hex">
                        <?php echo icone('lock'); ?>
                        <input type="password" id="password" name="password" autocomplete="current-password" required placeholder="Palavra-passe">
                        <button type="button" class="botao-olho" aria-label="Mostrar palavra-passe" aria-pressed="false"><?php echo icone('eye'); ?></button>
                    </div>
                </div>
                <div class="mensagem mensagem-erro" role="alert"<?php echo $erro === '' ? ' hidden' : ''; ?>><?php echo e($erro); ?></div>
                <button type="submit" class="login-botao">Entrar na gestão</button>
            </form>
            <p class="login-rodape">Sessão separada da aplicação principal.</p>
        </section>
    </div>
</main>
<script src="<?php echo e(url_publica() . 'assets/js/login.js'); ?>"></script>
<?php require __DIR__ . '/../../app/views/partials/foot.php'; ?>
