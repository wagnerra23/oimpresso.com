/* eslint-disable no-console */
// Migracao filesystem -> MySQL pro auth state Baileys (pre-requisito PR #701).
//
// Rodar 1x antes de mudar AUTH_STATE_BACKEND=mysql no daemon. Idempotente (UPSERT).
// FS continua intacto pos-migracao (fallback dev-only preservado).
//
// Uso: WHATSAPP_AUTH_STATE_ENCRYPTION_KEY=... MYSQL_AUTH_STATE_{USER,PASS,DB}=... \
//      SESSIONS_DIR=/app/sessions npx tsx scripts/migrate-fs-to-mysql.ts [--dry-run] [--instance-id=ch-XXX]
//
// Doc completa: README do PR + memory/handoffs/2026-05-12-*-authstate-scripts.md

import { readdir, readFile, stat } from 'node:fs/promises';
import { join } from 'node:path';
import mysql, { type Pool, type PoolOptions } from 'mysql2/promise';
import { decodeEncryptionKey, encryptValue } from './_crypto.js';

interface CliArgs {
  dryRun: boolean;
  instanceId: string | null;
}

interface InstanceReport {
  instanceId: string;
  keysMigrated: number;
  keysSkipped: number; // ja existia em MySQL
  durationMs: number;
  error?: string;
}

const SAFE_INSTANCE_ID = /^[A-Za-z0-9_-]{1,64}$/;

