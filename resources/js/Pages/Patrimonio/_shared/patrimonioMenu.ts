// Abas da área Patrimônio + seleção da entry do `shell.menu`.
//
// Módulo PURO (só `import type` → zero runtime React), pelo mesmo motivo do
// `financeiroMenu.ts`: assim a lista de abas e o gate multi-tenant são testáveis direto
// (tests/patrimonioSubNav.spec.ts) sem montar componente.
//
// Criado pela thread 07 do playbook SINCRONIZAR Patrimônio. As telas 08–12 herdam esta
// lista pelo `PatrimonioSubNav` — mudar aqui muda seis telas.

import type { PageHeaderGhost, PageHeaderPrimary, PageHeaderOverflowItem } from '@/Components/shared/PageHeaderTabs';

export type PatMenuEntry = {
  label?: string;
  group?: string;
  primary?: PageHeaderPrimary;
  ghosts?: PageHeaderGhost[];
};

/**
 * As 6 abas COM ROTA. Ordem e rótulos do protótipo (`patrimonio-page.jsx:835`); os `href`
 * são os do `Routes/web.php` — nenhum inventado.
 *
 * Duas notas deliberadas:
 *  - "Bens", e não "Ativos" como o `shell.menu` rotula: é o que o canon de tradução diz —
 *    `Resources/lang/pt/lang.php:9` traduz `assets` como "Bens" — e coincide com o protótipo.
 *    O `lang.php` é internamente inconsistente ("recurso" em `view_asset`); reconciliar isso
 *    é decisão de produto, registrada no `_saida-07.md`.
 *  - `revocation` ("Devoluções") fica DEPOIS das 5 do protótipo, caindo no overflow `⋯`: o
 *    protótipo funde revogação dentro de Alocações, mas fundir a ROTA é decisão [W]. Até lá
 *    ela segue navegável em vez de sumir.
 *
 * Ícones espelham 1:1 o protótipo (`chart · list · target · settings · shield · lock ·
 * settings`) em nome lucide. Manutenções e Configurações compartilham `settings` lá, e a
 * fidelidade de FORMA é do protótipo (UI-0029) — não "corrigido" aqui.
 */
export const PATRIMONIO_SUBNAV_GHOSTS: PageHeaderGhost[] = [
  { key: 'dashboard',         label: 'Painel',        href: '/asset/dashboard',         icon: 'bar-chart-3' },
  { key: 'assets',            label: 'Bens',          href: '/asset/assets',            icon: 'list' },
  { key: 'allocation',        label: 'Alocações',     href: '/asset/allocation',        icon: 'target' },
  { key: 'asset-maintenance', label: 'Manutenções',   href: '/asset/asset-maintenance', icon: 'settings' },
  { key: 'settings',          label: 'Configurações', href: '/asset/settings',          icon: 'settings' },
  { key: 'revocation',        label: 'Devoluções',    href: '/asset/revocation' },
];

/**
 * As 2 abas SEM ROTA — Garantias e Auditoria existem no protótipo e **não existem no
 * backend**. Entram no `⋯ Mais` inertes, com `title` dizendo por quê, em vez de virarem link
 * para rota inventada (que daria 404 e pareceria bug da tela).
 *
 * Por que `PageHeaderOverflowItem` e não um ghost desabilitado: `PageHeaderGhost` não tem
 * `disabled` nem `title`, e todo ghost renderiza como `<Link href>`. Dar-lhe esses campos
 * significaria editar `Components/shared/PageHeaderTabs.tsx`, compartilhado por 4 módulos e
 * fora do prefixo desta thread. `PageHeaderOverflowItem` **já** tem `title`.
 *
 * Quando [W] responder D-GARANTIAS / D-AUDITORIA, estes viram ghosts normais acima.
 */
export const PATRIMONIO_SEM_ROTA: PageHeaderOverflowItem[] = [
  {
    key: 'garantias',
    label: 'Garantias',
    title: 'Ainda não existe tela de Garantias: falta decidir se é tela própria ou filtro de Bens (D-GARANTIAS).',
    onClick: () => {},
  },
  {
    key: 'auditoria',
    label: 'Auditoria',
    title: 'Ainda não existe tela de Auditoria aqui: falta decidir se é aba deste módulo ou link para o módulo Auditoria (D-AUDITORIA).',
    onClick: () => {},
  },
];

/**
 * A entry do Patrimônio no `shell.menu`, ou `undefined`.
 *
 * `undefined` é o GATE multi-tenant Tier 0 (ADR 0093): o `DataController:109` só declara a
 * entry quando o pacote `assetmanagement_module` está assinado E o usuário tem uma das
 * permissões `asset.*`. Sem entry, a barra inteira não renderiza.
 *
 * Casamos pelos próprios ghosts e não por `label`/`group`: a entry não declara `group` (o
 * `findGroupKey` do Sidebar a resolve por label, 'Gestão de ativos' → grupo `estoque`), e
 * rótulo de menu muda com tradução. `key` de ghost é contrato com a rota — é o identificador
 * estável. O `href.startsWith('/asset/')` evita casar um `dashboard` de outro módulo.
 */
export function pickPatrimonioEntry(menu: PatMenuEntry[] | undefined): PatMenuEntry | undefined {
  return (menu ?? []).find(
    (m) => m.ghosts?.some((g) => g.key === 'dashboard' && typeof g.href === 'string' && g.href.startsWith('/asset/')),
  );
}
