// Aba (ghost) da rota atual — usado pelo ModuleTabs. Fora do componente para o fast refresh.

import type { PageHeaderGhost } from '@/Components/shared/PageHeaderTabs';

function caminho(href: string | undefined): string {
  if (!href) return '';
  try {
    return new URL(href, 'http://x').pathname.replace(/\/+$/, '');
  } catch {
    return '';
  }
}

/** Ghost da rota atual: o de caminho mais longo que é a rota ou prefixo dela. */
export function ghostAtivo(ghosts: PageHeaderGhost[], rotaAtual: string): string | undefined {
  const atual = caminho(rotaAtual);
  let melhor: { key: string; n: number } | undefined;
  for (const g of ghosts) {
    const alvo = caminho(g.href);
    if (!alvo) continue;
    if (atual === alvo || atual.startsWith(`${alvo}/`)) {
      if (!melhor || alvo.length > melhor.n) melhor = { key: g.key, n: alvo.length };
    }
  }
  return melhor?.key;
}
