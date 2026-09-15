/**
 * ProofFrame — a card framed as a press proof sheet (DS print-craft primitive).
 * Crop marks at the four corners + an optional faint proof grid. Wrap any content
 * to give it the "folha de prova" identity. Pure, dependency-free.
 *
 * cropMarks (default true) · grid (default true) · padding (px) · radius (CSS)
 */
export function ProofFrame({ children, cropMarks = true, grid = true, padding = 26, radius = 'var(--radius-lg, 8px)', mark = 14, inset = 8 }) {
  const tick = (s, k) => <span key={k} aria-hidden style={{ position: 'absolute', background: 'var(--text-mute)', ...s }} />;
  const gridStyle = grid ? {
    '--proof-grid': 'color-mix(in oklch, var(--text) 5%, transparent)',
    backgroundImage: 'linear-gradient(var(--proof-grid) 1px, transparent 1px), linear-gradient(90deg, var(--proof-grid) 1px, transparent 1px)',
    backgroundSize: '22px 22px',
    backgroundPosition: '-1px -1px',
  } : {};
  return (
    <div style={{
      position: 'relative', background: 'var(--surface)', border: '1px solid var(--border)',
      borderRadius: radius, boxShadow: '0 1px 2px rgba(0,0,0,.04)', ...gridStyle,
    }}>
      {cropMarks && [
        tick({ left: inset, top: inset, width: mark, height: 1.5 }, 'tl-h'),
        tick({ left: inset, top: inset, width: 1.5, height: mark }, 'tl-v'),
        tick({ right: inset, top: inset, width: mark, height: 1.5 }, 'tr-h'),
        tick({ right: inset, top: inset, width: 1.5, height: mark }, 'tr-v'),
        tick({ left: inset, bottom: inset, width: mark, height: 1.5 }, 'bl-h'),
        tick({ left: inset, bottom: inset, width: 1.5, height: mark }, 'bl-v'),
        tick({ right: inset, bottom: inset, width: mark, height: 1.5 }, 'br-h'),
        tick({ right: inset, bottom: inset, width: 1.5, height: mark }, 'br-v'),
      ]}
      <div style={{ padding }}>{children}</div>
    </div>
  );
}
