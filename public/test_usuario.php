<?php
// Diagnóstico desativado: não expõe configuração, hashes nem palavras-passe pela web.
http_response_code(403);
exit;

require __DIR__ . '/../app/core/helpers.php';
iniciar_sessao_segura();

$config = config();
echo "Configuração do banco de dados:\n";
print_r($config);

try {
    $pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}", $config['username'], $config['password'], $config['options']);
    echo "Conexão estabelecida com sucesso!\n";
    
    // Verificar se o usuário existe
    $utilizador = 'admin';
    $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE utilizador = ?");
    $stmt->execute([$utilizador]);
    $user = $stmt->fetch();
    
    echo "Usuário '$utilizador' encontrado: " . ($user ? "SIM" : "NÃO") . "\n";
    
    if ($user) {
        echo "Detalhes do usuário:\n";
        echo "ID: {$user['id']}\n";
        echo "Nome: {$user['nome']}\n";
        echo "Utilizador: {$user['utilizador']}\n";
        echo "Ativo: " . ($user['ativo'] ? 'SIM' : 'NÃO') . "\n";
        echo "Perfil: {$user['perfil']}\n";
        echo "Password Hash: {$user['password_hash']}\n";
        
        // Testar password_verify
        $password = 'admin123';
        $hash = $user['password_hash'];
        echo "\nTestando password_verify:\n";
        echo "Senha: $password\n";
        echo "Hash: $hash\n";
        echo "Verificação: " . (password_verify($password, $hash) ? 'OK' : 'FALHOU') . "\n";
    }
    
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>