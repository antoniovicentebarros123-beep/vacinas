<?php
/**
 * Cria a conta administradora inicial por linha de comandos.
 *
 * Utilização: php bin/criar_admin.php
 * A palavra-passe não é mostrada no final nem guardada em ficheiro.
 */

if (php_sapi_name() !== 'cli') {
    echo "Este script só pode ser executado em linha de comandos.\n";
    exit(1);
}

if (!defined('PASSWORD_ARGON2ID')) {
    echo "Erro: esta instalação de PHP não suporta Argon2id.\n";
    exit(1);
}

require __DIR__ . '/../app/core/helpers.php';

$config = config();

try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
} catch (PDOException $e) {
    error_log('Erro ao ligar à base de dados no criador de administrador: ' . $e->getMessage());
    echo "Erro ao ligar à base de dados. Verifique a configuração local.\n";
    exit(1);
}

function ler_linha_cli($pergunta) {
    echo $pergunta;
    $linha = readline();
    if ($linha === false) {
        echo "\nEntrada cancelada.\n";
        exit(1);
    }

    return trim($linha);
}

do {
    $utilizador = ler_linha_cli('Nome de utilizador: ');
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $utilizador)) {
        echo "Use entre 3 e 50 caracteres: letras, números, ponto, hífen ou sublinhado.\n";
    }
} while (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $utilizador));

$stmt = $pdo->prepare('SELECT id FROM utilizadores WHERE utilizador = ? LIMIT 1');
$stmt->execute([$utilizador]);
if ($stmt->fetch()) {
    echo "Erro: o nome de utilizador já existe.\n";
    exit(1);
}

do {
    $password = ler_linha_cli('Palavra-passe temporária (mínimo 8 caracteres): ');
    if (strlen($password) < 8) {
        echo "A palavra-passe deve ter pelo menos 8 caracteres.\n";
    }
} while (strlen($password) < 8);

do {
    $confirmacao = ler_linha_cli('Confirme a palavra-passe: ');
    if (!hash_equals($password, $confirmacao)) {
        echo "As palavras-passe não coincidem.\n";
    }
} while (!hash_equals($password, $confirmacao));

$passwordHash = password_hash($password, PASSWORD_ARGON2ID);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO utilizadores (nome, utilizador, password_hash, perfil, ativo, deve_mudar_password)
         VALUES (?, ?, ?, 'administrador', TRUE, TRUE)"
    );
    $stmt->execute([$utilizador, $utilizador, $passwordHash]);

    echo "\nConta administradora criada com sucesso.\n";
    echo 'ID: ' . $pdo->lastInsertId() . "\n";
    echo 'Utilizador: ' . $utilizador . "\n";
    echo "A palavra-passe terá de ser alterada no primeiro login.\n";
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        echo "Erro: o nome de utilizador já existe.\n";
    } else {
        error_log('Erro ao criar administrador: ' . $e->getMessage());
        echo "Erro ao criar a conta administradora.\n";
    }

    exit(1);
}
