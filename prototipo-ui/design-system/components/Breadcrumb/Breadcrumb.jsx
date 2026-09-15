/**
 * Breadcrumb — current-page hierarchy (DS v4). Pure, dependency-free.
 * items: [{ label, href? }] — the last item renders as current (bold, no link).
 */
export function Breadcrumb({ items = [] }) {
  return (
    <nav aria-label="Trilha" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, font: '400 12px/1.4 var(--font-sans)' }}>
      {items.map((it, i) => {
        const last = i === items.length - 1;
        return (
          <React.Fragment key={i}>
            {i > 0 && <span aria-hidden style={{ opacity: 0.4 }}>/</span>}
            {last || !it.href ? (
              <span style={last ? { color: 'var(--text)', fontWeight: 600 } : { color: 'var(--text-dim)' }} aria-current={last ? 'page' : undefined}>{it.label}</span>
            ) : (
              <a href={it.href}
                onMouseEnter={(e) => { e.currentTarget.style.color = 'var(--text)'; e.currentTarget.style.background = 'var(--bg-2)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--text-dim)'; e.currentTarget.style.background = 'transparent'; }}
                style={{ color: 'var(--text-dim)', textDecoration: 'none', padding: '2px 4px', borderRadius: 3, transition: 'color .15s, background .15s' }}>{it.label}</a>
            )}
          </React.Fragment>
        );
      })}
    </nav>
  );
}
