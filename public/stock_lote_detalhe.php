<?php
/**
 * Detalhe do lote e histórico append-only dos seus movimentos.
 */

require_once __DIR__ . '/../app/core/stock.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('enfermeiro', 'responsavel_stock', 'administrador');

$pdo = conexao_app();
$id = isset($_GET['id']) && is_scalar($_GET['id']) ? (int) $_GET['id'] : 0;
$lote = $id > 0 ? obter_lote_stock($pdo, $id) : false;
if (!$lote) {
    http_response_code(404);
    exit('Lote não encontrado.');
}
$movimentos = listar_movimentos_lote($pdo, $id);
$limiares = limiares_stock($pdo);
$estado = estado_stock_lote($lote, $limiares);
$podeGerir = tem_perfil('responsavel_stock');
$tipos = ['entrada' => 'Entrada', 'saida_vacinacao' => 'Saída por vacinação', 'perda' => 'Perda', 'ajuste_mais' => 'Ajuste positivo', 'ajuste_menos' => 'Ajuste negativo'];
$tituloPagina = 'Detalhe do lote';
$menuAtual = 'stock.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho">
    <div><h1>Lote <?php echo e($lote['numero_lote']); ?></h1><p><?php echo e($lote['vacina_nome']); ?> · <?php echo e($lote['fabricante']); ?></p></div>
    <div class="modulo-acoes">
        <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'stock.php'); ?>">Voltar</a>
        <?php if ($podeGerir): ?>
            <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'stock_lote_form.php?id=' . $id); ?>">Editar lote</a>
            <a class="botao" href="<?php echo e(url_publica() . 'stock_movimento_form.php?lote_id=' . $id); ?>">Movimento</a>
        <?php endif; ?>
    </div>
</section>

<div class="modulo-grade">
    <section class="cartao">
        <h2>Dados do lote</h2>
        <dl class="dados-lista">
            <dt>Vacina</dt><dd><?php echo e($lote['vacina_nome']); ?></dd>
            <dt>Número</dt><dd><?php echo e($lote['numero_lote']); ?></dd>
            <dt>Fabricante</dt><dd><?php echo e($lote['fabricante']); ?></dd>
            <dt>Validade</dt><dd><?php echo e(data_pt_simples($lote['validade'])); ?></dd>
            <dt>Quantidade inicial</dt><dd><?php echo e($lote['quantidade_inicial']); ?> doses</dd>
            <dt>Saldo calculado</dt><dd><strong><?php echo e(max(0, (int) $lote['saldo_atual'])); ?> doses</strong></dd>
            <dt>Estado</dt><dd><span class="etiqueta <?php echo e($estado['classe']); ?>"><?php echo e($estado['rotulo']); ?></span></dd>
        </dl>
        <?php if ($estado['rotulo'] === 'Vencido'): ?><p class="mensagem mensagem-erro" role="alert">Lote vencido: as suas doses não estão disponíveis para atendimento.</p><?php endif; ?>
    </section>
    <aside class="cartao">
        <h2>Regra dos alertas</h2>
        <p>Validade próxima: até <?php echo e($limiares['validade_dias']); ?> dias. Stock baixo: saldo igual ou inferior a <?php echo e($limiares['minimo_doses']); ?> doses.</p>
        <p class="ajuda-destaque">O saldo não é editado. Corrija-o apenas com um movimento justificado.</p>
    </aside>
</div>

<section class="cartao">
    <h2>Movimentos</h2>
    <?php if (!$movimentos): ?>
        <p class="vazio">Ainda não há movimentos neste lote.</p>
    <?php else: ?>
        <div class="tabela-container">
            <table class="tabela tabela-movimentos">
                <caption class="sr-only">Histórico de movimentos do lote</caption>
                <thead><tr><th scope="col">Data</th><th scope="col">Tipo</th><th scope="col" class="numero">Doses</th><th scope="col">Motivo</th><th scope="col">Responsável</th></tr></thead>
                <tbody>
                    <?php foreach ($movimentos as $movimento): ?>
                        <tr>
                            <td><?php echo e(data_hora_pt_simples($movimento['data_movimento'])); ?></td>
                            <td><?php echo e($tipos[$movimento['tipo']] ?? $movimento['tipo']); ?></td>
                            <td class="numero"><?php echo e($movimento['quantidade']); ?></td>
                            <td><?php echo e($movimento['motivo'] ?: '—'); ?></td>
                            <td><?php echo e($movimento['utilizador_nome'] ?: 'Sistema'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
