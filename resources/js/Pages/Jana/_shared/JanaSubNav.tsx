import { usePage } from '@inertiajs/react';
import PageHeaderTabs, {
  type PageHeaderGhost,
  type PageHeaderPrimary,
  type PageHeaderOverflowItem,
} from '@/Components/shared/PageHeaderTabs';

/**
 * JanaSubNav — ghost tabs ARIA do hub IA (ADR 0182 canon + GUIA-SIDEBAR-V3).
 *
 * 5 destinos canon do guia (Copiloto/Brief/Memórias/KB/Regras) + 3 operacionais
 * internos (Dashboard/Metas/Custos). PageHeaderTabs auto-promove ghost ativo
 * inline mesmo se index >= maxVisible.
 *
 * Hue OKLCH 220 (azul — grupo `ia` topo).
 *
 * Multi-tenant Tier 0 (ADR 0093): retorna null se Modules/Jana desinstalado
 * pro business (shell.menu não declara entry).
 *
 * Uso canon:
 *
 *   <JanaSubNav active="copiloto" hidePrimary extraOverflowItems={[...]}/>
 *   <PageHeaderPrimary label="Conversar" onClick={...} />
 */
interface JanaSubNavProps {
  active: string;
  extraOverflowItems?: PageHeaderOverflowItem[];
  hidePrimary?: boolean;
}

/** key do ghost (DataController) → ícone lucide, na ordem/vocabulário da âncora `JmTabs`.
 *  Não exportado de propósito: este arquivo exporta só o componente (react-refresh). */
const JANA_TAB_ICON: Record<string, string> = {
  dashboard: 'bar-chart-3',   // painel   → `chart`
  copiloto: 'sparkles',       // conversa → `sparkles`
  alertas: 'triangle-alert',  // alertas  → `alert`
  acoes: 'lightbulb',         // ações    → `bulb`
  memorias: 'database',       // memória  → `database`
  plataforma: 'shield',       // plataforma → `shield`
};

export default function JanaSubNav({ active, extraOverflowItems, hidePrimary }: JanaSubNavProps) {
  const sharedShell = (usePage().props as any)?.shell as {
    menu?: Array<{ label: string; group?: string; primary?: PageHeaderPrimary; ghosts?: PageHeaderGhost[] }>;
  } | undefined;

  // Procura entry da Jana no shell.menu (declarada pelo DataController).
  // Match por group='ia' OU label='Jana' (depending on DataController setup).
  const janaItem = sharedShell?.menu?.find(
    (m) => m.group === 'ia' || m.label?.toLowerCase() === 'jana',
  );

  if (!janaItem?.ghosts?.length) return null;

  // Ícone por aba — FORMA, logo é do protótipo (UI-0029), e vive aqui e não no
  // `DataController` porque o `SidebarGhost` PHP não tem campo `icon` e a sidebar
  // não desenha ícone de ghost. Mapa 1:1 com `jana-merge.jsx` §JmTabs
  // (`chart · sparkles · alert · bulb · database · shield`), em nome lucide (kebab)
  // como o `PageHeaderTabs` espera. Ghost sem entrada aqui renderiza sem ícone.
  const ghosts = janaItem.ghosts.map((g) => ({
    ...g,
    icon: g.icon ?? JANA_TAB_ICON[g.key],
  }));

  return (
    <PageHeaderTabs
      primary={hidePrimary ? undefined : janaItem.primary}
      ghosts={ghosts}
      activeGhostKey={active}
      group="ia"
      // 6 desde 2026-09-02: a área tem 6 abas com Plataforma (só superadmin), e a âncora
      // (`jana-merge.jsx` §JmTabs) mostra todas inline — nada cai no "⋯ Mais".
      maxVisible={6}
      extraOverflowItems={extraOverflowItems}
    />
  );
}
