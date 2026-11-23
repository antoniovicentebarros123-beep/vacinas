// Teste de fumo da interface com jsdom (não substitui testar num navegador real).
import 'fake-indexeddb/auto';
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!doctype html><html lang="pt-AO"><body><div id="app"></div></body></html>', { url: 'http://localhost/' });
globalThis.window = dom.window;
globalThis.document = dom.window.document;
globalThis.addEventListener = () => {};
Object.defineProperty(globalThis, 'navigator', { value: { onLine: true }, configurable: true });

const { abrirBD } = await import('../public/assets/js/db/indexeddb.js');
const { desenharLogin } = await import('../public/assets/js/ui/login.js');
const { desenharPainel } = await import('../public/assets/js/ui/painel.js');
const { descreverEstado } = await import('../public/assets/js/ui/indicador-sync.js');
const { h } = await import('../public/assets/js/ui/dom.js');

const novaRaiz = () => { const r = document.createElement('div'); document.body.append(r); return r; };
let n = 0;

test('login: todos os campos têm label, botão Entrar, Manter sessão e Esqueci a palavra-passe', () => {
  const raiz = novaRaiz();
  desenharLogin(raiz, { aoEntrar: async () => {} });
  for (const id of ['utilizador', 'password', 'manter']) {
    assert.ok(raiz.querySelector(`label[for="${id}"]`), `falta label para ${id}`);
    assert.ok(raiz.querySelector(`#${id}`));
  }
  assert.match(raiz.textContent, /Entrar/);
  assert.match(raiz.textContent, /Manter sessão/);
  assert.match(raiz.textContent, /Esqueci a palavra-passe/);
});

test('login: mostrar/ocultar a palavra-passe e erro claro com campos vazios', () => {
  const raiz = novaRaiz();
  desenharLogin(raiz, { aoEntrar: async () => {} });
  const campo = raiz.querySelector('#password');
  const olho = raiz.querySelector('.botao-olho');
  assert.equal(campo.type, 'password');
  olho.click();
  assert.equal(campo.type, 'text');
  assert.equal(olho.getAttribute('aria-pressed'), 'true');
  olho.click();
  assert.equal(campo.type, 'password');
  raiz.querySelector('form').dispatchEvent(new dom.window.Event('submit', { cancelable: true }));
  const erro = raiz.querySelector('[role="alert"]');
  assert.equal(erro.hidden, false);
  assert.match(erro.textContent, /Indique o utilizador e a palavra-passe/);
});

test('login: "Esqueci a palavra-passe" explica que o administrador repõe', () => {
  const raiz = novaRaiz();
  desenharLogin(raiz, { aoEntrar: async () => {} });
  const botao = [...raiz.querySelectorAll('button')].find((b) => /Esqueci/.test(b.textContent));
  botao.click();
  assert.match(raiz.textContent, /administrador do seu posto/);
});

async function painelPara(perfil, nome = 'Maria Previa') {
  const bd = await abrirBD({ nome: 'ui-' + ++n });
  const raiz = novaRaiz();
  await desenharPainel(raiz, { bd, sessao: { nome, perfil, postoNome: 'Posto X', dispositivoCodigo: 'PC01' }, aoSair: () => {} });
  return raiz;
}

test('painel: Administração só aparece para administradores', async () => {
  assert.ok(!(await painelPara('enfermeiro')).querySelector('.menu').textContent.includes('Administração'));
  assert.ok((await painelPara('administrador')).querySelector('.menu').textContent.includes('Administração'));
  assert.ok((await painelPara('administrador_central')).querySelector('.menu').textContent.includes('Administração'));
});

test('painel: doses mostram "cálculo ainda não disponível" e há indicador de estado', async () => {
  const raiz = await painelPara('enfermeiro');
  assert.equal(raiz.querySelectorAll('.faixa-hoje em').length, 3);
  assert.match(raiz.querySelector('.faixa-hoje').textContent, /Cálculo ainda não disponível/);
  assert.ok(raiz.querySelector('.indicador-sync'));
});

test('XSS: dados com HTML entram como texto, nunca como elementos', async () => {
  const mau = '<img src=x onerror=alert(1)>';
  const raiz = await painelPara('enfermeiro', mau);
  assert.equal(raiz.querySelectorAll('img').length, 0);
  assert.ok(raiz.textContent.includes(mau));
  assert.equal(h('p', {}, '<b>x</b>').children.length, 0);
});

test('indicador: os quatro estados', () => {
  assert.equal(descreverEstado({ online: false, pendentes: 0, ultima: null }).texto, 'Offline');
  assert.match(descreverEstado({ online: false, pendentes: 3, ultima: null }).texto, /Offline · 3 por enviar/);
  assert.equal(descreverEstado({ online: true, pendentes: 2, ultima: null }).texto, '2 registos por enviar');
  assert.equal(descreverEstado({ online: true, pendentes: 1, ultima: null }).texto, '1 registo por enviar');
  assert.match(descreverEstado({ online: true, pendentes: 0, ultima: '2026-11-16T10:30:00Z' }).texto, /^Sincronizado \(última: \d{2}:\d{2}\)$/);
  assert.equal(descreverEstado({ online: true, pendentes: 0, ultima: null }).estado, 'espera');
});
