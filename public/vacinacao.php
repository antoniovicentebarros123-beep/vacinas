<?php
/**
 * Área de vacinação: atendimentos em curso e acesso a doses externas.
 */

require_once __DIR__ . '/../app/core/vacinacao.php';
require_once __DIR__ . '/../app/core/criancas.php';
require_once __DIR__ . '/../app/core/stock.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('enfermeiro', 'administrador');
$pdo = conexao_app();
$atendimentos = listar_atendimentos_vacinacao($pdo);
$termo = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$pesquisa = $termo !== '' && tem_perfil('enfermeiro') ? pesquisar_criancas($pdo, $termo, 1, 10) : ['registos' => []];
$tituloPagina = 'Vacinação';
$menuAtual = 'vacinacao.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho">
    <div><h1>Vacinação</h1><p>Registe as doses em atendimentos em curso. O lote sugerido é o que expira primeiro entre os válidos e com saldo.</p></div>
</section>

<div class="aviso-clinico" role="note">Os valores clínicos do calendário continuam sujeitos a validação pela equipa clínica. A aplicação não inventa intervalos ou idades mínimas.</div>

<section class="cartao">
    <h2>Atendimentos em curso</h2>
    <?php if (!$atendimentos): ?>
        <p class="vazio">Não há atendimentos em curso.</p>
    <?php else: ?>
        <div class="tabela-container"><table class="tabela">
            <caption class="sr-only">Atendimentos que podem receber vacinação</caption>
            <thead><tr><th scope="col">Criança</th><th scope="col">Processo</th><th scope="col">Início</th><th scope="col" class="numero">Doses pendentes</th><th scope="col" class="acoes">Ação</th></tr></thead>
            <tbody><?php foreach ($atendimentos as $atendimento): ?><tr>
                <td><?php echo e($atendimento['nome']); ?></td><td><?php echo e($atendimento['numero_processo']); ?></td>
                <td><?php echo e(data_hora_pt_simples($atendimento['iniciado_em'])); ?></td><td class="numero"><?php echo e($atendimento['vacinas_pendentes']); ?></td>
                <td class="acoes"><a class="ligacao-acao" href="<?php echo e(url_publica() . (tem_perfil('enfermeiro') ? 'vacinacao_form.php?atendimento_id=' : 'atendimento_detalhe.php?id=') . $atendimento['id']); ?>"><?php echo tem_perfil('enfermeiro') ? 'Registar dose' : 'Consultar'; ?></a></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php if (tem_perfil('enfermeiro')): ?>
<section class="cartao">
    <h2>Dose transcrita do cartão</h2>
    <p>Para vacinações feitas fora do posto. Não exigem atendimento, não usam lote e não alteram o stock.</p>
    <form method="GET" action="<?php echo e(url_publica() . 'vacinacao.php'); ?>" class="barra-pesquisa">
        <div class="campo"><label for="q">Pesquisar criança</label><input type="search" id="q" name="q" value="<?php echo e($termo); ?>" maxlength="100"></div>
        <button class="botao botao-secundario" type="submit">Pesquisar</button>
    </form>
    <?php if ($termo !== ''): ?>
        <?php if (!$pesquisa['registos']): ?><p class="vazio">Nenhuma criança encontrada.</p><?php else: ?>
            <ul><?php foreach ($pesquisa['registos'] as $crianca): ?><li><a class="ligacao-acao" href="<?php echo e(url_publica() . 'vacinacao_externa_form.php?crianca_id=' . $crianca['id']); ?>">Registar dose externa de <?php echo e($crianca['nome']); ?> — <?php echo e($crianca['numero_processo']); ?></a></li><?php endforeach; ?></ul>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
