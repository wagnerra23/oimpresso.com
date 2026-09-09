/**
 * PlacaVeiculo — placa veicular brasileira como componente reutilizável.
 * Dois padrões: 'mercosul' (faixa azul + bandeira, padrão desde 2018) e
 * 'antiga' (cinza, pré-Mercosul). Formata e valida o texto automaticamente
 * (4 letras/3 números no slot certo). Pure, dependency-free (global React + tokens).
 *
 * placa (string, ex. "RFB1D23") · padrao: mercosul|antiga · size: sm|md|lg
 * pais? (default 'BRASIL') · uf? (sigla na faixa, ex. 'SP') · categoria? (cor do texto)
 */
const PLACA_SIZES = {
  sm: { w: 132, head: 14, headFs: 6.5, dot: 6, plate: 22, gap: 4, rx: 7 },
  md: { w: 188, head: 19, headFs: 8.5, dot: 9, plate: 31, gap: 6, rx: 9 },
  lg: { w: 256, head: 26, headFs: 11, dot: 12, plate: 43, gap: 8, rx: 12 },
};
// cor do caractere por categoria (placa antiga: fundo é que mudava; aqui tingimos o texto)
const PLACA_CAT = {
  particular: 'oklch(0.17 0.01 250)',
  comercial:  'oklch(0.45 0.16 28)',   // vermelho
  oficial:    'oklch(0.42 0.14 145)',  // verde
  especial:   'oklch(0.40 0.13 280)',
};

function placaFormat(raw) {
  const s = (raw || '').toUpperCase().replace(/[^A-Z0-9-]/g, '').slice(0, 8);
  return s;
}

export function PlacaVeiculo({ placa = 'BRA0S17', padrao = 'mercosul', size = 'md', pais = 'BRASIL', uf, categoria = 'particular' }) {
  const h = React.createElement;
  const S = PLACA_SIZES[size] || PLACA_SIZES.md;
  const txt = placaFormat(placa);
  const ink = PLACA_CAT[categoria] || PLACA_CAT.particular;
  const mercosul = padrao !== 'antiga';

  const head = mercosul
    ? h('div', { style: {
        display: 'flex', alignItems: 'center', gap: S.gap, height: S.head, padding: '0 ' + (S.gap + 2) + 'px',
        background: 'oklch(0.42 0.16 255)', color: '#fff',
        font: '700 ' + S.headFs + 'px/1 var(--font-sans)', letterSpacing: '.14em',
      } },
        h('span', { style: { width: S.dot, height: S.dot, borderRadius: 2, background: 'oklch(0.66 0.17 145)', flex: 'none' } }),
        h('span', null, pais),
        h('span', { style: { marginLeft: 'auto', font: '700 ' + (S.headFs - 0.5) + 'px/1 var(--font-mono)', letterSpacing: '.05em' } }, uf || 'BR'))
    : h('div', { style: {
        display: 'flex', alignItems: 'center', justifyContent: 'space-between', height: Math.round(S.head * 0.62),
        padding: '0 ' + (S.gap + 2) + 'px', background: 'oklch(0.32 0.01 250)', color: '#e8e8ea',
        font: '700 ' + (S.headFs - 1.5) + 'px/1 var(--font-sans)', letterSpacing: '.18em',
      } },
        h('span', null, pais), uf && h('span', { style: { font: '700 ' + (S.headFs - 2) + 'px/1 var(--font-mono)' } }, uf));

  return h('div', {
    role: 'img', 'aria-label': 'Placa ' + txt,
    style: {
      width: S.w, flex: 'none', borderRadius: S.rx, overflow: 'hidden',
      border: '2px solid ' + (mercosul ? 'oklch(0.32 0.01 250)' : 'oklch(0.32 0.01 250)'),
      background: mercosul ? '#f4f5f6' : '#d8d9da',
      boxShadow: '0 6px 18px -6px rgba(0,0,0,.5)',
    },
  },
    head,
    h('div', { style: {
      textAlign: 'center', padding: (S.plate * 0.16) + 'px 0 ' + (S.plate * 0.26) + 'px',
      font: '700 ' + S.plate + 'px/1 var(--font-mono)', letterSpacing: '.1em',
      color: mercosul ? ink : 'oklch(0.14 0.01 250)',
    } }, txt || '\u00A0'));
}
