// ──────────────────────────────────────────────────────────────
// audit — trilha de auditoria append-only para ações sensíveis
// (preço, estoque, financeiro, fiscal). Corrige v1 §04 / v3 Cadeia 1.
// Sem isto, corrupção de dado é irrastreável.
// ──────────────────────────────────────────────────────────────
import type { PgDatabase } from "drizzle-orm/pg-core";
import { auditLog } from "../db/schema";

export type AuditAction =
  | "create" | "update" | "delete"
  | "advance_etapa" | "baixa" | "emit_fiscal" | "cancel_fiscal"
  | "price_change" | "stock_move" | "merge";

export interface AuditEntry {
  tenantId: string;
  actorId: string | null;
  entity: string;        // ex.: "pedido", "titulo", "produto"
  entityId: string;
  action: AuditAction;
  before?: unknown;
  after?: unknown;
}

/** Grava um evento de auditoria. Chame na MESMA transação da mutação. */
export async function audit(db: PgDatabase<any, any>, e: AuditEntry): Promise<void> {
  await db.insert(auditLog).values({
    tenantId: e.tenantId,
    actorId: e.actorId ?? undefined,
    entity: e.entity,
    entityId: e.entityId,
    action: e.action,
    before: (e.before ?? null) as any,
    after: (e.after ?? null) as any,
  });
}
