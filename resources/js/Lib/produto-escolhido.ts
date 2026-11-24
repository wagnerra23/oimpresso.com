// Produto escolhido na lista de produtos, entregue a OUTRA ABA (UC-PIDX-53 · UC-V413).
//
// Quem está montando um documento que não guarda rascunho (a Nova venda V3) não pode trocar de
// página: perderia o que montou. Ele abre a lista de produtos numa aba nova com
// `?de=…&de_rotulo=…&para=…&canal=<id>`, e o "Voltar e usar" da lista manda o produto por este
// canal e fecha a aba. A aba de origem nunca recarrega.
//
// O canal leva só identificação (id, SKU, nome). Preço, unidade e estoque NÃO viajam: quem recebe
// busca o produto pelo caminho de sempre, com as regras dele (tabela de preço, local).
//
// `BroadcastChannel` só alcança abas do mesmo site (mesma origem), e o `canal` aleatório separa
// duas vendas abertas ao mesmo tempo.

export const NOME_DO_CANAL = 'oi-produto-escolhido';

export interface ProdutoEscolhido {
  canal: string;
  produtoId: number;
  sku: string | null;
  nome: string;
}

/** Id de canal aceito: letras, números e hífen, até 64. Qualquer outra coisa vira `null`. */
export function canalValido(valor: string | null | undefined): string | null {
  if (!valor) return null;
  const v = valor.trim();
  return /^[A-Za-z0-9-]{1,64}$/.test(v) ? v : null;
}

/** Id novo para uma aba de origem. */
export function novoCanal(): string {
  try {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
  } catch {
    /* sem crypto: cai no aleatório simples */
  }
  return `c${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`;
}

/** Manda o produto para a aba de origem. `false` quando o navegador não tem `BroadcastChannel`. */
export function enviarProdutoEscolhido(msg: ProdutoEscolhido): boolean {
  if (typeof BroadcastChannel === 'undefined') return false;
  const bc = new BroadcastChannel(NOME_DO_CANAL);
  bc.postMessage(msg);
  bc.close();
  return true;
}

/** Ouve os produtos escolhidos para ESTE canal. Devolve a função que para de ouvir. */
export function ouvirProdutoEscolhido(canal: string, aoReceber: (msg: ProdutoEscolhido) => void): () => void {
  if (typeof BroadcastChannel === 'undefined') return () => {};
  const bc = new BroadcastChannel(NOME_DO_CANAL);
  bc.onmessage = (e: MessageEvent) => {
    const m = e.data as Partial<ProdutoEscolhido> | null;
    if (!m || m.canal !== canal || typeof m.produtoId !== 'number' || typeof m.nome !== 'string') return;
    aoReceber({ canal, produtoId: m.produtoId, sku: typeof m.sku === 'string' && m.sku ? m.sku : null, nome: m.nome });
  };
  return () => bc.close();
}
