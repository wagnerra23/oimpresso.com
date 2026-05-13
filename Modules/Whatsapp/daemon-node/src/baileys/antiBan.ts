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
  /**
   * Circadian rhythm — multiplica o jitter durante "quiet hours" (madrugada
   * típica BRT 02-06) pra parecer humano dormindo. Fora dessa janela o jitter
   * volta ao normal.
   *
   * Pesquisa anti-ban 2026 (baileys-antiban, kobie3717): Meta ML pontua
   * "temporal patterns" — bot enviando 04:00 BRT é robotic = high risk.
   * Default canônico: multiplier 4x em 02-06h timezone "America/Sao_Paulo".
   *
   * `circadianEnabled=false` desliga (preserva comportamento legado pra dev/test).
   */
  circadianEnabled: boolean;
  circadianQuietStartHour: number; // hora local, inclusiva (0-23)
  circadianQuietEndHour: number;   // hora local, exclusiva (0-23)
  circadianMultiplier: number;     // jitter * multiplier durante quiet
  circadianTimezone: string;       // IANA tz, default "America/Sao_Paulo"
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
 * Determina hora local (0-23) de um timezone IANA dado um Date UTC.
 *
 * Usa `Intl.DateTimeFormat` (built-in Node 20+ via ICU), sem dep externa.
 * Fallback: se timezone inválido, retorna hora UTC (não quebra anti-ban).
 *
 * Exportada pra teste isolado.
 */
export function localHourIn(now: Date, timezone: string): number {
  try {
    const formatter = new Intl.DateTimeFormat('en-US', {
      timeZone: timezone,
      hour: 'numeric',
      hour12: false,
    });
    const hourStr = formatter.format(now);
    const hour = Number.parseInt(hourStr, 10);
    if (Number.isNaN(hour)) return now.getUTCHours();
    // Intl format pode devolver "24" pra meia-noite — normalize
    return hour === 24 ? 0 : hour;
  } catch {
    return now.getUTCHours();
  }
}

/**
 * Verifica se `now` está dentro da janela quiet (madrugada timezone).
 *
 * `start <= end` (normal): match se start <= hour < end.
 * `start > end` (overnight): match se hour >= start OU hour < end.
 *   ex: start=22, end=6 cobre 22h-23h e 0h-5h
 *
 * Exportada pra teste.
 */
export function isQuietHour(now: Date, config: AntiBanConfig): boolean {
  if (!config.circadianEnabled) return false;
  const hour = localHourIn(now, config.circadianTimezone);
  const start = config.circadianQuietStartHour;
  const end = config.circadianQuietEndHour;

  if (start === end) return false; // janela vazia = desligado
  if (start < end) return hour >= start && hour < end;
  return hour >= start || hour < end; // overnight wrap
}

/**
 * Aplica multiplier circadian ao jitter quando dentro da janela quiet.
 * Fora da janela retorna os bounds originais.
 *
 * Exportada pra teste.
 */
export function applyCircadianMultiplier(
  config: AntiBanConfig,
  now: Date = new Date(),
): { jitterMinMs: number; jitterMaxMs: number; quiet: boolean } {
  if (!isQuietHour(now, config)) {
    return { jitterMinMs: config.jitterMinMs, jitterMaxMs: config.jitterMaxMs, quiet: false };
  }
  const m = Math.max(1, config.circadianMultiplier);
  return {
    jitterMinMs: Math.floor(config.jitterMinMs * m),
    jitterMaxMs: Math.floor(config.jitterMaxMs * m),
    quiet: true,
  };
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

  // 3. Jitter Gaussian antes do send — com circadian multiplier se quiet hour
  const bounds = applyCircadianMultiplier(config);
  const delay = gaussianRandom(bounds.jitterMinMs, bounds.jitterMaxMs);
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
  ANTIBAN_CIRCADIAN_ENABLED: boolean;
  ANTIBAN_CIRCADIAN_QUIET_START: number;
  ANTIBAN_CIRCADIAN_QUIET_END: number;
  ANTIBAN_CIRCADIAN_MULTIPLIER: number;
  ANTIBAN_CIRCADIAN_TZ: string;
}): AntiBanConfig {
  return {
    enabled: env.ANTIBAN_ENABLED,
    jitterMinMs: env.ANTIBAN_JITTER_MIN_MS,
    jitterMaxMs: env.ANTIBAN_JITTER_MAX_MS,
    typingMs: env.ANTIBAN_TYPING_MS,
    warmupDays: env.ANTIBAN_WARMUP_DAYS,
    circadianEnabled: env.ANTIBAN_CIRCADIAN_ENABLED,
    circadianQuietStartHour: env.ANTIBAN_CIRCADIAN_QUIET_START,
    circadianQuietEndHour: env.ANTIBAN_CIRCADIAN_QUIET_END,
    circadianMultiplier: env.ANTIBAN_CIRCADIAN_MULTIPLIER,
    circadianTimezone: env.ANTIBAN_CIRCADIAN_TZ,
  };
}

// Re-export pra teste/typing externo
export type { Instance };
