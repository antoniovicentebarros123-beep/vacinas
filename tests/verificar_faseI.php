<?php
/**
 * Testes da Fase I: demonstração, matriz de permissões, recursos e fecho.
 *
 * O cenário é gerado e limpo pelo próprio CLI de demonstração. As palavras-passe
 * fictícias são capturadas apenas em memória e nunca são escritas no relatório.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Este teste só pode ser executado em linha de comandos.\n");
}

require __DIR__ . '/suporte_http.php';

function executar_php_i(array $comando) {
    $processo = proc_open($comando, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($processo)) {
        throw new RuntimeException('Não foi possível iniciar o processo PHP.');
    }
    fclose($pipes[0]);
    $saida = stream_get_contents($pipes[1]);
    $erro = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [
        'status' => proc_close($processo),
        'saida' => $saida === false ? '' : $saida,
        'erro' => $erro === false ? '' : $erro,
    ];
}

function executar_demo_i($acao = null) {
    $comando = [PHP_BINARY, dirname(__DIR__) . '/bin/dados_demo.php'];
    if ($acao !== null) {
        $comando[] = $acao;
    }
    return executar_php_i($comando);
}

function senhas_demo_i($saida) {
    $senhas = [];
    preg_match_all('/^\s{2}(demo\.[a-z_]+): ([0-9a-f]{24})\s*$/m', $saida, $encontradas, PREG_SET_ORDER);
    foreach ($encontradas as $linha) {
        $senhas[$linha[1]] = $linha[2];
    }
    return $senhas;
}

function contagens_limpeza_i($saida) {
    $contagens = [];
    preg_match_all('/^\s{2}([a-z_]+): (\d+)\s*$/m', $saida, $encontradas, PREG_SET_ORDER);
    foreach ($encontradas as $linha) {
        $contagens[$linha[1]] = (int) $linha[2];
    }
    return $contagens;
}

function cookies_jar_i($caminho) {
    $cookies = [];
    foreach (file($caminho, FILE_IGNORE_NEW_LINES) ?: [] as $linha) {
        if (strpos($linha, '#HttpOnly_') === 0) {
            $linha = substr($linha, 9);
        } elseif ($linha === '' || $linha[0] === '#') {
            continue;
        }
        $partes = preg_split('/\s+/', $linha);
        if (count($partes) >= 7) {
            $cookies[$partes[5]] = $partes[6];
        }
    }
    return $cookies;
}

function login_gestao_i($base, $utilizador, $password, $cookie) {
    $inicial = pedido_http_teste($base . '/gestao/index.php', $cookie);
    return pedido_http_teste($base . '/gestao/index.php', $cookie, [
        'utilizador' => $utilizador,
        'password' => $password,
        'csrf_token' => csrf_do_html($inicial['corpo']),
    ]);
}

function sem_inline_i($conteudo) {
    return preg_match('/<script\b(?![^>]*\bsrc=)[^>]*>/i', $conteudo) !== 1
        && preg_match('/\son[a-z]+\s*=/i', $conteudo) !== 1
        && preg_match('/\sstyle\s*=/i', $conteudo) !== 1;
}

function sem_erros_php_i($conteudo) {
    return stripos($conteudo, 'Warning:') === false
        && stripos($conteudo, 'Notice:') === false
        && stripos($conteudo, 'Fatal error:') === false
        && stripos($conteudo, 'Deprecated:') === false;
}

function links_menu_i($html) {
    if (preg_match('/<nav aria-label="Secções">(.*?)<\/nav>/s', $html, $encontrado) !== 1) {
        return [];
    }
    preg_match_all('/href="([^"]+)"/', $encontrado[1], $links);
    return array_values(array_unique($links[1]));
}

function sem_botao_barra_i($html) {
    if (preg_match('/<aside\b[^>]*\bid="barra"[^>]*>(.*?)<\/aside>/s', $html, $encontrado) !== 1) {
        return false;
    }
    return stripos($encontrado[1], '<button') === false;
}

function item_fila_i(array $itens, $id) {
    foreach ($itens as $item) {
        if ((int) ($item['id'] ?? 0) === (int) $id) {
            return $item;
        }
    }
    return null;
}

$raiz = dirname(__DIR__);
$base = 'http://localhost/vacinacao-infantil-fase1a/vacinas/public';
$baseRaiz = 'http://localhost/vacinacao-infantil-fase1a/vacinas';
$sufixo = strtoupper(bin2hex(random_bytes(3)));
$pasta = sys_get_temp_dir() . '/vacinas_fasei_' . strtolower($sufixo);
mkdir($pasta);
$cookies = [];
$senhas = [];
$ids = [];
$demoGerada = false;
$falhas = 0;
$total = 0;
$pdo = conexao_teste_root();
$perfis = ['rec' => 'recepcao', 'enf' => 'enfermeiro', 'sto' => 'responsavel_stock', 'adm' => 'administrador'];
$todos = array_keys($perfis);
$nomeHospital = 'Centro Médico Hospitalar Maria de Fátima';

try {
    $scriptDemo = $raiz . '/bin/dados_demo.php';
    $conteudoDemo = file_get_contents($scriptDemo);
    verificar_teste(is_file($scriptDemo), 'gerador de dados demo existe', $falhas, $total);
    verificar_teste(strpos($conteudoDemo, "php_sapi_name() !== 'cli'") !== false, 'gerador demo recusa execução web', $falhas, $total);
    verificar_teste(strpos($conteudoDemo, 'bin2hex(random_bytes(12))') !== false && strpos($conteudoDemo, 'PASSWORD_ARGON2ID') !== false, 'palavras-passe demo são aleatórias e usam Argon2id', $falhas, $total);
    verificar_teste(stripos($conteudoDemo, 'DELETE FROM') === false, 'limpeza demo não contém eliminação física', $falhas, $total);

    $stmt = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'nome_posto' LIMIT 1");
    verificar_teste($stmt->fetchColumn() === $nomeHospital, 'base local usa o nome oficial do hospital', $falhas, $total);
    $migracaoNome = file_get_contents($raiz . '/database/update_nome_hospital.sql');
    verificar_teste(strpos($migracaoNome, 'SET NAMES utf8mb4') !== false && strpos($migracaoNome, 'UPDATE configuracoes') !== false && stripos($migracaoNome, 'DELETE FROM') === false && stripos($migracaoNome, 'CREATE TABLE') === false, 'migração do nome é idempotente, UTF-8 e não destrutiva', $falhas, $total);
    foreach (['database/seed.sql', 'config/config.local.example.php', 'config/config.local.php', 'REPLICAR_PAINEL.md', 'docs/Report.md'] as $ficheiroNome) {
        $conteudoNome = file_get_contents($raiz . '/' . $ficheiroNome);
        verificar_teste(strpos($conteudoNome, $nomeHospital) !== false, 'nome oficial presente em ' . $ficheiroNome, $falhas, $total);
    }
    $helpers = file_get_contents($raiz . '/app/core/helpers.php');
    verificar_teste(strpos($helpers, 'function nome_posto()') !== false && strpos($helpers, "config('nome_posto'") === false, 'helper lê o nome da base sem usar a configuração local', $falhas, $total);
    $nomeFixoAtivo = false;
    foreach (['app', 'public'] as $pastaAtiva) {
        $ficheirosAtivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/' . $pastaAtiva, FilesystemIterator::SKIP_DOTS));
        foreach ($ficheirosAtivos as $ficheiroAtivo) {
            if (!$ficheiroAtivo->isFile() || !in_array(strtolower($ficheiroAtivo->getExtension()), ['php', 'html', 'js'], true)) {
                continue;
            }
            if (strpos(file_get_contents($ficheiroAtivo->getPathname()), $nomeHospital) !== false) {
                $nomeFixoAtivo = true;
                break 2;
            }
        }
    }
    verificar_teste(!$nomeFixoAtivo, 'nome da instituição não está fixo em PHP, HTML ou JavaScript ativo', $falhas, $total);

    $sintaxe = executar_php_i([PHP_BINARY, '-l', $scriptDemo]);
    verificar_teste($sintaxe['status'] === 0, 'php -l do gerador demo passa', $falhas, $total);
    $ajuda = executar_demo_i();
    verificar_teste($ajuda['status'] === 0 && strpos($ajuda['saida'], 'Utilização:') !== false, 'CLI sem argumentos mostra ajuda e termina sem erro', $falhas, $total);
    $invalido = executar_demo_i('--invalido');
    verificar_teste($invalido['status'] === 2, 'CLI rejeita argumento inválido', $falhas, $total);
    $cookies['anonimo'] = $pasta . '/anonimo.txt';
    $resposta = pedido_http_teste($baseRaiz . '/bin/dados_demo.php', $cookies['anonimo']);
    verificar_teste($resposta['status'] === 403, 'ficheiro demo direto devolve HTTP 403', $falhas, $total);
    $resposta = pedido_http_teste($base . '/login.php', $cookies['anonimo']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], '<title>') !== false && strpos($resposta['corpo'], $nomeHospital) !== false && strpos($resposta['corpo'], 'login-nome') !== false, 'login principal mostra o nome oficial no título e no ecrã', $falhas, $total);
    $cookies['gestao_anonimo'] = $pasta . '/gestao_anonimo.txt';
    $resposta = pedido_http_teste($base . '/gestao/index.php', $cookies['gestao_anonimo']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], $nomeHospital) !== false, 'login da gestão mostra o nome oficial no título', $falhas, $total);

    $modulos = file_get_contents($raiz . '/app/core/modulos.php');
    $painelFonte = file_get_contents($raiz . '/public/painel.php');
    verificar_teste(strpos($modulos, 'Em desenvolvimento') === false && strpos($painelFonte, 'Em desenvolvimento') === false, 'não resta etiqueta Em desenvolvimento no código dos menus', $falhas, $total);

    $limpezaInicial = executar_demo_i('--limpar');
    verificar_teste($limpezaInicial['status'] === 0 && strpos($limpezaInicial['saida'], 'Limpeza lógica concluída') !== false, 'limpeza inicial do cenário demo é segura', $falhas, $total);

    $geracao = executar_demo_i('--gerar');
    verificar_teste($geracao['status'] === 0 && strpos($geracao['saida'], 'Dados fictícios gerados com sucesso') !== false, 'geração do cenário demo conclui', $falhas, $total);
    if ($geracao['status'] === 0) {
        $demoGerada = true;
        $senhas = senhas_demo_i($geracao['saida']);
        verificar_teste(count($senhas) === 4, 'geração devolve quatro credenciais fictícias em memória', $falhas, $total);
        $repetida = executar_demo_i('--gerar');
        verificar_teste($repetida['status'] === 1 && strpos($repetida['saida'], 'Já existem dados fictícios ativos') !== false, 'geração duplicada é recusada sem duplicar dados', $falhas, $total);
    } else {
        throw new RuntimeException('Não foi possível gerar o cenário demo para os testes HTTP.');
    }

    $consultasDemo = [
        'criancas' => ["SELECT COUNT(*) FROM criancas WHERE nome LIKE 'Teste Demo %' AND observacoes LIKE '%[DEMO]%' AND ativo = TRUE AND eliminado_em IS NULL", 6],
        'encarregados' => ["SELECT COUNT(*) FROM encarregados WHERE nome LIKE 'Teste Demo Encarregado %' AND morada LIKE '%[DEMO]%' AND ativo = TRUE AND eliminado_em IS NULL", 6],
        'lotes' => ["SELECT COUNT(*) FROM stock_lotes WHERE numero_lote LIKE 'DEMO-%' AND fabricante = 'Fabricante Fictício Demo' AND ativo = TRUE AND eliminado_em IS NULL", 4],
        'contas' => ["SELECT COUNT(*) FROM utilizadores WHERE utilizador IN ('demo.recepcao','demo.enfermeiro','demo.stock','demo.admin') AND ativo = TRUE", 4],
        'atendimentos' => ["SELECT COUNT(*) FROM atendimentos a INNER JOIN criancas c ON c.id = a.crianca_id WHERE c.nome LIKE 'Teste Demo %' AND c.observacoes LIKE '%[DEMO]%' AND c.ativo = TRUE AND c.eliminado_em IS NULL", 5],
        'vacinacoes' => ["SELECT COUNT(*) FROM vacinacoes vc INNER JOIN criancas c ON c.id = vc.crianca_id WHERE vc.observacoes LIKE '%[DEMO]%' AND c.ativo = TRUE AND c.eliminado_em IS NULL", 3],
        'validas' => ["SELECT COUNT(*) FROM vacinacoes vc INNER JOIN criancas c ON c.id = vc.crianca_id LEFT JOIN vacinacao_anulacoes va ON va.vacinacao_id = vc.id WHERE vc.observacoes LIKE '%[DEMO]%' AND c.ativo = TRUE AND c.eliminado_em IS NULL AND va.id IS NULL", 2],
        'contactos' => ["SELECT COUNT(*) FROM contactos_faltosos WHERE notas LIKE '%[DEMO]%' AND eliminado_em IS NULL", 3],
        'movimentos' => ["SELECT COUNT(*) FROM stock_movimentos sm INNER JOIN stock_lotes sl ON sl.id = sm.lote_id WHERE sl.numero_lote LIKE 'DEMO-%' AND sl.fabricante = 'Fabricante Fictício Demo' AND sl.ativo = TRUE AND sl.eliminado_em IS NULL AND sm.motivo LIKE '%[DEMO]%'", 7],
    ];
    foreach ($consultasDemo as $nome => [$sql, $esperado]) {
        $valor = (int) $pdo->query($sql)->fetchColumn();
        verificar_teste($valor === $esperado, "cenário demo tem {$esperado} {$nome}", $falhas, $total);
    }

    $buscarId = function ($sql, $parametros = []) use ($pdo) {
        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($parametros);
        return (int) ($stmt->fetchColumn() ?: 0);
    };
    $ids['crianca1'] = $buscarId("SELECT id FROM criancas WHERE nome = 'Teste Demo 01' AND ativo = TRUE");
    $ids['crianca2'] = $buscarId("SELECT id FROM criancas WHERE nome = 'Teste Demo 02' AND ativo = TRUE");
    $ids['crianca4'] = $buscarId("SELECT id FROM criancas WHERE nome = 'Teste Demo 04' AND ativo = TRUE");
    $ids['crianca6'] = $buscarId("SELECT id FROM criancas WHERE nome = 'Teste Demo 06' AND ativo = TRUE");
    $ids['encarregado'] = $buscarId('SELECT encarregado_id FROM criancas WHERE id = ?', [$ids['crianca1']]);
    $ids['aguardando'] = $buscarId("SELECT id FROM atendimentos WHERE crianca_id = ? AND estado = 'aguardando'", [$ids['crianca2']]);
    $ids['em_curso'] = $buscarId("SELECT id FROM atendimentos WHERE crianca_id = (SELECT id FROM criancas WHERE nome = 'Teste Demo 03' AND ativo = TRUE AND eliminado_em IS NULL) AND estado = 'em_atendimento'");
    $ids['concluido'] = $buscarId("SELECT id FROM atendimentos WHERE crianca_id = ? AND estado = 'concluido'", [$ids['crianca1']]);
    $ids['lote'] = $buscarId("SELECT id FROM stock_lotes WHERE numero_lote = 'DEMO-BCG-01' AND ativo = TRUE");
    $ids['vacinacao'] = $buscarId("SELECT id FROM vacinacoes WHERE crianca_id = ? AND origem = 'posto'", [$ids['crianca1']]);
    $ids['contacto'] = $buscarId('SELECT id FROM contactos_faltosos WHERE crianca_id = ? AND eliminado_em IS NULL', [$ids['crianca4']]);
    verificar_teste(count(array_filter($ids)) === count($ids), 'todos os IDs demo necessários foram encontrados', $falhas, $total);

    $saldoBcg = (int) $pdo->query("SELECT saldo_atual FROM stock_saldo_lotes WHERE numero_lote = 'DEMO-BCG-01'")->fetchColumn();
    $saldoPolio = (int) $pdo->query("SELECT saldo_atual FROM stock_saldo_lotes WHERE numero_lote = 'DEMO-POLIO-01'")->fetchColumn();
    verificar_teste($saldoBcg === 9 && $saldoPolio === 3, 'saldos demo refletem saída, perda e ajuste', $falhas, $total);

    foreach ($perfis as $abrev => $perfil) {
        $utilizador = ['rec' => 'demo.recepcao', 'enf' => 'demo.enfermeiro', 'sto' => 'demo.stock', 'adm' => 'demo.admin'][$abrev];
        $cookies[$abrev] = $pasta . '/' . $abrev . '.txt';
        $login = login_http_teste($base, $utilizador, $senhas[$utilizador], $cookies[$abrev]);
        verificar_teste($login['status'] === 302 && strpos($login['cabecalhos'], 'painel.php') !== false, "login HTTP do perfil {$perfil}", $falhas, $total);
    }

    $cookies['enf_gestao'] = $pasta . '/enf_gestao.txt';
    $resposta = pedido_http_teste($base . '/gestao/funcionarios.php', $cookies['enf']);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'gestao/index.php') !== false, 'sessão principal de enfermeiro não abre a gestão', $falhas, $total);
    $resposta = pedido_http_teste($base . '/gestao/funcionarios.php', $cookies['adm']);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'gestao/index.php') !== false, 'sessão principal de administrador também não abre a gestão', $falhas, $total);
    $loginEnfGestao = login_gestao_i($base, 'demo.enfermeiro', $senhas['demo.enfermeiro'], $cookies['enf_gestao']);
    verificar_teste($loginEnfGestao['status'] === 302 && strpos($loginEnfGestao['cabecalhos'], 'gestao/index.php') !== false, 'credenciais de enfermeiro não entram na gestão', $falhas, $total);
    $resposta = pedido_http_teste($base . '/gestao/index.php', $cookies['enf_gestao']);
    verificar_teste(strpos($resposta['corpo'], 'exclusiva a administradores') !== false, 'recusa da gestão é explícita e sem dados', $falhas, $total);

    $cookies['adm_gestao'] = $pasta . '/adm_gestao.txt';
    $loginAdmGestao = login_gestao_i($base, 'demo.admin', $senhas['demo.admin'], $cookies['adm_gestao']);
    verificar_teste($loginAdmGestao['status'] === 302 && strpos($loginAdmGestao['cabecalhos'], 'gestao/funcionarios.php') !== false, 'administrador entra na gestão com sessão própria', $falhas, $total);
    $cookiesGestao = cookies_jar_i($cookies['adm_gestao']);
    $cookiesPrincipais = cookies_jar_i($cookies['adm']);
    verificar_teste(array_keys($cookiesGestao) === ['VACINAS_GESTAO'], 'cookie da gestão é independente', $falhas, $total);
    verificar_teste(isset($cookiesPrincipais['PHPSESSID'], $cookiesPrincipais['VACINAS_GESTAO']) && $cookiesPrincipais['PHPSESSID'] !== $cookiesPrincipais['VACINAS_GESTAO'], 'cookies principal e de gestão têm nomes e valores distintos', $falhas, $total);
    $resposta = pedido_http_teste($base . '/gestao/funcionarios.php', $cookies['adm_gestao']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Teste Demo') !== false, 'gestão lista funcionários fictícios', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], $nomeHospital) !== false && sem_botao_barra_i($resposta['corpo']), 'layout da gestão mostra o hospital e não tem botão na barra lateral', $falhas, $total);
    $resposta = pedido_http_teste($base . '/gestao/index.php', $cookies['anonimo'], ['csrf_token' => 'invalido', 'utilizador' => 'x', 'password' => 'x']);
    verificar_teste($resposta['status'] === 403, 'login da gestão rejeita CSRF inválido', $falhas, $total);

    $paginas = [
        ['painel.php', $todos],
        ['criancas.php', ['rec', 'enf', 'adm']],
        ['crianca_form.php', ['rec']],
        ['crianca_detalhe.php?id=' . $ids['crianca1'], ['rec', 'enf', 'adm']],
        ['encarregados.php', ['rec', 'enf', 'adm']],
        ['encarregado_form.php?id=' . $ids['encarregado'], ['rec']],
        ['atendimento.php', ['rec', 'enf', 'adm']],
        ['atendimento_form.php?crianca_id=' . $ids['crianca1'], ['rec']],
        ['atendimento_detalhe.php?id=' . $ids['aguardando'], ['rec', 'enf', 'adm']],
        ['vacinacao.php', ['enf', 'adm']],
        ['vacinacao_form.php?atendimento_id=' . $ids['em_curso'], ['enf']],
        ['vacinacao_externa_form.php?crianca_id=' . $ids['crianca1'], ['enf']],
        ['faltosos.php', ['rec', 'enf', 'adm']],
        ['faltoso_detalhe.php?id=' . $ids['crianca4'], ['rec', 'enf', 'adm']],
        ['stock.php', ['enf', 'sto', 'adm']],
        ['stock_lote_detalhe.php?id=' . $ids['lote'], ['enf', 'sto', 'adm']],
        ['stock_lote_form.php', ['sto']],
        ['stock_movimento_form.php?lote_id=' . $ids['lote'], ['sto']],
        ['relatorios.php', ['enf', 'sto', 'adm']],
        ['relatorios.csv.php?tipo=sem_vacina', ['enf', 'sto', 'adm']],
        ['cartao_vacinas.php?id=' . $ids['crianca1'], ['rec', 'enf', 'adm']],
        ['disponibilidade.php', $todos],
        ['fila.php', ['enf', 'adm']],
    ];
    foreach ($paginas as [$caminho, $permitidos]) {
        foreach ($perfis as $abrev => $perfil) {
            $esperado = in_array($abrev, $permitidos, true) ? 200 : 403;
            $resposta = pedido_http_teste($base . '/' . $caminho, $cookies[$abrev]);
            verificar_teste($resposta['status'] === $esperado, "matriz {$caminho}: {$perfil} recebe {$esperado}", $falhas, $total);
            if ($esperado === 200) {
                verificar_teste(sem_erros_php_i($resposta['corpo']), "matriz {$caminho}: sem erros PHP para {$perfil}", $falhas, $total);
                verificar_teste(stripos($resposta['corpo'], '/gestao/') === false, "matriz {$caminho}: sem ligação para gestão", $falhas, $total);
            }
        }
        $resposta = pedido_http_teste($base . '/' . $caminho, $cookies['anonimo']);
        verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'login.php') !== false, "sessão anónima redirecionada em {$caminho}", $falhas, $total);
    }

    $resposta = pedido_http_teste($base . '/alterar_password.php', $cookies['anonimo']);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'login.php') !== false, 'alteração de palavra-passe exige sessão', $falhas, $total);
    foreach ($perfis as $abrev => $perfil) {
        $resposta = pedido_http_teste($base . '/alterar_password.php', $cookies[$abrev]);
        verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'painel.php') !== false, "alteração de palavra-passe redireciona {$perfil} sem troca pendente", $falhas, $total);
    }

    $endpoints = [
        'crianca_eliminar.php' => ['rec'],
        'encarregado_eliminar.php' => ['rec'],
        'atendimento_estado.php' => ['rec', 'enf'],
        'vacinacao_registar.php' => ['enf'],
        'vacinacao_anular.php' => ['enf', 'adm'],
        'faltoso_contacto.php' => ['rec', 'enf'],
        'faltoso_contacto_eliminar.php' => ['rec', 'enf'],
        'stock_movimento.php' => ['sto'],
    ];
    $tokens = [];
    foreach ($todos as $abrev) {
        $tokens[$abrev] = csrf_do_html(pedido_http_teste($base . '/painel.php', $cookies[$abrev])['corpo']);
    }
    foreach ($endpoints as $caminho => $permitidos) {
        foreach ($perfis as $abrev => $perfil) {
            $autorizado = in_array($abrev, $permitidos, true);
            $resposta = pedido_http_teste($base . '/' . $caminho, $cookies[$abrev]);
            $esperado = $autorizado ? 405 : 403;
            verificar_teste($resposta['status'] === $esperado, "endpoint {$caminho}: {$perfil} recebe {$esperado} em GET", $falhas, $total);
            $resposta = pedido_http_teste($base . '/' . $caminho, $cookies[$abrev], ['csrf_token' => 'invalido']);
            verificar_teste($resposta['status'] === 403, "endpoint {$caminho}: CSRF inválido recusado para {$perfil}", $falhas, $total);
            $resposta = pedido_http_teste($base . '/' . $caminho, $cookies[$abrev], ['csrf_token' => $tokens[$abrev]]);
            if ($autorizado) {
                verificar_teste($resposta['status'] < 500 && $resposta['status'] !== 403 && $resposta['status'] !== 405, "endpoint {$caminho}: perfil autorizado não é bloqueado por permissão", $falhas, $total);
            } else {
                verificar_teste($resposta['status'] === 403, "endpoint {$caminho}: perfil sem direito continua com 403", $falhas, $total);
            }
        }
        $resposta = pedido_http_teste($base . '/' . $caminho, $cookies['anonimo']);
        verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'login.php') !== false, "endpoint {$caminho}: sessão anónima redirecionada", $falhas, $total);
    }

    $tiposRelatorio = [
        'sem_vacina' => ['enf', 'sto', 'adm'],
        'vacinacoes' => ['enf', 'adm'],
        'stock' => ['sto', 'adm'],
        'faltosos' => ['enf', 'adm'],
        'auditoria' => ['adm'],
    ];
    foreach ($tiposRelatorio as $tipo => $permitidos) {
        foreach ($perfis as $abrev => $perfil) {
            $esperado = in_array($abrev, $permitidos, true) ? 200 : 403;
            foreach (['relatorios.php?tipo=' . $tipo, 'relatorios.csv.php?tipo=' . $tipo] as $caminho) {
                $resposta = pedido_http_teste($base . '/' . $caminho, $cookies[$abrev]);
                verificar_teste($resposta['status'] === $esperado, "relatório {$tipo} em {$caminho}: {$perfil} recebe {$esperado}", $falhas, $total);
            }
        }
    }

    $mapaMenu = [
        'painel.php' => $todos,
        'criancas.php' => ['rec', 'enf', 'adm'],
        'atendimento.php' => ['rec', 'enf', 'adm'],
        'vacinacao.php' => ['enf', 'adm'],
        'faltosos.php' => ['rec', 'enf', 'adm'],
        'stock.php' => ['enf', 'sto', 'adm'],
        'relatorios.php' => ['enf', 'sto', 'adm'],
    ];
    foreach ($perfis as $abrev => $perfil) {
        $resposta = pedido_http_teste($base . '/painel.php', $cookies[$abrev]);
        $html = $resposta['corpo'];
        verificar_teste(strpos($html, 'data-menu="fechado"') !== false && strpos($html, 'aria-controls="barra"') !== false && strpos($html, 'aria-expanded="false"') !== false, "menu móvel tem estrutura acessível para {$perfil}", $falhas, $total);
        verificar_teste(sem_botao_barra_i($html) && strpos($html, 'class="botao botao-secundario botao-icone botao-menu"') !== false, "menu móvel mantém apenas o hambúrguer para {$perfil}", $falhas, $total);
        verificar_teste(strpos($html, $nomeHospital) !== false && strpos($html, '<title>') !== false, "painel de {$perfil} mostra o nome oficial", $falhas, $total);
        verificar_teste(strpos($html, 'Em desenvolvimento') === false && sem_inline_i($html), "painel de {$perfil} sem etiqueta pendente ou inline", $falhas, $total);
        $links = links_menu_i($html);
        foreach ($mapaMenu as $arquivo => $permitidos) {
            $presente = false;
            foreach ($links as $link) {
                if (strpos($link, '/' . $arquivo) !== false) {
                    $presente = true;
                }
            }
            $esperado = in_array($abrev, $permitidos, true);
            verificar_teste($presente === $esperado, "menu de {$perfil}: {$arquivo} " . ($esperado ? 'é link' : 'não é link'), $falhas, $total);
            if ($esperado) {
                $destino = pedido_http_teste($base . '/' . $arquivo, $cookies[$abrev]);
                verificar_teste($destino['status'] === 200, "link do menu {$arquivo} funciona para {$perfil}", $falhas, $total);
            }
        }
        $modulo = $abrev === 'rec' ? 'criancas.php' : ($abrev === 'sto' ? 'stock.php' : 'relatorios.php');
        $resposta = pedido_http_teste($base . '/' . $modulo, $cookies[$abrev]);
        verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Em desenvolvimento') === false && sem_inline_i($resposta['corpo']), "página de módulo sem etiqueta ou inline para {$perfil}", $falhas, $total);
    }
    $jsModulos = file_get_contents($raiz . '/public/assets/js/modulos.js');
    $jsPainel = file_get_contents($raiz . '/public/assets/js/painel.js');
    $jsLogin = file_get_contents($raiz . '/public/assets/js/login.js');
    foreach ([$jsModulos, $jsPainel] as $js) {
        verificar_teste(strpos($js, "addEventListener('click'") !== false && strpos($js, "addEventListener('keydown'") !== false && strpos($js, 'Escape') !== false, 'JavaScript do menu usa listeners e fecha por Escape', $falhas, $total);
        verificar_teste(strpos($js, 'onclick=') === false, 'JavaScript do menu não usa eventos inline', $falhas, $total);
        verificar_teste(strpos($js, 'primeiroItemMenu.focus()') !== false && strpos($js, 'botaoMenu.focus()') !== false && strpos($js, "!evento.target.closest('.barra-lateral')") !== false && strpos($js, 'Fechar navegação') !== false, 'menu abre com foco no primeiro item e devolve o foco ao hambúrguer ao fechar', $falhas, $total);
    }
    verificar_teste(strpos($jsLogin, 'if (olhoBtn && passwordInput)') !== false && strpos($jsLogin, 'if (esqueciBtn)') !== false, 'JavaScript do login tolera os layouts principal e de gestão', $falhas, $total);
    $cssModulos = file_get_contents($raiz . '/public/assets/css/modulos.css');
    verificar_teste(preg_match('/\.cartao-documento\s*\{[^}]*min-width:\s*0/', $cssModulos) === 1, '2.ª via pode encolher dentro do viewport móvel', $falhas, $total);

    $resposta = pedido_http_teste($base . '/criancas.php?q=' . rawurlencode('Teste Demo 01'), $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Teste Demo 01') !== false, 'pesquisa encontra criança demo', $falhas, $total);
    $resposta = pedido_http_teste($base . '/crianca_detalhe.php?id=' . $ids['crianca1'], $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'BCG') !== false && strpos($resposta['corpo'], 'Valores clínicos por validar') !== false, 'ficha demo mostra histórico e calendário indicativo', $falhas, $total);
    $resposta = pedido_http_teste($base . '/atendimento.php', $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Teste Demo 02') !== false, 'atendimento lista a criança na fila demo', $falhas, $total);

    $resposta = pedido_http_teste($base . '/fila.php', $cookies['enf']);
    $fila = json_decode($resposta['corpo'], true);
    $item = item_fila_i($fila['itens'] ?? [], $ids['aguardando']);
    verificar_teste($resposta['status'] === 200 && is_array($item), 'fila JSON contém atendimento demo aguardando', $falhas, $total);
    verificar_teste(is_array($item) && array_keys($item) === ['id', 'primeiro_nome', 'idade', 'vacinas', 'chegada'], 'fila JSON expõe apenas os cinco campos mínimos', $falhas, $total);
    verificar_teste(is_array($item) && $item['primeiro_nome'] === 'Teste' && $item['vacinas'] === ['Polio oral'] && preg_match('/^\d{2}:\d{2}$/', $item['chegada']) === 1, 'fila JSON tem primeiro nome, idade, vacina e chegada', $falhas, $total);
    verificar_teste(stripos($resposta['corpo'], 'lote') === false && stripos($resposta['corpo'], 'saldo') === false && stripos($resposta['corpo'], 'quantidade') === false && strpos($resposta['corpo'], 'DEMO-') === false, 'fila JSON não expõe stock nem identificadores de demo', $falhas, $total);
    verificar_teste(strpos($resposta['cabecalhos'], "Content-Security-Policy: default-src 'none'") !== false, 'fila JSON usa CSP restrito', $falhas, $total);

    foreach (['disponibilidade.php', 'atendimento_form.php?crianca_id=' . $ids['crianca1']] as $caminho) {
        $resposta = pedido_http_teste($base . '/' . $caminho, $cookies['rec']);
        $semStock = strpos($resposta['corpo'], 'DEMO-') === false
            && stripos($resposta['corpo'], 'saldo_atual') === false
            && stripos($resposta['corpo'], 'quantidade_inicial') === false
            && preg_match('/\b\d+\s+doses\b/i', $resposta['corpo']) !== 1;
        verificar_teste($resposta['status'] === 200 && $semStock, "recepção vê apenas disponibilidade em {$caminho}", $falhas, $total);
    }

    $resposta = pedido_http_teste($base . '/vacinacao.php', $cookies['enf']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Teste Demo 03') !== false && strpos($resposta['corpo'], 'vacinacao_form.php?atendimento_id=' . $ids['em_curso']) !== false, 'vacinação lista atendimento em curso demo', $falhas, $total);
    $resposta = pedido_http_teste($base . '/stock.php', $cookies['sto']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'DEMO-BCG-01') !== false && strpos($resposta['corpo'], '9 doses') !== false && strpos($resposta['corpo'], '3 doses') !== false, 'stock demo mostra lotes e saldos calculados', $falhas, $total);
    $resposta = pedido_http_teste($base . '/stock.php', $cookies['enf']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Consulta apenas') !== false && strpos($resposta['corpo'], 'Novo lote') === false, 'enfermeiro consulta stock sem ações de escrita', $falhas, $total);

    $resposta = pedido_http_teste($base . '/faltosos.php?estado=todos&q=' . rawurlencode('Teste Demo'), $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Teste Demo 04') !== false && strpos($resposta['corpo'], 'Valores clínicos por validar pela equipa clínica') !== false, 'faltosos lista crianças demo com aviso clínico', $falhas, $total);
    $resposta = pedido_http_teste($base . '/faltoso_detalhe.php?id=' . $ids['crianca4'], $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Sem resposta (demo)') !== false && strpos($resposta['corpo'], 'Encarregado contactado (demo)') !== false, 'detalhe de faltoso mostra contactos demo', $falhas, $total);

    $hoje = (string) $pdo->query('SELECT CURDATE()')->fetchColumn();
    $inicioRelatorio = (new DateTimeImmutable($hoje))->modify('-60 days')->format('Y-m-d');
    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=sem_vacina&data_inicio=' . $hoje . '&data_fim=' . $hoje, $cookies['adm']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Teste Demo 04') !== false && strpos($resposta['corpo'], 'Vacina sem stock fictícia') !== false, 'relatório sem vacina apresenta resultado demo', $falhas, $total);
    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=sem_vacina&data_inicio=' . $hoje . '&data_fim=' . $hoje, $cookies['sto']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Teste Demo 04') !== false && strpos($resposta['corpo'], 'crianca_detalhe.php') === false, 'relatório de stock não oferece link proibido para ficha da criança', $falhas, $total);
    $csv = pedido_http_teste($base . '/relatorios.csv.php?tipo=sem_vacina&data_inicio=' . $hoje . '&data_fim=' . $hoje, $cookies['adm']);
    verificar_teste($csv['status'] === 200 && substr($csv['corpo'], 0, 3) === "\xEF\xBB\xBF" && strpos($csv['cabecalhos'], 'text/csv') !== false && strpos($csv['corpo'], 'Teste Demo 04') !== false, 'CSV do relatório demo é UTF-8 e contém o resultado', $falhas, $total);
    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=vacinacoes&data_inicio=' . $inicioRelatorio . '&data_fim=' . $hoje, $cookies['enf']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'BCG') !== false && strpos($resposta['corpo'], 'Polio oral') !== false && strpos($resposta['corpo'], 'Anulada') !== false, 'relatório de vacinações identifica válidas e anulada demo', $falhas, $total);
    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=stock', $cookies['sto']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'DEMO-BCG-01') !== false, 'relatório de stock apresenta lote demo', $falhas, $total);
    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=faltosos&data_inicio=' . $hoje . '&data_fim=' . $hoje, $cookies['adm']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Teste Demo 04') !== false, 'relatório de faltosos apresenta contactos demo', $falhas, $total);
    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=auditoria&data_inicio=' . $hoje . '&data_fim=' . $hoje, $cookies['adm']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], '[DEMO]') !== false, 'auditoria demo permanece visível ao administrador', $falhas, $total);

    $nomeAnulada = (string) $pdo->query('SELECT nome FROM vacinas WHERE id = 15')->fetchColumn();
    $resposta = pedido_http_teste($base . '/cartao_vacinas.php?id=' . $ids['crianca6'], $cookies['enf']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Polio oral') !== false && strpos($resposta['corpo'], $nomeAnulada) === false, '2.ª via exclui vacina demo anulada', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], $nomeHospital) !== false && sem_botao_barra_i($resposta['corpo']), '2.ª via mostra o hospital e não contém botão na barra lateral', $falhas, $total);
    $resposta = pedido_http_teste($base . '/crianca_detalhe.php?id=' . $ids['crianca6'], $cookies['enf']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Anulada') !== false, 'histórico da criança marca vacinação demo anulada', $falhas, $total);
    $resposta = pedido_http_teste($base . '/cartao_vacinas.php?id=' . $ids['crianca1'], $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Consulta apenas') !== false && strpos($resposta['corpo'], 'data-acao="imprimir-cartao"') === false, 'recepção consulta 2.ª via sem imprimir', $falhas, $total);
    $resposta = pedido_http_teste($base . '/cartao_vacinas.php?id=' . $ids['crianca1'], $cookies['adm']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'data-acao="imprimir-cartao"') !== false && strpos($resposta['corpo'], 'cartao.js') !== false, 'administrador dispõe de impressão externa', $falhas, $total);

    $stmt = $pdo->query(
        "SELECT COUNT(*) AS criancas FROM criancas WHERE ativo = TRUE AND eliminado_em IS NULL"
    );
    $esperadoCriancas = (int) $stmt->fetch()['criancas'];
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS vacinacoes FROM vacinacoes vc INNER JOIN criancas c ON c.id = vc.crianca_id WHERE c.ativo = TRUE AND c.eliminado_em IS NULL"
    );
    $esperadoVacinacoes = (int) $stmt->fetch()['vacinacoes'];
    $stmt = $pdo->query(
        "SELECT COALESCE(SUM(a.estado = 'aguardando'),0) AS aguardando,
                COALESCE(SUM(a.estado = 'em_atendimento'),0) AS em_atendimento,
                COALESCE(SUM(a.estado = 'concluido' AND DATE(a.concluido_em) = CURDATE()),0) AS concluidos
         FROM atendimentos a INNER JOIN criancas c ON c.id = a.crianca_id
         WHERE c.ativo = TRUE AND c.eliminado_em IS NULL"
    );
    $esperadoHoje = $stmt->fetch();
    $resposta = pedido_http_teste($base . '/painel.php', $cookies['adm']);
    preg_match('/<section class="faixa-hoje".*?<\/section>/s', $resposta['corpo'], $faixa);
    preg_match_all('/<strong>(\d+)<\/strong>/', $faixa[0] ?? '', $valores);
    preg_match_all('/<article class="cartao resumo">.*?<strong>(\d+)<\/strong>/s', $resposta['corpo'], $resumos);
    verificar_teste(array_map('intval', $valores[1] ?? []) === [(int) $esperadoHoje['aguardando'], (int) $esperadoHoje['em_atendimento'], (int) $esperadoHoje['concluidos']], 'painel mostra hoje valores reais com dados demo', $falhas, $total);
    verificar_teste(array_map('intval', $resumos[1] ?? []) === [$esperadoCriancas, $esperadoVacinacoes], 'painel mostra contagens reais de crianças e vacinações', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], 'Cálculo ainda não disponível') === false, 'painel não contém cálculo pendente', $falhas, $total);
    verificar_teste(strpos($resposta['cabecalhos'], "Content-Security-Policy: default-src 'self'") !== false, 'painel mantém CSP default-src self', $falhas, $total);

    $tokenGestao = csrf_do_html(pedido_http_teste($base . '/gestao/funcionarios.php', $cookies['adm_gestao'])['corpo']);
    foreach (['funcionario_estado.php', 'funcionario_password.php'] as $endpoint) {
        $resposta = pedido_http_teste($base . '/gestao/' . $endpoint, $cookies['adm_gestao']);
        verificar_teste($resposta['status'] === 405, "gestão {$endpoint} exige POST", $falhas, $total);
        $resposta = pedido_http_teste($base . '/gestao/' . $endpoint, $cookies['adm_gestao'], ['csrf_token' => 'invalido']);
        verificar_teste($resposta['status'] === 403, "gestão {$endpoint} exige CSRF", $falhas, $total);
        $resposta = pedido_http_teste($base . '/gestao/' . $endpoint, $cookies['adm_gestao'], ['csrf_token' => $tokenGestao]);
        verificar_teste($resposta['status'] < 500 && $resposta['status'] !== 403 && $resposta['status'] !== 405, "gestão {$endpoint} aceita administrador autenticado", $falhas, $total);
    }

    $extensoes = ['css', 'js', 'woff2', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'ico'];
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/public/assets', FilesystemIterator::SKIP_DOTS));
    $recursos = 0;
    foreach ($iterador as $ficheiro) {
        if (!$ficheiro->isFile()) {
            continue;
        }
        $extensao = strtolower($ficheiro->getExtension());
        if (!in_array($extensao, $extensoes, true)) {
            continue;
        }
        $relativo = str_replace('\\', '/', substr($ficheiro->getPathname(), strlen($raiz . '/public/assets/')));
        $resposta = pedido_http_teste($base . '/assets/' . $relativo, $cookies['anonimo']);
        verificar_teste($resposta['status'] === 200, "recurso estático HTTP 200: assets/{$relativo}", $falhas, $total);
        if ($extensao === 'css') {
            verificar_teste(stripos($resposta['cabecalhos'], 'text/css') !== false, "recurso CSS tem tipo correto: {$relativo}", $falhas, $total);
            verificar_teste(strpos(file_get_contents($ficheiro->getPathname()), 'url(/') === false, "CSS sem url absoluto: {$relativo}", $falhas, $total);
        } elseif ($extensao === 'js') {
            verificar_teste(stripos($resposta['cabecalhos'], 'javascript') !== false && strpos($resposta['corpo'], '<?php') === false, "recurso JS puro e com tipo correto: {$relativo}", $falhas, $total);
        } elseif ($extensao === 'svg') {
            verificar_teste(stripos($resposta['cabecalhos'], 'image/svg+xml') !== false, "ícone SVG com tipo correto: {$relativo}", $falhas, $total);
        }
        $recursos++;
    }
    verificar_teste($recursos >= 35, 'inventário contém todos os CSS, fontes, ícones e JavaScript esperados', $falhas, $total);

    $ficheirosPhp = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/public', FilesystemIterator::SKIP_DOTS));
    $ignorarInline = ['debug_login.php', 'test_usuario.php', 'criar_admin_final.php', 'criar_admin_novo.php'];
    foreach ($ficheirosPhp as $ficheiro) {
        if (!$ficheiro->isFile() || strtolower($ficheiro->getExtension()) !== 'php' || in_array($ficheiro->getFilename(), $ignorarInline, true)) {
            continue;
        }
        $conteudo = file_get_contents($ficheiro->getPathname());
        verificar_teste(sem_inline_i($conteudo), 'PHP ativo sem script, evento ou estilo inline: ' . $ficheiro->getFilename(), $falhas, $total);
    }

    $limpeza = executar_demo_i('--limpar');
    $contagens = contagens_limpeza_i($limpeza['saida']);
    verificar_teste($limpeza['status'] === 0, 'limpeza final do cenário demo conclui', $falhas, $total);
    foreach (['vacinacoes_anuladas' => 2, 'contactos' => 3, 'criancas' => 6, 'encarregados' => 6, 'lotes' => 4, 'utilizadores' => 4] as $chave => $esperado) {
        verificar_teste(($contagens[$chave] ?? -1) === $esperado, "limpeza desativa logicamente {$esperado} {$chave}", $falhas, $total);
    }
    $segundaLimpeza = executar_demo_i('--limpar');
    $contagensSegunda = contagens_limpeza_i($segundaLimpeza['saida']);
    verificar_teste($segundaLimpeza['status'] === 0 && $contagensSegunda && array_sum($contagensSegunda) === 0, 'segunda limpeza é idempotente', $falhas, $total);

    $verificacoesPosLimpeza = [
        ["SELECT COUNT(*) FROM criancas WHERE nome LIKE 'Teste Demo %' AND observacoes LIKE '%[DEMO]%' AND ativo = TRUE AND eliminado_em IS NULL", 0],
        ["SELECT COUNT(*) FROM encarregados WHERE nome LIKE 'Teste Demo Encarregado %' AND ativo = TRUE AND eliminado_em IS NULL", 0],
        ["SELECT COUNT(*) FROM stock_lotes WHERE numero_lote LIKE 'DEMO-%' AND fabricante = 'Fabricante Fictício Demo' AND ativo = TRUE AND eliminado_em IS NULL", 0],
        ["SELECT COUNT(*) FROM utilizadores WHERE utilizador IN ('demo.recepcao','demo.enfermeiro','demo.stock','demo.admin') AND ativo = TRUE", 0],
        ["SELECT COUNT(*) FROM vacinacoes vc LEFT JOIN vacinacao_anulacoes va ON va.vacinacao_id = vc.id WHERE vc.observacoes LIKE '%[DEMO]%' AND va.id IS NULL", 0],
        ["SELECT COUNT(*) FROM stock_saldo_lotes WHERE numero_lote LIKE 'DEMO-%' AND fabricante = 'Fabricante Fictício Demo'", 0],
        ["SELECT COUNT(*) FROM contactos_faltosos WHERE notas LIKE '%[DEMO]%' AND eliminado_em IS NULL", 0],
    ];
    foreach ($verificacoesPosLimpeza as [$sql, $esperado]) {
        verificar_teste((int) $pdo->query($sql)->fetchColumn() === $esperado, 'dados demo ativos ou válidos após limpeza: ' . $esperado, $falhas, $total);
    }
    foreach ([
        'movimentos' => "SELECT COUNT(*) FROM stock_movimentos sm INNER JOIN stock_lotes sl ON sl.id = sm.lote_id WHERE sl.numero_lote LIKE 'DEMO-%' AND sl.fabricante = 'Fabricante Fictício Demo' AND sm.motivo LIKE '%[DEMO]%'",
        'vacinacoes' => "SELECT COUNT(*) FROM vacinacoes WHERE observacoes LIKE '%[DEMO]%'",
        'anulacoes' => "SELECT COUNT(*) FROM vacinacao_anulacoes WHERE motivo LIKE '%[DEMO]%'",
        'auditoria' => "SELECT COUNT(*) FROM auditoria WHERE valor_novo LIKE '%[DEMO]%' OR valor_anterior LIKE '%[DEMO]%'",
    ] as $nome => $sql) {
        verificar_teste((int) $pdo->query($sql)->fetchColumn() > 0, "histórico append-only de {$nome} foi preservado", $falhas, $total);
    }

    $resposta = pedido_http_teste($base . '/crianca_detalhe.php?id=' . $ids['crianca1'], $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'eliminada logicamente') !== false && strpos($resposta['corpo'], 'BCG') !== false, 'ficha histórica da criança demo permanece acessível após limpeza', $falhas, $total);
    $resposta = pedido_http_teste($base . '/atendimento_detalhe.php?id=' . $ids['concluido'], $cookies['rec']);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'atendimento.php') !== false, 'atendimento de criança inativa não abre por URL direta', $falhas, $total);
    $resposta = pedido_http_teste($base . '/cartao_vacinas.php?id=' . $ids['crianca1'], $cookies['rec']);
    verificar_teste($resposta['status'] === 404, '2.ª via de criança inativa devolve 404', $falhas, $total);
    $resposta = pedido_http_teste($base . '/stock_lote_detalhe.php?id=' . $ids['lote'], $cookies['sto']);
    verificar_teste($resposta['status'] === 404, 'lote inativo não entra no saldo nem na consulta operacional', $falhas, $total);
    $resposta = pedido_http_teste($base . '/criancas.php?q=' . rawurlencode('Teste Demo'), $cookies['rec']);
    verificar_teste(strpos($resposta['corpo'], 'Teste Demo 01') === false, 'pesquisa operacional não devolve criança demo limpa', $falhas, $total);
    $resposta = pedido_http_teste($base . '/fila.php', $cookies['enf']);
    verificar_teste(strpos($resposta['corpo'], '"id":' . $ids['aguardando']) === false, 'fila não devolve atendimento demo limpo', $falhas, $total);
    $resposta = pedido_http_teste($base . '/stock.php', $cookies['sto']);
    verificar_teste(strpos($resposta['corpo'], 'DEMO-BCG-01') === false, 'stock operacional não devolve lote demo limpo', $falhas, $total);
    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=sem_vacina&data_inicio=' . $hoje . '&data_fim=' . $hoje, $cookies['adm']);
    verificar_teste(strpos($resposta['corpo'], 'Teste Demo 04') === false, 'relatório sem vacina não devolve criança demo limpa', $falhas, $total);

    $loginDemoLimpo = login_http_teste($base, 'demo.recepcao', $senhas['demo.recepcao'], $pasta . '/demo_limpo.txt');
    verificar_teste($loginDemoLimpo['status'] === 302 && strpos($loginDemoLimpo['cabecalhos'], 'login.php') !== false, 'conta demo desativada não autentica após limpeza', $falhas, $total);
} catch (Throwable $e) {
    $falhas++;
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
} finally {
    if ($demoGerada) {
        $limpezaEmergencia = executar_demo_i('--limpar');
        if ($limpezaEmergencia['status'] !== 0) {
            fwrite(STDERR, "ATENÇÃO: a limpeza de emergência do cenário demo falhou.\n");
        }
    }
    foreach (glob($pasta . '/*.txt') ?: [] as $ficheiro) {
        unlink($ficheiro);
    }
    if (is_dir($pasta)) {
        rmdir($pasta);
    }
}

fwrite($falhas === 0 ? STDOUT : STDERR, "Fase I: {$total} verificações, {$falhas} falhas.\n");
exit($falhas === 0 ? 0 : 1);
