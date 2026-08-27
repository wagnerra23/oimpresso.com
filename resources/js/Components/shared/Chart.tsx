import { useRef, useState } from 'react';

/**
 * Chart — porte 1:1 do `components/Chart/Chart.jsx` do Design System vivo
 * (bundle `_ds_bundle.js`, namespace OfficeImpressoPontoWR2DesignSystem_019dd0).
 *
 * SVG puro, ZERO dependência externa. Isso é o ponto: o `package.json` não tem
 * (nem precisa de) lib de gráfico — o que a Visão geral do protótipo usa é este
 * componente, e ele desenha `path`/`polyline`/`rect` na mão num viewBox 100×100
 * com `preserveAspectRatio: none`, de modo que o gráfico estica pro container.
 *
 * Fiel ao original em: geometria (pad 3 na área, 0 na barra · yAt reserva 6 no
 * rodapé e 12 no vão), largura de barra (0.6 do passo), gradiente 0.28 → 0.02,
 * hover por colunas invisíveis em flex, e tooltip com `label · valor`.
 *
 * Cor sai de token (`--accent` por padrão), nunca de literal.
 *
 * ÚNICO desvio declarado do original: `role="img"` + `aria-label` no `<svg>`. O DS não os
 * tem; foram somados aqui porque a tela passa pelo gate de a11y. É adição, não alteração
 * de render — nenhum pixel muda.
 */

export type ChartDatum = number | { label?: string; value: number };

export interface ChartProps {
  type?: 'area' | 'line' | 'bar';
  data: ChartDatum[];
  height?: number;
  /** token CSS; o default é o accent do tema */
  color?: string;
  strokeWidth?: number;
  /** destaca a última barra quando nada está sob o cursor (uso: ano fiscal) */
  highlightLast?: boolean;
  formatValue?: (v: number) => string;
}

export function Chart({
  type = 'area',
  data = [],
  height = 140,
  color = 'var(--accent)',
  strokeWidth = 2,
  highlightLast = false,
  formatValue,
}: ChartProps) {
  const [hi, setHi] = useState(-1);
  const gid = useRef('cg' + Math.random().toString(36).slice(2, 8)).current;

  const vals = data.map((d) => (typeof d === 'number' ? d : d?.value || 0));
  const labels = data.map((d) => (typeof d === 'number' ? '' : d?.label || ''));
  const n = vals.length;

  if (!n) return <div style={{ height }} />;

  const fmt = formatValue || ((v: number) => String(v));
  const max = Math.max(...vals, 1);
  const min = Math.min(...vals, 0);
  const span = max - min || 1;
  const W = 100;
  const H = 100;
  const pad = type === 'bar' ? 0 : 3;

  const xAt = (i: number) => (n <= 1 ? W / 2 : pad + (i / (n - 1)) * (W - 2 * pad));
  const yAt = (v: number) => H - 6 - ((v - min) / span) * (H - 12);

  const hovered = hi >= 0 && hi < n;
  // `noUncheckedIndexedAccess`: vals[hi] e `number | undefined` pro TS mesmo sob `hovered`.
  // Resolve UMA vez em vez de espalhar `!` pelos 3 usos.
  const vHi = hovered ? vals[hi] ?? 0 : 0;

  let shape: React.ReactNode;
  if (type === 'bar') {
    const bw = (W / n) * 0.6;
    shape = vals.map((v, i) => {
      const cx = (i + 0.5) * (W / n);
      const on = hi === i || (hi === -1 && highlightLast && i === n - 1);
      return (
        <rect
          key={i}
          x={cx - bw / 2}
          y={yAt(v)}
          width={bw}
          height={H - 6 - yAt(v)}
          rx={1.4}
          fill={on ? color : `color-mix(in oklch, ${color} 34%, transparent)`}
        />
      );
    });
  } else {
    const pts = vals.map((v, i) => `${xAt(i)},${yAt(v)}`).join(' ');
    const area = `M${xAt(0)},${H - 6} L${vals.map((v, i) => `${xAt(i)},${yAt(v)}`).join(' L')} L${xAt(n - 1)},${H - 6} Z`;
    shape = (
      <g>
        {type === 'area' && <path d={area} fill={`url(#${gid})`} />}
        <polyline
          points={pts}
          fill="none"
          stroke={color}
          strokeWidth={strokeWidth}
          strokeLinejoin="round"
          strokeLinecap="round"
          vectorEffect="non-scaling-stroke"
        />
      </g>
    );
  }

  return (
    <div style={{ position: 'relative', width: '100%', height }}>
      <svg
        viewBox={`0 0 ${W} ${H}`}
        preserveAspectRatio="none"
        style={{ width: '100%', height: '100%', display: 'block', overflow: 'visible' }}
        role="img"
        aria-label={`Gráfico de ${type === 'bar' ? 'barras' : 'área'} com ${n} pontos`}
      >
        {type === 'area' && (
          <defs>
            <linearGradient id={gid} x1={0} y1={0} x2={0} y2={1}>
              <stop offset="0%" stopColor={color} stopOpacity={0.28} />
              <stop offset="100%" stopColor={color} stopOpacity={0.02} />
            </linearGradient>
          </defs>
        )}
        {shape}
        {hovered && type !== 'bar' && (
          <line
            x1={xAt(hi)}
            y1={0}
            x2={xAt(hi)}
            y2={H - 6}
            stroke="var(--border)"
            strokeWidth={1}
            vectorEffect="non-scaling-stroke"
          />
        )}
        {hovered && type !== 'bar' && (
          <circle
            cx={xAt(hi)}
            cy={yAt(vHi)}
            r={2.5}
            fill={color}
            stroke="var(--surface)"
            strokeWidth={1.5}
            vectorEffect="non-scaling-stroke"
          />
        )}
      </svg>

      {/*
        Colunas invisíveis: capturam o hover sem sujar o SVG. É decoração de PONTEIRO —
        quem lê por AT já recebe o conteúdo pelo `aria-label` do <svg> acima, então esta
        camada some da árvore de acessibilidade em vez de virar um alvo sem semântica.
      */}
      <div
        role="presentation"
        aria-hidden="true"
        style={{ position: 'absolute', inset: 0, display: 'flex' }}
        onMouseLeave={() => setHi(-1)}
      >
        {vals.map((_, i) => (
          <div
            key={i}
            role="presentation"
            aria-hidden="true"
            onMouseEnter={() => setHi(i)}
            style={{ flex: 1 }}
          />
        ))}
      </div>

      {hovered && (
        <div
          style={{
            position: 'absolute',
            left: `${xAt(hi)}%`,
            top: `${(yAt(vHi) / H) * 100}%`,
            transform: 'translate(-50%, -130%)',
            background: 'var(--text)',
            color: 'var(--bg)',
            font: '600 11px/1 var(--font-mono)',
            padding: '4px 7px',
            borderRadius: 5,
            whiteSpace: 'nowrap',
            pointerEvents: 'none',
            boxShadow: '0 4px 12px -4px rgba(0,0,0,.4)',
          }}
        >
          {(labels[hi] ? `${labels[hi]} · ` : '') + fmt(vHi)}
        </div>
      )}
    </div>
  );
}

export default Chart;
