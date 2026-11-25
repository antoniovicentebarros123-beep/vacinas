// Ecrã de login. Campos: Utilizador, Palavra-passe (mostrar/ocultar), Manter sessão,
// "Esqueci a palavra-passe" (sem e-mail: quem repõe é o administrador).
import { h, icone } from './dom.js';
import { entrar } from '../autenticacao.js';

export function desenharLogin(raiz, { nomeInstituicao = '', aoEntrar }) {
  const erro = h('div', { classe: 'mensagem mensagem-erro', role: 'alert', hidden: true });
  const ajudaEsqueci = h('div', { classe: 'mensagem mensagem-aviso', hidden: true },
    'Peça ao administrador do seu posto para repor a palavra-passe. No próximo início de sessão terá de escolher uma nova.');

  const campoUtilizador = h('input', { id: 'utilizador', name: 'utilizador', type: 'text', autocomplete: 'username', required: true, autocapitalize: 'none', spellcheck: 'false', placeholder: 'Utilizador' });
  const campoPassword = h('input', { id: 'password', name: 'password', type: 'password', autocomplete: 'current-password', required: true, placeholder: 'Palavra-passe' });
  const manter = h('input', { id: 'manter', type: 'checkbox' });

  const olho = h('button', { type: 'button', classe: 'botao-olho', 'aria-label': 'Mostrar palavra-passe', 'aria-pressed': 'false' }, icone('eye'));
  olho.addEventListener('click', () => {
    const visivel = campoPassword.type === 'password';
    campoPassword.type = visivel ? 'text' : 'password';
    olho.setAttribute('aria-pressed', String(visivel));
    olho.setAttribute('aria-label', visivel ? 'Ocultar palavra-passe' : 'Mostrar palavra-passe');
    olho.replaceChildren(icone(visivel ? 'eye-off' : 'eye'));
  });

  const botao = h('button', { type: 'submit', classe: 'login-botao' }, 'Entrar');

  const formulario = h('form', { classe: 'login-form', novalidate: true },
    h('div', { classe: 'campo' },
      h('label', { for: 'utilizador', classe: 'so-leitor' }, 'Utilizador'),
      h('div', { classe: 'entrada-hex' }, icone('user'), campoUtilizador)),
    h('div', { classe: 'campo' },
      h('label', { for: 'password', classe: 'so-leitor' }, 'Palavra-passe'),
      h('div', { classe: 'entrada-hex' }, icone('lock'), campoPassword, olho)),
    erro,
    botao,
    h('div', { classe: 'login-extras' },
      h('label', { for: 'manter' }, manter, 'Manter sessão'),
      h('button', { type: 'button', classe: 'link-botao', 'aria-expanded': 'false', onclick: (ev) => {
        ajudaEsqueci.hidden = !ajudaEsqueci.hidden;
        ev.currentTarget.setAttribute('aria-expanded', String(!ajudaEsqueci.hidden));
      } }, 'Esqueci a palavra-passe')),
    ajudaEsqueci);

  formulario.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    erro.hidden = true;
    const utilizador = campoUtilizador.value.trim();
    const password = campoPassword.value;
    if (!utilizador || !password) {
      erro.textContent = 'Indique o utilizador e a palavra-passe.';
      erro.hidden = false;
      (utilizador ? campoPassword : campoUtilizador).focus();
      return;
    }
    botao.disabled = true;
    botao.textContent = 'A entrar…';
    try {
      const resultado = await entrar({ utilizador, password, manter: manter.checked });
      campoPassword.value = '';
      await aoEntrar(resultado);
    } catch (e) {
      erro.textContent = e.message;
      erro.hidden = false;
    } finally {
      botao.disabled = false;
      botao.textContent = 'Entrar';
    }
  });

  raiz.replaceChildren(
    h('main', { classe: 'login', id: 'conteudo' },
      h('div', { classe: 'login-caixa' },
        h('div', { classe: 'login-selo', 'aria-hidden': 'true' }, icone('user')),
        h('section', { classe: 'login-painel', 'aria-labelledby': 'titulo-login' },
          h('h1', { id: 'titulo-login' }, 'Entrar'),
          h('p', { classe: 'login-nome' }, nomeInstituicao),
          formulario,
          h('p', { classe: 'login-rodape' }, 'Controlo de vacinação infantil')))));
  campoUtilizador.focus();
}
