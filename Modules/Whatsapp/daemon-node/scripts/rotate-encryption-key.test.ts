import { beforeEach, describe, expect, it, vi } from 'vitest';

// ------------------------------------------------------------------------------------------------
// Vitest spec do rotate-encryption-key.ts.
//
// Mocka mysql2/promise (PoolConnection + transacao) e exercita:
//   1. 3 rows cifradas com OLD key -> todas re-encrypted com NEW key (decrypt ok)
//   2. Row com decrypt FAIL (chave errada simulada) -> rows_failed += 1, NAO interrompe batch
//   3. --dry-run nao UPDATE
//   4. Idempotencia: segunda execucao com mesma OLD key (rows ja em NEW) -> tudo failed
// ------------------------------------------------------------------------------------------------

import { decodeEncryptionKey, encryptValue } from './_crypto';

type Row = { id: number; instance_id: string; key_id: string; value_encrypted: string };

const memory: Row[] = [];
const updateCalls: { id: number; value_encrypted: string }[] = [];
const beginCalls: number[] = [];
const commitCalls: number[] = [];
const rollbackCalls: number[] = [];

vi.mock('mysql2/promise', () => {
  const makeConn = () => ({
    query: vi.fn(async (sql: string, params: unknown[] = []) => {
      const trimmed = sql.trim().toUpperCase();
      if (trimmed.startsWith('SELECT')) {
        const [lastId, batchSize] = params as [number, number];
        const filtered = memory
          .filter((r) => r.id > lastId)
          .sort((a, b) => a.id - b.id)
          .slice(0, batchSize);
        return [filtered as unknown as import('mysql2').RowDataPacket[]];
      }
      if (trimmed.startsWith('UPDATE')) {
        const [valueEncrypted, id] = params as [string, number];
        updateCalls.push({ id, value_encrypted: valueEncrypted });
        const idx = memory.findIndex((r) => r.id === id);
        if (idx >= 0) memory[idx]!.value_encrypted = valueEncrypted;
        return [{ affectedRows: 1 }];
      }
      return [[]];
    }),
    beginTransaction: vi.fn(async () => beginCalls.push(Date.now())),
    commit: vi.fn(async () => commitCalls.push(Date.now())),
    rollback: vi.fn(async () => rollbackCalls.push(Date.now())),
    release: vi.fn(),
  });

  const pool = {
    getConnection: vi.fn(async () => makeConn()),
    end: vi.fn(async () => undefined),
  };
  return {
    default: { createPool: vi.fn(() => pool) },
    createPool: vi.fn(() => pool),
  };
});

import mysql from 'mysql2/promise';
import { rotate } from './rotate-encryption-key';

const OLD_KEY = decodeEncryptionKey(`base64:${Buffer.alloc(32, 1).toString('base64')}`);
const NEW_KEY = decodeEncryptionKey(`base64:${Buffer.alloc(32, 2).toString('base64')}`);

function seed(rows: Array<{ id: number; instance_id: string; key_id: string; plaintext: string }>): void {
  for (const r of rows) {
    memory.push({
      id: r.id,
      instance_id: r.instance_id,
      key_id: r.key_id,
      value_encrypted: encryptValue(r.plaintext, OLD_KEY),
    });
  }
}

function resetState(): void {
  memory.length = 0;
  updateCalls.length = 0;
  beginCalls.length = 0;
  commitCalls.length = 0;
  rollbackCalls.length = 0;
}

