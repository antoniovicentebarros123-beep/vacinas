-- =============================================================================
-- Schema MySQL/MariaDB para sistema de controlo de vacinação infantil
-- Compatibility: MariaDB 10.4.32 e MySQL 8 (não testado em MySQL 8)
-- InnoDB, utf8mb4_unicode_ci, chaves estrangeiras reais
-- IDs BIGINT UNSIGNED AUTO_INCREMENT
-- Ordem de criação: configuracoes, comunas, bairros, utilizadores, login_tentativas, encarregados, criancas, vacinas, calendario_vacinal, stock_lotes, consultas, vacinacoes, vacinacao_anulacoes, stock_movimentos, auditoria, contactos_faltosos
-- =============================================================================

-- ---------- Configurações ----------
CREATE TABLE configuracoes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único da configuração',
  chave VARCHAR(100) NOT NULL UNIQUE COMMENT 'Chave de identificação da configuração',
  valor TEXT NOT NULL COMMENT 'Valor da configuração',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de última atualização',
  criado_por VARCHAR(150) COMMENT 'Utilizador que criou a configuração'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Tabela de configurações do sistema';

-- ---------- Território (comunas e bairros) ----------
CREATE TABLE comunas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único da comuna',
  nome VARCHAR(100) NOT NULL COMMENT 'Nome da comuna',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de última atualização',
  criado_por VARCHAR(150) COMMENT 'Utilizador que criou a comuna',
  eliminado_em DATETIME COMMENT 'Data de eliminação lógica'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Tabela de comunas (território administrativo)';

