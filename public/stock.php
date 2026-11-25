<?php
/**
 * Consulta de lotes e saldos calculados a partir dos movimentos.
 */

require_once __DIR__ . '/../app/core/stock.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('enfermeiro', 'responsavel_stock', 'administrador');

$pdo = conexao_app();
$vacinaId = isset($_GET['vacina_id']) && is_scalar($_GET['vacina_id']) ? (int) $_GET['vacina_id'] : 0;
$termo = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$lotes = listar_lotes_stock($pdo, $vacinaId, $termo);
$vacinas = listar_vacinas_stock($pdo);
$limiares = limiares_stock($pdo);
$podeGerir = tem_perfil('responsavel_stock');
$totalDoses = 0;
$alertas = 0;
foreach ($lotes as $lote) {
    $estado = estado_stock_lote($lote, $limiares);
    $totalDoses += max(0, (int) $lote['saldo_atual']);
    if ($estado['rotulo'] !== 'Regular') {
        $alertas++;
    }
}

$tituloPagina = 'Stock';
$menuAtual = 'stock.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho">
    <div>
        <h1>Stock de vacinas</h1>
        <p>Os saldos são calculados a partir dos movimentos registados. Os lotes vencidos não contam como doses disponíveis.</p>
    </div>
    <?php if ($podeGerir): ?>
        <div class="modulo-acoes">
            <a class="botao" href="<?php echo e(url_publica() . 'stock_lote_form.php'); ?>">Novo lote</a>
            <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'stock_movimento_form.php'); ?>">Registar movimento</a>
        </div>
    <?php endif; ?>
</section>

<section class="cartao" aria-label="Resumo do stock">
    <div class="resumo-inline">
        <div><strong><?php echo e($totalDoses); ?></strong><span>Doses no inventário</span></div>
        <div><strong><?php echo e(count($lotes)); ?></strong><span>Lotes no resultado</span></div>
        <div><strong><?php echo e($alertas); ?></strong><span>Lotes com alerta</span></div>
        <div><strong><?php echo e($limiares['validade_dias']); ?> dias</strong><span>Aviso de validade</span></div>
        <div><strong><?php echo e($limiares['minimo_doses']); ?> doses</strong><span>Limite de stock baixo</span></div>
    </div>
</section>

<section class="cartao">
    <form method="GET" action="<?php echo e(url_publica() . 'stock.php'); ?>" class="barra-pesquisa">
        <div class="campo">
            <label for="q">Pesquisar lote ou fabricante</label>
            <input type="search" id="q" name="q" value="<?php echo e($termo); ?>" maxlength="100">
        </div>
        <div class="campo">
            <label for="vacina_id">Vacina</label>
            <select id="vacina_id" name="vacina_id">
                <option value="0">Todas as vacinas</option>
                <?php foreach ($vacinas as $vacina): ?>
                    <option value="<?php echo e($vacina['id']); ?>"<?php echo $vacinaId === (int) $vacina['id'] ? ' selected' : ''; ?>><?php echo e($vacina['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="botao botao-secundario" type="submit">Filtrar</button>
        <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'stock.php'); ?>">Limpar</a>
    </form>
</section>

<section class="cartao">
    <h2>Lotes</h2>
    <?php if (!$lotes): ?>
        <p class="vazio">Nenhum lote encontrado.</p>
    <?php else: ?>
        <div class="tabela-container">
            <table class="tabela">
                <caption class="sr-only">Lotes, validades, saldos e estados de stock</caption>
                <thead><tr><th scope="col">Vacina</th><th scope="col">Lote</th><th scope="col">Fabricante</th><th scope="col">Validade</th><th scope="col" class="numero">Saldo</th><th scope="col">Estado</th><th scope="col" class="acoes">Detalhe</th></tr></thead>
                <tbody>
                    <?php foreach ($lotes as $lote): ?>
                        <?php $estado = estado_stock_lote($lote, $limiares); ?>
                        <tr>
                            <td><?php echo e($lote['vacina_nome']); ?></td>
                            <td><?php echo e($lote['numero_lote']); ?></td>
                            <td><?php echo e($lote['fabricante']); ?></td>
                            <td><?php echo e(data_pt_simples($lote['validade'])); ?></td>
                            <td class="numero"><?php echo e(max(0, (int) $lote['saldo_atual'])); ?> doses</td>
                            <td><span class="etiqueta <?php echo e($estado['classe']); ?>"><?php echo e($estado['rotulo']); ?></span></td>
                            <td class="acoes"><a class="ligacao-acao" href="<?php echo e(url_publica() . 'stock_lote_detalhe.php?id=' . $lote['id']); ?>" aria-label="Abrir lote <?php echo e($lote['numero_lote']); ?>">Abrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if (!$podeGerir): ?>
    <p class="ajuda-destaque">Consulta apenas. Entradas, perdas e ajustes são registados pelo responsável de stock.</p>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
