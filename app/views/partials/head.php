<?php
/**
 * Partial: Cabeçalho HTML
 */

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\' data:; font-src \'self\';');

// Configurar sessão antes de iniciar
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', 0); // Mudar para 1 em HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.use_trans_sid', 0);

// Iniciar sessão com segurança
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Gerar token CSRF se não existir
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controlo de Vacinação Infantil</title>
    
    <?php if (is_preview_mode()): ?>
    <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    
    <link rel="stylesheet" href="<?php echo e(url_base() . 'assets/css/base.css'); ?>">
    <link rel="stylesheet" href="<?php echo e(url_base() . 'assets/css/componentes.css'); ?>">
    <link rel="stylesheet" href="<?php echo e(url_base() . 'assets/css/layout.css'); ?>">
    <?php if (isset($pagina_css) && $pagina_css): ?>
    <link rel="stylesheet" href="<?php echo e(url_base() . 'assets/css/paginas/' . $pagina_css . '.css'); ?>">
    <?php else: ?>
    <link rel="stylesheet" href="<?php echo e(url_base() . 'assets/css/paginas/login.css'); ?>">
    <?php endif; ?>
</head>
<body>
    <?php if (is_preview_mode()): ?>
    <div class="faixa-preview">
        PRÉ-VISUALIZAÇÃO: sem autenticação e sem dados reais
    </div>
    <?php endif; ?>