// Onda 11 v9,75 — Sparkline SVG real (extraído de Index.tsx pra reuso cross-Pages).

interface Props {
  points: number[];
  color?: string;
  width?: number;
  height?: number;
}

export default function Sparkline({
  points,
  color = 'oklch(0.75 0.13 145)',
  width = 80,
  height = 24,
}: Props) {
  if (!points.length) return null;
  const max = Math.max(...points, 1);
  const min = Math.min(...points, 0);
  const range = Math.max(max - min, 1);
  const step = width / Math.max(points.length - 1, 1);
  const linePath = points
    .map((p, i) => {
      const x = i * step;
      const y = height - ((p - min) / range) * height;
      return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
    })
    .join(' ');
  const areaPath = `${linePath} L${width},${height} L0,${height} Z`;
  const gradId = `sparkG-${Math.abs(points.reduce((a, b) => a + b, 0) | 0)}-${color.slice(-6)}`;

  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none">
      <defs>
        <linearGradient id={gradId} x1="0" x2="0" y1="0" y2="1">
          <stop offset="0%" stopColor={color} stopOpacity="0.45" />
          <stop offset="100%" stopColor={color} stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={areaPath} fill={`url(#${gradId})`} />
      <path
        d={linePath}
        stroke={color}
        strokeWidth="1.5"
        fill="none"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}
