import { describe, expect, it, beforeEach, afterEach, vi } from 'vitest';

/**
 * Regression test: garante que defaults conservadores dos hardenings
 * pós-merge não regridem.
 *
 * Por que: hardening pós PRs #813-#819 mudou `HEALTH_ZOMBIE_THRESHOLD_MS`
 * default de 30min → 60min pra evitar restart loop Docker em silêncio
 * prolongado real. Defensive: se alguém reverter pra 30min num PR futuro,
 * este test quebra e força revisão consciente.
 *
 * Caso real do incident 2026-05-13 (instância zumbi 99min) ainda é
 * detectado com folga vs threshold 60min — não há perda de cobertura.
 *
 * Estratégia: usa `vi.resetModules()` pra zerar o singleton `cached` de
 * loadEnv() entre tests — necessário porque env.ts mantém Env cached
 * após primeira chamada (perf em runtime, mas atrapalha em test).
 */
describe('env.ts hardening defaults', () => {
  const originalEnv = { ...process.env };

  beforeEach(async () => {
    vi.resetModules();
    process.env = { ...originalEnv };
    delete process.env.HEALTH_ZOMBIE_THRESHOLD_MS;
    delete process.env.ANTIBAN_CIRCADIAN_QUIET_START;
    delete process.env.ANTIBAN_CIRCADIAN_QUIET_END;
    delete process.env.ANTIBAN_CIRCADIAN_MULTIPLIER;
    process.env.WEBHOOK_BASE_URL = 'https://example.test';
    process.env.API_KEY = 'a'.repeat(16);
  });
  afterEach(() => {
    process.env = { ...originalEnv };
  });

  it('R-ENV-001 — HEALTH_ZOMBIE_THRESHOLD_MS default 60min (NÃO 30min — hardening pós PR #817)', async () => {
    const envMod = await import('./env');
    const env = envMod.loadEnv();

    expect(env.HEALTH_ZOMBIE_THRESHOLD_MS).toBe(60 * 60 * 1000);
    expect(env.HEALTH_ZOMBIE_THRESHOLD_MS).not.toBe(30 * 60 * 1000);
  });

  it('R-ENV-002 — quiet hours canônico 02-06 BRT (manter estável)', async () => {
    const envMod = await import('./env');
    const env = envMod.loadEnv();

    expect(env.ANTIBAN_CIRCADIAN_QUIET_START).toBe(2);
    expect(env.ANTIBAN_CIRCADIAN_QUIET_END).toBe(6);
    expect(env.ANTIBAN_CIRCADIAN_TZ).toBe('America/Sao_Paulo');
    expect(env.ANTIBAN_CIRCADIAN_MULTIPLIER).toBe(4);
  });

  it('R-ENV-003 — HEALTH_ZOMBIE_THRESHOLD_MS overrideável via env var', async () => {
    process.env.HEALTH_ZOMBIE_THRESHOLD_MS = '900000'; // 15min
    const envMod = await import('./env');
    const env = envMod.loadEnv();
    expect(env.HEALTH_ZOMBIE_THRESHOLD_MS).toBe(900000);
  });
});
