<?php
/**
 * Regras de stock: lotes, movimentos append-only, saldos e alertas.
 */

require_once __DIR__ . '/modulos.php';

function texto_dentro_limite_stock($texto, $limite) {
    $comprimento = function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
    return $comprimento <= (int) $limite;
}

function normalizar_numero_lote($valor) {
    $texto = trim((string) $valor);
    return function_exists('mb_strtoupper') ? mb_strtoupper($texto, 'UTF-8') : strtoupper($texto);
}

function validar_dados_lote(array $entrada) {
    $erros = [];
    $vacinaId = isset($entrada['vacina_id']) && is_scalar($entrada['vacina_id']) ? (int) $entrada['vacina_id'] : 0;
    $numeroLote = normalizar_numero_lote(is_string($entrada['numero_lote'] ?? null) ? $entrada['numero_lote'] : '');
    $validade = is_string($entrada['validade'] ?? null) ? trim($entrada['validade']) : '';
    $fabricante = is_string($entrada['fabricante'] ?? null) ? trim($entrada['fabricante']) : '';
    $quantidadeInicial = 0;

    if ($vacinaId <= 0) {
        $erros[] = 'Selecione uma vacina.';
    }
    if ($numeroLote === '') {
        $erros[] = 'O número do lote é obrigatório.';
    } elseif (!texto_dentro_limite_stock($numeroLote, 50)) {
        $erros[] = 'O número do lote não pode exceder 50 caracteres.';
    } elseif (preg_match('/[\x00-\x1F\x7F]/', $numeroLote)) {
        $erros[] = 'O número do lote contém caracteres inválidos.';
    }
    if (!data_iso_valida($validade)) {
        $erros[] = 'Indique uma validade válida.';
    }
    if ($fabricante === '') {
        $erros[] = 'O fabricante é obrigatório.';
    } elseif (!texto_dentro_limite_stock($fabricante, 100)) {
        $erros[] = 'O fabricante não pode exceder 100 caracteres.';
    }
    if (isset($entrada['quantidade_inicial'])) {
        $quantidadeTexto = is_scalar($entrada['quantidade_inicial']) ? trim((string) $entrada['quantidade_inicial']) : '';
        if ($quantidadeTexto !== '' && preg_match('/^\d+$/', $quantidadeTexto) !== 1) {
            $erros[] = 'A quantidade inicial deve ser um número inteiro não negativo.';
        } elseif ($quantidadeTexto !== '') {
            $quantidadeInicial = (int) $quantidadeTexto;
            if ($quantidadeInicial > 2147483647) {
                $erros[] = 'A quantidade inicial excede o limite permitido.';
            }
        }
    }

    return [
        'erros' => $erros,
        'dados' => [
            'vacina_id' => $vacinaId,
            'numero_lote' => $numeroLote,
            'validade' => $validade,
            'fabricante' => $fabricante,
            'quantidade_inicial' => $quantidadeInicial,
        ],
    ];
}

function listar_vacinas_stock(PDO $pdo) {
    $stmt = $pdo->query(
        'SELECT id, nome FROM vacinas
         WHERE ativo = TRUE AND eliminado_em IS NULL
         ORDER BY nome'
    );
    return $stmt->fetchAll();
}

function obter_lote_stock(PDO $pdo, $id) {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.vacina_id, s.numero_lote, s.validade, s.fabricante,
                s.saldo_atual, l.quantidade_inicial, l.criado_em, v.nome AS vacina_nome
         FROM stock_saldo_lotes s
         INNER JOIN stock_lotes l ON l.id = s.id
         INNER JOIN vacinas v ON v.id = s.vacina_id
         WHERE s.id = ? LIMIT 1'
    );
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}

function listar_lotes_stock(PDO $pdo, $vacinaId = 0, $termo = '') {
    $where = [];
    $parametros = [];
    if ($vacinaId > 0) {
        $where[] = 's.vacina_id = ?';
        $parametros[] = (int) $vacinaId;
    }
    $termo = trim((string) $termo);
    if ($termo !== '') {
        $where[] = '(s.numero_lote LIKE ? OR v.nome LIKE ? OR s.fabricante LIKE ?)';
        $like = '%' . $termo . '%';
        $parametros[] = $like;
        $parametros[] = $like;
        $parametros[] = $like;
    }
    $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare(
        'SELECT s.id, s.vacina_id, s.numero_lote, s.validade, s.fabricante,
                s.saldo_atual, v.nome AS vacina_nome
         FROM stock_saldo_lotes s
         INNER JOIN vacinas v ON v.id = s.vacina_id' . $sqlWhere . '
         ORDER BY v.nome, s.validade, s.numero_lote'
    );
    $stmt->execute($parametros);
    return $stmt->fetchAll();
}

