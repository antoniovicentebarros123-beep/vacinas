<?php
/**
 * Painel de atendimento: pesquisa, fila e estados do dia.
 */

require __DIR__ . '/../app/core/modulos.php';
require_once __DIR__ . '/../app/core/atendimentos.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('recepcao', 'enfermeiro', 'administrador');
$pdo = conexao_app();
$termo = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$pagina = isset($_GET['pagina']) && is_scalar($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$pesquisa = $termo !== '' ? pesquisar_criancas($pdo, $termo, $pagina, 10) : null;
$atendimentosDia = listar_atendimentos_dia($pdo);
$fila = tem_perfil('enfermeiro', 'administrador') ? listar_fila_atendimento($pdo) : [];
$tituloPagina = 'Atendimento';
$menuAtual = 'atendimento.php';
$scriptPagina = tem_perfil('enfermeiro', 'administrador') ? 'fila.js' : '';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>
<section class="modulo-cabecalho">
    <div><h1>Atendimento</h1><p>Abra atendimentos na recepção e acompanhe a fila da enfermagem.</p></div>
    <div class="modulo-acoes"><a class="botao botao-secundario" href="<?php echo e(url_publica() . 'disponibilidade.php'); ?>"><?php echo icone('package'); ?>Ver disponibilidade</a></div>
</section>

<?php if (tem_perfil('recepcao')): ?>
<section class="cartao">
    <h2>Pesquisar criança</h2>
    <form class="barra-pesquisa" method="get" action="<?php echo e(url_publica() . 'atendimento.php'); ?>">
        <div class="campo"><label for="q">Nome, processo, cartão ou telefone</label><input type="search" id="q" name="q" value="<?php echo e($termo); ?>" placeholder="Pesquisar criança"></div>
        <button type="submit" class="botao botao-primario">Pesquisar</button>
    </form>
    <?php if ($pesquisa !== null): ?>
        <div class="tabela-container">
            <table class="tabela">
                <caption class="so-leitor">Resultados da pesquisa</caption>
                <thead><tr><th scope="col">Criança</th><th scope="col">Processo</th><th scope="col">Encarregado</th><th scope="col" class="acoes">Ação</th></tr></thead>
                <tbody>
                <?php if (!$pesquisa['registos']): ?><tr><td colspan="4" class="vazio">Nenhuma criança encontrada.</td></tr><?php endif; ?>
                <?php foreach ($pesquisa['registos'] as $crianca): ?>
                    <tr><td><?php echo e($crianca['nome']); ?></td><td><?php echo e($crianca['numero_processo']); ?></td><td><?php echo e($crianca['encarregado_nome']); ?><br><small><?php echo e($crianca['telefone']); ?></small></td><td class="acoes"><a class="botao botao-primario botao-pequeno" href="<?php echo e(url_publica() . 'atendimento_form.php?crianca_id=' . (int) $crianca['id']); ?>">Abrir atendimento</a></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="paginacao"><span class="paginacao-info">Página <?php echo (int) $pesquisa['pagina']; ?> de <?php echo (int) $pesquisa['paginas']; ?></span><div class="modulo-acoes">
            <?php if ($pesquisa['pagina'] > 1): ?><a class="botao botao-secundario botao-pequeno" href="<?php echo e(url_publica() . 'atendimento.php?q=' . urlencode($termo) . '&pagina=' . ($pesquisa['pagina'] - 1)); ?>">Anterior</a><?php endif; ?>
            <?php if ($pesquisa['pagina'] < $pesquisa['paginas']): ?><a class="botao botao-secundario botao-pequeno" href="<?php echo e(url_publica() . 'atendimento.php?q=' . urlencode($termo) . '&pagina=' . ($pesquisa['pagina'] + 1)); ?>">Seguinte</a><?php endif; ?>
        </div></div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (tem_perfil('enfermeiro', 'administrador')): ?>
<section class="cartao">
    <div class="modulo-cabecalho"><div><h2>Fila de atendimento</h2><p>Atualização automática a cada 12 segundos.</p></div><span class="etiqueta etiqueta-aviso"><?php echo count($fila); ?> aguardando</span></div>
    <p id="filaEstado" class="fila-estado" role="status" aria-live="polite">A acompanhar a fila.</p>
    <div id="filaDados" class="fila-lista" data-url="<?php echo e(url_publica() . 'fila.php'); ?>" data-detalhe="<?php echo e(url_publica() . 'atendimento_detalhe.php?id='); ?>">
        <?php if (!$fila): ?><p class="vazio">Não há atendimentos aguardando.</p><?php endif; ?>
        <?php foreach ($fila as $item): ?>
            <article class="fila-item"><div><strong><?php echo e($item['crianca_nome']); ?></strong><br><small><?php echo e(idade_por_extenso_curta($item['data_nascimento'])); ?></small></div><div><?php echo e($item['vacinas'] ? implode(', ', $item['vacinas']) : '—'); ?></div><time datetime="<?php echo e($item['criado_em']); ?>"><?php echo e(date('H:i', strtotime($item['criado_em']))); ?></time><a class="botao botao-primario botao-pequeno" href="<?php echo e(url_publica() . 'atendimento_detalhe.php?id=' . (int) $item['id']); ?>">Abrir ficha</a></article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="cartao">
    <h2>Atendimentos de hoje</h2>
    <div class="tabela-container">
        <table class="tabela"><caption class="so-leitor">Atendimentos do dia</caption><thead><tr><th scope="col">Chegada</th><th scope="col">Criança</th><th scope="col">Vacinas pretendidas</th><th scope="col">Estado</th><th scope="col" class="acoes">Ficha</th></tr></thead><tbody>
        <?php if (!$atendimentosDia): ?><tr><td colspan="5" class="vazio">Ainda não há atendimentos hoje.</td></tr><?php endif; ?>
        <?php foreach ($atendimentosDia as $atendimento): ?><tr><td><?php echo e(date('H:i', strtotime($atendimento['criado_em']))); ?></td><td><?php echo e($atendimento['crianca_nome']); ?><br><small><?php echo e($atendimento['numero_processo']); ?></small></td><td><?php echo e($atendimento['vacinas'] ? implode(', ', $atendimento['vacinas']) : '—'); ?></td><td><span class="etiqueta <?php echo e(classe_estado_atendimento($atendimento['estado'])); ?>"><?php echo e(rotulo_estado_atendimento($atendimento['estado'])); ?></span></td><td class="acoes"><a class="ligacao-acao" href="<?php echo e(url_publica() . 'atendimento_detalhe.php?id=' . (int) $atendimento['id']); ?>">Abrir</a></td></tr><?php endforeach; ?>
        </tbody></table>
    </div>
</section>
<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
