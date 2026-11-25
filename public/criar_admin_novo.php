<?php
// Criação de contas pela web desativada; usar bin/criar_admin.php por CLI.
http_response_code(403);
exit;

require __DIR__ . '/../app/core/helpers.php';
iniciar_sessao_segura();

$config = config();

echo "Criando novo usuário administrador...\n";

try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}", $config['username'], $config['password'], $config['options']);
    echo "✅ Conexão com o banco de dados estabelecida.\n";
    
    // Verificar se já existe um admin
    $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE utilizador = 'admin'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "� Já existe um usuário 'admin'.\n";
        exit;
    }
    
    // Criar admin
    $utilizador = 'admin';
    $password = 'admin123';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $nome = 'Administrador';
    
    $stmt = $pdo->prepare("INSERT INTO utilizadores (nome, utilizador, password_hash, perfil, ativo, deve_mudar_password) VALUES (?, ?, ?, ?, TRUE, TRUE)");
    $stmt->execute([$nome, $utilizador, $passwordHash, 'administrador']);
    
    echo "✅ Usuário administrador criado com sucesso!\n";
    echo "Usuário: {$utilizador}\n";
    echo "Nome: {$nome}\n";
    echo "Senha: {$password}\n";
    echo "Importante: Altere a senha após o primeiro login!\n";
    
    // Verificar se foi criado
    $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE utilizador = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "\n✅ Verificação: Usuário encontrado na base de dados!\n";
        echo "ID: {$user['id']}\n";
        echo "Nome: {$user['nome']}\n";
        echo "Utilizador: {$user['utilizador']}\n";
        echo "Ativo: " . ($user['ativo'] ? 'SIM' : 'NÃO') . "\n";
        echo "Perfil: {$user['perfil']}\n";
        
        // Testar password_verify
        if (password_verify($password, $user['password_hash'])) {
            echo "✅ Senha verificada com sucesso!\n";
        } else {
            echo "❌ Falha na verificação da senha!\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>