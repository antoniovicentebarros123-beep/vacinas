<?php
/**
 * Testes da Fase A: configuração fora do Git e pastas/diagnósticos bloqueados.
 */

$raiz = dirname(__DIR__);
$base = 'http://localhost/vacinacao-infantil-fase1a/vacinas';
$falhas = 0;
$verificacoes = 0;

function verificar_fase_a($condicao, $mensagem, &$falhas, &$verificacoes) {
    $verificacoes++;
    if (!$condicao) {
        $falhas++;
        fwrite(STDERR, "FALHA: {$mensagem}\n");
    }
}

function codigo_http_fase_a($url) {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($curl);
    $codigo = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return $codigo;
}

$gitignore = file_get_contents($raiz . '/.gitignore');
verificar_fase_a(strpos($gitignore, 'config/config.local.php') !== false, 'config.local.php deve estar ignorado', $falhas, $verificacoes);

$rastreados = [];
$codigoSaida = 0;
exec('git -C ' . escapeshellarg($raiz) . ' ls-files config/config.local.php 2>NUL', $rastreados, $codigoSaida);
verificar_fase_a($codigoSaida === 0 && count($rastreados) === 0, 'config.local.php não pode permanecer no índice Git', $falhas, $verificacoes);
verificar_fase_a(is_file($raiz . '/config/config.local.php'), 'config.local.php deve continuar a existir localmente', $falhas, $verificacoes);

foreach (['debug_login.php', 'test_usuario.php', 'criar_admin_final.php', 'criar_admin_novo.php'] as $ficheiro) {
    $codigo = codigo_http_fase_a($base . '/public/' . $ficheiro);
    verificar_fase_a($codigo === 403, "{$ficheiro} deve devolver HTTP 403", $falhas, $verificacoes);
}

foreach (['database', 'config', 'arquivo_cloudflare', '.git', 'app', 'bin', 'tests'] as $pasta) {
    $codigo = codigo_http_fase_a($base . '/' . $pasta . '/');
    verificar_fase_a($codigo === 403, "/{$pasta}/ deve devolver HTTP 403", $falhas, $verificacoes);
}

$documento = file_get_contents($raiz . '/docs/Seguranca_Local.md');
verificar_fase_a(strpos($documento, "ALTER USER 'root'@'localhost'") !== false, 'devem existir passos para proteger o root', $falhas, $verificacoes);
verificar_fase_a(strpos($documento, "['auth_type'] = 'cookie'") !== false, 'devem existir passos para autenticação por cookie', $falhas, $verificacoes);

if ($falhas > 0) {
    fwrite(STDERR, "{$falhas} de {$verificacoes} verificações falharam.\n");
    exit(1);
}

echo "Fase A: {$verificacoes} verificações concluídas sem falhas.\n";
