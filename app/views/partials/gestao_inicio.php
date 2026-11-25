<?php
/**
 * Layout exclusivo da gestão de funcionários.
 */

$tituloGestao = $tituloGestao ?? 'Funcionários';
$menuGestao = $menuGestao ?? '';
$baseCabecalho = url_publica();
$tituloDocumento = $tituloGestao . ' — Vacinação infantil';
require __DIR__ . '/head.php';
?>
<a class="saltar" href="#conteudo">Saltar para o conteúdo</a>
<div class="aplicacao" data-menu="fechado">
    <aside class="barra-lateral" id="barra" aria-label="Menu da gestão">
        <div class="marca">
            <div class="marca-selo" aria-hidden="true"><?php echo icone('shield-check'); ?></div>
            <div>
                <div class="marca-nome">Área restrita</div>
                <div class="marca-posto"><?php echo e(nome_posto()); ?></div>
            </div>
        </div>
        <nav aria-label="Secções da gestão">
            <ul class="menu">
                <li><a href="<?php echo e(url_gestao() . 'funcionarios.php'); ?>"<?php echo $menuGestao === 'funcionarios.php' ? ' aria-current="page"' : ''; ?>>
                    <?php echo icone('users'); ?>Funcionários
                </a></li>
            </ul>
        </nav>
    </aside>
    <div class="coluna-principal">
        <header class="topo">
            <button type="button" class="botao botao-secundario botao-icone botao-menu" aria-label="Abrir menu" aria-controls="barra" aria-expanded="false">
                <?php echo icone('menu'); ?>
            </button>
            <div class="topo-titulo"><?php echo e($tituloGestao); ?></div>
            <div class="indicador-sync" data-estado="ok" role="status"><?php echo icone('lock'); ?><span>Sessão de gestão</span></div>
            <div class="topo-utilizador">
                <div><?php echo e($_SESSION['utilizador_nome'] ?? ''); ?><small>Administrador</small></div>
                <button type="button" class="botao botao-secundario botao-icone" data-acao="sair" aria-label="Terminar sessão da gestão" title="Terminar sessão da gestão">
                    <?php echo icone('log-out'); ?>
                </button>
            </div>
        </header>
        <main class="conteudo" id="conteudo" tabindex="-1">
            <?php foreach (mensagens_modulo() as $mensagem): ?>
                <div class="mensagem mensagem-<?php echo e($mensagem['tipo']); ?>" role="alert"><?php echo e($mensagem['texto']); ?></div>
            <?php endforeach; ?>
