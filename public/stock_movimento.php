<?php
/**
 * Registo manual de entradas, perdas e ajustes de stock.
 */

require_once __DIR__ . '/../app/core/stock.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('responsavel_stock');
exigir_post_csrf();

$pdo = conexao_app();
$loteId = isset($_POST['lote_id']) && is_scalar($_POST['lote_id']) ? (int) $_POST['lote_id'] : 0;
$tipo = is_string($_POST['tipo'] ?? null) ? $_POST['tipo'] : '';
$quantidade = $_POST['quantidade'] ?? '';
$motivo = is_string($_POST['motivo'] ?? null) ? $_POST['motivo'] : '';
try {
    registrar_movimento_stock($pdo, $loteId, $tipo, $quantidade, $motivo, (int) $_SESSION['utilizador_id']);
    redirecionar_modulo('stock_lote_detalhe.php?id=' . $loteId, 'sucesso', 'Movimento registado. O saldo foi recalculado.');
} catch (RuntimeException $e) {
    redirecionar_modulo('stock_movimento_form.php?lote_id=' . $loteId . '&tipo=' . rawurlencode($tipo), 'erro', $e->getMessage());
} catch (Throwable $e) {
    error_log('Erro ao registar movimento de stock: ' . $e->getMessage());
    redirecionar_modulo('stock.php', 'erro', 'Não foi possível registar o movimento.');
}
