/**
 * TabBar — module sub-tabs with counts (DS v4 `moduletopnav`, slot 2 of PT-01).
 * The Clientes-style tab row: underline-active in accent, mono counters.
 * Pure, dependency-free (global React + inline token styles).
 *
 * Rola horizontalmente sem barra nativa: `.ds-tabbar` esconde a scrollbar e,
 * quando há conteúdo cortado, ganha máscara de fade nas bordas. A aba ativa é
 * trazida para o viewport da nav via scrollLeft calculado (nunca scrollIntoView).
 *
 * tabs: [{ key, label, icon?, count? }] · active key · onChange(key)
 *
 * O próprio <nav> é o contrato: `className` soma-se a `ds-tabbar` (não substitui),
 * `ariaLabel` sobrepõe o rótulo default e todo `...rest` (data-contract, data-*,
 * aria-*, id, role) cai direto no <nav>. Nenhum wrapper é necessário — nem pra
 * contrato, nem pra recuo: `inset` dá o padding lateral no próprio <nav>, o que
 * mantém a borda inferior sangrando de ponta a ponta.
 */
const DS_TABBAR_STYLE_ID = 'ds-tabbar-style';

function useTabBarStyle() {
  React.useEffect(() => {
    if (document.getElementById(DS_TABBAR_STYLE_ID)) return;
    const el = document.createElement('style');
    el.id = DS_TABBAR_STYLE_ID;
    el.textContent =
      `.ds-tabbar{scrollbar-width:none;-ms-overflow-style:none;scroll-behavior:smooth}` +
      `.ds-tabbar::-webkit-scrollbar{display:none;width:0;height:0}` +
      `.ds-tabbar[data-overflow="both"]{-webkit-mask-image:linear-gradient(to right,transparent 0,#000 12px,#000 calc(100% - 12px),transparent 100%);mask-image:linear-gradient(to right,transparent 0,#000 12px,#000 calc(100% - 12px),transparent 100%)}` +
      `.ds-tabbar[data-overflow="end"]{-webkit-mask-image:linear-gradient(to right,#000 0,#000 calc(100% - 12px),transparent 100%);mask-image:linear-gradient(to right,#000 0,#000 calc(100% - 12px),transparent 100%)}` +
      `.ds-tabbar[data-overflow="start"]{-webkit-mask-image:linear-gradient(to right,transparent 0,#000 12px,#000 100%);mask-image:linear-gradient(to right,transparent 0,#000 12px,#000 100%)}` +
      `@media (prefers-reduced-motion: reduce){.ds-tabbar{scroll-behavior:auto}}`;
    document.head.appendChild(el);
  }, []);
}

const TABBAR_SIZES = {
  sm: { height: 30, font: 12, count: 10, gap: 5 },
  md: { height: 36, font: 13, count: 10.5, gap: 6 },
  lg: { height: 42, font: 14, count: 11.5, gap: 7 },
};

