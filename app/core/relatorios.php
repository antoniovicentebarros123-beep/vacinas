<?php
/**
 * Consultas e exportações dos relatórios operacionais.
 */

require_once __DIR__ . '/modulos.php';
require_once __DIR__ . '/stock.php';
require_once __DIR__ . '/faltosos.php';

function relatorios_tipos_permitidos($perfil) {
    $tipos = [
        'enfermeiro' => ['sem_vacina', 'vacinacoes', 'faltosos'],
        'responsavel_stock' => ['sem_vacina', 'stock'],
        'administrador' => ['sem_vacina', 'vacinacoes', 'stock', 'faltosos', 'auditoria'],
    ];
    return $tipos[$perfil] ?? [];
}

function relatorios_tipo_padrao($perfil) {
    $tipos = relatorios_tipos_permitidos($perfil);
    return $tipos ? $tipos[0] : '';
}

function relatorios_tipo_autorizado($perfil, $tipo) {
    return in_array($tipo, relatorios_tipos_permitidos($perfil), true);
}

function relatorios_rotulo_tipo($tipo) {
    $rotulos = [
        'sem_vacina' => 'Saídas sem vacina',
        'vacinacoes' => 'Vacinações',
        'stock' => 'Stock e validades',
        'faltosos' => 'Crianças faltosas com contactos',
        'auditoria' => 'Auditoria',
    ];
    return $rotulos[$tipo] ?? 'Relatório';
}

function normalizar_filtros_relatorio(array $entrada, $tipo) {
    $inicio = is_string($entrada['data_inicio'] ?? null) ? trim($entrada['data_inicio']) : '';
    $fim = is_string($entrada['data_fim'] ?? null) ? trim($entrada['data_fim']) : '';
    if ($tipo !== 'stock') {
        if ($inicio === '') $inicio = date('Y-m-01');
        if ($fim === '') $fim = date('Y-m-d');
    }
    if ($inicio !== '' && !data_iso_valida($inicio)) {
        throw new RuntimeException('A data inicial do relatório é inválida.');
    }
    if ($fim !== '' && !data_iso_valida($fim)) {
        throw new RuntimeException('A data final do relatório é inválida.');
    }
    if ($inicio !== '' && $fim !== '' && $inicio > $fim) {
        throw new RuntimeException('A data inicial não pode ser posterior à data final.');
    }
    $vacinaId = isset($entrada['vacina_id']) && is_scalar($entrada['vacina_id']) ? (int) $entrada['vacina_id'] : 0;
    $enfermeiroId = isset($entrada['enfermeiro_id']) && is_scalar($entrada['enfermeiro_id']) ? (int) $entrada['enfermeiro_id'] : 0;

    return [
        'data_inicio' => $inicio,
        'data_fim' => $fim,
        'vacina_id' => max(0, $vacinaId),
        'enfermeiro_id' => max(0, $enfermeiroId),
    ];
}

function listar_vacinas_relatorio(PDO $pdo) {
    $stmt = $pdo->query(
        'SELECT id, nome FROM vacinas
         WHERE ativo = TRUE AND eliminado_em IS NULL
         ORDER BY nome'
    );
    return $stmt->fetchAll();
}

function listar_enfermeiros_relatorio(PDO $pdo) {
    $stmt = $pdo->query(
        "SELECT id, nome FROM utilizadores
         WHERE perfil = 'enfermeiro' AND ativo = TRUE
         ORDER BY nome"
    );
    return $stmt->fetchAll();
}

