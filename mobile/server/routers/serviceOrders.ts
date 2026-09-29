import { TRPCError } from "@trpc/server";
import { randomUUID } from "node:crypto";
import { and, asc, desc, eq, sql } from "drizzle-orm";
import { z } from "zod";

import {
  customers,
  serviceOrderItems,
  serviceOrderPhotos,
  serviceOrderPublicTokens,
  serviceOrders,
  vehicles,
} from "../../drizzle/schema";
import { getDb } from "../db";
import { sendPushToUser } from "../push";
import { companyProcedure, router } from "../_core/trpc";

function publicBaseUrl(): string {
  // Prefer explicit override; fall back to local dev API.
  const raw =
    process.env.PUBLIC_BASE_URL ??
    process.env.API_BASE_URL ??
    process.env.EXPO_PUBLIC_API_BASE_URL ??
    "http://localhost:3000";
  return raw.replace(/\/+$/, "");
}

/**
 * Service Order (Ordem de Serviço) router. Handles full OS workflow:
 * - CRUD with line items (peças + serviços)
 * - 9-stage status pipeline (recepcao → ... → entregue)
 * - Photos (entrada/durante/saida)
 * - Public approval tokens (server-rendered HTML form)
 */

const STATUS_VALUES = [
  "recepcao",
  "diagnostico",
  "orcamento",
  "aguardando_aprovacao",
  "aguardando_pecas",
  "em_execucao",
  "revisao",
  "pronto",
  "entregue",
] as const;
const StatusSchema = z.enum(STATUS_VALUES);

const PhotoTipoSchema = z.enum(["entrada", "durante", "saida"]);
const ItemTipoSchema = z.enum(["peca", "servico"]);

const ItemInputSchema = z.object({
  tipo: ItemTipoSchema,
  codigo: z.string().optional().nullable(),
  descricao: z.string().min(1),
  quantidade: z.number().positive().default(1),
  valorUnit: z.number().nonnegative(),
  fornecedor: z.string().optional().nullable(),
  tempoEstimadoHoras: z.number().nonnegative().optional().nullable(),
  mecanicoResponsavel: z.string().optional().nullable(),
});

const ServiceOrderCreateSchema = z.object({
  customerId: z.string().uuid(),
  vehicleId: z.string().uuid(),
  queixaCliente: z.string().optional().nullable(),
  diagnostico: z.string().optional().nullable(),
  kmEntrada: z.number().int().nonnegative().optional().nullable(),
  dataEntrada: z.string().optional(),
  dataPrevista: z.string().optional().nullable(),
  items: z.array(ItemInputSchema).optional(),
});

const ServiceOrderUpdateSchema = z.object({
  id: z.string().uuid(),
  customerId: z.string().uuid().optional(),
  vehicleId: z.string().uuid().optional(),
  queixaCliente: z.string().optional().nullable(),
  diagnostico: z.string().optional().nullable(),
  kmEntrada: z.number().int().nonnegative().optional().nullable(),
  kmSaida: z.number().int().nonnegative().optional().nullable(),
  status: StatusSchema.optional(),
  dataEntrada: z.string().optional(),
  dataPrevista: z.string().optional().nullable(),
  dataSaida: z.string().optional().nullable(),
  items: z.array(ItemInputSchema).optional(),
});

type SORow = typeof serviceOrders.$inferSelect;
type VehicleRow = typeof vehicles.$inferSelect;
type CustomerRow = typeof customers.$inferSelect;
type ItemRow = typeof serviceOrderItems.$inferSelect;
type PhotoRow = typeof serviceOrderPhotos.$inferSelect;
type TokenRow = typeof serviceOrderPublicTokens.$inferSelect;

function itemToWire(i: ItemRow) {
  return {
    id: i.id,
    serviceOrderId: i.serviceOrderId,
    tipo: i.tipo,
    codigo: i.codigo,
    descricao: i.descricao,
    quantidade: Number(i.quantidade),
    valorUnit: Number(i.valorUnit),
    valorTotal: Number(i.valorTotal),
    fornecedor: i.fornecedor,
    tempoEstimadoHoras: i.tempoEstimadoHoras ? Number(i.tempoEstimadoHoras) : null,
    mecanicoResponsavel: i.mecanicoResponsavel,
    createdAt: i.createdAt.toISOString(),
  };
}

