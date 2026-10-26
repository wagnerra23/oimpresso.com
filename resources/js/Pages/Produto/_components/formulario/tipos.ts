// Formulário de produto compartilhado pelas telas Novo (Create.tsx) e Editar (Edit.tsx).
// Cada bloco deste diretório é uma seção do formulário. Cada tela monta o próprio useForm e
// entrega aos blocos o que eles leem e escrevem.

export interface OptionMap {
  [id: number]: string;
}

export interface OptionStringMap {
  [key: string]: string;
}

/** Novo e Editar divergem em rótulos e em quais campos o formulário tem. */
export type ModoFormulario = 'novo' | 'editar';

export type TipoProduto = 'single' | 'variable' | 'combo';

/**
 * Variável e composto ainda não têm editor na tela React: sem `product_variation` o store() cria o
 * variável sem variação nenhuma, e sem `composition_variation_id` o composto nasce sem itens e com
 * preço zero. Nos dois casos o produto não entra em venda, compra, OS nem fabricação. Até o editor
 * existir, a tela Novo só salva o simples e manda os outros para o cadastro clássico.
 */
export function tipoSemEditor(tipo: TipoProduto | undefined): boolean {
  return tipo === 'variable' || tipo === 'combo';
}

/** O cadastro Blade, mesmo com a chave do cadastro React ligada (ProductController@create). */
export const URL_CADASTRO_CLASSICO = '/products/create?classico=1';

/** Número de um campo personalizado do produto (`product_custom_field1` … `product_custom_field20`). */
export type NumeroCampoPersonalizado =
  | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10
  | 11 | 12 | 13 | 14 | 15 | 16 | 17 | 18 | 19 | 20;

export type CampoPersonalizado = `product_custom_field${NumeroCampoPersonalizado}`;

/**
 * Os 20 campos personalizados com o valor gravado, para o Editar devolver no envio (UC-PEDIT-10).
 * O `ProductController@update` grava '' em todo campo personalizado que não vem no request: sem
 * isto, salvar pela tela Editar apagava os campos 5 a 20, que a tela não mostra.
 */
export function camposPersonalizadosGravados(
  gravados: Partial<Record<NumeroCampoPersonalizado, string | null>> | null | undefined,
): Record<CampoPersonalizado, string> {
  const campos = {} as Record<CampoPersonalizado, string>;
  for (let n = 1; n <= 20; n++) {
    const numero = n as NumeroCampoPersonalizado;
    campos[`product_custom_field${numero}`] = gravados?.[numero] ?? '';
  }
  return campos;
}

/**
 * O que os blocos leem do formulário. Os ids chegam como `number | string` porque o useForm
 * alarga o valor inicial `''` para string. Os quatro campos opcionais existem só no formulário
 * da tela Novo, e os blocos só os mostram em modo `novo`.
 */
export interface CamposProduto {
  name: string;
  sku: string;
  unit_id: number | string;
  brand_id: number | string;
  category_id: number | string;
  tax: number | string;
  tax_type: 'inclusive' | 'exclusive';
  origem_mercadoria: number;
  /** O NCM, só dígitos (ncm.ts). O Editar só o envia quando a pessoa mexeu nele. */
  ncm?: string;
  /** O CEST, só dígitos (cest.ts). O Editar só o envia quando a pessoa mexeu nele. */
  cest?: string;
  barcode_type: string;
  alert_quantity: number | string;
  /** Peso bruto em kg, com embalagem: é o peso que o WooCommerce usa no frete. */
  weight: number | string;
  /** Peso líquido (kg) e dimensões da embalagem (cm) — UC-PCAD-16 e UC-PEDIT-13. Opcionais. */
  peso_liquido?: number | string;
  largura_cm?: number | string;
  altura_cm?: number | string;
  comprimento_cm?: number | string;
  product_description: string;
  warranty_id: number | string;
  product_custom_field1: string;
  product_custom_field2: string;
  type?: TipoProduto;
  enable_stock?: number;
  not_for_selling?: number;
  expiry_period?: string;
  /** `months` · `days` · `''` (não aplicável). */
  expiry_period_type?: string;
  product_locations?: number[];
}

/**
 * Natureza fiscal do produto, decidida pelo contador na Tributação (UC-PEDIT-14). Só o Editar
 * recebe: produto novo ainda não tem natureza decidida.
 */
export interface NaturezaFiscalProduto {
  natureza: 'mercadoria' | 'servico' | 'pendente' | string;
  codigoServico: string | null;
  link: string | null;
}

/** Os erros de validação que os blocos exibem. */
export type ErrosProduto = Partial<
  Record<
    'name' | 'origem_mercadoria' | 'ncm' | 'cest' | 'peso_liquido' | 'largura_cm' | 'altura_cm' | 'comprimento_cm',
    string
  >
>;

export type DefinirCampo = <K extends keyof CamposProduto>(campo: K, valor: CamposProduto[K]) => void;

/** As listas de opções que as duas telas recebem do servidor. */
export interface OpcoesProduto {
  units: OptionMap;
  categories: OptionMap;
  brands: OptionMap;
  taxes: OptionMap;
  barcodeTypes: OptionStringMap;
  warranties: OptionMap;
  productTypes: OptionStringMap;
  /** Novo: os locais do negócio que quem cadastra pode acessar. */
  businessLocations?: OptionMap;
}

/**
 * As opções de uma lista vinda do servidor, sem a chave vazia. O servidor acrescenta '' às
 * listas de unidades ("Selecionar") e de impostos ("nenhum") para os selects do Blade; o Select
 * do Radix não aceita item com valor vazio e derruba a tela inteira (tela em branco medida em
 * produção em 2026-10-16 · §5 2026-06-29). Toda lista que vira <SelectItem> passa por aqui.
 */
export function opcoesDe(mapa: OptionMap | OptionStringMap): Array<[string, string]> {
  return Object.entries(mapa).filter(([chave]) => chave !== '');
}

/**
 * Ponte entre o `setData` do useForm de cada tela e os blocos. O tipo do Inertia é casado com o
 * formulário de UMA tela, e os blocos escrevem campos das duas. O que garante que a tela tem o
 * campo é o `modo`: os campos opcionais de CamposProduto só aparecem em modo `novo`.
 */
export function definidorDe(setData: unknown): DefinirCampo {
  const escrever = setData as (campo: string, valor: unknown) => void;
  return (campo, valor) => escrever(campo, valor);
}
