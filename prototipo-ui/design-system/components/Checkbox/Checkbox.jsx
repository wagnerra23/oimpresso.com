/**
 * Checkbox — labeled checkbox with accent fill when checked (DS v4).
 * Pure, dependency-free. Controlled: checked + onChange(next).
 * `indeterminate` desenha o traço de "parte marcada" (cabeçalho de grade) e `ariaLabel` nomeia a
 * caixa sem rótulo visível — os dois chegaram com o DataGrid (D1, [W] 2026-11-25).
 */
export function Checkbox({ checked = false, onChange, label, disabled = false, name, indeterminate = false, ariaLabel }) {
  const on = checked || indeterminate;
  return (
    <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: disabled ? 'not-allowed' : 'pointer', userSelect: 'none', opacity: disabled ? 0.55 : 1, font: '500 12.5px/1.4 var(--font-sans)', color: 'var(--text)' }}>
      <input type="checkbox" name={name} checked={checked} disabled={disabled} aria-label={ariaLabel}
        aria-checked={indeterminate ? 'mixed' : undefined}
        ref={(el) => { if (el) el.indeterminate = indeterminate; }}
        onChange={onChange ? (e) => onChange(e.target.checked) : undefined}
        style={{ position: 'absolute', opacity: 0, pointerEvents: 'none', width: 1, height: 1 }} />
      <span style={{
        width: 16, height: 16, flexShrink: 0, borderRadius: 'var(--radius-sm, 4px)',
        border: '1.5px solid ' + (on ? 'var(--accent)' : 'var(--border)'),
        background: on ? 'var(--accent)' : 'var(--surface)',
        display: 'grid', placeItems: 'center', transition: 'background .15s, border-color .15s',
      }}>
        {indeterminate && !checked && (
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3.5" strokeLinecap="round" aria-hidden>
            <path d="M6 12h12" />
          </svg>
        )}
        {checked && (
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
            <path d="M5 12l5 5 9-9" />
          </svg>
        )}
      </span>
      {label}
    </label>
  );
}
