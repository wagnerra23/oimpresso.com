/**
 * Field controls — Input, Textarea, Select (DS v4). Pure, dependency-free.
 * Each wraps the optional Field chrome: uppercase label + help + inline error.
 * Props: label, help, error (string → invalid), plus the native props you pass.
 */
function fieldShell(label, error, help, control) {
  const invalid = !!error;
  return (
    <label style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
      {label && <span style={{ fontSize: 10.5, fontWeight: 600, letterSpacing: '.04em', textTransform: 'uppercase', color: invalid ? 'var(--color-destructive-fg)' : 'var(--text-mute)' }}>{label}</span>}
      {control}
      {error && <span style={{ marginTop: 4, display: 'inline-flex', alignItems: 'center', gap: 6, font: '500 11.5px/1.4 var(--font-sans)', color: 'var(--color-destructive-fg)' }}>⚠ {error}</span>}
      {!error && help && <span style={{ marginTop: 4, font: '400 11.5px/1.4 var(--font-sans)', color: 'var(--text-mute)' }}>{help}</span>}
    </label>
  );
}

/** Leading icon + trailing kbd hint wrapper. Adds side padding to the control itself. */
function adorn(control, icon, kbd) {
  if (!icon && !kbd) return control;
  return (
    <span style={{ position: 'relative', display: 'block' }}>
      {icon && <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', display: 'inline-flex', alignItems: 'center', color: 'var(--text-mute)', pointerEvents: 'none', lineHeight: 0 }} aria-hidden>{icon}</span>}
      {control}
      {kbd && <kbd style={{ position: 'absolute', right: 6, top: '50%', transform: 'translateY(-50%)', font: '600 10px/1 var(--font-mono)', color: 'var(--text-mute)', background: 'var(--bg-2)', border: '1px solid var(--border)', borderRadius: 4, padding: '3px 6px', pointerEvents: 'none' }}>{kbd}</kbd>}
    </span>
  );
}

/** Global key binding that focuses+selects the field. `"/"`, `"mod+k"`, `"mod+f"`… */
function useFocusKey(focusKey, ref) {
  React.useEffect(() => {
    if (!focusKey) return;
    const parts = focusKey.toLowerCase().split('+');
    const key = parts[parts.length - 1];
    const wantMod = parts.includes('mod') || parts.includes('ctrl') || parts.includes('cmd');
    const wantShift = parts.includes('shift');
    const onKey = (e) => {
      const mod = e.metaKey || e.ctrlKey;
      if (e.key.toLowerCase() !== key || wantMod !== mod || wantShift !== e.shiftKey) return;
      const el = document.activeElement;
      const typing = el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' || el.isContentEditable);
      if (typing && !wantMod && el !== ref.current) return;
      e.preventDefault();
      ref.current?.focus();
      ref.current?.select?.();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [focusKey]);
}

function controlStyle(invalid, disabled, readOnly) {
  return {
    font: '13px/1.4 var(--font-sans)', color: 'var(--text)', width: '100%', boxSizing: 'border-box',
    padding: '7px 10px', borderRadius: 'var(--radius-md, 6px)',
    border: '1px solid ' + (invalid ? 'var(--color-destructive-fg)' : 'var(--border)'),
    background: readOnly ? 'var(--bg-2)' : 'var(--surface)',
    outline: 'none', transition: 'border-color .15s, box-shadow .15s',
    opacity: disabled ? 0.55 : 1, filter: disabled ? 'saturate(.6)' : 'none',
    cursor: disabled ? 'not-allowed' : readOnly ? 'default' : 'text',
  };
}
function focusRing(e, invalid) {
  e.currentTarget.style.borderColor = invalid ? 'var(--color-destructive-fg)' : 'var(--accent)';
  e.currentTarget.style.boxShadow = '0 0 0 3px ' + (invalid ? 'color-mix(in oklch, var(--color-destructive-fg) 18%, transparent)' : 'var(--accent-soft)');
}
function blurRing(e) { e.currentTarget.style.boxShadow = 'none'; e.currentTarget.style.borderColor = e.currentTarget.dataset.invalid === '1' ? 'var(--color-destructive-fg)' : 'var(--border)'; }

export function Input({ label, help, error, value, defaultValue, placeholder, type = 'text', disabled, readOnly, onChange, onKeyDown, name, id, icon, kbd, focusKey, autoFocus, inputRef }) {
  const invalid = !!error;
  const ownRef = React.useRef(null);
  const ref = inputRef || ownRef;
  useFocusKey(focusKey, ref);
  const pad = { ...controlStyle(invalid, disabled, readOnly) };
  if (icon) pad.paddingLeft = 30;
  if (kbd) pad.paddingRight = Math.max(28, 14 + kbd.length * 7);
  const ctrl = (
    <input ref={ref} id={id} name={name} type={type} value={value} defaultValue={defaultValue} placeholder={placeholder}
      disabled={disabled} readOnly={readOnly} onChange={onChange} onKeyDown={onKeyDown} autoFocus={autoFocus} data-invalid={invalid ? '1' : '0'}
      onFocus={(e) => focusRing(e, invalid)} onBlur={blurRing}
      style={pad} />
  );
  return fieldShell(label, error, help, adorn(ctrl, icon, kbd));
}

/** Search input preset — magnifier icon + `/` focus shortcut by default. */
export function SearchInput({ placeholder = 'Buscar…', icon, kbd = '/', focusKey = '/', ...rest }) {
  const glass = icon !== undefined ? icon : (
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
  );
  return <Input type="search" placeholder={placeholder} icon={glass} kbd={kbd} focusKey={focusKey} {...rest} />;
}

export function Textarea({ label, help, error, value, defaultValue, placeholder, rows = 3, disabled, readOnly, onChange, name }) {
  const invalid = !!error;
  const ctrl = (
    <textarea name={name} rows={rows} value={value} defaultValue={defaultValue} placeholder={placeholder}
      disabled={disabled} readOnly={readOnly} onChange={onChange} data-invalid={invalid ? '1' : '0'}
      onFocus={(e) => focusRing(e, invalid)} onBlur={blurRing}
      style={{ ...controlStyle(invalid, disabled, readOnly), resize: 'vertical', minHeight: 64, lineHeight: 1.5 }} />
  );
  return fieldShell(label, error, help, ctrl);
}

export function Select({ label, help, error, value, defaultValue, disabled, onChange, name, children, options }) {
  const invalid = !!error;
  const ctrl = (
    <select name={name} value={value} defaultValue={defaultValue} disabled={disabled} onChange={onChange}
      data-invalid={invalid ? '1' : '0'} onFocus={(e) => focusRing(e, invalid)} onBlur={blurRing}
      style={{ ...controlStyle(invalid, disabled, false), cursor: disabled ? 'not-allowed' : 'pointer', appearance: 'none',
        backgroundImage: 'url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23888\' stroke-width=\'2\'><path d=\'M6 9l6 6 6-6\'/></svg>")',
        backgroundRepeat: 'no-repeat', backgroundPosition: 'right 10px center', paddingRight: 28 }}>
      {options ? options.map((o) => <option key={o.value ?? o} value={o.value ?? o}>{o.label ?? o}</option>) : children}
    </select>
  );
  return fieldShell(label, error, help, ctrl);
}
