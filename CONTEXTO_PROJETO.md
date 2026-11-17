# CONTEXTO DO PROJETO: Controlo de Vacinação Infantil

> Ficheiro de contexto para qualquer IA/IDE que continue este projeto. Lê-o INTEIRO antes de fazer o que quer que seja. Mantém a secção "Estado atual" atualizada no fim de cada fase. Este ficheiro NUNCA deve conter segredos (chaves, palavras-passe, pepper).

## 0. Regras de trabalho

- Responde SEMPRE em português de Angola. Comentários no código em português.
- O projeto foi iniciado noutra IDE (cujo limite terminou). Tu não conheces o código: o primeiro passo é LER, não escrever.
- Não reescrevas código que já funciona sem me dizeres porquê. Respeita a estrutura e as convenções existentes.
- Uma fase de cada vez. Antes de cada fase, mostra o plano e espera a minha aprovação.
- No fim de cada fase diz-me exatamente o que NÃO conseguiste verificar.
- Se uma instrução for ambígua, ou entrar em conflito com o código existente, pergunta antes de assumir.
- Não inventes valores clínicos: onde faltar informação, marca "a validar".
- Antes de usares comandos ou opções do Wrangler ou limites da Cloudflare, confirma a documentação atual. Não inventes sintaxe, versões nem limites.
- Não cries ficheiros fora do projeto nem ficheiros de anexos tipo "pasted-text.txt".
- Trabalho em Windows (PowerShell). Dá os comandos para PowerShell.
- Dados de saúde nunca em logs. Mensagens de erro claras, em português. Validação no servidor e no cliente. Escape de saída contra XSS (nunca innerHTML com dados). Consultas D1 sempre com bind.

## 1. PRIMEIRO PASSO (antes de qualquer alteração)

1. Lê a estrutura completa do projeto: README, wrangler.jsonc/toml, package.json, migrações em database/migrations, seeds, testes, public/ e src/.
2. Corre `npm install` e os testes existentes (esperam-se 31 a passar). Corre `npm run dev` e confirma que http://localhost:8787 abre.
3. Responde-me com: resumo do que existe, o que está de acordo com este ficheiro, o que diverge e o que não percebeste. Só depois propões o plano da Fase 1b e esperas a minha aprovação. Ainda NÃO escrevas código.

## 2. O projeto

Sistema web (PWA) de CONTROLO DE VACINAÇÃO INFANTIL para postos médicos em Angola (cliente inicial: Centro Médico Hospitalar Maria de Fátima). Interface em português de Angola. Corre na Cloudflare e tem de funcionar SEM internet.

## 3. Stack (obrigatória)

- Interface: HTML5, CSS puro (sem Bootstrap, Tailwind ou frameworks), JavaScript puro com módulos ES (sem frameworks e sem etapa de compilação).
- Offline: Service Worker + IndexedDB (dados locais e fila `sync_outbox`). Pedir `navigator.storage.persist()`.
- API: Cloudflare Workers em JavaScript puro, com router próprio.
- Base central: Cloudflare D1 (dialeto SQLite). Backups: R2 + Cron Triggers.
- Interface hospedada com Workers Assets. Desenvolvimento: Wrangler (Node.js).
- Segurança: Web Crypto API (PBKDF2).
- Tudo local no projeto (sem CDN): fonte Inter, ícones Lucide em SVG.
- Futuro: embrulhar a PWA em Electron ou Tauri para desktop.

## 4. Arquitetura: offline-first, sem hub e sem PHP

- Cada computador é um navegador/app com a sua própria base local (IndexedDB). Os computadores NÃO comunicam entre si. Cada um sincroniza diretamente com a API na nuvem (Workers + D1) por HTTPS.
- NUNCA depende da internet para registar ou consultar. Toda a gravação vai primeiro para o IndexedDB e entra em `sync_outbox` na MESMA transação.
- Indicador sempre visível: "Sincronizado (última: hh:mm)", "X registos por enviar" ou "Offline".
- Primeira utilização de um computador: exige internet e login online (descarrega catálogo, postos e utilizadores reduzidos).

## 5. Sincronização (desenho aprovado; implementa na Fase 5)

