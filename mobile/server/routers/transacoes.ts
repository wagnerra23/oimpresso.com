import { TRPCError } from "@trpc/server";
import { randomUUID } from "node:crypto";
import { and, eq } from "drizzle-orm";
import { z } from "zod";

import { transacoes } from "../../drizzle/schema";
import { getDb } from "../db";
import { companyProcedure, router } from "../_core/trpc";

const TipoSchema = z.enum(["receita", "despesa"]);

const TransacaoCreateSchema = z.object({
  tipo: TipoSchema,
  descricao: z.string().min(1),
  valor: z.number().nonnegative(),
  categoria: z.string().min(1),
  data: z.string().datetime().optional(),
});

const TransacaoUpdateSchema = z.object({
  id: z.string().uuid(),
  tipo: TipoSchema,
  descricao: z.string().min(1),
  valor: z.number().positive(),
  data: z.string().datetime(),
  categoria: z.string().min(1),
});

function rowToTransacao(row: typeof transacoes.$inferSelect) {
  return {
    id: row.id,
    tipo: row.tipo,
    descricao: row.descricao,
    valor: Number(row.valor),
    data: row.data,
    categoria: row.categoria,
  };
}

async function requireDb() {
  const db = await getDb();
  if (!db) {
    throw new TRPCError({
      code: "SERVICE_UNAVAILABLE",
      message: "Database is not configured (DATABASE_URL missing).",
    });
  }
  return db;
}

export const transacoesRouter = router({
  list: companyProcedure.query(async ({ ctx }) => {
    const db = await requireDb();
    const rows = await db
      .select()
      .from(transacoes)
      .where(eq(transacoes.companyId, ctx.companyId));
    return rows.map(rowToTransacao);
  }),

  create: companyProcedure
    .input(TransacaoCreateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const id = randomUUID();
      const data = input.data ?? new Date().toISOString();
      await db.insert(transacoes).values({
        id,
        userId: ctx.user.id,
        companyId: ctx.companyId,
        tipo: input.tipo,
        descricao: input.descricao,
        valor: input.valor.toFixed(2),
        categoria: input.categoria,
        data,
      });
      return {
        id,
        tipo: input.tipo,
        descricao: input.descricao,
        valor: input.valor,
        categoria: input.categoria,
        data,
      };
    }),

  update: companyProcedure
    .input(TransacaoUpdateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const result = await db
        .update(transacoes)
        .set({
          tipo: input.tipo,
          descricao: input.descricao,
          valor: input.valor.toFixed(2),
          data: input.data,
          categoria: input.categoria,
        })
        .where(
          and(eq(transacoes.id, input.id), eq(transacoes.companyId, ctx.companyId)),
        );
      const affected = (result as unknown as { affectedRows?: number })
        .affectedRows;
      if (affected === 0) {
        throw new TRPCError({
          code: "NOT_FOUND",
          message: "Transacao not found.",
        });
      }
      const rows = await db
        .select()
        .from(transacoes)
        .where(
          and(eq(transacoes.id, input.id), eq(transacoes.companyId, ctx.companyId)),
        );
      if (rows.length === 0) {
        throw new TRPCError({
          code: "NOT_FOUND",
          message: "Transacao not found.",
        });
      }
      return rowToTransacao(rows[0]);
    }),

  delete: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await db
        .delete(transacoes)
        .where(and(eq(transacoes.id, input.id), eq(transacoes.companyId, ctx.companyId)));
      return { id: input.id };
    }),
});
