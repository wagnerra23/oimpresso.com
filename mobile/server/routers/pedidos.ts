import { TRPCError } from "@trpc/server";
import { randomUUID } from "node:crypto";
import { and, asc, eq, inArray } from "drizzle-orm";
import { z } from "zod";

import { customers, orderItems, pedidos } from "../../drizzle/schema";
import { getDb } from "../db";
import { sendPushToUser } from "../push";
import { companyProcedure, router } from "../_core/trpc";
import { sendWhatsApp, whatsappTemplates } from "../_core/whatsapp";
import { deductForPedido } from "./inventory";

const StatusSchema = z.enum(["novo", "aprovado", "execucao", "entregue"]);

const OrderItemInputSchema = z.object({
  produtoId: z.string().uuid().optional(),
  descricao: z.string().min(1),
  quantidade: z.number().positive().default(1),
  valorUnit: z.number().positive(),
});

const PedidoCreateSchema = z.object({
  cliente: z.string().min(1).optional(),
  customerId: z.string().uuid().optional(),
  produto: z.string().min(1).optional(),
  valor: z.number().nonnegative().optional(),
  status: StatusSchema.optional(),
  /** ISO 8601 timestamp. Defaults to "now" if omitted. */
  data: z.string().datetime().optional(),
  tipo: z.string().optional(),
  items: z.array(OrderItemInputSchema).optional(),
});

const PedidoUpdateSchema = z.object({
  id: z.string().uuid(),
  cliente: z.string().min(1),
  produto: z.string().min(1),
  valor: z.number().nonnegative(),
  status: StatusSchema,
  data: z.string().datetime(),
  tipo: z.string(),
  customerId: z.string().uuid().optional().nullable(),
});

type PedidoRow = typeof pedidos.$inferSelect;
type CustomerRow = typeof customers.$inferSelect;
type OrderItemRow = typeof orderItems.$inferSelect;

