// Repositório base: TODOS os repositórios locais herdam desta classe.
// Em cada gravação, e na MESMA transação, faz:
//   1) incrementa a versao;  2) preenche dispositivo_id e posto_id;
//   3) atualiza atualizado_em (hora técnica, só informativa);
//   4) insere a operação em sync_outbox;  5) (opcional) regista a auditoria campo a campo.
// Se qualquer passo falhar, nada é gravado.
import { ulid, agoraUtc } from '../../../shared/ulid.js';
import { transacao, pedido } from './indexeddb.js';

const CAMPOS_TECNICOS = new Set(['id', 'posto_id', 'criado_em', 'atualizado_em', 'dispositivo_id', 'versao', 'eliminado_em']);

const igual = (a, b) => JSON.stringify(a ?? null) === JSON.stringify(b ?? null);

/**
 * Corre várias gravações de repositórios diferentes numa só transação
 * (ex.: criar a vacinação e o movimento de stock). Passa as stores que vão ser tocadas;
 * sync_outbox é acrescentada automaticamente.
 */
export async function executar(bd, stores, fn) {
  const todas = [...new Set([...stores, 'sync_outbox'])];
  const resultado = await transacao(bd, todas, 'readwrite', fn);
  // Avisa a interface (indicador de sincronização). Em Node não existe dispatchEvent: ignora.
  try { globalThis.dispatchEvent?.(new Event('fila-alterada')); } catch { /* sem interface */ }
  return resultado;
}

export class RepositorioBase {
  /**
   * @param {IDBDatabase} bd
   * @param {object} opcoes
   * @param {string} opcoes.store            nome da store (e da entidade na sincronização)
   * @param {() => {postoId:string|null, dispositivoId:string, utilizadorId?:string}} opcoes.contexto
   * @param {boolean} [opcoes.somenteAcrescentar]  registos que nunca se editam nem se eliminam
   * @param {boolean} [opcoes.auditar]       regista a auditoria campo a campo (criancas, encarregados)
   * @param {boolean} [opcoes.global]        catálogo: posto_id fica null
   */
  constructor(bd, { store, contexto, somenteAcrescentar = false, auditar = false, global = false }) {
    this.bd = bd;
    this.store = store;
    this.entidade = store;
    this.contexto = contexto;
    this.somenteAcrescentar = somenteAcrescentar;
    this.auditar = auditar;
    this.global = global;
  }

  // ---------------------------------------------------------------- leitura
  async obter(id) {
    const r = await transacao(this.bd, [this.store], 'readonly', (tx) => pedido(tx.objectStore(this.store).get(id)));
    return r && !r.eliminado_em ? r : null;
  }

  async listar() {
    const todos = await transacao(this.bd, [this.store], 'readonly', (tx) => pedido(tx.objectStore(this.store).getAll()));
    return todos.filter((r) => !r.eliminado_em);
  }

  // -------------------------------------------------- gravação (transação própria)
  criar(dados, opcoes) {
    return executar(this.bd, this._storesUsadas(), (tx) => this.criarEm(tx, dados, opcoes));
  }
  atualizar(id, alteracoes, opcoes) {
    return executar(this.bd, this._storesUsadas(), (tx) => this.atualizarEm(tx, id, alteracoes, opcoes));
  }
  eliminar(id, opcoes) {
    return executar(this.bd, this._storesUsadas(), (tx) => this.eliminarEm(tx, id, opcoes));
  }

  _storesUsadas() {
    return this.auditar ? [this.store, 'auditoria'] : [this.store];
  }

  // ---------------------------------- gravação dentro de uma transação existente
  async criarEm(tx, dados, { grupoId = null } = {}) {
    const ctx = this._ctx();
    const agora = agoraUtc();
    const registo = {
      ...dados,
      id: dados.id ?? ulid(),
      posto_id: this.global ? null : ctx.postoId,
      criado_em: agora,
      atualizado_em: agora,
      dispositivo_id: ctx.dispositivoId,
      versao: 1,
      eliminado_em: null,
    };
    await pedido(tx.objectStore(this.store).add(registo));
    await this._enfileirar(tx, registo.id, 'inserir', registo, 1, grupoId, agora);
    if (this.auditar) await this._auditar(tx, registo.id, 'criar', null, null, null, grupoId, agora);
    return registo;
  }

