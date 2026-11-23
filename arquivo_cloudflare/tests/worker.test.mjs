import { test } from 'node:test';
import assert from 'node:assert/strict';
import { criarRouter } from '../src/worker/index.js';

const router = criarRouter();
const env = { DB: { prepare: () => ({ first: async () => ({ 1: 1 }) }) } };
const chamar = (url, init) => router.tratar(new Request('https://vacinas.exemplo' + url, init), env, {});

test('GET /api/saude responde ok com cabeçalhos de segurança', async () => {
  const r = await chamar('/api/saude');
  assert.equal(r.status, 200);
  assert.deepEqual(await r.json(), { estado: 'ok', bd: 'ok' });
  assert.equal(r.headers.get('X-Content-Type-Options'), 'nosniff');
  assert.equal(r.headers.get('Cache-Control'), 'no-store');
});

test('rota inexistente → 404 e método errado → 405, sempre em JSON', async () => {
  assert.equal((await chamar('/api/nada')).status, 404);
  const r = await chamar('/api/saude', { method: 'DELETE', headers: { 'X-Requested-With': 'vacinacao', Origin: 'https://vacinas.exemplo' } });
  assert.equal(r.status, 405);
});

test('CSRF: pedidos que alteram dados sem cabeçalho ou de outra origem são recusados', async () => {
  const semCabecalho = await chamar('/api/saude', { method: 'POST', headers: { Origin: 'https://vacinas.exemplo' } });
  assert.equal(semCabecalho.status, 403);
  const outraOrigem = await chamar('/api/saude', { method: 'POST', headers: { 'X-Requested-With': 'vacinacao', Origin: 'https://mau.exemplo' } });
  assert.equal(outraOrigem.status, 403);
  const semOrigem = await chamar('/api/saude', { method: 'POST', headers: { 'X-Requested-With': 'vacinacao' } });
  assert.equal(semOrigem.status, 403);
});

test('erro interno não expõe detalhes', async () => {
  const mau = { DB: { prepare: () => { throw new Error('segredo: dados da criança X'); } } };
  const r = await router.tratar(new Request('https://vacinas.exemplo/api/saude'), mau, {});
  assert.equal(r.status, 200);                       // a rota apanha o erro e só diz "indisponivel"
  const corpo = await r.json();
  assert.equal(corpo.bd, 'indisponivel');
  assert.ok(!JSON.stringify(corpo).includes('segredo'));
});
