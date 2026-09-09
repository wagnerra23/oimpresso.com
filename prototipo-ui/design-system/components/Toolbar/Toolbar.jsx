/**
 * Toolbar — barra de ferramentas acima de listas, tabelas e editores (DS).
 * Três zonas (left / center / right), divisores, busca embutida e variante
 * sticky. Substitui as barras improvisadas módulo a módulo.
 *
 * Acompanham: ToolbarButton, ToolbarSearch, ToolbarDivider, ToolbarSpacer.
 */
export function Toolbar({ left, center, right, children, sticky = false, dense = false, tone = 'surface', bordered = true }) {
  const bg = tone === 'muted' ? 'var(--bg-2)' : tone === 'transparent' ? 'transparent' : 'var(--surface)';
  return (
    <div role="toolbar" style={{
      display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap', minWidth: 0,
      padding: dense ? '6px 10px' : '9px 12px', background: bg,
      borderBottom: bordered ? '1px solid var(--border)' : 0,
      position: sticky ? 'sticky' : undefined, top: sticky ? 0 : undefined, zIndex: sticky ? 5 : undefined,
    }}>
      {left && <div style={{ display: 'flex', alignItems: 'center', gap: 6, minWidth: 0 }}>{left}</div>}
      {children}
      {center
        ? <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6, minWidth: 0 }}>{center}</div>
        : <div style={{ flex: 1, minWidth: 8 }} />}
      {right && <div style={{ display: 'flex', alignItems: 'center', gap: 6, minWidth: 0 }}>{right}</div>}
    </div>
  );
}

export function ToolbarDivider() {
  return <span aria-hidden style={{ width: 1, alignSelf: 'stretch', minHeight: 20, background: 'var(--border)', margin: '0 2px' }} />;
}

export function ToolbarSpacer() {
  return <span style={{ flex: 1, minWidth: 8 }} />;
}

export function ToolbarButton({ icon, children, active = false, disabled = false, tone = 'default', onClick, title, iconOnly = false }) {
  const danger = tone === 'danger';
  const fg = disabled ? 'var(--text-mute)' : danger ? 'var(--neg)' : active ? 'var(--accent)' : 'var(--text)';
  return (
    <button type="button" disabled={disabled} onClick={onClick} title={title} aria-pressed={active || undefined} aria-label={iconOnly ? title || undefined : undefined}
      onMouseEnter={(e) => { if (!disabled && !active) e.currentTarget.style.background = 'var(--bg-2)'; }}
      onMouseLeave={(e) => { if (!disabled && !active) e.currentTarget.style.background = 'transparent'; }}
      style={{
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 6, height: 28,
        padding: iconOnly ? 0 : '0 9px', width: iconOnly ? 28 : undefined,
        border: '1px solid ' + (active ? 'color-mix(in oklch, var(--accent) 40%, transparent)' : 'transparent'),
        borderRadius: 6, background: active ? 'var(--accent-soft)' : 'transparent', color: fg,
        font: '500 12.5px/1 var(--font-sans)', cursor: disabled ? 'not-allowed' : 'pointer',
        opacity: disabled ? 0.5 : 1, transition: 'background .15s, color .15s, border-color .15s', whiteSpace: 'nowrap',
      }}>
      {icon && <span style={{ display: 'inline-flex', flex: 'none', opacity: active ? 1 : 0.75 }} aria-hidden>{icon}</span>}
      {!iconOnly && children}
    </button>
  );
}

export function ToolbarSearch({ value, onChange, placeholder = 'Buscar…', width = 240, kbd }) {
  const [focus, setFocus] = React.useState(false);
  return (
    <div style={{
      display: 'inline-flex', alignItems: 'center', gap: 6, height: 28, padding: '0 8px', width,
      border: '1px solid ' + (focus ? 'var(--accent)' : 'var(--border)'), borderRadius: 6,
      background: 'var(--bg)', boxShadow: focus ? '0 0 0 3px var(--accent-soft)' : 'none', transition: 'border-color .15s, box-shadow .15s',
    }}>
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flex: 'none', color: 'var(--text-mute)' }} aria-hidden>
        <circle cx="11" cy="11" r="7" /><path d="m20 20-3.2-3.2" />
      </svg>
      <input value={value} onChange={onChange ? (e) => onChange(e.target.value) : undefined} placeholder={placeholder}
        onFocus={() => setFocus(true)} onBlur={() => setFocus(false)}
        style={{ flex: 1, minWidth: 0, border: 0, outline: 'none', background: 'transparent', color: 'var(--text)', font: '400 12.5px/1 var(--font-sans)' }} />
      {kbd && <kbd style={{ font: '600 10px/1 var(--font-mono)', color: 'var(--text-mute)', background: 'var(--bg-2)', border: '1px solid var(--border)', borderRadius: 4, padding: '2px 4px' }}>{kbd}</kbd>}
    </div>
  );
}
