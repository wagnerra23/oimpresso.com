// ──────────────────────────────────────────────────────────────
// Router de Produção (Fase 1 · fecha o laço orçar→produzir→baixar).
// Iniciar a produção de uma OS consome o material do estoque pela
// MESMA imposição do orçamento. Saldo insuficiente é EXCEÇÃO tratada
// (caminho infeliz, v3 §B2) — não baixa escondido nem quebra em silêncio.
// ──────────────────────────────────────────────────────────────
import { z } from "zod";
import { and, eq, sql } from "drizzle-orm";
import { TRPCError } from "@trpc/server";
import { router, protectedProcedure } from "../trpc/trpc";
import { withTenant } from "../trpc/context";
import { assertCan } from "../domain/policy";
import { audit } from "../lib/audit";
import { pedidos, pedidoItens, produtos, estoqueMovimentos } from "../db/schema";
import { planoDeConsumo, type ItemFicha } from "../domain/production/consumeStock";
import { baixarParaOS, type EstadoEstoque } from "../domain/inventory/inventoryService";
import type { ProdutoEstoque, Unidade } from "../domain/inventory/types";

// SRA3 padrão (no produto real vem da configuração da máquina/suporte).
const SRA3 = { larguraMm: 320, alturaMm: 450, margemMm: 10 };
const REFUGO_PCT = 5;

export const producaoRouter = router({
  // Inicia a produção: valida estoque, baixa material, avança etapa → "prod".
  // permitirNegativo=true libera a baixa mesmo sem saldo (com registro/alerta).
  iniciar: protectedProcedure
    .input(z.object({
      pedidoId: z.string().uuid(),
      expectedVersion: z.number().int().nonnegative(),
      permitirNegativo: z.boolean().default(false),
    }))
    .mutation(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "avancar", "producao");

        const [pedido] = await tx.select().from(pedidos)
          .where(and(eq(pedidos.id, input.pedidoId), eq(pedidos.tenantId, ctx.user.tenantId)));
        if (!pedido) throw new TRPCError({ code: "NOT_FOUND", message: "Pedido não encontrado" });

        // Concorrência otimista — não sobrescreve trabalho alheio.
        if (pedido.version !== input.expectedVersion) {
          throw new TRPCError({ code: "CONFLICT", message: "Pedido alterado por outra pessoa. Recarregue." });
        }

        // Itens com ficha (atributos) + material consumido.
        const itens = await tx.select().from(pedidoItens).where(eq(pedidoItens.pedidoId, pedido.id));
        const fichas: ItemFicha[] = itens
          .filter((i) => (i.atributos as any)?.materialId)
          .map((i) => {
            const a = i.atributos as any;
            return { materialId: a.materialId, larguraMm: a.larguraMm, alturaMm: a.alturaMm, tiragem: Number(i.qtd), sangriaMm: a.sangriaMm };
          });
        const plano = planoDeConsumo(fichas, SRA3, REFUGO_PCT);

        // Verifica saldo de cada material ANTES de baixar.
        const baixas: { materialId: string; folhas: number; custoConsumoCents: number; saldoNegativo: boolean; nome: string }[] = [];
        for (const c of plano) {
          const [mat] = await tx.select().from(produtos)
            .where(and(eq(produtos.id, c.materialId), eq(produtos.tenantId, ctx.user.tenantId)));
          if (!mat) throw new TRPCError({ code: "NOT_FOUND", message: `Material ${c.materialId} não encontrado` });
          const saldo = await saldoDe(tx, ctx.user.tenantId, mat.id);
          const produtoEstoque: ProdutoEstoque = {
            id: mat.id, nome: mat.nome,
            unidadeCompra: (mat.unidadeCompra ?? "folha") as Unidade,
            unidadeEstoque: (mat.unidadeEstoque ?? "folha") as Unidade,
            unidadeVenda: (mat.unidadeEstoque ?? "folha") as Unidade,
            fatorCompraParaEstoque: Number(mat.fatorConversao ?? 1),
            perdaPct: 0, // perda já embutida no refugo da imposição
            custoMedioCents: mat.custoCents ?? 0,
          };
          const estado: EstadoEstoque = { saldo, custoMedioCents: mat.custoCents ?? 0, lotes: [] };
          const r = baixarParaOS(estado, produtoEstoque, c.folhas, produtoEstoque.unidadeEstoque);
          baixas.push({ materialId: mat.id, folhas: c.folhas, custoConsumoCents: r.custoConsumoCents, saldoNegativo: r.saldoNegativo, nome: mat.nome });
        }

        // Caminho infeliz: falta material → bloqueia, a menos que liberado explicitamente.
        const semSaldo = baixas.filter((b) => b.saldoNegativo);
        if (semSaldo.length && !input.permitirNegativo) {
          throw new TRPCError({
            code: "PRECONDITION_FAILED",
            message: `Estoque insuficiente: ${semSaldo.map((b) => b.nome).join(", ")}. Reabasteça ou confirme a baixa negativa.`,
          });
        }

        // Aplica as baixas (movimentos append-only) e avança a etapa.
        for (const b of baixas) {
          await tx.insert(estoqueMovimentos).values({
            tenantId: ctx.user.tenantId, produtoId: b.materialId, tipo: "saida",
            quantidade: String(b.folhas), unidade: "folha", pedidoId: pedido.id,
          });
        }
        const [novo] = await tx.update(pedidos)
          .set({ etapa: "prod", version: pedido.version + 1 })
          .where(and(eq(pedidos.id, pedido.id), eq(pedidos.version, input.expectedVersion)))
          .returning();

        const custoMaterialCents = baixas.reduce((s, b) => s + b.custoConsumoCents, 0);
        await audit(tx, {
          tenantId: ctx.user.tenantId, actorId: ctx.user.id,
          entity: "pedido", entityId: pedido.id, action: "iniciar_producao",
          after: { baixas, custoMaterialCents, negativo: semSaldo.length > 0 },
        });

        return { pedido: novo, baixas, custoMaterialCents, alertaSaldoNegativo: semSaldo.length > 0 };
      })),
});

// Saldo derivado dos movimentos (auditável).
async function saldoDe(tx: any, tenantId: string, produtoId: string): Promise<number> {
  const [row] = await tx.select({
    saldo: sql<number>`coalesce(sum(case when ${estoqueMovimentos.tipo} = 'saida' then -1 else 1 end * ${estoqueMovimentos.quantidade}::numeric), 0)`,
  }).from(estoqueMovimentos)
    .where(and(eq(estoqueMovimentos.tenantId, tenantId), eq(estoqueMovimentos.produtoId, produtoId)));
  return Number(row?.saldo ?? 0);
}
