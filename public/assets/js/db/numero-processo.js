// Número de processo local, com prefixo do posto e do dispositivo: MF-PC01-2026-000123.
// Nunca colide entre computadores porque cada um tem o seu código (PC01, PC02...).
// O contador NÃO é guardado: recalcula-se a partir dos números já existentes deste
// dispositivo, ao arrancar e depois de cada sincronização. Se o Worker rejeitar um
// número por colisão, a app chama de novo esta função e reenvia o registo (Fase 5).
import { transacao } from './indexeddb.js';

export function prefixoProcesso(codigoPosto, codigoDispositivo, ano) {
  return `${codigoPosto}-${codigoDispositivo}-${ano}-`;
}

/** Devolve o maior sequencial já usado com este prefixo (0 se não houver nenhum). */
export function maiorSequencial(bd, prefixo) {
  return transacao(bd, ['criancas'], 'readonly', (tx) => new Promise((resolve, reject) => {
    const faixa = IDBKeyRange.bound(prefixo, prefixo + '\uffff');
    const req = tx.objectStore('criancas').index('by_processo').openCursor(faixa, 'prev');
    req.onsuccess = () => {
      const c = req.result;
      resolve(c ? parseInt(c.value.numero_processo.slice(prefixo.length), 10) || 0 : 0);
    };
    req.onerror = () => reject(req.error);
  }));
}

/** @param {number} [minimo] sequencial mínimo (usado depois de uma colisão) */
export async function proximoNumeroProcesso(bd, { codigoPosto, codigoDispositivo, ano = new Date().getFullYear(), minimo = 0 }) {
  const prefixo = prefixoProcesso(codigoPosto, codigoDispositivo, ano);
  const seguinte = Math.max(await maiorSequencial(bd, prefixo), minimo) + 1;
  return prefixo + String(seguinte).padStart(6, '0');
}