- `POST /api/sync/enviar` (lote de operações) e `GET /api/sync/receber?desde=<seq>`.
- Tabela `alteracoes` na D1: `seq` (PK crescente, é o cursor), `op_id` (ULID por operação, ÚNICO, garante idempotência), `posto_id` ('GLOBAL' para o catálogo), `entidade`, `entidade_id`, `operacao`, `payload`, `versao`, `dispositivo_id`, `enviado_por`, `criado_em`. Índice (`posto_id`, `seq`).
- `grupo_id` junta operações atómicas (ex.: fusão = insert em `fusoes_criancas` + update em `criancas`).
- Só se acrescentam (inserir se o ID não existir): vacinacoes, vacinacao_anulacoes, stock_movimentos, auditoria, contactos_faltosos, fusoes_criancas.
- Editáveis (criancas, encarregados): o payload leva só os campos alterados. A nuvem aplica campo a campo, a última operação recebida prevalece, e regista histórico na auditoria.
- A ordem final é a da nuvem, nunca a dos relógios dos computadores.
- Falhas de rede: backoff, tentativas e último erro na fila. Nada se apaga da fila antes da confirmação do servidor. Operação rejeitada fica com estado "erro" e mensagem visível ao utilizador, nunca apagada em silêncio.
- Sessão expirada após trabalho offline: a fila fica guardada e a app pede novo login online antes de enviar. Se a operação foi criada pelo utilizador A e quem sincroniza é o B no mesmo posto, aceita e regista `enviado_por`. Nunca aceitar operações para outro posto.
- Módulo de validações e regras partilhado entre cliente e Worker em `public/shared/` (fonte única, importada pelo Worker por caminho relativo). Nada de segredos nessa pasta.
- Operações de administração (catálogo, utilizadores, dispositivos, reposição de palavra-passe) exigem internet e vão direto à API, sem outbox. O Worker escreve e acrescenta a `alteracoes`.

## 6. Regras de dados

- IDs ULID (CHAR(26)) gerados no cliente. Nunca auto-incremento nas tabelas sincronizáveis.
- Tabelas sincronizáveis: `id`, `posto_id`, `criado_em`, `atualizado_em` (UTC), `dispositivo_id`, `versao`, `eliminado_em` (eliminação lógica, nunca DELETE físico). NÃO há coluna "sincronizado": `sync_outbox` é a única fonte de verdade.
- O repositório base local, em cada gravação e na mesma transação: incrementa a `versao`, preenche `dispositivo_id` e `posto_id`, atualiza `atualizado_em` e insere em `sync_outbox` (já existe: RepositorioBase).
- Datas clínicas (`data_nascimento`, `data_administracao`) em DATE, separadas das técnicas.
- Vacinações só se acrescentam. Erro = anulação com motivo obrigatório + novo registo. Uma vacinação é válida se não tiver anulação.
- Stock: só movimentos (entrada, saida_vacinacao, perda, ajuste_mais, ajuste_menos), saldo calculado e nunca guardado, contado em doses. Só `origem = posto` faz baixa. Anular uma vacinação NÃO devolve stock automaticamente (ajuste manual com motivo). Alerta de saldo negativo após sincronização. `numero_lote` normalizado ao gravar (trim, maiúsculas). Na D1 o índice do lote NÃO é único e o saldo agrupa por (`posto_id`, `vacina_id`, `numero_lote`).
- Número de processo gerado localmente com prefixo do posto e do dispositivo (ex.: MF-PC01-2026-000123). O contador é recalculado a partir dos números existentes ao arrancar e após cada sincronização. Se o Worker rejeitar por colisão, gera novo número e reenvia sem perder o registo.
- Data de nascimento com opção "aproximada". Um encarregado principal por criança.
- Duplicados de crianças (nome + data de nascimento + encarregado semelhantes) com fusão assistida, ao criar e ao receber dados. FUSÃO: NÃO mover vacinações nem consultas. A ficha de origem fica com `fundida_em_id` apontando para o destino e tudo segue esse redirecionamento. A fusão sincroniza como um único registo. Alerta de dose repetida (também vinda de outro computador), sem apagar nada.
- Cada vacinação guarda também `administrado_por_nome`.
- Pesquisa no IndexedDB: campos `nome_busca` (minúsculas, sem acentos) e `telefone_busca` (só dígitos), pesquisa por prefixo.
- Isolamento entre postos: todas as tabelas de dados têm `posto_id` e o Worker filtra SEMPRE a partir da sessão, nunca do pedido do cliente. Registos globais (vacinas, calendário) têm `posto_id` NULL.

