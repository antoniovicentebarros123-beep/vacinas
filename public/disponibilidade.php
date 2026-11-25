<?php
/**
 * Disponibilidade de vacinas apenas como sim/não para todos os perfis.
 */

require __DIR__ . '/../app/core/modulos.php';
require_once __DIR__ . '/../app/core/atendimentos.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('recepcao', 'enfermeiro', 'responsavel_stock', 'administrador');
$vacinas = listar_disponibilidade_vacinas(conexao_app());
$tituloPagina = 'Disponibilidade';
$menuAtual = 'atendimento.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>
<section class="modulo-cabecalho"><div><h1>Disponibilidade de vacinas</h1><p>Consulta apenas de disponibilidade. Lotes e quantidades não são apresentados.</p></div></section>
<section class="cartao"><div class="tabela-container"><table class="tabela"><caption class="so-leitor">Disponibilidade atual</caption><thead><tr><th scope="col">Vacina</th><th scope="col">Estado</th></tr></thead><tbody><?php foreach ($vacinas as $vacina): ?><tr><td><?php echo e($vacina['nome']); ?></td><td><span class="etiqueta <?php echo (int) $vacina['disponivel'] === 1 ? 'etiqueta-sucesso' : 'etiqueta-erro'; ?>"><?php echo (int) $vacina['disponivel'] === 1 ? 'Disponível' : 'Indisponível'; ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
