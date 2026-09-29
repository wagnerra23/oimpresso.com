import { TRPCError } from "@trpc/server";
import { randomUUID } from "node:crypto";
import { and, asc, desc, eq, inArray } from "drizzle-orm";
import { z } from "zod";
import PDFDocument from "pdfkit";

import {
  customers,
  orderItems,
  pedidos,
  priceTables,
  quoteItems,
  quotes,
} from "../../drizzle/schema";
import { getDb } from "../db";
import { storagePut } from "../storage";
import { companyProcedure, router } from "../_core/trpc";

/**
 * Quotes (orçamentos) router — F2-01 Comunicação Visual.
 *
 * Includes nested price-table management used by quote items to pre-fill
 * `precoPorM2` from a saved material catalog.
 */

const StatusSchema = z.enum([
  "rascunho",
  "enviado",
  "aprovado",
  "rejeitado",
  "convertido",
]);

const QuoteItemInputSchema = z.object({
  descricao: z.string().min(1),
  material: z.string().max(120).optional().nullable(),
  larguraCm: z.number().positive(),
  alturaCm: z.number().positive(),
  quantidade: z.number().positive().default(1),
  precoPorM2: z.number().nonnegative(),
  acabamento: z.string().max(120).optional().nullable(),
});

const QuoteCreateSchema = z.object({
  customerId: z.string().uuid().optional().nullable(),
  titulo: z.string().min(1).max(255),
  validadeDias: z.number().int().positive().optional(),
  observacoes: z.string().optional().nullable(),
  status: StatusSchema.optional(),
  items: z.array(QuoteItemInputSchema).min(1),
});

const QuoteUpdateSchema = z.object({
  id: z.string().uuid(),
  customerId: z.string().uuid().optional().nullable(),
  titulo: z.string().min(1).max(255).optional(),
  validadeDias: z.number().int().positive().optional(),
  observacoes: z.string().optional().nullable(),
  status: StatusSchema.optional(),
  items: z.array(QuoteItemInputSchema).optional(),
});

const PriceTableCreateSchema = z.object({
  material: z.string().min(1).max(120),
  precoPorM2: z.number().nonnegative(),
  acabamentoExtra: z.number().nonnegative().optional(),
  ativo: z.boolean().optional(),
});

const PriceTableUpdateSchema = PriceTableCreateSchema.partial().extend({
  id: z.string().uuid(),
});

type QuoteRow = typeof quotes.$inferSelect;
type QuoteItemRow = typeof quoteItems.$inferSelect;
type CustomerRow = typeof customers.$inferSelect;
type PriceTableRow = typeof priceTables.$inferSelect;

function rowToPriceTable(row: PriceTableRow) {
  return {
    id: row.id,
    material: row.material,
    precoPorM2: Number(row.precoPorM2),
    acabamentoExtra: Number(row.acabamentoExtra),
    ativo: row.ativo === 1,
    createdAt: row.createdAt.toISOString(),
  };
}

function rowToQuoteItem(row: QuoteItemRow) {
  return {
    id: row.id,
    quoteId: row.quoteId,
    descricao: row.descricao,
    material: row.material,
    larguraCm: Number(row.larguraCm),
    alturaCm: Number(row.alturaCm),
    quantidade: Number(row.quantidade),
    areaM2: Number(row.areaM2),
    precoPorM2: Number(row.precoPorM2),
    acabamento: row.acabamento,
    valorTotal: Number(row.valorTotal),
  };
}

function rowToQuote(
  row: QuoteRow,
  customer: CustomerRow | null,
  items: QuoteItemRow[],
) {
  return {
    id: row.id,
    customerId: row.customerId,
    titulo: row.titulo,
    validadeDias: row.validadeDias,
    status: row.status,
    valorTotal: Number(row.valorTotal),
    observacoes: row.observacoes,
    pdfKey: row.pdfKey,
    createdAt: row.createdAt.toISOString(),
    updatedAt: row.updatedAt.toISOString(),
    customer: customer
      ? {
          id: customer.id,
          nome: customer.nome,
          telefone: customer.telefone,
          email: customer.email,
        }
      : null,
    items: items.map(rowToQuoteItem),
  };
}

