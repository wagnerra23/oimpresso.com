import { TRPCError } from "@trpc/server";
import { randomUUID } from "node:crypto";
import { and, eq } from "drizzle-orm";
import { z } from "zod";

import {
  companies,
  companyMembers,
  companySettings,
} from "../../drizzle/schema";
import { getDb } from "../db";
import { getSessionCookieOptions } from "../_core/cookies";
import { CURRENT_COMPANY_COOKIE } from "../_core/context";
import { protectedProcedure, router } from "../_core/trpc";

/**
 * F3-08 — Multiempresa router.
 *
 * Companies are tenants. A user may own/manage many. Membership is tracked in
 * `companyMembers`. The active company per session is carried by the
 * `current_company_id` cookie (HttpOnly, set by `switch`).
 *
 * NOTE: all procedures here are `protectedProcedure` (not `companyProcedure`)
 * because the user must be able to call them BEFORE picking a company (e.g.
 * the first call after login is `current`).
 */

const VerticalEnum = z.enum(["cv", "mecanica", "outro"]);
const RoleEnum = z.enum(["owner", "admin", "member"]);

const ONE_YEAR_MS = 365 * 24 * 60 * 60 * 1000;

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

function rowToCompany(row: typeof companies.$inferSelect) {
  return {
    id: row.id,
    ownerUserId: row.ownerUserId,
    nome: row.nome,
    vertical: row.vertical,
    ativa: row.ativa === 1,
    createdAt: row.createdAt.toISOString(),
    updatedAt: row.updatedAt.toISOString(),
  };
}

async function assertMember(
  db: Awaited<ReturnType<typeof requireDb>>,
  userId: number,
  companyId: string,
) {
  const [m] = await db
    .select()
    .from(companyMembers)
    .where(
      and(
        eq(companyMembers.userId, userId),
        eq(companyMembers.companyId, companyId),
      ),
    )
    .limit(1);
  if (!m) {
    throw new TRPCError({
      code: "FORBIDDEN",
      message: "Você não é membro dessa empresa.",
    });
  }
  return m;
}

async function assertOwner(
  db: Awaited<ReturnType<typeof requireDb>>,
  userId: number,
  companyId: string,
) {
  const [c] = await db
    .select()
    .from(companies)
    .where(eq(companies.id, companyId))
    .limit(1);
  if (!c) {
    throw new TRPCError({ code: "NOT_FOUND", message: "Empresa não encontrada." });
  }
  if (c.ownerUserId !== userId) {
    throw new TRPCError({
      code: "FORBIDDEN",
      message: "Apenas o dono pode realizar essa operação.",
    });
  }
  return c;
}

export const companiesRouter = router({
  /** Companies the authenticated user is a member of. */
  list: protectedProcedure.query(async ({ ctx }) => {
    const db = await requireDb();
    const rows = await db
      .select({
        company: companies,
        role: companyMembers.role,
      })
      .from(companyMembers)
      .innerJoin(companies, eq(companies.id, companyMembers.companyId))
      .where(eq(companyMembers.userId, ctx.user.id));
    return rows.map((r) => ({ ...rowToCompany(r.company), role: r.role }));
  }),

  /** Active company for this session (from cookie or fallback). Nullable. */
  current: protectedProcedure.query(async ({ ctx }) => {
    if (!ctx.companyId) return null;
    const db = await requireDb();
    const [row] = await db
      .select()
      .from(companies)
      .where(eq(companies.id, ctx.companyId))
      .limit(1);
    return row ? rowToCompany(row) : null;
  }),

  /** Switch active company. Validates membership, sets the cookie. */
  switch: protectedProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await assertMember(db, ctx.user.id, input.id);
      const [row] = await db
        .select()
        .from(companies)
        .where(eq(companies.id, input.id))
        .limit(1);
      if (!row) {
        throw new TRPCError({ code: "NOT_FOUND", message: "Empresa não encontrada." });
      }
      const cookieOptions = getSessionCookieOptions(ctx.req);
      ctx.res.cookie(CURRENT_COMPANY_COOKIE, input.id, {
        ...cookieOptions,
        maxAge: ONE_YEAR_MS,
      });
      return rowToCompany(row);
    }),

  /** Create a new company owned by the caller. Auto-seeds membership. */
  create: protectedProcedure
    .input(
      z.object({
        nome: z.string().min(1).max(255),
        vertical: VerticalEnum.default("outro"),
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const id = randomUUID();
      await db.insert(companies).values({
        id,
        ownerUserId: ctx.user.id,
        nome: input.nome,
        vertical: input.vertical,
        ativa: 1,
      });
      await db.insert(companyMembers).values({
        id: randomUUID(),
        companyId: id,
        userId: ctx.user.id,
        role: "owner",
      });
      // Seed a blank companySettings row so the fiscal module never returns null.
      await db.insert(companySettings).values({
        id: randomUUID(),
        userId: ctx.user.id,
        companyId: id,
      });
      const [row] = await db
        .select()
        .from(companies)
        .where(eq(companies.id, id))
        .limit(1);
      return row ? rowToCompany(row) : { id, ownerUserId: ctx.user.id, nome: input.nome, vertical: input.vertical, ativa: true, createdAt: new Date().toISOString(), updatedAt: new Date().toISOString() };
    }),

  /** Update name/vertical. Owner only. */
  update: protectedProcedure
    .input(
      z.object({
        id: z.string().uuid(),
        nome: z.string().min(1).max(255).optional(),
        vertical: VerticalEnum.optional(),
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await assertOwner(db, ctx.user.id, input.id);
      const patch: Record<string, unknown> = {};
      if (input.nome !== undefined) patch.nome = input.nome;
      if (input.vertical !== undefined) patch.vertical = input.vertical;
      if (Object.keys(patch).length > 0) {
        await db.update(companies).set(patch).where(eq(companies.id, input.id));
      }
      const [row] = await db
        .select()
        .from(companies)
        .where(eq(companies.id, input.id))
        .limit(1);
      if (!row) throw new TRPCError({ code: "NOT_FOUND" });
      return rowToCompany(row);
    }),

  /** Soft delete: marks the company inactive (data preserved). Owner only. */
  delete: protectedProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await assertOwner(db, ctx.user.id, input.id);
      await db
        .update(companies)
        .set({ ativa: 0 })
        .where(eq(companies.id, input.id));
      return { id: input.id, ativa: false } as const;
    }),

  /** MVP invite — only stub: requires the user to exist by email. */
  inviteMember: protectedProcedure
    .input(
      z.object({
        companyId: z.string().uuid(),
        userId: z.number().int().positive(),
        role: RoleEnum.default("member"),
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await assertOwner(db, ctx.user.id, input.companyId);
      // Idempotent: reject duplicates.
      const [existing] = await db
        .select()
        .from(companyMembers)
        .where(
          and(
            eq(companyMembers.companyId, input.companyId),
            eq(companyMembers.userId, input.userId),
          ),
        )
        .limit(1);
      if (existing) {
        throw new TRPCError({
          code: "CONFLICT",
          message: "Usuário já é membro dessa empresa.",
        });
      }
      const id = randomUUID();
      await db.insert(companyMembers).values({
        id,
        companyId: input.companyId,
        userId: input.userId,
        role: input.role,
      });
      return { id, companyId: input.companyId, userId: input.userId, role: input.role };
    }),
});
