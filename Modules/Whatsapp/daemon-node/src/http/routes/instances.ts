import type { FastifyPluginAsync } from 'fastify';
import type { InstanceManager } from '../../baileys/InstanceManager.js';
import { connectBody, instanceIdParam } from '../schemas.js';

interface Deps {
  manager: InstanceManager;
}

export const instanceRoutes: FastifyPluginAsync<Deps> = async (app, deps) => {
  app.post('/instances/:id/connect', async (req, reply) => {
    const { id } = instanceIdParam.parse(req.params);
    const body = connectBody.parse(req.body);
    const meta: Parameters<InstanceManager['connect']>[0] = {
      instance_id: id,
      business_uuid: body.business_uuid,
      ...(body.business_id !== undefined ? { business_id: body.business_id } : {}),
    };
    const instance = await deps.manager.connect(meta);
    return reply.code(202).send(instance.snapshot());
  });

  app.post('/instances/:id/disconnect', async (req) => {
    const { id } = instanceIdParam.parse(req.params);
    await deps.manager.disconnect(id);
    return { ok: true };
  });

  app.delete('/instances/:id', async (req) => {
    const { id } = instanceIdParam.parse(req.params);
    await deps.manager.purge(id);
    return { ok: true };
  });

  app.get('/instances/:id/status', async (req, reply) => {
    const { id } = instanceIdParam.parse(req.params);
    const instance = deps.manager.get(id);
    if (!instance) return reply.code(404).send({ error: 'instance_not_found' });
    return instance.snapshot();
  });

  app.get('/instances/:id/qr', async (req, reply) => {
    const { id } = instanceIdParam.parse(req.params);
    const instance = deps.manager.get(id);
    if (!instance) return reply.code(404).send({ error: 'instance_not_found' });
    const snap = instance.snapshot();
    if (snap.state !== 'qr_required' || !snap.qr) {
      return reply.code(409).send({ error: 'qr_unavailable', state: snap.state });
    }
    return { qr: snap.qr };
  });
};
