-- Seed do sistema de controlo de vacinação infantil
-- Dados iniciais estáticos com IDs fixos
-- Criado em 23/11/2026

-- Configurações iniciais
INSERT INTO configuracoes (chave, valor, descricao) VALUES
('nome_posto', 'Posto de Saúde do Bairro do Rangel', 'Nome oficial do posto de saúde'),
('prefixo_processo', 'RG', 'Prefixo para número de processo das crianças'),
('modo_previa', 'true', 'Modo de pré-visualização para desenvolvimento');

-- Vacinas (catálogo fixo)
INSERT INTO vacinas (id, nome, descricao, ativo, criado_por_id) VALUES
(1, 'BCG', 'Vacina BCG para tuberculose', TRUE, NULL),
(2, 'Pentavalente', 'Vacina pentavalente (Difteria, Tétano, Coqueluche, Haemophilus influenzae tipo b, Hepatite B)', TRUE, NULL),
(3, 'Vacina contra o Virus da Poliomielite (VIP)', 'Vacina injetável contra o virus da poliomielite', TRUE, NULL),
(4, 'Vacina contra o Sarampo', 'Vacina contra o sarampo', TRUE, NULL),
(5, 'Vacina contra a Rubéola', 'Vacina contra a rubéola', TRUE, NULL),
(6, 'Vacina contra as Caxumbas', 'Vacina contra as caxumbas', TRUE, NULL),
(7, 'Vacina contra o Haemophilus influenzae tipo b (Hib)', 'Vacina contra o Haemophilus influenzae tipo b', TRUE, NULL),
(8, 'Vacina contra a Hepatite B', 'Vacina contra a hepatite B', TRUE, NULL),
(9, 'Vacina contra o Tétano', 'Vacina contra o tétano', TRUE, NULL),
(10, 'Vacina contra a Difteria', 'Vacina contra a difteria', TRUE, NULL),
(11, 'Vacina contra a Coqueluche', 'Vacina contra a coqueluche', TRUE, NULL),
(12, 'Vacina contra a Febre Amarela', 'Vacina contra a febre amarela', TRUE, NULL),
(13, 'Vacina contra o Rotavírus', 'Vacina contra o rotavírus', TRUE, NULL),
(14, 'Vacina contra o Pneumococo', 'Vacina contra o pneumococo', TRUE, NULL),
(15, 'Vacina contra o Meningococo', 'Vacina contra o meningococo', TRUE, NULL),
(16, 'Vacina contra a Hepatite A', 'Vacina contra a hepatite A', TRUE, NULL),
(17, 'Vacina contra o HPV', 'Vacina contra o HPV', TRUE, NULL),
(18, 'Vacina contra a Varicela', 'Vacina contra a varicela', TRUE, NULL);

-- Calendário vacinal (baseado nas 15 oportunidades com IDs fixos)
INSERT INTO calendario_vacinal (id, vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio_meses, janela_fim_meses, idade_minima_meses, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_por_id) VALUES
-- Nascimento (ao nascer)
(1, 1, 1, 'Nascimento', 0, 0, 0, 0, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'BCG ao nascer', NULL),
(2, 3, 1, 'Nascimento', 0, 0, 0, 0, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'VIP ao nascer', NULL),

-- 6 semanas
(3, 2, 1, '6 semanas', 1.5, 1, 3, 6, 30, NULL, NULL, FALSE, TRUE, TRUE, 'Primeira dose da Pentavalente', NULL),
(4, 7, 1, '6 semanas', 1.5, 1, 3, 6, 30, NULL, NULL, FALSE, TRUE, 'Primeira dose do Hib', NULL),
(5, 8, 1, '6 semanas', 1.5, 1, 3, 6, 30, NULL, NULL, FALSE, TRUE, 'Primeira dose da Hepatite B', NULL),
(6, 10, 1, '6 semanas', 1.5, 1, 3, 6, 30, NULL, NULL, FALSE, TRUE, 'Primeira dose da Difteria', NULL),
(7, 11, 1, '6 semanas', 1.5, 1, 3, 6, 30, NULL, NULL, FALSE, TRUE, 'Primeira dose da Coqueluche', NULL),

-- 10 semanas
(8, 2, 2, '10 semanas', 2.5, 2, 4, 10, 30, 3, NULL, FALSE, TRUE, 'Segunda dose da Pentavalente', NULL),
(9, 7, 2, '10 semanas', 2.5, 2, 4, 10, 30, 4, NULL, FALSE, TRUE, 'Segunda dose do Hib', NULL),
(10, 8, 2, '10 semanas', 2.5, 2, 4, 10, 30, 5, NULL, FALSE, TRUE, 'Segunda dose da Hepatite B', NULL),
(11, 10, 2, '10 semanas', 2.5, 2, 4, 10, 30, 6, NULL, FALSE, TRUE, 'Segunda dose da Difteria', NULL),
(12, 11, 2, '10 semanas', 2.5, 2, 4, 10, 30, 7, NULL, FALSE, TRUE, 'Segunda dose da Coqueluche', NULL),

