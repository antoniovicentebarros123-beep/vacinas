// Sessão local deste computador (store sessao_local). A sessão verdadeira, com o
// cookie HttpOnly, vive no Worker; aqui só guardamos quem entrou e em que posto/dispositivo,
// para a app abrir offline.
import { transacao, pedido } from './db/indexeddb.js';

const CHAVE = 'atual';

export async function lerSessao(bd) {
  const s = await transacao(bd, ['sessao_local'], 'readonly', (tx) => pedido(tx.objectStore('sessao_local').get(CHAVE)));
  if (!s) return null;
  if (s.expiraOfflineEm && s.expiraOfflineEm < new Date().toISOString()) return null;   // exige novo login online
  return s;
}

export function guardarSessao(bd, sessao) {
  return transacao(bd, ['sessao_local'], 'readwrite', (tx) => pedido(tx.objectStore('sessao_local').put({ chave: CHAVE, ...sessao })));
}

export function terminarSessao(bd) {
  return transacao(bd, ['sessao_local'], 'readwrite', (tx) => pedido(tx.objectStore('sessao_local').delete(CHAVE)));
}

/** Contexto que os repositórios usam em cada gravação. */
export function criarContexto(obterSessao) {
  return () => {
    const s = obterSessao();
    return s ? { postoId: s.postoId ?? null, dispositivoId: s.dispositivoId ?? null, utilizadorId: s.utilizadorId ?? null } : null;
  };
}
