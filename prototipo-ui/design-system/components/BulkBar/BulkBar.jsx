/**
 * BulkBar — floating action bar shown when N rows are selected (DS v4).
 * Sticky dark pill, bottom-center. Pure, dependency-free.
 *
 * count: number selected · actions: [{ label, icon?, tone?: 'danger', onClick }]
 * onClose: clears selection.
 */
export function BulkBar({ count = 0, label = 'selecionadas', actions = [], onClose }) {
  return (
    <div style={{
      position: 'sticky', bottom: 16, margin: '0 auto', width: 'fit-content',
      display: 'flex', alignItems: 'center', gap: 14, padding: '8px 10px 8px 16px',
      background: 'oklch(0.21 0 0)', color: 'oklch(0.96 0 0)', borderRadius: 99,
      boxShadow: '0 20px 40px -12px rgba(0,0,0,.45)',
    }}>
      <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8, font: '600 12.5px/1 var(--font-sans)' }}>
        <span style={{
          display: 'inline-grid', placeItems: 'center', minWidth: 22, height: 22, padding: '0 6px',
          background: 'var(--accent)', color: '#fff', borderRadius: 99, font: '700 11.5px/1 var(--font-mono)',
        }}>{count}</span>
        {label}
      </span>
      <span style={{ width: 1, height: 20, background: 'oklch(0.35 0 0)' }} aria-hidden />
      {actions.map((a, i) => (
        <button key={i} type="button" onClick={a.onClick}
          onMouseEnter={(e) => { e.currentTarget.style.background = a.tone === 'danger' ? 'var(--color-destructive)' : 'oklch(0.30 0 0)'; }}
          onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; }}
          style={{
            display: 'inline-flex', alignItems: 'center', gap: 6, padding: '6px 10px',
            borderRadius: 'var(--radius-md, 6px)', border: 0, background: 'transparent',
            color: 'oklch(0.96 0 0)', font: '500 12.5px/1 var(--font-sans)', cursor: 'pointer',
          }}>
          {a.icon && <span style={{ display: 'inline-flex', opacity: 0.8 }} aria-hidden>{a.icon}</span>}
          {a.label}
        </button>
      ))}
      {onClose && (
        <button type="button" onClick={onClose} aria-label="Limpar seleção"
          onMouseEnter={(e) => { e.currentTarget.style.background = 'oklch(0.30 0 0)'; }}
          onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; }}
          style={{ display: 'grid', placeItems: 'center', width: 24, height: 24, border: 0, borderRadius: '50%', background: 'transparent', color: 'oklch(0.7 0 0)', cursor: 'pointer', fontSize: 14 }}>×</button>
      )}
    </div>
  );
}
