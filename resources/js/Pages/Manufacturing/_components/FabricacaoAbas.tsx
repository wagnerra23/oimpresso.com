// FabricacaoAbas — a barra de abas das 5 telas da Fabricação, num lugar só.
//
// No protótipo (`prototipo-ui/cowork/Wagner/manufacturing-page.jsx`) a barra é UM `TabBar` do DS
// para o módulo inteiro. Até 2026-09-30 cada tela carregava a própria cópia (`<nav
// className="mfg-tabs">` com `.mfg-tab` local), e a aba ativa saía sem o fundo, sem o peso 600 e
// com o contador apagado — medido com a sonda nos dois lados. O par do `TabBar` no React é o
// `PageHeaderTabs` (governance/design/component-registry.json), que já desenha a aba ativa como o
// protótipo. Uma barra só também impede que as 5 cópias voltem a divergir entre si.
//
// A `<nav aria-label="Fabricação">` fica por fora: é o marco de navegação da página e a âncora
// do teste de navegador (`tests/Browser/Manufacturing/RecipesIndexTest.php`).

import { usePage } from '@inertiajs/react';
import PageHeaderTabs, { type PageHeaderGhost } from '@/Components/shared/PageHeaderTabs';

export type AbaFabricacao = 'receitas' | 'insumos' | 'producao' | 'relatorio' | 'config';

interface Props {
  ativa: AbaFabricacao;
  /** Contador da aba Receitas. Ausente = a aba sai sem contador. */
  receitas?: number;
  /** Contador da aba Ordens de produção: total e rascunhos em aberto. */
  producao?: { total: number; rascunhos?: number };
  /** `permissions.prod` — sem ela as abas Ordens de produção, Relatório e Configurações não aparecem. */
  podeProduzir?: boolean;
}

/**
 * UC-RECIPE-24 (lote 2.4 da auditoria): cada aba leva a uma rota que o usuário abre. Receitas e Insumos
 * pedem `access_recipe`; Ordens, Relatório e Configurações pedem `access_production` (decisão B3 de [W],
 * 2026-11-09). As duas chaves vêm no prop `permissions` das cinco telas; a que faltar não esconde nada,
 * para tela antiga não perder a barra — quem barra de verdade é a rota.
 */
function usePermissoesDaTela(): { receita?: boolean; prod?: boolean } {
  const props = usePage().props as { permissions?: { receita?: boolean; prod?: boolean } };

  return props.permissions ?? {};
}

export default function FabricacaoAbas({
  ativa,
  receitas,
  producao,
  podeProduzir = true,
}: Props) {
  const permissoes = usePermissoesDaTela();
  const podeReceitas = permissoes.receita !== false;
  const podeProducao = podeProduzir && permissoes.prod !== false;

  const ghosts: PageHeaderGhost[] = [];

  if (podeReceitas) {
    ghosts.push(
      { key: 'receitas', label: 'Receitas', href: '/manufacturing/recipe', badge: receitas },
      { key: 'insumos', label: 'Insumos', href: '/manufacturing/insumos' },
    );
  }

  if (podeProducao) {
    ghosts.push({
      key: 'producao',
      label: 'Ordens de produção',
      href: '/manufacturing/production',
      badge: producao
        ? `${producao.total}${producao.rascunhos ? ` · ${producao.rascunhos} rasc.` : ''}`
        : undefined,
    });
    ghosts.push(
      { key: 'relatorio', label: 'Relatório', href: '/manufacturing/report' },
      { key: 'config', label: 'Configurações', href: '/manufacturing/settings' },
    );
  }

  return (
    <nav className="mfg-tabs" aria-label="Fabricação">
      <PageHeaderTabs ghosts={ghosts} activeGhostKey={ativa} maxVisible={ghosts.length} />
    </nav>
  );
}
