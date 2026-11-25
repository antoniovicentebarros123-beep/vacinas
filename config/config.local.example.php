<?php
/**
 * Configuração local de exemplo
 * 
 * Copie este arquivo para config.local.php e ajuste os valores conforme necessário.
 */

return [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'vacinas_db',
    'username' => 'vacinas_app',
    'password' => '',                 // Preencher apenas na configuração local
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'modo_previa' => false,          // A faixa de pré-visualização não substitui a autenticação
    'nome_posto' => 'Centro Médico Hospitalar Maria de Fátima', // Valor documental; a interface lê configuracoes.nome_posto
    'display_errors' => false,       // Off por defeito
    'debug' => false,
    'session_lifetime' => 1800,      // Expiração por inatividade em segundos
    'session_remember_lifetime' => 604800, // Duração da opção "Manter sessão" em segundos
    'max_login_attempts' => 5,
    'login_lockout_time' => 900,     // Bloqueio temporário em segundos
];