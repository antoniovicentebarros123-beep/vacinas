<?php
/**
 * Reposição de palavra-passe com revelação única.
 */

require __DIR__ . '/inicio.php';
$pdo = requer_gestao();
exigir_post_csrf();
$id = isset($_POST['id']) && is_scalar($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    redirecionar_gestao('funcionarios.php', 'erro', 'Pedido inválido.');
}
try {
    $temporaria = repor_password_funcionario($pdo, $id, (int) $_SESSION['utilizador_id']);
    $_SESSION['senha_temporaria_gestao'] = ['id' => $id, 'valor' => $temporaria];
    redirecionar_gestao('funcionarios.php', 'sucesso', 'Palavra-passe reposta. A temporária aparece abaixo uma única vez.');
} catch (RuntimeException $e) {
    redirecionar_gestao('funcionarios.php', 'erro', $e->getMessage());
} catch (Throwable $e) {
    error_log('Erro ao repor palavra-passe: ' . $e->getMessage());
    redirecionar_gestao('funcionarios.php', 'erro', 'Não foi possível repor a palavra-passe.');
}
