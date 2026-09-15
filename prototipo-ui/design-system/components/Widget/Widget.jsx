/**
 * Widget — moldura de painel do cockpit: título + nota, ações no canto,
 * corpo e rodapé opcional. Substitui os "cards com título" refeitos por módulo.
 * Pure, dependency-free (global React + tokens inline).
 *
 * title · note (linha de apoio) · badge (nó à direita do título)
 * actions (nó no canto sup. dir.) · footer · tone · pad · height · scroll
 */
const WIDGET_TONE = {
  default: { border: 'var(--border)', bg: 'var(--surface)', accent: null },
  muted:   { border: 'var(--border)', bg: 'var(--bg-2)', accent: null },
  accent:  { border: 'color-mix(in oklch, var(--accent) 26%, transparent)', bg: 'color-mix(in oklch, var(--accent) 4%, var(--surface))', accent: 'var(--accent)' },
  warning: { border: 'color-mix(in oklch, var(--warn, var(--color-warning)) 26%, transparent)', bg: 'color-mix(in oklch, var(--warn, var(--color-warning)) 5%, var(--surface))', accent: 'var(--warn, var(--color-warning))' },
  danger:  { border: 'color-mix(in oklch, var(--neg) 26%, transparent)', bg: 'color-mix(in oklch, var(--neg) 4%, var(--surface))', accent: 'var(--neg)' },
};

export function Widget({ title, note, badge, actions, footer, tone = 'default', pad = 14, height, scroll = false, flush = false, children }) {
  const t = WIDGET_TONE[tone] || WIDGET_TONE.default;
  const hasHead = title != null || actions != null || note != null;
  return (
    <section style={{
      display: 'flex', flexDirection: 'column', minWidth: 0, height,
      border: '1px solid ' + t.border, borderRadius: 12, background: t.bg,
      boxShadow: '0 1px 2px rgba(0,0,0,.04)', overflow: 'hidden',
      borderTop: t.accent ? '2px solid ' + t.accent : '1px solid ' + t.border,
    }}>
      {hasHead && (
        <header style={{ display: 'flex', alignItems: 'flex-start', gap: 12, padding: '12px ' + pad + 'px', borderBottom: children ? '1px solid var(--border-2)' : 0 }}>
          <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 3 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, minWidth: 0 }}>
              {title && <h3 style={{ margin: 0, font: '600 13.5px/1.25 var(--font-sans)', letterSpacing: '-0.008em', color: 'var(--text)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{title}</h3>}
              {badge}
            </div>
            {note && <p style={{ margin: 0, font: '400 11.5px/1.4 var(--font-sans)', color: 'var(--text-mute)', textWrap: 'pretty' }}>{note}</p>}
          </div>
          {actions && <div style={{ flex: 'none', display: 'flex', alignItems: 'center', gap: 6 }}>{actions}</div>}
        </header>
      )}
      {children != null && (
        <div style={{ flex: 1, minHeight: 0, padding: flush ? 0 : pad, overflow: scroll ? 'auto' : 'visible', color: 'var(--text)', font: '400 13px/1.5 var(--font-sans)' }}>{children}</div>
      )}
      {footer && (
        <footer style={{ flex: 'none', padding: '9px ' + pad + 'px', borderTop: '1px solid var(--border-2)', background: 'color-mix(in oklch, var(--bg-2) 60%, transparent)', font: '400 11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)', display: 'flex', alignItems: 'center', gap: 10 }}>{footer}</footer>
      )}
    </section>
  );
}
