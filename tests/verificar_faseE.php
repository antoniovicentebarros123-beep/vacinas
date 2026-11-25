<?php
/**
 * Testes da Fase E: lotes, movimentos, saldo calculado, alertas, CSRF e perfis.
 */

require __DIR__ . '/suporte_http.php';

function id_apartir_redirecionamento_teste(array $resposta) {
    return preg_match('/stock_lote_detalhe\.php\?id=(\d+)/i', $resposta['cabecalhos'], $matches) === 1
        ? (int) $matches[1] : 0;
}
function post_lote_teste($base, $cookie, array $dados) {
    $pagina = pedido_http_teste($base . '/stock_lote_form.php' . ($dados['id'] ?? 0 ? '?id=' . (int) $dados['id'] : ''), $cookie);
    if (!isset($dados['csrf_token'])) $dados['csrf_token'] = csrf_do_html($pagina['corpo']);
    return pedido_http_teste($base . '/stock_lote_form.php', $cookie, $dados);
}
function post_movimento_teste($base, $cookie, array $dados) {
    $pagina = pedido_http_teste($base . '/stock_movimento_form.php?lote_id=' . (int) $dados['lote_id'], $cookie);
    $dados['csrf_token'] = csrf_do_html($pagina['corpo']);
    return pedido_http_teste($base . '/stock_movimento.php', $cookie, $dados);
}
function saldo_teste(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT saldo_atual FROM stock_saldo_lotes WHERE id = ?');
    $stmt->execute([(int) $id]);
    return (int) $stmt->fetchColumn();
}
function item_menu_stock_teste($html) {
    preg_match_all('/<li>(.*?)<\/li>/s', $html, $matches);
    foreach ($matches[1] as $item) {
        if (strpos($item, 'Stock') !== false) return $item;
    }
    return '';
}

$base = 'http://localhost/vacinacao-infantil-fase1a/vacinas/public';
$sufixo = strtoupper(bin2hex(random_bytes(3)));
$password = 'FaseE-' . bin2hex(random_bytes(8)) . '!';
$pasta = sys_get_temp_dir() . '/vacinas_fasee_' . strtolower($sufixo);
mkdir($pasta);
$cookies = [];
$utilizadores = [];
$lotesIds = [];
$marcador = 'LOTEE' . $sufixo;
$falhas = 0;
$total = 0;
$pdo = conexao_teste_root();

