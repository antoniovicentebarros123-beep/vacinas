<?php
/**
 * Início comum das páginas autenticadas dos módulos.
 */

$tituloPagina = $tituloPagina ?? 'Módulo';
$menuAtual = $menuAtual ?? '';
$usuarioNome = (string) ($_SESSION['utilizador_nome'] ?? '');
$usuarioPerfil = perfil_atual();
$nomePosto = nome_posto();

require __DIR__ . '/head.php';
?>

<a class="saltar" href="#conteudo">Saltar para o conteúdo</a>

<div class="aplicacao" data-menu="fechado">
    <aside class="barra-lateral" id="barra" aria-label="Menu principal">
        <div class="marca">
            <div class="marca-selo" aria-hidden="true"><?php echo icone('syringe'); ?></div>
            <div>
                <div class="marca-nome">Vacinação infantil</div>
                <div class="marca-posto"><?php echo e($nomePosto); ?></div>
            </div>
        </div>

        <nav aria-label="Secções">
            <ul class="menu">
                <li><a href="<?php echo e(url_publica() . 'painel.php'); ?>"<?php echo $menuAtual === 'painel.php' ? ' aria-current="page"' : ''; ?>>
                    <?php echo icone('layout-dashboard'); ?>Painel
                </a></li>
                <li><?php echo item_menu_modulo('criancas.php', 'Crianças', 'baby', $menuAtual, ['recepcao', 'enfermeiro', 'administrador']); ?></li>
                <li><?php echo item_menu_modulo('atendimento.php', 'Atendimento', 'calendar-days', $menuAtual, ['recepcao', 'enfermeiro', 'administrador']); ?></li>
                <li><?php echo item_menu_modulo('vacinacao.php', 'Vacinação', 'syringe', $menuAtual, ['enfermeiro', 'administrador']); ?></li>
                <li><?php echo item_menu_modulo('faltosos.php', 'Faltosos', 'phone-call', $menuAtual, ['recepcao', 'enfermeiro', 'administrador']); ?></li>
                <li><?php echo item_menu_modulo('stock.php', 'Stock', 'package', $menuAtual, ['enfermeiro', 'responsavel_stock', 'administrador']); ?></li>
                <li><?php echo item_menu_modulo('relatorios.php', 'Relatórios', 'chart-column', $menuAtual, ['enfermeiro', 'responsavel_stock', 'administrador']); ?></li>
            </ul>
        </nav>
    </aside>

    <div class="coluna-principal">
        <header class="topo">
            <button type="button" class="botao botao-secundario botao-icone botao-menu" aria-label="Abrir menu" aria-controls="barra" aria-expanded="false">
                <?php echo icone('menu'); ?>
            </button>
            <div class="topo-titulo"><?php echo e($tituloPagina); ?></div>
            <div class="indicador-sync" data-estado="ok" role="status" aria-live="polite">
                <?php echo icone('circle-check'); ?><span>Servidor local</span>
            </div>
            <div class="topo-utilizador">
                <div><?php echo e($usuarioNome); ?><small><?php echo e(rotulo_perfil($usuarioPerfil)); ?></small></div>
                <button type="button" class="botao botao-secundario botao-icone" data-acao="sair" aria-label="Terminar sessão" title="Terminar sessão">
                    <?php echo icone('log-out'); ?>
                </button>
            </div>
        </header>

        <main class="conteudo" id="conteudo" tabindex="-1">
            <?php foreach (mensagens_modulo() as $mensagem): ?>
                <div class="mensagem mensagem-<?php echo e($mensagem['tipo']); ?>" role="alert"><?php echo e($mensagem['texto']); ?></div>
            <?php endforeach; ?>
