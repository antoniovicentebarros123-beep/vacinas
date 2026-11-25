<?php
/**
 * Cálculo indicativo do calendário e contactos de crianças faltosas.
 */

require_once __DIR__ . '/modulos.php';

function faltosos_dias_aviso(PDO $pdo) {
    return max(1, (int) obter_configuracao_bd($pdo, 'faltosos_aviso_dias', 30));
}

function data_mais_meses($data, $meses) {
    if (!data_iso_valida($data)) {
        throw new RuntimeException('Data de nascimento inválida.');
    }
    $meses = max(0, (int) $meses);
    $origem = new DateTimeImmutable($data);
    $dia = (int) $origem->format('j');
    $alvo = $origem->setDate((int) $origem->format('Y'), (int) $origem->format('n'), 1);
    $alvo = $alvo->modify('+' . $meses . ' months');
    $ultimoDia = (int) $alvo->format('t');
    return $alvo->setDate((int) $alvo->format('Y'), (int) $alvo->format('n'), min($dia, $ultimoDia))->format('Y-m-d');
}

function data_mais_dias($data, $dias) {
    if (!data_iso_valida($data)) {
        throw new RuntimeException('Data inválida.');
    }
    return (new DateTimeImmutable($data))->modify('+' . max(0, (int) $dias) . ' days')->format('Y-m-d');
}

function listar_oportunidades_calendario(PDO $pdo) {
    $stmt = $pdo->query(
        'SELECT c.*, v.nome AS vacina_nome
         FROM calendario_vacinal c
         INNER JOIN vacinas v ON v.id = c.vacina_id
         WHERE c.ativo = TRUE AND c.eliminado_em IS NULL
           AND v.ativo = TRUE AND v.eliminado_em IS NULL
         ORDER BY c.idade_ideal_meses, c.id'
    );
    return $stmt->fetchAll();
}

