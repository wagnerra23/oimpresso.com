import { TRPCError } from "@trpc/server";
import { randomUUID } from "node:crypto";
import { and, asc, desc, eq, like, or, sql } from "drizzle-orm";
import { z } from "zod";

import { inventory, inventoryMovements } from "../../drizzle/schema";
import { getDb } from "../db";
import { companyProcedure, router } from "../_core/trpc";

/**
 * Inventory CRUD + movement audit trail (F2-07).
 *
 * Quantity is read-only on `update`; the only way to change `inventory.quantidade`
 * is through `recordMovement`, which writes a paired `inventoryMovements` row
 * and updates the parent atomically. Soft delete via `ativo`.
 *
 * DECIMAL columns are exposed to clients as `number` (the schema stores them
 * as strings server-side for precision).
 */

const TipoMovimentoSchema = z.enum(["entrada", "saida", "ajuste", "perda"]);
const ReferenciaTipoSchema = z.string().max(32);

type InventoryRow = typeof inventory.$inferSelect;
type MovementRow = typeof inventoryMovements.$inferSelect;
type DbInstance = NonNullable<Awaited<ReturnType<typeof getDb>>>;

function rowToInventory(row: InventoryRow) {
  return {
    id: row.id,
    produtoId: row.produtoId,
    codigo: row.codigo,
    nome: row.nome,
    unidade: row.unidade,
    quantidade: Number(row.quantidade),
    estoqueMinimo: Number(row.estoqueMinimo),
    custoUnit: Number(row.custoUnit),
    precoVenda: row.precoVenda == null ? null : Number(row.precoVenda),
    fornecedorPrincipal: row.fornecedorPrincipal,
    localizacao: row.localizacao,
    ativo: row.ativo === 1,
    createdAt: row.createdAt.toISOString(),
    updatedAt: row.updatedAt.toISOString(),
  };
}

