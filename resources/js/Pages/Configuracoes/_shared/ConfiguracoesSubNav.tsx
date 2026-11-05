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

/**
 * `emLinha`: todas as abas visíveis, sem o "Mais" — é como o protótipo desenha a faixa de abas
 * (configuracoes-page.jsx, CliTabs abaixo do cabeçalho). Só serve em faixa própria (`PageHeader below`);
 * dentro da linha do título (slot `subnav`) nove abas não cabem, por isso o padrão segue 6.
 */
export default function ConfiguracoesSubNav({ emLinha = false }: { emLinha?: boolean }) {
  const { shell } = usePage<{ shell?: { menu?: ItemDeMenu[] } }>().props;
  const rota = window.location.pathname;
  const filhos = acharGrupo(shell?.menu, rota)?.filter((c) => c.href && c.href !== '#') ?? [];

  // Sem o grupo no menu (rota fora do AdminSidebarMenu, permissão nenhuma) a tela segue sem abas.
  if (filhos.length < 2) return null;

  const ghosts: PageHeaderGhost[] = filhos.map((c) => ({ key: caminho(c.href!), label: c.label, href: caminho(c.href!) }));
  const porChave = new Map(filhos.map((c) => [caminho(c.href!), c]));

  const abas = (
    <PageHeaderTabs
      ghosts={ghosts}
      activeGhostKey={rota}
      group="sistema"
      maxVisible={emLinha ? filhos.length : 6}
      onGhostChange={(key) => {
        if (porChave.get(key)?.inertia) router.visit(key);
        else window.location.assign(key);
      }}
    />
  );
  // Em linha, a faixa rola por dentro (como .hrm-tabs do protótipo); sem isto o PageHeaderTabs, que é
  // overflow-visible a partir de md, empurra o <main> inteiro para rolar na horizontal.
  // `hrm-tabs cfg-abas-telas`: a faixa ganha borda, recuo de 24px e as medidas de aba do protótipo
  // (cowork-configuracoes-bundle.css). Fora de `.cfg-page` essas classes não têm efeito.
  return emLinha ? <div className="hrm-tabs cfg-abas-telas min-w-0 overflow-x-auto [scrollbar-width:thin]" data-contract="abas-telas">{abas}</div> : abas;
}
