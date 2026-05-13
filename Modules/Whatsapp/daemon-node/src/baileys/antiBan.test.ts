import { describe, expect, it, beforeEach, vi } from 'vitest';
import {
  type AntiBanConfig,
  type InstanceLike,
  applyCircadianMultiplier,
  checkAndIncrementWarmupQuota,
  gaussianRandom,
  isQuietHour,
  localHourIn,
  resetQuotaState,
  sendWithAntiBan,
  warmupQuotaPerHour,
} from './antiBan';

// ------------------------------------------------------------------------------------------------
// US-WA-094 — vitest spec do anti-ban middleware
//
// Cobertura:
//  1. enabled=false → bypass (sem typing, sem jitter, sem warmup check)
//  2. jitter Gaussian respeita bounds [min, max]
//  3. typing presence chamado antes do send (sequencia composing → sleep → paused → sleep → send)
//  4. chip <1d → max 10 msgs/h (11a chamada throw)
//  5. chip ~5d → quota progressiva (~150-180 msgs/h)
//  6. quota excedida → throw + sendFn NAO eh chamado
// ------------------------------------------------------------------------------------------------

function makeInstance(id: string): InstanceLike & { socket: { sendPresenceUpdate: ReturnType<typeof vi.fn> } } {
  return {
    meta: { instance_id: id },
    socket: {
      sendPresenceUpdate: vi.fn().mockResolvedValue(undefined),
    },
  };
}

function makeConfig(overrides: Partial<AntiBanConfig> = {}): AntiBanConfig {
  return {
    enabled: true,
    jitterMinMs: 50, // pequeno em test pra nao travar suite
    jitterMaxMs: 100,
    typingMs: 10,
    warmupDays: 7,
    circadianEnabled: false,
    circadianQuietStartHour: 2,
    circadianQuietEndHour: 6,
    circadianMultiplier: 4,
    circadianTimezone: 'America/Sao_Paulo',
    ...overrides,
  };
}

describe('antiBan — circadian rhythm', () => {
  it('localHourIn devolve hora local correta de UTC pra BRT (-3)', () => {
    // 2026-05-13T12:00:00Z = 09h BRT
    const utc = new Date(Date.UTC(2026, 4, 13, 12, 0, 0));
    expect(localHourIn(utc, 'America/Sao_Paulo')).toBe(9);
  });

  it('localHourIn devolve hora UTC se timezone inválido (fallback)', () => {
    const utc = new Date(Date.UTC(2026, 4, 13, 7, 0, 0));
    expect(localHourIn(utc, 'Bogus/Invalid')).toBe(7);
  });

  it('isQuietHour false quando circadianEnabled=false', () => {
    const utc = new Date(Date.UTC(2026, 4, 13, 6, 0, 0)); // 03h BRT (quiet)
    const cfg = makeConfig({ circadianEnabled: false });
    expect(isQuietHour(utc, cfg)).toBe(false);
  });

  it('isQuietHour true em 03h BRT (dentro 02-06)', () => {
    const utc = new Date(Date.UTC(2026, 4, 13, 6, 0, 0)); // 03h BRT
    const cfg = makeConfig({ circadianEnabled: true });
    expect(isQuietHour(utc, cfg)).toBe(true);
  });

  it('isQuietHour false em 14h BRT (fora 02-06)', () => {
    const utc = new Date(Date.UTC(2026, 4, 13, 17, 0, 0)); // 14h BRT
    const cfg = makeConfig({ circadianEnabled: true });
    expect(isQuietHour(utc, cfg)).toBe(false);
  });

  it('isQuietHour false na hora limite end (06h exclusive)', () => {
    const utc = new Date(Date.UTC(2026, 4, 13, 9, 0, 0)); // 06h BRT
    const cfg = makeConfig({ circadianEnabled: true });
    expect(isQuietHour(utc, cfg)).toBe(false);
  });

  it('isQuietHour cobre janela overnight (22h → 06h)', () => {
    const cfg = makeConfig({
      circadianEnabled: true,
      circadianQuietStartHour: 22,
      circadianQuietEndHour: 6,
    });
    // 23h BRT
    expect(isQuietHour(new Date(Date.UTC(2026, 4, 13, 2, 0, 0)), cfg)).toBe(true);
    // 03h BRT
    expect(isQuietHour(new Date(Date.UTC(2026, 4, 13, 6, 0, 0)), cfg)).toBe(true);
    // 12h BRT (fora)
    expect(isQuietHour(new Date(Date.UTC(2026, 4, 13, 15, 0, 0)), cfg)).toBe(false);
  });

  it('applyCircadianMultiplier multiplica jitter ×4 em quiet hour', () => {
    const utc = new Date(Date.UTC(2026, 4, 13, 6, 0, 0)); // 03h BRT
    const cfg = makeConfig({
      circadianEnabled: true,
      jitterMinMs: 1500,
      jitterMaxMs: 4000,
      circadianMultiplier: 4,
    });
    const r = applyCircadianMultiplier(cfg, utc);
    expect(r.quiet).toBe(true);
    expect(r.jitterMinMs).toBe(6000);
    expect(r.jitterMaxMs).toBe(16000);
  });

  it('applyCircadianMultiplier devolve bounds originais fora quiet', () => {
    const utc = new Date(Date.UTC(2026, 4, 13, 17, 0, 0)); // 14h BRT
    const cfg = makeConfig({
      circadianEnabled: true,
      jitterMinMs: 1500,
      jitterMaxMs: 4000,
      circadianMultiplier: 4,
    });
    const r = applyCircadianMultiplier(cfg, utc);
    expect(r.quiet).toBe(false);
    expect(r.jitterMinMs).toBe(1500);
    expect(r.jitterMaxMs).toBe(4000);
  });

  it('applyCircadianMultiplier clamp multiplier mínimo a 1 (segurança)', () => {
    const utc = new Date(Date.UTC(2026, 4, 13, 6, 0, 0));
    const cfg = makeConfig({
      circadianEnabled: true,
      jitterMinMs: 1500,
      jitterMaxMs: 4000,
      circadianMultiplier: 0.5, // < 1, deve clampear
    });
    const r = applyCircadianMultiplier(cfg, utc);
    expect(r.jitterMinMs).toBe(1500); // floor(1500 * 1) = 1500
    expect(r.jitterMaxMs).toBe(4000);
  });

  it('janela vazia (start === end) desabilita circadian', () => {
    const utc = new Date(Date.UTC(2026, 4, 13, 6, 0, 0));
    const cfg = makeConfig({
      circadianEnabled: true,
      circadianQuietStartHour: 4,
      circadianQuietEndHour: 4,
    });
    expect(isQuietHour(utc, cfg)).toBe(false);
  });
});

