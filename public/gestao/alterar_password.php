<?php
/**
 * Mudança obrigatória dentro da sessão independente de gestão.
 */

require __DIR__ . '/inicio.php';
$pdo = requer_gestao();
$erro = '';
$id = (int) $_SESSION['utilizador_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $password = $_POST['password'] ?? '';
    $confirmacao = $_POST['password_confirmacao'] ?? '';
    if (!is_string($password) || !is_string($confirmacao)) {
        $erro = 'Indique e confirme a nova palavra-passe.';
    } elseif (strlen($password) < 8) {
        $erro = 'A nova palavra-passe deve ter pelo menos 8 caracteres.';
    } elseif (!hash_equals($password, $confirmacao)) {
        $erro = 'A confirmação não corresponde à nova palavra-passe.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_ARGON2ID);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE utilizadores SET password_hash = ?, deve_mudar_password = FALSE, tentativas_falhadas = 0, bloqueado_ate = NULL WHERE id = ? AND ativo = TRUE AND deve_mudar_password = TRUE');
            $stmt->execute([$hash, $id]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('A conta já não exige alteração da palavra-passe.');
            }
            registrar_auditoria($pdo, 'utilizadores', $id, 'alterar_password', 'deve_mudar_password', 1, 0, $id);
            $pdo->commit();
            $_SESSION['deve_mudar_password'] = false;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            regenerar_id_sessao();
            redirecionar_gestao('funcionarios.php', 'sucesso', 'Palavra-passe alterada. A conta está pronta a usar.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erro ao alterar palavra-passe da gestão: ' . $e->getMessage());
            $erro = 'Não foi possível atualizar a palavra-passe. Tente novamente.';
        }
    }
}

$baseCabecalho = url_publica();
$tituloDocumento = 'Alterar palavra-passe — Gestão';
require __DIR__ . '/../../app/views/partials/head.php';
?>
<main class="login" id="conteudo">
    <div class="login-caixa">
        <div class="login-selo" aria-hidden="true"><?php echo icone('lock'); ?></div>
        <section class="login-painel" aria-labelledby="titulo-password-gestao">
            <h1 id="titulo-password-gestao">Alterar palavra-passe</h1>
            <p class="login-nome">Escolha uma nova palavra-passe com pelo menos 8 caracteres.</p>
            <form class="login-form" method="post" action="<?php echo e(url_gestao() . 'alterar_password.php'); ?>">
                <?php echo csrf_field(); ?>
                <div class="campo"><label for="password" class="so-leitor">Nova palavra-passe</label><div class="entrada-hex"><?php echo icone('lock'); ?><input type="password" id="password" name="password" autocomplete="new-password" minlength="8" required></div></div>
                <div class="campo"><label for="password_confirmacao" class="so-leitor">Confirmar nova palavra-passe</label><div class="entrada-hex"><?php echo icone('lock'); ?><input type="password" id="password_confirmacao" name="password_confirmacao" autocomplete="new-password" minlength="8" required></div></div>
                <div class="mensagem mensagem-erro" role="alert"<?php echo $erro === '' ? ' hidden' : ''; ?>><?php echo e($erro); ?></div>
                <button type="submit" class="login-botao">Guardar nova palavra-passe</button>
            </form>
            <p class="login-rodape">A palavra-passe não será apresentada novamente.</p>
        </section>
    </div>
</main>
<?php require __DIR__ . '/../../app/views/partials/foot.php'; ?>
