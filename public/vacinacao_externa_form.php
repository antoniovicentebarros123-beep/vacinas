<?php
/**
 * Formulário de dose externa, sem atendimento, lote ou movimento de stock.
 */

require_once __DIR__ . '/../app/core/vacinacao.php';
require_once __DIR__ . '/../app/core/criancas.php';
require_once __DIR__ . '/../app/core/stock.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('enfermeiro');
$pdo = conexao_app();
$criancaId = isset($_GET['crianca_id']) && is_scalar($_GET['crianca_id']) ? (int) $_GET['crianca_id'] : (int) ($_POST['crianca_id'] ?? 0);
$crianca = $criancaId > 0 ? obter_crianca($pdo, $criancaId) : false;
if (!$crianca || (int) $crianca['ativo'] !== 1 || $crianca['eliminado_em'] !== null) redirecionar_modulo('vacinacao.php', 'erro', 'Criança não encontrada ou inativa.');
$vacinas = listar_vacinas_stock($pdo);
$vacinaId = isset($_POST['vacina_id']) && is_scalar($_POST['vacina_id']) ? (int) $_POST['vacina_id'] : (isset($_GET['vacina_id']) && is_scalar($_GET['vacina_id']) ? (int) $_GET['vacina_id'] : (int) $vacinas[0]['id']);
$doses = listar_doses_calendario($pdo, $vacinaId);
$dados = ['numero_dose' => $doses[0]['numero_dose'] ?? '', 'calendario_id' => $doses[0]['id'] ?? 0, 'data_administracao' => date('Y-m-d'), 'administrado_por_nome' => '', 'local_anatomico' => '', 'reacoes' => '', 'observacoes' => ''];
$erros = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $validacao = validar_dados_vacinacao($_POST, true);
    $erros = $validacao['erros'];
    $dados = array_merge($dados, $validacao['dados'], $_POST);
    $vacinaId = $validacao['dados']['vacinaId'];
    $doses = listar_doses_calendario($pdo, $vacinaId);
    if (!$erros) {
        try {
            registrar_vacinacao_externa($pdo, $criancaId, $_POST, (int) $_SESSION['utilizador_id']);
            redirecionar_modulo('crianca_detalhe.php?id=' . $criancaId, 'sucesso', 'Dose externa registada. O stock não foi alterado.');
        } catch (RuntimeException $e) {
            $erros[] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Erro ao registar dose externa: ' . $e->getMessage());
            $erros[] = 'Não foi possível registar a dose externa.';
        }
    }
}
$tituloPagina = 'Dose externa';
$menuAtual = 'vacinacao.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho"><div><h1>Dose transcrita do cartão</h1><p><?php echo e($crianca['nome']); ?> · Processo <?php echo e($crianca['numero_processo']); ?></p></div><a class="botao botao-secundario" href="<?php echo e(url_publica() . 'vacinacao.php'); ?>">Voltar</a></section>
<div class="aviso-clinico" role="note">Esta dose é de origem externa: não tem atendimento nem lote e não movimenta stock.</div>
<?php if ($erros): ?><div class="mensagem mensagem-erro" role="alert"><ul><?php foreach ($erros as $erro): ?><li><?php echo e($erro); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST" action="<?php echo e(url_publica() . 'vacinacao_registar.php'); ?>">
    <?php echo csrf_field(); ?><input type="hidden" name="origem" value="externa"><input type="hidden" name="crianca_id" value="<?php echo e($criancaId); ?>">
    <section class="cartao"><div class="grelha-formulario">
        <div class="campo campo-amplo"><label for="vacina_id">Vacina *</label><select id="vacina_id" name="vacina_id" data-vacina-select data-parametro="crianca_id" data-valor="<?php echo e($criancaId); ?>" required>
            <?php foreach ($vacinas as $vacina): ?><option value="<?php echo e($vacina['id']); ?>"<?php echo $vacinaId === (int) $vacina['id'] ? ' selected' : ''; ?>><?php echo e($vacina['nome']); ?></option><?php endforeach; ?>
        </select><span class="ajuda">Após trocar de vacina, atualize a página para carregar as doses.</span></div>
        <div class="campo"><label for="calendario_id">Dose do calendário *</label><select id="calendario_id" name="calendario_id" required>
            <?php foreach ($doses as $dose): ?><option value="<?php echo e($dose['id']); ?>"<?php echo (int) $dados['calendario_id'] === (int) $dose['id'] ? ' selected' : ''; ?>><?php echo e($dose['rotulo_idade'] . ' — dose ' . $dose['numero_dose']); ?></option><?php endforeach; ?>
        </select><input type="hidden" name="numero_dose" value="<?php echo e($dados['numero_dose']); ?>"></div>
        <div class="campo"><label for="data_administracao">Data de administração *</label><input type="date" id="data_administracao" name="data_administracao" value="<?php echo e($dados['data_administracao']); ?>" max="<?php echo e(date('Y-m-d')); ?>" required></div>
        <div class="campo"><label for="administrado_por_nome">Administrado por *</label><input type="text" id="administrado_por_nome" name="administrado_por_nome" value="<?php echo e($dados['administrado_por_nome']); ?>" maxlength="100" required></div>
        <div class="campo"><label for="local_anatomico">Local anatómico</label><input type="text" id="local_anatomico" name="local_anatomico" value="<?php echo e($dados['local_anatomico']); ?>" maxlength="100"></div>
        <div class="campo campo-amplo"><label for="reacoes">Reações</label><textarea id="reacoes" name="reacoes" maxlength="2000"><?php echo e($dados['reacoes']); ?></textarea></div>
        <div class="campo campo-amplo"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" maxlength="2000"><?php echo e($dados['observacoes']); ?></textarea></div>
    </div><div class="formulario-acoes"><button class="botao" type="submit">Registar dose externa</button><a class="botao botao-secundario" href="<?php echo e(url_publica() . 'vacinacao.php'); ?>">Cancelar</a></div></section>
</form>
<script src="<?php echo e(url_base() . 'assets/js/vacinacao.js'); ?>"></script>
<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
