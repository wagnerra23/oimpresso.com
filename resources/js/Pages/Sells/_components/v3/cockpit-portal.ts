// Onde os diálogos e as gavetas da Venda V3 montam.
//
// Diálogo e gaveta são portais: o Radix monta o conteúdo no <body>, fora do `.cockpit`, e lá os
// tokens do Design System (`--bg`, `--surface`, `--text`, `--border`…) e o `data-theme` não chegam.
// Medido em produção em 2026-11-03 (empresa 1, tema escuro): a janela "Lançar <produto>" saía com
// `background-color: rgba(0, 0, 0, 0)` — a regra `.sells-cowork { background: var(--bg) }`, fora de
// @layer, vence o `bg-background` e `--bg` está vazio no <body>. Nos outros diálogos o fundo vinha
// do `html.dark`, mas `--text` e `--border` chegavam vazios.
//
// O conserto é o da lápide §5 2026-10-19: montar dentro do `.cockpit` pela prop `container` do
// `ui/dialog` e do `ui/sheet` (o mesmo caminho do Produto e da Fabricação), sem regra de token nova
// (ADR UI-0013). Layout effect, e não effect: a janela de lançar item já nasce aberta, e o portal
// precisa nascer no `.cockpit` em vez de montar no <body> e mudar de lugar depois.
import { useLayoutEffect, useState } from 'react';

export function useCockpit(): HTMLElement | null {
  const [cockpit, setCockpit] = useState<HTMLElement | null>(null);
  useLayoutEffect(() => setCockpit(document.querySelector<HTMLElement>('.cockpit')), []);
  return cockpit;
}
