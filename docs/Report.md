# Relatório de implementação — Fases 1, 2 e A a I

## Estado da fase

Fase 1 concluída e testada. O login e o painel usam os mesmos CSS, na mesma ordem do `index.html` original da tag `cloudflare-fase1a`. A autenticação, a sessão, o token CSRF e o logout existentes foram mantidos.

A Fase 2 foi concluída e testada. A base local foi atualizada após a criação de uma cópia de segurança.

As Fases A a I foram concluídas. Os módulos Crianças, Atendimento, Vacinação, Faltosos, Stock, Relatórios, 2.ª via do cartão e `/gestao/` estão implementados, ligados aos respetivos menus quando o perfil tem acesso e validados no ambiente local com dados fictícios. Permanecem apenas as validações clínicas, documentais e de infraestrutura identificadas no fecho.

## Ficheiros criados ou alterados

| Ficheiro | Alterações |
| --- | --- |
| `app/views/partials/head.php` | Carrega os oito CSS originais por `url_base()`, na ordem correta, em login e painel. Mantém CSP e os cabeçalhos de segurança. Mostra `previa.css` e a faixa apenas quando `modo_previa` está ativo. |
| `app/views/partials/foot.php` | Deixou de carregar `interface.js`, que duplicava handlers incorretos do menu e da password. Cada página carrega apenas o seu JavaScript. |
| `app/core/helpers.php` | `is_preview_mode()` passou a ler `config('modo_previa', false)`. A lógica de autenticação não foi reescrita. |
| `public/login.php` | `display_errors` passa a obedecer à configuração, com `Off` por defeito. O selo usa o ícone `user` da referência original. O script externo é escapado com `e()` e fica antes do fecho do documento. |
| `public/painel.php` | Corrige `$loginTime`, `display_errors`, menu, indicador, cartões, ícones, textos e data. Acrescenta botão interno para fechar a gaveta no telemóvel. Mantém logout POST com `csrf_field()`. |
| `public/assets/js/painel.js` | JavaScript puro, sem PHP/HTML. Usa `addEventListener` para abrir/fechar o menu, fechar por botão/Escape/área exterior e submeter o logout. |
| `public/assets/js/login.js` | Corrige apenas o valor de `aria-pressed` ao mostrar/ocultar a palavra-passe. |
| `public/assets/css/previa.css` | Reduzido ao estilo da faixa de pré-visualização. Os CSS originais não foram alterados. |
| `tests/verificar_classes.php` | Reescrito como teste de linha de comandos: valida ordem dos CSS, classes da referência, regras CSS, ícones SVG e caminhos relativos dos recursos. |
| `docs/Report.md` | Este relatório. |

## O que foi reutilizado da versão Cloudflare

Foram usados como referência:

- Tag Git `cloudflare-fase1a`.
- `arquivo_cloudflare/public/index.html`.
- `public/assets/js/ui/login.js` e `public/assets/js/ui/painel.js`, que continuam iguais aos ficheiros da tag.
- `public/assets/css/variaveis.css`, `base.css`, `icones.css`, `componentes.css`, `layout.css`, `paginas/login.css`, `paginas/painel.css` e `impressao.css`.

Os CSS originais não sofreram alterações. A ordem carregada é:

1. `variaveis.css`
2. `base.css`
3. `icones.css`
4. `componentes.css`
5. `layout.css`
6. `paginas/login.css`
7. `paginas/painel.css`
8. `impressao.css`

## Decisões tomadas

- O menu segue a secção 5.2: Painel, Crianças, Atendimento, Vacinação, Faltosos, Stock e Relatórios. A entrada Administração foi removida do menu principal, conforme a secção 6.
- Os módulos futuros mostram `Em desenvolvimento`, em vez de números de fase.
- O indicador do topo usa `circle-check` e o texto `Servidor local`, sem lógica de sincronização.
- O terceiro cartão usa `shield-check`, texto `Operacional` e rótulo `Estado do sistema`.
- Na Fase 1 foi adicionado um botão de fecho dentro da barra lateral. Esse botão foi removido na manutenção posterior; o menu móvel passa a fechar pelo fundo, Escape e seleção de itens.
- `modo_previa` controla a faixa e `previa.css`. A autenticação real foi mantida; não foi reintroduzido um bypass de login.
- O nome da instituição passou a ser lido exclusivamente de `configuracoes.nome_posto`; o fallback local com o nome antigo foi removido.
- Não foram introduzidos dados clínicos, nomes de bairros nem valores de calendário.

## Testes executados

### Sintaxe e verificador de classes

- `php -l` passou em `painel.php`, `login.php`, `head.php`, `foot.php`, `helpers.php` e `tests/verificar_classes.php`.
- `node --check` passou em `painel.js` e `login.js`.
- `php tests/verificar_classes.php`: concluído sem falhas.
- Todas as classes estruturais da referência Cloudflare existem no HTML gerado e têm regra num CSS carregado.
- Todos os ícones usados têm máscara CSS e ficheiro SVG.
- Nenhum CSS contém `url(/...)` absoluto; todos os caminhos de fontes e ícones são relativos e existem.

### HTTP e recursos

- `login.php`: HTTP 200.
- Login autenticado e `painel.php`: HTTP 200.
- HTML autenticado do painel: zero ocorrências de `Warning`, `Notice` ou `Fatal error`.
- O painel carrega oito stylesheets e `assets/js/painel.js`.
- `painel.js`: `Content-Type: text/javascript`, sem PHP ou HTML no conteúdo.
- `painel.css`: `Content-Type: text/css`.
- 37 recursos `.css`, `.woff2` e `.svg`: todos HTTP 200.
- Nenhum atributo `onclick`, `onchange`, `onsubmit` ou script inline em `public/*.php`.

### Cabeçalhos e proteção de pastas

