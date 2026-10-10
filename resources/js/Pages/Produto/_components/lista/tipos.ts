// Lista de produtos (/products) no desenho do protótipo — tipos e formatação.
// Âncora: prototipo-ui/cowork/Felipe/produto-blade.jsx, vista `lista` (TelaLista) · charter #9118.
// Os dados vêm de ProductController::buildProdutoListaPaginada (mesma consulta da lista Blade).

import type { PaginatorShape } from '@/Components/shared/DataTable';
import { brl, qtdComUnidade } from '../../Unificado/_components/catalogo';

export type TipoProduto = 'single' | 'variable' | 'combo' | string;

export interface LinhaProduto {
  id: number;
  nome: string;
  sku: string;
  tipo: TipoProduto;
  categoria: string | null;
  subcategoria: string | null;
  unidade: string | null;
  unidade_curta: string | null;
  marca: string | null;
  imposto: string | null;
  imagem: string | null;
  controla_estoque: boolean;
  estoque: number | null;
  alerta: number | null;
  abaixo_do_alerta: boolean;
  inativo: boolean;
  nao_para_venda: boolean;
  serie: boolean;
  locais: string[];
  variacoes: number;
  campos: Record<string, string | null>;
  anexo: { url: string; nome: string } | null;
  woocommerce_sync?: boolean;
  // Só existem quando o usuário pode ver (gate no servidor). Ausência ≠ zero.
  compra_min?: number;
  compra_max?: number;
  venda_min?: number;
  venda_max?: number;
}

export type ListaPaginada = PaginatorShape<LinhaProduto> & { per_page: number };

export interface FiltrosLista {
  type: string | null;
  category_id: string | null;
  brand_id: string | null;
  unit_id: string | null;
  tax_id: string | null;
  location_id: string | null;
  active_state: 'active' | 'inactive' | null;
  not_for_selling: boolean;
  woocommerce_enabled: boolean;
  repair_model_id: string | null;
  q: string;
  sort: string;
  dir: 'asc' | 'desc';
  per_page: number;
  page: number;
}

export interface Opcao {
  id: string;
  nome: string;
}

export interface OpcoesLista {
  categorias: Opcao[];
  marcas: Opcao[];
  unidades: Opcao[];
  impostos: Opcao[];
  locais: Opcao[];
  modelos_dispositivo: Opcao[] | null;
}

export interface ContextoLista {
  empresa: string;
  grupos_de_preco: number;
  woocommerce: boolean;
  papel: string | null;
  rotulos_campos: Record<string, string | null>;
}

export interface PermissoesLista {
  create: boolean;
  update: boolean;
  delete: boolean;
  opening_stock: boolean;
  view?: boolean;
  stock_report?: boolean;
  view_purchase_price?: boolean;
  access_default_selling_price?: boolean;
}

/** Rótulos do protótipo (TYPE_LABEL do produto-blade.jsx). */
export const TIPO_ROTULO: Record<string, string> = {
  single: 'Único',
  variable: 'Variável',
  combo: 'Composição',
};

/**
 * Faixa de preço como a lista Blade mostra: o mínimo, e "mín – máx" só quando o produto é
 * variável e as variações têm preços diferentes (`@if($max != $min && $type == "variable")`).
 */
export function faixaDePreco(tipo: TipoProduto, min: number | undefined, max: number | undefined): string {
  if (min === undefined) return '—';
  if (tipo === 'variable' && max !== undefined && max !== min) return `${brl(min)} – ${brl(max)}`;
  return brl(min);
}

/** "418 m²" — quantidade com a unidade curta; sem controle de estoque não há número. */
export function estoqueTexto(l: LinhaProduto): string {
  if (!l.controla_estoque || l.estoque === null) return '—';
  return qtdComUnidade(l.estoque, l.unidade_curta ?? l.unidade ?? '');
}

/** 2ª linha da célula Produto, como o protótipo monta (`sub` do `prod`). */
export function subLinhaProduto(l: LinhaProduto): string {
  return [
    l.controla_estoque ? 'Estoque gerenciado' : 'Sem controle de estoque',
    l.nao_para_venda ? 'não para venda' : null,
    l.inativo ? 'inativo' : null,
    l.tipo === 'variable' ? `${l.variacoes} ${l.variacoes === 1 ? 'variação' : 'variações'}` : null,
  ]
    .filter(Boolean)
    .join(' · ');
}

export const CHAVE_COLUNAS = 'oimpresso.produto.lista.colunas';
export const CHAVE_DENSIDADE = 'oimpresso.produto.lista.densa';

/** Colunas opcionais (o menu "Colunas" do protótipo — PB_COLS + PB_COLS_PADRAO). */
export interface ColunaOpcional {
  id: string;
  rotulo: string;
  padrao: boolean;
}

export function colunasOpcionais(rotulos: Record<string, string | null>): ColunaOpcional[] {
  const base: ColunaOpcional[] = [
    { id: 'imagem', rotulo: 'Imagem', padrao: true },
    { id: 'locais', rotulo: 'Locais', padrao: true },
    { id: 'compra', rotulo: 'Preço de compra un.', padrao: true },
    { id: 'venda', rotulo: 'Preço de venda', padrao: true },
    { id: 'estoque', rotulo: 'Estoque atual', padrao: true },
    { id: 'tipo', rotulo: 'Tipo', padrao: true },
    { id: 'categoria', rotulo: 'Categoria', padrao: true },
    { id: 'marca', rotulo: 'Marca', padrao: true },
    { id: 'imposto', rotulo: 'Imposto', padrao: true },
    { id: 'sku', rotulo: 'SKU', padrao: true },
    { id: 'serie', rotulo: 'IMEI / série', padrao: false },
  ];
  // Campos personalizados: como na lista Blade, só existem os que a empresa rotulou.
  for (let i = 1; i <= 7; i++) {
    const r = rotulos[String(i)];
    if (r) base.push({ id: `cf${i}`, rotulo: r, padrao: false });
  }
  return base;
}