  async atualizarEm(tx, id, alteracoes, { grupoId = null } = {}) {
    if (this.somenteAcrescentar) {
      throw new Error('Este registo não pode ser editado. Se houver um erro, faça uma anulação com motivo.');
    }
    const ctx = this._ctx();
    const st = tx.objectStore(this.store);
    const atual = await pedido(st.get(id));
    if (!atual || atual.eliminado_em) throw new Error('O registo já não existe.');

    // Só contam os campos de negócio que realmente mudaram.
    const diff = {};
    for (const [campo, valor] of Object.entries(alteracoes)) {
      if (CAMPOS_TECNICOS.has(campo)) continue;
      if (!igual(atual[campo], valor)) diff[campo] = valor;
    }
    if (Object.keys(diff).length === 0) return atual;

    const agora = agoraUtc();
    const novo = { ...atual, ...diff, atualizado_em: agora, dispositivo_id: ctx.dispositivoId, versao: atual.versao + 1 };
    await pedido(st.put(novo));
    // O payload leva SÓ os campos alterados; a nuvem aplica campo a campo.
    await this._enfileirar(tx, id, 'atualizar', diff, novo.versao, grupoId, agora);
    if (this.auditar) {
      for (const campo of Object.keys(diff)) {
        await this._auditar(tx, id, 'atualizar', campo, atual[campo], diff[campo], grupoId, agora);
      }
    }
    return novo;
  }

  async eliminarEm(tx, id, { grupoId = null } = {}) {
    if (this.somenteAcrescentar) throw new Error('Este registo não pode ser eliminado.');
    const ctx = this._ctx();
    const st = tx.objectStore(this.store);
    const atual = await pedido(st.get(id));
    if (!atual || atual.eliminado_em) throw new Error('O registo já não existe.');
    const agora = agoraUtc();
    const novo = { ...atual, eliminado_em: agora, atualizado_em: agora, dispositivo_id: ctx.dispositivoId, versao: atual.versao + 1 };
    await pedido(st.put(novo));          // eliminação lógica: nunca DELETE físico
    await this._enfileirar(tx, id, 'eliminar', { eliminado_em: agora }, novo.versao, grupoId, agora);
    if (this.auditar) await this._auditar(tx, id, 'eliminar', null, null, null, grupoId, agora);
    return novo;
  }

  // ------------------------------------------------------------- internos
  _ctx() {
    const ctx = this.contexto();
    if (!ctx?.dispositivoId) throw new Error('Este computador ainda não está registado. Faça o primeiro início de sessão com internet.');
    if (!this.global && !ctx.postoId) throw new Error('Sessão sem posto. Inicie sessão novamente.');
    return ctx;
  }

  _enfileirar(tx, entidadeId, operacao, payload, versao, grupoId, agora, entidade = this.entidade) {
    return pedido(tx.objectStore('sync_outbox').add({
      op_id: ulid(),               // identifica a operação: reenviar a mesma não duplica
      grupo_id: grupoId,           // operações que a nuvem aplica em conjunto (ex.: fusão)
      entidade,
      entidade_id: entidadeId,
      operacao,
      payload,
      versao,
      criado_em: agora,
      tentativas: 0,
      proxima_tentativa: null,
      ultimo_erro: null,
      estado: 'pendente',          // pendente | erro  (nunca se apaga antes de a nuvem confirmar)
    }));
  }

  async _auditar(tx, entidadeId, acao, campo, anterior, novo, grupoId, agora) {
    const ctx = this._ctx();
    const linha = {
      id: ulid(),
      posto_id: ctx.postoId,
      entidade: this.entidade,
      entidade_id: entidadeId,
      acao,
      campo,
      valor_anterior: anterior === null || anterior === undefined ? null : JSON.stringify(anterior),
      valor_novo: novo === null || novo === undefined ? null : JSON.stringify(novo),
      utilizador_id: ctx.utilizadorId ?? null,
      criado_em: agora,
      atualizado_em: agora,
      dispositivo_id: ctx.dispositivoId,
      versao: 1,
      eliminado_em: null,
    };
    await pedido(tx.objectStore('auditoria').add(linha));
    await this._enfileirar(tx, linha.id, 'inserir', linha, 1, grupoId, agora, 'auditoria');
  }
}
