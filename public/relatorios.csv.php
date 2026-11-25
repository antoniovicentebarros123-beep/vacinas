<?php
/**
 * Exportação CSV dos relatórios, com as mesmas permissões da página HTML.
 */

require_once __DIR__ . '/../app/core/relatorios.php';
ini_set('display_errors', config('display_errors', false) ? '1' : '0');
iniciar_sessao_segura();
requer_perfil('enfermeiro', 'responsavel_stock', 'administrador');

$perfil = perfil_atual();
$tipo = isset($_GET['tipo']) && is_string($_GET['tipo']) ? trim($_GET['tipo']) : relatorios_tipo_padrao($perfil);
if (!relatorios_tipo_autorizado($perfil, $tipo)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Relatório não autorizado para este perfil.');
}

try {
    $filtros = normalizar_filtros_relatorio($_GET, $tipo);
    $linhas = obter_linhas_relatorio(conexao_app(), $tipo, $filtros);
} catch (RuntimeException $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit($e->getMessage());
}

enviar_csv_relatorio($tipo, $linhas);
exit;