function listar_movimentos_lote(PDO $pdo, $loteId) {
    $stmt = $pdo->prepare(
        'SELECT m.id, m.tipo, m.quantidade, m.motivo, m.data_movimento,
                u.nome AS utilizador_nome
         FROM stock_movimentos m
         LEFT JOIN utilizadores u ON u.id = m.utilizador_id
         WHERE m.lote_id = ?
         ORDER BY m.data_movimento DESC, m.id DESC'
    );
    $stmt->execute([(int) $loteId]);
    return $stmt->fetchAll();
}

function limiares_stock(PDO $pdo) {
    return [
        'validade_dias' => max(1, (int) obter_configuracao_bd($pdo, 'stock_validade_aviso_dias', 30)),
        'minimo_doses' => max(0, (int) obter_configuracao_bd($pdo, 'stock_minimo_doses', 5)),
    ];
}

function dias_ate_validade($validade) {
    if (!data_iso_valida($validade)) {
        return null;
    }
    $hoje = new DateTime('today');
    $data = new DateTime($validade);
    return (int) $hoje->diff($data)->format('%r%a');
}

function estado_stock_lote(array $lote, array $limiares) {
    $dias = dias_ate_validade($lote['validade']);
    $saldo = (int) $lote['saldo_atual'];
    if ($saldo <= 0) {
        return ['rotulo' => 'Sem stock', 'classe' => 'etiqueta-erro'];
    }
    if ($dias !== null && $dias < 0) {
        return ['rotulo' => 'Vencido', 'classe' => 'etiqueta-erro'];
    }
    if ($saldo <= (int) $limiares['minimo_doses']) {
        return ['rotulo' => 'Stock baixo', 'classe' => 'etiqueta-aviso'];
    }
    if ($dias !== null && $dias <= (int) $limiares['validade_dias']) {
        return ['rotulo' => 'Validade próxima', 'classe' => 'etiqueta-aviso'];
    }
    return ['rotulo' => 'Regular', 'classe' => 'etiqueta-sucesso'];
}

