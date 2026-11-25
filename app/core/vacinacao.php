<?php
/**
 * Regras de vacinação, histórico e anulação sem devolução automática de stock.
 */

require_once __DIR__ . '/modulos.php';
require_once __DIR__ . '/atendimentos.php';

function validar_dados_vacinacao(array $entrada, $externa = false) {
    $erros = [];
    $vacinaId = isset($entrada['vacina_id']) && is_scalar($entrada['vacina_id']) ? (int) $entrada['vacina_id'] : 0;
    $dose = isset($entrada['numero_dose']) && is_scalar($entrada['numero_dose']) ? (int) $entrada['numero_dose'] : -1;
    $calendarioId = isset($entrada['calendario_id']) && is_scalar($entrada['calendario_id']) ? (int) $entrada['calendario_id'] : 0;
    $loteId = $externa ? 0 : (isset($entrada['lote_id']) && is_scalar($entrada['lote_id']) ? (int) $entrada['lote_id'] : 0);
    $data = is_string($entrada['data_administracao'] ?? null) ? trim($entrada['data_administracao']) : ($externa ? '' : date('Y-m-d'));
    $local = is_string($entrada['local_anatomico'] ?? null) ? trim($entrada['local_anatomico']) : '';
    $reacoes = is_string($entrada['reacoes'] ?? null) ? trim($entrada['reacoes']) : '';
    $observacoes = is_string($entrada['observacoes'] ?? null) ? trim($entrada['observacoes']) : '';
    $administradorExterno = is_string($entrada['administrado_por_nome'] ?? null) ? trim($entrada['administrado_por_nome']) : '';
    if ($vacinaId <= 0) $erros[] = 'Selecione uma vacina.';
    if ($dose < 0 || $dose > 255) $erros[] = 'Indique um número de dose válido.';
    if ($externa && $administradorExterno === '') $erros[] = 'Indique quem administrou a dose externa.';
    if ($externa && $administradorExterno !== '' && !texto_dentro_limite($administradorExterno, 100)) $erros[] = 'O nome de quem administrou não pode exceder 100 caracteres.';
    if (!data_iso_valida($data)) $erros[] = 'Indique uma data de administração válida.';
    elseif ($data > date('Y-m-d')) $erros[] = 'A data de administração não pode estar no futuro.';
    if (!$externa && $loteId <= 0) $erros[] = 'Selecione um lote.';
    if ($local !== '' && !texto_dentro_limite($local, 100)) $erros[] = 'O local anatómico não pode exceder 100 caracteres.';
    foreach (['reacoes' => $reacoes, 'observacoes' => $observacoes] as $campo => $valor) {
        if (!texto_dentro_limite($valor, 2000)) $erros[] = "O campo {$campo} não pode exceder 2000 caracteres.";
    }
    return ['erros' => $erros, 'dados' => compact('vacinaId', 'dose', 'calendarioId', 'loteId', 'data', 'local', 'reacoes', 'observacoes', 'administradorExterno')];
}

function listar_atendimentos_vacinacao(PDO $pdo) {
    $stmt = $pdo->query(
        "SELECT a.id, a.estado, a.criado_em, a.iniciado_em, c.nome, c.numero_processo,
                COUNT(av.id) AS vacinas_pretendidas,
                SUM(CASE WHEN vc.id IS NULL THEN 1 ELSE 0 END) AS vacinas_pendentes
         FROM atendimentos a
         INNER JOIN criancas c ON c.id = a.crianca_id
         INNER JOIN atendimento_vacinas av ON av.atendimento_id = a.id
         LEFT JOIN vacinacoes vc ON vc.atendimento_id = a.id AND vc.vacina_id = av.vacina_id
              AND NOT EXISTS (SELECT 1 FROM vacinacao_anulacoes va WHERE va.vacinacao_id = vc.id)
         WHERE a.estado = 'em_atendimento'
           AND c.ativo = TRUE AND c.eliminado_em IS NULL
         GROUP BY a.id, a.estado, a.criado_em, a.iniciado_em, c.nome, c.numero_processo
         ORDER BY a.iniciado_em, a.id"
    );
    return $stmt->fetchAll();
}

