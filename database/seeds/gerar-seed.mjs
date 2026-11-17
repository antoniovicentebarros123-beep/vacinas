// Gera database/seeds/0001_catalogo.sql a partir de dados-calendario.mjs.
// FALHA (código de saída 1) se o calendário não tiver exatamente 15 oportunidades
// de vacinação, que é o que o cartão oficial tem.
// Uso: npm run seed:gerar
import { writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { VACINAS, CALENDARIO, OPORTUNIDADES_ESPERADAS, POSTO_INICIAL } from './dados-calendario.mjs';

/**
 * ID fixo e determinístico com 26 caracteres, para ser igual em todos os postos.
 * Formato: "01FXTR" + tipo (1 letra) + número com 19 dígitos.
 */
export function idFixo(tipo, n) {
  return '01FXTR' + tipo + String(n).padStart(19, '0');
}

export function validarCalendario(linhas = CALENDARIO, esperado = OPORTUNIDADES_ESPERADAS) {
  const oportunidades = new Set(linhas.map((l) => l[0]));
  if (oportunidades.size !== esperado) {
    throw new Error(
      `Seed inválido: o calendário tem ${oportunidades.size} oportunidades de vacinação, ` +
      `mas o cartão oficial tem ${esperado}. Oportunidades lidas: ${[...oportunidades].join(' | ')}`
    );
  }
  const codigos = new Set(VACINAS.map((v) => v.codigo));
  for (const l of linhas) {
    if (!codigos.has(l[4])) throw new Error(`Seed inválido: vacina desconhecida "${l[4]}"`);
  }
  const chaves = new Set();
  for (const l of linhas) {
    const k = `${l[0]}|${l[4]}`;
    if (chaves.has(k)) throw new Error(`Seed inválido: linha repetida ${k}`);
    chaves.add(k);
  }
  return oportunidades.size;
}

const q = (v) => (v === null || v === undefined ? 'NULL' : typeof v === 'number' ? String(v) : `'${String(v).replace(/'/g, "''")}'`);

export function gerarSql() {
  validarCalendario();
  const agora = '2026-11-16T00:00:00.000Z';
  const idVacina = new Map(VACINAS.map((v, i) => [v.codigo, idFixo('V', i + 1)]));
  const out = [];
  out.push('-- GERADO por database/seeds/gerar-seed.mjs: não editar à mão.');
  out.push(`INSERT INTO postos (id, codigo, nome, ativo, criado_em, atualizado_em, dispositivo_id, versao) VALUES (${q(idFixo('P', 1))}, ${q(POSTO_INICIAL.codigo)}, ${q(POSTO_INICIAL.nome)}, 1, ${q(agora)}, ${q(agora)}, 'SEED', 1);`);
  VACINAS.forEach((v, i) => {
    out.push(`INSERT INTO vacinas (id, posto_id, codigo, nome, tipo, via_administracao, ativo, ordem, criado_em, atualizado_em, dispositivo_id, versao) VALUES (${q(idFixo('V', i + 1))}, NULL, ${q(v.codigo)}, ${q(v.nome)}, ${q(v.tipo)}, NULL, 1, ${i + 1}, ${q(agora)}, ${q(agora)}, 'SEED', 1);`);
  });
  CALENDARIO.forEach(([rotulo, ideal, ji, jf, cod, dose, ex], i) => {
    out.push(`INSERT INTO calendario_vacinal (id, posto_id, vacina_id, numero_dose, rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, idade_minima, intervalo_minimo_dias, dose_anterior_exigida, grupo_alternativa, dose_unica, ativo, validar_clinica, notas_validacao, criado_em, atualizado_em, dispositivo_id, versao) VALUES (${q(idFixo('C', i + 1))}, NULL, ${q(idVacina.get(cod))}, ${dose}, ${q(rotulo)}, ${q(ideal)}, ${q(ji)}, ${q(jf)}, NULL, NULL, NULL, ${q(ex.grupo ?? null)}, ${ex.dose_unica ?? 0}, 1, 1, ${q(ex.notas ?? null)}, ${q(agora)}, ${q(agora)}, 'SEED', 1);`);
  });
  return out.join('\n') + '\n';
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  try {
    const sql = gerarSql();
    const destino = join(dirname(fileURLToPath(import.meta.url)), '0001_catalogo.sql');
    writeFileSync(destino, sql);
    console.log(`OK: ${destino} (${OPORTUNIDADES_ESPERADAS} oportunidades, ${CALENDARIO.length} doses, ${VACINAS.length} vacinas)`);
  } catch (e) {
    console.error(e.message);
    process.exit(1);
  }
}
