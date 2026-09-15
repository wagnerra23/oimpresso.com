/**
 * Kebab — gatilho "⋮" do DropdownMenu (fusão 2026-08, ver NOTAS_INTERNAS.md).
 * Não reimplementa menu: desenha só o botão icônico e delega itens, teclado,
 * clique-fora e ancoragem ao DropdownMenu do DS.
 *
 * items · align 'start'|'end' · width · size 'sm'|'md' · orientation · label · disabled
 */
export function Kebab({ items = [], align = 'end', width = 200, size = 'md', label = 'Mais ações', orientation = 'vertical', disabled = false }) {
  const NS = (typeof window !== 'undefined' && window.OfficeImpressoPontoWR2DesignSystem_019dd0) || {};
  const Menu = NS.DropdownMenu;
  const box = size === 'sm' ? 24 : 28;
  const dots = orientation === 'horizontal' ? [[5, 12], [12, 12], [19, 12]] : [[12, 5], [12, 12], [12, 19]];

  const trigger = ({ open, onClick }) => React.createElement('button', {
    type: 'button', 'aria-haspopup': 'menu', 'aria-expanded': open, 'aria-label': label, title: label, disabled,
    onClick: (e) => { e.stopPropagation(); onClick(); },
    onMouseEnter: (e) => { if (!disabled && !open) e.currentTarget.style.background = 'var(--bg-2)'; },
    onMouseLeave: (e) => { if (!open) e.currentTarget.style.background = 'transparent'; },
    style: {
      width: box, height: box, display: 'inline-grid', placeItems: 'center', border: '1px solid transparent',
      borderRadius: 6, background: open ? 'var(--accent-soft)' : 'transparent',
      color: disabled ? 'var(--text-mute)' : open ? 'var(--accent)' : 'var(--text-dim)',
      cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.5 : 1, transition: 'background .15s, color .15s',
    },
  }, React.createElement('svg', { width: 15, height: 15, viewBox: '0 0 24 24', fill: 'currentColor', 'aria-hidden': true },
    dots.map((d, i) => React.createElement('circle', { key: i, cx: d[0], cy: d[1], r: 1.7 }))));

  if (!Menu) return trigger({ open: false, onClick: () => {} });
  return React.createElement(Menu, { trigger, items, align, width });
}
