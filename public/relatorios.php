<?php
/**
 * Relatórios operacionais com filtros e permissões por perfil.
 */

require_once __DIR__ . '/../app/core/relatorios.php';
ini_set('display_errors', config('display_errors', false) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('enfermeiro', 'responsavel_stock', 'administrador');

$pdo = conexao_app();
$perfil = perfil_atual();
$podeAbrirCrianca = tem_perfil('recepcao', 'enfermeiro', 'administrador');
$tiposPermitidos = relatorios_tipos_permitidos($perfil);
$tipoSolicitado = isset($_GET['tipo']) && is_string($_GET['tipo']) ? trim($_GET['tipo']) : '';
$tipo = $tipoSolicitado !== '' ? $tipoSolicitado : relatorios_tipo_padrao($perfil);
if (!relatorios_tipo_autorizado($perfil, $tipo)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('Relatório não autorizado para este perfil.');
}

$erroFiltro = '';
try {
    $filtros = normalizar_filtros_relatorio($_GET, $tipo);
} catch (RuntimeException $e) {
    $erroFiltro = $e->getMessage();
    $filtros = normalizar_filtros_relatorio([], $tipo);
}
$linhas = obter_linhas_relatorio($pdo, $tipo, $filtros);
$vacinas = listar_vacinas_relatorio($pdo);
$enfermeiros = $tipo === 'vacinacoes' ? listar_enfermeiros_relatorio($pdo) : [];
$parametrosCsv = [
    'tipo' => $tipo,
    'data_inicio' => $filtros['data_inicio'],
    'data_fim' => $filtros['data_fim'],
    'vacina_id' => $filtros['vacina_id'],
    'enfermeiro_id' => $filtros['enfermeiro_id'],
];
$urlCsv = url_publica() . 'relatorios.csv.php?' . http_build_query($parametrosCsv);
$periodo = $tipo === 'stock' && $filtros['data_inicio'] === '' && $filtros['data_fim'] === ''
    ? 'Todas as validades'
    : data_pt_simples($filtros['data_inicio']) . ' a ' . data_pt_simples($filtros['data_fim']);

$tituloPagina = 'Relatórios';
$menuAtual = 'relatorios.php';
require __DIR__ . '/../app/views/partials/modulo_inicio.php';
?>

<section class="modulo-cabecalho">
    <div>
        <h1>Relatórios</h1>
        <p>Consulte os resultados operacionais por período e exporte os dados em CSV.</p>
    </div>
    <div class="modulo-acoes">
        <a class="botao botao-secundario" href="<?php echo e($urlCsv); ?>">Exportar CSV</a>
    </div>
</section>

<nav class="relatorios-grade" aria-label="Tipos de relatório">
    <?php foreach ($tiposPermitidos as $tipoDisponivel): ?>
        <a class="cartao relatorio-opcao" href="<?php echo e(url_publica() . 'relatorios.php?tipo=' . rawurlencode($tipoDisponivel)); ?>"<?php echo $tipo === $tipoDisponivel ? ' aria-current="page"' : ''; ?>>
            <h2><?php echo e(relatorios_rotulo_tipo($tipoDisponivel)); ?></h2>
            <p><?php echo $tipoDisponivel === 'sem_vacina' ? 'Saídas registadas por falta de vacina.' : ($tipoDisponivel === 'vacinacoes' ? 'Doses registadas, incluindo anulações identificadas.' : ($tipoDisponivel === 'stock' ? 'Saldos, lotes e alertas de validade.' : ($tipoDisponivel === 'faltosos' ? 'Crianças em atraso com contactos no período.' : 'Histórico de alterações e operações auditadas.'))); ?></p>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tipo === 'faltosos'): ?>
    <div class="aviso-clinico" role="note">Valores clínicos por validar pela equipa clínica: o cálculo é indicativo.</div>
<?php endif; ?>

