<?php
/**
 * Regras de atendimento, disponibilidade e fila.
 */

require_once __DIR__ . '/modulos.php';
require_once __DIR__ . '/criancas.php';

function rotulo_estado_atendimento($estado) {
    $rotulos = [
        'aguardando' => 'Aguardando',
        'em_atendimento' => 'Em atendimento',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
        'sem_vacina' => 'Sem vacina',
    ];
    return $rotulos[$estado] ?? $estado;
}

function classe_estado_atendimento($estado) {
    $classes = [
        'aguardando' => 'etiqueta-aviso',
        'em_atendimento' => 'etiqueta',
        'concluido' => 'etiqueta-sucesso',
        'cancelado' => 'etiqueta-neutra',
        'sem_vacina' => 'etiqueta-erro',
    ];
    return $classes[$estado] ?? 'etiqueta-neutra';
}

/**
 * A consulta devolve apenas um indicador booleano; nunca um saldo numérico.
 */
function listar_disponibilidade_vacinas(PDO $pdo) {
    $stmt = $pdo->query(
        'SELECT v.id, v.nome,
                CASE WHEN COALESCE(vd.saldo_disponivel, 0) > 0 THEN 1 ELSE 0 END AS disponivel
         FROM vacinas v
         LEFT JOIN vacinas_disponiveis vd ON vd.id = v.id
         WHERE v.ativo = TRUE AND v.eliminado_em IS NULL
         ORDER BY v.nome'
    );
    return $stmt->fetchAll();
}

function obter_atendimento(PDO $pdo, $id) {
    $stmt = $pdo->prepare(
        'SELECT a.id AS atendimento_id, a.crianca_id, a.estado, a.recebido_por_id, a.atendido_por_id,
                a.iniciado_em, a.concluido_em, a.motivo, a.observacoes, a.criado_em,
                c.*, e.nome AS encarregado_nome, e.parentesco, e.telefone, e.telefone_alt,
                e.morada, b.nome AS bairro_nome,
                recebido.nome AS recebido_por_nome, atendido.nome AS atendido_por_nome
         FROM atendimentos a
         INNER JOIN criancas c ON c.id = a.crianca_id
         INNER JOIN encarregados e ON e.id = c.encarregado_id
         LEFT JOIN bairros b ON b.id = e.bairro_id
         LEFT JOIN utilizadores recebido ON recebido.id = a.recebido_por_id
         LEFT JOIN utilizadores atendido ON atendido.id = a.atendido_por_id
         WHERE a.id = ? AND c.ativo = TRUE AND c.eliminado_em IS NULL
         LIMIT 1'
    );
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}

function listar_vacinas_atendimento(PDO $pdo, $atendimentoId) {
    $stmt = $pdo->prepare(
        'SELECT v.id, v.nome,
                CASE WHEN COALESCE(vd.saldo_disponivel, 0) > 0 THEN 1 ELSE 0 END AS disponivel
         FROM atendimento_vacinas av
         INNER JOIN vacinas v ON v.id = av.vacina_id
         LEFT JOIN vacinas_disponiveis vd ON vd.id = v.id
         WHERE av.atendimento_id = ?
         ORDER BY v.nome'
    );
    $stmt->execute([(int) $atendimentoId]);
    return $stmt->fetchAll();
}

/**
 * Junta os nomes das vacinas por atendimento sem os separar por vírgulas,
 * porque alguns nomes do catálogo também contêm vírgulas.
 */
function agrupar_vacinas_atendimento(PDO $pdo, array $atendimentos) {
    if (!$atendimentos) {
        return [];
    }
    $ids = [];
    foreach ($atendimentos as $atendimento) {
        $ids[] = (int) $atendimento['id'];
    }
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT av.atendimento_id, v.nome
         FROM atendimento_vacinas av
         INNER JOIN vacinas v ON v.id = av.vacina_id
         WHERE av.atendimento_id IN ({$marcadores})
         ORDER BY av.atendimento_id, v.nome"
    );
    $stmt->execute($ids);
    $vacinasPorAtendimento = [];
    foreach ($stmt->fetchAll() as $linha) {
        $vacinasPorAtendimento[(int) $linha['atendimento_id']][] = $linha['nome'];
    }
    foreach ($atendimentos as $indice => $atendimento) {
        $atendimentos[$indice]['vacinas'] = $vacinasPorAtendimento[(int) $atendimento['id']] ?? [];
    }
    return $atendimentos;
}

function listar_atendimentos_dia(PDO $pdo, $data = null) {
    $data = $data === null ? date('Y-m-d') : $data;
    $stmt = $pdo->prepare(
        'SELECT a.id, a.estado, a.criado_em, a.iniciado_em, a.concluido_em,
                c.nome AS crianca_nome, c.numero_processo
         FROM atendimentos a
         INNER JOIN criancas c ON c.id = a.crianca_id
         WHERE DATE(a.criado_em) = ?
           AND c.ativo = TRUE AND c.eliminado_em IS NULL
         ORDER BY a.criado_em, a.id'
    );
    $stmt->execute([$data]);
    return agrupar_vacinas_atendimento($pdo, $stmt->fetchAll());
}

function listar_fila_atendimento(PDO $pdo) {
    $stmt = $pdo->query(
        "SELECT a.id, a.criado_em, c.nome AS crianca_nome, c.data_nascimento
         FROM atendimentos a
         INNER JOIN criancas c ON c.id = a.crianca_id
         WHERE a.estado = 'aguardando' AND c.ativo = TRUE AND c.eliminado_em IS NULL
         ORDER BY a.criado_em, a.id"
    );
    return agrupar_vacinas_atendimento($pdo, $stmt->fetchAll());
}

