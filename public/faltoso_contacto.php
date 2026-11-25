<?php
/**
 * Registo manual de um contacto com o encarregado.
 */

require_once __DIR__ . '/../app/core/faltosos.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('recepcao', 'enfermeiro');
exigir_post_csrf();

$pdo = conexao_app();
$criancaId = isset($_POST['crianca_id']) && is_scalar($_POST['crianca_id']) ? (int) $_POST['crianca_id'] : 0;
try {
    registar_contacto_faltoso($pdo, $criancaId, $_POST, (int) $_SESSION['utilizador_id']);
    redirecionar_modulo('faltoso_detalhe.php?id=' . $criancaId, 'sucesso', 'Contacto registado. Nenhuma mensagem automática foi enviada.');
} catch (RuntimeException $e) {
    redirecionar_modulo('faltoso_detalhe.php?id=' . $criancaId, 'erro', $e->getMessage());
} catch (Throwable $e) {
    error_log('Erro ao registar contacto de faltoso: ' . $e->getMessage());
    redirecionar_modulo('faltoso_detalhe.php?id=' . $criancaId, 'erro', 'Não foi possível registar o contacto.');
}
