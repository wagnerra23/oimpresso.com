/**
 * PresenterMode — modo apresentação / impressão do cockpit (DS print-craft).
 * Cobre a tela com um palco escuro, some com toda a navegação, empilha as
 * folhas em tamanho de papel real e imprime só elas (regras @media print
 * próprias, sem CSS por módulo).
 *
 * open · onClose · title · subtitle · pages · children (nó ou (i) => nó)
 * paper 'A4'|'letter' · orientation · zoom inicial · onPrint
 * Teclado: ← → (folhas) · + − (zoom) · P (imprimir) · Esc (sair)
 */
const PAPER = { A4: [210, 297], letter: [216, 279] };
const PRESENTER_STYLE_ID = 'ds-presenter-style';

function usePresenterStyle() {
  React.useEffect(() => {
    if (document.getElementById(PRESENTER_STYLE_ID)) return;
    const el = document.createElement('style');
    el.id = PRESENTER_STYLE_ID;
    el.textContent =
      '@media print{' +
      'body.ds-presenting{background:#fff!important}' +
      'body.ds-presenting > *{visibility:hidden!important}' +
      'body.ds-presenting .ds-presenter{position:static!important;inset:auto!important;background:#fff!important;display:block!important;overflow:visible!important;visibility:visible!important}' +
      'body.ds-presenting .ds-presenter *{visibility:visible}' +
      'body.ds-presenting .ds-presenter__chrome{display:none!important}' +
      'body.ds-presenting .ds-presenter__stage{padding:0!important;overflow:visible!important;display:block!important;background:#fff!important}' +
      'body.ds-presenting .ds-presenter__sheet{transform:none!important;margin:0!important;box-shadow:none!important;border:0!important;border-radius:0!important;width:auto!important;min-height:auto!important;break-after:page;page-break-after:always}' +
      'body.ds-presenting .ds-presenter__sheet:last-child{break-after:auto;page-break-after:auto}' +
      '}' +
      '@keyframes ds-presenter-in{from{opacity:0}to{opacity:1}}';
    document.head.appendChild(el);
  }, []);
}

