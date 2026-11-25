<?php
/**
 * Testes da Fase H: relatórios, CSV, 2.ª via, painel e permissões.
 */

require __DIR__ . '/suporte_http.php';
require_once __DIR__ . '/../app/core/relatorios.php';

function sem_inline_h($html) {
    return preg_match('/<script\b(?![^>]*\bsrc=)[^>]*>/i', $html) !== 1
        && preg_match('/\son(?:click|change|submit|load|error)\s*=/i', $html) !== 1;
}

function linha_csv_h($csv, $termo) {
    foreach (explode("\n", $csv) as $linha) {
        if (strpos($linha, $termo) !== false) return $linha;
    }
    return '';
}

function resultado_relatorio_h($html) {
    return preg_match('/<section class="cartao relatorio-resultado">(.*?)<\/section>/s', $html, $matches) === 1
        ? $matches[1] : '';
}

$base = 'http://localhost/vacinacao-infantil-fase1a/vacinas/public';
$sufixo = strtoupper(bin2hex(random_bytes(3)));
$password = 'FaseH-' . bin2hex(random_bytes(8)) . '!';
$pasta = sys_get_temp_dir() . '/vacinas_faseh_' . strtolower($sufixo);
mkdir($pasta);
$cookies = [];
$utilizadores = [];
$criancas = [];
$atendimentos = [];
$lotes = [];
$vacinacoes = [];
$contactos = [];
$auditorias = [];
$encarregadoId = 0;
$falhas = 0;
$total = 0;
$pdo = conexao_teste_root();
$hoje = new DateTimeImmutable((string) $pdo->query('SELECT CURDATE()')->fetchColumn());
$dataHoje = $hoje->format('Y-m-d');
$dataOntem = $hoje->modify('-1 day')->format('Y-m-d');
$dataAntiga = $hoje->modify('-40 days')->format('Y-m-d');

