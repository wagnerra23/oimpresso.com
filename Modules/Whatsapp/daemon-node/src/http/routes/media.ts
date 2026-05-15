import { createHash } from 'node:crypto';
import type { FastifyPluginAsync } from 'fastify';
import { downloadContentFromMessage } from '@whiskeysockets/baileys';
import { ZodError } from 'zod';
import { decryptUrlBody, type DecryptUrlBody } from '../schemas.js';
import {
  mediaDecryptCounter,
  mediaDecryptLatencyHistogram,
} from '../../observability/metrics.js';

// Rate limit grosseiro in-memory (sliding window 60s). Daemon roda single-instance no CT 100,
// então não precisa Redis. Plugin @fastify/rate-limit não está nas deps; evitar bump.
const RATE_LIMIT_PER_MIN = 100;
const rateWindow: number[] = [];

function rateLimitCheck(now: number): boolean {
  const cutoff = now - 60_000;
  while (rateWindow.length && rateWindow[0]! < cutoff) {
    rateWindow.shift();
  }
  if (rateWindow.length >= RATE_LIMIT_PER_MIN) return false;
  rateWindow.push(now);
  return true;
}

function hashUrl(url: string): string {
  return createHash('sha256').update(url).digest('hex').slice(0, 16);
}

/**
 * POST /media/decrypt-url
 *
 * Decrypta uma URL `.enc` Baileys inbound usando `mediaKey` + `type`. Stateless:
 * não depende de nenhuma instance conectada (usa só SDK estático).
 *
 * Body: { url, mediaKey (b64), mimetype, fileSha256?, fileLength?, type }
 * Response: 200 application/octet-stream (raw bytes decryptados)
 * Errors:
 *   - 400 invalid_body (Zod parse falhou)
 *   - 422 decrypt_failed (mediaKey errada, payload corrompido)
 *   - 429 rate_limited
 *   - 502 cdn_unreachable (Baileys CDN inalcançável)
 */
export const mediaRoutes: FastifyPluginAsync = async (app) => {
  app.post('/decrypt-url', async (req, reply) => {
    const now = Date.now();
    if (!rateLimitCheck(now)) {
      mediaDecryptCounter.inc({ status: 'rate_limited', type: 'unknown' });
      return reply.code(429).send({
        error: 'rate_limited',
        message: `max ${RATE_LIMIT_PER_MIN} decrypts/min globais`,
      });
    }

    // Parse manual pra distinguir 400 (body inválido) de 422 (decrypt falhou).
    // ErrorHandler global devolveria 422 pra ZodError — aqui queremos 400.
    let body: DecryptUrlBody;
    try {
      body = decryptUrlBody.parse(req.body);
    } catch (err) {
      mediaDecryptCounter.inc({ status: 'invalid_body', type: 'unknown' });
      if (err instanceof ZodError) {
        return reply.code(400).send({
          error: 'invalid_body',
          message: 'request body invalid',
          details: err.flatten(),
        });
      }
      throw err;
    }

    const urlHash = hashUrl(body.url);
    const start = Date.now();

    try {
      // `downloadContentFromMessage` aceita um objeto compatível com
      // proto.Message.IImageMessage/IAudioMessage/etc. — basta ter `url` (ou
      // `directPath`) + `mediaKey` (Buffer). Retorna AsyncIterable<Buffer>.
      const mediaKeyBuf = Buffer.from(body.mediaKey, 'base64');

      const messageContent = {
        url: body.url,
        mediaKey: mediaKeyBuf,
        ...(body.fileSha256 ? { fileSha256: Buffer.from(body.fileSha256, 'base64') } : {}),
        ...(body.fileLength !== undefined ? { fileLength: body.fileLength } : {}),
        mimetype: body.mimetype,
      };

      // Tipagem do Baileys exige um shape específico de proto.Message por tipo.
      // Cast `as never` é seguro aqui — downloadContentFromMessage só usa
      // url + mediaKey + (opcional) fileSha256/fileLength em runtime.
      const stream = await downloadContentFromMessage(
        messageContent as never,
        body.type,
      );

      // Materializar pra Buffer pra poder medir bytes e enviar via reply.send().
      // Mídias WhatsApp são geralmente <16MB (limite do app). Fastify bodyLimit
      // não se aplica a respostas — risco de OOM controlado.
      const chunks: Buffer[] = [];
      let totalBytes = 0;
      for await (const chunk of stream) {
        chunks.push(chunk);
        totalBytes += chunk.length;
      }
      const buffer = Buffer.concat(chunks, totalBytes);

      const elapsed = Date.now() - start;
      mediaDecryptCounter.inc({ status: 'ok', type: body.type });
      mediaDecryptLatencyHistogram.observe({ type: body.type }, elapsed);

      // Log estruturado SEM mediaKey (sensível) nem URL completa (rastro privado).
      req.log.info(
        { url_hash: urlHash, type: body.type, bytes: totalBytes, decrypt_ms: elapsed },
        'media decrypted',
      );

      reply
        .header('content-type', body.mimetype || 'application/octet-stream')
        .header('content-length', String(totalBytes))
        .header('x-decrypt-bytes', String(totalBytes))
        .header('x-decrypt-ms', String(elapsed));
      return reply.send(buffer);
    } catch (err) {
      const elapsed = Date.now() - start;
      const message = err instanceof Error ? err.message : String(err);
      const isCdnError =
        /ENOTFOUND|ECONNREFUSED|ETIMEDOUT|EAI_AGAIN|fetch failed|network/i.test(message);

      if (isCdnError) {
        mediaDecryptCounter.inc({ status: 'cdn_unreachable', type: body.type });
        req.log.warn(
          { url_hash: urlHash, type: body.type, decrypt_ms: elapsed, err: message },
          'media decrypt cdn unreachable',
        );
        return reply.code(502).send({
          error: 'cdn_unreachable',
          message: 'baileys cdn unreachable',
        });
      }

      mediaDecryptCounter.inc({ status: 'decrypt_failed', type: body.type });
      req.log.warn(
        { url_hash: urlHash, type: body.type, decrypt_ms: elapsed, err: message },
        'media decrypt failed',
      );
      return reply.code(422).send({
        error: 'decrypt_failed',
        message: 'unable to decrypt media (wrong mediaKey or corrupted payload)',
      });
    }
  });
};

// Exporta helper só pra teste — força reset do rate-limit window.
export const __test_resetRateLimit = (): void => {
  rateWindow.length = 0;
};
