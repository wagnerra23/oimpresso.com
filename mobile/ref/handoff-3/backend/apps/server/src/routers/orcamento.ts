// Router de Orçamento — expõe o QuoteEngine (Fase 1).
// Calcular é idempotente/sem efeito → query. Salvar versão → mutation.
//
// REGRA DE NEGÓCIO: a margem NÃO vem da entrada do usuário. Ela é lida do
// CADASTRO do produto (produtos.margemPct) — o vendedor não escolhe o lucro.
// Só o perfil admin altera a margem, no cadastro do produto.
import { z } from "zod";
import { and, eq } from "drizzle-orm";
import { TRPCError } from "@trpc/server";
import { router, protectedProcedure } from "../trpc/trpc";
import { withTenant } from "../trpc/context";
import { assertCan } from "../domain/policy";
import { calcularOrcamento } from "../domain/quote/quoteEngine";
import { orcamentos, produtos } from "../db/schema";

// Schemas de entrada (espelham domain/quote/types). margemPct NÃO está aqui.
const fichaSchema = z.object({
  larguraMm: z.number().positive(), alturaMm: z.number().positive(),
  cores: z.enum(["4/4", "4/0", "1/1", "1/0"]),
  papel: z.string(), gramatura: z.number().positive(),
  acabamentos: z.array(z.string()), tiragem: z.number().int().positive(),
  sangriaMm: z.number().nonnegative().optional(),
});
const suporteSchema = z.object({
  tipo: z.enum(["folha", "bobina"]), larguraMm: z.number().positive(),
  alturaMm: z.number().positive().optional(), margemMm: z.number().nonnegative(),
  custoCents: z.number().int().nonnegative(),
});
// Parâmetros de PRODUÇÃO (máquina/chapa/refugo) — técnicos, não comerciais.
const paramsSchema = z.object({
  digital: z.boolean(), custoChapaCents: z.number().int().nonnegative(),
  maquinaCentsPorMin: z.number().nonnegative(), setupMin: z.number().nonnegative(),
  velocidadeFolhasPorH: z.number().nonnegative(), refugoPct: z.number().nonnegative(),
  maoDeObraCentsPorMin: z.number().nonnegative(),
  acabamentoCentsPorPeca: z.record(z.number()),
});

const baseInput = z.object({
  produtoId: z.string().uuid(),   // de onde vem a margem (cadastro)
  ficha: fichaSchema, suporte: suporteSchema, params: paramsSchema,
});

/** Lê a margem do cadastro do produto. Falha se o produto não existir no tenant. */
async function margemDoProduto(tx: any, tenantId: string, produtoId: string): Promise<number> {
  const [p] = await tx.select({ margemPct: produtos.margemPct }).from(produtos)
    .where(and(eq(produtos.id, produtoId), eq(produtos.tenantId, tenantId)));
  if (!p) throw new TRPCError({ code: "NOT_FOUND", message: "Produto não encontrado" });
  return Number(p.margemPct ?? 45);
}

export const orcamentoRouter = router({
  // Cálculo ao vivo. Margem lida do produto (não aceita override do cliente).
  calcular: protectedProcedure
    .input(baseInput)
    .query(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "ler", "pedido");
        const margemPct = await margemDoProduto(tx, ctx.user.tenantId, input.produtoId);
        return calcularOrcamento(input.ficha as any, input.suporte as any, input.params as any, margemPct);
      })),

  // Persiste uma versão do orçamento (histórico imutável).
  salvar: protectedProcedure
    .input(baseInput.extend({ pedidoId: z.string().uuid().optional() }))
    .mutation(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "criar", "pedido");
        const margemPct = await margemDoProduto(tx, ctx.user.tenantId, input.produtoId);
        const r = calcularOrcamento(input.ficha as any, input.suporte as any, input.params as any, margemPct);
        const [orc] = await tx.insert(orcamentos).values({
          tenantId: ctx.user.tenantId, pedidoId: input.pedidoId,
          fichaTecnica: input.ficha as any, imposicao: { pecasPorFolha: r.pecasPorFolha } as any,
          custoCents: r.custoTotalCents, precoCents: r.precoCents,
        }).returning();
        return { ...orc, breakdown: r };
      })),
});
