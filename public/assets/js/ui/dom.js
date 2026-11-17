// Criação de elementos sem innerHTML: o texto entra sempre como nó de texto,
// por isso nenhum dado (nome, observação...) pode injetar HTML ou scripts (XSS).
export function h(tag, atributos = {}, ...filhos) {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(atributos ?? {})) {
    if (v === false || v === null || v === undefined) continue;
    if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2).toLowerCase(), v);
    else if (k === 'classe') el.className = v;
    else if (k === 'dados') Object.assign(el.dataset, v);
    else el.setAttribute(k, v === true ? '' : String(v));
  }
  for (const f of filhos.flat(Infinity)) {
    if (f === null || f === undefined || f === false) continue;
    el.append(typeof f === 'object' && f.nodeType ? f : document.createTextNode(String(f)));
  }
  return el;
}

/** Ícone Lucide local (classe gerada em icones.css). Decorativo: escondido dos leitores de ecrã. */
export const icone = (nome) => h('span', { classe: `icone icone-${nome}`, 'aria-hidden': 'true' });
