// Estrutura do sistema depois do login (barra lateral, topo, conteúdo) e o painel inicial.
import { h, icone } from './dom.js';
import { criarIndicador, contarPendentes } from './indicador-sync.js';
import { lerTodos } from '../db/indexeddb.js';
import { ROTULOS_PERFIL, temPermissao } from '../../../shared/perfis.js';

function saudacao(nome, agora = new Date()) {
  const hora = agora.getHours();
  const forma = hora < 12 ? 'Bom dia' : hora < 19 ? 'Boa tarde' : 'Boa noite';
  return `${forma}, ${nome.split(' ')[0]}`;
}

// Itens do menu. "fase" = quando fica disponível; sem rota = ainda não existe.
function itensMenu(perfil) {
  const itens = [
    { rotulo: 'Painel', icone: 'layout-dashboard', rota: '#/painel' },
    { rotulo: 'Crianças', icone: 'baby', fase: 2 },
    { rotulo: 'Vacinação', icone: 'syringe', fase: 3 },
    { rotulo: 'Faltosos', icone: 'phone-call', fase: 4 },
    { rotulo: 'Stock', icone: 'package', fase: 4 },
    { rotulo: 'Relatórios', icone: 'chart-column', fase: 6 },
  ];
  if (temPermissao(perfil, 'utilizadores.gerir') || temPermissao(perfil, 'catalogo.editar')) {
    itens.push({ rotulo: 'Administração', icone: 'settings', fase: 7 });
  }
  return itens;
}

