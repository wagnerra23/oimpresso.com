/**
 * DropdownMenu — menu de ações ancorado a um gatilho. Abre clicando, fecha no
 * esc / clique-fora / seleção. Navegação por teclado (↑/↓ · ↵), itens com ícone,
 * atalho, tom danger e separadores. Pure, dependency-free (global React + tokens).
 *
 * trigger (node — recebe { open, ref, onClick }) · align: start|end · width?
 * items: [{ id, label, icon?, kbd?, tone?: 'danger', disabled?, separator?, onSelect?() }]
 */
export function DropdownMenu({ trigger, items = [], align = 'start', width = 220 }) {
  const h = React.createElement;
  const [open, setOpen] = React.useState(false);
  const [active, setActive] = React.useState(-1);
  const wrap = React.useRef(null);
  const actionable = items.filter((it) => !it.separator && !it.disabled);

  React.useEffect(() => {
    if (!open) { setActive(-1); return; }
    const onDoc = (e) => { if (wrap.current && !wrap.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, [open]);

  const run = (it) => { if (it && !it.disabled && !it.separator) { if (it.onSelect) it.onSelect(); setOpen(false); } };
  const onKey = (e) => {
    if (e.key === 'Escape') { setOpen(false); }
    else if (e.key === 'ArrowDown') { e.preventDefault(); if (!open) { setOpen(true); return; } setActive((a) => Math.min(a + 1, actionable.length - 1)); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(a - 1, 0)); }
    else if (e.key === 'Enter' && open && active >= 0) { e.preventDefault(); run(actionable[active]); }
  };

  const trig = typeof trigger === 'function'
    ? trigger({ open, onClick: () => setOpen((o) => !o) })
    : h('button', { onClick: () => setOpen((o) => !o), 'aria-haspopup': 'menu', 'aria-expanded': open, style: {
        display: 'inline-flex', alignItems: 'center', gap: 6, height: 32, padding: '0 11px', cursor: 'pointer',
        background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 'var(--radius-sm, 6px)',
        font: '500 13px/1 var(--font-sans)', color: 'var(--text)', boxShadow: 'var(--shadow-soft)' } },
        trigger || 'Ações', h('svg', { width: 13, height: 13, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, style: { opacity: .6 } }, h('path', { d: 'm6 9 6 6 6-6' })));

  let ai = -1;
  return h('div', { ref: wrap, onKeyDown: onKey, style: { position: 'relative', display: 'inline-flex' } },
    trig,
    open && h('div', { role: 'menu', style: {
      position: 'absolute', top: '100%', [align === 'end' ? 'right' : 'left']: 0, marginTop: 6, zIndex: 70, width,
      background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 10, padding: 5,
      boxShadow: 'var(--shadow-pop)', animation: 'ds-dd-in .12s ease both',
    } },
      items.map((it, i) => {
        if (it.separator) return h('div', { key: 'sep' + i, role: 'separator', style: { height: 1, background: 'var(--border)', margin: '5px 6px' } });
        ai++; const my = ai; const on = my === active; const danger = it.tone === 'danger';
        return h('button', {
          key: it.id, role: 'menuitem', disabled: it.disabled,
          onMouseEnter: () => setActive(my), onClick: () => run(it),
          style: {
            display: 'flex', alignItems: 'center', gap: 10, width: '100%', padding: '7px 8px', border: 0, borderRadius: 7,
            cursor: it.disabled ? 'default' : 'pointer', textAlign: 'left', font: '500 13px/1 var(--font-sans)',
            background: on && !it.disabled ? (danger ? 'var(--neg-soft)' : 'var(--accent-soft)') : 'transparent',
            color: it.disabled ? 'var(--text-mute)' : danger ? 'var(--neg)' : on ? 'var(--accent)' : 'var(--text)',
            opacity: it.disabled ? .55 : 1,
          },
        },
          it.icon && h('span', { style: { flex: 'none', width: 16, height: 16, display: 'grid', placeItems: 'center', opacity: .85 } }, it.icon),
          h('span', { style: { flex: 1, minWidth: 0, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, it.label),
          it.kbd && h('kbd', { style: { font: '600 10px/1 var(--font-mono)', color: 'var(--text-mute)', background: 'var(--bg-2)', border: '1px solid var(--border)', borderRadius: 4, padding: '2px 5px' } }, it.kbd));
      }),
      h('style', null, '@keyframes ds-dd-in{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}')));
}
