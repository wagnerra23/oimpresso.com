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
 *   <JanaPrimaryButton onClick={...}>Conversar</JanaPrimaryButton>
 */
interface JanaSubNavProps {
  active: string;
  extraOverflowItems?: PageHeaderOverflowItem[];
  hidePrimary?: boolean;
}

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

  return (
    <PageHeaderTabs
      primary={hidePrimary ? undefined : janaItem.primary}
      ghosts={janaItem.ghosts}
      activeGhostKey={active}
      group="ia"
      maxVisible={5}
      extraOverflowItems={extraOverflowItems}
    />
  );
}
