import { setTimeout as sleep } from 'node:timers/promises';
import type { WAPresence } from '@whiskeysockets/baileys';
import type { Instance } from './Instance';

// ------------------------------------------------------------------------------------------------
// US-WA-094 — Anti-ban middleware (P1)
//
// Wrap pra `socket.sendMessage()` que aplica 3 mitigacoes contra rate-limit /
// ban do WhatsApp Multi-Device:
//
//  1. Typing presence ('composing') antes do send + 'paused' depois, com sleep
//     curto (200-800ms tipico) simulando "humano digitando".
//  2. Jitter Gaussian (Box-Muller) entre 1.5-4s antes do send — anti-rate-limit
//     server-side. Gaussian (vs uniforme) parece humano: maioria perto da media,
//     poucos outliers nas pontas.
//  3. Warmup quota — chip novo (<7d) tem limite progressivo de msgs/h. Dia 0-1
//     = 10/h, dia 1-2 = 25/h, dia 2-7 progressivo ate 200/h, depois sem limite.
//     Counter in-memory per-instance — NAO persiste cross-restart (P2: Redis).
//
// Defaults seguros: enabled=true em prod, false em dev/test (evita slow tests).
// Toda config vem via env (validada em config/env.ts).
//
// Importante: este modulo NAO conhece detalhes da Instance — recebe a Instance
// como parametro e usa `instance.socket` + `instance.meta.instance_id`. Manter
// SoC pra facilitar testar isoladamente (mock Instance).
// ------------------------------------------------------------------------------------------------

export interface AntiBanConfig {
  enabled: boolean;
  jitterMinMs: number;
  jitterMaxMs: number;
  typingMs: number;
  warmupDays: number;
}

export interface InstanceLike {
  meta: { instance_id: string };
  // Acesso ao socket Baileys — typed minimamente pra tipa-checar sem expor
  // o WASocket inteiro (que tem ~100 metodos). Ajustes futuros: trocar pra
  // Pick<WASocket, 'sendPresenceUpdate'>.
  socket: {
    sendPresenceUpdate: (type: WAPresence, toJid?: string) => Promise<void>;
  } | null;
}

// State per-instance — persistencia in-memory (resetado a cada restart daemon).
interface InstanceQuotaState {
  firstSeen: Date;
  hourlyResetAt: Date;
  hourlyCount: number;
}

// Map global module-scope. Em testes, exposicao via `resetQuotaState()`.
const quotaStateByInstance = new Map<string, InstanceQuotaState>();

/**
 * Box-Muller transform — sample Gaussian distribution clamped em [min, max].
 *
 * Mean = (min+max)/2, stddev = (max-min)/6 — i.e., 99.7% das samples dentro
 * de 3 sigma do mean, e clamp garante hard bounds.
 */
export function gaussianRandom(min: number, max: number): number {
  let u = 0;
  let v = 0;
  while (u === 0) u = Math.random();
  while (v === 0) v = Math.random();
  const z = Math.sqrt(-2.0 * Math.log(u)) * Math.cos(2.0 * Math.PI * v);
  const mean = (min + max) / 2;
  const stddev = (max - min) / 6;
  const result = z * stddev + mean;
  return Math.max(min, Math.min(max, result));
}

/**
 * Calcula quota horaria pra um chip baseado em idade em dias desde firstSeen.
 *
 * Tabela:
 *   - Dia 0-1 (primeiras 24h): 10 msgs/h
 *   - Dia 1-2: 25 msgs/h
 *   - Dia 2-7: linear de 50 ate 200 msgs/h
 *   - Apos warmupDays: Infinity (sem limite middleware-side)
 */
export function warmupQuotaPerHour(ageDays: number, warmupDays: number): number {
  if (ageDays >= warmupDays) return Number.POSITIVE_INFINITY;
  if (ageDays < 1) return 10;
  if (ageDays < 2) return 25;
  // Dia 2 ate warmupDays — linear de 50 -> 200
  const t = (ageDays - 2) / Math.max(1, warmupDays - 2);
  return Math.floor(50 + t * 150);
}

/**
 * Checa + incrementa o counter horario. Lanca se quota excedida.
 *
 * Reset automatico: se passou 1h desde `hourlyResetAt`, zera o counter.
 *
 * `firstPairAtOverride` permite caller (Instance) passar o timestamp real
 * de pareamento (vindo de `meta.json`), em vez de usar firstSeen module-local
 * (que reseta a cada restart). Em producao isso evita bypass via restart.
 */