try {
    $filtros = normalizar_filtros_relatorio(['vacina_id' => ['1'], 'enfermeiro_id' => ['2']], 'vacinacoes');
    verificar_teste($filtros['vacina_id'] === 0 && $filtros['enfermeiro_id'] === 0, 'filtros arrays não geram avisos nem conversões inválidas', $falhas, $total);
    $invalido = false;
    try {
        normalizar_filtros_relatorio(['data_inicio' => '2026-02-30'], 'vacinacoes');
    } catch (RuntimeException $e) {
        $invalido = true;
    }
    verificar_teste($invalido, 'data impossível é rejeitada pela normalização', $falhas, $total);
    $invalido = false;
    try {
        normalizar_filtros_relatorio(['data_inicio' => $dataHoje, 'data_fim' => $dataOntem], 'vacinacoes');
    } catch (RuntimeException $e) {
        $invalido = true;
    }
    verificar_teste($invalido, 'intervalo invertido é rejeitado', $falhas, $total);
    verificar_teste(texto_csv_seguro('=1+1') === "'=1+1" && texto_csv_seguro('+923000000') === "'+923000000", 'CSV neutraliza fórmulas em valores de risco', $falhas, $total);
    verificar_teste(relatorios_cabecalhos_csv('auditoria')[0] === 'Data/hora', 'cabeçalhos de auditoria estão definidos', $falhas, $total);

    foreach (['rec' => 'recepcao', 'enf' => 'enfermeiro', 'sto' => 'responsavel_stock', 'adm' => 'administrador'] as $abrev => $perfil) {
        $nome = 'fh_' . strtolower($abrev) . '_' . strtolower($sufixo);
        $id = criar_utilizador_teste($pdo, $nome, $perfil, $password);
        $utilizadores[$abrev] = ['id' => $id, 'nome' => $nome];
        $cookies[$abrev] = $pasta . '/' . $abrev . '.txt';
        $login = login_http_teste($base, $nome, $password, $cookies[$abrev]);
        verificar_teste($login['status'] === 302 && strpos($login['cabecalhos'], 'painel.php') !== false, "login HTTP com CSRF do perfil {$perfil}", $falhas, $total);
    }
    $cookies['anonimo'] = $pasta . '/anonimo.txt';
    $resposta = pedido_http_teste($base . '/login.php', $cookies['anonimo'], [
        'utilizador' => $utilizadores['rec']['nome'], 'password' => $password,
        'csrf_token' => 'invalido', 'redirect' => '',
    ]);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'login.php') !== false, 'login rejeita CSRF inválido e redireciona com erro', $falhas, $total);
    $resposta = pedido_http_teste($base . '/painel.php', $cookies['anonimo']);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'login.php') !== false, 'CSRF inválido não cria sessão autenticada', $falhas, $total);

    $stmt = $pdo->prepare('INSERT INTO encarregados (nome, parentesco, telefone, telefone_alt, morada, criado_por_id) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute(['Encarregado fictício H ' . $sufixo, 'Mãe', '923000333', '924000444', 'Morada fictícia H', $utilizadores['rec']['id']]);
    $encarregadoId = (int) $pdo->lastInsertId();

    $nomes = [
        1 => 'Teste Demo Relatórios H 01 <b>' . $sufixo . '</b>',
        2 => 'Teste Demo Relatórios H 02 ' . $sufixo,
        3 => 'Teste Demo Relatórios H 03 ' . $sufixo,
        4 => 'Teste Demo Relatórios H 04 ' . $sufixo,
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO criancas (numero_processo, numero_cartao, nome, data_nascimento, sexo, encarregado_id, criado_por_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($nomes as $indice => $nome) {
        $nascimento = $indice === 3 ? $dataHoje : '2020-01-01';
        $stmt->execute(['TH' . $sufixo . '0' . $indice, 'CARTH' . $sufixo . '0' . $indice, $nome, $nascimento, $indice % 2 ? 'F' : 'M', $encarregadoId, $utilizadores['rec']['id']]);
        $criancas[$indice] = (int) $pdo->lastInsertId();
    }
    $stmt = $pdo->prepare('UPDATE criancas SET ativo = FALSE, eliminado_em = NOW() WHERE id = ?');
    $stmt->execute([$criancas[4]]);

    $criarLote = function ($vacinaId, $validade, $saldo) use ($pdo, &$lotes, $sufixo, $utilizadores) {
        $stmt = $pdo->prepare('INSERT INTO stock_lotes (vacina_id, numero_lote, validade, fabricante, quantidade_inicial, criado_por_id) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$vacinaId, 'LOTEH' . $sufixo . '-' . $vacinaId . '-' . count($lotes), $validade, 'Fabricante fictício H', $saldo, $utilizadores['sto']['id']]);
        $id = (int) $pdo->lastInsertId();
        $lotes[$id] = $vacinaId;
        $stmt = $pdo->prepare("INSERT INTO stock_movimentos (lote_id, tipo, quantidade, motivo, utilizador_id) VALUES (?, 'entrada', ?, 'Entrada fictícia H', ?)");
        $stmt->execute([$id, $saldo, $utilizadores['sto']['id']]);
        return $id;
    };
    $loteValido = $criarLote(1, $hoje->modify('+10 days')->format('Y-m-d'), 8);
    $loteVencido = $criarLote(2, $hoje->modify('-2 days')->format('Y-m-d'), 3);

    $inserirVacinacao = function ($criancaId, $vacinaId, $calendarioId, $dose, $origem, $loteId = null, $nomeExterno = null) use ($pdo, &$vacinacoes, $utilizadores, $dataHoje) {
        $stmt = $pdo->prepare(
            'INSERT INTO vacinacoes (crianca_id, vacina_id, calendario_id, numero_dose, data_administracao, lote_id,
                administrado_por_id, administrado_por_nome, origem, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $criancaId, $vacinaId, $calendarioId, $dose, $dataHoje, $loteId,
            $origem === 'posto' ? $utilizadores['enf']['id'] : null,
            $nomeExterno, $origem, 'Observação fictícia H',
        ]);
        $id = (int) $pdo->lastInsertId();
        $vacinacoes[$id] = $criancaId;
        return $id;
    };
    $vacinaValida = $inserirVacinacao($criancas[1], 1, 1, 1, 'posto', $loteValido);
    $stmt = $pdo->prepare("INSERT INTO stock_movimentos (lote_id, tipo, quantidade, motivo, vacinacao_id, utilizador_id) VALUES (?, 'saida_vacinacao', 1, 'Saída fictícia H', ?, ?)");
    $stmt->execute([$loteValido, $vacinaValida, $utilizadores['enf']['id']]);
    $vacinaAnulada = $inserirVacinacao($criancas[1], 2, 6, 1, 'externa', null, 'Profissional externo anulado H');
    $stmt = $pdo->prepare('INSERT INTO vacinacao_anulacoes (vacinacao_id, motivo, utilizador_id) VALUES (?, ?, ?)');
    $stmt->execute([$vacinaAnulada, 'Erro fictício de registo H', $utilizadores['adm']['id']]);
    $vacinaExterna = $inserirVacinacao($criancas[2], 3, 3, 1, 'externa', null, 'Profissional externo válido H');

    $criarAtendimento = function ($criancaId, $estado, $vacinaId, $criadoEm, $concluidoEm = null, $motivo = null) use ($pdo, &$atendimentos, $utilizadores) {
        $stmt = $pdo->prepare(
            'INSERT INTO atendimentos (crianca_id, estado, recebido_por_id, atendido_por_id, iniciado_em, concluido_em, motivo, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $criancaId, $estado, $utilizadores['rec']['id'],
            $estado === 'em_atendimento' ? $utilizadores['enf']['id'] : null,
            $estado === 'em_atendimento' ? $criadoEm : null,
            $concluidoEm, $motivo, $criadoEm,
        ]);
        $id = (int) $pdo->lastInsertId();
        $atendimentos[$id] = $estado;
        $stmt = $pdo->prepare('INSERT INTO atendimento_vacinas (atendimento_id, vacina_id) VALUES (?, ?)');
        $stmt->execute([$id, $vacinaId]);
        return $id;
    };
    $semVacinaHoje = $criarAtendimento($criancas[2], 'sem_vacina', 1, $dataHoje . ' 12:34:00', null, '=SEMVACINA_H_' . $sufixo);
    $semVacinaOutra = $criarAtendimento($criancas[2], 'sem_vacina', 2, $dataHoje . ' 13:00:00', null, 'OUTRA_VACINA_H_' . $sufixo);
    $semVacinaAntigo = $criarAtendimento($criancas[1], 'sem_vacina', 1, $dataAntiga . ' 09:00:00', null, 'PERIODO_ANTIGO_H_' . $sufixo);
    $criarAtendimento($criancas[1], 'aguardando', 1, $dataHoje . ' 08:00:00');
    $criarAtendimento($criancas[2], 'em_atendimento', 3, $dataHoje . ' 09:00:00');
    $criarAtendimento($criancas[3], 'concluido', 4, $dataHoje . ' 10:00:00', $dataHoje . ' 10:30:00');
    $criarAtendimento($criancas[1], 'concluido', 1, $dataOntem . ' 10:00:00', $dataOntem . ' 10:30:00');

    $stmt = $pdo->prepare('INSERT INTO contactos_faltosos (crianca_id, data_contacto, canal, resultado, notas, utilizador_id, eliminado_em) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach (['telefone', 'sms', 'whatsapp'] as $canal) {
        $stmt->execute([$criancas[1], $dataHoje, $canal, 'Resultado fictício H', 'Notas fictícias H', $utilizadores['rec']['id'], null]);
        $contactos[] = (int) $pdo->lastInsertId();
    }
    $stmt->execute([$criancas[1], $dataHoje, 'telefone', 'Contacto eliminado H', '', $utilizadores['rec']['id'], date('Y-m-d H:i:s')]);
    $contactos[] = (int) $pdo->lastInsertId();
    $stmt->execute([$criancas[3], $dataHoje, 'sms', 'Criança sem atraso H', '', $utilizadores['rec']['id'], null]);
    $contactos[] = (int) $pdo->lastInsertId();
    $stmt->execute([$criancas[1], $dataAntiga, 'telefone', 'Contacto antigo H', '', $utilizadores['rec']['id'], null]);
    $contactos[] = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('INSERT INTO auditoria (entidade, entidade_id, acao, campo, valor_anterior, valor_novo, utilizador_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute(['criancas', $criancas[1], 'alterar', 'nome', '=ANTES_H_' . $sufixo, 'AUDITORIA_H_' . $sufixo, $utilizadores['rec']['id']]);
    $auditorias[] = (int) $pdo->lastInsertId();

    $permissoes = [
        'rec' => [],
        'enf' => ['sem_vacina', 'vacinacoes', 'faltosos'],
        'sto' => ['sem_vacina', 'stock'],
        'adm' => ['sem_vacina', 'vacinacoes', 'stock', 'faltosos', 'auditoria'],
    ];
    $tipos = ['sem_vacina', 'vacinacoes', 'stock', 'faltosos', 'auditoria'];
    foreach ($permissoes as $abrev => $permitidos) {
        foreach ($tipos as $tipo) {
            $esperado = in_array($tipo, $permitidos, true) ? 200 : 403;
            $resposta = pedido_http_teste($base . '/relatorios.php?tipo=' . $tipo, $cookies[$abrev]);
            verificar_teste($resposta['status'] === $esperado, "HTML {$tipo}: perfil {$abrev} recebe {$esperado}", $falhas, $total);
            verificar_teste($esperado !== 200 || strpos($resposta['corpo'], 'Warning') === false, "HTML {$tipo}: sem warnings para {$abrev}", $falhas, $total);
            $resposta = pedido_http_teste($base . '/relatorios.csv.php?tipo=' . $tipo, $cookies[$abrev]);
            verificar_teste($resposta['status'] === $esperado, "CSV {$tipo}: perfil {$abrev} recebe {$esperado}", $falhas, $total);
        }
    }
    foreach (['relatorios.php?tipo=auditoria', 'relatorios.csv.php?tipo=auditoria', 'cartao_vacinas.php?id=' . $criancas[1]] as $caminho) {
        $resposta = pedido_http_teste($base . '/' . $caminho, $cookies['anonimo']);
        verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'login.php') !== false, "sessão anónima redirecionada em {$caminho}", $falhas, $total);
    }

    $urlSem = '/relatorios.php?tipo=sem_vacina&data_inicio=' . $dataHoje . '&data_fim=' . $dataHoje;
    $resposta = pedido_http_teste($base . $urlSem, $cookies['adm']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], $nomes[2]) !== false, 'relatório sem vacina mostra a criança do dia', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], '=SEMVACINA_H_' . $sufixo) !== false && strpos($resposta['corpo'], 'PERIODO_ANTIGO_H_' . $sufixo) === false, 'período e motivo de sem vacina são respeitados', $falhas, $total);
    $resposta = pedido_http_teste($base . $urlSem . '&vacina_id=1', $cookies['adm']);
    verificar_teste(strpos($resposta['corpo'], '=SEMVACINA_H_' . $sufixo) !== false && strpos($resposta['corpo'], 'OUTRA_VACINA_H_' . $sufixo) === false, 'filtro de vacina isola saídas sem vacina', $falhas, $total);
    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=sem_vacina&data_inicio=' . $dataAntiga . '&data_fim=' . $dataAntiga, $cookies['adm']);
    verificar_teste(strpos($resposta['corpo'], 'PERIODO_ANTIGO_H_' . $sufixo) !== false && strpos($resposta['corpo'], '=SEMVACINA_H_' . $sufixo) === false, 'filtro temporal encontra saída antiga', $falhas, $total);
    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=sem_vacina&data_inicio=2026-02-30', $cookies['adm']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'A data inicial do relatório é inválida') !== false, 'HTML avisa e repõe filtros inválidos', $falhas, $total);
    $resposta = pedido_http_teste($base . '/relatorios.csv.php?tipo=sem_vacina&data_inicio=2026-02-30', $cookies['adm']);
    verificar_teste($resposta['status'] === 400, 'CSV rejeita filtro inválido', $falhas, $total);
    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=sem_vacina&vacina_id[]=1', $cookies['adm']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Warning') === false, 'HTML tolera filtro array sem warning', $falhas, $total);

    $csv = pedido_http_teste($base . '/relatorios.csv.php?tipo=sem_vacina&data_inicio=' . $dataHoje . '&data_fim=' . $dataHoje, $cookies['adm']);
    verificar_teste(substr($csv['corpo'], 0, 3) === "\xEF\xBB\xBF" && strpos($csv['cabecalhos'], 'text/csv') !== false, 'CSV tem BOM UTF-8 e tipo correto', $falhas, $total);
    verificar_teste(strpos($csv['corpo'], 'Data;Atendimento;Criança;Processo;Vacina;Motivo') !== false, 'CSV usa cabeçalho e delimitador ponto e vírgula', $falhas, $total);
    verificar_teste(strpos($csv['corpo'], "'=SEMVACINA_H_" . $sufixo) !== false, 'CSV protege motivo que começa por fórmula', $falhas, $total);

    $urlVac = '/relatorios.php?tipo=vacinacoes&data_inicio=' . $dataHoje . '&data_fim=' . $dataHoje;
    $resposta = pedido_http_teste($base . $urlVac, $cookies['enf']);
    verificar_teste(strpos($resposta['corpo'], 'BCG') !== false && strpos($resposta['corpo'], 'Polio oral') !== false && strpos($resposta['corpo'], 'Anulada') !== false, 'relatório de vacinações identifica doses válidas e anuladas', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], $utilizadores['enf']['nome']) !== false, 'relatório identifica o enfermeiro', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], 'Profissional externo válido H') !== false, 'relatório mostra o administrador real da dose externa', $falhas, $total);
    $resposta = pedido_http_teste($base . $urlVac . '&vacina_id=1&enfermeiro_id=' . $utilizadores['enf']['id'], $cookies['enf']);
    $resultado = resultado_relatorio_h($resposta['corpo']);
    verificar_teste(strpos($resultado, 'BCG') !== false && strpos($resultado, 'Polio oral') === false, 'filtros de vacina e enfermeiro são combinados', $falhas, $total);
    $csv = pedido_http_teste($base . '/relatorios.csv.php?tipo=vacinacoes&data_inicio=' . $dataHoje . '&data_fim=' . $dataHoje, $cookies['enf']);
    verificar_teste(strpos($csv['corpo'], 'Data;Criança;Processo;Vacina;Dose;Profissional;Origem;Estado') !== false && strpos($csv['corpo'], 'Anulada') !== false, 'CSV de vacinações preserva estado e cabeçalho', $falhas, $total);

    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=stock', $cookies['sto']);
    verificar_teste(strpos($resposta['corpo'], 'LOTEH' . $sufixo . '-1-0') !== false && strpos($resposta['corpo'], '7 doses') !== false, 'relatório stock mostra saldo calculado do lote', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], 'Validade próxima') !== false && strpos($resposta['corpo'], 'Vencido') !== false, 'relatório stock mostra estados de validade', $falhas, $total);
    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=stock&vacina_id=1', $cookies['sto']);
    verificar_teste(strpos($resposta['corpo'], 'LOTEH' . $sufixo . '-1-0') !== false && strpos($resposta['corpo'], 'LOTEH' . $sufixo . '-2-1') === false, 'relatório stock filtra por vacina', $falhas, $total);
    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=stock&data_inicio=' . $hoje->modify('+100 days')->format('Y-m-d'), $cookies['sto']);
    verificar_teste(strpos($resposta['corpo'], 'Nenhum registo encontrado') !== false, 'relatório stock filtra intervalo de validade', $falhas, $total);

    $urlFalt = '/relatorios.php?tipo=faltosos&data_inicio=' . $dataHoje . '&data_fim=' . $dataHoje;
    $resposta = pedido_http_teste($base . $urlFalt, $cookies['enf']);
    $nomeEscapado = htmlspecialchars($nomes[1], ENT_QUOTES, 'UTF-8');
    verificar_teste(strpos($resposta['corpo'], $nomeEscapado) !== false && strpos($resposta['corpo'], 'Telefone, SMS, WhatsApp') !== false, 'faltosos agrupa os três canais de contacto', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], $nomes[3]) === false, 'criança sem dose em atraso não entra no relatório', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], 'Contacto eliminado H') === false && strpos($resposta['corpo'], 'Valores clínicos por validar pela equipa clínica') !== false, 'contacto eliminado é excluído e aviso clínico permanece', $falhas, $total);
    $csv = pedido_http_teste($base . '/relatorios.csv.php?tipo=faltosos&data_inicio=' . $dataHoje . '&data_fim=' . $dataHoje, $cookies['enf']);
    verificar_teste(strpos($csv['corpo'], 'Telefone, SMS, WhatsApp') !== false && strpos($csv['corpo'], $nomes[1]) !== false, 'CSV de faltosos inclui criança e canais', $falhas, $total);

    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=auditoria&data_inicio=' . $dataHoje . '&data_fim=' . $dataHoje, $cookies['adm']);
    verificar_teste(strpos($resposta['corpo'], 'AUDITORIA_H_' . $sufixo) !== false && strpos($resposta['corpo'], $utilizadores['rec']['nome']) !== false, 'relatório de auditoria mostra ação e autor', $falhas, $total);
    $csv = pedido_http_teste($base . '/relatorios.csv.php?tipo=auditoria&data_inicio=' . $dataHoje . '&data_fim=' . $dataHoje, $cookies['adm']);
    verificar_teste(strpos($csv['corpo'], "'=ANTES_H_" . $sufixo) !== false, 'CSV de auditoria neutraliza valor de risco', $falhas, $total);

    foreach (['enf', 'adm'] as $abrev) {
        $resposta = pedido_http_teste($base . '/cartao_vacinas.php?id=' . $criancas[1], $cookies[$abrev]);
        verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'BCG') !== false && strpos($resposta['corpo'], 'Polio oral') === false, "2.ª via do perfil {$abrev} exclui vacinação anulada", $falhas, $total);
        verificar_teste(strpos($resposta['corpo'], 'data-acao="imprimir-cartao"') !== false && strpos($resposta['corpo'], 'cartao.js') !== false, "2.ª via do perfil {$abrev} oferece impressão externa", $falhas, $total);
        verificar_teste(strpos($resposta['corpo'], '&lt;b&gt;' . $sufixo . '&lt;/b&gt;') !== false && strpos($resposta['corpo'], '<b>' . $sufixo . '</b>') === false, "2.ª via do perfil {$abrev} escapa o nome", $falhas, $total);
        verificar_teste(sem_inline_h($resposta['corpo']), "2.ª via do perfil {$abrev} não tem script ou evento inline", $falhas, $total);
    }
    $resposta = pedido_http_teste($base . '/cartao_vacinas.php?id=' . $criancas[2], $cookies['enf']);
    verificar_teste(strpos($resposta['corpo'], 'Hepatite B') !== false && strpos($resposta['corpo'], 'Profissional externo válido H') !== false && strpos($resposta['corpo'], 'Não aplicável') !== false, '2.ª via inclui dose externa válida sem lote', $falhas, $total);
    $resposta = pedido_http_teste($base . '/cartao_vacinas.php?id=' . $criancas[1], $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'data-acao="imprimir-cartao"') === false && strpos($resposta['corpo'], 'Consulta apenas') !== false, 'recepção consulta 2.ª via sem imprimir', $falhas, $total);
    $resposta = pedido_http_teste($base . '/cartao_vacinas.php?id=' . $criancas[1], $cookies['sto']);
    verificar_teste($resposta['status'] === 403, 'responsável de stock não acede à 2.ª via', $falhas, $total);
    $resposta = pedido_http_teste($base . '/cartao_vacinas.php?id=' . $criancas[4], $cookies['adm']);
    verificar_teste($resposta['status'] === 404, '2.ª via não abre criança eliminada', $falhas, $total);

    $stmt = $pdo->query(
        "SELECT COALESCE(SUM(a.estado = 'aguardando'), 0) AS aguardando,
                COALESCE(SUM(a.estado = 'em_atendimento'), 0) AS em_atendimento,
                COALESCE(SUM(a.estado = 'concluido' AND DATE(a.concluido_em) = CURDATE()), 0) AS concluidos
         FROM atendimentos a
         INNER JOIN criancas c ON c.id = a.crianca_id
         WHERE c.ativo = 1 AND c.eliminado_em IS NULL"
    );
    $esperadoPainel = $stmt->fetch();
    $resposta = pedido_http_teste($base . '/painel.php', $cookies['adm']);
    preg_match('/<section class="faixa-hoje".*?<\/section>/s', $resposta['corpo'], $faixa);
    preg_match_all('/<strong>(\d+)<\/strong>/', $faixa[0] ?? '', $valores);
    verificar_teste($resposta['status'] === 200 && array_map('intval', $valores[1] ?? []) === [(int) $esperadoPainel['aguardando'], (int) $esperadoPainel['em_atendimento'], (int) $esperadoPainel['concluidos']], 'painel mostra contagens reais de hoje', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], 'Cálculo ainda não disponível') === false && strpos($resposta['corpo'], 'Em desenvolvimento') === false, 'painel não mostra cálculos nem módulos em desenvolvimento', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], 'relatorios.php') !== false && sem_inline_h($resposta['corpo']), 'menu do painel liga relatórios e não tem inline', $falhas, $total);

    $resposta = pedido_http_teste($base . '/relatorios.php?tipo=stock', $cookies['sto']);
    verificar_teste(strpos($resposta['corpo'], 'relatorios.php') !== false && strpos($resposta['corpo'], 'aria-current="page"') !== false && strpos($resposta['corpo'], 'Em desenvolvimento') === false, 'menu do módulo marca Relatórios como página atual', $falhas, $total);
    verificar_teste(strpos($resposta['cabecalhos'], "Content-Security-Policy: default-src 'self'") !== false && sem_inline_h($resposta['corpo']), 'relatório mantém CSP e ausência de inline', $falhas, $total);
} finally {
    if (!empty($contactos)) {
        $marcadores = implode(',', array_fill(0, count($contactos), '?'));
        $stmt = $pdo->prepare("DELETE FROM contactos_faltosos WHERE id IN ({$marcadores})");
        $stmt->execute($contactos);
    }
    if (!empty($vacinacoes)) {
        $stmt = $pdo->prepare('DELETE FROM vacinacao_anulacoes WHERE vacinacao_id IN (' . implode(',', array_fill(0, count($vacinacoes), '?')) . ')');
        $stmt->execute(array_keys($vacinacoes));
    }
    if (!empty($lotes)) {
        $stmt = $pdo->prepare('DELETE FROM stock_movimentos WHERE lote_id IN (' . implode(',', array_fill(0, count($lotes), '?')) . ')');
        $stmt->execute(array_keys($lotes));
    }
    if (!empty($vacinacoes)) {
        $stmt = $pdo->prepare('DELETE FROM vacinacoes WHERE id IN (' . implode(',', array_fill(0, count($vacinacoes), '?')) . ')');
        $stmt->execute(array_keys($vacinacoes));
    }
    if (!empty($atendimentos)) {
        $stmt = $pdo->prepare('DELETE FROM atendimento_vacinas WHERE atendimento_id IN (' . implode(',', array_fill(0, count($atendimentos), '?')) . ')');
        $stmt->execute(array_keys($atendimentos));
        $stmt = $pdo->prepare('DELETE FROM atendimentos WHERE id IN (' . implode(',', array_fill(0, count($atendimentos), '?')) . ')');
        $stmt->execute(array_keys($atendimentos));
    }
    if (!empty($criancas)) {
        $stmt = $pdo->prepare('DELETE FROM criancas WHERE id IN (' . implode(',', array_fill(0, count($criancas), '?')) . ')');
        $stmt->execute(array_values($criancas));
    }
    if ($encarregadoId > 0) {
        $stmt = $pdo->prepare('DELETE FROM encarregados WHERE id = ?');
        $stmt->execute([$encarregadoId]);
    }
    if (!empty($lotes)) {
        $stmt = $pdo->prepare('DELETE FROM stock_lotes WHERE id IN (' . implode(',', array_fill(0, count($lotes), '?')) . ')');
        $stmt->execute(array_keys($lotes));
    }
    foreach ($utilizadores as $abrev => $utilizador) {
        limpar_utilizador_teste($pdo, $utilizador['id'], $utilizador['nome']);
    }
    foreach (glob($pasta . '/*.txt') ?: [] as $ficheiro) unlink($ficheiro);
    rmdir($pasta);
}

fwrite($falhas === 0 ? STDOUT : STDERR, "Fase H: {$total} verificações, {$falhas} falhas.\n");
exit($falhas === 0 ? 0 : 1);