<section class="cartao">
    <h2>Filtros</h2>
    <?php if ($erroFiltro !== ''): ?>
        <div class="mensagem mensagem-erro" role="alert"><?php echo e($erroFiltro); ?> Foram repostos os filtros predefinidos.</div>
    <?php endif; ?>
    <form method="GET" action="<?php echo e(url_publica() . 'relatorios.php'); ?>" class="barra-pesquisa relatorio-filtros">
        <input type="hidden" name="tipo" value="<?php echo e($tipo); ?>">
        <div class="campo">
            <label for="data_inicio"><?php echo $tipo === 'stock' ? 'Validade desde' : 'Data inicial'; ?></label>
            <input type="date" id="data_inicio" name="data_inicio" value="<?php echo e($filtros['data_inicio']); ?>">
        </div>
        <div class="campo">
            <label for="data_fim"><?php echo $tipo === 'stock' ? 'Validade até' : 'Data final'; ?></label>
            <input type="date" id="data_fim" name="data_fim" value="<?php echo e($filtros['data_fim']); ?>">
        </div>
        <?php if (in_array($tipo, ['sem_vacina', 'vacinacoes', 'stock'], true)): ?>
            <div class="campo">
                <label for="vacina_id">Vacina</label>
                <select id="vacina_id" name="vacina_id">
                    <option value="0">Todas as vacinas</option>
                    <?php foreach ($vacinas as $vacina): ?>
                        <option value="<?php echo e($vacina['id']); ?>"<?php echo $filtros['vacina_id'] === (int) $vacina['id'] ? ' selected' : ''; ?>><?php echo e($vacina['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if ($tipo === 'vacinacoes'): ?>
            <div class="campo">
                <label for="enfermeiro_id">Enfermeiro</label>
                <select id="enfermeiro_id" name="enfermeiro_id">
                    <option value="0">Todos os profissionais</option>
                    <?php foreach ($enfermeiros as $enfermeiro): ?>
                        <option value="<?php echo e($enfermeiro['id']); ?>"<?php echo $filtros['enfermeiro_id'] === (int) $enfermeiro['id'] ? ' selected' : ''; ?>><?php echo e($enfermeiro['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <button class="botao botao-secundario" type="submit">Filtrar</button>
        <a class="botao botao-secundario" href="<?php echo e(url_publica() . 'relatorios.php?tipo=' . rawurlencode($tipo)); ?>">Limpar</a>
    </form>
</section>

<section class="cartao relatorio-resultado">
    <div class="modulo-cabecalho">
        <div>
            <h2><?php echo e(relatorios_rotulo_tipo($tipo)); ?></h2>
            <p><?php echo e($periodo); ?></p>
        </div>
        <div class="resumo-inline">
            <div><strong><?php echo e(count($linhas)); ?></strong><span>Registos no resultado</span></div>
        </div>
    </div>

    <?php if (!$linhas): ?>
        <p class="vazio">Nenhum registo encontrado com os filtros selecionados.</p>
    <?php elseif ($tipo === 'sem_vacina'): ?>
        <div class="tabela-container"><table class="tabela">
            <caption class="sr-only">Crianças que saíram sem vacina por vacina e período</caption>
            <thead><tr><th scope="col">Data</th><th scope="col">Atendimento</th><th scope="col">Criança</th><th scope="col">Processo</th><th scope="col">Vacina</th><th scope="col">Motivo</th></tr></thead>
            <tbody><?php foreach ($linhas as $linha): ?><tr>
                <td><?php echo e(data_hora_pt_simples($linha['criado_em'])); ?></td>
                <td><?php echo e($linha['id']); ?></td>
                <td><?php if ($podeAbrirCrianca): ?><a class="ligacao-acao" href="<?php echo e(url_publica() . 'crianca_detalhe.php?id=' . $linha['crianca_id']); ?>"><?php echo e($linha['crianca_nome']); ?></a><?php else: ?><?php echo e($linha['crianca_nome']); ?><?php endif; ?></td>
                <td><?php echo e($linha['numero_processo']); ?></td>
                <td><?php echo e($linha['vacina_nome']); ?></td>
                <td><?php echo e($linha['motivo']); ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
    <?php elseif ($tipo === 'vacinacoes'): ?>
        <div class="tabela-container"><table class="tabela">
            <caption class="sr-only">Vacinações por período, vacina e enfermeiro</caption>
            <thead><tr><th scope="col">Data</th><th scope="col">Criança</th><th scope="col">Processo</th><th scope="col">Vacina</th><th scope="col">Dose</th><th scope="col">Profissional</th><th scope="col">Origem</th><th scope="col">Estado</th></tr></thead>
            <tbody><?php foreach ($linhas as $linha): ?><tr>
                <td><?php echo e(data_pt_simples($linha['data_administracao'])); ?></td>
                <td><?php if ($podeAbrirCrianca): ?><a class="ligacao-acao" href="<?php echo e(url_publica() . 'crianca_detalhe.php?id=' . $linha['crianca_id']); ?>"><?php echo e($linha['crianca_nome']); ?></a><?php else: ?><?php echo e($linha['crianca_nome']); ?><?php endif; ?></td>
                <td><?php echo e($linha['numero_processo']); ?></td>
                <td><?php echo e($linha['vacina_nome']); ?></td>
                <td><?php echo e($linha['numero_dose']); ?></td>
                <td><?php echo e($linha['profissional']); ?></td>
                <td><?php echo $linha['origem'] === 'externa' ? 'Externa' : 'Posto'; ?></td>
                <td><span class="etiqueta <?php echo (int) $linha['valida'] === 1 ? 'etiqueta-sucesso' : 'etiqueta-erro'; ?>"><?php echo (int) $linha['valida'] === 1 ? 'Válida' : 'Anulada'; ?></span></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
    <?php elseif ($tipo === 'stock'): ?>
        <div class="tabela-container"><table class="tabela">
            <caption class="sr-only">Stock atual e validades dos lotes</caption>
            <thead><tr><th scope="col">Vacina</th><th scope="col">Lote</th><th scope="col">Fabricante</th><th scope="col">Validade</th><th scope="col" class="numero">Saldo</th><th scope="col">Estado</th><th scope="col" class="acoes">Detalhe</th></tr></thead>
            <tbody><?php foreach ($linhas as $linha): ?><tr>
                <td><?php echo e($linha['vacina_nome']); ?></td>
                <td><?php echo e($linha['numero_lote']); ?></td>
                <td><?php echo e($linha['fabricante']); ?></td>
                <td><?php echo e(data_pt_simples($linha['validade'])); ?></td>
                <td class="numero"><?php echo e($linha['saldo_atual']); ?> doses</td>
                <td><span class="etiqueta <?php echo e($linha['estado_classe']); ?>"><?php echo e($linha['estado_rotulo']); ?></span></td>
                <td class="acoes"><a class="ligacao-acao" href="<?php echo e(url_publica() . 'stock_lote_detalhe.php?id=' . $linha['id']); ?>">Abrir</a></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
    <?php elseif ($tipo === 'faltosos'): ?>
        <div class="tabela-container"><table class="tabela">
            <caption class="sr-only">Crianças em atraso com contactos registados no período</caption>
            <thead><tr><th scope="col">Criança</th><th scope="col">Processo</th><th scope="col">Encarregado</th><th scope="col">Telefone</th><th scope="col" class="numero">Doses em atraso</th><th scope="col" class="numero">Contactos</th><th scope="col">Último contacto</th><th scope="col">Canais</th><th scope="col">Último resultado</th><th scope="col">Registado por</th></tr></thead>
            <tbody><?php foreach ($linhas as $linha): ?><tr>
                <td><a class="ligacao-acao" href="<?php echo e(url_publica() . 'faltoso_detalhe.php?id=' . $linha['crianca_id']); ?>"><?php echo e($linha['crianca_nome']); ?></a></td>
                <td><?php echo e($linha['numero_processo']); ?></td>
                <td><?php echo e($linha['encarregado_nome']); ?></td>
                <td><?php echo e($linha['telefone']); ?></td>
                <td class="numero"><?php echo e($linha['dias_em_atraso']); ?></td>
                <td class="numero"><?php echo e($linha['total_contactos']); ?></td>
                <td><?php echo e(data_pt_simples($linha['ultimo_contacto'])); ?></td>
                <td><?php echo e($linha['canais']); ?></td>
                <td><?php echo e($linha['ultimo_resultado']); ?></td>
                <td><?php echo e($linha['ultimo_registado_por']); ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
    <?php else: ?>
        <div class="tabela-container"><table class="tabela">
            <caption class="sr-only">Registos de auditoria no período</caption>
            <thead><tr><th scope="col">Data/hora</th><th scope="col">Entidade</th><th scope="col">ID</th><th scope="col">Ação</th><th scope="col">Campo</th><th scope="col">Valor anterior</th><th scope="col">Valor novo</th><th scope="col">Utilizador</th></tr></thead>
            <tbody><?php foreach ($linhas as $linha): ?><tr>
                <td><?php echo e(data_hora_pt_simples($linha['criado_em'])); ?></td>
                <td><?php echo e($linha['entidade']); ?></td>
                <td><?php echo e($linha['entidade_id']); ?></td>
                <td><?php echo e($linha['acao']); ?></td>
                <td><?php echo e($linha['campo'] ?: '—'); ?></td>
                <td><?php echo e($linha['valor_anterior'] ?: '—'); ?></td>
                <td><?php echo e($linha['valor_novo'] ?: '—'); ?></td>
                <td><?php echo e($linha['utilizador_nome'] ?: 'Sistema'); ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../app/views/partials/modulo_fim.php'; ?>