CREATE TABLE bairros (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único do bairro',
  comuna_id BIGINT UNSIGNED NOT NULL COMMENT 'ID da comuna a que pertence',
  nome VARCHAR(100) NOT NULL COMMENT 'Nome do bairro',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de última atualização',
  criado_por VARCHAR(150) COMMENT 'Utilizador que criou o bairro',
  eliminado_em DATETIME COMMENT 'Data de eliminação lógica',
  FOREIGN KEY (comuna_id) REFERENCES comunas(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Tabela de bairros dentro das comunas';
CREATE INDEX idx_bairros_comuna ON bairros(comuna_id);

-- ---------- Utilizadores ----------
CREATE TABLE utilizadores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único do utilizador',
  nome VARCHAR(150) NOT NULL COMMENT 'Nome completo do utilizador',
  utilizador VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nome de utilizador único para login',
  password_hash VARCHAR(255) NOT NULL COMMENT 'Hash da palavra-passe (Argon2id)',
  perfil VARCHAR(25) NOT NULL CHECK (perfil IN ('administrador','enfermeiro','recepcao')) COMMENT 'Perfil de acesso do utilizador',
  ativo TINYINT NOT NULL DEFAULT 1 CHECK (ativo IN (0,1)) COMMENT 'Indica se o utilizador está ativo',
  deve_mudar_password TINYINT NOT NULL DEFAULT 1 CHECK (deve_mudar_password IN (0,1)) COMMENT 'Indica se deve mudar a palavra-passe',
  ultimo_login DATETIME COMMENT 'Data do último login bem-sucedido',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de última atualização',
  criado_por VARCHAR(150) COMMENT 'Utilizador que criou este registo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Tabela de utilizadores do sistema';

CREATE TABLE login_tentativas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único da tentativa de login',
  utilizador VARCHAR(50) NOT NULL COMMENT 'Nome de utilizador tentado',
  ip VARCHAR(64) NOT NULL COMMENT 'Endereço IP da tentativa',
  sucesso TINYINT NOT NULL CHECK (sucesso IN (0,1)) COMMENT 'Indica se o login foi bem-sucedido',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data da tentativa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Tabela de registos de tentativas de login';
CREATE INDEX idx_login_tentativas_utilizador ON login_tentativas(utilizador, criado_em);
CREATE INDEX idx_login_tentativas_ip ON login_tentativas(ip, criado_em);

-- ---------- Pessoas ----------
CREATE TABLE encarregados (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único do encarregado de educação',
  nome VARCHAR(150) NOT NULL COMMENT 'Nome completo do encarregado',
  parentesco VARCHAR(40) COMMENT 'Grau de parentesco com a criança',
  telefone VARCHAR(30) COMMENT 'Telefone principal (apenas dígitos)',
  telefone_alt VARCHAR(30) COMMENT 'Telefone alternativo (apenas dígitos)',
  morada VARCHAR(255) COMMENT 'Morada completa do encarregado',
  bairro_id BIGINT UNSIGNED COMMENT 'ID do bairro de residência',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de última atualização',
  criado_por VARCHAR(150) COMMENT 'Utilizador que criou o registo',
  eliminado_em DATETIME COMMENT 'Data de eliminação lógica',
  FOREIGN KEY (bairro_id) REFERENCES bairros(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Tabela de encarregados de educação';
CREATE INDEX idx_encarregados_nome ON encarregados(nome);
CREATE INDEX idx_encarregados_telefone ON encarregados(telefone);

CREATE TABLE criancas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único da criança',
  numero_processo VARCHAR(40) NOT NULL COMMENT 'Número do processo de saúde',
  numero_cartao VARCHAR(40) COMMENT 'Número do cartão de vacinação',
  nome VARCHAR(150) NOT NULL COMMENT 'Nome completo da criança',
  data_nascimento DATE NOT NULL COMMENT 'Data de nascimento da criança',
  data_nascimento_aproximada TINYINT NOT NULL DEFAULT 0 CHECK (data_nascimento_aproximada IN (0,1)) COMMENT 'Indica se a data de nascimento é aproximada',
  sexo CHAR(1) CHECK (sexo IN ('M','F')) COMMENT 'Sexo da criança (M=Masculino, F=Feminino)',
  encarregado_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do encarregado responsável',
  observacoes TEXT COMMENT 'Observações sobre a criança',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de última atualização',
  criado_por VARCHAR(150) COMMENT 'Utilizador que criou o registo',
  eliminado_em DATETIME COMMENT 'Data de eliminação lógica',
  FOREIGN KEY (encarregado_id) REFERENCES encarregados(id) ON DELETE RESTRICT,
  UNIQUE KEY (numero_processo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Tabela de crianças com histórico de vacinação';
CREATE INDEX idx_criancas_nome ON criancas(nome);
CREATE INDEX idx_criancas_data_nascimento ON criancas(data_nascimento);

-- ---------- Catálogo (vacinas e calendário) ----------
CREATE TABLE vacinas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único da vacina',
  codigo VARCHAR(30) NOT NULL UNIQUE COMMENT 'Código único da vacina',
  nome VARCHAR(150) NOT NULL COMMENT 'Nome completo da vacina/suplemento',
  tipo VARCHAR(12) NOT NULL CHECK (tipo IN ('vacina','suplemento')) COMMENT 'Tipo de imunização',
  via_administracao VARCHAR(40) COMMENT 'Via de administração',
  ativo TINYINT NOT NULL DEFAULT 1 CHECK (ativo IN (0,1)) COMMENT 'Indica se está ativa no sistema',
  ordem INTEGER NOT NULL DEFAULT 0 COMMENT 'Ordem de apresentação no calendário',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de última atualização',
  criado_por VARCHAR(150) COMMENT 'Utilizador que criou o registo',
  eliminado_em DATETIME COMMENT 'Data de eliminação lógica'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Tabela do catálogo de vacinas e suplementos';

CREATE TABLE calendario_vacinal (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único do item do calendário',
  vacina_id BIGINT UNSIGNED NOT NULL COMMENT 'ID da vacina associada',
  numero_dose INTEGER NOT NULL COMMENT 'Número da dose na sequência',
  rotulo_idade VARCHAR(30) NOT NULL COMMENT 'Rótulo de idade (ex: \"2 meses\")',
  idade_ideal_meses INTEGER COMMENT 'Idade ideal em meses para a dose',
  janela_inicio_meses INTEGER COMMENT 'Início da janela recomendada em meses',
  janela_fim_meses INTEGER COMMENT 'Fim da janela recomendada em meses',
  idade_minima_meses INTEGER COMMENT 'Idade mínima em meses para a dose',
  intervalo_minimo_dias INTEGER COMMENT 'Intervalo mínimo em dias em relação à dose anterior',
  dose_anterior_exigida BIGINT UNSIGNED COMMENT 'ID da dose anterior obrigatória',
  grupo_alternativa VARCHAR(40) COMMENT 'Grupo de vacinas alternativas',
  dose_unica TINYINT NOT NULL DEFAULT 0 CHECK (dose_unica IN (0,1)) COMMENT 'Indica se é dose única',
  ativo TINYINT NOT NULL DEFAULT 1 CHECK (ativo IN (0,1)) COMMENT 'Indica se está ativo',
  validar_clinica TINYINT NOT NULL DEFAULT 1 CHECK (validar_clinica IN (0,1)) COMMENT 'Indica se requer validação clínica',
  notas_validacao TEXT COMMENT 'Notas de validação clínica',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de última atualização',
  criado_por VARCHAR(150) COMMENT 'Utilizador que criou o registo',
  eliminado_em DATETIME COMMENT 'Data de eliminação lógica',
  FOREIGN KEY (vacina_id) REFERENCES vacinas(id) ON DELETE RESTRICT,
  FOREIGN KEY (dose_anterior_exigida) REFERENCES calendario_vacinal(id) ON DELETE RESTRICT,
  KEY (vacina_id, numero_dose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Calendário de vacinações por idade e dose';

-- ---------- Stock (movimentos) ----------
CREATE TABLE stock_lotes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único do lote',
  vacina_id BIGINT UNSIGNED NOT NULL COMMENT 'ID da vacina do lote',
  numero_lote VARCHAR(60) NOT NULL COMMENT 'Número do lote (normalizado em PHP)',
  validade DATE NOT NULL COMMENT 'Data de validade da vacina',
  fabricante VARCHAR(100) COMMENT 'Fabricante da vacina',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de última atualização',
  criado_por VARCHAR(150) COMMENT 'Utilizador que criou o registo',
  eliminado_em DATETIME COMMENT 'Data de eliminação lógica',
  FOREIGN KEY (vacina_id) REFERENCES vacinas(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Tabela de lotes de vacinas';

CREATE TABLE stock_movimentos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único do movimento de stock',
  lote_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do lote movimentado',
  tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('entrada','saida_vacinacao','perda','ajuste_mais','ajuste_menos')) COMMENT 'Tipo de movimento',
  quantidade INTEGER NOT NULL CHECK (quantidade > 0) COMMENT 'Quantidade movimentada',
  motivo TEXT NOT NULL COMMENT 'Motivo do movimento (obrigatório para perda, ajustes)',
  vacinacao_id BIGINT UNSIGNED COMMENT 'ID da vacinação associada (apenas para saída)',
  data_movimento DATE NOT NULL COMMENT 'Data do movimento',
  utilizador_id BIGINT UNSIGNED COMMENT 'ID do utilizador que registou o movimento',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  criado_por VARCHAR(150) COMMENT 'Utilizador que criou o registo',
  FOREIGN KEY (lote_id) REFERENCES stock_lotes(id) ON DELETE RESTRICT,
  FOREIGN KEY (vacinacao_id) REFERENCES vacinacoes(id) ON DELETE RESTRICT,
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE RESTRICT,
  KEY (lote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Movimentos de stock de vacinas';

-- ---------- Consultas e vacinação ----------
CREATE TABLE consultas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único da consulta',
  crianca_id BIGINT UNSIGNED NOT NULL COMMENT 'ID da criança consultada',
  data_consulta DATE NOT NULL COMMENT 'Data da consulta',
  utilizador_id BIGINT UNSIGNED COMMENT 'ID do utilizador que registou a consulta',
  observacoes TEXT COMMENT 'Observações da consulta',
  peso_kg DECIMAL(5,2) COMMENT 'Peso em kg',
  altura_cm DECIMAL(5,2) COMMENT 'Altura em cm',
  perimetro_cefalico_cm DECIMAL(5,2) COMMENT 'Perímetro cefálico em cm',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de última atualização',
  criado_por VARCHAR(150) COMMENT 'Utilizador que criou o registo',
  eliminado_em DATETIME COMMENT 'Data de eliminação lógica',
  FOREIGN KEY (crianca_id) REFERENCES criancas(id) ON DELETE RESTRICT,
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE RESTRICT,
  KEY (crianca_id, data_consulta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Registos de consultas médicas';

CREATE TABLE vacinacoes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único da vacinação',
  crianca_id BIGINT UNSIGNED NOT NULL COMMENT 'ID da criança vacinada',
  consulta_id BIGINT UNSIGNED COMMENT 'ID da consulta associada à vacinação',
  vacina_id BIGINT UNSIGNED NOT NULL COMMENT 'ID da vacina administrada',
  calendario_id BIGINT UNSIGNED COMMENT 'ID do calendário vacinal correspondente',
  numero_dose INTEGER NOT NULL COMMENT 'Número da dose administrada',
  data_administracao DATE NOT NULL COMMENT 'Data da administração da vacina',
  lote_id BIGINT UNSIGNED COMMENT 'ID do lote de vacina utilizado',
  administrado_por_id BIGINT UNSIGNED COMMENT 'ID do utilizador que administrou',
  administrado_por_nome VARCHAR(150) NOT NULL COMMENT 'Nome do utilizador que administrou',
  local_anatomico VARCHAR(40) COMMENT 'Local anatômico de administração',
  origem VARCHAR(10) NOT NULL DEFAULT 'posto' CHECK (origem IN ('posto','externa')) COMMENT 'Origem da vacinação',
  observacoes TEXT COMMENT 'Observações sobre a vacinação',
  reacoes TEXT COMMENT 'Reações adversas registradas',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data de última atualização',
  criado_por VARCHAR(150) COMMENT 'Utilizador que criou o registo',
  eliminado_em DATETIME COMMENT 'Data de eliminação lógica',
  FOREIGN KEY (crianca_id) REFERENCES criancas(id) ON DELETE RESTRICT,
  FOREIGN KEY (consulta_id) REFERENCES consultas(id) ON DELETE RESTRICT,
  FOREIGN KEY (vacina_id) REFERENCES vacinas(id) ON DELETE RESTRICT,
  FOREIGN KEY (calendario_id) REFERENCES calendario_vacinal(id) ON DELETE RESTRICT,
  FOREIGN KEY (lote_id) REFERENCES stock_lotes(id) ON DELETE RESTRICT,
  FOREIGN KEY (administrado_por_id) REFERENCES utilizadores(id) ON DELETE RESTRICT,
  KEY (crianca_id, vacina_id, numero_dose),
  KEY (data_administracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Registos de vacinações administradas';

CREATE TABLE vacinacao_anulacoes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único da anulação',
  vacinacao_id BIGINT UNSIGNED NOT NULL UNIQUE COMMENT 'ID da vacinação anulada',
  motivo TEXT NOT NULL CHECK (CHAR_LENGTH(TRIM(motivo)) > 0) COMMENT 'Motivo da anulação',
  utilizador_id BIGINT UNSIGNED COMMENT 'ID do utilizador que anulou',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data da anulação',
  criado_por VARCHAR(150) COMMENT 'Utilizador que registou a anulação',
  FOREIGN KEY (vacinacao_id) REFERENCES vacinacoes(id) ON DELETE RESTRICT,
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Anulações de vacinações';

-- ---------- Auditoria e contactos ----------
CREATE TABLE auditoria (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único do registo de auditoria',
  entidade VARCHAR(40) NOT NULL COMMENT 'Nome da tabela auditada',
  entidade_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do registo auditado',
  acao VARCHAR(20) NOT NULL COMMENT 'Ação realizada (CREATE, UPDATE, DELETE)',
  campo VARCHAR(60) COMMENT 'Campo alterado',
  valor_anterior TEXT COMMENT 'Valor anterior ao alterar',
  valor_novo TEXT COMMENT 'Valor novo após alteração',
  utilizador_id BIGINT UNSIGNED COMMENT 'ID do utilizador que realizou a ação',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data da ação',
  criado_por VARCHAR(150) COMMENT 'Utilizador que registrou a ação',
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Tabela de auditoria do sistema';
CREATE INDEX idx_auditoria_entidade ON auditoria(entidade, entidade_id, criado_em);

CREATE TABLE contactos_faltosos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID único do contacto',
  crianca_id BIGINT UNSIGNED NOT NULL COMMENT 'ID da criança contactada',
  data_contacto DATETIME NOT NULL COMMENT 'Data e hora do contacto',
  canal VARCHAR(10) NOT NULL DEFAULT 'telefone' CHECK (canal IN ('telefone','sms','whatsapp')) COMMENT 'Canal de contacto',
  resultado VARCHAR(100) COMMENT 'Resultado do contacto',
  notas TEXT COMMENT 'Notas sobre o contacto',
  utilizador_id BIGINT UNSIGNED COMMENT 'ID do utilizador que registrou o contacto',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
  criado_por VARCHAR(150) COMMENT 'Utilizador que criou o registo',
  eliminado_em DATETIME COMMENT 'Data de eliminação lógica',
  FOREIGN KEY (crianca_id) REFERENCES criancas(id) ON DELETE RESTRICT,
  FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE RESTRICT,
  KEY (crianca_id, data_contacto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'Contactos realizados a crianças faltosas';

-- ---------- Vista de saldo de stock ----------
CREATE VIEW stock_saldo_lotes AS
SELECT 
  sl.id,
  sl.vacina_id,
  v.codigo AS vacina_codigo,
  v.nome AS vacina_nome,
  sl.numero_lote,
  MIN(sl.validade) AS validade,
  sl.fabricante,
  COALESCE(SUM(
    CASE WHEN sm.tipo = 'entrada' THEN sm.quantidade
         WHEN sm.tipo = 'saida_vacinacao' THEN -sm.quantidade
         WHEN sm.tipo = 'perda' THEN -sm.quantidade
         WHEN sm.tipo = 'ajuste_mais' THEN sm.quantidade
         WHEN sm.tipo = 'ajuste_menos' THEN -sm.quantidade
         ELSE 0 END
  ), 0) AS saldo_atual
FROM stock_lotes sl
JOIN vacinas v ON sl.vacina_id = v.id
LEFT JOIN stock_movimentos sm ON sl.id = sm.lote_id AND sm.eliminado_em IS NULL
WHERE sl.eliminado_em IS NULL
GROUP BY sl.id, sl.vacina_id, v.codigo, v.nome, sl.numero_lote, sl.validade, sl.fabricante;
-- =============================================================================
-- Fim do schema
-- =============================================================================