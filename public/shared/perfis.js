// Perfis e permissões. O Worker é quem manda; o cliente só usa isto para esconder botões.
export const PERFIS = Object.freeze({
  ADMIN_CENTRAL: 'administrador_central',
  ADMIN_POSTO: 'administrador',
  ENFERMEIRO: 'enfermeiro',
  RECEPCAO: 'recepcao',
});

export const ROTULOS_PERFIL = Object.freeze({
  administrador_central: 'Administrador central',
  administrador: 'Administrador do posto',
  enfermeiro: 'Enfermeiro(a)',
  recepcao: 'Recepção',
});

// Permissão → perfis que a têm.
const PERMISSOES = {
  'catalogo.editar':      ['administrador_central'],
  'postos.gerir':         ['administrador_central'],
  'utilizadores.gerir':   ['administrador_central', 'administrador'],   // do próprio posto (o Worker verifica)
  'dispositivos.gerir':   ['administrador_central', 'administrador'],
  'criancas.escrever':    ['administrador', 'enfermeiro', 'recepcao'],
  'criancas.ler':         ['administrador', 'enfermeiro', 'recepcao'],
  'vacinacao.registar':   ['enfermeiro'],
  'vacinacao.anular':     ['enfermeiro', 'administrador'],
  'stock.gerir':          ['enfermeiro', 'administrador'],
  'relatorios.ver':       ['administrador', 'enfermeiro'],
};

export function temPermissao(perfil, permissao) {
  return (PERMISSOES[permissao] ?? []).includes(perfil);
}
