/**
 * Command — the ⌘K command palette (DS premium). Scrim + centered panel with a
 * live filter, grouped results, full keyboard nav (↑/↓ to move, ↵ to run, esc to
 * close) and focus management. Pure, dependency-free (global React + inline tokens).
 *
 * open · onClose() · placeholder
 * groups: [{ label, items: [{ id, label, hint?, kbd?, icon?(node), onSelect?() }] }]
 */
export function Command({ open, onClose, placeholder = 'Buscar ou executar…', groups = [] }) {
  const h = React.createElement;
  const [q, setQ] = React.useState('');
  const [active, setActive] = React.useState(0);
  const inputRef = React.useRef(null);
  const prevFocus = React.useRef(null);

  React.useEffect(() => {
    if (open) {
      prevFocus.current = document.activeElement;
      setQ(''); setActive(0);
      const t = setTimeout(() => { if (inputRef.current) inputRef.current.focus(); }, 10);
      return () => clearTimeout(t);
    } else if (prevFocus.current && prevFocus.current.focus) {
      prevFocus.current.focus();
    }
  }, [open]);

  if (!open) return null;

  const ql = q.trim().toLowerCase();
  const fgroups = groups
    .map((g) => ({ label: g.label, items: (g.items || []).filter((it) => !ql || (it.label + ' ' + (it.hint || '')).toLowerCase().includes(ql)) }))
    .filter((g) => g.items.length);
  const flat = [];
  fgroups.forEach((g) => g.items.forEach((it) => flat.push(it)));
  const cur = Math.max(0, Math.min(active, flat.length - 1));

  const run = (it) => { if (it) { if (it.onSelect) it.onSelect(); onClose(); } };
  const onKey = (e) => {
    if (e.key === 'Escape') { e.preventDefault(); onClose(); }
    else if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => Math.min((a < 0 ? -1 : a) + 1, flat.length - 1)); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(a - 1, 0)); }
    else if (e.key === 'Enter') { e.preventDefault(); run(flat[cur]); }
  };

  let idx = -1;
  const rowEls = fgroups.map((g, gi) => h('div', { key: 'g' + gi, style: { marginTop: gi ? 6 : 0 } },
    h('div', { style: { font: '600 9.5px/1 var(--font-sans)', letterSpacing: '.08em', textTransform: 'uppercase', color: 'var(--text-mute)', padding: '8px 14px 6px' } }, g.label),
    g.items.map((it) => {
      idx++; const on = idx === cur; const myIdx = idx;
      return h('div', {
        key: it.id,
        onMouseEnter: () => setActive(myIdx),
        onClick: () => run(it),
        style: {
          display: 'flex', alignItems: 'center', gap: 11, margin: '0 6px', padding: '8px 9px', borderRadius: 9, cursor: 'pointer',
          background: on ? 'var(--accent-soft)' : 'transparent', boxShadow: on ? 'inset 3px 0 0 var(--accent)' : 'none',
        },
      },
        h('span', { style: { width: 26, height: 26, flex: 'none', borderRadius: 7, display: 'grid', placeItems: 'center', background: on ? 'var(--accent)' : 'var(--bg-2)', color: on ? '#fff' : 'var(--text-mute)' } }, it.icon || null),
        h('span', { style: { flex: 1, minWidth: 0, fontSize: 13, fontWeight: on ? 600 : 500, color: on ? 'var(--accent)' : 'var(--text)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, it.label),
        it.hint && h('span', { style: { fontSize: 11.5, color: 'var(--text-mute)', whiteSpace: 'nowrap' } }, it.hint),
        it.kbd && h('kbd', { style: { font: '600 10px/1 var(--font-mono)', color: 'var(--text-mute)', background: 'var(--bg-2)', border: '1px solid var(--border)', borderRadius: 4, padding: '2px 5px' } }, it.kbd));
    })));

  return h('div', { onKeyDown: onKey, style: { position: 'fixed', inset: 0, zIndex: 90, display: 'flex', alignItems: 'flex-start', justifyContent: 'center', padding: '12vh 20px 20px' } },
    h('div', { onClick: onClose, 'aria-hidden': true, style: { position: 'absolute', inset: 0, background: 'oklch(0.12 0 0 / 0.5)', backdropFilter: 'blur(2px)' } }),
    h('div', { role: 'dialog', 'aria-modal': true, 'aria-label': 'Paleta de comandos', style: {
      position: 'relative', width: '100%', maxWidth: 580, maxHeight: '64vh', display: 'flex', flexDirection: 'column',
      background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 14, overflow: 'hidden',
      boxShadow: '0 28px 70px -18px rgba(0,0,0,.6), 0 0 0 1px var(--border)',
    } },
      h('div', { style: { display: 'flex', alignItems: 'center', gap: 11, padding: '14px 16px', borderBottom: '1px solid var(--border)' } },
        h('svg', { width: 17, height: 17, viewBox: '0 0 24 24', fill: 'none', stroke: 'var(--text-mute)', strokeWidth: 2 }, h('circle', { cx: 11, cy: 11, r: 8 }), h('path', { d: 'm21 21-4.3-4.3' })),
        h('input', { ref: inputRef, value: q, onChange: (e) => { setQ(e.target.value); setActive(0); }, placeholder, style: { flex: 1, border: 0, outline: 0, background: 'transparent', font: 'inherit', fontSize: 15, color: 'var(--text)' } }),
        h('kbd', { style: { font: '600 10px/1 var(--font-mono)', color: 'var(--text-mute)', background: 'var(--bg-2)', border: '1px solid var(--border)', borderRadius: 4, padding: '3px 6px' } }, 'esc')),
      h('div', { style: { flex: 1, overflowY: 'auto', padding: '8px 0' } },
        flat.length ? rowEls : h('div', { style: { padding: '34px 16px', textAlign: 'center', color: 'var(--text-mute)', fontSize: 13 } }, 'Nada encontrado para "' + q + '"')),
      h('div', { style: { display: 'flex', alignItems: 'center', gap: 14, padding: '9px 16px', borderTop: '1px solid var(--border)', background: 'var(--bg-2)', font: '11px/1 var(--font-sans)', color: 'var(--text-mute)' } },
        h('span', null, h('kbd', { style: kbF }, '↑'), h('kbd', { style: kbF }, '↓'), ' navegar'),
        h('span', null, h('kbd', { style: kbF }, '↵'), ' abrir'),
        h('span', { style: { marginLeft: 'auto' } }, flat.length + ' resultados'))));
}

const kbF = { font: '600 10px/1 var(--font-mono)', color: 'var(--text-mute)', background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 4, padding: '2px 5px', marginRight: 3 };
