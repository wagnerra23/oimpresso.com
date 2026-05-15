import { beforeEach, describe, expect, it, vi } from 'vitest';

// ------------------------------------------------------------------------------------------------
// Vitest spec do migrate-fs-to-mysql.ts.
//
// Mocka mysql2/promise (sem MySQL real) e fs/promises (sem disco real) — exercita:
//   1. FS com 1 session (creds + 2 pre-keys) -> 3 rows INSERTed
//   2. --dry-run nao chama INSERT
//   3. Keys ja existentes nao duplicam (UPSERT idempotente)
//   4. JSON corrompido pula sem quebrar batch (skipped contabilizado)
// ------------------------------------------------------------------------------------------------

type Row = { instance_id: string; key_id: string; value_encrypted: string };

const memory: Row[] = [];
const queryCalls: { sql: string; params: unknown[] }[] = [];

vi.mock('mysql2/promise', () => {
  const query = vi.fn(async (sql: string, params: unknown[] = []) => {
    queryCalls.push({ sql, params });
    const trimmed = sql.trim().toUpperCase();
    if (trimmed.startsWith('SELECT 1 FROM')) {
      const [instanceId, keyId] = params as [string, string];
      const row = memory.find((r) => r.instance_id === instanceId && r.key_id === keyId);
      return [row ? [{ 1: 1 }] : []];
    }
    if (trimmed.startsWith('INSERT')) {
      const [instanceId, keyId, valueEncrypted] = params as [string, string, string];
      const idx = memory.findIndex((r) => r.instance_id === instanceId && r.key_id === keyId);
      if (idx >= 0) memory[idx]!.value_encrypted = valueEncrypted;
      else memory.push({ instance_id: instanceId, key_id: keyId, value_encrypted: valueEncrypted });
      return [{ affectedRows: 1 }];
    }
    return [[]];
  });
  const pool = { query, end: vi.fn(async () => undefined) };
  return {
    default: { createPool: vi.fn(() => pool) },
    createPool: vi.fn(() => pool),
  };
});

// FS mock: estrutura virtual { baseDir: { instance: { file: content } } } — Records aninhados
// (Maps perdem inferencia de tipo aninhado via JSON.stringify-like uso, entao Record e mais claro)
interface FsTree {
  [baseDir: string]: { [instance: string]: { [file: string]: string } };
}
const fsTree: FsTree = {};

/** Normaliza path Windows/POSIX -> '/' antes de comparar (mock cross-platform). */
function norm(p: string): string {
  return p.replace(/\\/g, '/');
}

function findBaseDir(p: string): { baseDir: string; rest: string } | null {
  const np = norm(p);
  for (const baseDir of Object.keys(fsTree)) {
    if (np === baseDir) return { baseDir, rest: '' };
    if (np.startsWith(`${baseDir}/`)) return { baseDir, rest: np.slice(baseDir.length + 1) };
  }
  return null;
}

