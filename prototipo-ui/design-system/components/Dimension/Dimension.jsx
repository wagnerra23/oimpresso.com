/**
 * Dimension — a technical measure line (cota) for print/proof layouts.
 * Spans its container; end caps + a centered mono label sitting on the surface.
 * orientation 'h' fills width; 'v' fills height (needs a flex/stretch parent).
 * Pure, dependency-free.
 *
 * value (e.g. "3.000 mm") · orientation 'h'|'v' · color · surface (label chip bg) · size (track thickness)
 */
export function Dimension({ value, orientation = 'h', color = 'var(--text-mute)', surface = 'var(--surface)', size = 22 }) {
  const labelFont = '600 10.5px/1 var(--font-mono)';
  const label = (extra) => (
    <span style={{
      position: 'absolute', top: '50%', left: '50%', whiteSpace: 'nowrap',
      background: surface, padding: '0 6px', font: labelFont, color: 'var(--text-dim)', ...extra,
    }}>{value}</span>
  );
  if (orientation === 'v') {
    return (
      <div style={{ position: 'relative', width: size, flex: 'none', alignSelf: 'stretch', height: '100%', minHeight: 40 }}>
        <div style={{ position: 'absolute', top: 0, bottom: 0, left: '50%', width: 1, background: color }} />
        <div style={{ position: 'absolute', top: 0, left: 'calc(50% - 4px)', width: 9, height: 1, background: color }} />
        <div style={{ position: 'absolute', bottom: 0, left: 'calc(50% - 4px)', width: 9, height: 1, background: color }} />
        {label({ transform: 'translate(-50%,-50%) rotate(-90deg)' })}
      </div>
    );
  }
  return (
    <div style={{ position: 'relative', height: size, width: '100%' }}>
      <div style={{ position: 'absolute', left: 0, right: 0, top: '50%', height: 1, background: color }} />
      <div style={{ position: 'absolute', left: 0, top: 'calc(50% - 6px)', width: 1, height: 12, background: color }} />
      <div style={{ position: 'absolute', right: 0, top: 'calc(50% - 6px)', width: 1, height: 12, background: color }} />
      {label({ transform: 'translate(-50%,-50%)' })}
    </div>
  );
}
