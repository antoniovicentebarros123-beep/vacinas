// Ponto de entrada do Worker. A interface (public/) é servida pelos assets da Cloudflare;
// o Worker só corre para /api/* (ver assets.run_worker_first no wrangler.jsonc).
import { Router } from './router.js';
import { json } from './resposta.js';
import { verificarCsrf } from './seguranca.js';

export function criarRouter() {
  const router = new Router();
  router.usar(verificarCsrf);

  // Verificação simples de que o Worker e a D1 estão vivos. Não devolve dados.
  router.get('/api/saude', async (_pedido, ctx) => {
    let bd = 'indisponivel';
    try {
      await ctx.env.DB.prepare('SELECT 1').first();
      bd = 'ok';
    } catch { /* a mensagem de erro da D1 não vai para o cliente */ }
    return json({ estado: 'ok', bd });
  });

  // Fase 1b: /api/auth/login, /api/auth/logout, /api/auth/sessao, /api/arranque
  // Fase 5:  /api/sync/enviar, /api/sync/receber
  return router;
}

const router = criarRouter();

export default {
  async fetch(pedido, env, execCtx) {
    try {
      return await router.tratar(pedido, env, execCtx);
    } catch {
      // Nunca devolver detalhes internos nem dados do pedido.
      return json({ erro: 'Ocorreu um erro no servidor. Tente de novo.' }, 500);
    }
  },
};
