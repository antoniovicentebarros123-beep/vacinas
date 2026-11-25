<?php
/**
 * Configuração do banco de dados
 * 
 * Configuração para conexão com MySQL/MariaDB
 */

return [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'vacinas_db',
    'username' => 'vacinas_app',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
    ],
];