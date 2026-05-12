import { beforeEach, describe, expect, it, vi } from 'vitest';

// ------------------------------------------------------------------------------------------------
// P0 #1 — vitest spec do useMySQLAuthState custom (mysqlAuthState.ts).
//
// Mocka mysql2/promise pra exercitar contract sem precisar MySQL real:
//   - creds inicial → cria via initAuthCreds()
//   - saveCreds → INSERT cifrado
//   - get pre-key existente → decrypt
//   - set pre-key → INSERT ON DUPLICATE KEY UPDATE cifrado
//   - cifrar+decifrar preserva conteúdo (round-trip puro)
// ------------------------------------------------------------------------------------------------

type Row = { instance_id: string; key_id: string; value_encrypted: string };
const memory: Row[] = [];
const queryCalls: { sql: string; params: unknown[] }[] = [];

vi.mock('mysql2/promise', () => {
  const query = vi.fn(async (sql: string, params: unknown[] = []) => {
    queryCalls.push({ sql, params });
    const trimmed = sql.trim().toUpperCase();
    if (trimmed.startsWith('SELECT')) {
      const [instanceId, keyId] = params as [string, string];
      const row = memory.find((r) => r.instance_id === instanceId && r.key_id === keyId);
      return [row ? [{ value_encrypted: row.value_encrypted }] : []];
    }
    if (trimmed.startsWith('INSERT')) {
      const [instanceId, keyId, valueEncrypted] = params as [string, string, string];
      const idx = memory.findIndex((r) => r.instance_id === instanceId && r.key_id === keyId);
      if (idx >= 0) memory[idx].value_encrypted = valueEncrypted;
      else memory.push({ instance_id: instanceId, key_id: keyId, value_encrypted: valueEncrypted });
      return [{ affectedRows: 1 }];
    }
    if (trimmed.startsWith('DELETE')) {
      const [instanceId, keyId] = params as [string, string];
      const idx = memory.findIndex((r) => r.instance_id === instanceId && r.key_id === keyId);
      if (idx >= 0) memory.splice(idx, 1);
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

import {
  decodeEncryptionKey,
  decryptValue,
  encryptValue,
  useMySQLAuthState,
} from './mysqlAuthState';

const ENCRYPTION_KEY = `base64:${Buffer.alloc(32, 7).toString('base64')}`;

function baseOptions(instanceId = 'ch-test01') {
  return {
    instanceId,
    encryptionKey: ENCRYPTION_KEY,
    mysql: {
      host: '127.0.0.1',
      port: 3306,
      user: 'test',
      password: 'test',
      database: 'test',
    },
  };
}

describe('mysqlAuthState — cifragem AES-256-CBC', () => {
  it('decodeEncryptionKey aceita prefixo base64: e crua', () => {
    const raw = Buffer.alloc(32, 1).toString('base64');
    expect(decodeEncryptionKey(raw)).toHaveLength(32);
    expect(decodeEncryptionKey(`base64:${raw}`)).toHaveLength(32);
  });

  it('decodeEncryptionKey rejeita chave fora de 32 bytes', () => {
    const short = Buffer.alloc(16, 1).toString('base64');
    expect(() => decodeEncryptionKey(short)).toThrow(/32 bytes/);
  });

  it('encrypt → decrypt preserva conteúdo (round-trip puro)', () => {
    const key = decodeEncryptionKey(ENCRYPTION_KEY);
    const plaintext = JSON.stringify({
      registrationId: 1234,
      noiseKey: { private: 'AAAA', public: 'BBBB' },
      nested: { deep: ['a', 'b', null, 0] },
    });
    const enc = encryptValue(plaintext, key);
    expect(enc).not.toContain(plaintext);
    const dec = decryptValue(enc, key);
    expect(dec).toBe(plaintext);
  });

  it('encrypt gera IV aleatório — mesmo plaintext nunca produz mesmo ciphertext', () => {
    const key = decodeEncryptionKey(ENCRYPTION_KEY);
    const a = encryptValue('hello', key);
    const b = encryptValue('hello', key);
    expect(a).not.toBe(b);
    expect(decryptValue(a, key)).toBe('hello');
    expect(decryptValue(b, key)).toBe('hello');
  });
});

describe('useMySQLAuthState — contract Baileys', () => {
  beforeEach(() => {
    memory.length = 0;
    queryCalls.length = 0;
  });

  it('creds inicial — cria via initAuthCreds() quando tabela vazia', async () => {
    const auth = await useMySQLAuthState(baseOptions('ch-init'));
    expect(auth.state.creds).toBeDefined();
    expect(typeof auth.state.creds.registrationId).toBe('number');
    expect(auth.state.creds.noiseKey).toBeDefined();
    expect(auth.state.creds.signedIdentityKey).toBeDefined();
    expect(memory).toHaveLength(0);
    await auth.close();
  });

  it('saveCreds → INSERT cifrado em whatsapp_baileys_auth_state', async () => {
    const auth = await useMySQLAuthState(baseOptions('ch-save'));
    await auth.saveCreds();
    expect(memory).toHaveLength(1);
    expect(memory[0].instance_id).toBe('ch-save');
    expect(memory[0].key_id).toBe('creds');
    expect(memory[0].value_encrypted).not.toContain('registrationId');
    const insert = queryCalls.find((c) => c.sql.includes('ON DUPLICATE KEY UPDATE'));
    expect(insert).toBeDefined();
    await auth.close();
  });

  it('get pre-key → retorna {} quando ausente, valor decifrado quando presente', async () => {
    const optionsA = baseOptions('ch-prekey');
    const authA = await useMySQLAuthState(optionsA);
    await authA.state.keys.set({
      'pre-key': {
        '1': { private: Buffer.from([1, 2, 3]), public: Buffer.from([4, 5, 6]) } as never,
      },
    });
    await authA.close();
    expect(memory).toHaveLength(1);
    expect(memory[0].key_id).toBe('pre-key-1');

    const authB = await useMySQLAuthState(optionsA);
    const data = await authB.state.keys.get('pre-key', ['1', '999']);
    expect(data['1']).toBeDefined();
    expect((data['1'] as { private: Buffer }).private).toBeInstanceOf(Buffer);
    expect(data['999']).toBeUndefined();
    await authB.close();
  });

  it('set com value=null → DELETE da linha (Signal store invalida key)', async () => {
    const auth = await useMySQLAuthState(baseOptions('ch-del'));
    await auth.state.keys.set({
      'pre-key': { '1': { private: Buffer.alloc(1), public: Buffer.alloc(1) } as never },
    });
    expect(memory).toHaveLength(1);

    await auth.state.keys.set({
      'pre-key': { '1': null as never },
    });
    expect(memory).toHaveLength(0);
    const del = queryCalls.find((c) => c.sql.includes('DELETE'));
    expect(del).toBeDefined();
    await auth.close();
  });

  it('isolamento entre instances — instance_id diferente não vê creds do outro', async () => {
    const authA = await useMySQLAuthState(baseOptions('ch-tenant-a'));
    await authA.saveCreds();
    await authA.close();
    expect(memory).toHaveLength(1);

    const authB = await useMySQLAuthState(baseOptions('ch-tenant-b'));
    expect(authB.state.creds.registrationId).not.toBe(authA.state.creds.registrationId);
    await authB.close();
  });
});
