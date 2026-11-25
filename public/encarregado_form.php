<?php
require_once __DIR__ . '/../app/core/criancas.php';
ini_set('display_errors', config('display_errors', false) ? '1' : '0');
iniciar_sessao_segura();
requer_login();
requer_perfil('recepcao');

$pdo = conexao_app();
$id = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
$encarregado = $id > 0 ? obter_encarregado($pdo, $id) : false;
if ($id > 0 && !$encarregado) {
    http_response_code(404);
    exit('Encarregado não encontrado.');
}
$dados = $encarregado ?: [
    'nome' => '', 'parentesco' => '', 'telefone' => '', 'telefone_alt' => '',
    'morada' => '', 'bairro_id' => 0, 'novo_bairro' => '',
];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $validacao = validar_dados_encarregado($_POST);
    $erros = $validacao['erros'];
    $dados = array_merge($dados, $validacao['dados']);
    if ($dados['bairro_id'] > 0) {
        $stmt = $pdo->prepare('SELECT id FROM bairros WHERE id = ? AND ativo = 1 AND eliminado_em IS NULL LIMIT 1');
        $stmt->execute([$dados['bairro_id']]);
        if (!$stmt->fetch()) $erros[] = 'O bairro selecionado não está ativo.';
    }
    if (!$erros) {
        try {
            gravar_encarregado($pdo, $dados, (int) $_SESSION['utilizador_id'], $id);
            redirecionar_modulo('encarregados.php', 'sucesso', $id > 0 ? 'Encarregado atualizado com auditoria.' : 'Encarregado cadastrado com sucesso.');
        } catch (Throwable $e) {
            error_log('Erro ao gravar encarregado: ' . $e->getMessage());
            $erros[] = 'Não foi possível gravar o encarregado. Tente novamente.';
        }
    }
}

$bairros = listar_bairros($pdo);
$tituloPagina = $id > 0 ? 'Editar encarregado' : 'Cadastrar encarregado';
$menuAtual = 'criancas.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho">
    <div>
        <h1><?php echo e($tituloPagina); ?></h1>
        <p>O bairro é opcional. A lista começa vazia e pode ser preenchida pelo campo “Novo bairro”.</p>
    </div>
    <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'encarregados.php'); ?>">Voltar</a>
</section>

<?php if ($erros): ?>
    <div class="mensagem mensagem-erro" role="alert">
        <strong>Corrija os seguintes campos:</strong>
        <ul><?php foreach ($erros as $erro): ?><li><?php echo e($erro); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="POST" action="<?php echo e(url_publica() . 'encarregado_form.php'); ?>">
    <?php echo csrf_field(); ?>
    <?php if ($id > 0): ?><input type="hidden" name="id" value="<?php echo e($id); ?>"><?php endif; ?>
    <section class="cartao">
        <div class="grelha-formulario">
            <div class="campo campo-amplo">
                <label for="nome">Nome completo *</label>
                <input type="text" id="nome" name="nome" value="<?php echo e($dados['nome']); ?>" maxlength="100" required autofocus>
            </div>
            <div class="campo">
                <label for="parentesco">Parentesco *</label>
                <input type="text" id="parentesco" name="parentesco" value="<?php echo e($dados['parentesco']); ?>" maxlength="50" placeholder="Ex.: mãe, pai, avó" required>
            </div>
            <div class="campo">
                <label for="telefone">Telefone *</label>
                <input type="tel" id="telefone" name="telefone" value="<?php echo e($dados['telefone']); ?>" maxlength="20" data-apenas-digitos required>
                <span class="ajuda">Apenas dígitos, com indicativo.</span>
            </div>
            <div class="campo">
                <label for="telefone_alt">Telefone alternativo</label>
                <input type="tel" id="telefone_alt" name="telefone_alt" value="<?php echo e($dados['telefone_alt']); ?>" maxlength="20" data-apenas-digitos>
            </div>
            <div class="campo campo-amplo">
                <label for="morada">Morada</label>
                <textarea id="morada" name="morada" maxlength="500"><?php echo e($dados['morada']); ?></textarea>
            </div>
            <div class="campo">
                <label for="bairro_id">Bairro existente</label>
                <select id="bairro_id" name="bairro_id">
                    <option value="">Não informado</option>
                    <?php foreach ($bairros as $bairro): ?>
                        <option value="<?php echo e($bairro['id']); ?>"<?php echo (int) $dados['bairro_id'] === (int) $bairro['id'] ? ' selected' : ''; ?>><?php echo e($bairro['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="novo_bairro">Novo bairro</label>
                <input type="text" id="novo_bairro" name="novo_bairro" value="<?php echo e($dados['novo_bairro'] ?? ''); ?>" maxlength="100">
                <span class="ajuda">Preencher apenas se o bairro ainda não existir.</span>
            </div>
        </div>
        <div class="formulario-acoes">
            <button class="botao" type="submit"><?php echo $id > 0 ? 'Guardar alterações' : 'Cadastrar encarregado'; ?></button>
            <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'encarregados.php'); ?>">Cancelar</a>
        </div>
    </section>
</form>

<?php if ($id > 0 && (int) $encarregado['ativo'] === 1 && $encarregado['eliminado_em'] === null): ?>
    <section class="cartao">
        <h2>Eliminação lógica</h2>
        <p class="ajuda">Só é permitida quando o encarregado não tem crianças ativas. Os registos são preservados.</p>
        <form method="POST" action="<?php echo e(url_publica() . 'encarregado_eliminar.php'); ?>" class="acao-eliminar" data-confirm="Confirmar a eliminação lógica deste encarregado?">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo e($id); ?>">
            <button class="botao botao-perigo" type="submit">Eliminar logicamente</button>
        </form>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