export function checkAndIncrementWarmupQuota(
  instanceId: string,
  warmupDays: number,
  firstPairAtOverride?: Date,
): void {
  const now = new Date();
  let state = quotaStateByInstance.get(instanceId);

  if (!state) {
    state = {
      firstSeen: firstPairAtOverride ?? now,
      hourlyResetAt: now,
      hourlyCount: 0,
    };
    quotaStateByInstance.set(instanceId, state);
  } else if (firstPairAtOverride && firstPairAtOverride.getTime() < state.firstSeen.getTime()) {
    // Caller passou firstPair mais antigo — adota (chip mais velho que parecia).
    state.firstSeen = firstPairAtOverride;
  }

  // Reset janela horaria
  if (now.getTime() - state.hourlyResetAt.getTime() >= 60 * 60 * 1000) {
    state.hourlyResetAt = now;
    state.hourlyCount = 0;
  }

  const ageMs = now.getTime() - state.firstSeen.getTime();
  const ageDays = ageMs / (24 * 60 * 60 * 1000);
  const quota = warmupQuotaPerHour(ageDays, warmupDays);

  if (state.hourlyCount >= quota) {
    throw new Error(
      `Warmup quota exceeded for instance ${instanceId} ` +
        `(age=${ageDays.toFixed(2)}d, hourly=${state.hourlyCount}/${quota}, warmupDays=${warmupDays})`,
    );
  }

  state.hourlyCount += 1;
}

/**
 * Wrapper principal — aplica typing presence + jitter Gaussian + warmup check
 * antes de invocar `sendFn()`. Se `config.enabled=false`, bypassa tudo.
 *
 * Erros em `sendPresenceUpdate` sao swallowed (nao-fatais — presence eh
 * cosmetico). Erros em warmup quota propagam (caller decide retry/drop).
 */
export async function sendWithAntiBan<T>(
  instance: InstanceLike,
  jid: string,
  sendFn: () => Promise<T>,
  config: AntiBanConfig,
  firstPairAtOverride?: Date,
): Promise<T> {
  if (!config.enabled) return sendFn();

  // 1. Warmup check ANTES de typing (se quota excedida, nao desperdica
  //    presence update + jitter — falha fast).
  checkAndIncrementWarmupQuota(instance.meta.instance_id, config.warmupDays, firstPairAtOverride);

  // 2. Typing presence (parece humano digitando)
  try {
    await instance.socket?.sendPresenceUpdate('composing', jid);
    await sleep(config.typingMs);
    await instance.socket?.sendPresenceUpdate('paused', jid);
  } catch {
    // Nao-fatal — presence eh cosmetico
  }

  // 3. Jitter Gaussian antes do send
  const delay = gaussianRandom(config.jitterMinMs, config.jitterMaxMs);
  await sleep(delay);

  return sendFn();
}

/**
 * Limpa state in-memory — usado em testes pra isolar cases. Em producao
 * nao precisa ser chamado.
 */
export function resetQuotaState(instanceId?: string): void {
  if (instanceId) {
    quotaStateByInstance.delete(instanceId);
  } else {
    quotaStateByInstance.clear();
  }
}

/**
 * Helper pra build config a partir do Env (objeto carregado em loadEnv).
 * Mantido aqui pra colocacao SoC — env.ts so valida string→tipo, semantic
 * mapping vive aqui.
 */
export function antiBanConfigFromEnv(env: {
  ANTIBAN_ENABLED: boolean;
  ANTIBAN_JITTER_MIN_MS: number;
  ANTIBAN_JITTER_MAX_MS: number;
  ANTIBAN_TYPING_MS: number;
  ANTIBAN_WARMUP_DAYS: number;
}): AntiBanConfig {
  return {
    enabled: env.ANTIBAN_ENABLED,
    jitterMinMs: env.ANTIBAN_JITTER_MIN_MS,
    jitterMaxMs: env.ANTIBAN_JITTER_MAX_MS,
    typingMs: env.ANTIBAN_TYPING_MS,
    warmupDays: env.ANTIBAN_WARMUP_DAYS,
  };
}

// Re-export pra teste/typing externo
export type { Instance };
