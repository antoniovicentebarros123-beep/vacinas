<?php
$config = require 'config/database.php';

try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}", $config['username'], $config['password'], $config['options']);
    $stmt = $pdo->query("SELECT id, utilizador, nome, password_hash FROM utilizadores WHERE utilizador = 'admin'");
    echo "Usuário admin:\n";
    if ($row = $stmt->fetch()) {
        echo "ID: {$row['id']}\n";
        echo "Utilizador: {$row['utilizador']}\n";
        echo "Nome: {$row['nome']}\n";
        echo "Password Hash: {$row['password_hash']}\n";
        
        // Testar password_verify
        $password = 'admin123';
        $hash = $row['password_hash'];
        echo "\nTestando password_verify:\n";
        echo "Senha: {$password}\n";
        echo "Hash: {$hash}\n";
        echo "Verificação: " . (password_verify($password, $hash) ? 'OK' : 'FALHOU') . "\n";
    } else {
        echo "Usuário admin não encontrado!\n";
    }
} catch(PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>