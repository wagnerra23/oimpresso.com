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

/**
 * Subnav UNIFICADA do Financeiro — fidelidade ao protótipo Cowork aprovado por
 * [W] 2026-06-29 (ADR 0313, supersede_partial da 0180). A 0180 acoplava a barra
 * de abas aos ghosts da entry ATIVA do sidebar (cada tela do Financeiro mostrava
 * só as abas da própria entry). Agora TODA tela do Financeiro renderiza a MESMA
 * barra (a do protótipo). O sidebar segue com as 4 entries flat da 0180
 * (preservado) e `pickFinanceiroEntry` continua resolvendo a entry ativa só pra
 * escolher o PRIMARY contextual por página.
 *
 * Ordem = protótipo (8 primeiras, visíveis com maxVisible=8). O restante são
 * destinos legacy que vão pro overflow `⋯` — nada se perde (Contas a Pagar/
 * Receber, Categorias, Caixa, Extrato, etc).
 */
export const FINANCEIRO_SUBNAV_GHOSTS: PageHeaderGhost[] = [
  { key: 'unificado',         label: 'Financeiro',            href: '/financeiro/unificado' },
  { key: 'cobranca',          label: 'Cobrança',              href: '/financeiro/cobranca' },
  { key: 'recurring-billing', label: 'Assinaturas',           href: '/recurring-billing' },
  { key: 'fluxo',             label: 'Fluxo de caixa',        href: '/financeiro/fluxo' },
  { key: 'conciliacao',       label: 'Conciliação',           href: '/financeiro/conciliacao' },
  { key: 'dre',               label: 'DRE / Relatórios',      href: '/financeiro/dre' },
  { key: 'plano-contas',      label: 'Plano de contas',       href: '/financeiro/plano-contas' },
  { key: 'impostos',          label: 'Impostos e obrigações', href: '/financeiro/impostos' },
  // — abaixo NÃO estão no protótipo: overflow `⋯` (maxVisible=8), nada se perde —
  { key: 'contas-pagar',      label: 'Contas a Pagar',        href: '/financeiro/contas-pagar' },
  { key: 'contas-receber',    label: 'Contas a Receber',      href: '/financeiro/contas-receber' },
  { key: 'relatorios',        label: 'Relatórios',            href: '/financeiro/relatorios' },
  { key: 'categorias',        label: 'Categorias',            href: '/financeiro/categorias' },
  { key: 'caixa',             label: 'Caixa',                 href: '/financeiro/caixa' },
  { key: 'extrato',           label: 'Extrato',               href: '/financeiro/extrato' },
  { key: 'contas-bancarias',  label: 'Contas Bancárias',      href: '/financeiro/contas-bancarias' },
  { key: 'contador',          label: 'Contador',              href: '/financeiro/configuracoes/contador' },
  { key: 'gateway',           label: 'Gateway',               href: '/settings/payment-gateways' },
];
