/**
 * Modal — centered confirmation/short-action dialog (DS v4 PT-04). NOT for detail
 * (that's Drawer). Pure, dependency-free. Controlled: open + onClose.
 *
 * title · children (body) · footer (action row, right-aligned) · width (default 420)
 */
export function Modal({ open, onClose, title, children, footer, width = 420 }) {
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
    <div style={{ position: 'fixed', inset: 0, zIndex: 70, display: 'grid', placeItems: 'center', padding: 24 }}>
      <div onClick={onClose} aria-hidden style={{ position: 'absolute', inset: 0, background: 'oklch(0.15 0 0 / 0.45)' }} />
      <div ref={panelRef} tabIndex={-1} role="dialog" aria-modal="true" aria-label={title}
        style={{
          position: 'relative', width: '100%', maxWidth: width,
          background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 'var(--radius-lg, 8px)',
          padding: 20, boxShadow: '0 24px 60px -12px rgba(0,0,0,.45)',
        }}>
        {title && <h3 style={{ margin: '0 0 6px', font: '600 17px/1.3 var(--font-sans)', letterSpacing: '-.01em', color: 'var(--text)' }}>{title}</h3>}
        {children && <div style={{ margin: '0 0 18px', fontSize: 13, color: 'var(--text-dim)', lineHeight: 1.5 }}>{children}</div>}
        {footer && <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>{footer}</div>}
      </div>
    </div>
  );
}
