<?php
/**
 * Verificações HTTP de persistência, expiração e isolamento das sessões.
 */

require __DIR__ . '/suporte_http.php';
require_once __DIR__ . '/../app/core/helpers.php';

function cookies_sessao_teste($ficheiro) {
    $cookies = [];
    if (!is_file($ficheiro)) {
        return $cookies;
    }

    foreach (file($ficheiro, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (strpos($linha, '#HttpOnly_') === 0) {
            $linha = substr($linha, strlen('#HttpOnly_'));
        } elseif ($linha === '' || $linha[0] === '#') {
            continue;
        }
        $colunas = explode("\t", $linha);
        if (count($colunas) >= 7) {
            $cookies[$colunas[5]] = [
                'domain' => $colunas[0],
                'path' => $colunas[2],
                'secure' => $colunas[3] === 'TRUE',
                'expires' => (int) $colunas[4],
                'value' => $colunas[6],
            ];
        }
    }

    return $cookies;
}

function login_sessao_teste($base, $caminho, $cookie, $utilizador, $password, $manter = false) {
    $url = $base . '/' . $caminho;
    $inicial = pedido_http_teste($url, $cookie);
    $dados = [
        'csrf_token' => csrf_do_html($inicial['corpo']),
        'utilizador' => $utilizador,
        'password' => $password,
    ];
    if ($manter) {
        $dados['manter'] = 'on';
    }
    return pedido_http_teste($url, $cookie, $dados);
}

function atualizar_ultimo_acesso_sessao_teste($id, $nome, $ultimoAcesso) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $nomeOriginal = session_name();
    $caminhoOriginal = session_save_path();
    $strictOriginal = ini_get('session.use_strict_mode');
    ini_set('session.use_strict_mode', '0');
    session_name($nome);
    session_save_path((string) ini_get('session.save_path'));
    session_id($id);
    if (!session_start()) {
        throw new RuntimeException('Não foi possível abrir a sessão de teste.');
    }
    $_SESSION['ultimo_acesso'] = $ultimoAcesso;
    session_write_close();
    session_id('');
    session_name($nomeOriginal);
    session_save_path($caminhoOriginal);
    ini_set('session.use_strict_mode', (string) $strictOriginal);
}

function ler_ultimo_acesso_sessao_teste($id, $nome) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $nomeOriginal = session_name();
    $caminhoOriginal = session_save_path();
    $strictOriginal = ini_get('session.use_strict_mode');
    ini_set('session.use_strict_mode', '0');
    session_name($nome);
    session_save_path((string) ini_get('session.save_path'));
    session_id($id);
    if (!session_start()) {
        throw new RuntimeException('Não foi possível ler a sessão de teste.');
    }
    $ultimoAcesso = (int) ($_SESSION['ultimo_acesso'] ?? 0);
    session_abort();
    session_id('');
    session_name($nomeOriginal);
    session_save_path($caminhoOriginal);
    ini_set('session.use_strict_mode', (string) $strictOriginal);
    return $ultimoAcesso;
}

$base = 'http://localhost/vacinacao-infantil-fase1a/vacinas/public';
$sufixo = strtolower(bin2hex(random_bytes(5)));
$password = bin2hex(random_bytes(16)) . 'A!';
$adminNome = 'sessao.admin.' . $sufixo;
$enfermeiroNome = 'sessao.enfermeiro.' . $sufixo;
$pdo = conexao_teste_root();
$adminId = 0;
$enfermeiroId = 0;
$encarregadoId = 0;
$pasta = sys_get_temp_dir() . '/vacinas_sessao_' . $sufixo;
$cookies = [];
$sessionFiles = [];
$falhas = 0;
$total = 0;
mkdir($pasta);