function relatorio_sem_vacina(PDO $pdo, array $filtros) {
    $where = "a.estado = 'sem_vacina' AND DATE(a.criado_em) BETWEEN ? AND ?
              AND c.ativo = TRUE AND c.eliminado_em IS NULL";
    $parametros = [$filtros['data_inicio'], $filtros['data_fim']];
    if ($filtros['vacina_id'] > 0) {
        $where .= ' AND v.id = ?';
        $parametros[] = $filtros['vacina_id'];
    }
    $stmt = $pdo->prepare(
        "SELECT a.id, a.criado_em, a.motivo, c.id AS crianca_id, c.nome AS crianca_nome, c.numero_processo,
                v.id AS vacina_id, v.nome AS vacina_nome
         FROM atendimentos a
         INNER JOIN criancas c ON c.id = a.crianca_id
         INNER JOIN atendimento_vacinas av ON av.atendimento_id = a.id
         INNER JOIN vacinas v ON v.id = av.vacina_id
         WHERE {$where}
         ORDER BY a.criado_em DESC, c.nome, v.nome"
    );
    $stmt->execute($parametros);
    return $stmt->fetchAll();
}

function relatorio_vacinacoes(PDO $pdo, array $filtros) {
    $where = 'DATE(vc.data_administracao) BETWEEN ? AND ?
              AND c.ativo = TRUE AND c.eliminado_em IS NULL';
    $parametros = [$filtros['data_inicio'], $filtros['data_fim']];
    if ($filtros['vacina_id'] > 0) {
        $where .= ' AND v.id = ?';
        $parametros[] = $filtros['vacina_id'];
    }
    if ($filtros['enfermeiro_id'] > 0) {
        $where .= ' AND vc.administrado_por_id = ?';
        $parametros[] = $filtros['enfermeiro_id'];
    }
    $stmt = $pdo->prepare(
        "SELECT vc.id, vc.data_administracao, vc.numero_dose, vc.origem,
                c.id AS crianca_id, c.nome AS crianca_nome, c.numero_processo, v.nome AS vacina_nome,
                CASE WHEN vc.origem = 'externa'
                    THEN COALESCE(NULLIF(vc.administrado_por_nome, ''), 'Não identificado')
                    ELSE COALESCE(u.nome, 'Não identificado') END AS profissional,
                NOT EXISTS (SELECT 1 FROM vacinacao_anulacoes va WHERE va.vacinacao_id = vc.id) AS valida
         FROM vacinacoes vc
         INNER JOIN criancas c ON c.id = vc.crianca_id
         INNER JOIN vacinas v ON v.id = vc.vacina_id
         LEFT JOIN utilizadores u ON u.id = vc.administrado_por_id
         WHERE {$where}
         ORDER BY vc.data_administracao DESC, c.nome, v.nome, vc.id DESC"
    );
    $stmt->execute($parametros);
    return $stmt->fetchAll();
}

function relatorio_stock(PDO $pdo, array $filtros) {
    $where = [];
    $parametros = [];
    if ($filtros['data_inicio'] !== '') {
        $where[] = 's.validade >= ?';
        $parametros[] = $filtros['data_inicio'];
    }
    if ($filtros['data_fim'] !== '') {
        $where[] = 's.validade <= ?';
        $parametros[] = $filtros['data_fim'];
    }
    if ($filtros['vacina_id'] > 0) {
        $where[] = 's.vacina_id = ?';
        $parametros[] = $filtros['vacina_id'];
    }
    $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare(
        'SELECT s.id, s.vacina_id, s.numero_lote, s.validade, s.fabricante,
                s.saldo_atual, v.nome AS vacina_nome
         FROM stock_saldo_lotes s
         INNER JOIN vacinas v ON v.id = s.vacina_id' . $sqlWhere . '
         ORDER BY s.validade, v.nome, s.numero_lote'
    );
    $stmt->execute($parametros);
    $linhas = $stmt->fetchAll();
    $limiares = limiares_stock($pdo);
    foreach ($linhas as $indice => $linha) {
        $estado = estado_stock_lote($linha, $limiares);
        $linhas[$indice]['estado_rotulo'] = $estado['rotulo'];
        $linhas[$indice]['estado_classe'] = $estado['classe'];
        $linhas[$indice]['dias_validade'] = dias_ate_validade($linha['validade']);
    }
    return $linhas;
}

