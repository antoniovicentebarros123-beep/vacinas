<?php
/**
 * Formulário de movimentos manuais de stock.
 */

require_once __DIR__ . '/../app/core/stock.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('responsavel_stock');

$pdo = conexao_app();
$loteId = isset($_GET['lote_id']) && is_scalar($_GET['lote_id']) ? (int) $_GET['lote_id'] : 0;
$tipo = is_string($_GET['tipo'] ?? null) && in_array($_GET['tipo'], ['entrada', 'perda', 'ajuste_mais', 'ajuste_menos'], true) ? $_GET['tipo'] : 'entrada';
$lote = $loteId > 0 ? obter_lote_stock($pdo, $loteId) : false;
if ($loteId > 0 && !$lote) {
    http_response_code(404);
    exit('Lote não encontrado.');
}
$lotes = listar_lotes_stock($pdo);
$limiares = limiares_stock($pdo);
$tituloPagina = 'Movimento de stock';
$menuAtual = 'stock.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
$tipos = ['entrada' => 'Entrada', 'perda' => 'Perda', 'ajuste_mais' => 'Ajuste positivo', 'ajuste_menos' => 'Ajuste negativo'];
?>

<section class="modulo-cabecalho">
    <div><h1>Registar movimento</h1><p>Todos os movimentos são acrescentados ao histórico. Não existe edição ou eliminação de movimentos.</p></div>
    <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'stock.php'); ?>">Voltar ao stock</a>
</section>

<form method="POST" action="<?php echo e(url_publica() . 'stock_movimento.php'); ?>">
    <?php echo csrf_field(); ?>
    <section class="cartao">
        <div class="grelha-formulario">
            <div class="campo campo-amplo">
                <label for="lote_id">Lote *</label>
                <select id="lote_id" name="lote_id" required>
                    <option value="">Selecione um lote</option>
                    <?php foreach ($lotes as $item): ?>
                        <?php $estado = estado_stock_lote($item, $limiares); ?>
                        <option value="<?php echo e($item['id']); ?>"<?php echo $loteId === (int) $item['id'] ? ' selected' : ''; ?>>
                            <?php echo e($item['vacina_nome'] . ' — ' . $item['numero_lote'] . ' — ' . max(0, (int) $item['saldo_atual']) . ' doses — ' . $estado['rotulo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="tipo">Tipo de movimento *</label>
                <select id="tipo" name="tipo" required>
                    <?php foreach ($tipos as $valor => $rotulo): ?>
                        <option value="<?php echo e($valor); ?>"<?php echo $tipo === $valor ? ' selected' : ''; ?>><?php echo e($rotulo); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="quantidade">Quantidade em doses *</label>
                <input type="number" id="quantidade" name="quantidade" min="1" max="2147483647" step="1" required autofocus>
            </div>
            <div class="campo campo-amplo">
                <label for="motivo">Motivo</label>
                <textarea id="motivo" name="motivo" maxlength="2000"></textarea>
                <span class="ajuda">Obrigatório em perdas e ajustes. A saída por vacinação é registada pelo módulo de vacinação.</span>
            </div>
        </div>
        <div class="formulario-acoes">
            <button class="botao" type="submit">Registar movimento</button>
            <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'stock.php'); ?>">Cancelar</a>
        </div>
    </section>
</form>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
