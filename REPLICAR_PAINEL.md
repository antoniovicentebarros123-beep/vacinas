# INSTRUÇÕES V2: sistema web de registo de crianças e controlo da vacinação infantil (online)

> Este ficheiro SUBSTITUI o INSTRUCOES_PHP_MYSQL.md. Dele só continua válida a lista do calendário vacinal (15 oportunidades) da secção 5. Em conflito, manda este ficheiro. Sem segredos.

## 0. Regras de trabalho
- Português de Angola. Comentários no código em português. Comandos para PowerShell.
- Trabalhas por ETAPAS (secção 10): plano, aprovação, entrega. No fim de cada etapa diz o que NÃO verificaste.
- Não afirmes que algo está feito sem mostrar o ficheiro e como o testaste. Separa: "implementado e testado", "só esquema/desenho", "não existe".
- Se falhares duas vezes o mesmo erro, PÁRA e explica. Nunca alteres testes nem a base de dados para esconder falhas.
- Não inventes valores clínicos nem nomes de bairros. Onde faltar informação, marca "a validar" ou "a confirmar".
- Nunca apagues ficheiros sem eu aprovar (usa git mv). NÃO executes git commit, tag nem checkout: eu faço.
- NÃO importes nada na base de dados antes de eu aprovar o schema.
- Código simples e explicável: o projeto é defendido por 3 estudantes.
- Dados de saúde de crianças são sensíveis: nunca em logs, nunca segredos no repositório. Não cries ficheiros tipo "pasted-text.txt".

## 1. O projeto
Tema (não alterar): "DESENVOLVIMENTO DE UM SISTEMA WEB PARA A GESTÃO DO REGISTO DE CRIANÇAS E CONTROLO DA VACINAÇÃO INFANTIL NO CENTRO MÉDICO HOSPITALAR MARIA DE FÁTIMA".
O nome oficial da instituição fica em configuracoes (chave nome_posto): "Centro Médico Hospitalar Maria de Fátima".
Fora do âmbito: pagamentos e faturação (há vacinas gratuitas e pagas, não se tratam), agregado familiar, agentes comunitários, funcionamento offline e sincronização, gráficos de crescimento.
Prazo: 1.ª pré-defesa a 30/11/2026 (hoje 23/11). ER até 28/11. Prioridade: schema, pré-visualização de login e painel, requisitos e casos de uso, diagramas, guião.

## 2. Levantamento no posto (origem dos requisitos: "visita ao posto")
Processo atual:
- A criança chega à recepção com o encarregado e pergunta se a vacina está disponível.
- A recepção NÃO sabe: vai à sala de enfermagem perguntar.
- Havendo vacina, a criança vai para a enfermagem, onde é vacinada. A enfermeira assinala no cartão de papel.
- Se o cartão se perde, a família fica sem histórico.
- Quem controla as vacinas e as quantidades é a Dra. Virgínia (apelido a confirmar, pedir autorização para a citar no relatório).
- A sala de stock e a enfermagem têm computador. A recepção: a confirmar. Todo o posto tem Wi-Fi com internet.
- Vacinam todos os dias. O volume diário fica por confirmar.
Problemas: a recepção interrompe a enfermagem para saber do stock; ninguém tem visão central das quantidades; cartão perdido = histórico perdido; não há dados para relatórios; não se regista quem saiu sem vacina.
Processo proposto: a recepção pesquisa ou cadastra a criança e abre um ATENDIMENTO com a(s) vacina(s) pretendida(s); consulta a disponibilidade no ecrã; se há, o atendimento entra na FILA da enfermagem; a enfermeira vê a fila, regista a dose e o lote, e o stock baixa; o cartão em papel mantém-se e o sistema imprime uma 2.ª via. Se não há vacina, a recepção regista "sem vacina" com o motivo.