- `Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self';`
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: same-origin`
- `/database/`: HTTP 403.
- `/config/`: HTTP 403.
- `/arquivo_cloudflare/`: HTTP 403.
- `/.git/`: HTTP 403.

### Navegador

- Consola sem erros ou warnings.
- Rede sem pedidos falhados ou 404.
- Mostrar/ocultar palavra-passe e ajuda de recuperação funcionam.
- Login e logout funcionam.
- Menu móvel abre pelo botão do topo.
- Menu móvel fecha pelo novo botão interno e por Escape.
- `data-menu` e `aria-expanded` alternam corretamente.
- O terceiro cartão contém `.icone-shield-check`.
- Nenhum atributo `on*` ou script inline no DOM.

## Falhas encontradas durante os testes

1. **Botão de fecho do menu coberto pela barra lateral no telemóvel.**
   - Encontrado na primeira validação do navegador.
   - Corrigido na Fase 1 com um controlo interno de fecho; esse controlo foi removido na manutenção posterior.
   - Retestado com sucesso.

2. **`git diff --check` global continua a assinalar espaços no fim de linhas.**
   - Os avisos estão em alterações preexistentes de `app/core/helpers.php` e `database/schema.sql`.
   - Não foram alteradas essas linhas para evitar modificar trabalho fora do escopo da Fase 1.

## Pendências e limitações

- A validação visual automatizada do navegador ficou num viewport móvel de 532×590. A estrutura desktop foi validada por DOM, classes, CSS e HTTP, mas não foi produzida uma captura desktop.
- A faixa de pré-visualização não foi exercitada com `modo_previa = true`, porque a configuração local está com `false`.
- `requer_perfil()` ainda não existe. Não há operações protegidas por perfil na Fase 1; esta função deve ser introduzida nas fases seguintes, antes das operações respetivas.
- `public/test_usuario.php` e `public/debug_login.php` já existiam e expõem informação de diagnóstico sensível. Não foram alterados nem apagados nesta fase; devem ser desativados ou removidos antes de produção.
- Não foi feito commit.

## Como repetir os testes

Com o Apache do XAMPP ativo:

1. Abrir `http://localhost/vacinacao-infantil-fase1a/vacinas/public/login.php`.
2. Entrar com uma conta local válida.
3. Confirmar o painel, os oito CSS, os textos e os ícones.
4. Reduzir a janela para telemóvel e testar abertura/fecho do menu.
5. Terminar sessão e confirmar o regresso ao login.

Executar:

```powershell
C:\xampp\php\php.exe -l public\painel.php
C:\xampp\php\php.exe -l public\login.php
C:\xampp\php\php.exe -l app\views\partials\head.php
C:\xampp\php\php.exe -l tests\verificar_classes.php
node --check public\assets\js\painel.js
node --check public\assets\js\login.js
C:\xampp\php\php.exe tests\verificar_classes.php
```

## Fase 2: base de dados, autenticação e painel

### Estado da fase

Fase 2 concluída. Foram revistos o schema e o seed, aplicada uma migração não destrutiva, criado o utilizador MySQL `vacinas_app`, concluído o login com Argon2id e bloqueio temporário, criada a mudança obrigatória da palavra-passe e ligadas as contagens reais do painel. A Fase 3 não foi iniciada.

### Problemas encontrados e corrigidos

- O schema anterior continha `CREATE DATABASE` e `USE`, não aceitava `NULL` em valores clínicos não fornecidos, não tinha `eliminado_em` em `contactos_faltosos`, usava índices separados em crianças e não tinha várias FK de autoria e restrições `CHECK`.
- O seed anterior tinha 36 doses, intervalos e idades mínimas inventados e associava incorretamente a Tetravalente à Hepatite A.
- O login não rehashava hashes antigos, não devolvia corretamente o estado de mudança obrigatória e a verificação de tentativas podia voltar a bloquear uma conta após um login válido.
- A aplicação ainda herdava `root` na configuração e não existia `vacinas_app`.
- A página `alterar_password.php` não existia, apesar de o login já redirecionar para ela.
- Durante a criação das permissões, o MariaDB reportou a tabela interna `mysql.db` como corrompida. A tabela foi reparada com `REPAIR TABLE mysql.db` e as permissões foram aplicadas depois.

### Ficheiros criados ou alterados na Fase 2

| Ficheiro | Alterações |
| --- | --- |
| `database/schema.sql` | Schema canónico com 16 tabelas e 2 vistas, InnoDB, `utf8mb4_unicode_ci`, IDs `BIGINT UNSIGNED`, FK `ON DELETE RESTRICT`, comentários em português e restrições `CHECK`. |
| `database/seed.sql` | 18 vacinas, 15 oportunidades e 37 doses. Valores clínicos desconhecidos em `NULL` e grupo Pentavalente/Tetravalente corrigido. |
| `database/update_schema_fase2.sql` | Migração não destrutiva para bases já existentes, sem remover tabelas ou registos. |
| `database/backup_antes_fase2.sql` | Cópia de segurança integral de `vacinas_db` antes da migração. Ficheiro ignorado pelo Git. |
| `config/database.php` | Utilizador da aplicação alterado de `root` para `vacinas_app`. |
| `config/config.local.example.php` | Exemplo com credenciais, `display_errors=false`, duração da sessão e parâmetros de bloqueio. |
| `config/config.local.php` | Configuração local atualizada para `vacinas_app`, nome provisório do posto e `display_errors=false`. Contém a palavra-passe local e não deve ser commitado. |
| `bin/criar_admin.php` | Criação apenas por CLI, validação de utilizador e palavra-passe, Argon2id, sem palavra-passe por defeito e sem a imprimir no final. |
| `app/core/helpers.php` | Login com tentativas consecutivas, bloqueio por `bloqueado_ate`, registo de falhas, rehash Argon2id, sessão e `deve_mudar_password`. Redirect interno mantido. |
| `app/core/sessao.php` | Expiração por inatividade configurável e comentários alinhados com o comportamento. |
| `public/login.php` | Validação de tipos, mensagens genéricas e redirect para mudança obrigatória no primeiro acesso. |
| `public/alterar_password.php` | Formulário com CSRF, validação no servidor, Argon2id, transação, auditoria sem hashes, regeneração da sessão e sem JavaScript inline. |
| `public/painel.php` | Contagens reais de crianças ativas não eliminadas e de vacinações registadas. |
| `.gitignore` | Ignora `config/config.local.php` e `database/backup_antes_fase2.sql`. |
| `tests/verificar_fase2.php` | Testes de schema, seed, permissões, login, bloqueio, rehash, sessão e fluxo HTTP com cookies e CSRF. |
| `docs/Report.md` | Este relatório. |

### Estado da base local

- Cópia de segurança criada antes de qualquer alteração: `database/backup_antes_fase2.sql`.
- A migração foi executada primeiro numa cópia temporária da base e só depois aplicada em `vacinas_db`.
- Base final: 16 tabelas, 2 vistas, 31 FK e 11 restrições `CHECK`; todas as FK usam `ON DELETE RESTRICT`.
- Seed importado: 18 vacinas, 37 doses e 15 oportunidades.
- Os dois utilizadores existentes e as 14 tentativas de login existentes foram preservados.
- Foi criado o administrador `admin.rangel` com Argon2id e `deve_mudar_password = TRUE`. A palavra-passe temporária não é guardada no relatório.
- `vacinas_app` tem `SELECT`, `INSERT` e `UPDATE` em `vacinas_db.*` e `DELETE` apenas em `vacinas_db.login_tentativas`.
- Os dados temporários dos testes foram removidos. As bases temporárias de validação também foram removidas.

