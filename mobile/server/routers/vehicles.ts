import { TRPCError } from "@trpc/server";
import { randomUUID } from "node:crypto";
import { and, asc, desc, eq, like } from "drizzle-orm";
import { z } from "zod";

import { customers, serviceOrders, vehicles } from "../../drizzle/schema";
import { getDb } from "../db";
import { companyProcedure, router } from "../_core/trpc";

/**
 * Vehicle CRUD + history. All procedures are protected and scoped to
 * `ctx.user.id`. UUID PKs are server-generated. Placa is uppercase-normalized
 * and validated against both old (ABC-1234) and Mercosul (ABC1D23) patterns.
 */

// Accepts both old (AAA-9999 / AAA9999) and Mercosul (AAA9A99) patterns.
const PLACA_REGEX = /^[A-Z]{3}[-]?[0-9][A-Z0-9][0-9]{2}$/;

const VehicleCreateSchema = z.object({
  customerId: z.string().uuid(),
  placa: z.string().min(1),
  chassi: z.string().max(17).optional().nullable(),
  marca: z.string().min(1).max(64),
  modelo: z.string().min(1).max(120),
  ano: z.number().int().optional().nullable(),
  cor: z.string().max(32).optional().nullable(),
  kmAtual: z.number().int().nonnegative().optional(),
  observacoes: z.string().optional().nullable(),
});

const VehicleUpdateSchema = VehicleCreateSchema.partial().extend({
  id: z.string().uuid(),
});

type VehicleRow = typeof vehicles.$inferSelect;
type CustomerLite = {
  id: string;
  nome: string;
  telefone: string | null;
} | null;

