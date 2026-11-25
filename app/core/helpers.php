<?php
/**
 * Helpers da aplicação
 */

// Função para escapar saída HTML (similar ao e() do Laravel)
function e($string, $flags = ENT_QUOTES | ENT_HTML5, $encoding = 'UTF-8') {
    return htmlspecialchars($string, $flags, $encoding);
}

// Função para obter a URL base da aplicação
function url_base() {
    // SCRIPT_NAME retorna o caminho completo do script
    $script_name = $_SERVER['SCRIPT_NAME'];
    // Extrai o diretório do script
    $dir = dirname($script_name);
    // Mantém public/ no caminho para acessar assets
    // Garante que começa e termina com barra única
    $base = rtrim($dir, '/') . '/';
    
    // Se for raiz, usa '/'
    return $base === '/' ? '/' : $base;
}

// Mantém os recursos e redirects em public/ mesmo a partir de subpastas como /gestao/.
function url_publica() {
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $pos = strrpos($script, '/public/');
    if ($pos !== false) {
        return substr($script, 0, $pos + strlen('/public/'));
    }
    return url_base();
}

// Função para obter configuração
function config($key = null, $default = null) {
    static $config = null;
    
    if ($config === null) {
        $config = [];
        
        // Carregar arquivo de configuração do banco de dados
        $dbConfigFile = __DIR__ . '/../../config/database.php';
        if (file_exists($dbConfigFile)) {
            $config = array_merge($config, require $dbConfigFile);
        }
        
        // Carregar arquivo de configuração local se existir
        $localConfigFile = __DIR__ . '/../../config/config.local.php';
        if (file_exists($localConfigFile)) {
            $config = array_merge($config, require $localConfigFile);
        } else {
            // Carregar configuração padrão
            $localExampleConfigFile = __DIR__ . '/../../config/config.local.example.php';
            if (file_exists($localExampleConfigFile)) {
                $config = array_merge($config, require $localExampleConfigFile);
            }
        }
    }
    
    if ($key === null) {
        return $config;
    }
    
    return $config[$key] ?? $default;
}

// O nome da instituição é sempre lido da base de dados, sem fallback fixo.
function nome_posto() {
    static $nome = null;

    if ($nome === null) {
        try {
            $config = config();
            $pdo = function_exists('conexao_app')
                ? conexao_app()
                : new PDO(
                    "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}",
                    $config['username'],
                    $config['password'],
                    $config['options']
                );
            $stmt = $pdo->prepare('SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1');
            $stmt->execute(['nome_posto']);
            $valor = $stmt->fetchColumn();
            $nome = $valor === false ? '' : (string) $valor;
        } catch (PDOException $e) {
            error_log('Erro ao obter o nome da instituição na base de dados.');
            $nome = '';
        }
    }

    return $nome;
}

// Função para verificar se está em modo pré-visualização
function is_preview_mode() {
    return (bool) config('modo_previa', false);
}

