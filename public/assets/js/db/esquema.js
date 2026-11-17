// Esquema do IndexedDB. A versão sobe quando se acrescenta uma migração em MIGRACOES.
// Nunca editar uma migração já distribuída: criar uma nova.

export const NOME_BD = 'vacinacao';

/** Stores cujos registos se sincronizam com a nuvem (cada gravação entra no sync_outbox). */
export const STORES_SINCRONIZAVEIS = [
  'postos', 'dispositivos', 'utilizadores_reduzidos',
  'encarregados', 'criancas', 'fusoes_criancas', 'duplicados_descartados',
  'consultas', 'vacinacoes', 'vacinacao_anulacoes',
  'vacinas', 'calendario_vacinal',
  'stock_lotes', 'stock_movimentos', 'contactos_faltosos', 'auditoria',
];

/** Stores exclusivamente locais. */
export const STORES_LOCAIS = [
  'sync_outbox', 'sync_cursor', 'sync_log', 'sessao_local', 'verificador_offline', 'configuracoes',
];

/** Cada entrada recebe (bd, transacao) e cria o que falta. Índice 0 = versão 1. */
export const MIGRACOES = [
  function v1(bd) {
    const criar = (nome, indices = [], opcoes = { keyPath: 'id' }) => {
      const s = bd.createObjectStore(nome, opcoes);
      for (const [ix, caminho, opts] of indices) s.createIndex(ix, caminho, opts ?? {});
      return s;
    };

    // --- Sincronizáveis ---
    criar('postos',  [['by_codigo', 'codigo', { unique: true }]]);
    criar('dispositivos', [['by_posto', 'posto_id']]);
    criar('utilizadores_reduzidos', [['by_posto', 'posto_id']]);
    criar('encarregados', [['by_nome_busca', 'nome_busca'], ['by_telefone', 'telefone_busca']]);
    criar('criancas', [
      ['by_nome_busca', 'nome_busca'],
      ['by_processo', 'numero_processo', { unique: true }],
      ['by_nasc_nome', ['data_nascimento', 'nome_busca']],
      ['by_encarregado', 'encarregado_id'],
    ]);
    criar('fusoes_criancas', [['by_destino', 'destino_id'], ['by_origem', 'origem_id']]);
    criar('duplicados_descartados', [['by_a', 'crianca_a_id'], ['by_b', 'crianca_b_id']]);
    criar('consultas', [['by_crianca_data', ['crianca_id', 'data_consulta']]]);
    criar('vacinacoes', [
      ['by_crianca', 'crianca_id'],
      ['by_consulta', 'consulta_id'],
      ['by_data', 'data_administracao'],
      ['by_dose', ['crianca_id', 'vacina_id', 'numero_dose']],   // alerta de dose repetida
    ]);
    criar('vacinacao_anulacoes', [['by_vacinacao', 'vacinacao_id', { unique: true }]]);
    criar('vacinas', [['by_codigo', 'codigo', { unique: true }]]);
    criar('calendario_vacinal', [['by_vacina_dose', ['vacina_id', 'numero_dose']]]);
    // Sem índice único em lotes: o mesmo lote pode ser criado em dois PCs offline.
    criar('stock_lotes', [['by_vacina_lote', ['vacina_id', 'numero_lote']]]);
    criar('stock_movimentos', [['by_lote', 'lote_id']]);
    criar('contactos_faltosos', [['by_crianca', 'crianca_id']]);
    criar('auditoria', [['by_entidade', ['entidade', 'entidade_id']]]);

    // --- Só locais ---
    criar('sync_outbox', [['by_estado', 'estado'], ['by_op', 'op_id', { unique: true }]],
      { keyPath: 'id', autoIncrement: true });
    criar('sync_cursor', [], { keyPath: 'chave' });
    criar('sync_log', [], { keyPath: 'id', autoIncrement: true });
    criar('sessao_local', [], { keyPath: 'chave' });
    criar('verificador_offline', [], { keyPath: 'utilizador' });
    criar('configuracoes', [], { keyPath: 'chave' });
  },
];

export const VERSAO_ESQUEMA = MIGRACOES.length;
