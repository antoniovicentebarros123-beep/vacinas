<?php
/**
 * Endpoint de registo de vacinação do posto ou de origem externa.
 */

require_once __DIR__ . '/../app/core/vacinacao.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('enfermeiro');
exigir_post_csrf();
$pdo = conexao_app();
$origem = is_string($_POST['origem'] ?? null) ? $_POST['origem'] : '';
$criancaId = isset($_POST['crianca_id']) && is_scalar($_POST['crianca_id']) ? (int) $_POST['crianca_id'] : 0;
$atendimentoId = isset($_POST['atendimento_id']) && is_scalar($_POST['atendimento_id']) ? (int) $_POST['atendimento_id'] : 0;
try {
    if ($origem === 'posto') {
        $resultado = registrar_vacinacao_posto($pdo, $atendimentoId, $_POST, (int) $_SESSION['utilizador_id']);
        $atendimento = obter_atendimento($pdo, $atendimentoId);
        redirecionar_modulo('crianca_detalhe.php?id=' . (int) $atendimento['crianca_id'], 'sucesso', $resultado['concluido'] ? 'Vacinação registada e atendimento concluído.' : 'Vacinação registada. Ainda há vacinas pendentes no atendimento.');
    }
    if ($origem !== 'externa') throw new RuntimeException('Origem de vacinação inválida.');
    registrar_vacinacao_externa($pdo, $criancaId, $_POST, (int) $_SESSION['utilizador_id']);
    redirecionar_modulo('crianca_detalhe.php?id=' . $criancaId, 'sucesso', 'Dose externa registada sem alterar o stock.');
} catch (RuntimeException $e) {
    redirecionar_modulo($origem === 'externa' ? 'vacinacao_externa_form.php?crianca_id=' . $criancaId : 'vacinacao.php', 'erro', $e->getMessage());
} catch (Throwable $e) {
    error_log('Erro no registo de vacinação: ' . $e->getMessage());
    redirecionar_modulo('vacinacao.php', 'erro', 'Não foi possível registar a vacinação. Verifique o stock e tente novamente.');
}
