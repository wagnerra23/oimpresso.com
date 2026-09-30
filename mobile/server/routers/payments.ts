/**
 * F3-03 — Payments router.
 *
 * Thin tRPC layer around the Asaas helpers. All queries/mutations are tenant
 * scoped by `userId`. Provider failures degrade gracefully: the paymentLinks
 * row is persisted with status="falhou" so the operator sees the attempt
 * surface in the UI instead of silently disappearing.
 */
import { TRPCError } from "@trpc/server";
import { randomUUID } from "node:crypto";
import { and, desc, eq, inArray } from "drizzle-orm";
import { z } from "zod";

import {
  customers,
  paymentLinks,
  pedidos,
  quotes,
  serviceOrders,
} from "../../drizzle/schema";
import { getDb } from "../db";
import { companyProcedure, router } from "../_core/trpc";
import {
  cancelPayment as asaasCancelPayment,
  createOrGetAsaasCustomer,
  createPayment as asaasCreatePayment,
  getAsaasStatus,
  getPayment as asaasGetPayment,
  mapAsaasStatus,
  type AsaasBillingType,
} from "../_core/asaas";

type PaymentLinkRow = typeof paymentLinks.$inferSelect;
type CustomerRow = typeof customers.$inferSelect;

const ReferenciaTipo = z.enum(["pedido", "os", "quote"]);
const StatusEnum = z.enum([
  "pendente",
  "pago",
  "vencido",
  "cancelado",
  "estornado",
  "falhou",
]);
const MetodoEnum = z.enum(["pix", "boleto", "cartao", "qualquer"]);

function metodoToBillingType(
  metodo: z.infer<typeof MetodoEnum> | null | undefined,
): AsaasBillingType {
  switch (metodo) {
    case "pix":
      return "PIX";
    case "boleto":
      return "BOLETO";
    case "cartao":
      return "CREDIT_CARD";
    default:
      return "UNDEFINED";
  }
}

