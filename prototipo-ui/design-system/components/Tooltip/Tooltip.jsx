/**
 * Tooltip — dica contextual sobre hover E foco (acessível por teclado). Wrapper
 * leve: envolve qualquer elemento, mostra um balão escuro com seta. Pure,
 * dependency-free (global React + inline tokens).
 *
 * content (node) · side: top|bottom|left|right · kbd? · delay? · children
 */
export function Tooltip({ content, side = 'top', kbd, delay = 120, children }) {
  const h = React.createElement;
  const [open, setOpen] = React.useState(false);
  const t = React.useRef(null);

  const show = () => { clearTimeout(t.current); t.current = setTimeout(() => setOpen(true), delay); };
  const hide = () => { clearTimeout(t.current); setOpen(false); };
  React.useEffect(() => () => clearTimeout(t.current), []);

  const isV = side === 'top' || side === 'bottom';
  const pos = {
    top:    { bottom: '100%', left: '50%', transform: 'translateX(-50%)', marginBottom: 8 },
    bottom: { top: '100%', left: '50%', transform: 'translateX(-50%)', marginTop: 8 },
    left:   { right: '100%', top: '50%', transform: 'translateY(-50%)', marginRight: 8 },
    right:  { left: '100%', top: '50%', transform: 'translateY(-50%)', marginLeft: 8 },
  }[side];
  const arrow = {
    top:    { top: '100%', left: '50%', marginLeft: -4, borderWidth: '4px 4px 0', borderColor: 'var(--tt-bg) transparent transparent' },
    bottom: { bottom: '100%', left: '50%', marginLeft: -4, borderWidth: '0 4px 4px', borderColor: 'transparent transparent var(--tt-bg)' },
    left:   { left: '100%', top: '50%', marginTop: -4, borderWidth: '4px 0 4px 4px', borderColor: 'transparent transparent transparent var(--tt-bg)' },
    right:  { right: '100%', top: '50%', marginTop: -4, borderWidth: '4px 4px 4px 0', borderColor: 'transparent var(--tt-bg) transparent transparent' },
  }[side];

  return h('span', {
    style: { position: 'relative', display: 'inline-flex', '--tt-bg': 'oklch(0.24 0.01 80)' },
    onMouseEnter: show, onMouseLeave: hide, onFocus: show, onBlur: hide,
  },
    children,
    open && content != null && h('span', {
      role: 'tooltip',
      style: {
        position: 'absolute', zIndex: 80, whiteSpace: isV ? 'nowrap' : 'normal', maxWidth: 240, width: 'max-content',
        background: 'var(--tt-bg)', color: 'oklch(0.97 0.005 90)', borderRadius: 7,
        padding: kbd ? '5px 7px 5px 9px' : '5px 9px', font: '500 11.5px/1.35 var(--font-sans)',
        display: 'flex', alignItems: 'center', gap: 7, boxShadow: '0 6px 20px -6px rgba(0,0,0,.4)',
        pointerEvents: 'none', animation: 'ds-tt-in .12s ease both', ...pos,
      },
    },
      h('span', null, content),
      kbd && h('kbd', { style: { font: '600 9.5px/1 var(--font-mono)', color: 'oklch(0.97 0.005 90)', background: 'oklch(1 0 0 / .14)', borderRadius: 4, padding: '2px 5px' } }, kbd),
      h('span', { style: { position: 'absolute', width: 0, height: 0, border: 'solid transparent', ...arrow } })),
    h('style', null, '@keyframes ds-tt-in{from{opacity:0;transform:' + (pos.transform || '') + ' scale(.96)}to{opacity:1}}'));
}
