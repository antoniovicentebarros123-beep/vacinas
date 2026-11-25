<?php
/**
 * Arranque independente da área de gestão.
 */

require __DIR__ . '/../../app/core/modulos.php';
require_once __DIR__ . '/../../app/core/gestao.php';
ini_set('display_errors', config('display_errors', config('debug', false)) ? '1' : '0');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('VACINAS_GESTAO');
    iniciar_sessao_segura();
}

function url_gestao() {
    return url_base();
}

function redirecionar_gestao($caminho, $tipo = null, $texto = '') {
    if ($tipo !== null && session_status() !== PHP_SESSION_ACTIVE) {
        session_name('VACINAS_GESTAO');
        iniciar_sessao_segura();
    }
    if ($tipo !== null) {
        mensagem_flash($tipo, $texto);
    }
    header('Location: ' . url_gestao() . ltrim($caminho, '/'));
    exit;
}

function requer_gestao() {
    if (!sessao_ativa_e_valida()) {
        redirecionar_gestao('index.php');
    }
    if (!tem_perfil('administrador')) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="pt-AO"><head><meta charset="UTF-8"><title>Acesso negado</title></head>';
        echo '<body><main><h1>Acesso negado</h1><p>Esta área é exclusiva a administradores.</p></main></body></html>';
        exit;
    }
    $pdo = conexao_app();
    $stmt = $pdo->prepare('SELECT nome, perfil, ativo, deve_mudar_password FROM utilizadores WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['utilizador_id']]);
    $conta = $stmt->fetch();
    if (!$conta || (int) $conta['ativo'] !== 1 || $conta['perfil'] !== 'administrador') {
        logout();
        redirecionar_gestao('index.php');
    }
    $_SESSION['utilizador_nome'] = (string) $conta['nome'];
    $_SESSION['utilizador_perfil'] = 'administrador';
    $_SESSION['deve_mudar_password'] = (bool) $conta['deve_mudar_password'];
    $scriptAtual = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($_SESSION['deve_mudar_password'] && $scriptAtual !== 'alterar_password.php' && $scriptAtual !== 'logout.php') {
        redirecionar_gestao('alterar_password.php');
    }
    return $pdo;
}
