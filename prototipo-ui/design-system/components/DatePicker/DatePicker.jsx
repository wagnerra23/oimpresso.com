/**
 * DatePicker — campo de data com calendário PT-BR. Trigger estilo Input
 * (dd/mm/aaaa) + popover com grade do mês, navegação, hoje destacado, dia
 * selecionado em accent. Fecha no esc / clique-fora / seleção. min/max opcionais.
 * Pure, dependency-free (global React + inline tokens).
 *
 * value (Date|ISO|null) · onChange(Date) · label? · placeholder? · min? · max? · disabled?
 */
const DP_MONTHS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const DP_DOW = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
const dpParse = (v) => { if (!v) return null; const d = v instanceof Date ? v : new Date(v); return isNaN(d) ? null : d; };
const dpFmt = (d) => d ? String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear() : '';
const dpSame = (a, b) => a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
const dpKey = (d) => d.getFullYear() * 10000 + d.getMonth() * 100 + d.getDate();

export function DatePicker({ value, onChange, label, placeholder = 'dd/mm/aaaa', min, max, disabled }) {
  const h = React.createElement;
  const sel = dpParse(value);
  const [open, setOpen] = React.useState(false);
  const [view, setView] = React.useState(() => { const d = sel || new Date(); return { y: d.getFullYear(), m: d.getMonth() }; });
  const wrap = React.useRef(null);
  const today = new Date();
  const lo = dpParse(min), hi = dpParse(max);

  React.useEffect(() => {
    if (!open) return;
    if (sel) setView({ y: sel.getFullYear(), m: sel.getMonth() });
    const onDoc = (e) => { if (wrap.current && !wrap.current.contains(e.target)) setOpen(false); };
    const onEsc = (e) => { if (e.key === 'Escape') setOpen(false); };
    document.addEventListener('mousedown', onDoc); document.addEventListener('keydown', onEsc);
    return () => { document.removeEventListener('mousedown', onDoc); document.removeEventListener('keydown', onEsc); };
  }, [open]);

  const shift = (n) => setView((v) => { const m = v.m + n; return { y: v.y + Math.floor(m / 12), m: ((m % 12) + 12) % 12 }; });
  const first = new Date(view.y, view.m, 1);
  const start = first.getDay();
  const days = new Date(view.y, view.m + 1, 0).getDate();
  const cells = [];
  for (let i = 0; i < start; i++) cells.push(null);
  for (let d = 1; d <= days; d++) cells.push(new Date(view.y, view.m, d));
  const disabledDay = (d) => (lo && dpKey(d) < dpKey(lo)) || (hi && dpKey(d) > dpKey(hi));

  const navBtn = (dir, path) => h('button', { onClick: () => shift(dir), 'aria-label': dir < 0 ? 'Mês anterior' : 'Próximo mês', style: { width: 28, height: 28, border: '1px solid var(--border)', background: 'var(--surface)', borderRadius: 7, cursor: 'pointer', color: 'var(--text-dim)', display: 'grid', placeItems: 'center' } },
    h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2.2, strokeLinecap: 'round', strokeLinejoin: 'round' }, h('path', { d: path })));

  return h('div', { ref: wrap, style: { position: 'relative', display: 'flex', flexDirection: 'column', gap: 6, font: 'var(--font-sans)' } },
    label && h('label', { style: { fontSize: 10.5, fontWeight: 600, letterSpacing: '.06em', textTransform: 'uppercase', color: 'var(--text-mute)' } }, label),
    h('button', {
      onClick: () => !disabled && setOpen((o) => !o), disabled, 'aria-haspopup': 'dialog', 'aria-expanded': open,
      style: {
        display: 'flex', alignItems: 'center', gap: 9, height: 36, padding: '0 11px', width: '100%', cursor: disabled ? 'default' : 'pointer',
        background: 'var(--surface)', border: '1px solid ' + (open ? 'var(--accent)' : 'var(--border)'), borderRadius: 'var(--radius, 8px)',
        boxShadow: open ? '0 0 0 3px var(--accent-soft)' : 'var(--shadow-soft)', font: 'inherit', textAlign: 'left', opacity: disabled ? .6 : 1,
      },
    },
      h('svg', { width: 15, height: 15, viewBox: '0 0 24 24', fill: 'none', stroke: 'var(--text-mute)', strokeWidth: 2, strokeLinecap: 'round' }, h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2 }), h('path', { d: 'M16 2v4M8 2v4M3 10h18' })),
      h('span', { style: { flex: 1, fontSize: 13.5, color: sel ? 'var(--text)' : 'var(--text-mute)', fontVariantNumeric: 'tabular-nums' } }, sel ? dpFmt(sel) : placeholder)),

    open && h('div', { role: 'dialog', 'aria-label': 'Escolher data', style: {
      position: 'absolute', top: '100%', left: 0, marginTop: 6, zIndex: 70, width: 268, padding: 12,
      background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 12, boxShadow: 'var(--shadow-pop)', animation: 'ds-dp-in .12s ease both',
    } },
      h('div', { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 10 } },
        navBtn(-1, 'm15 18-6-6 6-6'),
        h('div', { style: { fontSize: 13, fontWeight: 600, color: 'var(--text)', textTransform: 'capitalize' } }, DP_MONTHS[view.m] + ' ' + view.y),
        navBtn(1, 'm9 18 6-6-6-6')),
      h('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 2, marginBottom: 4 } },
        DP_DOW.map((d, i) => h('div', { key: i, style: { textAlign: 'center', font: '600 10px/1 var(--font-sans)', color: 'var(--text-mute)', padding: '4px 0' } }, d))),
      h('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 2 } },
        cells.map((d, i) => {
          if (!d) return h('div', { key: 'e' + i });
          const isSel = dpSame(d, sel), isToday = dpSame(d, today), off = disabledDay(d);
          return h('button', {
            key: dpKey(d), disabled: off, onClick: () => { if (!off && onChange) onChange(d); setOpen(false); },
            style: {
              height: 32, border: 0, borderRadius: 7, cursor: off ? 'default' : 'pointer', position: 'relative',
              font: (isSel ? '600' : '500') + ' 12.5px/1 var(--font-mono)', fontVariantNumeric: 'tabular-nums',
              background: isSel ? 'var(--accent)' : 'transparent',
              color: off ? 'var(--text-mute)' : isSel ? 'var(--accent-fg)' : isToday ? 'var(--accent)' : 'var(--text)',
              opacity: off ? .4 : 1,
            },
          }, d.getDate(),
            isToday && !isSel && h('span', { style: { position: 'absolute', bottom: 4, left: '50%', transform: 'translateX(-50%)', width: 3, height: 3, borderRadius: 999, background: 'var(--accent)' } }));
        })),
      h('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 10, paddingTop: 9, borderTop: '1px solid var(--border)' } },
        h('button', { onClick: () => { const t = new Date(); if (onChange) onChange(t); setOpen(false); }, style: { border: 0, background: 'transparent', cursor: 'pointer', font: '600 12px/1 var(--font-sans)', color: 'var(--accent)' } }, 'Hoje'),
        sel && h('button', { onClick: () => { if (onChange) onChange(null); setOpen(false); }, style: { border: 0, background: 'transparent', cursor: 'pointer', font: '500 12px/1 var(--font-sans)', color: 'var(--text-mute)' } }, 'Limpar')),
      h('style', null, '@keyframes ds-dp-in{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}')));
}