function rowToPayment(row: PaymentLinkRow, customer: CustomerRow | null) {
  return {
    id: row.id,
    userId: row.userId,
    customerId: row.customerId,
    referenciaTipo: row.referenciaTipo,
    referenciaId: row.referenciaId,
    valor: Number(row.valor),
    descricao: row.descricao,
    metodoPreferido: row.metodoPreferido,
    status: row.status,
    providerPaymentId: row.providerPaymentId,
    paymentUrl: row.paymentUrl,
    vencimento: row.vencimento,
    pagoEm: row.pagoEm,
    netValue: row.netValue !== null && row.netValue !== undefined
      ? Number(row.netValue)
      : null,
    createdAt: row.createdAt.toISOString(),
    updatedAt: row.updatedAt.toISOString(),
    customer: customer
      ? {
          id: customer.id,
          nome: customer.nome,
          telefone: customer.telefone,
          email: customer.email,
          documento: customer.documento,
        }
      : null,
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

async function resolveReferencia(
  db: Awaited<ReturnType<typeof requireDb>>,
  companyId: string,
  referenciaTipo: z.infer<typeof ReferenciaTipo>,
  referenciaId: string,
): Promise<{ valorSugerido: number; customerId: string | null; descricao: string }> {
  if (referenciaTipo === "pedido") {
    const found = await db
      .select()
      .from(pedidos)
      .where(and(eq(pedidos.id, referenciaId), eq(pedidos.companyId, companyId)))
      .limit(1);
    if (!found[0]) {
      throw new TRPCError({ code: "NOT_FOUND", message: "Pedido não encontrado" });
    }
    return {
      valorSugerido: Number(found[0].valor),
      customerId: found[0].customerId ?? null,
      descricao: `Pedido — ${found[0].produto}`,
    };
  }
  if (referenciaTipo === "os") {
    const found = await db
      .select()
      .from(serviceOrders)
      .where(
        and(eq(serviceOrders.id, referenciaId), eq(serviceOrders.companyId, companyId)),
      )
      .limit(1);
    if (!found[0]) {
      throw new TRPCError({ code: "NOT_FOUND", message: "OS não encontrada" });
    }
    return {
      valorSugerido: Number(found[0].valorTotal),
      customerId: found[0].customerId,
      descricao: `OS #${found[0].numero}`,
    };
  }
  // quote
  const found = await db
    .select()
    .from(quotes)
    .where(and(eq(quotes.id, referenciaId), eq(quotes.companyId, companyId)))
    .limit(1);
  if (!found[0]) {
    throw new TRPCError({ code: "NOT_FOUND", message: "Orçamento não encontrado" });
  }
  return {
    valorSugerido: Number(found[0].valorTotal),
    customerId: found[0].customerId ?? null,
    descricao: `Orçamento — ${found[0].titulo}`,
  };
}

async function getCustomerScoped(
  db: Awaited<ReturnType<typeof requireDb>>,
  companyId: string,
  customerId: string,
): Promise<CustomerRow | null> {
  const found = await db
    .select()
    .from(customers)
    .where(and(eq(customers.id, customerId), eq(customers.companyId, companyId)))
    .limit(1);
  return found[0] ?? null;
}

async function listWithCustomers(
  db: Awaited<ReturnType<typeof requireDb>>,
  companyId: string,
  rows: PaymentLinkRow[],
) {
  if (rows.length === 0) return [];
  const ids = Array.from(
    new Set(rows.map((r) => r.customerId).filter((x): x is string => Boolean(x))),
  );
  if (ids.length === 0) {
    return rows.map((r) => rowToPayment(r, null));
  }
  const found = await db
    .select()
    .from(customers)
    .where(and(eq(customers.companyId, companyId), inArray(customers.id, ids)));
  const custMap = new Map(found.map((c) => [c.id, c] as const));
  return rows.map((r) =>
    rowToPayment(r, r.customerId ? custMap.get(r.customerId) ?? null : null),
  );
}

export const paymentsRouter = router({
  status: companyProcedure.query(() => getAsaasStatus()),

  list: companyProcedure
    .input(
      z
        .object({
          status: StatusEnum.optional(),
          customerId: z.string().uuid().optional(),
          referenciaTipo: ReferenciaTipo.optional(),
          referenciaId: z.string().uuid().optional(),
        })
        .optional(),
    )
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const conditions = [eq(paymentLinks.companyId, ctx.companyId)];
      if (input?.status) conditions.push(eq(paymentLinks.status, input.status));
      if (input?.customerId)
        conditions.push(eq(paymentLinks.customerId, input.customerId));
      if (input?.referenciaTipo)
        conditions.push(eq(paymentLinks.referenciaTipo, input.referenciaTipo));
      if (input?.referenciaId)
        conditions.push(eq(paymentLinks.referenciaId, input.referenciaId));

      const rows = await db
        .select()
        .from(paymentLinks)
        .where(and(...conditions))
        .orderBy(desc(paymentLinks.createdAt));
      return listWithCustomers(db, ctx.companyId, rows);
    }),

  getById: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const found = await db
        .select()
        .from(paymentLinks)
        .where(
          and(eq(paymentLinks.id, input.id), eq(paymentLinks.companyId, ctx.companyId)),
        )
        .limit(1);
      if (!found[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Pagamento não encontrado" });
      }
      const customer = found[0].customerId
        ? await getCustomerScoped(db, ctx.companyId, found[0].customerId)
        : null;
      return rowToPayment(found[0], customer);
    }),

  create: companyProcedure
    .input(
      z.object({
        referenciaTipo: ReferenciaTipo,
        referenciaId: z.string().uuid(),
        customerId: z.string().uuid().optional().nullable(),
        valor: z.number().positive(),
        /** ISO date YYYY-MM-DD or full ISO datetime. */
        vencimento: z.string().min(8),
        metodoPreferido: MetodoEnum.optional(),
        descricao: z.string().max(255).optional().nullable(),
        /**
         * Client-supplied dedup key. The offline mutation queue ALWAYS sets
         * one; direct online callers MAY set one. When present, server returns
         * the prior row for that (companyId, idempotencyKey) instead of
         * calling Asaas again — prevents double-charging on retry.
         */
        idempotencyKey: z.string().min(8).max(64).optional(),
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();

      // Idempotency short-circuit: if we've seen this (companyId, key) before,
      // return the stored row instead of creating a new Asaas charge.
      if (input.idempotencyKey) {
        const existing = await db
          .select()
          .from(paymentLinks)
          .where(
            and(
              eq(paymentLinks.companyId, ctx.companyId),
              eq(paymentLinks.idempotencyKey, input.idempotencyKey),
            ),
          )
          .limit(1);
        if (existing[0]) {
          const cust = existing[0].customerId
            ? await getCustomerScoped(db, ctx.companyId, existing[0].customerId)
            : null;
          return {
            ok: existing[0].status !== "falhou",
            payment: rowToPayment(existing[0], cust),
          } as const;
        }
      }

      const ref = await resolveReferencia(
        db,
        ctx.companyId,
        input.referenciaTipo,
        input.referenciaId,
      );
      const customerId = input.customerId ?? ref.customerId;
      const customer = customerId
        ? await getCustomerScoped(db, ctx.companyId, customerId)
        : null;
      if (customerId && !customer) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Cliente não encontrado" });
      }

      const id = randomUUID();
      const descricao = input.descricao ?? ref.descricao;
      const metodo = input.metodoPreferido ?? "qualquer";
      const dueDate = input.vencimento.slice(0, 10); // YYYY-MM-DD for Asaas

      const status = getAsaasStatus();
      if (!status.configured) {
        // Graceful degradation: persist a "falhou" row so the operator sees it.
        await db.insert(paymentLinks).values({
          id,
          userId: ctx.user.id,
          companyId: ctx.companyId,
          customerId: customer?.id ?? null,
          referenciaTipo: input.referenciaTipo,
          referenciaId: input.referenciaId,
          valor: input.valor.toFixed(2),
          descricao,
          metodoPreferido: metodo,
          status: "falhou",
          vencimento: input.vencimento,
          idempotencyKey: input.idempotencyKey ?? null,
          providerResponse: JSON.stringify({
            error: "Asaas não configurado (ASAAS_API_KEY ausente).",
          }),
        });
        const saved = await db
          .select()
          .from(paymentLinks)
          .where(eq(paymentLinks.id, id))
          .limit(1);
        return { ok: false as const, payment: rowToPayment(saved[0], customer) };
      }

      try {
        // Need an Asaas customer-id before creating the payment.
        const customerName =
          customer?.nome ?? `Cliente ${input.referenciaTipo} ${input.referenciaId.slice(0, 8)}`;
        const { asaasCustomerId } = await createOrGetAsaasCustomer({
          customerName,
          customerEmail: customer?.email ?? null,
          customerDoc: customer?.documento ?? null,
        });
        const payment = await asaasCreatePayment({
          asaasCustomerId,
          value: input.valor,
          dueDate,
          description: descricao,
          billingType: metodoToBillingType(metodo),
          externalReference: id,
        });
        await db.insert(paymentLinks).values({
          id,
          userId: ctx.user.id,
          companyId: ctx.companyId,
          customerId: customer?.id ?? null,
          referenciaTipo: input.referenciaTipo,
          referenciaId: input.referenciaId,
          valor: input.valor.toFixed(2),
          descricao,
          metodoPreferido: metodo,
          status: mapAsaasStatus(payment.status),
          providerPaymentId: payment.id,
          paymentUrl: payment.invoiceUrl ?? payment.bankSlipUrl ?? null,
          vencimento: input.vencimento,
          idempotencyKey: input.idempotencyKey ?? null,
          netValue:
            payment.netValue !== null && payment.netValue !== undefined
              ? payment.netValue.toFixed(2)
              : null,
          providerResponse: JSON.stringify(payment._raw).slice(0, 60000),
        });
        const saved = await db
          .select()
          .from(paymentLinks)
          .where(eq(paymentLinks.id, id))
          .limit(1);
        return { ok: true as const, payment: rowToPayment(saved[0], customer) };
      } catch (err) {
        const message = err instanceof Error ? err.message : String(err);
        await db.insert(paymentLinks).values({
          id,
          userId: ctx.user.id,
          companyId: ctx.companyId,
          customerId: customer?.id ?? null,
          referenciaTipo: input.referenciaTipo,
          referenciaId: input.referenciaId,
          valor: input.valor.toFixed(2),
          descricao,
          metodoPreferido: metodo,
          status: "falhou",
          vencimento: input.vencimento,
          idempotencyKey: input.idempotencyKey ?? null,
          providerResponse: JSON.stringify({ error: message }),
        });
        const saved = await db
          .select()
          .from(paymentLinks)
          .where(eq(paymentLinks.id, id))
          .limit(1);
        return {
          ok: false as const,
          payment: rowToPayment(saved[0], customer),
          error: message,
        };
      }
    }),

  cancel: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const found = await db
        .select()
        .from(paymentLinks)
        .where(
          and(eq(paymentLinks.id, input.id), eq(paymentLinks.companyId, ctx.companyId)),
        )
        .limit(1);
      const row = found[0];
      if (!row) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Pagamento não encontrado" });
      }
      if (row.providerPaymentId && getAsaasStatus().configured) {
        try {
          await asaasCancelPayment(row.providerPaymentId);
        } catch (err) {
          // Fire-and-forget: even if provider rejects (already paid?) we still
          // flip our row to cancelado so the UI matches the operator intent.
          console.warn(
            `[asaas] cancel failed for ${row.providerPaymentId}: ${
              err instanceof Error ? err.message : err
            }`,
          );
        }
      }
      await db
        .update(paymentLinks)
        .set({ status: "cancelado" })
        .where(eq(paymentLinks.id, input.id));
      return { id: input.id, status: "cancelado" as const };
    }),

  refreshStatus: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const found = await db
        .select()
        .from(paymentLinks)
        .where(
          and(eq(paymentLinks.id, input.id), eq(paymentLinks.companyId, ctx.companyId)),
        )
        .limit(1);
      const row = found[0];
      if (!row) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Pagamento não encontrado" });
      }
      if (!row.providerPaymentId) {
        return { id: input.id, status: row.status };
      }
      if (!getAsaasStatus().configured) {
        throw new TRPCError({
          code: "PRECONDITION_FAILED",
          message: "Asaas não configurado.",
        });
      }
      const payment = await asaasGetPayment(row.providerPaymentId);
      const status = mapAsaasStatus(payment.status);
      const update: Record<string, unknown> = {
        status,
        providerResponse: JSON.stringify(payment._raw).slice(0, 60000),
      };
      if (status === "pago") {
        update.pagoEm = payment.paymentDate ?? new Date().toISOString();
        if (payment.netValue !== null && payment.netValue !== undefined) {
          update.netValue = payment.netValue.toFixed(2);
        }
      }
      await db.update(paymentLinks).set(update).where(eq(paymentLinks.id, input.id));
      return { id: input.id, status };
    }),

  copyLink: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const found = await db
        .select({ paymentUrl: paymentLinks.paymentUrl })
        .from(paymentLinks)
        .where(
          and(eq(paymentLinks.id, input.id), eq(paymentLinks.companyId, ctx.companyId)),
        )
        .limit(1);
      if (!found[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Pagamento não encontrado" });
      }
      if (!found[0].paymentUrl) {
        throw new TRPCError({
          code: "PRECONDITION_FAILED",
          message: "Pagamento sem link público.",
        });
      }
      return { url: found[0].paymentUrl };
    }),
});
