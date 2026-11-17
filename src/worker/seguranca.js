// Proteção CSRF: cabeçalho personalizado obrigatório + verificação de origem.
// Um formulário ou página de outro site não consegue enviar este cabeçalho sem
// pré-verificação CORS, e o Worker não responde a CORS de outras origens.
import { erro } from './resposta.js';

export const CABECALHO_CSRF = 'X-Requested-With';
export const VALOR_CSRF = 'vacinacao';
const METODOS_QUE_ALTERAM = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

/** Middleware: devolve uma Response de erro se o pedido não passar, ou undefined. */
export function verificarCsrf(pedido, ctx) {
  if (!METODOS_QUE_ALTERAM.has(pedido.method)) return undefined;
  if (pedido.headers.get(CABECALHO_CSRF) !== VALOR_CSRF) {
    return erro('Pedido recusado por segurança. Recarregue a página e tente de novo.', 403);
  }
  const origem = pedido.headers.get('Origin');
  if (!origem || origem !== ctx.url.origin) {
    return erro('Pedido recusado por segurança. Recarregue a página e tente de novo.', 403);
  }
  return undefined;
}
