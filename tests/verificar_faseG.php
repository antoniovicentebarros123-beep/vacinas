<?php
/**
 * Testes da Fase G: calendário indicativo, faltosos, contactos, CSRF e perfis.
 */

require __DIR__ . '/suporte_http.php';
require_once __DIR__ . '/../app/core/faltosos.php';

function oportunidade_g($id, $vacinaId, $dose, $ideal, array $extras = []) {
    return array_merge([
        'id' => $id,
        'vacina_id' => $vacinaId,
        'numero_dose' => $dose,
        'rotulo_idade' => $ideal . ' meses',
        'idade_ideal_meses' => $ideal,
        'janela_inicio_meses' => null,
        'janela_fim_meses' => null,
        'idade_minima_meses' => null,
        'intervalo_minimo_dias' => null,
        'dose_anterior_exigida' => null,
        'grupo_alternativa' => null,
        'dose_unica' => false,
        'validar_clinica' => true,
        'vacina_nome' => 'Vacina fictícia ' . $vacinaId,
    ], $extras);
}

function vacinacao_g($id, $criancaId, $vacinaId, $calendarioId, $dose, $data) {
    return [
        'id' => $id,
        'crianca_id' => $criancaId,
        'vacina_id' => $vacinaId,
        'calendario_id' => $calendarioId,
        'numero_dose' => $dose,
        'data_administracao' => $data,
    ];
}

function linha_menu_faltosos_g($html) {
    preg_match_all('/<li>(.*?)<\/li>/s', $html, $matches);
    foreach ($matches[1] as $item) {
        if (strpos($item, 'Faltosos') !== false) return $item;
    }
    return '';
}

function post_contacto_g($base, $cookie, $criancaId, array $dados, $token = null) {
    if ($token === null) {
        $pagina = pedido_http_teste($base . '/faltoso_detalhe.php?id=' . (int) $criancaId, $cookie);
        $token = csrf_do_html($pagina['corpo']);
    }
    return pedido_http_teste($base . '/faltoso_contacto.php', $cookie, array_merge([
        'csrf_token' => $token,
        'crianca_id' => $criancaId,
        'data_contacto' => date('Y-m-d'),
        'canal' => 'telefone',
        'resultado' => 'Contacto fictício',
        'notas' => '',
    ], $dados));
}

function post_eliminar_contacto_g($base, $cookie, $contactoId, $token = null) {
    if ($token === null) {
        $pagina = pedido_http_teste($base . '/faltoso_detalhe.php?id=' . (int) $GLOBALS['criancaContactoG'], $cookie);
        $token = csrf_do_html($pagina['corpo']);
    }
    return pedido_http_teste($base . '/faltoso_contacto_eliminar.php', $cookie, [
        'csrf_token' => $token,
        'id' => $contactoId,
    ]);
}

$base = 'http://localhost/vacinacao-infantil-fase1a/vacinas/public';
$sufixo = strtoupper(bin2hex(random_bytes(3)));
$password = 'FaseG-' . bin2hex(random_bytes(8)) . '!';
$pasta = sys_get_temp_dir() . '/vacinas_faseg_' . strtolower($sufixo);
mkdir($pasta);
$cookies = [];
$utilizadores = [];
$criancas = [];
$contactos = [];
$vacinacoes = [];
$encarregadoId = 0;
$criancaContactoG = 0;
$falhas = 0;
$total = 0;
$pdo = conexao_teste_root();

