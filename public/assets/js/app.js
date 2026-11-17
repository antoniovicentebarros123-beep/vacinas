// Arranque da aplicação: abre a base local, decide entre login e painel, pede armazenamento persistente.
import { abrirBD, transacao, pedido } from './db/indexeddb.js';
import { lerSessao, guardarSessao, terminarSessao } from './sessao.js';
import { desenharLogin } from './ui/login.js';
import { desenharPainel } from './ui/painel.js';
import { h } from './ui/dom.js';

const raiz = document.getElementById('app');

/** Pede que o navegador não apague os dados locais (a fila de envio vive aqui). */
async function pedirArmazenamentoPersistente(bd) {
  try {
    if (!navigator.storage?.persist) return;
    const ja = await navigator.storage.persisted();
    const concedido = ja || await navigator.storage.persist();
    await transacao(bd, ['configuracoes'], 'readwrite', (tx) =>
      pedido(tx.objectStore('configuracoes').put({ chave: 'armazenamento_persistente', valor: concedido })));
  } catch { /* o navegador pode recusar: o aviso da fila continua visível */ }
}

// ---- TEMPORÁRIO (Fase 1a): pré-visualização do painel sem login.
// Só funciona em localhost com ?previa. Será removido quando o login existir (Fase 1b).
async function sessaoDePrevia(bd) {
  const local = ['localhost', '127.0.0.1'].includes(location.hostname);
  if (!local || !new URLSearchParams(location.search).has('previa')) return;
  await guardarSessao(bd, {
    utilizadorId: '0'.repeat(26), nome: 'Maria Previa', perfil: 'enfermeiro',
    postoId: '01FXTRP0000000000000000001', postoNome: 'Centro Médico Hospitalar Maria de Fátima',
    dispositivoId: '0'.repeat(25) + '1', dispositivoCodigo: 'PC01',
  });
}

async function mostrar(bd) {
  const sessao = await lerSessao(bd);
  if (!sessao) {
    desenharLogin(raiz, { aoEntrar: async () => { /* Fase 1b: guarda a sessão e o verificador offline */ await mostrar(bd); } });
    return;
  }
  await desenharPainel(raiz, {
    bd, sessao,
    aoSair: async () => { await terminarSessao(bd); await mostrar(bd); },
  });
  pedirArmazenamentoPersistente(bd);
}

async function iniciar() {
  let bd;
  try {
    bd = await abrirBD();
  } catch (e) {
    raiz.replaceChildren(h('main', { classe: 'conteudo', id: 'conteudo' },
      h('p', { classe: 'mensagem mensagem-erro', role: 'alert' },
        e.message || 'Não foi possível abrir a base de dados deste computador. Verifique se o navegador permite guardar dados e tente de novo.')));
    return;
  }
  await sessaoDePrevia(bd);
  await mostrar(bd);
}

iniciar();
