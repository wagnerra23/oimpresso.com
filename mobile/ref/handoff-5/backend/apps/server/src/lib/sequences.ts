// ──────────────────────────────────────────────────────────────
// sequences — numeração de negócio ATÔMICA e sequencial por
// (tenant, série). Corrige v2 §5 (numeração local/colidível) e é
// pré-requisito da numeração fiscal (sequencial, sem lacuna).
//
// Use sempre dentro da MESMA transação da criação do documento,
// para que um rollback não deixe buraco indevido.
// ──────────────────────────────────────────────────────────────
import { sql } from "drizzle-orm";
import type { PgDatabase } from "drizzle-orm/pg-core";

/**
 * Reserva e retorna o próximo número da série para o tenant.
 * UPSERT atômico: dois processos simultâneos recebem números distintos.
 */
export async function nextNumero(
  db: PgDatabase<any, any>,
  tenantId: string,
  serie: string,
): Promise<number> {
  const rows = await db.execute(sql`
    insert into sequences (tenant_id, serie, valor)
    values (${tenantId}, ${serie}, 1)
    on conflict (tenant_id, serie)
    do update set valor = sequences.valor + 1
    returning valor
  `);
  // drizzle/pg: rows é um array-like; primeira linha tem { valor }
  const valor = (rows as any).rows?.[0]?.valor ?? (rows as any)[0]?.valor;
  if (valor == null) throw new Error("Falha ao reservar número de série");
  return Number(valor);
}