## 7. Esquema (resumo; a migração 0001_esquema_inicial.sql já existe)

Se o esquema existente divergir deste resumo, o que está nas migrações manda. Avisa-me das diferenças.

- Só na nuvem: utilizadores (password_hash, sal, iteracoes, pepper_versao, perfil, ativo, deve_mudar_password, etc.), sessoes, login_tentativas, alteracoes, backups_log.
- Sincronizáveis: postos, dispositivos, utilizadores_reduzidos (id, nome, posto_id, perfil, ativo; NUNCA o hash), encarregados, criancas, fusoes_criancas, duplicados_descartados, consultas, vacinacoes, vacinacao_anulacoes, vacinas, calendario_vacinal, stock_lotes, stock_movimentos, contactos_faltosos, auditoria.
- Só locais (IndexedDB): sync_outbox (id auto, op_id, grupo_id, entidade, entidade_id, operacao, payload, versao, criado_em, tentativas, proxima_tentativa, ultimo_erro, estado), sync_cursor, sync_log, sessao_local, verificador_offline, configuracoes.

## 8. Perfis

- administrador_central (posto_id NULL): único que edita o catálogo global de vacinas e calendário.
- administrador: gere utilizadores e dispositivos só do seu posto.
- enfermeiro e recepcao.
- Permissões verificadas SEMPRE no Worker. No cliente só escondem botões.

## 9. Calendário vacinal

O cartão oficial tem 15 oportunidades:

- Ao nascer: BCG, Polio oral, Hepatite B
- 2 meses: Pentavalente, Pneumo 13, Polio oral, Rotavírus
- 3 meses: Meningocócica conjugada quadrivalente (A, C, Y, W-135)
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

O seed já gera 15 oportunidades, 37 doses e 18 vacinas, e falha se a contagem não der 15. Todas as linhas estão "a validar pela equipa clínica". Intervalos e idades mínimas ficam NULL. NÃO inventes valores clínicos.

O cálculo automático de doses (previstas, estados, recálculo) é FASE FUTURA. Só existe o módulo CalculadoraDoses com funções vazias em `public/shared/`. Painel e faltosos mostram "cálculo ainda não disponível".

## 10. Autenticação (decisões aprovadas para a Fase 1b)

Plano Workers Paid aprovado. Não desenhar o login para o plano gratuito.

1. PBKDF2-HMAC-SHA-256 com 100.000 iterações (limite máximo nos Workers; fonte de terceiros, a confirmar medindo). O número fica no campo `iteracoes` de cada utilizador.
2. Pepper como segredo do Worker (HMAC-SHA-256 da palavra-passe com o pepper, antes do PBKDF2). Definido só com `wrangler secret`, nunca no repositório nem no wrangler.jsonc/toml. Campo `pepper_versao` nos utilizadores. README: o pepper deve ter cópia segura fora da Cloudflare, porque a sua perda impede todos os logins.
3. Sal aleatório de 16 bytes por utilizador. Comparação em tempo constante.
4. Palavra-passe: mínimo 10 e máximo 128 caracteres, diferente do nome de utilizador, fora de uma lista curta de palavras comuns.
5. Limite de tentativas: 5 falhas em 15 minutos por utilizador e por IP, a correr ANTES de calcular qualquer hash. Turnstile opcional, desligado por omissão.
6. Utilizador inexistente: hash falso para o tempo de resposta ser equivalente, e a mesma mensagem de erro que para palavra-passe errada.
7. Se o utilizador tiver menos iterações do que o valor atual, refazer o hash no login seguinte com sucesso.
8. Não encadear vários PBKDF2. Sem Argon2id/WASM por agora.
9. Sessão: token opaco em cookie HttpOnly, Secure, SameSite=Strict. Na D1 só o `token_hash`. CSRF por cabeçalho personalizado obrigatório + verificação de origem (já existe).
10. Login offline: só para quem já entrou online naquele computador. Verificador local derivado com 600.000 iterações (configurável), sal próprio do dispositivo. Nunca a palavra-passe nem o hash da nuvem. Expira ao fim de N dias (configurável) e então exige login online. Inclui uma pequena medição do tempo que o verificador demora no computador atual, registada, para ajustar o valor num PC do posto.
11. "Esqueci a palavra-passe": sem e-mail. O administrador repõe e o utilizador é obrigado a mudá-la no primeiro login.
12. Antes de dares o login por bom, mede o PBKDF2 num Worker IMPLANTADO (o `wrangler dev` pode não aplicar o limite) e mostra-me o resultado: tempo de CPU, se o limite de 100.000 é respeitado e se há erro 1102. Guia-me passo a passo nos comandos (wrangler login, criar a D1 remota, aplicar migrações, definir o secret, deploy).

