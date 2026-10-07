// ConfiguracoesSubNav — as abas de Configurações (decisão D1 [W] 2026-10-06: "uma tela com abas, como o protótipo").
//
// DERIVA, não declara: as abas são os filhos do dropdown de configurações que o `AdminSidebarMenu`
// monta (cada filho já sob a permissão dele: `business_settings.access`, `barcode_settings.access`,
// `access_printers`…) e o `ShellMenuBuilder` entrega como `shell.menu`. Escrever a lista aqui criaria
// um segundo dono, e uma aba que o usuário não pode abrir apareceria. Mesmo motivo do PatrimonioSubNav.
//
// A maioria das abas ainda é Blade: só as que o adaptador marca `inertia` navegam por router.visit;
// as outras fazem navegação cheia (um <Link> Inertia para página Blade abriria o HTML num modal).

import { router, usePage } from '@inertiajs/react';
import PageHeaderTabs, { type PageHeaderGhost } from '@/Components/shared/PageHeaderTabs';

interface ItemDeMenu { label: string; href?: string; inertia?: boolean; children?: ItemDeMenu[] }

const caminho = (href: string) => new URL(href, window.location.origin).pathname;

/** O pai cujos filhos contêm a rota atual — é o dropdown onde esta aba mora. */
function acharGrupo(itens: ItemDeMenu[] | undefined, rota: string): ItemDeMenu[] | null {
  for (const item of itens ?? []) {
    if (item.children?.some((c) => c.href && caminho(c.href) === rota)) return item.children;
    const dentro = acharGrupo(item.children, rota);
    if (dentro) return dentro;
  }
  return null;
}

export default function ConfiguracoesSubNav() {
  const { shell } = usePage<{ shell?: { menu?: ItemDeMenu[] } }>().props;
  const rota = window.location.pathname;
  const filhos = acharGrupo(shell?.menu, rota)?.filter((c) => c.href && c.href !== '#') ?? [];

  // Sem o grupo no menu (rota fora do AdminSidebarMenu, permissão nenhuma) a tela segue sem abas.
  if (filhos.length < 2) return null;

  const ghosts: PageHeaderGhost[] = filhos.map((c) => ({ key: caminho(c.href!), label: c.label, href: caminho(c.href!) }));
  const porChave = new Map(filhos.map((c) => [caminho(c.href!), c]));

  return (
    <PageHeaderTabs
      ghosts={ghosts}
      activeGhostKey={rota}
      group="sistema"
      maxVisible={6}
      onGhostChange={(key) => {
        if (porChave.get(key)?.inertia) router.visit(key);
        else window.location.assign(key);
      }}
    />
  );
}
