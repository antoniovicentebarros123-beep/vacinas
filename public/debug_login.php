<?php
// Diagnóstico desativado: não aceita credenciais nem mostra dados de autenticação.
http_response_code(403);
exit;

require __DIR__ . '/../app/core/helpers.php';
iniciar_sessao_segura();

echo "<h1>Debug Login</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $utilizador = $_POST['utilizador'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo "<h2>Dados recebidos:</h2>";
    echo "Utilizador: " . htmlspecialchars($utilizador) . "<br>";
    echo "Senha: " . htmlspecialchars($password) . "<br>";
    
    // Verificar tentativas falhas
    echo "<h2>Verificando tentativas:</h2>";
    $tentativasBloqueadas = verificar_tentativas_login($utilizador);
    echo "Tentativas bloqueadas: " . ($tentativasBloqueadas ? 'SIM' : 'NÃO') . "<br>";
    
    if (!$tentativasBloqueadas) {
        echo "<h2>Tentando login:</h2>";
        $resultado = login($utilizador, $password);
        echo "Resultado login: " . print_r($resultado, true) . "<br>";
        
        if ($resultado['sucesso']) {
            echo "<h3>Login SUCESSO!</h3>";
            echo "Redirecionando para painel...";
            header('Location: ' . url_base() . 'painel.php');
            exit;
        } else {
            echo "<h3>Login FALHOU!</h3>";
            echo "Mensagem: " . ($resultado['bloqueado'] ? 'Bloqueado' : 'Senha incorreta') . "<br>";
        }
    } else {
        echo "<h2>Usuário bloqueado!</h2>";
    }
}
?>

<form method="post">
    <input type="text" name="utilizador" placeholder="Utilizador" required>
    <input type="password" name="password" placeholder="Senha" required>
    <button type="submit">Testar Login</button>
</form>