## 3. Stack e implantação
- PHP 8.0.30 (sem funcionalidades do 8.1: enums, readonly, first-class callable, new em inicializadores). MariaDB 10.4.32 com PDO e consultas preparadas. Schema compatível com MySQL 8, que NÃO foi testado.
- HTML5, CSS puro, JavaScript puro, sem frameworks. Fonte Inter e ícones Lucide locais, sem CDN.
- Palavras-passe com Argon2id (confirmado disponível no XAMPP).
- Desenvolvimento: XAMPP em C:\xampp\htdocs\vacinacao-infantil-fase1a\vacinas, só com dados FICTÍCIOS.
- Produção: sistema totalmente online, alojamento com PHP e MySQL/MariaDB (escolha em aberto, não necessária antes de 30/11). Requisitos: HTTPS obrigatório, cookies de sessão Secure, HttpOnly e SameSite=Strict, só a pasta public/ acessível pelo navegador, configuração fora de public/, phpMyAdmin do alojamento protegido. Sem dados reais de crianças antes de HTTPS, permissões e cópias de segurança.
- Os três postos de trabalho (recepção, stock, enfermagem) acedem pelo navegador, pela internet. Sem internet o sistema pára: a contingência é o cartão de papel, a registar depois. Documenta como risco.
- Sem Cloudflare, Workers, IndexedDB, Service Worker nem sincronização. O código antigo vai para /arquivo_cloudflare.
- Segurança no XAMPP (desenvolvimento): .htaccess na raiz a negar acesso a tudo o que não seja public/. Confirma que AllowOverride está ativo e testa que /database/, /config/, /arquivo_cloudflare/ e /.git/ dão 403. Descarta o VirtualHost na porta 8080 e a configuração de rede local.

## 4. Perfis (um utilizador tem um perfil)
- recepcao: pesquisa e cadastra crianças e encarregados; abre atendimentos; vê a disponibilidade (SÓ "disponível / indisponível", sem lotes nem quantidades); regista "sem vacina"; vê o estado da fila.
- enfermeiro: vê a fila; regista vacinação (escolhe o lote); anula com motivo; consulta o histórico; imprime a 2.ª via do cartão; vê saldos de stock em leitura.
- responsavel_stock: lotes, entradas, perdas, ajustes, saldos, alertas de validade, relatório de pedidos sem stock.
- administrador: utilizadores, catálogo e configurações, relatórios e auditoria. No protótipo, o catálogo gere-se pelo phpMyAdmin.
O encarregado não é utilizador.

