// FinStatStrip — KPI strip canon do Financeiro como COMPONENTE.
//
// Piloto do passo 2-3 do MANUAL-CSS-JS: extrai o padrão `.fin-stats`/`.fin-stat`
// (hoje CSS bespoke compartilhado em ~14 telas, def. em fin-cowork.css:117-227)
// pra um componente reutilizável em Tailwind + tokens. Reproduz FIELMENTE:
//   - grid 5-col `minmax(260px,1.6fr) repeat(4,1fr)` com reflow por CONTAINER
//     (@container finbody: ≤1100px → 4col + hero full; ≤600px → 2col)
//   - card branco, borda var(--fin-line), radius 8, pad 12/14, flex-col gap 2
//   - variante `hero` "documento contábil" warm-dark + override dark-mode
//   - tons pos/neg (oklch canon do fin-cowork)
//
// ⚠️ USAR dentro de um ancestral `.fin-curadoria` — ele fornece os tokens
//    `--fin-*` E o container `finbody/inline-size` que o reflow observa.
//
// Refs: MANUAL-CSS-JS passo 2-3 · ADR UI-0013 (Padrão de Tela → componente é a unidade)
//       fonte de verdade visual: resources/css/fin-cowork.css:117-227

import { type ReactNode } from 'react';

export type FinStatTone = 'pos' | 'neg' | 'neutral';

interface FinStatProps {
  label: string;
  value: ReactNode;
  hint?: ReactNode;
  tone?: FinStatTone;
  /** Card "documento contábil" warm-dark — 1 por strip, na primeira posição. */
  hero?: boolean;
}

// Cor do número (`<b>`) por tom. Espelha .fin-num-pos / .fin-num-neg do fin-cowork,
// com os valores mais claros quando dentro do hero (fundo escuro).
function toneClass(tone: FinStatTone, hero: boolean): string {
  if (tone === 'pos') return hero ? 'text-[oklch(0.78_0.14_145)]' : 'text-[oklch(0.45_0.18_145)]';
  if (tone === 'neg') return hero ? 'text-[oklch(0.78_0.14_25)]' : 'text-[oklch(0.50_0.18_25)]';
  return hero ? 'text-white' : 'text-[var(--fin-text)]';
}

export function FinStat({ label, value, hint, tone = 'neutral', hero = false }: FinStatProps) {
  const card = hero
    ? // .fin-stat-hero: warm-dark "documento contábil" + dark-backfill (raised surface)
      'relative overflow-hidden border-0 bg-[oklch(0.22_0.01_80)] text-white ' +
      'col-[1/-1] @max-[1100px]:col-span-full dark:border dark:border-[oklch(0.40_0.012_80)] dark:bg-[oklch(0.30_0.012_80)]'
    : 'border border-[var(--fin-line)] bg-white';

  return (
    <div className={`flex min-w-0 flex-col gap-0.5 rounded-lg px-3.5 py-3 ${card}`}>
      <small
        className={`text-[10px] font-semibold uppercase tracking-[0.06em] ${
          hero ? 'text-[oklch(0.75_0.01_80)] opacity-95' : 'text-[var(--fin-text-mute)]'
        }`}
      >
        {label}
      </small>
      <b
        className={`font-mono font-bold tracking-[-0.01em] ${hero ? 'text-[26px]' : 'text-[22px]'} ${toneClass(
          tone,
          hero,
        )}`}
      >
        {value}
      </b>
      {hint != null && (
        <span
          className={`text-[11px] ${
            hero ? 'text-[oklch(0.72_0.01_80)] opacity-85' : 'text-[var(--fin-text-mute)]'
          }`}
        >
          {hint}
        </span>
      )}
    </div>
  );
}

interface FinStatStripProps {
  children: ReactNode;
  className?: string;
}

/**
 * Grid do KPI strip. Reproduz `.fin-stats`: 5 colunas
 * `minmax(260px,1.6fr) repeat(4,1fr)`, reflow por container `finbody`
 * (≤1100px → 4 col; ≤600px → 2 col). Os `@max-*` são variantes de CONTAINER
 * do Tailwind v4 — observam o ancestral `.fin-curadoria` (container finbody).
 */
export default function FinStatStrip({ children, className = '' }: FinStatStripProps) {
  return (
    <div
      className={
        'mt-3 mb-2.5 grid w-full gap-2 ' +
        'grid-cols-[minmax(260px,1.6fr)_repeat(4,1fr)] ' +
        '@max-[1100px]:grid-cols-[repeat(4,1fr)] ' +
        '@max-[600px]:grid-cols-[repeat(2,1fr)] ' +
        className
      }
    >
      {children}
    </div>
  );
}
