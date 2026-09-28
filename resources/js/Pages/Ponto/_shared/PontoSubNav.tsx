// PontoSubNav (ADR 0182 propagação — Wave Ponto 2026-05-22)
//
// Lê primary/ghosts da entry "Ponto" do shell.menu (Inertia shared prop
// populado via LegacyMenuAdapter — DataController Ponto declara attrs
// dropdown com primary 'Bater ponto' + ghosts das sub-views). Renderiza
// ghost tabs ARIA tablist abaixo do header `os-page-h` custom da tela.
//
// Active prop = key do ghost atual (ex 'dashboard' em Dashboard/Index.tsx,
// 'espelho' em Espelho/Index.tsx, etc). Fallback: nada renderiza se
// shell.menu não tem entry "Ponto" com ghosts (módulo desinstalado ou
// usuário sem ponto.access).
//
// W9 ([W] 2026-09-28, ADR 0418): TODAS as abas visíveis, na ordem/rótulo/ícone do protótipo
// (declarados no DataController), em faixa própria ABAIXO do header — como o Clientes — e com
// scroll horizontal até a aba ativa (`scrollable`). Até então: 5 abas + `⋯ Mais`, dentro do
// `os-page-h-r`. Caller pode passar `hidePrimary` pra não repetir o primary `+ Bater ponto`.
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
      // W9 ([W] 2026-09-28, ADR 0418): todas as abas visíveis, como o protótipo — sem `⋯ Mais`.
      maxVisible={pontoItem.ghosts.length}
      scrollable
      // `compact` = inativa 13px/500, o peso das abas do protótipo (medido 2026-09-28: `13px 500`
      // na âncora contra `13px 400` no `default`). Não é dial de gosto — é o que a âncora pede.
      density="compact"
      extraOverflowItems={extraOverflowItems}
    />
  );
}
