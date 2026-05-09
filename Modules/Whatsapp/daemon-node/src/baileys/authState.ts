import { mkdir } from 'node:fs/promises';
import { join, resolve } from 'node:path';
import { useMultiFileAuthState, type AuthenticationState, type SignalDataTypeMap } from '@whiskeysockets/baileys';

export interface PersistedAuth {
  state: AuthenticationState;
  saveCreds: () => Promise<void>;
  keys: { get: (type: keyof SignalDataTypeMap, ids: string[]) => Promise<Record<string, unknown>> };
  dir: string;
}

const SAFE_INSTANCE_ID = /^[A-Za-z0-9_-]{1,64}$/;

export function instanceDir(baseDir: string, instanceId: string): string {
  if (!SAFE_INSTANCE_ID.test(instanceId)) {
    throw new Error(`instance_id inválido: ${instanceId}`);
  }
  return resolve(join(baseDir, instanceId));
}

export async function loadAuthState(baseDir: string, instanceId: string): Promise<PersistedAuth> {
  const dir = instanceDir(baseDir, instanceId);
  await mkdir(dir, { recursive: true, mode: 0o700 });
  const { state, saveCreds } = await useMultiFileAuthState(dir);
  return {
    state,
    saveCreds,
    keys: state.keys as unknown as PersistedAuth['keys'],
    dir,
  };
}