### Decisões tomadas

- O bloqueio é consecutivo: um login válido repõe `tentativas_falhadas` e `bloqueado_ate`. O histórico append-only de `login_tentativas` não volta a bloquear a conta após esse sucesso.
- Falhas de utilizadores inexistentes ou inativos são registadas, mas a mensagem ao utilizador continua genérica.
- Hashes bcrypt existentes são rehashados para Argon2id automaticamente após um login válido.
- “Crianças registadas” conta apenas registos ativos e não eliminados. “Vacinações registadas” conta todas as linhas de `vacinacoes`, incluindo eventuais anulações registadas separadamente.
- A mudança obrigatória é validada no servidor, registada em auditoria sem guardar o hash e concluída com regeneração do ID da sessão e rotação do token CSRF.

### Testes executados

- `php -l` passou em `config.local.php`, `database.php`, `login.php`, `alterar_password.php`, `painel.php`, `helpers.php`, `sessao.php`, `criar_admin.php` e `tests/verificar_fase2.php`.
- `php tests/verificar_classes.php`: concluído sem falhas.
- `php tests/verificar_fase2.php`: 59 verificações concluídas sem falhas.
- Foram validadas as contagens 18/15/37, os valores clínicos `NULL`, o índice composto de crianças, os comentários das tabelas, as 31 FK e as permissões mínimas.
- O fluxo HTTP real validou CSRF inválido, login, primeiro acesso, password curta, confirmação diferente, mudança obrigatória, auditoria, redirect externo rejeitado, painel autenticado, contagens 1/1 com dados temporários, logout e expiração por inatividade.
- O painel autenticado respondeu HTTP 200 sem `Warning`, `Notice` ou `Fatal error`.
- `database/schema.sql` não contém `CREATE DATABASE`, `USE`, `DROP`, `DELIMITER`, trigger ou procedimento.
- Nenhum PHP em `public/` contém script inline ou atributos `onclick`, `onchange`, `onsubmit`, `onload` ou `onerror`.
- `git diff --check` continua a assinalar espaços no fim de linhas preexistentes em `app/core/helpers.php`; não foram alterados fora do escopo funcional da Fase 2.

### Pendências e limitações

- O `root` local continua sem palavra-passe e o phpMyAdmin não foi convertido para autenticação por cookie. Esta configuração exige decisão e validação separadas para não quebrar o XAMPP.
- `config/config.local.php` já estava a ser seguido pelo Git. A entrada no `.gitignore` não o remove do índice; deve ser tratada antes de qualquer commit.
- `public/test_usuario.php` e `public/debug_login.php` já existiam e expõem diagnóstico. Devem ser desativados ou removidos antes de produção.
- A validação HTTP e de sessão foi automatizada, mas não foi produzida uma nova captura visual no navegador.
- Não foi feito commit.

## Fase A — endurecimento

### Feito

- `config/config.local.php` foi retirado do índice Git e continua ignorado e presente localmente.
- `public/debug_login.php`, `public/test_usuario.php` e os dois scripts públicos de criação de administrador foram desativados com HTTP 403 antes de qualquer execução.
- Foi criado `docs/Seguranca_Local.md` com os passos manuais para definir palavra-passe do `root` e ativar autenticação por cookie no phpMyAdmin. Nenhuma credencial foi alterada automaticamente.

### Testes

- `php -l` passou nos quatro scripts desativados e em `tests/verificar_faseA.php`.
- `php tests/verificar_faseA.php`: 16 verificações sem falhas.
- `/database/`, `/config/`, `/arquivo_cloudflare/`, `/.git/`, `/app/`, `/bin/` e `/tests/` responderam HTTP 403.

### Pendências

- O utilizador deve executar os passos manuais de `docs/Seguranca_Local.md`.
- Não foi feito commit.

## Fase B — crianças e encarregados

### Feito

- Foi criado o menu Crianças sem etiqueta de desenvolvimento e as páginas de pesquisa, cadastro, edição, detalhe e eliminação lógica.
- A pesquisa cobre nome, processo, cartão e telefone normalizado do encarregado, com paginação.
- O número de processo é gerado em PHP dentro de transação, com prefixo configurável e nova tentativa em colisão.
- O cadastro avisa sobre duplicados e permite continuar ou abrir o registo existente, sem fusão.
- Encarregados e bairros opcionais são geridos por formulário; telefones ficam apenas com dígitos.
- Criações e alterações de crianças e encarregados registam auditoria. Perfis e CSRF são verificados no servidor.

### Testes

- `php -l` e `node --check` passaram nos ficheiros novos e alterados.
- `php tests/verificar_classes.php`: concluído sem falhas.
- `php tests/verificar_faseB.php`: 44 verificações HTTP e de base de dados sem falhas.
- Foram validados os quatro perfis, CSRF inválido, duplicados, pesquisas, paginação, auditoria e eliminações lógicas.

### Pendências

- O botão “Abrir atendimento” será ligado à página real na Fase D.
- O histórico da criança será preenchido na Fase F.
- Não foi feito commit.

## Fase C — gestão de funcionários

### Feito

- Foi criada a área independente `public/gestao/`, com cookie `VACINAS_GESTAO`, login próprio e acesso exclusivo a administradores ativos.
- Foram implementados cadastro, edição de nome/utilizador/perfil, desativação, reativação e reposição de palavra-passe.
- A palavra-passe temporária é gerada com Argon2id, obriga a mudança no primeiro acesso e é mostrada uma única vez, sem entrar na auditoria.
- Criações, alterações, desativações, reativações e reposições registam auditoria. A aplicação principal não contém ligações para `/gestao/`.
- Foi criado `docs/Gestao_Producao.md` com as restrições manuais por IP/VPN e segundo factor.

### Testes

- `php -l` passou em todos os ficheiros PHP novos e alterados.
- `php tests/verificar_classes.php`: concluído sem falhas.
- `php tests/verificar_faseC.php`: 57 verificações sem falhas.
- Foram validados cookies distintos, login próprio, perfis, CSRF, criação, edição, auditoria, desativação/reativação, reposição, mudança obrigatória, bloqueio e logout independente.

