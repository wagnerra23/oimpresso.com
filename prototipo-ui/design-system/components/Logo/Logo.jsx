/**
 * Logo — a marca oficial Office Impresso. O símbolo é o cubo CMYK isométrico
 * (amarelo · magenta · ciano/azul · cinza — as cores de processo da comunicação
 * visual), reproduzido como SVG vetorial escalável. `wordmark` adiciona o
 * lettering "Office Impresso"; `tagline` adiciona a assinatura. Pure, dependency-free.
 *
 * O lettering é o tratamento de aplicação (IBM Plex). Para o wordmark de marketing
 * exato (tipografia custom em vetor), use o asset assets/brand/logo-full.svg.
 *
 * size (altura do cubo, px) · wordmark? · tagline? · color? (cor de "Office", default currentColor)
 */
const LOGO_CUBE = [
  ['#89869D', '116,329 69,302 116,275 163,302'],
  ['#272727', '69,302 116,329 116,383 69,356'],
  ['#272727', '163,302 116,329 116,383 163,356'],
  ['#7DD1EB', '168,359 121,332 168,305 215,332'],
  ['#235EA9', '121,332 168,359 168,413 121,386'],
  ['#235EA9', '215,332 168,359 168,413 215,386'],
  ['#F5F19D', '65,359 18,332 65,304 111,332'],
  ['#F0E62D', '18,332 65,359 65,413 18,386'],
  ['#F0E62D', '112,332 65,359 65,413 112,386'],
  ['#D087B9', '116,389 70,361 116,334 163,361'],
  ['#EB3088', '70,361 116,389 116,443 69,416'],
  ['#EB3088', '163,361 116,389 116,443 163,416'],
];

export function Logo({ size = 32, wordmark = false, tagline = false, color = 'currentColor' }) {
  const h = React.createElement;
  const w = Math.round(size * 213 / 184);
  const mark = h('svg', {
    width: w, height: size, viewBox: '10 267 213 184', role: 'img',
    'aria-label': wordmark ? null : 'Office Impresso', style: { display: 'block', flex: 'none' },
  }, LOGO_CUBE.map(([fill, points], i) =>
    h('polygon', { key: i, points, fill, stroke: fill, strokeWidth: 16, strokeMiterlimit: 22.9256, style: { fillRule: 'evenodd' } })));

  if (!wordmark) return mark;

  const fs = Math.round(size * 0.92);
  return h('span', { style: { display: 'inline-flex', alignItems: 'center', gap: Math.round(size * 0.34), lineHeight: 1 }, role: 'img', 'aria-label': 'Office Impresso' },
    mark,
    h('span', { style: { display: 'inline-flex', flexDirection: 'column', gap: Math.round(size * 0.08) } },
      h('span', { style: { font: '300 ' + fs + 'px/1 var(--font-sans)', letterSpacing: '-0.01em', whiteSpace: 'nowrap' } },
        h('span', { style: { color } }, 'Office'),
        h('span', { style: { color: '#2987D0', fontWeight: 400 } }, ' Impresso')),
      tagline && h('span', { style: { font: '400 ' + Math.round(size * 0.27) + 'px/1.2 var(--font-sans)', letterSpacing: '0.01em', color, opacity: 0.85, whiteSpace: 'nowrap' } }, 'Software de Gestão para Comunicação Visual')));
}
