<?php
require_once __DIR__ . '/../app/core/criancas.php';
ini_set('display_errors', config('display_errors', false) ? '1' : '0');
iniciar_sessao_segura();
requer_login();
requer_perfil('recepcao', 'enfermeiro', 'administrador');

$pdo = conexao_app();
$termo = (string) ($_GET['q'] ?? '');
$encarregados = listar_encarregados($pdo, $termo);
$tituloPagina = 'Encarregados';
$menuAtual = 'criancas.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho">
    <div>
        <h1>Encarregados de educação</h1>
        <p>Contactos e moradas associados às crianças. Os telefones são guardados apenas com dígitos.</p>
    </div>
    <div class="modulo-acoes">
        <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'criancas.php'); ?>">Crianças</a>
        <?php if (tem_perfil('recepcao')): ?>
            <a class="botao" href="<?php echo e(url_publica() . 'encarregado_form.php'); ?>">Cadastrar encarregado</a>
        <?php endif; ?>
    </div>
</section>

<section class="cartao">
    <form method="GET" class="barra-pesquisa">
        <div class="campo">
            <label for="q">Pesquisar por nome ou telefone</label>
            <input type="search" id="q" name="q" value="<?php echo e($termo); ?>" maxlength="100">
        </div>
        <button class="botao" type="submit">Pesquisar</button>
        <?php if ($termo !== ''): ?><a class="botao botao-secundario" href="<?php echo e(url_publica() . 'encarregados.php'); ?>">Limpar</a><?php endif; ?>
    </form>
</section>

<section class="cartao">
    <?php if (!$encarregados): ?>
        <div class="vazio">Nenhum encarregado encontrado.</div>
    <?php else: ?>
        <div class="tabela-container">
            <table class="tabela">
                <thead><tr><th>Nome</th><th>Parentesco</th><th>Telefone</th><th>Alternativo</th><th>Bairro</th><th class="acoes">Ações</th></tr></thead>
                <tbody>
                <?php foreach ($encarregados as $encarregado): ?>
                    <tr>
                        <td><?php echo e($encarregado['nome']); ?></td>
                        <td><?php echo e($encarregado['parentesco']); ?></td>
                        <td><?php echo e($encarregado['telefone']); ?></td>
                        <td><?php echo e($encarregado['telefone_alt'] ?: '—'); ?></td>
                        <td><?php echo e($encarregado['bairro_nome'] ?: '—'); ?></td>
                        <td class="acoes">
                            <?php if (tem_perfil('recepcao')): ?>
                                <a class="botao botao-secundario botao-icone" href="<?php echo e(url_publica() . 'encarregado_form.php?id=' . $encarregado['id']); ?>" aria-label="Editar <?php echo e($encarregado['nome']); ?>"><?php echo icone('settings'); ?></a>
                            <?php else: ?>
                                <span class="etiqueta etiqueta-neutra">só leitura</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
