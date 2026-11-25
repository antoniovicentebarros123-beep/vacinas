<?php
/**
 * Eliminação lógica de um contacto de faltoso.
 */

require_once __DIR__ . '/../app/core/faltosos.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('recepcao', 'enfermeiro');
exigir_post_csrf();

$pdo = conexao_app();
$id = isset($_POST['id']) && is_scalar($_POST['id']) ? (int) $_POST['id'] : 0;
$contacto = obter_contacto_faltoso($pdo, $id);
if (!$contacto) {
    redirecionar_modulo('faltosos.php', 'erro', 'Contacto não encontrado ou já eliminado.');
}
try {
    eliminar_contacto_faltoso_logica($pdo, $id, (int) $_SESSION['utilizador_id']);
    redirecionar_modulo('faltoso_detalhe.php?id=' . (int) $contacto['crianca_id'], 'sucesso', 'Contacto eliminado logicamente. O registo foi preservado.');
} catch (RuntimeException $e) {
    redirecionar_modulo('faltoso_detalhe.php?id=' . (int) $contacto['crianca_id'], 'erro', $e->getMessage());
} catch (Throwable $e) {
    error_log('Erro ao eliminar contacto de faltoso: ' . $e->getMessage());
    redirecionar_modulo('faltoso_detalhe.php?id=' . (int) $contacto['crianca_id'], 'erro', 'Não foi possível eliminar o contacto.');
}
