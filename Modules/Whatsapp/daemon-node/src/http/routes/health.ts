import type { FastifyPluginAsync } from 'fastify';
import { registry } from '../../observability/metrics';
import type { InstanceManager } from '../../baileys/InstanceManager';
import type { Env } from '../../config/env';

interface Deps {
  manager: InstanceManager;
  env: Env;
  startedAt: Date;
}

export const healthRoutes: FastifyPluginAsync<Deps> = async (app, deps) => {
  app.get('/health', async () => ({
    status: 'ok',
    uptime_seconds: Math.floor((Date.now() - deps.startedAt.getTime()) / 1000),
    instances: deps.manager.list().map((i) => ({
      id: i.instance_id,
      state: i.state,
      session_age_seconds: i.session_age_seconds,
    })),
  }));

  if (deps.env.METRICS_ENABLED) {
    app.get(deps.env.METRICS_ROUTE, async (_req, reply) => {
      reply.header('content-type', registry.contentType);
      return registry.metrics();
    });
  }
};
