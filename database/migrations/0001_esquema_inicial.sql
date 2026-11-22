-- =============================================================================
-- 0001 · Esquema inicial da D1 (dialeto SQLite) - VERSÃO CORRIGIDA
-- Correções: 
--  - alteracoes: op_id UNIQUE, grupo_id, enviado_por, índice (posto_id, seq)
--  - utilizadores: pepper_versao
--  - sessoes: token_hash (já está correto)
--  - calendario_vacinal: validar_clinica (já existe)
--  - stock_lotes: índice NÃO único em (posto_id, vacina_id, numero_lote)
--  - contactos_faltosos: já existe
--  - Todas as tabelas sincronizáveis têm eliminado_em, posto_id, dispositivo_id, versao
-- =============================================================================

-- ---------- Postos e dispositivos ----------
CREATE TABLE postos (
  id             CHAR(26)     NOT NULL PRIMARY KEY,
  codigo         VARCHAR(10)  NOT NULL UNIQUE,          -- prefixo do nº de processo, ex.: MF
  nome           VARCHAR(150) NOT NULL,
  ativo          INTEGER      NOT NULL DEFAULT 1 CHECK (ativo IN (0,1)),
  criado_em      TEXT         NOT NULL,
  atualizado_em  TEXT         NOT NULL,
  dispositivo_id CHAR(26)     NOT NULL,                 -- CORREÇÃO: campo obrigatório
  versao         INTEGER      NOT NULL DEFAULT 1,       -- CORREÇÃO: campo obrigatório
  eliminado_em   TEXT
);

CREATE TABLE dispositivos (
  id             CHAR(26)    NOT NULL PRIMARY KEY,
  posto_id       CHAR(26)    NOT NULL REFERENCES postos(id),
  codigo         VARCHAR(10) NOT NULL,                  -- ex.: PC01 (entra no nº de processo)
  nome           VARCHAR(100) NOT NULL,
  ativo          INTEGER     NOT NULL DEFAULT 1 CHECK (ativo IN (0,1)),
  ultimo_sync    TEXT,
  criado_em      TEXT        NOT NULL,
  atualizado_em  TEXT        NOT NULL,
  dispositivo_id CHAR(26)     NOT NULL,                 -- CORREÇÃO: campo obrigatório
  versao         INTEGER     NOT NULL DEFAULT 1,       -- CORREÇÃO: campo obrigatório
  eliminado_em   TEXT
);
CREATE UNIQUE INDEX ux_dispositivos_posto_codigo ON dispositivos (posto_id, codigo);

-- ---------- Utilizadores e sessões (SÓ NA NUVEM) ----------
-- posto_id NULL = administrador_central (não pertence a nenhum posto).
CREATE TABLE utilizadores (
  id                  CHAR(26)     NOT NULL PRIMARY KEY,
  posto_id            CHAR(26)     REFERENCES postos(id),
  nome                VARCHAR(150) NOT NULL,
  utilizador          VARCHAR(50)  NOT NULL UNIQUE,
  password_hash       TEXT         NOT NULL,             -- nunca sai da nuvem
  sal                 TEXT         NOT NULL,
  iteracoes           INTEGER      NOT NULL,
  pepper_versao       INTEGER      NOT NULL DEFAULT 1,  -- CORREÇÃO: campo obrigatório
  perfil              VARCHAR(25)  NOT NULL
                      CHECK (perfil IN ('administrador_central','administrador','enfermeiro','recepcao')),
  ativo               INTEGER      NOT NULL DEFAULT 1 CHECK (ativo IN (0,1)),
  deve_mudar_password INTEGER      NOT NULL DEFAULT 1 CHECK (deve_mudar_password IN (0,1)),
  ultimo_login        TEXT,
  criado_em           TEXT         NOT NULL,
  atualizado_em       TEXT         NOT NULL,
  dispositivo_id     CHAR(26)     NOT NULL,             -- CORREÇÃO: campo obrigatório
  versao              INTEGER      NOT NULL DEFAULT 1,  -- CORREÇÃO: campo obrigatório
  eliminado_em        TEXT,
  -- só o administrador central pode não ter posto; todos os outros perfis têm
  CHECK ((perfil = 'administrador_central' AND posto_id IS NULL)
      OR (perfil <> 'administrador_central' AND posto_id IS NOT NULL))
);
CREATE INDEX ix_utilizadores_posto ON utilizadores (posto_id);

