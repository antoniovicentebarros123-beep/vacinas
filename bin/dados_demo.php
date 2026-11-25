<?php
/**
 * Gera ou limpa dados fictícios para demonstração local.
 *
 * Utilização:
 *   php bin/dados_demo.php --gerar
 *   php bin/dados_demo.php --limpar
 *
 * O script nunca corre automaticamente. A limpeza é lógica e preserva
 * movimentos, vacinações, anulações e auditoria por serem históricos append-only.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado em linha de comandos.\n");
}

$argumentos = array_slice($argv, 1);
$uso = "Utilização: php bin/dados_demo.php --gerar | --limpar\n";

if (count($argumentos) !== 1 || !in_array($argumentos[0], ['--gerar', '--limpar'], true)) {
    echo $uso;
    exit(count($argumentos) === 0 ? 0 : 2);
}

if (!defined('PASSWORD_ARGON2ID')) {
    echo "Erro: esta instalação de PHP não suporta Argon2id.\n";
    exit(1);
}

require __DIR__ . '/../app/core/helpers.php';
require_once __DIR__ . '/../app/core/modulos.php';
require_once __DIR__ . '/../app/core/criancas.php';
require_once __DIR__ . '/../app/core/stock.php';
require_once __DIR__ . '/../app/core/atendimentos.php';
require_once __DIR__ . '/../app/core/vacinacao.php';
require_once __DIR__ . '/../app/core/faltosos.php';

const DEMO_MARCADOR = '[DEMO]';
const DEMO_FABRICANTE = 'Fabricante Fictício Demo';

function demo_ajuda()
{
    echo "Dados exclusivamente fictícios para ambiente de desenvolvimento.\n";
    echo "Não executar em produção. A limpeza não apaga históricos append-only.\n";
}

function demo_contas()
{
    return [
        'recepcao' => 'demo.recepcao',
        'enfermeiro' => 'demo.enfermeiro',
        'responsavel_stock' => 'demo.stock',
        'administrador' => 'demo.admin',
    ];
}

function demo_marcadores(array $ids)
{
    return implode(',', array_fill(0, count($ids), '?'));
}

function demo_autor_administrativo(PDO $pdo)
{
    $stmt = $pdo->query(
        "SELECT id FROM utilizadores
         WHERE perfil = 'administrador' AND ativo = TRUE
           AND utilizador NOT IN ('demo.recepcao', 'demo.enfermeiro', 'demo.stock', 'demo.admin')
         ORDER BY id LIMIT 1"
    );
    $id = $stmt->fetchColumn();
    if (!$id) {
        throw new RuntimeException('É necessária uma conta administradora não fictícia e ativa. Crie-a com bin/criar_admin.php.');
    }
    return (int) $id;
}

function demo_existem_dados_ativos(PDO $pdo)
{
    $contas = array_values(demo_contas());
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM utilizadores WHERE ativo = TRUE AND utilizador IN (' . demo_marcadores($contas) . ')'
    );
    $stmt->execute($contas);
    if ((int) $stmt->fetchColumn() > 0) {
        return true;
    }

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM criancas
         WHERE ativo = TRUE AND eliminado_em IS NULL
           AND nome LIKE 'Teste Demo %' AND observacoes LIKE '%[DEMO]%'"
    );
    if ((int) $stmt->fetchColumn() > 0) {
        return true;
    }

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM stock_lotes
         WHERE ativo = TRUE AND eliminado_em IS NULL
           AND numero_lote LIKE 'DEMO-%' AND fabricante = '" . DEMO_FABRICANTE . "'"
    );
    return (int) $stmt->fetchColumn() > 0;
}

function demo_registar_auditoria(PDO $pdo, $entidade, $entidadeId, $acao, $campo, $anterior, $novo, $utilizadorId)
{
    registrar_auditoria(
        $pdo,
        $entidade,
        $entidadeId,
        $acao,
        $campo,
        $anterior,
        DEMO_MARCADOR . ' ' . (string) $novo,
        $utilizadorId
    );
}

function demo_criar_ou_reativar_conta(PDO $pdo, $perfil, $utilizador, $password, $autorId)
{
    $nome = [
        'recepcao' => 'Teste Demo Recepcao',
        'enfermeiro' => 'Teste Demo Enfermeiro',
        'responsavel_stock' => 'Teste Demo Stock',
        'administrador' => 'Teste Demo Administrador',
    ][$perfil];
    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $stmt = $pdo->prepare('SELECT id, nome, ativo FROM utilizadores WHERE utilizador = ? LIMIT 1');
    $stmt->execute([$utilizador]);
    $existente = $stmt->fetch();

    if ($existente) {
        if (strpos($existente['nome'], 'Teste Demo ') !== 0) {
            throw new RuntimeException('O nome de utilizador ' . $utilizador . ' já pertence a uma conta não fictícia.');
        }
        $stmt = $pdo->prepare(
            'UPDATE utilizadores
             SET nome = ?, password_hash = ?, perfil = ?, ativo = TRUE, deve_mudar_password = FALSE,
                 tentativas_falhadas = 0, bloqueado_ate = NULL
             WHERE id = ?'
        );
        $stmt->execute([$nome, $hash, $perfil, (int) $existente['id']]);
        demo_registar_auditoria(
            $pdo,
            'utilizadores',
            (int) $existente['id'],
            (int) $existente['ativo'] === 1 ? 'alterar' : 'reativar',
            'conta_demo',
            null,
            $utilizador,
            $autorId
        );
        return (int) $existente['id'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO utilizadores (nome, utilizador, password_hash, perfil, ativo, deve_mudar_password, criado_por_id)
         VALUES (?, ?, ?, ?, TRUE, FALSE, ?)'
    );
    $stmt->execute([$nome, $utilizador, $hash, $perfil, $autorId]);
    $id = (int) $pdo->lastInsertId();
    demo_registar_auditoria($pdo, 'utilizadores', $id, 'criar', 'registo', null, $utilizador, $autorId);
    return $id;
}

function demo_criar_encarregado(PDO $pdo, $numero, $autorId)
{
    $stmt = $pdo->prepare(
        'INSERT INTO encarregados
         (nome, parentesco, telefone, telefone_alt, morada, bairro_id, ativo, criado_por_id)
         VALUES (?, ?, ?, ?, ?, NULL, TRUE, ?)'
    );
    $stmt->execute([
        'Teste Demo Encarregado ' . str_pad((string) $numero, 2, '0', STR_PAD_LEFT),
        $numero % 2 === 0 ? 'Mãe' : 'Pai',
        '9000000' . str_pad((string) $numero, 2, '0', STR_PAD_LEFT),
        '9100000' . str_pad((string) $numero, 2, '0', STR_PAD_LEFT),
        DEMO_MARCADOR . ' Morada fictícia para demonstração.',
        $autorId,
    ]);
    $id = (int) $pdo->lastInsertId();
    demo_registar_auditoria($pdo, 'encarregados', $id, 'criar', 'registo', null, 'Encarregado fictício', $autorId);
    return $id;
}

function demo_criar_crianca(PDO $pdo, $numero, $nascimento, $sexo, $encarregadoId, $autorId)
{
    $dados = [
        'numero_cartao' => 'DEMO-CART-' . str_pad((string) $numero, 2, '0', STR_PAD_LEFT),
        'nome' => 'Teste Demo ' . str_pad((string) $numero, 2, '0', STR_PAD_LEFT),
        'data_nascimento' => $nascimento,
        'data_nascimento_aproximada' => $numero === 3,
        'sexo' => $sexo,
        'encarregado_id' => $encarregadoId,
        'observacoes' => DEMO_MARCADOR . ' Criança fictícia para demonstração do sistema.',
    ];
    $validacao = validar_dados_crianca($dados);
    if ($validacao['erros']) {
        throw new RuntimeException($validacao['erros'][0]);
    }

    $numeroProcesso = gerar_numero_processo($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO criancas
         (numero_processo, numero_cartao, nome, data_nascimento, data_nascimento_aproximada,
          sexo, encarregado_id, observacoes, ativo, criado_por_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?)'
    );
    $stmt->execute([
        $numeroProcesso,
        $dados['numero_cartao'],
        $dados['nome'],
        $dados['data_nascimento'],
        $dados['data_nascimento_aproximada'],
        $dados['sexo'],
        $dados['encarregado_id'],
        $dados['observacoes'],
        $autorId,
    ]);
    $id = (int) $pdo->lastInsertId();
    demo_registar_auditoria($pdo, 'criancas', $id, 'criar', 'registo', null, $numeroProcesso, $autorId);
    return $id;
}

function demo_criar_lote(PDO $pdo, $vacinaId, $numeroLote, $validade, $quantidade, $autorId)
{
    $stmt = $pdo->prepare(
        'SELECT id FROM stock_lotes
         WHERE vacina_id = ? AND numero_lote = ? AND ativo = TRUE AND eliminado_em IS NULL LIMIT 1'
    );
    $stmt->execute([$vacinaId, $numeroLote]);
    if ($stmt->fetch()) {
        throw new RuntimeException('Já existe um lote fictício ativo com o número ' . $numeroLote . '.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO stock_lotes
         (vacina_id, numero_lote, validade, fabricante, quantidade_inicial, ativo, criado_por_id)
         VALUES (?, ?, ?, ?, ?, TRUE, ?)'
    );
    $stmt->execute([$vacinaId, $numeroLote, $validade, DEMO_FABRICANTE, $quantidade, $autorId]);
    $id = (int) $pdo->lastInsertId();
    demo_registar_auditoria($pdo, 'stock_lotes', $id, 'criar', 'registo', null, $numeroLote, $autorId);
    return $id;
}

function demo_movimento(PDO $pdo, $loteId, $tipo, $quantidade, $motivo, $utilizadorId, $vacinacaoId = null)
{
    $stmt = $pdo->prepare(
        'INSERT INTO stock_movimentos (lote_id, tipo, quantidade, motivo, vacinacao_id, utilizador_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$loteId, $tipo, $quantidade, DEMO_MARCADOR . ' ' . $motivo, $vacinacaoId, $utilizadorId]);
    $id = (int) $pdo->lastInsertId();
    demo_registar_auditoria($pdo, 'stock_movimentos', $id, 'criar', 'tipo', null, $tipo, $utilizadorId);
    return $id;
}

function demo_atendimento(PDO $pdo, $criancaId, $vacinaId, $estado, $recebidoPorId, $atendidoPorId, $iniciadoEm, $concluidoEm, $motivo, $criadoEm)
{
    $stmt = $pdo->prepare(
        'INSERT INTO atendimentos
         (crianca_id, estado, recebido_por_id, atendido_por_id, iniciado_em, concluido_em, motivo, observacoes, criado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $criancaId,
        $estado,
        $recebidoPorId,
        $atendidoPorId,
        $iniciadoEm,
        $concluidoEm,
        $motivo,
        DEMO_MARCADOR . ' Atendimento fictício para demonstração.',
        $criadoEm,
    ]);
    $id = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('INSERT INTO atendimento_vacinas (atendimento_id, vacina_id) VALUES (?, ?)');
    $stmt->execute([$id, $vacinaId]);
    demo_registar_auditoria($pdo, 'atendimentos', $id, 'criar', 'estado', null, $estado, $recebidoPorId);
    if ($estado === 'concluido') {
        demo_registar_auditoria($pdo, 'atendimentos', $id, 'concluir', 'estado', 'em_atendimento', 'concluido', $atendidoPorId);
    }
    return $id;
}

function demo_vacinacao(PDO $pdo, array $dados, $utilizadorId)
{
    $stmt = $pdo->prepare(
        'INSERT INTO vacinacoes
         (crianca_id, atendimento_id, vacina_id, calendario_id, numero_dose, data_administracao,
          lote_id, administrado_por_id, administrado_por_nome, local_anatomico, origem, observacoes, reacoes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $dados['crianca_id'],
        $dados['atendimento_id'],
        $dados['vacina_id'],
        $dados['calendario_id'],
        $dados['numero_dose'],
        $dados['data_administracao'],
        $dados['lote_id'],
        $dados['administrado_por_id'],
        $dados['administrado_por_nome'],
        $dados['local_anatomico'],
        $dados['origem'],
        DEMO_MARCADOR . ' Vacinação fictícia para demonstração.',
        $dados['reacoes'],
    ]);
    $id = (int) $pdo->lastInsertId();
    demo_registar_auditoria(
        $pdo,
        'vacinacoes',
        $id,
        $dados['origem'] === 'externa' ? 'criar_externo' : 'criar',
        'registo',
        null,
        (string) $id,
        $utilizadorId
    );
    return $id;
}

function demo_contacto(PDO $pdo, $criancaId, $data, $canal, $resultado, $utilizadorId)
{
    $dados = [
        'data_contacto' => $data,
        'canal' => $canal,
        'resultado' => $resultado,
        'notas' => DEMO_MARCADOR . ' Contacto fictício; nenhuma mensagem foi enviada.',
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO contactos_faltosos (crianca_id, data_contacto, canal, resultado, notas, utilizador_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$criancaId, $data, $canal, $resultado, $dados['notas'], $utilizadorId]);
    $id = (int) $pdo->lastInsertId();
    demo_registar_auditoria($pdo, 'contactos_faltosos', $id, 'criar', 'registo', null, 'Contacto fictício', $utilizadorId);
    return $id;
}

function demo_gerar(PDO $pdo)
{
    if (demo_existem_dados_ativos($pdo)) {
        throw new RuntimeException('Já existem dados fictícios ativos. Execute --limpar antes de gerar novamente.');
    }

    $autorId = demo_autor_administrativo($pdo);
    $passwords = [];
    $contas = demo_contas();
    $hoje = new DateTimeImmutable((string) $pdo->query('SELECT CURDATE()')->fetchColumn());
    $agora = new DateTimeImmutable((string) $pdo->query('SELECT NOW()')->fetchColumn());
    $horarioHoje = function (string $intervalo) use ($agora, $hoje) {
        $horario = $agora->modify($intervalo);
        return $horario < $hoje ? $hoje : $horario;
    };
    $pdo->beginTransaction();

    try {
        $idsUtilizadores = [];
        foreach ($contas as $perfil => $utilizador) {
            $password = bin2hex(random_bytes(12));
            $passwords[$utilizador] = $password;
            $idsUtilizadores[$perfil] = demo_criar_ou_reativar_conta($pdo, $perfil, $utilizador, $password, $autorId);
        }

        $nascimentos = [
            $hoje->modify('-1 day')->format('Y-m-d'),
            $hoje->modify('-2 months')->modify('-5 days')->format('Y-m-d'),
            $hoje->modify('-4 months')->modify('-10 days')->format('Y-m-d'),
            $hoje->modify('-9 months')->modify('-15 days')->format('Y-m-d'),
            $hoje->modify('-17 months')->format('Y-m-d'),
            $hoje->modify('-5 years')->format('Y-m-d'),
        ];
        $criancas = [];
        for ($numero = 1; $numero <= 6; $numero++) {
            $encarregadoId = demo_criar_encarregado($pdo, $numero, $autorId);
            $criancas[] = demo_criar_crianca(
                $pdo,
                $numero,
                $nascimentos[$numero - 1],
                $numero % 2 === 0 ? 'F' : 'M',
                $encarregadoId,
                $autorId
            );
        }

        $loteBcg = demo_criar_lote($pdo, 1, 'DEMO-BCG-01', $hoje->modify('+90 days')->format('Y-m-d'), 10, $idsUtilizadores['responsavel_stock']);
        $loteBcgVencido = demo_criar_lote($pdo, 1, 'DEMO-BCG-VENCIDO', $hoje->modify('-10 days')->format('Y-m-d'), 5, $idsUtilizadores['responsavel_stock']);
        $lotePolio = demo_criar_lote($pdo, 2, 'DEMO-POLIO-01', $hoje->modify('+20 days')->format('Y-m-d'), 5, $idsUtilizadores['responsavel_stock']);
        $lotePenta = demo_criar_lote($pdo, 4, 'DEMO-PENTA-01', $hoje->modify('+180 days')->format('Y-m-d'), 20, $idsUtilizadores['responsavel_stock']);

        demo_movimento($pdo, $loteBcg, 'entrada', 10, 'Entrada inicial fictícia.', $idsUtilizadores['responsavel_stock']);
        demo_movimento($pdo, $loteBcgVencido, 'entrada', 5, 'Entrada inicial fictícia de lote vencido.', $idsUtilizadores['responsavel_stock']);
        demo_movimento($pdo, $lotePolio, 'entrada', 5, 'Entrada inicial fictícia.', $idsUtilizadores['responsavel_stock']);
        demo_movimento($pdo, $lotePolio, 'perda', 1, 'Perda fictícia para demonstração.', $idsUtilizadores['responsavel_stock']);
        demo_movimento($pdo, $lotePolio, 'ajuste_menos', 1, 'Ajuste fictício após contagem.', $idsUtilizadores['responsavel_stock']);
        demo_movimento($pdo, $lotePenta, 'entrada', 20, 'Entrada inicial fictícia.', $idsUtilizadores['responsavel_stock']);

        $recebidoPor = $idsUtilizadores['recepcao'];
        $enfermeiro = $idsUtilizadores['enfermeiro'];
        $admin = $idsUtilizadores['administrador'];

        $atendimentoConcluido = demo_atendimento(
            $pdo,
            $criancas[0],
            1,
            'concluido',
            $recebidoPor,
            $enfermeiro,
            $horarioHoje('-20 minutes')->format('Y-m-d H:i:s'),
            $horarioHoje('-5 minutes')->format('Y-m-d H:i:s'),
            null,
            $horarioHoje('-25 minutes')->format('Y-m-d H:i:s')
        );
        demo_atendimento(
            $pdo,
            $criancas[1],
            2,
            'aguardando',
            $recebidoPor,
            null,
            null,
            null,
            null,
            $horarioHoje('-3 minutes')->format('Y-m-d H:i:s')
        );
        demo_atendimento(
            $pdo,
            $criancas[2],
            4,
            'em_atendimento',
            $recebidoPor,
            $enfermeiro,
            $horarioHoje('-10 minutes')->format('Y-m-d H:i:s'),
            null,
            null,
            $horarioHoje('-15 minutes')->format('Y-m-d H:i:s')
        );
        demo_atendimento(
            $pdo,
            $criancas[3],
            10,
            'sem_vacina',
            $recebidoPor,
            null,
            null,
            null,
            'Vacina sem stock fictícia para demonstração do relatório.',
            $horarioHoje('-40 minutes')->format('Y-m-d H:i:s')
        );
        demo_atendimento(
            $pdo,
            $criancas[4],
            15,
            'cancelado',
            $recebidoPor,
            null,
            null,
            null,
            'Cancelamento fictício para demonstração.',
            $horarioHoje('-1 hour')->format('Y-m-d H:i:s')
        );

        $vacinacaoPosto = demo_vacinacao($pdo, [
            'crianca_id' => $criancas[0],
            'atendimento_id' => $atendimentoConcluido,
            'vacina_id' => 1,
            'calendario_id' => 1,
            'numero_dose' => 1,
            'data_administracao' => $hoje->format('Y-m-d'),
            'lote_id' => $loteBcg,
            'administrado_por_id' => $enfermeiro,
            'administrado_por_nome' => null,
            'local_anatomico' => 'Local fictício de demonstração',
            'origem' => 'posto',
            'reacoes' => 'Sem reações registadas (dados fictícios).',
        ], $enfermeiro);
        demo_movimento($pdo, $loteBcg, 'saida_vacinacao', 1, 'Saída por vacinação fictícia.', $enfermeiro, $vacinacaoPosto);

        demo_vacinacao($pdo, [
            'crianca_id' => $criancas[5],
            'atendimento_id' => null,
            'vacina_id' => 2,
            'calendario_id' => 17,
            'numero_dose' => 3,
            'data_administracao' => $hoje->modify('-20 days')->format('Y-m-d'),
            'lote_id' => null,
            'administrado_por_id' => $enfermeiro,
            'administrado_por_nome' => 'Profissional Fictício Demo',
            'local_anatomico' => 'Local externo fictício',
            'origem' => 'externa',
            'reacoes' => 'Não informada (dados fictícios).',
        ], $enfermeiro);

        $vacinacaoAnulada = demo_vacinacao($pdo, [
            'crianca_id' => $criancas[5],
            'atendimento_id' => null,
            'vacina_id' => 15,
            'calendario_id' => 27,
            'numero_dose' => 1,
            'data_administracao' => $hoje->modify('-30 days')->format('Y-m-d'),
            'lote_id' => null,
            'administrado_por_id' => $enfermeiro,
            'administrado_por_nome' => 'Profissional Fictício Demo',
            'local_anatomico' => 'Local externo fictício',
            'origem' => 'externa',
            'reacoes' => 'Não informada (dados fictícios).',
        ], $enfermeiro);
        $stmt = $pdo->prepare('INSERT INTO vacinacao_anulacoes (vacinacao_id, motivo, utilizador_id) VALUES (?, ?, ?)');
        $stmt->execute([$vacinacaoAnulada, DEMO_MARCADOR . ' Anulação fictícia para demonstração.', $admin]);
        demo_registar_auditoria($pdo, 'vacinacoes', $vacinacaoAnulada, 'anular', 'anulacao', null, 'registada', $admin);

        demo_contacto($pdo, $criancas[3], $hoje->modify('-1 day')->format('Y-m-d'), 'telefone', 'Sem resposta (demo)', $recebidoPor);
        demo_contacto($pdo, $criancas[3], $hoje->format('Y-m-d'), 'whatsapp', 'Encarregado contactado (demo)', $recebidoPor);
        demo_contacto($pdo, $criancas[5], $hoje->modify('-2 days')->format('Y-m-d'), 'sms', 'Mensagem fictícia registada', $recebidoPor);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    echo "Dados fictícios gerados com sucesso.\n";
    echo "Crianças: 6; encarregados: 6; lotes: 4; atendimentos: 5; vacinações: 3; contactos: 3.\n";
    echo "Contas fictícias (palavras-passe mostradas apenas agora):\n";
    foreach ($passwords as $utilizador => $password) {
        echo '  ' . $utilizador . ': ' . $password . "\n";
    }
    echo "Guarde estas credenciais apenas no ambiente de demonstração.\n";
}

function demo_limpar(PDO $pdo)
{
    $autorId = demo_autor_administrativo($pdo);
    $contas = array_values(demo_contas());
    $stmt = $pdo->prepare(
        'SELECT id, utilizador FROM utilizadores
         WHERE utilizador IN (' . demo_marcadores($contas) . ") AND nome LIKE 'Teste Demo %'"
    );
    $stmt->execute($contas);
    $utilizadoresDemo = $stmt->fetchAll();

    $stmt = $pdo->query(
        "SELECT id FROM criancas WHERE nome LIKE 'Teste Demo %' AND observacoes LIKE '%[DEMO]%'"
    );
    $criancas = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    $vacinacoes = [];
    $contactos = [];
    if ($criancas) {
        $stmt = $pdo->prepare(
            'SELECT id FROM vacinacoes WHERE crianca_id IN (' . demo_marcadores($criancas) . ") AND observacoes LIKE '%[DEMO]%'"
        );
        $stmt->execute($criancas);
        $vacinacoes = array_map('intval', array_column($stmt->fetchAll(), 'id'));
        $stmt = $pdo->prepare(
            'SELECT id FROM contactos_faltosos WHERE crianca_id IN (' . demo_marcadores($criancas) . ')
             AND eliminado_em IS NULL AND notas LIKE ?'
        );
        $stmt->execute(array_merge($criancas, ['%[DEMO]%']));
        $contactos = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    $stmt = $pdo->query(
        "SELECT id FROM stock_lotes
         WHERE numero_lote LIKE 'DEMO-%' AND fabricante = '" . DEMO_FABRICANTE . "'"
    );
    $lotes = array_map('intval', array_column($stmt->fetchAll(), 'id'));

    $pdo->beginTransaction();
    $contagens = ['vacinacoes_anuladas' => 0, 'contactos' => 0, 'criancas' => 0, 'encarregados' => 0, 'lotes' => 0, 'utilizadores' => 0];
    try {
        foreach ($vacinacoes as $vacinacaoId) {
            $stmt = $pdo->prepare('SELECT id FROM vacinacao_anulacoes WHERE vacinacao_id = ? LIMIT 1');
            $stmt->execute([$vacinacaoId]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare('INSERT INTO vacinacao_anulacoes (vacinacao_id, motivo, utilizador_id) VALUES (?, ?, ?)');
                $stmt->execute([$vacinacaoId, DEMO_MARCADOR . ' Limpeza lógica dos dados de demonstração.', $autorId]);
                demo_registar_auditoria($pdo, 'vacinacoes', $vacinacaoId, 'anular', 'anulacao', null, 'limpeza demo', $autorId);
                $contagens['vacinacoes_anuladas']++;
            }
        }

        foreach ($contactos as $contactoId) {
            $stmt = $pdo->prepare('UPDATE contactos_faltosos SET eliminado_em = NOW() WHERE id = ? AND eliminado_em IS NULL');
            $stmt->execute([$contactoId]);
            if ($stmt->rowCount() > 0) {
                demo_registar_auditoria($pdo, 'contactos_faltosos', $contactoId, 'eliminar', 'eliminado_em', null, 'limpeza demo', $autorId);
                $contagens['contactos']++;
            }
        }

        foreach ($criancas as $criancaId) {
            $stmt = $pdo->prepare('UPDATE criancas SET ativo = FALSE, eliminado_em = NOW() WHERE id = ? AND ativo = TRUE AND eliminado_em IS NULL');
            $stmt->execute([$criancaId]);
            if ($stmt->rowCount() > 0) {
                demo_registar_auditoria($pdo, 'criancas', $criancaId, 'eliminar', 'eliminado_em', null, 'limpeza demo', $autorId);
                $contagens['criancas']++;
            }
        }

        if ($criancas) {
            $stmt = $pdo->prepare(
                'SELECT DISTINCT e.id FROM encarregados e
                 INNER JOIN criancas c ON c.encarregado_id = e.id
                 WHERE c.id IN (' . demo_marcadores($criancas) . ")
                   AND e.nome LIKE 'Teste Demo Encarregado %'
                   AND e.morada LIKE '%[DEMO]%'"
            );
            $stmt->execute($criancas);
            $encarregados = array_map('intval', array_column($stmt->fetchAll(), 'id'));
            foreach ($encarregados as $encarregadoId) {
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM criancas
                     WHERE encarregado_id = ? AND ativo = TRUE AND eliminado_em IS NULL
                       AND NOT (nome LIKE ? AND observacoes LIKE ?)'
                );
                $stmt->execute([$encarregadoId, 'Teste Demo %', '%[DEMO]%']);
                if ((int) $stmt->fetchColumn() > 0) {
                    continue;
                }
                $stmt = $pdo->prepare('UPDATE encarregados SET ativo = FALSE, eliminado_em = NOW() WHERE id = ? AND ativo = TRUE AND eliminado_em IS NULL');
                $stmt->execute([$encarregadoId]);
                if ($stmt->rowCount() > 0) {
                    demo_registar_auditoria($pdo, 'encarregados', $encarregadoId, 'eliminar', 'eliminado_em', null, 'limpeza demo', $autorId);
                    $contagens['encarregados']++;
                }
            }
        }

        foreach ($lotes as $loteId) {
            $stmt = $pdo->prepare('UPDATE stock_lotes SET ativo = FALSE, eliminado_em = NOW() WHERE id = ? AND ativo = TRUE AND eliminado_em IS NULL');
            $stmt->execute([$loteId]);
            if ($stmt->rowCount() > 0) {
                demo_registar_auditoria($pdo, 'stock_lotes', $loteId, 'eliminar', 'eliminado_em', null, 'limpeza demo', $autorId);
                $contagens['lotes']++;
            }
        }

        foreach ($utilizadoresDemo as $utilizador) {
            $stmt = $pdo->prepare('UPDATE utilizadores SET ativo = FALSE WHERE id = ? AND ativo = TRUE');
            $stmt->execute([(int) $utilizador['id']]);
            if ($stmt->rowCount() > 0) {
                demo_registar_auditoria($pdo, 'utilizadores', (int) $utilizador['id'], 'desativar', 'ativo', '1', '0', $autorId);
                $contagens['utilizadores']++;
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    echo "Limpeza lógica concluída.\n";
    foreach ($contagens as $chave => $quantidade) {
        echo '  ' . $chave . ': ' . $quantidade . "\n";
    }
    echo "Movimentos, vacinações, anulações e auditoria foram preservados.\n";
    echo "Lotes inativos não entram no saldo atual; vacinações demo deixam de ser válidas.\n";
}

try {
    $pdo = conexao_app();
    demo_ajuda();
    if ($argumentos[0] === '--gerar') {
        demo_gerar($pdo);
    } else {
        demo_limpar($pdo);
    }
    exit(0);
} catch (Throwable $e) {
    error_log('Erro nos dados de demonstração: ' . $e->getMessage());
    echo 'Erro: ' . $e->getMessage() . "\n";
    exit(1);
}