function listar_vacinas_pendentes_atendimento(PDO $pdo, $atendimentoId) {
    $stmt = $pdo->prepare(
        "SELECT v.id, v.nome
         FROM atendimento_vacinas av
         INNER JOIN vacinas v ON v.id = av.vacina_id
         WHERE av.atendimento_id = ?
           AND NOT EXISTS (
               SELECT 1 FROM vacinacoes vc
               WHERE vc.atendimento_id = av.atendimento_id AND vc.vacina_id = av.vacina_id
                 AND NOT EXISTS (SELECT 1 FROM vacinacao_anulacoes va WHERE va.vacinacao_id = vc.id)
           )
         ORDER BY v.nome"
    );
    $stmt->execute([(int) $atendimentoId]);
    return $stmt->fetchAll();
}

function listar_doses_calendario(PDO $pdo, $vacinaId) {
    $stmt = $pdo->prepare('SELECT id, numero_dose, rotulo_idade FROM calendario_vacinal WHERE vacina_id = ? AND ativo = TRUE AND eliminado_em IS NULL ORDER BY numero_dose, id');
    $stmt->execute([(int) $vacinaId]);
    return $stmt->fetchAll();
}

function listar_lotes_para_vacinacao(PDO $pdo, $vacinaId) {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.numero_lote, s.validade, s.fabricante, s.saldo_atual
         FROM stock_saldo_lotes s
         INNER JOIN stock_lotes l ON l.id = s.id
         WHERE s.vacina_id = ? AND s.validade >= CURDATE() AND s.saldo_atual > 0
         ORDER BY s.validade, s.numero_lote, s.id'
    );
    $stmt->execute([(int) $vacinaId]);
    return $stmt->fetchAll();
}

function calendario_valido_vacinacao(PDO $pdo, $vacinaId, $dose, $calendarioId = 0) {
    if ($calendarioId > 0) {
        $stmt = $pdo->prepare('SELECT c.id FROM calendario_vacinal c INNER JOIN vacinas v ON v.id = c.vacina_id WHERE c.id = ? AND c.vacina_id = ? AND c.numero_dose = ? AND c.ativo = TRUE AND c.eliminado_em IS NULL AND v.ativo = TRUE AND v.eliminado_em IS NULL');
        $stmt->execute([$calendarioId, (int) $vacinaId, (int) $dose]);
        if ($stmt->fetch()) return $calendarioId;
        throw new RuntimeException('A dose selecionada não pertence à vacina ou ao calendário.');
    }
    $stmt = $pdo->prepare('SELECT c.id FROM calendario_vacinal c INNER JOIN vacinas v ON v.id = c.vacina_id WHERE c.vacina_id = ? AND c.numero_dose = ? AND c.ativo = TRUE AND c.eliminado_em IS NULL AND v.ativo = TRUE AND v.eliminado_em IS NULL ORDER BY c.id LIMIT 1');
    $stmt->execute([(int) $vacinaId, (int) $dose]);
    $id = $stmt->fetchColumn();
    if (!$id) throw new RuntimeException('Esta dose não existe no calendário da vacina.');
    return (int) $id;
}

