// FinanceiroSubNav (ADR 0180 Fase 5 — extraído como shared 2026-05-21)
//
// Lê primary/ghosts da entry do grupo FINANÇAS DONA do `active` do shell.menu (Inertia
// shared prop populado via LegacyMenuAdapter — Fase 4 piloto declarou attrs no
// Modules/Financeiro/Http/Controllers/DataController). A seleção da entry vive em
// ./financeiroMenu (pickFinanceiroEntry — pós-split ADR 0180 são 4 entries). Renderiza
// ghost tabs ARIA tablist abaixo do header `os-page-h` custom da tela.
//
// Active prop = key do ghost atual (ex 'unificado' em Unificado/Index.tsx,
// 'contas-receber' em ContasReceber/Index.tsx, etc). Fallback: nada renderiza
// se shell.menu não tem entry "Financeiro" com ghosts (ex usuário sem permissão
// financeiro.access ou Modules/Financeiro desinstalado).
//
// Pattern aprovado por Wagner 2026-05-21 (PR #1365 — Unificado piloto):
// ghost tabs (esquerda) + ⋯ Mais (overflow) + primary `+ Novo título` (direita).
// Caller pode passar `hidePrimary` pra renderizar o primary separadamente.

import { usePage } from '@inertiajs/react';
import PageHeaderTabs, { type PageHeaderOverflowItem } from '@/Components/shared/PageHeaderTabs';
import { pickFinanceiroEntry, FINANCEIRO_SUBNAV_GHOSTS, type FinMenuEntry } from './financeiroMenu';

export interface FinanceiroSubNavProps {
  active: string;
  /** Ações features-específicas (Resumir/Fechamento/Apresentar/etc) que vão pro overflow `⋯ Mais` */
  extraOverflowItems?: PageHeaderOverflowItem[];
  /** Quando true, omite primary (renderiza só ghosts + overflow). Caller renderiza primary separado à direita. */
  hidePrimary?: boolean;
  /**
   * Abas visíveis antes do overflow `⋯`. Default 5 = cabe INLINE na faixa do
   * PageHeader (título + lente + primary) sem espremer o título. A Unificada
   * renderiza a subnav em LINHA PRÓPRIA full-width (ADR 0313) e passa 8 pra
   * mostrar todas as abas do protótipo. As demais telas (subnav inline) ficam
   * no default 5 até migrarem pra linha-própria.
   */
  maxVisible?: number;
}

export default function FinanceiroSubNav({ active, extraOverflowItems, hidePrimary, maxVisible = 5 }: FinanceiroSubNavProps) {
  const sharedShell = (usePage().props as any)?.shell as { menu?: FinMenuEntry[] } | undefined;

  const finItem = pickFinanceiroEntry(sharedShell?.menu, active);

  if (!finItem?.ghosts?.length) return null;

  // ADR 0313 (fidelidade protótipo Cowork [W] 2026-06-29, supersede_partial 0180):
  // a barra de abas agora é UNIFICADA (FINANCEIRO_SUBNAV_GHOSTS) — toda tela do
  // Financeiro mostra a MESMA barra do protótipo, não mais os ghosts da entry ativa.
  // `pickFinanceiroEntry` segue resolvendo a entry ativa só pro PRIMARY contextual
  // por página (Novo título / Nova cobrança / Abrir caixa) e como gate de permissão
  // (sem entry FINANÇAS → finItem undefined → null acima).
  // ADR 0180 Fase 5 tweak2: primary pode renderizar SEPARADO pelo caller
  // (`hidePrimary=true`); ações features-específicas entram no overflow `⋯`.
  return (
    <PageHeaderTabs
      primary={hidePrimary ? undefined : finItem.primary}
      ghosts={FINANCEIRO_SUBNAV_GHOSTS}
      activeGhostKey={active}
      group="financas"
      maxVisible={maxVisible}
      extraOverflowItems={extraOverflowItems}
    />
  );
}
