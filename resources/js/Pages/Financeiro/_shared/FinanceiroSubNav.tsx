// FinanceiroSubNav (ADR 0180 Fase 5 — extraído como shared 2026-05-21)
//
// Lê primary/ghosts da entry "Financeiro" do shell.menu (Inertia shared prop
// populado via LegacyMenuAdapter — Fase 4 piloto declarou attrs no
// Modules/Financeiro/Http/Controllers/DataController). Renderiza ghost tabs
// ARIA tablist abaixo do header `os-page-h` custom da tela.
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
import PageHeaderTabs, {
  type PageHeaderGhost,
  type PageHeaderPrimary,
  type PageHeaderOverflowItem,
} from '@/Components/shared/PageHeaderTabs';

export interface FinanceiroSubNavProps {
  active: string;
  /** Ações features-específicas (Resumir/Fechamento/Apresentar/etc) que vão pro overflow `⋯ Mais` */
  extraOverflowItems?: PageHeaderOverflowItem[];
  /** Quando true, omite primary (renderiza só ghosts + overflow). Caller renderiza primary separado à direita. */
  hidePrimary?: boolean;
}

export default function FinanceiroSubNav({ active, extraOverflowItems, hidePrimary }: FinanceiroSubNavProps) {
  const sharedShell = (usePage().props as any)?.shell as {
    menu?: Array<{ label: string; group?: string; primary?: PageHeaderPrimary; ghosts?: PageHeaderGhost[] }>;
  } | undefined;

  const finItem = sharedShell?.menu?.find(
    (m) => m.group === 'fin-op' || m.group === 'financas' || m.label?.toLowerCase() === 'financeiro',
  );

  if (!finItem?.ghosts?.length) return null;

  // ADR 0180 Fase 5 tweak2 Wagner 2026-05-21 — primary `+ Novo título` pode
  // renderizar SEPARADO no canto direito do header pelo caller (`hidePrimary=true`);
  // os botões action features-específicas (Resumir/Fechamento/Apresentar/etc)
  // entram NO overflow `⋯ Mais` (via `extraOverflowItems`).
  return (
    <PageHeaderTabs
      primary={hidePrimary ? undefined : finItem.primary}
      ghosts={finItem.ghosts}
      activeGhostKey={active}
      group="financas"
      maxVisible={5}
      extraOverflowItems={extraOverflowItems}
    />
  );
}