function existe_vacinacao_valida(PDO $pdo, $criancaId, $vacinaId, $dose) {
    $stmt = $pdo->prepare(
        'SELECT vc.id FROM vacinacoes vc
         WHERE vc.crianca_id = ? AND vc.vacina_id = ? AND vc.numero_dose = ?
           AND NOT EXISTS (SELECT 1 FROM vacinacao_anulacoes va WHERE va.vacinacao_id = vc.id)
         LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([(int) $criancaId, (int) $vacinaId, (int) $dose]);
    return (bool) $stmt->fetch();
}

function registrar_vacinacao_posto(PDO $pdo, $atendimentoId, array $entrada, $utilizadorId) {
    $validacao = validar_dados_vacinacao($entrada);
    if ($validacao['erros']) throw new RuntimeException($validacao['erros'][0]);
    $dados = $validacao['dados'];
    $pdo->beginTransaction();
    try {
        // Serializa o atendimento antes de bloquear o lote partilhado.
        $stmt = $pdo->prepare('SELECT id, crianca_id, estado FROM atendimentos WHERE id = ? FOR UPDATE');
        $stmt->execute([(int) $atendimentoId]);
        $atendimento = $stmt->fetch();
        if (!$atendimento || $atendimento['estado'] !== 'em_atendimento') throw new RuntimeException('O atendimento precisa de estar em curso.');
        $stmt = $pdo->prepare('SELECT id FROM criancas WHERE id = ? AND ativo = TRUE AND eliminado_em IS NULL FOR UPDATE');
        $stmt->execute([(int) $atendimento['crianca_id']]);
        if (!$stmt->fetch()) throw new RuntimeException('A criança não está ativa.');
        $stmt = $pdo->prepare('SELECT id FROM atendimento_vacinas WHERE atendimento_id = ? AND vacina_id = ?');
        $stmt->execute([(int) $atendimentoId, $dados['vacinaId']]);
        if (!$stmt->fetch()) throw new RuntimeException('A vacina não foi pretendida neste atendimento.');

        // Bloqueio do lote e cálculo do saldo dentro da mesma transação.
        $stmt = $pdo->prepare('SELECT id, vacina_id, validade FROM stock_lotes WHERE id = ? AND ativo = TRUE AND eliminado_em IS NULL FOR UPDATE');
        $stmt->execute([$dados['loteId']]);
        $lote = $stmt->fetch();
        if (!$lote) throw new RuntimeException('Lote não encontrado ou inativo.');
        if ((int) $lote['vacina_id'] !== $dados['vacinaId']) throw new RuntimeException('O lote pertence a outra vacina.');
        if ($lote['validade'] < date('Y-m-d')) throw new RuntimeException('O lote está fora da validade.');
        // A soma direta evita snapshot obsoleto da vista agregada em REPEATABLE READ.
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(CASE sm.tipo
                WHEN 'entrada' THEN sm.quantidade
                WHEN 'saida_vacinacao' THEN -sm.quantidade
                WHEN 'perda' THEN -sm.quantidade
                WHEN 'ajuste_mais' THEN sm.quantidade
                WHEN 'ajuste_menos' THEN -sm.quantidade
                ELSE 0 END), 0)
             FROM stock_movimentos sm
             WHERE sm.lote_id = ? FOR UPDATE"
        );
        $stmt->execute([$dados['loteId']]);
        $saldo = (int) $stmt->fetchColumn();
        if ($saldo <= 0) throw new RuntimeException('Sem stock neste lote.');
        if (existe_vacinacao_valida($pdo, $atendimento['crianca_id'], $dados['vacinaId'], $dados['dose'])) {
            throw new RuntimeException('Já existe uma vacinação válida desta dose para a criança.');
        }
        $calendarioId = calendario_valido_vacinacao($pdo, $dados['vacinaId'], $dados['dose'], $dados['calendarioId']);
        $stmt = $pdo->prepare(
            'INSERT INTO vacinacoes (crianca_id, atendimento_id, vacina_id, calendario_id, numero_dose,
                data_administracao, lote_id, administrado_por_id, local_anatomico, origem, observacoes, reacoes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $atendimento['crianca_id'], (int) $atendimentoId, $dados['vacinaId'], $calendarioId,
            $dados['dose'], $dados['data'], $dados['loteId'], (int) $utilizadorId,
            $dados['local'] ?: null, 'posto', $dados['observacoes'] ?: null, $dados['reacoes'] ?: null,
        ]);
        $vacinacaoId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO stock_movimentos (lote_id, tipo, quantidade, motivo, vacinacao_id, utilizador_id) VALUES (?, 'saida_vacinacao', 1, 'Saída por vacinação', ?, ?)");
        $stmt->execute([$dados['loteId'], $vacinacaoId, (int) $utilizadorId]);

        // Um atendimento com várias vacinas só termina quando todas têm dose válida.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM atendimento_vacinas av
             WHERE av.atendimento_id = ?
               AND NOT EXISTS (
                   SELECT 1 FROM vacinacoes vc WHERE vc.atendimento_id = av.atendimento_id AND vc.vacina_id = av.vacina_id
                     AND NOT EXISTS (SELECT 1 FROM vacinacao_anulacoes va WHERE va.vacinacao_id = vc.id)
               )"
        );
        $stmt->execute([(int) $atendimentoId]);
        $concluido = (int) $stmt->fetchColumn() === 0;
        if ($concluido) {
            $stmt = $pdo->prepare("UPDATE atendimentos SET estado = 'concluido', concluido_em = NOW() WHERE id = ? AND estado = 'em_atendimento'");
            $stmt->execute([(int) $atendimentoId]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('Não foi possível concluir o atendimento.');
            registrar_auditoria($pdo, 'atendimentos', $atendimentoId, 'concluir', 'estado', 'em_atendimento', 'concluido', $utilizadorId);
        }
        registrar_auditoria($pdo, 'vacinacoes', $vacinacaoId, 'criar', 'registo', null, $vacinacaoId, $utilizadorId);
        $pdo->commit();
        return ['id' => $vacinacaoId, 'concluido' => $concluido];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function registrar_vacinacao_externa(PDO $pdo, $criancaId, array $entrada, $utilizadorId) {
    $validacao = validar_dados_vacinacao($entrada, true);
    if ($validacao['erros']) throw new RuntimeException($validacao['erros'][0]);
    $dados = $validacao['dados'];
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id FROM criancas WHERE id = ? AND ativo = TRUE AND eliminado_em IS NULL FOR UPDATE');
        $stmt->execute([(int) $criancaId]);
        if (!$stmt->fetch()) throw new RuntimeException('Criança não encontrada ou inativa.');
        if (existe_vacinacao_valida($pdo, $criancaId, $dados['vacinaId'], $dados['dose'])) throw new RuntimeException('Já existe uma vacinação válida desta dose para a criança.');
        $calendarioId = calendario_valido_vacinacao($pdo, $dados['vacinaId'], $dados['dose'], $dados['calendarioId']);
        $stmt = $pdo->prepare(
            'INSERT INTO vacinacoes (crianca_id, vacina_id, calendario_id, numero_dose, data_administracao,
                administrado_por_id, administrado_por_nome, local_anatomico, origem, observacoes, reacoes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $criancaId, $dados['vacinaId'], $calendarioId, $dados['dose'], $dados['data'],
            (int) $utilizadorId, $dados['administradorExterno'], $dados['local'] ?: null,
            'externa', $dados['observacoes'] ?: null, $dados['reacoes'] ?: null,
        ]);
        $id = (int) $pdo->lastInsertId();
        registrar_auditoria($pdo, 'vacinacoes', $id, 'criar_externo', 'registo', null, $id, $utilizadorId);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function listar_historico_crianca(PDO $pdo, $criancaId) {
    $stmt = $pdo->prepare(
        'SELECT vc.*, v.nome AS vacina_nome, l.numero_lote,
                u.nome AS administrado_por, va.id AS anulacao_id, va.motivo AS anulacao_motivo,
                va.criado_em AS anulada_em, au.nome AS anulada_por
         FROM vacinacoes vc
         INNER JOIN vacinas v ON v.id = vc.vacina_id
         LEFT JOIN stock_lotes l ON l.id = vc.lote_id
         LEFT JOIN utilizadores u ON u.id = vc.administrado_por_id
         LEFT JOIN vacinacao_anulacoes va ON va.vacinacao_id = vc.id
         LEFT JOIN utilizadores au ON au.id = va.utilizador_id
         WHERE vc.crianca_id = ?
         ORDER BY vc.data_administracao, vc.id'
    );
    $stmt->execute([(int) $criancaId]);
    return $stmt->fetchAll();
}

function obter_vacinacao(PDO $pdo, $id) {
    $stmt = $pdo->prepare(
        'SELECT vc.*, c.nome AS crianca_nome, v.nome AS vacina_nome, va.id AS anulacao_id
         FROM vacinacoes vc INNER JOIN criancas c ON c.id = vc.crianca_id INNER JOIN vacinas v ON v.id = vc.vacina_id
         LEFT JOIN vacinacao_anulacoes va ON va.vacinacao_id = vc.id WHERE vc.id = ? LIMIT 1'
    );
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}

function anular_vacinacao(PDO $pdo, $id, $motivo, $utilizadorId) {
    $motivo = trim($motivo);
    if ($motivo === '') throw new RuntimeException('O motivo da anulação é obrigatório.');
    if (!texto_dentro_limite($motivo, 2000)) throw new RuntimeException('O motivo não pode exceder 2000 caracteres.');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id FROM vacinacoes WHERE id = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        if (!$stmt->fetch()) throw new RuntimeException('Vacinação não encontrada.');
        $stmt = $pdo->prepare('SELECT id FROM vacinacao_anulacoes WHERE vacinacao_id = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        if ($stmt->fetch()) throw new RuntimeException('Esta vacinação já está anulada.');
        $stmt = $pdo->prepare('INSERT INTO vacinacao_anulacoes (vacinacao_id, motivo, utilizador_id) VALUES (?, ?, ?)');
        $stmt->execute([(int) $id, $motivo, (int) $utilizadorId]);
        registrar_auditoria($pdo, 'vacinacoes', $id, 'anular', 'anulacao', null, 'registada', $utilizadorId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
