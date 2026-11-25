<?php
/**
 * Histórico cronológico partilhado pela ficha da criança.
 */
?>
<section class="cartao">
    <h2>Histórico de vacinação</h2>
    <?php if (!$historico): ?>
        <p class="vazio">Ainda não há vacinações registadas.</p>
    <?php else: ?>
        <div class="tabela-container"><table class="tabela">
            <caption class="sr-only">Vacinações por ordem cronológica, incluindo anuladas</caption>
            <thead><tr><th scope="col">Data</th><th scope="col">Vacina</th><th scope="col">Dose</th><th scope="col">Lote</th><th scope="col">Origem</th><th scope="col">Estado</th><th scope="col">Observações</th><?php if (!empty($podeAnular)): ?><th scope="col" class="acoes">Anulação</th><?php endif; ?></tr></thead>
            <tbody><?php foreach ($historico as $registo): ?>
                <tr<?php echo $registo['anulacao_id'] !== null ? ' class="registo-anulado"' : ''; ?>>
                    <td><?php echo e(data_pt_simples($registo['data_administracao'])); ?></td>
                    <td><?php echo e($registo['vacina_nome']); ?></td><td><?php echo e($registo['numero_dose']); ?></td>
                    <td><?php echo e($registo['origem'] === 'externa' ? 'Não aplicável' : ($registo['numero_lote'] ?: '—')); ?></td>
                    <td><?php echo $registo['origem'] === 'externa' ? 'Externa' : 'Posto'; ?></td>
                    <td><?php if ($registo['anulacao_id'] !== null): ?><span class="etiqueta etiqueta-erro">Anulada</span><?php else: ?><span class="etiqueta etiqueta-sucesso">Válida</span><?php endif; ?></td>
                    <td><?php if ($registo['anulacao_id'] !== null): ?><?php echo e('Motivo: ' . $registo['anulacao_motivo'] . ' · Por: ' . $registo['anulada_por']); ?><?php else: ?><?php echo e($registo['observacoes'] ?: '—'); ?><?php endif; ?></td>
                    <?php if (!empty($podeAnular)): ?><td class="acoes"><?php if ($registo['anulacao_id'] === null): ?>
                        <form method="POST" action="<?php echo e(url_publica() . 'vacinacao_anular.php'); ?>" class="formulario-inline" data-confirm="Confirma a anulação? O stock não será devolvido automaticamente.">
                            <?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo e($registo['id']); ?>">
                            <div class="campo"><label for="motivo_<?php echo e($registo['id']); ?>">Motivo obrigatório</label><textarea id="motivo_<?php echo e($registo['id']); ?>" name="motivo" required maxlength="2000"></textarea></div>
                            <button class="botao botao-perigo" type="submit">Anular</button>
                        </form>
                    <?php else: ?>—<?php endif; ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
    <?php if (!empty($podeAnular)): ?><p class="ajuda-destaque">A anulação não devolve stock automaticamente. Se a dose não foi gasta, o responsável de stock registará um ajuste positivo com motivo.</p><?php endif; ?>
</section>
