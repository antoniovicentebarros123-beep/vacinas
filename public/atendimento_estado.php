<?php
/**
 * Endpoint das transições de atendimento.
 */

require __DIR__ . '/../app/core/modulos.php';
require_once __DIR__ . '/../app/core/atendimentos.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('recepcao', 'enfermeiro');
exigir_post_csrf();
$pdo = conexao_app();
$id = isset($_POST['id']) && is_scalar($_POST['id']) ? (int) $_POST['id'] : 0;
$transicao = is_string($_POST['transicao'] ?? null) ? $_POST['transicao'] : '';
$motivo = is_string($_POST['motivo'] ?? null) ? $_POST['motivo'] : '';
try {
    transicionar_atendimento($pdo, $id, $transicao, $motivo, (int) $_SESSION['utilizador_id'], perfil_atual());
    redirecionar_modulo('atendimento_detalhe.php?id=' . $id, 'sucesso', 'Estado do atendimento atualizado.');
} catch (RuntimeException $e) {
    redirecionar_modulo('atendimento_detalhe.php?id=' . $id, 'erro', $e->getMessage());
} catch (Throwable $e) {
    error_log('Erro na transição de atendimento: ' . $e->getMessage());
    redirecionar_modulo('atendimento.php', 'erro', 'Não foi possível atualizar o atendimento.');
}
