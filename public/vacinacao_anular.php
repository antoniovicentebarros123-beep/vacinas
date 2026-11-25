<?php
/**
 * Endpoint de anulação. O stock nunca é devolvido automaticamente.
 */

require_once __DIR__ . '/../app/core/vacinacao.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('enfermeiro', 'administrador');
exigir_post_csrf();
$pdo = conexao_app();
$id = isset($_POST['id']) && is_scalar($_POST['id']) ? (int) $_POST['id'] : 0;
$motivo = is_string($_POST['motivo'] ?? null) ? $_POST['motivo'] : '';
try {
    anular_vacinacao($pdo, $id, $motivo, (int) $_SESSION['utilizador_id']);
    $vacinacao = obter_vacinacao($pdo, $id);
    redirecionar_modulo('crianca_detalhe.php?id=' . (int) $vacinacao['crianca_id'], 'aviso', 'Vacinação anulada. O stock não foi devolvido; se a dose não foi gasta, o responsável deve registar um ajuste positivo com motivo.');
} catch (RuntimeException $e) {
    redirecionar_modulo('vacinacao.php', 'erro', $e->getMessage());
} catch (Throwable $e) {
    error_log('Erro ao anular vacinação: ' . $e->getMessage());
    redirecionar_modulo('vacinacao.php', 'erro', 'Não foi possível anular a vacinação.');
}
