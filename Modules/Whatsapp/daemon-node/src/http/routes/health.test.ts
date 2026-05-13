import { describe, expect, it, beforeEach } from 'vitest';
import { detectZombies } from './health';
import { zombiesDetectedCounter } from '../../observability/metrics';
import type { InstanceSnapshot } from '../../baileys/Instance';

// ------------------------------------------------------------------------------------------------
// Zombie socket detection — fecha Gap D do post-mortem 2026-05-13.
//
// Caso real: instance ch-88b13697... reportava state=connected last_seen
// estagnado 99min mas socket WhatsApp já estava morto. Healthcheck antigo
// só checava HTTP up → Docker marcava healthy → restart policy não disparava.
// ------------------------------------------------------------------------------------------------

function snap(overrides: Partial<InstanceSnapshot> = {}): InstanceSnapshot {
  return {
    instance_id: 'ch-test',
    business_uuid: 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
    state: 'connected',
    display_phone: '5511999999999',
    last_seen: new Date(Date.UTC(2026, 4, 13, 19, 0, 0)).toISOString(),
    session_age_seconds: 1000,
    qr: null,
    ban_reason: null,
    ...overrides,
  };
}

describe('detectZombies', () => {
  it('flagra instância connected com last_seen > threshold (cenário 2026-05-13)', () => {
    const now = new Date(Date.UTC(2026, 4, 13, 19, 20, 0));
    const instances = [
      snap({
        instance_id: 'ch-88b13697b89e451cb65be917533bab21',
        state: 'connected',
        last_seen: new Date(Date.UTC(2026, 4, 13, 17, 39, 39)).toISOString(),
      }),
    ];
    const zombies = detectZombies(instances, 30 * 60 * 1000, now);
    expect(zombies).toHaveLength(1);
    expect(zombies[0].instance_id).toBe('ch-88b13697b89e451cb65be917533bab21');
  });

  it('NÃO flagra instância connected com last_seen recente', () => {
    const now = new Date(Date.UTC(2026, 4, 13, 19, 20, 0));
    const instances = [
      snap({
        state: 'connected',
        last_seen: new Date(Date.UTC(2026, 4, 13, 19, 19, 0)).toISOString(),
      }),
    ];
    expect(detectZombies(instances, 30 * 60 * 1000, now)).toHaveLength(0);
  });

  it('NÃO flagra state != connected (e.g. banned, qr_required)', () => {
    const now = new Date(Date.UTC(2026, 4, 13, 19, 20, 0));
    const instances = [
      snap({
        state: 'banned',
        ban_reason: 'logged_out',
        last_seen: new Date(Date.UTC(2026, 4, 13, 17, 0, 0)).toISOString(),
      }),
      snap({
        instance_id: 'ch-qr',
        state: 'qr_required',
        last_seen: null,
      }),
    ];
    expect(detectZombies(instances, 30 * 60 * 1000, now)).toHaveLength(0);
  });

  it('NÃO flagra last_seen null (nunca chegou a conectar)', () => {
    const now = new Date(Date.UTC(2026, 4, 13, 19, 20, 0));
    const instances = [snap({ state: 'connected', last_seen: null })];
    expect(detectZombies(instances, 30 * 60 * 1000, now)).toHaveLength(0);
  });

  it('NÃO flagra last_seen ISO inválido (defensivo)', () => {
    const now = new Date(Date.UTC(2026, 4, 13, 19, 20, 0));
    const instances = [snap({ state: 'connected', last_seen: 'not-a-date' })];
    expect(detectZombies(instances, 30 * 60 * 1000, now)).toHaveLength(0);
  });

  it('threshold customizável (60min permite zombies de 45min)', () => {
    const now = new Date(Date.UTC(2026, 4, 13, 19, 20, 0));
    const instances = [
      snap({
        state: 'connected',
        last_seen: new Date(Date.UTC(2026, 4, 13, 18, 35, 0)).toISOString(), // 45min atrás
      }),
    ];
    expect(detectZombies(instances, 60 * 60 * 1000, now)).toHaveLength(0);
    expect(detectZombies(instances, 30 * 60 * 1000, now)).toHaveLength(1);
  });

  it('múltiplas zombies + saudáveis em mesma lista', () => {
    const now = new Date(Date.UTC(2026, 4, 13, 19, 20, 0));
    const stale = new Date(Date.UTC(2026, 4, 13, 17, 0, 0)).toISOString();
    const fresh = new Date(Date.UTC(2026, 4, 13, 19, 19, 0)).toISOString();
    const instances = [
      snap({ instance_id: 'ch-zombie1', last_seen: stale }),
      snap({ instance_id: 'ch-fresh', last_seen: fresh }),
      snap({ instance_id: 'ch-zombie2', last_seen: stale }),
      snap({ instance_id: 'ch-banned', state: 'banned', last_seen: stale }),
    ];
    const zombies = detectZombies(instances, 30 * 60 * 1000, now);
    expect(zombies.map((z) => z.instance_id).sort()).toEqual(['ch-zombie1', 'ch-zombie2']);
  });
});

// ------------------------------------------------------------------------------------------------
// Regression tests pros 2 hardenings do PR de hardening pós-merge:
//   1. Counter Prometheus existe + tem label correto
//   2. Counter incrementa quando zombie detectado (smoke direto)
// ------------------------------------------------------------------------------------------------

describe('zombiesDetectedCounter (hardening: OTel pre-restart alert)', () => {
  beforeEach(() => {
    zombiesDetectedCounter.reset();
  });

  it('existe registrado com label `instance_id` (pré-condição pra alerta Grafana)', async () => {
    // Procura no metric output do counter pelo nome canônico
    const metrics = await zombiesDetectedCounter.get();
    expect(metrics.name).toBe('whatsapp_baileys_zombies_detected_total');
    expect(metrics.help).toContain('Zombie sockets detectados');
    // Counter inicia em 0 com nenhuma label gravada (reset() acima)
    expect(metrics.values).toEqual([]);
  });

  it('incrementa quando inc({instance_id}) é chamado', async () => {
    zombiesDetectedCounter.inc({ instance_id: 'ch-88b13697b89e451cb65be917533bab21' });
    zombiesDetectedCounter.inc({ instance_id: 'ch-88b13697b89e451cb65be917533bab21' });
    zombiesDetectedCounter.inc({ instance_id: 'ch-da8c23c55a6c4538b82f1a05c47ac5da' });

    const metrics = await zombiesDetectedCounter.get();
    const ch1 = metrics.values.find((v) => v.labels.instance_id === 'ch-88b13697b89e451cb65be917533bab21');
    const ch2 = metrics.values.find((v) => v.labels.instance_id === 'ch-da8c23c55a6c4538b82f1a05c47ac5da');

    expect(ch1?.value).toBe(2);
    expect(ch2?.value).toBe(1);
  });
});
