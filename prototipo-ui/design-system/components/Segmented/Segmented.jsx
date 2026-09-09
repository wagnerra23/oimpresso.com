/**
 * Segmented — controle segmentado (2–5 opções mutuamente exclusivas) para
 * alternar visão, período ou densidade. Trilho único com pílula ativa;
 * suporta ícone, contador, largura total e navegação por setas.
 *
 * options: [{ value, label?, icon?, count?, disabled? }] · value · onChange(value)
 */
export function Segmented({ options = [], value, onChange, size = 'md', full = false, iconOnly = false, ariaLabel = 'Alternar visão' }) {
  const H = size === 'sm' ? 26 : size === 'lg' ? 36 : 30;
  const fs = size === 'sm' ? 11.5 : size === 'lg' ? 13.5 : 12.5;
  const refs = React.useRef([]);

  const move = (i, delta) => {
    const n = options.length;
    for (let s = 1; s <= n; s++) {
      const j = (i + delta * s + n * 4) % n;
      if (!options[j].disabled) { if (onChange) onChange(options[j].value); const el = refs.current[j]; if (el) el.focus(); return; }
    }
  };

  return (
    <div role="tablist" aria-label={ariaLabel} style={{
      display: full ? 'grid' : 'inline-grid', gridAutoFlow: 'column',
      gridAutoColumns: full ? '1fr' : 'auto', width: full ? '100%' : undefined,
      gap: 2, padding: 2, background: 'var(--bg-2)', border: '1px solid var(--border)', borderRadius: 8,
    }}>
      {options.map((o, i) => {
        const on = o.value === value;
        return (
          <button key={o.value} type="button" role="tab" aria-selected={on} disabled={o.disabled}
            ref={(el) => { refs.current[i] = el; }}
            tabIndex={on ? 0 : -1}
            onClick={() => { if (!o.disabled && onChange) onChange(o.value); }}
            onKeyDown={(e) => { if (e.key === 'ArrowRight') { e.preventDefault(); move(i, 1); } else if (e.key === 'ArrowLeft') { e.preventDefault(); move(i, -1); } }}
            title={iconOnly && typeof o.label === 'string' ? o.label : undefined}
            aria-label={iconOnly && typeof o.label === 'string' ? o.label : undefined}
            onMouseEnter={(e) => { if (!on && !o.disabled) e.currentTarget.style.color = 'var(--text)'; }}
            onMouseLeave={(e) => { if (!on && !o.disabled) e.currentTarget.style.color = 'var(--text-dim)'; }}
            style={{
              display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 6,
              height: H, padding: iconOnly ? 0 : '0 ' + (size === 'sm' ? 9 : 12) + 'px', width: iconOnly ? H : undefined,
              border: 0, borderRadius: 6,
              background: on ? 'var(--surface)' : 'transparent',
              boxShadow: on ? '0 1px 2px rgba(0,0,0,.10), 0 0 0 1px color-mix(in oklch, var(--accent) 22%, transparent)' : 'none',
              color: o.disabled ? 'var(--text-mute)' : on ? 'var(--text)' : 'var(--text-dim)',
              font: (on ? '600' : '500') + ' ' + fs + 'px/1 var(--font-sans)',
              cursor: o.disabled ? 'not-allowed' : 'pointer', opacity: o.disabled ? 0.5 : 1,
              whiteSpace: 'nowrap', transition: 'background .15s, color .15s, box-shadow .15s',
            }}>
            {o.icon && <span style={{ display: 'inline-flex', flex: 'none', color: on ? 'var(--accent)' : 'inherit', opacity: on ? 1 : 0.75 }} aria-hidden>{o.icon}</span>}
            {!iconOnly && o.label}
            {!iconOnly && o.count != null && (
              <span style={{ font: '600 10px/1.4 var(--font-mono)', padding: '0 5px', minWidth: 16, textAlign: 'center', borderRadius: 99, background: on ? 'var(--accent)' : 'var(--border-2)', color: on ? 'var(--accent-fg)' : 'var(--text-dim)' }}>{o.count}</span>
            )}
          </button>
        );
      })}
    </div>
  );
}
