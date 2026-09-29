// ──────────────────────────────────────────────────────────────
// Sessão — resolve um token de sessão → AuthUser (tenant + papel).
// Skeleton: troque o store por Redis/DB de sessões reais.
// ──────────────────────────────────────────────────────────────
import { eq } from "drizzle-orm";
import { db } from "../db/client";
import { users } from "../db/schema";
import type { AuthUser } from "../trpc/context";
import type { Papel } from "../domain/policy";

// Store de sessão em memória (DEV). Produção: Redis ou tabela sessions.
const sessions = new Map<string, { userId: string }>();

export function createSession(token: string, userId: string): void {
  sessions.set(token, { userId });
}

/** Token → usuário autenticado, ou null. */
export async function resolveSession(token: string): Promise<AuthUser | null> {
  const s = sessions.get(token);
  if (!s) return null;
  const [u] = await db.select().from(users).where(eq(users.id, s.userId));
  if (!u) return null;
  return { id: u.id, tenantId: u.tenantId, papel: u.papel as Papel };
}
