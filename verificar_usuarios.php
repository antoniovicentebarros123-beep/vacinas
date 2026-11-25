<?php
$config = require 'config/database.php';

try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}", $config['username'], $config['password'], $config['options']);
    $stmt = $pdo->query("SELECT utilizador, nome, perfil FROM utilizadores");
    echo "Usuários encontrados:\n";
    while($row = $stmt->fetch()) {
        echo "- {$row['utilizador']} ({$row['perfil']})\n";
    }
} catch(PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>