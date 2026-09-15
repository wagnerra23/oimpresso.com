/**
 * FsmStepper — canonical pipeline indicator (DS v4). Two variants:
 *   · inline — 6 small dots + current-phase mono label (table cells)
 *   · full   — numbered circles + connecting lines + labels (drawer/detail)
 * Pure, dependency-free (global React + inline token styles).
 *
 * steps: array of { label, state } where state ∈ 'done' | 'current' | 'todo' | 'term'.
 * hue:   OKLCH hue for the pipeline color (default 220 = blue; 295 = roxo brand).
 */
export function FsmStepper({ steps = [], variant = 'inline', hue = 220 }) {
  const color = `oklch(0.55 0.13 ${hue})`;
  const soft = `oklch(0.94 0.05 ${hue})`;
  const current = steps.find((s) => s.state === 'current');

  if (variant === 'full') {
    return (
      <ol style={{ listStyle: 'none', margin: 0, padding: '10px 14px', display: 'flex', alignItems: 'flex-start' }}>
        {steps.map((s, i) => {
          const done = s.state === 'done', cur = s.state === 'current';
          return (
            <li key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', position: 'relative' }}>
              {i < steps.length - 1 && (
                <span aria-hidden style={{
                  position: 'absolute', top: 11, left: '50%', right: '-50%', height: 2, zIndex: 0,
                  background: done ? color : 'var(--border-2)',
                }} />
              )}
              <span style={{
                position: 'relative', zIndex: 1, display: 'grid', placeItems: 'center',
                width: 24, height: 24, borderRadius: '50%', fontSize: 11, fontWeight: 600,
                border: '2px solid ' + (done || cur ? color : 'var(--border)'),
                background: done ? color : 'var(--surface)',
                color: done ? '#fff' : cur ? color : 'var(--text-mute)',
                boxShadow: cur ? '0 0 0 3px ' + soft : 'none',
              }} title={s.label + (s.meta ? ' · ' + s.meta : '')}>{done ? '✓' : i + 1}</span>
              <span style={{
                marginTop: 6, font: (cur ? '700' : '600') + ' 10.5px/1.3 var(--font-sans)',
                textTransform: 'uppercase', letterSpacing: '.04em', textAlign: 'center',
                color: cur ? color : (done ? 'var(--text)' : 'var(--text-mute)'),
              }}>{s.label}</span>
              {s.meta && <span style={{ marginTop: 3, font: '500 9px/1 var(--font-mono)', color: 'var(--text-mute)' }}>{s.meta}</span>}
            </li>
          );
        })}
      </ol>
    );
  }

  // inline
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
      {steps.map((s, i) => {
        const bg = s.state === 'term' ? 'var(--color-destructive)'
          : (s.state === 'done' || s.state === 'current') ? color : 'var(--border)';
        return (
          <span key={i} aria-hidden style={{
            width: 7, height: 7, borderRadius: '50%', display: 'inline-block', background: bg,
            boxShadow: s.state === 'current' ? '0 0 0 2px ' + soft : 'none',
          }} />
        );
      })}
      {current && (
        <span style={{
          marginLeft: 4, font: '600 10px/1 var(--font-mono)', color,
          textTransform: 'uppercase', letterSpacing: '.04em',
        }}>{current.label}</span>
      )}
    </span>
  );
}
