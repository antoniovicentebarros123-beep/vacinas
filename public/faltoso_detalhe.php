<?php
/**
 * Detalhe do calendário pendente e histórico de contactos de uma criança.
 */

require_once __DIR__ . '/../app/core/faltosos.php';
require_once __DIR__ . '/../app/core/criancas.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('recepcao', 'enfermeiro', 'administrador');

$pdo = conexao_app();
$id = max(0, (int) ($_GET['id'] ?? 0));
$crianca = $id > 0 ? obter_crianca($pdo, $id) : false;
if (!$crianca || (int) $crianca['ativo'] !== 1 || $crianca['eliminado_em'] !== null) {
    http_response_code(404);
    exit('Criança não encontrada.');
}
$oportunidades = listar_oportunidades_calendario($pdo);
$vacinacoes = listar_vacinacoes_validas_faltosos($pdo, $id);
$calendario = calcular_estado_calendario($crianca, $oportunidades, $vacinacoes, date('Y-m-d'), faltosos_dias_aviso($pdo));
$contactos = listar_contactos_crianca($pdo, $id);
$podeContactar = tem_perfil('recepcao', 'enfermeiro');
$feitas = 0;
foreach ($calendario as $oportunidade) if ($oportunidade['feita']) $feitas++;

$tituloPagina = 'Detalhe dos faltosos';
$menuAtual = 'faltosos.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho">
    <div>
        <h1><?php echo e($crianca['nome']); ?></h1>
        <p>Processo <?php echo e($crianca['numero_processo']); ?> · <?php echo e(idade_por_extenso_curta($crianca['data_nascimento'])); ?></p>
    </div>
    <div class="modulo-acoes">
        <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'faltosos.php'); ?>">Voltar</a>
        <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'crianca_detalhe.php?id=' . $crianca['id']); ?>">Ficha da criança</a>
    </div>
</section>

<div class="aviso-clinico" role="note">Valores clínicos por validar pela equipa clínica: o cálculo é indicativo. Regras com valor NULL são ignoradas.</div>

<section class="detalhe-grade">
    <article class="cartao">
        <h2>Encarregado</h2>
        <hr class="separador">
        <dl class="dados-lista">
            <dt>Nome</dt><dd><?php echo e($crianca['encarregado_nome']); ?></dd>
            <dt>Parentesco</dt><dd><?php echo e($crianca['parentesco']); ?></dd>
            <dt>Telefone</dt><dd><?php echo e($crianca['telefone']); ?></dd>
            <dt>Telefone alternativo</dt><dd><?php echo e($crianca['telefone_alt'] ?: '—'); ?></dd>
        </dl>
    </article>
    <article class="cartao">
        <h2>Resumo do calendário</h2>
        <hr class="separador">
        <div class="resumo-inline">
            <div><strong><?php echo e($feitas); ?></strong><span>Oportunidades cumpridas</span></div>
            <div><strong><?php echo e(count($calendario) - $feitas); ?></strong><span>Doses pendentes</span></div>
        </div>
    </article>
</section>

<?php require __DIR__ . '/../app/views/partials/calendario_pendente.php'; ?>

<section class="cartao">
    <h2>Contactos registados</h2>
    <?php if (!$podeContactar): ?><p class="ajuda-destaque">Consulta apenas. O registo de contactos é feito pela recepção ou enfermagem.</p><?php endif; ?>
    <?php if (!$contactos): ?>
        <p class="vazio">Ainda não há contactos registados.</p>
    <?php else: ?>
        <div class="tabela-container"><table class="tabela">
            <caption class="sr-only">Histórico de contactos com o encarregado</caption>
            <thead><tr><th scope="col">Data</th><th scope="col">Canal</th><th scope="col">Resultado</th><th scope="col">Notas</th><th scope="col">Registado por</th><?php if ($podeContactar): ?><th scope="col" class="acoes">Ação</th><?php endif; ?></tr></thead>
            <tbody><?php foreach ($contactos as $contacto): ?>
                <tr>
                    <td><?php echo e(data_pt_simples($contacto['data_contacto'])); ?></td>
                    <td><?php echo e(ucfirst($contacto['canal'])); ?></td>
                    <td><?php echo e($contacto['resultado']); ?></td>
                    <td><?php echo e($contacto['notas'] ?: '—'); ?></td>
                    <td><?php echo e($contacto['registado_por'] ?: '—'); ?></td>
                    <?php if ($podeContactar): ?><td class="acoes">
                        <form method="POST" action="<?php echo e(url_publica() . 'faltoso_contacto_eliminar.php'); ?>" class="formulario-inline" data-confirm="Confirmar a eliminação lógica deste contacto?">
                            <?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo e($contacto['id']); ?>">
                            <button class="botao botao-perigo" type="submit">Eliminar</button>
                        </form>
                    </td><?php endif; ?>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php if ($podeContactar): ?>
<section class="cartao">
    <h2>Registar contacto</h2>
    <p>O contacto é guardado manualmente. O sistema não envia SMS nem mensagens automáticas.</p>
    <form method="POST" action="<?php echo e(url_publica() . 'faltoso_contacto.php'); ?>">
        <?php echo csrf_field(); ?><input type="hidden" name="crianca_id" value="<?php echo e($crianca['id']); ?>">
        <div class="grelha-formulario">
            <div class="campo"><label for="data_contacto">Data do contacto *</label><input type="date" id="data_contacto" name="data_contacto" value="<?php echo e(date('Y-m-d')); ?>" max="<?php echo e(date('Y-m-d')); ?>" required></div>
            <div class="campo"><label for="canal">Canal *</label><select id="canal" name="canal" required><option value="telefone">Telefone</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></div>
            <div class="campo campo-amplo"><label for="resultado">Resultado *</label><input type="text" id="resultado" name="resultado" maxlength="100" required></div>
            <div class="campo campo-amplo"><label for="notas">Notas</label><textarea id="notas" name="notas" maxlength="2000"></textarea></div>
        </div>
        <div class="formulario-acoes"><button class="botao" type="submit">Registar contacto</button></div>
    </form>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
