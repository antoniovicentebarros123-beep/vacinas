# INSTRUÇÕES PARA A IDE: mudança de stack para PHP + MySQL/MariaDB (XAMPP)

Vais continuar um projeto académico que já tem trabalho feito, mas com MUDANÇA DE STACK. Não conheces o código. O primeiro passo é LER e fazer o inventário, não escrever código.

## 0. Regras de trabalho

- Responde sempre em português de Angola. Comentários no código em português.
- Trabalho em Windows (PowerShell). Dá os comandos para PowerShell.
- Trabalhas por ETAPAS (secção 10). No fim de cada etapa dizes exatamente o que NÃO verificaste e esperas a minha aprovação.
- Não afirmes que algo está feito sem mostrar o ficheiro e como o testaste. Separa sempre em três categorias: "implementado e testado", "só esquema/desenho" e "não existe".
- Se falhares duas vezes seguidas o mesmo erro, PÁRA, diz-me o que tentaste e espera. Nunca alteres um teste ou a base de dados para esconder uma falha: o teste adapta-se ao esquema, e nunca o contrário.
- Não inventes valores clínicos nem nomes de bairros ou comunas. Onde faltar informação, marca "a validar" ou "a confirmar na visita ao posto".
- Nunca apagues código antigo sem eu aprovar. Mostra o diff ou a lista antes de qualquer remoção.
- Este projeto é defendido por 3 estudantes. O código deve ser simples, legível e explicável. Prefere clareza a sofisticação.
- Dados de saúde de crianças são sensíveis: nunca em logs, nunca palavras-passe nem segredos no repositório.
- Não cries ficheiros de anexos tipo "pasted-text.txt".

## 1. O projeto e o prazo

Tema (não alterar): "DESENVOLVIMENTO DE UMA PLATAFORMA WEB PARA A GESTÃO DE DADOS FAMILIARES E APOIO AOS SERVIÇOS DE SAÚDE COMUNITÁRIO NO MUNICÍPIO DO RANGEL".

Delimitação: o estudo incide sobre um posto de saúde do Rangel (caso de estudo: Centro Médico Hospitalar Maria de Fátima; confirmar a localização na visita). A plataforma regista os dados familiares necessários ao acompanhamento da saúde infantil (criança, encarregado, contactos, localização) e implementa, como módulo piloto, o controlo da vacinação infantil. Outros serviços comunitários e a gestão completa do agregado familiar ficam como trabalho futuro.

Cronograma relevante: actividade 5 (objectivos e requisitos), 6 (funcionalidades), 7 (diagramas UML e ER, até 28/11/2026), 1.ª pré-defesa (30/11 a 04/12/2026; entregável: slides e protótipo inicial). Hoje é 22/11/2026, por isso o prazo é curto. Prioridade: base de dados + ER + requisitos + protótipo mínimo.

## 2. Stack (decisão: PHP + MySQL/MariaDB no XAMPP)

- Back-end: PHP 8+ sem framework, organizado em camadas simples. MySQL/MariaDB com PDO e consultas preparadas. O ambiente é o XAMPP, que traz MariaDB: o schema tem de ser compatível com MariaDB E com MySQL 8 (não uses funcionalidades exclusivas de uma delas e diz-me se usares alguma).
- Front-end: HTML5, CSS puro (sem Bootstrap nem Tailwind), JavaScript puro (sem frameworks).
- Ambiente: XAMPP no Windows (Apache + PHP + MariaDB), já instalado em C:\xampp. O projeto fica em C:\xampp\htdocs\<nome-do-projeto>. Sem Composer, salvo necessidade justificada.
- O PHP está em C:\xampp\php\php.exe e pode não estar no PATH. Nos comandos para o PowerShell usa o caminho completo e diz-me como acrescentá-lo ao PATH.
- A aplicação NUNCA se liga à base de dados com o utilizador root. O database/README.md explica a criação de um utilizador próprio (ex.: vacinas_app) com permissões só sobre a base do projeto. A palavra-passe fica em config/config.local.php, que NÃO vai para o Git (está no .gitignore), com um config/config.local.example.php sem segredos.
- Tudo local no projeto (sem CDN): fonte Inter e ícones Lucide em SVG.
- Implantação: um computador do posto faz de servidor local (XAMPP). Os outros acedem pelo navegador, pela rede local, ao IP do servidor. NÃO precisa de internet. O phpMyAdmin NÃO deve ficar acessível pela rede. O README documenta: IP fixo do servidor, regra de firewall do Windows para o Apache e cópias de segurança com mysqldump agendado (só documentar por agora).
- NÃO há sincronização com nuvem, Cloudflare, Workers, D1, IndexedDB nem Service Worker nesta versão. Ficam como trabalho futuro.

