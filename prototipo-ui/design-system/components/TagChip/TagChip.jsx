/**
 * TagChip — small lowercase category chip (CRM/Clientes).
 * Pure, dependency-free (global React + inline token styles).
 *
 * A fixed semantic palette keyed by category. Unknown tags fall back to neutral.
 * Pass `removable` + `onRemove` to render an ✕ (filter-style).
 */
const TAG_HUE = {
  varejo: 70, atacado: 300, corporativo: 255, evento: 350, parceiro: 155,
  agencia: 275, governo: 25, vip: 95, reincidente: 50,
};

export function TagChip({ label, removable = false, onRemove }) {
  const key = (label || '').toString().toLowerCase();
  const hue = TAG_HUE[key];
  const known = hue != null;
  const bg = known ? `oklch(0.30 0.05 ${hue})` : 'var(--color-secondary)';
  const fg = known ? `oklch(0.84 0.12 ${hue})` : 'var(--color-secondary-foreground)';
  const bd = known ? `oklch(0.40 0.07 ${hue})` : 'var(--color-border)';
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 5,
      padding: removable ? '1px 5px 1px 8px' : '1px 8px',
      borderRadius: 9999, border: '1px solid ' + bd,
      background: bg, color: fg,
      fontSize: 'var(--fs-1)', fontWeight: 500, lineHeight: 1.6,
      textTransform: 'lowercase', letterSpacing: '-.01em',
    }}>
      {label}
      {removable && (
        <button type="button" onClick={onRemove} aria-label={'Remover ' + label}
          style={{
            border: 0, background: 'transparent', color: 'currentColor', cursor: 'pointer',
            padding: 0, margin: 0, width: 14, height: 14, lineHeight: 1,
            display: 'grid', placeItems: 'center', opacity: 0.7, fontSize: 12,
          }}>×</button>
      )}
    </span>
  );
}
