-- Configuração operacional do aviso de doses a vencer na Fase G.
-- Não é um intervalo clínico e não substitui a validação da equipa clínica.
INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES
('faltosos_aviso_dias', '30', 'Antecedência operacional do aviso “a vencer”; predefinição: 30 dias');