## 3. O que já existe (verifica, não assumas)

O projeto foi começado noutra stack (Cloudflare Workers + D1/SQLite + IndexedDB), fase "1a". A pasta atual é a desse projeto (tem wrangler.jsonc). O ficheiro CONTEXTO_PROJETO.md atual descreve essa stack antiga e fica SUBSTITUÍDO por este documento (arquiva-o, não o apagues). Segundo o que sei, existe:

- CSS em ficheiros separados (variaveis, base, componentes, layout, icones, impressao, paginas/login, paginas/painel), login hexagonal em vidro com alternativa opaca, painel com barra lateral, fonte Inter e ícones Lucide locais.
- Migração SQLite (database/migrations/0001_esquema_inicial.sql), com alterações possivelmente não commitadas, e seed do calendário vacinal que gera 15 oportunidades, 37 doses e 18 vacinas (database/seeds/gerar-seed.mjs).
- Código específico da Cloudflare que NÃO se vai usar: Worker, router, CSRF do Worker, IndexedDB, RepositorioBase, fila de sincronização, PBKDF2 e Wrangler.

Faz o inventário e diz-me o que realmente lá está.

Decisão sobre o código antigo: antes de mexer, propõe-me os comandos Git para guardar o estado atual (tag "cloudflare-fase1a", incluindo alterações pendentes) e criar a branch "php-mysql". EU executo o commit. Depois move o que for específico da Cloudflare para /arquivo_cloudflare (não apagues).

Reaproveita: o CSS, as fontes, os ícones, o desenho do login e do painel (convertidos para PHP), os dados do calendário vacinal (convertidos para MySQL) e as regras da secção 5.

## 4. Perfis

- administrador: gere utilizadores, calendário e configurações.
- enfermeiro: regista e consulta vacinação, stock e relatórios.
- recepcao: regista crianças e encarregados, pesquisa e lista de faltosos.
- O encarregado não é utilizador do sistema.
- "Agente comunitário": só entra se a visita ao posto confirmar que existem agentes. Marca como "a confirmar".

## 5. Regras de dados (MySQL/MariaDB)

- InnoDB, utf8mb4, chaves estrangeiras reais. IDs BIGINT UNSIGNED AUTO_INCREMENT.
- Tabelas de dados: criado_em, atualizado_em, criado_por. Nas tabelas clínicas, eliminação lógica (eliminado_em) e nunca DELETE físico.
- Datas clínicas (data_nascimento, data_administracao) em DATE, separadas das técnicas.
- Vacinações só se acrescentam. Erro = anulação com motivo obrigatório (tabela vacinacao_anulacoes) + novo registo. Uma vacinação é válida se não tiver anulação.
- Stock: só movimentos (entrada, saida_vacinacao, perda, ajuste_mais, ajuste_menos), saldo calculado numa vista e nunca guardado, contado em doses. Anular uma vacinação não devolve stock automaticamente (ajuste manual com motivo). numero_lote normalizado (trim, maiúsculas).
- Auditoria (tabela auditoria): quem, quando, entidade, campo, valor anterior e novo, sem dados sensíveis desnecessários.
- Número de processo gerado pelo sistema (ex.: MF-2026-000123), único.
- Data de nascimento com opção "aproximada". Um encarregado principal por criança. Um encarregado pode ter vários filhos.
- Calendário vacinal na BD (nunca no código), editável só pelo administrador. Tabela calendario_vacinal: vacina_id, numero_dose, rotulo_idade (ex.: "5-6 anos"), idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica (default 1), notas_validacao. Valores clínicos que o cartão não dá ficam NULL e "a validar pela equipa clínica".
- O cálculo de doses pendentes (actividade 17 do cronograma, 16 a 23/01/2027) NÃO se implementa agora. Só se desenha: data prevista = nascimento + idade ideal; atraso quando passa a janela. O estado nunca é guardado, é sempre calculado.

