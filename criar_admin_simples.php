<?php
// Configuração do banco de dados
$config = require 'config/database.php';

try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}", $config['username'], $config['password'], $config['options']);
    echo "✅ Conexão com o banco de dados estabelecida.\n";
    
    // Verificar se já existe um admin
    $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE perfil = 'administrador'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "� Já existe um usuário administrador.\n";
        exit;
    }
    
    // Criar admin
    $utilizador = 'admin';
    $password = 'admin123';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO utilizadores (nome, utilizador, password_hash, perfil, ativo, deve_mudar_password) VALUES (?, ?, ?, ?, TRUE, TRUE)");
    $stmt->execute([$utilizador, $utilizador, $passwordHash, 'administrador']);
    
    echo "✅ Usuário administrador criado com sucesso!\n";
    echo "Usuário: {$utilizador}\n";
    echo "Senha: {$password}\n";
    echo "Importante: Altere a senha após o primeiro login!\n";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>