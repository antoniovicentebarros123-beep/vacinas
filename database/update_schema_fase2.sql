-- Atualização não destrutiva da base existente para a Fase 2.
-- Executar em vacinas_db depois de criar database/backup_antes_fase2.sql.
-- Não contém DROP, CREATE DATABASE, USE, DELIMITER, triggers ou procedimentos.

-- Contactos passam a suportar eliminação lógica.
ALTER TABLE contactos_faltosos
    ADD COLUMN eliminado_em DATETIME NULL COMMENT 'Data e hora de eliminação lógica' AFTER utilizador_id;

-- Os valores clínicos não fornecidos pelo cartão passam a aceitar NULL.
ALTER TABLE calendario_vacinal
    MODIFY COLUMN janela_inicio_meses SMALLINT UNSIGNED NULL COMMENT 'Início da janela em meses, quando indicado',
    MODIFY COLUMN janela_fim_meses SMALLINT UNSIGNED NULL COMMENT 'Fim da janela em meses, quando indicado',
    MODIFY COLUMN idade_minima_meses SMALLINT UNSIGNED NULL COMMENT 'Idade mínima em meses, quando validada';

-- A pesquisa de crianças passa a usar o índice composto exigido.
ALTER TABLE criancas
    DROP INDEX idx_nome,
    DROP INDEX idx_data_nascimento,
    ADD KEY idx_nome_nascimento (nome, data_nascimento);

-- Autoria: as colunas já existiam, mas não tinham chave estrangeira.
ALTER TABLE bairros
    ADD CONSTRAINT fk_bairros_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT;
ALTER TABLE utilizadores
    ADD CONSTRAINT fk_utilizadores_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT;
ALTER TABLE encarregados
    ADD CONSTRAINT fk_encarregados_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT;
ALTER TABLE criancas
    ADD CONSTRAINT fk_criancas_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT;
ALTER TABLE vacinas
    ADD CONSTRAINT fk_vacinas_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT;
ALTER TABLE calendario_vacinal
    ADD CONSTRAINT fk_calendario_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT;
ALTER TABLE stock_lotes
    ADD CONSTRAINT fk_stock_lotes_criado_por FOREIGN KEY (criado_por_id) REFERENCES utilizadores (id) ON DELETE RESTRICT;

-- Restrições de integridade previstas na especificação.
ALTER TABLE utilizadores
    ADD CONSTRAINT ck_utilizadores_perfil CHECK (perfil IN ('administrador', 'enfermeiro', 'recepcao', 'responsavel_stock'));
ALTER TABLE criancas
    ADD CONSTRAINT ck_criancas_sexo CHECK (sexo IN ('M', 'F'));
ALTER TABLE calendario_vacinal
    ADD CONSTRAINT ck_calendario_janela CHECK (
        janela_inicio_meses IS NULL
        OR janela_fim_meses IS NULL
        OR janela_inicio_meses <= janela_fim_meses
    );
ALTER TABLE atendimentos
    ADD CONSTRAINT ck_atendimentos_estado CHECK (estado IN ('aguardando', 'em_atendimento', 'concluido', 'cancelado', 'sem_vacina')),
    ADD CONSTRAINT ck_atendimentos_motivo CHECK (
        estado NOT IN ('cancelado', 'sem_vacina')
        OR (motivo IS NOT NULL AND CHAR_LENGTH(TRIM(motivo)) > 0)
    );
ALTER TABLE vacinacoes
    ADD CONSTRAINT ck_vacinacoes_origem CHECK (origem IN ('posto', 'externa'));
ALTER TABLE vacinacao_anulacoes
    ADD CONSTRAINT ck_vacinacao_anulacoes_motivo CHECK (CHAR_LENGTH(TRIM(motivo)) > 0);
ALTER TABLE stock_movimentos
    ADD CONSTRAINT ck_stock_movimentos_tipo CHECK (tipo IN ('entrada', 'saida_vacinacao', 'perda', 'ajuste_mais', 'ajuste_menos')),
    ADD CONSTRAINT ck_stock_movimentos_quantidade CHECK (quantidade > 0),
    ADD CONSTRAINT ck_stock_movimentos_motivo CHECK (
        tipo NOT IN ('perda', 'ajuste_mais', 'ajuste_menos')
        OR (motivo IS NOT NULL AND CHAR_LENGTH(TRIM(motivo)) > 0)
    );
ALTER TABLE contactos_faltosos
    ADD CONSTRAINT ck_contactos_faltosos_canal CHECK (canal IN ('telefone', 'sms', 'whatsapp'));

-- Comentários das tabelas alinhados com o schema canónico.
ALTER TABLE configuracoes COMMENT = 'Configurações gerais do sistema';
ALTER TABLE bairros COMMENT = 'Bairros disponíveis para os encarregados';
ALTER TABLE utilizadores COMMENT = 'Utilizadores do sistema e respetivos perfis';
ALTER TABLE login_tentativas COMMENT = 'Registo append-only de tentativas de login';
ALTER TABLE encarregados COMMENT = 'Encarregados de educação das crianças';
ALTER TABLE criancas COMMENT = 'Registo de crianças do posto';
ALTER TABLE vacinas COMMENT = 'Catálogo de vacinas e suplementos';
ALTER TABLE calendario_vacinal COMMENT = 'Calendário vacinal transcrito do cartão';
ALTER TABLE stock_lotes COMMENT = 'Lotes de vacinas em stock';
ALTER TABLE atendimentos COMMENT = 'Atendimentos e fila do posto';
ALTER TABLE atendimento_vacinas COMMENT = 'Vacinas pretendidas em cada atendimento';
ALTER TABLE vacinacoes COMMENT = 'Histórico de vacinações realizadas';
ALTER TABLE vacinacao_anulacoes COMMENT = 'Anulações de vacinações com motivo';
ALTER TABLE stock_movimentos COMMENT = 'Movimentos append-only de stock';
ALTER TABLE auditoria COMMENT = 'Registo append-only de auditoria';
ALTER TABLE contactos_faltosos COMMENT = 'Contactos registados a crianças faltosas';
