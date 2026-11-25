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
    eliminar_encarregado_logica($pdo, $id, (int) $_SESSION['utilizador_id']);
    redirecionar_modulo('encarregados.php', 'sucesso', 'Encarregado eliminado logicamente.');
} catch (Throwable $e) {
    error_log('Erro ao eliminar encarregado: ' . $e->getMessage());
    redirecionar_modulo('encarregado_form.php?id=' . $id, 'erro', 'Não foi possível eliminar o encarregado. Verifique se existem crianças ativas.');
}