// Funções CSRF
function csrf_token() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function validate_csrf($token) {
    return session_status() === PHP_SESSION_ACTIVE
        && is_string($token)
        && !empty($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// Carregar função de sessão
require_once __DIR__ . '/sessao.php';


// Função para formatar data em português
function formatar_data_pt($date) {
    $data = new DateTime($date);
    $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    
    $diaSemana = $dias[(int)$data->format('w')];
    $dia = (int)$data->format('d');
    $mes = $meses[(int)$data->format('n') - 1];
    $ano = $data->format('Y');
    
    return strtolower("{$diaSemana}, {$dia} de {$mes}");
}

// Função para obter saudação
function saudacao($nome = null) {
    $hora = (int)date('H');
    $forma = $hora < 12 ? 'Bom dia' : ($hora < 19 ? 'Boa tarde' : 'Boa noite');
    
    if ($nome) {
        return $forma . ', ' . explode(' ', $nome)[0];
    }
    
    return $forma;
}

/** Função para gerar ícone Lucide (classe CSS .icone) */
function icone($nome) {
    return '<span class="icone icone-' . htmlspecialchars($nome) . '" aria-hidden="true"></span>';
}

function requer_login() {
    if (!sessao_ativa_e_valida()) {
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header('Location: ' . url_base() . 'login.php?redirect=' . $redirect);
        exit;
    }

    $scriptAtual = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!empty($_SESSION['deve_mudar_password']) && $scriptAtual !== 'alterar_password.php') {
        header('Location: ' . url_base() . 'alterar_password.php');
        exit;
    }
}

function obter_configuracao_bd(PDO $pdo, $chave, $default) {
    $stmt = $pdo->prepare('SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1');
    $stmt->execute([$chave]);
    $configuracao = $stmt->fetch();

    return $configuracao ? (string) $configuracao['valor'] : $default;
}

function parametros_login(PDO $pdo) {
    return [
        'max_tentativas' => max(1, (int) obter_configuracao_bd($pdo, 'max_login_attempts', config('max_login_attempts', 5))),
        'tempo_bloqueio' => max(1, (int) obter_configuracao_bd($pdo, 'login_lockout_time', config('login_lockout_time', 900))),
    ];
}

function registrar_tentativa_login(PDO $pdo, $utilizador, $sucesso) {
    $stmt = $pdo->prepare('INSERT INTO login_tentativas (utilizador, sucesso, ip) VALUES (?, ?, ?)');
    $stmt->execute([$utilizador, (bool) $sucesso, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
}

function login($utilizador, $password, $manterSessao = false) {
    if (!is_string($utilizador) || !is_string($password)) {
        return ['sucesso' => false, 'erro' => 'Erro interno'];
    }

    $utilizador = trim($utilizador);
    if ($utilizador === '' || $password === '') {
        return ['sucesso' => false, 'senha_incorreta' => true];
    }

    try {
        $config = config();
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        $parametros = parametros_login($pdo);

        $stmt = $pdo->prepare(
            'SELECT id, nome, utilizador, password_hash, perfil, ativo, deve_mudar_password,
                    tentativas_falhadas, bloqueado_ate
             FROM utilizadores
             WHERE utilizador = ?
             LIMIT 1'
        );
        $stmt->execute([$utilizador]);
        $user = $stmt->fetch();

        if (!$user || (int) $user['ativo'] !== 1) {
            registrar_tentativa_login($pdo, $utilizador, false);
            return ['sucesso' => false, 'usuario_inexistente' => true];
        }

        $agora = date('Y-m-d H:i:s');
        if ($user['bloqueado_ate'] !== null && $user['bloqueado_ate'] > $agora) {
            registrar_tentativa_login($pdo, $utilizador, false);
            return ['sucesso' => false, 'bloqueado' => true];
        }

        // Findo o período de bloqueio, a contagem consecutiva recomeça.
        if ($user['bloqueado_ate'] !== null && $user['bloqueado_ate'] <= $agora) {
            $stmt = $pdo->prepare('UPDATE utilizadores SET tentativas_falhadas = 0, bloqueado_ate = NULL WHERE id = ?');
            $stmt->execute([$user['id']]);
            $user['tentativas_falhadas'] = 0;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $novasTentativas = (int) $user['tentativas_falhadas'] + 1;
            $bloqueadoAte = null;

            if ($novasTentativas >= $parametros['max_tentativas']) {
                $bloqueadoAte = date('Y-m-d H:i:s', time() + $parametros['tempo_bloqueio']);
            }

            $stmt = $pdo->prepare('UPDATE utilizadores SET tentativas_falhadas = ?, bloqueado_ate = ? WHERE id = ?');
            $stmt->execute([$novasTentativas, $bloqueadoAte, $user['id']]);
            registrar_tentativa_login($pdo, $utilizador, false);

            return [
                'sucesso' => false,
                'senha_incorreta' => true,
                'bloqueado' => $bloqueadoAte !== null,
            ];
        }

        $stmt = $pdo->prepare(
            'UPDATE utilizadores
             SET tentativas_falhadas = 0, bloqueado_ate = NULL, ultimo_login = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$user['id']]);

        // Contas antigas migram para Argon2id após um login válido.
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
            $novoHash = password_hash($password, PASSWORD_ARGON2ID);
            $stmt = $pdo->prepare('UPDATE utilizadores SET password_hash = ? WHERE id = ?');
            $stmt->execute([$novoHash, $user['id']]);
        }

        iniciar_sessao_segura();
        regenerar_id_sessao();
        $_SESSION['utilizador_id'] = $user['id'];
        $_SESSION['utilizador_nome'] = $user['nome'];
        $_SESSION['utilizador_perfil'] = $user['perfil'];
        $_SESSION['deve_mudar_password'] = (bool) $user['deve_mudar_password'];
        $_SESSION['login_em'] = time();
        $_SESSION['ultimo_acesso'] = time();
        if (!configurar_sessao_persistente($manterSessao)) {
            throw new RuntimeException('Não foi possível guardar a preferência de duração da sessão.');
        }

        registrar_tentativa_login($pdo, $utilizador, true);

        return [
            'sucesso' => true,
            'deve_mudar_password' => (bool) $user['deve_mudar_password'],
        ];
    } catch (PDOException $e) {
        error_log('Erro no login: ' . $e->getMessage());
        return ['sucesso' => false, 'erro' => 'Erro interno'];
    }
}

function logout() {
    destruir_sessao();
}

function verificar_tentativas_login($utilizador) {
    if (!is_string($utilizador) || trim($utilizador) === '') {
        return false;
    }

    try {
        $config = config();
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        // O bloqueio é consecutivo: um login válido repõe a contagem.
        // Por isso, a verificação usa o estado da conta e não o histórico append-only.
        $stmt = $pdo->prepare(
            'SELECT id
             FROM utilizadores
             WHERE utilizador = ? AND ativo = 1
               AND bloqueado_ate IS NOT NULL AND bloqueado_ate > NOW()
             LIMIT 1'
        );
        $stmt->execute([trim($utilizador)]);

        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log('Erro ao verificar tentativas: ' . $e->getMessage());
        return false;
    }
}