### Pendências

- A restrição por IP e o segundo factor de produção devem ser configurados manualmente no alojamento.
- Não foi feito commit.

## Fase D — atendimento e fila

### Feito

- O menu Atendimento deixou de ter etiqueta de desenvolvimento e liga à página real para recepção, enfermagem e administração.
- A recepção pesquisa crianças, abre atendimentos com vacinas pretendidas e vê apenas disponibilidade sim/não, sem lotes ou quantidades.
- Atendimentos sem stock nascem como `sem_vacina` e exigem motivo; cancelamento também exige motivo. As transições são validadas no servidor.
- Enfermagem consulta a fila JSON mínima, atualizada por `fila.js` a cada 12 segundos, e inicia atendimentos guardando o profissional e a hora.
- O JSON passou a agrupar vacinas em PHP, preservando nomes que contêm vírgulas.
- Foi corrigido o encoding de cinco nomes do catálogo através de `database/corrigir_encoding_catalogo.sql`, sem alterar IDs ou valores clínicos.

### Testes

- `php -l` passou nos ficheiros PHP alterados e `node --check` passou em `fila.js`.
- `php tests/verificar_faseD.php`: 108 verificações HTTP e de base de dados sem falhas.
- Foram validados os quatro perfis, CSRF, stock disponível e vencido, motivo obrigatório, transições proibidas, auditoria, JSON mínimo, escaping e ausência de lotes/saldos na recepção.
- Regressões aprovadas: `verificar_classes.php`, Fase 2 com 59 verificações, Fase B com 44 e Fase C com 57.

### Pendências

- A conclusão do atendimento e o registo de vacinação serão ligados na Fase F.
- Não foi feito commit.

## Fase E — stock e movimentos

### Feito

- O menu Stock liga à consulta real para enfermeiro, responsável de stock e administrador; a recepção continua sem acesso.
- Foram implementados cadastro e edição de lotes com número normalizado, vacina imutável e auditoria por campo.
- O saldo é calculado apenas pela vista `stock_saldo_lotes`; a quantidade inicial origina um movimento de entrada.
- Entradas, perdas e ajustes são append-only, exigem quantidade positiva e não permitem saldo negativo. Perdas e ajustes exigem motivo.
- A saída por vacinação é rejeitada no formulário manual e ficará reservada à transação da Fase F.
- Os alertas usam `stock_validade_aviso_dias` (predefinição 30 dias) e `stock_minimo_doses` (predefinição 5 doses), documentados no seed e em `database/update_faseE.sql`.
- O responsável de stock escreve; enfermeiro e administrador consultam. Foram corrigidas consultas à vista e um conflito de variável no menu comum.

### Testes

- `php -l` passou no núcleo, páginas, endpoints, painel e teste da fase.
- `php tests/verificar_faseE.php`: 86 verificações HTTP e de base de dados sem falhas.
- Foram validados quatro perfis, CSRF, normalização, duplicados, auditoria, imutabilidade da vacina, cinco movimentos válidos, motivos, quantidades, saldo negativo, alertas, menu e ausência de inline.
- Regressões aprovadas: verificador de classes, Fase 2 com 59 verificações, Fase B com 44, Fase C com 57 e Fase D com 108.

### Pendências

- A saída automática por vacinação e a anulação sem devolução automática de stock serão implementadas na Fase F.
- Não foi feito commit.

## Fase F — vacinação e histórico

### Feito

- O menu Vacinação liga à área real para enfermeiro e administrador; recepção e responsável de stock continuam sem acesso.
- A vacinação do posto usa uma transação única com bloqueio do atendimento, criança e lote, validação de saldo, vacina, validade, calendário e duplicado.
- O lote sugerido é o que expira primeiro entre os válidos e com saldo. A saída de uma dose fica ligada à vacinação e o atendimento só termina quando todas as vacinas pretendidas têm dose válida.
- Doses externas não exigem atendimento nem lote e não movimentam stock. O histórico cronológico marca anuladas e a anulação exige motivo, sem devolução automática de stock.

### Testes

- `php -l` passou nos ficheiros PHP alterados e `node --check` passou em `vacinacao.js`.
- `php tests/verificar_faseF.php`: 68 verificações HTTP e de base de dados sem falhas.
- Foram validados quatro perfis, CSRF, FEFO, exclusão de lotes inválidos, duplicados, atendimento múltiplo, dose externa, anulação, histórico e ausência de inline.
- Dois pedidos simultâneos à última dose produziram exatamente um sucesso, uma vacinação e saldo final zero.
- A validação repetida encontrou e corrigiu um snapshot obsoleto da vista agregada: o saldo transacional passou a ser somado diretamente sobre os movimentos com leitura de bloqueio. O redirect também passou a usar o `crianca_id` selecionado pelo atendimento.
- Regressões aprovadas: verificador de classes, Fase 2 com 59 verificações, Fase B com 44, Fase C com 57, Fase D com 108 e Fase E com 86.

### Pendências

- Os valores clínicos continuam sujeitos a validação pela equipa clínica.
- Não foi feito commit.

## Fase G — faltosos e calendário

### Feito

- O menu Faltosos liga à listagem real para recepção, enfermagem e administração; o responsável de stock continua sem acesso.
- O calendário é calculado no momento da consulta, nunca guardado. Usa nascimento mais idade ideal, ignora regras clínicas NULL, aplica idade mínima, janela, intervalo e dose anterior quando existem.
- Uma dose de Pentavalente ou Tetravalente cumpre o grupo alternativo. Doses anuladas não contam e voltam a aparecer como pendentes.
- A pesquisa cobre criança, processo, cartão, encarregado e telefone normalizado, com filtros e paginação. A ficha da criança passou a mostrar as doses pendentes.
- Contactos manuais por telefone, SMS e WhatsApp têm data, resultado, notas, autor e eliminação lógica. Não há envio automático.
- O aviso “a vencer” usa `faltosos_aviso_dias`, predefinição operacional de 30 dias, aplicada por `database/update_faseG.sql` e documentada no seed.

### Testes

- `php -l` passou no núcleo, páginas, endpoints, partial, painel e teste da fase.
- `php tests/verificar_faseG.php`: 89 verificações HTTP e de base de dados sem falhas.
- Foram validados quatro perfis, sessão anónima, CSRF, datas, quatro estados, regras NULL, grupo alternativo, anulações, pesquisas, paginação, escaping, três canais, dados inválidos e eliminação lógica.
- Regressões aprovadas: verificador de classes, Fase A com 16, Fase 2 com 59, Fase B com 44, Fase C com 57, Fase D com 108, Fase E com 86 e Fase F com 68.

