// PontoSubNav (ADR 0182 propagação — Wave Ponto 2026-05-22)
//
// Lê primary/ghosts da entry "Ponto" do shell.menu (Inertia shared prop
// populado via LegacyMenuAdapter — DataController Ponto declara attrs
// dropdown com primary 'Bater ponto' + ghosts[10] sub-views). Renderiza
// ghost tabs ARIA tablist abaixo do header `os-page-h` custom da tela.
//
// Active prop = key do ghost atual (ex 'dashboard' em Dashboard/Index.tsx,
// 'espelho' em Espelho/Index.tsx, etc). Fallback: nada renderiza se
// shell.menu não tem entry "Ponto" com ghosts (módulo desinstalado ou
// usuário sem ponto.access).
//
// Pattern: ghost tabs (esquerda) + ⋯ Mais (overflow) + primary `+ Bater ponto`
// (direita). Caller pode passar `hidePrimary` pra renderizar primary separado.
//
// Hue 295 (roxo claro pessoas — SIDEBAR_GROUP_HUE.pessoas).

import { usePage } from '@inertiajs/react';
import PageHeaderTabs, {
  type PageHeaderGhost,
  type PageHeaderPrimary,
  type PageHeaderOverflowItem,
} from '@/Components/shared/PageHeaderTabs';

export interface PontoSubNavProps {
  active: string;
  /** Ações features-específicas (Exportar/Importar/Apurar/etc) que vão pro overflow `⋯ Mais` */
  extraOverflowItems?: PageHeaderOverflowItem[];
  /** Quando true, omite primary (renderiza só ghosts + overflow). Caller renderiza primary separado à direita. */
  hidePrimary?: boolean;
}

export default function PontoSubNav({ active, extraOverflowItems, hidePrimary }: PontoSubNavProps) {
  const sharedShell = (usePage().props as any)?.shell as {
    menu?: Array<{ label: string; group?: string; primary?: PageHeaderPrimary; ghosts?: PageHeaderGhost[] }>;
  } | undefined;

  // Label literal — Ponto module_label = 'Ponto' (Resources/lang/pt/ponto.php).
  // Group `pessoas` ambíguo (HRM/Essentials também é pessoas), label match preferido.
  const pontoItem = sharedShell?.menu?.find(
    (m) => m.label?.toLowerCase() === 'ponto' || m.label?.toLowerCase() === 'ponto wr2',
  );

  if (!pontoItem?.ghosts?.length) return null;

  return (
    <PageHeaderTabs
      primary={hidePrimary ? undefined : pontoItem.primary}
      ghosts={pontoItem.ghosts}
      activeGhostKey={active}
      group="pessoas"
      maxVisible={5}
      extraOverflowItems={extraOverflowItems}
    />
  );
}