function computeItemArea(item: {
  larguraCm: number;
  alturaCm: number;
  quantidade: number;
}) {
  return (item.larguraCm * item.alturaCm * item.quantidade) / 10000;
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

async function loadQuoteFull(
  db: Awaited<ReturnType<typeof requireDb>>,
  companyId: string,
  id: string,
) {
  const rows = await db
    .select({ quote: quotes, customer: customers })
    .from(quotes)
    .leftJoin(customers, eq(quotes.customerId, customers.id))
    .where(and(eq(quotes.id, id), eq(quotes.companyId, companyId)))
    .limit(1);
  if (!rows[0]) {
    throw new TRPCError({ code: "NOT_FOUND", message: "Orçamento não encontrado" });
  }
  const items = await db
    .select()
    .from(quoteItems)
    .where(eq(quoteItems.quoteId, id))
    .orderBy(asc(quoteItems.createdAt));
  return rowToQuote(rows[0].quote, rows[0].customer, items);
}

function renderQuotePdf(quote: Awaited<ReturnType<typeof loadQuoteFull>>) {
  return new Promise<Buffer>((resolve, reject) => {
    try {
      const doc = new PDFDocument({ size: "A4", margin: 40 });
      const chunks: Buffer[] = [];
      doc.on("data", (c: Buffer) => chunks.push(c));
      doc.on("end", () => resolve(Buffer.concat(chunks)));
      doc.on("error", reject);

      doc
        .fontSize(18)
        .text(`ORÇAMENTO #${quote.id.slice(0, 8).toUpperCase()}`, { align: "left" });
      doc.moveDown(0.3);
      doc.fontSize(14).text(quote.titulo);
      doc.moveDown(0.5);

      doc.fontSize(10);
      doc.text(`Cliente: ${quote.customer?.nome ?? "—"}`);
      if (quote.customer?.telefone) doc.text(`Telefone: ${quote.customer.telefone}`);
      if (quote.customer?.email) doc.text(`Email: ${quote.customer.email}`);
      doc.text(`Validade: ${quote.validadeDias} dia(s)`);
      doc.text(`Emitido em: ${new Date(quote.createdAt).toLocaleDateString("pt-BR")}`);
      doc.moveDown();

      doc.fontSize(12).text("Itens", { underline: true });
      doc.moveDown(0.3);
      doc.fontSize(9);
      for (const it of quote.items) {
        const dims = `${it.larguraCm}x${it.alturaCm} cm × ${it.quantidade}`;
        doc.text(
          `${it.descricao} — ${dims} — ${it.areaM2.toFixed(2)} m² × R$ ${it.precoPorM2.toFixed(2)}/m² = R$ ${it.valorTotal.toFixed(2)}`,
        );
        if (it.material) doc.text(`   Material: ${it.material}`);
        if (it.acabamento) doc.text(`   Acabamento: ${it.acabamento}`);
        doc.moveDown(0.2);
      }

      doc.moveDown(0.5);
      doc
        .fontSize(14)
        .text(`Total: R$ ${quote.valorTotal.toFixed(2)}`, { align: "right" });

      if (quote.observacoes) {
        doc.moveDown();
        doc.fontSize(10).text("Observações:", { underline: true });
        doc.fontSize(9).text(quote.observacoes);
      }

      doc.end();
    } catch (err) {
      reject(err);
    }
  });
}

export const quotesRouter = router({
  // ─── Price tables ────────────────────────────────────────────────────────
  listPriceTables: companyProcedure.query(async ({ ctx }) => {
    const db = await requireDb();
    const rows = await db
      .select()
      .from(priceTables)
      .where(eq(priceTables.companyId, ctx.companyId))
      .orderBy(asc(priceTables.material));
    return rows.map(rowToPriceTable);
  }),

  createPriceTable: companyProcedure
    .input(PriceTableCreateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const id = randomUUID();
      await db.insert(priceTables).values({
        id,
        userId: ctx.user.id,
        companyId: ctx.companyId,
        material: input.material.trim(),
        precoPorM2: input.precoPorM2.toFixed(2),
        acabamentoExtra: (input.acabamentoExtra ?? 0).toFixed(2),
        ativo: (input.ativo ?? true) ? 1 : 0,
      });
      const rows = await db
        .select()
        .from(priceTables)
        .where(eq(priceTables.id, id))
        .limit(1);
      return rowToPriceTable(rows[0]);
    }),

  updatePriceTable: companyProcedure
    .input(PriceTableUpdateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const { id, ...rest } = input;
      const patch: Partial<typeof priceTables.$inferInsert> = {};
      if (rest.material !== undefined) patch.material = rest.material.trim();
      if (rest.precoPorM2 !== undefined)
        patch.precoPorM2 = rest.precoPorM2.toFixed(2);
      if (rest.acabamentoExtra !== undefined)
        patch.acabamentoExtra = rest.acabamentoExtra.toFixed(2);
      if (rest.ativo !== undefined) patch.ativo = rest.ativo ? 1 : 0;

      await db
        .update(priceTables)
        .set(patch)
        .where(
          and(eq(priceTables.id, id), eq(priceTables.companyId, ctx.companyId)),
        );
      const rows = await db
        .select()
        .from(priceTables)
        .where(
          and(eq(priceTables.id, id), eq(priceTables.companyId, ctx.companyId)),
        )
        .limit(1);
      if (!rows[0]) {
        throw new TRPCError({
          code: "NOT_FOUND",
          message: "Tabela de preço não encontrada",
        });
      }
      return rowToPriceTable(rows[0]);
    }),

  deletePriceTable: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await db
        .delete(priceTables)
        .where(
          and(
            eq(priceTables.id, input.id),
            eq(priceTables.companyId, ctx.companyId),
          ),
        );
      return { id: input.id };
    }),

  // ─── Quotes ──────────────────────────────────────────────────────────────
  list: companyProcedure
    .input(z.object({ status: StatusSchema.optional() }).optional())
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const where = input?.status
        ? and(eq(quotes.companyId, ctx.companyId), eq(quotes.status, input.status))
        : eq(quotes.companyId, ctx.companyId);
      const rows = await db
        .select({ quote: quotes, customer: customers })
        .from(quotes)
        .leftJoin(customers, eq(quotes.customerId, customers.id))
        .where(where)
        .orderBy(desc(quotes.createdAt));
      if (rows.length === 0) return [];

      const ids = rows.map((r) => r.quote.id);
      const items = await db
        .select()
        .from(quoteItems)
        .where(inArray(quoteItems.quoteId, ids));
      const itemsByQuote = new Map<string, QuoteItemRow[]>();
      for (const it of items) {
        const arr = itemsByQuote.get(it.quoteId) ?? [];
        arr.push(it);
        itemsByQuote.set(it.quoteId, arr);
      }
      return rows.map((r) =>
        rowToQuote(r.quote, r.customer, itemsByQuote.get(r.quote.id) ?? []),
      );
    }),

  getById: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      return loadQuoteFull(db, ctx.companyId, input.id);
    }),

  create: companyProcedure
    .input(QuoteCreateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const id = randomUUID();

      let customerRow: CustomerRow | null = null;
      if (input.customerId) {
        const found = await db
          .select()
          .from(customers)
          .where(
            and(
              eq(customers.id, input.customerId),
              eq(customers.companyId, ctx.companyId),
            ),
          )
          .limit(1);
        if (!found[0]) {
          throw new TRPCError({
            code: "NOT_FOUND",
            message: "Cliente não encontrado",
          });
        }
        customerRow = found[0];
      }

      const itemsComputed = input.items.map((it) => {
        const areaM2 = computeItemArea(it);
        const valorTotal = areaM2 * it.precoPorM2;
        return { ...it, areaM2, valorTotal };
      });
      const valorTotal = itemsComputed.reduce((s, i) => s + i.valorTotal, 0);

      await db.transaction(async (tx) => {
        await tx.insert(quotes).values({
          id,
          userId: ctx.user.id,
          companyId: ctx.companyId,
          customerId: customerRow?.id ?? null,
          titulo: input.titulo.trim(),
          validadeDias: input.validadeDias ?? 7,
          status: input.status ?? "rascunho",
          valorTotal: valorTotal.toFixed(2),
          observacoes: input.observacoes ?? null,
        });
        await tx.insert(quoteItems).values(
          itemsComputed.map((it) => ({
            id: randomUUID(),
            quoteId: id,
            descricao: it.descricao,
            material: it.material ?? null,
            larguraCm: it.larguraCm.toFixed(2),
            alturaCm: it.alturaCm.toFixed(2),
            quantidade: it.quantidade.toFixed(3),
            areaM2: it.areaM2.toFixed(4),
            precoPorM2: it.precoPorM2.toFixed(2),
            acabamento: it.acabamento ?? null,
            valorTotal: it.valorTotal.toFixed(2),
          })),
        );
      });

      return loadQuoteFull(db, ctx.companyId, id);
    }),

  update: companyProcedure
    .input(QuoteUpdateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const existing = await db
        .select()
        .from(quotes)
        .where(
          and(eq(quotes.id, input.id), eq(quotes.companyId, ctx.companyId)),
        )
        .limit(1);
      if (!existing[0]) {
        throw new TRPCError({
          code: "NOT_FOUND",
          message: "Orçamento não encontrado",
        });
      }

      const patch: Partial<typeof quotes.$inferInsert> = {};
      if (input.customerId !== undefined) patch.customerId = input.customerId;
      if (input.titulo !== undefined) patch.titulo = input.titulo.trim();
      if (input.validadeDias !== undefined)
        patch.validadeDias = input.validadeDias;
      if (input.observacoes !== undefined) patch.observacoes = input.observacoes;
      if (input.status !== undefined) patch.status = input.status;

      await db.transaction(async (tx) => {
        if (input.items) {
          const itemsComputed = input.items.map((it) => {
            const areaM2 = computeItemArea(it);
            const valorTotal = areaM2 * it.precoPorM2;
            return { ...it, areaM2, valorTotal };
          });
          const valorTotal = itemsComputed.reduce(
            (s, i) => s + i.valorTotal,
            0,
          );
          patch.valorTotal = valorTotal.toFixed(2);
          await tx
            .delete(quoteItems)
            .where(eq(quoteItems.quoteId, input.id));
          if (itemsComputed.length > 0) {
            await tx.insert(quoteItems).values(
              itemsComputed.map((it) => ({
                id: randomUUID(),
                quoteId: input.id,
                descricao: it.descricao,
                material: it.material ?? null,
                larguraCm: it.larguraCm.toFixed(2),
                alturaCm: it.alturaCm.toFixed(2),
                quantidade: it.quantidade.toFixed(3),
                areaM2: it.areaM2.toFixed(4),
                precoPorM2: it.precoPorM2.toFixed(2),
                acabamento: it.acabamento ?? null,
                valorTotal: it.valorTotal.toFixed(2),
              })),
            );
          }
        }
        if (Object.keys(patch).length > 0) {
          await tx
            .update(quotes)
            .set(patch)
            .where(
              and(eq(quotes.id, input.id), eq(quotes.companyId, ctx.companyId)),
            );
        }
      });

      return loadQuoteFull(db, ctx.companyId, input.id);
    }),

  delete: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await db
        .delete(quotes)
        .where(
          and(eq(quotes.id, input.id), eq(quotes.companyId, ctx.companyId)),
        );
      return { id: input.id };
    }),

  generatePdf: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const quote = await loadQuoteFull(db, ctx.companyId, input.id);
      const buffer = await renderQuotePdf(quote);
      const { key, url } = await storagePut(
        `quotes/${ctx.user.id}/${quote.id}.pdf`,
        buffer,
        "application/pdf",
      );
      await db
        .update(quotes)
        .set({ pdfKey: key })
        .where(
          and(eq(quotes.id, quote.id), eq(quotes.companyId, ctx.companyId)),
        );
      return { key, url };
    }),

  convertToPedido: companyProcedure
    .input(
      z.object({
        id: z.string().uuid(),
        dataEntrega: z.string().datetime().optional(),
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const quote = await loadQuoteFull(db, ctx.companyId, input.id);
      if (quote.status !== "aprovado") {
        throw new TRPCError({
          code: "BAD_REQUEST",
          message: "Apenas orçamentos aprovados podem ser convertidos",
        });
      }
      if (quote.items.length === 0) {
        throw new TRPCError({
          code: "BAD_REQUEST",
          message: "Orçamento sem itens",
        });
      }

      const pedidoId = randomUUID();
      const data = input.dataEntrega ?? new Date().toISOString();
      const firstDesc = quote.items[0].descricao;
      const produtoLabel =
        quote.items.length === 1
          ? firstDesc
          : `${firstDesc} (+${quote.items.length - 1})`;

      await db.transaction(async (tx) => {
        await tx.insert(pedidos).values({
          id: pedidoId,
          userId: ctx.user.id,
          companyId: ctx.companyId,
          customerId: quote.customerId,
          cliente: quote.customer?.nome ?? "—",
          produto: produtoLabel,
          valor: quote.valorTotal.toFixed(2),
          status: "aprovado",
          data,
          tipo: "Orcamento",
        });
        await tx.insert(orderItems).values(
          quote.items.map((it) => ({
            id: randomUUID(),
            pedidoId,
            produtoId: null,
            descricao: it.descricao,
            quantidade: it.quantidade.toFixed(3),
            valorUnit: (it.valorTotal / it.quantidade).toFixed(2),
            valorTotal: it.valorTotal.toFixed(2),
          })),
        );
        await tx
          .update(quotes)
          .set({ status: "convertido" })
          .where(
            and(eq(quotes.id, quote.id), eq(quotes.companyId, ctx.companyId)),
          );
      });

      return { pedidoId, quoteId: quote.id };
    }),
});