### Pendências

- O PDF `docs/Especificacao_Aplicacao_Final.pdf` já não existe no workspace. A fase foi validada contra os requisitos enviados, o schema e o seed canónicos; o documento deve ser reposto para conferência documental.
- Os valores clínicos continuam sujeitos a validação pela equipa clínica.
- Não foi feito commit.

## Fase H — relatórios e 2.ª via

### Feito

- O menu Relatórios liga à página real para enfermeiro, responsável de stock e administrador; recepção continua sem acesso.
- Foram implementados relatórios de saídas sem vacina, vacinações, stock e validades, crianças faltosas com contactos e auditoria, com filtros de data, vacina e enfermeiro conforme o caso.
- A exportação CSV usa UTF-8 com BOM, delimitador `;`, cabeçalhos em português e proteção contra fórmulas. HTML e CSV partilham o mesmo mapa de permissões.
- A 2.ª via do cartão mostra apenas vacinações válidas, inclui doses externas e permite impressão a enfermeiro e administrador; recepção consulta sem botão de impressão.
- O painel passou a mostrar aguardando, em atendimento e concluídos do dia com valores reais e já não apresenta “Cálculo ainda não disponível”.

### Testes

- `php -l` passou no núcleo, páginas, painel, ficha da criança e teste da fase; `node --check` passou em `cartao.js`.
- `php tests/verificar_faseH.php`: 116 verificações HTTP e de base de dados sem falhas.
- Foram validados os cinco relatórios, permissões por perfil em HTML e CSV, filtros, CSV seguro, 2.ª via sem anuladas, impressão condicionada, escaping, CSP, ausência de inline e contagens reais do painel.
- O verificador de classes foi atualizado para deixar de exigir a classe antiga `em-breve`, agora que todos os módulos estão implementados.
- Regressões aprovadas: verificador de classes, Fase A com 16, Fase 2 com 59, Fase B com 44, Fase C com 57, Fase D com 108, Fase E com 86, Fase F com 68 e Fase G com 89.

### Pendências

- O PDF `docs/Especificacao_Aplicacao_Final.pdf` continua ausente; a validação usa os requisitos enviados, o schema e o seed.
- Os valores clínicos continuam sujeitos a validação pela equipa clínica.
- Não foi feito commit.

## Fase I — fecho, demonstração e validação final

### Feito

- Foi criado `bin/dados_demo.php`, executável apenas por linha de comandos e somente com `--gerar` ou `--limpar`. O acesso direto pela web devolve HTTP 403.
- O cenário fictício usa o marcador `[DEMO]` e cobre crianças, encarregados, lotes, atendimentos, vacinações, contactos e os quatro perfis. As credenciais são aleatórias, usam Argon2id e não são escritas neste relatório.
- A limpeza é lógica e idempotente. Foram desativados 6 crianças, 6 encarregados, 4 lotes e 4 utilizadores; 2 vacinações adicionais foram anuladas e 3 contactos foram eliminados logicamente. Movimentos, vacinações, anulações e auditoria foram preservados por serem históricos append-only.
- Depois da limpeza, os dados demo não entram na pesquisa ativa, atendimento, fila, stock operacional, relatório sem vacina ou 2.ª via. A ficha histórica continua acessível e a conta demo desativada não autentica.
- Foi concluída a matriz de permissões das páginas e endpoints para recepção, enfermeiro, responsável de stock e administrador. A sessão principal não abre a gestão; o cookie `VACINAS_GESTAO` é independente.
- Não resta a etiqueta `Em desenvolvimento` no código ativo dos módulos ou dos menus. As ocorrências em `tests/verificar_faseI.php` são asserções que confirmam essa ausência.
- A validação no navegador encontrou e corrigiu quatro defeitos: erro JavaScript no login da gestão, ligação proibida no relatório de stock, overflow horizontal da 2.ª via em telemóvel e foco não restaurado ao fechar o menu móvel.
- O menu móvel foi revalidado com abertura, fecho por botão e Escape, navegação e restauração do foco. A 2.ª via passou a caber no viewport móvel, mantendo a tabela histórica com scroll interno.

### Testes

- `php -l` passou nos ficheiros PHP alterados nas fases e `node --check` passou nos ficheiros JavaScript alterados.
- `php tests/verificar_faseI.php`: **709 verificações, 0 falhas**.
- Regressões aprovadas:
  - `tests/verificar_classes.php`: sem falhas;
  - Fase 2: 59 verificações;
  - Fase A: 16 verificações;
  - Fase B: 44 verificações;
  - Fase C: 57 verificações;
  - Fase D: 108 verificações;
  - Fase E: 86 verificações;
  - Fase F: 68 verificações, incluindo o teste de concorrência da última dose;
  - Fase G: 89 verificações;
  - Fase H: 116 verificações.
- Total: **1.352 verificações numeradas aprovadas**, sem contar o verificador de classes.
- Foram exercitados pedidos HTTP reais com sessões e CSRF, incluindo acessos anónimos, perfis autorizados e não autorizados, redirecionamentos/403 esperados, logout, cookies independentes e fluxos de escrita.
- Todos os CSS, fontes, ícones e JavaScript inventariados responderam HTTP 200; respostas 304 observadas no navegador correspondem apenas à revalidação normal de cache.
- A validação final no navegador não encontrou erros de JavaScript na consola, pedidos 404, respostas 403 inesperadas nem bloqueios CSP. Foram verificados os quatro perfis autenticados, a fila JSON, o download CSV, o login da gestão e o menu móvel.
- A fila JSON continua a expor apenas os cinco campos mínimos e não inclui lotes, saldos ou quantidades.
- A limpeza final do cenário demo foi executada e uma segunda execução de `--limpar` confirmou a idempotência.

### Pendências

- `docs/Especificacao_Aplicacao_Final.pdf` não existe no workspace. A implementação foi validada contra os requisitos enviados, o schema e o seed canónicos; o PDF deve ser reposto para conferência documental.
- A ferramenta de navegador não conseguiu produzir capturas devido a `NATIVE_BROWSER_VIEWPORT_UNAVAILABLE`. A verificação visual foi feita por DOM, CSSOM, consola e rede, em desktop de 1440×900 e telemóvel de 390×844, sem capturas para anexar.
- Os nomes auxiliares do seletor nativo de datas podem seguir o idioma do sistema operativo, embora o documento esteja marcado como `pt-AO`.
- `git diff --check` continua a assinalar espaços no fim de quatro linhas de `app/core/helpers.php` (41, 47, 107 e 112). Não foram alteradas essas linhas no fecho.
- Os passos manuais de segurança local e de produção ainda não foram executados pelo utilizador.
- Não foi feito commit.

