// Gerador de ULID (26 caracteres, ordenável no tempo). Sem dependências.
// Funciona no navegador e no Worker (usa só crypto.getRandomValues).
// Dentro do mesmo milissegundo os valores são crescentes (monotónico).

const ALFABETO = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford base32
let ultimoTempo = -1;
let ultimoAleatorio = new Uint8Array(10);

function codificarTempo(ms) {
  let s = '';
  for (let i = 0; i < 10; i++) {
    s = ALFABETO[ms % 32] + s;
    ms = Math.floor(ms / 32);
  }
  return s;
}

function codificarAleatorio(bytes) {
  // 80 bits → 16 caracteres de 5 bits
  let s = '';
  let bits = 0, acc = 0;
  for (const b of bytes) {
    acc = (acc << 8) | b;
    bits += 8;
    while (bits >= 5) {
      bits -= 5;
      s += ALFABETO[(acc >> bits) & 31];
    }
    acc &= (1 << bits) - 1;
  }
  return s;
}

function incrementar(bytes) {
  for (let i = bytes.length - 1; i >= 0; i--) {
    if (bytes[i] === 255) { bytes[i] = 0; continue; }
    bytes[i]++;
    return;
  }
}

/** @param {number} [agora] milissegundos (só para testes) */
export function ulid(agora = Date.now()) {
  if (agora <= ultimoTempo) {
    agora = ultimoTempo;
    incrementar(ultimoAleatorio);
  } else {
    ultimoTempo = agora;
    ultimoAleatorio = crypto.getRandomValues(new Uint8Array(10));
  }
  return codificarTempo(agora) + codificarAleatorio(ultimoAleatorio);
}

/** Hora técnica em UTC (ISO 8601). Nunca serve para resolver conflitos. */
export function agoraUtc() {
  return new Date().toISOString();
}