function rowToMovement(row: MovementRow) {
  return {
    id: row.id,
    inventoryId: row.inventoryId,
    tipo: row.tipo,
    quantidade: Number(row.quantidade),
    saldoApos: Number(row.saldoApos),
    motivo: row.motivo,
    referenciaTipo: row.referenciaTipo,
    referenciaId: row.referenciaId,
    custoUnitMovimento:
      row.custoUnitMovimento == null ? null : Number(row.custoUnitMovimento),
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

const CreateInputSchema = z.object({
  produtoId: z.string().uuid().optional().nullable(),
  codigo: z.string().max(64).optional().nullable(),
  nome: z.string().min(1).max(255),
  unidade: z.string().min(1).max(16).default("un"),
  quantidade: z.number().nonnegative().optional(),
  estoqueMinimo: z.number().nonnegative().optional(),
  custoUnit: z.number().nonnegative().optional(),
  precoVenda: z.number().nonnegative().optional().nullable(),
  fornecedorPrincipal: z.string().max(255).optional().nullable(),
  localizacao: z.string().max(120).optional().nullable(),
});

const UpdateInputSchema = z.object({
  id: z.string().uuid(),
  produtoId: z.string().uuid().optional().nullable(),
  codigo: z.string().max(64).optional().nullable(),
  nome: z.string().min(1).max(255).optional(),
  unidade: z.string().min(1).max(16).optional(),
  estoqueMinimo: z.number().nonnegative().optional(),
  custoUnit: z.number().nonnegative().optional(),
  precoVenda: z.number().nonnegative().optional().nullable(),
  fornecedorPrincipal: z.string().max(255).optional().nullable(),
  localizacao: z.string().max(120).optional().nullable(),
});

const RecordMovementInputSchema = z.object({
  inventoryId: z.string().uuid(),
  tipo: TipoMovimentoSchema,
  quantidade: z.number().positive(),
  motivo: z.string().max(255).optional().nullable(),
  referenciaTipo: ReferenciaTipoSchema.optional().nullable(),
  referenciaId: z.string().max(36).optional().nullable(),
  custoUnitMovimento: z.number().nonnegative().optional().nullable(),
});

export type RecordMovementParams = z.infer<typeof RecordMovementInputSchema>;

/**
 * Core movement logic — usable both from the tRPC procedure and from other
 * server routers (e.g., pedidos.update triggers a "saida" on delivery).
 *
 * Throws TRPCError on insufficient stock for non-ajuste types. The caller is
 * responsible for catching when the failure should not propagate.
 */
export async function recordMovementFor(
  db: DbInstance,
  companyId: string,
  userId: number,
  params: RecordMovementParams,
): Promise<{ movement: ReturnType<typeof rowToMovement>; newSaldo: number }> {
  return await db.transaction(async (tx) => {
    // SELECT … FOR UPDATE prevents two concurrent transactions from reading
    // the same saldo, both passing the negative-saldo check, and oversell-ing.
    // Without this lock TiDB/MySQL with REPEATABLE READ does NOT serialize the
    // read-modify-write pattern.
    const current = await tx
      .select()
      .from(inventory)
      .where(and(eq(inventory.id, params.inventoryId), eq(inventory.companyId, companyId)))
      .limit(1)
      .for("update");
    if (!current[0]) {
      throw new TRPCError({ code: "NOT_FOUND", message: "Item de estoque não encontrado" });
    }
    const saldoAtual = Number(current[0].quantidade);
    let newSaldo: number;
    switch (params.tipo) {
      case "entrada":
        newSaldo = saldoAtual + params.quantidade;
        break;
      case "saida":
      case "perda":
        newSaldo = saldoAtual - params.quantidade;
        break;
      case "ajuste":
        newSaldo = params.quantidade;
        break;
    }
    if (newSaldo < 0 && params.tipo !== "ajuste") {
      throw new TRPCError({ code: "BAD_REQUEST", message: "Estoque insuficiente" });
    }
    const movementId = randomUUID();
    await tx.insert(inventoryMovements).values({
      id: movementId,
      userId,
      companyId,
      inventoryId: params.inventoryId,
      tipo: params.tipo,
      quantidade: params.quantidade.toFixed(3),
      saldoApos: newSaldo.toFixed(3),
      motivo: params.motivo ?? null,
      referenciaTipo: params.referenciaTipo ?? null,
      referenciaId: params.referenciaId ?? null,
      custoUnitMovimento:
        params.custoUnitMovimento != null
          ? params.custoUnitMovimento.toFixed(2)
          : null,
    });
    await tx
      .update(inventory)
      .set({ quantidade: newSaldo.toFixed(3) })
      .where(eq(inventory.id, params.inventoryId));

    const fresh = await tx
      .select()
      .from(inventoryMovements)
      .where(eq(inventoryMovements.id, movementId))
      .limit(1);
    return { movement: rowToMovement(fresh[0]), newSaldo };
  });
}

/**
 * Helper for other routers (e.g., pedidos.ts when status transitions to
 * "entregue"). Best-effort: returns ok=false instead of throwing so callers
 * can decide whether to surface the failure.
 */
export async function deductForPedido(
  companyId: string,
  userId: number,
  pedidoId: string,
  items: Array<{ produtoId: string | null; quantidade: number; descricao: string }>,
): Promise<{ ok: boolean; warnings: string[] }> {
  const warnings: string[] = [];
  const db = await getDb();
  if (!db) {
    return { ok: false, warnings: ["DB indisponível"] };
  }
  for (const item of items) {
    if (!item.produtoId) {
      warnings.push(`Item "${item.descricao}" sem produtoId — ignorado`);
      continue;
    }
    const found = await db
      .select({ id: inventory.id })
      .from(inventory)
      .where(
        and(
          eq(inventory.companyId, companyId),
          eq(inventory.produtoId, item.produtoId),
          eq(inventory.ativo, 1),
        ),
      )
      .limit(1);
    const invRow = found[0];
    if (!invRow) {
      warnings.push(`Sem item de estoque para produtoId=${item.produtoId}`);
      continue;
    }
    try {
      await recordMovementFor(db, companyId, userId, {
        inventoryId: invRow.id,
        tipo: "saida",
        quantidade: item.quantidade,
        motivo: "Saída automática pedido entregue",
        referenciaTipo: "pedido",
        referenciaId: pedidoId,
      });
    } catch (e) {
      warnings.push(
        e instanceof Error ? e.message : `Falha ao baixar estoque ${invRow.id}`,
      );
    }
  }
  return { ok: warnings.length === 0, warnings };
}

export const inventoryRouter = router({
  list: companyProcedure
    .input(
      z
        .object({
          search: z.string().optional(),
          onlyLowStock: z.boolean().optional(),
          includeInactive: z.boolean().optional(),
        })
        .optional(),
    )
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const filters = [eq(inventory.companyId, ctx.companyId)];
      if (!input?.includeInactive) {
        filters.push(eq(inventory.ativo, 1));
      }
      const term = input?.search?.trim();
      if (term) {
        const pattern = `%${term}%`;
        const orExpr = or(
          like(inventory.nome, pattern),
          like(inventory.codigo, pattern),
        );
        if (orExpr) filters.push(orExpr);
      }
      if (input?.onlyLowStock) {
        filters.push(sql`${inventory.quantidade} <= ${inventory.estoqueMinimo}`);
      }
      const rows = await db
        .select()
        .from(inventory)
        .where(and(...filters))
        .orderBy(asc(inventory.nome));
      return rows.map(rowToInventory);
    }),

  getById: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const rows = await db
        .select()
        .from(inventory)
        .where(and(eq(inventory.id, input.id), eq(inventory.companyId, ctx.companyId)))
        .limit(1);
      if (!rows[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Item não encontrado" });
      }
      const movements = await db
        .select()
        .from(inventoryMovements)
        .where(eq(inventoryMovements.inventoryId, input.id))
        .orderBy(desc(inventoryMovements.createdAt))
        .limit(20);
      return {
        ...rowToInventory(rows[0]),
        movements: movements.map(rowToMovement),
      };
    }),

  create: companyProcedure
    .input(CreateInputSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const id = randomUUID();
      const quantidade = input.quantidade ?? 0;
      const result = await db.transaction(async (tx) => {
        await tx.insert(inventory).values({
          id,
          userId: ctx.user.id,
          companyId: ctx.companyId,
          produtoId: input.produtoId ?? null,
          codigo: input.codigo ?? null,
          nome: input.nome.trim(),
          unidade: input.unidade,
          quantidade: quantidade.toFixed(3),
          estoqueMinimo: (input.estoqueMinimo ?? 0).toFixed(3),
          custoUnit: (input.custoUnit ?? 0).toFixed(2),
          precoVenda:
            input.precoVenda != null ? input.precoVenda.toFixed(2) : null,
          fornecedorPrincipal: input.fornecedorPrincipal ?? null,
          localizacao: input.localizacao ?? null,
          ativo: 1,
        });
        await tx.insert(inventoryMovements).values({
          id: randomUUID(),
          userId: ctx.user.id,
          companyId: ctx.companyId,
          inventoryId: id,
          tipo: "entrada",
          quantidade: quantidade.toFixed(3),
          saldoApos: quantidade.toFixed(3),
          motivo: "Cadastro inicial",
        });
        const fresh = await tx
          .select()
          .from(inventory)
          .where(eq(inventory.id, id))
          .limit(1);
        return fresh[0];
      });
      return rowToInventory(result);
    }),

  update: companyProcedure
    .input(UpdateInputSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const { id, ...rest } = input;
      const patch: Partial<typeof inventory.$inferInsert> = {};
      if (rest.produtoId !== undefined) patch.produtoId = rest.produtoId;
      if (rest.codigo !== undefined) patch.codigo = rest.codigo;
      if (rest.nome !== undefined) patch.nome = rest.nome.trim();
      if (rest.unidade !== undefined) patch.unidade = rest.unidade;
      if (rest.estoqueMinimo !== undefined)
        patch.estoqueMinimo = rest.estoqueMinimo.toFixed(3);
      if (rest.custoUnit !== undefined) patch.custoUnit = rest.custoUnit.toFixed(2);
      if (rest.precoVenda !== undefined)
        patch.precoVenda = rest.precoVenda != null ? rest.precoVenda.toFixed(2) : null;
      if (rest.fornecedorPrincipal !== undefined)
        patch.fornecedorPrincipal = rest.fornecedorPrincipal;
      if (rest.localizacao !== undefined) patch.localizacao = rest.localizacao;

      await db
        .update(inventory)
        .set(patch)
        .where(and(eq(inventory.id, id), eq(inventory.companyId, ctx.companyId)));

      const rows = await db
        .select()
        .from(inventory)
        .where(and(eq(inventory.id, id), eq(inventory.companyId, ctx.companyId)))
        .limit(1);
      if (!rows[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Item não encontrado" });
      }
      return rowToInventory(rows[0]);
    }),

  softDelete: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await db
        .update(inventory)
        .set({ ativo: 0 })
        .where(and(eq(inventory.id, input.id), eq(inventory.companyId, ctx.companyId)));
      return { id: input.id, ativo: false };
    }),

  restore: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await db
        .update(inventory)
        .set({ ativo: 1 })
        .where(and(eq(inventory.id, input.id), eq(inventory.companyId, ctx.companyId)));
      return { id: input.id, ativo: true };
    }),

  recordMovement: companyProcedure
    .input(RecordMovementInputSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const result = await recordMovementFor(db, ctx.companyId, ctx.user.id, input);
      return result;
    }),

  listMovements: companyProcedure
    .input(
      z.object({
        inventoryId: z.string().uuid(),
        limit: z.number().int().positive().max(500).optional(),
      }),
    )
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      // Ownership check.
      const owner = await db
        .select({ id: inventory.id })
        .from(inventory)
        .where(
          and(
            eq(inventory.id, input.inventoryId),
            eq(inventory.companyId, ctx.companyId),
          ),
        )
        .limit(1);
      if (!owner[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Item não encontrado" });
      }
      const rows = await db
        .select()
        .from(inventoryMovements)
        .where(eq(inventoryMovements.inventoryId, input.inventoryId))
        .orderBy(desc(inventoryMovements.createdAt))
        .limit(input.limit ?? 100);
      return rows.map(rowToMovement);
    }),

  lowStockAlert: companyProcedure.query(async ({ ctx }) => {
    const db = await requireDb();
    const rows = await db
      .select()
      .from(inventory)
      .where(
        and(
          eq(inventory.companyId, ctx.companyId),
          eq(inventory.ativo, 1),
          sql`${inventory.quantidade} <= ${inventory.estoqueMinimo}`,
        ),
      )
      .orderBy(asc(inventory.nome));
    return rows.map(rowToInventory);
  }),
});
