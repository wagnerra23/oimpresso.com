// GovernancaSubNav — strip de sub-navegação do módulo Governança.
//
// Espelha o wrapper canônico já usado por Financeiro/Jana/Ponto: lê
// `primary` + `ghosts` da entry da Governança em `shell.menu` (shared prop do
// Inertia, populado pelo LegacyMenuAdapter a partir de
// `Modules/Governance/Http/Controllers/DataController::modifyAdminMenu`) e
// delega o render pro `PageHeaderTabs` (ghost tabs ARIA + overflow `⋯ Mais` +
// auto-promoção do ghost ativo).
//
// Por que existe (2026-08-05): até aqui a Governança não tinha sub-navegação
// nenhuma — a entry de sidebar estava desligada desde 2026-05-25 e as telas
// eram alcançadas pelo ghost 'governanca' do hub Jana. Com a ADR 0366 §D-B
// mandando telas do Jana PRA CÁ (Governança MCP, Custos, Qualidade IA), a
// Governança precisa da própria strip: sem ela, tela movida fica órfã.
//
// Fonte da lista = os ghosts do DataController. NÃO duplicar a lista aqui —
// tela nova entra no DataController e aparece aqui de graça.
//
// Match da entry: por `href` sob /governance, com fallback por label. O group
// `sistema` NÃO serve pra identificar (é compartilhado com Auditoria/Cms/
// Connector/Officeimpresso — ver ADR 0180 v3).
//
// Hue 245 (índigo — SIDEBAR_GROUP_HUE.sistema).

import { usePage } from '@inertiajs/react';
import PageHeaderTabs, {
  type PageHeaderGhost,
  type PageHeaderPrimary,
  type PageHeaderOverflowItem,
} from '@/Components/shared/PageHeaderTabs';

export interface GovernancaSubNavProps {
  /** Key do ghost ativo — bate com o `key` declarado no DataController (ex 'audit'). */
  active: string;
  /** Ações específicas da tela que vão pro overflow `⋯ Mais`. */
  extraOverflowItems?: PageHeaderOverflowItem[];
  /** Quando true, omite o primary (caller renderiza o próprio à direita). */
  hidePrimary?: boolean;
}

type ShellMenuEntry = {
  label?: string;
  href?: string;
  group?: string;
  primary?: PageHeaderPrimary;
  ghosts?: PageHeaderGhost[];
};

export default function GovernancaSubNav({
  active,
  extraOverflowItems,
  hidePrimary,
}: GovernancaSubNavProps) {
  // Cast via `unknown` (não `any`): os wrappers irmãos usam `as any`, mas aquilo
  // está grandfathered no eslint-baseline — em arquivo novo `no-explicit-any`
  // conta como regressão. `unknown` dá o mesmo alcance sem afrouxar a régua.
  const sharedShell = (usePage().props as unknown as { shell?: { menu?: ShellMenuEntry[] } }).shell;

  const governancaItem = sharedShell?.menu?.find(
    (m) => m.href?.startsWith('/governance') || m.label?.toLowerCase() === 'governança',
  );

  // Silencioso quando o módulo está desinstalado pro business, quando o usuário
  // não tem `governance.dashboard.view`, ou quando a rota não passa pelo
  // middleware AdminSidebarMenu — nos três casos `shell.menu` não traz a entry.
  if (!governancaItem?.ghosts?.length) return null;

  return (
    <PageHeaderTabs
      primary={hidePrimary ? undefined : governancaItem.primary}
      ghosts={governancaItem.ghosts}
      activeGhostKey={active}
      group="sistema"
      maxVisible={5}
      extraOverflowItems={extraOverflowItems}
    />
  );
}