try {
    // Cálculo puro: datas, quatro estados, regras NULL e grupos alternativos.
    verificar_teste(data_mais_meses('2024-01-31', 1) === '2024-02-29', 'adição de meses limita o dia ao fim do mês', $falhas, $total);
    verificar_teste(data_mais_meses('2024-01-31', 2) === '2024-03-31', 'adição de meses preserva o dia quando possível', $falhas, $total);
    verificar_teste(data_mais_dias('2024-02-28', 2) === '2024-03-01', 'adição de dias atravessa corretamente o fim do mês', $falhas, $total);

    $criancaCalculo = ['data_nascimento' => '2024-01-01'];
    $opsCalculo = [
        oportunidade_g(1, 1, 1, 1),
        oportunidade_g(2, 2, 1, 2, ['idade_minima_meses' => 3]),
        oportunidade_g(3, 3, 1, 6),
    ];
    $estados = calcular_estado_calendario($criancaCalculo, $opsCalculo, [], '2024-04-01', 30);
    verificar_teste($estados[0]['data_prevista'] === '2024-02-01' && $estados[0]['estado'] === 'em_atraso', 'dose vencida é classificada em atraso', $falhas, $total);
    verificar_teste($estados[1]['data_elegivel'] === '2024-04-01' && $estados[1]['estado'] === 'a_vencer', 'idade mínima aplicada desloca a elegibilidade', $falhas, $total);
    verificar_teste($estados[2]['estado'] === 'em_dia', 'dose futura é classificada em dia', $falhas, $total);
    $feita = calcular_estado_calendario($criancaCalculo, $opsCalculo, [vacinacao_g(10, 1, 1, 1, 1, '2024-02-01')], '2024-04-01', 30);
    verificar_teste($feita[0]['estado'] === 'feita' && $feita[0]['feita'] === true, 'vacinação válida marca a dose como feita', $falhas, $total);

    $opsRegras = [
        oportunidade_g(5, 4, 1, 1),
        oportunidade_g(4, 4, 2, 2, ['intervalo_minimo_dias' => 10, 'dose_anterior_exigida' => 5]),
        oportunidade_g(8, 8, 1, 1, ['janela_inicio_meses' => 3]),
        oportunidade_g(9, 9, 2, 2, ['intervalo_minimo_dias' => null]),
    ];
    $semAnterior = calcular_estado_calendario($criancaCalculo, $opsRegras, [], '2024-04-01', 30);
    verificar_teste($semAnterior[1]['elegivel'] === false && strpos($semAnterior[1]['motivo_elegibilidade'], 'dose anterior') !== false, 'dose anterior exigida é respeitada', $falhas, $total);
    verificar_teste($semAnterior[2]['data_elegivel'] === '2024-04-01', 'início da janela é aplicado quando não é NULL', $falhas, $total);
    verificar_teste($semAnterior[3]['elegivel'] === true && $semAnterior[3]['motivo_elegibilidade'] === '', 'regra clínica NULL é ignorada', $falhas, $total);
    $comAnterior = calcular_estado_calendario(
        $criancaCalculo,
        $opsRegras,
        [vacinacao_g(11, 1, 4, 5, 1, '2024-03-25')],
        '2024-04-01',
        30
    );
    verificar_teste($comAnterior[1]['elegivel'] === true && $comAnterior[1]['data_elegivel'] === '2024-04-04', 'intervalo mínimo é somado à última dose anterior', $falhas, $total);

    $opsAlternativa = [
        oportunidade_g(26, 4, 4, 18, ['grupo_alternativa' => 'PENTA_OU_TETRA_18M']),
        oportunidade_g(27, 15, 1, 18, ['grupo_alternativa' => 'PENTA_OU_TETRA_18M']),
    ];
    $alternativa = calcular_estado_calendario(
        ['data_nascimento' => '2022-01-01'],
        $opsAlternativa,
        [vacinacao_g(12, 1, 15, 27, 1, '2024-01-01')],
        '2024-04-01',
        30
    );
    verificar_teste($alternativa[0]['feita'] && $alternativa[1]['feita'], 'uma dose do grupo alternativo cumpre as duas linhas', $falhas, $total);

    // Utilizadores e sessões HTTP.
    foreach (['rec' => 'recepcao', 'enf' => 'enfermeiro', 'sto' => 'responsavel_stock', 'adm' => 'administrador'] as $abrev => $perfil) {
        $nome = 'fg_' . strtolower($abrev) . '_' . strtolower($sufixo);
        $id = criar_utilizador_teste($pdo, $nome, $perfil, $password);
        $utilizadores[$abrev] = ['id' => $id, 'nome' => $nome];
        $cookies[$abrev] = $pasta . '/' . $abrev . '.txt';
        $login = login_http_teste($base, $nome, $password, $cookies[$abrev]);
        verificar_teste($login['status'] === 302 && strpos($login['cabecalhos'], 'painel.php') !== false, "login HTTP do perfil {$perfil}", $falhas, $total);
    }
    $cookies['anonimo'] = $pasta . '/anonimo.txt';

    $stmt = $pdo->prepare('INSERT INTO encarregados (nome, parentesco, telefone, telefone_alt, criado_por_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['Encarregado fictício G ' . $sufixo, 'Mãe', '923000111', '924000222', $utilizadores['rec']['id']]);
    $encarregadoId = (int) $pdo->lastInsertId();

    $hoje = new DateTimeImmutable('today');
    $dataAviso = $hoje->modify('+10 days')->format('Y-m-d');
    $nascimentoAviso = '';
    for ($i = 0; $i < 80; $i++) {
        $candidata = $hoje->modify('-' . $i . ' days')->format('Y-m-d');
        if (data_mais_meses($candidata, 2) === $dataAviso) {
            $nascimentoAviso = $candidata;
            break;
        }
    }
    verificar_teste($nascimentoAviso !== '', 'fixture de dose a vencer tem data prevista exata', $falhas, $total);

    $stmt = $pdo->prepare(
        'INSERT INTO criancas (numero_processo, numero_cartao, nome, data_nascimento, sexo, encarregado_id, criado_por_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $nomes = [
        1 => 'Teste Demo Faltosos G 01 ' . $sufixo,
        2 => 'Teste Demo Faltosos G 02 ' . $sufixo,
        3 => 'Teste Demo Faltosos G 03 ' . $sufixo,
        4 => 'Teste Demo Faltosos G 04 <b>' . $sufixo . '</b>',
        5 => 'Teste Demo Faltosos G 05 ' . $sufixo,
    ];
    foreach ($nomes as $indice => $nome) {
        $nascimento = $indice === 2 ? $nascimentoAviso : '2020-01-01';
        $stmt->execute(['TG' . $sufixo . '0' . $indice, 'CARTG' . $sufixo . '0' . $indice, $nome, $nascimento, $indice % 2 ? 'F' : 'M', $encarregadoId, $utilizadores['rec']['id']]);
        $criancas[$indice] = (int) $pdo->lastInsertId();
    }

    $inserirVacinacao = function ($criancaId, $vacinaId, $calendarioId, $dose, $data = null) use ($pdo, &$vacinacoes, $utilizadores) {
        $stmt = $pdo->prepare(
            'INSERT INTO vacinacoes (crianca_id, vacina_id, calendario_id, numero_dose, data_administracao, origem, administrado_por_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([(int) $criancaId, $vacinaId, $calendarioId, $dose, $data ?: date('Y-m-d'), 'externa', $utilizadores['enf']['id']]);
        $id = (int) $pdo->lastInsertId();
        $vacinacoes[] = $id;
        return $id;
    };

    // A criança 2 tem as oportunidades ao nascer cumpridas e as de 2 meses a vencer.
    $inserirVacinacao($criancas[2], 1, 1, 1);
    $inserirVacinacao($criancas[2], 2, 2, 0);
    $inserirVacinacao($criancas[2], 3, 3, 1);
    $validaBcg = $inserirVacinacao($criancas[3], 1, 1, 1);
    $anuladaBcg = $inserirVacinacao($criancas[4], 1, 1, 1);
    $stmt = $pdo->prepare('INSERT INTO vacinacao_anulacoes (vacinacao_id, motivo, utilizador_id) VALUES (?, ?, ?)');
    $stmt->execute([$anuladaBcg, 'Erro fictício de registo', $utilizadores['adm']['id']]);
    $inserirVacinacao($criancas[5], 15, 27, 1);

    $validas = listar_vacinacoes_validas_faltosos($pdo, $criancas[3]);
    verificar_teste(count($validas) === 1 && (int) $validas[0]['id'] === $validaBcg, 'vacinação válida entra no cálculo do calendário', $falhas, $total);
    $validasAnulada = listar_vacinacoes_validas_faltosos($pdo, $criancas[4]);
    verificar_teste(count($validasAnulada) === 0, 'vacinação anulada não conta como cumprida', $falhas, $total);

    $opsDb = listar_oportunidades_calendario($pdo);
    $calAnulada = calcular_estado_calendario(['data_nascimento' => '2020-01-01'], $opsDb, $validasAnulada, date('Y-m-d'), 30);
    verificar_teste($calAnulada[0]['feita'] === false && $calAnulada[0]['estado'] === 'em_atraso', 'dose anulada volta a aparecer como pendente em atraso', $falhas, $total);
    $calAlternativaDb = calcular_estado_calendario(
        ['data_nascimento' => '2020-01-01'],
        $opsDb,
        listar_vacinacoes_validas_faltosos($pdo, $criancas[5]),
        date('Y-m-d'),
        30
    );
    $linha26 = null;
    $linha27 = null;
    foreach ($calAlternativaDb as $linha) {
        if ((int) $linha['id'] === 26) $linha26 = $linha;
        if ((int) $linha['id'] === 27) $linha27 = $linha;
    }
    verificar_teste($linha26['feita'] && $linha27['feita'], 'Tetravalente cumpre o grupo Pentavalente OU Tetravalente na base real', $falhas, $total);

    // Acesso às páginas e endpoints.
    foreach (['faltosos.php', 'faltoso_detalhe.php?id=' . $criancas[1], 'crianca_detalhe.php?id=' . $criancas[1]] as $pagina) {
        $resposta = pedido_http_teste($base . '/' . $pagina, $cookies['anonimo']);
        verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'login.php') !== false, "{$pagina} exige sessão", $falhas, $total);
    }
    foreach (['rec', 'enf', 'adm'] as $abrev) {
        foreach (['faltosos.php', 'faltoso_detalhe.php?id=' . $criancas[1], 'crianca_detalhe.php?id=' . $criancas[1]] as $pagina) {
            $resposta = pedido_http_teste($base . '/' . $pagina, $cookies[$abrev]);
            verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Warning') === false && strpos($resposta['corpo'], 'Fatal error') === false, "perfil {$abrev} consulta {$pagina}", $falhas, $total);
        }
    }
    foreach (['faltosos.php', 'faltoso_detalhe.php?id=' . $criancas[1], 'crianca_detalhe.php?id=' . $criancas[1]] as $pagina) {
        $resposta = pedido_http_teste($base . '/' . $pagina, $cookies['sto']);
        verificar_teste($resposta['status'] === 403, "responsável de stock não acede a {$pagina}", $falhas, $total);
    }
    foreach (['faltoso_contacto.php', 'faltoso_contacto_eliminar.php'] as $endpoint) {
        $resposta = pedido_http_teste($base . '/' . $endpoint, $cookies['rec']);
        verificar_teste($resposta['status'] === 405, "{$endpoint} só aceita POST", $falhas, $total);
    }
    $resposta = post_contacto_g($base, $cookies['rec'], $criancas[1], [], 'invalido');
    verificar_teste($resposta['status'] === 403, 'registo de contacto rejeita CSRF inválido', $falhas, $total);
    $resposta = post_eliminar_contacto_g($base, $cookies['enf'], 999999, 'invalido');
    verificar_teste($resposta['status'] === 403, 'eliminação de contacto rejeita CSRF inválido', $falhas, $total);

    // Pesquisas, filtros, paginação e escaping.
    $resposta = pedido_http_teste($base . '/faltosos.php?q=' . rawurlencode($sufixo) . '&estado=todos', $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], $nomes[1]) !== false && strpos($resposta['corpo'], '923000111') !== false, 'pesquisa por criança mostra contacto do encarregado', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], '<b>' . $sufixo . '</b>') === false && strpos($resposta['corpo'], '&lt;b&gt;' . $sufixo . '&lt;/b&gt;') !== false, 'nome da criança é escapado no HTML', $falhas, $total);
    $resposta = pedido_http_teste($base . '/faltosos.php?q=' . rawurlencode('923 000 111') . '&estado=todos', $cookies['enf']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], $nomes[1]) !== false, 'pesquisa normaliza telefone apenas com dígitos', $falhas, $total);
    $resposta = pedido_http_teste($base . '/faltosos.php?q=' . rawurlencode('CARTG' . $sufixo . '01') . '&estado=em_atraso', $cookies['adm']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], $nomes[1]) !== false, 'pesquisa por número de cartão funciona', $falhas, $total);
    $resposta = pedido_http_teste($base . '/faltosos.php?q=' . rawurlencode($sufixo) . '&estado=a_vencer', $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], $nomes[2]) !== false && strpos($resposta['corpo'], $nomes[1]) === false, 'filtro a vencer isola a fixture com dose próxima', $falhas, $total);
    $pagina = listar_criancas_com_calendario($pdo, $sufixo, 'em_atraso', 1, 2);
    verificar_teste($pagina['total'] >= 4 && $pagina['paginas'] === 2 && count($pagina['registos']) === 2, 'listagem de faltosos é paginada', $falhas, $total);

    $resposta = pedido_http_teste($base . '/faltoso_detalhe.php?id=' . $criancas[3], $cookies['enf']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Doses pendentes') !== false, 'detalhe do faltoso mostra calendário pendente', $falhas, $total);
    $resposta = pedido_http_teste($base . '/crianca_detalhe.php?id=' . $criancas[4], $cookies['adm']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Anulada') !== false && strpos($resposta['corpo'], 'Doses pendentes') !== false, 'ficha da criança mostra anulação e doses pendentes', $falhas, $total);
    $resposta = pedido_http_teste($base . '/faltoso_detalhe.php?id=99999999', $cookies['rec']);
    verificar_teste($resposta['status'] === 404, 'detalhe de criança inexistente devolve 404', $falhas, $total);

    // Menu ativo e ausência de inline.
    foreach (['rec', 'enf', 'adm'] as $abrev) {
        $item = linha_menu_faltosos_g(pedido_http_teste($base . '/painel.php', $cookies[$abrev])['corpo']);
        verificar_teste($item !== '' && strpos($item, 'faltosos.php') !== false && strpos($item, 'Em desenvolvimento') === false, "menu Faltosos ativo para {$abrev}", $falhas, $total);
    }
    $item = linha_menu_faltosos_g(pedido_http_teste($base . '/painel.php', $cookies['sto'])['corpo']);
    verificar_teste($item !== '' && strpos($item, 'Sem acesso') !== false && strpos($item, '<a ') === false, 'menu Faltosos nega acesso ao responsável de stock', $falhas, $total);

    // Contactos manuais: três canais, validações, permissões e eliminação lógica.
    $criancaContactoG = $criancas[1];
    $resultado = 'Sem resposta <script>alert(1)</script> ' . $sufixo;
    $resposta = post_contacto_g($base, $cookies['rec'], $criancas[1], ['canal' => 'telefone', 'resultado' => $resultado, 'notas' => 'Nota fictícia <b>G</b>']);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'faltoso_detalhe.php') !== false, 'recepção regista contacto por telefone', $falhas, $total);
    $stmt = $pdo->prepare('SELECT * FROM contactos_faltosos WHERE crianca_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$criancas[1]]);
    $contacto = $stmt->fetch();
    $contactos[] = (int) $contacto['id'];
    verificar_teste($contacto['canal'] === 'telefone' && $contacto['resultado'] === $resultado && (int) $contacto['utilizador_id'] === (int) $utilizadores['rec']['id'], 'contacto guarda canal, resultado e autor', $falhas, $total);
    $resposta = pedido_http_teste($base . '/faltoso_detalhe.php?id=' . $criancas[1], $cookies['rec']);
    verificar_teste(strpos($resposta['corpo'], '<script>alert(1)</script>') === false && strpos($resposta['corpo'], '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'resultado do contacto é escapado no HTML', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], 'role="alert"') !== false, 'mensagem de sucesso do contacto usa role alert', $falhas, $total);

    foreach (['sms', 'whatsapp'] as $canal) {
        $resposta = post_contacto_g($base, $cookies['enf'], $criancas[1], ['canal' => $canal, 'resultado' => 'Resultado ' . $canal]);
        verificar_teste($resposta['status'] === 302, "enfermeiro regista contacto por {$canal}", $falhas, $total);
        $stmt = $pdo->prepare('SELECT id FROM contactos_faltosos WHERE crianca_id = ? AND canal = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$criancas[1], $canal]);
        $contactos[] = (int) $stmt->fetchColumn();
    }
    $antes = (int) $pdo->query('SELECT COUNT(*) FROM contactos_faltosos WHERE crianca_id = ' . (int) $criancas[1])->fetchColumn();
    foreach ([
        ['data_contacto' => date('Y-m-d', strtotime('+1 day')), 'resultado' => 'Futuro'],
        ['canal' => 'carta', 'resultado' => 'Canal inválido'],
        ['resultado' => ''],
    ] as $dadosInvalidos) {
        $resposta = post_contacto_g($base, $cookies['rec'], $criancas[1], $dadosInvalidos);
        verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'faltoso_detalhe.php') !== false, 'dados inválidos de contacto são recusados', $falhas, $total);
    }
    $depois = (int) $pdo->query('SELECT COUNT(*) FROM contactos_faltosos WHERE crianca_id = ' . (int) $criancas[1])->fetchColumn();
    verificar_teste($antes === $depois, 'contactos inválidos não são inseridos', $falhas, $total);

    $resposta = pedido_http_teste($base . '/faltoso_detalhe.php?id=' . $criancas[1], $cookies['adm']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Registar contacto') === false, 'administrador consulta contactos sem formulário de escrita', $falhas, $total);
    $resposta = post_contacto_g($base, $cookies['adm'], $criancas[1], ['resultado' => 'Não permitido']);
    verificar_teste($resposta['status'] === 403, 'administrador não regista contacto', $falhas, $total);
    $resposta = post_contacto_g($base, $cookies['sto'], $criancas[1], ['resultado' => 'Não permitido']);
    verificar_teste($resposta['status'] === 403, 'responsável de stock não regista contacto', $falhas, $total);

    $resposta = post_eliminar_contacto_g($base, $cookies['enf'], $contactos[0]);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'faltoso_detalhe.php') !== false, 'enfermeiro elimina contacto logicamente', $falhas, $total);
    $stmt = $pdo->prepare('SELECT eliminado_em FROM contactos_faltosos WHERE id = ?');
    $stmt->execute([$contactos[0]]);
    verificar_teste($stmt->fetchColumn() !== null, 'eliminação de contacto preserva a linha com eliminado_em', $falhas, $total);
    $resposta = pedido_http_teste($base . '/faltoso_detalhe.php?id=' . $criancas[1], $cookies['enf']);
    verificar_teste(strpos($resposta['corpo'], $resultado) === false, 'contacto eliminado logicamente deixa de ser listado', $falhas, $total);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM auditoria WHERE entidade = 'contactos_faltosos' AND entidade_id = ? AND acao IN ('criar', 'eliminar')");
    $stmt->execute([$contactos[0]]);
    verificar_teste((int) $stmt->fetchColumn() >= 2, 'criação e eliminação lógica do contacto são auditadas', $falhas, $total);

    foreach (['app/core/faltosos.php', 'public/faltosos.php', 'public/faltoso_detalhe.php', 'public/faltoso_contacto.php', 'public/faltoso_contacto_eliminar.php', 'app/views/partials/calendario_pendente.php'] as $ficheiro) {
        $conteudo = file_get_contents(dirname(__DIR__) . '/' . $ficheiro);
        verificar_teste(preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $conteudo) !== 1, "{$ficheiro} não contém script inline", $falhas, $total);
        verificar_teste(preg_match('/\son[a-z]+\s*=/i', $conteudo) !== 1 && preg_match('/\sstyle\s*=/i', $conteudo) !== 1, "{$ficheiro} não contém eventos ou estilos inline", $falhas, $total);
        verificar_teste(strpos($conteudo, 'url(/') === false, "{$ficheiro} não usa caminhos absolutos", $falhas, $total);
    }
    $resposta = pedido_http_teste($base . '/assets/css/modulos.css', $cookies['anonimo']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['cabecalhos'], 'text/css') !== false, 'CSS dos módulos continua servido corretamente', $falhas, $total);
} catch (Throwable $e) {
    $falhas++;
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
} finally {
    if ($pdo instanceof PDO) {
        // Recolhe também identificadores criados antes de uma eventual exceção.
        $stmt = $pdo->prepare('SELECT id FROM criancas WHERE numero_processo LIKE ?');
        $stmt->execute(['TG' . $sufixo . '%']);
        foreach ($stmt->fetchAll() as $linha) $criancas[] = (int) $linha['id'];
        $criancas = array_values(array_unique(array_filter($criancas)));
        if ($criancas) {
            $marcadores = implode(',', array_fill(0, count($criancas), '?'));
            $stmt = $pdo->prepare("SELECT id FROM vacinacoes WHERE crianca_id IN ({$marcadores})");
            $stmt->execute($criancas);
            foreach ($stmt->fetchAll() as $linha) $vacinacoes[] = (int) $linha['id'];
            $stmt = $pdo->prepare("SELECT id FROM contactos_faltosos WHERE crianca_id IN ({$marcadores})");
            $stmt->execute($criancas);
            foreach ($stmt->fetchAll() as $linha) $contactos[] = (int) $linha['id'];
        }
        $vacinacoes = array_values(array_unique(array_filter($vacinacoes)));
        $contactos = array_values(array_unique(array_filter($contactos)));
        if ($contactos) {
            $mc = implode(',', array_fill(0, count($contactos), '?'));
            $stmt = $pdo->prepare("DELETE FROM auditoria WHERE entidade = 'contactos_faltosos' AND entidade_id IN ({$mc})");
            $stmt->execute($contactos);
            $stmt = $pdo->prepare("DELETE FROM contactos_faltosos WHERE id IN ({$mc})");
            $stmt->execute($contactos);
        }
        if ($vacinacoes) {
            $mv = implode(',', array_fill(0, count($vacinacoes), '?'));
            $stmt = $pdo->prepare("DELETE FROM vacinacao_anulacoes WHERE vacinacao_id IN ({$mv})");
            $stmt->execute($vacinacoes);
            $stmt = $pdo->prepare("DELETE FROM vacinacoes WHERE id IN ({$mv})");
            $stmt->execute($vacinacoes);
        }
        if ($criancas) {
            $m = implode(',', array_fill(0, count($criancas), '?'));
            $stmt = $pdo->prepare("DELETE FROM criancas WHERE id IN ({$m})");
            $stmt->execute($criancas);
        }
        if ($encarregadoId > 0) {
            $stmt = $pdo->prepare('DELETE FROM encarregados WHERE id = ?');
            $stmt->execute([$encarregadoId]);
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
echo "Fase G: {$total} verificações concluídas sem falhas.\n";
