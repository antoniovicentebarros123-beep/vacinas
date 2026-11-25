<?php
require_once __DIR__ . '/../app/core/criancas.php';
ini_set('display_errors', config('display_errors', false) ? '1' : '0');
iniciar_sessao_segura();
requer_login();
requer_perfil('recepcao', 'enfermeiro', 'administrador');

$pdo = conexao_app();
$termo = (string) ($_GET['q'] ?? '');
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$resultado = pesquisar_criancas($pdo, $termo, $pagina);
$tituloPagina = 'Crianças';
$menuAtual = 'criancas.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho">
    <div>
        <h1>Crianças e encarregados</h1>
        <p>Pesquise pelo nome, número de processo, número do cartão ou telefone do encarregado.</p>
    </div>
    <div class="modulo-acoes">
        <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'encarregados.php'); ?>">Encarregados</a>
        <?php if (tem_perfil('recepcao')): ?>
            <a class="botao" href="<?php echo e(url_publica() . 'crianca_form.php'); ?>"><?php echo icone('baby'); ?>Cadastrar criança</a>
        <?php endif; ?>
    </div>
</section>

<section class="cartao" aria-label="Pesquisa de crianças">
    <form method="GET" class="barra-pesquisa">
        <div class="campo">
            <label for="q">Pesquisar</label>
            <input type="search" id="q" name="q" value="<?php echo e($termo); ?>" maxlength="100" placeholder="Nome, processo, cartão ou telefone">
        </div>
        <button class="botao" type="submit">Pesquisar</button>
        <?php if ($termo !== ''): ?>
            <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'criancas.php'); ?>">Limpar</a>
        <?php endif; ?>
    </form>
</section>

<section class="cartao">
    <div class="paginacao">
        <div><strong><?php echo e($resultado['total']); ?></strong> criança(s) encontrada(s)</div>
        <?php if (tem_perfil('recepcao')): ?>
            <a class="ligacao-acao" href="<?php echo e(url_publica() . 'encarregado_form.php'); ?>">Cadastrar encarregado</a>
        <?php endif; ?>
    </div>
    <hr class="separador">
    <?php if (!$resultado['registos']): ?>
        <div class="vazio">Nenhuma criança encontrada com os critérios indicados.</div>
    <?php else: ?>
        <div class="tabela-container">
            <table class="tabela">
                <thead><tr><th>Nome</th><th>Processo</th><th>Cartão</th><th>Nascimento</th><th>Encarregado</th><th>Telefone</th><th class="acoes">Ações</th></tr></thead>
                <tbody>
                <?php foreach ($resultado['registos'] as $crianca): ?>
                    <tr>
                        <td><a href="<?php echo e(url_publica() . 'crianca_detalhe.php?id=' . $crianca['id']); ?>"><?php echo e($crianca['nome']); ?></a></td>
                        <td><?php echo e($crianca['numero_processo']); ?></td>
                        <td><?php echo e($crianca['numero_cartao'] ?: '—'); ?></td>
                        <td><?php echo e(data_pt_simples($crianca['data_nascimento'])); ?><?php if ($crianca['data_nascimento_aproximada']): ?> <span class="etiqueta etiqueta-aviso">aproximada</span><?php endif; ?></td>
                        <td><?php echo e($crianca['encarregado_nome']); ?></td>
                        <td><?php echo e($crianca['telefone']); ?></td>
                        <td class="acoes">
                            <a class="botao botao-secundario botao-icone" href="<?php echo e(url_publica() . 'crianca_detalhe.php?id=' . $crianca['id']); ?>" aria-label="Abrir ficha de <?php echo e($crianca['nome']); ?>"><?php echo icone('eye'); ?></a>
                            <?php if (tem_perfil('recepcao')): ?>
                                <a class="botao botao-secundario botao-icone" href="<?php echo e(url_publica() . 'crianca_form.php?id=' . $crianca['id']); ?>" aria-label="Editar <?php echo e($crianca['nome']); ?>"><?php echo icone('settings'); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($resultado['paginas'] > 1): ?>
        <nav class="paginacao" aria-label="Paginação">
            <span class="paginacao-info">Página <?php echo e($resultado['pagina']); ?> de <?php echo e($resultado['paginas']); ?></span>
            <div class="modulo-acoes">
                <?php if ($resultado['pagina'] > 1): ?>
                    <a class="botao botao-secundario" href="?<?php echo e(http_build_query(['q' => $termo, 'pagina' => $resultado['pagina'] - 1])); ?>">Anterior</a>
                <?php endif; ?>
                <?php if ($resultado['pagina'] < $resultado['paginas']): ?>
                    <a class="botao botao-secundario" href="?<?php echo e(http_build_query(['q' => $termo, 'pagina' => $resultado['pagina'] + 1])); ?>">Seguinte</a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
