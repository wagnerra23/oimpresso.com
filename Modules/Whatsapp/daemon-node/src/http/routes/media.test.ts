import { beforeEach, describe, expect, it, vi } from 'vitest';
import Fastify, { type FastifyInstance } from 'fastify';

// Mockar Baileys ANTES do import da rota.
vi.mock('@whiskeysockets/baileys', () => ({
  downloadContentFromMessage: vi.fn(),
}));

import { downloadContentFromMessage } from '@whiskeysockets/baileys';
import { mediaRoutes, __test_resetRateLimit } from './media';

const mockedDownload = vi.mocked(downloadContentFromMessage);

async function makeApp(): Promise<FastifyInstance> {
  const app = Fastify({ logger: false });
  await app.register(mediaRoutes, { prefix: '/media' });
  return app;
}

async function* yieldChunks(chunks: Buffer[]): AsyncIterable<Buffer> {
  for (const c of chunks) yield c;
}

const validBody = {
  url: 'https://mmg.whatsapp.net/v/t62.7117-24/abc.enc?token=xyz',
  mediaKey: Buffer.from('a'.repeat(32)).toString('base64'),
  mimetype: 'audio/ogg; codecs=opus',
  type: 'audio' as const,
};

describe('POST /media/decrypt-url', () => {
  beforeEach(() => {
    mockedDownload.mockReset();
    __test_resetRateLimit();
  });

  it('200 — retorna bytes decryptados em application/octet-stream', async () => {
    const fakeBytes = Buffer.from([0x00, 0x01, 0x02, 0x03]);
    mockedDownload.mockResolvedValueOnce(yieldChunks([fakeBytes]) as never);

    const app = await makeApp();
    const res = await app.inject({
      method: 'POST',
      url: '/media/decrypt-url',
      payload: validBody,
    });

    expect(res.statusCode).toBe(200);
    expect(res.headers['content-type']).toContain('audio/ogg');
    expect(res.headers['x-decrypt-bytes']).toBe('4');
    expect(res.rawPayload).toEqual(fakeBytes);
    expect(mockedDownload).toHaveBeenCalledTimes(1);
    await app.close();
  });

  it('400 — body inválido (mediaKey muito curta) devolve invalid_body', async () => {
    const app = await makeApp();
    const res = await app.inject({
      method: 'POST',
      url: '/media/decrypt-url',
      payload: { ...validBody, mediaKey: 'short' },
    });
    expect(res.statusCode).toBe(400);
    expect(res.json()).toMatchObject({ error: 'invalid_body' });
    expect(mockedDownload).not.toHaveBeenCalled();
    await app.close();
  });

  it('400 — type ausente devolve invalid_body', async () => {
    const app = await makeApp();
    const { type: _omit, ...noType } = validBody;
    const res = await app.inject({
      method: 'POST',
      url: '/media/decrypt-url',
      payload: noType,
    });
    expect(res.statusCode).toBe(400);
    expect(res.json()).toMatchObject({ error: 'invalid_body' });
    await app.close();
  });

  it('422 — quando downloadContentFromMessage lança devolve decrypt_failed', async () => {
    mockedDownload.mockRejectedValueOnce(new Error('Invalid mediaKey: HMAC mismatch'));

    const app = await makeApp();
    const res = await app.inject({
      method: 'POST',
      url: '/media/decrypt-url',
      payload: validBody,
    });

    expect(res.statusCode).toBe(422);
    expect(res.json()).toMatchObject({ error: 'decrypt_failed' });
    await app.close();
  });

  it('502 — quando erro indica CDN unreachable devolve cdn_unreachable', async () => {
    mockedDownload.mockRejectedValueOnce(new Error('fetch failed: ENOTFOUND mmg.whatsapp.net'));

    const app = await makeApp();
    const res = await app.inject({
      method: 'POST',
      url: '/media/decrypt-url',
      payload: validBody,
    });

    expect(res.statusCode).toBe(502);
    expect(res.json()).toMatchObject({ error: 'cdn_unreachable' });
    await app.close();
  });

  it('NÃO loga mediaKey nos campos retornados', async () => {
    const fakeBytes = Buffer.from([0xff]);
    mockedDownload.mockResolvedValueOnce(yieldChunks([fakeBytes]) as never);

    const app = await makeApp();
    const res = await app.inject({
      method: 'POST',
      url: '/media/decrypt-url',
      payload: validBody,
    });

    expect(res.statusCode).toBe(200);
    // Headers não devem conter mediaKey
    for (const [k, v] of Object.entries(res.headers)) {
      expect(String(v)).not.toContain(validBody.mediaKey);
      expect(k.toLowerCase()).not.toContain('mediakey');
    }
    await app.close();
  });
});
