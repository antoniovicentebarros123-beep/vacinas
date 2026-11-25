<?php
/**
 * Página de logout
 */

// Iniciar sessão ANTES de qualquer coisa
require __DIR__ . '/../app/core/helpers.php';
iniciar_sessao_segura();

// Processar logout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        // Token inválido, redirecionar para login
        header('Location: ' . url_base() . 'login.php');
        exit;
    }
    
    // Executar logout
    logout();
}

// Redirecionar para login após logout
header('Location: ' . url_base() . 'login.php');
exit;
?>