function rowToPedido(
  row: PedidoRow,
  customer: CustomerRow | null,
  items: OrderItemRow[],
) {
  return {
    id: row.id,
    cliente: row.cliente,
    produto: row.produto,
    valor: Number(row.valor),
    status: row.status,
    data: row.data,
    tipo: row.tipo,
    customerId: row.customerId,
    customer: customer
      ? {
          id: customer.id,
          nome: customer.nome,
          telefone: customer.telefone,
        }
      : null,
    items: items.map((i) => ({
      id: i.id,
      pedidoId: i.pedidoId,
      produtoId: i.produtoId,
      descricao: i.descricao,
      quantidade: Number(i.quantidade),
      valorUnit: Number(i.valorUnit),
      valorTotal: Number(i.valorTotal),
    })),
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

export const pedidosRouter = router({
  list: companyProcedure.query(async ({ ctx }) => {
    const db = await requireDb();
    // Left join so legacy rows without customerId still come through.
    const rows = await db
      .select({
        pedido: pedidos,
        customer: customers,
      })
      .from(pedidos)
      .leftJoin(customers, eq(pedidos.customerId, customers.id))
      .where(eq(pedidos.companyId, ctx.companyId));

    if (rows.length === 0) return [];

    const ids = rows.map((r) => r.pedido.id);
    const items = await db
      .select()
      .from(orderItems)
      .where(inArray(orderItems.pedidoId, ids));

    const itemsByPedido = new Map<string, OrderItemRow[]>();
    for (const it of items) {
      const arr = itemsByPedido.get(it.pedidoId) ?? [];
      arr.push(it);
      itemsByPedido.set(it.pedidoId, arr);
    }

    return rows.map((r) =>
      rowToPedido(r.pedido, r.customer, itemsByPedido.get(r.pedido.id) ?? []),
    );
  }),

  getItems: companyProcedure
    .input(z.object({ pedidoId: z.string().uuid() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      // Scope through pedidos to enforce userId.
      const owner = await db
        .select({ id: pedidos.id })
        .from(pedidos)
        .where(and(eq(pedidos.id, input.pedidoId), eq(pedidos.companyId, ctx.companyId)))
        .limit(1);
      if (!owner[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Pedido não encontrado" });
      }
      const items = await db
        .select()
        .from(orderItems)
        .where(eq(orderItems.pedidoId, input.pedidoId))
        .orderBy(asc(orderItems.createdAt));
      return items.map((i) => ({
        id: i.id,
        pedidoId: i.pedidoId,
        produtoId: i.produtoId,
        descricao: i.descricao,
        quantidade: Number(i.quantidade),
        valorUnit: Number(i.valorUnit),
        valorTotal: Number(i.valorTotal),
      }));
    }),

  create: companyProcedure
    .input(PedidoCreateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const id = randomUUID();
      const data = input.data ?? new Date().toISOString();
      const status = input.status ?? "novo";
      const tipo = input.tipo ?? "Venda";

      // Resolve customer: customerId takes precedence over free-text `cliente`.
      let customerRow: CustomerRow | null = null;
      let clienteNome = input.cliente?.trim() ?? "";
      if (input.customerId) {
        const found = await db
          .select()
          .from(customers)
          .where(
            and(eq(customers.id, input.customerId), eq(customers.companyId, ctx.companyId)),
          )
          .limit(1);
        if (!found[0]) {
          throw new TRPCError({ code: "NOT_FOUND", message: "Cliente não encontrado" });
        }
        customerRow = found[0];
        // Backfill legacy column with the customer's name.
        clienteNome = customerRow.nome;
      }
      if (!clienteNome) {
        throw new TRPCError({
          code: "BAD_REQUEST",
          message: "Informe um cliente (customerId ou cliente)",
        });
      }

      // Compute valor & produto: items take precedence when supplied.
      let valor: number;
      let produtoLabel: string;
      if (input.items && input.items.length > 0) {
        valor = input.items.reduce(
          (sum, it) => sum + it.quantidade * it.valorUnit,
          0,
        );
        // Legacy `produto` column shows the first item's descricao (or " + N more").
        produtoLabel =
          input.items.length === 1
            ? input.items[0].descricao
            : `${input.items[0].descricao} (+${input.items.length - 1})`;
      } else {
        if (!input.produto || input.valor === undefined) {
          throw new TRPCError({
            code: "BAD_REQUEST",
            message: "Informe items ou (produto + valor)",
          });
        }
        valor = input.valor;
        produtoLabel = input.produto;
      }

      // Wrap pedido + orderItems in a single transaction so a partial insert
      // never leaves a phantom pedido with no items (audit P1 from senior review).
      const insertedItems: OrderItemRow[] = await db.transaction(async (tx) => {
        await tx.insert(pedidos).values({
          id,
          userId: ctx.user.id,
          companyId: ctx.companyId,
          customerId: customerRow?.id ?? null,
          cliente: clienteNome,
          produto: produtoLabel,
          valor: valor.toFixed(2),
          status,
          data,
          tipo,
        });

        if (!input.items || input.items.length === 0) return [];

        const rows = input.items.map((it) => ({
          id: randomUUID(),
          pedidoId: id,
          produtoId: it.produtoId ?? null,
          descricao: it.descricao,
          quantidade: it.quantidade.toFixed(3),
          valorUnit: it.valorUnit.toFixed(2),
          valorTotal: (it.quantidade * it.valorUnit).toFixed(2),
        }));
        await tx.insert(orderItems).values(rows);
        const fresh = await tx
          .select()
          .from(orderItems)
          .where(eq(orderItems.pedidoId, id));
        return fresh;
      });

      const pedidoRow: PedidoRow = {
        id,
        userId: ctx.user.id,
        companyId: ctx.companyId,
        customerId: customerRow?.id ?? null,
        cliente: clienteNome,
        produto: produtoLabel,
        valor: valor.toFixed(2),
        status,
        data,
        tipo,
        // createdAt/updatedAt are filled by the DB; safe to fake here since
        // rowToPedido doesn't use them in the wire format.
        createdAt: new Date(),
        updatedAt: new Date(),
      };
      return rowToPedido(pedidoRow, customerRow, insertedItems);
    }),

  update: companyProcedure
    .input(PedidoUpdateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const existing = await db
        .select()
        .from(pedidos)
        .where(and(eq(pedidos.id, input.id), eq(pedidos.companyId, ctx.companyId)))
        .limit(1);
      const prevStatus = existing[0]?.status;

      await db
        .update(pedidos)
        .set({
          cliente: input.cliente,
          produto: input.produto,
          valor: input.valor.toFixed(2),
          status: input.status,
          data: input.data,
          tipo: input.tipo,
          ...(input.customerId !== undefined
            ? { customerId: input.customerId }
            : {}),
        })
        .where(and(eq(pedidos.id, input.id), eq(pedidos.companyId, ctx.companyId)));

      if (prevStatus && prevStatus !== input.status) {
        // Fire-and-forget — must not block the response or throw.
        sendPushToUser(ctx.user.id, {
          title: "Pedido atualizado",
          body: `${input.cliente} → ${input.status}`,
          data: { type: "pedido", id: input.id },
        }).catch(() => {});
      }

      // F2-08: WhatsApp notification when entering "execucao". Fire-and-forget.
      const customerId = input.customerId ?? existing[0]?.customerId ?? null;
      if (
        prevStatus &&
        prevStatus !== input.status &&
        input.status === "execucao" &&
        customerId
      ) {
        (async () => {
          try {
            const cust = await db
              .select()
              .from(customers)
              .where(
                and(eq(customers.id, customerId), eq(customers.companyId, ctx.companyId)),
              )
              .limit(1);
            const c = cust[0];
            if (c?.telefone && c.telefone.trim()) {
              await sendWhatsApp({
                userId: ctx.user.id,
                companyId: ctx.companyId,
                telefone: c.telefone,
                mensagem: whatsappTemplates.pedido_em_producao(c.nome, input.produto),
                customerId: c.id,
                referenciaTipo: "pedido",
                referenciaId: input.id,
              });
            }
          } catch {
            // swallow — fire-and-forget
          }
        })().catch(() => {});
      }

      // F2-07: automatic stock deduction when status transitions to "entregue".
      if (
        prevStatus &&
        prevStatus !== input.status &&
        input.status === "entregue"
      ) {
        (async () => {
          try {
            const items = await db
              .select()
              .from(orderItems)
              .where(eq(orderItems.pedidoId, input.id));
            const mapped = items.map((it) => ({
              produtoId: it.produtoId,
              quantidade: Number(it.quantidade),
              descricao: it.descricao,
            }));
            await deductForPedido(ctx.companyId, ctx.user.id, input.id, mapped);
          } catch {
            // swallow — must not block delivery
          }
        })().catch(() => {});
      }

      return input;
    }),

  delete: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await db
        .delete(pedidos)
        .where(and(eq(pedidos.id, input.id), eq(pedidos.companyId, ctx.companyId)));
      return { id: input.id };
    }),
});
