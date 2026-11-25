<?php
require_once __DIR__ . '/../app/core/criancas.php';
ini_set('display_errors', config('display_errors', false) ? '1' : '0');
iniciar_sessao_segura();
requer_login();
requer_perfil('recepcao');
exigir_post_csrf();

$pdo = conexao_app();
$id = is_scalar($_POST['id'] ?? null) ? (int) $_POST['id'] : 0;
try {
    eliminar_crianca_logica($pdo, $id, (int) $_SESSION['utilizador_id']);
    redirecionar_modulo('criancas.php', 'sucesso', 'Criança eliminada logicamente. O histórico foi preservado.');
} catch (Throwable $e) {
    error_log('Erro ao eliminar criança: ' . $e->getMessage());
    redirecionar_modulo('crianca_detalhe.php?id=' . $id, 'erro', 'Não foi possível eliminar a criança.');
}
