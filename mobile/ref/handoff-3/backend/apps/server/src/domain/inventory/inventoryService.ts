// ──────────────────────────────────────────────────────────────
// domain/inventory · serviço — saldo, custo médio ponderado e baixa
// dimensional disparada pela OS. Funções PURAS (recebem o estado,
// devolvem o novo estado) → fáceis de testar e de plugar na transação.
// ──────────────────────────────────────────────────────────────
import type { ProdutoEstoque, Movimento, SaldoLote } from "./types";
import { consumoComPerda } from "./conversion";

export interface EstadoEstoque {
  saldo: number;            // unidade de estoque
  custoMedioCents: number;  // média ponderada por unidade de estoque
  lotes: SaldoLote[];
}

/** Aplica uma ENTRADA: soma saldo e recalcula o custo médio ponderado. */
export function aplicarEntrada(estado: EstadoEstoque, mov: Movimento): EstadoEstoque {
  if (mov.tipo !== "entrada") throw new Error("Movimento não é entrada");
  const custoUnit = mov.custoUnitCents ?? estado.custoMedioCents;
  const novoSaldo = estado.saldo + mov.quantidade;
  // média ponderada: (valor_atual + valor_entrada) / saldo_total
  const valorAtual = estado.saldo * estado.custoMedioCents;
  const valorEntrada = mov.quantidade * custoUnit;
  const custoMedio = novoSaldo > 0 ? Math.round((valorAtual + valorEntrada) / novoSaldo) : 0;
  const lotes = mov.lote
    ? [...estado.lotes, { lote: mov.lote, quantidade: mov.quantidade, custoUnitCents: custoUnit }]
    : estado.lotes;
  return { saldo: novoSaldo, custoMedioCents: custoMedio, lotes };
}

/** Resultado de uma baixa: novo estado + custo do consumo (alimenta o custeio da OS). */
export interface ResultadoBaixa {
  estado: EstadoEstoque;
  consumo: number;          // unidades de estoque consumidas (com perda)
  custoConsumoCents: number;
  saldoNegativo: boolean;   // sinaliza exceção (caminho infeliz)
}

/**
 * Baixa dimensional ao fechar a OS: converte a venda → estoque, aplica perda,
 * debita do saldo e devolve o custo consumido (custo médio × consumo).
 */
export function baixarParaOS(
  estado: EstadoEstoque, produto: ProdutoEstoque,
  qtdVenda: number, unidadeVenda = produto.unidadeVenda,
): ResultadoBaixa {
  const consumo = consumoComPerda(produto, qtdVenda, unidadeVenda);
  const custoConsumoCents = Math.round(consumo * estado.custoMedioCents);
  const novoSaldo = estado.saldo - consumo;
  return {
    estado: { ...estado, saldo: novoSaldo },
    consumo,
    custoConsumoCents,
    saldoNegativo: novoSaldo < 0,
  };
}

/** Quantas unidades de COMPRA pedir para repor até o mínimo. */
export function sugestaoCompra(estado: EstadoEstoque, produto: ProdutoEstoque, estoqueMin: number): number {
  if (estado.saldo >= estoqueMin) return 0;
  const faltaEstoque = estoqueMin - estado.saldo;
  return Math.ceil(faltaEstoque / produto.fatorCompraParaEstoque);
}
