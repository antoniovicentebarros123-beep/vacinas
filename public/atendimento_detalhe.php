<?php
/**
 * Ficha de atendimento e transições permitidas.
 */

require __DIR__ . '/../app/core/modulos.php';
require_once __DIR__ . '/../app/core/atendimentos.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('recepcao', 'enfermeiro', 'administrador');
$pdo = conexao_app();
$id = isset($_GET['id']) && is_scalar($_GET['id']) ? (int) $_GET['id'] : 0;
$atendimento = $id > 0 ? obter_atendimento($pdo, $id) : null;
if (!$atendimento) {
    redirecionar_modulo('atendimento.php', 'erro', 'Atendimento não encontrado.');
}
$vacinas = listar_vacinas_atendimento($pdo, $id);
$tituloPagina = 'Ficha de atendimento';
$menuAtual = 'atendimento.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>
<section class="modulo-cabecalho"><div><h1>Atendimento #<?php echo (int) $id; ?></h1><p><?php echo e($atendimento['nome']); ?> · <?php echo e($atendimento['numero_processo']); ?></p></div><span class="etiqueta <?php echo e(classe_estado_atendimento($atendimento['estado'])); ?>"><?php echo e(rotulo_estado_atendimento($atendimento['estado'])); ?></span></section>
<div class="detalhe-grade">
    <section class="cartao"><h2>Criança</h2><dl class="dados-lista"><dt>Nome</dt><dd><?php echo e($atendimento['nome']); ?></dd><dt>Nascimento</dt><dd><?php echo e(data_pt_simples($atendimento['data_nascimento'])); ?><?php echo (int) $atendimento['data_nascimento_aproximada'] === 1 ? ' (aproximada)' : ''; ?></dd><dt>Idade</dt><dd><?php echo e(idade_por_extenso_curta($atendimento['data_nascimento'])); ?></dd><dt>Chegada</dt><dd><?php echo e(data_hora_pt_simples($atendimento['criado_em'])); ?></dd><dt>Recebido por</dt><dd><?php echo e($atendimento['recebido_por_nome'] ?? '—'); ?></dd><dt>Iniciado por</dt><dd><?php echo e($atendimento['atendido_por_nome'] ?? '—'); ?></dd></dl></section>
    <section class="cartao"><h2>Encarregado</h2><dl class="dados-lista"><dt>Nome</dt><dd><?php echo e($atendimento['encarregado_nome']); ?></dd><dt>Parentesco</dt><dd><?php echo e($atendimento['parentesco']); ?></dd><dt>Telefone</dt><dd><?php echo e($atendimento['telefone']); ?></dd><dt>Alternativo</dt><dd><?php echo e($atendimento['telefone_alt'] ?? '—'); ?></dd><dt>Morada</dt><dd><?php echo e($atendimento['morada'] ?? '—'); ?></dd><dt>Bairro</dt><dd><?php echo e($atendimento['bairro_nome'] ?? 'Não informado'); ?></dd></dl></section>
</div>
<section class="cartao"><h2>Vacinas pretendidas</h2><div class="tabela-container"><table class="tabela"><thead><tr><th scope="col">Vacina</th><th scope="col">Disponibilidade</th></tr></thead><tbody><?php foreach ($vacinas as $vacina): ?><tr><td><?php echo e($vacina['nome']); ?></td><td><span class="etiqueta <?php echo (int) $vacina['disponivel'] === 1 ? 'etiqueta-sucesso' : 'etiqueta-erro'; ?>"><?php echo (int) $vacina['disponivel'] === 1 ? 'Disponível' : 'Indisponível'; ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php if ($atendimento['motivo'] !== null): ?><section class="cartao"><h2>Motivo</h2><p><?php echo e($atendimento['motivo']); ?></p></section><?php endif; ?>
<?php if ($atendimento['estado'] === 'aguardando' && tem_perfil('enfermeiro')): ?>
<section class="cartao"><h2>Iniciar atendimento</h2><form method="post" action="<?php echo e(url_publica() . 'atendimento_estado.php'); ?>" data-confirm="Confirma o início deste atendimento?"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><input type="hidden" name="transicao" value="iniciar"><button type="submit" class="botao botao-primario">Iniciar atendimento</button></form></section>
<?php elseif ($atendimento['estado'] === 'aguardando' && tem_perfil('recepcao')): ?>
<div class="detalhe-grade">
    <section class="cartao"><h2>Registar sem vacina</h2><form method="post" action="<?php echo e(url_publica() . 'atendimento_estado.php'); ?>" data-confirm="Confirma o registo sem vacina?"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><input type="hidden" name="transicao" value="sem_vacina"><div class="campo"><label for="motivo_sem">Motivo obrigatório</label><textarea id="motivo_sem" name="motivo" required maxlength="2000"></textarea></div><button type="submit" class="botao botao-perigo">Registar sem vacina</button></form></section>
    <section class="cartao"><h2>Cancelar atendimento</h2><form method="post" action="<?php echo e(url_publica() . 'atendimento_estado.php'); ?>" data-confirm="Confirma o cancelamento deste atendimento?"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><input type="hidden" name="transicao" value="cancelado"><div class="campo"><label for="motivo_cancelar">Motivo obrigatório</label><textarea id="motivo_cancelar" name="motivo" required maxlength="2000"></textarea></div><button type="submit" class="botao botao-perigo">Cancelar atendimento</button></form></section>
</div>
<?php elseif ($atendimento['estado'] === 'em_atendimento' && tem_perfil('enfermeiro')): ?>
<section class="cartao"><h2>Atendimento em curso</h2><p>O registo da vacinação e a conclusão são feitos no módulo Vacinação.</p><a class="botao" href="<?php echo e(url_publica() . 'vacinacao_form.php?atendimento_id=' . $id); ?>">Registar vacinação</a></section>
<?php endif; ?>
<section class="modulo-acoes"><a class="botao botao-secundario" href="<?php echo e(url_publica() . 'atendimento.php'); ?>">Voltar ao atendimento</a></section>
<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