export function PresenterMode({
  open = true, onClose, onPrint, title, subtitle, pages = 1, children,
  paper = 'A4', orientation = 'portrait', zoom: zoomProp = 0.9, showPrint = true, hint = true,
}) {
  usePresenterStyle();
  const [zoom, setZoom] = React.useState(zoomProp);
  const [page, setPage] = React.useState(0);
  const stage = React.useRef(null);
  const sheets = React.useRef([]);

  React.useEffect(() => {
    if (!open) return undefined;
    document.body.classList.add('ds-presenting');
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => { document.body.classList.remove('ds-presenting'); document.body.style.overflow = prev; };
  }, [open]);

  const goTo = React.useCallback((i) => {
    const n = Math.max(0, Math.min(i, pages - 1));
    setPage(n);
    const st = stage.current, sh = sheets.current[n];
    if (st && sh) st.scrollTop = Math.max(0, sh.offsetTop - 24);
  }, [pages]);

  const doPrint = React.useCallback(() => { if (onPrint) onPrint(); else window.print(); }, [onPrint]);

  React.useEffect(() => {
    if (!open) return undefined;
    const onKey = (e) => {
      if (e.key === 'Escape') { if (onClose) onClose(); }
      else if (e.key === 'ArrowRight' || e.key === 'PageDown') { e.preventDefault(); goTo(page + 1); }
      else if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); goTo(page - 1); }
      else if (e.key === '+' || e.key === '=') { setZoom((z) => Math.min(2, +(z + 0.1).toFixed(2))); }
      else if (e.key === '-') { setZoom((z) => Math.max(0.4, +(z - 0.1).toFixed(2))); }
      else if (e.key === 'p' || e.key === 'P') { e.preventDefault(); doPrint(); }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, page, goTo, onClose, doPrint]);

  if (!open) return null;

  const dims = PAPER[paper] || PAPER.A4;
  const W = orientation === 'landscape' ? dims[1] : dims[0];
  const H = orientation === 'landscape' ? dims[0] : dims[1];

  const btn = (label, onClick, extra) => (
    <button type="button" onClick={onClick} title={label} aria-label={label}
      onMouseEnter={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,.16)'; }}
      onMouseLeave={(e) => { e.currentTarget.style.background = 'rgba(255,255,255,.07)'; }}
      style={{
        display: 'inline-flex', alignItems: 'center', gap: 6, height: 30, padding: '0 10px',
        border: '1px solid rgba(255,255,255,.16)', borderRadius: 7, background: 'rgba(255,255,255,.07)',
        color: '#fff', font: '500 12.5px/1 var(--font-sans)', cursor: 'pointer', whiteSpace: 'nowrap', ...extra,
      }}>{label}</button>
  );

  return (
    <div className="ds-presenter" role="dialog" aria-modal="true" aria-label={title || 'Modo apresentação'} style={{
      position: 'fixed', inset: 0, zIndex: 120, display: 'flex', flexDirection: 'column',
      background: 'oklch(0.19 0.008 240)', animation: 'ds-presenter-in .15s ease both',
    }}>
      <header className="ds-presenter__chrome" style={{
        flex: 'none', display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap',
        padding: '10px 14px', borderBottom: '1px solid rgba(255,255,255,.12)', color: '#fff',
      }}>
        <div style={{ flex: 1, minWidth: 0 }}>
          {title && <div style={{ font: '600 13.5px/1.2 var(--font-sans)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{title}</div>}
          {subtitle && <div style={{ font: '400 11.5px/1.3 var(--font-sans)', opacity: 0.65 }}>{subtitle}</div>}
        </div>
        {pages > 1 && (
          <div style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
            {btn('◀', () => goTo(page - 1))}
            <span style={{ font: '600 12px/1 var(--font-mono)', color: '#fff', opacity: 0.8, fontVariantNumeric: 'tabular-nums' }}>{page + 1} / {pages}</span>
            {btn('▶', () => goTo(page + 1))}
          </div>
        )}
        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
          {btn('−', () => setZoom((z) => Math.max(0.4, +(z - 0.1).toFixed(2))))}
          <span style={{ minWidth: 42, textAlign: 'center', font: '600 12px/1 var(--font-mono)', color: '#fff', opacity: 0.8 }}>{Math.round(zoom * 100)}%</span>
          {btn('+', () => setZoom((z) => Math.min(2, +(z + 0.1).toFixed(2))))}
        </div>
        {showPrint && btn('Imprimir', doPrint, { background: 'var(--accent)', borderColor: 'transparent', color: 'var(--accent-fg)', fontWeight: 600 })}
        {onClose && btn('Sair', onClose)}
      </header>

      <div ref={stage} className="ds-presenter__stage" style={{
        flex: 1, minHeight: 0, overflow: 'auto', padding: '24px 16px 48px',
        display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 24,
      }}>
        {Array.from({ length: Math.max(1, pages) }, (_, i) => (
          <div key={i} ref={(el) => { sheets.current[i] = el; }} className="ds-presenter__sheet" style={{
            flex: 'none', width: W + 'mm', minHeight: H + 'mm', background: '#fff', color: '#111',
            boxShadow: '0 18px 44px -18px rgba(0,0,0,.7)', transform: 'scale(' + zoom + ')',
            transformOrigin: 'top center', marginBottom: (zoom - 1) * H * 3.78 + 'px',
          }}>
            {typeof children === 'function' ? children(i) : children}
          </div>
        ))}
      </div>

      {hint && (
        <footer className="ds-presenter__chrome" style={{
          flex: 'none', padding: '7px 14px', borderTop: '1px solid rgba(255,255,255,.12)',
          font: '400 11px/1 var(--font-mono)', color: 'rgba(255,255,255,.55)', textAlign: 'center',
        }}>← → folhas · + − zoom · P imprimir · Esc sair</footer>
      )}
    </div>
  );
}
