// Respostas JSON com cabeçalhos de segurança. As respostas de erro nunca levam dados de saúde.

/** Cabeçalhos para respostas geradas pelo Worker (a API). Os estáticos usam public/_headers. */
export const CABECALHOS_SEGURANCA = Object.freeze({
  'Content-Security-Policy': "default-src 'none'; frame-ancestors 'none'",
  'X-Content-Type-Options': 'nosniff',
  'Referrer-Policy': 'no-referrer',
  'X-Frame-Options': 'DENY',
  'Cache-Control': 'no-store',
});

export function json(corpo, estado = 200, extra = {}) {
  return new Response(JSON.stringify(corpo), {
    status: estado,
    headers: { 'Content-Type': 'application/json; charset=utf-8', ...CABECALHOS_SEGURANCA, ...extra },
  });
}

export const erro = (mensagem, estado = 400, extra) => json({ erro: mensagem }, estado, extra);
