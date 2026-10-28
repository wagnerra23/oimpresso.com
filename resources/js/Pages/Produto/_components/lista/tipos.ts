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
  /**
   * Preço proposto pela compra recebida, aguardando aprovação (UC-PIDX-28 · UC-FLX-17). Vem junto com
   * o preço de venda (mesmo gate); null = nenhuma proposta pendente. `preco` só quando há uma proposta.
   */
  preco_proposto?: { quantidade: number; preco: number | null } | null;
  /** Requisição de compra aberta (UC-PIDX-30). Ausente = sem permissão de ver requisições; null = nenhuma. */
  requisicao?: RequisicaoAberta | null;
  /** Natureza fiscal decidida pelo contador (UC-PIDX-31). Ausente = sem o módulo Fiscal. */
  natureza_fiscal?: NaturezaFiscal;
}

export type NaturezaFiscal = 'mercadoria' | 'servico' | 'pendente';

/** Requisição de compra aberta do produto, criada pelo estoque mínimo ou à mão (UC-PIDX-30 · UC-FLX-19). */
export interface RequisicaoAberta {
  id: number;
  numero: string;
  /** AAAA-MM-DD. */
  data: string;
  fornecedor: string | null;
  url: string;
}

/** Data AAAA-MM-DD como DD/MM/AAAA. */
export const dataBr = (d: string | null | undefined) => (d ? d.slice(0, 10).split('-').reverse().join('/') : '');

export type ListaPaginada = PaginatorShape<LinhaProduto> & { per_page: number };

/** Opções de linhas por página — as MESMAS da lista Blade (`common.js` aLengthMenu); -1 = todas. */
export const POR_PAGINA = [25, 50, 100, 200, 500, 1000, -1];

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
  /** UC-PIDX-31 — só com o módulo Fiscal. */
  natureza_fiscal: NaturezaFiscal | null;
  q: string;
  sort: string;
  dir: 'asc' | 'desc';
  per_page: number;
  page: number;
  aba: 'lista' | 'estoque';
  estoque_pagina: number;
  /** Linhas por página da aba "Relatório de estoque" (-1 = todas). */
  estoque_por_pagina: number;
}

/** Indicadores do topo (ProductController::buildProdutoListaKpis) — catálogo inteiro, sem filtro. */
export interface KpisLista {
  total: number;
  variaveis: number;
  composicoes: number;
  abaixo_do_alerta: number;
  inativos: number;
  /** null = sem permissão de ver valor do estoque (não é zero). */
  valor_custo: number | null;
  valor_calculado_em: string | null;
}

/** Uma linha da aba "Relatório de estoque" (ProductUtil::linhaDoRelatorioEstoque). */
export interface LinhaRelatorioEstoque {
  sku: string;
  produto: string;
  variacao: string;
  categoria: string;
  local: string;
  /** null = sem `access_default_selling_price`. */
  preco: number | null;
  /** null = produto sem controle de estoque (a Blade mostra "--"). */
  estoque: number | null;
  unidade: string;
  /** null = sem `view_product_stock_value`. */
  valor_compra: number | null;
  valor_venda: number | null;
  lucro_potencial: number | null;
  vendido: number;
  transferido: number;
  ajustado: number;
  alerta: boolean;
}

/** Aba "Relatório de estoque" (ProductController::buildRelatorioEstoqueDaLista). */
export interface RelatorioEstoqueLista {
  linhas: LinhaRelatorioEstoque[];
  /** Totais de TODAS as linhas com os filtros da tela (não só da página). */
  totais: {
    linhas: number;
    estoque: number;
    vendido: number;
    transferido: number;
    ajustado: number;
    valor_compra: number | null;
    valor_venda: number | null;
    lucro_potencial: number | null;
    em: string | null;
  };
  mostra_preco: boolean;
  mostra_valor: boolean;
  /** `por_pagina`: -1 = todas as linhas. */
  paginacao: { atual: number; ultima: number; total: number; por_pagina: number };
}

