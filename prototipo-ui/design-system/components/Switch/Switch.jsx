/**
 * Switch — boolean toggle with label + optional sublabel (DS v4 toggle).
 * Pure, dependency-free. Controlled: checked + onChange(next).
 */
export function Switch({ checked = false, onChange, label, sublabel, disabled = false, name }) {
  return (
    <label style={{ display: 'inline-flex', alignItems: 'center', gap: 10, cursor: disabled ? 'not-allowed' : 'pointer', userSelect: 'none', opacity: disabled ? 0.55 : 1 }}>
      <input type="checkbox" name={name} checked={checked} disabled={disabled}
        onChange={onChange ? (e) => onChange(e.target.checked) : undefined}
        style={{ position: 'absolute', opacity: 0, pointerEvents: 'none', width: 1, height: 1 }} />
      <span style={{
        position: 'relative', width: 32, height: 18, borderRadius: 99, flexShrink: 0,
        background: checked ? 'var(--accent)' : 'var(--border)', transition: 'background .2s',
      }}>
        <span style={{
          position: 'absolute', top: 2, left: 2, width: 14, height: 14, borderRadius: '50%', background: '#fff',
          boxShadow: '0 1px 2px rgba(0,0,0,.2)', transform: checked ? 'translateX(14px)' : 'none', transition: 'transform .2s cubic-bezier(.2,.7,.3,1)',
        }} />
      </span>
      {(label || sublabel) && (
        <span style={{ font: '500 12.5px/1.4 var(--font-sans)', color: 'var(--text)' }}>
          {label}
          {sublabel && <small style={{ display: 'block', font: '400 11px/1.4 var(--font-sans)', color: 'var(--text-mute)', marginTop: 1 }}>{sublabel}</small>}
        </span>
      )}
    </label>
  );
}