## Estado final dos módulos

| Módulo | Página | Perfis e alcance |
| --- | --- | --- |
| Painel | `public/painel.php` | Todos os perfis autenticados; contagens reais. |
| Crianças | `public/criancas.php` | Recepção pesquisa, cadastra, edita e elimina logicamente; enfermeiro e administrador consultam. |
| Atendimento | `public/atendimento.php` | Recepção abre/cancela/regista sem vacina; enfermeiro vê a fila e inicia; administrador tem acesso. |
| Vacinação | `public/vacinacao.php` | Enfermeiro regista e anula; administrador consulta/anula. |
| Faltosos | `public/faltosos.php` | Recepção, enfermeiro e administrador; responsável de stock sem acesso. |
| Stock | `public/stock.php` | Responsável de stock escreve; enfermeiro e administrador consultam; recepção sem acesso. |
| Relatórios | `public/relatorios.php` | Enfermeiro: parcial; responsável de stock: sem-vacina e stock; administrador: todos; recepção sem acesso. |
| 2.ª via do cartão | `public/cartao_vacinas.php` | Recepção consulta; enfermeiro e administrador consultam e imprimem; apenas vacinações válidas. |
| Gestão de funcionários | `public/gestao/` | Sessão própria e exclusivamente administrador ativo; sem ligação na aplicação principal. |

Os itens sem permissão são ocultados ou apresentados sem ligação, e a autorização continua a ser verificada no servidor mesmo quando a página é aberta por URL direto.

## Passos manuais que ficam a cargo do utilizador

1. Executar `docs/Seguranca_Local.md`: fazer a cópia de segurança, definir uma palavra-passe forte para o `root` do MariaDB e configurar `auth_type = cookie` e `AllowNoPassword = false` no phpMyAdmin. A aplicação deve continuar a usar `vacinas_app`.
2. Confirmar o login da aplicação e do phpMyAdmin depois da alteração, guardar a credencial administrativa em local seguro e testar o restauro da cópia de segurança.
3. Antes de produção, aplicar as restrições de `docs/Gestao_Producao.md`: IP autorizados e/ou VPN, segundo fator ou credencial forte para `/gestao/`, TLS válido e política de conservação de logs.
4. Fazer validar pela equipa clínica todos os intervalos, janelas, idades mínimas e demais valores `NULL` do calendário. Enquanto isso, o cálculo é indicativo e ignora essas regras, conforme pedido.
5. Confirmar o nome oficial do posto e o apelido da Dra. Virgínia antes de os fixar na interface; nenhum foi inventado.
6. Validar a legislação angolana de proteção de dados, o período de conservação, o alojamento e a política de cópias de segurança.
7. Repor `docs/Especificacao_Aplicacao_Final.pdf` e comparar o resultado com o documento original.
8. Manter `config/config.local.php` fora do Git. O ficheiro local existe, está ignorado e foi retirado do índice; não foi feito commit.

## Como repetir a demonstração e os testes

Com o Apache e o MariaDB do XAMPP ativos, a partir da raiz do projeto:

```powershell
C:\xampp\php\php.exe bin\dados_demo.php --gerar
```

## Persistência da sessão — 25/11/2026

### Diagnóstico antes da correção

- O redirect inesperado para o login não foi reproduzido neste XAMPP: após autenticar, dez pedidos HTTP seguidos ao painel, módulos e fila devolveram HTTP 200 e mantiveram o mesmo ID de sessão. Portanto, não foi possível atribuir o incidente reportado a um pedido específico.
- Na configuração anterior, o cookie principal era `PHPSESSID`, host-only, com `Path=/`, `HttpOnly`, `SameSite=Strict`, sem `Secure` em HTTP e sem `Expires`/`Max-Age`. O caminho global podia permitir que outra aplicação PHP no mesmo `localhost` substituísse o cookie com o mesmo nome. A colisão não ocorreu durante a reprodução local.
- A gestão já usava o nome de cookie distinto `VACINAS_GESTAO`.
- O `php.ini` do XAMPP tinha `session.gc_maxlifetime=1440`, inferior aos 1800 segundos de inatividade configurados pela aplicação. `C:\xampp\tmp` existe e permite escrita. O strict mode global estava desligado, embora a aplicação já o ativasse antes de iniciar a sessão.
- Não existe `bootstrap.php`; o início comum é feito por `iniciar_sessao_segura()`, antes da saída HTML. A chamada é idempotente por pedido. A regeneração do ID ocorre no login; destruição ocorre no logout e na expiração.

### Correção

- O cookie passa a usar o caminho público calculado para a aplicação (`/vacinacao-infantil-fase1a/vacinas/public/`), em vez de `/`, mantendo o domínio host-only.
- `Secure` é ativado apenas para HTTPS, detetado por `HTTPS` e `X-Forwarded-Proto`. `HttpOnly` e `SameSite=Strict` foram mantidos; `session.use_strict_mode` fica ativo.
- O limite de recolha do PHP é alinhado com os prazos configurados. `ultimo_acesso` é renovado em pedidos autenticados válidos; a expiração normal continua a ser de 30 minutos por inatividade.
- A opção “Manter sessão” grava a preferência e emite cookie persistente pelo prazo configurado (7 dias por defeito). O prazo de inatividade persistente também é aplicado; a palavra-passe não é guardada.
- A gestão continua a usar `VACINAS_GESTAO`, separado de `PHPSESSID`. O ID é regenerado no login e CSRF não foi desativado.
- A migração idempotente `database/update_sessao.sql` foi aplicada à base local com `SET NAMES utf8mb4`. Foram confirmados `session_lifetime=1800` e `session_remember_lifetime=604800`; a migração não altera valores já existentes.
- `database/seed.sql` inclui os dois limites e os IDs das configurações foram ajustados para não colidir com as novas entradas.

### Verificação

