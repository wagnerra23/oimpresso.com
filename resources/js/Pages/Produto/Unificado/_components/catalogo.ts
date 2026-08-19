/**
 * Vocabulário do catálogo — o que DUAS ou mais peças da tela usam.
 *
 * Equivale ao `prod-ui.jsx` do handoff "Consulta de Produtos" (2026-08-18): formatadores,
 * o estado de estoque e a regra de margem. Nada que o design system já resolva é reescrito
 * aqui — badge, botão e campo vêm de `@/Components/ui/*`.
 *
 * Derivado NUNCA em estado (handoff §8): tudo abaixo é função pura da linha.
 */

/** Quem pode ver o quê. Ausência de permissão declarada nunca vira permissão. */
export type Permissoes = { custo: boolean; preco: boolean; composicao: boolean };

/** Tipo do item. Derivado no servidor a partir de `type`/`not_for_selling`/`enable_stock`. */
export type TipoItem = 'produto' | 'servico' | 'materia' | 'kit';

/**
 * Uma linha do catálogo.
 *
 * `price`, `cost`, `margin` e `bomCount` são OPCIONAIS de propósito: o backend NÃO EMITE a
 * chave do dado que o usuário não pode ver (AR-PROD-015 — os campos somem da tela). Esta tela
 * usa os flags de permissão pra não MONTAR a coluna, nunca pra imprimir 0/— no lugar do valor:
 * zero é uma afirmação sobre o custo, e o contrato é ausência.
 *
 * ⚠️ Não é decoração: `brl(undefined)` lançaria TypeError e derrubaria a página inteira.
 * Renderizar a coluna sem o dado troca um vazamento por uma tela branca.
 */
export type ProdutoRow = {
  id: number;
  /** Identificador interno do cadastro — a coluna "Código". */
  codigo: number;
  /** SKU cadastrado — rotulado "Referência" (SPEC exceção 9). Pesquisável, sem coluna própria. */
  referencia: string | null;
  sku: string | null;
  name: string;
  tipo: TipoItem;
  cat: string | null;
  cat_label: string | null;
  unit: string;
  /** `null` = não estocável (serviço) · `0` = sem estoque · `>0` = saldo. Três estados, não dois. */
  stockQty: number | null;
  stockKind: 'estoque' | 'sob_demanda';
  minimo: number | null;
  parado: boolean;
  ultimaVenda: string | null;
  active: boolean;
  price?: number;
  cost?: number;
  margin?: number;
  bomCount?: number;
  /**
   * Saldo por local. Chave AUSENTE quando o item não é estocável ou tem um local só — nesses
   * casos não há o que comparar, e a presença da chave faria a tela montar um gatilho de
   * popover que não revela nada (handoff V2 §4.6).
   */
  locais?: LocalSaldo[];
  /** Observação livre do produto, já sem HTML. Chave ausente quando não há nota (§4.7). */
  obs?: string;
  /** Atributos de variação com a contagem de valores de cada um. Ausente quando não há (§3.2). */
  variacoes?: AtributoVariacao[];
};

/** Uma linha do popover de estoque por local. */
export type LocalSaldo = { nome: string; qtd: number };

/**
 * Um atributo de variação e quantos valores ele tem.
 *
 * `nome` vem do cadastro do tenant (`product_variations.name`) e é texto livre — pode ser
 * "Cor", "Cores" ou "Tonalidade". Por isso o resumo imprime o nome LITERAL com a contagem
 * entre parênteses em vez de pluralizar, como faz o protótipo: pluralizar o que o cliente
 * digitou daria "4 Cors".
 */
export type AtributoVariacao = { nome: string; n: number };

/** `Cor (4) · Tamanho (3)` — a terceira linha da célula Produto (handoff V2 §3.2). */
export const resumoVariacoes = (vs: AtributoVariacao[] | undefined): string =>
  !vs || vs.length === 0 ? '' : vs.map((v) => `${v.nome} (${v.n})`).join(' · ');

