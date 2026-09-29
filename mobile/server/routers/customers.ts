import { TRPCError } from "@trpc/server";
import { randomUUID } from "node:crypto";
import { and, asc, eq, like, or } from "drizzle-orm";
import { z } from "zod";

import { customers } from "../../drizzle/schema";
import { getDb } from "../db";
import { companyProcedure, router } from "../_core/trpc";

/**
 * Customer (Cliente / Pessoa) CRUD. All procedures are protected and scoped
 * to `ctx.companyId`. UUID PKs are server-generated.
 *
 * v2: aceita novos campos opcionais do wizard (papéis, endereço completo,
 * fiscal PJ, LGPD, comercial). Todos opcionais — o front pode mandar parcial.
 */

const TipoSchema = z.enum(["PF", "PJ"]);
const PapelSchema = z.enum([
  "cliente",
  "fornecedor",
  "funcionario",
  "transportadora",
]);
const IndicadorIeSchema = z.enum([
  "contribuinte",
  "isento",
  "nao_contribuinte",
]);
const ClassificacaoSchema = z.enum(["A", "B", "C", "D"]);

const CustomerExtendedFields = {
  // Identificação avançada
  papeis: z.array(PapelSchema).optional().nullable(),
  razaoSocial: z.string().max(255).optional().nullable(),
  nomeFantasia: z.string().max(255).optional().nullable(),
  inscricaoEstadual: z.string().max(32).optional().nullable(),
  indicadorIe: IndicadorIeSchema.optional().nullable(),
  // Endereço estruturado
  cep: z.string().max(9).optional().nullable(),
  logradouro: z.string().max(255).optional().nullable(),
  numero: z.string().max(20).optional().nullable(),
  complemento: z.string().max(100).optional().nullable(),
  bairro: z.string().max(120).optional().nullable(),
  cidade: z.string().max(120).optional().nullable(),
  uf: z.string().length(2).optional().nullable(),
  codigoMunicipioIbge: z.string().max(7).optional().nullable(),
  // Contato extra
  whatsapp: z.string().max(32).optional().nullable(),
  emailNfe: z
    .string()
    .email()
    .max(320)
    .optional()
    .nullable()
    .or(z.literal("")),
  // LGPD
  aceitaWhatsapp: z.boolean().optional(),
  aceitaEmail: z.boolean().optional(),
  aceitaSms: z.boolean().optional(),
  consentimentoData: z.string().max(32).optional().nullable(),
  consentimentoIp: z.string().max(64).optional().nullable(),
  // Comercial
  classificacao: ClassificacaoSchema.optional().nullable(),
  limiteCredito: z.number().nonnegative().optional().nullable(),
  prazoPadraoDias: z.number().int().nonnegative().optional().nullable(),
};

const CustomerCreateSchema = z.object({
  nome: z.string().min(1),
  tipo: TipoSchema.optional(),
  documento: z.string().max(20).optional().nullable(),
  telefone: z.string().max(32).optional().nullable(),
  email: z.string().email().max(320).optional().nullable().or(z.literal("")),
  endereco: z.string().optional().nullable(),
  observacoes: z.string().optional().nullable(),
  ...CustomerExtendedFields,
});

const CustomerUpdateSchema = CustomerCreateSchema.partial().extend({
  id: z.string().uuid(),
});

type CustomerRow = typeof customers.$inferSelect;