## 11. Fases (uma de cada vez)

- **1a** FEITA.
- **1b**: login online e offline, sessões, perfis (incluindo administrador_central), medição do PBKDF2 no Worker implantado, testes de isolamento entre postos e de que um administrador de posto não altera o catálogo nem outro posto, endpoint de arranque provisório (descarrega catálogo, postos e utilizadores reduzidos), remoção do `?previa`.
- **2**: crianças e encarregados, pesquisa, duplicados, fusão assistida, auditoria campo a campo.
- **3**: vacinas e calendário (admin central), consultas com várias vacinas, vacinação, anulação, alerta de dose repetida.
- **4**: painel diário e faltosos, contactos, stock.
- **5**: sincronização completa, Service Worker, indicador, alertas vindos da sincronização.
- **6**: relatórios e cartão de vacinas (impressao.css).
- **7**: auditoria completa, backups (Cron + R2; confirma na documentação se a D1 se exporta dentro de um Worker, senão despejo lógico por tabela em blocos; documenta Time Travel e exportação manual com o Wrangler), testes (isolamento entre postos, falhas de rede), README.

## 12. Design

Identidade médica: verde-esmeralda a turquesa, azul-petróleo, fundo claro, cartões brancos com cantos arredondados e sombras suaves. Variáveis CSS em `:root`. Glassmorphism SÓ no login. Responsivo, amigável ao toque, acessível (contraste, labels, foco visível, navegação por teclado).

## 13. Estado atual (atualizar no fim de cada fase)

**Feito (Fase 1a):**
- Estrutura do projeto e Wrangler. Migração e seed da D1 aplicados em local (D1 "vacinacao").
- Wrapper de IndexedDB e RepositorioBase (escrita no sync_outbox, auditoria campo a campo, número de processo, ULID).
- Worker com router, CSRF e cabeçalhos de segurança.
- CSS em ficheiros separados (variaveis, base, componentes, layout, icones, impressao, paginas/login, paginas/painel).
- Ecrã de login hexagonal em vidro com alternativa opaca (os campos existem mas AINDA NÃO autentica).
- Painel com barra lateral e indicador de sincronização. Visto no navegador e aprovado visualmente.
- 31 testes a passar.
- Scripts npm: `seed:gerar`, `db:migrar:local`, `db:seed:local`, `dev`.

**Incidente já resolvido:** o `wrangler dev` falhou porque a `compatibility_date` do projeto era mais recente do que o workerd instalado suportava. Resolveu-se com `npm install` (atualizou o Wrangler). Garante que a `compatibility_date` nunca é mais recente do que a suportada pelo workerd instalado.

**Não verificado:**
- O login nunca foi testado.
- O PBKDF2 nunca foi medido num Worker real.
- Os testes automáticos foram feitos em DOM simulado.
- `http://localhost:8787/?previa` é uma pré-visualização provisória e sai no fim da Fase 1b.
- `favicon.ico` dá 404 (irrelevante, pode ser resolvido quando convier).

**Próximo passo:** Fase 1b (depois da aprovação do plano).