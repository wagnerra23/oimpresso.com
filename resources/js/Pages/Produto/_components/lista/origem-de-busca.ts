// Contrato de "Você veio de… / Voltar e usar" (UC-PIDX-53): outra tela manda o usuário para a
// lista de produtos procurando um produto, e "Voltar e usar" devolve o escolhido para ela.
//
// Ida (quem chama monta):
//   /products?de=<caminho de volta>&de_rotulo=<Nova venda>&para=<o item que procura>
// Volta (a lista monta):
//   <caminho de volta>?…&produto_escolhido=<id>
//
// `de` só vale como caminho do próprio sistema ("/…", nunca "//…" nem URL com esquema): sem essa
// trava, um link montado por fora levaria o "Voltar e usar" para outro site com o id do produto.
//
// A origem fica guardada na aba (sessionStorage), porque a lista refaz a URL a cada filtro e a
// recarga da página perderia `de`. Sai quando o usuário volta ou dispensa o aviso.

export interface OrigemDaBusca {
  /** Caminho de volta, sempre começando com uma barra só. */
  de: string;
  /** Como a tela de origem se chama no aviso ("Nova venda", "Orçamento 123"). */
  rotulo: string;
  /** O item para o qual procura um produto (opcional). */
  para: string | null;
}

export const CHAVE_ORIGEM = 'oi.produto.origem-da-busca';

/** Caminho interno: começa com "/" e não com "//" nem "/\" (que o navegador lê como outro host). */
export function caminhoInterno(valor: string | null | undefined): string | null {
  if (!valor) return null;
  const v = valor.trim();
  if (!v.startsWith('/') || v.startsWith('//') || v.startsWith('/\\')) return null;
  // Quebra de linha e tab no meio do caminho: o navegador as descarta e o resto pode virar outro host.
  if ([...v].some((c) => c.charCodeAt(0) < 32)) return null;
  return v;
}

/** Lê a origem da URL; sem ela na URL, a guardada na aba. */
export function lerOrigem(search: string, guardada: string | null): OrigemDaBusca | null {
  const q = new URLSearchParams(search);
  const de = caminhoInterno(q.get('de'));
  if (de) {
    return { de, rotulo: (q.get('de_rotulo') ?? '').trim() || 'outra tela', para: (q.get('para') ?? '').trim() || null };
  }
  if (!guardada) return null;
  try {
    const o = JSON.parse(guardada) as Partial<OrigemDaBusca>;
    const deGuardado = caminhoInterno(o.de);
    if (!deGuardado) return null;
    return { de: deGuardado, rotulo: o.rotulo || 'outra tela', para: o.para ?? null };
  } catch {
    return null;
  }
}

/** Caminho de volta com o produto escolhido (troca um `produto_escolhido` que já estivesse lá). */
export function urlDeVolta(de: string, produtoId: number): string {
  const [caminho, hash = ''] = de.split('#', 2);
  const [base, query = ''] = (caminho ?? '').split('?', 2);
  const q = new URLSearchParams(query);
  q.set('produto_escolhido', String(produtoId));
  return `${base}?${q.toString()}${hash ? `#${hash}` : ''}`;
}