## 5. Regras de dados e schema (MariaDB 10.4)
Calendário vacinal e regra do seed: ver a secção 5 do INSTRUCOES_PHP_MYSQL.md (15 oportunidades, 37 doses, 18 vacinas; Pentavalente OU Tetravalente aos 18 meses = duas linhas com o mesmo grupo_alternativa). O seed é SQL estático com IDs fixos. Compara com os totais antigos e diz-me qualquer diferença.
16 tabelas + 2 vistas, por esta ordem de criação: configuracoes, bairros, utilizadores, login_tentativas, encarregados, criancas, vacinas, calendario_vacinal, stock_lotes, atendimentos, atendimento_vacinas, vacinacoes, vacinacao_anulacoes, stock_movimentos, auditoria, contactos_faltosos; vistas stock_saldo_lotes e vacinas_disponiveis.
Regras gerais:
1. InnoDB, utf8mb4, collation utf8mb4_unicode_ci explícita. IDs BIGINT UNSIGNED AUTO_INCREMENT. Sem JSON, RETURNING nem ADD COLUMN IF NOT EXISTS.
2. Todas as chaves estrangeiras com ON DELETE RESTRICT (sem CASCADE nem SET NULL): os dados também se gerem pelo phpMyAdmin.
3. criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP. atualizado_em com ON UPDATE CURRENT_TIMESTAMP, só nas tabelas editáveis. Tabelas só de acrescentar (vacinacao_anulacoes, stock_movimentos, auditoria, login_tentativas, atendimento_vacinas) não levam atualizado_em. Autoria: criado_por_id (FK para utilizadores, NULL permitido porque o catálogo entra pelo phpMyAdmin).
4. eliminado_em só em bairros, encarregados, criancas, vacinas, calendario_vacinal, stock_lotes e contactos_faltosos. Vacinações não têm eliminado_em: o erro corrige-se com anulação.
5. Sem comunas, sem posto_id, sem versao, sem dispositivo_id, sem nome_busca nem telefone_busca. O telefone grava-se só com dígitos, no PHP.
6. utilizadores: nome, utilizador (único), password_hash VARCHAR(255) NOT NULL, perfil (CHECK administrador, enfermeiro, recepcao, responsavel_stock), ativo, deve_mudar_password, ultimo_login. Sem sal nem iteracoes.
7. bairros: nome (único), ativo. Arranca VAZIA, sem nomes inventados. encarregados: nome, parentesco, telefone, telefone_alt, morada, bairro_id (opcional). criancas: numero_processo (único), numero_cartao, nome, data_nascimento, data_nascimento_aproximada, sexo, encarregado_id, observacoes. Sem morada nem bairro na criança.
8. atendimentos (substitui consultas): crianca_id, estado (CHECK aguardando, em_atendimento, concluido, cancelado, sem_vacina), recebido_por_id, atendido_por_id (NULL até a enfermagem iniciar), iniciado_em, concluido_em, motivo (obrigatório, não vazio, quando o estado é cancelado ou sem_vacina), observacoes, peso_kg, altura_cm, perimetro_cefalico_cm (opcionais, "decisão pendente do grupo", sem ecrã). atendimento_vacinas: atendimento_id, vacina_id (par único), as vacinas pretendidas na recepção.
9. vacinacoes: crianca_id, atendimento_id (NULL para doses transcritas de cartão, origem externa), vacina_id, calendario_id (opcional), numero_dose, data_administracao, lote_id (NULL se externa), administrado_por_id, administrado_por_nome, local_anatomico, origem (posto ou externa), observacoes, reacoes. SEM UNIQUE em (crianca_id, vacina_id, numero_dose): índice normal. A regra "não existir duas vacinações válidas (sem anulação) da mesma criança, vacina e dose" fica no PHP, dentro de uma transação.
10. vacinacao_anulacoes: vacinacao_id (único), motivo, CHECK CHAR_LENGTH(TRIM(motivo)) > 0, utilizador_id. Válida = sem anulação.
11. Stock: stock_lotes (vacina_id, numero_lote normalizado no PHP com trim e maiúsculas, sem CHECK; validade; fabricante). stock_movimentos: lote_id, tipo (entrada, saida_vacinacao, perda, ajuste_mais, ajuste_menos), quantidade > 0, motivo (CHECK: obrigatório e não vazio em perda e ajustes), vacinacao_id, data_movimento, utilizador_id. Só acrescentar. Saldo SEMPRE calculado, nunca guardado. Contado em doses.
12. Vista stock_saldo_lotes com COALESCE(SUM(...), 0). Vista vacinas_disponiveis: por vacina, a soma do saldo dos lotes não eliminados com validade >= CURDATE(). O PHP mostra à recepção apenas sim ou não.
13. calendario_vacinal: vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio_meses, janela_fim_meses, idade_minima_meses, intervalo_minimo_dias, dose_anterior_exigida (auto-referência), grupo_alternativa, dose_unica, ativo, validar_clinica (default 1), notas_validacao. Valores clínicos que o cartão não dá ficam NULL e "a validar pela equipa clínica". O cálculo de doses pendentes NÃO se implementa agora (fica no desenho: prevista = nascimento + idade ideal; o estado é calculado, nunca guardado).
14. contactos_faltosos: crianca_id, data_contacto, canal (CHECK telefone, sms, whatsapp), resultado, notas, utilizador_id.
15. auditoria: entidade, entidade_id, acao, campo, valor_anterior, valor_novo, utilizador_id, criado_em. Sem dados sensíveis desnecessários.
16. Índices: login_tentativas (utilizador, criado_em) e (ip, criado_em); criancas (nome, data_nascimento); encarregados (nome) e (telefone); bairros (nome); atendimentos (estado, criado_em); vacinacoes (crianca_id, vacina_id, numero_dose) e (data_administracao).
17. COMMENT em português em todas as tabelas e colunas principais.
18. Importável pelo separador Importar do phpMyAdmin numa base já criada: sem CREATE DATABASE, USE, DROP, DELIMITER, triggers nem procedimentos.
19. Utilizador da aplicação vacinas_app, só com SELECT, INSERT e UPDATE nessa base (DELETE só em login_tentativas). A aplicação nunca usa root. Palavra-passe do root definida e config.inc.php ajustado (auth_type cookie). config/config.local.php fora do Git e config.local.example.php sem segredos.
20. Tabelas geridas à mão pelo phpMyAdmin: bairros, vacinas, calendario_vacinal, configuracoes. Nunca editadas à mão: atendimentos, vacinacoes, vacinacao_anulacoes, stock_movimentos, auditoria, login_tentativas.

