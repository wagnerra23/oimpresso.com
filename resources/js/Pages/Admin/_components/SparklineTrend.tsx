import * as React from 'react';
import { cn } from '@/Lib/utils';

/**
 * SparklineTrend — SVG inline 30 pontos (sem lib externa)
 *
 * Wave 29 (W29-C). Pattern leve copiado do blueprint kb_v2 — zero JS chart
 * lib, evita network call extra. Renderiza linha + área preenchida + último
 * ponto destacado. Tokens canon (semantic colors via stroke/fill em currentColor).
 */
interface Props {
  values: number[];
  width?: number;
  height?: number;
  /** className aplica em <svg>; cor controlada via text-{token} parent */
  className?: string;
  /** se true desenha fill sombreado sob a linha */
  showArea?: boolean;
  ariaLabel?: string;
}

export default function SparklineTrend({
  values,
  width = 120,
  height = 28,
  className,
  showArea = true,
  ariaLabel = 'Tendência últimos 30 pontos',
}: Props) {
  if (!values || values.length === 0) {
    return (
      <div
        className={cn(
          'text-[10px] text-muted-foreground italic',
          className,
        )}
        style={{ width, height }}
        aria-label="sem dados"
      >
        sem dados
      </div>
    );
  }

  const pts = values.length === 1 ? [values[0], values[0]] : values;
  const min = Math.min(...pts);
  const max = Math.max(...pts);
  const range = max - min || 1;

  const padX = 1.5;
  const padY = 2;
  const w = width - padX * 2;
  const h = height - padY * 2;

  const coords = pts.map((v, i) => {
    const x = padX + (i / (pts.length - 1)) * w;
    const y = padY + h - ((v - min) / range) * h;
    return [x, y] as const;
  });

  const linePath = coords
    .map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`)
    .join(' ');

  const areaPath = showArea
    ? `${linePath} L${coords[coords.length - 1][0].toFixed(2)},${(padY + h).toFixed(2)} L${coords[0][0].toFixed(2)},${(padY + h).toFixed(2)} Z`
    : '';

  const last = coords[coords.length - 1];

  return (
    <svg
      role="img"
      aria-label={ariaLabel}
      viewBox={`0 0 ${width} ${height}`}
      width={width}
      height={height}
      className={cn('text-foreground/80', className)}
    >
      {showArea && (
        <path
          d={areaPath}
          fill="currentColor"
          fillOpacity="0.12"
          stroke="none"
        />
      )}
      <path
        d={linePath}
        stroke="currentColor"
        strokeWidth="1.25"
        fill="none"
        strokeLinejoin="round"
        strokeLinecap="round"
      />
      <circle cx={last[0]} cy={last[1]} r="1.75" fill="currentColor" />
    </svg>
  );
}
