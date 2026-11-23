-- Seed MySQL/MariaDB para sistema de controlo de vacinação infantil
-- Extraído de database/seeds/0001_catalogo.sql e adaptado para MySQL/MariaDB
-- Garantido: 18 vacinas, 37 doses, 15 oportunidades distintas de idade
-- Comparação com o original: mantêm-se os IDs fixos e as notas "a validar"

-- ---------- Vacinas (18 vacinas) ----------
INSERT INTO vacinas (codigo, nome, tipo, via_administracao, ativo, ordem, criado_em, atualizado_em, criado_por) VALUES
('BCG', 'BCG', 'vacina', NULL, 1, 1, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('POLIO_ORAL', 'Polio oral', 'vacina', NULL, 1, 2, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('HEPB', 'Hepatite B', 'vacina', NULL, 1, 3, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('PENTA', 'Pentavalente', 'vacina', NULL, 1, 4, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('PNEUMO13', 'Pneumo 13', 'vacina', NULL, 1, 5, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('ROTA', 'Rotavírus', 'vacina', NULL, 1, 6, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('MENACWY', 'Meningocócica conjugada quadrivalente (A, C, Y, W-135)', 'vacina', NULL, 1, 7, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('VPI', 'Polio injectável (VPI)', 'vacina', NULL, 1, 8, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('VITA', 'Vitamina A', 'suplemento', NULL, 1, 9, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('SR', 'Sarampo + Rubéola', 'vacina', NULL, 1, 10, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('FA', 'Febre amarela', 'vacina', NULL, 1, 11, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('HEPA', 'Hepatite A', 'vacina', NULL, 1, 12, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('VARICELA', 'Varicela', 'vacina', NULL, 1, 13, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('VASPR', 'VASPR', 'vacina', NULL, 1, 14, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('TETRA', 'Tetravalente', 'vacina', NULL, 1, 15, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('TIFOIDE', 'Febre tifoide', 'vacina', NULL, 1, 16, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('TETANO', 'Tétano', 'vacina', NULL, 1, 17, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
('HPV', 'HPV', 'vacina', NULL, 1, 18, '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Calendário vacinal (37 doses, 15 oportunidades distintas de idade)
-- IDs fixos mantidos, notas "a validar" mantidas

-- Doses ao nascer (3 vacinas) -- Oportunidade 1
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(1, 1, 'Ao nascer', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(2, 0, 'Ao nascer', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Dose ao nascer registada como dose 0: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(3, 1, 'Ao nascer', 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Dose ao nascer registada como dose 1: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Doses dos 2 meses (4 vacinas) -- Oportunidade 2
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(4, 1, '2 meses', 2, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(5, 1, '2 meses', 2, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(2, 1, '2 meses', 2, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(6, 1, '2 meses', 2, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Doses dos 3 meses (1 vacina) -- Oportunidade 3
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(7, 1, '3 meses', 3, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Doses dos 4 meses (4 vacinas) -- Oportunidade 4
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(4, 2, '4 meses', 4, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(5, 2, '4 meses', 4, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(2, 2, '4 meses', 4, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(6, 2, '4 meses', 4, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Doses dos 5 meses (1 vacina) -- Oportunidade 5
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(7, 2, '5 meses', 5, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Doses dos 6 meses (3 vacinas) -- Oportunidade 6
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(4, 3, '6 meses', 6, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(5, 3, '6 meses', 6, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(2, 3, '6 meses', 6, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Doses de Vitamina A dos 6 meses -- Oportunidade 6 (cont.)
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(9, 1, '6 meses', 6, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Doses dos 9 meses (3 vacinas) -- Oportunidade 7
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(10, 1, '9 meses', 9, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(11, 1, '9 meses', 9, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 'Dose única segundo o cartão.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(9, 2, '9 meses', 9, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Doses dos 12 meses (2 vacinas) -- Oportunidade 8
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(12, 1, '12 meses', 12, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(13, 1, '12 meses', 12, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Doses dos 15 meses (2 vacinas) -- Oportunidade 9
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(14, 1, '15 meses', 15, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(7, 3, '15 meses', 15, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'No cartão aparece como "Meningite conjugada quadrivalente (A, C, Y, W-135)": confirmar que é a mesma vacina. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Doses dos 18 meses (5 vacinas, incluindo 2 com grupo_alternativa) -- Oportunidade 10
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(4, 4, '18 meses', 18, NULL, NULL, NULL, NULL, NULL, 'PENTA_OU_TETRA_18M', 0, 1, 1, 'Alternativa a escolher na hora (Pentavalente OU Tetravalente). Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(15, 1, '18 meses', 18, NULL, NULL, NULL, NULL, NULL, 'PENTA_OU_TETRA_18M', 0, 1, 1, 'Alternativa a escolher na hora (Pentavalente OU Tetravalente). Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(5, 4, '18 meses', 18, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(2, 4, '18 meses', 18, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(12, 2, '18 meses', 18, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Doses dos 2 anos (2 vacinas) -- Oportunidade 11
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(16, 1, '2 anos', 24, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Reforço de 3 em 3 anos segundo o cartão: a validar. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(7, 4, '2 anos', 24, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Dose dos 4 anos (1 vacina) -- Oportunidade 12
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(13, 2, '4 anos', 48, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Reforço. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Doses dos 5-6 anos (2 vacinas) -- Oportunidade 13
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(4, 5, '5-6 anos', 60, 60, 72, NULL, NULL, NULL, NULL, 0, 1, 1, 'Fim da janela interpretado como 72 meses: a validar. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed'),
(14, 2, '5-6 anos', 60, 60, 72, NULL, NULL, NULL, NULL, 0, 1, 1, 'Fim da janela interpretado como 72 meses: a validar. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Dose dos 7 anos (1 vacina) -- Oportunidade 14
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(17, 1, '7 anos', 84, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');

-- Dose dos 10-12 anos (1 vacina) -- Oportunidade 15
INSERT INTO calendario_vacinal (vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, criado_por) VALUES
(18, 1, '10-12 anos', 120, 120, 144, NULL, NULL, NULL, NULL, 0, 1, 1, 'Fim da janela interpretado como 144 meses: a validar. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', '2026-11-16 00:00:00', '2026-11-16 00:00:00', 'seed');