describe('rotate-encryption-key — rotate()', () => {
  beforeEach(resetState);

  it('3 rows cifradas com OLD -> todas re-encrypted com NEW (decrypt OK pos rotacao)', async () => {
    seed([
      { id: 1, instance_id: 'ch-a', key_id: 'creds', plaintext: '{"a":1}' },
      { id: 2, instance_id: 'ch-a', key_id: 'pre-key-1', plaintext: '{"k":"v"}' },
      { id: 3, instance_id: 'ch-b', key_id: 'creds', plaintext: '{"b":2}' },
    ]);

    const pool = mysql.createPool({} as never);
    const report = await rotate({
      pool: pool as never,
      oldKey: OLD_KEY,
      newKey: NEW_KEY,
      dryRun: false,
    });

    expect(report.rowsRotated).toBe(3);
    expect(report.rowsFailed).toBe(0);
    expect(updateCalls).toHaveLength(3);
    expect(beginCalls).toHaveLength(1);
    expect(commitCalls).toHaveLength(1);
    expect(rollbackCalls).toHaveLength(0);

    // valida que rows ficam decifravel com NEW_KEY agora
    const { decryptValue } = await import('./_crypto');
    for (const row of memory) {
      expect(() => decryptValue(row.value_encrypted, NEW_KEY)).not.toThrow();
      expect(() => decryptValue(row.value_encrypted, OLD_KEY)).toThrow();
    }
  });

  it('row com decrypt FAIL -> rows_failed += 1, batch continua', async () => {
    seed([
      { id: 1, instance_id: 'ch-a', key_id: 'creds', plaintext: '{"ok":true}' },
      { id: 3, instance_id: 'ch-b', key_id: 'creds', plaintext: '{"ok":true}' },
    ]);
    // injeta row corrompida (cifrada com chave RANDOM — nao OLD_KEY)
    const WRONG_KEY = decodeEncryptionKey(`base64:${Buffer.alloc(32, 99).toString('base64')}`);
    memory.push({
      id: 2,
      instance_id: 'ch-corrupt',
      key_id: 'creds',
      value_encrypted: encryptValue('{"bad":1}', WRONG_KEY),
    });

    const pool = mysql.createPool({} as never);
    const report = await rotate({
      pool: pool as never,
      oldKey: OLD_KEY,
      newKey: NEW_KEY,
      dryRun: false,
    });

    expect(report.rowsRotated).toBe(2);
    expect(report.rowsFailed).toBe(1);
    expect(updateCalls).toHaveLength(2); // id 2 nao foi UPDATE
    expect(updateCalls.map((u) => u.id).sort()).toEqual([1, 3]);
    expect(rollbackCalls).toHaveLength(0); // failed row nao causa rollback (commit normal)
    expect(commitCalls).toHaveLength(1);
  });

  it('--dry-run nao UPDATE, mas conta rotated igual', async () => {
    seed([
      { id: 1, instance_id: 'ch-a', key_id: 'creds', plaintext: '{"ok":true}' },
      { id: 2, instance_id: 'ch-a', key_id: 'pre-key-1', plaintext: '{"k":"v"}' },
    ]);

    const snapshot = memory.map((r) => r.value_encrypted);

    const pool = mysql.createPool({} as never);
    const report = await rotate({
      pool: pool as never,
      oldKey: OLD_KEY,
      newKey: NEW_KEY,
      dryRun: true,
    });

    expect(report.rowsRotated).toBe(2);
    expect(report.rowsFailed).toBe(0);
    expect(updateCalls).toHaveLength(0);
    expect(beginCalls).toHaveLength(0); // nem comeca transacao
    expect(commitCalls).toHaveLength(0);
    // memory inalterada
    expect(memory.map((r) => r.value_encrypted)).toEqual(snapshot);
  });

  it('idempotencia inversa: rodar 2x com mesma OLD/NEW -> 2a tentativa tudo failed', async () => {
    seed([
      { id: 1, instance_id: 'ch-a', key_id: 'creds', plaintext: '{"a":1}' },
      { id: 2, instance_id: 'ch-a', key_id: 'pre-key-1', plaintext: '{"k":"v"}' },
    ]);
    const pool = mysql.createPool({} as never);

    const first = await rotate({ pool: pool as never, oldKey: OLD_KEY, newKey: NEW_KEY, dryRun: false });
    expect(first.rowsRotated).toBe(2);
    expect(first.rowsFailed).toBe(0);

    // segunda tentativa com mesma OLD_KEY — rows ja estao em NEW_KEY, decrypt deve falhar tudo
    updateCalls.length = 0;
    const second = await rotate({ pool: pool as never, oldKey: OLD_KEY, newKey: NEW_KEY, dryRun: false });
    expect(second.rowsRotated).toBe(0);
    expect(second.rowsFailed).toBe(2);
    expect(updateCalls).toHaveLength(0);
  });

  it('batch paginado: 250 rows com batchSize=100 -> 3 batches, 3 commits', async () => {
    for (let i = 1; i <= 250; i++) {
      memory.push({
        id: i,
        instance_id: 'ch-bulk',
        key_id: `pre-key-${i}`,
        value_encrypted: encryptValue(`{"i":${i}}`, OLD_KEY),
      });
    }

    const pool = mysql.createPool({} as never);
    const report = await rotate({
      pool: pool as never,
      oldKey: OLD_KEY,
      newKey: NEW_KEY,
      dryRun: false,
      batchSize: 100,
    });

    expect(report.rowsRotated).toBe(250);
    expect(report.rowsFailed).toBe(0);
    expect(beginCalls).toHaveLength(3); // 100 + 100 + 50
    expect(commitCalls).toHaveLength(3);
  });
});
