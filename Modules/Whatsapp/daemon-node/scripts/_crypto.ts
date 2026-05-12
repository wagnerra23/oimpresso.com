// Helpers AES-256-CBC compartilhados entre scripts ops (migrate-fs-to-mysql, rotate-encryption-key).
//
// Implementacao identica a `../src/baileys/mysqlAuthState.ts` (PR #701) — duplicado pra manter
// esta branch isolada do PR #701 (base origin/main). Pos-merge #701, substituir por re-export
// de `../src/baileys/mysqlAuthState` (mesmo contrato: chave 32B base64, IV 16B random por write).

import { createCipheriv, createDecipheriv, randomBytes } from 'node:crypto';

const ALGORITHM = 'aes-256-cbc';
const IV_LENGTH = 16;
const KEY_LENGTH = 32;

export function decodeEncryptionKey(raw: string): Buffer {
  const trimmed = raw.startsWith('base64:') ? raw.slice(7) : raw;
  const buf = Buffer.from(trimmed, 'base64');
  if (buf.length !== KEY_LENGTH) {
    throw new Error(
      `encryption key invalida — esperado ${KEY_LENGTH} bytes apos base64-decode, recebeu ${buf.length}`,
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
