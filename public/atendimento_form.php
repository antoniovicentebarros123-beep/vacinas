<?php
/**
 * Abertura de atendimento pela recepção.
 */

require __DIR__ . '/../app/core/modulos.php';
require_once __DIR__ . '/../app/core/atendimentos.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('recepcao');
$pdo = conexao_app();
$criancaId = isset($_GET['crianca_id']) && is_scalar($_GET['crianca_id']) ? (int) $_GET['crianca_id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crianca_id']) && is_scalar($_POST['crianca_id'])) {
    $criancaId = (int) $_POST['crianca_id'];
}
$crianca = $criancaId > 0 ? obter_crianca($pdo, $criancaId) : null;
if (!$crianca || (int) $crianca['ativo'] !== 1 || $crianca['eliminado_em'] !== null) {
    redirecionar_modulo('atendimento.php', 'erro', 'Selecione uma criança ativa para abrir atendimento.');
}
$vacinas = listar_disponibilidade_vacinas($pdo);
$selecionadas = [];
$motivo = '';
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
        $selecionadas = [];
        if (isset($_POST['vacinas']) && is_array($_POST['vacinas'])) {
            foreach ($_POST['vacinas'] as $vacinaId) {
                if (is_scalar($vacinaId)) {
                    $selecionadas[] = (int) $vacinaId;
                }
            }
        }
    $motivo = is_string($_POST['motivo'] ?? null) ? trim($_POST['motivo']) : '';
    try {
        $id = criar_atendimento($pdo, $criancaId, $selecionadas, $motivo, (int) $_SESSION['utilizador_id']);
        redirecionar_modulo('atendimento_detalhe.php?id=' . $id, 'sucesso', 'Atendimento aberto.');
    } catch (RuntimeException $e) {
        $erros[] = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Erro ao abrir atendimento: ' . $e->getMessage());
        $erros[] = 'Não foi possível abrir o atendimento. Tente novamente.';
    }
}

$tituloPagina = 'Abrir atendimento';
$menuAtual = 'atendimento.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>
<section class="modulo-cabecalho"><div><h1>Abrir atendimento</h1><p><?php echo e($crianca['nome']); ?> · <?php echo e($crianca['numero_processo']); ?></p></div></section>
<section class="cartao">
    <?php foreach ($erros as $erro): ?><div class="mensagem mensagem-erro" role="alert"><?php echo e($erro); ?></div><?php endforeach; ?>
    <form method="post" action="<?php echo e(url_publica() . 'atendimento_form.php'); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="crianca_id" value="<?php echo (int) $criancaId; ?>">
        <fieldset><legend>Vacinas pretendidas</legend><p class="ajuda-destaque">A disponibilidade é mostrada apenas como sim ou não.</p>
            <div class="grelha-cartoes">
            <?php foreach ($vacinas as $vacina): ?>
                <label class="campo-check cartao vacina-opcao"><input type="checkbox" name="vacinas[]" value="<?php echo (int) $vacina['id']; ?>"<?php echo in_array((int) $vacina['id'], $selecionadas, true) ? ' checked' : ''; ?>><span><?php echo e($vacina['nome']); ?><small class="etiqueta <?php echo (int) $vacina['disponivel'] === 1 ? 'etiqueta-sucesso' : 'etiqueta-erro'; ?>"><?php echo (int) $vacina['disponivel'] === 1 ? 'Disponível' : 'Indisponível'; ?></small></span></label>
            <?php endforeach; ?>
            </div>
        </fieldset>
        <div class="campo campo-espacado"><label for="motivo">Motivo</label><textarea id="motivo" name="motivo" maxlength="2000" placeholder="Obrigatório se nenhuma vacina selecionada estiver disponível"><?php echo e($motivo); ?></textarea><small>Se houver pelo menos uma vacina disponível, o atendimento entra em aguardando.</small></div>
        <div class="formulario-acoes"><button type="submit" class="botao botao-primario">Abrir atendimento</button><a class="botao botao-secundario" href="<?php echo e(url_publica() . 'atendimento.php'); ?>">Cancelar</a></div>
    </form>
</section>
<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
