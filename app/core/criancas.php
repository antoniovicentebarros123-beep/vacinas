<?php
/**
 * Regras de negócio de crianças e encarregados.
 */

require_once __DIR__ . '/modulos.php';

function obter_crianca(PDO $pdo, $id) {
    $stmt = $pdo->prepare(
        'SELECT c.*, e.nome AS encarregado_nome, e.parentesco, e.telefone, e.telefone_alt,
                e.morada, b.nome AS bairro_nome
         FROM criancas c
         INNER JOIN encarregados e ON e.id = c.encarregado_id
         LEFT JOIN bairros b ON b.id = e.bairro_id
         WHERE c.id = ?
         LIMIT 1'
    );
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}

function pesquisar_criancas(PDO $pdo, $termo, $pagina = 1, $porPagina = 15) {
    $termo = trim((string) $termo);
    $pagina = max(1, (int) $pagina);
    $porPagina = max(1, min(50, (int) $porPagina));
    $where = 'c.ativo = 1 AND c.eliminado_em IS NULL';
    $params = [];

    if ($termo !== '') {
        $like = '%' . $termo . '%';
        $where .= ' AND (c.nome LIKE ? OR c.numero_processo LIKE ? OR c.numero_cartao LIKE ?';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $telefone = normalizar_telefone($termo);
        if ($telefone !== '') {
            $where .= ' OR e.telefone LIKE ? OR e.telefone_alt LIKE ?';
            $params[] = '%' . $telefone . '%';
            $params[] = '%' . $telefone . '%';
        }
        $where .= ')';
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM criancas c INNER JOIN encarregados e ON e.id = c.encarregado_id WHERE {$where}");
    $stmt->execute($params);
    $total = (int) $stmt->fetch()['total'];
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina = min($pagina, $paginas);
    $offset = ($pagina - 1) * $porPagina;

    $paramsInt = $params;
    $paramsInt[] = $porPagina;
    $paramsInt[] = $offset;
    $stmt = $pdo->prepare(
        "SELECT c.id, c.numero_processo, c.numero_cartao, c.nome, c.data_nascimento,
                c.data_nascimento_aproximada, c.sexo, e.nome AS encarregado_nome, e.telefone
         FROM criancas c
         INNER JOIN encarregados e ON e.id = c.encarregado_id
         WHERE {$where}
         ORDER BY c.nome, c.data_nascimento
         LIMIT ? OFFSET ?"
    );
    foreach ($paramsInt as $indice => $valor) {
        $stmt->bindValue($indice + 1, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return [
        'total' => $total,
        'pagina' => $pagina,
        'paginas' => $paginas,
        'registos' => $stmt->fetchAll(),
    ];
}

function validar_dados_crianca(array $dados) {
    $erros = [];
    $nome = is_string($dados['nome'] ?? null) ? trim($dados['nome']) : '';
    $cartao = is_string($dados['numero_cartao'] ?? null) ? trim($dados['numero_cartao']) : '';
    $nascimento = is_string($dados['data_nascimento'] ?? null) ? $dados['data_nascimento'] : '';
    $sexo = is_string($dados['sexo'] ?? null) ? $dados['sexo'] : '';
    $encarregadoId = is_scalar($dados['encarregado_id'] ?? null) ? (int) $dados['encarregado_id'] : 0;
    $observacoes = is_string($dados['observacoes'] ?? null) ? trim($dados['observacoes']) : '';

    if ($nome === '' || !texto_dentro_limite($nome, 100)) $erros[] = 'Informe o nome da criança (até 100 caracteres).';
    if (strlen($cartao) > 50) $erros[] = 'O número do cartão não pode exceder 50 caracteres.';
    if (!data_iso_valida($nascimento)) {
        $erros[] = 'Informe uma data de nascimento válida.';
    } elseif ($nascimento > date('Y-m-d')) {
        $erros[] = 'A data de nascimento não pode estar no futuro.';
    }
    if (!in_array($sexo, ['M', 'F'], true)) $erros[] = 'Selecione o sexo da criança.';
    if ($encarregadoId <= 0) $erros[] = 'Selecione o encarregado.';
    if (!texto_dentro_limite($observacoes, 2000)) $erros[] = 'As observações não podem exceder 2000 caracteres.';

    return [
        'erros' => $erros,
        'dados' => [
            'numero_cartao' => $cartao === '' ? null : $cartao,
            'nome' => $nome,
            'data_nascimento' => $nascimento,
            'data_nascimento_aproximada' => !empty($dados['data_nascimento_aproximada']),
            'sexo' => $sexo,
            'encarregado_id' => $encarregadoId,
            'observacoes' => $observacoes === '' ? null : $observacoes,
        ],
    ];
}

function encarregado_ativo_valido(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT id FROM encarregados WHERE id = ? AND ativo = 1 AND eliminado_em IS NULL LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() !== false;
}

function encontrar_duplicados_crianca(PDO $pdo, $nome, $nascimento, $ignorarId = 0) {
    $stmt = $pdo->prepare(
        'SELECT id, numero_processo, nome, data_nascimento
         FROM criancas
         WHERE nome = ? AND data_nascimento = ? AND ativo = 1 AND eliminado_em IS NULL AND id <> ?
         ORDER BY id
         LIMIT 10'
    );
    $stmt->execute([$nome, $nascimento, (int) $ignorarId]);
    return $stmt->fetchAll();
}

function criar_crianca(PDO $pdo, array $dados, $utilizadorId) {
    for ($tentativa = 0; $tentativa < 5; $tentativa++) {
        $pdo->beginTransaction();
        try {
            $numero = gerar_numero_processo($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO criancas
                 (numero_processo, numero_cartao, nome, data_nascimento, data_nascimento_aproximada,
                  sexo, encarregado_id, observacoes, ativo, criado_por_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?)'
            );
            $stmt->execute([
                $numero,
                $dados['numero_cartao'],
                $dados['nome'],
                $dados['data_nascimento'],
                $dados['data_nascimento_aproximada'],
                $dados['sexo'],
                $dados['encarregado_id'],
                $dados['observacoes'],
                (int) $utilizadorId,
            ]);
            $id = (int) $pdo->lastInsertId();
            registrar_auditoria($pdo, 'criancas', $id, 'criar', 'registo', null, $numero, $utilizadorId);
            $pdo->commit();
            return ['id' => $id, 'numero_processo' => $numero];
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000' && strpos($e->getMessage(), 'uk_numero_processo') !== false && $tentativa < 4) {
                continue;
            }
            throw $e;
        }
    }
    throw new RuntimeException('Não foi possível gerar um número de processo único.');
}

function atualizar_crianca(PDO $pdo, $id, array $dados, $utilizadorId) {
    $antes = obter_crianca($pdo, $id);
    if (!$antes) throw new RuntimeException('Criança não encontrada.');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE criancas
             SET numero_cartao = ?, nome = ?, data_nascimento = ?, data_nascimento_aproximada = ?,
                 sexo = ?, encarregado_id = ?, observacoes = ?
             WHERE id = ? AND ativo = 1 AND eliminado_em IS NULL'
        );
        $stmt->execute([
            $dados['numero_cartao'], $dados['nome'], $dados['data_nascimento'],
            $dados['data_nascimento_aproximada'], $dados['sexo'], $dados['encarregado_id'],
            $dados['observacoes'], (int) $id,
        ]);
        auditar_alteracoes($pdo, 'criancas', $id, $antes, $dados, [
            'numero_cartao', 'nome', 'data_nascimento', 'data_nascimento_aproximada',
            'sexo', 'encarregado_id', 'observacoes',
        ], $utilizadorId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function eliminar_crianca_logica(PDO $pdo, $id, $utilizadorId) {
    $stmt = $pdo->prepare('UPDATE criancas SET ativo = 0, eliminado_em = NOW() WHERE id = ? AND ativo = 1 AND eliminado_em IS NULL');
    $stmt->execute([(int) $id]);
    if ($stmt->rowCount() === 0) throw new RuntimeException('Criança não encontrada ou já eliminada.');
    registrar_auditoria($pdo, 'criancas', $id, 'eliminar', 'eliminado_em', null, date('Y-m-d H:i:s'), $utilizadorId);
}

function listar_bairros(PDO $pdo) {
    $stmt = $pdo->prepare('SELECT id, nome FROM bairros WHERE ativo = 1 AND eliminado_em IS NULL ORDER BY nome');
    $stmt->execute();
    return $stmt->fetchAll();
}

function listar_encarregados(PDO $pdo, $termo = '') {
    $termo = trim((string) $termo);
    $params = [];
    $where = 'e.ativo = 1 AND e.eliminado_em IS NULL';
    if ($termo !== '') {
        $where .= ' AND (e.nome LIKE ? OR e.telefone LIKE ? OR e.telefone_alt LIKE ?';
        $params[] = '%' . $termo . '%';
        $params[] = '%' . normalizar_telefone($termo) . '%';
        $params[] = '%' . normalizar_telefone($termo) . '%';
        $where .= ')';
    }
    $stmt = $pdo->prepare("SELECT e.*, b.nome AS bairro_nome FROM encarregados e LEFT JOIN bairros b ON b.id = e.bairro_id WHERE {$where} ORDER BY e.nome LIMIT 200");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function obter_encarregado(PDO $pdo, $id) {
    $stmt = $pdo->prepare('SELECT e.*, b.nome AS bairro_nome FROM encarregados e LEFT JOIN bairros b ON b.id = e.bairro_id WHERE e.id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}

function validar_dados_encarregado(array $dados) {
    $erros = [];
    $nome = is_string($dados['nome'] ?? null) ? trim($dados['nome']) : '';
    $parentesco = is_string($dados['parentesco'] ?? null) ? trim($dados['parentesco']) : '';
    $telefone = normalizar_telefone(is_string($dados['telefone'] ?? null) ? $dados['telefone'] : '');
    $telefoneAlt = normalizar_telefone(is_string($dados['telefone_alt'] ?? null) ? $dados['telefone_alt'] : '');
    $morada = is_string($dados['morada'] ?? null) ? trim($dados['morada']) : '';
    $bairroId = is_scalar($dados['bairro_id'] ?? null) ? (int) $dados['bairro_id'] : 0;
    $novoBairro = is_string($dados['novo_bairro'] ?? null) ? trim($dados['novo_bairro']) : '';

    if ($nome === '' || !texto_dentro_limite($nome, 100)) $erros[] = 'Informe o nome do encarregado (até 100 caracteres).';
    if ($parentesco === '' || !texto_dentro_limite($parentesco, 50)) $erros[] = 'Informe o parentesco (até 50 caracteres).';
    if (!preg_match('/^\d{7,20}$/', $telefone)) $erros[] = 'O telefone deve ter entre 7 e 20 dígitos.';
    if ($telefoneAlt !== '' && !preg_match('/^\d{7,20}$/', $telefoneAlt)) $erros[] = 'O telefone alternativo deve ter entre 7 e 20 dígitos.';
    if (!texto_dentro_limite($morada, 500)) $erros[] = 'A morada não pode exceder 500 caracteres.';
    if ($bairroId < 0) $erros[] = 'Bairro inválido.';
    if ($novoBairro !== '' && !texto_dentro_limite($novoBairro, 100)) $erros[] = 'O nome do novo bairro não pode exceder 100 caracteres.';
    if ($novoBairro !== '' && $bairroId > 0) $erros[] = 'Escolha um bairro existente ou escreva um novo, não ambos.';

    return [
        'erros' => $erros,
        'dados' => [
            'nome' => $nome, 'parentesco' => $parentesco, 'telefone' => $telefone,
            'telefone_alt' => $telefoneAlt === '' ? null : $telefoneAlt,
            'morada' => $morada === '' ? null : $morada,
            'bairro_id' => $bairroId > 0 ? $bairroId : null,
            'novo_bairro' => $novoBairro,
        ],
    ];
}

function resolver_bairro(PDO $pdo, array $dados, $utilizadorId) {
    if ($dados['novo_bairro'] === '') return $dados['bairro_id'];
    $stmt = $pdo->prepare('SELECT id FROM bairros WHERE nome = ? LIMIT 1');
    $stmt->execute([$dados['novo_bairro']]);
    $existente = $stmt->fetch();
    if ($existente) return (int) $existente['id'];
    $stmt = $pdo->prepare('INSERT INTO bairros (nome, ativo, criado_por_id) VALUES (?, TRUE, ?)');
    $stmt->execute([$dados['novo_bairro'], (int) $utilizadorId]);
    return (int) $pdo->lastInsertId();
}

function gravar_encarregado(PDO $pdo, array $dados, $utilizadorId, $id = 0) {
    $pdo->beginTransaction();
    try {
        $bairroId = resolver_bairro($pdo, $dados, $utilizadorId);
        if ($id > 0) {
            $antes = obter_encarregado($pdo, $id);
            if (!$antes) throw new RuntimeException('Encarregado não encontrado.');
            $stmt = $pdo->prepare(
                'UPDATE encarregados SET nome = ?, parentesco = ?, telefone = ?, telefone_alt = ?, morada = ?, bairro_id = ?
                 WHERE id = ? AND ativo = 1 AND eliminado_em IS NULL'
            );
            $stmt->execute([$dados['nome'], $dados['parentesco'], $dados['telefone'], $dados['telefone_alt'], $dados['morada'], $bairroId, $id]);
            $dados['bairro_id'] = $bairroId;
            auditar_alteracoes($pdo, 'encarregados', $id, $antes, $dados, ['nome', 'parentesco', 'telefone', 'telefone_alt', 'morada', 'bairro_id']);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO encarregados (nome, parentesco, telefone, telefone_alt, morada, bairro_id, ativo, criado_por_id)
                 VALUES (?, ?, ?, ?, ?, ?, TRUE, ?)'
            );
            $stmt->execute([$dados['nome'], $dados['parentesco'], $dados['telefone'], $dados['telefone_alt'], $dados['morada'], $bairroId, $utilizadorId]);
            $id = (int) $pdo->lastInsertId();
            registrar_auditoria($pdo, 'encarregados', $id, 'criar', 'registo', null, 'Encarregado registado');
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function eliminar_encarregado_logica(PDO $pdo, $id, $utilizadorId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM criancas WHERE encarregado_id = ? AND ativo = 1 AND eliminado_em IS NULL');
    $stmt->execute([(int) $id]);
    if ((int) $stmt->fetch()['total'] > 0) throw new RuntimeException('Não é possível eliminar um encarregado com crianças ativas.');
    $stmt = $pdo->prepare('UPDATE encarregados SET ativo = 0, eliminado_em = NOW() WHERE id = ? AND ativo = 1 AND eliminado_em IS NULL');
    $stmt->execute([(int) $id]);
    if ($stmt->rowCount() === 0) throw new RuntimeException('Encarregado não encontrado ou já eliminado.');
    registrar_auditoria($pdo, 'encarregados', $id, 'eliminar', 'eliminado_em', null, date('Y-m-d H:i:s'), $utilizadorId);
}
