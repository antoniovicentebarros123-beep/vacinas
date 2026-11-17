// Pequeno invólucro de IndexedDB com promessas. Sem bibliotecas.
import { MIGRACOES, NOME_BD, VERSAO_ESQUEMA } from './esquema.js';

/** Converte um IDBRequest numa promessa. */
export function pedido(req) {
  return new Promise((resolve, reject) => {
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

/** Abre a base local e corre as migrações em falta (onupgradeneeded). */
export function abrirBD({ nome = NOME_BD, fabrica = globalThis.indexedDB } = {}) {
  return new Promise((resolve, reject) => {
    const req = fabrica.open(nome, VERSAO_ESQUEMA);
    req.onupgradeneeded = (ev) => {
      const bd = req.result;
      for (let v = ev.oldVersion; v < VERSAO_ESQUEMA; v++) MIGRACOES[v](bd, req.transaction);
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
    req.onblocked = () => reject(new Error('Feche os outros separadores do sistema para atualizar a base de dados local.'));
  });
}

/**
 * Corre fn(tx) numa única transação. Tudo o que fn fizer fica ou tudo ou nada.
 * REGRA: dentro de fn só se pode esperar (await) por pedidos de IndexedDB. Esperar por
 * fetch, crypto.subtle ou temporizadores faz a transação fechar-se sozinha.
 */
export function transacao(bd, stores, modo, fn) {
  return new Promise((resolve, reject) => {
    const tx = bd.transaction(stores, modo);
    let resultado, erro;
    tx.oncomplete = () => resolve(resultado);
    tx.onerror = () => { /* o detalhe chega pelo onabort */ };
    tx.onabort = () => reject(erro ?? tx.error ?? new Error('A gravação foi cancelada.'));
    let p;
    try { p = fn(tx); } catch (e) { p = Promise.reject(e); }
    Promise.resolve(p).then(
      (r) => { resultado = r; },
      (e) => { erro = e; try { tx.abort(); } catch { /* já terminada */ } },
    );
  });
}

/** Leitura simples de todos os registos de uma store. */
export async function lerTodos(bd, store) {
  return transacao(bd, [store], 'readonly', (tx) => pedido(tx.objectStore(store).getAll()));
}