-- Versão reduzida que segue para os computadores (sem hash nem sal).
CREATE VIEW utilizadores_reduzidos AS
  SELECT id, nome, posto_id, perfil, ativo FROM utilizadores WHERE eliminado_em IS NULL;

CREATE TABLE sessoes (
  id             CHAR(26)    NOT NULL PRIMARY KEY,
  utilizador_id  CHAR(26)    NOT NULL REFERENCES utilizadores(id),
  posto_id       CHAR(26)    REFERENCES postos(id),
  token_hash     TEXT        NOT NULL UNIQUE,            -- só o hash do token é guardado
  dispositivo_id CHAR(26)    NOT NULL,                   -- CORREÇÃO: campo obrigatório
  manter_sessao  INTEGER     NOT NULL DEFAULT 0,
  criado_em      TEXT        NOT NULL,
  expira_em      TEXT        NOT NULL,
  revogada_em    TEXT
);
CREATE INDEX ix_sessoes_utilizador ON sessoes (utilizador_id);

CREATE TABLE login_tentativas (
  id        INTEGER      PRIMARY KEY AUTOINCREMENT,      -- tabela local à nuvem, não sincroniza
  utilizador VARCHAR(50) NOT NULL,
  ip        VARCHAR(64)  NOT NULL,
  sucesso   INTEGER      NOT NULL CHECK (sucesso IN (0,1)),
  criado_em TEXT         NOT NULL
);
CREATE INDEX ix_login_utilizador ON login_tentativas (utilizador, criado_em);
CREATE INDEX ix_login_ip ON login_tentativas (ip, criado_em);

-- ---------- Registo de alterações (a ordem final é a daqui, não dos relógios) ----------
CREATE TABLE alteracoes (
  seq            INTEGER     PRIMARY KEY AUTOINCREMENT,  -- cursor de cada computador
  op_id          CHAR(26)    NOT NULL UNIQUE,            -- idempotência: reenviar não duplica
  posto_id       TEXT        NOT NULL,                   -- 'GLOBAL' para o catálogo
  entidade       VARCHAR(40) NOT NULL,
  entidade_id    CHAR(26)    NOT NULL,
  operacao       VARCHAR(10) NOT NULL CHECK (operacao IN ('inserir','atualizar','eliminar')),
  payload        TEXT        NOT NULL,                   -- JSON; em 'atualizar' só os campos alterados
  versao         INTEGER     NOT NULL,
  grupo_id       CHAR(26)    NOT NULL,                   -- CORREÇÃO: campo obrigatório (operações atômicas)
  dispositivo_id CHAR(26)    NOT NULL,                   -- CORREÇÃO: campo obrigatório
  enviado_por    CHAR(26)    NOT NULL,                   -- CORREÇÃO: campo obrigatório (quem sincronizou)
  criado_em      TEXT        NOT NULL                    -- hora de receção na nuvem
);
CREATE INDEX ix_alteracoes_posto_seq ON alteracoes (posto_id, seq);  -- CORREÇÃO: índice obrigatório
CREATE INDEX ix_alteracoes_grupo ON alteracoes (grupo_id);

CREATE TABLE backups_log (
  id         CHAR(26)     NOT NULL PRIMARY KEY,
  ficheiro   VARCHAR(255) NOT NULL,
  tamanho    INTEGER,
  estado     VARCHAR(20)  NOT NULL,
  criado_em  TEXT         NOT NULL
);

