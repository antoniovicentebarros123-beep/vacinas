-- Configurações de alerta da Fase E. Não substitui valores já definidos.
INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES
('stock_validade_aviso_dias', '30', 'Dias restantes para alertar de validade próxima; predefinição: 30'),
('stock_minimo_doses', '5', 'Saldo igual ou inferior alerta de stock baixo; predefinição: 5 doses');