function relatorio_faltosos(PDO $pdo, array $filtros) {
    $stmt = $pdo->prepare(
        "SELECT cf.crianca_id, cf.data_contacto, cf.canal, cf.resultado, cf.notas,
                u.nome AS registado_por
         FROM contactos_faltosos cf
         LEFT JOIN utilizadores u ON u.id = cf.utilizador_id
         WHERE cf.eliminado_em IS NULL
           AND cf.data_contacto >= ? AND cf.data_contacto < DATE_ADD(?, INTERVAL 1 DAY)
         ORDER BY cf.data_contacto DESC, cf.id DESC"
    );
    $stmt->execute([$filtros['data_inicio'] . ' 00:00:00', $filtros['data_fim']]);
    $contactosPorCrianca = [];
    foreach ($stmt->fetchAll() as $contacto) {
        $contactosPorCrianca[(int) $contacto['crianca_id']][] = $contacto;
    }
    if (!$contactosPorCrianca) return [];

    $criancas = listar_criancas_para_faltosos($pdo);
    $oportunidades = listar_oportunidades_calendario($pdo);
    $vacinacoesPorCrianca = [];
    foreach (listar_vacinacoes_validas_faltosos($pdo) as $vacinacao) {
        $vacinacoesPorCrianca[(int) $vacinacao['crianca_id']][] = $vacinacao;
    }
    $linhas = [];
    $diasAviso = faltosos_dias_aviso($pdo);
    foreach ($criancas as $crianca) {
        $criancaId = (int) $crianca['id'];
        if (!isset($contactosPorCrianca[$criancaId])) continue;
        $calendario = calcular_estado_calendario(
            $crianca,
            $oportunidades,
            $vacinacoesPorCrianca[$criancaId] ?? [],
            date('Y-m-d'),
            $diasAviso
        );
        $emAtraso = 0;
        foreach ($calendario as $oportunidade) {
            if (!$oportunidade['feita'] && $oportunidade['estado'] === 'em_atraso') $emAtraso++;
        }
        if ($emAtraso === 0) continue;
        $contactos = $contactosPorCrianca[$criancaId];
        $canais = [];
        $rotulosCanais = ['telefone' => 'Telefone', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'];
        foreach ($contactos as $contacto) {
            $canal = (string) $contacto['canal'];
            $canais[$canal] = $rotulosCanais[$canal] ?? $canal;
        }
        $canaisOrdenados = [];
        foreach ($rotulosCanais as $canal => $rotulo) {
            if (isset($canais[$canal])) $canaisOrdenados[] = $rotulo;
        }
        $ultimo = $contactos[0];
        $linhas[] = [
            'crianca_id' => $criancaId,
            'crianca_nome' => $crianca['nome'],
            'numero_processo' => $crianca['numero_processo'],
            'encarregado_nome' => $crianca['encarregado_nome'],
            'telefone' => $crianca['telefone'],
            'dias_em_atraso' => $emAtraso,
            'total_contactos' => count($contactos),
            'ultimo_contacto' => $ultimo['data_contacto'],
            'canais' => implode(', ', $canaisOrdenados),
            'ultimo_resultado' => $ultimo['resultado'],
            'ultimo_registado_por' => $ultimo['registado_por'] ?: '—',
        ];
    }
    usort($linhas, function (array $a, array $b) {
        return strcmp($a['crianca_nome'], $b['crianca_nome']);
    });
    return $linhas;
}

function relatorio_auditoria(PDO $pdo, array $filtros) {
    $stmt = $pdo->prepare(
        "SELECT a.id, a.criado_em, a.entidade, a.entidade_id, a.acao, a.campo,
                a.valor_anterior, a.valor_novo, u.nome AS utilizador_nome
         FROM auditoria a
         LEFT JOIN utilizadores u ON u.id = a.utilizador_id
         WHERE a.criado_em >= ? AND a.criado_em < DATE_ADD(?, INTERVAL 1 DAY)
         ORDER BY a.criado_em DESC, a.id DESC"
    );
    $stmt->execute([$filtros['data_inicio'] . ' 00:00:00', $filtros['data_fim']]);
    return $stmt->fetchAll();
}

function obter_linhas_relatorio(PDO $pdo, $tipo, array $filtros) {
    if ($tipo === 'sem_vacina') return relatorio_sem_vacina($pdo, $filtros);
    if ($tipo === 'vacinacoes') return relatorio_vacinacoes($pdo, $filtros);
    if ($tipo === 'stock') return relatorio_stock($pdo, $filtros);
    if ($tipo === 'faltosos') return relatorio_faltosos($pdo, $filtros);
    if ($tipo === 'auditoria') return relatorio_auditoria($pdo, $filtros);
    throw new RuntimeException('Tipo de relatório inválido.');
}

function relatorios_cabecalhos_csv($tipo) {
    $cabecalhos = [
        'sem_vacina' => ['Data', 'Atendimento', 'Criança', 'Processo', 'Vacina', 'Motivo'],
        'vacinacoes' => ['Data', 'Criança', 'Processo', 'Vacina', 'Dose', 'Profissional', 'Origem', 'Estado'],
        'stock' => ['Vacina', 'Lote', 'Validade', 'Fabricante', 'Saldo', 'Estado'],
        'faltosos' => ['Criança', 'Processo', 'Encarregado', 'Telefone', 'Doses em atraso', 'Contactos no período', 'Último contacto', 'Canais', 'Último resultado'],
        'auditoria' => ['Data/hora', 'Entidade', 'ID', 'Ação', 'Campo', 'Valor anterior', 'Valor novo', 'Utilizador'],
    ];
    return $cabecalhos[$tipo] ?? [];
}

function relatorios_linha_csv($tipo, array $linha) {
    if ($tipo === 'sem_vacina') {
        return [data_pt_simples(substr((string) $linha['criado_em'], 0, 10)), $linha['id'], $linha['crianca_nome'], $linha['numero_processo'], $linha['vacina_nome'], $linha['motivo']];
    }
    if ($tipo === 'vacinacoes') {
        return [data_pt_simples($linha['data_administracao']), $linha['crianca_nome'], $linha['numero_processo'], $linha['vacina_nome'], $linha['numero_dose'], $linha['profissional'], $linha['origem'] === 'externa' ? 'Externa' : 'Posto', (int) $linha['valida'] === 1 ? 'Válida' : 'Anulada'];
    }
    if ($tipo === 'stock') {
        return [$linha['vacina_nome'], $linha['numero_lote'], data_pt_simples($linha['validade']), $linha['fabricante'], $linha['saldo_atual'], $linha['estado_rotulo']];
    }
    if ($tipo === 'faltosos') {
        return [$linha['crianca_nome'], $linha['numero_processo'], $linha['encarregado_nome'], $linha['telefone'], $linha['dias_em_atraso'], $linha['total_contactos'], data_pt_simples($linha['ultimo_contacto']), $linha['canais'], $linha['ultimo_resultado']];
    }
    if ($tipo === 'auditoria') {
        return [data_hora_pt_simples($linha['criado_em']), $linha['entidade'], $linha['entidade_id'], $linha['acao'], $linha['campo'], $linha['valor_anterior'], $linha['valor_novo'], $linha['utilizador_nome'] ?: 'Sistema'];
    }
    return [];
}

function texto_csv_seguro($valor) {
    if (is_string($valor) && preg_match('/^[\x00-\x20]*[=+\-@]/', $valor)) {
        return "'" . $valor;
    }
    return $valor;
}

function enviar_csv_relatorio($tipo, array $linhas) {
    $nomeFicheiro = 'relatorio_' . preg_replace('/[^a-z_]/', '', $tipo) . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomeFicheiro . '"');
    header('Cache-Control: no-store');
    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, relatorios_cabecalhos_csv($tipo), ';');
    foreach ($linhas as $linha) {
        fputcsv($saida, array_map('texto_csv_seguro', relatorios_linha_csv($tipo, $linha)), ';');
    }
    fclose($saida);
}