-- ---------- Pessoas ----------
CREATE TABLE encarregados (
  id              CHAR(26)     NOT NULL PRIMARY KEY,
  posto_id        CHAR(26)     NOT NULL REFERENCES postos(id),
  nome            VARCHAR(150) NOT NULL,
  nome_busca      VARCHAR(150) NOT NULL,                 -- minúsculas, sem acentos
  parentesco      VARCHAR(40),
  telefone        VARCHAR(30),
  telefone_alt    VARCHAR(30),
  telefone_busca  VARCHAR(30),                           -- só dígitos
  morada          VARCHAR(255),
  bairro          VARCHAR(100),
  criado_em       TEXT         NOT NULL,
  atualizado_em   TEXT         NOT NULL,
  dispositivo_id  CHAR(26)     NOT NULL,                 -- CORREÇÃO: campo obrigatório
  versao          INTEGER      NOT NULL DEFAULT 1,       -- CORREÇÃO: campo obrigatório
  eliminado_em    TEXT
);
CREATE INDEX ix_encarregados_telefone ON encarregados (posto_id, telefone_busca);
CREATE INDEX ix_encarregados_nome ON encarregados (posto_id, nome_busca);

CREATE TABLE criancas (
  id                         CHAR(26)     NOT NULL PRIMARY KEY,
  posto_id                   CHAR(26)     NOT NULL REFERENCES postos(id),
  numero_processo            VARCHAR(40)  NOT NULL,      -- ex.: MF-PC01-2026-000123
  numero_cartao              VARCHAR(40),
  nome                       VARCHAR(150) NOT NULL,
  nome_busca                 VARCHAR(150) NOT NULL,
  data_nascimento            TEXT         NOT NULL,
  data_nascimento_aproximada INTEGER      NOT NULL DEFAULT 0 CHECK (data_nascimento_aproximada IN (0,1)),
  sexo                       CHAR(1)      CHECK (sexo IN ('M','F')),
  encarregado_id             CHAR(26)     REFERENCES encarregados(id),
  morada                     VARCHAR(255),
  bairro                     VARCHAR(100),
  observacoes                TEXT,
  fundida_em_id              CHAR(26)     REFERENCES criancas(id),  -- fusão: nada se move, só se redireciona
  criado_em                  TEXT         NOT NULL,
  atualizado_em              TEXT         NOT NULL,
  dispositivo_id            CHAR(26)     NOT NULL,                 -- CORREÇÃO: campo obrigatório
  versao                     INTEGER      NOT NULL DEFAULT 1,       -- CORREÇÃO: campo obrigatório
  eliminado_em               TEXT
);
CREATE UNIQUE INDEX ux_criancas_processo ON criancas (posto_id, numero_processo);
CREATE INDEX ix_criancas_nome_nasc ON criancas (posto_id, nome_busca, data_nascimento);
CREATE INDEX ix_criancas_encarregado ON criancas (encarregado_id);

CREATE TABLE fusoes_criancas (
  id             CHAR(26)    NOT NULL PRIMARY KEY,
  posto_id       CHAR(26)    NOT NULL REFERENCES postos(id),
  origem_id      CHAR(26)    NOT NULL REFERENCES criancas(id),
  destino_id     CHAR(26)    NOT NULL REFERENCES criancas(id),
  utilizador_id  CHAR(26),
  motivo         TEXT,
  criado_em      TEXT        NOT NULL,
  atualizado_em  TEXT        NOT NULL,
  dispositivo_id CHAR(26)     NOT NULL,                 -- CORREÇÃO: campo obrigatório
  versao         INTEGER     NOT NULL DEFAULT 1,       -- CORREÇÃO: campo obrigatório
  eliminado_em   TEXT
);

CREATE TABLE duplicados_descartados (
  id             CHAR(26)    NOT NULL PRIMARY KEY,
  posto_id       CHAR(26)    NOT NULL REFERENCES postos(id),
  crianca_a_id   CHAR(26)    NOT NULL REFERENCES criancas(id),
  crianca_b_id   CHAR(26)    NOT NULL REFERENCES criancas(id),
  utilizador_id  CHAR(26),
  criado_em      TEXT        NOT NULL,
  atualizado_em  TEXT        NOT NULL,
  dispositivo_id CHAR(26)     NOT NULL,                 -- CORREÇÃO: campo obrigatório
  versao         INTEGER     NOT NULL DEFAULT 1,       -- CORREÇÃO: campo obrigatório
  eliminado_em   TEXT
);

