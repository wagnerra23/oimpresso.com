import { TRPCError } from "@trpc/server";
import { randomUUID } from "node:crypto";
import { and, eq } from "drizzle-orm";
import { z } from "zod";

import { ops } from "../../drizzle/schema";
import { getDb } from "../db";
import { sendPushToUser } from "../push";
import { companyProcedure, router } from "../_core/trpc";

const StatusSchema = z.enum(["fila", "andamento", "revisao", "concluido"]);

const OPCreateSchema = z.object({
  pedidoId: z.string().min(1),
  cliente: z.string().min(1),
  produto: z.string().min(1),
  status: StatusSchema.optional(),
  customerId: z.string().uuid().optional(),
});

const OPUpdateSchema = z.object({
  id: z.string().uuid(),
  pedidoId: z.string().min(1),
  cliente: z.string().min(1),
  produto: z.string().min(1),
  status: StatusSchema,
  customerId: z.string().uuid().optional().nullable(),
});

function rowToOP(row: typeof ops.$inferSelect) {
  return {
    id: row.id,
    pedidoId: row.pedidoId,
    cliente: row.cliente,
    produto: row.produto,
    status: row.status,
    customerId: row.customerId,
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

export const opsRouter = router({
  list: companyProcedure.query(async ({ ctx }) => {
    const db = await requireDb();
    const rows = await db.select().from(ops).where(eq(ops.companyId, ctx.companyId));
    return rows.map(rowToOP);
  }),

  create: companyProcedure
    .input(OPCreateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const id = randomUUID();
      const status = input.status ?? "fila";
      await db.insert(ops).values({
        id,
        userId: ctx.user.id,
        companyId: ctx.companyId,
        pedidoId: input.pedidoId,
        cliente: input.cliente,
        produto: input.produto,
        status,
        customerId: input.customerId ?? null,
      });
      return {
        id,
        pedidoId: input.pedidoId,
        cliente: input.cliente,
        produto: input.produto,
        status,
        customerId: input.customerId ?? null,
      };
    }),

  update: companyProcedure
    .input(OPUpdateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const existing = await db
        .select()
        .from(ops)
        .where(and(eq(ops.id, input.id), eq(ops.companyId, ctx.companyId)))
        .limit(1);
      const prevStatus = existing[0]?.status;

      await db
        .update(ops)
        .set({
          pedidoId: input.pedidoId,
          cliente: input.cliente,
          produto: input.produto,
          status: input.status,
          ...(input.customerId !== undefined
            ? { customerId: input.customerId }
            : {}),
        })
        .where(and(eq(ops.id, input.id), eq(ops.companyId, ctx.companyId)));

      if (prevStatus && prevStatus !== input.status) {
        sendPushToUser(ctx.user.id, {
          title: "OP atualizada",
          body: `${input.cliente} → ${input.status}`,
          data: { type: "op", id: input.id },
        }).catch(() => {});
      }

      return input;
    }),

  delete: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await db
        .delete(ops)
        .where(and(eq(ops.id, input.id), eq(ops.companyId, ctx.companyId)));
      return { id: input.id };
    }),
});
