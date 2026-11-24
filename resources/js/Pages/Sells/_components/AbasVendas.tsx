import { usePage } from '@inertiajs/react';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';

/**
 * Abas do módulo Vendas — a MESMA barra em todas as telas do módulo.
 *
 * Fonte de design: `prototipo-ui/cowork/Wagner/venda-blade.jsx` (`VendaBladePage`): o protótipo
 * desenha um cabeçalho de módulo e, logo abaixo, uma barra com as telas do módulo, presente em
 * todas elas. Antes desta lista, só a Lista de POS tinha a barra (6 abas, constante local), e
 * quem entrava por Rascunhos, Cotações etc. não tinha como voltar pelas abas — medido em
 * produção em 2026-11-24 (`/sells/drafts` sem barra; `/pos` com 6 abas). A aba Assinaturas
 * daquela constante apontava para `/subscriptions`, que dá 404: a rota é `/sells/subscriptions`.
 *
 * Ordem e telas = protótipo. Rótulos: os 6 que já existiam em produção ficam como estavam;
 * Descontos, Pedido de venda e Importação de vendas vêm do `TITULOS` do protótipo. O protótipo
 * chama Rascunhos de "Lista de rascunhos" e Cotações de "Lista de compromissos"; essa divergência
 * de rótulo fica registrada, não aplicada aqui.
 *
 * QUEM VÊ CADA ABA — sem regra própria no front:
 *  - As abas com item na sidebar só aparecem se o item está no hub Vendas que o SERVIDOR mandou
 *    em `shell.menu` (AdminSidebarMenu já filtrou por permissão e por módulo ligado). Assim a aba
 *    não leva a 403 — o defeito que o lote 4 (2026-11-12) consertou no próprio hub.
 *  - A Caixa do dia não tem item na sidebar: segue as três permissões que
 *    `SellController::inertiaCaixa` aceita, compartilhadas em `auth.can`.
 *  - Sem `shell.menu` (página fora do AppShellV2), mostra todas: é o comportamento anterior.
 *  - A aba da tela atual sempre aparece.
 *
 * Use no slot `below` do `PageHeader` (cabeçalho em cima, abas abaixo).
 */
export const ABAS_VENDAS = [
  { key: 'pos', label: 'Lista de POS', href: '/pos' },
  { key: 'rascunhos', label: 'Rascunhos', href: '/sells/drafts' },
  { key: 'cotacoes', label: 'Cotações', href: '/sells/quotations' },
  { key: 'remessas', label: 'Remessas', href: '/shipments' },
  { key: 'descontos', label: 'Descontos', href: '/discount' },
  { key: 'assinaturas', label: 'Assinaturas', href: '/sells/subscriptions' },
  { key: 'pedidos', label: 'Pedido de venda', href: '/sales-order' },
  { key: 'caixa', label: 'Caixa do dia', href: '/vendas/caixa' },
  { key: 'importar', label: 'Importação de vendas', href: '/import-sales' },
] as const;

export type AbaVenda = (typeof ABAS_VENDAS)[number]['key'];

type ItemMenu = { href?: string; children?: ItemMenu[] };

const PERMISSOES_CAIXA = ['direct_sell.view', 'view_own_sell_only', 'view_commission_agent_sell'];

const semQuery = (href: string) => (href.split('?')[0] ?? '').replace(/\/+$/, '');

/** Hrefs do hub Vendas da sidebar: o item cujos filhos levam a `/pos` ou `/sells`. */
export function hrefsDoHubVendas(menu: ItemMenu[] | undefined): Set<string> | null {
  if (!Array.isArray(menu)) return null;
  const hub = menu.find((m) =>
    m.children?.some((c) => c.href && ['/pos', '/sells'].includes(semQuery(c.href))),
  );
  if (!hub?.children) return null;
  return new Set(hub.children.filter((c) => c.href).map((c) => semQuery(c.href as string)));
}

export function abasVisiveis(
  ativa: AbaVenda,
  menu: ItemMenu[] | undefined,
  can: Record<string, boolean> | undefined,
) {
  const hub = hrefsDoHubVendas(menu);
  return ABAS_VENDAS.filter((a) => {
    if (a.key === ativa || hub === null) return true;
    if (a.key === 'caixa') return PERMISSOES_CAIXA.some((p) => can?.[p]);
    return hub.has(a.href);
  });
}

export default function AbasVendas({ ativa }: { ativa: AbaVenda }) {
  const props = usePage().props as {
    shell?: { menu?: ItemMenu[] };
    auth?: { can?: Record<string, boolean> };
  };
  const abas = abasVisiveis(ativa, props.shell?.menu, props.auth?.can);
  return (
    <div data-contract="tabs">
      <PageHeaderTabs
        ghosts={abas.map((a) => ({ ...a }))}
        activeGhostKey={ativa}
        maxVisible={ABAS_VENDAS.length}
      />
    </div>
  );
}
