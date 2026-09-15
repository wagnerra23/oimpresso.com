/**
 * Pagination — page navigation with labeled prev/next, numbers, ellipsis, meta (DS v6).
 * Pure, dependency-free (global React + inline token styles).
 *
 * page (1-based) · pageCount · onChange(page)
 * total? + pageSize? → "N–M de T" meta · onPageSize? + pageSizeOptions? → per-page <select>
 * compact? → icon-only prev/next (no "Anterior/Próximo" labels), for tight toolbars.
 */
function pageList(page, count) {
  if (count <= 7) return Array.from({ length: count }, (_, i) => i + 1);
  const out = [1];
  const lo = Math.max(2, page - 1), hi = Math.min(count - 1, page + 1);
  if (lo > 2) out.push('…');
  for (let i = lo; i <= hi; i++) out.push(i);
  if (hi < count - 1) out.push('…');
  out.push(count);
  return out;
}

function Chevron({ dir }) {
  return (
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
      strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" style={{ display: 'block', flex: 'none' }}>
      <polyline points={dir === 'left' ? '15 18 9 12 15 6' : '9 18 15 12 9 6'} />
    </svg>
  );
}

export function Pagination({
  page = 1, pageCount = 1, onChange, total, pageSize,
  onPageSize, pageSizeOptions = [10, 20, 50, 100], compact = false,
  prevLabel = 'Anterior', nextLabel = 'Próximo', totalLabel,
}) {
  const go = (p) => { if (onChange && p >= 1 && p <= pageCount && p !== page) onChange(p); };

  // Labeled prev/next (from the "Editorial" treatment) — chevron + word, hairline-bordered.
  const edge = (dir, label, disabled, onClick) => (
    <button type="button" disabled={disabled} onClick={onClick} aria-label={label}
      onMouseEnter={(e) => { if (!disabled) { e.currentTarget.style.borderColor = 'var(--accent)'; e.currentTarget.style.color = 'var(--accent)'; } }}
      onMouseLeave={(e) => { if (!disabled) { e.currentTarget.style.borderColor = 'var(--border)'; e.currentTarget.style.color = 'var(--text)'; } }}
      style={{
        display: 'inline-flex', alignItems: 'center', gap: 5, height: 28, padding: compact ? 0 : '0 11px',
        width: compact ? 28 : undefined, justifyContent: 'center',
        border: '1px solid var(--border)', borderRadius: 'var(--radius-md, 6px)', background: 'transparent',
        color: disabled ? 'var(--text-mute)' : 'var(--text)', font: '500 12.5px/1 var(--font-sans)',
        cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.45 : 1,
        transition: 'border-color var(--t-1, .15s) var(--ease), color var(--t-1, .15s) var(--ease)',
      }}>
      {dir === 'left' && <Chevron dir="left" />}
      {!compact && label}
      {dir === 'right' && <Chevron dir="right" />}
    </button>
  );

  // Number cell — keeps the current bordered look + accent-fill active page.
  const num = (p, current) => (
    <button key={p} type="button" onClick={() => go(p)} aria-current={current ? 'page' : undefined}
      onMouseEnter={(e) => { if (!current) { e.currentTarget.style.background = 'var(--bg-2)'; e.currentTarget.style.color = 'var(--text)'; } }}
      onMouseLeave={(e) => { if (!current) { e.currentTarget.style.background = 'transparent'; e.currentTarget.style.color = 'var(--text-dim)'; } }}
      style={{
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center', minWidth: 28, height: 28, padding: '0 8px',
        border: '1px solid ' + (current ? 'transparent' : 'var(--border)'), borderRadius: 'var(--radius-md, 6px)',
        background: current ? 'var(--accent)' : 'transparent', color: current ? 'var(--accent-fg)' : 'var(--text-dim)',
        font: (current ? '600' : '500') + ' 12.5px/1 var(--font-sans)', fontVariantNumeric: 'tabular-nums',
        cursor: 'pointer', boxShadow: current ? '0 2px 8px -3px oklch(0.55 0.15 295 / .5)' : 'none',
        transition: 'background var(--t-1, .15s) var(--ease), color var(--t-1, .15s) var(--ease)',
      }}>{p}</button>
  );

  return (
    <nav aria-label="Paginação" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
      <div style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
        {edge('left', prevLabel, page <= 1, () => go(page - 1))}
        {pageList(page, pageCount).map((p, i) =>
          p === '…'
            ? <span key={'e' + i} style={{ padding: '0 4px', color: 'var(--text-mute)', userSelect: 'none' }}>…</span>
            : num(p, p === page)
        )}
        {edge('right', nextLabel, page >= pageCount, () => go(page + 1))}
      </div>
      {total != null && pageSize != null && (
        <span style={{ marginLeft: 4, paddingLeft: 12, borderLeft: '1px solid var(--border)', display: 'inline-flex', alignItems: 'center', gap: 12, font: '400 11.5px/1 var(--font-sans)', color: 'var(--text-mute)' }}>
          <span style={{ fontVariantNumeric: 'tabular-nums' }}>
            <b style={{ color: 'var(--text-dim)', fontWeight: 600 }}>{Math.min((page - 1) * pageSize + 1, total)}–{Math.min(page * pageSize, total)}</b> de {total}{totalLabel ? ' ' + totalLabel : ''}
          </span>
          {onPageSize && (
            <label style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
              por página
              <select value={pageSize} onChange={(e) => onPageSize(+e.target.value)}
                style={{ height: 26, padding: '0 6px', border: '1px solid var(--border)', borderRadius: 'var(--radius-sm, 4px)', background: 'var(--surface)', color: 'var(--text)', font: '500 11.5px/1 var(--font-sans)', cursor: 'pointer' }}>
                {pageSizeOptions.map((n) => <option key={n} value={n}>{n}</option>)}
              </select>
            </label>
          )}
        </span>
      )}
    </nav>
  );
}
