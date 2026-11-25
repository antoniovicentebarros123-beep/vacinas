<?php
/**
 * Doses ainda não cumpridas, calculadas e nunca guardadas.
 */
$calendarioPendente = [];
foreach ($calendario as $linhaCalendario) {
    if (!$linhaCalendario['feita']) $calendarioPendente[] = $linhaCalendario;
}
?>
<section class="cartao">
    <h2>Doses pendentes</h2>
    <?php if (!$calendarioPendente): ?>
        <p class="vazio">Não há doses pendentes segundo o calendário registado.</p>
    <?php else: ?>
        <div class="tabela-container"><table class="tabela">
            <caption class="sr-only">Doses previstas, estados e condições de elegibilidade</caption>
            <thead><tr><th scope="col">Vacina</th><th scope="col">Dose</th><th scope="col">Idade ideal</th><th scope="col">Data prevista</th><th scope="col">Estado</th><th scope="col">Condição</th></tr></thead>
            <tbody><?php foreach ($calendarioPendente as $pendente): ?>
                <tr>
                    <td><?php echo e($pendente['vacina_nome']); ?></td>
                    <td><?php echo e($pendente['numero_dose']); ?></td>
                    <td><?php echo e($pendente['rotulo_idade']); ?></td>
                    <td><?php echo e(data_pt_simples($pendente['data_prevista'])); ?></td>
                    <td><span class="etiqueta <?php echo e(classe_estado_faltoso($pendente['estado'])); ?>"><?php echo e(rotulo_estado_faltoso($pendente['estado'])); ?></span></td>
                    <td>
                        <?php if ($pendente['motivo_elegibilidade'] !== ''): ?>
                            <?php echo e($pendente['motivo_elegibilidade']); ?>
                        <?php elseif ($pendente['data_elegivel'] > date('Y-m-d')): ?>
                            Elegível a partir de <?php echo e(data_pt_simples($pendente['data_elegivel'])); ?>.
                        <?php else: ?>
                            Elegível segundo os valores registados.
                        <?php endif; ?>
                        <?php if ($pendente['validar_clinica']): ?><br><small>Valores clínicos por validar pela equipa clínica.</small><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
