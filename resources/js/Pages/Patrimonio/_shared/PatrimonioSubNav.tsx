import { usePage } from '@inertiajs/react';
import PageHeaderTabs, { type PageHeaderOverflowItem } from '@/Components/shared/PageHeaderTabs';
import {
  PATRIMONIO_SUBNAV_GHOSTS,
  PATRIMONIO_SEM_ROTA,
  pickPatrimonioEntry,
  type PatMenuEntry,
} from './patrimonioMenu';

/**
 * PatrimonioSubNav — barra de abas da área Patrimônio (ADR 0182 canon).
 *
 * Padrão do `FinanceiroSubNav` (ADR 0313): a lista de abas é CANÔNICA no frontend (a do
 * protótipo) e o `shell.menu` serve de gate de permissão e fonte do `primary` contextual.
 * A lista, o gate e as abas sem rota vivem no módulo puro `./patrimonioMenu` — é o que
 * `tests/patrimonioSubNav.spec.ts` exercita.
 *
 * Criado pela thread 07 do playbook SINCRONIZAR Patrimônio; as telas 08–12 importam ESTE
 * arquivo. Mudar a lista muda seis telas.
 *
 * Uso:
 *   <PatrimonioSubNav active="dashboard" />
 *   <PatrimonioSubNav active="assets" hidePrimary />
 */
interface PatrimonioSubNavProps {
  /** `key` da aba atual — a mesma do `shell.menu` (dashboard · assets · allocation · …). */
  active: string;
  extraOverflowItems?: PageHeaderOverflowItem[];
  hidePrimary?: boolean;
}

export default function PatrimonioSubNav({ active, extraOverflowItems = [], hidePrimary }: PatrimonioSubNavProps) {
  const sharedShell = (usePage().props as any)?.shell as { menu?: PatMenuEntry[] } | undefined;
  const patItem = pickPatrimonioEntry(sharedShell?.menu);

  if (!patItem?.ghosts?.length) return null;

  return (
    <PageHeaderTabs
      primary={hidePrimary ? undefined : patItem.primary}
      ghosts={PATRIMONIO_SUBNAV_GHOSTS}
      activeGhostKey={active}
      // `estoque` — medido, não herdado da ADR 0180, que diz `operar`. `operar` é alias
      // legacy v2 no SIDEBAR_GROUP_HUE; o agrupamento vivo do módulo é `estoque`
      // (`Sidebar.tsx:243` lista 'Gestão de ativos' na whitelist desse grupo).
      group="estoque"
      // 5 abas do protótipo inline; `revocation` cai no `⋯` junto com as 2 sem rota.
      maxVisible={5}
      extraOverflowItems={[...PATRIMONIO_SEM_ROTA, ...extraOverflowItems]}
    />
  );
}
