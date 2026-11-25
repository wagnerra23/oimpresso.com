import type { ModeloVariacao } from './grade';
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

/** Os nomes que a tela mostra (protótipo, a mesma lista da exportação da lista de produtos). O
 *  servidor manda os do lang do UltimatePOS ("Combo"); onde a chave não estiver aqui, vale o dele. */
export const ROTULO_TIPO: Record<TipoProduto, string> = { single: 'Único', variable: 'Variável', combo: 'Composição' };

/**
 * O composto ainda não tem editor na tela React: sem `composition_variation_id` ele nasce sem itens e
 * com preço zero, e não entra em venda, compra, OS nem fabricação. A tela Novo manda o composto para
 * o cadastro clássico. O editor espera a D2 (`product_bom` como fonte única da composição, SDD §10.6),
 * para o cadastro e a Fabricação não mostrarem composições diferentes. O variável tem a grade
 * (UC-PCAD-32, grade.ts).
 */
export function tipoSemEditor(tipo: TipoProduto | undefined): boolean {
  return tipo === 'combo';
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
export interface CamposProduto extends Record<CampoPersonalizado, string> {
  name: string;
  sku: string;
  unit_id: number | string;
  brand_id: number | string;
  category_id: number | string;
  /** UC-PCAD-25 · UC-PEDIT-25: '' = sem subcategoria. */
  sub_category_id?: number | string;
  /** UC-PCAD-26 · UC-PEDIT-26: as sub-unidades ligadas ao produto. */
  sub_unit_ids?: number[];
  /** UC-PCAD-27 · UC-PEDIT-27: IMEI / nº de série (1 liga). */
  enable_sr_no?: number;
  /** UC-PCAD-29: a imagem e o folheto escolhidos no Novo (vão com o produto ao salvar). */
  image?: File | null;
  product_brochure?: File | null;
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
    | 'name'
    | 'product_variation'
    | 'unit_id'
    | 'origem_mercadoria'
    | 'ncm'
    | 'cest'
    | 'peso_liquido'
    | 'largura_cm'
    | 'altura_cm'
    | 'comprimento_cm'
    | 'politica_preco'
    | 'margem_alvo_pct',
    string
  >
>;

export type DefinirCampo = <K extends keyof CamposProduto>(campo: K, valor: CamposProduto[K]) => void;

/**
 * UC-PCAD-24 · UC-PEDIT-24: um campo personalizado que a empresa nomeou em Configurações → Rótulos
 * (ProductController::camposPersonalizadosDaEmpresa). Campo sem nome não vem, como no Blade.
 */
export interface CampoDaEmpresa {
  numero: NumeroCampoPersonalizado;
  rotulo: string;
  /** `dropdown` só vem com opções; sem elas o servidor manda `text`. */
  tipo: 'text' | 'date' | 'dropdown';
  opcoes: string[];
}

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
  /** Os campos personalizados com o nome que a empresa deu (UC-PCAD-24 · UC-PEDIT-24). */
  camposDaEmpresa?: CampoDaEmpresa[];
  /** UC-PCAD-25 · UC-PEDIT-25: categoria => {subcategoria => nome}. Vazio: a empresa não usa subcategoria. */
  subcategoriasPorCategoria?: Record<string, Record<string, string>>;
  /** UC-PCAD-26 · UC-PEDIT-26: unidade => sub-unidades. Vazio: a empresa não usa sub-unidades. */
  subUnidadesPorUnidade?: Record<string, SubUnidade[]>;
  /** UC-PCAD-28 · UC-PEDIT-28: libera o "+" de unidade (`unit.create`) e de marca (`brand.create`). */
  podeCriarUnidade?: boolean;
  podeCriarMarca?: boolean;
  /** UC-PCAD-32: os modelos de variação da empresa, com os valores. */
  modelosVariacao?: ModeloVariacao[];
}

/** Uma unidade da lista de sub-unidades: `multiplicador` em unidades da principal (a principal tem 1). */
export interface SubUnidade {
  id: number;
  nome: string;
  multiplicador: number;
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
