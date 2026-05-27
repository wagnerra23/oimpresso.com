import * as React from 'react';
import { cn } from '@/Lib/utils';

/**
 * KpiGrid — grid responsivo para KpiCards.
 *
 * cols define quantas colunas no desktop (breakpoint lg):
 *   2 → 1 col mobile, 2 tablet, 2 desktop
 *   3 → 1 col mobile, 2 tablet, 3 desktop
 *   4 → 1 col mobile, 2 tablet, 4 desktop (padrão)
 *   6 → 2 col mobile, 3 tablet, 6 desktop
 *
 * Uso:
 *   <KpiGrid cols={4}>
 *     <KpiCard ... />
 *     <KpiCard ... />
 *     ...
 *   </KpiGrid>
 */
interface Props {
  cols?: 2 | 3 | 4 | 6;
  children: React.ReactNode;
  className?: string;
}

const colsMap: Record<NonNullable<Props['cols']>, string> = {
  2: 'grid-cols-1 sm:grid-cols-2',
  3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
  4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
  6: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
};

export default function KpiGrid({ cols = 4, children, className }: Props) {
  return (
    <div data-slot="kpi-grid" className={cn('grid gap-3', colsMap[cols], className)}>
      {children}
    </div>
  );
}