function photoToWire(p: PhotoRow) {
  return {
    id: p.id,
    serviceOrderId: p.serviceOrderId,
    tipo: p.tipo,
    fileKey: p.fileKey,
    descricao: p.descricao,
    createdAt: p.createdAt.toISOString(),
  };
}

function tokenToWire(t: TokenRow) {
  return {
    id: t.id,
    serviceOrderId: t.serviceOrderId,
    token: t.token,
    expiresAt: t.expiresAt,
    createdAt: t.createdAt.toISOString(),
  };
}

function vehicleLite(v: VehicleRow | null) {
  return v
    ? {
        id: v.id,
        placa: v.placa,
        marca: v.marca,
        modelo: v.modelo,
        ano: v.ano,
        cor: v.cor,
      }
    : null;
}

function customerLite(c: CustomerRow | null) {
  return c
    ? {
        id: c.id,
        nome: c.nome,
        telefone: c.telefone,
        email: c.email,
      }
    : null;
}

function rowToServiceOrder(
  row: SORow,
  vehicle: VehicleRow | null,
  customer: CustomerRow | null,
  items: ItemRow[] = [],
  photos: PhotoRow[] = [],
  publicTokens: TokenRow[] = [],
) {
  return {
    id: row.id,
    numero: row.numero,
    customerId: row.customerId,
    vehicleId: row.vehicleId,
    queixaCliente: row.queixaCliente,
    diagnostico: row.diagnostico,
    kmEntrada: row.kmEntrada,
    kmSaida: row.kmSaida,
    status: row.status,
    valorPecas: Number(row.valorPecas),
    valorMaoObra: Number(row.valorMaoObra),
    valorTotal: Number(row.valorTotal),
    dataEntrada: row.dataEntrada,
    dataPrevista: row.dataPrevista,
    dataSaida: row.dataSaida,
    aprovacaoCliente:
      row.aprovacaoCliente === null ? null : row.aprovacaoCliente === 1,
    aprovacaoData: row.aprovacaoData,
    aprovacaoIp: row.aprovacaoIp,
    aprovacaoComentario: row.aprovacaoComentario,
    createdAt: row.createdAt.toISOString(),
    updatedAt: row.updatedAt.toISOString(),
    vehicle: vehicleLite(vehicle),
    customer: customerLite(customer),
    items: items.map(itemToWire),
    photos: photos.map(photoToWire),
    publicTokens: publicTokens.map(tokenToWire),
  };
}

