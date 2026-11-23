-- Schema do sistema de controlo de vacinação infantil
-- Criado em 23/11/2026
-- Base de dados: vacinas_db
-- Motor: InnoDB
-- Codificação: utf8mb4
-- Collation: utf8mb4_unicode_ci

-- Criar base de dados (remover o CREATE DATABASE para importação no phpMyAdmin)
-- CREATE DATABASE IF NOT EXISTS vacinas_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE vacinas_db;

-- Configurações do sistema
CREATE TABLE configuracoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    chave VARCHAR(100) NOT NULL,
    valor TEXT NOT NULL,
    descricao TEXT,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_chave (chave),
    COMMENT 'Configurações do sistema como nome do posto, prefixo de processos, etc.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurações do sistema';

-- Bairros
CREATE TABLE bairros (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    eliminado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_por_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_nome (nome),
    COMMENT 'Bairros do município onde o posto está localizado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bairros';

-- Utilizadores
CREATE TABLE utilizadores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    utilizador VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    perfil ENUM('administrador', 'enfermeiro', 'recepcao', 'responsavel_stock') NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    deve_mudar_password BOOLEAN NOT NULL DEFAULT FALSE,
    ultimo_login DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_por_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_utilizador (utilizador),
    COMMENT 'Utilizadores do sistema com perfis de acesso'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Utilizadores';

-- Tentativas de login
CREATE TABLE login_tentativas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilizador VARCHAR(50) NOT NULL,
    sucesso BOOLEAN NOT NULL,
    ip VARCHAR(45) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_utilizador (utilizador, criado_em),
    KEY idx_ip (ip, criado_em),
    COMMENT 'Registo de tentativas de login para segurança'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tentativas de login';

-- Encarregados de educação
CREATE TABLE encarregados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    parentesco VARCHAR(50) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    telefone_alt VARCHAR(20) NULL,
    morada TEXT NULL,
    bairro_id BIGINT UNSIGNED NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    eliminado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_por_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_nome (nome),
    KEY idx_telefone (telefone),
    FOREIGN KEY (bairro_id) REFERENCES bairros(id) ON DELETE RESTRICT,
    COMMENT 'Encarregados de educação das crianças'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Encarregados de educação';

-- Crianças
CREATE TABLE criancas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero_processo VARCHAR(50) NOT NULL,
    numero_cartao VARCHAR(50) NULL,
    nome VARCHAR(100) NOT NULL,
    data_nascimento DATE NOT NULL,
    data_nascimento_aproximada BOOLEAN NOT NULL DEFAULT FALSE,
    sexo ENUM('M', 'F') NOT NULL,
    encarregado_id BIGINT UNSIGNED NOT NULL,
    observacoes TEXT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    eliminado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_por_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_numero_processo (numero_processo),
    KEY idx_nome (nome),
    KEY idx_data_nascimento (data_nascimento),
    FOREIGN KEY (encarregado_id) REFERENCES encarregados(id) ON DELETE RESTRICT,
    COMMENT 'Registo de crianças no sistema'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Crianças';

-- Vacinas
CREATE TABLE vacinas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    eliminado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_por_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_nome (nome),
    COMMENT 'Catálogo de vacinas disponíveis'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vacinas';

-- Calendário vacinal
CREATE TABLE calendario_vacinal (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vacina_id BIGINT UNSIGNED NOT NULL,
    numero_dose TINYINT UNSIGNED NOT NULL,
    rotulo_idade VARCHAR(50) NOT NULL,
    idade_ideal_meses SMALLINT UNSIGNED NOT NULL,
    janela_inicio_meses SMALLINT UNSIGNED NOT NULL,
    janela_fim_meses SMALLINT UNSIGNED NOT NULL,
    idade_minima_meses SMALLINT UNSIGNED NOT NULL,
    intervalo_minimo_dias SMALLINT UNSIGNED NULL,
    dose_anterior_exigida BIGINT UNSIGNED NULL,
    grupo_alternativa VARCHAR(50) NULL,
    dose_unica BOOLEAN NOT NULL DEFAULT FALSE,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    validar_clinica BOOLEAN NOT NULL DEFAULT TRUE,
    notas_validacao TEXT NULL,
    eliminado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_por_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_vacina_dose (vacina_id, numero_dose),
    FOREIGN KEY (vacina_id) REFERENCES vacinas(id) ON DELETE RESTRICT,
    FOREIGN KEY (dose_anterior_exigida) REFERENCES calendario_vacinal(id) ON DELETE RESTRICT,
    COMMENT 'Calendário vacinal com doses recomendadas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Calendário vacinal';

-- Lotes de stock
CREATE TABLE stock_lotes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vacina_id BIGINT UNSIGNED NOT NULL,
    numero_lote VARCHAR(50) NOT NULL,
    validade DATE NOT NULL,
    fabricante VARCHAR(100) NOT NULL,
    quantidade_inicial INT UNSIGNED NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    eliminado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_por_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_vacina_lote (vacina_id, numero_lote),
    FOREIGN KEY (vacina_id) REFERENCES vacinas(id) ON DELETE RESTRICT,
    COMMENT 'Lotes de vacinas em stock'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lotes de stock';

-- Atendimentos
CREATE TABLE atendimentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    crianca_id BIGINT UNSIGNED NOT NULL,
    estado ENUM('aguardando', 'em_atendimento', 'concluido', 'cancelado', 'sem_vacina') NOT NULL,
    recebido_por_id BIGINT UNSIGNED NULL,
    atendido_por_id BIGINT UNSIGNED NULL,
    iniciado_em DATETIME NULL,
    concluido_em DATETIME NULL,
    motivo TEXT NULL,
    observacoes TEXT NULL,
    peso_kg DECIMAL(5,2) NULL,
    altura_cm DECIMAL(4,1) NULL,
    perimetro_cefalico_cm DECIMAL(4,1) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estado_criado (estado, criado_em),
    FOREIGN KEY (crianca_id) REFERENCES criancas(id) ON DELETE RESTRICT,
    FOREIGN KEY (recebido_por_id) REFERENCES utilizadores(id) ON DELETE RESTRICT,
    FOREIGN KEY (atendido_por_id) REFERENCES utilizadores(id) ON DELETE RESTRICT,
    COMMENT 'Atendimentos de crianças no posto'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Atendimentos';

-- Vacinas pretendidas nos atendimentos
CREATE TABLE atendimento_vacinas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atendimento_id BIGINT UNSIGNED NOT NULL,
    vacina_id BIGINT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_atendimento_vacina (atendimento_id, vacina_id),
    FOREIGN KEY (atendimento_id) REFERENCES atendimentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (vacina_id) REFERENCES vacinas(id) ON DELETE RESTRICT,
    COMMENT 'Vacinas pretendidas nos atendimentos'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vacinas pretendidas nos atendimentos';

-- Vacinações realizadas
CREATE TABLE vacinacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    crianca_id BIGINT UNSIGNED NOT NULL,
    atendimento_id BIGINT UNSIGNED NULL,
    vacina_id BIGINT UNSIGNED NOT NULL,
    calendario_id BIGINT UNSIGNED NULL,
    numero_dose TINYINT UNSIGNED NOT NULL,
    data_administracao DATE NOT NULL,
    lote_id BIGINT UNSIGNED NULL,
    administrado_por_id BIGINT UNSIGNED NULL,
    administrado_por_nome VARCHAR(100) NULL,
    local_anatomico VARCHAR(100) NULL,
    origem ENUM('posto', 'externa') NOT NULL,
    observacoes TEXT NULL,
    reacoes TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crianca_vacina_dose (crianca_id, vacina_id, numero_dose),
    KEY idx_data_administracao (data_administracao),
    FOREIGN KEY (crianca_id) REFERENCES criancas(id) ON DELETE RESTRICT,
    FOREIGN KEY (atendimento_id) REFERENCES atendimentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (vacina_id) REFERENCES vacinas(id) ON DELETE RESTRICT,
    FOREIGN KEY (calendario_id) REFERENCES calendario_vacinal(id) ON DELETE RESTRICT,
    FOREIGN KEY (lote_id) REFERENCES stock_lotes(id) ON DELETE RESTRICT,
    FOREIGN KEY (administrado_por_id) REFERENCES utilizadores(id) ON DELETE RESTRICT,
    COMMENT 'Vacinações realizadas nas crianças'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vacinações realizadas';

-- Anulações de vacinação
CREATE TABLE vacinacao_anulacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vacinacao_id BIGINT UNSIGNED NOT NULL,
    motivo TEXT NOT NULL,
    utilizador_id BIGINT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_vacinacao (vacinacao_id),
    FOREIGN KEY (vacinacao_id) REFERENCES vacinacoes(id) ON DELETE RESTRICT,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE RESTRICT,
    CHECK (CHAR_LENGTH(TRIM(motivo)) > 0),
    COMMENT 'Anulações de vacinações com motivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anulações de vacinação';

-- Movimentos de stock
CREATE TABLE stock_movimentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lote_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('entrada', 'saida_vacinacao', 'perda', 'ajuste_mais', 'ajuste_menos') NOT NULL,
    quantidade INT UNSIGNED NOT NULL,
    motivo TEXT NULL,
    vacinacao_id BIGINT UNSIGNED NULL,
    data_movimento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    utilizador_id BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lote_data (lote_id, data_movimento),
    FOREIGN KEY (lote_id) REFERENCES stock_lotes(id) ON DELETE RESTRICT,
    FOREIGN KEY (vacinacao_id) REFERENCES vacinacoes(id) ON DELETE RESTRICT,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE RESTRICT,
    CHECK (
        (tipo IN ('entrada', 'saida_vacinacao') AND motivo IS NULL) OR
        (tipo IN ('perda', 'ajuste_mais', 'ajuste_menos') AND motivo IS NOT NULL AND CHAR_LENGTH(TRIM(motivo)) > 0)
    ),
    COMMENT 'Movimentos de stock de vacinas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Movimentos de stock';

-- Contactos faltosos
CREATE TABLE contactos_faltosos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    crianca_id BIGINT UNSIGNED NOT NULL,
    data_contacto DATE NOT NULL,
    canal ENUM('telefone', 'sms', 'whatsapp') NOT NULL,
    resultado VARCHAR(100) NOT NULL,
    notas TEXT NULL,
    utilizador_id BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_crianca_data (crianca_id, data_contacto),
    FOREIGN KEY (crianca_id) REFERENCES criancas(id) ON DELETE RESTRICT,
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE RESTRICT,
    COMMENT 'Contactos a crianças que saíram sem vacina'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contactos faltosos';

-- Auditoria
CREATE TABLE auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entidade VARCHAR(50) NOT NULL,
    entidade_id BIGINT UNSIGNED NOT NULL,
    acao VARCHAR(20) NOT NULL,
    campo VARCHAR(50) NULL,
    valor_anterior TEXT NULL,
    valor_novo TEXT NULL,
    utilizador_id BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_entidade_acao (entidade, acao),
    KEY idx_criado_em (criado_em),
    FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE RESTRICT,
    COMMENT 'Registo de auditoria do sistema'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditoria';

-- Vista: Saldo de lotes
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
    ), 0) as saldo_atual
FROM stock_lotes sl
LEFT JOIN stock_movimentos sm ON sl.id = sm.lote_id
WHERE sl.ativo = TRUE AND sl.eliminado_em IS NULL
GROUP BY sl.id, sl.vacina_id, sl.numero_lote, sl.validade, sl.fabricante;

-- Vista: Disponibilidade de vacinas
CREATE VIEW vacinas_disponiveis AS
SELECT 
    v.id,
    v.nome,
    COALESCE(
        SUM(CASE 
            WHEN sl.validade >= CURDATE() THEN ssl.saldo_atual 
            ELSE 0 
        END), 
        0
    ) as saldo_disponivel
FROM vacinas v
LEFT JOIN stock_lotes sl ON v.id = sl.vacina_id AND sl.ativo = TRUE AND sl.eliminado_em IS NULL
LEFT JOIN stock_saldo_lotes ssl ON sl.id = ssl.id
WHERE v.ativo = TRUE AND v.eliminado_em IS NULL
GROUP BY v.id, v.nome;