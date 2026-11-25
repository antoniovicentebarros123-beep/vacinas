<?php
/**
 * Apoio comum aos testes HTTP das fases.
 */

function conexao_teste_root() {
    $config = require dirname(__DIR__) . '/config/database.php';
    $config['username'] = 'root';
    $config['password'] = getenv('VACINAS_TEST_ROOT_PASSWORD') ?: '';
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
    return new PDO($dsn, $config['username'], $config['password'], $config['options']);
}

function criar_utilizador_teste(PDO $pdo, $utilizador, $perfil, $password) {
    $stmt = $pdo->prepare(
        'INSERT INTO utilizadores (nome, utilizador, password_hash, perfil, ativo, deve_mudar_password)
         VALUES (?, ?, ?, ?, TRUE, FALSE)'
    );
    $stmt->execute([$utilizador, $utilizador, password_hash($password, PASSWORD_ARGON2ID), $perfil]);
    return (int) $pdo->lastInsertId();
}

function pedido_http_teste($url, $cookie, $post = null) {
    $cabecalhos = [];
    $curl = curl_init($url);
    $opcoes = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_HEADERFUNCTION => function ($ch, $linha) use (&$cabecalhos) {
            $cabecalhos[] = trim($linha);
            return strlen($linha);
        },
    ];
    if ($post !== null) {
        $opcoes[CURLOPT_POST] = true;
        $opcoes[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($curl, $opcoes);
    $corpo = curl_exec($curl);
    $resultado = [
        'status' => (int) curl_getinfo($curl, CURLINFO_HTTP_CODE),
        'cabecalhos' => implode("\n", $cabecalhos),
        'corpo' => $corpo === false ? '' : $corpo,
        'erro' => curl_error($curl),
    ];
    curl_close($curl);
    return $resultado;
}

function post_form_http_teste($base, $caminho, $cookie, array $dados) {
    $pagina = pedido_http_teste($base . '/' . $caminho, $cookie);
    $dados['csrf_token'] = csrf_do_html($pagina['corpo']);
    return pedido_http_teste($base . '/' . $caminho, $cookie, $dados);
}

function csrf_do_html($html) {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $matches)) {
        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

function login_http_teste($base, $utilizador, $password, $cookie) {
    $inicial = pedido_http_teste($base . '/login.php', $cookie);
    $csrf = csrf_do_html($inicial['corpo']);
    return pedido_http_teste($base . '/login.php', $cookie, [
        'utilizador' => $utilizador,
        'password' => $password,
        'csrf_token' => $csrf,
        'redirect' => '',
    ]);
}

function verificar_teste($condicao, $mensagem, &$falhas, &$total) {
    $total++;
    if (!$condicao) {
        $falhas++;
        fwrite(STDERR, "FALHA: {$mensagem}\n");
    }
}

function limpar_utilizador_teste(PDO $pdo, $id, $utilizador) {
    $stmt = $pdo->prepare('DELETE FROM login_tentativas WHERE utilizador = ?');
    $stmt->execute([$utilizador]);
    $stmt = $pdo->prepare('DELETE FROM auditoria WHERE utilizador_id = ?');
    $stmt->execute([$id]);
    $stmt = $pdo->prepare('DELETE FROM utilizadores WHERE id = ?');
    $stmt->execute([$id]);
}
