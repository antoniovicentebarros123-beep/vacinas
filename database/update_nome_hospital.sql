-- Atualização idempotente do nome da instituição.
-- Executar com --default-character-set=utf8mb4 para preservar os acentos.
SET NAMES utf8mb4;

UPDATE configuracoes
SET valor = 'Centro Médico Hospitalar Maria de Fátima'
WHERE chave = 'nome_posto'
  AND valor <> 'Centro Médico Hospitalar Maria de Fátima';
