/**
 * RadioGroup — single-choice radio set (DS v4). Pure, dependency-free.
 * options: [{ value, label }] · value · onChange(value) · name (required for grouping)
 */
export function RadioGroup({ options = [], value, onChange, name, disabled = false, direction = 'column' }) {
  return (
    <div role="radiogroup" style={{ display: 'flex', flexDirection: direction, gap: direction === 'row' ? 16 : 8 }}>
      {options.map((o) => {
        const v = o.value ?? o;
        const lbl = o.label ?? o;
        const on = v === value;
        return (
          <label key={v} style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: disabled ? 'not-allowed' : 'pointer', userSelect: 'none', opacity: disabled ? 0.55 : 1, font: '500 12.5px/1.4 var(--font-sans)', color: 'var(--text)' }}>
            <input type="radio" name={name} value={v} checked={on} disabled={disabled}
              onChange={onChange ? () => onChange(v) : undefined}
              style={{ position: 'absolute', opacity: 0, pointerEvents: 'none', width: 1, height: 1 }} />
            <span style={{
              width: 16, height: 16, flexShrink: 0, borderRadius: '50%',
              border: '1.5px solid ' + (on ? 'var(--accent)' : 'var(--border)'),
              background: 'var(--surface)', display: 'grid', placeItems: 'center', transition: 'border-color .15s',
            }}>
              {on && <span style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--accent)' }} />}
            </span>
            {lbl}
          </label>
        );
      })}
    </div>
  );
}
