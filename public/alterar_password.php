<?php
/**
 * Alteração obrigatória da palavra-passe no primeiro acesso.
 */

require __DIR__ . '/../app/core/helpers.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_login();

$erro = '';
$utilizadorId = (int) $_SESSION['utilizador_id'];

try {
    $config = config();
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);

    $stmt = $pdo->prepare('SELECT id, ativo, deve_mudar_password FROM utilizadores WHERE id = ? LIMIT 1');
    $stmt->execute([$utilizadorId]);
    $utilizador = $stmt->fetch();

    if (!$utilizador || (int) $utilizador['ativo'] !== 1) {
        logout();
        header('Location: ' . url_base() . 'login.php');
        exit;
    }

    if ((int) $utilizador['deve_mudar_password'] !== 1) {
        $_SESSION['deve_mudar_password'] = false;
        header('Location: ' . url_base() . 'painel.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
            $erro = 'Sessão inválida. Tente novamente.';
        } else {
            $password = $_POST['password'] ?? '';
            $confirmacao = $_POST['password_confirmacao'] ?? '';

            if (!is_string($password) || !is_string($confirmacao)) {
                $erro = 'Indique e confirme a nova palavra-passe.';
            } elseif (strlen($password) < 8) {
                $erro = 'A nova palavra-passe deve ter pelo menos 8 caracteres.';
            } elseif (!hash_equals($password, $confirmacao)) {
                $erro = 'A confirmação não corresponde à nova palavra-passe.';
            } else {
                $novoHash = password_hash($password, PASSWORD_ARGON2ID);

                if (!is_string($novoHash) || $novoHash === '') {
                    $erro = 'Não foi possível atualizar a palavra-passe. Tente novamente.';
                } else {
                    $pdo->beginTransaction();

                    try {
                        $stmt = $pdo->prepare(
                            'UPDATE utilizadores
                             SET password_hash = ?, deve_mudar_password = FALSE,
                                 tentativas_falhadas = 0, bloqueado_ate = NULL
                             WHERE id = ? AND ativo = 1 AND deve_mudar_password = TRUE'
                        );
                        $stmt->execute([$novoHash, $utilizadorId]);

                        if ($stmt->rowCount() !== 1) {
                            throw new RuntimeException('A conta já não exige alteração da palavra-passe.');
                        }

                        $stmt = $pdo->prepare(
                            "INSERT INTO auditoria
                             (entidade, entidade_id, acao, campo, valor_anterior, valor_novo, utilizador_id)
                             VALUES ('utilizadores', ?, 'alterar_password', 'deve_mudar_password', '1', '0', ?)"
                        );
                        $stmt->execute([$utilizadorId, $utilizadorId]);

                        $pdo->commit();
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        throw $e;
                    }

                    $_SESSION['deve_mudar_password'] = false;
                    regenerar_id_sessao();
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['ultimo_acesso'] = time();

                    header('Location: ' . url_base() . 'painel.php');
                    exit;
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log('Erro ao alterar palavra-passe: ' . $e->getMessage());
    $erro = 'Não foi possível atualizar a palavra-passe. Tente novamente.';
}

require __DIR__ . '/../app/views/partials/head.php';
?>

<main class="login" id="conteudo">
    <div class="login-caixa">
        <div class="login-selo" aria-hidden="true">
            <?php echo icone('lock'); ?>
        </div>

        <section class="login-painel" aria-labelledby="titulo-alterar-password">
            <h1 id="titulo-alterar-password">Alterar palavra-passe</h1>
            <p class="login-nome"><?php echo e(nome_posto()); ?></p>
            <p class="login-nome">No primeiro acesso, escolha uma nova palavra-passe com pelo menos 8 caracteres.</p>

            <form class="login-form" method="post" action="<?php echo e(url_base() . 'alterar_password.php'); ?>">
                <?php echo csrf_field(); ?>

                <div class="campo">
                    <label for="password" class="so-leitor">Nova palavra-passe</label>
                    <div class="entrada-hex">
                        <?php echo icone('lock'); ?>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            placeholder="Nova palavra-passe"
                        >
                    </div>
                </div>

                <div class="campo">
                    <label for="password_confirmacao" class="so-leitor">Confirmar nova palavra-passe</label>
                    <div class="entrada-hex">
                        <?php echo icone('lock'); ?>
                        <input
                            type="password"
                            id="password_confirmacao"
                            name="password_confirmacao"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            placeholder="Confirmar palavra-passe"
                        >
                    </div>
                </div>

                <div class="mensagem mensagem-erro" role="alert" <?php echo $erro === '' ? 'hidden' : ''; ?>>
                    <?php echo e($erro); ?>
                </div>

                <button type="submit" class="login-botao">Guardar nova palavra-passe</button>
            </form>

            <p class="login-rodape">A palavra-passe não será apresentada novamente.</p>
        </section>
    </div>
</main>

<?php require __DIR__ . '/../app/views/partials/foot.php'; ?>
