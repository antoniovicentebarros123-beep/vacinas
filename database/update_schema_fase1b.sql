-- SQL para atualizar schema para Fase 1b (login real)
-- Execute este script se já tiver um banco de dados em uso

-- Adicionar campos de segurança à tabela utilizadores
ALTER TABLE utilizadores 
ADD COLUMN IF NOT EXISTS tentativas_falhadas TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Número de tentativas falhadas consecutivas',
ADD COLUMN IF NOT EXISTS bloqueado_ate DATETIME NULL COMMENT 'Data/hora até qual o utilizador está bloqueado';

-- Criar tabela de tentativas de login (se não existir)
CREATE TABLE IF NOT EXISTS login_tentativas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    utilizador VARCHAR(50) NOT NULL,
    sucesso BOOLEAN NOT NULL,
    ip VARCHAR(45) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_utilizador (utilizador, criado_em),
    KEY idx_ip (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registo de tentativas de login para segurança';

-- Atualizar campo perfil existente (se necessário)
ALTER TABLE utilizadores 
MODIFY COLUMN perfil ENUM('administrador', 'enfermeiro', 'recepcao', 'responsavel_stock') NOT NULL;