// Testa o repositório base do IndexedDB (com fake-indexeddb, só em desenvolvimento).
import 'fake-indexeddb/auto';
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { abrirBD, lerTodos } from '../public/assets/js/db/indexeddb.js';
import { RepositorioBase, executar } from '../public/assets/js/db/repositorio-base.js';
import { proximoNumeroProcesso } from '../public/assets/js/db/numero-processo.js';
import { ulid } from '../public/shared/ulid.js';
import { normalizarNome, normalizarTelefone, normalizarLote } from '../public/shared/normalizar.js';

let n = 0;
const ctx = () => ({ postoId: 'P'.repeat(26), dispositivoId: 'D'.repeat(26), utilizadorId: 'U'.repeat(26) });
async function preparar(opcoes = {}) {
  const bd = await abrirBD({ nome: 'teste-' + ++n });
  const repo = new RepositorioBase(bd, { store: 'criancas', contexto: ctx, auditar: true, ...opcoes });
  return { bd, repo };
}
const crianca = (nome = 'Ana Silva', proc = 'MF-PC01-2026-000001') => ({
  numero_processo: proc, nome, nome_busca: normalizarNome(nome), data_nascimento: '2026-01-10',
});

test('criar: preenche campos técnicos e entra na fila na mesma transação', async () => {
  const { bd, repo } = await preparar();
  const c = await repo.criar(crianca());
  assert.equal(c.versao, 1);
  assert.equal(c.posto_id, 'P'.repeat(26));
  assert.equal(c.dispositivo_id, 'D'.repeat(26));
  assert.equal(c.eliminado_em, null);
  assert.equal(c.id.length, 26);
  const fila = await lerTodos(bd, 'sync_outbox');
  const op = fila.filter((o) => o.entidade === 'criancas');
  assert.equal(op.length, 1);
  assert.equal(op[0].operacao, 'inserir');
  assert.equal(op[0].estado, 'pendente');
  assert.equal(op[0].op_id.length, 26);
  assert.equal(op[0].payload.nome, 'Ana Silva');
});

test('atualizar: payload só com os campos alterados, versão sobe e a auditoria é campo a campo', async () => {
  const { bd, repo } = await preparar();
  const c = await repo.criar(crianca());
  const novo = await repo.atualizar(c.id, { bairro: 'Viana', nome: 'Ana Silva', sexo: 'F' });
  assert.equal(novo.versao, 2);
  const fila = await lerTodos(bd, 'sync_outbox');
  const upd = fila.find((o) => o.entidade === 'criancas' && o.operacao === 'atualizar');
  assert.deepEqual(Object.keys(upd.payload).sort(), ['bairro', 'sexo']);   // nome não mudou
  const aud = (await lerTodos(bd, 'auditoria')).filter((a) => a.acao === 'atualizar');
  assert.deepEqual(aud.map((a) => a.campo).sort(), ['bairro', 'sexo']);
  assert.equal(aud.find((a) => a.campo === 'bairro').valor_novo, '"Viana"');
  // a auditoria também se sincroniza
  assert.equal(fila.filter((o) => o.entidade === 'auditoria').length, 1 + 2);   // 1 de "criar" + 2 campos
});

test('atualizar sem alterações reais não cria operação nem versão', async () => {
  const { bd, repo } = await preparar();
  const c = await repo.criar(crianca());
  const antes = (await lerTodos(bd, 'sync_outbox')).length;
  const r = await repo.atualizar(c.id, { nome: 'Ana Silva' });
  assert.equal(r.versao, 1);
  assert.equal((await lerTodos(bd, 'sync_outbox')).length, antes);
});

test('campos técnicos não se alteram por esta via', async () => {
  const { repo } = await preparar();
  const c = await repo.criar(crianca());
  const r = await repo.atualizar(c.id, { posto_id: 'X'.repeat(26), versao: 99, bairro: 'Cazenga' });
  assert.equal(r.posto_id, 'P'.repeat(26));
  assert.equal(r.versao, 2);
});

