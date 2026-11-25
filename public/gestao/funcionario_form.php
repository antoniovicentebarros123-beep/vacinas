<?php
/**
 * Cadastro e edição de funcionários.
 */

require __DIR__ . '/inicio.php';
$pdo = requer_gestao();
$id = isset($_GET['id']) && is_scalar($_GET['id']) ? (int) $_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && is_scalar($_POST['id'])) {
    $id = (int) $_POST['id'];
}
$funcionario = $id > 0 ? obter_funcionario($pdo, $id) : null;
if ($id > 0 && !$funcionario) {
    redirecionar_gestao('funcionarios.php', 'erro', 'Funcionário não encontrado.');
}
$dados = [
    'nome' => (string) ($funcionario['nome'] ?? ''),
    'utilizador' => (string) ($funcionario['utilizador'] ?? ''),
    'perfil' => (string) ($funcionario['perfil'] ?? 'recepcao'),
];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_csrf();
    [$dados, $erros] = validar_funcionario($pdo, $_POST, $id);
    if (!$erros) {
        try {
            if ($id === 0) {
                $resultado = criar_funcionario($pdo, $dados, (int) $_SESSION['utilizador_id']);
                $_SESSION['senha_temporaria_gestao'] = ['id' => $resultado['id'], 'valor' => $resultado['temporaria']];
                redirecionar_gestao('funcionarios.php', 'sucesso', 'Funcionário criado. A palavra-passe temporária aparece abaixo uma única vez.');
            }
            atualizar_funcionario($pdo, $id, $dados, (int) $_SESSION['utilizador_id']);
            redirecionar_gestao('funcionarios.php', 'sucesso', 'Dados do funcionário atualizados.');
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                $erros[] = 'Já existe um funcionário com esse nome de utilizador.';
            } else {
                error_log('Erro na gestão de funcionário: ' . $e->getMessage());
                $erros[] = 'Não foi possível guardar o funcionário. Tente novamente.';
            }
        } catch (RuntimeException $e) {
            $erros[] = $e->getMessage();
        }
    }
}

$tituloGestao = $id > 0 ? 'Editar funcionário' : 'Novo funcionário';
$menuGestao = 'funcionarios.php';
require __DIR__ . '/../../app/views/partials/gestao_inicio.php';
?>
<section class="modulo-cabecalho">
    <div><h1><?php echo e($tituloGestao); ?></h1><p>Os perfis determinam as permissões na aplicação principal.</p></div>
</section>
<section class="cartao">
    <?php foreach ($erros as $erro): ?><div class="mensagem mensagem-erro" role="alert"><?php echo e($erro); ?></div><?php endforeach; ?>
    <form method="post" action="<?php echo e(url_gestao() . 'funcionario_form.php'); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
        <div class="grelha-formulario">
            <div class="campo">
                <label for="nome">Nome completo</label>
                <input type="text" id="nome" name="nome" maxlength="100" required value="<?php echo e($dados['nome']); ?>">
            </div>
            <div class="campo">
                <label for="utilizador">Nome de utilizador</label>
                <input type="text" id="utilizador" name="utilizador" maxlength="50" required autocapitalize="none" spellcheck="false" value="<?php echo e($dados['utilizador']); ?>">
            </div>
            <div class="campo campo-amplo">
                <label for="perfil">Perfil de acesso</label>
                <select id="perfil" name="perfil" required>
                    <?php foreach (perfis_funcionario() as $perfil): ?>
                        <option value="<?php echo e($perfil); ?>"<?php echo $dados['perfil'] === $perfil ? ' selected' : ''; ?>><?php echo e(rotulo_perfil($perfil)); ?></option>
                    <?php endforeach; ?>
                </select>
                <small>O administrador gere funcionários; a recepção, enfermagem e stock têm acessos específicos.</small>
            </div>
        </div>
        <div class="formulario-acoes">
            <button type="submit" class="botao botao-primario"><?php echo $id > 0 ? 'Guardar alterações' : 'Criar funcionário'; ?></button>
            <a class="botao botao-secundario" href="<?php echo e(url_gestao() . 'funcionarios.php'); ?>">Cancelar</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/../../app/views/partials/gestao_fim.php'; ?>
