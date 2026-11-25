<?php
/**
 * Testes da Fase F: vacinação transacional, stock, histórico, anulação e concorrência.
 */

require __DIR__ . '/suporte_http.php';

function posto_f($base, $cookie, $atendimentoId, $vacinaId, $calendarioId, $dose, $loteId, $token = null) {
    if ($token === null) {
        $pagina = pedido_http_teste($base . '/vacinacao_form.php?atendimento_id=' . (int) $atendimentoId, $cookie);
        $token = csrf_do_html($pagina['corpo']);
    }
    return pedido_http_teste($base . '/vacinacao_registar.php', $cookie, [
        'csrf_token' => $token, 'origem' => 'posto', 'atendimento_id' => $atendimentoId,
        'vacina_id' => $vacinaId, 'calendario_id' => $calendarioId, 'numero_dose' => $dose,
        'lote_id' => $loteId, 'local_anatomico' => 'Coxa esquerda fictícia', 'reacoes' => '', 'observacoes' => 'Teste fictício',
    ]);
}
function externa_f($base, $cookie, $criancaId, $vacinaId, $calendarioId, $dose, $token = null) {
    if ($token === null) {
        $pagina = pedido_http_teste($base . '/vacinacao_externa_form.php?crianca_id=' . (int) $criancaId, $cookie);
        $token = csrf_do_html($pagina['corpo']);
    }
    return pedido_http_teste($base . '/vacinacao_registar.php', $cookie, [
        'csrf_token' => $token, 'origem' => 'externa', 'crianca_id' => $criancaId,
        'vacina_id' => $vacinaId, 'calendario_id' => $calendarioId, 'numero_dose' => $dose,
        'data_administracao' => date('Y-m-d'), 'administrado_por_nome' => 'Profissional externo fictício',
        'local_anatomico' => '', 'reacoes' => '', 'observacoes' => '',
    ]);
}
function anular_f($base, $cookie, $id, $motivo) {
    $pagina = pedido_http_teste($base . '/crianca_detalhe.php?id=' . (int) $GLOBALS['criancaAnulada'], $cookie);
    return pedido_http_teste($base . '/vacinacao_anular.php', $cookie, ['csrf_token' => csrf_do_html($pagina['corpo']), 'id' => $id, 'motivo' => $motivo]);
}
function simultaneos_f($pedidos) {
    $multi = curl_multi_init();
    $handles = [];
    foreach ($pedidos as $indice => $pedido) {
        $curl = curl_init($pedido['url']);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIEJAR => $pedido['cookie'], CURLOPT_COOKIEFILE => $pedido['cookie'],
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($pedido['dados']),
            CURLOPT_HEADER => true,
        ]);
        curl_multi_add_handle($multi, $curl);
        $handles[$indice] = $curl;
    }
    do {
        $estado = curl_multi_exec($multi, $aExecutar);
        if ($aExecutar) curl_multi_select($multi, 0.2);
    } while ($aExecutar && $estado === CURLM_OK);
    $respostas = [];
    foreach ($handles as $indice => $curl) {
        $respostas[$indice] = curl_multi_getcontent($curl);
        curl_multi_remove_handle($multi, $curl);
        curl_close($curl);
    }
    curl_multi_close($multi);
    return $respostas;
}
function saldo_f(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT saldo_atual FROM stock_saldo_lotes WHERE id = ?');
    $stmt->execute([(int) $id]);
    return (int) $stmt->fetchColumn();
}
function estado_f(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT estado, concluido_em FROM atendimentos WHERE id = ?');
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}
function agenda_f(PDO $pdo, $criancaId, array $vacinas, $estado = 'em_atendimento') {
    $stmt = $pdo->prepare('INSERT INTO atendimentos (crianca_id, estado, atendido_por_id, iniciado_em) VALUES (?, ?, 1, NOW())');
    $stmt->execute([(int) $criancaId, $estado]);
    $id = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('INSERT INTO atendimento_vacinas (atendimento_id, vacina_id) VALUES (?, ?)');
    foreach ($vacinas as $vacina) $stmt->execute([$id, (int) $vacina]);
    return $id;
}

