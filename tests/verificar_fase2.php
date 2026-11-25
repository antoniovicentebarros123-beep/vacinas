<?php
/**
 * Testes de linha de comandos da Fase 2.
 * Usa uma conta temporária e remove-a no final.
 */

if (php_sapi_name() !== 'cli') {
    echo "Este teste só pode ser executado em linha de comandos.\n";
    exit(1);
}

// Mantém a saída em memória para que os testes de sessão não enviem cabeçalhos antes do session_start().
ob_start();
require __DIR__ . '/../app/core/helpers.php';

$verificacoes = 0;

function verificar_fase2($condicao, $mensagem) {
    global $verificacoes;

    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }

    $verificacoes++;
    echo "OK - {$mensagem}\n";
}

function requisicao_fase2($url, $campos, $cookieFile) {
    $ch = curl_init($url);
    $cabecalhos = '';

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HEADERFUNCTION => function ($ch, $linha) use (&$cabecalhos) {
            $cabecalhos .= $linha;
            return strlen($linha);
        },
    ]);

    if ($campos !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($campos));
    }

    $corpo = curl_exec($ch);
    if ($corpo === false) {
        $erro = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Falha HTTP: ' . $erro);
    }

    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return ['status' => $status, 'corpo' => $corpo, 'cabecalhos' => $cabecalhos];
}

function extrair_csrf_fase2($html) {
    return preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $html, $match) === 1
        ? $match[1]
        : '';
}

$config = config();
$utilizadorTeste = 'fase2_teste_' . bin2hex(random_bytes(4));
$utilizadorInexistente = $utilizadorTeste . '_inexistente';
$passwordTeste = 'Fase2!' . bin2hex(random_bytes(10));
$novaPasswordTeste = 'NovaFase2!' . bin2hex(random_bytes(10));
$baseUrl = rtrim((string) (getenv('APP_BASE_URL') ?: 'http://localhost/vacinacao-infantil-fase1a/vacinas/public/'), '/') . '/';
$cookieFile = tempnam(sys_get_temp_dir(), 'vacinas_fase2_');
$encarregadoId = null;
$criancaId = null;
$vacinacaoId = null;
$root = null;

