// ──────────────────────────────────────────────────────────────
// Contexto tRPC — resolve usuário/tenant por requisição e oferece
// withTenant() (fixa app.tenant_id na transação → alimenta o RLS).
// ──────────────────────────────────────────────────────────────
import { sql } from "drizzle-orm";
import type { PgDatabase } from "drizzle-orm/pg-core";
import { db } from "../db/client";
import { resolveSession } from "../auth/session";
import type { Papel } from "../domain/policy";

export interface AuthUser {
  id: string;
  tenantId: string;
  papel: Papel;
}

export interface Context {
  db: PgDatabase<any, any>;
  user: AuthUser | null;
}

/** Cria o contexto a partir do header de autorização da requisição. */
export async function createContext(opts: { req: { headers: Record<string, string | undefined> } }): Promise<Context> {
  const token = (opts.req.headers["authorization"] ?? "").replace(/^Bearer\s+/i, "");
  const user = token ? await resolveSession(token) : null;
  return { db, user };
}

/**
 * Executa `fn` numa transação com o tenant corrente fixado na sessão,
 * de modo que o RLS isole automaticamente todas as queries.
 */
export async function withTenant<T>(
  ctx: { db: PgDatabase<any, any>; user: AuthUser },
  fn: (tx: PgDatabase<any, any>) => Promise<T>,
): Promise<T> {
  return ctx.db.transaction(async (tx) => {
    await tx.execute(sql`select set_config('app.tenant_id', ${ctx.user.tenantId}, true)`);
    return fn(tx);
  });
}
