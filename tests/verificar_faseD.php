<?php
/**
 * Testes da Fase D: atendimento, disponibilidade, fila, CSRF e perfis.
 */

require __DIR__ . '/suporte_http.php';

function id_atendimento_teste(array $resposta) {
    return preg_match('/atendimento_detalhe\.php\?id=(\d+)/i', $resposta['cabecalhos'], $matches) === 1
        ? (int) $matches[1]
        : 0;
}

function post_atendimento_teste($base, $cookie, array $dados) {
    $criancaId = (int) $dados['crianca_id'];
    $pagina = pedido_http_teste($base . '/atendimento_form.php?crianca_id=' . $criancaId, $cookie);
    $dados['csrf_token'] = csrf_do_html($pagina['corpo']);
    return pedido_http_teste($base . '/atendimento_form.php', $cookie, $dados);
}

function post_transicao_teste($base, $cookie, $id, $transicao, $motivo = '') {
    $pagina = pedido_http_teste($base . '/atendimento_detalhe.php?id=' . (int) $id, $cookie);
    return pedido_http_teste($base . '/atendimento_estado.php', $cookie, [
        'csrf_token' => csrf_do_html($pagina['corpo']),
        'id' => $id,
        'transicao' => $transicao,
        'motivo' => $motivo,
    ]);
}

function item_menu_atendimento_teste($html) {
    if (preg_match_all('/<li>(.*?)<\/li>/s', $html, $matches)) {
        foreach ($matches[1] as $item) {
            if (strpos($item, 'Atendimento') !== false) {
                return $item;
            }
        }
    }
    return '';
}

function estado_atendimento_teste(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT * FROM atendimentos WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}

$base = 'http://localhost/vacinacao-infantil-fase1a/vacinas/public';
$sufixo = bin2hex(random_bytes(3));
$password = 'FaseD-' . bin2hex(random_bytes(8)) . '!';
$pastaTemporaria = sys_get_temp_dir() . '/vacinas_fased_' . $sufixo;
mkdir($pastaTemporaria);
$cookies = [];
$utilizadores = [];
$criancasIds = [];
$atendimentosIds = [];
$lotesIds = [];
$encarregadoId = 0;
$vacinaSemStockId = 0;
$marcadorLote = 'LOTED' . strtoupper($sufixo);
$falhas = 0;
$total = 0;
$pdo = conexao_teste_root();