/** Painel de detalhe (ProductController::buildProdutoDetalhe). Chave de preço ausente = sem permissão. */
/** Abas do painel de detalhe. Quem abre o painel escolhe em qual ele começa. */
export type AbaDoDetalhe = 'dados' | 'variacoes' | 'composicao' | 'estoque' | 'roteiro' | 'fiscal' | 'prateleira';

/** Preço proposto pela compra recebida, aguardando aprovação (UC-PIDX-28). */
export interface PrecoPropostoDoProduto {
  id: number;
  /** Nome da variação; null em produto que não é variável. */
  variacao: string | null;
  preco_atual: number;
  preco_proposto: number;
  /** O preço mudou depois da proposta: no detalhe da compra ela só pode ser recusada. */
  preco_mudou: boolean;
  compra_ref: string | null;
  /** AAAA-MM-DD. */
  compra_data: string | null;
  /** Detalhe da compra, onde se aprova ou recusa; null sem `purchase.view`. */
  compra_url: string | null;
}

/** Roteiro de produção do produto, com o posto e o setor de cada etapa (UC-PIDX-29). */
export interface RoteiroDoProduto {
  nome: string;
  ativo: boolean;
  etapas: Array<{ nome: string; posto: string | null; setor: string | null; horas: number | null; opcional: boolean; paralela: boolean }>;
}

export interface DetalheProdutoDados {
  id: number;
  nome: string;
  sku: string;
  tipo: TipoProduto;
  inativo: boolean;
  imagem: string | null;
  dados: {
    codigo_de_barras: string;
    unidade: string;
    unidade_curta: string;
    marca: string;
    categoria: string;
    subcategoria: string;
    imposto: string;
    tipo_imposto: string;
    peso: string;
    controla_estoque: boolean;
    alerta: number | null;
    validade: { periodo: number; tipo: string } | null;
    locais: string[];
    nao_para_venda: boolean;
    garantia: string | null;
    tempo_preparo: number | null;
    descricao: string;
    campos: Array<{ rotulo: string; valor: string }>;
  };
  variacoes: Array<{
    nome: string;
    sku: string;
    /** URLs das imagens da variação (`variation_images` da Blade); vazio = sem imagem. */
    imagens: string[];
    compra_exc?: number;
    compra_inc?: number;
    /** UC-PIDX-32: só quando a empresa gravou a regra de custo; metodo null = insumo sem método definido. */
    custo_metodo?: { metodo: 'medio' | 'ultimo' | null; ultima_compra_em: string | null };
    lucro_percentual?: number;
    venda_exc?: number;
    venda_inc?: number;
    grupos: Array<{ grupo: string; preco: number | null }>;
  }>;
  /** Itens da composição, com as colunas de preço da Blade (`combo_product_details`) e os mesmos gates. */
  composicao: Array<{
    nome: string;
    sku: string;
    quantidade: number;
    unidade: string;
    imagens: string[];
    compra_exc?: number;
    compra_inc?: number;
    lucro_percentual?: number;
    venda_exc?: number;
    venda_inc?: number;
    total_exc?: number;
  }>;
  /** "Total líquido": soma dos `total_exc` dos itens, feita no servidor. null = sem `view_purchase_price` ou não é composição. */
  composicao_total_exc: number | null;
  /** null = sem `stock_report.view` ou produto sem controle de estoque. */
  estoque: LinhaRelatorioEstoque[] | null;
  /**
   * Soma das linhas de `estoque` (ProductUtil::somaDasLinhasDoEstoque, a mesma do rodapé do relatório).
   * null junto com `estoque`. Os três valores são null sem `view_product_stock_value` (não é zero).
   */
  estoque_totais: Omit<RelatorioEstoqueLista['totais'], 'em'> | null;
  fiscal: {
    ncm: string;
    cest: string;
    cfop_interno: string;
    cfop_externo: string;
    cst_csosn: string;
    cst_pis: string;
    cst_cofins: string;
    cst_ipi: string;
    /** Código da tabela A da NF-e. Hoje é o que a emissão usa para todo item (UC-PIDX-25). */
    origem: string;
    /** false enquanto o cadastro não tiver origem por produto — a tela avisa que vale pra todos. */
    origem_por_produto: boolean;
    /** Tributação do NfeBrasil com este produto no simulador; null sem o módulo Fiscal no pacote, sem `fiscal.access` ou sem `nfe.tributacao.manage`. */
    link_modulo: string | null;
    /** UC-PIDX-31: null sem o módulo Fiscal. */
    natureza?: { natureza: NaturezaFiscal; codigo_servico: string | null } | null;
  };
  prateleiras: {
    colunas: { prateleira: boolean; fileira: boolean; posicao: boolean };
    linhas: Array<{ local: string; prateleira: string; fileira: string; posicao: string }>;
  };
  /** null = sem o módulo Produção ou produto sem roteiro. */
  roteiro?: RoteiroDoProduto | null;
  /** Só vem para quem vê o preço de venda (ausência ≠ lista vazia). */
  precos_propostos?: PrecoPropostoDoProduto[];
  /** UC-PIDX-30: ausente sem permissão de ver requisições; null = nenhuma aberta. */
  requisicao?: RequisicaoAberta | null;
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
  /** Módulo Fiscal no pacote da empresa: decide a aba Fiscal do painel e o "Dados fiscais" do menu. */
  emite_nota: boolean;
  papel: string | null;
  rotulos_campos: Record<string, string | null>;
  /** O filtro "Natureza fiscal" existe (módulo Fiscal ligado). */
  natureza_fiscal?: boolean;
}

