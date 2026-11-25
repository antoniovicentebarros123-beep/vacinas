<?php
/**
 * Regras de negócio da gestão de funcionários.
 */

require_once __DIR__ . '/modulos.php';

function perfis_funcionario() {
    return ['recepcao', 'enfermeiro', 'responsavel_stock', 'administrador'];
}

function gerar_password_temporaria() {
    $grupos = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnopqrstuvwxyz',
        '23456789',
        '!@#$%&*?',
    ];
    $alfabeto = implode('', $grupos);
    $caracteres = [];
    foreach ($grupos as $grupo) {
        $caracteres[] = $grupo[random_int(0, strlen($grupo) - 1)];
    }
    while (count($caracteres) < 16) {
        $caracteres[] = $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }
    for ($i = count($caracteres) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        $troca = $caracteres[$i];
        $caracteres[$i] = $caracteres[$j];
        $caracteres[$j] = $troca;
    }
    return implode('', $caracteres);
}

function obter_funcionario(PDO $pdo, $id) {
    $stmt = $pdo->prepare(
        'SELECT id, nome, utilizador, perfil, ativo, deve_mudar_password,
                tentativas_falhadas, bloqueado_ate, ultimo_login, criado_em, atualizado_em
         FROM utilizadores WHERE id = ? LIMIT 1'
    );
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
}

