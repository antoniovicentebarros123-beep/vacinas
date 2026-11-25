-- Configuração idempotente dos limites das sessões.
SET NAMES utf8mb4;

INSERT INTO configuracoes (chave, valor, descricao) VALUES
    ('session_lifetime', '1800', 'Duração máxima de inatividade da sessão em segundos'),
    ('session_remember_lifetime', '604800', 'Duração da opção Manter sessão em segundos')
ON DUPLICATE KEY UPDATE chave = VALUES(chave);