/** Saldo com unidade, pro popover e pro drawer. */
export const qtdComUnidade = (qtd: number, unit: string) => `${numero(qtd)} ${unit}`;

/** Estado de disponibilidade — vocabulário aprovado (handoff §6 exceção 6). */
export type EstadoEstoque = {
  chave: 'em' | 'baixo' | 'sem' | 'nao';
  label: string;
  /** Saldo pra exibir ao lado do rótulo. `null` quando o item não guarda saldo. */
  rel: string | null;
  /** Ordenação SEMÂNTICA (sem estoque < baixo < em estoque), não alfabética pelo rótulo. */
  rank: number;
};

/**
 * NBSP no rótulo pra ele não quebrar dentro do badge.
 *
 * A ordem dos testes importa: `null` (não estocável) vem ANTES de `=== 0`, senão um serviço
 * seria lido como "sem estoque" e apareceria bloqueando venda que não depende de saldo.
 */
export function estadoEstoque(r: Pick<ProdutoRow, 'stockQty' | 'minimo'>): EstadoEstoque {
  if (r.stockQty === null) {
    return { chave: 'nao', label: 'Não estocável', rel: null, rank: -1 };
  }
  if (r.stockQty === 0) {
    return { chave: 'sem', label: 'Sem estoque', rel: '0', rank: 0 };
  }
  if (r.minimo !== null && r.stockQty <= r.minimo) {
    return { chave: 'baixo', label: 'Estoque baixo', rel: numero(r.stockQty), rank: 1 };
  }
  return { chave: 'em', label: 'Em estoque', rel: numero(r.stockQty), rank: 2 };
}

/** Chave de ordenação da coluna Disponibilidade: rank primeiro, saldo como desempate. */
export const ordemDisponibilidade = (r: ProdutoRow): number =>
  estadoEstoque(r).rank * 1e9 + (r.stockQty ?? 0);

/** Margem em fração. `undefined` quando o perfil não recebeu custo OU preço. */
export function margemFrac(r: ProdutoRow): number | undefined {
  if (r.margin !== undefined) return r.margin;
  if (r.cost === undefined || r.price === undefined || r.price <= 0) return undefined;
  return (r.price - r.cost) / r.price;
}

/**
 * Item sob o piso de margem. O piso é PARÂMETRO DE NEGÓCIO e vem do servidor (`pisoMargem`) —
 * a tela nunca redeclara o número (handoff §9: "margem calculada com o piso vigente, não com
 * 42% fixo").
 */
export function sobOPiso(r: ProdutoRow, piso: number): boolean {
  const m = margemFrac(r);
  return m !== undefined && m < piso;
}

const numero = (n: number) =>
  Number.isInteger(n) ? String(n) : n.toLocaleString('pt-BR', { maximumFractionDigits: 3 });

/** Saldo com unidade, pro drawer. NBSP entre número e unidade pra não quebrar linha. */
export const saldoTexto = (r: ProdutoRow) =>
  (r.stockQty === null ? '—' : numero(r.stockQty)) + ' ' + r.unit;

export const brl = (n: number) => 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const pct = (n: number) => Math.round(n * 100) + '%';

export const TIPO_LABEL: Record<TipoItem, string> = {
  produto: 'PROD',
  servico: 'SERV',
  materia: 'M-PRIMA',
  kit: 'KIT',
};

/** Abas por TIPO. Trocam tabela, contagem E os KPIs — não são filtro decorativo (handoff §4.2). */
export const ABAS_CATALOGO: ReadonlyArray<{ key: AbaKey; label: string }> = [
  { key: 'todos', label: 'Todos' },
  { key: 'produtos', label: 'Produtos' },
  { key: 'servicos', label: 'Serviços' },
  { key: 'materia', label: 'Matéria-prima' },
  { key: 'kits', label: 'Kits' },
  { key: 'inativos', label: 'Inativos' },
];

export type AbaKey = 'todos' | 'produtos' | 'servicos' | 'materia' | 'kits' | 'inativos';

export type KpiKey = 'ativos' | 'min' | 'zero' | 'parado' | 'margem' | 'total';
