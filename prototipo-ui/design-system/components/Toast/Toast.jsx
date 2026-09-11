/**
 * Toast — fleeting confirmation (DS v4). Dark pill by default; tone recolors it.
 * Pure, dependency-free (global React + inline token styles).
 * tone: 'default' | 'ok' | 'warn' | 'danger'  ·  optional `kbd` hint, `icon`.
 */
export function Toast({ children, tone = 'default', icon, kbd }) {
  const bg = tone === 'ok' ? 'var(--color-success)'
    : tone === 'warn' ? 'var(--color-warning)'
    : tone === 'danger' ? 'var(--color-destructive)'
    : 'oklch(0.21 0 0)';
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 10,
      padding: '10px 14px', background: bg, color: 'oklch(0.97 0 0)',
      borderRadius: 'var(--radius-md, 6px)', fontSize: 12.5, fontWeight: 500,
      boxShadow: '0 8px 24px -6px rgba(0,0,0,.35)',
    }}>
      {icon && <span style={{ display: 'inline-flex', flexShrink: 0 }} aria-hidden>{icon}</span>}
      <span>{children}</span>
      {kbd && <kbd style={{
        fontFamily: 'var(--font-mono)', fontSize: 10.5, padding: '1px 5px',
        background: 'color-mix(in oklch, white 18%, transparent)', borderRadius: 3,
      }}>{kbd}</kbd>}
    </span>
  );
}
