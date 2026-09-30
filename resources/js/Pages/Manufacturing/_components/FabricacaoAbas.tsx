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

import PageHeaderTabs, { type PageHeaderGhost } from '@/Components/shared/PageHeaderTabs';

export type AbaFabricacao = 'receitas' | 'insumos' | 'producao' | 'relatorio' | 'config';

interface Props {
  ativa: AbaFabricacao;
  /** Contador da aba Receitas. Ausente = a aba sai sem contador. */
  receitas?: number;
  /** Contador da aba Ordens de produção: total e rascunhos em aberto. */
  producao?: { total: number; rascunhos?: number };
  /** `permissions.prod` — sem ela a aba Ordens de produção não aparece. */
  podeProduzir?: boolean;
}

export default function FabricacaoAbas({
  ativa,
  receitas,
  producao,
  podeProduzir = true,
}: Props) {
  const ghosts: PageHeaderGhost[] = [
    { key: 'receitas', label: 'Receitas', href: '/manufacturing/recipe', badge: receitas },
    { key: 'insumos', label: 'Insumos', href: '/manufacturing/insumos' },
  ];

  if (podeProduzir) {
    ghosts.push({
      key: 'producao',
      label: 'Ordens de produção',
      href: '/manufacturing/production',
      badge: producao
        ? `${producao.total}${producao.rascunhos ? ` · ${producao.rascunhos} rasc.` : ''}`
        : undefined,
    });
  }

  ghosts.push(
    { key: 'relatorio', label: 'Relatório', href: '/manufacturing/report' },
    { key: 'config', label: 'Configurações', href: '/manufacturing/settings' },
  );

  return (
    <nav className="mfg-tabs" aria-label="Fabricação">
      <PageHeaderTabs ghosts={ghosts} activeGhostKey={ativa} maxVisible={ghosts.length} />
    </nav>
  );
}