-- ---------- Catálogo (global: posto_id NULL; só o administrador central edita) ----------
CREATE TABLE vacinas (
  id               CHAR(26)     NOT NULL PRIMARY KEY,    -- ID fixo no seed (igual em todos os postos)
  posto_id         CHAR(26),                             -- sempre NULL (global)
  codigo           VARCHAR(30)  NOT NULL UNIQUE,
  nome             VARCHAR(150) NOT NULL,
  tipo             VARCHAR(12)  NOT NULL CHECK (tipo IN ('vacina','suplemento')),
  via_administracao VARCHAR(40),                         -- NULL: a validar pela equipa clínica
  ativo            INTEGER      NOT NULL DEFAULT 1 CHECK (ativo IN (0,1)),
  ordem            INTEGER      NOT NULL DEFAULT 0,
  criado_em        TEXT         NOT NULL,
  atualizado_em    TEXT         NOT NULL,
  dispositivo_id   CHAR(26)     NOT NULL,                 -- CORREÇÃO: campo obrigatório
  versao           INTEGER      NOT NULL DEFAULT 1,       -- CORREÇÃO: campo obrigatório
  eliminado_em     TEXT
);

CREATE TABLE calendario_vacinal (
  id                    CHAR(26)     NOT NULL PRIMARY KEY,
  posto_id              CHAR(26),                        -- sempre NULL (global)
  vacina_id             CHAR(26)     NOT NULL REFERENCES vacinas(id),
  numero_dose           INTEGER      NOT NULL,
  rotulo_idade          VARCHAR(30)  NOT NULL,           -- ex.: 'Ao nascer', '5-6 anos'
  idade_ideal_meses     INTEGER,
  janela_inicio         INTEGER,
  janela_fim            INTEGER,
  idade_minima          INTEGER,
  intervalo_minimo_dias INTEGER,
  dose_anterior_exigida CHAR(26)     REFERENCES calendario_vacinal(id),
  grupo_alternativa     VARCHAR(40),                     -- linhas do mesmo grupo = uma escolha na hora
  dose_unica            INTEGER      NOT NULL DEFAULT 0 CHECK (dose_unica IN (0,1)),
  ativo                 INTEGER      NOT NULL DEFAULT 1 CHECK (ativo IN (0,1)),
  validar_clinica       INTEGER      NOT NULL DEFAULT 1 CHECK (validar_clinica IN (0,1)),
  notas_validacao       TEXT,
  criado_em             TEXT         NOT NULL,
  atualizado_em         TEXT         NOT NULL,
  dispositivo_id        CHAR(26),
  versao                INTEGER      NOT NULL DEFAULT 1,
  eliminado_em          TEXT
);
CREATE INDEX ix_calendario_vacina ON calendario_vacinal (vacina_id, numero_dose);

-- ---------- Consultas e vacinação (só se acrescenta) ----------
CREATE TABLE consultas (
  id             CHAR(26)    NOT NULL PRIMARY KEY,
  posto_id       CHAR(26)    NOT NULL REFERENCES postos(id),
  crianca_id     CHAR(26)    NOT NULL REFERENCES criancas(id),
  data_consulta  TEXT        NOT NULL,
  utilizador_id  CHAR(26),
  observacoes    TEXT,
  criado_em      TEXT        NOT NULL,
  atualizado_em  TEXT        NOT NULL,
  dispositivo_id CHAR(26)     NOT NULL,                 -- CORREÇÃO: campo obrigatório
  versao         INTEGER     NOT NULL DEFAULT 1,       -- CORREÇÃO: campo obrigatório
  eliminado_em   TEXT
);
CREATE INDEX ix_consultas_crianca ON consultas (crianca_id, data_consulta);

