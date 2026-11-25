<?php
/**
 * 2.ª via do cartão apenas com vacinações válidas.
 */

require_once __DIR__ . '/../app/core/criancas.php';
require_once __DIR__ . '/../app/core/vacinacao.php';
ini_set('display_errors', config('display_errors', false) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('recepcao', 'enfermeiro', 'administrador');

$pdo = conexao_app();
$id = isset($_GET['id']) && is_scalar($_GET['id']) ? (int) $_GET['id'] : 0;
$crianca = $id > 0 ? obter_crianca($pdo, $id) : false;
if (!$crianca || (int) $crianca['ativo'] !== 1 || $crianca['eliminado_em'] !== null) {
    http_response_code(404);
    exit('Criança não encontrada.');
}

$historicoValido = [];
foreach (listar_historico_crianca($pdo, $id) as $registo) {
    if ($registo['anulacao_id'] === null) {
        $historicoValido[] = $registo;
    }
}
$podeImprimir = tem_perfil('enfermeiro', 'administrador');
$scriptPagina = 'cartao.js';
$tituloPagina = '2.ª via do cartão';
$menuAtual = 'criancas.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho">
    <div>
        <h1>2.ª via do cartão</h1>
        <p>Documento de consulta com o histórico de vacinações válidas da criança.</p>
    </div>
    <div class="modulo-acoes">
        <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'crianca_detalhe.php?id=' . $crianca['id']); ?>">Voltar à ficha</a>
        <?php if ($podeImprimir): ?>
            <button class="botao" type="button" data-acao="imprimir-cartao">Imprimir</button>
        <?php endif; ?>
    </div>
</section>

<?php if (!$podeImprimir): ?>
    <div class="mensagem mensagem-aviso" role="alert">Consulta apenas. A impressão da 2.ª via é feita pela enfermagem ou administração.</div>
<?php endif; ?>

<article class="cartao cartao-documento" aria-labelledby="titulo-cartao">
    <header class="cartao-documento-cabecalho">
        <div>
            <p class="cartao-documento-posto"><?php echo e(nome_posto()); ?></p>
            <h2 id="titulo-cartao">Cartão de Vacinação Infantil</h2>
            <p>2.ª via · Emitido em <?php echo e(date('d/m/Y')); ?></p>
        </div>
        <div class="cartao-documento-selo" aria-hidden="true"><?php echo icone('syringe'); ?></div>
    </header>

    <section class="cartao-documento-dados" aria-label="Dados da criança e do encarregado">
        <div>
            <h3>Criança</h3>
            <dl class="dados-lista">
                <dt>Nome</dt><dd><?php echo e($crianca['nome']); ?></dd>
                <dt>Processo</dt><dd><?php echo e($crianca['numero_processo']); ?></dd>
                <dt>Cartão</dt><dd><?php echo e($crianca['numero_cartao'] ?: '—'); ?></dd>
                <dt>Nascimento</dt><dd><?php echo e(data_pt_simples($crianca['data_nascimento'])); ?><?php if ($crianca['data_nascimento_aproximada']): ?> (aproximada)<?php endif; ?></dd>
                <dt>Sexo</dt><dd><?php echo $crianca['sexo'] === 'F' ? 'Feminino' : 'Masculino'; ?></dd>
            </dl>
        </div>
        <div>
            <h3>Encarregado</h3>
            <dl class="dados-lista">
                <dt>Nome</dt><dd><?php echo e($crianca['encarregado_nome']); ?></dd>
                <dt>Parentesco</dt><dd><?php echo e($crianca['parentesco']); ?></dd>
                <dt>Telefone</dt><dd><?php echo e($crianca['telefone']); ?></dd>
                <dt>Telefone alternativo</dt><dd><?php echo e($crianca['telefone_alt'] ?: '—'); ?></dd>
                <dt>Bairro</dt><dd><?php echo e($crianca['bairro_nome'] ?: '—'); ?></dd>
            </dl>
        </div>
    </section>

    <section class="cartao-documento-historico" aria-label="Histórico de vacinações válidas">
        <h3>Vacinações válidas</h3>
        <?php if (!$historicoValido): ?>
            <p class="vazio">Ainda não há vacinações válidas registadas.</p>
        <?php else: ?>
            <div class="tabela-container"><table class="tabela">
                <caption class="sr-only">Vacinações válidas por ordem cronológica</caption>
                <thead><tr><th scope="col">Data</th><th scope="col">Vacina</th><th scope="col">Dose</th><th scope="col">Origem</th><th scope="col">Lote</th><th scope="col">Profissional</th></tr></thead>
                <tbody><?php foreach ($historicoValido as $registo): ?><tr>
                    <td><?php echo e(data_pt_simples($registo['data_administracao'])); ?></td>
                    <td><?php echo e($registo['vacina_nome']); ?></td>
                    <td><?php echo e($registo['numero_dose']); ?></td>
                    <td><?php echo $registo['origem'] === 'externa' ? 'Externa' : 'Posto'; ?></td>
                    <td><?php echo e($registo['origem'] === 'externa' ? 'Não aplicável' : ($registo['numero_lote'] ?: '—')); ?></td>
                    <td><?php echo e($registo['origem'] === 'externa' ? ($registo['administrado_por_nome'] ?: 'Não identificado') : ($registo['administrado_por'] ?: 'Não identificado')); ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <footer class="cartao-documento-rodape">
        <p>Este documento inclui apenas vacinações válidas. Vacinações anuladas não constam do histórico.</p>
        <p>Gerado pelo sistema em <?php echo e(date('d/m/Y H:i')); ?>.</p>
    </footer>
</article>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
