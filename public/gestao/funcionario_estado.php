<?php
/**
 * Desativação e reativação lógica de contas.
 */

require __DIR__ . '/inicio.php';
$pdo = requer_gestao();
exigir_post_csrf();
$id = isset($_POST['id']) && is_scalar($_POST['id']) ? (int) $_POST['id'] : 0;
$ativo = isset($_POST['ativo']) && is_scalar($_POST['ativo']) ? (int) $_POST['ativo'] : -1;
if ($id <= 0 || !in_array($ativo, [0, 1], true)) {
    redirecionar_gestao('funcionarios.php', 'erro', 'Pedido inválido.');
}
try {
    alterar_estado_funcionario($pdo, $id, $ativo, (int) $_SESSION['utilizador_id']);
    redirecionar_gestao('funcionarios.php', 'sucesso', $ativo === 1 ? 'Conta reativada.' : 'Conta desativada. O registo foi preservado.');
} catch (RuntimeException $e) {
    redirecionar_gestao('funcionarios.php', 'erro', $e->getMessage());
} catch (Throwable $e) {
    error_log('Erro ao alterar estado de funcionário: ' . $e->getMessage());
    redirecionar_gestao('funcionarios.php', 'erro', 'Não foi possível alterar o estado da conta.');
}