## 6. Regras de negócio a documentar (implementação mais tarde)
- Fila: a enfermagem vê os atendimentos "aguardando" e atualiza-os por pedidos JavaScript (fetch) a cada 10 a 15 segundos, a um endpoint JSON protegido por sessão e perfil, que devolve só os dados necessários. Sem WebSockets.
- Baixa de stock ao vacinar (origem posto), numa só transação: bloquear o lote (SELECT ... FOR UPDATE), confirmar saldo > 0, gravar a vacinação, gravar o movimento saida_vacinacao. Se o saldo for 0, recusar e avisar. O sistema sugere o lote que expira primeiro e nunca oferece lotes fora da validade.
- Anular uma vacinação não devolve stock automaticamente (ajuste manual com motivo).
- Número de processo gerado no PHP, dentro de uma transação, com UNIQUE e nova tentativa em caso de colisão. Prefixo em configuracoes (chave prefixo_processo, valor provisório "RG"; confirmar).
- Ao cadastrar uma criança, avisar de possível duplicado (mesmo nome e data de nascimento). A fusão assistida fica como trabalho futuro.
- 2.ª via do cartão: página de impressão com CSS próprio (impressao.css), com o histórico de vacinas válidas da criança.
- Relatório "crianças que saíram sem vacina" (estado sem_vacina, por vacina e período).

## 7. Entregáveis (pasta /docs)
1. 00-inventario.md: curto, só atualizar o que mudou.
2. 01-requisitos.md: RF e RNF com id, descrição, prioridade, módulo e origem (visita ao posto, fontes bibliográficas ou decisão do grupo). RNF: sistema online com HTTPS; segurança (Argon2id, perfis, auditoria); usabilidade em português; desempenho; cópias de segurança; proteção de dados pessoais (referir a legislação angolana, confirmar número e texto); risco "sem internet o sistema pára". Resume no início as decisões pendentes: nome oficial do posto, recepção com computador, crescimento, alojamento.
3. 02-modulos.md: autenticação e perfis; crianças e encarregados; atendimento e fila; vacinação e histórico; stock; relatórios; administração. Cada um com objetivo, entradas, saídas, perfis e a actividade do cronograma.
4. 03-atores-casos-de-uso.md: processo atual e processo proposto; atores (recepção, enfermeiro, responsável de stock, administrador); lista de casos de uso e descrição textual dos 6 principais: autenticar, registar criança, abrir atendimento e consultar disponibilidade, registar vacinação, anular vacinação, consultar histórico e imprimir 2.ª via.
5. /docs/diagramas, código-fonte e imagem se conseguires exportar: casos de uso (PlantUML), atividades (fluxo recepção → enfermagem; registo de vacinação), classes, sequência (login; registar vacinação com baixa de stock), ER (Mermaid erDiagram a partir do schema.sql REAL, sem divergências). Se não puderes exportar imagens, dá-me o código e como renderizá-lo online. Sem dados reais.
6. 04-dicionario-de-dados.md: igual ao schema.sql, tabela a tabela.
7. database/schema.sql, database/seed.sql e database/README.md (importar pelo phpMyAdmin, criar utilizador, o que se edita à mão e o que não).
8. 05-guiao-slides.md: cerca de 10 slides com o texto e o que mostrar. Eu faço os slides.
9. CONTEXTO_PROJETO.md novo, na raiz, refletindo esta versão.

