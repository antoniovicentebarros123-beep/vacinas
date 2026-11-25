<?php
/**
 * Partial: Cabeçalho HTML
 */

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\' data:; font-src \'self\';');
?>

<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $baseAssets = isset($baseCabecalho) ? $baseCabecalho : url_base();
    $tituloBase = $tituloDocumento ?? 'Controlo de Vacinação Infantil';
    $nomeInstituicao = nome_posto();
    $tituloCompleto = $nomeInstituicao !== '' ? $tituloBase . ' — ' . $nomeInstituicao : $tituloBase;
    ?>
    <title><?php echo e($tituloCompleto); ?></title>
    <meta name="theme-color" content="#0f5e73">
    
    <?php if (is_preview_mode()): ?>
    <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    
    <!-- Mesma ordem dos ficheiros CSS do index.html original da tag cloudflare-fase1a. -->
    <link rel="stylesheet" href="<?php echo e($baseAssets . 'assets/css/variaveis.css'); ?>">
    <link rel="stylesheet" href="<?php echo e($baseAssets . 'assets/css/base.css'); ?>">
    <link rel="stylesheet" href="<?php echo e($baseAssets . 'assets/css/icones.css'); ?>">
    <link rel="stylesheet" href="<?php echo e($baseAssets . 'assets/css/componentes.css'); ?>">
    <link rel="stylesheet" href="<?php echo e($baseAssets . 'assets/css/layout.css'); ?>">
    <link rel="stylesheet" href="<?php echo e($baseAssets . 'assets/css/paginas/login.css'); ?>">
    <link rel="stylesheet" href="<?php echo e($baseAssets . 'assets/css/paginas/painel.css'); ?>">
    <link rel="stylesheet" href="<?php echo e($baseAssets . 'assets/css/impressao.css'); ?>" media="print">
    <?php if (is_preview_mode()): ?>
    <link rel="stylesheet" href="<?php echo e($baseAssets . 'assets/css/previa.css'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo e(url_publica() . 'assets/css/modulos.css'); ?>">
</head>
<body>
    <?php if (is_preview_mode()): ?>
    <div class="faixa-preview">
        PRÉ-VISUALIZAÇÃO: sem autenticação e sem dados reais
    </div>
    <?php endif; ?>