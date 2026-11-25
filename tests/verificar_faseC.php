<?php
/**
 * Testes da Fase C: sessão independente, funcionários, auditoria e bloqueio.
 */

require __DIR__ . '/suporte_http.php';

$base = 'http://localhost/vacinacao-infantil-fase1a/vacinas/public';
$gestao = $base . '/gestao';
$sufixo = bin2hex(random_bytes(3));
$password = 'FaseC-' . bin2hex(random_bytes(8)) . '!';
$pastaTemporaria = sys_get_temp_dir() . '/vacinas_fasec_' . $sufixo;
mkdir($pastaTemporaria);
$cookies = [];
$utilizadores = [];
$falhas = 0;
$total = 0;
$pdo = conexao_teste_root();

function login_gestao_teste($base, $utilizador, $password, $cookie) {
    $inicial = pedido_http_teste($base . '/gestao/index.php', $cookie);
    return pedido_http_teste($base . '/gestao/index.php', $cookie, [
        'utilizador' => $utilizador,
        'password' => $password,
        'csrf_token' => csrf_do_html($inicial['corpo']),
    ]);
}

function password_temporaria_html($html) {
    if (preg_match('/<code>([^<]+)<\/code>/', $html, $matches)) {
        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

function post_gestao_lista_teste($gestaoBase, $cookie, array $dados) {
    $lista = pedido_http_teste($gestaoBase . '/funcionarios.php', $cookie);
    $dados['csrf_token'] = csrf_do_html($lista['corpo']);
    return pedido_http_teste($gestaoBase . '/' . $dados['acao'], $cookie, $dados);
}

try {
    foreach (['adm' => 'administrador', 'enf' => 'enfermeiro', 'rec' => 'recepcao', 'sto' => 'responsavel_stock', 'adm2' => 'administrador'] as $abrev => $perfil) {
        $nome = 'fc_' . $abrev . '_' . $sufixo;
        $id = criar_utilizador_teste($pdo, $nome, $perfil, $password);
        $utilizadores[$abrev] = ['id' => $id, 'nome' => $nome];
    }

    $cookieAnonimo = $pastaTemporaria . '/anonimo.txt';
    $resposta = pedido_http_teste($gestao . '/index.php', $cookieAnonimo);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Gestão restrita') !== false, 'login próprio da gestão abre', $falhas, $total);
    verificar_teste(strpos($resposta['corpo'], '/public/assets/css/modulos.css') !== false && strpos($resposta['corpo'], '/gestao/assets/') === false, 'CSS da gestão vem da raiz pública', $falhas, $total);
    $resposta = pedido_http_teste($gestao . '/funcionarios.php', $cookieAnonimo);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'gestao/index.php') !== false, 'gestão sem sessão redireciona para login próprio', $falhas, $total);

    $cookies['enf'] = $pastaTemporaria . '/enf.txt';
    $login = login_http_teste($base, $utilizadores['enf']['nome'], $password, $cookies['enf']);
    verificar_teste($login['status'] === 302 && strpos($login['cabecalhos'], 'painel.php') !== false, 'enfermeiro entra na aplicação principal', $falhas, $total);
    $resposta = pedido_http_teste($gestao . '/funcionarios.php', $cookies['enf']);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'gestao/index.php') !== false, 'sessão de enfermeiro não abre a gestão', $falhas, $total);
    $jar = is_file($cookies['enf']) ? file_get_contents($cookies['enf']) : '';
    verificar_teste(strpos($jar, 'PHPSESSID') !== false && strpos($jar, 'VACINAS_GESTAO') !== false, 'cookies principal e de gestão têm nomes diferentes', $falhas, $total);

    $cookies['adm'] = $pastaTemporaria . '/adm.txt';
    $resposta = pedido_http_teste($gestao . '/index.php', $cookies['adm'], ['csrf_token' => 'invalido', 'utilizador' => 'x', 'password' => 'x']);
    verificar_teste($resposta['status'] === 403, 'login da gestão rejeita CSRF inválido', $falhas, $total);
    $login = login_gestao_teste($base, $utilizadores['adm']['nome'], $password, $cookies['adm']);
    verificar_teste($login['status'] === 302 && strpos($login['cabecalhos'], 'gestao/funcionarios.php') !== false, 'administrador entra na gestão', $falhas, $total);
    $jar = is_file($cookies['adm']) ? file_get_contents($cookies['adm']) : '';
    verificar_teste(strpos($jar, 'VACINAS_GESTAO') !== false && strpos($jar, 'PHPSESSID') === false, 'login da gestão usa apenas o cookie próprio', $falhas, $total);
    $resposta = pedido_http_teste($gestao . '/funcionarios.php', $cookies['adm']);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Funcionários') !== false, 'administrador consulta funcionários', $falhas, $total);

    $cookies['enfGestao'] = $pastaTemporaria . '/enf_gestao.txt';
    $login = login_gestao_teste($base, $utilizadores['enf']['nome'], $password, $cookies['enfGestao']);
    verificar_teste($login['status'] === 302 && strpos($login['cabecalhos'], 'gestao/index.php') !== false, 'credenciais de enfermeiro não entram na gestão', $falhas, $total);
    $resposta = pedido_http_teste($gestao . '/index.php', $cookies['enfGestao']);
    verificar_teste(strpos($resposta['corpo'], 'exclusiva a administradores') !== false, 'recusa da gestão é explicitada sem expor dados', $falhas, $total);

    foreach (['enf', 'rec', 'sto'] as $abrev) {
        $cookie = $pastaTemporaria . '/' . $abrev . '.txt';
        if ($abrev !== 'enf') {
            $cookies[$abrev] = $cookie;
            login_http_teste($base, $utilizadores[$abrev]['nome'], $password, $cookie);
        }
        $resposta = pedido_http_teste($gestao . '/funcionarios.php', $cookies[$abrev]);
        verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'gestao/index.php') !== false, "perfil {$abrev} não herda acesso à gestão", $falhas, $total);
    }

    $cookies['admMain'] = $pastaTemporaria . '/adm_main.txt';
    login_http_teste($base, $utilizadores['adm']['nome'], $password, $cookies['admMain']);
    $painel = pedido_http_teste($base . '/painel.php', $cookies['admMain']);
    verificar_teste($painel['status'] === 200 && stripos($painel['corpo'], '/gestao/') === false, 'aplicação principal não contém ligação para a gestão', $falhas, $total);

    $novoNome = 'fc_novo_' . $sufixo;
    $dados = ['nome' => 'Teste Demo Funcionário ' . $sufixo, 'utilizador' => $novoNome, 'perfil' => 'enfermeiro'];
    $resposta = post_form_http_teste($gestao, 'funcionario_form.php', $cookies['adm'], $dados);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'gestao/funcionarios.php') !== false, 'administrador cria funcionário', $falhas, $total);
    $stmt = $pdo->prepare('SELECT * FROM utilizadores WHERE utilizador = ? LIMIT 1');
    $stmt->execute([$novoNome]);
    $novo = $stmt->fetch();
    $novoId = (int) ($novo['id'] ?? 0);
    $utilizadores['novo'] = ['id' => $novoId, 'nome' => $novoNome];
    verificar_teste($novoId > 0 && $novo['perfil'] === 'enfermeiro' && (int) $novo['ativo'] === 1, 'conta persistida com perfil e estado corretos', $falhas, $total);
    verificar_teste(strpos($novo['password_hash'] ?? '', '$argon2id$') === 0 && (int) $novo['deve_mudar_password'] === 1, 'conta nova usa Argon2id e exige mudança', $falhas, $total);
    $lista = pedido_http_teste($gestao . '/funcionarios.php', $cookies['adm']);
    $temporaria = password_temporaria_html($lista['corpo']);
    verificar_teste($temporaria !== '' && password_verify($temporaria, $novo['password_hash']), 'palavra-passe temporária é válida e aparece no ecrã', $falhas, $total);
    $listaRepetida = pedido_http_teste($gestao . '/funcionarios.php', $cookies['adm']);
    verificar_teste(password_temporaria_html($listaRepetida['corpo']) === '', 'palavra-passe temporária não aparece duas vezes', $falhas, $total);
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM auditoria WHERE entidade = 'utilizadores' AND entidade_id = ? AND acao = 'criar'");
    $stmt->execute([$novoId]);
    verificar_teste((int) $stmt->fetch()['total'] === 2, 'criação registou auditoria sem dados sensíveis', $falhas, $total);

    $resposta = post_form_http_teste($gestao, 'funcionario_form.php', $cookies['adm'], $dados);
    verificar_teste($resposta['status'] === 200 && strpos($resposta['corpo'], 'Já existe um funcionário') !== false, 'nome de utilizador duplicado é recusado', $falhas, $total);
    $resposta = pedido_http_teste($gestao . '/funcionario_form.php', $cookies['adm'], ['csrf_token' => 'invalido', 'nome' => 'Teste', 'utilizador' => 'x', 'perfil' => 'recepcao']);
    verificar_teste($resposta['status'] === 403, 'criação rejeita CSRF inválido', $falhas, $total);

    $dados['id'] = $novoId;
    $dados['nome'] = 'Teste Demo Funcionário Editado ' . $sufixo;
    $dados['perfil'] = 'responsavel_stock';
    $resposta = post_form_http_teste($gestao, 'funcionario_form.php', $cookies['adm'], $dados);
    verificar_teste($resposta['status'] === 302, 'administrador edita funcionário', $falhas, $total);
    $stmt = $pdo->prepare('SELECT nome, perfil FROM utilizadores WHERE id = ?');
    $stmt->execute([$novoId]);
    $editado = $stmt->fetch();
    verificar_teste($editado['nome'] === $dados['nome'] && $editado['perfil'] === 'responsavel_stock', 'alteração de nome e perfil persistiu', $falhas, $total);
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT campo) AS total FROM auditoria WHERE entidade = 'utilizadores' AND entidade_id = ? AND acao = 'alterar'");
    $stmt->execute([$novoId]);
    verificar_teste((int) $stmt->fetch()['total'] === 2, 'edição audita nome e perfil por campo', $falhas, $total);

    $resposta = post_gestao_lista_teste($gestao, $cookies['adm'], ['acao' => 'funcionario_estado.php', 'id' => $novoId, 'ativo' => 0]);
    verificar_teste($resposta['status'] === 302, 'administrador desativa conta', $falhas, $total);
    $stmt = $pdo->prepare('SELECT ativo FROM utilizadores WHERE id = ?');
    $stmt->execute([$novoId]);
    verificar_teste((int) $stmt->fetch()['ativo'] === 0, 'desativação preserva a linha', $falhas, $total);
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM auditoria WHERE entidade = 'utilizadores' AND entidade_id = ? AND acao = 'desativar'");
    $stmt->execute([$novoId]);
    verificar_teste((int) $stmt->fetch()['total'] === 1, 'desativação registou auditoria', $falhas, $total);
    $cookieNovo = $pastaTemporaria . '/novo.txt';
    $login = login_http_teste($base, $novoNome, $temporaria, $cookieNovo);
    verificar_teste($login['status'] === 302 && strpos($login['cabecalhos'], 'login.php') !== false, 'conta desativada não entra na aplicação', $falhas, $total);

    $resposta = post_gestao_lista_teste($gestao, $cookies['adm'], ['acao' => 'funcionario_estado.php', 'id' => $novoId, 'ativo' => 1]);
    verificar_teste($resposta['status'] === 302, 'administrador reativa conta', $falhas, $total);
    $stmt = $pdo->prepare('SELECT ativo FROM utilizadores WHERE id = ?');
    $stmt->execute([$novoId]);
    verificar_teste((int) $stmt->fetch()['ativo'] === 1, 'reativação persistiu', $falhas, $total);

    $resposta = post_gestao_lista_teste($gestao, $cookies['adm'], ['acao' => 'funcionario_password.php', 'id' => $novoId]);
    verificar_teste($resposta['status'] === 302, 'administrador repõe palavra-passe', $falhas, $total);
    $lista = pedido_http_teste($gestao . '/funcionarios.php', $cookies['adm']);
    $novaTemporaria = password_temporaria_html($lista['corpo']);
    $stmt = $pdo->prepare('SELECT password_hash, deve_mudar_password FROM utilizadores WHERE id = ?');
    $stmt->execute([$novoId]);
    $conta = $stmt->fetch();
    verificar_teste($novaTemporaria !== '' && password_verify($novaTemporaria, $conta['password_hash']), 'nova temporária corresponde ao hash guardado', $falhas, $total);
    verificar_teste(!password_verify($temporaria, $conta['password_hash']) && (int) $conta['deve_mudar_password'] === 1, 'temporária anterior inválida e mudança obrigatória ativa', $falhas, $total);
    $stmt = $pdo->prepare("SELECT valor_anterior, valor_novo FROM auditoria WHERE entidade = 'utilizadores' AND entidade_id = ? AND acao = 'repor_password'");
    $stmt->execute([$novoId]);
    $auditoriaPassword = $stmt->fetch();
    verificar_teste($auditoriaPassword && strpos((string) $auditoriaPassword['valor_anterior'] . (string) $auditoriaPassword['valor_novo'], $novaTemporaria) === false, 'auditoria não contém a palavra-passe', $falhas, $total);

    $login = login_http_teste($base, $novoNome, $novaTemporaria, $cookieNovo);
    verificar_teste($login['status'] === 302 && strpos($login['cabecalhos'], 'alterar_password.php') !== false, 'temporária força mudança no primeiro acesso', $falhas, $total);
    $novaDefinitiva = 'DefinitivaC-' . bin2hex(random_bytes(8)) . '!';
    $resposta = post_form_http_teste($base, 'alterar_password.php', $cookieNovo, ['password' => $novaDefinitiva, 'password_confirmacao' => $novaDefinitiva]);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'painel.php') !== false, 'funcionário troca a temporária', $falhas, $total);
    $stmt = $pdo->prepare('SELECT password_hash, deve_mudar_password FROM utilizadores WHERE id = ?');
    $stmt->execute([$novoId]);
    $conta = $stmt->fetch();
    verificar_teste(password_verify($novaDefinitiva, $conta['password_hash']) && (int) $conta['deve_mudar_password'] === 0, 'palavra-passe definitiva e flag de mudança atualizados', $falhas, $total);

    $resposta = post_gestao_lista_teste($gestao, $cookies['adm'], ['acao' => 'funcionario_estado.php', 'id' => $utilizadores['adm']['id'], 'ativo' => 0]);
    $lista = pedido_http_teste($gestao . '/funcionarios.php', $cookies['adm']);
    verificar_teste(strpos($lista['corpo'], 'própria conta') !== false, 'administrador não desativa a própria conta', $falhas, $total);

    $cookies['adm2'] = $pastaTemporaria . '/adm2.txt';
    $login = login_gestao_teste($base, $utilizadores['adm2']['nome'], $password, $cookies['adm2']);
    verificar_teste($login['status'] === 302 && strpos($login['cabecalhos'], 'funcionarios.php') !== false, 'segundo administrador abre sessão de gestão', $falhas, $total);
    $resposta = post_gestao_lista_teste($gestao, $cookies['adm'], ['acao' => 'funcionario_estado.php', 'id' => $utilizadores['adm2']['id'], 'ativo' => 0]);
    $resposta = pedido_http_teste($gestao . '/funcionarios.php', $cookies['adm2']);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'gestao/index.php') !== false, 'sessão de gestão é revogada ao desativar a conta', $falhas, $total);

    $nomeBloqueio = 'fc_bloqueio_' . $sufixo;
    $idBloqueio = criar_utilizador_teste($pdo, $nomeBloqueio, 'administrador', $password);
    $utilizadores['bloqueio'] = ['id' => $idBloqueio, 'nome' => $nomeBloqueio];
    $cookieBloqueio = $pastaTemporaria . '/bloqueio.txt';
    for ($i = 0; $i < 5; $i++) {
        $inicial = pedido_http_teste($gestao . '/index.php', $cookieBloqueio);
        pedido_http_teste($gestao . '/index.php', $cookieBloqueio, [
            'utilizador' => $nomeBloqueio,
            'password' => 'incorreta',
            'csrf_token' => csrf_do_html($inicial['corpo']),
        ]);
    }
    $stmt = $pdo->prepare('SELECT tentativas_falhadas, bloqueado_ate FROM utilizadores WHERE id = ?');
    $stmt->execute([$idBloqueio]);
    $bloqueio = $stmt->fetch();
    verificar_teste((int) $bloqueio['tentativas_falhadas'] === 5 && $bloqueio['bloqueado_ate'] !== null && $bloqueio['bloqueado_ate'] > date('Y-m-d H:i:s'), 'gestão bloqueia após cinco falhas', $falhas, $total);
    $login = login_gestao_teste($base, $nomeBloqueio, $password, $cookieBloqueio);
    verificar_teste($login['status'] === 302 && strpos($login['cabecalhos'], 'gestao/index.php') !== false, 'conta bloqueada não entra na gestão', $falhas, $total);

    $resposta = pedido_http_teste($gestao . '/logout.php', $cookies['adm'], ['csrf_token' => 'invalido']);
    verificar_teste($resposta['status'] === 403, 'logout da gestão exige CSRF', $falhas, $total);
    $formulario = pedido_http_teste($gestao . '/funcionarios.php', $cookies['adm']);
    $resposta = pedido_http_teste($gestao . '/logout.php', $cookies['adm'], ['csrf_token' => csrf_do_html($formulario['corpo'])]);
    verificar_teste($resposta['status'] === 302 && strpos($resposta['cabecalhos'], 'gestao/index.php') !== false, 'logout da gestão termina sessão própria', $falhas, $total);
    $resposta = pedido_http_teste($gestao . '/funcionarios.php', $cookies['adm']);
    verificar_teste($resposta['status'] === 302, 'página protegida fica inacessível após logout', $falhas, $total);
    $resposta = pedido_http_teste($base . '/painel.php', $cookies['admMain']);
    verificar_teste($resposta['status'] === 200, 'logout da gestão não termina a sessão principal', $falhas, $total);

    $ficheirosGestao = [
        'public/gestao/index.php', 'public/gestao/funcionarios.php', 'public/gestao/funcionario_form.php',
        'public/gestao/funcionario_estado.php', 'public/gestao/funcionario_password.php',
        'public/gestao/alterar_password.php', 'public/gestao/logout.php',
    ];
    foreach ($ficheirosGestao as $ficheiro) {
        $conteudo = file_get_contents(dirname(__DIR__) . '/' . $ficheiro);
        verificar_teste(preg_match('/\son[a-z]+\s*=|style\s*=|<script(?![^>]+src=)/i', $conteudo) === 0, "sem eventos, estilos ou scripts inline: {$ficheiro}", $falhas, $total);
    }
    verificar_teste(is_file(dirname(__DIR__) . '/docs/Gestao_Producao.md'), 'documentação de proteção adicional existe', $falhas, $total);
} catch (Throwable $e) {
    $falhas++;
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
} finally {
    $ids = array_values(array_filter(array_map(function ($dados) { return (int) $dados['id']; }, $utilizadores)));
    if ($ids) {
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM auditoria WHERE (entidade = 'utilizadores' AND entidade_id IN ({$marcadores})) OR utilizador_id IN ({$marcadores})");
        $stmt->execute(array_merge($ids, $ids));
        $stmt = $pdo->prepare("UPDATE utilizadores SET criado_por_id = NULL WHERE id IN ({$marcadores})");
        $stmt->execute($ids);
        foreach ($utilizadores as $dados) {
            $stmt = $pdo->prepare('DELETE FROM login_tentativas WHERE utilizador = ?');
            $stmt->execute([$dados['nome']]);
        }
        $stmt = $pdo->prepare("DELETE FROM utilizadores WHERE id IN ({$marcadores})");
        $stmt->execute($ids);
    }
    foreach ($cookies as $cookie) if (is_file($cookie)) unlink($cookie);
    foreach (['bloqueio.txt', 'novo.txt', 'anonimo.txt'] as $nome) {
        $cookie = $pastaTemporaria . '/' . $nome;
        if (is_file($cookie)) unlink($cookie);
    }
    if (is_dir($pastaTemporaria)) rmdir($pastaTemporaria);
}

if ($falhas > 0) {
    fwrite(STDERR, "{$falhas} de {$total} verificações falharam.\n");
    exit(1);
}
echo "Fase C: {$total} verificações concluídas sem falhas.\n";
