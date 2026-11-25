<?php
/**
 * Testes da Fase B: crianças, encarregados, duplicados, auditoria e perfis.
 */

require __DIR__ . '/suporte_http.php';

$base = 'http://localhost/vacinacao-infantil-fase1a/vacinas/public';
$sufixo = bin2hex(random_bytes(3));
$password = 'FaseB-' . bin2hex(random_bytes(8)) . '!';
$pastaTemporaria = sys_get_temp_dir() . '/vacinas_faseb_' . $sufixo;
mkdir($pastaTemporaria);
$cookies = [];
$utilizadores = [];
$criancasIds = [];
$encarregadoId = 0;
$falhas = 0;
$total = 0;
$pdo = conexao_teste_root();

try {
    $perfis = [
        'rec' => 'recepcao',
        'enf' => 'enfermeiro',
        'sto' => 'responsavel_stock',
        'adm' => 'administrador',
    ];
    foreach ($perfis as $abrev => $perfil) {
        $nome = 'fb_' . $abrev . '_' . $sufixo;
        $id = criar_utilizador_teste($pdo, $nome, $perfil, $password);
        $utilizadores[$abrev] = ['id' => $id, 'nome' => $nome];
        $cookie = $pastaTemporaria . '/' . $abrev . '.txt';
        $cookies[$abrev] = $cookie;
        $login = login_http_teste($base, $nome, $password, $cookie);
        verificar_teste($login['status'] === 302 && strpos($login['cabecalhos'], 'painel.php') !== false, "login HTTP do perfil {$perfil}", $falhas, $total);
    }

    foreach (['rec', 'enf', 'adm'] as $abrev) {
        $resposta = pedido_http_teste($base . '/criancas.php', $cookies[$abrev]);
        verificar_teste($resposta['status'] === 200, "perfil {$abrev} pode consultar crianças", $falhas, $total);
        verificar_teste(strpos($resposta['corpo'], 'Warning') === false && strpos($resposta['corpo'], 'Fatal error') === false, "HTML sem erros para {$abrev}", $falhas, $total);
    }
    $resposta = pedido_http_teste($base . '/criancas.php', $cookies['sto']);
    verificar_teste($resposta['status'] === 403, 'responsável de stock não acede a crianças', $falhas, $total);

    foreach (['enf', 'sto', 'adm'] as $abrev) {
        $resposta = pedido_http_teste($base . '/crianca_form.php', $cookies[$abrev]);
        verificar_teste($resposta['status'] === 403, "perfil {$abrev} não cadastra criança", $falhas, $total);
    }
    $resposta = pedido_http_teste($base . '/crianca_form.php', $cookies['rec']);
    verificar_teste($resposta['status'] === 200, 'recepção abre formulário de criança', $falhas, $total);

    $csrfInvalido = pedido_http_teste($base . '/crianca_form.php', $cookies['rec'], [
        'csrf_token' => 'invalido', 'nome' => 'Teste', 'data_nascimento' => '2020-01-01',
        'sexo' => 'M', 'encarregado_id' => 1,
    ]);
    verificar_teste($csrfInvalido['status'] === 403, 'POST de criança rejeita CSRF inválido', $falhas, $total);

    $nomeEncarregado = 'Teste Demo Encarregado Fase B ' . $sufixo;
    $resposta = post_form_http_teste($base, 'encarregado_form.php', $cookies['rec'], [
        'nome' => $nomeEncarregado,
        'parentesco' => 'Mãe',
        'telefone' => '923 456 789',
        'telefone_alt' => '924-000-111',
        'morada' => 'Morada fictícia para teste',
        'bairro_id' => 0,
        'novo_bairro' => '',
    ]);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'encarregados.php') !== false, 'recepção cadastra encarregado', $falhas, $total);
    $stmt = $pdo->prepare('SELECT id, telefone, telefone_alt FROM encarregados WHERE nome = ? LIMIT 1');
    $stmt->execute([$nomeEncarregado]);
    $encarregado = $stmt->fetch();
    $encarregadoId = (int) ($encarregado['id'] ?? 0);
    verificar_teste($encarregadoId > 0, 'encarregado foi persistido', $falhas, $total);
    verificar_teste(($encarregado['telefone'] ?? '') === '923456789' && ($encarregado['telefone_alt'] ?? '') === '924000111', 'telefones normalizados apenas com dígitos', $falhas, $total);

    $nomeCrianca = 'Teste Demo Criança Fase B ' . $sufixo;
    $dadosCrianca = [
        'numero_cartao' => 'CARTTESTE' . $sufixo,
        'nome' => $nomeCrianca,
        'data_nascimento' => '2020-01-15',
        'data_nascimento_aproximada' => 1,
        'sexo' => 'F',
        'encarregado_id' => $encarregadoId,
        'observacoes' => 'Registo fictício de teste',
    ];
    $resposta = post_form_http_teste($base, 'crianca_form.php', $cookies['rec'], $dadosCrianca);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'crianca_detalhe.php') !== false, 'recepção cadastra criança', $falhas, $total);
    $stmt = $pdo->prepare('SELECT id, numero_processo FROM criancas WHERE nome = ? ORDER BY id LIMIT 1');
    $stmt->execute([$nomeCrianca]);
    $crianca = $stmt->fetch();
    $primeiraCriancaId = (int) ($crianca['id'] ?? 0);
    if ($primeiraCriancaId > 0) $criancasIds[] = $primeiraCriancaId;
    verificar_teste($primeiraCriancaId > 0, 'criança foi persistida', $falhas, $total);
    verificar_teste(preg_match('/^RG\d{6}$/', $crianca['numero_processo'] ?? '') === 1, 'número de processo segue prefixo e sequência', $falhas, $total);

    $resposta = post_form_http_teste($base, 'crianca_form.php', $cookies['rec'], $dadosCrianca);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Possível duplicado') !== false, 'cadastro mostra aviso de duplicado', $falhas, $total);
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM criancas WHERE nome = ?');
    $stmt->execute([$nomeCrianca]);
    verificar_teste((int) $stmt->fetch()['total'] === 1, 'duplicado não é gravado antes da confirmação', $falhas, $total);

    $dadosCrianca['confirmar_duplicado'] = 1;
    $dadosCrianca['numero_cartao'] = 'CARTTESTE2' . $sufixo;
    $resposta = post_form_http_teste($base, 'crianca_form.php', $cookies['rec'], $dadosCrianca);
    verificar_teste($resposta['status'] === 302, 'duplicado pode ser confirmado sem fusão', $falhas, $total);
    $stmt = $pdo->prepare('SELECT id FROM criancas WHERE nome = ? ORDER BY id');
    $stmt->execute([$nomeCrianca]);
    foreach ($stmt->fetchAll() as $linha) $criancasIds[] = (int) $linha['id'];
    verificar_teste(count(array_unique($criancasIds)) === 2, 'confirmação cria segundo registo distinto', $falhas, $total);

    foreach (['923 456 789', $crianca['numero_processo'], 'CARTTESTE' . $sufixo, $nomeCrianca] as $termo) {
        $resposta = pedido_http_teste($base . '/criancas.php?q=' . rawurlencode($termo), $cookies['rec']);
        verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], $nomeCrianca) !== false, "pesquisa encontra por {$termo}", $falhas, $total);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO criancas (numero_processo, numero_cartao, nome, data_nascimento, sexo, encarregado_id, observacoes, ativo, criado_por_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, ?)'
    );
    for ($i = 1; $i <= 14; $i++) {
        $stmt->execute([
            'TB' . $sufixo . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            null,
            'Teste Demo Fase B ' . $sufixo . ' ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            '2021-02-03',
            'M',
            $encarregadoId,
            'Fixture temporário de paginação',
            $utilizadores['rec']['id'],
        ]);
        $criancasIds[] = (int) $pdo->lastInsertId();
    }
    $resposta = pedido_http_teste($base . '/criancas.php?q=' . rawurlencode('Fase B ' . $sufixo), $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Página 1 de 2') !== false, 'paginação mostra primeira página', $falhas, $total);
    $resposta = pedido_http_teste($base . '/criancas.php?q=' . rawurlencode('Fase B ' . $sufixo) . '&pagina=2', $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Página 2 de 2') !== false, 'paginação mostra segunda página', $falhas, $total);

    $dadosCrianca['id'] = $primeiraCriancaId;
    $dadosCrianca['numero_cartao'] = 'CARTEDIT' . $sufixo;
    $resposta = post_form_http_teste($base, 'crianca_form.php', $cookies['rec'], $dadosCrianca);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'crianca_detalhe.php') !== false, 'recepção edita criança', $falhas, $total);
    $stmt = $pdo->prepare('SELECT numero_cartao FROM criancas WHERE id = ?');
    $stmt->execute([$primeiraCriancaId]);
    verificar_teste($stmt->fetch()['numero_cartao'] === 'CARTEDIT' . $sufixo, 'edição persistiu o novo cartão', $falhas, $total);
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM auditoria WHERE entidade = 'criancas' AND entidade_id = ? AND acao = 'alterar' AND campo = 'numero_cartao'");
    $stmt->execute([$primeiraCriancaId]);
    verificar_teste((int) $stmt->fetch()['total'] >= 1, 'edição registou auditoria por campo', $falhas, $total);

    $resposta = post_form_http_teste($base, 'crianca_form.php', $cookies['enf'], $dadosCrianca);
    verificar_teste($resposta['status'] === 403, 'enfermeiro não edita criança', $falhas, $total);

    $resposta = pedido_http_teste($base . '/crianca_detalhe.php?id=' . $primeiraCriancaId, $cookies['rec']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Abrir atendimento') !== false, 'recepção vê botão de atendimento', $falhas, $total);
    $resposta = pedido_http_teste($base . '/crianca_detalhe.php?id=' . $primeiraCriancaId, $cookies['adm']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Abrir atendimento') === false, 'administrador consulta sem abrir atendimento', $falhas, $total);

    $formulario = pedido_http_teste($base . '/crianca_detalhe.php?id=' . $primeiraCriancaId, $cookies['rec']);
    $csrf = csrf_do_html($formulario['corpo']);
    $resposta = pedido_http_teste($base . '/crianca_eliminar.php', $cookies['rec'], ['csrf_token' => $csrf, 'id' => $primeiraCriancaId]);
    verificar_teste($resposta['status'] === 302, 'recepção elimina criança logicamente', $falhas, $total);
    $stmt = $pdo->prepare('SELECT ativo, eliminado_em FROM criancas WHERE id = ?');
    $stmt->execute([$primeiraCriancaId]);
    $estado = $stmt->fetch();
    verificar_teste((int) $estado['ativo'] === 0 && $estado['eliminado_em'] !== null, 'eliminação lógica preserva a linha', $falhas, $total);

    $formulario = pedido_http_teste($base . '/encarregado_form.php?id=' . $encarregadoId, $cookies['rec']);
    $csrf = csrf_do_html($formulario['corpo']);
    $resposta = pedido_http_teste($base . '/encarregado_eliminar.php', $cookies['rec'], ['csrf_token' => $csrf, 'id' => $encarregadoId]);
    verificar_teste($resposta['status'] === 302, 'eliminação do encarregado com crianças ativas é recusada por redirect', $falhas, $total);
    $stmt = $pdo->prepare('SELECT ativo FROM encarregados WHERE id = ?');
    $stmt->execute([$encarregadoId]);
    verificar_teste((int) $stmt->fetch()['ativo'] === 1, 'encarregado com crianças ativas não é eliminado', $falhas, $total);

    // As fixtures de paginação são removidas apenas da cópia lógica para testar a eliminação do encarregado.
    $stmt = $pdo->prepare('UPDATE criancas SET ativo = 0, eliminado_em = NOW() WHERE encarregado_id = ? AND ativo = 1');
    $stmt->execute([$encarregadoId]);
    $resposta = pedido_http_teste($base . '/encarregado_eliminar.php', $cookies['rec'], ['csrf_token' => $csrf, 'id' => $encarregadoId]);
    verificar_teste($resposta['status'] === 302, 'encarregado sem crianças ativas pode ser eliminado', $falhas, $total);
    $stmt = $pdo->prepare('SELECT ativo, eliminado_em FROM encarregados WHERE id = ?');
    $stmt->execute([$encarregadoId]);
    $estado = $stmt->fetch();
    verificar_teste((int) $estado['ativo'] === 0 && $estado['eliminado_em'] !== null, 'eliminação lógica do encarregado preserva a linha', $falhas, $total);
} catch (Throwable $e) {
    $falhas++;
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
} finally {
    if ($encarregadoId > 0) {
        $stmt = $pdo->prepare('DELETE FROM auditoria WHERE entidade IN (\'criancas\', \'encarregados\') AND entidade_id = ?');
        $stmt->execute([$encarregadoId]);
        $stmt = $pdo->prepare('DELETE FROM criancas WHERE encarregado_id = ?');
        $stmt->execute([$encarregadoId]);
        $stmt = $pdo->prepare('DELETE FROM encarregados WHERE id = ?');
        $stmt->execute([$encarregadoId]);
    }
    foreach ($utilizadores as $dados) limpar_utilizador_teste($pdo, $dados['id'], $dados['nome']);
    foreach ($cookies as $cookie) if (is_file($cookie)) unlink($cookie);
    if (is_dir($pastaTemporaria)) rmdir($pastaTemporaria);
}

if ($falhas > 0) {
    fwrite(STDERR, "{$falhas} de {$total} verificações falharam.\n");
    exit(1);
}
echo "Fase B: {$total} verificações concluídas sem falhas.\n";
