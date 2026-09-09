/**
 * Progress — indicador de progresso determinístico. variant "bar" (linha) ou
 * "ring" (anel SVG). Tom semântico, valor opcional, animação suave de largura.
 * Para fechamento de mês, upload, banco de horas. Pure, dependency-free.
 *
 * value (0..max) · max=100 · tone: accent|success|warn|danger · variant: bar|ring
 * label? · showValue? · size? (ring px / bar height) · formatValue?(v)
 */
const PROG_TONES = {
  accent: 'var(--accent)', success: 'var(--pos)', warn: 'var(--warn)', danger: 'var(--neg)',
};

export function Progress({ value = 0, max = 100, tone = 'accent', variant = 'bar', label, showValue = false, size, formatValue }) {
  const h = React.createElement;
  const c = PROG_TONES[tone] || PROG_TONES.accent;
  const pct = Math.max(0, Math.min(1, max ? value / max : 0));
  const txt = formatValue ? formatValue(value) : Math.round(pct * 100) + '%';

  if (variant === 'ring') {
    const d = size || 56, sw = Math.max(4, Math.round(d * 0.1)), r = (d - sw) / 2, C = 2 * Math.PI * r;
    return h('div', { style: { display: 'inline-grid', placeItems: 'center', position: 'relative', width: d, height: d } },
      h('svg', { width: d, height: d, viewBox: '0 0 ' + d + ' ' + d, style: { transform: 'rotate(-90deg)' }, role: 'progressbar', 'aria-valuenow': Math.round(pct * 100) },
        h('circle', { cx: d / 2, cy: d / 2, r, fill: 'none', stroke: 'var(--border)', strokeWidth: sw }),
        h('circle', { cx: d / 2, cy: d / 2, r, fill: 'none', stroke: c, strokeWidth: sw, strokeLinecap: 'round', strokeDasharray: C, strokeDashoffset: C * (1 - pct), style: { transition: 'stroke-dashoffset .5s cubic-bezier(.4,0,.2,1)' } })),
      (showValue || label) && h('div', { style: { position: 'absolute', textAlign: 'center', lineHeight: 1 } },
        showValue && h('div', { style: { font: '700 ' + Math.round(d * 0.24) + 'px/1 var(--font-mono)', color: 'var(--text)', fontVariantNumeric: 'tabular-nums' } }, txt),
        label && h('div', { style: { fontSize: Math.round(d * 0.13), color: 'var(--text-mute)', marginTop: 2 } }, label)));
  }

  const hgt = size || 7;
  return h('div', { style: { display: 'flex', flexDirection: 'column', gap: 6, width: '100%' } },
    (label || showValue) && h('div', { style: { display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', gap: 12 } },
      label && h('span', { style: { fontSize: 12, fontWeight: 500, color: 'var(--text-dim)' } }, label),
      showValue && h('span', { style: { font: '600 12px/1 var(--font-mono)', color: 'var(--text)', fontVariantNumeric: 'tabular-nums' } }, txt)),
    h('div', { role: 'progressbar', 'aria-valuenow': Math.round(pct * 100), style: { height: hgt, borderRadius: 999, background: 'var(--bg-2)', border: '1px solid var(--border)', overflow: 'hidden' } },
      h('div', { style: { height: '100%', width: (pct * 100) + '%', background: c, borderRadius: 999, transition: 'width .5s cubic-bezier(.4,0,.2,1)' } })));
}
