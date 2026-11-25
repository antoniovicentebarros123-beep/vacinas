<?php
/**
 * Rotinas comuns aos módulos funcionais.
 */

require_once __DIR__ . '/helpers.php';

function conexao_app() {
    static $pdo = null;

    if ($pdo === null) {
        $config = config();
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
    }

    return $pdo;
}

function perfil_atual() {
    return (string) ($_SESSION['utilizador_perfil'] ?? '');
}

function tem_perfil(...$perfis) {
    return in_array(perfil_atual(), $perfis, true);
}

function requer_perfil(...$perfis) {
    if (!sessao_ativa_e_valida()) {
        header('Location: ' . url_publica() . 'login.php');
        exit;
    }

    if (!tem_perfil(...$perfis)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="pt-AO"><head><meta charset="UTF-8"><title>Acesso negado</title></head>';
        echo '<body><main><h1>Acesso negado</h1><p>O seu perfil não tem permissão para esta página.</p>';
        echo '<p><a href="' . e(url_publica() . 'painel.php') . '">Voltar ao painel</a></p></main></body></html>';
        exit;
    }
}

function exigir_post_csrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }

    $token = $_POST['csrf_token'] ?? null;
    if (!validate_csrf($token)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="pt-AO"><head><meta charset="UTF-8"><title>Pedido inválido</title></head>';
        echo '<body><main><h1>Pedido inválido</h1><p>O token de segurança é inválido ou expirou.</p></main></body></html>';
        exit;
    }
}

function mensagem_flash($tipo, $texto) {
    $_SESSION['mensagens_modulo'][] = [
        'tipo' => in_array($tipo, ['sucesso', 'erro', 'aviso'], true) ? $tipo : 'aviso',
        'texto' => (string) $texto,
    ];
}

function mensagens_modulo() {
    $mensagens = $_SESSION['mensagens_modulo'] ?? [];
    unset($_SESSION['mensagens_modulo']);
    return is_array($mensagens) ? $mensagens : [];
}

function redirecionar_modulo($caminho, $tipo = null, $texto = '') {
    if ($tipo !== null) {
        mensagem_flash($tipo, $texto);
    }
    header('Location: ' . url_publica() . ltrim($caminho, '/'));
    exit;
}

function registrar_auditoria(PDO $pdo, $entidade, $entidadeId, $acao, $campo = null, $anterior = null, $novo = null, $utilizadorId = null) {
    $stmt = $pdo->prepare(
        'INSERT INTO auditoria
         (entidade, entidade_id, acao, campo, valor_anterior, valor_novo, utilizador_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (string) $entidade,
        (int) $entidadeId,
        (string) $acao,
        $campo === null ? null : (string) $campo,
        $anterior === null ? null : (string) $anterior,
        $novo === null ? null : (string) $novo,
        $utilizadorId === null ? (int) ($_SESSION['utilizador_id'] ?? 0) : (int) $utilizadorId,
    ]);
}

function auditar_alteracoes(PDO $pdo, $entidade, $entidadeId, array $antes, array $depois, array $campos, $utilizadorId = null) {
    foreach ($campos as $campo) {
        $anterior = array_key_exists($campo, $antes) ? $antes[$campo] : null;
        $novo = array_key_exists($campo, $depois) ? $depois[$campo] : null;
        if ((string) $anterior !== (string) $novo) {
            registrar_auditoria($pdo, $entidade, $entidadeId, 'alterar', $campo, $anterior, $novo, $utilizadorId);
        }
    }
}

function texto_dentro_limite($valor, $limite) {
    return function_exists('mb_strlen') ? mb_strlen($valor, 'UTF-8') <= $limite : strlen($valor) <= $limite;
}

function normalizar_telefone($valor) {
    return preg_replace('/\D/', '', (string) $valor);
}

function data_iso_valida($valor) {
    if (!is_string($valor) || $valor === '') {
        return false;
    }
    $data = DateTime::createFromFormat('Y-m-d', $valor);
    return $data instanceof DateTime && $data->format('Y-m-d') === $valor;
}

function data_pt_simples($valor) {
    if (!is_string($valor) || $valor === '') {
        return '—';
    }
    $data = DateTime::createFromFormat('Y-m-d', $valor);
    if (!$data instanceof DateTime) {
        return e($valor);
    }
    return $data->format('d/m/Y');
}

function data_hora_pt_simples($valor) {
    if (!is_string($valor) || $valor === '') {
        return '—';
    }
    $data = DateTime::createFromFormat('Y-m-d H:i:s', $valor);
    if (!$data instanceof DateTime) {
        return e($valor);
    }
    return $data->format('d/m/Y H:i');
}

function idade_por_extenso_curta($dataNascimento, $dataReferencia = null) {
    if (!data_iso_valida($dataNascimento)) {
        return '—';
    }
    $nascimento = new DateTime($dataNascimento);
    $referencia = $dataReferencia instanceof DateTime ? clone $dataReferencia : new DateTime('today');
    if ($nascimento > $referencia) {
        return '—';
    }
    $meses = (($referencia->format('Y') - $nascimento->format('Y')) * 12)
        + ($referencia->format('n') - $nascimento->format('n'));
    if ($referencia->format('d') < $nascimento->format('d')) {
        $meses--;
    }
    $meses = max(0, $meses);
    if ($meses < 12) {
        return $meses . ($meses === 1 ? ' mês' : ' meses');
    }
    $anos = intdiv($meses, 12);
    return $anos . ($anos === 1 ? ' ano' : ' anos');
}

function rotulo_perfil($perfil) {
    $rotulos = [
        'administrador' => 'Administrador do posto',
        'enfermeiro' => 'Enfermeiro(a)',
        'recepcao' => 'Recepção',
        'responsavel_stock' => 'Responsável de stock',
    ];
    return $rotulos[$perfil] ?? $perfil;
}

function gerar_numero_processo(PDO $pdo) {
    $prefixo = strtoupper(trim(obter_configuracao_bd($pdo, 'prefixo_processo', 'RG')));
    if ($prefixo === '') {
        $prefixo = 'RG';
    }
    $stmt = $pdo->prepare(
        'SELECT numero_processo
         FROM criancas
         WHERE numero_processo LIKE ?
         ORDER BY LENGTH(numero_processo) DESC, numero_processo DESC
         LIMIT 1'
    );
    $stmt->execute([$prefixo . '%']);
    $ultimo = $stmt->fetch();
    $sequencia = 0;
    if ($ultimo && preg_match('/(\d+)$/', $ultimo['numero_processo'], $matches)) {
        $sequencia = (int) $matches[1];
    }
    return $prefixo . str_pad((string) ($sequencia + 1), 6, '0', STR_PAD_LEFT);
}

function item_menu_modulo($arquivo, $rotulo, $icone, $menuAtivo, array $perfis) {
    // O nome do argumento evita conflito com a variável local dos layouts incluídos.
    $url = url_publica() . $arquivo;
    if (!in_array(perfil_atual(), $perfis, true)) {
        return '<span class="item-menu menu-sem-acesso" aria-disabled="true">' . icone($icone) . e($rotulo) . '<small>Sem acesso</small></span>';
    }
    if (!file_exists(dirname(__DIR__, 2) . '/public/' . $arquivo)) {
        return '<span class="item-menu em-breve" aria-disabled="true">' . icone($icone) . e($rotulo) . '<small>Módulo indisponível</small></span>';
    }
    $atual = $menuAtivo === $arquivo ? ' aria-current="page"' : '';
    return '<a href="' . e($url) . '"' . $atual . '>' . icone($icone) . e($rotulo) . '</a>';
}
