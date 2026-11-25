<?php
/**
 * Formulário de vacinação num atendimento em curso.
 */

require_once __DIR__ . '/../app/core/vacinacao.php';
require_once __DIR__ . '/../app/core/criancas.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('enfermeiro');
$pdo = conexao_app();
$id = isset($_GET['atendimento_id']) && is_scalar($_GET['atendimento_id']) ? (int) $_GET['atendimento_id'] : (int) ($_POST['atendimento_id'] ?? 0);
$atendimento = $id > 0 ? obter_atendimento($pdo, $id) : false;
if (!$atendimento || $atendimento['estado'] !== 'em_atendimento') redirecionar_modulo('vacinacao.php', 'erro', 'O atendimento precisa de estar em curso.');
$vacinas = listar_vacinas_pendentes_atendimento($pdo, $id);
if (!$vacinas) redirecionar_modulo('atendimento_detalhe.php?id=' . $id, 'aviso', 'Não há vacinas pendentes neste atendimento.');
$vacinaId = isset($_POST['vacina_id']) && is_scalar($_POST['vacina_id']) ? (int) $_POST['vacina_id'] : (isset($_GET['vacina_id']) && is_scalar($_GET['vacina_id']) ? (int) $_GET['vacina_id'] : (int) $vacinas[0]['id']);
$selecionada = false;
foreach ($vacinas as $vacina) if ((int) $vacina['id'] === $vacinaId) $selecionada = true;
if (!$selecionada) $vacinaId = (int) $vacinas[0]['id'];
$doses = listar_doses_calendario($pdo, $vacinaId);
$lotes = listar_lotes_para_vacinacao($pdo, $vacinaId);
$dados = ['numero_dose' => $doses[0]['numero_dose'] ?? '', 'calendario_id' => $doses[0]['id'] ?? 0, 'lote_id' => $lotes[0]['id'] ?? 0, 'local_anatomico' => '', 'reacoes' => '', 'observacoes' => ''];
$erros = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $validacao = validar_dados_vacinacao($_POST);
    $erros = $validacao['erros'];
    $dados = array_merge($dados, $validacao['dados'], $_POST);
    $vacinaId = $validacao['dados']['vacinaId'];
    $doses = listar_doses_calendario($pdo, $vacinaId);
    $lotes = listar_lotes_para_vacinacao($pdo, $vacinaId);
    if (!$erros) {
        try {
            $resultado = registrar_vacinacao_posto($pdo, $id, $_POST, (int) $_SESSION['utilizador_id']);
            redirecionar_modulo('crianca_detalhe.php?id=' . $atendimento['crianca_id'], 'sucesso', $resultado['concluido'] ? 'Vacinação registada e atendimento concluído.' : 'Vacinação registada. O atendimento continua em curso para as restantes vacinas.');
        } catch (RuntimeException $e) {
            $erros[] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Erro ao registar vacinação: ' . $e->getMessage());
            $erros[] = 'Não foi possível registar a vacinação. Tente novamente.';
        }
    }
}
$tituloPagina = 'Registar vacinação';
$menuAtual = 'vacinacao.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho"><div><h1>Registar vacinação</h1><p><?php echo e($atendimento['nome']); ?> · Processo <?php echo e($atendimento['numero_processo']); ?></p></div><a class="botao botao-secundario" href="<?php echo e(url_publica() . 'vacinacao.php'); ?>">Voltar</a></section>
<?php if ($erros): ?><div class="mensagem mensagem-erro" role="alert"><ul><?php foreach ($erros as $erro): ?><li><?php echo e($erro); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!$lotes): ?><div class="mensagem mensagem-erro" role="alert">Sem stock válido nesta vacina. O responsável de stock deve verificar os lotes.</div><?php endif; ?>

<form method="POST" action="<?php echo e(url_publica() . 'vacinacao_registar.php'); ?>">
    <?php echo csrf_field(); ?><input type="hidden" name="origem" value="posto"><input type="hidden" name="atendimento_id" value="<?php echo e($id); ?>">
    <section class="cartao"><div class="grelha-formulario">
        <div class="campo campo-amplo"><label for="vacina_id">Vacina *</label><select id="vacina_id" name="vacina_id" data-vacina-select data-parametro="atendimento_id" data-valor="<?php echo e($id); ?>" required>
            <?php foreach ($vacinas as $vacina): ?><option value="<?php echo e($vacina['id']); ?>"<?php echo $vacinaId === (int) $vacina['id'] ? ' selected' : ''; ?>><?php echo e($vacina['nome']); ?></option><?php endforeach; ?>
        </select><span class="ajuda">Após trocar de vacina, atualize a página para carregar os seus lotes.</span></div>
        <div class="campo"><label for="calendario_id">Dose do calendário *</label><select id="calendario_id" name="calendario_id" required>
            <?php foreach ($doses as $dose): ?><option value="<?php echo e($dose['id']); ?>" data-dose="<?php echo e($dose['numero_dose']); ?>"<?php echo (int) $dados['calendario_id'] === (int) $dose['id'] ? ' selected' : ''; ?>><?php echo e($dose['rotulo_idade'] . ' — dose ' . $dose['numero_dose']); ?></option><?php endforeach; ?>
        </select><input type="hidden" name="numero_dose" value="<?php echo e($dados['numero_dose']); ?>"></div>
        <div class="campo"><label for="lote_id">Lote *</label><select id="lote_id" name="lote_id" required<?php echo !$lotes ? ' disabled' : ''; ?>>
            <?php foreach ($lotes as $indice => $lote): ?><option value="<?php echo e($lote['id']); ?>"<?php echo (int) $dados['lote_id'] === (int) $lote['id'] ? ' selected' : ''; ?>><?php echo e($lote['numero_lote'] . ' — validade ' . data_pt_simples($lote['validade']) . ' — ' . $lote['saldo_atual'] . ' doses' . ($indice === 0 ? ' (sugerido)' : '')); ?></option><?php endforeach; ?>
        </select></div>
        <div class="campo"><label for="local_anatomico">Local anatómico</label><input type="text" id="local_anatomico" name="local_anatomico" value="<?php echo e($dados['local_anatomico']); ?>" maxlength="100"></div>
        <div class="campo campo-amplo"><label for="reacoes">Reações</label><textarea id="reacoes" name="reacoes" maxlength="2000"><?php echo e($dados['reacoes']); ?></textarea></div>
        <div class="campo campo-amplo"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" maxlength="2000"><?php echo e($dados['observacoes']); ?></textarea></div>
    </div><div class="formulario-acoes"><button class="botao" type="submit"<?php echo !$lotes ? ' disabled' : ''; ?>>Registar vacinação</button><a class="botao botao-secundario" href="<?php echo e(url_publica() . 'vacinacao.php'); ?>">Cancelar</a></div></section>
</form>
<script src="<?php echo e(url_base() . 'assets/js/vacinacao.js'); ?>"></script>
<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
