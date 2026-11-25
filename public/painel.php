<?php
/**
 * Página do painel principal com autenticação real
 */

// Iniciar sessão ANTES de qualquer coisa
require __DIR__ . '/../app/core/helpers.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();

// Exigir login
requer_login();

// Obter dados da sessão
$usuarioId = $_SESSION['utilizador_id'];
$usuarioNome = (string) ($_SESSION['utilizador_nome'] ?? '');
$usuarioPerfil = (string) ($_SESSION['utilizador_perfil'] ?? '');
$loginTime = (int) ($_SESSION['ultimo_acesso'] ?? time());
if ($loginTime <= 0) {
    $loginTime = time();
}

// Contagens reais apresentadas no resumo e no quadro de hoje.
$totalCriancas = 0;
$totalVacinacoes = 0;
$aguardando = 0;
$emAtendimento = 0;
$concluidosHoje = 0;

// Obter dados do utilizador da base de dados
try {
    $config = config();
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
    $stmt = $pdo->prepare("SELECT nome, perfil FROM utilizadores WHERE id = ? LIMIT 1");
    $stmt->execute([$usuarioId]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        $usuarioNome = (string) $usuario['nome'];
        $usuarioPerfil = (string) $usuario['perfil'];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total FROM criancas WHERE ativo = 1 AND eliminado_em IS NULL'
    );
    $stmt->execute();
    $totalCriancas = (int) $stmt->fetch()['total'];

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total FROM vacinacoes vc
         INNER JOIN criancas c ON c.id = vc.crianca_id
         WHERE c.ativo = 1 AND c.eliminado_em IS NULL'
    );
    $stmt->execute();
    $totalVacinacoes = (int) $stmt->fetch()['total'];

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(a.estado = 'aguardando'), 0) AS aguardando,
                COALESCE(SUM(a.estado = 'em_atendimento'), 0) AS em_atendimento,
                COALESCE(SUM(a.estado = 'concluido' AND DATE(a.concluido_em) = CURDATE()), 0) AS concluidos_hoje
         FROM atendimentos a
         INNER JOIN criancas c ON c.id = a.crianca_id
         WHERE c.ativo = 1 AND c.eliminado_em IS NULL"
    );
    $stmt->execute();
    $resumoHoje = $stmt->fetch();
    $aguardando = (int) $resumoHoje['aguardando'];
    $emAtendimento = (int) $resumoHoje['em_atendimento'];
    $concluidosHoje = (int) $resumoHoje['concluidos_hoje'];
} catch (PDOException $e) {
    error_log("Erro ao obter dados do utilizador: " . $e->getMessage());
}

$nomePosto = nome_posto();
$hoje = date('Y-m-d');
$dataLogin = date('d/m/Y H:i:s', $loginTime);
$dataLoginIso = date(DATE_ATOM, $loginTime);
$rotulosPerfil = [
    'administrador' => 'Administrador do posto',
    'enfermeiro' => 'Enfermeiro(a)',
    'recepcao' => 'Recepção',
    'responsavel_stock' => 'Responsável de stock',
];
$perfilRotulo = $rotulosPerfil[$usuarioPerfil] ?? $usuarioPerfil;