function listar_funcionarios(PDO $pdo, $termo = '', $pagina = 1, $porPagina = 15) {
    $termo = trim($termo);
    $porPagina = max(1, min(50, (int) $porPagina));
    $like = '%' . $termo . '%';
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total FROM utilizadores
         WHERE (? = "" OR nome LIKE ? OR utilizador LIKE ? OR perfil LIKE ?)'
    );
    $stmt->execute([$termo, $like, $like, $like]);
    $total = (int) $stmt->fetch()['total'];
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina = max(1, min($paginas, (int) $pagina));
    $offset = ($pagina - 1) * $porPagina;
    $stmt = $pdo->prepare(
        'SELECT id, nome, utilizador, perfil, ativo, deve_mudar_password,
                ultimo_login, criado_em
         FROM utilizadores
         WHERE (? = "" OR nome LIKE ? OR utilizador LIKE ? OR perfil LIKE ?)
         ORDER BY ativo DESC, nome ASC, id ASC
         LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, $termo, PDO::PARAM_STR);
    $stmt->bindValue(2, $like, PDO::PARAM_STR);
    $stmt->bindValue(3, $like, PDO::PARAM_STR);
    $stmt->bindValue(4, $like, PDO::PARAM_STR);
    $stmt->bindValue(5, $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(6, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return [
        'itens' => $stmt->fetchAll(),
        'total' => $total,
        'pagina' => $pagina,
        'paginas' => $paginas,
    ];
}

function validar_funcionario(PDO $pdo, array $entrada, $id = 0) {
    $dados = [
        'nome' => is_string($entrada['nome'] ?? null) ? trim($entrada['nome']) : '',
        'utilizador' => is_string($entrada['utilizador'] ?? null) ? trim($entrada['utilizador']) : '',
        'perfil' => is_string($entrada['perfil'] ?? null) ? $entrada['perfil'] : '',
    ];
    $erros = [];
    if (mb_strlen($dados['nome']) < 3 || mb_strlen($dados['nome']) > 100) {
        $erros[] = 'Indique o nome completo, entre 3 e 100 caracteres.';
    }
    if (preg_match('/^[A-Za-z0-9._-]{3,50}$/', $dados['utilizador']) !== 1) {
        $erros[] = 'O utilizador deve ter 3 a 50 letras, números, pontos, hífenes ou sublinhados.';
    }
    if (!in_array($dados['perfil'], perfis_funcionario(), true)) {
        $erros[] = 'Selecione um perfil válido.';
    }
    if (!$erros) {
        $stmt = $pdo->prepare('SELECT id FROM utilizadores WHERE utilizador = ? AND id <> ? LIMIT 1');
        $stmt->execute([$dados['utilizador'], (int) $id]);
        if ($stmt->fetch()) {
            $erros[] = 'Já existe um funcionário com esse nome de utilizador.';
        }
    }
    return [$dados, $erros];
}

function criar_funcionario(PDO $pdo, array $dados, $responsavelId) {
    $temporaria = gerar_password_temporaria();
    $hash = password_hash($temporaria, PASSWORD_ARGON2ID);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO utilizadores
             (nome, utilizador, password_hash, perfil, ativo, deve_mudar_password, criado_por_id)
             VALUES (?, ?, ?, ?, TRUE, TRUE, ?)'
        );
        $stmt->execute([$dados['nome'], $dados['utilizador'], $hash, $dados['perfil'], (int) $responsavelId]);
        $id = (int) $pdo->lastInsertId();
        registrar_auditoria($pdo, 'utilizadores', $id, 'criar', 'registo', null, $dados['utilizador'], $responsavelId);
        $stmt = $pdo->prepare(
            "INSERT INTO auditoria (entidade, entidade_id, acao, campo, valor_anterior, valor_novo, utilizador_id)
             VALUES ('utilizadores', ?, 'criar', 'perfil', NULL, ?, ?)"
        );
        $stmt->execute([$id, $dados['perfil'], $responsavelId]);
        $pdo->commit();
        return ['id' => $id, 'temporaria' => $temporaria];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function atualizar_funcionario(PDO $pdo, $id, array $dados, $responsavelId) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM utilizadores WHERE id = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $antes = $stmt->fetch();
        if (!$antes) {
            throw new RuntimeException('Funcionário não encontrado.');
        }
        if ((int) $antes['ativo'] === 1 && $antes['perfil'] === 'administrador' && $dados['perfil'] !== 'administrador') {
            $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM utilizadores WHERE perfil = ? AND ativo = TRUE');
            $stmt->execute(['administrador']);
            if ((int) $stmt->fetch()['total'] <= 1) {
                throw new RuntimeException('O sistema deve manter pelo menos um administrador ativo.');
            }
        }
        if ((int) $id === (int) $responsavelId && $dados['perfil'] !== 'administrador') {
            throw new RuntimeException('Não pode retirar o perfil de administrador à própria conta.');
        }
        $stmt = $pdo->prepare('UPDATE utilizadores SET nome = ?, utilizador = ?, perfil = ? WHERE id = ?');
        $stmt->execute([$dados['nome'], $dados['utilizador'], $dados['perfil'], (int) $id]);
        auditar_alteracoes($pdo, 'utilizadores', $id, $antes, $dados, ['nome', 'utilizador', 'perfil'], $responsavelId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function alterar_estado_funcionario(PDO $pdo, $id, $ativo, $responsavelId) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, perfil, ativo FROM utilizadores WHERE id = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $funcionario = $stmt->fetch();
        if (!$funcionario) {
            throw new RuntimeException('Funcionário não encontrado.');
        }
        if ((int) $id === (int) $responsavelId && (int) $ativo === 0) {
            throw new RuntimeException('Não pode desativar a própria conta.');
        }
        if ((int) $funcionario['ativo'] === 1 && $funcionario['perfil'] === 'administrador' && (int) $ativo === 0) {
            $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM utilizadores WHERE perfil = ? AND ativo = TRUE');
            $stmt->execute(['administrador']);
            if ((int) $stmt->fetch()['total'] <= 1) {
                throw new RuntimeException('O sistema deve manter pelo menos um administrador ativo.');
            }
        }
        $stmt = $pdo->prepare(
            'UPDATE utilizadores SET ativo = ?, tentativas_falhadas = 0, bloqueado_ate = NULL WHERE id = ?'
        );
        $stmt->execute([(int) $ativo, (int) $id]);
        registrar_auditoria(
            $pdo,
            'utilizadores',
            $id,
            (int) $ativo === 1 ? 'reativar' : 'desativar',
            'ativo',
            (int) $funcionario['ativo'],
            (int) $ativo,
            $responsavelId
        );
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function repor_password_funcionario(PDO $pdo, $id, $responsavelId) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, ativo, deve_mudar_password FROM utilizadores WHERE id = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $funcionario = $stmt->fetch();
        if (!$funcionario || (int) $funcionario['ativo'] !== 1) {
            throw new RuntimeException('Só é possível repor a palavra-passe de uma conta ativa.');
        }
        $temporaria = gerar_password_temporaria();
        $hash = password_hash($temporaria, PASSWORD_ARGON2ID);
        $stmt = $pdo->prepare(
            'UPDATE utilizadores SET password_hash = ?, deve_mudar_password = TRUE,
                    tentativas_falhadas = 0, bloqueado_ate = NULL WHERE id = ?'
        );
        $stmt->execute([$hash, (int) $id]);
        registrar_auditoria(
            $pdo,
            'utilizadores',
            $id,
            'repor_password',
            'deve_mudar_password',
            (int) $funcionario['deve_mudar_password'],
            1,
            $responsavelId
        );
        $pdo->commit();
        return $temporaria;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
