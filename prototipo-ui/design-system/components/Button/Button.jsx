/**
 * Button — primary action button (DS v4). Pure, dependency-free.
 * variant: 'primary' | 'ghost' | 'danger'  ·  size: 'sm' | 'default' | 'lg'
 * icon (square, for icon-only) · kbd hint · disabled · type · onClick
 */
export function Button({ children, variant = 'ghost', size = 'default', icon = false, kbd, disabled = false, type = 'button', onClick, style }) {
  const H = { sm: 26, default: 30, lg: 36 }[size] || 30;
  const PAD = icon ? 0 : ({ sm: '0 10px', default: '0 12px', lg: '0 16px' }[size] || '0 12px');
  const FS = { sm: 12, default: 12.5, lg: 13 }[size] || 12.5;
  const V = {
    primary: { bg: 'var(--accent)', fg: 'var(--accent-fg)', bd: 'transparent', weight: 600, hbg: 'var(--accent-2)' },
    ghost:   { bg: 'var(--surface)', fg: 'var(--text-dim)', bd: 'var(--border)', weight: 500, hbg: 'var(--bg-2)' },
    danger:  { bg: 'var(--surface)', fg: 'var(--color-destructive-fg)', bd: 'color-mix(in oklch, var(--color-destructive-fg) 30%, transparent)', weight: 500, hbg: 'var(--color-destructive-soft)' },
  }[variant] || {};
  return (
    <button type={type} disabled={disabled} onClick={onClick}
      onMouseEnter={(e) => { if (!disabled) { e.currentTarget.style.background = V.hbg; if (variant === 'ghost') { e.currentTarget.style.color = 'var(--text)'; e.currentTarget.style.borderColor = 'var(--text-mute)'; } } }}
      onMouseLeave={(e) => { if (!disabled) { e.currentTarget.style.background = V.bg; if (variant === 'ghost') { e.currentTarget.style.color = V.fg; e.currentTarget.style.borderColor = V.bd; } } }}
      style={{
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 6,
        height: H, width: icon ? H : undefined, padding: PAD,
        border: '1px solid ' + V.bd, borderRadius: 'var(--radius-md, 6px)',
        background: V.bg, color: V.fg, font: V.weight + ' ' + FS + 'px/1 var(--font-sans)',
        cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.5 : 1,
        transition: 'background .15s, color .15s, border-color .15s',
        ...style,
      }}>
      {children}
      {kbd && <kbd style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, padding: '1px 5px', marginLeft: 4, background: 'color-mix(in oklch, currentColor 14%, transparent)', borderRadius: 3 }}>{kbd}</kbd>}
    </button>
  );
}