- `php -l`: 86 ficheiros PHP sem erros.
- `tests/verificar_classes.php`, `tests/verificar_fase2.php` e fases A a I passaram sem falhas; a Fase 2 teve 59 verificações, e as fases A–I tiveram, respetivamente, 16, 44, 57, 108, 86, 68, 89, 116 e 726 verificações.
- `tests/verificar_sessao.php`: 13 verificações sem falhas, incluindo dez pedidos seguidos à aplicação e à gestão com ID estável, atualização do último acesso, expiração simulada, logout, opção “Manter sessão” e negação de acesso à gestão para enfermeiro.
- No navegador, em largura desktop e 390×844, login, refresh e navegação entre painel, crianças, atendimento, vacinação, stock, relatórios e 2.ª via responderam sem voltar ao login. A área `/gestao/` manteve a sessão após quatro refreshes. O nome da instituição apareceu no login, no painel e na gestão. Não foram observados erros de consola durante a verificação válida.
- Os utilizadores, a criança e o encarregado sintéticos criados para a verificação no navegador foram removidos.
C:\xampp\php\php.exe tests\verificar_classes.php
C:\xampp\php\php.exe tests\verificar_fase2.php
C:\xampp\php\php.exe tests\verificar_faseA.php
C:\xampp\php\php.exe tests\verificar_faseB.php
C:\xampp\php\php.exe tests\verificar_faseC.php
C:\xampp\php\php.exe tests\verificar_faseD.php
C:\xampp\php\php.exe tests\verificar_faseE.php
C:\xampp\php\php.exe tests\verificar_faseF.php
C:\xampp\php\php.exe tests\verificar_faseG.php
C:\xampp\php\php.exe tests\verificar_faseH.php
C:\xampp\php\php.exe tests\verificar_faseI.php
C:\xampp\php\php.exe bin\dados_demo.php --limpar
```

O gerador mostra as credenciais fictícias apenas na execução de demonstração. Não as copiar para este relatório. O teste da Fase I gera e limpa o próprio cenário.

## Manutenção — menu móvel e nome da instituição

### Botão de fecho da barra lateral

- O botão branco com X foi removido de `public/painel.php`, `app/views/partials/modulo_inicio.php` e `app/views/partials/gestao_inicio.php`. Estes são os três layouts ativos da aplicação, incluindo `/gestao/` e a 2.ª via do cartão.
- O botão hambúrguer do topo foi mantido. Em desktop, `.botao-menu` continua oculto e a barra lateral permanece sempre visível; não existe qualquer botão de fecho na barra.
- `public/assets/js/painel.js` e `public/assets/js/modulos.js` usam `addEventListener`. O menu fecha ao clicar no fundo escurecido, ao pressionar Escape e ao escolher um item.
- Ao abrir, o foco passa ao primeiro item do menu. Ao fechar, volta ao hambúrguer. `aria-expanded` e o rótulo acessível do hambúrguer são atualizados.
- Não existia uma regra CSS exclusiva do botão removido em `public/assets/css/modulos.css`; por isso, esse ficheiro não precisou de alterações. Nenhum CSS original foi alterado.
- A classe genérica `.icone-x` e o ficheiro `public/assets/icons/x.svg` permanecem em `icones.css` porque são recursos originais genéricos, não referências ao botão removido. O ícone já não é usado em nenhum layout ativo.
- A pesquisa global pelos identificadores de classe, ID, rótulo acessível e seletores JavaScript associados ao botão removido não encontra ocorrências fora desta descrição histórica.

### Nome oficial da instituição

- O nome oficial passou a ser **Centro Médico Hospitalar Maria de Fátima**.
- `app/core/helpers.php` agora expõe `nome_posto()`, que lê exclusivamente `configuracoes.nome_posto` através de PDO e não possui fallback com texto fixo.
- O título das páginas, login principal, login da gestão, painel, módulos, barra lateral da gestão e cabeçalho da 2.ª via passaram a usar o helper.
- O JavaScript legado em `public/assets/js/app.js` e `public/assets/js/ui/login.js` deixou de conter o nome fixo e recebe-o da store local `configuracoes`, chave `nome_posto`.
- `database/seed.sql`, `config/config.local.example.php`, `config/config.local.php` e `REPLICAR_PAINEL.md` foram atualizados. A chave `nome_posto` foi mantida.
- Foi criado `database/update_nome_hospital.sql`, com `SET NAMES utf8mb4` e um `UPDATE` condicionado e idempotente. Não cria nem elimina tabelas e não toca noutros dados.
- A migração foi executada duas vezes na base local. A segunda execução não alterou o valor. A verificação `HEX(valor)` confirmou os bytes UTF-8 `C3A9` em “Médico” e `C3A1` em “Fátima”.
- Os relatórios HTML já mostravam o nome através do título/lateral. Os CSV não continham metadado da instituição nem o nome antigo e não foram alterados. `bin/dados_demo.php` também não continha o nome antigo.

### Ocorrências de “posto” deixadas por decidir

Apenas o nome completo da instituição foi substituído. As restantes ocorrências abaixo foram mantidas, conforme pedido.

#### Textos visíveis ou documentação operacional

| Ficheiro e linha | Ocorrência mantida |
| --- | --- |
| `app/core/modulos.php:171` | `Administrador do posto` |
| `app/views/partials/historico_vacinacao.php:19` | Origem da vacinação: `Posto` |
| `app/core/relatorios.php:281` | Origem da vacinação no CSV: `Posto` |
| `public/painel.php:80` | `Administrador do posto` |
| `public/painel.php:148` | `Hoje no posto` |
| `public/gestao/index.php:59` | `Funcionários do posto` |
| `public/vacinacao.php:47` | `vacinações feitas fora do posto` |
| `public/cartao_vacinas.php:95` | Origem da vacinação: `Posto` |
| `public/relatorios.php:152` | Origem da vacinação: `Posto` |
| `public/assets/js/login.js:31` | `administrador do seu posto` |
| `public/assets/js/ui/login.js:9` | `administrador do seu posto` |
| `public/assets/js/ui/painel.js:84` | `Hoje no posto` |
| `public/assets/js/db/repositorio-base.js:141` | Mensagem `Sessão sem posto` |
| `README.md:3` | `postos médicos` |
| `README.md:40` | `nome do posto` |
| `REPLICAR_PAINEL.md:22` | `Levantamento no posto` e `visita ao posto` |
| `REPLICAR_PAINEL.md:29` | `Todo o posto tem Wi-Fi` |
| `REPLICAR_PAINEL.md:40` | `três postos de trabalho` |
| `REPLICAR_PAINEL.md:87` | `visita ao posto` e `nome oficial do posto` |
| `REPLICAR_PAINEL.md:101` | `Hoje no posto` e `Nome do posto` |
| `database/README.md:25` | `Atendimentos no posto` |
| `docs/Seguranca_Local.md:3` | `administrador do posto` |
| `docs/Seguranca_Local.md:63` | `restrição de IP do posto` |
| `docs/Report.md:168` | Nota histórica `nome provisório do posto` |
| `docs/Report.md:335` | Nota histórica `vacinação do posto` |
| `docs/Report.md:467` | Pendência histórica `nome oficial do posto` |

#### Identificadores técnicos, enums e classes preservados

| Ficheiro e linha | Ocorrência técnica mantida |
| --- | --- |
| `app/core/helpers.php:69,84` | Função e chave `nome_posto()` / `nome_posto` |
| `app/views/partials/head.php:21` | Chamada `nome_posto()` |
| `app/views/partials/modulo_inicio.php:10,23` | Variável e classe `marca-posto` |
| `app/views/partials/gestao_inicio.php:19` | Classe `marca-posto` |
| `app/core/vacinacao.php:112,164` | `registrar_vacinacao_posto` e enum `posto` |
| `public/login.php:95` | Chamada `nome_posto()` |
| `public/alterar_password.php:109` | Chamada `nome_posto()` |
| `public/cartao_vacinas.php:53` | Classe `cartao-documento-posto` |
| `public/painel.php:75,98` | Variável e classe `marca-posto` |
| `public/vacinacao_form.php:35,55` | Função e campo técnico `origem=posto` |
| `public/vacinacao_registar.php:3,16,17` | Comentário, enum e função |
| `public/assets/css/layout.css:17` | Classe `.marca-posto` |
| `public/assets/css/modulos.css:112` | Classe `.cartao-documento-posto` |
| `public/assets/js/app.js:10,13,35,48` | Chave `nome_posto` e campos `postoId`/`postoNome` |
| `public/assets/js/ui/painel.js:53` | Classe e campo `marca-posto` / `postoNome` |
| `public/assets/js/sessao.js:2,27` | Campo técnico `postoId` |
| `public/assets/js/db/esquema.js:8,30-32` | Store `postos` e `posto_id` |
| `public/assets/js/db/repositorio-base.js:3,10,32,35,80,166` | Campo técnico `posto_id`/`postoId` |
| `public/assets/js/db/numero-processo.js:1,8,9,26,27` | Prefixo e parâmetros `codigoPosto` |
| `public/assets/js/autenticacao.js:2` | Comentário técnico `posto/dispositivo` |
| `config/config.local.example.php:17` | Chave `nome_posto` |
| `config/config.local.php:17` | Chave `nome_posto` |
| `database/seed.sql:8` | Chave `nome_posto` |
| `database/update_nome_hospital.sql:8` | Chave `nome_posto` |
| `database/schema.sql:111,200,227,234` | Comentários e enum `origem=posto` |
| `database/update_schema_fase2.sql:55,74,78` | Enum e comentários técnicos |
| `database/backup_antes_fase2.sql:77,140,212,535` | Comentários e enum do backup |
| `database/seeds/0001_catalogo.sql:2-10` | Tabela `postos` e `posto_id` da versão Cloudflare |
| `bin/dados_demo.php:462,473,476` | Variável e enum técnico `posto` |
| `tests/verificar_faseI.php:150,159,239` | Chave, helper e enum técnico |
| `tests/verificar_faseF.php:8,14,156,158,160,162,165,177,181,183,234` | Função de teste e enum `posto` |
| `tests/verificar_faseH.php:127,134` | Enum técnico `posto` |
| `tests/verificar_fase2.php:325` | Enum técnico `posto` |
| `REPLICAR_PAINEL.md:18,59,63,78` | Chave, `posto_id` e enum `origem=posto` |
| `database/README.md:48` | Chave `configuracoes.nome_posto` |
| `docs/Report.md:54` | Chave `configuracoes.nome_posto` |
| `arquivo_cloudflare/migrations/0001_esquema_inicial.sql:4,8,10,13,14,28,39,42,45,62-66,265` | Modelo técnico arquivado `postos`/`posto_id`/enum |
| `arquivo_cloudflare/tests/esquema-seed.test.mjs:53,56-58,67,68,72,84-86,88,90` | Identificadores técnicos de teste |
| `arquivo_cloudflare/tests/ui.test.mjs:56,62` | Texto e campo técnico da versão arquivada |
| `arquivo_cloudflare/tests/repositorio-base.test.mjs:73` | Enum técnico `posto` |

Foram excluídos falsos positivos como `repostos` e `composto`, que não são a palavra `posto`.

### Verificação final desta tarefa — 25/11/2026

- `database/update_nome_hospital.sql` foi limitado ao `valor` da chave `nome_posto`; preserva a descrição e os restantes dados. A migração foi executada duas vezes e o valor resultante foi verificado como **Centro Médico Hospitalar Maria de Fátima**. `HEX(valor)` confirmou os bytes UTF-8 dos acentos.
- O login da gestão mostra o nome obtido por `nome_posto()`, mantendo o texto funcional “Funcionários do posto”.
- Os controladores JS atualizam `aria-expanded`, fecham por fundo, Escape e item, e devolvem o foco ao hambúrguer depois da ação de navegação. Ao passar para desktop, o estado móvel é fechado sem esconder a barra lateral.
- O módulo alternativo `public/assets/js/ui/painel.js` tinha uma chamada `h()` sem o fecho do elemento de marca; o erro de sintaxe foi corrigido e o módulo foi importado com sucesso no navegador.
- Pesquisa global pelo nome antigo e pelos identificadores/seletores do botão X removido: sem ocorrências. A busca inclui HTML, classes, IDs, `aria-label` e seletores JS.
- Não foram alterados os CSS originais; `public/assets/css/modulos.css` não contém regra exclusiva do botão X que tenha ficado sem uso.
- `php -l`: 85 ficheiros PHP verificados sem erros.
- Testes: `tests/verificar_classes.php`, `tests/verificar_fase2.php` e fases A a I concluídos sem falhas (59, 16, 44, 57, 108, 86, 68, 89, 116 e 726 verificações, respetivamente).
- No navegador, em 1440×900 e 390×844, foram verificados o login principal, login e área de gestão, painel e 2.ª via. Não há botão X; o menu móvel abre e fecha pelo fundo e Escape, mantém foco e `aria-expanded`, e a barra permanece visível no desktop. O nome surge no login, barra lateral, painel, gestão e cartão. Sem erros na consola.
- Os registos temporários usados para validar as páginas autenticadas no navegador foram removidos.
