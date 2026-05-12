/* eslint-disable no-console */
// Rotacao da chave de cifragem do `whatsapp_baileys_auth_state` (pre-requisito PR #701).
//
// Rodar quando: APP_KEY rotacionada, vazamento, rotacao trimestral.
// Falha-segura: UPDATE transacional por batch; rows com decrypt error contam em rows_failed mas
// NAO interrompem o batch. Idempotente inverso (rodar 2x com mesma chave -> tudo failed).
//
// Uso: MYSQL_AUTH_STATE_{USER,PASS,DB}=... npx tsx scripts/rotate-encryption-key.ts \
//      --old-key=base64:... --new-key=base64:... [--dry-run]
//
// Doc completa: README do PR + memory/handoffs/2026-05-12-*-authstate-scripts.md

import mysql, { type PoolConnection, type PoolOptions } from 'mysql2/promise';
import { decodeEncryptionKey, decryptValue, encryptValue } from './_crypto';

interface CliArgs {
  oldKey: string | null;
  newKey: string | null;
  dryRun: boolean;
}

export interface RotationReport {
  rowsRotated: number;
  rowsFailed: number;
  durationMs: number;
}

interface Row {
  id: number;
  instance_id: string;
  key_id: string;
  value_encrypted: string;
}

function parseArgs(argv: string[]): CliArgs {
  const args: CliArgs = { oldKey: null, newKey: null, dryRun: false };
  for (const a of argv) {
    if (a === '--dry-run') args.dryRun = true;
    else if (a.startsWith('--old-key=')) args.oldKey = a.slice('--old-key='.length);
    else if (a.startsWith('--new-key=')) args.newKey = a.slice('--new-key='.length);
  }
  return args;
}

function requireEnv(name: string): string {
  const v = process.env[name];
  if (!v || v.length === 0) {
    throw new Error(`Env ${name} obrigatoria nao definida`);
  }
  return v;
}

function loadPoolOptions(): PoolOptions {
  return {
    host: process.env.MYSQL_AUTH_STATE_HOST ?? '127.0.0.1',
    port: process.env.MYSQL_AUTH_STATE_PORT ? Number(process.env.MYSQL_AUTH_STATE_PORT) : 3306,
    user: requireEnv('MYSQL_AUTH_STATE_USER'),
    password: requireEnv('MYSQL_AUTH_STATE_PASS'),
    database: requireEnv('MYSQL_AUTH_STATE_DB'),
    waitForConnections: true,
    connectionLimit: 3,
  };
}

export interface RotateOptions {
  pool: { getConnection: () => Promise<PoolConnection> };
  oldKey: Buffer;
  newKey: Buffer;
  dryRun: boolean;
  batchSize?: number;
}

/**
 * Rotaciona todas rows em `whatsapp_baileys_auth_state`. Pagina por id pra nao carregar tudo em RAM.
 *
 * Estrategia:
 *   1. SELECT id, instance_id, key_id, value_encrypted FROM ... WHERE id > lastId ORDER BY id LIMIT batchSize
 *   2. Pra cada row: decrypt(old) -> encrypt(new) -> UPDATE WHERE id = ? (in-conn transacao)
 *   3. Se decrypt falhar: contabiliza rows_failed, segue. Se encrypt falhar: idem.
 *   4. Avanca lastId = max(id) do batch e repete ate batch vazio.
 */
export async function rotate(opts: RotateOptions): Promise<RotationReport> {
  const started = Date.now();
  const batchSize = opts.batchSize ?? 100;
  const report: RotationReport = { rowsRotated: 0, rowsFailed: 0, durationMs: 0 };
  const conn = await opts.pool.getConnection();

  try {
    let lastId = 0;
    // eslint-disable-next-line no-constant-condition
    while (true) {
      const [rows] = await conn.query<import('mysql2').RowDataPacket[]>(
        'SELECT id, instance_id, key_id, value_encrypted FROM whatsapp_baileys_auth_state ' +
          'WHERE id > ? ORDER BY id LIMIT ?',
        [lastId, batchSize],
      );
      if (!Array.isArray(rows) || rows.length === 0) break;

      if (!opts.dryRun) {
        await conn.beginTransaction();
      }

      try {
        for (const r of rows as unknown as Row[]) {
          let plaintext: string;
          try {
            plaintext = decryptValue(r.value_encrypted, opts.oldKey);
          } catch (err) {
            report.rowsFailed += 1;
            console.warn(
              `[rotate] decrypt FAIL id=${r.id} instance=${r.instance_id} key=${r.key_id}: ${(err as Error).message}`,
            );
            continue;
          }

          let reencrypted: string;
          try {
            reencrypted = encryptValue(plaintext, opts.newKey);
          } catch (err) {
            report.rowsFailed += 1;
            console.warn(
              `[rotate] encrypt FAIL id=${r.id} instance=${r.instance_id} key=${r.key_id}: ${(err as Error).message}`,
            );
            continue;
          }

          if (!opts.dryRun) {
            await conn.query(
              'UPDATE whatsapp_baileys_auth_state SET value_encrypted = ? WHERE id = ?',
              [reencrypted, r.id],
            );
          }
          report.rowsRotated += 1;
        }

        if (!opts.dryRun) {
          await conn.commit();
        }
      } catch (err) {
        if (!opts.dryRun) {
          await conn.rollback();
        }
        throw err;
      }

      lastId = Math.max(...(rows as unknown as Row[]).map((r) => r.id));
    }
  } finally {
    conn.release();
  }

  report.durationMs = Date.now() - started;
  return report;
}

export async function main(argv: string[] = process.argv.slice(2)): Promise<number> {
  const args = parseArgs(argv);
  if (!args.oldKey || !args.newKey) {
    console.error('[rotate] --old-key e --new-key sao obrigatorios');
    console.error('uso: npx tsx scripts/rotate-encryption-key.ts --old-key=<base64> --new-key=<base64> [--dry-run]');
    return 2;
  }

  let oldKey: Buffer;
  let newKey: Buffer;
  try {
    oldKey = decodeEncryptionKey(args.oldKey);
    newKey = decodeEncryptionKey(args.newKey);
  } catch (err) {
    console.error(`[rotate] chave invalida: ${(err as Error).message}`);
    return 2;
  }

  if (oldKey.equals(newKey)) {
    console.error('[rotate] --old-key == --new-key — nada a rotacionar');
    return 2;
  }

  console.log(`[rotate-encryption-key] dryRun=${args.dryRun}`);

  const pool = mysql.createPool(loadPoolOptions());
  let report: RotationReport;
  try {
    report = await rotate({ pool, oldKey, newKey, dryRun: args.dryRun });
  } finally {
    await pool.end();
  }

  console.log(
    `\n[rotate] TOTAL — rotated=${report.rowsRotated} failed=${report.rowsFailed} duration_ms=${report.durationMs} dryRun=${args.dryRun}`,
  );
  return report.rowsFailed > 0 ? 1 : 0;
}

if (require.main === module) {
  main().then((code) => process.exit(code)).catch((err) => {
    console.error('[rotate] erro fatal:', err);
    process.exit(10);
  });
}
