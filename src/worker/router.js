// Router mínimo, sem frameworks. Suporta métodos, parâmetros (:id) e middleware simples.
import { json } from './resposta.js';

export class Router {
  constructor() {
    this.rotas = [];        // { metodo, regex, chaves, handlers }
    this.antes = [];        // middleware global: async (pedido, ctx) => Response | undefined
  }

  usar(fn) { this.antes.push(fn); return this; }

  adicionar(metodo, caminho, ...handlers) {
    const chaves = [];
    const padrao = caminho.replace(/:([A-Za-z_]+)/g, (_, k) => { chaves.push(k); return '([^/]+)'; });
    this.rotas.push({ metodo, regex: new RegExp(`^${padrao}/?$`), chaves, handlers });
    return this;
  }
  get(c, ...h)    { return this.adicionar('GET', c, ...h); }
  post(c, ...h)   { return this.adicionar('POST', c, ...h); }
  patch(c, ...h)  { return this.adicionar('PATCH', c, ...h); }
  delete(c, ...h) { return this.adicionar('DELETE', c, ...h); }

  /** @returns {Promise<Response>} */
  async tratar(pedido, env, execCtx) {
    const url = new URL(pedido.url);
    const ctx = { env, execCtx, url, params: {}, utilizador: null };

    for (const mw of this.antes) {
      const r = await mw(pedido, ctx);
      if (r) return r;
    }

    let existeOutroMetodo = false;
    for (const rota of this.rotas) {
      const m = rota.regex.exec(url.pathname);
      if (!m) continue;
      if (rota.metodo !== pedido.method) { existeOutroMetodo = true; continue; }
      ctx.params = Object.fromEntries(rota.chaves.map((k, i) => [k, decodeURIComponent(m[i + 1])]));
      // cada handler pode devolver uma Response (termina) ou nada (passa ao seguinte)
      for (const h of rota.handlers) {
        const r = await h(pedido, ctx);
        if (r) return r;
      }
      return json({ erro: 'Pedido sem resposta.' }, 500);
    }
    return existeOutroMetodo
      ? json({ erro: 'Método não permitido.' }, 405)
      : json({ erro: 'Não encontrado.' }, 404);
  }
}
