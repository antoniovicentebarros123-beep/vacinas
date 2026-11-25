-- Seed inicial do sistema de controlo de vacinação infantil.
-- Dados estáticos com IDs fixos: 18 itens de catálogo, 15 oportunidades e 37 doses.
-- Os valores clínicos não indicados no cartão ficam NULL e marcados para validação.
-- Importar apenas depois de database/schema.sql, numa base já criada.

-- Configurações iniciais.
INSERT INTO configuracoes (id, chave, valor, descricao) VALUES
(1, 'nome_posto', 'Centro Médico Hospitalar Maria de Fátima', 'Nome da instituição'),
(2, 'prefixo_processo', 'RG', 'Prefixo provisório do número de processo das crianças'),
(3, 'max_login_attempts', '5', 'Número máximo de tentativas falhadas antes do bloqueio'),
(4, 'login_lockout_time', '900', 'Duração do bloqueio temporário em segundos'),
(5, 'stock_validade_aviso_dias', '30', 'Dias restantes para alertar de validade próxima; predefinição: 30'),
(6, 'session_lifetime', '1800', 'Duração máxima de inatividade da sessão em segundos'),
(7, 'session_remember_lifetime', '604800', 'Duração da opção Manter sessão em segundos'),
(8, 'stock_minimo_doses', '5', 'Saldo igual ou inferior alerta de stock baixo; predefinição: 5 doses'),
(9, 'faltosos_aviso_dias', '30', 'Antecedência operacional do aviso “a vencer”; predefinição: 30 dias');

-- Catálogo inicial transcrito do cartão.
INSERT INTO vacinas (id, nome, descricao, ativo, criado_por_id) VALUES
(1, 'BCG', NULL, TRUE, NULL),
(2, 'Polio oral', NULL, TRUE, NULL),
(3, 'Hepatite B', NULL, TRUE, NULL),
(4, 'Pentavalente', NULL, TRUE, NULL),
(5, 'Pneumo 13', NULL, TRUE, NULL),
(6, 'Rotavírus', NULL, TRUE, NULL),
(7, 'Meningocócica conjugada quadrivalente (A, C, Y, W-135)', NULL, TRUE, NULL),
(8, 'Polio injectável (VPI)', NULL, TRUE, NULL),
(9, 'Vitamina A', NULL, TRUE, NULL),
(10, 'Sarampo + Rubéola', NULL, TRUE, NULL),
(11, 'Febre amarela', NULL, TRUE, NULL),
(12, 'Hepatite A', NULL, TRUE, NULL),
(13, 'Varicela', NULL, TRUE, NULL),
(14, 'VASPR', NULL, TRUE, NULL),
(15, 'Tetravalente', NULL, TRUE, NULL),
(16, 'Febre tifoide', NULL, TRUE, NULL),
(17, 'Tétano', NULL, TRUE, NULL),
(18, 'HPV', NULL, TRUE, NULL);

-- Calendário vacinal: 15 oportunidades e 37 doses.
-- janela_inicio_meses, janela_fim_meses, idade_minima_meses, intervalo_minimo_dias
-- e dose_anterior_exigida só são preenchidos quando o cartão fornece o valor.
INSERT INTO calendario_vacinal (
    id,
    vacina_id,
    numero_dose,
    rotulo_idade,
    idade_ideal_meses,
    janela_inicio_meses,
    janela_fim_meses,
    idade_minima_meses,
    intervalo_minimo_dias,
    dose_anterior_exigida,
    grupo_alternativa,
    dose_unica,
    ativo,
    validar_clinica,
    notas_validacao,
    criado_por_id
) VALUES
-- Ao nascer.
(1, 1, 1, 'Ao nascer', 0, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(2, 2, 0, 'Ao nascer', 0, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Dose ao nascer registada como dose 0: a validar pela equipa clínica.', NULL),
(3, 3, 1, 'Ao nascer', 0, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Dose ao nascer registada como dose 1: a validar pela equipa clínica.', NULL),

-- 2 meses.
(4, 4, 1, '2 meses', 2, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(5, 5, 1, '2 meses', 2, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(6, 2, 1, '2 meses', 2, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(7, 6, 1, '2 meses', 2, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),

-- 3 meses.
(8, 7, 1, '3 meses', 3, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),

-- 4 meses.
(9, 4, 2, '4 meses', 4, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(10, 5, 2, '4 meses', 4, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(11, 2, 2, '4 meses', 4, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(12, 6, 2, '4 meses', 4, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(13, 8, 1, '4 meses', 4, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),

-- 5 meses.
(14, 7, 2, '5 meses', 5, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),

-- 6 meses.
(15, 4, 3, '6 meses', 6, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(16, 5, 3, '6 meses', 6, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(17, 2, 3, '6 meses', 6, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(18, 9, 1, '6 meses', 6, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),

-- 9 meses.
(19, 10, 1, '9 meses', 9, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(20, 11, 1, '9 meses', 9, NULL, NULL, NULL, NULL, NULL, NULL, TRUE, TRUE, TRUE, 'Dose única segundo o cartão.', NULL),
(21, 9, 2, '9 meses', 9, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),

-- 12 meses.
(22, 12, 1, '12 meses', 12, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(23, 13, 1, '12 meses', 12, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),

-- 15 meses.
(24, 14, 1, '15 meses', 15, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(25, 7, 3, '15 meses', 15, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'No cartão aparece como Meningite conjugada quadrivalente (A, C, Y, W-135): confirmar que é a mesma vacina. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),

-- 18 meses: Pentavalente OU Tetravalente, duas linhas com o mesmo grupo.
(26, 4, 4, '18 meses', 18, NULL, NULL, NULL, NULL, NULL, 'PENTA_OU_TETRA_18M', FALSE, TRUE, TRUE, 'Alternativa a escolher na hora: Pentavalente OU Tetravalente. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(27, 15, 1, '18 meses', 18, NULL, NULL, NULL, NULL, NULL, 'PENTA_OU_TETRA_18M', FALSE, TRUE, TRUE, 'Alternativa a escolher na hora: Pentavalente OU Tetravalente. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(28, 5, 4, '18 meses', 18, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(29, 2, 4, '18 meses', 18, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(30, 12, 2, '18 meses', 18, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),

-- 2 anos.
(31, 16, 1, '2 anos', 24, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Reforço de 3 em 3 anos segundo o cartão: a validar. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(32, 7, 4, '2 anos', 24, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),

-- 4 anos.
(33, 13, 2, '4 anos', 48, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Reforço. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),

-- 5-6 anos: o cartão indica o intervalo; 72 meses é interpretação a validar.
(34, 4, 5, '5-6 anos', 60, 60, 72, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Fim da janela interpretado como 72 meses: a validar. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),
(35, 14, 2, '5-6 anos', 60, 60, 72, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Fim da janela interpretado como 72 meses: a validar. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),

-- 7 anos.
(36, 17, 1, '7 anos', 84, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL),

-- 10-12 anos: ideal no início do intervalo; 144 meses = 12 anos.
(37, 18, 1, '10-12 anos', 120, 120, 144, NULL, NULL, NULL, NULL, FALSE, TRUE, TRUE, 'Fim da janela interpretado como 144 meses: a validar. Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.', NULL);
