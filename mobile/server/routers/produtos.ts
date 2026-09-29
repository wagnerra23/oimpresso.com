import { TRPCError } from "@trpc/server";
import { randomUUID } from "node:crypto";
import { and, eq } from "drizzle-orm";
import { z } from "zod";

import { produtos } from "../../drizzle/schema";
import { getDb } from "../db";
import { companyProcedure, router } from "../_core/trpc";

/**
 * tRPC router for Produto CRUD. All procedures are protected and scoped to
 * the authenticated user (`ctx.user.id`).
 *
 * v2: aceita campos opcionais do wizard (tipo, SKU, GTIN, fiscal, estoque,
 * ficha técnica). Todos são opt-in: omissão = manter; null = limpar.
 *
 * Drizzle returns DECIMAL columns as strings; we coerce to number at the
 * API boundary so clients keep using `preco: number` (matches the existing
 * `lib/erp-context` types).
 */

const TipoItemSchema = z.enum(["produto", "servico", "insumo"]);

const ProdutoExtendedFields = {
  tipoItem: TipoItemSchema.optional().nullable(),
  sku: z.string().max(64).optional().nullable(),
  gtin: z.string().max(14).optional().nullable(),
  ncm: z.string().max(8).optional().nullable(),
  cfop: z.string().max(4).optional().nullable(),
  cest: z.string().max(7).optional().nullable(),
  origem: z.string().max(1).optional().nullable(),
  unidade: z.string().max(6).optional().nullable(),
  precoCusto: z.number().nonnegative().optional().nullable(),
  precoPromo: z.number().nonnegative().optional().nullable(),
  margemLucroPercent: z.number().optional().nullable(),
  controlaEstoque: z.boolean().optional(),
  estoqueAtual: z.number().optional().nullable(),
  estoqueMinimo: z.number().optional().nullable(),
  localizacao: z.string().max(120).optional().nullable(),
  fornecedorId: z.string().uuid().optional().nullable(),
  imagemPrincipal: z.string().max(255).optional().nullable(),
  gramatura: z.string().max(64).optional().nullable(),
  acabamento: z.string().max(120).optional().nullable(),
  descricao: z.string().optional().nullable(),
};

const ProdutoCreateSchema = z.object({
  nome: z.string().min(1),
  categoria: z.string().min(1),
  preco: z.number().nonnegative(),
  ...ProdutoExtendedFields,
});

const ProdutoUpdateSchema = z.object({
  id: z.string().uuid(),
  nome: z.string().min(1).optional(),
  categoria: z.string().min(1).optional(),
  preco: z.number().nonnegative().optional(),
  ...ProdutoExtendedFields,
});

type ProdutoRow = typeof produtos.$inferSelect;

