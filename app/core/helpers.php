<?php
/**
 * Helpers da aplicação
 */

// Função para escapar saída HTML (similar ao e() do Laravel)
function e($string, $flags = ENT_QUOTES | ENT_HTML5, $encoding = 'UTF-8') {
    return htmlspecialchars($string, $flags, $encoding);
}

// Função para obter a URL base da aplicação
function url_base() {
    // SCRIPT_NAME retorna o caminho completo do script
    $script_name = $_SERVER['SCRIPT_NAME'];
    // Extrai o diretório do script
    $dir = dirname($script_name);
    // Mantém public/ no caminho para acessar assets
    // Garante que começa e termina com barra única
    $base = rtrim($dir, '/') . '/';
    
    // Se for raiz, usa '/'
    return $base === '/' ? '/' : $base;
}

// Função para obter configuração
function config($key = null, $default = null) {
    static $config = null;
    
    if ($config === null) {
        // Carregar arquivo de configuração local se existir
        $localConfigFile = __DIR__ . '/../../config/config.local.php';
        if (file_exists($localConfigFile)) {
            $config = require $localConfigFile;
        } else {
            // Carregar configuração padrão
            $config = require __DIR__ . '/../../config/config.local.example.php';
        }
    }
    
    if ($key === null) {
        return $config;
    }
    
    return $config[$key] ?? $default;
}

// Função para verificar se está em modo pré-visualização
function is_preview_mode() {
    $modoPrevia = config('modo_previa', false);
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Verificar se é localhost IPv4 ou IPv6
    return $modoPrevia && ($remoteAddr === '127.0.0.1' || $remoteAddr === '::1');
}

// Função para gerar token CSRF
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

// Função para validar token CSRF
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Função para formatar data em português
function formatar_data_pt($date) {
    $data = new DateTime($date);
    return $data->format('d \d\e F \d\e Y');
}

// Função para obter saudação
function saudacao($nome = null) {
    $hora = (int)date('H');
    $forma = $hora < 12 ? 'Bom dia' : ($hora < 19 ? 'Boa tarde' : 'Boa noite');
    
    if ($nome) {
        return $forma . ', ' . explode(' ', $nome)[0];
    }
    
    return $forma;
}