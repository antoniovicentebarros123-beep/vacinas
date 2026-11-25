-- Schema do sistema de controlo de vacinação infantil.
-- Base de dados: vacinas_db (deve existir antes da importação).
-- Motor: InnoDB; codificação: utf8mb4; collation: utf8mb4_unicode_ci.
-- Compatível com MariaDB 10.4 e MySQL 8.

-- Configurações do sistema.
CREATE TABLE configuracoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador da configuração',
    chave VARCHAR(100) NOT NULL COMMENT 'Chave única da configuração',
    valor TEXT NOT NULL COMMENT 'Valor da configuração',
    descricao TEXT NULL COMMENT 'Descrição da finalidade da configuração',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data e hora da última alteração',
    PRIMARY KEY (id),
    UNIQUE KEY uk_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurações gerais do sistema';

-- Bairros.
CREATE TABLE bairros (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador do bairro',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome do bairro',
    ativo BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indica se o bairro está ativo',
    eliminado_em DATETIME NULL COMMENT 'Data e hora de eliminação lógica',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data e hora da última alteração',
    criado_por_id BIGINT UNSIGNED NULL COMMENT 'Utilizador que criou o registo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bairros disponíveis para os encarregados';

-- Utilizadores.
CREATE TABLE utilizadores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador do utilizador',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome completo do utilizador',
    utilizador VARCHAR(50) NOT NULL COMMENT 'Nome de acesso único',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Hash Argon2id da palavra-passe',
    perfil ENUM('administrador', 'enfermeiro', 'recepcao', 'responsavel_stock') NOT NULL COMMENT 'Perfil de acesso do utilizador',
    ativo BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indica se a conta está ativa',
    deve_mudar_password BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Obriga a mudar a palavra-passe no primeiro acesso',
    tentativas_falhadas TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Número de tentativas falhadas consecutivas',
    bloqueado_ate DATETIME NULL COMMENT 'Data e hora até à qual a conta está bloqueada',
    ultimo_login DATETIME NULL COMMENT 'Data e hora do último login bem-sucedido',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data e hora da última alteração',
    criado_por_id BIGINT UNSIGNED NULL COMMENT 'Utilizador que criou a conta',
    PRIMARY KEY (id),
    UNIQUE KEY uk_utilizador (utilizador),
    CONSTRAINT ck_utilizadores_perfil CHECK (perfil IN ('administrador', 'enfermeiro', 'recepcao', 'responsavel_stock')),
    CONSTRAINT fk_utilizadores_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Utilizadores do sistema e respetivos perfis';

-- A tabela bairros é criada antes dos utilizadores, pelo que a FK de autoria é acrescentada agora.
ALTER TABLE bairros
    ADD CONSTRAINT fk_bairros_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT;

-- Tentativas de login (apenas acrescentar).
CREATE TABLE login_tentativas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador da tentativa',
    utilizador VARCHAR(50) NOT NULL COMMENT 'Nome de acesso usado na tentativa',
    sucesso BOOLEAN NOT NULL COMMENT 'Indica se o login foi bem-sucedido',
    ip VARCHAR(45) NOT NULL COMMENT 'Endereço IP de origem',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora da tentativa',
    PRIMARY KEY (id),
    KEY idx_utilizador (utilizador, criado_em),
    KEY idx_ip (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registo append-only de tentativas de login';

-- Encarregados de educação.
CREATE TABLE encarregados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador do encarregado',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome completo do encarregado',
    parentesco VARCHAR(50) NOT NULL COMMENT 'Parentesco com a criança',
    telefone VARCHAR(20) NOT NULL COMMENT 'Telefone guardado apenas com dígitos',
    telefone_alt VARCHAR(20) NULL COMMENT 'Telefone alternativo apenas com dígitos',
    morada TEXT NULL COMMENT 'Morada do encarregado',
    bairro_id BIGINT UNSIGNED NULL COMMENT 'Bairro do encarregado',
    ativo BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indica se o encarregado está ativo',
    eliminado_em DATETIME NULL COMMENT 'Data e hora de eliminação lógica',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data e hora da última alteração',
    criado_por_id BIGINT UNSIGNED NULL COMMENT 'Utilizador que criou o registo',
    PRIMARY KEY (id),
    KEY idx_nome (nome),
    KEY idx_telefone (telefone),
    CONSTRAINT fk_encarregados_bairro FOREIGN KEY (bairro_id) REFERENCES bairros (id) ON DELETE RESTRICT,
    CONSTRAINT fk_encarregados_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Encarregados de educação das crianças';

-- Crianças.
CREATE TABLE criancas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador da criança',
    numero_processo VARCHAR(50) NOT NULL COMMENT 'Número de processo único',
    numero_cartao VARCHAR(50) NULL COMMENT 'Número do cartão de vacinação',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome completo da criança',
    data_nascimento DATE NOT NULL COMMENT 'Data de nascimento da criança',
    data_nascimento_aproximada BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Indica se a data de nascimento é aproximada',
    sexo ENUM('M', 'F') NOT NULL COMMENT 'Sexo da criança',
    encarregado_id BIGINT UNSIGNED NOT NULL COMMENT 'Encarregado principal da criança',
    observacoes TEXT NULL COMMENT 'Observações gerais',
    ativo BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indica se a criança está ativa',
    eliminado_em DATETIME NULL COMMENT 'Data e hora de eliminação lógica',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data e hora da última alteração',
    criado_por_id BIGINT UNSIGNED NULL COMMENT 'Utilizador que criou o registo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_numero_processo (numero_processo),
    KEY idx_nome_nascimento (nome, data_nascimento),
    CONSTRAINT ck_criancas_sexo CHECK (sexo IN ('M', 'F')),
    CONSTRAINT fk_criancas_encarregado FOREIGN KEY (encarregado_id) REFERENCES encarregados (id) ON DELETE RESTRICT,
    CONSTRAINT fk_criancas_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registo de crianças do posto';

-- Vacinas.
CREATE TABLE vacinas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador da vacina',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome da vacina ou suplemento',
    descricao TEXT NULL COMMENT 'Descrição do catálogo',
    ativo BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indica se o item está ativo',
    eliminado_em DATETIME NULL COMMENT 'Data e hora de eliminação lógica',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data e hora da última alteração',
    criado_por_id BIGINT UNSIGNED NULL COMMENT 'Utilizador que criou o registo',
    PRIMARY KEY (id),
    UNIQUE KEY uk_nome (nome),
    CONSTRAINT fk_vacinas_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de vacinas e suplementos';

-- Calendário vacinal.
CREATE TABLE calendario_vacinal (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador da dose do calendário',
    vacina_id BIGINT UNSIGNED NOT NULL COMMENT 'Vacina desta dose',
    numero_dose TINYINT UNSIGNED NOT NULL COMMENT 'Número da dose no cartão',
    rotulo_idade VARCHAR(50) NOT NULL COMMENT 'Rótulo de idade transcrito do cartão',
    idade_ideal_meses SMALLINT UNSIGNED NOT NULL COMMENT 'Idade ideal em meses',
    janela_inicio_meses SMALLINT UNSIGNED NULL COMMENT 'Início da janela em meses, quando indicado',
    janela_fim_meses SMALLINT UNSIGNED NULL COMMENT 'Fim da janela em meses, quando indicado',
    idade_minima_meses SMALLINT UNSIGNED NULL COMMENT 'Idade mínima em meses, quando validada',
    intervalo_minimo_dias SMALLINT UNSIGNED NULL COMMENT 'Intervalo mínimo em dias, quando validado',
    dose_anterior_exigida BIGINT UNSIGNED NULL COMMENT 'Dose anterior exigida, quando validada',
    grupo_alternativa VARCHAR(50) NULL COMMENT 'Grupo de vacinas alternativas',
    dose_unica BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Indica se é dose única',
    ativo BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indica se a linha está ativa',
    validar_clinica BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indica que os valores clínicos precisam de validação',
    notas_validacao TEXT NULL COMMENT 'Notas de transcrição e validação clínica',
    eliminado_em DATETIME NULL COMMENT 'Data e hora de eliminação lógica',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data e hora da última alteração',
    criado_por_id BIGINT UNSIGNED NULL COMMENT 'Utilizador que criou o registo',
    PRIMARY KEY (id),
    KEY idx_vacina_dose (vacina_id, numero_dose),
    KEY idx_dose_anterior (dose_anterior_exigida),
    CONSTRAINT ck_calendario_janela CHECK (janela_inicio_meses IS NULL OR janela_fim_meses IS NULL OR janela_inicio_meses <= janela_fim_meses),
    CONSTRAINT fk_calendario_vacina FOREIGN KEY (vacina_id) REFERENCES vacinas (id) ON DELETE RESTRICT,
    CONSTRAINT fk_calendario_dose_anterior FOREIGN KEY (dose_anterior_exigida) REFERENCES calendario_vacinal (id) ON DELETE RESTRICT,
    CONSTRAINT fk_calendario_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Calendário vacinal transcrito do cartão';

-- Lotes de stock.
CREATE TABLE stock_lotes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador do lote',
    vacina_id BIGINT UNSIGNED NOT NULL COMMENT 'Vacina do lote',
    numero_lote VARCHAR(50) NOT NULL COMMENT 'Número do lote normalizado',
    validade DATE NOT NULL COMMENT 'Data de validade do lote',
    fabricante VARCHAR(100) NOT NULL COMMENT 'Fabricante do lote',
    quantidade_inicial INT UNSIGNED NOT NULL COMMENT 'Quantidade inicial informada no lote',
    ativo BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indica se o lote está ativo',
    eliminado_em DATETIME NULL COMMENT 'Data e hora de eliminação lógica',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data e hora da última alteração',
    criado_por_id BIGINT UNSIGNED NULL COMMENT 'Utilizador que criou o registo',
    PRIMARY KEY (id),
    KEY idx_vacina_lote (vacina_id, numero_lote),
    CONSTRAINT fk_stock_lotes_vacina FOREIGN KEY (vacina_id) REFERENCES vacinas (id) ON DELETE RESTRICT,
    CONSTRAINT fk_stock_lotes_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lotes de vacinas em stock';

-- Atendimentos.
CREATE TABLE atendimentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador do atendimento',
    crianca_id BIGINT UNSIGNED NOT NULL COMMENT 'Criança atendida',
    estado ENUM('aguardando', 'em_atendimento', 'concluido', 'cancelado', 'sem_vacina') NOT NULL COMMENT 'Estado atual do atendimento',
    recebido_por_id BIGINT UNSIGNED NULL COMMENT 'Utilizador da recepção',
    atendido_por_id BIGINT UNSIGNED NULL COMMENT 'Profissional que iniciou o atendimento',
    iniciado_em DATETIME NULL COMMENT 'Data e hora de início',
    concluido_em DATETIME NULL COMMENT 'Data e hora de conclusão',
    motivo TEXT NULL COMMENT 'Motivo obrigatório em cancelados e sem vacina',
    observacoes TEXT NULL COMMENT 'Observações do atendimento',
    peso_kg DECIMAL(5,2) NULL COMMENT 'Peso opcional em quilogramas',
    altura_cm DECIMAL(4,1) NULL COMMENT 'Altura opcional em centímetros',
    perimetro_cefalico_cm DECIMAL(4,1) NULL COMMENT 'Perímetro cefálico opcional em centímetros',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data e hora da última alteração',
    PRIMARY KEY (id),
    KEY idx_estado_criado (estado, criado_em),
    CONSTRAINT ck_atendimentos_estado CHECK (estado IN ('aguardando', 'em_atendimento', 'concluido', 'cancelado', 'sem_vacina')),
    CONSTRAINT ck_atendimentos_motivo CHECK (estado NOT IN ('cancelado', 'sem_vacina') OR (motivo IS NOT NULL AND CHAR_LENGTH(TRIM(motivo)) > 0)),
    CONSTRAINT fk_atendimentos_crianca FOREIGN KEY (crianca_id) REFERENCES criancas (id) ON DELETE RESTRICT,
    CONSTRAINT fk_atendimentos_recebido_por FOREIGN KEY (recebido_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT,
    CONSTRAINT fk_atendimentos_atendido_por FOREIGN KEY (atendido_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Atendimentos e fila do posto';

-- Vacinas pretendidas nos atendimentos (apenas acrescentar).
CREATE TABLE atendimento_vacinas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador da associação',
    atendimento_id BIGINT UNSIGNED NOT NULL COMMENT 'Atendimento associado',
    vacina_id BIGINT UNSIGNED NOT NULL COMMENT 'Vacina pretendida',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    PRIMARY KEY (id),
    UNIQUE KEY uk_atendimento_vacina (atendimento_id, vacina_id),
    CONSTRAINT fk_atendimento_vacinas_atendimento FOREIGN KEY (atendimento_id) REFERENCES atendimentos (id) ON DELETE RESTRICT,
    CONSTRAINT fk_atendimento_vacinas_vacina FOREIGN KEY (vacina_id) REFERENCES vacinas (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vacinas pretendidas em cada atendimento';

-- Vacinações realizadas (apenas acrescentar; erros são corrigidos por anulação).
CREATE TABLE vacinacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador da vacinação',
    crianca_id BIGINT UNSIGNED NOT NULL COMMENT 'Criança vacinada',
    atendimento_id BIGINT UNSIGNED NULL COMMENT 'Atendimento associado; NULL para origem externa',
    vacina_id BIGINT UNSIGNED NOT NULL COMMENT 'Vacina administrada',
    calendario_id BIGINT UNSIGNED NULL COMMENT 'Linha do calendário associada',
    numero_dose TINYINT UNSIGNED NOT NULL COMMENT 'Número da dose administrada',
    data_administracao DATE NOT NULL COMMENT 'Data de administração',
    lote_id BIGINT UNSIGNED NULL COMMENT 'Lote usado; NULL para origem externa',
    administrado_por_id BIGINT UNSIGNED NULL COMMENT 'Utilizador que administrou a dose',
    administrado_por_nome VARCHAR(100) NULL COMMENT 'Nome informado para doses de origem externa',
    local_anatomico VARCHAR(100) NULL COMMENT 'Local anatómico da administração',
    origem ENUM('posto', 'externa') NOT NULL COMMENT 'Origem do registo da vacinação',
    observacoes TEXT NULL COMMENT 'Observações da vacinação',
    reacoes TEXT NULL COMMENT 'Reações registadas',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    PRIMARY KEY (id),
    KEY idx_crianca_vacina_dose (crianca_id, vacina_id, numero_dose),
    KEY idx_data_administracao (data_administracao),
    CONSTRAINT ck_vacinacoes_origem CHECK (origem IN ('posto', 'externa')),
    CONSTRAINT fk_vacinacoes_crianca FOREIGN KEY (crianca_id) REFERENCES criancas (id) ON DELETE RESTRICT,
    CONSTRAINT fk_vacinacoes_atendimento FOREIGN KEY (atendimento_id) REFERENCES atendimentos (id) ON DELETE RESTRICT,
    CONSTRAINT fk_vacinacoes_vacina FOREIGN KEY (vacina_id) REFERENCES vacinas (id) ON DELETE RESTRICT,
    CONSTRAINT fk_vacinacoes_calendario FOREIGN KEY (calendario_id) REFERENCES calendario_vacinal (id) ON DELETE RESTRICT,
    CONSTRAINT fk_vacinacoes_lote FOREIGN KEY (lote_id) REFERENCES stock_lotes (id) ON DELETE RESTRICT,
    CONSTRAINT fk_vacinacoes_administrado_por FOREIGN KEY (administrado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de vacinações realizadas';

-- Anulações de vacinação (apenas acrescentar).
CREATE TABLE vacinacao_anulacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador da anulação',
    vacinacao_id BIGINT UNSIGNED NOT NULL COMMENT 'Vacinação anulada',
    motivo TEXT NOT NULL COMMENT 'Motivo obrigatório da anulação',
    utilizador_id BIGINT UNSIGNED NOT NULL COMMENT 'Utilizador que anulou a vacinação',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora da anulação',
    PRIMARY KEY (id),
    UNIQUE KEY uk_vacinacao (vacinacao_id),
    CONSTRAINT ck_vacinacao_anulacoes_motivo CHECK (CHAR_LENGTH(TRIM(motivo)) > 0),
    CONSTRAINT fk_vacinacao_anulacoes_vacinacao FOREIGN KEY (vacinacao_id) REFERENCES vacinacoes (id) ON DELETE RESTRICT,
    CONSTRAINT fk_vacinacao_anulacoes_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizadores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anulações de vacinações com motivo';

-- Movimentos de stock (apenas acrescentar).
CREATE TABLE stock_movimentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador do movimento',
    lote_id BIGINT UNSIGNED NOT NULL COMMENT 'Lote movimentado',
    tipo ENUM('entrada', 'saida_vacinacao', 'perda', 'ajuste_mais', 'ajuste_menos') NOT NULL COMMENT 'Tipo de movimento',
    quantidade INT UNSIGNED NOT NULL COMMENT 'Quantidade de doses movimentadas',
    motivo TEXT NULL COMMENT 'Motivo obrigatório em perdas e ajustes',
    vacinacao_id BIGINT UNSIGNED NULL COMMENT 'Vacinação que originou a saída',
    data_movimento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora do movimento',
    utilizador_id BIGINT UNSIGNED NULL COMMENT 'Utilizador responsável pelo movimento',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    PRIMARY KEY (id),
    KEY idx_lote_data (lote_id, data_movimento),
    CONSTRAINT ck_stock_movimentos_tipo CHECK (tipo IN ('entrada', 'saida_vacinacao', 'perda', 'ajuste_mais', 'ajuste_menos')),
    CONSTRAINT ck_stock_movimentos_quantidade CHECK (quantidade > 0),
    CONSTRAINT ck_stock_movimentos_motivo CHECK (tipo NOT IN ('perda', 'ajuste_mais', 'ajuste_menos') OR (motivo IS NOT NULL AND CHAR_LENGTH(TRIM(motivo)) > 0)),
    CONSTRAINT fk_stock_movimentos_lote FOREIGN KEY (lote_id) REFERENCES stock_lotes (id) ON DELETE RESTRICT,
    CONSTRAINT fk_stock_movimentos_vacinacao FOREIGN KEY (vacinacao_id) REFERENCES vacinacoes (id) ON DELETE RESTRICT,
    CONSTRAINT fk_stock_movimentos_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizadores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Movimentos append-only de stock';

-- Auditoria (apenas acrescentar).
CREATE TABLE auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador do registo de auditoria',
    entidade VARCHAR(50) NOT NULL COMMENT 'Entidade auditada',
    entidade_id BIGINT UNSIGNED NOT NULL COMMENT 'Identificador do registo auditado',
    acao VARCHAR(20) NOT NULL COMMENT 'Ação realizada',
    campo VARCHAR(50) NULL COMMENT 'Campo alterado',
    valor_anterior TEXT NULL COMMENT 'Valor anterior sem dados sensíveis',
    valor_novo TEXT NULL COMMENT 'Valor novo sem dados sensíveis',
    utilizador_id BIGINT UNSIGNED NULL COMMENT 'Utilizador responsável pela ação',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora da ação',
    PRIMARY KEY (id),
    KEY idx_entidade_acao (entidade, acao),
    KEY idx_criado_em (criado_em),
    CONSTRAINT fk_auditoria_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizadores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registo append-only de auditoria';

-- Contactos de crianças faltosas.
CREATE TABLE contactos_faltosos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Identificador do contacto',
    crianca_id BIGINT UNSIGNED NOT NULL COMMENT 'Criança contactada',
    data_contacto DATE NOT NULL COMMENT 'Data do contacto',
    canal ENUM('telefone', 'sms', 'whatsapp') NOT NULL COMMENT 'Canal usado no contacto',
    resultado VARCHAR(100) NOT NULL COMMENT 'Resultado do contacto',
    notas TEXT NULL COMMENT 'Notas do contacto',
    utilizador_id BIGINT UNSIGNED NULL COMMENT 'Utilizador que registou o contacto',
    eliminado_em DATETIME NULL COMMENT 'Data e hora de eliminação lógica',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora de criação',
    PRIMARY KEY (id),
    KEY idx_crianca_data (crianca_id, data_contacto),
    CONSTRAINT ck_contactos_faltosos_canal CHECK (canal IN ('telefone', 'sms', 'whatsapp')),
    CONSTRAINT fk_contactos_faltosos_crianca FOREIGN KEY (crianca_id) REFERENCES criancas (id) ON DELETE RESTRICT,
    CONSTRAINT fk_contactos_faltosos_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizadores (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contactos registados a crianças faltosas';

-- Vista: saldo atual de cada lote ativo e não eliminado.
CREATE VIEW stock_saldo_lotes AS
SELECT
    sl.id,
    sl.vacina_id,
    sl.numero_lote,
    sl.validade,
    sl.fabricante,
    COALESCE(SUM(
        CASE sm.tipo
            WHEN 'entrada' THEN sm.quantidade
            WHEN 'saida_vacinacao' THEN -sm.quantidade
            WHEN 'perda' THEN -sm.quantidade
            WHEN 'ajuste_mais' THEN sm.quantidade
            WHEN 'ajuste_menos' THEN -sm.quantidade
            ELSE 0
        END
    ), 0) AS saldo_atual
FROM stock_lotes sl
LEFT JOIN stock_movimentos sm ON sl.id = sm.lote_id
WHERE sl.ativo = TRUE AND sl.eliminado_em IS NULL
GROUP BY sl.id, sl.vacina_id, sl.numero_lote, sl.validade, sl.fabricante;

-- Vista: saldo disponível por vacina, considerando apenas lotes válidos.
CREATE VIEW vacinas_disponiveis AS
SELECT
    v.id,
    v.nome,
    COALESCE(SUM(
        CASE WHEN sl.validade >= CURDATE() THEN saldo.saldo_atual ELSE 0 END
    ), 0) AS saldo_disponivel
FROM vacinas v
LEFT JOIN stock_lotes sl ON v.id = sl.vacina_id AND sl.ativo = TRUE AND sl.eliminado_em IS NULL
LEFT JOIN stock_saldo_lotes saldo ON sl.id = saldo.id
WHERE v.ativo = TRUE AND v.eliminado_em IS NULL
GROUP BY v.id, v.nome;
