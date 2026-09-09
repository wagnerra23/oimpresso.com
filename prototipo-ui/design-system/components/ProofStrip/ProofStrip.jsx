/**
 * ProofStrip — a press control strip (tira de controle) for proof layouts.
 * 'density' renders a grayscale step wedge (calibration); 'cmyk' renders the
 * four process-ink swatches. Decorative depiction of the printed piece — aria-hidden.
 * Pure, dependency-free.
 *
 * kind 'density'|'cmyk' · steps (density count) · height · swatch (width per cell)
 */
export function ProofStrip({ kind = 'density', steps = 5, height = 9, swatch = 13 }) {
  let colors;
  if (kind === 'cmyk') {
    colors = ['oklch(0.72 0.13 220)', 'oklch(0.62 0.24 350)', 'oklch(0.88 0.17 100)', 'oklch(0.25 0 0)'];
  } else {
    const n = Math.max(2, steps);
    colors = Array.from({ length: n }, (_, i) => {
      const L = 0.96 - (i * (0.96 - 0.20) / (n - 1));
      return 'oklch(' + L.toFixed(2) + ' 0 0)';
    });
  }
  return (
    <div aria-hidden style={{ display: 'inline-flex', border: '1px solid var(--border)', borderRadius: 2, overflow: 'hidden' }}>
      {colors.map((c, i) => <span key={i} style={{ width: swatch, height, background: c }} />)}
    </div>
  );
}