try {
    $perfis = [
        'rec' => 'recepcao',
        'enf' => 'enfermeiro',
        'sto' => 'responsavel_stock',
        'adm' => 'administrador',
    ];
    foreach ($perfis as $abrev => $perfil) {
        $nome = 'fd_' . $abrev . '_' . $sufixo;
        $id = criar_utilizador_teste($pdo, $nome, $perfil, $password);
        $utilizadores[$abrev] = ['id' => $id, 'nome' => $nome];
        $cookie = $pastaTemporaria . '/' . $abrev . '.txt';
        $cookies[$abrev] = $cookie;
        $login = login_http_teste($base, $nome, $password, $cookie);
        verificar_teste($login['status'] === 302 && strpos($login['cabecalhos'], 'painel.php') !== false, "login HTTP do perfil {$perfil}", $falhas, $total);
    }
    $cookieAnonimo = $pastaTemporaria . '/anonimo.txt';
    $cookies['anonimo'] = $cookieAnonimo;

    $resposta = pedido_http_teste($base . '/atendimento.php', $cookieAnonimo);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'login.php') !== false, 'atendimento exige sessão válida', $falhas, $total);

    foreach (['rec', 'enf', 'adm'] as $abrev) {
        $resposta = pedido_http_teste($base . '/atendimento.php', $cookies[$abrev]);
        verificar_teste($resposta['status'] === 200, "perfil {$abrev} acede ao atendimento", $falhas, $total);
        verificar_teste(strpos($resposta['corpo'], 'Warning') === false && strpos($resposta['corpo'], 'Fatal error') === false, "HTML de atendimento sem erros para {$abrev}", $falhas, $total);
    }
    $resposta = pedido_http_teste($base . '/atendimento.php', $cookies['sto']);
    verificar_teste($resposta['status'] === 403, 'responsável de stock não acede ao atendimento', $falhas, $total);

    foreach (['rec', 'enf', 'sto', 'adm'] as $abrev) {
        $resposta = pedido_http_teste($base . '/disponibilidade.php', $cookies[$abrev]);
        verificar_teste($resposta['status'] === 200, "perfil {$abrev} consulta disponibilidade sim/não", $falhas, $total);
        verificar_teste(strpos($resposta['corpo'], $marcadorLote) === false && strpos($resposta['corpo'], 'saldo_disponivel') === false, "disponibilidade não expõe lote ou saldo para {$abrev}", $falhas, $total);
    }

    $resposta = pedido_http_teste($base . '/atendimento_form.php?crianca_id=1', $cookies['rec']);
    verificar_teste($resposta['status'] === 302, 'formulário sem criança ativa redireciona', $falhas, $total);
    foreach (['enf', 'sto', 'adm'] as $abrev) {
        $resposta = pedido_http_teste($base . '/atendimento_form.php?crianca_id=1', $cookies[$abrev]);
        verificar_teste($resposta['status'] === 403, "perfil {$abrev} não abre atendimento", $falhas, $total);
    }
    foreach (['enf', 'adm'] as $abrev) {
        $resposta = pedido_http_teste($base . '/fila.php', $cookies[$abrev]);
        verificar_teste($resposta['status'] === 200, "perfil {$abrev} consulta JSON da fila", $falhas, $total);
        verificar_teste(strpos($resposta['cabecalhos'], 'Content-Type: application/json') !== false, "JSON da fila tem content-type correto para {$abrev}", $falhas, $total);
    }
    foreach (['rec', 'sto'] as $abrev) {
        $resposta = pedido_http_teste($base . '/fila.php', $cookies[$abrev]);
        verificar_teste($resposta['status'] === 403, "perfil {$abrev} não consulta JSON da fila", $falhas, $total);
    }
    $resposta = pedido_http_teste($base . '/fila.php', $cookieAnonimo);
    verificar_teste($resposta['status'] === 302, 'JSON da fila exige sessão válida', $falhas, $total);

    $stmt = $pdo->prepare(
        'INSERT INTO encarregados (nome, parentesco, telefone, morada, criado_por_id)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute(['Teste Demo Encarregado Fase D ' . $sufixo, 'Mãe', '923000111', 'Morada fictícia de teste', null]);
    $encarregadoId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'INSERT INTO criancas (numero_processo, numero_cartao, nome, data_nascimento, sexo, encarregado_id, observacoes, criado_por_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $nomesCriancas = [
        'Teste <b>Demo</b> Fase D A ' . $sufixo,
        'Teste Demo Fase D B ' . $sufixo,
        'Teste Demo Fase D C ' . $sufixo,
        'Teste Demo Fase D D ' . $sufixo,
    ];
    foreach ($nomesCriancas as $indice => $nome) {
        $stmt->execute([
            'TD' . $sufixo . str_pad((string) ($indice + 1), 3, '0', STR_PAD_LEFT),
            'CARTAOD' . $sufixo . $indice,
            $nome,
            '2020-01-15',
            $indice % 2 === 0 ? 'F' : 'M',
            $encarregadoId,
            'Fixture temporário da Fase D',
            $utilizadores['rec']['id'],
        ]);
        $criancasIds[] = (int) $pdo->lastInsertId();
    }

    // Duas vacinas do catálogo ficam disponíveis apenas durante o teste.
    foreach ([1, 7] as $vacinaId) {
        $stmt = $pdo->prepare(
            'INSERT INTO stock_lotes (vacina_id, numero_lote, validade, fabricante, quantidade_inicial, criado_por_id)
             VALUES (?, ?, ?, ?, ?, NULL)'
        );
        $stmt->execute([$vacinaId, $marcadorLote . 'V' . $vacinaId, date('Y-m-d', strtotime('+1 year')), 'Fabricante fictício de teste', 5]);
        $loteId = (int) $pdo->lastInsertId();
        $lotesIds[] = $loteId;
        $stmt = $pdo->prepare(
            'INSERT INTO stock_movimentos (lote_id, tipo, quantidade, motivo, utilizador_id)
             VALUES (?, ?, ?, ?, NULL)'
        );
        $stmt->execute([$loteId, 'entrada', 5, 'Entrada fictícia de teste']);
    }

    // Uma vacina sem stock e um lote vencido garantem um resultado determinístico.
    $stmt = $pdo->prepare('INSERT INTO vacinas (nome, ativo, criado_por_id) VALUES (?, TRUE, NULL)');
    $stmt->execute(['Vacina Teste Sem Stock D ' . $sufixo]);
    $vacinaSemStockId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare(
        'INSERT INTO stock_lotes (vacina_id, numero_lote, validade, fabricante, quantidade_inicial, criado_por_id)
         VALUES (?, ?, ?, ?, ?, NULL)'
    );
    $stmt->execute([$vacinaSemStockId, $marcadorLote . 'VENCIDO', date('Y-m-d', strtotime('-1 day')), 'Fabricante fictício de teste', 9]);
    $loteVencidoId = (int) $pdo->lastInsertId();
    $lotesIds[] = $loteVencidoId;
    $stmt = $pdo->prepare(
        'INSERT INTO stock_movimentos (lote_id, tipo, quantidade, motivo, utilizador_id)
         VALUES (?, ?, ?, ?, NULL)'
    );
    $stmt->execute([$loteVencidoId, 'entrada', 9, 'Entrada fictícia vencida de teste']);

    $resposta = pedido_http_teste($base . '/atendimento_form.php?crianca_id=' . $criancasIds[0], $cookies['rec']);
    verificar_teste($resposta['status'] === 200, 'recepção abre formulário de atendimento', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], $marcadorLote) === false && strpos($resposta['corpo'], 'saldo_disponivel') === false, 'formulário da recepção não expõe lotes ou saldos', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], 'Vacina Teste Sem Stock D ' . $sufixo) !== false && strpos($resposta['corpo'], 'Indisponível') !== false, 'vacina sem stock válido aparece como indisponível', $falhas, $total);

    $resposta = pedido_http_teste($base . '/atendimento_form.php?crianca_id=' . $criancasIds[0], $cookies['rec'], [
        'csrf_token' => 'invalido',
        'crianca_id' => $criancasIds[0],
        'vacinas' => [1],
    ]);
    verificar_teste($resposta['status'] === 403, 'abertura de atendimento rejeita CSRF inválido', $falhas, $total);
    $resposta = pedido_http_teste($base . '/atendimento_estado.php', $cookies['enf']);
    verificar_teste($resposta['status'] === 405, 'endpoint de transição só aceita POST', $falhas, $total);
    $resposta = pedido_http_teste($base . '/atendimento_estado.php', $cookies['enf'], [
        'csrf_token' => 'invalido', 'id' => 1, 'transicao' => 'iniciar', 'motivo' => '',
    ]);
    verificar_teste($resposta['status'] === 403, 'transição rejeita CSRF inválido', $falhas, $total);

    $dadosAbertura = [
        'crianca_id' => $criancasIds[0],
        'vacinas' => [1, 7],
        'motivo' => '',
    ];
    $resposta = post_atendimento_teste($base, $cookies['rec'], $dadosAbertura);
    $atendimentoA = id_atendimento_teste($resposta);
    if ($atendimentoA > 0) $atendimentosIds[] = $atendimentoA;
    verificar_teste($resposta['status'] === 302 && $atendimentoA > 0, 'recepção abre atendimento com vacina disponível', $falhas, $total);
    $estadoA = estado_atendimento_teste($pdo, $atendimentoA);
    verificar_teste(($estadoA['estado'] ?? '') === 'aguardando', 'atendimento com stock entra em aguardando', $falhas, $total);
    verificar_teste((int) ($estadoA['recebido_por_id'] ?? 0) === (int) $utilizadores['rec']['id'], 'atendimento guarda recepcionista', $falhas, $total);
    verificar_teste(($estadoA['motivo'] ?? null) === null, 'motivo não é exigido quando há vacina disponível', $falhas, $total);
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM atendimento_vacinas WHERE atendimento_id = ?');
    $stmt->execute([$atendimentoA]);
    verificar_teste((int) $stmt->fetch()['total'] === 2, 'vacinas pretendidas são persistidas', $falhas, $total);

    $resposta = post_atendimento_teste($base, $cookies['rec'], $dadosAbertura);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'já tem um atendimento ativo') !== false, 'segundo atendimento ativo da mesma criança é recusado', $falhas, $total);
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM atendimentos WHERE crianca_id = ?');
    $stmt->execute([$criancasIds[0]]);
    verificar_teste((int) $stmt->fetch()['total'] === 1, 'recusa não cria atendimento duplicado', $falhas, $total);

    $resposta = pedido_http_teste($base . '/atendimento.php?q=' . rawurlencode($sufixo), $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Teste &lt;b&gt;Demo&lt;/b&gt;') !== false, 'pesquisa da recepção encontra criança e escapa o nome', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], 'atendimento_form.php?crianca_id=' . $criancasIds[0]) !== false, 'pesquisa oferece abertura real de atendimento', $falhas, $total);

    $resposta = pedido_http_teste($base . '/fila.php', $cookies['enf']);
    $dadosFila = json_decode($resposta['corpo'], true);
    $itemA = null;
    foreach (($dadosFila['itens'] ?? []) as $item) {
        if ((int) $item['id'] === $atendimentoA) $itemA = $item;
    }
    verificar_teste(is_array($itemA), 'atendimento aguardando aparece no JSON da fila', $falhas, $total);
    verificar_teste(is_array($itemA) && array_keys($itemA) === ['id', 'primeiro_nome', 'idade', 'vacinas', 'chegada'], 'JSON da fila contém apenas os cinco campos mínimos', $falhas, $total);
    verificar_teste(is_array($itemA) && $itemA['primeiro_nome'] === 'Teste' && preg_match('/\d+ (mês|meses|ano|anos)/', $itemA['idade']) === 1, 'JSON usa primeiro nome e idade', $falhas, $total);
    $nomeComVirgulas = 'Meningocócica conjugada quadrivalente (A, C, Y, W-135)';
    $vacinaComVirgulasInteira = is_array($itemA) && count($itemA['vacinas']) === 2 && in_array($nomeComVirgulas, $itemA['vacinas'], true);
    if (!$vacinaComVirgulasInteira && is_array($itemA)) {
        fwrite(STDERR, 'Vacinas no JSON: ' . json_encode($itemA['vacinas'], JSON_UNESCAPED_UNICODE) . "\n");
    }
    verificar_teste($vacinaComVirgulasInteira, 'nome de vacina com vírgulas permanece um único item', $falhas, $total);
    verificar_teste(is_array($itemA) && preg_match('/^\d{2}:\d{2}$/', $itemA['chegada']) === 1, 'JSON contém hora de chegada', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], $marcadorLote) === false && strpos($resposta['corpo'], 'quantidade') === false && strpos($resposta['corpo'], 'saldo') === false && strpos($resposta['corpo'], 'lote') === false, 'JSON da fila não contém lotes, quantidades ou saldos', $falhas, $total);
    verificar_teste(strpos($resposta['cabecalhos'], "Content-Security-Policy: default-src 'none'") !== false, 'JSON da fila tem CSP restrita', $falhas, $total);

    $dadosSemStock = [
        'crianca_id' => $criancasIds[1],
        'vacinas' => [$vacinaSemStockId],
        'motivo' => '',
    ];
    $resposta = post_atendimento_teste($base, $cookies['rec'], $dadosSemStock);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'indique o motivo do registo sem vacina') !== false, 'sem vacina exige motivo obrigatório', $falhas, $total);
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM atendimentos WHERE crianca_id = ?');
    $stmt->execute([$criancasIds[1]]);
    verificar_teste((int) $stmt->fetch()['total'] === 0, 'falta de motivo não cria atendimento', $falhas, $total);

    $dadosSemStock['motivo'] = 'Vacina indisponível na unidade sanitária fictícia.';
    $resposta = post_atendimento_teste($base, $cookies['rec'], $dadosSemStock);
    $atendimentoB = id_atendimento_teste($resposta);
    if ($atendimentoB > 0) $atendimentosIds[] = $atendimentoB;
    $estadoB = estado_atendimento_teste($pdo, $atendimentoB);
    verificar_teste($resposta['status'] === 302 && ($estadoB['estado'] ?? '') === 'sem_vacina', 'recepção registra atendimento sem vacina com motivo', $falhas, $total);
    verificar_teste(($estadoB['motivo'] ?? '') === $dadosSemStock['motivo'], 'motivo do sem-vacina é persistido', $falhas, $total);
    $resposta = pedido_http_teste($base . '/fila.php', $cookies['enf']);
    $dadosFila = json_decode($resposta['corpo'], true);
    $idsFila = array_map(static function ($item) { return (int) $item['id']; }, $dadosFila['itens'] ?? []);
    verificar_teste(in_array($atendimentoA, $idsFila, true) && !in_array($atendimentoB, $idsFila, true), 'JSON da fila contém só atendimentos aguardando', $falhas, $total);

    foreach (['sto', 'adm'] as $abrev) {
        $csrf = csrf_do_html(pedido_http_teste($base . '/painel.php', $cookies[$abrev])['corpo']);
        $resposta = pedido_http_teste($base . '/atendimento_estado.php', $cookies[$abrev], [
            'csrf_token' => $csrf,
            'id' => $atendimentoA,
            'transicao' => 'iniciar',
            'motivo' => '',
        ]);
        verificar_teste($resposta['status'] === 403, "perfil {$abrev} não inicia atendimento", $falhas, $total);
    }

    $dadosCancelar = ['crianca_id' => $criancasIds[2], 'vacinas' => [1], 'motivo' => ''];
    $resposta = post_atendimento_teste($base, $cookies['rec'], $dadosCancelar);
    $atendimentoC = id_atendimento_teste($resposta);
    if ($atendimentoC > 0) $atendimentosIds[] = $atendimentoC;
    verificar_teste(($estadoC = estado_atendimento_teste($pdo, $atendimentoC)) && $estadoC['estado'] === 'aguardando', 'recepção abre atendimento para cancelamento', $falhas, $total);

    $resposta = post_transicao_teste($base, $cookies['rec'], $atendimentoC, 'cancelado', '');
    verificar_teste($resposta['status'] === 302 && estado_atendimento_teste($pdo, $atendimentoC)['estado'] === 'aguardando', 'cancelamento sem motivo é recusado', $falhas, $total);
    $detalhe = pedido_http_teste($base . '/atendimento_detalhe.php?id=' . $atendimentoC, $cookies['rec']);
    verificar_teste(strpos($detalhe['corpo'], 'O motivo é obrigatório.') !== false && strpos($detalhe['corpo'], 'role="alert"') !== false, 'erro de motivo aparece em mensagem acessível', $falhas, $total);

    foreach (['enf', 'sto', 'adm'] as $abrev) {
        $resposta = pedido_http_teste($base . '/atendimento_detalhe.php?id=' . $atendimentoA, $cookies[$abrev]);
        $esperado = in_array($abrev, ['enf', 'adm'], true) ? 200 : 403;
        verificar_teste($resposta['status'] === $esperado, "ficha de atendimento respeita perfil {$abrev}", $falhas, $total);
        if ($resposta['status'] === 200) {
            verificar_teste(strpos($resposta['corpo'], $marcadorLote) === false && strpos($resposta['corpo'], 'saldo_disponivel') === false, "ficha de {$abrev} não expõe lotes ou saldos", $falhas, $total);
        }
    }

    $resposta = post_transicao_teste($base, $cookies['adm'], $atendimentoC, 'cancelado', 'Cancelamento fictício.');
    verificar_teste($resposta['status'] === 403, 'administrador não cancela atendimento', $falhas, $total);
    $resposta = post_transicao_teste($base, $cookies['enf'], $atendimentoC, 'cancelado', 'Cancelamento fictício.');
    verificar_teste($resposta['status'] === 302 && estado_atendimento_teste($pdo, $atendimentoC)['estado'] === 'aguardando', 'enfermeiro não cancela atendimento', $falhas, $total);

    $resposta = post_transicao_teste($base, $cookies['rec'], $atendimentoC, 'cancelado', 'Cancelamento fictício a pedido do encarregado.');
    $estadoC = estado_atendimento_teste($pdo, $atendimentoC);
    verificar_teste($resposta['status'] === 302 && $estadoC['estado'] === 'cancelado', 'recepção cancela atendimento aguardando', $falhas, $total);
    verificar_teste($estadoC['motivo'] === 'Cancelamento fictício a pedido do encarregado.', 'cancelamento guarda motivo', $falhas, $total);
    $resposta = post_transicao_teste($base, $cookies['rec'], $atendimentoC, 'cancelado', 'Segundo cancelamento.');
    verificar_teste($resposta['status'] === 302 && estado_atendimento_teste($pdo, $atendimentoC)['estado'] === 'cancelado', 'transição a partir de cancelado é recusada', $falhas, $total);

    $dadosSemVacina = ['crianca_id' => $criancasIds[3], 'vacinas' => [1], 'motivo' => ''];
    $resposta = post_atendimento_teste($base, $cookies['rec'], $dadosSemVacina);
    $atendimentoD = id_atendimento_teste($resposta);
    if ($atendimentoD > 0) $atendimentosIds[] = $atendimentoD;
    $resposta = post_transicao_teste($base, $cookies['rec'], $atendimentoD, 'sem_vacina', '');
    verificar_teste($resposta['status'] === 302 && estado_atendimento_teste($pdo, $atendimentoD)['estado'] === 'aguardando', 'transição sem-vacina exige motivo', $falhas, $total);
    $resposta = post_transicao_teste($base, $cookies['rec'], $atendimentoD, 'sem_vacina', 'Vacina pretendida esgotada.');
    verificar_teste($resposta['status'] === 302 && estado_atendimento_teste($pdo, $atendimentoD)['estado'] === 'sem_vacina', 'recepção registra sem-vacina em atendimento aguardando', $falhas, $total);
    $resposta = post_transicao_teste($base, $cookies['rec'], $atendimentoD, 'sem_vacina', 'Repetição inválida.');
    verificar_teste($resposta['status'] === 302 && estado_atendimento_teste($pdo, $atendimentoD)['estado'] === 'sem_vacina', 'transição a partir de sem-vacina é recusada', $falhas, $total);

    $resposta = post_transicao_teste($base, $cookies['rec'], $atendimentoA, 'iniciar', '');
    verificar_teste($resposta['status'] === 302 && estado_atendimento_teste($pdo, $atendimentoA)['estado'] === 'aguardando', 'recepção não inicia atendimento', $falhas, $total);
    $resposta = post_transicao_teste($base, $cookies['enf'], $atendimentoA, 'iniciar', '');
    $estadoA = estado_atendimento_teste($pdo, $atendimentoA);
    verificar_teste($resposta['status'] === 302 && $estadoA['estado'] === 'em_atendimento', 'enfermeiro inicia atendimento aguardando', $falhas, $total);
    verificar_teste((int) $estadoA['atendido_por_id'] === (int) $utilizadores['enf']['id'] && $estadoA['iniciado_em'] !== null, 'início guarda enfermeiro e data/hora', $falhas, $total);
    verificar_teste($estadoA['concluido_em'] === null, 'início não conclui atendimento', $falhas, $total);
    $resposta = post_transicao_teste($base, $cookies['enf'], $atendimentoA, 'iniciar', '');
    verificar_teste($resposta['status'] === 302 && estado_atendimento_teste($pdo, $atendimentoA)['estado'] === 'em_atendimento', 'segundo início do mesmo atendimento é recusado', $falhas, $total);
    $resposta = post_transicao_teste($base, $cookies['enf'], $atendimentoA, 'concluir', 'Conclusão fora da Fase D.');
    verificar_teste($resposta['status'] === 302 && estado_atendimento_teste($pdo, $atendimentoA)['estado'] === 'em_atendimento', 'transição de conclusão não é aceita fora do fluxo previsto', $falhas, $total);

    $resposta = pedido_http_teste($base . '/fila.php', $cookies['enf']);
    $dadosFila = json_decode($resposta['corpo'], true);
    $idsFila = array_map(static function ($item) { return (int) $item['id']; }, $dadosFila['itens'] ?? []);
    verificar_teste(!in_array($atendimentoA, $idsFila, true) && !in_array($atendimentoB, $idsFila, true) && !in_array($atendimentoC, $idsFila, true) && !in_array($atendimentoD, $idsFila, true), 'atendimentos iniciados, cancelados e sem-vacina saem da fila', $falhas, $total);

    $auditoriaAtendimentos = 0;
    if ($atendimentosIds) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM auditoria WHERE entidade = 'atendimentos' AND entidade_id IN (" . implode(',', array_fill(0, count($atendimentosIds), '?')) . ")");
        $stmt->execute($atendimentosIds);
        $auditoriaAtendimentos = (int) $stmt->fetch()['total'];
    }
    verificar_teste($auditoriaAtendimentos >= 7, 'criações e transições de atendimento registam auditoria', $falhas, $total);

    foreach (['rec', 'enf', 'adm'] as $abrev) {
        foreach (['painel.php', 'atendimento.php'] as $pagina) {
            $resposta = pedido_http_teste($base . '/' . $pagina, $cookies[$abrev]);
            $itemMenu = item_menu_atendimento_teste($resposta['corpo']);
            verificar_teste($itemMenu !== '' && strpos($itemMenu, 'Em desenvolvimento') === false && strpos($itemMenu, '<a ') !== false, "menu Atendimento ativo em {$pagina} para {$abrev}", $falhas, $total);
        }
    }
    $painelStock = pedido_http_teste($base . '/painel.php', $cookies['sto']);
    $itemStock = item_menu_atendimento_teste($painelStock['corpo']);
    verificar_teste($itemStock !== '' && strpos($itemStock, '<a ') === false && strpos($itemStock, 'Sem acesso') !== false, 'menu Atendimento mostra sem acesso para stock', $falhas, $total);

    $resposta = pedido_http_teste($base . '/assets/js/fila.js', $cookieAnonimo);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['cabecalhos'], 'javascript') !== false, 'asset fila.js é servido como JavaScript', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], 'setInterval(atualizar, 12000)') !== false && strpos($resposta['corpo'], 'fetch(url') !== false, 'fila.js faz polling a cada 12 segundos', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], 'addEventListener') !== false && strpos($resposta['corpo'], 'innerHTML') === false && strpos($resposta['corpo'], "setAttribute('role', 'alert')") !== false, 'fila.js usa listeners, DOM seguro e aviso acessível', $falhas, $total);

    $ficheirosPhp = [
        'app/core/atendimentos.php',
        'public/atendimento.php',
        'public/atendimento_form.php',
        'public/atendimento_detalhe.php',
        'public/atendimento_estado.php',
        'public/disponibilidade.php',
        'public/fila.php',
    ];
    foreach ($ficheirosPhp as $ficheiro) {
        $conteudo = file_get_contents(dirname(__DIR__) . '/' . $ficheiro);
        verificar_teste(preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $conteudo) !== 1, "{$ficheiro} não contém script inline", $falhas, $total);
        verificar_teste(preg_match('/\son[a-z]+\s*=/i', $conteudo) !== 1 && preg_match('/\sstyle\s*=/i', $conteudo) !== 1, "{$ficheiro} não contém eventos nem estilos inline", $falhas, $total);
    }
} catch (Throwable $e) {
    $falhas++;
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
} finally {
    if ($pdo instanceof PDO) {
        if ($criancasIds) {
            $marcadores = implode(',', array_fill(0, count($criancasIds), '?'));
            $stmt = $pdo->prepare("SELECT id FROM atendimentos WHERE crianca_id IN ({$marcadores})");
            $stmt->execute($criancasIds);
            $atendimentosFixture = array_map(static function ($linha) { return (int) $linha['id']; }, $stmt->fetchAll());
            if ($atendimentosFixture) {
                $marcadoresAtendimento = implode(',', array_fill(0, count($atendimentosFixture), '?'));
                $stmt = $pdo->prepare("DELETE FROM atendimento_vacinas WHERE atendimento_id IN ({$marcadoresAtendimento})");
                $stmt->execute($atendimentosFixture);
                $stmt = $pdo->prepare("DELETE FROM auditoria WHERE entidade = 'atendimentos' AND entidade_id IN ({$marcadoresAtendimento})");
                $stmt->execute($atendimentosFixture);
                $stmt = $pdo->prepare("DELETE FROM atendimentos WHERE id IN ({$marcadoresAtendimento})");
                $stmt->execute($atendimentosFixture);
            }
        }
        if ($lotesIds) {
            $marcadores = implode(',', array_fill(0, count($lotesIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM stock_movimentos WHERE lote_id IN ({$marcadores})");
            $stmt->execute($lotesIds);
            $stmt = $pdo->prepare("DELETE FROM stock_lotes WHERE id IN ({$marcadores})");
            $stmt->execute($lotesIds);
        }
        if ($vacinaSemStockId > 0) {
            $stmt = $pdo->prepare('DELETE FROM vacinas WHERE id = ?');
            $stmt->execute([$vacinaSemStockId]);
        }
        if ($criancasIds) {
            $marcadores = implode(',', array_fill(0, count($criancasIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM criancas WHERE id IN ({$marcadores})");
            $stmt->execute($criancasIds);
        }
        if ($encarregadoId > 0) {
            $stmt = $pdo->prepare('DELETE FROM encarregados WHERE id = ?');
            $stmt->execute([$encarregadoId]);
        }
        foreach ($utilizadores as $dados) {
            limpar_utilizador_teste($pdo, $dados['id'], $dados['nome']);
        }
    }
    foreach ($cookies as $cookie) {
        if (is_file($cookie)) unlink($cookie);
    }
    if (is_dir($pastaTemporaria)) rmdir($pastaTemporaria);
}

if ($falhas > 0) {
    fwrite(STDERR, "{$falhas} de {$total} verificações falharam.\n");
    exit(1);
}
echo "Fase D: {$total} verificações concluídas sem falhas.\n";
