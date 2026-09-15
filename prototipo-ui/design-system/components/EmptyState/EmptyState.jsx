/**
 * EmptyState — contextual empty/zero/error state (DS v4).
 * Pure, dependency-free (global React + inline token styles).
 * Always say WHY it's empty and WHAT to do — pass a specific `action`.
 *
 * variant: 'default' | 'first' | 'no-results' | 'no-perm' | 'offline'
 *        | 'done' | 'filtered' | 'error'
 */
export function EmptyState({ variant = 'default', icon, title, description, action }) {
  const V = {
    default:      { border: 'var(--border)', dash: false, bg: 'var(--surface)', ico: ['var(--bg-2)', 'var(--text-mute)'] },
    first:        { border: 'color-mix(in oklch, var(--accent) 30%, var(--border))', dash: false, bg: 'color-mix(in oklch, var(--accent-soft) 30%, var(--surface))', ico: ['var(--accent-soft)', 'var(--accent)'] },
    'no-results': { border: 'var(--border)', dash: true, bg: 'var(--surface)', ico: ['var(--bg-2)', 'var(--text-mute)'] },
    'no-perm':    { border: 'color-mix(in oklch, var(--color-warning) 30%, var(--border))', dash: false, bg: 'color-mix(in oklch, var(--color-warning-soft) 40%, var(--surface))', ico: ['var(--color-warning-soft)', 'var(--color-warning-fg)'] },
    offline:      { border: 'color-mix(in oklch, var(--text-mute) 40%, var(--border))', dash: false, bg: 'var(--bg-2)', ico: ['var(--border)', 'var(--text-mute)'] },
    done:         { border: 'color-mix(in oklch, var(--color-success) 30%, var(--border))', dash: false, bg: 'color-mix(in oklch, var(--color-success-soft) 40%, var(--surface))', ico: ['var(--color-success-soft)', 'var(--color-success-fg)'] },
    filtered:     { border: 'color-mix(in oklch, var(--accent) 25%, var(--border))', dash: false, bg: 'var(--surface)', ico: ['var(--bg-2)', 'var(--text-mute)'] },
    error:        { border: 'color-mix(in oklch, var(--color-destructive) 35%, var(--border))', dash: false, bg: 'color-mix(in oklch, var(--color-destructive-soft) 30%, var(--surface))', ico: ['var(--color-destructive-soft)', 'var(--color-destructive-fg)'] },
  };
  const v = V[variant] || V.default;
  return (
    <div style={{
      textAlign: 'center', padding: '32px 24px',
      border: '1px ' + (v.dash ? 'dashed' : 'solid') + ' ' + v.border,
      borderRadius: 'var(--radius-md, 6px)', background: v.bg,
    }}>
      <span style={{
        width: 40, height: 40, margin: '0 auto 12px', display: 'grid', placeItems: 'center',
        borderRadius: '50%', background: v.ico[0], color: v.ico[1],
      }} aria-hidden>{icon}</span>
      {title && <b style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4, color: 'var(--text)' }}>{title}</b>}
      {description && <small style={{ display: 'block', fontSize: 12, color: 'var(--text-dim)', maxWidth: 320, margin: '0 auto', lineHeight: 1.5 }}>{description}</small>}
      {action && <div style={{ marginTop: 12 }}>{action}</div>}
    </div>
  );
}