function rowToProduto(row: ProdutoRow) {
  return {
    id: row.id,
    nome: row.nome,
    categoria: row.categoria,
    preco: Number(row.preco),
    // ── v2 extended ─────────────────────────────────────────────────────
    tipoItem: row.tipoItem,
    sku: row.sku,
    gtin: row.gtin,
    ncm: row.ncm,
    cfop: row.cfop,
    cest: row.cest,
    origem: row.origem,
    unidade: row.unidade,
    precoCusto:
      row.precoCusto !== null && row.precoCusto !== undefined
        ? Number(row.precoCusto)
        : null,
    precoPromo:
      row.precoPromo !== null && row.precoPromo !== undefined
        ? Number(row.precoPromo)
        : null,
    margemLucroPercent:
      row.margemLucroPercent !== null && row.margemLucroPercent !== undefined
        ? Number(row.margemLucroPercent)
        : null,
    controlaEstoque: row.controlaEstoque === 1,
    estoqueAtual:
      row.estoqueAtual !== null && row.estoqueAtual !== undefined
        ? Number(row.estoqueAtual)
        : null,
    estoqueMinimo:
      row.estoqueMinimo !== null && row.estoqueMinimo !== undefined
        ? Number(row.estoqueMinimo)
        : null,
    localizacao: row.localizacao,
    fornecedorId: row.fornecedorId,
    imagemPrincipal: row.imagemPrincipal,
    gramatura: row.gramatura,
    acabamento: row.acabamento,
    descricao: row.descricao,
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

type WizardInput = z.infer<typeof ProdutoCreateSchema>;

function buildWizardPatch(input: Partial<WizardInput>) {
  const patch: Partial<typeof produtos.$inferInsert> = {};
  if (input.tipoItem !== undefined) patch.tipoItem = input.tipoItem;
  if (input.sku !== undefined) patch.sku = input.sku;
  if (input.gtin !== undefined) patch.gtin = input.gtin;
  if (input.ncm !== undefined) patch.ncm = input.ncm;
  if (input.cfop !== undefined) patch.cfop = input.cfop;
  if (input.cest !== undefined) patch.cest = input.cest;
  if (input.origem !== undefined) patch.origem = input.origem;
  if (input.unidade !== undefined) patch.unidade = input.unidade;
  if (input.precoCusto !== undefined)
    patch.precoCusto =
      input.precoCusto === null ? null : input.precoCusto.toFixed(2);
  if (input.precoPromo !== undefined)
    patch.precoPromo =
      input.precoPromo === null ? null : input.precoPromo.toFixed(2);
  if (input.margemLucroPercent !== undefined)
    patch.margemLucroPercent =
      input.margemLucroPercent === null
        ? null
        : input.margemLucroPercent.toFixed(2);
  if (input.controlaEstoque !== undefined)
    patch.controlaEstoque = input.controlaEstoque ? 1 : 0;
  if (input.estoqueAtual !== undefined)
    patch.estoqueAtual =
      input.estoqueAtual === null ? null : input.estoqueAtual.toFixed(3);
  if (input.estoqueMinimo !== undefined)
    patch.estoqueMinimo =
      input.estoqueMinimo === null ? null : input.estoqueMinimo.toFixed(3);
  if (input.localizacao !== undefined) patch.localizacao = input.localizacao;
  if (input.fornecedorId !== undefined) patch.fornecedorId = input.fornecedorId;
  if (input.imagemPrincipal !== undefined)
    patch.imagemPrincipal = input.imagemPrincipal;
  if (input.gramatura !== undefined) patch.gramatura = input.gramatura;
  if (input.acabamento !== undefined) patch.acabamento = input.acabamento;
  if (input.descricao !== undefined) patch.descricao = input.descricao;
  return patch;
}

export const produtosRouter = router({
  list: companyProcedure.query(async ({ ctx }) => {
    const db = await requireDb();
    const rows = await db
      .select()
      .from(produtos)
      .where(eq(produtos.companyId, ctx.companyId));
    return rows.map(rowToProduto);
  }),

  getById: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const rows = await db
        .select()
        .from(produtos)
        .where(and(eq(produtos.id, input.id), eq(produtos.companyId, ctx.companyId)))
        .limit(1);
      if (!rows[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Produto não encontrado" });
      }
      return rowToProduto(rows[0]);
    }),

  create: companyProcedure
    .input(ProdutoCreateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const id = randomUUID();
      await db.insert(produtos).values({
        id,
        userId: ctx.user.id,
        companyId: ctx.companyId,
        nome: input.nome,
        categoria: input.categoria,
        preco: input.preco.toFixed(2),
        ...buildWizardPatch(input),
      });
      const rows = await db
        .select()
        .from(produtos)
        .where(eq(produtos.id, id))
        .limit(1);
      return rowToProduto(rows[0]);
    }),

  update: companyProcedure
    .input(ProdutoUpdateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const { id, ...rest } = input;
      const patch: Partial<typeof produtos.$inferInsert> = {};
      if (rest.nome !== undefined) patch.nome = rest.nome;
      if (rest.categoria !== undefined) patch.categoria = rest.categoria;
      if (rest.preco !== undefined) patch.preco = rest.preco.toFixed(2);
      Object.assign(patch, buildWizardPatch(rest));

      await db
        .update(produtos)
        .set(patch)
        .where(and(eq(produtos.id, id), eq(produtos.companyId, ctx.companyId)));

      const rows = await db
        .select()
        .from(produtos)
        .where(and(eq(produtos.id, id), eq(produtos.companyId, ctx.companyId)))
        .limit(1);
      if (!rows[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Produto não encontrado" });
      }
      return rowToProduto(rows[0]);
    }),

  delete: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await db
        .delete(produtos)
        .where(and(eq(produtos.id, input.id), eq(produtos.companyId, ctx.companyId)));
      return { id: input.id };
    }),
});
