import type { FastifyPluginAsync } from 'fastify';
import { registry, zombiesDetectedCounter } from '../../observability/metrics.js';
import type { InstanceManager } from '../../baileys/InstanceManager.js';
import type { InstanceSnapshot } from '../../baileys/Instance.js';
import type { Env } from '../../config/env.js';

interface Deps {
  manager: InstanceManager;
  env: Env;
  startedAt: Date;
}

/**
 * Source SHA gravado em build time via Dockerfile ARG. Permite o cron
 * `whatsapp:daemon-source-drift-check` no Hostinger comparar versão local
 * (main HEAD) vs daemon prod e alertar drift.
 *
 * Default 'unknown' quando rodando local sem build arg (dev/test).
 *
 * @see Modules/Whatsapp/Console/Commands/DaemonSourceDriftCheckCommand.php
 * @see memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md
 */
const DAEMON_SOURCE_SHA = process.env.DAEMON_SOURCE_SHA ?? 'unknown';

/**
 * Detecta "zombie sockets": instances cujo state diz `connected` mas o
 * `last_seen` está estagnado além de `thresholdMs`. Significa que o daemon
 * acredita estar conectado mas o socket WhatsApp Web já parou de receber
 * frames — cliente está fora do ar sem alarme.
 *
 * Caso real 2026-05-13: `ch-88b13697...` ficou state=connected last_seen
 * estagnado 99min até intervenção manual. Docker healthcheck reportava
 * `healthy` (HTTP up) → policy de restart não disparava.
 *
 * Exportada pra teste isolado.
 */
export function detectZombies(
  instances: InstanceSnapshot[],
  thresholdMs: number,
  now: Date = new Date(),
): InstanceSnapshot[] {
  return instances.filter((i) => {
    if (i.state !== 'connected') return false;
    if (!i.last_seen) return false;
    const lastSeenMs = Date.parse(i.last_seen);
    if (Number.isNaN(lastSeenMs)) return false;
    return now.getTime() - lastSeenMs > thresholdMs;
  });
}

export const healthRoutes: FastifyPluginAsync<Deps> = async (app, deps) => {
  app.get('/health', async (_req, reply) => {
    const instances = deps.manager.list();
    const zombies = detectZombies(instances, deps.env.HEALTH_ZOMBIE_THRESHOLD_MS);

    // Incrementa counter Prometheus ANTES de retornar — permite alertar via
    // OTel (Grafana/Prometheus) antes do Docker policy disparar restart
    // silencioso. 1 incremento por zombie detectado por hit do healthcheck.
    for (const z of zombies) {
      zombiesDetectedCounter.inc({ instance_id: z.instance_id });
    }

    const body = {
      status: zombies.length > 0 ? 'degraded' : 'ok',
      uptime_seconds: Math.floor((Date.now() - deps.startedAt.getTime()) / 1000),
      daemon_source_sha: DAEMON_SOURCE_SHA,
      zombie_threshold_ms: deps.env.HEALTH_ZOMBIE_THRESHOLD_MS,
      zombies: zombies.map((i) => ({
        id: i.instance_id,
        display_phone: i.display_phone,
        last_seen: i.last_seen,
        session_age_seconds: i.session_age_seconds,
      })),
      instances: instances.map((i) => ({
        id: i.instance_id,
        state: i.state,
        last_seen: i.last_seen,
        session_age_seconds: i.session_age_seconds,
      })),
    };

    // 503 quando degraded — Docker healthcheck (HEALTHCHECK CMD curl -f /health)
    // detecta como unhealthy → restart policy reage. CT 100 Proxmox alerta via
    // counter `whatsapp_baileys_zombies_detected_total` ANTES do restart.
    return reply.code(zombies.length > 0 ? 503 : 200).send(body);
  });

  if (deps.env.METRICS_ENABLED) {
    app.get(deps.env.METRICS_ROUTE, async (_req, reply) => {
      reply.header('content-type', registry.contentType);
      return registry.metrics();
    });
  }
};
