// Router de Estoque (Fase 1 · A6). Entrada de compra, baixa pela OS,
// consulta de saldo e sugestão de reposição. Saldo e custo médio vivem
// no produto; movimentos são append-only (auditável).
import { z } from "zod";
import { eq, and, sql } from "drizzle-orm";
import { TRPCError } from "@trpc/server";
import { router, protectedProcedure } from "../trpc/trpc";
import { withTenant } from "../trpc/context";
import { assertCan } from "../domain/policy";
import { audit } from "../lib/audit";
import { produtos, estoqueMovimentos } from "../db/schema";
import {
  aplicarEntrada, baixarParaOS, sugestaoCompra, type EstadoEstoque,
} from "../domain/inventory/inventoryService";
import type { ProdutoEstoque, Unidade } from "../domain/inventory/types";

// Monta o ProdutoEstoque do domínio a partir da linha do banco.
function toProdutoEstoque(p: any): ProdutoEstoque {
  return {
    id: p.id, nome: p.nome,
    unidadeCompra: (p.unidadeCompra ?? "peca") as Unidade,
    unidadeEstoque: (p.unidadeEstoque ?? "peca") as Unidade,
    unidadeVenda: (p.unidadeVenda ?? "peca") as Unidade,
    fatorCompraParaEstoque: Number(p.fatorConversao ?? 1),
    perdaPct: Number(p.perdaPct ?? 0),
    custoMedioCents: p.custoCents ?? 0,
  };
}

export const estoqueRouter = router({
  // Entrada de compra: converte para unidade de estoque e recalcula custo médio.
  entrada: protectedProcedure
    .input(z.object({
      produtoId: z.string().uuid(),
      quantidadeEstoque: z.number().positive(),
      custoUnitCents: z.number().int().nonnegative(),
      lote: z.string().optional(),
    }))
    .mutation(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "criar", "pedido"); // TODO: recurso 'estoque' próprio
        const [p] = await tx.select().from(produtos)
          .where(and(eq(produtos.id, input.produtoId), eq(produtos.tenantId, ctx.user.tenantId)));
        if (!p) throw new TRPCError({ code: "NOT_FOUND", message: "Produto não encontrado" });

        // saldo atual derivado dos movimentos
        const saldoAtual = await saldoDe(tx, ctx.user.tenantId, p.id);
        const estado: EstadoEstoque = { saldo: saldoAtual, custoMedioCents: p.custoCents ?? 0, lotes: [] };
        const novo = aplicarEntrada(estado, {
          produtoId: p.id, tipo: "entrada", quantidade: input.quantidadeEstoque,
          unidade: (p.unidadeEstoque ?? "peca") as Unidade, custoUnitCents: input.custoUnitCents, lote: input.lote,
        });

        await tx.insert(estoqueMovimentos).values({
          tenantId: ctx.user.tenantId, produtoId: p.id, tipo: "entrada",
          quantidade: String(input.quantidadeEstoque), unidade: p.unidadeEstoque ?? "peca", lote: input.lote,
        });
        await tx.update(produtos).set({ custoCents: novo.custoMedioCents }).where(eq(produtos.id, p.id));
        await audit(tx, ctx.user, "entrada", "estoque", p.id, { quantidade: input.quantidadeEstoque });
        return { saldo: novo.saldo, custoMedioCents: novo.custoMedioCents };
      })),

  // Baixa dimensional pela OS: aplica perda, debita e devolve o custo do consumo.
  baixarOS: protectedProcedure
    .input(z.object({
      produtoId: z.string().uuid(), pedidoId: z.string().uuid(),
      qtdVenda: z.number().positive(),
    }))
    .mutation(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "criar", "pedido");
        const [p] = await tx.select().from(produtos)
          .where(and(eq(produtos.id, input.produtoId), eq(produtos.tenantId, ctx.user.tenantId)));
        if (!p) throw new TRPCError({ code: "NOT_FOUND", message: "Produto não encontrado" });

        const saldoAtual = await saldoDe(tx, ctx.user.tenantId, p.id);
        const estado: EstadoEstoque = { saldo: saldoAtual, custoMedioCents: p.custoCents ?? 0, lotes: [] };
        const r = baixarParaOS(estado, toProdutoEstoque(p), input.qtdVenda);

        await tx.insert(estoqueMovimentos).values({
          tenantId: ctx.user.tenantId, produtoId: p.id, tipo: "saida",
          quantidade: String(r.consumo), unidade: p.unidadeEstoque ?? "peca", pedidoId: input.pedidoId,
        });
        await audit(tx, ctx.user, "baixa", "estoque", p.id, {
          pedidoId: input.pedidoId, consumo: r.consumo, custoConsumoCents: r.custoConsumoCents, saldoNegativo: r.saldoNegativo,
        });
        return r;
      })),

  // Consulta de saldo + sugestão de reposição.
  saldo: protectedProcedure
    .input(z.object({ produtoId: z.string().uuid() }))
    .query(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "ler", "pedido");
        const [p] = await tx.select().from(produtos)
          .where(and(eq(produtos.id, input.produtoId), eq(produtos.tenantId, ctx.user.tenantId)));
        if (!p) throw new TRPCError({ code: "NOT_FOUND" });
        const saldo = await saldoDe(tx, ctx.user.tenantId, p.id);
        const estado: EstadoEstoque = { saldo, custoMedioCents: p.custoCents ?? 0, lotes: [] };
        const repor = sugestaoCompra(estado, toProdutoEstoque(p), Number(p.estoqueMin ?? 0));
        return { saldo, custoMedioCents: p.custoCents ?? 0, unidade: p.unidadeEstoque, sugestaoCompra: repor };
      })),
});

// Saldo = Σ entradas − Σ saídas (± ajustes), derivado dos movimentos (auditável).
async function saldoDe(tx: any, tenantId: string, produtoId: string): Promise<number> {
  const [row] = await tx.select({
    saldo: sql<number>`coalesce(sum(case when ${estoqueMovimentos.tipo} = 'saida' then -1 else 1 end * ${estoqueMovimentos.quantidade}::numeric), 0)`,
  }).from(estoqueMovimentos)
    .where(and(eq(estoqueMovimentos.tenantId, tenantId), eq(estoqueMovimentos.produtoId, produtoId)));
  return Number(row?.saldo ?? 0);
}
