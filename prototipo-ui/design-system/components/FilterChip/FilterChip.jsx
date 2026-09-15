/**
 * FilterChip — active, removable filter (DS v4). accent-soft pill with label,
 * optional value, and an ✕. Pure, dependency-free (global React + inline tokens).
 */
export function FilterChip({ label, value, onRemove }) {
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 6,
      height: 24, padding: onRemove ? '0 4px 0 10px' : '0 10px',
      borderRadius: 99, fontSize: 11.5,
      background: 'color-mix(in oklch, var(--accent) 12%, var(--surface))', color: 'var(--accent)',
      border: '1px solid color-mix(in oklch, var(--accent) 32%, transparent)',
    }}>
      <span style={{ fontWeight: 600 }}>{label}</span>
      {value != null && (
        <span style={{ fontWeight: 400, color: 'color-mix(in oklch, var(--accent) 80%, var(--text))' }}>{value}</span>
      )}
      {onRemove && (
        <button type="button" onClick={onRemove} aria-label={'Remover filtro ' + label}
          onMouseEnter={(e) => { e.currentTarget.style.background = 'color-mix(in oklch, var(--accent) 18%, transparent)'; e.currentTarget.style.opacity = '1'; }}
          onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; e.currentTarget.style.opacity = '0.7'; }}
          style={{
            display: 'grid', placeItems: 'center', width: 18, height: 18, marginLeft: 1,
            border: 0, borderRadius: '50%', background: 'transparent', color: 'currentColor',
            cursor: 'pointer', opacity: 0.7, fontSize: 12, lineHeight: 1,
          }}>×</button>
      )}
    </span>
  );
}
