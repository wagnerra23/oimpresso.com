/**
 * Chart — compact data-viz primitive (DS premium). Responsive SVG line / area /
 * bar with hover guide + tooltip. Axis-less by design (operational dashboards).
 * Pure, dependency-free (global React + inline tokens).
 *
 * type 'area'|'line'|'bar'  ·  data: number[] | {label,value}[]  ·  height (px)
 * color (CSS)  ·  strokeWidth  ·  highlightLast (bar)  ·  formatValue(v)=>string
 */
export function Chart({ type = 'area', data = [], height = 140, color = 'var(--accent)', strokeWidth = 2, highlightLast = false, formatValue }) {
  const h = React.createElement;
  const Hpx = typeof height === 'number' ? height : (parseInt(height, 10) || 140);
  const sw = typeof strokeWidth === 'number' ? strokeWidth : (parseFloat(strokeWidth) || 2);
  const [hi, setHi] = React.useState(-1);
  const gid = React.useRef('cg' + Math.random().toString(36).slice(2, 8)).current;
  const vals = data.map((d) => (typeof d === 'number' ? d : (d && d.value) || 0));
  const labels = data.map((d) => (typeof d === 'number' ? '' : (d && d.label) || ''));
  const n = vals.length;
  if (!n) return h('div', { style: { height: Hpx } });
  const fmt = formatValue || ((v) => String(v));
  const max = Math.max.apply(null, vals.concat([1]));
  const min = Math.min.apply(null, vals.concat([0]));
  const span = max - min || 1;
  const W = 100, H = 100, pad = type === 'bar' ? 0 : 3;
  const xAt = (i) => (n <= 1 ? W / 2 : pad + (i / (n - 1)) * (W - 2 * pad));
  const yAt = (v) => H - 6 - ((v - min) / span) * (H - 12);

  let shape;
  if (type === 'bar') {
    const bw = (W / n) * 0.6;
    shape = vals.map((v, i) => {
      const cx = (i + 0.5) * (W / n);
      const on = hi === i || (hi === -1 && highlightLast && i === n - 1);
      return h('rect', { key: i, x: cx - bw / 2, y: yAt(v), width: bw, height: H - 6 - yAt(v), rx: 1.4,
        fill: on ? color : 'color-mix(in oklch, ' + color + ' 34%, transparent)' });
    });
  } else {
    const pts = vals.map((v, i) => xAt(i) + ',' + yAt(v)).join(' ');
    const area = 'M' + xAt(0) + ',' + (H - 6) + ' L' + vals.map((v, i) => xAt(i) + ',' + yAt(v)).join(' L') + ' L' + xAt(n - 1) + ',' + (H - 6) + ' Z';
    shape = h('g', null,
      type === 'area' && h('path', { d: area, fill: 'url(#' + gid + ')' }),
      h('polyline', { points: pts, fill: 'none', stroke: color, strokeWidth: sw, strokeLinejoin: 'round', strokeLinecap: 'round', vectorEffect: 'non-scaling-stroke' }));
  }

  const hovered = hi >= 0 && hi < n;
  return h('div', { style: { position: 'relative', width: '100%', height: Hpx } },
    h('svg', { viewBox: '0 0 ' + W + ' ' + H, preserveAspectRatio: 'none', style: { width: '100%', height: '100%', display: 'block', overflow: 'visible' } },
      type === 'area' && h('defs', null, h('linearGradient', { id: gid, x1: 0, y1: 0, x2: 0, y2: 1 },
        h('stop', { offset: '0%', stopColor: color, stopOpacity: 0.28 }), h('stop', { offset: '100%', stopColor: color, stopOpacity: 0.02 }))),
      shape,
      hovered && type !== 'bar' && h('line', { x1: xAt(hi), y1: 0, x2: xAt(hi), y2: H - 6, stroke: 'var(--border)', strokeWidth: 1, vectorEffect: 'non-scaling-stroke' }),
      hovered && type !== 'bar' && h('circle', { cx: xAt(hi), cy: yAt(vals[hi]), r: 2.5, fill: color, stroke: 'var(--surface)', strokeWidth: 1.5, vectorEffect: 'non-scaling-stroke' })),
    // hover columns
    h('div', { style: { position: 'absolute', inset: 0, display: 'flex' }, onMouseLeave: () => setHi(-1) },
      vals.map((v, i) => h('div', { key: i, onMouseEnter: () => setHi(i), style: { flex: 1 } }))),
    // tooltip
    hovered && h('div', { style: {
      position: 'absolute', left: xAt(hi) + '%', top: (yAt(vals[hi]) / H * 100) + '%', transform: 'translate(-50%, -130%)',
      background: 'var(--text)', color: 'var(--bg)', font: '600 11px/1 var(--font-mono)', padding: '4px 7px', borderRadius: 5,
      whiteSpace: 'nowrap', pointerEvents: 'none', boxShadow: '0 4px 12px -4px rgba(0,0,0,.4)',
    } }, (labels[hi] ? labels[hi] + ' · ' : '') + fmt(vals[hi])));
}