try {
    $perfis = ['rec' => 'recepcao', 'enf' => 'enfermeiro', 'sto' => 'responsavel_stock', 'adm' => 'administrador'];
    foreach ($perfis as $abrev => $perfil) {
        $nome = 'fe_' . strtolower($abrev) . '_' . strtolower($sufixo);
        $id = criar_utilizador_teste($pdo, $nome, $perfil, $password);
        $utilizadores[$abrev] = ['id' => $id, 'nome' => $nome];
        $cookies[$abrev] = $pasta . '/' . $abrev . '.txt';
        $login = login_http_teste($base, $nome, $password, $cookies[$abrev]);
        verificar_teste($login['status'] === 302 && strpos($login['cabecalhos'], 'painel.php') !== false, "login HTTP do perfil {$perfil}", $falhas, $total);
    }
    $cookies['anonimo'] = $pasta . '/anonimo.txt';

    foreach (['stock.php', 'stock_lote_form.php', 'stock_movimento_form.php'] as $pagina) {
        $resposta = pedido_http_teste($base . '/' . $pagina, $cookies['anonimo']);
        verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'login.php') !== false, "{$pagina} exige sessão", $falhas, $total);
    }
    foreach (['enf', 'sto', 'adm'] as $abrev) {
        $resposta = pedido_http_teste($base . '/stock.php', $cookies[$abrev]);
        verificar_teste($resposta['status'] === 200, "perfil {$abrev} consulta stock", $falhas, $total);
        verificar_teste(strpos($resposta['corpo'], 'Warning') === false && strpos($resposta['corpo'], 'Fatal error') === false, "HTML de stock sem erros para {$abrev}", $falhas, $total);
        verificar_teste((strpos($resposta['corpo'], 'Novo lote') !== false) === ($abrev === 'sto'), "escrita visível apenas para responsável de stock: {$abrev}", $falhas, $total);
    }
    $resposta = pedido_http_teste($base . '/stock.php', $cookies['rec']);
    verificar_teste($resposta['status'] === 403, 'recepção não acede a stock', $falhas, $total);
    foreach (['enf', 'adm', 'rec'] as $abrev) {
        foreach (['stock_lote_form.php', 'stock_movimento_form.php'] as $pagina) {
            $resposta = pedido_http_teste($base . '/' . $pagina, $cookies[$abrev]);
            verificar_teste($resposta['status'] === 403, "perfil {$abrev} não abre {$pagina}", $falhas, $total);
        }
    }

    $dadosLote = [
        'id' => 0,
        'vacina_id' => 1,
        'numero_lote' => '  ' . $marcador . 'a  ',
        'validade' => date('Y-m-d', strtotime('+20 days')),
        'fabricante' => 'Fabricante <b>fictício</b> ' . $sufixo,
        'quantidade_inicial' => '5',
    ];
    $resposta = post_lote_teste($base, $cookies['sto'], array_merge($dadosLote, ['csrf_token' => 'invalido']));
    verificar_teste($resposta['status'] === 403, 'criação de lote rejeita CSRF inválido', $falhas, $total);
    $resposta = post_lote_teste($base, $cookies['enf'], $dadosLote);
    verificar_teste($resposta['status'] === 403, 'enfermeiro não cria lote', $falhas, $total);
    $resposta = post_lote_teste($base, $cookies['sto'], $dadosLote);
    $loteId = id_apartir_redirecionamento_teste($resposta);
    if ($loteId > 0) $lotesIds[] = $loteId;
    verificar_teste($resposta['status'] === 302 && $loteId > 0, 'responsável de stock cria lote', $falhas, $total);
    $stmt = $pdo->prepare('SELECT * FROM stock_lotes WHERE id = ?');
    $stmt->execute([$loteId]);
    $lote = $stmt->fetch();
    verificar_teste(($lote['numero_lote'] ?? '') === $marcador . 'A', 'número do lote é normalizado com trim e maiúsculas', $falhas, $total);
    verificar_teste((int) ($lote['criado_por_id'] ?? 0) === (int) $utilizadores['sto']['id'], 'lote guarda o responsável pela criação', $falhas, $total);
    verificar_teste(saldo_teste($pdo, $loteId) === 5, 'entrada inicial produz saldo calculado de cinco doses', $falhas, $total);
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM stock_movimentos WHERE lote_id = ? AND tipo = 'entrada'");
    $stmt->execute([$loteId]);
    verificar_teste((int) $stmt->fetch()['total'] === 1, 'quantidade inicial gera movimento de entrada', $falhas, $total);

    $resposta = post_lote_teste($base, $cookies['sto'], $dadosLote);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Já existe um lote ativo') !== false, 'lote duplicado da mesma vacina é recusado', $falhas, $total);
    $resposta = post_lote_teste($base, $cookies['sto'], array_merge($dadosLote, ['quantidade_inicial' => '-1']));
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'não negativo') !== false, 'quantidade inicial negativa é recusada', $falhas, $total);

    $dadosEdicao = array_merge($dadosLote, [
        'id' => $loteId, 'numero_lote' => $marcador . 'B', 'validade' => date('Y-m-d', strtotime('+1 year')),
        'fabricante' => 'Fabricante fictício atualizado ' . $sufixo,
    ]);
    $resposta = post_lote_teste($base, $cookies['sto'], $dadosEdicao);
    verificar_teste($resposta['status'] === 302 && id_apartir_redirecionamento_teste($resposta) === $loteId, 'responsável de stock edita lote', $falhas, $total);
    $resposta = post_lote_teste($base, $cookies['sto'], array_merge($dadosEdicao, ['vacina_id' => 2]));
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'não pode ser alterada') !== false, 'vacina do lote é imutável', $falhas, $total);
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM auditoria WHERE entidade = 'stock_lotes' AND entidade_id = ? AND acao IN ('criar', 'alterar')");
    $stmt->execute([$loteId]);
    verificar_teste((int) $stmt->fetch()['total'] >= 3, 'criação e alterações do lote são auditadas por campo', $falhas, $total);

    $resposta = pedido_http_teste($base . '/stock_lote_detalhe.php?id=' . $loteId, $cookies['enf']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Fabricante fictício atualizado') !== false, 'enfermeiro consulta detalhe do lote', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], 'Editar lote') === false && strpos($resposta['corpo'], '>Movimento<') === false, 'detalhe não oferece escrita ao enfermeiro', $falhas, $total);
    $resposta = pedido_http_teste($base . '/stock.php?q=' . rawurlencode($marcador), $cookies['adm']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], $marcador . 'B') !== false, 'pesquisa de lotes funciona para administrador', $falhas, $total);

    $resposta = pedido_http_teste($base . '/stock_movimento.php', $cookies['sto']);
    verificar_teste($resposta['status'] === 405, 'endpoint de movimento só aceita POST', $falhas, $total);
    $resposta = post_movimento_teste($base, $cookies['sto'], ['lote_id' => $loteId, 'tipo' => 'entrada', 'quantidade' => '0', 'motivo' => '']);
    verificar_teste($resposta['status'] === 302, 'quantidade zero é recusada com redirecionamento e aviso', $falhas, $total);
    verificar_teste(saldo_teste($pdo, $loteId) === 5, 'movimento inválido não altera saldo', $falhas, $total);
    $resposta = post_movimento_teste($base, $cookies['sto'], ['lote_id' => $loteId, 'tipo' => 'perda', 'quantidade' => '2', 'motivo' => '']);
    verificar_teste(strpos($resposta['cabecalhos'], 'stock_movimento_form.php') !== false, 'perda sem motivo é redirecionada ao formulário', $falhas, $total);
    verificar_teste(saldo_teste($pdo, $loteId) === 5, 'perda sem motivo não altera saldo', $falhas, $total);
    $resposta = post_movimento_teste($base, $cookies['enf'], ['lote_id' => $loteId, 'tipo' => 'entrada', 'quantidade' => '10', 'motivo' => '']);
    verificar_teste($resposta['status'] === 403, 'enfermeiro não regista movimentos', $falhas, $total);
    $resposta = post_movimento_teste($base, $cookies['sto'], ['lote_id' => $loteId, 'tipo' => 'saida_vacinacao', 'quantidade' => '1', 'motivo' => '']);
    verificar_teste(strpos($resposta['cabecalhos'], 'stock_movimento_form.php') !== false, 'saída por vacinação não é permitida no formulário manual', $falhas, $total);

    $movimentos = [
        ['entrada', '4', 'Entrada fictícia de teste', 9],
        ['perda', '1', 'Perda fictícia de teste', 8],
        ['ajuste_mais', '2', 'Ajuste positivo fictício de teste', 10],
        ['ajuste_menos', '3', 'Ajuste negativo fictício de teste', 7],
    ];
    foreach ($movimentos as [$tipo, $quantidade, $motivo, $esperado]) {
        $resposta = post_movimento_teste($base, $cookies['sto'], ['lote_id' => $loteId, 'tipo' => $tipo, 'quantidade' => $quantidade, 'motivo' => $motivo]);
        verificar_teste($resposta['status'] === 302 && id_apartir_redirecionamento_teste($resposta) === $loteId, "movimento {$tipo} é registado", $falhas, $total);
        verificar_teste(saldo_teste($pdo, $loteId) === $esperado, "saldo após {$tipo} é {$esperado}", $falhas, $total);
    }
    $resposta = post_movimento_teste($base, $cookies['sto'], ['lote_id' => $loteId, 'tipo' => 'ajuste_menos', 'quantidade' => '99', 'motivo' => 'Teste de saldo negativo']);
    verificar_teste(strpos($resposta['cabecalhos'], 'stock_movimento_form.php') !== false, 'movimento que produziria saldo negativo é recusado', $falhas, $total);
    verificar_teste(saldo_teste($pdo, $loteId) === 7, 'saldo permanece sete após tentativa negativa', $falhas, $total);
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM stock_movimentos WHERE lote_id = ?');
    $stmt->execute([$loteId]);
    verificar_teste((int) $stmt->fetch()['total'] === 5, 'histórico contém apenas movimentos válidos e a entrada inicial', $falhas, $total);
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM stock_movimentos WHERE lote_id = ? AND utilizador_id = ?');
    $stmt->execute([$loteId, $utilizadores['sto']['id']]);
    verificar_teste((int) $stmt->fetch()['total'] === 5, 'movimentos guardam o responsável', $falhas, $total);

    $dadosAlerta = $dadosLote;
    $dadosAlerta['id'] = 0;
    $dadosAlerta['numero_lote'] = $marcador . 'ALERTA';
    $dadosAlerta['quantidade_inicial'] = '3';
    $resposta = post_lote_teste($base, $cookies['sto'], $dadosAlerta);
    $loteAlertaId = id_apartir_redirecionamento_teste($resposta);
    if ($loteAlertaId > 0) $lotesIds[] = $loteAlertaId;
    verificar_teste($loteAlertaId > 0, 'lote de teste para alertas é criado', $falhas, $total);
    $resposta = pedido_http_teste($base . '/stock_lote_detalhe.php?id=' . $loteAlertaId, $cookies['sto']);
    verificar_teste(strpos($resposta['corpo'], 'Stock baixo') !== false && strpos($resposta['corpo'], '30 dias') !== false && strpos($resposta['corpo'], '5 doses') !== false, 'alerta de stock baixo usa limiares configurados', $falhas, $total);
    $dadosAlerta['numero_lote'] = $marcador . 'VENCIDO';
    $dadosAlerta['validade'] = date('Y-m-d', strtotime('-1 day'));
    $dadosAlerta['quantidade_inicial'] = '20';
    $resposta = post_lote_teste($base, $cookies['sto'], $dadosAlerta);
    $loteVencidoId = id_apartir_redirecionamento_teste($resposta);
    if ($loteVencidoId > 0) $lotesIds[] = $loteVencidoId;
    $resposta = pedido_http_teste($base . '/stock_lote_detalhe.php?id=' . $loteVencidoId, $cookies['sto']);
    verificar_teste(strpos($resposta['corpo'], 'Vencido') !== false && strpos($resposta['corpo'], 'não estão disponíveis') !== false, 'lote vencido tem alerta e indica indisponibilidade', $falhas, $total);

    foreach (['enf', 'sto', 'adm'] as $abrev) {
        foreach (['painel.php', 'stock.php'] as $pagina) {
            $resposta = pedido_http_teste($base . '/' . $pagina, $cookies[$abrev]);
            $item = item_menu_stock_teste($resposta['corpo']);
            verificar_teste($item !== '' && strpos($item, 'Em desenvolvimento') === false && strpos($item, 'stock.php') !== false, "menu Stock ativo em {$pagina} para {$abrev}", $falhas, $total);
        }
    }
    $item = item_menu_stock_teste(pedido_http_teste($base . '/painel.php', $cookies['rec'])['corpo']);
    verificar_teste(strpos($item, 'Sem acesso') !== false && strpos($item, '<a ') === false, 'menu Stock mostra sem acesso à recepção', $falhas, $total);

    $resposta = pedido_http_teste($base . '/assets/css/modulos.css', $cookies['anonimo']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['cabecalhos'], 'text/css') !== false, 'CSS dos módulos é servido corretamente', $falhas, $total);
    foreach (['app/core/stock.php', 'public/stock.php', 'public/stock_lote_form.php', 'public/stock_lote_detalhe.php', 'public/stock_movimento.php', 'public/stock_movimento_form.php'] as $ficheiro) {
        $conteudo = file_get_contents(dirname(__DIR__) . '/' . $ficheiro);
        verificar_teste(preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $conteudo) !== 1, "{$ficheiro} não contém script inline", $falhas, $total);
        verificar_teste(preg_match('/\son[a-z]+\s*=/i', $conteudo) !== 1 && preg_match('/\sstyle\s*=/i', $conteudo) !== 1, "{$ficheiro} não contém eventos ou estilos inline", $falhas, $total);
        verificar_teste(strpos($conteudo, 'url(/') === false, "{$ficheiro} não usa caminhos absolutos", $falhas, $total);
    }
} catch (Throwable $e) {
    $falhas++;
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
} finally {
    if ($pdo instanceof PDO) {
        // Limpa também lotes cuja resposta não permitiu obter o identificador.
        $stmt = $pdo->prepare("SELECT id FROM stock_lotes WHERE numero_lote LIKE ? AND criado_por_id IN (SELECT id FROM utilizadores WHERE utilizador LIKE ?)");
        $stmt->execute([$marcador . '%', 'fe_%_' . strtolower($sufixo)]);
        foreach ($stmt->fetchAll() as $linha) $lotesIds[] = (int) $linha['id'];
        $lotesIds = array_values(array_unique($lotesIds));
        if ($lotesIds) {
            $marcadores = implode(',', array_fill(0, count($lotesIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM stock_movimentos WHERE lote_id IN ({$marcadores})");
            $stmt->execute($lotesIds);
            $stmt = $pdo->prepare("DELETE FROM auditoria WHERE entidade = 'stock_lotes' AND entidade_id IN ({$marcadores})");
            $stmt->execute($lotesIds);
            $stmt = $pdo->prepare("DELETE FROM stock_lotes WHERE id IN ({$marcadores})");
            $stmt->execute($lotesIds);
        }
        foreach ($utilizadores as $dados) limpar_utilizador_teste($pdo, $dados['id'], $dados['nome']);
    }
    foreach ($cookies as $cookie) if (is_file($cookie)) unlink($cookie);
    if (is_dir($pasta)) rmdir($pasta);
}

if ($falhas > 0) {
    fwrite(STDERR, "{$falhas} de {$total} verificações falharam.\n");
    exit(1);
}
echo "Fase E: {$total} verificações concluídas sem falhas.\n";