vi.mock('node:fs/promises', () => {
  return {
    readdir: vi.fn(async (path: string, opts?: { withFileTypes?: boolean }) => {
      const m = findBaseDir(path);
      if (!m) throw Object.assign(new Error('ENOENT'), { code: 'ENOENT' });
      if (m.rest === '') {
        // baseDir level — lista instances (sao dirs)
        const names = Object.keys(fsTree[m.baseDir] ?? {});
        if (opts?.withFileTypes) {
          return names.map((name) => ({ name, isDirectory: () => true, isFile: () => false }));
        }
        return names;
      }
      // instance level — lista files
      const inst = m.rest;
      const filesObj = fsTree[m.baseDir]?.[inst];
      if (!filesObj) throw Object.assign(new Error('ENOENT'), { code: 'ENOENT' });
      const names = Object.keys(filesObj);
      if (opts?.withFileTypes) {
        return names.map((name) => ({ name, isDirectory: () => false, isFile: () => true }));
      }
      return names;
    }),
    readFile: vi.fn(async (path: string) => {
      const m = findBaseDir(path);
      if (!m || m.rest === '') throw Object.assign(new Error('ENOENT'), { code: 'ENOENT' });
      const parts = m.rest.split('/');
      if (parts.length !== 2) throw Object.assign(new Error('ENOENT'), { code: 'ENOENT' });
      const [inst, file] = parts as [string, string];
      const content = fsTree[m.baseDir]?.[inst]?.[file];
      if (content === undefined) throw Object.assign(new Error('ENOENT'), { code: 'ENOENT' });
      return content;
    }),
    stat: vi.fn(async (path: string) => {
      const m = findBaseDir(path);
      if (!m || m.rest === '') throw Object.assign(new Error('ENOENT'), { code: 'ENOENT' });
      const parts = m.rest.split('/');
      if (parts.length !== 2) throw Object.assign(new Error('ENOENT'), { code: 'ENOENT' });
      const [inst, file] = parts as [string, string];
      const content = fsTree[m.baseDir]?.[inst]?.[file];
      if (content === undefined) throw Object.assign(new Error('ENOENT'), { code: 'ENOENT' });
      return { isFile: () => true, size: content.length };
    }),
  };
});

import mysql from 'mysql2/promise';
import { deriveKeyId, listSessionDirs, migrateInstance } from './migrate-fs-to-mysql.js';
import { decodeEncryptionKey } from './_crypto.js';

const ENCRYPTION_KEY = decodeEncryptionKey(`base64:${Buffer.alloc(32, 7).toString('base64')}`);

function seedFs(baseDir: string, instance: string, files: Record<string, unknown>): void {
  if (!fsTree[baseDir]) fsTree[baseDir] = {};
  if (!fsTree[baseDir]![instance]) fsTree[baseDir]![instance] = {};
  for (const [name, payload] of Object.entries(files)) {
    fsTree[baseDir]![instance]![name] = typeof payload === 'string' ? payload : JSON.stringify(payload);
  }
}

function clearFs(): void {
  for (const k of Object.keys(fsTree)) delete fsTree[k];
}

describe('migrate-fs-to-mysql — helpers', () => {
  it('deriveKeyId strip .json e rejeita non-json', () => {
    expect(deriveKeyId('creds.json')).toBe('creds');
    expect(deriveKeyId('pre-key-12.json')).toBe('pre-key-12');
    expect(deriveKeyId('app-state-sync-key-AAAA.json')).toBe('app-state-sync-key-AAAA');
    expect(deriveKeyId('README.md')).toBeNull();
    expect(deriveKeyId('.json')).toBeNull();
  });

  it('listSessionDirs filtra apenas pastas com nome SAFE_INSTANCE_ID', async () => {
    clearFs();
    seedFs('/sessions', 'ch-valid01', { 'creds.json': { registrationId: 1 } });
    seedFs('/sessions', 'ch-valid02', { 'creds.json': { registrationId: 2 } });
    const dirs = await listSessionDirs('/sessions');
    expect(dirs).toEqual(['ch-valid01', 'ch-valid02']);
  });
});