test('registos que só se acrescentam recusam edição e eliminação', async () => {
  const { repo } = await preparar({ store: 'vacinacoes', auditar: false, somenteAcrescentar: true });
  const v = await repo.criar({ crianca_id: 'C'.repeat(26), vacina_id: 'V'.repeat(26), numero_dose: 1, data_administracao: '2026-02-01', administrado_por_nome: 'Enf. X', origem: 'posto' });
  await assert.rejects(() => repo.atualizar(v.id, { numero_dose: 2 }), /não pode ser editado/);
  await assert.rejects(() => repo.eliminar(v.id), /não pode ser eliminado/);
});

test('atomicidade: se a gravação falha, nada fica na fila', async () => {
  const { bd, repo } = await preparar();
  const c = await repo.criar(crianca());
  const antes = (await lerTodos(bd, 'sync_outbox')).length;
  await assert.rejects(() => repo.criar({ ...crianca('Outra', 'MF-PC01-2026-000002'), id: c.id }));   // id repetido
  assert.equal((await lerTodos(bd, 'sync_outbox')).length, antes);
  assert.equal((await lerTodos(bd, 'criancas')).length, 1);
});

test('executar junta repositórios diferentes numa só transação (tudo ou nada)', async () => {
  const { bd, repo } = await preparar();
  const lotes = new RepositorioBase(bd, { store: 'stock_lotes', contexto: ctx });
  const mov = new RepositorioBase(bd, { store: 'stock_movimentos', contexto: ctx, somenteAcrescentar: true });
  await assert.rejects(() => executar(bd, ['stock_lotes', 'stock_movimentos'], async (tx) => {
    const l = await lotes.criarEm(tx, { vacina_id: 'V'.repeat(26), numero_lote: 'ABC1', validade: '2027-12-31' });
    await mov.criarEm(tx, { lote_id: l.id, tipo: 'entrada', quantidade: 10, data_movimento: '2026-11-16' });
    throw new Error('falha a meio');
  }), /falha a meio/);
  assert.equal((await lerTodos(bd, 'stock_lotes')).length, 0);
  assert.equal((await lerTodos(bd, 'sync_outbox')).length, 0);
});

test('eliminar é lógico e fica na fila', async () => {
  const { bd, repo } = await preparar();
  const c = await repo.criar(crianca());
  await repo.eliminar(c.id);
  assert.equal(await repo.obter(c.id), null);
  assert.equal((await lerTodos(bd, 'criancas')).length, 1);          // continua na store
  const fila = await lerTodos(bd, 'sync_outbox');
  assert.ok(fila.some((o) => o.entidade === 'criancas' && o.operacao === 'eliminar'));
});

test('sem dispositivo registado a gravação é recusada com mensagem clara', async () => {
  const { repo } = await preparar({ contexto: () => ({ postoId: 'P'.repeat(26), dispositivoId: null }) });
  await assert.rejects(() => repo.criar(crianca()), /primeiro início de sessão com internet/);
});

test('número de processo: recalcula a partir do que existe e salta colisões', async () => {
  const { bd, repo } = await preparar();
  const base = { codigoPosto: 'MF', codigoDispositivo: 'PC01', ano: 2026 };
  assert.equal(await proximoNumeroProcesso(bd, base), 'MF-PC01-2026-000001');
  await repo.criar(crianca('A', 'MF-PC01-2026-000001'));
  await repo.criar(crianca('B', 'MF-PC01-2026-000007'));
  await repo.criar(crianca('C', 'MF-PC02-2026-000099'));              // outro dispositivo: não conta
  assert.equal(await proximoNumeroProcesso(bd, base), 'MF-PC01-2026-000008');
  assert.equal(await proximoNumeroProcesso(bd, { ...base, minimo: 20 }), 'MF-PC01-2026-000021');
});

test('ULID: 26 caracteres, único e ordenável mesmo dentro do mesmo milissegundo', () => {
  const t = Date.now() + 1000;
  const ids = Array.from({ length: 50 }, () => ulid(t));
  assert.ok(ids.every((i) => /^[0-9A-HJKMNP-TV-Z]{26}$/.test(i)));
  assert.equal(new Set(ids).size, 50);
  assert.deepEqual([...ids].sort(), ids);
});

test('normalização de nome, telefone e lote', () => {
  assert.equal(normalizarNome('  João   Ngola '), 'joao ngola');
  assert.equal(normalizarTelefone('+244 923-456 789'), '244923456789');
  assert.equal(normalizarLote('  ab12-x '), 'AB12-X');
});
