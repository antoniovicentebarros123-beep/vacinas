<?php
/**
 * Gestão de sessões seguras, com expiração por inatividade.
 */

function configuracao_sessao() {
    static $valores = null;
    if ($valores !== null) {
        return $valores;
    }

    $valores = [
        'session_lifetime' => max(1, (int) config('session_lifetime', 1800)),
        'session_remember_lifetime' => max(1, (int) config('session_remember_lifetime', 604800)),
    ];

    try {
        if (function_exists('conexao_app')) {
            $pdo = conexao_app();
        } else {
            $config = config();
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
            $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        }

        $stmt = $pdo->prepare(
            'SELECT chave, valor FROM configuracoes WHERE chave IN (?, ?)'
        );
        $stmt->execute(['session_lifetime', 'session_remember_lifetime']);
        foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $chave => $valor) {
            if (isset($valores[$chave]) && is_numeric($valor)) {
                $valores[$chave] = max(1, (int) $valor);
            }
        }
    } catch (PDOException $e) {
        error_log('Não foi possível ler os limites de sessão da configuração da base de dados.');
    }

    return $valores;
}

function caminho_cookie_sessao() {
    $base = function_exists('url_publica') ? (string) url_publica() : '/';
    $base = rtrim($base, '/');

    if ($base === '' || $base[0] !== '/') {
        return '/';
    }

    return $base . '/';
}

function sessao_usa_https() {
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    $protocoloEncaminhado = explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 2)[0];
    return strtolower(trim($protocoloEncaminhado)) === 'https';
}

function emitir_cookie_sessao($duracao = 0) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $parametros = session_get_cookie_params();
    $opcoes = [
        'expires' => $duracao < 0 ? time() - 42000 : ($duracao > 0 ? time() + $duracao : 0),
        'path' => $parametros['path'] !== '' ? $parametros['path'] : '/',
        'secure' => (bool) $parametros['secure'],
        'httponly' => true,
        'samesite' => $parametros['samesite'] !== '' ? $parametros['samesite'] : 'Strict',
    ];
    if ($parametros['domain'] !== '') {
        $opcoes['domain'] = $parametros['domain'];
    }

    return setcookie(session_name(), session_id(), $opcoes);
}

function configurar_sessao_persistente($ativada) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $_SESSION['manter_sessao'] = (bool) $ativada;
    $limites = configuracao_sessao();
    $duracao = $ativada ? $limites['session_remember_lifetime'] : 0;
    if ($ativada) {
        ini_set('session.gc_maxlifetime', (string) max($duracao, $limites['session_lifetime']));
    }

    if (!emitir_cookie_sessao($duracao)) {
        error_log('Não foi possível atualizar o cookie da sessão.');
        return false;
    }

    return true;
}

/**
 * Inicia uma sessão segura e atualiza o relógio de inatividade em cada pedido.
 */
function iniciar_sessao_segura() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $limites = configuracao_sessao();
    $https = sessao_usa_https();

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.cookie_secure', $https ? '1' : '0');
    ini_set('session.cookie_lifetime', '0');
    ini_set('session.gc_maxlifetime', (string) max(
        $limites['session_lifetime'],
        $limites['session_remember_lifetime']
    ));

    $cookie = [
        'lifetime' => 0,
        'path' => caminho_cookie_sessao(),
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ];
    $dominio = (string) config('session_cookie_domain', '');
    if ($dominio !== '') {
        $cookie['domain'] = $dominio;
    }
    session_set_cookie_params($cookie);

    if (!session_start()) {
        throw new RuntimeException('Não foi possível iniciar a sessão.');
    }

    $lembreteAtivo = !empty($_SESSION['manter_sessao']) && !empty($_SESSION['utilizador_id']);
    $limiteInatividade = $lembreteAtivo
        ? $limites['session_remember_lifetime']
        : $limites['session_lifetime'];

    if (isset($_SESSION['ultimo_acesso']) && is_numeric($_SESSION['ultimo_acesso'])) {
        $inatividade = time() - (int) $_SESSION['ultimo_acesso'];
        if ($inatividade > $limiteInatividade) {
            $_SESSION = [];
            emitir_cookie_sessao(-1);
            session_destroy();
            return;
        }
    }

    $_SESSION['ultimo_acesso'] = time();
    if ($lembreteAtivo && !emitir_cookie_sessao($limites['session_remember_lifetime'])) {
        error_log('Não foi possível renovar o cookie persistente da sessão.');
    }
}

function sessao_ativa_e_valida() {
    return session_status() === PHP_SESSION_ACTIVE
        && !empty($_SESSION['utilizador_id']);
}

/**
 * Regenera o ID após autenticação bem-sucedida.
 */
function regenerar_id_sessao() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['ultimo_acesso'] = time();
    }
}

function destruir_sessao() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];
    emitir_cookie_sessao(-1);
    session_destroy();
}
