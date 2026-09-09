/**
 * Alert — banner inline para avisos no fluxo da página (fiscal, LGPD,
 * intercorrência, sucesso). Fundo tintado 6% + borda 22% no tom semântico —
 * nunca pastel sólido. Ícone à esquerda, título + descrição, ação e fechar
 * opcionais. Pure, dependency-free (global React + inline tokens).
 *
 * tone: info|success|warn|danger · title · children(desc) · icon? · action? · onClose?
 */
const ALERT_TONES = {
  info:    { c: 'var(--accent)', s: 'var(--accent-soft)', d: 'M12 16v-4M12 8h.01M12 3a9 9 0 100 18 9 9 0 000-18z' },
  success: { c: 'var(--pos)', s: 'var(--pos-soft)', d: 'M20 6L9 17l-5-5' },
  warn:    { c: 'var(--warn)', s: 'var(--warn-soft)', d: 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L14.4 3.9a2 2 0 00-3.4 0z' },
  danger:  { c: 'var(--neg)', s: 'var(--neg-soft)', d: 'M12 8v5M12 16h.01M12 3a9 9 0 100 18 9 9 0 000-18z' },
};

export function Alert({ tone = 'info', title, children, icon, action, onClose }) {
  const h = React.createElement;
  const tk = ALERT_TONES[tone] || ALERT_TONES.info;
  const mix = (a) => 'color-mix(in oklab, ' + tk.c + ' ' + a + ', transparent)';

  return h('div', {
    role: tone === 'danger' ? 'alert' : 'status',
    style: {
      display: 'flex', alignItems: 'flex-start', gap: 11, padding: '12px 14px',
      background: mix('6%'), border: '1px solid ' + mix('22%'), borderRadius: 'var(--radius, 8px)',
      font: 'var(--font-sans)', color: 'var(--text)',
    },
  },
    h('span', { style: { flex: 'none', width: 22, height: 22, borderRadius: 6, display: 'grid', placeItems: 'center', background: mix('14%'), color: tk.c, marginTop: 1 } },
      icon || h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' }, h('path', { d: tk.d }))),
    h('div', { style: { flex: 1, minWidth: 0, paddingTop: 1 } },
      title && h('div', { style: { fontSize: 13, fontWeight: 600, color: 'var(--text)', lineHeight: 1.3 } }, title),
      children != null && h('div', { style: { fontSize: 12.5, color: 'var(--text-dim)', lineHeight: 1.45, marginTop: title ? 2 : 0 } }, children),
      action && h('div', { style: { marginTop: 9 } }, action)),
    onClose && h('button', {
      onClick: onClose, 'aria-label': 'Fechar',
      style: { flex: 'none', width: 22, height: 22, marginTop: 1, border: 0, background: 'transparent', cursor: 'pointer', color: 'var(--text-mute)', borderRadius: 5, display: 'grid', placeItems: 'center' },
    }, h('svg', { width: 13, height: 13, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2.2, strokeLinecap: 'round' }, h('path', { d: 'M18 6 6 18M6 6l12 12' }))));
}