-- 14 semanas
(13, 2, 3, '14 semanas', 3.5, 3, 5, 14, 30, 8, NULL, FALSE, TRUE, 'Terceira dose da Pentavalente', NULL),
(14, 3, 2, '14 semanas', 3.5, 3, 5, 14, 30, 2, NULL, FALSE, TRUE, 'Segunda dose do VIP', NULL),
(15, 9, 1, '14 semanas', 3.5, 3, 5, 14, 30, NULL, NULL, FALSE, TRUE, 'Primeira dose do Tétano', NULL),

-- 9 meses
(16, 4, 1, '9 meses', 9, 8, 11, 6, 30, NULL, NULL, FALSE, TRUE, 'Primeira dose contra o Sarampo', NULL),
(17, 5, 1, '9 meses', 9, 8, 11, 6, 30, NULL, NULL, FALSE, TRUE, 'Primeira dose contra a Rubéola', NULL),
(18, 6, 1, '9 meses', 9, 8, 11, 6, 30, NULL, NULL, FALSE, TRUE, 'Primeira dose contra as Caxumbas', NULL),
(19, 12, 1, '9 meses', 9, 8, 11, 6, 30, NULL, NULL, FALSE, TRUE, 'Primeira dose da Febre Amarela', NULL),

-- 12 meses
(20, 13, 1, '12 meses', 12, 11, 14, 9, 30, NULL, NULL, FALSE, TRUE, 'Primeira dose do Rotavírus', NULL),
(21, 14, 1, '12 meses', 12, 11, 14, 9, 30, NULL, NULL, FALSE, TRUE, 'Primeira dose do Pneumococo', NULL),

-- 15 meses
(22, 4, 2, '15 meses', 15, 14, 18, 12, 30, 16, NULL, FALSE, TRUE, 'Segunda dose contra o Sarampo', NULL),
(23, 5, 2, '15 meses', 15, 14, 18, 12, 30, 17, NULL, FALSE, TRUE, 'Segunda dose contra a Rubéola', NULL),
(24, 6, 2, '15 meses', 15, 14, 18, 12, 30, 18, NULL, FALSE, TRUE, 'Segunda dose contra as Caxumbas', NULL),
(25, 15, 1, '15 meses', 15, 14, 18, 12, 30, NULL, NULL, FALSE, TRUE, 'Primeira dose do Meningococo', NULL),

-- 18 meses - Pentavalente OU Tetravalente (grupo_alternativa)
(26, 2, 4, '18 meses', 18, 17, 21, 15, 30, 13, 'pentavalente', FALSE, TRUE, 'Reforço da Pentavalente', NULL),
(27, 16, 1, '18 meses', 18, 17, 21, 15, 30, NULL, 'tetravalente', FALSE, TRUE, 'Reforço da Tetravalente', NULL),

-- 24 meses
(28, 9, 2, '24 meses', 24, 22, 26, 18, 180, 26, NULL, FALSE, TRUE, 'Segunda dose do Tétano', NULL),

-- 4 anos
(29, 3, 3, '4 anos', 48, 46, 50, 36, 365, 14, NULL, FALSE, TRUE, 'Terceira dose do VIP', NULL),
(30, 4, 3, '4 anos', 48, 46, 50, 36, 180, 22, NULL, FALSE, TRUE, 'Terceira dose contra o Sarampo', NULL),
(31, 5, 3, '4 anos', 48, 46, 50, 36, 180, 23, NULL, FALSE, TRUE, 'Terceira dose contra a Rubéola', NULL),
(32, 6, 3, '4 anos', 48, 46, 50, 36, 180, 24, NULL, FALSE, TRUE, 'Terceira dose contra as Caxumbas', NULL),

-- 6 anos
(33, 15, 2, '6 anos', 72, 70, 74, 60, 365, 25, NULL, FALSE, TRUE, 'Segunda dose do Meningococo', NULL),
(34, 17, 1, '9 anos feminino', 108, 106, 110, 84, 365, NULL, 'hpv_feminino', TRUE, TRUE, 'Primeira dose do HPV para meninas', NULL),

-- 9 anos (HPV para meninas)
(35, 17, 2, '11 anos feminino', 132, 130, 134, 120, 365, 34, 'hpv_feminino', FALSE, TRUE, 'Segunda dose do HPV para meninas', NULL),

-- 15 anos
(36, 18, 1, '15 anos', 180, 178, 182, 150, 30, NULL, NULL, FALSE, TRUE, 'Vacina contra a Varicela', NULL);