function dados_fila_json(PDO $pdo) {
    $itens = [];
    foreach (listar_fila_atendimento($pdo) as $linha) {
        $partes = preg_split('/\s+/', trim($linha['crianca_nome']));
        $itens[] = [
            'id' => (int) $linha['id'],
            'primeiro_nome' => $partes[0] ?? '',
            'idade' => idade_por_extenso_curta($linha['data_nascimento']),
            'vacinas' => $linha['vacinas'],
            'chegada' => date('H:i', strtotime($linha['criado_em'])),
        ];
    }
    return $itens;
}

function criar_atendimento(PDO $pdo, $criancaId, array $vacinasIds, $motivo, $recebidoPorId) {
    $vacinasIds = array_values(array_unique(array_filter(array_map('intval', $vacinasIds))));
    if (!$vacinasIds) {
        throw new RuntimeException('Selecione pelo menos uma vacina pretendida.');
    }
    $motivo = trim($motivo);
    if (!texto_dentro_limite($motivo, 2000)) {
        throw new RuntimeException('O motivo não pode exceder 2000 caracteres.');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id FROM criancas WHERE id = ? AND ativo = TRUE AND eliminado_em IS NULL FOR UPDATE');
        $stmt->execute([(int) $criancaId]);
        if (!$stmt->fetch()) {
            throw new RuntimeException('A criança selecionada não está ativa.');
        }
        $stmt = $pdo->prepare("SELECT id FROM atendimentos WHERE crianca_id = ? AND estado IN ('aguardando', 'em_atendimento') FOR UPDATE");
        $stmt->execute([(int) $criancaId]);
        if ($stmt->fetch()) {
            throw new RuntimeException('Esta criança já tem um atendimento ativo.');
        }
        $marcadores = implode(',', array_fill(0, count($vacinasIds), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM vacinas WHERE id IN ({$marcadores}) AND ativo = TRUE AND eliminado_em IS NULL");
        $stmt->execute($vacinasIds);
        if ((int) $stmt->fetch()['total'] !== count($vacinasIds)) {
            throw new RuntimeException('Uma das vacinas selecionadas não está ativa.');
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM vacinas_disponiveis WHERE id IN ({$marcadores}) AND saldo_disponivel > 0");
        $stmt->execute($vacinasIds);
        $disponiveis = (int) $stmt->fetch()['total'];
        $estado = $disponiveis > 0 ? 'aguardando' : 'sem_vacina';
        if ($estado === 'sem_vacina' && $motivo === '') {
            throw new RuntimeException('Como não há vacina disponível, indique o motivo do registo sem vacina.');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO atendimentos (crianca_id, estado, recebido_por_id, motivo)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([(int) $criancaId, $estado, (int) $recebidoPorId, $estado === 'sem_vacina' ? $motivo : null]);
        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO atendimento_vacinas (atendimento_id, vacina_id) VALUES (?, ?)');
        foreach ($vacinasIds as $vacinaId) {
            $stmt->execute([$id, $vacinaId]);
        }
        registrar_auditoria($pdo, 'atendimentos', $id, 'criar', 'estado', null, $estado, $recebidoPorId);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function transicionar_atendimento(PDO $pdo, $id, $transicao, $motivo, $utilizadorId, $perfil) {
    $permissoes = [
        'iniciar' => ['perfil' => 'enfermeiro', 'estado' => 'em_atendimento'],
        'cancelado' => ['perfil' => 'recepcao', 'estado' => 'cancelado'],
        'sem_vacina' => ['perfil' => 'recepcao', 'estado' => 'sem_vacina'],
    ];
    if (!isset($permissoes[$transicao]) || $perfil !== $permissoes[$transicao]['perfil']) {
        throw new RuntimeException('O seu perfil não pode realizar esta transição.');
    }
    $motivo = trim($motivo);
    if ($transicao !== 'iniciar' && $motivo === '') {
        throw new RuntimeException('O motivo é obrigatório.');
    }
    if (!texto_dentro_limite($motivo, 2000)) {
        throw new RuntimeException('O motivo não pode exceder 2000 caracteres.');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT a.estado FROM atendimentos a
             INNER JOIN criancas c ON c.id = a.crianca_id
             WHERE a.id = ? AND c.ativo = TRUE AND c.eliminado_em IS NULL FOR UPDATE'
        );
        $stmt->execute([(int) $id]);
        $atendimento = $stmt->fetch();
        if (!$atendimento || $atendimento['estado'] !== 'aguardando') {
            throw new RuntimeException('Só um atendimento aguardando de uma criança ativa pode sofrer esta transição.');
        }
        $novoEstado = $permissoes[$transicao]['estado'];
        if ($transicao === 'iniciar') {
            $stmt = $pdo->prepare("UPDATE atendimentos SET estado = 'em_atendimento', atendido_por_id = ?, iniciado_em = NOW() WHERE id = ? AND estado = 'aguardando'");
            $stmt->execute([(int) $utilizadorId, (int) $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE atendimentos SET estado = ?, motivo = ? WHERE id = ? AND estado = 'aguardando'");
            $stmt->execute([$novoEstado, $motivo, (int) $id]);
        }
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('O estado do atendimento mudou. Atualize a ficha.');
        }
        registrar_auditoria($pdo, 'atendimentos', $id, $transicao, 'estado', 'aguardando', $novoEstado, $utilizadorId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