Calendário vacinal (15 oportunidades, cartão oficial):
- Ao nascer: BCG, Polio oral, Hepatite B
- 2 meses: Pentavalente, Pneumo 13, Polio oral, Rotavírus
- 3 meses: Meningocócica conjugada quadrivalente (A, C, Y, W-135) (confirmado por dedução: a contagem dá 15)
- 4 meses: Pentavalente, Pneumo 13, Polio oral, Rotavírus, Polio injectável (VPI)
- 5 meses: Meningocócica conjugada quadrivalente
- 6 meses: Pentavalente, Pneumo 13, Polio oral, Vitamina A
- 9 meses: Sarampo + Rubéola, Febre amarela (dose única), Vitamina A
- 12 meses: Hepatite A, Varicela (1ª dose)
- 15 meses: VASPR (1ª dose), Meningite conjugada quadrivalente
- 18 meses: Pentavalente OU Tetravalente, Pneumo 13, Polio oral, Hepatite A
- 2 anos: Febre tifoide (reforço de 3/3 anos), Meningocócica conjugada quadrivalente
- 4 anos: Varicela (reforço)
- 5-6 anos: Pentavalente, VASPR (2ª dose)
- 7 anos: Tétano
- 10-12 anos: HPV

## 6. Decisões pendentes do grupo (assume o defeito, marca-o claramente e torna fácil de remover)

a) Bairros: INCLUI uma tabela de território simples (comunas e bairros), ligada ao encarregado e à criança, mantendo a morada em texto. Arranca VAZIA (preenche-se na visita). Não inventes nomes. Serve para os relatórios por bairro.
b) Agregado familiar leve (família e membros: nome, parentesco, data de nascimento): defeito = NÃO implementar. Desenha-o no ER como "módulo opcional" a tracejado, só como proposta.
c) Crescimento: defeito = campos opcionais peso_kg, altura_cm e perimetro_cefalico_cm na tabela consultas (sem gráficos). Marca "decisão pendente".
d) Stock: incluído no modelo de dados, mas fora do protótipo.
Resume estas assunções no início do documento de requisitos.

## 7. Entregáveis até à actividade 7 (pasta /docs)

1. 00-inventario.md: tabela com o que existe, o que se reaproveita, o que se adapta, o que se descarta e o que falta criar, e como (passos concretos).
2. 01-requisitos.md: requisitos funcionais (RF01...) e não funcionais (RNF01...) com identificador, descrição, prioridade (alta/média/baixa), módulo e origem (visita ao posto, fontes bibliográficas ou decisão do grupo). RNF obrigatórios: funcionar na rede local sem internet; segurança (palavras-passe com hash, perfis, auditoria); usabilidade em português; desempenho razoável em computadores simples; manutenção e cópias de segurança; proteção de dados pessoais (referir a legislação angolana de proteção de dados pessoais, a confirmar o número e o texto exato). Os que dependem do posto ficam "a confirmar na visita".
3. 02-modulos.md: módulos (autenticação e perfis; crianças e encarregados; calendário vacinal; vacinação e histórico; doses pendentes e faltosos; stock; relatórios; administração), cada um com objetivo, entradas, saídas, perfis e a actividade do cronograma correspondente.
4. 03-atores-casos-de-uso.md: atores, lista de casos de uso e descrição textual dos 5 mais importantes (fluxo principal e alternativos): autenticar, registar criança, registar vacinação, anular vacinação, consultar histórico.
5. Diagramas em /docs/diagramas, com o código-fonte e a imagem exportada, se possível: casos de uso (PlantUML, .puml); atividades (registar vacinação; fluxo da lista de faltosos); classes (domínio e controlo); sequência (login; registar vacinação; anular vacinação); ER (Mermaid erDiagram, .mmd), com cardinalidades e chaves, a partir do schema.sql REAL, sem divergências. Usa Mermaid para atividades, classes, sequência e ER, e PlantUML para casos de uso. Se não conseguires exportar imagens, deixa o código-fonte e diz-me como renderizá-lo online (mermaid.live, plantuml.com). Os diagramas não têm dados reais.
6. 04-dicionario-de-dados.md: para cada tabela, cada coluna (tipo, nulo, chave, descrição) e as relações. Tem de coincidir exatamente com o schema.sql.
7. database/schema.sql e database/seed.sql (MySQL/MariaDB), mais database/README.md (como importar no XAMPP, pelo phpMyAdmin e pela linha de comandos, e como criar o utilizador da aplicação). O seed deve garantir exatamente 15 oportunidades (15 valores distintos de rotulo_idade). Reaproveita os dados do seed antigo, compara os totais com os antigos (15 oportunidades, 37 doses, 18 vacinas) e diz-me qualquer diferença. A "Pentavalente OU Tetravalente" dos 18 meses entra como duas linhas com o mesmo grupo_alternativa.
8. 05-guiao-slides.md: guião para a 1.ª pré-defesa (cerca de 10 slides): tema e problema; objetivos; revisão bibliográfica; metodologia; requisitos; arquitetura; ER e casos de uso; protótipo (o que se mostra); cronograma e próximos passos. Só o texto de cada slide e uma sugestão do que mostrar. Eu faço os slides.
9. CONTEXTO_PROJETO.md novo, na raiz, a refletir esta stack (o antigo fica arquivado). Sem segredos.

