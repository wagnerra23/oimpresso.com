import { TRPCError } from "@trpc/server";
import { and, desc, eq } from "drizzle-orm";
import { z } from "zod";

import { customers, whatsappMessages } from "../../drizzle/schema";
import { getDb } from "../db";
import { companyProcedure, router } from "../_core/trpc";
import { sendWhatsApp, whatsappTemplates } from "../_core/whatsapp";

type MessageRow = typeof whatsappMessages.$inferSelect;

function rowToMessage(row: MessageRow) {
  return {
    id: row.id,
    customerId: row.customerId,
    telefone: row.telefone,
    mensagem: row.mensagem,
    tipo: row.tipo,
    referenciaTipo: row.referenciaTipo,
    referenciaId: row.referenciaId,
    providerMessageId: row.providerMessageId,
    erro: row.erro,
    createdAt: row.createdAt.toISOString(),
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

async function resolvePhone(
  db: NonNullable<Awaited<ReturnType<typeof getDb>>>,
  companyId: string,
  customerId: string | undefined | null,
  telefone: string | undefined | null,
): Promise<{ phone: string; customerId: string | null }> {
  if (telefone && telefone.trim()) {
    return { phone: telefone.trim(), customerId: customerId ?? null };
  }
  if (customerId) {
    const found = await db
      .select()
      .from(customers)
      .where(and(eq(customers.id, customerId), eq(customers.companyId, companyId)))
      .limit(1);
    const c = found[0];
    if (!c) {
      throw new TRPCError({ code: "NOT_FOUND", message: "Cliente não encontrado" });
    }
    if (!c.telefone || !c.telefone.trim()) {
      throw new TRPCError({
        code: "BAD_REQUEST",
        message: "Cliente não possui telefone cadastrado",
      });
    }
    return { phone: c.telefone, customerId: c.id };
  }
  throw new TRPCError({
    code: "BAD_REQUEST",
    message: "Informe um telefone ou customerId",
  });
}

export const whatsappRouter = router({
  messages: router({
    list: companyProcedure
      .input(
        z
          .object({
            customerId: z.string().uuid().optional(),
            limit: z.number().int().positive().max(200).optional(),
          })
          .optional(),
      )
      .query(async ({ ctx, input }) => {
        const db = await requireDb();
        const filters = [eq(whatsappMessages.companyId, ctx.companyId)];
        if (input?.customerId) {
          filters.push(eq(whatsappMessages.customerId, input.customerId));
        }
        const rows = await db
          .select()
          .from(whatsappMessages)
          .where(and(...filters))
          .orderBy(desc(whatsappMessages.createdAt))
          .limit(input?.limit ?? 50);
        return rows.map(rowToMessage);
      }),
  }),

  sendCustom: companyProcedure
    .input(
      z.object({
        customerId: z.string().uuid().optional().nullable(),
        telefone: z.string().max(32).optional().nullable(),
        mensagem: z.string().min(1).max(4000),
        referenciaTipo: z.string().max(32).optional(),
        referenciaId: z.string().max(36).optional(),
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const { phone, customerId } = await resolvePhone(
        db,
        ctx.companyId,
        input.customerId ?? undefined,
        input.telefone ?? undefined,
      );
      const result = await sendWhatsApp({
        userId: ctx.user.id,
        companyId: ctx.companyId,
        telefone: phone,
        mensagem: input.mensagem,
        customerId,
        referenciaTipo: input.referenciaTipo,
        referenciaId: input.referenciaId,
      });
      return result;
    }),

  sendTemplate: companyProcedure
    .input(
      z.object({
        template: z.enum([
          "pedido_em_producao",
          "os_pronta",
          "aprovacao_orcamento",
          "entrega_agendada",
        ]),
        customerId: z.string().uuid().optional().nullable(),
        telefone: z.string().max(32).optional().nullable(),
        params: z.record(z.string(), z.string()),
        referenciaTipo: z.string().max(32).optional(),
        referenciaId: z.string().max(36).optional(),
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const { phone, customerId } = await resolvePhone(
        db,
        ctx.companyId,
        input.customerId ?? undefined,
        input.telefone ?? undefined,
      );
      const p = input.params;
      let mensagem: string;
      switch (input.template) {
        case "pedido_em_producao":
          mensagem = whatsappTemplates.pedido_em_producao(
            p.nome ?? "",
            p.produto ?? "",
          );
          break;
        case "os_pronta":
          mensagem = whatsappTemplates.os_pronta(p.nome ?? "", p.placa ?? "");
          break;
        case "aprovacao_orcamento":
          mensagem = whatsappTemplates.aprovacao_orcamento(
            p.nome ?? "",
            p.link ?? "",
          );
          break;
        case "entrega_agendada":
          mensagem = whatsappTemplates.entrega_agendada(
            p.nome ?? "",
            p.data ?? "",
          );
          break;
      }
      const result = await sendWhatsApp({
        userId: ctx.user.id,
        companyId: ctx.companyId,
        telefone: phone,
        mensagem,
        customerId,
        referenciaTipo: input.referenciaTipo,
        referenciaId: input.referenciaId,
      });
      return { ...result, mensagem };
    }),
});
