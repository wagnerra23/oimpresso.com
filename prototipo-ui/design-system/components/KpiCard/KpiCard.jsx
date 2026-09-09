/**
 * KpiCard — semantic KPI tile (DS v6). Pure, dependency-free (global React + inline tokens).
 *
 * Mirrors the Vendas / Financeiro KPI family:
 *   · tone: default | success | warning | danger | info  → tinted soft card
 *   · hero: true                                          → dark navy "feature" plate
 *   · spark: number[]                                     → inline sparkline (hero looks best)
 *   · unit:  small suffix after the value ("h", "/mês", "itens")
 *   · delta + deltaLabel                                  → ↗/↘ colored variation
 */
function Sparkline({ points, stroke, fill }) {
  if (!points || points.length < 2) return null;
  const W = 120, H = 32, max = Math.max(...points), min = Math.min(...points);
  const span = max - min || 1;
  const step = W / (points.length - 1);
  const xy = points.map((p, i) => [i * step, H - ((p - min) / span) * (H - 4) - 2]);
  const line = xy.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ');
  const area = line + ` L${W},${H} L0,${H} Z`;
  const id = 'sp' + Math.random().toString(36).slice(2, 8);
  return (
    <svg viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="none" style={{ width: '100%', height: 32, display: 'block', marginTop: 8 }} aria-hidden>
      <defs><linearGradient id={id} x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stopColor={fill} stopOpacity="0.35" />
        <stop offset="100%" stopColor={fill} stopOpacity="0" />
      </linearGradient></defs>
      <path d={area} fill={`url(#${id})`} />
      <path d={line} fill="none" stroke={stroke} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

const KPI_FILTER_TONE = {
  primary: ['color-mix(in oklch, var(--color-primary) 16%, transparent)', 'var(--color-primary)'],
  amber:   ['color-mix(in oklch, oklch(0.72 0.15 70) 18%, transparent)',  'oklch(0.80 0.13 70)'],
  rose:    ['color-mix(in oklch, oklch(0.65 0.20 20) 18%, transparent)',  'oklch(0.78 0.16 20)'],
  emerald: ['color-mix(in oklch, oklch(0.65 0.14 155) 18%, transparent)', 'oklch(0.78 0.12 155)'],
  violet:  ['color-mix(in oklch, oklch(0.60 0.18 295) 18%, transparent)', 'oklch(0.80 0.14 295)'],
};

/** Tile de KPI clicável usado como filtro (era o KpiFilterCard — fusão 2026-08). */
function KpiFilterTile({ label, value, sub, icon, tone = 'primary', selected = false, onClick }) {
  const [tileBg, tileFg] = KPI_FILTER_TONE[tone] || KPI_FILTER_TONE.primary;
  return (
    <button type="button" onClick={onClick} aria-pressed={selected} style={{
      display: 'flex', alignItems: 'center', gap: 12, width: '100%', textAlign: 'left',
      padding: 12, borderRadius: 8, cursor: 'pointer', background: 'var(--color-card)',
      border: '1px solid ' + (selected ? 'var(--color-primary)' : 'var(--color-border)'),
      boxShadow: selected ? '0 0 0 1px var(--color-primary)' : '0 1px 2px rgba(0,0,0,.05)',
      transition: 'box-shadow .15s, border-color .15s',
    }}>
      <span style={{ width: 36, height: 36, borderRadius: 8, flexShrink: 0, display: 'grid', placeItems: 'center', background: tileBg, color: tileFg }}>
        {typeof icon === 'string' ? <span style={{ fontSize: 17, lineHeight: 1 }} aria-hidden>{icon}</span> : icon}
      </span>
      <span style={{ minWidth: 0 }}>
        <span style={{ display: 'block', fontSize: 'var(--fs-1)', fontWeight: 600, letterSpacing: '.06em', textTransform: 'uppercase', color: 'var(--color-muted-foreground)', lineHeight: 1 }}>{label}</span>
        <span style={{ display: 'block', fontSize: 'var(--fs-6)', fontWeight: 600, fontVariantNumeric: 'tabular-nums', color: 'var(--color-foreground)', lineHeight: 1.2, marginTop: 4 }}>{value}</span>
        {sub && <span style={{ display: 'block', fontSize: 'var(--fs-1)', color: 'var(--color-muted-foreground)', marginTop: 2, lineHeight: 1 }}>{sub}</span>}
      </span>
    </button>
  );
}

export function KpiCard({ label, value, unit, description, tone = 'default', hero = false, delta, deltaLabel, spark, progress, variant, sub, icon, selected, onClick }) {
  if (variant === 'filter') return KpiFilterTile({ label, value, sub: sub != null ? sub : description, icon, tone: tone === 'default' ? 'primary' : tone, selected, onClick });
  const bar = (track, fill) => progress == null ? null : (
    <div style={{ height: 4, borderRadius: 99, background: track, overflow: 'hidden', marginTop: 4 }}>
      <div style={{ width: Math.max(0, Math.min(1, progress)) * 100 + '%', height: '100%', background: fill, borderRadius: 99 }} />
    </div>
  );
  const up = delta != null && delta >= 0;

  if (hero) {
    return (
      <div style={{
        display: 'flex', flexDirection: 'column', gap: 6,
        padding: 16, borderRadius: 12,
        background: 'var(--color-kpi-feature-bg)',
        border: '1px solid var(--color-kpi-feature-line)',
        color: 'var(--color-kpi-feature-fg)',
        boxShadow: 'var(--sh-1)',
      }}>
        <span style={{ fontSize: 'var(--fs-1)', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '.06em', color: 'var(--color-kpi-feature-fg-2)' }}>{label}</span>
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, flexWrap: 'wrap' }}>
          <span style={{ fontSize: 'var(--fs-8)', fontWeight: 700, lineHeight: 1, fontVariantNumeric: 'tabular-nums' }}>
            {value}{unit && <small style={{ fontSize: 'var(--fs-4)', fontWeight: 500, marginLeft: 3, color: 'var(--color-kpi-feature-fg-2)' }}>{unit}</small>}
          </span>
          {delta != null && (
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 3, fontSize: 'var(--fs-2)', fontWeight: 600, fontVariantNumeric: 'tabular-nums', color: up ? 'oklch(0.80 0.11 162)' : 'oklch(0.78 0.13 25)' }}>
              {up ? '↗' : '↘'} {up ? '+' : ''}{delta}{deltaLabel && <span style={{ marginLeft: 3, fontWeight: 400, color: 'var(--color-kpi-feature-fg-2)' }}>{deltaLabel}</span>}
            </span>
          )}
        </div>
        {description && <span style={{ fontSize: 'var(--fs-2)', color: 'var(--color-kpi-feature-fg-2)' }}>{description}</span>}
        {bar('color-mix(in oklch, var(--color-kpi-feature-fg) 16%, transparent)', 'var(--color-kpi-spark)')}
        <Sparkline points={spark} stroke="var(--color-kpi-spark)" fill="var(--color-kpi-spark)" />
      </div>
    );
  }

  const TONE = {
    default: { border: 'var(--color-border)', bg: 'var(--color-card)', spark: 'var(--color-primary)' },
    success: { border: 'color-mix(in oklch, var(--color-success) 20%, transparent)', bg: 'color-mix(in oklch, var(--color-success) 5%, transparent)', spark: 'var(--color-success)' },
    warning: { border: 'color-mix(in oklch, var(--color-warning) 20%, transparent)', bg: 'color-mix(in oklch, var(--color-warning) 5%, transparent)', spark: 'var(--color-warning)' },
    danger:  { border: 'color-mix(in oklch, var(--color-destructive) 20%, transparent)', bg: 'color-mix(in oklch, var(--color-destructive) 5%, transparent)', spark: 'var(--color-destructive)' },
    info:    { border: 'color-mix(in oklch, var(--color-info) 20%, transparent)', bg: 'color-mix(in oklch, var(--color-info) 5%, transparent)', spark: 'var(--color-info)' },
  };
  const t = TONE[tone] || TONE.default;
  return (
    <div style={{
      display: 'flex', flexDirection: 'column', gap: 6,
      padding: 14, borderRadius: 12,
      border: '1px solid ' + t.border, background: t.bg,
      boxShadow: '0 1px 2px rgba(0,0,0,.04)',
    }}>
      <span style={{ fontSize: 'var(--fs-1)', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '.05em', color: 'var(--color-muted-foreground)' }}>{label}</span>
      <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
        <span style={{ fontSize: 'var(--fs-7)', fontWeight: 700, lineHeight: 1.1, fontVariantNumeric: 'tabular-nums', color: 'var(--color-foreground)' }}>
          {value}{unit && <small style={{ fontSize: 'var(--fs-4)', fontWeight: 500, marginLeft: 2, color: 'var(--color-muted-foreground)' }}>{unit}</small>}
        </span>
        {delta != null && (
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 2, fontSize: 'var(--fs-2)', fontWeight: 500, fontVariantNumeric: 'tabular-nums', color: up ? 'var(--color-success)' : 'var(--color-destructive)' }}>
            {up ? '↗' : '↘'} {up ? '+' : ''}{delta}{deltaLabel && <span style={{ marginLeft: 4, color: 'var(--color-muted-foreground)', fontWeight: 400 }}>{deltaLabel}</span>}
          </span>
        )}
      </div>
      {description && <span style={{ fontSize: 'var(--fs-2)', color: 'var(--color-muted-foreground)' }}>{description}</span>}
      {bar('var(--color-muted)', t.spark)}
      {spark && <Sparkline points={spark} stroke={t.spark} fill={t.spark} />}
    </div>
  );
}
