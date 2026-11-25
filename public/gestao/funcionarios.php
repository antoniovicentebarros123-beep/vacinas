<?php
/**
 * Lista de funcionários e operações de conta.
 */

require __DIR__ . '/inicio.php';
$pdo = requer_gestao();
$termo = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$pagina = isset($_GET['pagina']) && is_scalar($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$lista = listar_funcionarios($pdo, $termo, $pagina);
$temporaria = $_SESSION['senha_temporaria_gestao'] ?? null;
unset($_SESSION['senha_temporaria_gestao']);

$tituloGestao = 'Funcionários';
$menuGestao = 'funcionarios.php';
require __DIR__ . '/../../app/views/partials/gestao_inicio.php';
?>
<section class="modulo-cabecalho">
    <div>
        <h1>Funcionários</h1>
        <p>Crie contas, atribua perfis e controle o acesso. As contas nunca são apagadas.</p>
    </div>
    <div class="modulo-acoes">
        <a class="botao botao-primario" href="<?php echo e(url_gestao() . 'funcionario_form.php'); ?>"><?php echo icone('user'); ?>Novo funcionário</a>
    </div>
</section>

<?php if (is_array($temporaria) && !empty($temporaria['valor'])): ?>
<section class="temporaria" role="status">
    <p><strong>Palavra-passe temporária</strong></p>
    <p>Esta é a única vez que aparece. Entregue-a ao funcionário por um canal seguro.</p>
    <p><code><?php echo e($temporaria['valor']); ?></code></p>
</section>
<?php endif; ?>

<section class="cartao">
    <form class="barra-pesquisa" method="get" action="<?php echo e(url_gestao() . 'funcionarios.php'); ?>">
        <div class="campo">
            <label for="q">Pesquisar funcionário</label>
            <input type="search" id="q" name="q" value="<?php echo e($termo); ?>" placeholder="Nome, utilizador ou perfil">
        </div>
        <button type="submit" class="botao botao-secundario">Pesquisar</button>
        <?php if ($termo !== ''): ?><a class="botao botao-texto" href="<?php echo e(url_gestao() . 'funcionarios.php'); ?>">Limpar</a><?php endif; ?>
    </form>
</section>

<section class="cartao">
    <?php if (!$lista['itens']): ?>
        <p class="vazio">Nenhum funcionário encontrado.</p>
    <?php else: ?>
        <div class="tabela-container">
            <table class="tabela">
                <caption class="so-leitor">Funcionários registados</caption>
                <thead><tr><th scope="col">Nome</th><th scope="col">Utilizador</th><th scope="col">Perfil</th><th scope="col">Estado</th><th scope="col">Último acesso</th><th scope="col" class="acoes">Ações</th></tr></thead>
                <tbody>
                <?php foreach ($lista['itens'] as $funcionario): ?>
                    <tr>
                        <td><?php echo e($funcionario['nome']); ?></td>
                        <td><?php echo e($funcionario['utilizador']); ?></td>
                        <td><?php echo e(rotulo_perfil($funcionario['perfil'])); ?></td>
                        <td><span class="etiqueta <?php echo (int) $funcionario['ativo'] === 1 ? 'etiqueta-sucesso' : 'etiqueta-neutra'; ?>"><?php echo (int) $funcionario['ativo'] === 1 ? 'Ativo' : 'Inativo'; ?></span></td>
                        <td><?php echo e(data_hora_pt_simples($funcionario['ultimo_login'])); ?></td>
                        <td class="acoes">
                            <a class="botao botao-secundario botao-pequeno" href="<?php echo e(url_gestao() . 'funcionario_form.php?id=' . (int) $funcionario['id']); ?>">Editar</a>
                            <form method="post" action="<?php echo e(url_gestao() . 'funcionario_estado.php'); ?>" data-confirm="Confirma a alteração do estado desta conta?" class="formulario-inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int) $funcionario['id']; ?>">
                                <input type="hidden" name="ativo" value="<?php echo (int) $funcionario['ativo'] === 1 ? 0 : 1; ?>">
                                <button type="submit" class="botao <?php echo (int) $funcionario['ativo'] === 1 ? 'botao-perigo' : 'botao-primario'; ?> botao-pequeno"><?php echo (int) $funcionario['ativo'] === 1 ? 'Desativar' : 'Reativar'; ?></button>
                            </form>
                            <?php if ((int) $funcionario['ativo'] === 1): ?>
                            <form method="post" action="<?php echo e(url_gestao() . 'funcionario_password.php'); ?>" data-confirm="Repor a palavra-passe desta conta? A anterior deixará de funcionar." class="formulario-inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo (int) $funcionario['id']; ?>">
                                <button type="submit" class="botao botao-secundario botao-pequeno">Repor palavra-passe</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="paginacao">
            <span class="paginacao-info">Página <?php echo (int) $lista['pagina']; ?> de <?php echo (int) $lista['paginas']; ?> · <?php echo (int) $lista['total']; ?> conta(s)</span>
            <div class="modulo-acoes">
                <?php if ($lista['pagina'] > 1): ?><a class="botao botao-secundario botao-pequeno" href="<?php echo e(url_gestao() . 'funcionarios.php?q=' . urlencode($termo) . '&pagina=' . ($lista['pagina'] - 1)); ?>">Anterior</a><?php endif; ?>
                <?php if ($lista['pagina'] < $lista['paginas']): ?><a class="botao botao-secundario botao-pequeno" href="<?php echo e(url_gestao() . 'funcionarios.php?q=' . urlencode($termo) . '&pagina=' . ($lista['pagina'] + 1)); ?>">Seguinte</a><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../app/views/partials/gestao_fim.php'; ?>
