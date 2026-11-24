// Para onde a lista de produtos leva cada ação, e de que jeito.
//
// As telas React de Novo produto, Preços por tabela e Histórico de estoque respondem a uma visita
// Inertia (`X-Inertia`); por GET de página inteira o servidor ainda serve o Blade. Por isso a lista
// só navega por Inertia quando a empresa tem a chave `useV2ProdutoCadastro` ligada
// (`contexto.telas_react`). Chave desligada: página inteira, e cada empresa segue no Blade como antes.
//
// Editar produto também abre a tela React (decisão [W] 2026-11-24, para todos os tipos). Até
// 2026-11-24 ficava de fora porque a edição React não tinha preço; o preço do produto simples
// chegou no UC-PEDIT-16. Variações e composição ainda não se editam na tela React: o [W] aceitou
// esse buraco em troca de a lista não mandar mais ninguém ao Blade.

const TELAS_REACT: RegExp[] = [
  /^\/products\/create(\?.*)?$/,
  /^\/products\/\d+\/edit(\?.*)?$/,
  /^\/products\/stock-history\/\d+(\?.*)?$/,
  /^\/products\/add-selling-prices\/\d+(\?.*)?$/,
];

/** A URL abre uma tela React pronta da lista de produtos? */
export function abreTelaReact(url: string): boolean {
  return TELAS_REACT.some((r) => r.test(url));
}

/** Decide o modo de navegar: `inertia` só com a chave ligada e para uma tela React pronta. */
export function modoDeNavegar(url: string, telasReact: boolean): 'inertia' | 'pagina' {
  return telasReact && abreTelaReact(url) ? 'inertia' : 'pagina';
}