function parseArgs(argv: string[]): CliArgs {
  const args: CliArgs = { dryRun: false, instanceId: null };
  for (const a of argv) {
    if (a === '--dry-run') args.dryRun = true;
    else if (a.startsWith('--instance-id=')) args.instanceId = a.slice('--instance-id='.length);
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

/** Le diretorio sessions e devolve subpastas com nome `^[A-Za-z0-9_-]{1,64}$` (instances validas). */
export async function listSessionDirs(baseDir: string): Promise<string[]> {
  const entries = await readdir(baseDir, { withFileTypes: true });
  return entries
    .filter((e) => e.isDirectory() && SAFE_INSTANCE_ID.test(e.name))
    .map((e) => e.name)
    .sort();
}

/**
 * Deriva o `key_id` MySQL a partir do nome do arquivo Baileys.
 *
 * Baileys salva 1 JSON por chave: `creds.json`, `pre-key-N.json`, `session-XXX@s.whatsapp.net.json`,
 * `sender-key-XXX.json`, `app-state-sync-key-XXX.json`, `app-state-sync-version-XXX.json`, etc.
 *
 * Convencao do `useMySQLAuthState`: keys sao `creds` ou `<type>-<id>` literal (mesmo nome do file
 * sem `.json`). Entao basta strip `.json`.
 */
export function deriveKeyId(filename: string): string | null {
  if (!filename.endsWith('.json')) return null;
  const stem = filename.slice(0, -'.json'.length);
  if (stem.length === 0) return null;
  return stem;
}

/** Verifica se (instanceId, keyId) ja existe em MySQL — pra contabilizar skipped no idempotent replay. */
async function alreadyMigrated(pool: Pool, instanceId: string, keyId: string): Promise<boolean> {
  const [rows] = await pool.query<import('mysql2').RowDataPacket[]>(
    'SELECT 1 FROM whatsapp_baileys_auth_state WHERE instance_id = ? AND key_id = ? LIMIT 1',
    [instanceId, keyId],
  );
  return Array.isArray(rows) && rows.length > 0;
}

async function upsertKey(
  pool: Pool,
  instanceId: string,
  keyId: string,
  valueEncrypted: string,
): Promise<void> {
  await pool.query(
    'INSERT INTO whatsapp_baileys_auth_state (instance_id, key_id, value_encrypted) VALUES (?, ?, ?) ' +
      'ON DUPLICATE KEY UPDATE value_encrypted = VALUES(value_encrypted)',
    [instanceId, keyId, valueEncrypted],
  );
}

export interface MigrateOneOptions {
  baseDir: string;
  instanceId: string;
  pool: Pool;
  encryptionKey: Buffer;
  dryRun: boolean;
}

/** Migra 1 instance (1 pasta). Re-encripta cada JSON com a chave dada antes do UPSERT. */
export async function migrateInstance(opts: MigrateOneOptions): Promise<InstanceReport> {
  const started = Date.now();
  const report: InstanceReport = {
    instanceId: opts.instanceId,
    keysMigrated: 0,
    keysSkipped: 0,
    durationMs: 0,
  };

  if (!SAFE_INSTANCE_ID.test(opts.instanceId)) {
    report.error = `instance_id invalido: ${opts.instanceId}`;
    report.durationMs = Date.now() - started;
    return report;
  }

  const dir = join(opts.baseDir, opts.instanceId);
  let files: string[];
  try {
    const entries = await readdir(dir, { withFileTypes: true });
    files = entries.filter((e) => e.isFile() && e.name.endsWith('.json')).map((e) => e.name);
  } catch (err) {
    report.error = `nao foi possivel ler ${dir}: ${(err as Error).message}`;
    report.durationMs = Date.now() - started;
    return report;
  }

  for (const f of files) {
    const keyId = deriveKeyId(f);
    if (!keyId) continue;
    const filepath = join(dir, f);
    try {
      // Validacao basica: precisa ser regular file > 0 bytes e parseable JSON
      const st = await stat(filepath);
      if (!st.isFile() || st.size === 0) continue;
      const raw = await readFile(filepath, 'utf8');
      // Valida JSON (lanca em conteudo corrompido — assim NAO escreve lixo no MySQL)
      JSON.parse(raw);

      if (!opts.dryRun && (await alreadyMigrated(opts.pool, opts.instanceId, keyId))) {
        report.keysSkipped += 1;
        continue;
      }

      if (opts.dryRun) {
        report.keysMigrated += 1; // contabiliza como "seria migrada"
        continue;
      }

      // Re-cifra com a APP_KEY de destino (Baileys serializa BufferJSON; aqui guardamos o raw
      // exatamente como esta no FS — o daemon le com BufferJSON.reviver na primeira conexao).
      const valueEncrypted = encryptValue(raw, opts.encryptionKey);
      await upsertKey(opts.pool, opts.instanceId, keyId, valueEncrypted);
      report.keysMigrated += 1;
    } catch (err) {
      // Arquivo corrompido — pula, nao quebra batch
      report.keysSkipped += 1;
      console.warn(
        `[migrate] instance=${opts.instanceId} file=${f} skip (parse/io error): ${(err as Error).message}`,
      );
    }
  }

  report.durationMs = Date.now() - started;
  return report;
}

function printTable(reports: InstanceReport[]): void {
  const head = ['instance_id', 'migrated', 'skipped', 'duration_ms', 'error'];
  const rows = reports.map((r) => [
    r.instanceId,
    String(r.keysMigrated),
    String(r.keysSkipped),
    String(r.durationMs),
    r.error ?? '',
  ]);
  const widths = head.map((h, i) =>
    Math.max(h.length, ...rows.map((r) => (r[i] ?? '').length)),
  );
  const fmt = (cols: string[]) =>
    cols.map((c, i) => c.padEnd(widths[i] ?? c.length)).join('  ');
  console.log(fmt(head));
  console.log(fmt(widths.map((w) => '-'.repeat(w))));
  for (const r of rows) console.log(fmt(r));
}

export async function main(argv: string[] = process.argv.slice(2)): Promise<number> {
  const args = parseArgs(argv);
  const baseDir = process.env.SESSIONS_DIR ?? './var/sessions';
  const encryptionKey = decodeEncryptionKey(requireEnv('WHATSAPP_AUTH_STATE_ENCRYPTION_KEY'));

  console.log(
    `[migrate-fs-to-mysql] baseDir=${baseDir} dryRun=${args.dryRun} instanceFilter=${args.instanceId ?? '(all)'}`,
  );

  let instances: string[];
  try {
    instances = await listSessionDirs(baseDir);
  } catch (err) {
    console.error(`[migrate] falha listando ${baseDir}: ${(err as Error).message}`);
    return 2;
  }

  if (args.instanceId) {
    instances = instances.filter((i) => i === args.instanceId);
    if (instances.length === 0) {
      console.error(`[migrate] instance ${args.instanceId} nao encontrada em ${baseDir}`);
      return 3;
    }
  }

  if (instances.length === 0) {
    console.log('[migrate] nenhuma instance pra migrar — saindo OK');
    return 0;
  }

  const pool = mysql.createPool(loadPoolOptions());
  const reports: InstanceReport[] = [];
  try {
    for (const id of instances) {
      const r = await migrateInstance({
        baseDir,
        instanceId: id,
        pool,
        encryptionKey,
        dryRun: args.dryRun,
      });
      reports.push(r);
    }
  } finally {
    await pool.end();
  }

  printTable(reports);

  const totalMigrated = reports.reduce((s, r) => s + r.keysMigrated, 0);
  const totalSkipped = reports.reduce((s, r) => s + r.keysSkipped, 0);
  const totalErrors = reports.filter((r) => r.error).length;
  console.log(
    `\n[migrate] TOTAL — instances=${reports.length} migrated=${totalMigrated} skipped=${totalSkipped} errors=${totalErrors} dryRun=${args.dryRun}`,
  );
  return totalErrors > 0 ? 1 : 0;
}

// Entrypoint CLI — so executa quando rodado direto (tsx scripts/...), nao quando importado em vitest.
if (require.main === module) {
  main().then((code) => process.exit(code)).catch((err) => {
    console.error('[migrate] erro fatal:', err);
    process.exit(10);
  });
}