function rowToCustomer(row: CustomerRow) {
  return {
    id: row.id,
    nome: row.nome,
    tipo: row.tipo,
    documento: row.documento,
    telefone: row.telefone,
    email: row.email,
    endereco: row.endereco,
    observacoes: row.observacoes,
    // ── v2 extended ─────────────────────────────────────────────────────
    papeis: (row.papeis as string[] | null) ?? null,
    razaoSocial: row.razaoSocial,
    nomeFantasia: row.nomeFantasia,
    inscricaoEstadual: row.inscricaoEstadual,
    indicadorIe: row.indicadorIe,
    cep: row.cep,
    logradouro: row.logradouro,
    numero: row.numero,
    complemento: row.complemento,
    bairro: row.bairro,
    cidade: row.cidade,
    uf: row.uf,
    codigoMunicipioIbge: row.codigoMunicipioIbge,
    whatsapp: row.whatsapp,
    emailNfe: row.emailNfe,
    aceitaWhatsapp: row.aceitaWhatsapp === 1,
    aceitaEmail: row.aceitaEmail === 1,
    aceitaSms: row.aceitaSms === 1,
    consentimentoData: row.consentimentoData,
    consentimentoIp: row.consentimentoIp,
    classificacao: row.classificacao,
    limiteCredito:
      row.limiteCredito !== null && row.limiteCredito !== undefined
        ? Number(row.limiteCredito)
        : null,
    prazoPadraoDias: row.prazoPadraoDias,
    createdAt: row.createdAt.toISOString(),
    updatedAt: row.updatedAt.toISOString(),
  };
}