try {
    $adminId = criar_utilizador_teste($pdo, $adminNome, 'administrador', $password);
    $enfermeiroId = criar_utilizador_teste($pdo, $enfermeiroNome, 'enfermeiro', $password);
    $pdo->prepare(
        'INSERT INTO encarregados (nome, parentesco, telefone, ativo) VALUES (?, ?, ?, TRUE)'
    )->execute(['Encarregado teste sessão ' . $sufixo, 'Mãe', '900000000']);
    $encarregadoId = (int) $pdo->lastInsertId();

    $cookies['principal'] = $pasta . '/principal.txt';
    $login = login_sessao_teste($base, 'login.php', $cookies['principal'], $adminNome, $password);
    verificar_teste(
        $login['status'] === 302 && strpos($login['cabecalhos'], 'painel.php') !== false,
        'login principal cria sessão autenticada',
        $falhas,
        $total
    );

    $jar = cookies_sessao_teste($cookies['principal']);
    $cookiePrincipal = $jar['PHPSESSID'] ?? null;
    verificar_teste(
        $cookiePrincipal !== null
            && $cookiePrincipal['path'] === '/vacinacao-infantil-fase1a/vacinas/public/'
            && !$cookiePrincipal['secure'],
        'cookie principal tem Path da aplicação e funciona em HTTP',
        $falhas,
        $total
    );
    verificar_teste(
        strpos($login['cabecalhos'], 'HttpOnly') !== false
            && stripos($login['cabecalhos'], 'SameSite=Strict') !== false
            && stripos($login['cabecalhos'], 'Secure') === false,
        'cookie principal mantém HttpOnly e SameSite Strict sem Secure em HTTP',
        $falhas,
        $total
    );
    $idPrincipal = (string) ($cookiePrincipal['value'] ?? '');
    if ($idPrincipal !== '') {
        $sessionFiles[] = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $idPrincipal;
    }

    $paginas = [
        'painel.php',
        'criancas.php',
        'atendimento.php',
        'vacinacao.php',
        'stock.php',
        'relatorios.php',
        'disponibilidade.php',
        'fila.php',
        'faltosos.php',
        'encarregados.php',
    ];
    $pedidosValidos = true;
    foreach ($paginas as $pagina) {
        $resposta = pedido_http_teste($base . '/' . $pagina, $cookies['principal']);
        $jarAtual = cookies_sessao_teste($cookies['principal']);
        $idAtual = (string) ($jarAtual['PHPSESSID']['value'] ?? '');
        if ($resposta['status'] !== 200 || $idAtual !== $idPrincipal
            || strpos($resposta['corpo'], 'login-nome') !== false) {
            $pedidosValidos = false;
        }
    }
    verificar_teste($pedidosValidos, '10 pedidos a páginas autenticadas mantêm o mesmo cookie e não voltam ao login', $falhas, $total);

    $limites = configuracao_sessao();
    $ultimoAcesso = ler_ultimo_acesso_sessao_teste($idPrincipal, 'PHPSESSID');
    verificar_teste(
        $ultimoAcesso >= time() - 5,
        'último acesso é atualizado durante a navegação autenticada',
        $falhas,
        $total
    );

    atualizar_ultimo_acesso_sessao_teste($idPrincipal, 'PHPSESSID', time() - $limites['session_lifetime'] + 10);
    $pertoDoLimite = pedido_http_teste($base . '/painel.php', $cookies['principal']);
    verificar_teste(
        $pertoDoLimite['status'] === 200
            && ler_ultimo_acesso_sessao_teste($idPrincipal, 'PHPSESSID') >= time() - 5,
        'atividade antes do limite renova o relógio de inatividade',
        $falhas,
        $total
    );

    $painel = pedido_http_teste($base . '/painel.php', $cookies['principal']);
    $csrf = csrf_do_html($painel['corpo']);
    $logout = pedido_http_teste($base . '/logout.php', $cookies['principal'], ['csrf_token' => $csrf]);
    $aposLogout = pedido_http_teste($base . '/painel.php', $cookies['principal']);
    verificar_teste(
        $logout['status'] === 302
            && $aposLogout['status'] === 302
            && strpos($aposLogout['cabecalhos'], 'login.php') !== false,
        'logout invalida a sessão e o painel redireciona para o login',
        $falhas,
        $total
    );

    $cookies['expirada'] = $pasta . '/expirada.txt';
    $login = login_sessao_teste($base, 'login.php', $cookies['expirada'], $adminNome, $password);
    $idExpirada = (string) (cookies_sessao_teste($cookies['expirada'])['PHPSESSID']['value'] ?? '');
    if ($idExpirada !== '') {
        $sessionFiles[] = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $idExpirada;
    }
    atualizar_ultimo_acesso_sessao_teste(
        $idExpirada,
        'PHPSESSID',
        time() - $limites['session_lifetime'] - 1
    );
    $expiracao = pedido_http_teste($base . '/painel.php', $cookies['expirada']);
    verificar_teste(
        $login['status'] === 302
            && $expiracao['status'] === 302
            && strpos($expiracao['cabecalhos'], 'login.php') !== false,
        'sessão só expira depois do tempo de inatividade configurado',
        $falhas,
        $total
    );

    $cookies['lembrar'] = $pasta . '/lembrar.txt';
    $loginLembrar = login_sessao_teste($base, 'login.php', $cookies['lembrar'], $adminNome, $password, true);
    $cookieLembrar = cookies_sessao_teste($cookies['lembrar'])['PHPSESSID'] ?? null;
    verificar_teste(
        $loginLembrar['status'] === 302
            && $cookieLembrar !== null
            && $cookieLembrar['expires'] >= time() + $limites['session_remember_lifetime'] - 10,
        'Manter sessão prolonga o cookie pelo prazo configurado',
        $falhas,
        $total
    );
    $idLembrar = (string) ($cookieLembrar['value'] ?? '');
    if ($idLembrar !== '') {
        $sessionFiles[] = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $idLembrar;
    }
    $cookies['principal_enfermeiro'] = $pasta . '/principal_enfermeiro.txt';
    login_sessao_teste($base, 'login.php', $cookies['principal_enfermeiro'], $enfermeiroNome, $password);
    $semPermissao = pedido_http_teste(
        $base . '/gestao/funcionarios.php',
        $cookies['principal_enfermeiro']
    );
    verificar_teste(
        $semPermissao['status'] === 302
            && strpos($semPermissao['cabecalhos'], 'gestao/index.php') !== false,
        'enfermeiro não acede à gestão com o cookie da aplicação principal',
        $falhas,
        $total
    );

    $cookies['gestao'] = $pasta . '/gestao.txt';
    $loginGestao = login_sessao_teste(
        $base,
        'gestao/index.php',
        $cookies['gestao'],
        $adminNome,
        $password
    );
    $jarGestao = cookies_sessao_teste($cookies['gestao']);
    $cookieGestao = $jarGestao['VACINAS_GESTAO'] ?? null;
    verificar_teste(
        $loginGestao['status'] === 302
            && strpos($loginGestao['cabecalhos'], 'gestao/funcionarios.php') !== false
            && $cookieGestao !== null
            && !isset($jarGestao['PHPSESSID'])
            && $cookieGestao['path'] === '/vacinacao-infantil-fase1a/vacinas/public/',
        'login da gestão usa cookie próprio no Path público',
        $falhas,
        $total
    );
    $idGestao = (string) ($cookieGestao['value'] ?? '');
    if ($idGestao !== '') {
        $sessionFiles[] = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $idGestao;
    }
    $gestaoValida = true;
    for ($i = 0; $i < 10; $i++) {
        $resposta = pedido_http_teste($base . '/gestao/funcionarios.php', $cookies['gestao']);
        $idAtual = (string) (cookies_sessao_teste($cookies['gestao'])['VACINAS_GESTAO']['value'] ?? '');
        if ($resposta['status'] !== 200 || $idAtual !== $idGestao) {
            $gestaoValida = false;
        }
    }
    verificar_teste($gestaoValida, '10 pedidos na gestão mantêm o cookie e o ID de sessão', $falhas, $total);

    $gestaoPage = pedido_http_teste($base . '/gestao/funcionarios.php', $cookies['gestao']);
    $csrfGestao = csrf_do_html($gestaoPage['corpo']);
    $logoutGestao = pedido_http_teste(
        $base . '/gestao/logout.php',
        $cookies['gestao'],
        ['csrf_token' => $csrfGestao]
    );
    $aposLogoutGestao = pedido_http_teste($base . '/gestao/funcionarios.php', $cookies['gestao']);
    verificar_teste(
        $logoutGestao['status'] === 302
            && $aposLogoutGestao['status'] === 302
            && strpos($aposLogoutGestao['cabecalhos'], 'gestao/index.php') !== false,
        'logout da gestão invalida a sessão independente',
        $falhas,
        $total
    );
} finally {
    foreach (array_unique($sessionFiles) as $sessionFile) {
        if (is_file($sessionFile)) {
            unlink($sessionFile);
        }
    }
    foreach ($cookies as $cookieFile) {
        if (is_file($cookieFile)) {
            unlink($cookieFile);
        }
    }
    if (is_dir($pasta)) {
        rmdir($pasta);
    }
    if ($encarregadoId > 0) {
        $pdo->prepare('DELETE FROM encarregados WHERE id = ?')->execute([$encarregadoId]);
    }
    if ($adminId > 0) {
        limpar_utilizador_teste($pdo, $adminId, $adminNome);
    }
    if ($enfermeiroId > 0) {
        limpar_utilizador_teste($pdo, $enfermeiroId, $enfermeiroNome);
    }
}

if ($falhas > 0) {
    fwrite(STDERR, "Verificação de sessões: {$total} testes, {$falhas} falhas.\n");
    exit(1);
}

echo "Verificação de sessões: {$total} testes concluídos sem falhas.\n";
