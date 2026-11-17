// ModuleTabs — barra de abas de topo de um módulo, lida da MESMA lista que o sidebar mostra.
//
// [W] 2026-11-06: "deveria ser o sidebar e as abas iguais?" — sim, na lista. O DataController do
// módulo publica `ghosts` no item do menu (ADR 0180); o sidebar mostra essa lista sob o item ativo
// (UI-0028) e a tela mostra a mesma lista como abas. Uma fonte só: quem acrescenta tela mexe no
// DataController e ela aparece nos dois lugares.
//
// Até aqui cada módulo tinha a sua cópia deste componente (PontoSubNav, PatrimonioSubNav,
// JanaSubNav…). Medido em produção (biz=1, 2026-11-06): onde a tela lê os ghosts, sidebar e abas
// batem (Ponto 13/13, Patrimônio 6/6, Jana 6/6); onde não lê, ou não há aba nenhuma (Repair, HRM,
// CRM, Oficina Auto) ou as duas listas divergem.
//
// Forma: a das abas do Ponto (W9, [W] 2026-09-28, ADR 0418) — todas visíveis, rolagem até a ativa,
// densidade `compact`. Vai no slot `below` do PageHeader canon (faixa própria abaixo do título).
//
// Sem item no menu, ou item sem ghosts (módulo fora do pacote, usuário sem permissão), não renderiza:
// a tela não inventa aba que o menu não dá.

import { usePage } from '@inertiajs/react';
import PageHeaderTabs, {
  type PageHeaderGhost,
  type PageHeaderOverflowItem,
  type PageHeaderPrimary,
} from '@/Components/shared/PageHeaderTabs';
import { ghostAtivo } from '@/Lib/ghost-ativo';

interface ItemDoMenu {
  label: string;
  href?: string;
  group?: string;
  primary?: PageHeaderPrimary;
  ghosts?: PageHeaderGhost[];
}

export interface ModuleTabsProps {
  /** Rótulo do item do módulo no `shell.menu` (sem diferença de maiúscula). Aceita apelidos. */
  modulo: string | string[];
  /** Key do ghost ativo. Sem ela, vale o ghost cuja rota é a da página atual. */
  active?: string;
  /** Mostra o botão primário do item junto das abas. Default: não (a tela já tem o dela). */
  showPrimary?: boolean;
  /** Contagem por key de ghost. `null`/ausente = sem número; `0` aparece. */
  badges?: Record<string, number | null | undefined>;
  extraOverflowItems?: PageHeaderOverflowItem[];
}

export default function ModuleTabs({ modulo, active, showPrimary, badges, extraOverflowItems }: ModuleTabsProps) {
  const pagina = usePage();
  const menu = ((pagina.props as { shell?: { menu?: ItemDoMenu[] } })?.shell?.menu) ?? [];
  const nomes = (Array.isArray(modulo) ? modulo : [modulo]).map((n) => n.toLowerCase());
  const item = menu.find((m) => nomes.includes(m.label?.toLowerCase() ?? ''));

  if (!item?.ghosts?.length) return null;

  const ghosts = badges
    ? item.ghosts.map((g) => (badges[g.key] != null ? { ...g, badge: badges[g.key] as number } : g))
    : item.ghosts;
  const rota = pagina.url ?? (typeof window !== 'undefined' ? window.location.pathname : '');

  return (
    <PageHeaderTabs
      primary={showPrimary ? item.primary : undefined}
      ghosts={ghosts}
      activeGhostKey={active ?? ghostAtivo(ghosts, rota) ?? ''}
      group={item.group}
      maxVisible={ghosts.length}
      scrollable
      density="compact"
      extraOverflowItems={extraOverflowItems}
    />
  );
}
