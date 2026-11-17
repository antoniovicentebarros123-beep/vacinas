// Validações partilhadas entre cliente e Worker. Mensagens em português de Angola.
// Cada função devolve null se estiver tudo bem, ou a mensagem de erro.

const ISO_DATA = /^\d{4}-\d{2}-\d{2}$/;

/** Data clínica 'AAAA-MM-DD', real e não futura. */
export function validarDataClinica(valor, { permitirFutura = false, hoje = new Date() } = {}) {
  if (!ISO_DATA.test(String(valor ?? ''))) return 'Indique uma data válida.';
  const d = new Date(valor + 'T00:00:00Z');
  if (Number.isNaN(d.getTime()) || d.toISOString().slice(0, 10) !== valor) return 'Esta data não existe.';
  if (!permitirFutura && valor > hoje.toISOString().slice(0, 10)) return 'A data não pode ser no futuro.';
  return null;
}

export function validarObrigatorio(valor, rotulo) {
  return String(valor ?? '').trim() ? null : `${rotulo} é obrigatório.`;
}