## 8. Protótipo para a 1.ª pré-defesa

Mínimo (obrigatório):
- Login real em PHP com password_hash/password_verify. Antes de escolher, corre C:\xampp\php\php.exe -r "var_dump(defined('PASSWORD_ARGON2ID'));". Se der true usa Argon2id, se não usa bcrypt. Diz-me o que usaste e porquê. Sessões seguras (session_regenerate_id, cookies HttpOnly e SameSite), CSRF, limitação de tentativas, mensagens de erro iguais para utilizador inexistente e palavra-passe errada, logout, bloqueio de páginas por perfil.
- Criação do primeiro administrador por um script de linha de comandos (bin/criar_admin.php) que pede a palavra-passe no terminal. Sem palavras-passe por defeito no repositório nem no seed.
- Painel com dados reais da BD (contagens de crianças, vacinações, etc.) e o menu lateral do design existente. Os módulos ainda não feitos aparecem como "Em desenvolvimento".
- Página de consulta do calendário vacinal lido da BD (15 oportunidades, doses, alternativas), com a indicação "a validar" onde aplicável.

Opcional (só depois do mínimo estar aprovado): registo e listagem simples de crianças e encarregados, com pesquisa por nome e nº de processo.

## 9. Estrutura de pastas sugerida

- public/ (index.php, login.php, painel.php, assets/css, assets/js, assets/fonts, assets/icons)
- app/ (config, db, auth, controllers, models, views)
- config/ (config.local.php NÃO vai para o Git; config.local.example.php vai)
- database/ (schema.sql, seed.sql, README.md)
- bin/ (criar_admin.php)
- docs/ (e docs/diagramas)
- tests/ (scripts PHP simples: verificação do seed, hash de palavras-passe, consultas preparadas)
- arquivo_cloudflare/
- CONTEXTO_PROJETO.md, README.md (instalar o XAMPP, importar a BD, criar o administrador, correr)

Mostra-me a estrutura final antes de moveres ficheiros.

## 10. Etapas (uma de cada vez: plano, aprovação, entrega, "o que não verifiquei")

- Etapa 0: lê o projeto e o CONTEXTO_PROJETO.md antigo. Corre C:\xampp\php\php.exe -v e C:\xampp\mysql\bin\mysql.exe --version e diz-me as versões. Entrega SÓ o 00-inventario.md em texto na resposta (sem criar ficheiros), com a tabela das três categorias (implementado e testado / só esquema / não existe) e as perguntas que tiveres. PÁRA.
- Etapa 1: Git (tag e branch, com a minha autorização para o commit), reestruturação das pastas, schema.sql, seed.sql, README da BD e testes de verificação do seed. Mostra-me o schema antes de o aplicar.
- Etapa 2: documentos 01, 02 e 03 (requisitos, módulos, atores e casos de uso).
- Etapa 3: diagramas e dicionário de dados (04), coerentes com o schema.sql.
- Etapa 4: protótipo mínimo (secção 8).
- Etapa 5: guião de slides (05), CONTEXTO_PROJETO.md novo e README.

No fim de cada etapa: lista do que criaste e alteraste, como eu testo no XAMPP e o que NÃO conseguiste verificar (por exemplo, por não haver navegador, descreve o que esperas que eu veja).
Se algo do que escrevi entrar em conflito com o código existente, diz-me em vez de assumir.