export interface PermissoesLista {
  create: boolean;
  update: boolean;
  delete: boolean;
  opening_stock: boolean;
  view?: boolean;
  stock_report?: boolean;
  etiquetas?: boolean;
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
/**
 * Larguras que a pessoa arrastou, por id de coluna — UC-PIDX-16. Lembradas no navegador, como a
 * escolha de colunas e a densidade: a ROTA LIVRE refazia o ajuste a cada visita (2026-10-17).
 */
export const CHAVE_LARGURAS = 'oimpresso.produto.lista.larguras';

/** Só o que é largura de verdade sai do navegador: número entre o piso do arrasto (48) e 1200. */
export function largurasValidas(valor: unknown): Record<string, number> {
  if (!valor || typeof valor !== 'object' || Array.isArray(valor)) return {};
  const out: Record<string, number> = {};
  for (const [id, w] of Object.entries(valor as Record<string, unknown>)) {
    if (typeof w === 'number' && Number.isFinite(w) && w >= 48 && w <= 1200) out[id] = Math.round(w);
  }
  return out;
}

/** Colunas opcionais (o menu "Colunas" do protótipo — PB_COLS + PB_COLS_PADRAO). */
export interface ColunaOpcional {
  id: string;
  rotulo: string;
  padrao: boolean;
}

/**
 * `temAliquota`: a empresa tem alguma alíquota de imposto cadastrada (`opcoes.impostos` não vazio).
 * Sem alíquota não há o que mostrar na coluna Imposto, e ela não existe no menu. Com alíquota ela
 * existe e nasce desligada: o preço da lista já é o que a venda cobra, com o imposto (decisão [F]
 * 2026-10-26).
 */
export function colunasOpcionais(rotulos: Record<string, string | null>, temAliquota = true): ColunaOpcional[] {
  const base: ColunaOpcional[] = [
    { id: 'imagem', rotulo: 'Imagem', padrao: true },
    { id: 'locais', rotulo: 'Locais', padrao: true },
    { id: 'compra', rotulo: 'Preço de compra un.', padrao: true },
    { id: 'venda', rotulo: 'Preço de venda', padrao: true },
    { id: 'estoque', rotulo: 'Estoque atual', padrao: true },
    { id: 'tipo', rotulo: 'Tipo', padrao: true },
    { id: 'categoria', rotulo: 'Categoria', padrao: true },
    { id: 'marca', rotulo: 'Marca', padrao: true },
    ...(temAliquota ? [{ id: 'imposto', rotulo: 'Imposto', padrao: false }] : []),
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

/** Opções fixas dos filtros (as da lista Blade). */
export const TIPOS_PRODUTO: Opcao[] = [
  { id: 'single', nome: 'Único' },
  { id: 'variable', nome: 'Variável' },
  { id: 'combo', nome: 'Composição' },
];
export const NATUREZAS_FISCAIS: Opcao[] = [
  { id: 'mercadoria', nome: 'Mercadoria (NF-e)' },
  { id: 'servico', nome: 'Serviço (NFS-e)' },
  { id: 'pendente', nome: 'A decidir pelo contador' },
];
export const SITUACOES: Opcao[] = [
  { id: 'active', nome: 'Ativo' },
  { id: 'inactive', nome: 'Inativo' },
];

/** Colunas que nascem ligadas (PB_COLS_PADRAO do protótipo). */
export function visibilidadePadrao(colunas: ColunaOpcional[]): Record<string, boolean> {
  return Object.fromEntries(colunas.map((c) => [c.id, c.padrao]));
}

/**
 * Marca, no navegador, que a coluna Imposto já foi desligada uma vez. Até 2026-10-26 ela nascia
 * ligada, e a tela grava a visibilidade de todas as colunas: o "ligada" guardado é o padrão antigo,
 * não uma escolha. Sem esta marca, a coluna seguiria ligada para quem já usou a lista.
 */
export const CHAVE_IMPOSTO_DESLIGADO = 'produto-lista-imposto-desligado-v1';

/** As colunas guardadas no navegador, sem o "Imposto" antigo na primeira vez (ver acima). */
export function colunasGuardadas(salvas: Record<string, boolean>, impostoJaDesligado: boolean): Record<string, boolean> {
  if (impostoJaDesligado) return salvas;
  const resto = { ...salvas };
  delete resto.imposto;
  return resto;
}

/** Chips "Filtrando por" — nada de filtro invisível (protótipo: `chips`). */
export function chipsDosFiltros(
  f: FiltrosLista,
  o: OpcoesLista,
): Array<{ chave: keyof FiltrosLista; rotulo: string }> {
  const nome = (lista: Opcao[] | null, id: string | null) => (lista ?? []).find((x) => x.id === id)?.nome ?? id ?? '';
  const out: Array<{ chave: keyof FiltrosLista; rotulo: string }> = [];
  if (f.type) out.push({ chave: 'type', rotulo: `Tipo: ${nome(TIPOS_PRODUTO, f.type)}` });
  if (f.category_id) out.push({ chave: 'category_id', rotulo: `Categoria: ${nome(o.categorias, f.category_id)}` });
  if (f.unit_id) out.push({ chave: 'unit_id', rotulo: `Unidade: ${nome(o.unidades, f.unit_id)}` });
  if (f.tax_id) out.push({ chave: 'tax_id', rotulo: `Imposto: ${nome(o.impostos, f.tax_id)}` });
  if (f.brand_id) out.push({ chave: 'brand_id', rotulo: `Marca: ${nome(o.marcas, f.brand_id)}` });
  if (f.location_id) out.push({ chave: 'location_id', rotulo: `Local: ${nome(o.locais, f.location_id)}` });
  if (f.active_state) out.push({ chave: 'active_state', rotulo: `Situação: ${nome(SITUACOES, f.active_state)}` });
  if (f.natureza_fiscal) out.push({ chave: 'natureza_fiscal', rotulo: `Natureza fiscal: ${nome(NATUREZAS_FISCAIS, f.natureza_fiscal)}` });
  if (f.repair_model_id) out.push({ chave: 'repair_model_id', rotulo: `Modelo: ${nome(o.modelos_dispositivo, f.repair_model_id)}` });
  if (f.not_for_selling) out.push({ chave: 'not_for_selling', rotulo: 'Não para venda' });
  if (f.woocommerce_enabled) out.push({ chave: 'woocommerce_enabled', rotulo: 'WooCommerce habilitado' });
  if (f.q) out.push({ chave: 'q', rotulo: `Busca: ${f.q}` });
  return out;
}
