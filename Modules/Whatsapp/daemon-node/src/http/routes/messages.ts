import type { FastifyPluginAsync } from 'fastify';
import type { InstanceManager } from '../../baileys/InstanceManager';
import {
  fetchHistoryBody,
  instanceIdParam,
  sendInteractiveBody,
  sendMediaBody,
  sendTextBody,
} from '../schemas';

interface Deps {
  manager: InstanceManager;
}

export const messageRoutes: FastifyPluginAsync<Deps> = async (app, deps) => {
  app.post('/instances/:id/text', async (req, reply) => {
    const { id } = instanceIdParam.parse(req.params);
    const body = sendTextBody.parse(req.body);
    const instance = deps.manager.get(id);
    if (!instance) return reply.code(404).send({ error: 'instance_not_found' });
    const result = await instance.sendText(body);
    return result;
  });

  app.post('/instances/:id/media', async (req, reply) => {
    const { id } = instanceIdParam.parse(req.params);
    const body = sendMediaBody.parse(req.body);
    const instance = deps.manager.get(id);
    if (!instance) return reply.code(404).send({ error: 'instance_not_found' });
    const input: Parameters<typeof instance.sendMedia>[0] = {
      to: body.to,
      media_url: body.media_url,
      type: body.type,
      ...(body.caption !== undefined ? { caption: body.caption } : {}),
      ...(body.filename !== undefined ? { filename: body.filename } : {}),
      ...(body.mimetype !== undefined ? { mimetype: body.mimetype } : {}),
    };
    const result = await instance.sendMedia(input);
    return result;
  });

  // US-WA-045/046 — mensagens interativas (buttons + list).
  // CTA URL é Meta Cloud-only — Zod do schema só aceita 2 variantes.
  app.post('/instances/:id/interactive', async (req, reply) => {
    const { id } = instanceIdParam.parse(req.params);
    const body = sendInteractiveBody.parse(req.body);
    const instance = deps.manager.get(id);
    if (!instance) return reply.code(404).send({ error: 'instance_not_found' });
    try {
      // Normalizar pra remover `description: undefined` (exactOptionalPropertyTypes
      // do tsconfig). Switch sobre discriminated union preserva tipos.
      let result;
      if (body.interactive.type === 'buttons') {
        result = await instance.sendInteractive({
          to: body.to,
          body: body.body,
          interactive: body.interactive,
        });
      } else {
        result = await instance.sendInteractive({
          to: body.to,
          body: body.body,
          interactive: {
            type: 'list',
            button_label: body.interactive.button_label,
            sections: body.interactive.sections.map((s) => ({
              title: s.title,
              items: s.items.map((it) => ({
                id: it.id,
                title: it.title,
                ...(it.description !== undefined ? { description: it.description } : {}),
              })),
            })),
          },
        });
      }
      return result;
    } catch (err) {
      const message = err instanceof Error ? err.message : String(err);
      if (message.includes('not connected')) {
        return reply.code(409).send({ error: 'instance_not_connected', message });
      }
      throw err;
    }
  });

  // US-WA-080 — import histórico Baileys (90d).
  // Caller (PHP `whatsapp:import-history`) passa cursor inicial
  // (before_id + before_ts) e itera. Daemon devolve 1 batch + has_more.
  app.post('/instances/:id/history', async (req, reply) => {
    const { id } = instanceIdParam.parse(req.params);
    const body = fetchHistoryBody.parse(req.body);
    const instance = deps.manager.get(id);
    if (!instance) return reply.code(404).send({ error: 'instance_not_found' });
    try {
      const result = await instance.fetchHistory({
        jid: body.jid,
        count: body.count,
        before_id: body.before_id,
        before_ts: body.before_ts,
        from_me: body.from_me,
        timeout_ms: body.timeout_ms,
      });
      return result;
    } catch (err) {
      const message = err instanceof Error ? err.message : String(err);
      // Não-conectado é 409 (alinha com /text e /media — sessionLost no PHP)
      if (message.includes('not connected')) {
        return reply.code(409).send({ error: 'instance_not_connected', message });
      }
      app.log.warn({ err, instance_id: id }, 'fetchHistory failed');
      return reply.code(500).send({ error: 'fetch_history_failed', message });
    }
  });
};