require __DIR__ . '/../app/views/partials/head.php';
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
                <li>
                    <a href="<?php echo e(url_base() . 'painel.php'); ?>" aria-current="page">
                        <?php echo icone('layout-dashboard'); ?>
                        Painel
                    </a>
                </li>
                <li><?php if (in_array($usuarioPerfil, ['recepcao', 'enfermeiro', 'administrador'], true)): ?><a href="<?php echo e(url_publica() . 'criancas.php'); ?>"><?php echo icone('baby'); ?>Crianças</a><?php else: ?><span class="item-menu menu-sem-acesso" aria-disabled="true"><?php echo icone('baby'); ?>Crianças<small>Sem acesso</small></span><?php endif; ?></li>
                <li><?php if (in_array($usuarioPerfil, ['recepcao', 'enfermeiro', 'administrador'], true)): ?><a href="<?php echo e(url_publica() . 'atendimento.php'); ?>"><?php echo icone('calendar-days'); ?>Atendimento</a><?php else: ?><span class="item-menu menu-sem-acesso" aria-disabled="true"><?php echo icone('calendar-days'); ?>Atendimento<small>Sem acesso</small></span><?php endif; ?></li>
                <li><?php if (in_array($usuarioPerfil, ['enfermeiro', 'administrador'], true)): ?><a href="<?php echo e(url_publica() . 'vacinacao.php'); ?>"><?php echo icone('syringe'); ?>Vacinação</a><?php else: ?><span class="item-menu menu-sem-acesso" aria-disabled="true"><?php echo icone('syringe'); ?>Vacinação<small>Sem acesso</small></span><?php endif; ?></li>
                <li><?php if (in_array($usuarioPerfil, ['recepcao', 'enfermeiro', 'administrador'], true)): ?><a href="<?php echo e(url_publica() . 'faltosos.php'); ?>"><?php echo icone('phone-call'); ?>Faltosos</a><?php else: ?><span class="item-menu menu-sem-acesso" aria-disabled="true"><?php echo icone('phone-call'); ?>Faltosos<small>Sem acesso</small></span><?php endif; ?></li>
                <li><?php if (in_array($usuarioPerfil, ['enfermeiro', 'responsavel_stock', 'administrador'], true)): ?><a href="<?php echo e(url_publica() . 'stock.php'); ?>"><?php echo icone('package'); ?>Stock</a><?php else: ?><span class="item-menu menu-sem-acesso" aria-disabled="true"><?php echo icone('package'); ?>Stock<small>Sem acesso</small></span><?php endif; ?></li>
                <li><?php if (in_array($usuarioPerfil, ['enfermeiro', 'responsavel_stock', 'administrador'], true)): ?><a href="<?php echo e(url_publica() . 'relatorios.php'); ?>"><?php echo icone('chart-column'); ?>Relatórios</a><?php else: ?><span class="item-menu menu-sem-acesso" aria-disabled="true"><?php echo icone('chart-column'); ?>Relatórios<small>Sem acesso</small></span><?php endif; ?></li>
            </ul>
        </nav>
    </aside>

    <div class="coluna-principal">
        <header class="topo">
            <button type="button" class="botao botao-secundario botao-icone botao-menu" aria-label="Abrir menu" aria-controls="barra" aria-expanded="false">
                <?php echo icone('menu'); ?>
            </button>
            <div class="topo-titulo">Painel</div>
            <div class="indicador-sync" data-estado="ok" role="status" aria-live="polite">
                <?php echo icone('circle-check'); ?>
                <span>Servidor local</span>
            </div>
            <div class="topo-utilizador">
                <div>
                    <?php echo e($usuarioNome); ?>
                    <small><?php echo e($perfilRotulo); ?></small>
                </div>
                <button type="button" class="botao botao-secundario botao-icone" data-acao="sair" aria-label="Terminar sessão" title="Terminar sessão">
                    <?php echo icone('log-out'); ?>
                </button>
            </div>
        </header>

        <main class="conteudo" id="conteudo" tabindex="-1">
            <section class="painel-saudacao">
                <h1><?php echo e(saudacao($usuarioNome)); ?></h1>
                <p><?php echo e(formatar_data_pt($hoje)); ?></p>
            </section>

            <section class="faixa-hoje" aria-labelledby="titulo-hoje">
                <h2 id="titulo-hoje">Hoje no posto</h2>
                <div class="indicador-grande">
                    <strong><?php echo e($aguardando); ?></strong>
                    <span>Aguardando</span>
                    <em>Na fila atual</em>
                </div>
                <div class="indicador-grande">
                    <strong><?php echo e($emAtendimento); ?></strong>
                    <span>Em atendimento</span>
                    <em>Atendimentos em curso</em>
                </div>
                <div class="indicador-grande">
                    <strong><?php echo e($concluidosHoje); ?></strong>
                    <span>Concluídos</span>
                    <em>Concluídos hoje</em>
                </div>
            </section>

            <section class="grelha-resumo" aria-label="Resumo">
                <article class="cartao resumo">
                    <div class="resumo-icone"><?php echo icone('users'); ?></div>
                    <div>
                        <strong><?php echo e($totalCriancas); ?></strong>
                        <p>Crianças registadas</p>
                    </div>
                </article>
                <article class="cartao resumo">
                    <div class="resumo-icone"><?php echo icone('syringe'); ?></div>
                    <div>
                        <strong><?php echo e($totalVacinacoes); ?></strong>
                        <p>Vacinações registadas</p>
                    </div>
                </article>
                <article class="cartao resumo">
                    <div class="resumo-icone"><?php echo icone('shield-check'); ?></div>
                    <div>
                        <strong>Operacional</strong>
                        <p>Estado do sistema</p>
                    </div>
                </article>
            </section>

            <section class="cartao painel-aviso-fase">
                <h2>Módulos operacionais</h2>
                <p>Autenticação, crianças, atendimento, vacinação, faltosos, stock e relatórios estão disponíveis. Os valores clínicos do calendário continuam sujeitos a validação pela equipa clínica.</p>
                <p><small>Login em: <time datetime="<?php echo e($dataLoginIso); ?>" data-login-date><?php echo e($dataLogin); ?></time></small></p>
            </section>
        </main>
    </div>
</div>

<noscript><p class="mensagem mensagem-erro">Este sistema precisa de JavaScript. Ative-o no navegador.</p></noscript>

<form method="POST" action="<?php echo e(url_base() . 'logout.php'); ?>" id="logoutForm" name="logoutForm" hidden>
    <?php echo csrf_field(); ?>
</form>

<script src="<?php echo e(url_base() . 'assets/js/painel.js'); ?>"></script>

<?php require __DIR__ . '/../app/views/partials/foot.php'; ?>
