// Testa a migração e o seed num SQLite em memória (node:sqlite).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { DatabaseSync } from 'node:sqlite';
import { gerarSql, validarCalendario, idFixo } from '../database/seeds/gerar-seed.mjs';
import { CALENDARIO } from '../database/seeds/dados-calendario.mjs';

function bdNova() {
  const db = new DatabaseSync(':memory:');
  db.exec('PRAGMA foreign_keys = ON;');
  db.exec(readFileSync(new URL('../database/migrations/0001_esquema_inicial.sql', import.meta.url), 'utf8'));
  return db;
}

test('o seed carrega exatamente 15 oportunidades', () => {
  const db = bdNova();
  db.exec(gerarSql());
  const r = db.prepare('SELECT COUNT(DISTINCT rotulo_idade) AS n FROM calendario_vacinal').get();
  assert.equal(r.n, 15);
});

test('o seed falha se faltar uma oportunidade', () => {
  const menos = CALENDARIO.filter((l) => l[0] !== '7 anos');
  assert.throws(() => validarCalendario(menos, 15), /tem 14 oportunidades/);
});

test('todas as linhas do calendário ficam marcadas a validar e sem valores clínicos inventados', () => {
  const db = bdNova();
  db.exec(gerarSql());
  const r = db.prepare('SELECT COUNT(*) AS n FROM calendario_vacinal WHERE validar_clinica = 1').get();
  assert.equal(r.n, CALENDARIO.length);
  const s = db.prepare('SELECT COUNT(*) AS n FROM calendario_vacinal WHERE idade_minima IS NOT NULL OR intervalo_minimo_dias IS NOT NULL').get();
  assert.equal(s.n, 0);
});

test('Pentavalente OU Tetravalente aos 18 meses partilham o grupo de alternativa', () => {
  const db = bdNova();
  db.exec(gerarSql());
  const r = db.prepare("SELECT COUNT(*) AS n FROM calendario_vacinal WHERE grupo_alternativa = 'PENTA_OU_TETRA_18M'").get();
  assert.equal(r.n, 2);
});

test('febre amarela é dose única e os IDs do seed são fixos com 26 caracteres', () => {
  const db = bdNova();
  db.exec(gerarSql());
  const fa = db.prepare("SELECT c.dose_unica FROM calendario_vacinal c JOIN vacinas v ON v.id = c.vacina_id WHERE v.codigo = 'FA'").get();
  assert.equal(fa.dose_unica, 1);
  assert.equal(idFixo('V', 1).length, 26);
  assert.equal(gerarSql(), gerarSql());
});

test('só o administrador central pode ficar sem posto', () => {
  const db = bdNova();
  db.exec(gerarSql());
  const ins = (perfil, posto) => db.prepare(
    "INSERT INTO utilizadores (id, posto_id, nome, utilizador, password_hash, sal, iteracoes, perfil, criado_em, atualizado_em) VALUES (?, ?, 'x', ?, 'h', 's', 1, ?, 'd', 'd')"
  ).run('0'.repeat(25) + perfil.length, posto, 'u' + perfil, perfil);
  ins('administrador_central', null);
  assert.throws(() => ins('enfermeiro', null));
  assert.throws(() => ins('administrador_central', idFixo('P', 1)));
});

test('stock: lote normalizado, movimentos com sinal pelo tipo e saldo calculado', () => {
  const db = bdNova();
  db.exec(gerarSql());
  const posto = idFixo('P', 1), vac = idFixo('V', 1), d = '2026-11-16T10:00:00.000Z';
  const lote = (id, num) => db.prepare('INSERT INTO stock_lotes (id,posto_id,vacina_id,numero_lote,validade,criado_em,atualizado_em) VALUES (?,?,?,?,?,?,?)').run(id, posto, vac, num, '2027-12-31', d, d);
  assert.throws(() => lote('L'.repeat(26), ' abc1 '), /CHECK/);          // não normalizado
  lote('A'.repeat(26), 'ABC1');
  lote('B'.repeat(26), 'ABC1');                                          // duplicado vindo de outro PC: permitido
  const mov = (id, lid, tipo, q, motivo = null) => db.prepare('INSERT INTO stock_movimentos (id,posto_id,lote_id,tipo,quantidade,motivo,data_movimento,criado_em,atualizado_em) VALUES (?,?,?,?,?,?,?,?,?)').run(id, posto, lid, tipo, q, motivo, d, d, d);
  mov('1'.repeat(26), 'A'.repeat(26), 'entrada', 50);
  mov('2'.repeat(26), 'B'.repeat(26), 'saida_vacinacao', 3);
  mov('3'.repeat(26), 'A'.repeat(26), 'perda', 2, 'frasco partido');
  assert.throws(() => mov('4'.repeat(26), 'A'.repeat(26), 'perda', 1));   // perda exige motivo
  const s = db.prepare('SELECT saldo FROM stock_saldo_lotes WHERE numero_lote = ?').get('ABC1');
  assert.equal(s.saldo, 45);                                             // agrupa os dois lotes duplicados
});

test('anulação exige motivo e só existe uma por vacinação', () => {
  const db = bdNova();
  db.exec(gerarSql());
  const posto = idFixo('P', 1), d = '2026-11-16T10:00:00.000Z', id = (c) => c.repeat(26);
  db.prepare('INSERT INTO criancas (id,posto_id,numero_processo,nome,nome_busca,data_nascimento,criado_em,atualizado_em) VALUES (?,?,?,?,?,?,?,?)').run(id('C'), posto, 'MF-PC01-2026-000001', 'Ana', 'ana', '2026-01-01', d, d);
  db.prepare("INSERT INTO vacinacoes (id,posto_id,crianca_id,vacina_id,numero_dose,data_administracao,administrado_por_nome,criado_em,atualizado_em) VALUES (?,?,?,?,1,'2026-02-01','Enf. X',?,?)").run(id('V'), posto, id('C'), idFixo('V', 1), d, d);
  const anular = (aid, motivo) => db.prepare('INSERT INTO vacinacao_anulacoes (id,posto_id,vacinacao_id,motivo,criado_em,atualizado_em) VALUES (?,?,?,?,?,?)').run(aid, posto, id('V'), motivo, d, d);
  assert.throws(() => anular(id('1'), '   '));
  anular(id('2'), 'lançada na criança errada');
  assert.throws(() => anular(id('3'), 'outra vez'));
});
