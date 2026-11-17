// Indicador visível no topo: "Sincronizado (última: hh:mm)", "X registos por enviar" ou "Offline".
// Até à Fase 5 não há sincronização: sem última sincronização mostra "Aguarda a primeira sincronização".
import { h, icone } from './dom.js';
import { transacao, pedido } from '../db/indexeddb.js';

export async function contarPendentes(bd) {
  const todas = await transacao(bd, ['sync_outbox'], 'readonly', (tx) => pedido(tx.objectStore('sync_outbox').getAll()));
  return todas.length;   // pendentes + com erro: nada sai da fila sem confirmação da nuvem
}

async function ultimaSincronizacao(bd) {
  const r = await transacao(bd, ['configuracoes'], 'readonly', (tx) => pedido(tx.objectStore('configuracoes').get('ultima_sincronizacao')));
  return r?.valor ?? null;
}

/** Devolve o texto, o estado visual e o ícone. Função pura: fácil de testar. */
export function descreverEstado({ online, pendentes, ultima }) {
  if (!online) return { estado: 'offline', icone: 'wifi-off', texto: pendentes ? `Offline · ${pendentes} por enviar` : 'Offline' };
  if (pendentes > 0) return { estado: 'pendente', icone: 'refresh-cw', texto: pendentes === 1 ? '1 registo por enviar' : `${pendentes} registos por enviar` };
  if (!ultima) return { estado: 'espera', icone: 'clock', texto: 'Aguarda a primeira sincronização' };
  const hora = new Date(ultima).toLocaleTimeString('pt-AO', { hour: '2-digit', minute: '2-digit' });
  return { estado: 'ok', icone: 'cloud-check', texto: `Sincronizado (última: ${hora})` };
}

export function criarIndicador(bd) {
  const el = h('div', { classe: 'indicador-sync', role: 'status', 'aria-live': 'polite' });
  async function atualizar() {
    const d = descreverEstado({ online: navigator.onLine, pendentes: await contarPendentes(bd), ultima: await ultimaSincronizacao(bd) });
    el.dataset.estado = d.estado;
    el.replaceChildren(icone(d.icone), d.texto);
    el.title = d.estado === 'pendente' || d.estado === 'offline'
      ? 'Os registos ficam guardados neste computador. Não limpe os dados do navegador até estarem enviados.' : '';
  }
  addEventListener('online', atualizar);
  addEventListener('offline', atualizar);
  addEventListener('fila-alterada', atualizar);
  atualizar();
  return el;
}
