import { mkdir } from 'node:fs/promises';
import { join, resolve } from 'node:path';
import { useMultiFileAuthState, type AuthenticationState, type SignalDataTypeMap } from '@whiskeysockets/baileys';
import { useMySQLAuthState } from './mysqlAuthState.js';
import type { Env } from '../config/env.js';

export interface PersistedAuth {
  state: AuthenticationState;
  saveCreds: () => Promise<void>;
  keys: { get: (type: keyof SignalDataTypeMap, ids: string[]) => Promise<Record<string, unknown>> };
  /** Diretório FS associado (sessão filesystem) ou null quando backend MySQL */
  dir: string | null;
  /** Cleanup opcional (fechar pool MySQL etc) — chamado em purgeSession */
  close?: () => Promise<void>;
}

const SAFE_INSTANCE_ID = /^[A-Za-z0-9_-]{1,64}$/;

export function instanceDir(baseDir: string, instanceId: string): string {
  if (!SAFE_INSTANCE_ID.test(instanceId)) {
    throw new Error(`instance_id inválido: ${instanceId}`);
  }
  return resolve(join(baseDir, instanceId));
}

/**
 * Carrega o auth state Baileys. Em prod (env.AUTH_STATE_BACKEND='mysql') usa
 * `useMySQLAuthState` com cifragem AES-256-CBC pela APP_KEY. Em dev (default
 * 'filesystem') mantém `useMultiFileAuthState` legacy — não quebra dev local.
 *
 * Validação de `instance_id` é feita em ambos os branches: regex SAFE_INSTANCE_ID
 * em FS evita path traversal; em MySQL a query é parameterizada (sem injection).
 */
export async function loadAuthState(env: Env, instanceId: string): Promise<PersistedAuth> {
  if (!SAFE_INSTANCE_ID.test(instanceId)) {
    throw new Error(`instance_id inválido: ${instanceId}`);
  }

  if (env.AUTH_STATE_BACKEND === 'mysql') {
    if (!env.WHATSAPP_AUTH_STATE_ENCRYPTION_KEY) {
      throw new Error(
        'AUTH_STATE_BACKEND=mysql exige WHATSAPP_AUTH_STATE_ENCRYPTION_KEY (base64 32 bytes derivada da APP_KEY Laravel)',
      );
    }
    if (!env.MYSQL_AUTH_STATE_USER || !env.MYSQL_AUTH_STATE_PASS || !env.MYSQL_AUTH_STATE_DB) {
      throw new Error(
        'AUTH_STATE_BACKEND=mysql exige MYSQL_AUTH_STATE_USER/PASS/DB configurados',
      );
    }
    const { state, saveCreds, close } = await useMySQLAuthState({
      instanceId,
      encryptionKey: env.WHATSAPP_AUTH_STATE_ENCRYPTION_KEY,
      mysql: {
        host: env.MYSQL_AUTH_STATE_HOST,
        port: env.MYSQL_AUTH_STATE_PORT,
        user: env.MYSQL_AUTH_STATE_USER,
        password: env.MYSQL_AUTH_STATE_PASS,
        database: env.MYSQL_AUTH_STATE_DB,
      },
    });
    return {
      state,
      saveCreds,
      keys: state.keys as unknown as PersistedAuth['keys'],
      dir: null,
      close,
    };
  }

  // Fallback dev-only — useMultiFileAuthState (NÃO usar em prod conforme Baileys upstream)
  const dir = instanceDir(env.SESSIONS_DIR, instanceId);
  await mkdir(dir, { recursive: true, mode: 0o700 });
  const { state, saveCreds } = await useMultiFileAuthState(dir);
  return {
    state,
    saveCreds,
    keys: state.keys as unknown as PersistedAuth['keys'],
    dir,
  };
}