## 8. Protótipo para 30/11 (por prioridade)
P1 (obrigatório): login e painel em PHP, em PRÉ-VISUALIZAÇÃO, sem base de dados. Endereço de teste: http://localhost/vacinacao-infantil-fase1a/vacinas/public/
- public/index.php redireciona para login.php. O index.html antigo vai com git mv para /arquivo_cloudflare/public/.
- login.php e painel.php como vistas finas com partials em app/views/partials. Reaproveita o HTML que já extraíste do login.js e do painel.js, com as MESMAS classes CSS e sem alterar o CSS. Remove tudo o que é de sincronização.
- Caminhos calculados a partir de SCRIPT_NAME, para funcionarem em subpasta e na raiz de um domínio.
- Menu lateral: Painel (ativo), Crianças, Atendimento, Vacinação, Stock, Relatórios, os restantes com "Em desenvolvimento". No topo: "Servidor local" (sem lógica de rede). Cartões: "Crianças registadas" e "Vacinações registadas" a 0; "Hoje no posto" com "—" e "Cálculo ainda não disponível". Nome do posto vem da configuração. Sem dados inventados.
- Modo de pré-visualização: só se config.local.php tiver modo_previa = true E o pedido vier de 127.0.0.1 ou ::1. "Entrar" abre o painel com um utilizador fictício, sem validar nada, com faixa visível "PRÉ-VISUALIZAÇÃO: sem autenticação e sem dados reais". Marca cada ponto com o comentário "REMOVER: login real" e diz-me onde estão. Sem utilizadores, palavras-passe nem hashes.
- Cabeçalhos de segurança (nosniff, X-Frame-Options DENY, Referrer-Policy, CSP restritiva sem inline), sessão com cookies HttpOnly e SameSite=Strict, saída sempre escapada. JavaScript só para mostrar/ocultar a palavra-passe e abrir o menu.
- Verificação (sem navegador): php -l em cada ficheiro, Invoke-WebRequest em login.php e painel.php com o código HTTP, e confirma que cada ficheiro CSS referenciado existe. Diz o que devo ver no ecrã. Log: C:\xampp\apache\logs\error.log.
P2 (só depois de o schema estar aprovado e importado, e se houver tempo): login real com Argon2id (password_hash e password_verify), sessões seguras, CSRF, limitação de tentativas com mensagens iguais para utilizador inexistente e palavra-passe errada, bin/criar_admin.php que pede a palavra-passe no terminal (sem palavras-passe por defeito), painel com contagens reais.
P3 (NÃO é para 30/11): atendimento e fila, vacinação, stock, relatórios, 2.ª via.

## 9. Estrutura de pastas
public/ (index.php, login.php, painel.php, assets/css, js, fonts, icons), app/ (core, controllers, models, views/partials), config/, database/, bin/, docs/ e docs/diagramas, tests/ (scripts PHP simples), arquivo_cloudflare/. Em produção, só public/ é visível. Mostra-me a árvore real antes e depois de moveres ficheiros.

## 10. Etapas (uma de cada vez; plano, aprovação, entrega, "o que não verifiquei")
Etapa A: schema.sql e seed.sql segundo a secção 5. Mostra o schema.sql COMPLETO em texto. NÃO importes. Responde também às pendências: árvore real de pastas, comparação do seed com os totais antigos, e se a solução do .htaccess está pronta.
Etapa B: P1 da secção 8.
Etapa C: docs 01, 02 e 03.
Etapa D: diagramas e dicionário de dados (04).
Etapa E: guião (05), CONTEXTO_PROJETO.md novo e README.
Etapa F (só se sobrar tempo): P2.
Se algo entrar em conflito com o código existente, pergunta em vez de assumir.