describe('antiBan — gaussianRandom', () => {
  it('Test 2: respeita bounds [min, max] em 1000 samples', () => {
    const samples: number[] = [];
    for (let i = 0; i < 1000; i += 1) {
      samples.push(gaussianRandom(1500, 4000));
    }
    const min = Math.min(...samples);
    const max = Math.max(...samples);
    expect(min).toBeGreaterThanOrEqual(1500);
    expect(max).toBeLessThanOrEqual(4000);

    // Distribuicao Gaussian: mean deve estar perto de 2750
    const mean = samples.reduce((a, b) => a + b, 0) / samples.length;
    expect(mean).toBeGreaterThan(2400);
    expect(mean).toBeLessThan(3100);
  });
});

describe('antiBan — warmupQuotaPerHour', () => {
  it('idade <1d → 10 msgs/h', () => {
    expect(warmupQuotaPerHour(0, 7)).toBe(10);
    expect(warmupQuotaPerHour(0.5, 7)).toBe(10);
    expect(warmupQuotaPerHour(0.99, 7)).toBe(10);
  });

  it('idade 1-2d → 25 msgs/h', () => {
    expect(warmupQuotaPerHour(1, 7)).toBe(25);
    expect(warmupQuotaPerHour(1.5, 7)).toBe(25);
  });

  it('idade 2-7d → progressivo 50-200', () => {
    expect(warmupQuotaPerHour(2, 7)).toBeGreaterThanOrEqual(50);
    expect(warmupQuotaPerHour(2, 7)).toBeLessThan(70);
    expect(warmupQuotaPerHour(5, 7)).toBeGreaterThan(100);
    expect(warmupQuotaPerHour(5, 7)).toBeLessThan(200);
    expect(warmupQuotaPerHour(6.9, 7)).toBeLessThanOrEqual(200);
  });

  it('idade >=warmupDays → Infinity', () => {
    expect(warmupQuotaPerHour(7, 7)).toBe(Number.POSITIVE_INFINITY);
    expect(warmupQuotaPerHour(30, 7)).toBe(Number.POSITIVE_INFINITY);
  });
});

