// ──────────────────────────────────────────────────────────────
// domain/inventory · tipos — estoque dimensional.
// Resolve v2 §10: gráfica compra em rolo/folha e vende em m²/peça.
// O saldo é mantido SEMPRE na unidade de estoque; conversões
// acontecem nas pontas (compra entra, venda/baixa sai).
// ──────────────────────────────────────────────────────────────

/** Unidades suportadas. Dimensional (m², m linear) ou discreta (folha, peça…). */
export type Unidade =
  | "m2" | "m_linear" | "folha" | "peca" | "milheiro" | "kg" | "litro" | "rolo" | "bobina";

export interface ProdutoEstoque {
  id: string;
  nome: string;
  unidadeCompra: Unidade;   // como compra (ex.: rolo)
  unidadeEstoque: Unidade;  // como guarda o saldo (ex.: m2)
  unidadeVenda: Unidade;    // como vende/baixa (ex.: m2 ou peca)
  /** Quantas unidades de estoque há em 1 unidade de compra (ex.: 1 rolo = 50 m²). */
  fatorCompraParaEstoque: number;
  /** Perda/refilo aplicada na baixa (%). */
  perdaPct: number;
  custoMedioCents: number;  // custo por unidade de estoque (média ponderada)
}

export type TipoMovimento = "entrada" | "saida" | "ajuste";

export interface Movimento {
  produtoId: string;
  tipo: TipoMovimento;
  /** Quantidade na unidade de ESTOQUE (já convertida). */
  quantidade: number;
  unidade: Unidade;
  lote?: string;
  pedidoId?: string;
  custoUnitCents?: number;  // só entrada: custo de aquisição por unidade de estoque
}

export interface SaldoLote {
  lote: string;
  quantidade: number;       // na unidade de estoque
  custoUnitCents: number;
}
