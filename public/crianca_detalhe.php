<?php
require_once __DIR__ . '/../app/core/criancas.php';
require_once __DIR__ . '/../app/core/vacinacao.php';
require_once __DIR__ . '/../app/core/faltosos.php';
ini_set('display_errors', config('display_errors', false) ? '1' : '0');
iniciar_sessao_segura();
requer_login();
requer_perfil('recepcao', 'enfermeiro', 'administrador');

$pdo = conexao_app();
$id = max(0, (int) ($_GET['id'] ?? 0));
$crianca = $id > 0 ? obter_crianca($pdo, $id) : false;
if (!$crianca) {
    http_response_code(404);
    exit('Criança não encontrada.');
}
$historico = listar_historico_crianca($pdo, $id);
$oportunidades = listar_oportunidades_calendario($pdo);
$vacinacoesValidas = listar_vacinacoes_validas_faltosos($pdo, $id);
$calendario = calcular_estado_calendario($crianca, $oportunidades, $vacinacoesValidas, date('Y-m-d'), faltosos_dias_aviso($pdo));
$tituloPagina = 'Ficha da criança';
$menuAtual = 'criancas.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho">
    <div>
        <h1><?php echo e($crianca['nome']); ?></h1>
        <p>Processo <?php echo e($crianca['numero_processo']); ?> · <?php echo e(idade_por_extenso_curta($crianca['data_nascimento'])); ?></p>
    </div>
    <div class="modulo-acoes">
        <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'criancas.php'); ?>">Voltar</a>
        <?php if ((int) $crianca['ativo'] === 1 && $crianca['eliminado_em'] === null): ?>
            <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'cartao_vacinas.php?id=' . $crianca['id']); ?>"><?php echo icone('file-text'); ?>2.ª via do cartão</a>
        <?php endif; ?>
        <?php if (tem_perfil('recepcao') && (int) $crianca['ativo'] === 1 && $crianca['eliminado_em'] === null): ?>
            <a class="botao" href="<?php echo e(url_publica() . 'atendimento_form.php?crianca_id=' . $crianca['id']); ?>"><?php echo icone('calendar-days'); ?>Abrir atendimento</a>
            <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'crianca_form.php?id=' . $crianca['id']); ?>">Editar</a>
        <?php endif; ?>
        <?php if (tem_perfil('enfermeiro') && (int) $crianca['ativo'] === 1 && $crianca['eliminado_em'] === null): ?>
            <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'vacinacao_externa_form.php?crianca_id=' . $crianca['id']); ?>">Registar dose externa</a>
        <?php endif; ?>
    </div>
</section>

<?php if ((int) $crianca['ativo'] === 0 || $crianca['eliminado_em'] !== null): ?>
    <div class="mensagem mensagem-aviso" role="alert">Esta criança está eliminada logicamente. O histórico é preservado.</div>
<?php endif; ?>

<section class="detalhe-grade">
    <article class="cartao">
        <h2>Dados da criança</h2>
        <hr class="separador">
        <dl class="dados-lista">
            <dt>Número de processo</dt><dd><?php echo e($crianca['numero_processo']); ?></dd>
            <dt>Número do cartão</dt><dd><?php echo e($crianca['numero_cartao'] ?: '—'); ?></dd>
            <dt>Nome completo</dt><dd><?php echo e($crianca['nome']); ?></dd>
            <dt>Data de nascimento</dt><dd><?php echo e(data_pt_simples($crianca['data_nascimento'])); ?><?php if ($crianca['data_nascimento_aproximada']): ?> <span class="etiqueta etiqueta-aviso">aproximada</span><?php endif; ?></dd>
            <dt>Sexo</dt><dd><?php echo $crianca['sexo'] === 'F' ? 'Feminino' : 'Masculino'; ?></dd>
            <dt>Observações</dt><dd><?php echo nl2br(e($crianca['observacoes'] ?: '—')); ?></dd>
        </dl>
    </article>

    <article class="cartao">
        <h2>Encarregado</h2>
        <hr class="separador">
        <dl class="dados-lista">
            <dt>Nome</dt><dd><?php echo e($crianca['encarregado_nome']); ?></dd>
            <dt>Parentesco</dt><dd><?php echo e($crianca['parentesco']); ?></dd>
            <dt>Telefone</dt><dd><?php echo e($crianca['telefone']); ?></dd>
            <dt>Telefone alternativo</dt><dd><?php echo e($crianca['telefone_alt'] ?: '—'); ?></dd>
            <dt>Morada</dt><dd><?php echo e($crianca['morada'] ?: '—'); ?></dd>
            <dt>Bairro</dt><dd><?php echo e($crianca['bairro_nome'] ?: '—'); ?></dd>
        </dl>
        <?php if (tem_perfil('recepcao')): ?>
            <hr class="separador">
            <a class="ligacao-acao" href="<?php echo e(url_publica() . 'encarregado_form.php?id=' . $crianca['encarregado_id']); ?>">Editar encarregado</a>
        <?php endif; ?>
    </article>
</section>

<?php $podeAnular = tem_perfil('enfermeiro', 'administrador'); ?>
<?php require __DIR__ . '/../app/views/partials/historico_vacinacao.php'; ?>

<div class="aviso-clinico" role="note">Valores clínicos por validar pela equipa clínica: o cálculo é indicativo. Regras com valor NULL são ignoradas.</div>
<?php require __DIR__ . '/../app/views/partials/calendario_pendente.php'; ?>

<?php if (tem_perfil('recepcao') && (int) $crianca['ativo'] === 1 && $crianca['eliminado_em'] === null): ?>
    <section class="cartao">
        <h2>Eliminação lógica</h2>
        <p class="ajuda">A criança deixa de aparecer nas pesquisas e no atendimento, mas os registos e a auditoria são preservados.</p>
        <form method="POST" action="<?php echo e(url_publica() . 'crianca_eliminar.php'); ?>" class="acao-eliminar" data-confirm="Confirmar a eliminação lógica desta criança?">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo e($crianca['id']); ?>">
            <button class="botao botao-perigo" type="submit">Eliminar logicamente</button>
        </form>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
