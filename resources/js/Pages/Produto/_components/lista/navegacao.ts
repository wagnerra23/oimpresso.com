// Para onde a lista de produtos leva cada ação, e de que jeito.
//
// As telas React de Novo produto, Preços por tabela e Histórico de estoque respondem a uma visita
// Inertia (`X-Inertia`); por GET de página inteira o servidor ainda serve o Blade. Por isso a lista
// só navega por Inertia quando a empresa tem a chave `useV2ProdutoCadastro` ligada
// (`contexto.telas_react`). Chave desligada: página inteira, e cada empresa segue no Blade como antes.
//
// Editar produto fica de fora: a tela React de edição ainda não tem o preço do produto (o preço do
// produto existente segue no Blade — Create.casos.md UC-PCAD-09 §Fora deste UC). Abrir a edição
// React tiraria da pessoa o lugar de mudar o preço.

const TELAS_REACT: RegExp[] = [
  /^\/products\/create(\?.*)?$/,
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
