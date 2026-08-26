/**
 * PageHeader — flat index/page header (DS v4 canon, slot 1 of PT-01).
 * border-b warm · title 22/700 · tabular subtitle with toned stats · action slot.
 * Pure, dependency-free (global React + inline token styles). No icon box.
 *
 * stats: array of { value, label?, tone? } rendered as "N abertas · N atrasadas …"
 *        tone: 'danger' | 'warn' | undefined (neutral). Or pass `subtitle` (node).
 * actions: React node (buttons) pinned right.
 */
export function PageHeader({ title, stats, subtitle, actions }) {
  const toneColor = (t) =>
    t === 'danger' ? 'var(--color-destructive)'
    : t === 'warn' ? 'var(--color-warning)'
    : 'var(--text)';
  return (
    <header style={{
      display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12,
      padding: '14px 0', borderBottom: '1px solid var(--border)', background: 'var(--bg)',
    }}>
      <div style={{ minWidth: 0, flex: '1 1 auto' }}>
        <h1 style={{
          margin: 0, font: '600 22px/1.3 var(--font-sans)', letterSpacing: '-.015em',
          color: 'var(--text)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
        }}>{title}</h1>
        {(stats || subtitle) && (
          <p style={{
            margin: '4px 0 0', font: '400 13px/1.45 var(--font-sans)',
            color: 'var(--text-dim)', fontVariantNumeric: 'tabular-nums',
            whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: '56ch',
          }}>
            {subtitle}
            {stats && stats.map((s, i) => (
              <React.Fragment key={i}>
                {i > 0 && ' · '}
                <strong style={{ color: toneColor(s.tone), fontWeight: 600 }}>{s.value}</strong>
                {s.label ? ' ' + s.label : ''}
              </React.Fragment>
            ))}
          </p>
        )}
      </div>
      {actions && <div style={{ display: 'flex', alignItems: 'center', gap: 8, flex: '0 0 auto' }}>{actions}</div>}
    </header>
  );
}
