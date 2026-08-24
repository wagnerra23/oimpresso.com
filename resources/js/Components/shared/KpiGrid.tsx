import * as React from 'react';
import { cn } from '@/Lib/utils';

/**
 * KpiGrid — grid responsivo para KpiCards.
 *
 * cols define quantas colunas no desktop (breakpoint lg):
 *   2 → 1 col mobile, 2 tablet, 2 desktop
 *   3 → 1 col mobile, 2 tablet, 3 desktop
 *   4 → 1 col mobile, 2 tablet, 4 desktop (padrão)
 *   5 → 1 col mobile, 2 tablet, 5 desktop (Governance v4 saúde KPIs)
 *   6 → 2 col mobile, 3 tablet, 6 só em 2xl (≥1536)
 *
 * Por que o 6 sobe só em 2xl, e não em lg como os outros: medido em prod 2026-08-24,
 * o container do painel tem max-width ~1232px, então 6 colunas dão card de 195px e o
 * rótulo fica com 133px — menos que "Colaboradores ativos" (157px) e "Aprovações
 * pendentes" (159px), que são copy de contrato (ponto-painel.contract.json). A 1280
 * (monitor da ROTA LIVRE) o card caía pra 150px e 4 dos 6 rótulos truncavam. Em 3
 * colunas o card vai a 248-311px e tudo cabe. Reproduzir: medir scrollWidth vs
 * clientWidth do <span> do rótulo em /ponto e /governance/dashboard.
 *
 * Uso:
 *   <KpiGrid cols={4}>
 *     <KpiCard ... />
 *     <KpiCard ... />
 *     ...
 *   </KpiGrid>
 */
interface Props {
  cols?: 2 | 3 | 4 | 5 | 6;
  children: React.ReactNode;
  className?: string;
  /**
   * Âncora de Contrato de Tela (ADR 0286) — e ela precisa CHEGAR AO DOM.
   *
   * Sem esta prop declarada, o React descarta `data-contract` em silêncio: num
   * componente (não num elemento DOM) prop desconhecida não vira atributo. O
   * `Ponto/Dashboard/Index.tsx` passava `data-contract="painel-kpis"` desde o F3
   * e a seção NUNCA teve âncora na página — medido em prod 2026-08-24, onde só 3
   * das 4 âncoras do contrato `ponto-painel` existiam no DOM.
   *
   * O gate `contrato-de-tela` não pega isso por construção: ele lê o `.tsx` e
   * encontra a string, que de fato está lá. Presença na fonte ≠ presença no DOM.
   */
  'data-contract'?: string;
}

const colsMap: Record<NonNullable<Props['cols']>, string> = {
  2: 'grid-cols-1 sm:grid-cols-2',
  3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
  4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
  5: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-5',
  6: 'grid-cols-2 sm:grid-cols-3 2xl:grid-cols-6',
};

export default function KpiGrid({ cols = 4, children, className, ...rest }: Props) {
  return (
    <div
      data-slot="kpi-grid"
      className={cn('grid gap-3', colsMap[cols], className)}
      {...rest}
    >
      {children}
    </div>
  );
}