function normalizeEmail(email: string | null | undefined) {
  if (email === undefined) return undefined;
  if (email === null) return null;
  const trimmed = email.trim();
  return trimmed.length === 0 ? null : trimmed;
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

type WizardInput = z.infer<typeof CustomerCreateSchema>;

/**
 * Translate the optional wizard payload into a Drizzle insert/update patch.
 * Every field is opt-in: `undefined` means "don't touch", `null` means "clear".
 */
function buildWizardPatch(input: Partial<WizardInput>) {
  const patch: Partial<typeof customers.$inferInsert> = {};
  if (input.papeis !== undefined) patch.papeis = input.papeis;
  if (input.razaoSocial !== undefined) patch.razaoSocial = input.razaoSocial;
  if (input.nomeFantasia !== undefined) patch.nomeFantasia = input.nomeFantasia;
  if (input.inscricaoEstadual !== undefined)
    patch.inscricaoEstadual = input.inscricaoEstadual;
  if (input.indicadorIe !== undefined) patch.indicadorIe = input.indicadorIe;
  if (input.cep !== undefined) patch.cep = input.cep;
  if (input.logradouro !== undefined) patch.logradouro = input.logradouro;
  if (input.numero !== undefined) patch.numero = input.numero;
  if (input.complemento !== undefined) patch.complemento = input.complemento;
  if (input.bairro !== undefined) patch.bairro = input.bairro;
  if (input.cidade !== undefined) patch.cidade = input.cidade;
  if (input.uf !== undefined) patch.uf = input.uf;
  if (input.codigoMunicipioIbge !== undefined)
    patch.codigoMunicipioIbge = input.codigoMunicipioIbge;
  if (input.whatsapp !== undefined) patch.whatsapp = input.whatsapp;
  if (input.emailNfe !== undefined) patch.emailNfe = normalizeEmail(input.emailNfe);
  if (input.aceitaWhatsapp !== undefined)
    patch.aceitaWhatsapp = input.aceitaWhatsapp ? 1 : 0;
  if (input.aceitaEmail !== undefined)
    patch.aceitaEmail = input.aceitaEmail ? 1 : 0;
  if (input.aceitaSms !== undefined) patch.aceitaSms = input.aceitaSms ? 1 : 0;
  if (input.consentimentoData !== undefined)
    patch.consentimentoData = input.consentimentoData;
  if (input.consentimentoIp !== undefined)
    patch.consentimentoIp = input.consentimentoIp;
  if (input.classificacao !== undefined)
    patch.classificacao = input.classificacao;
  if (input.limiteCredito !== undefined) {
    patch.limiteCredito =
      input.limiteCredito === null ? null : input.limiteCredito.toFixed(2);
  }
  if (input.prazoPadraoDias !== undefined)
    patch.prazoPadraoDias = input.prazoPadraoDias;
  return patch;
}

export const customersRouter = router({
  list: companyProcedure.query(async ({ ctx }) => {
    const db = await requireDb();
    const rows = await db
      .select()
      .from(customers)
      .where(eq(customers.companyId, ctx.companyId))
      .orderBy(asc(customers.nome));
    return rows.map(rowToCustomer);
  }),

  getById: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const rows = await db
        .select()
        .from(customers)
        .where(and(eq(customers.id, input.id), eq(customers.companyId, ctx.companyId)))
        .limit(1);
      if (!rows[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Cliente não encontrado" });
      }
      return rowToCustomer(rows[0]);
    }),

  search: companyProcedure
    .input(z.object({ q: z.string() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const term = input.q.trim();
      if (term.length === 0) {
        const rows = await db
          .select()
          .from(customers)
          .where(eq(customers.companyId, ctx.companyId))
          .orderBy(asc(customers.nome))
          .limit(50);
        return rows.map(rowToCustomer);
      }
      const pattern = `%${term}%`;
      const rows = await db
        .select()
        .from(customers)
        .where(
          and(
            eq(customers.companyId, ctx.companyId),
            or(
              like(customers.nome, pattern),
              like(customers.documento, pattern),
              like(customers.telefone, pattern),
            ),
          ),
        )
        .orderBy(asc(customers.nome))
        .limit(50);
      return rows.map(rowToCustomer);
    }),

  create: companyProcedure
    .input(CustomerCreateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const id = randomUUID();
      await db.insert(customers).values({
        id,
        userId: ctx.user.id,
        companyId: ctx.companyId,
        nome: input.nome.trim(),
        tipo: input.tipo ?? "PF",
        documento: input.documento ?? null,
        telefone: input.telefone ?? null,
        email: normalizeEmail(input.email) ?? null,
        endereco: input.endereco ?? null,
        observacoes: input.observacoes ?? null,
        ...buildWizardPatch(input),
      });
      const rows = await db
        .select()
        .from(customers)
        .where(eq(customers.id, id))
        .limit(1);
      return rowToCustomer(rows[0]);
    }),

  update: companyProcedure
    .input(CustomerUpdateSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const { id, ...rest } = input;
      const patch: Partial<typeof customers.$inferInsert> = {};
      if (rest.nome !== undefined) patch.nome = rest.nome.trim();
      if (rest.tipo !== undefined) patch.tipo = rest.tipo;
      if (rest.documento !== undefined) patch.documento = rest.documento;
      if (rest.telefone !== undefined) patch.telefone = rest.telefone;
      if (rest.email !== undefined) patch.email = normalizeEmail(rest.email);
      if (rest.endereco !== undefined) patch.endereco = rest.endereco;
      if (rest.observacoes !== undefined) patch.observacoes = rest.observacoes;
      Object.assign(patch, buildWizardPatch(rest));

      await db
        .update(customers)
        .set(patch)
        .where(and(eq(customers.id, id), eq(customers.companyId, ctx.companyId)));

      const rows = await db
        .select()
        .from(customers)
        .where(and(eq(customers.id, id), eq(customers.companyId, ctx.companyId)))
        .limit(1);
      if (!rows[0]) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Cliente não encontrado" });
      }
      return rowToCustomer(rows[0]);
    }),

  delete: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      try {
        await db
          .delete(customers)
          .where(and(eq(customers.id, input.id), eq(customers.companyId, ctx.companyId)));
      } catch (err) {
        const msg = err instanceof Error ? err.message : String(err);
        // MySQL FK violation: ER_ROW_IS_REFERENCED_2 (1451) / ER_ROW_IS_REFERENCED (1217)
        if (/1451|1217|foreign key|referenced/i.test(msg)) {
          throw new TRPCError({
            code: "CONFLICT",
            message: "Cliente possui pedidos vinculados",
          });
        }
        throw err;
      }
      return { id: input.id };
    }),
});
