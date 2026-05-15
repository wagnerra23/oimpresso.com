import { beforeEach, describe, expect, it, vi } from 'vitest';
import Fastify, { type FastifyInstance } from 'fastify';
import { messageRoutes } from './messages.js';
import errorHandlerPlugin from '../plugins/errorHandler.js';
import type { InstanceManager } from '../../baileys/InstanceManager.js';

// ------------------------------------------------------------------------------------------------
// US-WA-080 — vitest spec da rota POST /instances/:id/history
//
// Mocka Instance.fetchHistory pra exercitar contract do daemon (schema Zod
// + status codes 404/409/500/200) sem precisar socket Baileys real.
// ------------------------------------------------------------------------------------------------

interface MockInstance {
  fetchHistory: ReturnType<typeof vi.fn>;
}

function makeManager(instance: MockInstance | null): InstanceManager {
  return {
    get: vi.fn().mockReturnValue(instance),
  } as unknown as InstanceManager;
}

async function makeApp(manager: InstanceManager): Promise<FastifyInstance> {
  const app = Fastify({ logger: false });
  await app.register(errorHandlerPlugin);
  await app.register(messageRoutes, { manager });
  return app;
}

const validBody = {
  jid: '5548999872822@s.whatsapp.net',
  count: 50,
  before_id: 'MSG_ID_ABC',
  before_ts: 1746000000,
};

describe('POST /instances/:id/history', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('404 quando instance_id não existe no manager', async () => {
    const manager = makeManager(null);
    const app = await makeApp(manager);
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-deadbeef/history',
      payload: validBody,
    });
    expect(res.statusCode).toBe(404);
    expect(res.json()).toMatchObject({ error: 'instance_not_found' });
    await app.close();
  });

  it('422 quando body inválido (jid ausente) — Zod', async () => {
    const instance: MockInstance = { fetchHistory: vi.fn() };
    const manager = makeManager(instance);
    const app = await makeApp(manager);
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-abc/history',
      payload: { count: 50, before_id: 'x', before_ts: 1 },
    });
    expect(res.statusCode).toBe(422);
    expect(res.json()).toMatchObject({ error: 'validation_error' });
    expect(instance.fetchHistory).not.toHaveBeenCalled();
    await app.close();
  });

  it('200 batch normal — retorna messages + cursor', async () => {
    const instance: MockInstance = {
      fetchHistory: vi.fn().mockResolvedValue({
        count: 2,
        has_more: false,
        oldest_id: 'MSG_OLD',
        oldest_ts: 1745000000,
        empty: false,
        messages: [
          {
            key: { remoteJid: '5548999872822@s.whatsapp.net', id: 'MSG_NEW', fromMe: false },
            message: { conversation: 'Oi' },
            push_name: 'Larissa',
            timestamp: 1746000000,
          },
          {
            key: { remoteJid: '5548999872822@s.whatsapp.net', id: 'MSG_OLD', fromMe: true },
            message: { conversation: 'Bom dia' },
            push_name: null,
            timestamp: 1745000000,
          },
        ],
      }),
    };
    const manager = makeManager(instance);
    const app = await makeApp(manager);
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-abc/history',
      payload: validBody,
    });
    expect(res.statusCode).toBe(200);
    const json = res.json();
    expect(json.count).toBe(2);
    expect(json.has_more).toBe(false);
    expect(json.messages).toHaveLength(2);
    expect(instance.fetchHistory).toHaveBeenCalledWith(
      expect.objectContaining({
        jid: '5548999872822@s.whatsapp.net',
        count: 50,
        before_id: 'MSG_ID_ABC',
        before_ts: 1746000000,
      }),
    );
    await app.close();
  });

  it('200 empty — WhatsApp não devolveu nada dentro do timeout', async () => {
    const instance: MockInstance = {
      fetchHistory: vi.fn().mockResolvedValue({
        count: 0,
        has_more: false,
        oldest_id: null,
        oldest_ts: null,
        empty: true,
        messages: [],
      }),
    };
    const app = await makeApp(makeManager(instance));
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-abc/history',
      payload: validBody,
    });
    expect(res.statusCode).toBe(200);
    expect(res.json()).toMatchObject({ empty: true, has_more: false, count: 0 });
    await app.close();
  });

  it('409 quando instance não está conectada', async () => {
    const instance: MockInstance = {
      fetchHistory: vi
        .fn()
        .mockRejectedValue(new Error('Instance ch-abc not connected (state=disconnected)')),
    };
    const app = await makeApp(makeManager(instance));
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-abc/history',
      payload: validBody,
    });
    expect(res.statusCode).toBe(409);
    expect(res.json()).toMatchObject({ error: 'instance_not_connected' });
    await app.close();
  });

  it('500 em erro genérico do socket', async () => {
    const instance: MockInstance = {
      fetchHistory: vi.fn().mockRejectedValue(new Error('Boom: HMAC mismatch')),
    };
    const app = await makeApp(makeManager(instance));
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-abc/history',
      payload: validBody,
    });
    expect(res.statusCode).toBe(500);
    expect(res.json()).toMatchObject({ error: 'fetch_history_failed' });
    await app.close();
  });

  it('count cap respeitado — Zod rejeita count > 100', async () => {
    const instance: MockInstance = { fetchHistory: vi.fn() };
    const app = await makeApp(makeManager(instance));
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-abc/history',
      payload: { ...validBody, count: 200 },
    });
    // Zod schema cap em 100 — 200 vira 422 (validation_error)
    expect(res.statusCode).toBe(422);
    expect(instance.fetchHistory).not.toHaveBeenCalled();
    await app.close();
  });
});
