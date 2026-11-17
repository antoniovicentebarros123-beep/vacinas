// Cliente do login online. O contrato com o Worker (Fase 1b):
//   POST /api/auth/login  { utilizador, password, manter }  → sessão + dados do utilizador/posto/dispositivo
// Nesta fase (1a) o endpoint ainda não existe: a função devolve uma mensagem clara.
export const CABECALHOS_API = { 'Content-Type': 'application/json', 'X-Requested-With': 'vacinacao' };

export async function entrar({ utilizador, password, manter }) {
  let resposta;
  try {
    resposta = await fetch('/api/auth/login', {
      method: 'POST', credentials: 'same-origin', headers: CABECALHOS_API,
      body: JSON.stringify({ utilizador, password, manter }),
    });
  } catch {
    throw new Error('Sem ligação à internet. O primeiro início de sessão em cada computador precisa de internet.');
  }
  if (resposta.status === 404) throw new Error('O início de sessão ainda não está disponível neste ambiente (Fase 1b).');
  const corpo = await resposta.json().catch(() => ({}));
  if (!resposta.ok) throw new Error(corpo.erro ?? 'Não foi possível iniciar sessão. Tente de novo.');
  return corpo;
}
