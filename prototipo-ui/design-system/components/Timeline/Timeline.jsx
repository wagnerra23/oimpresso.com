/**
 * Timeline — trilha de auditoria / histórico de eventos (DS). Trilho vertical
 * com marcadores por tom, agrupamento por dia, ator + ação + detalhe,
 * pares campo→valor (de/para) e horário monoespaçado.
 *
 * entries: [{ id, time, day?, actor?, action, detail?, tone?, icon?,
 *             changes?: [{ field, from?, to? }], meta?: [{ label, value }] }]
 */
const TL_TONE = {
  default: 'var(--text-mute)',
  accent: 'var(--accent)',
  success: 'var(--pos, var(--color-success))',
  warning: 'var(--warn, var(--color-warning))',
  danger: 'var(--neg, var(--color-destructive))',
};

export function Timeline({ entries = [], dense = false, groupByDay = false, emptyLabel = 'Sem eventos registrados' }) {
  if (!entries.length) {
    return <p style={{ margin: 0, padding: '18px 0', textAlign: 'center', font: '400 12.5px/1.4 var(--font-sans)', color: 'var(--text-mute)' }}>{emptyLabel}</p>;
  }

  const groups = [];
  if (groupByDay) {
    entries.forEach((e) => {
      const key = e.day || '—';
      const last = groups[groups.length - 1];
      if (last && last.day === key) last.items.push(e); else groups.push({ day: key, items: [e] });
    });
  } else {
    groups.push({ day: null, items: entries });
  }

  const gapY = dense ? 10 : 16;

  const item = (e, isLast) => {
    const color = TL_TONE[e.tone] || TL_TONE.default;
    return (
      <li key={e.id} style={{ position: 'relative', paddingLeft: 26, paddingBottom: isLast ? 0 : gapY }}>
        <span aria-hidden style={{ position: 'absolute', left: 6, top: 14, bottom: isLast ? 'auto' : -2, height: isLast ? 0 : 'auto', width: 1, background: 'var(--border)' }} />
        <span aria-hidden style={{
          position: 'absolute', left: 0, top: 4, width: 13, height: 13, borderRadius: 99,
          display: 'grid', placeItems: 'center', background: 'var(--surface)',
          border: '2px solid ' + color, color,
        }}>{e.icon}</span>
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, flexWrap: 'wrap' }}>
          <span style={{ font: '400 12.5px/1.35 var(--font-sans)', color: 'var(--text)' }}>
            {e.actor && <b style={{ fontWeight: 600 }}>{e.actor}</b>}
            {e.actor && ' '}
            {e.action}
          </span>
          <time style={{ font: '400 11px/1 var(--font-mono)', color: 'var(--text-mute)', fontVariantNumeric: 'tabular-nums' }}>{e.time}</time>
        </div>
        {e.detail && <p style={{ margin: '2px 0 0', font: '400 11.5px/1.45 var(--font-sans)', color: 'var(--text-dim)', textWrap: 'pretty' }}>{e.detail}</p>}
        {e.changes && e.changes.length > 0 && (
          <ul style={{ listStyle: 'none', margin: '5px 0 0', padding: 0, display: 'grid', gap: 3 }}>
            {e.changes.map((c, i) => (
              <li key={i} style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap', font: '400 11px/1.4 var(--font-sans)', color: 'var(--text-mute)' }}>
                <span style={{ font: '600 10px/1.4 var(--font-sans)', textTransform: 'uppercase', letterSpacing: '.05em' }}>{c.field}</span>
                {c.from != null && <span style={{ padding: '1px 5px', borderRadius: 4, background: 'var(--bg-2)', color: 'var(--text-dim)', textDecoration: 'line-through', fontFamily: 'var(--font-mono)' }}>{c.from}</span>}
                <span aria-hidden>→</span>
                <span style={{ padding: '1px 5px', borderRadius: 4, background: 'var(--accent-soft)', color: 'var(--accent)', fontFamily: 'var(--font-mono)', fontWeight: 600 }}>{c.to}</span>
              </li>
            ))}
          </ul>
        )}
        {e.meta && e.meta.length > 0 && (
          <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 4 }}>
            {e.meta.map((m, i) => (
              <span key={i} style={{ font: '400 10.5px/1.4 var(--font-sans)', color: 'var(--text-mute)' }}>
                {m.label}: <span style={{ fontFamily: 'var(--font-mono)', color: 'var(--text-dim)' }}>{m.value}</span>
              </span>
            ))}
          </div>
        )}
      </li>
    );
  };

  return (
    <div style={{ display: 'grid', gap: dense ? 12 : 16 }}>
      {groups.map((g, gi) => (
        <section key={gi}>
          {g.day && (
            <h4 style={{ margin: '0 0 8px', font: '600 10px/1 var(--font-sans)', textTransform: 'uppercase', letterSpacing: '.08em', color: 'var(--text-mute)' }}>{g.day}</h4>
          )}
          <ol style={{ listStyle: 'none', margin: 0, padding: 0 }}>
            {g.items.map((e, i) => item(e, gi === groups.length - 1 && i === g.items.length - 1))}
          </ol>
        </section>
      ))}
    </div>
  );
}