CREATE TABLE vacinacoes (
  id                    CHAR(26)     NOT NULL PRIMARY KEY,
  posto_id              CHAR(26)     NOT NULL REFERENCES postos(id),
  crianca_id            CHAR(26)     NOT NULL REFERENCES criancas(id),
  consulta_id           CHAR(26)     REFERENCES consultas(id),
  vacina_id             CHAR(26)     NOT NULL REFERENCES vacinas(id),
  calendario_id         CHAR(26)     REFERENCES calendario_vacinal(id),  -- NULL se fora do calendário
  numero_dose           INTEGER      NOT NULL,
  data_administracao    TEXT         NOT NULL,           -- dado clínico, registado pelo enfermeiro
  lote_id               CHAR(26)     REFERENCES stock_lotes(id),         -- NULL se origem externa
  administrado_por_id   CHAR(26),
  administrado_por_nome VARCHAR(150) NOT NULL,           -- fica legível mesmo que o utilizador mude
  local_anatomico       VARCHAR(40),
  origem                VARCHAR(10)  NOT NULL DEFAULT 'posto' CHECK (origem IN ('posto','externa')),
  observacoes           TEXT,
  reacoes               TEXT,
  criado_em             TEXT         NOT NULL,
  atualizado_em         TEXT         NOT NULL,
  dispositivo_id        CHAR(26),
  versao                INTEGER      NOT NULL DEFAULT 1,
  eliminado_em          TEXT
);
CREATE INDEX ix_vacinacoes_dose ON vacinacoes (crianca_id, vacina_id, numero_dose);
CREATE INDEX ix_vacinacoes_data ON vacinacoes (posto_id, data_administracao);

CREATE TABLE vacinacao_anulacoes (
  id             CHAR(26)    NOT NULL PRIMARY KEY,
  posto_id       CHAR(26)    NOT NULL REFERENCES postos(id),
  vacinacao_id   CHAR(26)    NOT NULL UNIQUE REFERENCES vacinacoes(id),
  motivo         TEXT        NOT NULL CHECK (length(trim(motivo)) > 0),
  utilizador_id  CHAR(26),
  criado_em      TEXT        NOT NULL,
  atualizado_em  TEXT        NOT NULL,
  dispositivo_id CHAR(26)     NOT NULL,                 -- CORREÇÃO: campo obrigatório
  versao         INTEGER     NOT NULL DEFAULT 1,       -- CORREÇÃO: campo obrigatório
  eliminado_em   TEXT
);

-- ---------- Stock (só movimentos; o saldo é calculado) ----------
-- Índice NÃO único: o mesmo lote pode ser criado em dois computadores offline.
-- O saldo agrupa por (posto_id, vacina_id, numero_lote).
CREATE TABLE stock_lotes (
  id             CHAR(26)     NOT NULL PRIMARY KEY,
  posto_id       CHAR(26)     NOT NULL REFERENCES postos(id),
  vacina_id      CHAR(26)     NOT NULL REFERENCES vacinas(id),
  numero_lote    VARCHAR(60)  NOT NULL CHECK (numero_lote = UPPER(TRIM(numero_lote))),
  validade       TEXT         NOT NULL,
  fabricante     VARCHAR(100),
  criado_em      TEXT         NOT NULL,
  atualizado_em  TEXT         NOT NULL,
  dispositivo_id CHAR(26)     NOT NULL,                 -- CORREÇÃO: campo obrigatório
  versao         INTEGER      NOT NULL DEFAULT 1,       -- CORREÇÃO: campo obrigatório
  eliminado_em   TEXT
);
CREATE INDEX ix_lotes_natural ON stock_lotes (posto_id, vacina_id, numero_lote);  -- CORREÇÃO: índice NÃO único

