import type { CreateExpressContextOptions } from "@trpc/server/adapters/express";
import { and, eq } from "drizzle-orm";
import type { User } from "../../drizzle/schema";
import { companies, companyMembers } from "../../drizzle/schema";
import { getDb } from "../db";
import { sdk } from "./sdk";

/**
 * F3-08: cookie that carries the active companyId for the session.
 * Set by `companies.switch` mutation; read on every request to scope queries.
 */
export const CURRENT_COMPANY_COOKIE = "current_company_id";

export type TrpcContext = {
  req: CreateExpressContextOptions["req"];
  res: CreateExpressContextOptions["res"];
  user: User | null;
  /**
   * Active company for this request. Resolved from the `current_company_id`
   * cookie when present and the user is a member of that company. Falls back
   * to the FIRST company the user belongs to (membership table). Null when
   * the user has no company (rare — backfill should have created one).
   */
  companyId: string | null;
};

async function resolveCompanyId(
  user: User | null,
  cookieValue: string | undefined,
): Promise<string | null> {
  if (!user) return null;
  const db = await getDb();
  if (!db) return null;

  // 1. Try the cookie first — but VALIDATE membership before trusting it.
  if (cookieValue) {
    const [m] = await db
      .select({ id: companyMembers.companyId })
      .from(companyMembers)
      .where(
        and(
          eq(companyMembers.userId, user.id),
          eq(companyMembers.companyId, cookieValue),
        ),
      )
      .limit(1);
    if (m) return m.id;
  }

  // 2. Fallback to the first owned company.
  const [owned] = await db
    .select({ id: companies.id })
    .from(companies)
    .where(eq(companies.ownerUserId, user.id))
    .orderBy(companies.createdAt)
    .limit(1);
  if (owned) return owned.id;

  // 3. Last resort: any company they're a member of.
  const [member] = await db
    .select({ id: companyMembers.companyId })
    .from(companyMembers)
    .where(eq(companyMembers.userId, user.id))
    .limit(1);
  return member?.id ?? null;
}

export async function createContext(opts: CreateExpressContextOptions): Promise<TrpcContext> {
  let user: User | null = null;

  try {
    user = await sdk.authenticateRequest(opts.req);
  } catch {
    user = null;
  }

  const cookieValue = (opts.req.cookies as Record<string, string> | undefined)?.[
    CURRENT_COMPANY_COOKIE
  ];

  const companyId = await resolveCompanyId(user, cookieValue);

  return {
    req: opts.req,
    res: opts.res,
    user,
    companyId,
  };
}
