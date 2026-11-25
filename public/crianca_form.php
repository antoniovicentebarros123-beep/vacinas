<?php
require_once __DIR__ . '/../app/core/criancas.php';
ini_set('display_errors', config('display_errors', false) ? '1' : '0');
iniciar_sessao_segura();
requer_login();
requer_perfil('recepcao');

$pdo = conexao_app();
$id = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
$crianca = $id > 0 ? obter_crianca($pdo, $id) : false;
if ($id > 0 && !$crianca) {
    http_response_code(404);
    exit('Criança não encontrada.');
}

$dados = $crianca ?: [
    'numero_processo' => '', 'numero_cartao' => '', 'nome' => '', 'data_nascimento' => '',
    'data_nascimento_aproximada' => false, 'sexo' => '', 'encarregado_id' => 0, 'observacoes' => '',
];
$erros = [];
$duplicados = [];
$confirmarDuplicado = !empty($_POST['confirmar_duplicado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    $validacao = validar_dados_crianca($_POST);
    $erros = $validacao['erros'];
    $dados = array_merge($dados, $validacao['dados']);
    if ($dados['encarregado_id'] > 0 && !encarregado_ativo_valido($pdo, $dados['encarregado_id'])) {
        $erros[] = 'O encarregado selecionado não está ativo.';
    }
    if (!$erros && $id === 0 && !$confirmarDuplicado) {
        $duplicados = encontrar_duplicados_crianca($pdo, $dados['nome'], $dados['data_nascimento']);
    }
    if (!$erros && ($id > 0 || $confirmarDuplicado || !$duplicados)) {
        try {
            if ($id > 0) {
                atualizar_crianca($pdo, $id, $dados, (int) $_SESSION['utilizador_id']);
                redirecionar_modulo('crianca_detalhe.php?id=' . $id, 'sucesso', 'Dados da criança atualizados com auditoria.');
            }
            $nova = criar_crianca($pdo, $dados, (int) $_SESSION['utilizador_id']);
            redirecionar_modulo('crianca_detalhe.php?id=' . $nova['id'], 'sucesso', 'Criança registada com o processo ' . $nova['numero_processo'] . '.');
        } catch (Throwable $e) {
            error_log('Erro ao gravar criança: ' . $e->getMessage());
            $erros[] = 'Não foi possível gravar a criança. Tente novamente.';
        }
    }
}

$encarregados = listar_encarregados($pdo);
$tituloPagina = $id > 0 ? 'Editar criança' : 'Cadastrar criança';
$menuAtual = 'criancas.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho">
    <div>
        <h1><?php echo e($tituloPagina); ?></h1>
        <p>Os campos marcados são obrigatórios. O número de processo é único e gerado pelo sistema.</p>
    </div>
    <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'criancas.php'); ?>">Voltar</a>
</section>

<?php if ($erros): ?>
    <div class="mensagem mensagem-erro" role="alert">
        <strong>Corrija os seguintes campos:</strong>
        <ul><?php foreach ($erros as $erro): ?><li><?php echo e($erro); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($duplicados): ?>
    <div class="mensagem mensagem-aviso" role="alert">
        <strong>Possível duplicado.</strong> Existe uma criança ativa com o mesmo nome e data de nascimento.
        <div class="lista-acoes">
            <?php foreach ($duplicados as $duplicado): ?>
                <a href="<?php echo e(url_publica() . 'crianca_detalhe.php?id=' . $duplicado['id']); ?>">Abrir <?php echo e($duplicado['numero_processo']); ?> — <?php echo e($duplicado['nome']); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<form method="POST" action="<?php echo e(url_publica() . 'crianca_form.php'); ?>">
    <?php echo csrf_field(); ?>
    <?php if ($id > 0): ?><input type="hidden" name="id" value="<?php echo e($id); ?>"><?php endif; ?>
    <?php if ($duplicados): ?><input type="hidden" name="confirmar_duplicado" value="1"><?php endif; ?>

    <section class="cartao">
        <div class="grelha-formulario">
            <div class="campo">
                <label for="numero_processo">Número de processo</label>
                <input type="text" id="numero_processo" value="<?php echo e($dados['numero_processo'] ?: 'Gerado automaticamente'); ?>" readonly>
                <span class="ajuda">Prefixo e sequência definidos nas configurações.</span>
            </div>
            <div class="campo">
                <label for="numero_cartao">Número do cartão</label>
                <input type="text" id="numero_cartao" name="numero_cartao" value="<?php echo e($dados['numero_cartao']); ?>" maxlength="50">
            </div>
            <div class="campo campo-amplo">
                <label for="nome">Nome completo *</label>
                <input type="text" id="nome" name="nome" value="<?php echo e($dados['nome']); ?>" maxlength="100" required autofocus>
            </div>
            <div class="campo">
                <label for="data_nascimento">Data de nascimento *</label>
                <input type="date" id="data_nascimento" name="data_nascimento" value="<?php echo e($dados['data_nascimento']); ?>" max="<?php echo e(date('Y-m-d')); ?>" required>
            </div>
            <div class="campo-check">
                <input type="checkbox" id="data_nascimento_aproximada" name="data_nascimento_aproximada" value="1"<?php echo !empty($dados['data_nascimento_aproximada']) ? ' checked' : ''; ?>>
                <label for="data_nascimento_aproximada">Data de nascimento aproximada</label>
            </div>
            <div class="campo">
                <label for="sexo">Sexo *</label>
                <select id="sexo" name="sexo" required>
                    <option value="">Selecione</option>
                    <option value="M"<?php echo $dados['sexo'] === 'M' ? ' selected' : ''; ?>>Masculino</option>
                    <option value="F"<?php echo $dados['sexo'] === 'F' ? ' selected' : ''; ?>>Feminino</option>
                </select>
            </div>
            <div class="campo">
                <label for="encarregado_id">Encarregado *</label>
                <select id="encarregado_id" name="encarregado_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($encarregados as $encarregado): ?>
                        <option value="<?php echo e($encarregado['id']); ?>"<?php echo (int) $dados['encarregado_id'] === (int) $encarregado['id'] ? ' selected' : ''; ?>>
                            <?php echo e($encarregado['nome'] . ' — ' . $encarregado['telefone']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="ajuda"><a href="<?php echo e(url_publica() . 'encarregado_form.php'); ?>">Cadastrar um novo encarregado</a></span>
            </div>
            <div class="campo campo-amplo">
                <label for="observacoes">Observações gerais</label>
                <textarea id="observacoes" name="observacoes" maxlength="2000"><?php echo e($dados['observacoes']); ?></textarea>
                <span class="ajuda">Não registar dados clínicos desnecessários.</span>
            </div>
        </div>
        <div class="formulario-acoes">
            <button class="botao" type="submit"><?php echo $duplicados ? 'Continuar mesmo assim' : ($id > 0 ? 'Guardar alterações' : 'Cadastrar criança'); ?></button>
            <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'criancas.php'); ?>">Cancelar</a>
        </div>
    </section>
</form>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
