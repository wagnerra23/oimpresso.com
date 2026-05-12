import { beforeEach, describe, expect, it, vi } from 'vitest';
import Fastify, { type FastifyInstance } from 'fastify';
import { messageRoutes } from './messages';
import errorHandlerPlugin from '../plugins/errorHandler';
import type { InstanceManager } from '../../baileys/InstanceManager';

// ------------------------------------------------------------------------------------------------
// US-WA-045/046 — vitest spec da rota POST /instances/:id/interactive
//
// Mocka Instance.sendInteractive pra exercitar contrato Zod + status codes,
// sem socket Baileys real.
// ------------------------------------------------------------------------------------------------

interface MockInstance {
  sendInteractive: ReturnType<typeof vi.fn>;
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

const buttonsBody = {
  to: '5548999872822',
  body: 'Sua OS está pronta. Confirmar?',
  interactive: {
    type: 'buttons',
    buttons: [
      { id: 'sim', label: 'Sim' },
      { id: 'nao', label: 'Não' },
    ],
  },
};

const listBody = {
  to: '5548999872822',
  body: 'Escolha um tamanho:',
  interactive: {
    type: 'list',
    button_label: 'Tamanhos',
    sections: [
      {
        title: 'Linha vestido',
        items: [
          { id: 'p', title: 'P' },
          { id: 'm', title: 'M', description: '40-42' },
          { id: 'g', title: 'G' },
        ],
      },
    ],
  },
};

describe('POST /instances/:id/interactive — Zod schema + handler', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('200 — buttons válido chama Instance.sendInteractive', async () => {
    const instance: MockInstance = {
      sendInteractive: vi.fn().mockResolvedValue({ message_id: 'BAE5BTN', status: 'sent' }),
    };
    const app = await makeApp(makeManager(instance));
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-abc/interactive',
      payload: buttonsBody,
    });
    expect(res.statusCode).toBe(200);
    expect(res.json()).toMatchObject({ message_id: 'BAE5BTN', status: 'sent' });
    expect(instance.sendInteractive).toHaveBeenCalledOnce();
    expect(instance.sendInteractive).toHaveBeenCalledWith(
      expect.objectContaining({
        to: '5548999872822',
        body: expect.any(String),
        interactive: expect.objectContaining({ type: 'buttons' }),
      }),
    );
    await app.close();
  });

  it('200 — list válido (sections + items com description) chama Instance.sendInteractive', async () => {
    const instance: MockInstance = {
      sendInteractive: vi.fn().mockResolvedValue({ message_id: 'BAE5LST', status: 'sent' }),
    };
    const app = await makeApp(makeManager(instance));
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-abc/interactive',
      payload: listBody,
    });
    expect(res.statusCode).toBe(200);
    expect(instance.sendInteractive).toHaveBeenCalledOnce();
    await app.close();
  });

  it('422 — body inválido (4 botões — excede limite 3) devolve validation_error', async () => {
    const instance: MockInstance = { sendInteractive: vi.fn() };
    const app = await makeApp(makeManager(instance));
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-abc/interactive',
      payload: {
        to: '5548999872822',
        body: 'Pergunta?',
        interactive: {
          type: 'buttons',
          buttons: [
            { id: 'a', label: 'A' },
            { id: 'b', label: 'B' },
            { id: 'c', label: 'C' },
            { id: 'd', label: 'D' },
          ],
        },
      },
    });
    expect(res.statusCode).toBe(422);
    expect(res.json()).toMatchObject({ error: 'validation_error' });
    expect(instance.sendInteractive).not.toHaveBeenCalled();
    await app.close();
  });

  it('422 — type=cta_url (Meta Cloud-only) é rejeitado pela discriminated union', async () => {
    const instance: MockInstance = { sendInteractive: vi.fn() };
    const app = await makeApp(makeManager(instance));
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-abc/interactive',
      payload: {
        to: '5548999872822',
        body: 'Pague aqui:',
        interactive: {
          type: 'cta_url',
          button_label: 'Pagar',
          url: 'https://pay.example.com/x',
        },
      },
    });
    expect(res.statusCode).toBe(422);
    expect(instance.sendInteractive).not.toHaveBeenCalled();
    await app.close();
  });

  it('404 — instance_id não existe no manager', async () => {
    const app = await makeApp(makeManager(null));
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-deadbeef/interactive',
      payload: buttonsBody,
    });
    expect(res.statusCode).toBe(404);
    expect(res.json()).toMatchObject({ error: 'instance_not_found' });
    await app.close();
  });

  it('409 — instance not connected (sessionLost no PHP)', async () => {
    const instance: MockInstance = {
      sendInteractive: vi.fn().mockRejectedValue(new Error('Instance ch-abc not connected (state=disconnected)')),
    };
    const app = await makeApp(makeManager(instance));
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-abc/interactive',
      payload: buttonsBody,
    });
    expect(res.statusCode).toBe(409);
    expect(res.json()).toMatchObject({ error: 'instance_not_connected' });
    await app.close();
  });

  it('422 — list sem sections', async () => {
    const instance: MockInstance = { sendInteractive: vi.fn() };
    const app = await makeApp(makeManager(instance));
    const res = await app.inject({
      method: 'POST',
      url: '/instances/ch-abc/interactive',
      payload: {
        to: '5548999872822',
        body: 'Vazio',
        interactive: { type: 'list', button_label: 'X', sections: [] },
      },
    });
    expect(res.statusCode).toBe(422);
    await app.close();
  });
});