describe('migrate-fs-to-mysql — migrateInstance', () => {
  beforeEach(() => {
    memory.length = 0;
    queryCalls.length = 0;
    clearFs();
  });

  it('1 session (creds + 2 pre-keys) -> 3 rows INSERTed', async () => {
    seedFs('/sessions', 'ch-suorte', {
      'creds.json': { registrationId: 100, noiseKey: { private: 'AAA', public: 'BBB' } },
      'pre-key-1.json': { private: 'CCC', public: 'DDD' },
      'pre-key-2.json': { private: 'EEE', public: 'FFF' },
    });

    const pool = mysql.createPool({} as never);
    const report = await migrateInstance({
      baseDir: '/sessions',
      instanceId: 'ch-suorte',
      pool: pool as never,
      encryptionKey: ENCRYPTION_KEY,
      dryRun: false,
    });

    expect(report.keysMigrated).toBe(3);
    expect(report.keysSkipped).toBe(0);
    expect(report.error).toBeUndefined();
    expect(memory).toHaveLength(3);
    const keyIds = memory.map((r) => r.key_id).sort();
    expect(keyIds).toEqual(['creds', 'pre-key-1', 'pre-key-2']);
    // value_encrypted nao contem plaintext
    for (const row of memory) {
      expect(row.value_encrypted).not.toContain('registrationId');
      expect(row.value_encrypted).not.toContain('private');
    }
    // INSERT ... ON DUPLICATE KEY UPDATE foi chamado
    const insert = queryCalls.find((c) => c.sql.includes('ON DUPLICATE KEY UPDATE'));
    expect(insert).toBeDefined();
  });

  it('--dry-run NAO chama INSERT — apenas conta', async () => {
    seedFs('/sessions', 'ch-dry', {
      'creds.json': { registrationId: 99 },
      'pre-key-1.json': { private: 'X', public: 'Y' },
    });

    const pool = mysql.createPool({} as never);
    const report = await migrateInstance({
      baseDir: '/sessions',
      instanceId: 'ch-dry',
      pool: pool as never,
      encryptionKey: ENCRYPTION_KEY,
      dryRun: true,
    });

    expect(report.keysMigrated).toBe(2); // contabiliza como "seria migrada"
    expect(memory).toHaveLength(0);
    const insert = queryCalls.find((c) => c.sql.includes('INSERT'));
    expect(insert).toBeUndefined();
  });

  it('keys ja existentes nao duplicam — contabiliza em skipped', async () => {
    seedFs('/sessions', 'ch-exists', {
      'creds.json': { registrationId: 1 },
      'pre-key-1.json': { private: 'A', public: 'B' },
    });
    // pre-popula MySQL com creds existente
    memory.push({
      instance_id: 'ch-exists',
      key_id: 'creds',
      value_encrypted: 'pre-existing-blob',
    });

    const pool = mysql.createPool({} as never);
    const report = await migrateInstance({
      baseDir: '/sessions',
      instanceId: 'ch-exists',
      pool: pool as never,
      encryptionKey: ENCRYPTION_KEY,
      dryRun: false,
    });

    expect(report.keysSkipped).toBe(1); // creds skipped
    expect(report.keysMigrated).toBe(1); // pre-key-1 migrated
    // creds NAO foi sobrescrito (skip antes do upsert)
    const credsRow = memory.find((r) => r.key_id === 'creds');
    expect(credsRow?.value_encrypted).toBe('pre-existing-blob');
  });

  it('arquivo corrompido (JSON invalido) pula sem quebrar batch', async () => {
    seedFs('/sessions', 'ch-corrupt', {
      'creds.json': { registrationId: 5 },
      'pre-key-1.json': '{not valid json',
      'pre-key-2.json': { private: 'OK', public: 'OK' },
    });

    const pool = mysql.createPool({} as never);
    const report = await migrateInstance({
      baseDir: '/sessions',
      instanceId: 'ch-corrupt',
      pool: pool as never,
      encryptionKey: ENCRYPTION_KEY,
      dryRun: false,
    });

    expect(report.keysMigrated).toBe(2); // creds + pre-key-2
    expect(report.keysSkipped).toBe(1); // pre-key-1 corrupt
    expect(memory.find((r) => r.key_id === 'pre-key-1')).toBeUndefined();
  });

  it('instance_id invalido -> error sem tocar FS/MySQL', async () => {
    const pool = mysql.createPool({} as never);
    const report = await migrateInstance({
      baseDir: '/sessions',
      instanceId: '../etc/passwd',
      pool: pool as never,
      encryptionKey: ENCRYPTION_KEY,
      dryRun: false,
    });

    expect(report.error).toMatch(/invalido/);
    expect(report.keysMigrated).toBe(0);
    expect(memory).toHaveLength(0);
  });
});
