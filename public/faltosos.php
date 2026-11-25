<?php
/**
 * Lista indicativa de crianças com doses pendentes ou em atraso.
 */

require_once __DIR__ . '/../app/core/faltosos.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('recepcao', 'enfermeiro', 'administrador');

$pdo = conexao_app();
$termo = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$estado = is_string($_GET['estado'] ?? null) ? $_GET['estado'] : 'em_atraso';
$pagina = isset($_GET['pagina']) && is_scalar($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$resultado = listar_criancas_com_calendario($pdo, $termo, $estado, $pagina, 15);
$estado = $resultado['estado'];
$pagina = $resultado['pagina'];
$totalDoses = 0;
foreach ($resultado['registos'] as $registo) $totalDoses += count($registo['pendentes']);
$filtros = ['q' => $termo, 'estado' => $estado];
$estados = ['em_atraso' => 'Em atraso', 'a_vencer' => 'A vencer', 'em_dia' => 'Em dia', 'todos' => 'Todas as pendentes'];

$tituloPagina = 'Faltosos';
$menuAtual = 'faltosos.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho">
    <div>
        <h1>Faltosos e calendário</h1>
        <p>Estados calculados no momento da consulta. Doses anuladas não contam como cumpridas.</p>
    </div>
</section>

<div class="aviso-clinico" role="note">Valores clínicos por validar pela equipa clínica: o cálculo é indicativo. Regras com valor NULL são ignoradas.</div>

<section class="cartao" aria-label="Resumo dos faltosos">
    <div class="resumo-inline">
        <div><strong><?php echo e($resultado['total']); ?></strong><span>Crianças no resultado</span></div>
        <div><strong><?php echo e($totalDoses); ?></strong><span>Doses pendentes nesta página</span></div>
        <div><strong><?php echo e($resultado['dias_aviso']); ?> dias</strong><span>Antecedência do aviso operacional</span></div>
    </div>
</section>

<section class="cartao">
    <form method="GET" action="<?php echo e(url_publica() . 'faltosos.php'); ?>" class="barra-pesquisa">
        <div class="campo">
            <label for="q">Pesquisar criança, processo ou encarregado</label>
            <input type="search" id="q" name="q" value="<?php echo e($termo); ?>" maxlength="100">
        </div>
        <div class="campo">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <?php foreach ($estados as $valor => $rotulo): ?>
                    <option value="<?php echo e($valor); ?>"<?php echo $estado === $valor ? ' selected' : ''; ?>><?php echo e($rotulo); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="botao botao-secundario" type="submit">Filtrar</button>
        <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'faltosos.php'); ?>">Limpar</a>
    </form>
</section>

<section class="cartao">
    <h2>Crianças com doses pendentes</h2>
    <?php if (!$resultado['registos']): ?>
        <p class="vazio">Nenhuma criança encontrada para este estado.</p>
    <?php else: ?>
        <div class="tabela-container"><table class="tabela">
            <caption class="sr-only">Crianças, contactos do encarregado e doses pendentes</caption>
            <thead><tr><th scope="col">Criança</th><th scope="col">Processo</th><th scope="col">Encarregado</th><th scope="col">Telefone</th><th scope="col" class="numero">Em atraso</th><th scope="col">Doses pendentes</th><th scope="col">Próxima data</th><th scope="col" class="acoes">Detalhe</th></tr></thead>
            <tbody><?php foreach ($resultado['registos'] as $registo): ?>
                <?php
                $crianca = $registo['crianca'];
                $proxima = '';
                $nomes = [];
                foreach ($registo['pendentes'] as $pendente) {
                    $nomes[] = $pendente['vacina_nome'] . ' (dose ' . $pendente['numero_dose'] . ')';
                    if ($proxima === '' || $pendente['data_elegivel'] < $proxima) $proxima = $pendente['data_elegivel'];
                }
                ?>
                <tr>
                    <td><?php echo e($crianca['nome']); ?></td>
                    <td><?php echo e($crianca['numero_processo']); ?></td>
                    <td><?php echo e($crianca['encarregado_nome']); ?></td>
                    <td><?php echo e($crianca['telefone']); ?></td>
                    <td class="numero"><?php echo e($registo['contagens']['em_atraso']); ?></td>
                    <td><?php echo e(implode(', ', $nomes)); ?></td>
                    <td><?php echo e(data_pt_simples($proxima)); ?></td>
                    <td class="acoes"><a class="ligacao-acao" href="<?php echo e(url_publica() . 'faltoso_detalhe.php?id=' . $crianca['id']); ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php if ($resultado['paginas'] > 1): ?>
    <nav class="paginacao" aria-label="Paginação dos faltosos">
        <?php if ($pagina > 1): ?>
            <?php $anterior = $filtros; $anterior['pagina'] = $pagina - 1; ?>
            <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'faltosos.php?' . http_build_query($anterior)); ?>">Anterior</a>
        <?php endif; ?>
        <span class="paginacao-info">Página <?php echo e($pagina); ?> de <?php echo e($resultado['paginas']); ?></span>
        <?php if ($pagina < $resultado['paginas']): ?>
            <?php $seguinte = $filtros; $seguinte['pagina'] = $pagina + 1; ?>
            <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'faltosos.php?' . http_build_query($seguinte)); ?>">Seguinte</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<p class="ajuda-destaque">O aviso de “a vencer” usa um limite operacional configurável e não substitui uma janela clínica. Não são enviados SMS automáticos.</p>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