CREATE TABLE stock_movimentos (
  id             CHAR(26)    NOT NULL PRIMARY KEY,
  posto_id       CHAR(26)    NOT NULL REFERENCES postos(id),
  lote_id        CHAR(26)    NOT NULL REFERENCES stock_lotes(id),
  tipo           VARCHAR(20) NOT NULL
                 CHECK (tipo IN ('entrada','saida_vacinacao','perda','ajuste_mais','ajuste_menos')),
  quantidade     INTEGER     NOT NULL CHECK (quantidade > 0),   -- em doses; o sinal vem do tipo
  motivo         TEXT,
  vacinacao_id   CHAR(26)    REFERENCES vacinacoes(id),
  data_movimento TEXT        NOT NULL,
  utilizador_id  CHAR(26),
  criado_em      TEXT        NOT NULL,
  atualizado_em  TEXT        NOT NULL,
  dispositivo_id CHAR(26)     NOT NULL,                 -- CORREÇÃO: campo obrigatório
  versao         INTEGER     NOT NULL DEFAULT 1,       -- CORREÇÃO: campo obrigatório
  eliminado_em   TEXT,
  CHECK (tipo NOT IN ('perda','ajuste_mais','ajuste_menos') OR length(trim(coalesce(motivo,''))) > 0)
);
CREATE INDEX ix_movimentos_lote ON stock_movimentos (lote_id);

CREATE VIEW stock_saldo_lotes AS
SELECT l.posto_id, l.vacina_id, l.numero_lote,
       MIN(l.validade) AS validade,
       COALESCE(SUM(CASE m.tipo
         WHEN 'entrada'         THEN  m.quantidade
         WHEN 'ajuste_mais'     THEN  m.quantidade
         WHEN 'saida_vacinacao' THEN -m.quantidade
         WHEN 'perda'           THEN -m.quantidade
         WHEN 'ajuste_menos'    THEN -m.quantidade
       END), 0) AS saldo
FROM stock_lotes l
LEFT JOIN stock_movimentos m ON m.lote_id = l.id AND m.eliminado_em IS NULL
WHERE l.eliminado_em IS NULL
GROUP BY l.posto_id, l.vacina_id, l.numero_lote;

-- ---------- Contactos a faltosos e auditoria ----------
CREATE TABLE contactos_faltosos (
  id             CHAR(26)    NOT NULL PRIMARY KEY,
  posto_id       CHAR(26)    NOT NULL REFERENCES postos(id),
  crianca_id     CHAR(26)    NOT NULL REFERENCES criancas(id),
  data_contacto  TEXT        NOT NULL,
  canal          VARCHAR(10) NOT NULL DEFAULT 'telefone' CHECK (canal IN ('telefone','sms','whatsapp')),
  resultado      VARCHAR(40),
  notas          TEXT,
  utilizador_id  CHAR(26),
  criado_em      TEXT        NOT NULL,
  atualizado_em  TEXT        NOT NULL,
  dispositivo_id CHAR(26)     NOT NULL,                 -- CORREÇÃO: campo obrigatório
  versao         INTEGER     NOT NULL DEFAULT 1,       -- CORREÇÃO: campo obrigatório
  eliminado_em   TEXT
);
CREATE INDEX ix_contactos_crianca ON contactos_faltosos (crianca_id, data_contacto);

CREATE TABLE auditoria (
  id             CHAR(26)    NOT NULL PRIMARY KEY,
  posto_id       CHAR(26)    NOT NULL REFERENCES postos(id),
  entidade       VARCHAR(40) NOT NULL,
  entidade_id    CHAR(26)    NOT NULL,
  acao           VARCHAR(20) NOT NULL,
  campo          VARCHAR(60),
  valor_anterior TEXT,
  valor_novo     TEXT,
  utilizador_id  CHAR(26),
  criado_em      TEXT        NOT NULL,
  atualizado_em  TEXT        NOT NULL,
  dispositivo_id CHAR(26)     NOT NULL,                 -- CORREÇÃO: campo obrigatório
  versao         INTEGER     NOT NULL DEFAULT 1,       -- CORREÇÃO: campo obrigatório
  eliminado_em   TEXT
);
CREATE INDEX ix_auditoria_entidade ON auditoria (entidade, entidade_id, criado_em);
