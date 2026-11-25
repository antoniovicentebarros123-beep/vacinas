<?php
/**
 * Criação e edição de lotes, exclusivamente pelo responsável de stock.
 */

require_once __DIR__ . '/../app/core/stock.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('responsavel_stock');

$pdo = conexao_app();
$id = isset($_GET['id']) && is_scalar($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['id'] ?? 0);
$lote = $id > 0 ? obter_lote_stock($pdo, $id) : false;
if ($id > 0 && !$lote) {
    http_response_code(404);
    exit('Lote não encontrado.');
}
$dados = $lote ?: [
    'vacina_id' => 0, 'numero_lote' => '', 'validade' => '', 'fabricante' => '', 'quantidade_inicial' => 0,
];
$erros = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $validacao = validar_dados_lote($_POST);
    $erros = $validacao['erros'];
    $dados = array_merge($dados, $validacao['dados']);
    if (!$erros) {
        try {
            if ($id > 0) {
                atualizar_lote_stock($pdo, $id, $dados, (int) $_SESSION['utilizador_id']);
                redirecionar_modulo('stock_lote_detalhe.php?id=' . $id, 'sucesso', 'Lote atualizado com auditoria.');
            }
            $novoId = criar_lote_stock($pdo, $dados, (int) $_SESSION['utilizador_id']);
            redirecionar_modulo('stock_lote_detalhe.php?id=' . $novoId, 'sucesso', 'Lote criado. A entrada inicial foi registada como movimento.');
        } catch (RuntimeException $e) {
            $erros[] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Erro ao gravar lote: ' . $e->getMessage());
            $erros[] = 'Não foi possível gravar o lote. Tente novamente.';
        }
    }
}
$vacinas = listar_vacinas_stock($pdo);
$tituloPagina = $id > 0 ? 'Editar lote' : 'Novo lote';
$menuAtual = 'stock.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho">
    <div><h1><?php echo e($tituloPagina); ?></h1><p>A quantidade inicial gera um movimento de entrada e não é um saldo guardado.</p></div>
    <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'stock.php'); ?>">Voltar ao stock</a>
</section>

<?php if ($erros): ?>
    <div class="mensagem mensagem-erro" role="alert"><ul><?php foreach ($erros as $erro): ?><li><?php echo e($erro); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="POST" action="<?php echo e(url_publica() . 'stock_lote_form.php'); ?>">
    <?php echo csrf_field(); ?>
    <?php if ($id > 0): ?><input type="hidden" name="id" value="<?php echo e($id); ?>"><?php endif; ?>
    <section class="cartao">
        <div class="grelha-formulario">
            <div class="campo campo-amplo">
                <label for="vacina_id">Vacina *</label>
                <select id="vacina_id" name="vacina_id" required<?php echo $id > 0 ? ' disabled' : ''; ?>>
                    <option value="">Selecione</option>
                    <?php foreach ($vacinas as $vacina): ?>
                        <option value="<?php echo e($vacina['id']); ?>"<?php echo (int) $dados['vacina_id'] === (int) $vacina['id'] ? ' selected' : ''; ?>><?php echo e($vacina['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($id > 0): ?><input type="hidden" name="vacina_id" value="<?php echo e($dados['vacina_id']); ?>"><span class="ajuda">A vacina de um lote não pode ser alterada.</span><?php endif; ?>
            </div>
            <div class="campo">
                <label for="numero_lote">Número do lote *</label>
                <input type="text" id="numero_lote" name="numero_lote" value="<?php echo e($dados['numero_lote']); ?>" maxlength="50" required autofocus>
                <span class="ajuda">Espaços nas extremidades são removidos e as letras ficam em maiúsculas.</span>
            </div>
            <div class="campo">
                <label for="validade">Validade *</label>
                <input type="date" id="validade" name="validade" value="<?php echo e($dados['validade']); ?>" required>
            </div>
            <div class="campo campo-amplo">
                <label for="fabricante">Fabricante *</label>
                <input type="text" id="fabricante" name="fabricante" value="<?php echo e($dados['fabricante']); ?>" maxlength="100" required>
            </div>
            <?php if ($id === 0): ?>
                <div class="campo">
                    <label for="quantidade_inicial">Quantidade inicial</label>
                    <input type="number" id="quantidade_inicial" name="quantidade_inicial" value="<?php echo e($dados['quantidade_inicial']); ?>" min="0" max="2147483647" step="1">
                </div>
            <?php endif; ?>
        </div>
        <div class="formulario-acoes">
            <button class="botao" type="submit"><?php echo $id > 0 ? 'Guardar alterações' : 'Criar lote'; ?></button>
            <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'stock.php'); ?>">Cancelar</a>
        </div>
    </section>
</form>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
