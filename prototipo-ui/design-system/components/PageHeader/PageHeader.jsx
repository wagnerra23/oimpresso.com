/**
 * PageHeader — flat index/page header (DS v4 canon, slot 1 of PT-01).
 * border-b warm · title 22/700 · tabular subtitle with toned stats · action slot.
 * Pure, dependency-free (global React + inline token styles).
 *
 * stats: array of { value, label?, tone? } rendered as "N abertas · N atrasadas …"
 *        tone: 'danger' | 'warn' | undefined (neutral). Or pass `subtitle` (node).
 * actions: React node (buttons) pinned right.
 *
 * Absorve o antigo `cli-pagehead`: `leading` (marca de identidade antes do título —
 * dot de área, ícone, avatar), `context` (linha de contexto acima do título) e
 * `freshness` (pílula de frescor à direita do título; string usa StatusBadge
 * kind="frescor", nó React é renderizado como veio).
 *
 * `leading` vive DENTRO do h1, na linha de base do título — espelha o slot homônimo
 * do header canon do repo (`Components/PageHeader`, opt-in 2026-08-08). Não é caixa:
 * a caixa 40×40 `bg-primary/10` é do `shared/PageHeader.tsx`, que está CONGELADO.
 */
export function PageHeader({ title, stats, subtitle, actions, leading, context, freshness, freshnessRel }) {
  const NS = (typeof window !== 'undefined' && window.OfficeImpressoPontoWR2DesignSystem_019dd0) || {};
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
        {context && (
          <p style={{
            margin: '0 0 3px', font: '500 11px/1.2 var(--font-mono)', letterSpacing: '.04em',
            textTransform: 'uppercase', color: 'var(--text-dim)',
            whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
          }}>{context}</p>
        )}
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, minWidth: 0 }}>
          <h1 style={{
            margin: 0, font: '600 22px/1.3 var(--font-sans)', letterSpacing: '-.015em',
            color: 'var(--text)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
          }}>
            {leading && (
              <span aria-hidden style={{
                display: 'inline-flex', alignItems: 'center', verticalAlign: 'baseline',
                marginRight: 8, color: 'var(--accent)',
              }}>{leading}</span>
            )}
            {title}
          </h1>
          {freshness && (
            <span style={{ flex: '0 0 auto' }}>
              {typeof freshness === 'string'
                ? (NS.StatusBadge ? React.createElement(NS.StatusBadge, { kind: 'frescor', value: freshness, rel: freshnessRel }) : null)
                : freshness}
            </span>
          )}
        </div>
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
