import { createCipheriv, createDecipheriv, randomBytes } from 'node:crypto';
import {
  BufferJSON,
  initAuthCreds,
  proto,
  type AuthenticationCreds,
  type AuthenticationState,
  type SignalDataTypeMap,
} from '@whiskeysockets/baileys';
import mysql, { type Pool, type PoolOptions } from 'mysql2/promise';

// ------------------------------------------------------------------------------------------------
// P0 #1 — useMySQLAuthState custom (substitui useMultiFileAuthState em prod)
//
// Upstream Baileys: "Don't ever use the useMultiFileAuthState in production" — corrupcao de
// arquivo no volume FS = session revogada = QR fresh (perda de chip). Esta implementacao
// armazena creds + Signal keys em MySQL central com valor cifrado AES-256-CBC pela APP_KEY
// do Laravel (base64-decoded). Volume FS continua disponivel como fallback dev-only via
// factory em `./authState.ts`.
//
// Tabela: `whatsapp_baileys_auth_state` (migration Laravel separada).
//   - instance_id    string indice (ex: 'ch-deadbeefdeadbeefdeadbeefdeadbeef')
//   - key_id         string ('creds' OR '<signal-type>-<id>' OR 'app-state-sync-key-<id>')
//   - value_encrypted mediumtext (AES-256-CBC + IV prefix, base64 JSON cifrado)
//   - updated_at     timestamp ON UPDATE
//   - UNIQUE (instance_id, key_id)
//
// Multi-tenant: NAO usa `business_id` global scope porque daemon Node nao tem session Laravel.
// Filtrar por `instance_id` e suficiente — UUID derivado do business no Hostinger garante
// unicidade cross-tenant.
// ------------------------------------------------------------------------------------------------

const ALGORITHM = 'aes-256-cbc';
const IV_LENGTH = 16;
const KEY_LENGTH = 32;

export interface MySQLAuthStateOptions {
  instanceId: string;
  encryptionKey: string;
  mysql: PoolOptions;
}

export interface MySQLAuthStateResult {
  state: AuthenticationState;
  saveCreds: () => Promise<void>;
  close: () => Promise<void>;
}

export function decodeEncryptionKey(raw: string): Buffer {
  const trimmed = raw.startsWith('base64:') ? raw.slice(7) : raw;
  const buf = Buffer.from(trimmed, 'base64');
  if (buf.length !== KEY_LENGTH) {
    throw new Error(
      `WHATSAPP_AUTH_STATE_ENCRYPTION_KEY invalida — esperado ${KEY_LENGTH} bytes apos base64-decode, recebeu ${buf.length}`,
    );
  }
  return buf;
}

export function encryptValue(plaintext: string, key: Buffer): string {
  const iv = randomBytes(IV_LENGTH);
  const cipher = createCipheriv(ALGORITHM, key, iv);
  const ciphertext = Buffer.concat([cipher.update(plaintext, 'utf8'), cipher.final()]);
  return Buffer.concat([iv, ciphertext]).toString('base64');
}

export function decryptValue(encoded: string, key: Buffer): string {
  const blob = Buffer.from(encoded, 'base64');
  if (blob.length < IV_LENGTH + 1) {
    throw new Error('valor cifrado corrompido — payload menor que IV+1');
  }
  const iv = blob.subarray(0, IV_LENGTH);
  const ciphertext = blob.subarray(IV_LENGTH);
  const decipher = createDecipheriv(ALGORITHM, key, iv);
  const plaintext = Buffer.concat([decipher.update(ciphertext), decipher.final()]);
  return plaintext.toString('utf8');
}

function createPool(options: PoolOptions): Pool {
  return mysql.createPool({
    waitForConnections: true,
    connectionLimit: 5,
    queueLimit: 0,
    ...options,
  });
}

export async function useMySQLAuthState(
  options: MySQLAuthStateOptions,
): Promise<MySQLAuthStateResult> {
  const key = decodeEncryptionKey(options.encryptionKey);
  const pool = createPool(options.mysql);

  async function readKey(keyId: string): Promise<unknown | null> {
    const [rows] = await pool.query<
      { value_encrypted: string }[] & import('mysql2').RowDataPacket[]
    >(
      'SELECT value_encrypted FROM whatsapp_baileys_auth_state WHERE instance_id = ? AND key_id = ? LIMIT 1',
      [options.instanceId, keyId],
    );
    if (!rows || rows.length === 0) return null;
    const first = rows[0];
    if (!first) return null;
    const plaintext = decryptValue(first.value_encrypted, key);
    return JSON.parse(plaintext, BufferJSON.reviver);
  }

  async function writeKey(keyId: string, value: unknown): Promise<void> {
    const json = JSON.stringify(value, BufferJSON.replacer);
    const encrypted = encryptValue(json, key);
    await pool.query(
      'INSERT INTO whatsapp_baileys_auth_state (instance_id, key_id, value_encrypted) VALUES (?, ?, ?) ' +
        'ON DUPLICATE KEY UPDATE value_encrypted = VALUES(value_encrypted)',
      [options.instanceId, keyId, encrypted],
    );
  }

  async function removeKey(keyId: string): Promise<void> {
    await pool.query(
      'DELETE FROM whatsapp_baileys_auth_state WHERE instance_id = ? AND key_id = ?',
      [options.instanceId, keyId],
    );
  }

  const existingCreds = (await readKey('creds')) as AuthenticationCreds | null;
  const creds: AuthenticationCreds = existingCreds ?? initAuthCreds();

  const state: AuthenticationState = {
    creds,
    keys: {
      get: async (type, ids) => {
        const data: { [id: string]: SignalDataTypeMap[typeof type] } = {};
        await Promise.all(
          ids.map(async (id) => {
            const keyId = `${type}-${id}`;
            let value = (await readKey(keyId)) as SignalDataTypeMap[typeof type] | null;
            if (type === 'app-state-sync-key' && value) {
              value = proto.Message.AppStateSyncKeyData.fromObject(
                value as object,
              ) as unknown as SignalDataTypeMap[typeof type];
            }
            if (value) {
              data[id] = value;
            }
          }),
        );
        return data;
      },
      set: async (data) => {
        const tasks: Promise<void>[] = [];
        for (const category in data) {
          const bucket = data[category as keyof SignalDataTypeMap];
          if (!bucket) continue;
          for (const id in bucket) {
            const value = (bucket as Record<string, unknown>)[id];
            const keyId = `${category}-${id}`;
            tasks.push(value ? writeKey(keyId, value) : removeKey(keyId));
          }
        }
        await Promise.all(tasks);
      },
    },
  };

  return {
    state,
    saveCreds: async () => {
      await writeKey('creds', creds);
    },
    close: async () => {
      await pool.end();
    },
  };
}
