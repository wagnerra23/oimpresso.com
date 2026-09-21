// JanaKpiGrid — RÉPLICA do `.jc-kpis` da âncora, sob ADR 0388 ("réplica primeiro").
//
// Âncora: `node scripts/design/ancora.mjs Jana/Index` -> `prototipo-ui/cowork/Wagner/jana-merge.jsx`
// §`data.kpis.map` (markup) + `chat-jana.css` §`── KPIs ──` (estilo). Re-localize com
// `grep -n "jc-kpis" prototipo-ui/cowork/Wagner/chat-jana.css` — hoje `:139` e `:444`.
//
//   .jc-kpis{ display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:18px }
//   @media (max-width:1100px){ .jc-kpis{ grid-template-columns:repeat(2,1fr) } }
//
// Repare no que a âncora NÃO tem: degrau de mobile. Abaixo de 1100px ela é 2 colunas até o
// fim — nunca 1.
//
// ── POR QUE UM COMPONENTE, e não `className` no `KpiGrid` compartilhado ───────────────
// Tentado primeiro, e MEDIDO INERTE — duas vezes. O `colsMap[4]` do shared emite
// `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`, e no Tailwind 4 os variants ARBITRÁRIOS
// (`min-[…]`, `max-[…]`) são emitidos ANTES dos nomeados (`sm:`, `lg:`). Como a
// especificidade é a mesma, vence quem vem depois — ou seja, sempre o `colsMap`. Medido no
// CSS buildado (`public/build-inertia/assets/app-*.css`), nas duas tentativas:
//
//   .max-[1100px]:grid-cols-2   @264896   <   .lg:grid-cols-4   @271781   -> lg vence
//   .min-[1101px]:grid-cols-4   @264997   <   .lg:grid-cols-2   @271756   -> lg vence
//
// Reproduzir: buildar e comparar os offsets dos dois seletores no MESMO arquivo. Enquanto o
// `colsMap` estiver no meio, arbitrary variant ali é decoração — passa em typecheck, em lint
// e no CI, e não muda um pixel (LC-30).
//
// Sem o `colsMap` competindo sobra só `grid-cols-2` (base, sem media query) contra
// `min-[1101px]:grid-cols-4` (dentro da media) — e aí a ordem funciona a favor: a base vem
// muito antes, a arbitrária vem depois e vence acima de 1101px, que é exatamente o degrau da
// âncora.
//
// Mexer no `colsMap` do `KpiGrid` resolveria tecnicamente e está FORA de questão: ele serve
// 37 telas com o degrau PT-04, e a forma da Jana não se impõe ao resto do ERP — mesma razão
// que fez o `JanaKpiCard` nascer réplica em vez de ajuste no `KpiCard` (ADR 0388 §D-1), e que
// o `SectionTitle` seguiu em 2026-09-18.
//
// ATENCAO: ESTE COMPONENTE É DE APARÊNCIA. Ele não decide dado, rota nem permissão — ADR 0388 §D-5.
//
// ⚠️ O `margin-bottom: 18px` da âncora NÃO está aqui, e é decisão declarada: em produção o
// espaço abaixo do grid é 16px e vem do `space-y-4` do container pai (medido no DOM:
// `gridMarginBottom: 16px` · `paiClasses: "space-y-4"` · `irmaoMarginTop: 0px`). Uma utility
// `mb-[18px]` seria inerte — a regra do pai tem especificidade (0,2,0) contra (0,1,0) da
// utility. Fechar os 2px exige tocar o `space-y-4`, que governa TODAS as seções da tela.
// Medido e aberto, registrado no `Index.casos.md` §UC-JPAIN-30.

import * as React from 'react';
import { cn } from '@/Lib/utils';

interface Props {
  children: React.ReactNode;
  className?: string;
  /**
   * Âncora de Contrato de Tela (ADR 0286) — precisa ser declarada pra CHEGAR ao DOM.
   * Em componente (não elemento), prop desconhecida é descartada em silêncio pelo React;
   * o `KpiGrid` compartilhado documenta o mesmo cuidado, pela mesma razão medida.
   */
  'data-contract'?: string;
}

export default function JanaKpiGrid({ children, className, ...rest }: Props) {
  return (
    <div
      data-slot="kpi-grid"
      className={cn('grid grid-cols-2 gap-2.5 min-[1101px]:grid-cols-4', className)}
      {...rest}
    >
      {children}
    </div>
  );
}
