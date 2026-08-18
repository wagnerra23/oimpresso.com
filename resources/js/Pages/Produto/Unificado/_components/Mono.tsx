/**
 * Célula mono do catálogo — código, dinheiro, saldo, margem.
 *
 * Arquivo próprio porque `Colunas.tsx` exporta REGRA (colunas/células por autorização) e não
 * componente: misturar os dois quebra o fast-refresh e embaralha o que aquele arquivo é.
 *
 * `tabular-nums` para o número não dançar ao trocar de aba ou reordenar.
 */

import type { ReactNode } from 'react';

export function Mono({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <span className={'font-mono tabular-nums whitespace-nowrap ' + className}>{children}</span>;
}

export default Mono;
