// Dados iniciais do catálogo vacinal, transcritos do cartão oficial.
// Os nomes e as idades podem ser editados depois pelo administrador central.
// NÃO se inventam valores clínicos: o que o cartão não diz fica null e a linha
// fica marcada "a validar pela equipa clínica".

export const VACINAS = [
  { codigo: 'BCG',       nome: 'BCG',                         tipo: 'vacina' },
  { codigo: 'POLIO_ORAL',nome: 'Polio oral',                  tipo: 'vacina' },
  { codigo: 'HEPB',      nome: 'Hepatite B',                  tipo: 'vacina' },
  { codigo: 'PENTA',     nome: 'Pentavalente',                tipo: 'vacina' },
  { codigo: 'PNEUMO13',  nome: 'Pneumo 13',                   tipo: 'vacina' },
  { codigo: 'ROTA',      nome: 'Rotavírus',                   tipo: 'vacina' },
  { codigo: 'MENACWY',   nome: 'Meningocócica conjugada quadrivalente (A, C, Y, W-135)', tipo: 'vacina' },
  { codigo: 'VPI',       nome: 'Polio injectável (VPI)',      tipo: 'vacina' },
  { codigo: 'VITA',      nome: 'Vitamina A',                  tipo: 'suplemento' },
  { codigo: 'SR',        nome: 'Sarampo + Rubéola',           tipo: 'vacina' },
  { codigo: 'FA',        nome: 'Febre amarela',               tipo: 'vacina' },
  { codigo: 'HEPA',      nome: 'Hepatite A',                  tipo: 'vacina' },
  { codigo: 'VARICELA',  nome: 'Varicela',                    tipo: 'vacina' },
  { codigo: 'VASPR',     nome: 'VASPR',                       tipo: 'vacina' },
  { codigo: 'TETRA',     nome: 'Tetravalente',                tipo: 'vacina' },
  { codigo: 'TIFOIDE',   nome: 'Febre tifoide',               tipo: 'vacina' },
  { codigo: 'TETANO',    nome: 'Tétano',                      tipo: 'vacina' },
  { codigo: 'HPV',       nome: 'HPV',                         tipo: 'vacina' },
];

const NUM = 'Numeração da dose deduzida da ordem no cartão: a validar pela equipa clínica.';

// [rotulo_idade, idade_ideal_meses, janela_inicio, janela_fim, vacina, numero_dose, extras]
// Janelas só existem onde o cartão dá um intervalo ("5-6 anos", "10-12 anos").
export const CALENDARIO = [
  ['Ao nascer', 0, null, null, 'BCG',        1, { notas: NUM }],
  ['Ao nascer', 0, null, null, 'POLIO_ORAL', 0, { notas: 'Dose ao nascer registada como dose 0: a validar pela equipa clínica.' }],
  ['Ao nascer', 0, null, null, 'HEPB',       1, { notas: 'Dose ao nascer registada como dose 1: a validar pela equipa clínica.' }],

  ['2 meses', 2, null, null, 'PENTA',      1, { notas: NUM }],
  ['2 meses', 2, null, null, 'PNEUMO13',   1, { notas: NUM }],
  ['2 meses', 2, null, null, 'POLIO_ORAL', 1, { notas: NUM }],
  ['2 meses', 2, null, null, 'ROTA',       1, { notas: NUM }],

  ['3 meses', 3, null, null, 'MENACWY',    1, { notas: NUM }],

  ['4 meses', 4, null, null, 'PENTA',      2, { notas: NUM }],
  ['4 meses', 4, null, null, 'PNEUMO13',   2, { notas: NUM }],
  ['4 meses', 4, null, null, 'POLIO_ORAL', 2, { notas: NUM }],
  ['4 meses', 4, null, null, 'ROTA',       2, { notas: NUM }],
  ['4 meses', 4, null, null, 'VPI',        1, { notas: NUM }],

  ['5 meses', 5, null, null, 'MENACWY',    2, { notas: NUM }],

  ['6 meses', 6, null, null, 'PENTA',      3, { notas: NUM }],
  ['6 meses', 6, null, null, 'PNEUMO13',   3, { notas: NUM }],
  ['6 meses', 6, null, null, 'POLIO_ORAL', 3, { notas: NUM }],
  ['6 meses', 6, null, null, 'VITA',       1, { notas: NUM }],

  ['9 meses', 9, null, null, 'SR',         1, { notas: NUM }],
  ['9 meses', 9, null, null, 'FA',         1, { dose_unica: 1, notas: 'Dose única segundo o cartão.' }],
  ['9 meses', 9, null, null, 'VITA',       2, { notas: NUM }],

  ['12 meses', 12, null, null, 'HEPA',     1, { notas: NUM }],
  ['12 meses', 12, null, null, 'VARICELA', 1, { notas: NUM }],

  ['15 meses', 15, null, null, 'VASPR',    1, { notas: NUM }],
  ['15 meses', 15, null, null, 'MENACWY',  3, { notas: 'No cartão aparece como "Meningite conjugada quadrivalente (A, C, Y, W-135)": confirmar que é a mesma vacina. ' + NUM }],

  ['18 meses', 18, null, null, 'PENTA',    4, { grupo: 'PENTA_OU_TETRA_18M', notas: 'Alternativa a escolher na hora (Pentavalente OU Tetravalente). ' + NUM }],
  ['18 meses', 18, null, null, 'TETRA',    1, { grupo: 'PENTA_OU_TETRA_18M', notas: 'Alternativa a escolher na hora (Pentavalente OU Tetravalente). ' + NUM }],
  ['18 meses', 18, null, null, 'PNEUMO13', 4, { notas: NUM }],
  ['18 meses', 18, null, null, 'POLIO_ORAL',4, { notas: NUM }],
  ['18 meses', 18, null, null, 'HEPA',     2, { notas: NUM }],

  ['2 anos', 24, null, null, 'TIFOIDE',    1, { notas: 'Reforço de 3 em 3 anos segundo o cartão: a validar. ' + NUM }],
  ['2 anos', 24, null, null, 'MENACWY',    4, { notas: NUM }],

  ['4 anos', 48, null, null, 'VARICELA',   2, { notas: 'Reforço. ' + NUM }],

  // "5-6 anos": o cartão só dá o intervalo; o fim da janela (72 meses) é interpretação a validar.
  ['5-6 anos', 60, 60, 72, 'PENTA',        5, { notas: 'Fim da janela interpretado como 72 meses: a validar. ' + NUM }],
  ['5-6 anos', 60, 60, 72, 'VASPR',        2, { notas: 'Fim da janela interpretado como 72 meses: a validar. ' + NUM }],

  ['7 anos', 84, null, null, 'TETANO',     1, { notas: NUM }],

  // "10-12 anos": ideal no início do intervalo; 144 meses = 12 anos.
  ['10-12 anos', 120, 120, 144, 'HPV',     1, { notas: 'Fim da janela interpretado como 144 meses: a validar. ' + NUM }],
];

export const OPORTUNIDADES_ESPERADAS = 15;

export const POSTO_INICIAL = { codigo: 'MF', nome: 'Centro Médico Hospitalar Maria de Fátima' };