try {
    verificar_fase2($config['username'] === 'vacinas_app', 'A aplicação usa vacinas_app e não root');
    verificar_fase2(is_string($config['password']) && $config['password'] !== '', 'A palavra-passe local de vacinas_app está configurada');
    verificar_fase2((bool) $config['display_errors'] === false, 'display_errors está desligado');

    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
    $app = new PDO($dsn, $config['username'], $config['password'], $config['options']);

    $rootConfig = [
        'host' => $config['host'],
        'port' => $config['port'],
        'database' => $config['database'],
        'charset' => $config['charset'],
    ];
    $root = new PDO(
        "mysql:host={$rootConfig['host']};port={$rootConfig['port']};dbname={$rootConfig['database']};charset={$rootConfig['charset']}",
        'root',
        (string) (getenv('DB_ROOT_PASSWORD') ?: ''),
        $config['options']
    );

    $tabelas = (int) $app->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
    )->fetchColumn();
    $vistas = (int) $app->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'VIEW'"
    )->fetchColumn();
    verificar_fase2($tabelas === 16, 'A base tem 16 tabelas');
    verificar_fase2($vistas === 2, 'A base tem 2 vistas');

    $fkSemRestrict = (int) $app->query(
        "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND DELETE_RULE <> 'RESTRICT'"
    )->fetchColumn();
    verificar_fase2($fkSemRestrict === 0, 'Todas as chaves estrangeiras usam ON DELETE RESTRICT');

    $checks = (int) $app->query(
        "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()"
    )->fetchColumn();
    verificar_fase2($checks >= 11, 'As restrições CHECK da Fase 2 estão aplicadas');

    $colunaEliminado = (int) $app->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contactos_faltosos'
           AND COLUMN_NAME = 'eliminado_em'"
    )->fetchColumn();
    verificar_fase2($colunaEliminado === 1, 'contactos_faltosos tem eliminado_em');

    $indiceCriancas = (int) $app->query(
        "SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'criancas'
           AND INDEX_NAME = 'idx_nome_nascimento'
         GROUP BY INDEX_NAME
         HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) = 'nome,data_nascimento'"
    )->fetchColumn();
    verificar_fase2($indiceCriancas === 1, 'Crianças usa o índice composto nome e data_nascimento');

    $semComentario = (int) $app->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
           AND TABLE_COMMENT = ''"
    )->fetchColumn();
    verificar_fase2($semComentario === 0, 'Todas as tabelas têm comentário em português');

    verificar_fase2((int) $app->query('SELECT COUNT(*) FROM vacinas')->fetchColumn() === 18, 'O seed tem 18 vacinas');
    verificar_fase2((int) $app->query('SELECT COUNT(*) FROM calendario_vacinal')->fetchColumn() === 37, 'O seed tem 37 doses');
    verificar_fase2((int) $app->query('SELECT COUNT(DISTINCT rotulo_idade) FROM calendario_vacinal')->fetchColumn() === 15, 'O seed tem 15 oportunidades');

    $clinicosNulos = (int) $app->query(
        'SELECT COUNT(*) FROM calendario_vacinal
         WHERE idade_minima_meses IS NULL
           AND intervalo_minimo_dias IS NULL
           AND dose_anterior_exigida IS NULL'
    )->fetchColumn();
    verificar_fase2($clinicosNulos === 37, 'Os valores clínicos não fornecidos ficam NULL');

    $alternativa = (int) $app->query(
        "SELECT COUNT(*) FROM calendario_vacinal
         WHERE grupo_alternativa = 'PENTA_OU_TETRA_18M'
           AND vacina_id IN (4, 15)"
    )->fetchColumn();
    verificar_fase2($alternativa === 2, 'Pentavalente e Tetravalente partilham o grupo alternativo correto');

    $grants = implode("\n", $app->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN));
    verificar_fase2(strpos($grants, 'SELECT, INSERT, UPDATE') !== false, 'vacinas_app tem SELECT, INSERT e UPDATE');
    verificar_fase2(strpos($grants, 'DELETE ON `vacinas_db`.`login_tentativas`') !== false, 'vacinas_app só tem DELETE em login_tentativas');
    verificar_fase2(strpos($grants, 'ALL PRIVILEGES') === false, 'vacinas_app não tem ALL PRIVILEGES');

    $admin = $app->query(
        "SELECT password_hash, deve_mudar_password FROM utilizadores
         WHERE utilizador = 'admin.rangel' LIMIT 1"
    )->fetch();
    verificar_fase2($admin !== false, 'O administrador inicial foi criado pelo CLI');
    verificar_fase2(strpos($admin['password_hash'], '$argon2id$') === 0, 'O administrador inicial usa Argon2id');
    verificar_fase2((int) $admin['deve_mudar_password'] === 1, 'O administrador inicial tem de mudar a palavra-passe');

    $stmt = $app->prepare('SELECT COUNT(*) FROM login_tentativas WHERE utilizador = ?');
    $stmt->execute([$utilizadorInexistente]);
    $antesInexistente = (int) $stmt->fetchColumn();

    $resultado = login($utilizadorInexistente, 'palavra-passe-incorreta');
    verificar_fase2($resultado['sucesso'] === false, 'Login de utilizador inexistente falha');

    $stmt->execute([$utilizadorInexistente]);
    $depoisInexistente = (int) $stmt->fetchColumn();
    verificar_fase2($depoisInexistente === $antesInexistente + 1, 'Falha de utilizador inexistente é registada');

    $hashBcrypt = password_hash($passwordTeste, PASSWORD_BCRYPT);
    $stmt = $app->prepare(
        "INSERT INTO utilizadores
         (nome, utilizador, password_hash, perfil, ativo, deve_mudar_password)
         VALUES (?, ?, ?, 'administrador', TRUE, TRUE)"
    );
    $stmt->execute(['Teste Fase 2', $utilizadorTeste, $hashBcrypt]);
    $utilizadorId = (int) $app->lastInsertId();

    for ($tentativa = 1; $tentativa <= 4; $tentativa++) {
        $resultado = login($utilizadorTeste, 'incorreta-' . $tentativa);
        verificar_fase2($resultado['sucesso'] === false && empty($resultado['bloqueado']), "Tentativa falhada {$tentativa} não bloqueia antes do limite");
    }

    $resultado = login($utilizadorTeste, 'incorreta-5');
    verificar_fase2(!empty($resultado['bloqueado']), 'A quinta tentativa falhada ativa o bloqueio');

    $stmt = $app->prepare('SELECT tentativas_falhadas, bloqueado_ate FROM utilizadores WHERE id = ?');
    $stmt->execute([$utilizadorId]);
    $estado = $stmt->fetch();
    verificar_fase2((int) $estado['tentativas_falhadas'] === 5, 'A contagem falhada chega a cinco');
    verificar_fase2($estado['bloqueado_ate'] !== null && $estado['bloqueado_ate'] > date('Y-m-d H:i:s'), 'bloqueado_ate fica no futuro');

    $resultado = login($utilizadorTeste, $passwordTeste);
    verificar_fase2($resultado['sucesso'] === false && !empty($resultado['bloqueado']), 'A password correta não entra durante o bloqueio');

    $stmt = $root->prepare(
        "UPDATE utilizadores SET bloqueado_ate = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?"
    );
    $stmt->execute([$utilizadorId]);

    $resultado = login($utilizadorTeste, $passwordTeste);
    verificar_fase2($resultado['sucesso'] === true, 'O login correto funciona depois de o bloqueio expirar');
    verificar_fase2(!empty($resultado['deve_mudar_password']), 'O login devolve deve_mudar_password');
    verificar_fase2(!empty($_SESSION['deve_mudar_password']), 'A sessão guarda a mudança obrigatória');

    $stmt = $app->prepare('SELECT password_hash, tentativas_falhadas, bloqueado_ate FROM utilizadores WHERE id = ?');
    $stmt->execute([$utilizadorId]);
    $depoisLogin = $stmt->fetch();
    verificar_fase2(strpos($depoisLogin['password_hash'], '$argon2id$') === 0, 'O hash bcrypt é rehashado para Argon2id');
    verificar_fase2((int) $depoisLogin['tentativas_falhadas'] === 0 && $depoisLogin['bloqueado_ate'] === null, 'O login válido repõe a contagem e o bloqueio');
    verificar_fase2(verificar_tentativas_login($utilizadorTeste) === false, 'Falhas antigas não voltam a bloquear após login válido');

    // Fluxo HTTP real com cookies e CSRF.
    $resposta = requisicao_fase2($baseUrl . 'login.php', null, $cookieFile);
    verificar_fase2($resposta['status'] === 200, 'A página de login responde HTTP 200');
    $csrf = extrair_csrf_fase2($resposta['corpo']);
    verificar_fase2($csrf !== '', 'O login fornece token CSRF');

    $resposta = requisicao_fase2($baseUrl . 'login.php', [
        'csrf_token' => 'token-invalido',
        'utilizador' => $utilizadorTeste,
        'password' => $passwordTeste,
    ], $cookieFile);
    verificar_fase2($resposta['status'] === 302, 'Login com CSRF inválido é rejeitado');

    $resposta = requisicao_fase2($baseUrl . 'login.php', null, $cookieFile);
    $csrf = extrair_csrf_fase2($resposta['corpo']);
    $resposta = requisicao_fase2($baseUrl . 'login.php', [
        'csrf_token' => $csrf,
        'utilizador' => $utilizadorTeste,
        'password' => $passwordTeste,
    ], $cookieFile);
    verificar_fase2($resposta['status'] === 302, 'Login HTTP correto redireciona');
    verificar_fase2(stripos($resposta['cabecalhos'], 'alterar_password.php') !== false, 'Primeiro acesso redireciona para alterar_password.php');

    $resposta = requisicao_fase2($baseUrl . 'alterar_password.php', null, $cookieFile);
    verificar_fase2($resposta['status'] === 200, 'A página de mudança obrigatória responde HTTP 200');
    verificar_fase2(strpos($resposta['corpo'], 'Guardar nova palavra-passe') !== false, 'O formulário de mudança obrigatória é apresentado');
    $csrf = extrair_csrf_fase2($resposta['corpo']);
    verificar_fase2($csrf !== '', 'A mudança obrigatória fornece token CSRF');

    $resposta = requisicao_fase2($baseUrl . 'alterar_password.php', [
        'csrf_token' => $csrf,
        'password' => 'curta',
        'password_confirmacao' => 'curta',
    ], $cookieFile);
    verificar_fase2($resposta['status'] === 200 && strpos($resposta['corpo'], 'pelo menos 8 caracteres') !== false, 'Password curta é rejeitada no servidor');

    $resposta = requisicao_fase2($baseUrl . 'alterar_password.php', [
        'csrf_token' => $csrf,
        'password' => $novaPasswordTeste,
        'password_confirmacao' => $novaPasswordTeste . '-diferente',
    ], $cookieFile);
    verificar_fase2($resposta['status'] === 200 && strpos($resposta['corpo'], 'não corresponde') !== false, 'Confirmação diferente é rejeitada no servidor');

    $resposta = requisicao_fase2($baseUrl . 'alterar_password.php', [
        'csrf_token' => $csrf,
        'password' => $novaPasswordTeste,
        'password_confirmacao' => $novaPasswordTeste,
    ], $cookieFile);
    verificar_fase2($resposta['status'] === 302 && stripos($resposta['cabecalhos'], 'painel.php') !== false, 'Mudança obrigatória concluída redireciona para o painel');

    $stmt = $app->prepare('SELECT deve_mudar_password, password_hash FROM utilizadores WHERE id = ?');
    $stmt->execute([$utilizadorId]);
    $aposAlteracao = $stmt->fetch();
    verificar_fase2((int) $aposAlteracao['deve_mudar_password'] === 0, 'deve_mudar_password fica desligado na base');
    verificar_fase2(password_verify($novaPasswordTeste, $aposAlteracao['password_hash']), 'A nova palavra-passe fica com hash Argon2id verificável');

    $auditoria = (int) $app->query(
        "SELECT COUNT(*) FROM auditoria
         WHERE entidade = 'utilizadores' AND entidade_id = {$utilizadorId}
           AND acao = 'alterar_password'"
    )->fetchColumn();
    verificar_fase2($auditoria === 1, 'A mudança de palavra-passe é auditada sem guardar hashes');

    $resposta = requisicao_fase2($baseUrl . 'login.php', null, $cookieFile);
    $csrf = extrair_csrf_fase2($resposta['corpo']);
    $resposta = requisicao_fase2($baseUrl . 'login.php', [
        'csrf_token' => $csrf,
        'utilizador' => $utilizadorTeste,
        'password' => $novaPasswordTeste,
        'redirect' => 'https://exemplo.externo/',
    ], $cookieFile);
    verificar_fase2($resposta['status'] === 302 && stripos($resposta['cabecalhos'], 'painel.php') !== false, 'Redirect externo é rejeitado e substituído pelo painel');

    $stmt = $root->prepare(
        "INSERT INTO encarregados (nome, parentesco, telefone, ativo)
         VALUES ('Encarregado Teste Fase 2', 'Mãe', '900000000', TRUE)"
    );
    $stmt->execute();
    $encarregadoId = (int) $root->lastInsertId();

    $stmt = $root->prepare(
        "INSERT INTO criancas
         (numero_processo, nome, data_nascimento, sexo, encarregado_id, ativo)
         VALUES (?, 'Criança Teste Fase 2', '2024-01-01', 'F', ?, TRUE)"
    );
    $stmt->execute(['F2-' . $utilizadorTeste, $encarregadoId]);
    $criancaId = (int) $root->lastInsertId();

    $stmt = $root->prepare(
        "INSERT INTO vacinacoes
         (crianca_id, vacina_id, calendario_id, numero_dose, data_administracao, origem)
         VALUES (?, 1, 1, 1, CURDATE(), 'posto')"
    );
    $stmt->execute([$criancaId]);
    $vacinacaoId = (int) $root->lastInsertId();

    $resposta = requisicao_fase2($baseUrl . 'painel.php', null, $cookieFile);
    verificar_fase2($resposta['status'] === 200, 'O painel autenticado responde HTTP 200');
    verificar_fase2(!preg_match('/(Warning|Notice|Fatal error)/i', $resposta['corpo']), 'O painel não apresenta warnings ou erros');
    verificar_fase2(preg_match('/<strong>1<\/strong>\s*<p>Crianças registadas<\/p>/', $resposta['corpo']) === 1, 'O painel mostra a contagem real de crianças');
    verificar_fase2(preg_match('/<strong>1<\/strong>\s*<p>Vacinações registadas<\/p>/', $resposta['corpo']) === 1, 'O painel mostra a contagem real de vacinações');

    $csrf = extrair_csrf_fase2($resposta['corpo']);
    $resposta = requisicao_fase2($baseUrl . 'logout.php', ['csrf_token' => $csrf], $cookieFile);
    verificar_fase2($resposta['status'] === 302 && stripos($resposta['cabecalhos'], 'login.php') !== false, 'Logout com CSRF redireciona para o login');
    $resposta = requisicao_fase2($baseUrl . 'painel.php', null, $cookieFile);
    verificar_fase2($resposta['status'] === 302 && stripos($resposta['cabecalhos'], 'login.php') !== false, 'Painel deixa de ser acessível depois do logout');

    destruir_sessao();
    $_SESSION = [];

    iniciar_sessao_segura();
    $_SESSION['utilizador_id'] = $utilizadorId;
    $_SESSION['ultimo_acesso'] = time() - (int) config('session_lifetime', 1800) - 2;
    session_write_close();
    iniciar_sessao_segura();
    verificar_fase2(sessao_ativa_e_valida() === false, 'A sessão expira por inatividade');
    $_SESSION = [];

    echo "\n{$verificacoes} verificações da Fase 2 concluídas sem falhas.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FALHA - ' . $e->getMessage() . "\n");
    $codigo = 1;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        destruir_sessao();
    }
    $_SESSION = [];

    if ($root instanceof PDO) {
        try {
            if ($vacinacaoId !== null) {
                $stmt = $root->prepare('DELETE FROM vacinacoes WHERE id = ?');
                $stmt->execute([$vacinacaoId]);
            }
            if ($criancaId !== null) {
                $stmt = $root->prepare('DELETE FROM criancas WHERE id = ?');
                $stmt->execute([$criancaId]);
            }
            if ($encarregadoId !== null) {
                $stmt = $root->prepare('DELETE FROM encarregados WHERE id = ?');
                $stmt->execute([$encarregadoId]);
            }
            $stmt = $root->prepare('DELETE FROM auditoria WHERE entidade_id = ? AND entidade = ?');
            $stmt->execute([$utilizadorId ?? 0, 'utilizadores']);
            $stmt = $root->prepare('DELETE FROM login_tentativas WHERE utilizador IN (?, ?)');
            $stmt->execute([$utilizadorTeste, $utilizadorInexistente]);
            $stmt = $root->prepare('DELETE FROM utilizadores WHERE utilizador = ?');
            $stmt->execute([$utilizadorTeste]);
        } catch (Throwable $e) {
            fwrite(STDERR, 'FALHA NA LIMPEZA - ' . $e->getMessage() . "\n");
            $codigo = 1;
        }
    }

    if (is_file($cookieFile)) {
        unlink($cookieFile);
    }
}

exit($codigo ?? 0);
