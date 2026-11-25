<?php
// Criação de contas pela web desativada; usar bin/criar_admin.php por CLI.
http_response_code(403);
exit;

require __DIR__ . '/../app/core/helpers.php';
iniciar_sessao_segura();

$config = config();

echo "<h1>Criando novo usuário administrador...</h1>";

try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}", $config['username'], $config['password'], $config['options']);
    echo "✅ Conexão com o banco de dados estabelecida.<br>";
    
    // Verificar se já existe um admin
    $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE utilizador = 'admin'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "� Já existe um usuário 'admin'.<br>";
        exit;
    }
    
    // Criar admin
    $utilizador = 'admin';
    $password = 'admin123';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $nome = 'Administrador';
    
    $stmt = $pdo->prepare("INSERT INTO utilizadores (nome, utilizador, password_hash, perfil, ativo, deve_mudar_password) VALUES (?, ?, ?, ?, TRUE, TRUE)");
    $stmt->execute([$nome, $utilizador, $passwordHash, 'administrador']);
    
    echo "✅ Usuário administrador criado com sucesso!<br>";
    echo "Usuário: {$utilizador}<br>";
    echo "Nome: {$nome}<br>";
    echo "Senha: {$password}<br>";
    echo "Importante: Altere a senha após o primeiro login!<br>";
    
    // Verificar se foi criado
    $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE utilizador = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<h2>✅ Verificação: Usuário encontrado na base de dados!</h2>";
        echo "ID: {$user['id']}<br>";
        echo "Nome: {$user['nome']}<br>";
        echo "Utilizador: {$user['utilizador']}<br>";
        echo "Ativo: " . ($user['ativo'] ? 'SIM' : 'NÃO') . "<br>";
        echo "Perfil: {$user['perfil']}<br>";
        
        // Testar password_verify
        if (password_verify($password, $user['password_hash'])) {
            echo "✅ Senha verificada com sucesso!<br>";
        } else {
            echo "❌ Falha na verificação da senha!<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}
?>