function listar_criancas_para_faltosos(PDO $pdo, $termo = '') {
    $termo = trim((string) $termo);
    $params = [];
    $where = 'c.ativo = TRUE AND c.eliminado_em IS NULL AND e.ativo = TRUE AND e.eliminado_em IS NULL';
    if ($termo !== '') {
        $like = '%' . $termo . '%';
        $telefone = normalizar_telefone($termo);
        $where .= ' AND (c.nome LIKE ? OR c.numero_processo LIKE ? OR c.numero_cartao LIKE ? OR e.nome LIKE ?';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        if ($telefone !== '') {
            $where .= ' OR e.telefone LIKE ? OR e.telefone_alt LIKE ?';
            $params[] = '%' . $telefone . '%';
            $params[] = '%' . $telefone . '%';
        }
        $where .= ')';
    }
    $stmt = $pdo->prepare(
        "SELECT c.*, e.nome AS encarregado_nome, e.parentesco, e.telefone, e.telefone_alt
         FROM criancas c
         INNER JOIN encarregados e ON e.id = c.encarregado_id
         WHERE {$where}
         ORDER BY c.nome, c.data_nascimento, c.id"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function listar_vacinacoes_validas_faltosos(PDO $pdo, $criancaId = 0) {
    $where = 'NOT EXISTS (SELECT 1 FROM vacinacao_anulacoes va WHERE va.vacinacao_id = vc.id)';
    $params = [];
    if ($criancaId > 0) {
        $where .= ' AND vc.crianca_id = ?';
        $params[] = (int) $criancaId;
    }
    $stmt = $pdo->prepare(
        "SELECT vc.id, vc.crianca_id, vc.vacina_id, vc.calendario_id, vc.numero_dose, vc.data_administracao
         FROM vacinacoes vc
         WHERE {$where}
         ORDER BY vc.data_administracao, vc.id"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function vacinacoes_cumprem_oportunidade(array $oportunidade, array $vacinacoes, array $idsGrupo = []) {
    $cumprem = [];
    foreach ($vacinacoes as $vacinacao) {
        $mesmaLinha = (int) $vacinacao['calendario_id'] === (int) $oportunidade['id'];
        $mesmaDose = (int) $vacinacao['vacina_id'] === (int) $oportunidade['vacina_id']
            && (int) $vacinacao['numero_dose'] === (int) $oportunidade['numero_dose'];
        $mesmoGrupo = $idsGrupo && in_array((int) $vacinacao['calendario_id'], $idsGrupo, true);
        if ($mesmaLinha || $mesmaDose || $mesmoGrupo) {
            $cumprem[] = $vacinacao;
        }
    }
    return $cumprem;
}

function calcular_estado_calendario(array $crianca, array $oportunidades, array $vacinacoes, $referencia = null, $diasAviso = 30) {
    $referencia = data_iso_valida($referencia) ? $referencia : date('Y-m-d');
    $diasAviso = max(1, (int) $diasAviso);
    $limiteAviso = data_mais_dias($referencia, $diasAviso);
    $mapa = [];
    $grupos = [];
    foreach ($oportunidades as $oportunidade) {
        $mapa[(int) $oportunidade['id']] = $oportunidade;
        if ($oportunidade['grupo_alternativa'] !== null && $oportunidade['grupo_alternativa'] !== '') {
            $grupos[$oportunidade['grupo_alternativa']][] = (int) $oportunidade['id'];
        }
    }

    $resultado = [];
    foreach ($oportunidades as $oportunidade) {
        $idsGrupo = isset($grupos[$oportunidade['grupo_alternativa']])
            ? $grupos[$oportunidade['grupo_alternativa']] : [];
        $cumprem = vacinacoes_cumprem_oportunidade($oportunidade, $vacinacoes, $idsGrupo);
        $feita = count($cumprem) > 0;
        $prevista = data_mais_meses($crianca['data_nascimento'], (int) $oportunidade['idade_ideal_meses']);
        $elegivel = true;
        $motivos = [];
        $dataElegivel = $prevista;

        if ($oportunidade['idade_minima_meses'] !== null) {
            $minima = data_mais_meses($crianca['data_nascimento'], (int) $oportunidade['idade_minima_meses']);
            if ($minima > $dataElegivel) $dataElegivel = $minima;
        }
        if ($oportunidade['janela_inicio_meses'] !== null) {
            $inicio = data_mais_meses($crianca['data_nascimento'], (int) $oportunidade['janela_inicio_meses']);
            if ($inicio > $dataElegivel) $dataElegivel = $inicio;
        }

        $vacinacoesAnteriores = [];
        if ($oportunidade['dose_anterior_exigida'] !== null) {
            $anterior = $mapa[(int) $oportunidade['dose_anterior_exigida']] ?? null;
            if (!$anterior) {
                $elegivel = false;
                $motivos[] = 'A dose anterior exigida não está ativa no calendário.';
            } else {
                $idsGrupoAnterior = isset($grupos[$anterior['grupo_alternativa']])
                    ? $grupos[$anterior['grupo_alternativa']] : [];
                $vacinacoesAnteriores = vacinacoes_cumprem_oportunidade($anterior, $vacinacoes, $idsGrupoAnterior);
                if (!$vacinacoesAnteriores) {
                    $elegivel = false;
                    $motivos[] = 'Aguarda a dose anterior exigida.';
                }
            }
        } elseif ($oportunidade['intervalo_minimo_dias'] !== null) {
            foreach ($vacinacoes as $vacinacao) {
                if ((int) $vacinacao['vacina_id'] === (int) $oportunidade['vacina_id']
                    && (int) $vacinacao['numero_dose'] < (int) $oportunidade['numero_dose']) {
                    $vacinacoesAnteriores[] = $vacinacao;
                }
            }
            if (!$vacinacoesAnteriores) {
                $elegivel = false;
                $motivos[] = 'Aguarda uma dose anterior para aplicar o intervalo mínimo.';
            }
        }

        if ($vacinacoesAnteriores && $oportunidade['intervalo_minimo_dias'] !== null) {
            $ultima = end($vacinacoesAnteriores);
            $aposIntervalo = data_mais_dias($ultima['data_administracao'], (int) $oportunidade['intervalo_minimo_dias']);
            if ($aposIntervalo > $dataElegivel) $dataElegivel = $aposIntervalo;
        }

        $dataEstado = $elegivel ? $dataElegivel : $prevista;
        if ($feita) {
            $estado = 'feita';
        } elseif ($dataEstado < $referencia) {
            $estado = 'em_atraso';
        } elseif ($dataEstado <= $limiteAviso) {
            $estado = 'a_vencer';
        } else {
            $estado = 'em_dia';
        }
        $diasAte = (int) round((strtotime($dataEstado) - strtotime($referencia)) / 86400);
        $resultado[] = [
            'id' => (int) $oportunidade['id'],
            'vacina_id' => (int) $oportunidade['vacina_id'],
            'vacina_nome' => $oportunidade['vacina_nome'],
            'numero_dose' => (int) $oportunidade['numero_dose'],
            'rotulo_idade' => $oportunidade['rotulo_idade'],
            'grupo_alternativa' => $oportunidade['grupo_alternativa'],
            'data_prevista' => $prevista,
            'data_elegivel' => $dataElegivel,
            'estado' => $estado,
            'feita' => $feita,
            'elegivel' => $elegivel,
            'motivo_elegibilidade' => $motivos ? implode(' ', $motivos) : '',
            'dias_ate' => $diasAte,
            'validar_clinica' => (bool) $oportunidade['validar_clinica'],
            'vacinacao_id' => $feita ? (int) $cumprem[0]['id'] : null,
        ];
    }
    return $resultado;
}

function rotulo_estado_faltoso($estado) {
    $rotulos = [
        'feita' => 'Feita',
        'em_dia' => 'Em dia',
        'a_vencer' => 'A vencer',
        'em_atraso' => 'Em atraso',
    ];
    return $rotulos[$estado] ?? 'A validar';
}

function classe_estado_faltoso($estado) {
    $classes = [
        'feita' => 'etiqueta-sucesso',
        'em_dia' => 'etiqueta-neutra',
        'a_vencer' => 'etiqueta-aviso',
        'em_atraso' => 'etiqueta-erro',
    ];
    return $classes[$estado] ?? 'etiqueta-aviso';
}

function listar_criancas_com_calendario(PDO $pdo, $termo = '', $estado = 'em_atraso', $pagina = 1, $porPagina = 15, $referencia = null) {
    $estadosPermitidos = ['em_atraso', 'a_vencer', 'em_dia', 'todos'];
    $estado = in_array($estado, $estadosPermitidos, true) ? $estado : 'em_atraso';
    $pagina = max(1, (int) $pagina);
    $porPagina = max(1, min(50, (int) $porPagina));
    $criancas = listar_criancas_para_faltosos($pdo, $termo);
    $oportunidades = listar_oportunidades_calendario($pdo);
    $vacinacoes = listar_vacinacoes_validas_faltosos($pdo);
    $porCrianca = [];
    foreach ($vacinacoes as $vacinacao) {
        $porCrianca[(int) $vacinacao['crianca_id']][] = $vacinacao;
    }
    $diasAviso = faltosos_dias_aviso($pdo);
    $registos = [];
    foreach ($criancas as $crianca) {
        $calendario = calcular_estado_calendario(
            $crianca,
            $oportunidades,
            $porCrianca[(int) $crianca['id']] ?? [],
            $referencia,
            $diasAviso
        );
        $pendentes = [];
        foreach ($calendario as $oportunidade) {
            if (!$oportunidade['feita'] && ($estado === 'todos' || $oportunidade['estado'] === $estado)) {
                $pendentes[] = $oportunidade;
            }
        }
        if (!$pendentes) continue;
        $contagens = ['em_atraso' => 0, 'a_vencer' => 0, 'em_dia' => 0, 'feita' => 0];
        foreach ($calendario as $oportunidade) $contagens[$oportunidade['estado']]++;
        $registos[] = [
            'crianca' => $crianca,
            'calendario' => $calendario,
            'pendentes' => $pendentes,
            'contagens' => $contagens,
        ];
    }
    $total = count($registos);
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina = min($pagina, $paginas);
    $offset = ($pagina - 1) * $porPagina;
    return [
        'total' => $total,
        'pagina' => $pagina,
        'paginas' => $paginas,
        'estado' => $estado,
        'dias_aviso' => $diasAviso,
        'registos' => array_slice($registos, $offset, $porPagina),
    ];
}

function validar_dados_contacto_faltoso(array $entrada) {
    $erros = [];
    $data = is_string($entrada['data_contacto'] ?? null) ? trim($entrada['data_contacto']) : '';
    $canal = is_string($entrada['canal'] ?? null) ? $entrada['canal'] : '';
    $resultado = is_string($entrada['resultado'] ?? null) ? trim($entrada['resultado']) : '';
    $notas = is_string($entrada['notas'] ?? null) ? trim($entrada['notas']) : '';
    if (!data_iso_valida($data)) $erros[] = 'Indique uma data de contacto válida.';
    elseif ($data > date('Y-m-d')) $erros[] = 'A data de contacto não pode estar no futuro.';
    if (!in_array($canal, ['telefone', 'sms', 'whatsapp'], true)) $erros[] = 'Selecione um canal de contacto válido.';
    if ($resultado === '' || !texto_dentro_limite($resultado, 100)) $erros[] = 'Indique o resultado do contacto (até 100 caracteres).';
    if (!texto_dentro_limite($notas, 2000)) $erros[] = 'As notas não podem exceder 2000 caracteres.';
    return ['erros' => $erros, 'dados' => compact('data', 'canal', 'resultado', 'notas')];
}

function registar_contacto_faltoso(PDO $pdo, $criancaId, array $entrada, $utilizadorId) {
    $validacao = validar_dados_contacto_faltoso($entrada);
    if ($validacao['erros']) throw new RuntimeException($validacao['erros'][0]);
    $stmt = $pdo->prepare('SELECT id FROM criancas WHERE id = ? AND ativo = TRUE AND eliminado_em IS NULL LIMIT 1');
    $stmt->execute([(int) $criancaId]);
    if (!$stmt->fetch()) throw new RuntimeException('Criança não encontrada ou inativa.');
    $dados = $validacao['dados'];
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO contactos_faltosos (crianca_id, data_contacto, canal, resultado, notas, utilizador_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $criancaId, $dados['data'], $dados['canal'], $dados['resultado'],
            $dados['notas'] !== '' ? $dados['notas'] : null, (int) $utilizadorId,
        ]);
        $id = (int) $pdo->lastInsertId();
        registrar_auditoria($pdo, 'contactos_faltosos', $id, 'criar', 'registo', null, 'Contacto registado', $utilizadorId);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function obter_contacto_faltoso(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT * FROM contactos_faltosos WHERE id = ? AND eliminado_em IS NULL LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}

function listar_contactos_crianca(PDO $pdo, $criancaId) {
    $stmt = $pdo->prepare(
        'SELECT cf.*, u.nome AS registado_por
         FROM contactos_faltosos cf
         LEFT JOIN utilizadores u ON u.id = cf.utilizador_id
         WHERE cf.crianca_id = ? AND cf.eliminado_em IS NULL
         ORDER BY cf.data_contacto DESC, cf.id DESC'
    );
    $stmt->execute([(int) $criancaId]);
    return $stmt->fetchAll();
}

function eliminar_contacto_faltoso_logica(PDO $pdo, $id, $utilizadorId) {
    $stmt = $pdo->prepare('UPDATE contactos_faltosos SET eliminado_em = NOW() WHERE id = ? AND eliminado_em IS NULL');
    $stmt->execute([(int) $id]);
    if ($stmt->rowCount() === 0) throw new RuntimeException('Contacto não encontrado ou já eliminado.');
    registrar_auditoria($pdo, 'contactos_faltosos', $id, 'eliminar', 'eliminado_em', null, date('Y-m-d H:i:s'), $utilizadorId);
}
