/**
 * Drawer — right-side detail/form panel (DS v4 PT-02). Pure, dependency-free.
 * Scrim + sliding panel. Controlled: open + onClose.
 *
 * title · subtitle · badge (status node, top-right of header) · width (px, default 480)
 * children = body sections · footer = sticky action row.
 */
export function Drawer({ open, onClose, title, subtitle, badge, width = 480, children, footer }) {
  const panelRef = React.useRef(null);
  React.useEffect(() => {
    if (!open) return;
    const prev = document.activeElement;
    const panel = panelRef.current;
    const sel = 'a[href],button:not([disabled]),textarea:not([disabled]),input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])';
    const list = () => (panel ? Array.prototype.slice.call(panel.querySelectorAll(sel)) : []);
    const init = list(); (init[0] || panel) && (init[0] || panel).focus();
    const onKey = (e) => {
      if (e.key === 'Escape') { e.stopPropagation(); onClose && onClose(); return; }
      if (e.key === 'Tab') {
        const f = list(); if (!f.length) { e.preventDefault(); panel && panel.focus(); return; }
        const first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    };
    document.addEventListener('keydown', onKey, true);
    return () => { document.removeEventListener('keydown', onKey, true); prev && prev.focus && prev.focus(); };
  }, [open]);
  if (!open) return null;
  return (
    <div style={{ position: 'fixed', inset: 0, zIndex: 60 }}>
      <div onClick={onClose} aria-hidden style={{ position: 'absolute', inset: 0, background: 'oklch(0.15 0 0 / 0.45)' }} />
      <aside ref={panelRef} tabIndex={-1} role="dialog" aria-modal="true" aria-label={title}
        style={{
          position: 'absolute', top: 0, right: 0, bottom: 0, width: '100%', maxWidth: width,
          background: 'var(--surface)', borderLeft: '1px solid var(--border)',
          boxShadow: '-16px 0 40px -12px rgba(0,0,0,.30)', display: 'flex', flexDirection: 'column',
        }}>
        <div style={{ padding: '14px 18px', display: 'flex', alignItems: 'center', gap: 10, borderBottom: '1px solid var(--border)' }}>
          {badge}
          <span style={{ flex: 1 }} />
          <button type="button" onClick={onClose} aria-label="Fechar"
            onMouseEnter={(e) => { e.currentTarget.style.background = 'var(--bg-2)'; e.currentTarget.style.color = 'var(--text)'; }}
            onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; e.currentTarget.style.color = 'var(--text-mute)'; }}
            style={{ display: 'grid', placeItems: 'center', width: 28, height: 28, border: 0, borderRadius: 6, background: 'transparent', color: 'var(--text-mute)', cursor: 'pointer', fontSize: 16 }}>×</button>
        </div>
        {(title || subtitle) && (
          <div style={{ padding: '16px 18px' }}>
            {title && <h3 style={{ margin: 0, font: '600 17px/1.3 var(--font-sans)', letterSpacing: '-.01em', color: 'var(--text)' }}>{title}</h3>}
            {subtitle && <p style={{ margin: '4px 0 0', fontSize: 12.5, color: 'var(--text-dim)' }}>{subtitle}</p>}
          </div>
        )}
        <div style={{ flex: 1, overflowY: 'auto' }}>{children}</div>
        {footer && (
          <div style={{ marginTop: 'auto', padding: '12px 18px', display: 'flex', gap: 8, justifyContent: 'flex-end', borderTop: '1px solid var(--border)' }}>{footer}</div>
        )}
      </aside>
    </div>
  );
}

/** A titled section inside a Drawer body (uppercase heading + content). */
export function DrawerSection({ title, children }) {
  return (
    <div style={{ padding: '14px 18px', borderTop: '1px solid var(--border-2)' }}>
      {title && <h4 style={{ margin: '0 0 8px', font: '600 10.5px/1.4 var(--font-sans)', textTransform: 'uppercase', letterSpacing: '.05em', color: 'var(--text-mute)' }}>{title}</h4>}
      <div style={{ fontSize: 13, color: 'var(--text)', lineHeight: 1.5 }}>{children}</div>
    </div>
  );
}