function computeTotals(items: { tipo: "peca" | "servico"; quantidade: number; valorUnit: number }[]) {
  let pecas = 0;
  let maoObra = 0;
  for (const it of items) {
    const total = it.quantidade * it.valorUnit;
    if (it.tipo === "peca") pecas += total;
    else maoObra += total;
  }
  return { valorPecas: pecas, valorMaoObra: maoObra, valorTotal: pecas + maoObra };
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

async function loadFullById(
  db: Awaited<ReturnType<typeof requireDb>>,
  companyId: string,
  id: string,
) {
  const baseRows = await db
    .select({ so: serviceOrders, vehicle: vehicles, customer: customers })
    .from(serviceOrders)
    .leftJoin(vehicles, eq(serviceOrders.vehicleId, vehicles.id))
    .leftJoin(customers, eq(serviceOrders.customerId, customers.id))
    .where(and(eq(serviceOrders.id, id), eq(serviceOrders.companyId, companyId)))
    .limit(1);
  if (!baseRows[0]) {
    throw new TRPCError({ code: "NOT_FOUND", message: "OS não encontrada" });
  }
  const [items, photos, tokens] = await Promise.all([
    db
      .select()
      .from(serviceOrderItems)
      .where(eq(serviceOrderItems.serviceOrderId, id))
      .orderBy(asc(serviceOrderItems.createdAt)),
    db
      .select()
      .from(serviceOrderPhotos)
      .where(eq(serviceOrderPhotos.serviceOrderId, id))
      .orderBy(asc(serviceOrderPhotos.createdAt)),
    db
      .select()
      .from(serviceOrderPublicTokens)
      .where(eq(serviceOrderPublicTokens.serviceOrderId, id))
      .orderBy(desc(serviceOrderPublicTokens.createdAt)),
  ]);
  return rowToServiceOrder(
    baseRows[0].so,
    baseRows[0].vehicle,
    baseRows[0].customer,
    items,
    photos,
    tokens,
  );
}

export const serviceOrdersRouter = router({
  list: companyProcedure
    .input(
      z
        .object({
          status: StatusSchema.optional(),
          vehicleId: z.string().uuid().optional(),
          customerId: z.string().uuid().optional(),
        })
        .optional(),
    )
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const conds = [eq(serviceOrders.companyId, ctx.companyId)];
      if (input?.status) conds.push(eq(serviceOrders.status, input.status));
      if (input?.vehicleId) conds.push(eq(serviceOrders.vehicleId, input.vehicleId));
      if (input?.customerId)
        conds.push(eq(serviceOrders.customerId, input.customerId));
      const rows = await db
        .select({ so: serviceOrders, vehicle: vehicles, customer: customers })
        .from(serviceOrders)
        .leftJoin(vehicles, eq(serviceOrders.vehicleId, vehicles.id))
        .leftJoin(customers, eq(serviceOrders.customerId, customers.id))
        .where(and(...conds))
        .orderBy(desc(serviceOrders.createdAt));

      if (rows.length === 0) return [];
      // Fetch items for badge counts? Not requested. Skip items/photos for list.
      return rows.map((r) =>
        rowToServiceOrder(r.so, r.vehicle, r.customer, [], [], []),
      );
    }),

  getById: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      return loadFullById(db, ctx.companyId, input.id);
    }),

  create: companyProcedure
    .input(ServiceOrderCreateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const id = randomUUID();
      const dataEntrada = input.dataEntrada ?? new Date().toISOString();

      // Validate ownership of customer + vehicle
      const [custOk, vehOk] = await Promise.all([
        db
          .select({ id: customers.id })
          .from(customers)
          .where(
            and(eq(customers.id, input.customerId), eq(customers.companyId, ctx.companyId)),
          )
          .limit(1),
        db
          .select({ id: vehicles.id })
          .from(vehicles)
          .where(
            and(eq(vehicles.id, input.vehicleId), eq(vehicles.companyId, ctx.companyId)),
          )
          .limit(1),
      ]);
      if (!custOk[0])
        throw new TRPCError({ code: "NOT_FOUND", message: "Cliente não encontrado" });
      if (!vehOk[0])
        throw new TRPCError({ code: "NOT_FOUND", message: "Veículo não encontrado" });

      const items = input.items ?? [];
      const totals = computeTotals(items);

      await db.transaction(async (tx) => {
        // Sequential numero per user. SELECT IFNULL(MAX(...),0)+1.
        const maxRow = await tx
          .select({ max: sql<number | null>`MAX(${serviceOrders.numero})` })
          .from(serviceOrders)
          .where(eq(serviceOrders.companyId, ctx.companyId));
        const numero = (maxRow[0]?.max ?? 0) + 1;

        await tx.insert(serviceOrders).values({
          id,
          userId: ctx.user.id,
          companyId: ctx.companyId,
          customerId: input.customerId,
          vehicleId: input.vehicleId,
          numero,
          queixaCliente: input.queixaCliente ?? null,
          diagnostico: null,
          kmEntrada: input.kmEntrada ?? null,
          kmSaida: null,
          status: "recepcao",
          valorPecas: totals.valorPecas.toFixed(2),
          valorMaoObra: totals.valorMaoObra.toFixed(2),
          valorTotal: totals.valorTotal.toFixed(2),
          dataEntrada,
          dataPrevista: input.dataPrevista ?? null,
        });

        if (items.length > 0) {
          await tx.insert(serviceOrderItems).values(
            items.map((it) => ({
              id: randomUUID(),
              serviceOrderId: id,
              tipo: it.tipo,
              codigo: it.codigo ?? null,
              descricao: it.descricao,
              quantidade: it.quantidade.toFixed(3),
              valorUnit: it.valorUnit.toFixed(2),
              valorTotal: (it.quantidade * it.valorUnit).toFixed(2),
              fornecedor: it.fornecedor ?? null,
              tempoEstimadoHoras:
                it.tempoEstimadoHoras != null
                  ? it.tempoEstimadoHoras.toFixed(2)
                  : null,
              mecanicoResponsavel: it.mecanicoResponsavel ?? null,
            })),
          );
        }
      });

      return loadFullById(db, ctx.companyId, id);
    }),

  update: companyProcedure
    .input(ServiceOrderUpdateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const existing = await db
        .select()
        .from(serviceOrders)
        .where(
          and(eq(serviceOrders.id, input.id), eq(serviceOrders.companyId, ctx.companyId)),
        )
        .limit(1);
      if (!existing[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "OS não encontrada" });
      }

      const patch: Partial<typeof serviceOrders.$inferInsert> = {};
      if (input.customerId !== undefined) patch.customerId = input.customerId;
      if (input.vehicleId !== undefined) patch.vehicleId = input.vehicleId;
      if (input.queixaCliente !== undefined) patch.queixaCliente = input.queixaCliente;
      if (input.diagnostico !== undefined) patch.diagnostico = input.diagnostico;
      if (input.kmEntrada !== undefined) patch.kmEntrada = input.kmEntrada;
      if (input.kmSaida !== undefined) patch.kmSaida = input.kmSaida;
      if (input.status !== undefined) patch.status = input.status;
      if (input.dataEntrada !== undefined) patch.dataEntrada = input.dataEntrada;
      if (input.dataPrevista !== undefined) patch.dataPrevista = input.dataPrevista;
      if (input.dataSaida !== undefined) patch.dataSaida = input.dataSaida;

      await db.transaction(async (tx) => {
        if (input.items) {
          const totals = computeTotals(input.items);
          patch.valorPecas = totals.valorPecas.toFixed(2);
          patch.valorMaoObra = totals.valorMaoObra.toFixed(2);
          patch.valorTotal = totals.valorTotal.toFixed(2);

          await tx
            .delete(serviceOrderItems)
            .where(eq(serviceOrderItems.serviceOrderId, input.id));
          if (input.items.length > 0) {
            await tx.insert(serviceOrderItems).values(
              input.items.map((it) => ({
                id: randomUUID(),
                serviceOrderId: input.id,
                tipo: it.tipo,
                codigo: it.codigo ?? null,
                descricao: it.descricao,
                quantidade: it.quantidade.toFixed(3),
                valorUnit: it.valorUnit.toFixed(2),
                valorTotal: (it.quantidade * it.valorUnit).toFixed(2),
                fornecedor: it.fornecedor ?? null,
                tempoEstimadoHoras:
                  it.tempoEstimadoHoras != null
                    ? it.tempoEstimadoHoras.toFixed(2)
                    : null,
                mecanicoResponsavel: it.mecanicoResponsavel ?? null,
              })),
            );
          }
        }

        if (Object.keys(patch).length > 0) {
          await tx
            .update(serviceOrders)
            .set(patch)
            .where(
              and(eq(serviceOrders.id, input.id), eq(serviceOrders.companyId, ctx.companyId)),
            );
        }
      });

      return loadFullById(db, ctx.companyId, input.id);
    }),

  delete: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      // Photos & items & tokens cascade via FK.
      await db
        .delete(serviceOrders)
        .where(
          and(eq(serviceOrders.id, input.id), eq(serviceOrders.companyId, ctx.companyId)),
        );
      return { id: input.id };
    }),

  advanceStatus: companyProcedure
    .input(z.object({ id: z.string().uuid(), status: StatusSchema }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const existing = await db
        .select({ so: serviceOrders, vehicle: vehicles, customer: customers })
        .from(serviceOrders)
        .leftJoin(vehicles, eq(serviceOrders.vehicleId, vehicles.id))
        .leftJoin(customers, eq(serviceOrders.customerId, customers.id))
        .where(
          and(eq(serviceOrders.id, input.id), eq(serviceOrders.companyId, ctx.companyId)),
        )
        .limit(1);
      if (!existing[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "OS não encontrada" });
      }

      await db
        .update(serviceOrders)
        .set({ status: input.status })
        .where(
          and(eq(serviceOrders.id, input.id), eq(serviceOrders.companyId, ctx.companyId)),
        );

      try {
        const so = existing[0].so;
        const customer = existing[0].customer;
        const vehicle = existing[0].vehicle;
        sendPushToUser(ctx.user.id, {
          title: `OS #${so.numero}`,
          body: `${customer?.nome ?? ""} - ${vehicle?.placa ?? ""} agora em ${input.status}`,
          data: { type: "os", id: input.id },
        }).catch(() => {});

        // F2-08: notificar cliente via WhatsApp quando OS fica "pronto" (template os_pronta).
        if (input.status === "pronto" && customer?.telefone) {
          const { sendWhatsApp, whatsappTemplates } = await import("../_core/whatsapp");
          sendWhatsApp({
            userId: ctx.user.id,
            companyId: ctx.companyId,
            telefone: customer.telefone,
            mensagem: whatsappTemplates.os_pronta(
              customer.nome ?? "",
              vehicle?.placa ?? "",
            ),
            customerId: customer.id,
            referenciaTipo: "os",
            referenciaId: input.id,
          }).catch(() => {});
        }
      } catch {
        // ignore — notifications are fire-and-forget
      }

      return loadFullById(db, ctx.companyId, input.id);
    }),

  recordApproval: companyProcedure
    .input(
      z.object({
        id: z.string().uuid(),
        aprovado: z.boolean(),
        comentario: z.string().optional().nullable(),
        ip: z.string().optional().nullable(),
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await db
        .update(serviceOrders)
        .set({
          aprovacaoCliente: input.aprovado ? 1 : 0,
          aprovacaoData: new Date().toISOString(),
          aprovacaoIp: input.ip ?? null,
          aprovacaoComentario: input.comentario ?? null,
        })
        .where(
          and(eq(serviceOrders.id, input.id), eq(serviceOrders.companyId, ctx.companyId)),
        );
      return loadFullById(db, ctx.companyId, input.id);
    }),

  addPhoto: companyProcedure
    .input(
      z.object({
        serviceOrderId: z.string().uuid(),
        fileKey: z.string().min(1),
        tipo: PhotoTipoSchema,
        descricao: z.string().optional().nullable(),
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const owner = await db
        .select({ id: serviceOrders.id })
        .from(serviceOrders)
        .where(
          and(
            eq(serviceOrders.id, input.serviceOrderId),
            eq(serviceOrders.companyId, ctx.companyId),
          ),
        )
        .limit(1);
      if (!owner[0])
        throw new TRPCError({ code: "NOT_FOUND", message: "OS não encontrada" });

      const id = randomUUID();
      await db.insert(serviceOrderPhotos).values({
        id,
        serviceOrderId: input.serviceOrderId,
        tipo: input.tipo,
        fileKey: input.fileKey,
        descricao: input.descricao ?? null,
      });
      const rows = await db
        .select()
        .from(serviceOrderPhotos)
        .where(eq(serviceOrderPhotos.id, id))
        .limit(1);
      return photoToWire(rows[0]);
    }),

  removePhoto: companyProcedure
    .input(z.object({ photoId: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      // Validate ownership via join
      const found = await db
        .select({ photo: serviceOrderPhotos })
        .from(serviceOrderPhotos)
        .innerJoin(
          serviceOrders,
          eq(serviceOrderPhotos.serviceOrderId, serviceOrders.id),
        )
        .where(
          and(
            eq(serviceOrderPhotos.id, input.photoId),
            eq(serviceOrders.companyId, ctx.companyId),
          ),
        )
        .limit(1);
      if (!found[0])
        throw new TRPCError({ code: "NOT_FOUND", message: "Foto não encontrada" });

      await db
        .delete(serviceOrderPhotos)
        .where(eq(serviceOrderPhotos.id, input.photoId));
      return { id: input.photoId };
    }),

  addItem: companyProcedure
    .input(
      z.object({
        serviceOrderId: z.string().uuid(),
        item: ItemInputSchema,
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const so = await db
        .select()
        .from(serviceOrders)
        .where(
          and(
            eq(serviceOrders.id, input.serviceOrderId),
            eq(serviceOrders.companyId, ctx.companyId),
          ),
        )
        .limit(1);
      if (!so[0])
        throw new TRPCError({ code: "NOT_FOUND", message: "OS não encontrada" });

      const newId = randomUUID();
      await db.transaction(async (tx) => {
        await tx.insert(serviceOrderItems).values({
          id: newId,
          serviceOrderId: input.serviceOrderId,
          tipo: input.item.tipo,
          codigo: input.item.codigo ?? null,
          descricao: input.item.descricao,
          quantidade: input.item.quantidade.toFixed(3),
          valorUnit: input.item.valorUnit.toFixed(2),
          valorTotal: (input.item.quantidade * input.item.valorUnit).toFixed(2),
          fornecedor: input.item.fornecedor ?? null,
          tempoEstimadoHoras:
            input.item.tempoEstimadoHoras != null
              ? input.item.tempoEstimadoHoras.toFixed(2)
              : null,
          mecanicoResponsavel: input.item.mecanicoResponsavel ?? null,
        });

        // Recompute totals.
        const allItems = await tx
          .select()
          .from(serviceOrderItems)
          .where(eq(serviceOrderItems.serviceOrderId, input.serviceOrderId));
        const totals = computeTotals(
          allItems.map((i) => ({
            tipo: i.tipo,
            quantidade: Number(i.quantidade),
            valorUnit: Number(i.valorUnit),
          })),
        );
        await tx
          .update(serviceOrders)
          .set({
            valorPecas: totals.valorPecas.toFixed(2),
            valorMaoObra: totals.valorMaoObra.toFixed(2),
            valorTotal: totals.valorTotal.toFixed(2),
          })
          .where(eq(serviceOrders.id, input.serviceOrderId));
      });

      return loadFullById(db, ctx.companyId, input.serviceOrderId);
    }),

  removeItem: companyProcedure
    .input(z.object({ itemId: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const found = await db
        .select({ item: serviceOrderItems, soId: serviceOrders.id })
        .from(serviceOrderItems)
        .innerJoin(
          serviceOrders,
          eq(serviceOrderItems.serviceOrderId, serviceOrders.id),
        )
        .where(
          and(
            eq(serviceOrderItems.id, input.itemId),
            eq(serviceOrders.companyId, ctx.companyId),
          ),
        )
        .limit(1);
      if (!found[0])
        throw new TRPCError({ code: "NOT_FOUND", message: "Item não encontrado" });
      const soId = found[0].soId;

      await db.transaction(async (tx) => {
        await tx
          .delete(serviceOrderItems)
          .where(eq(serviceOrderItems.id, input.itemId));
        const allItems = await tx
          .select()
          .from(serviceOrderItems)
          .where(eq(serviceOrderItems.serviceOrderId, soId));
        const totals = computeTotals(
          allItems.map((i) => ({
            tipo: i.tipo,
            quantidade: Number(i.quantidade),
            valorUnit: Number(i.valorUnit),
          })),
        );
        await tx
          .update(serviceOrders)
          .set({
            valorPecas: totals.valorPecas.toFixed(2),
            valorMaoObra: totals.valorMaoObra.toFixed(2),
            valorTotal: totals.valorTotal.toFixed(2),
          })
          .where(eq(serviceOrders.id, soId));
      });

      return loadFullById(db, ctx.companyId, soId);
    }),

  generateOrcamentoLink: companyProcedure
    .input(
      z.object({
        id: z.string().uuid(),
        expiresInDays: z.number().int().positive().optional(),
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const owner = await db
        .select({ id: serviceOrders.id })
        .from(serviceOrders)
        .where(
          and(eq(serviceOrders.id, input.id), eq(serviceOrders.companyId, ctx.companyId)),
        )
        .limit(1);
      if (!owner[0])
        throw new TRPCError({ code: "NOT_FOUND", message: "OS não encontrada" });

      const token = randomUUID().replace(/-/g, "");
      const expiresAt = input.expiresInDays
        ? new Date(
            Date.now() + input.expiresInDays * 24 * 60 * 60 * 1000,
          ).toISOString()
        : null;
      const id = randomUUID();
      await db.insert(serviceOrderPublicTokens).values({
        id,
        serviceOrderId: input.id,
        token,
        expiresAt,
      });
      const base = publicBaseUrl();
      return {
        id,
        token,
        expiresAt,
        url: `${base}/os/${token}`,
      };
    }),
});

export type ServiceOrdersRouter = typeof serviceOrdersRouter;
export { STATUS_VALUES as SERVICE_ORDER_STATUS };