function criar_lote_stock(PDO $pdo, array $entrada, $utilizadorId) {
    $validacao = validar_dados_lote($entrada);
    if ($validacao['erros']) {
        throw new RuntimeException($validacao['erros'][0]);
    }
    $dados = $validacao['dados'];
    $pdo->beginTransaction();
    try {
        // O bloqueio da vacina serializa a criação de lotes para o mesmo item.
        $stmt = $pdo->prepare('SELECT id FROM vacinas WHERE id = ? AND ativo = TRUE AND eliminado_em IS NULL FOR UPDATE');
        $stmt->execute([$dados['vacina_id']]);
        if (!$stmt->fetch()) {
            throw new RuntimeException('A vacina selecionada não está ativa.');
        }
        $stmt = $pdo->prepare(
            'SELECT id FROM stock_lotes
             WHERE vacina_id = ? AND numero_lote = ? AND ativo = TRUE AND eliminado_em IS NULL
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$dados['vacina_id'], $dados['numero_lote']]);
        if ($stmt->fetch()) {
            throw new RuntimeException('Já existe um lote ativo com este número para a vacina.');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO stock_lotes
             (vacina_id, numero_lote, validade, fabricante, quantidade_inicial, criado_por_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $dados['vacina_id'], $dados['numero_lote'], $dados['validade'],
            $dados['fabricante'], $dados['quantidade_inicial'], (int) $utilizadorId,
        ]);
        $id = (int) $pdo->lastInsertId();
        if ($dados['quantidade_inicial'] > 0) {
            $stmt = $pdo->prepare(
                'INSERT INTO stock_movimentos (lote_id, tipo, quantidade, motivo, utilizador_id)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$id, 'entrada', $dados['quantidade_inicial'], 'Entrada inicial do lote', (int) $utilizadorId]);
        }
        registrar_auditoria($pdo, 'stock_lotes', $id, 'criar', 'registo', null, $dados['numero_lote'], $utilizadorId);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function atualizar_lote_stock(PDO $pdo, $id, array $entrada, $utilizadorId) {
    $validacao = validar_dados_lote($entrada);
    if ($validacao['erros']) {
        throw new RuntimeException($validacao['erros'][0]);
    }
    $dados = $validacao['dados'];
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT id, vacina_id, numero_lote, validade, fabricante
             FROM stock_lotes
             WHERE id = ? AND ativo = TRUE AND eliminado_em IS NULL FOR UPDATE'
        );
        $stmt->execute([(int) $id]);
        $antes = $stmt->fetch();
        if (!$antes) {
            throw new RuntimeException('Lote não encontrado.');
        }
        if ((int) $antes['vacina_id'] !== $dados['vacina_id']) {
            throw new RuntimeException('A vacina do lote não pode ser alterada.');
        }
        $stmt = $pdo->prepare(
            'SELECT id FROM stock_lotes
             WHERE vacina_id = ? AND numero_lote = ? AND id <> ? AND ativo = TRUE AND eliminado_em IS NULL
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$dados['vacina_id'], $dados['numero_lote'], (int) $id]);
        if ($stmt->fetch()) {
            throw new RuntimeException('Já existe outro lote ativo com este número para a vacina.');
        }
        $stmt = $pdo->prepare(
            'UPDATE stock_lotes SET numero_lote = ?, validade = ?, fabricante = ?
             WHERE id = ? AND ativo = TRUE AND eliminado_em IS NULL'
        );
        $stmt->execute([$dados['numero_lote'], $dados['validade'], $dados['fabricante'], (int) $id]);
        auditar_alteracoes($pdo, 'stock_lotes', $id, $antes, $dados, ['numero_lote', 'validade', 'fabricante'], $utilizadorId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function registrar_movimento_stock(PDO $pdo, $loteId, $tipo, $quantidade, $motivo, $utilizadorId) {
    $tiposPermitidos = ['entrada', 'perda', 'ajuste_mais', 'ajuste_menos'];
    if (!in_array($tipo, $tiposPermitidos, true)) {
        throw new RuntimeException('Tipo de movimento não permitido neste formulário.');
    }
    $quantidadeTexto = is_scalar($quantidade) ? trim((string) $quantidade) : '';
    if (preg_match('/^\d+$/', $quantidadeTexto) !== 1 || (int) $quantidadeTexto <= 0) {
        throw new RuntimeException('A quantidade deve ser um número inteiro maior que zero.');
    }
    $quantidadeInteira = (int) $quantidadeTexto;
    if ($quantidadeInteira > 2147483647) {
        throw new RuntimeException('A quantidade excede o limite permitido.');
    }
    $motivo = is_string($motivo) ? trim($motivo) : '';
    if (in_array($tipo, ['perda', 'ajuste_mais', 'ajuste_menos'], true) && $motivo === '') {
        throw new RuntimeException('O motivo é obrigatório para perdas e ajustes.');
    }
    if (!texto_dentro_limite_stock($motivo, 2000)) {
        throw new RuntimeException('O motivo não pode exceder 2000 caracteres.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT id FROM stock_lotes
             WHERE id = ? AND ativo = TRUE AND eliminado_em IS NULL FOR UPDATE'
        );
        $stmt->execute([(int) $loteId]);
        if (!$stmt->fetch()) {
            throw new RuntimeException('Lote não encontrado ou inativo.');
        }
        $stmt = $pdo->prepare('SELECT saldo_atual FROM stock_saldo_lotes WHERE id = ?');
        $stmt->execute([(int) $loteId]);
        $saldo = (int) $stmt->fetchColumn();
        $saldoFinal = in_array($tipo, ['entrada', 'ajuste_mais'], true)
            ? $saldo + $quantidadeInteira
            : $saldo - $quantidadeInteira;
        if ($saldoFinal < 0) {
            throw new RuntimeException('O movimento deixaria o saldo do lote negativo.');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO stock_movimentos
             (lote_id, tipo, quantidade, motivo, utilizador_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $loteId, $tipo, $quantidadeInteira,
            $motivo === '' ? null : $motivo, (int) $utilizadorId,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