export function TabBar({
  tabs = [], active, onChange,
  className, ariaLabel = 'Sub-navegação', pad = 14, size = 'md', off = false, icon, inset,
  ...rest
}) {
  useTabBarStyle();
  const sz = TABBAR_SIZES[size] || TABBAR_SIZES.md;
  const navRef = React.useRef(null);
  const activeRef = React.useRef(null);

  // Marca o estado de overflow (nenhum / início / fim / ambos) para a máscara de fade.
  const syncOverflow = React.useCallback(() => {
    const nav = navRef.current;
    if (!nav) return;
    const max = nav.scrollWidth - nav.clientWidth;
    if (max <= 1) { nav.removeAttribute('data-overflow'); return; }
    const atStart = nav.scrollLeft <= 1;
    const atEnd = nav.scrollLeft >= max - 1;
    nav.dataset.overflow = atStart ? 'end' : atEnd ? 'start' : 'both';
  }, []);

  React.useEffect(() => {
    const nav = navRef.current;
    if (!nav) return;
    syncOverflow();
    nav.addEventListener('scroll', syncOverflow, { passive: true });
    let ro;
    if (typeof ResizeObserver !== 'undefined') {
      ro = new ResizeObserver(syncOverflow);
      ro.observe(nav);
    } else {
      window.addEventListener('resize', syncOverflow);
    }
    return () => {
      nav.removeEventListener('scroll', syncOverflow);
      if (ro) ro.disconnect(); else window.removeEventListener('resize', syncOverflow);
    };
  }, [syncOverflow, tabs.length]);

  // Traz a aba ativa para dentro do viewport da nav sem mexer no scroll da página.
  React.useEffect(() => {
    const nav = navRef.current, btn = activeRef.current;
    if (!nav || !btn) return;
    const pad = 16;
    const left = btn.offsetLeft, right = left + btn.offsetWidth;
    let target = nav.scrollLeft;
    if (left - pad < nav.scrollLeft) target = Math.max(0, left - pad);
    else if (right + pad > nav.scrollLeft + nav.clientWidth) target = right + pad - nav.clientWidth;
    if (target !== nav.scrollLeft) nav.scrollLeft = target;
    syncOverflow();
  }, [active, tabs.length, syncOverflow]);

  return (
    <nav
      {...rest}
      ref={navRef}
      className={className ? 'ds-tabbar ' + className : 'ds-tabbar'}
      aria-label={ariaLabel}
      aria-disabled={off || undefined}
      style={{
        display: 'flex', alignItems: 'center', gap: 0, borderBottom: '1px solid var(--border)',
        overflowX: 'auto', scrollbarWidth: 'none', msOverflowStyle: 'none',
        paddingInline: inset == null ? undefined : (typeof inset === 'number' ? inset + 'px' : inset),
        opacity: off ? 0.5 : undefined, pointerEvents: off ? 'none' : undefined,
        ...(rest.style || null),
      }}
    >
      {tabs.map((t) => {
        const on = t.key === active;
        return (
          <button key={t.key} type="button" ref={on ? activeRef : undefined}
            onClick={onChange && !off ? () => onChange(t.key) : undefined}
            aria-current={on ? 'page' : undefined}
            disabled={off || t.disabled || undefined}
            onMouseEnter={(e) => { if (!on) { e.currentTarget.style.color = 'var(--text)'; e.currentTarget.style.background = 'color-mix(in oklch, var(--border-2) 60%, transparent)'; } }}
            onMouseLeave={(e) => { if (!on) { e.currentTarget.style.color = 'var(--text-dim)'; e.currentTarget.style.background = 'transparent'; } }}
            style={{
              display: 'inline-flex', alignItems: 'center', gap: sz.gap, padding: '0 ' + pad + 'px', height: sz.height,
              border: 0, borderBottom: '2px solid ' + (on ? 'var(--accent)' : 'transparent'), marginBottom: -1,
              background: on ? 'color-mix(in oklch, var(--accent-soft) 50%, transparent)' : 'transparent',
              color: on ? 'var(--text)' : 'var(--text-dim)', font: (on ? '600' : '500') + ' ' + sz.font + 'px/1 var(--font-sans)',
              cursor: 'pointer', whiteSpace: 'nowrap', flex: '0 0 auto', transition: 'color .15s, background .15s, border-color .15s',
            }}>
            {(t.icon || icon) && <span style={{ display: 'inline-flex', color: on ? 'var(--accent)' : 'inherit', opacity: on ? 1 : 0.7 }} aria-hidden>{t.icon || icon}</span>}
            {t.label}
            {t.count != null && (
              <span style={{
                font: '600 ' + sz.count + 'px/1.4 var(--font-mono)', padding: '0 6px', minWidth: 18, textAlign: 'center',
                borderRadius: 99, background: on ? 'var(--accent)' : 'var(--bg-2)', color: on ? 'var(--accent-fg)' : 'var(--text-dim)',
              }}>{t.count}</span>
            )}
          </button>
        );
      })}
    </nav>
  );
}
