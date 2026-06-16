// Seleção da entry do grupo FINANÇAS pra montar o sub-nav do Financeiro.
//
// ADR 0180 (2026-05-26) dividiu o grupo FINANÇAS em 4 entries FLAT (Caixa / Cobrança /
// Financeiro / Cobrança Recorrente), cada uma com seus próprios ghosts e keys ÚNICAS.
// O `.find(group === 'financas')` antigo (em FinanceiroSubNav) pegava SEMPRE a 1ª entry
// do grupo (Caixa) → toda página de Cobrança/Financeiro (Unificado, Fluxo, DRE, Impostos,
// Plano de Contas, Contas a Pagar…) renderizava as abas do CAIXA e não destacava nada.
// Regressão silenciosa desde o split (só as 4 telas do próprio Caixa funcionavam, por
// sorte de ser a 1ª). Detectada em prod 2026-06-16 (WR2 Sistemas vs cache antigo do ROTA LIVRE).
//
// Módulo PURO (só `import type` → zero runtime React) pra ser testável direto:
// tests/financeiroSubNav.spec.ts.

import type { PageHeaderGhost, PageHeaderPrimary } from '@/Components/shared/PageHeaderTabs';

export type FinMenuEntry = {
  label: string;
  group?: string;
  primary?: PageHeaderPrimary;
  ghosts?: PageHeaderGhost[];
};

/**
 * Seleciona a entry DONA do `active` (a que tem um ghost com esse key — keys são únicas
 * por entry pós-ADR 0180); fallback: a entry "Financeiro" (hub) e, por último, a 1ª do
 * grupo FINANÇAS. Devolve `undefined` se não há entry de FINANÇAS (ex sem permissão).
 */
export function pickFinanceiroEntry(
  menu: FinMenuEntry[] | undefined,
  active: string,
): FinMenuEntry | undefined {
  const fin = (menu ?? []).filter(
    (m) => m.group === 'fin-op' || m.group === 'financas' || m.label?.toLowerCase() === 'financeiro',
  );
  return (
    fin.find((m) => m.ghosts?.some((g) => g.key === active)) ??
    fin.find((m) => m.label?.toLowerCase() === 'financeiro') ??
    fin[0]
  );
}