$base = 'http://localhost/vacinacao-infantil-fase1a/vacinas/public';
$sufixo = strtoupper(bin2hex(random_bytes(3)));
$password = 'FaseF-' . bin2hex(random_bytes(8)) . '!';
$pasta = sys_get_temp_dir() . '/vacinas_fasef_' . strtolower($sufixo);
mkdir($pasta);
$cookies = [];
$utilizadores = [];
$criancas = [];
$atendimentos = [];
$lotes = [];
$encarregadoId = 0;
$criancaAnulada = 0;
$falhas = 0;
$total = 0;
$pdo = conexao_teste_root();

try {
    foreach (['rec' => 'recepcao', 'enf' => 'enfermeiro', 'enf2' => 'enfermeiro', 'sto' => 'responsavel_stock', 'adm' => 'administrador'] as $abrev => $perfil) {
        $nome = 'ff_' . strtolower($abrev) . '_' . strtolower($sufixo);
        $id = criar_utilizador_teste($pdo, $nome, $perfil, $password);
        $utilizadores[$abrev] = ['id' => $id, 'nome' => $nome];
        $cookies[$abrev] = $pasta . '/' . $abrev . '.txt';
        $login = login_http_teste($base, $nome, $password, $cookies[$abrev]);
        verificar_teste($login['status'] === 302, "login HTTP {$perfil} {$abrev}", $falhas, $total);
    }
    $cookies['anonimo'] = $pasta . '/anonimo.txt';
    $resposta = pedido_http_teste($base . '/vacinacao.php', $cookies['anonimo']);
    verificar_teste($resposta['status'] === 302, 'vacinação exige sessão', $falhas, $total);
    foreach (['enf', 'adm'] as $abrev) {
        $resposta = pedido_http_teste($base . '/vacinacao.php', $cookies[$abrev]);
        verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Warning') === false, "perfil {$abrev} consulta vacinação", $falhas, $total);
    }
    foreach (['rec', 'sto'] as $abrev) {
        $resposta = pedido_http_teste($base . '/vacinacao.php', $cookies[$abrev]);
        verificar_teste($resposta['status'] === 403, "perfil {$abrev} não acede à vacinação", $falhas, $total);
    }

    $stmt = $pdo->prepare('INSERT INTO encarregados (nome, parentesco, telefone) VALUES (?, ?, ?)');
    $stmt->execute(['Encarregado fictício F ' . $sufixo, 'Mãe', '923000000']);
    $encarregadoId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('INSERT INTO criancas (numero_processo, nome, data_nascimento, sexo, encarregado_id, criado_por_id) VALUES (?, ?, ?, ?, ?, ?)');
    for ($i = 1; $i <= 6; $i++) {
        $stmt->execute(['TF' . $sufixo . $i, 'Teste Demo Vacinação F ' . $i . ' ' . $sufixo, '2020-01-01', $i % 2 ? 'F' : 'M', $encarregadoId, $utilizadores['rec']['id']]);
        $criancas[$i] = (int) $pdo->lastInsertId();
    }
    $marcador = 'LOTEF' . $sufixo;
    $criarLote = function ($vacina, $numero, $validade, $saldo) use ($pdo, &$lotes) {
        $stmt = $pdo->prepare('INSERT INTO stock_lotes (vacina_id, numero_lote, validade, fabricante, quantidade_inicial) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$vacina, $numero, $validade, 'Fabricante fictício F', $saldo]);
        $id = (int) $pdo->lastInsertId();
        $lotes[$id] = ['vacina' => $vacina, 'saldo' => $saldo];
        if ($saldo > 0) {
            $stmt = $pdo->prepare("INSERT INTO stock_movimentos (lote_id, tipo, quantidade, motivo) VALUES (?, 'entrada', ?, 'Fixture F')");
            $stmt->execute([$id, $saldo]);
        }
        return $id;
    };
    $fefo = $criarLote(1, $marcador . 'FEFO', date('Y-m-d', strtotime('+10 days')), 5);
    $tarde = $criarLote(1, $marcador . 'TARDE', date('Y-m-d', strtotime('+100 days')), 5);
    $vencido = $criarLote(1, $marcador . 'VENCIDO', date('Y-m-d', strtotime('-1 day')), 9);
    $outra = $criarLote(2, $marcador . 'OUTRA', date('Y-m-d', strtotime('+20 days')), 5);
    $vazio = $criarLote(1, $marcador . 'VAZIO', date('Y-m-d', strtotime('+30 days')), 0);
    $ultima = $criarLote(1, $marcador . 'ULTIMA', date('Y-m-d', strtotime('+40 days')), 1);

    $atendimentos[1] = agenda_f($pdo, $criancas[1], [1]);
    $resposta = pedido_http_teste($base . '/vacinacao_form.php?atendimento_id=' . $atendimentos[1], $cookies['enf']);
    verificar_teste($resposta['status'] === 200, 'enfermeiro abre formulário de vacinação', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], $marcador . 'FEFO') < strpos($resposta['corpo'], $marcador . 'TARDE'), 'lote que expira primeiro é sugerido', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], $marcador . 'VENCIDO') === false && strpos($resposta['corpo'], $marcador . 'OUTRA') === false && strpos($resposta['corpo'], $marcador . 'VAZIO') === false, 'formulário exclui vencidos, vazios e lotes de outra vacina', $falhas, $total);
    foreach (['adm', 'rec', 'sto'] as $abrev) {
        $resposta = pedido_http_teste($base . '/vacinacao_form.php?atendimento_id=' . $atendimentos[1], $cookies[$abrev]);
        verificar_teste($resposta['status'] === 403, "perfil {$abrev} não abre formulário de vacinação", $falhas, $total);
    }
    $resposta = pedido_http_teste($base . '/vacinacao_registar.php', $cookies['enf']);
    verificar_teste($resposta['status'] === 405, 'endpoint de vacinação só aceita POST', $falhas, $total);
    $resposta = posto_f($base, $cookies['enf'], $atendimentos[1], 1, 1, 1, $fefo, 'invalido');
    verificar_teste($resposta['status'] === 403, 'vacinação rejeita CSRF inválido', $falhas, $total);
    $resposta = posto_f($base, $cookies['enf'], $atendimentos[1], 1, 1, 1, $outra);
    verificar_teste(strpos($resposta['cabecalhos'], 'vacinacao.php') !== false && saldo_f($pdo, $fefo) === 5, 'lote de outra vacina é rejeitado sem mexer no stock', $falhas, $total);
    $resposta = posto_f($base, $cookies['enf'], $atendimentos[1], 1, 1, 1, $vencido);
    verificar_teste(strpos($resposta['cabecalhos'], 'vacinacao.php') !== false, 'lote vencido é rejeitado no servidor', $falhas, $total);
    $resposta = posto_f($base, $cookies['enf'], $atendimentos[1], 1, 1, 1, $vazio);
    verificar_teste(strpos($resposta['cabecalhos'], 'vacinacao.php') !== false, 'lote sem saldo é rejeitado', $falhas, $total);

    $resposta = posto_f($base, $cookies['enf'], $atendimentos[1], 1, 1, 1, $fefo);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'crianca_detalhe.php?id=' . $criancas[1]) !== false, 'vacinação válida é registada e redireciona para a criança correta', $falhas, $total);
    verificar_teste(saldo_f($pdo, $fefo) === 4, 'vacinação diminui saldo em uma dose', $falhas, $total);
    verificar_teste(estado_f($pdo, $atendimentos[1])['estado'] === 'concluido', 'atendimento de uma vacina é concluído', $falhas, $total);
    $stmt = $pdo->prepare('SELECT * FROM vacinacoes WHERE crianca_id = ?');
    $stmt->execute([$criancas[1]]);
    $vacinacao = $stmt->fetch();
    verificar_teste((int) $vacinacao['lote_id'] === $fefo && (int) $vacinacao['administrado_por_id'] === (int) $utilizadores['enf']['id'], 'vacinação guarda lote e enfermeiro', $falhas, $total);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_movimentos WHERE lote_id = ? AND tipo = 'saida_vacinacao' AND vacinacao_id = ?");
    $stmt->execute([$fefo, $vacinacao['id']]);
    verificar_teste((int) $stmt->fetchColumn() === 1, 'saída de uma dose fica ligada à vacinação', $falhas, $total);
    $atendimentos[3] = agenda_f($pdo, $criancas[1], [1]);
    $resposta = posto_f($base, $cookies['enf'], $atendimentos[3], 1, 1, 1, $fefo);
    verificar_teste(strpos($resposta['cabecalhos'], 'vacinacao.php') !== false && saldo_f($pdo, $fefo) === 4, 'segunda dose válida da mesma criança é recusada', $falhas, $total);

    $atendimentos[2] = agenda_f($pdo, $criancas[2], [1, 2]);
    $resposta = posto_f($base, $cookies['enf'], $atendimentos[2], 1, 1, 1, $tarde);
    verificar_teste(strpos($resposta['cabecalhos'], 'crianca_detalhe.php') !== false && estado_f($pdo, $atendimentos[2])['estado'] === 'em_atendimento', 'primeira vacina de atendimento múltiplo mantém atendimento em curso', $falhas, $total);
    $resposta = posto_f($base, $cookies['enf'], $atendimentos[2], 2, 2, 0, $outra);
    verificar_teste(strpos($resposta['cabecalhos'], 'crianca_detalhe.php') !== false && estado_f($pdo, $atendimentos[2])['estado'] === 'concluido', 'última vacina pendente conclui atendimento múltiplo', $falhas, $total);
    verificar_teste(saldo_f($pdo, $outra) === 4, 'segunda vacina movimenta apenas o seu lote', $falhas, $total);

    $resposta = pedido_http_teste($base . '/vacinacao_externa_form.php?crianca_id=' . $criancas[4], $cookies['enf']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'não movimenta stock') !== false, 'formulário externo explica ausência de stock', $falhas, $total);
    foreach (['rec', 'sto', 'adm'] as $abrev) {
        $resposta = pedido_http_teste($base . '/vacinacao_externa_form.php?crianca_id=' . $criancas[4], $cookies[$abrev]);
        verificar_teste($resposta['status'] === 403, "perfil {$abrev} não regista dose externa", $falhas, $total);
    }
    $antesMovimentos = (int) $pdo->query('SELECT COUNT(*) FROM stock_movimentos')->fetchColumn();
    $resposta = externa_f($base, $cookies['enf'], $criancas[4], 2, 2, 0);
    verificar_teste(strpos($resposta['cabecalhos'], 'crianca_detalhe.php') !== false, 'dose externa é registada', $falhas, $total);
    $stmt = $pdo->prepare('SELECT * FROM vacinacoes WHERE crianca_id = ?');
    $stmt->execute([$criancas[4]]);
    $externa = $stmt->fetch();
    verificar_teste($externa['origem'] === 'externa' && $externa['atendimento_id'] === null && $externa['lote_id'] === null, 'dose externa não tem atendimento nem lote', $falhas, $total);
    verificar_teste((int) $pdo->query('SELECT COUNT(*) FROM stock_movimentos')->fetchColumn() === $antesMovimentos, 'dose externa não cria movimento de stock', $falhas, $total);
    $resposta = externa_f($base, $cookies['enf'], $criancas[4], 2, 2, 0);
    verificar_teste(strpos($resposta['cabecalhos'], 'vacinacao_externa_form.php') !== false, 'dose externa duplicada é recusada', $falhas, $total);

    foreach (['rec', 'enf', 'adm'] as $abrev) {
        $resposta = pedido_http_teste($base . '/crianca_detalhe.php?id=' . $criancas[1], $cookies[$abrev]);
        verificar_teste($resposta['status'] === 200 && (strpos($resposta['corpo'], 'name="motivo"') !== false) === ($abrev !== 'rec'), "formulário de anulação respeita perfil {$abrev}", $falhas, $total);
    }
    $criancaAnulada = $criancas[1];
    $resposta = anular_f($base, $cookies['enf'], $vacinacao['id'], '');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM vacinacao_anulacoes WHERE vacinacao_id = ?');
    $stmt->execute([$vacinacao['id']]);
    verificar_teste((int) $stmt->fetchColumn() === 0, 'anulação exige motivo', $falhas, $total);
    $resposta = anular_f($base, $cookies['rec'], $vacinacao['id'], 'Teste proibido');
    verificar_teste($resposta['status'] === 403, 'recepção não anula vacinação', $falhas, $total);
    $resposta = anular_f($base, $cookies['adm'], $vacinacao['id'], 'Erro fictício de registo');
    verificar_teste(strpos($resposta['cabecalhos'], 'crianca_detalhe.php') !== false, 'administrador anula vacinação com motivo', $falhas, $total);
    verificar_teste(saldo_f($pdo, $fefo) === 4, 'anulação não devolve stock automaticamente', $falhas, $total);
    $resposta = anular_f($base, $cookies['enf'], $vacinacao['id'], 'Repetido');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM vacinacao_anulacoes WHERE vacinacao_id = ?');
    $stmt->execute([$vacinacao['id']]);
    verificar_teste((int) $stmt->fetchColumn() === 1, 'vacinação não pode ser anulada duas vezes', $falhas, $total);
    foreach (['rec', 'enf', 'adm'] as $abrev) {
        $resposta = pedido_http_teste($base . '/crianca_detalhe.php?id=' . $criancaAnulada, $cookies[$abrev]);
        verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Anulada') !== false, "histórico marca dose anulada para {$abrev}", $falhas, $total);
        verificar_teste(strpos($resposta['corpo'], 'name="motivo"') === false, "dose anulada não oferece nova anulação para {$abrev}", $falhas, $total);
    }
    $resposta = pedido_http_teste($base . '/crianca_detalhe.php?id=' . $criancaAnulada, $cookies['sto']);
    verificar_teste($resposta['status'] === 403, 'responsável de stock não consulta ficha clínica', $falhas, $total);

    $atendimentos[5] = agenda_f($pdo, $criancas[5], [1]);
    $atendimentos[6] = agenda_f($pdo, $criancas[6], [1]);
    $token1 = csrf_do_html(pedido_http_teste($base . '/vacinacao_form.php?atendimento_id=' . $atendimentos[5], $cookies['enf'])['corpo']);
    $token2 = csrf_do_html(pedido_http_teste($base . '/vacinacao_form.php?atendimento_id=' . $atendimentos[6], $cookies['enf2'])['corpo']);
    $dadosComum = ['origem' => 'posto', 'vacina_id' => 1, 'calendario_id' => 1, 'numero_dose' => 1, 'lote_id' => $ultima, 'local_anatomico' => '', 'reacoes' => '', 'observacoes' => ''];
    $respostas = simultaneos_f([
        ['url' => $base . '/vacinacao_registar.php', 'cookie' => $cookies['enf'], 'dados' => array_merge($dadosComum, ['csrf_token' => $token1, 'atendimento_id' => $atendimentos[5]])],
        ['url' => $base . '/vacinacao_registar.php', 'cookie' => $cookies['enf2'], 'dados' => array_merge($dadosComum, ['csrf_token' => $token2, 'atendimento_id' => $atendimentos[6]])],
    ]);
    $sucessos = 0;
    foreach ($respostas as $corpo) {
        $vaiParaCrianca1 = strpos($corpo, 'crianca_detalhe.php?id=' . $criancas[5]) !== false;
        $vaiParaCrianca2 = strpos($corpo, 'crianca_detalhe.php?id=' . $criancas[6]) !== false;
        if (strpos($corpo, 'Location:') !== false && ($vaiParaCrianca1 || $vaiParaCrianca2)) $sucessos++;
    }
    $saldoConcorrente = saldo_f($pdo, $ultima);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM vacinacoes WHERE crianca_id IN (?, ?)');
    $stmt->execute([$criancas[5], $criancas[6]]);
    $quantidadeConcorrente = (int) $stmt->fetchColumn();
    if ($sucessos !== 1 || $saldoConcorrente !== 0 || $quantidadeConcorrente !== 1) {
        foreach ($respostas as $indice => $corpo) {
            fwrite(STDERR, "Resposta concorrente {$indice}: " . substr($corpo, 0, 1200) . "\n");
        }
        fwrite(STDERR, "Estado concorrente: saldo={$saldoConcorrente}; vacinacoes={$quantidadeConcorrente}\n");
    }
    verificar_teste($sucessos === 1, 'dois pedidos simultâneos na última dose têm exatamente um sucesso', $falhas, $total);
    verificar_teste($saldoConcorrente === 0, 'concorrência deixa saldo final zero', $falhas, $total);
    verificar_teste($quantidadeConcorrente === 1, 'concorrência cria apenas uma vacinação', $falhas, $total);

    foreach (['enf', 'adm'] as $abrev) {
        foreach (['painel.php', 'vacinacao.php'] as $pagina) {
            $html = pedido_http_teste($base . '/' . $pagina, $cookies[$abrev])['corpo'];
            preg_match_all('/<li>(.*?)<\/li>/s', $html, $itens);
            $item = '';
            foreach ($itens[1] as $li) if (strpos($li, 'Vacinação') !== false) $item = $li;
            verificar_teste(strpos($item, 'Em desenvolvimento') === false && strpos($item, 'vacinacao.php') !== false, "menu Vacinação ativo em {$pagina} para {$abrev}", $falhas, $total);
        }
    }
    $resposta = pedido_http_teste($base . '/assets/js/vacinacao.js', $cookies['anonimo']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'addEventListener') !== false && strpos($resposta['corpo'], 'onchange') === false, 'JavaScript de vacinação é externo e usa listeners', $falhas, $total);
    foreach (['app/core/vacinacao.php', 'public/vacinacao.php', 'public/vacinacao_form.php', 'public/vacinacao_externa_form.php', 'public/vacinacao_registar.php', 'public/vacinacao_anular.php', 'app/views/partials/historico_vacinacao.php'] as $ficheiro) {
        $conteudo = file_get_contents(dirname(__DIR__) . '/' . $ficheiro);
        verificar_teste(preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $conteudo) !== 1 && preg_match('/\son[a-z]+\s*=/i', $conteudo) !== 1 && preg_match('/\sstyle\s*=/i', $conteudo) !== 1, "{$ficheiro} não contém inline", $falhas, $total);
    }
} catch (Throwable $e) {
    $falhas++;
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
} finally {
    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT id FROM criancas WHERE encarregado_id = ?");
        $stmt->execute([$encarregadoId]);
        $ids = array_map(static function ($linha) { return (int) $linha['id']; }, $stmt->fetchAll());
        if ($ids) {
            $m = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id FROM vacinacoes WHERE crianca_id IN ({$m})");
            $stmt->execute($ids);
            $vacIds = array_map(static function ($linha) { return (int) $linha['id']; }, $stmt->fetchAll());
            if ($vacIds) {
                $mv = implode(',', array_fill(0, count($vacIds), '?'));
                $pdo->prepare("DELETE FROM vacinacao_anulacoes WHERE vacinacao_id IN ({$mv})")->execute($vacIds);
                $pdo->prepare("DELETE FROM stock_movimentos WHERE vacinacao_id IN ({$mv})")->execute($vacIds);
                $pdo->prepare("DELETE FROM auditoria WHERE entidade = 'vacinacoes' AND entidade_id IN ({$mv})")->execute($vacIds);
            }
            $pdo->prepare("DELETE FROM vacinacoes WHERE crianca_id IN ({$m})")->execute($ids);
            $stmt = $pdo->prepare("SELECT id FROM atendimentos WHERE crianca_id IN ({$m})");
            $stmt->execute($ids);
            $atIds = array_map(static function ($linha) { return (int) $linha['id']; }, $stmt->fetchAll());
            if ($atIds) {
                $ma = implode(',', array_fill(0, count($atIds), '?'));
                $pdo->prepare("DELETE FROM atendimento_vacinas WHERE atendimento_id IN ({$ma})")->execute($atIds);
                $pdo->prepare("DELETE FROM auditoria WHERE entidade = 'atendimentos' AND entidade_id IN ({$ma})")->execute($atIds);
                $pdo->prepare("DELETE FROM atendimentos WHERE id IN ({$ma})")->execute($atIds);
            }
            $pdo->prepare("DELETE FROM criancas WHERE id IN ({$m})")->execute($ids);
        }
        if ($lotes) {
            $idsLotes = array_keys($lotes);
            $ml = implode(',', array_fill(0, count($idsLotes), '?'));
            $pdo->prepare("DELETE FROM stock_movimentos WHERE lote_id IN ({$ml})")->execute($idsLotes);
            $pdo->prepare("DELETE FROM auditoria WHERE entidade = 'stock_lotes' AND entidade_id IN ({$ml})")->execute($idsLotes);
            $pdo->prepare("DELETE FROM stock_lotes WHERE id IN ({$ml})")->execute($idsLotes);
        }
        if ($encarregadoId) $pdo->prepare('DELETE FROM encarregados WHERE id = ?')->execute([$encarregadoId]);
        foreach ($utilizadores as $dados) limpar_utilizador_teste($pdo, $dados['id'], $dados['nome']);
    }
    foreach ($cookies as $cookie) if (is_file($cookie)) unlink($cookie);
    if (is_dir($pasta)) rmdir($pasta);
}
if ($falhas > 0) {
    fwrite(STDERR, "{$falhas} de {$total} verificações falharam.\n");
    exit(1);
}
echo "Fase F: {$total} verificações concluídas sem falhas.\n";
