// Normalização de texto para pesquisa e para regras de dados.
// Fonte única: usada pelo cliente e pelo Worker.

/** Minúsculas, sem acentos, espaços simplificados. Ex.: "  João  Ngola " → "joao ngola". */
export function normalizarNome(texto) {
  return String(texto ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim();
}

/** Só dígitos. Ex.: "+244 923-456 789" → "244923456789". */
export function normalizarTelefone(texto) {
  return String(texto ?? '').replace(/\D+/g, '');
}

/** Número de lote: sem espaços nas pontas e em maiúsculas (a D1 também o exige). */
export function normalizarLote(texto) {
  return String(texto ?? '').trim().toUpperCase();
}