export async function desenharPainel(raiz, { bd, sessao, aoSair }) {
  const aplicacao = h('div', { classe: 'aplicacao', dados: { menu: 'fechado' } });
  const menuMobile = window.matchMedia('(max-width: 62rem)');
  const alternarMenu = (aberto, restaurarFoco = true) => {
    if (aberto && !menuMobile.matches) return;
    aplicacao.dataset.menu = aberto ? 'aberto' : 'fechado';
    botaoMenu.setAttribute('aria-expanded', String(aberto));
    botaoMenu.setAttribute('aria-label', aberto ? 'Fechar navegação' : 'Abrir menu');
    if (aberto) primeiroItemMenu?.focus();
    else if (restaurarFoco) {
      window.setTimeout(() => {
        if (menuMobile.matches && aplicacao.dataset.menu === 'fechado') botaoMenu.focus();
      }, 0);
    }
  };

  const menu = h('ul', { classe: 'menu' }, itensMenu(sessao.perfil).map((i) =>
    h('li', {}, i.rota
      ? h('a', { href: i.rota, 'aria-current': 'page' }, icone(i.icone), i.rotulo)
      : h('span', { classe: 'item-menu em-breve', 'aria-disabled': 'true' }, icone(i.icone), i.rotulo, h('small', {}, `Fase ${i.fase}`)))));

  const barra = h('aside', { classe: 'barra-lateral', id: 'barra', 'aria-label': 'Menu principal' },
    h('div', { classe: 'marca' },
      h('div', { classe: 'marca-selo', 'aria-hidden': 'true' }, icone('syringe')),
      h('div', {}, h('div', { classe: 'marca-nome' }, 'Vacinação infantil'), h('div', { classe: 'marca-posto' }, sessao.postoNome ?? '')),
    ),
    h('nav', { 'aria-label': 'Secções' }, menu));

  const topo = h('header', { classe: 'topo' },
    h('button', { type: 'button', classe: 'botao botao-secundario botao-icone botao-menu', 'aria-label': 'Abrir menu', 'aria-controls': 'barra', 'aria-expanded': 'false' }, icone('menu')),
    h('div', { classe: 'topo-titulo' }, 'Painel'),
    criarIndicador(bd),
    h('div', { classe: 'topo-utilizador' },
      h('div', {}, sessao.nome, h('small', {}, ROTULOS_PERFIL[sessao.perfil] ?? sessao.perfil)),
      h('button', { type: 'button', classe: 'botao botao-secundario botao-icone', 'aria-label': 'Terminar sessão', title: 'Terminar sessão' }, icone('log-out'))));

  const botaoMenu = topo.querySelector('.botao-menu');
  const primeiroItemMenu = menu.querySelector('a');
  botaoMenu.addEventListener('click', () => alternarMenu(aplicacao.dataset.menu !== 'aberto'));
  topo.querySelector('[aria-label="Terminar sessão"]').addEventListener('click', aoSair);

  // Dados locais para os cartões de resumo
  const [criancas, pendentes] = await Promise.all([lerTodos(bd, 'criancas'), contarPendentes(bd)]);
  const nCriancas = criancas.filter((c) => !c.eliminado_em).length;

  const hoje = new Date().toLocaleDateString('pt-AO', { weekday: 'long', day: 'numeric', month: 'long' });
  const calculoIndisponivel = h('em', {}, 'Cálculo ainda não disponível');

  const conteudo = h('main', { classe: 'conteudo', id: 'conteudo', tabindex: '-1' },
    h('section', { classe: 'painel-saudacao' },
      h('h1', {}, saudacao(sessao.nome)),
      h('p', {}, hoje)),
    pendentes > 0 && h('div', { classe: 'mensagem mensagem-aviso', role: 'status' },
      `Há ${pendentes} ${pendentes === 1 ? 'registo' : 'registos'} por enviar. Estão guardados neste computador: não limpe os dados do navegador até serem enviados.`),
    h('section', { classe: 'faixa-hoje', 'aria-labelledby': 'titulo-hoje' },
      h('h2', { id: 'titulo-hoje' }, 'Hoje no posto'),
      h('div', { classe: 'indicador-grande' }, h('strong', {}, '—'), h('span', {}, 'Crianças esperadas hoje'), calculoIndisponivel.cloneNode(true)),
      h('div', { classe: 'indicador-grande' }, h('strong', {}, '—'), h('span', {}, 'Em atraso'), calculoIndisponivel.cloneNode(true)),
      h('div', { classe: 'indicador-grande' }, h('strong', {}, '—'), h('span', {}, 'Faltosas'), calculoIndisponivel.cloneNode(true))),
    h('section', { classe: 'grelha-resumo', 'aria-label': 'Resumo' },
      h('article', { classe: 'cartao resumo' }, h('div', { classe: 'resumo-icone' }, icone('users')),
        h('div', {}, h('strong', {}, String(nCriancas)), h('p', {}, 'Crianças registadas neste computador'))),
      h('article', { classe: 'cartao resumo' }, h('div', { classe: 'resumo-icone' }, icone('refresh-cw')),
        h('div', {}, h('strong', {}, String(pendentes)), h('p', {}, 'Registos por enviar para a nuvem'))),
      h('article', { classe: 'cartao resumo' }, h('div', { classe: 'resumo-icone' }, icone('shield-check')),
        h('div', {}, h('strong', {}, sessao.dispositivoCodigo ?? '—'), h('p', {}, 'Código deste computador')))),
    h('section', { classe: 'cartao painel-aviso-fase' },
      h('h2', {}, 'O que já está pronto'),
      h('p', {}, 'Base de dados local, fila de envio e perfis. As crianças, a vacinação e o cálculo das doses chegam nas próximas fases.')));

  aplicacao.append(barra, h('div', { classe: 'coluna-principal' }, topo, conteudo));
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && menuMobile.matches && aplicacao.dataset.menu === 'aberto') alternarMenu(false);
  });
  document.addEventListener('click', (ev) => {
    if (aplicacao.dataset.menu !== 'aberto' || !(ev.target instanceof Element)) return;
    if (ev.target.closest('.botao-menu')) return;
    if (ev.target.closest('.menu a, .menu .item-menu') || !ev.target.closest('.barra-lateral')) alternarMenu(false);
  });
  menuMobile.addEventListener?.('change', (ev) => {
    if (!ev.matches && aplicacao.dataset.menu === 'aberto') alternarMenu(false, false);
  });

  raiz.replaceChildren(aplicacao);
}