describe('antiBan — sendWithAntiBan', () => {
  beforeEach(() => {
    resetQuotaState();
  });

  it('Test 1: enabled=false → bypass (sem typing/jitter/warmup)', async () => {
    const instance = makeInstance('ch-bypass');
    const sendFn = vi.fn().mockResolvedValue({ ok: true });
    const config = makeConfig({ enabled: false });

    const start = Date.now();
    const result = await sendWithAntiBan(instance, '5511999999999@s.whatsapp.net', sendFn, config);
    const elapsed = Date.now() - start;

    expect(result).toEqual({ ok: true });
    expect(sendFn).toHaveBeenCalledTimes(1);
    expect(instance.socket.sendPresenceUpdate).not.toHaveBeenCalled();
    // Sem jitter: deve ser quase instantaneo
    expect(elapsed).toBeLessThan(50);
  });

  it('Test 3: typing presence chamado antes do send (mock socket)', async () => {
    const instance = makeInstance('ch-typing');
    const callOrder: string[] = [];

    instance.socket.sendPresenceUpdate.mockImplementation(async (type: string) => {
      callOrder.push(`presence:${type}`);
    });

    const sendFn = vi.fn().mockImplementation(async () => {
      callOrder.push('send');
      return { ok: true };
    });

    await sendWithAntiBan(instance, 'jid@s.whatsapp.net', sendFn, makeConfig());

    expect(callOrder).toEqual(['presence:composing', 'presence:paused', 'send']);
    expect(instance.socket.sendPresenceUpdate).toHaveBeenCalledWith('composing', 'jid@s.whatsapp.net');
    expect(instance.socket.sendPresenceUpdate).toHaveBeenCalledWith('paused', 'jid@s.whatsapp.net');
  });

  it('Test 4: chip <1d → max 10/h (11a chamada throw)', async () => {
    const instance = makeInstance('ch-new');
    const sendFn = vi.fn().mockResolvedValue({ ok: true });
    const config = makeConfig();

    // firstPairAt = agora (chip novinho)
    const firstPair = new Date();

    // 10 sends OK
    for (let i = 0; i < 10; i += 1) {
      await sendWithAntiBan(instance, 'jid@s.whatsapp.net', sendFn, config, firstPair);
    }
    expect(sendFn).toHaveBeenCalledTimes(10);

    // 11a deve throw
    await expect(
      sendWithAntiBan(instance, 'jid@s.whatsapp.net', sendFn, config, firstPair),
    ).rejects.toThrow(/Warmup quota exceeded/);

    // sendFn nao foi chamado na 11a
    expect(sendFn).toHaveBeenCalledTimes(10);
  });

  it('Test 5: chip 5d → quota progressiva (>=100, <200) — testa via warmupQuotaPerHour direto', () => {
    const quota = warmupQuotaPerHour(5, 7);
    expect(quota).toBeGreaterThanOrEqual(100);
    expect(quota).toBeLessThan(200);
  });

  it('Test 6: quota excedida → throw + sendFn nao chamado', async () => {
    const instanceId = 'ch-exceeded';
    const firstPair = new Date(); // chip novo, quota=10

    // Consome quota direto via helper
    for (let i = 0; i < 10; i += 1) {
      checkAndIncrementWarmupQuota(instanceId, 7, firstPair);
    }

    const instance = makeInstance(instanceId);
    const sendFn = vi.fn().mockResolvedValue({ ok: true });

    await expect(
      sendWithAntiBan(instance, 'jid@s.whatsapp.net', sendFn, makeConfig(), firstPair),
    ).rejects.toThrow(/Warmup quota exceeded/);

    expect(sendFn).not.toHaveBeenCalled();
    // Typing tambem nao foi chamado (quota check eh fast-fail antes)
    expect(instance.socket.sendPresenceUpdate).not.toHaveBeenCalled();
  });

  it('chip antigo (>7d) → sem limite de warmup', async () => {
    const instance = makeInstance('ch-old');
    const sendFn = vi.fn().mockResolvedValue({ ok: true });
    const oldPair = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000); // 30d atras

    // 20 chamadas sem erro (jitter pequeno em config)
    for (let i = 0; i < 20; i += 1) {
      await sendWithAntiBan(instance, 'jid@s.whatsapp.net', sendFn, makeConfig(), oldPair);
    }
    expect(sendFn).toHaveBeenCalledTimes(20);
  });

  it('typing presence erro nao propaga (nao-fatal)', async () => {
    const instance = makeInstance('ch-presence-fail');
    instance.socket.sendPresenceUpdate.mockRejectedValue(new Error('presence failed'));

    const sendFn = vi.fn().mockResolvedValue({ ok: true });
    const result = await sendWithAntiBan(instance, 'jid@s.whatsapp.net', sendFn, makeConfig());

    expect(result).toEqual({ ok: true });
    expect(sendFn).toHaveBeenCalled();
  });
});
