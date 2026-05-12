import { describe, expect, it, beforeEach, vi } from 'vitest';
import {
  type AntiBanConfig,
  type InstanceLike,
  checkAndIncrementWarmupQuota,
  gaussianRandom,
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
    ...overrides,
  };
}

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