function rowToVehicle(row: VehicleRow, customer: CustomerLite) {
  return {
    id: row.id,
    customerId: row.customerId,
    placa: row.placa,
    chassi: row.chassi,
    marca: row.marca,
    modelo: row.modelo,
    ano: row.ano,
    cor: row.cor,
    kmAtual: row.kmAtual,
    observacoes: row.observacoes,
    createdAt: row.createdAt.toISOString(),
    updatedAt: row.updatedAt.toISOString(),
    customer,
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

function normalizePlaca(input: string): string {
  return input.trim().toUpperCase().replace(/\s+/g, "");
}

function validatePlaca(placa: string) {
  if (!PLACA_REGEX.test(placa)) {
    throw new TRPCError({
      code: "BAD_REQUEST",
      message: "Placa inválida (use formato ABC-1234 ou ABC1D23)",
    });
  }
}

export const vehiclesRouter = router({
  list: companyProcedure
    .input(z.object({ customerId: z.string().uuid().optional() }).optional())
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const conds = [eq(vehicles.companyId, ctx.companyId)];
      if (input?.customerId) conds.push(eq(vehicles.customerId, input.customerId));
      const rows = await db
        .select({
          vehicle: vehicles,
          customer: customers,
        })
        .from(vehicles)
        .leftJoin(customers, eq(vehicles.customerId, customers.id))
        .where(and(...conds))
        .orderBy(asc(vehicles.placa));
      return rows.map((r) =>
        rowToVehicle(
          r.vehicle,
          r.customer
            ? { id: r.customer.id, nome: r.customer.nome, telefone: r.customer.telefone }
            : null,
        ),
      );
    }),

  getById: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const rows = await db
        .select({
          vehicle: vehicles,
          customer: customers,
        })
        .from(vehicles)
        .leftJoin(customers, eq(vehicles.customerId, customers.id))
        .where(and(eq(vehicles.id, input.id), eq(vehicles.companyId, ctx.companyId)))
        .limit(1);
      if (!rows[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Veículo não encontrado" });
      }
      return rowToVehicle(
        rows[0].vehicle,
        rows[0].customer
          ? {
              id: rows[0].customer.id,
              nome: rows[0].customer.nome,
              telefone: rows[0].customer.telefone,
            }
          : null,
      );
    }),

  getHistory: companyProcedure
    .input(z.object({ vehicleId: z.string().uuid() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const owner = await db
        .select({ id: vehicles.id })
        .from(vehicles)
        .where(
          and(eq(vehicles.id, input.vehicleId), eq(vehicles.companyId, ctx.companyId)),
        )
        .limit(1);
      if (!owner[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Veículo não encontrado" });
      }
      const rows = await db
        .select()
        .from(serviceOrders)
        .where(
          and(
            eq(serviceOrders.vehicleId, input.vehicleId),
            eq(serviceOrders.companyId, ctx.companyId),
          ),
        )
        .orderBy(desc(serviceOrders.createdAt));
      return rows.map((r) => ({
        id: r.id,
        numero: r.numero,
        status: r.status,
        valorTotal: Number(r.valorTotal),
        dataEntrada: r.dataEntrada,
        dataSaida: r.dataSaida,
        createdAt: r.createdAt.toISOString(),
      }));
    }),

  searchByPlaca: companyProcedure
    .input(z.object({ q: z.string() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const term = normalizePlaca(input.q);
      if (term.length === 0) return [];
      const rows = await db
        .select({
          vehicle: vehicles,
          customer: customers,
        })
        .from(vehicles)
        .leftJoin(customers, eq(vehicles.customerId, customers.id))
        .where(
          and(
            eq(vehicles.companyId, ctx.companyId),
            like(vehicles.placa, `%${term}%`),
          ),
        )
        .orderBy(asc(vehicles.placa))
        .limit(20);
      return rows.map((r) =>
        rowToVehicle(
          r.vehicle,
          r.customer
            ? { id: r.customer.id, nome: r.customer.nome, telefone: r.customer.telefone }
            : null,
        ),
      );
    }),

  create: companyProcedure
    .input(VehicleCreateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const placa = normalizePlaca(input.placa);
      validatePlaca(placa);

      // Make sure customer belongs to this user.
      const owner = await db
        .select({ id: customers.id })
        .from(customers)
        .where(
          and(eq(customers.id, input.customerId), eq(customers.companyId, ctx.companyId)),
        )
        .limit(1);
      if (!owner[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Cliente não encontrado" });
      }

      const id = randomUUID();
      await db.insert(vehicles).values({
        id,
        userId: ctx.user.id,
        companyId: ctx.companyId,
        customerId: input.customerId,
        placa,
        chassi: input.chassi ?? null,
        marca: input.marca.trim(),
        modelo: input.modelo.trim(),
        ano: input.ano ?? null,
        cor: input.cor ?? null,
        kmAtual: input.kmAtual ?? 0,
        observacoes: input.observacoes ?? null,
      });
      const rows = await db
        .select({ vehicle: vehicles, customer: customers })
        .from(vehicles)
        .leftJoin(customers, eq(vehicles.customerId, customers.id))
        .where(eq(vehicles.id, id))
        .limit(1);
      return rowToVehicle(
        rows[0].vehicle,
        rows[0].customer
          ? {
              id: rows[0].customer.id,
              nome: rows[0].customer.nome,
              telefone: rows[0].customer.telefone,
            }
          : null,
      );
    }),

  update: companyProcedure
    .input(VehicleUpdateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const { id, ...rest } = input;
      const patch: Partial<typeof vehicles.$inferInsert> = {};
      if (rest.customerId !== undefined) patch.customerId = rest.customerId;
      if (rest.placa !== undefined) {
        const normalized = normalizePlaca(rest.placa);
        validatePlaca(normalized);
        patch.placa = normalized;
      }
      if (rest.chassi !== undefined) patch.chassi = rest.chassi;
      if (rest.marca !== undefined) patch.marca = rest.marca.trim();
      if (rest.modelo !== undefined) patch.modelo = rest.modelo.trim();
      if (rest.ano !== undefined) patch.ano = rest.ano;
      if (rest.cor !== undefined) patch.cor = rest.cor;
      if (rest.kmAtual !== undefined) patch.kmAtual = rest.kmAtual;
      if (rest.observacoes !== undefined) patch.observacoes = rest.observacoes;

      await db
        .update(vehicles)
        .set(patch)
        .where(and(eq(vehicles.id, id), eq(vehicles.companyId, ctx.companyId)));

      const rows = await db
        .select({ vehicle: vehicles, customer: customers })
        .from(vehicles)
        .leftJoin(customers, eq(vehicles.customerId, customers.id))
        .where(and(eq(vehicles.id, id), eq(vehicles.companyId, ctx.companyId)))
        .limit(1);
      if (!rows[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Veículo não encontrado" });
      }
      return rowToVehicle(
        rows[0].vehicle,
        rows[0].customer
          ? {
              id: rows[0].customer.id,
              nome: rows[0].customer.nome,
              telefone: rows[0].customer.telefone,
            }
          : null,
      );
    }),

  delete: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      try {
        await db
          .delete(vehicles)
          .where(and(eq(vehicles.id, input.id), eq(vehicles.companyId, ctx.companyId)));
      } catch (err) {
        const msg = err instanceof Error ? err.message : String(err);
        if (/1451|1217|foreign key|referenced/i.test(msg)) {
          throw new TRPCError({
            code: "CONFLICT",
            message: "Veículo possui ordens de serviço vinculadas",
          });
        }
        throw err;
      }
      return { id: input.id };
    }),
});
