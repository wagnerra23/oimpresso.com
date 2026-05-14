import { setTimeout as sleep } from 'node:timers/promises';
import { request } from 'undici';
import type { Logger } from 'pino';
import type { Env } from '../config/env';
import { webhookDispatchCounter, webhookLatencyHistogram } from '../observability/metrics';

export type WebhookEvent =
  | 'message'
  | 'message_status'
  | 'session_lost'
  | 'ban_detected'
  | 'qr_updated'
  | 'connected'
  | 'disconnected'
  | 'history.sync';

export interface WebhookPayload {
  instance_id: string;
  business_uuid: string;
  event: WebhookEvent;
  data: Record<string, unknown>;
  ts: string;
}

// 404 INCLUÍDO no retryable (fix 2026-05-13): em burst de webhooks pra Hostinger
// PHP-FPM saturado, retorno errôneo 404 disfarçando rate-limit transitório.
// Trade-off: se channel realmente não existe (uuid errado), retry 5x polui log,
// mas custo é só log. Sem retry, msgs históricas se perdem definitivo.
const RETRYABLE_STATUS = new Set([404, 408, 425, 429, 500, 502, 503, 504]);

export class WebhookDispatcher {
  constructor(
    private readonly env: Env,
    private readonly logger: Logger,
  ) {}

  async dispatch(payload: Omit<WebhookPayload, 'ts'>): Promise<void> {
    const fullPayload: WebhookPayload = { ...payload, ts: new Date().toISOString() };
    const url = `${this.env.WEBHOOK_BASE_URL.replace(/\/+$/, '')}/${encodeURIComponent(payload.business_uuid)}`;

    let lastError: unknown;
    for (let attempt = 1; attempt <= this.env.WEBHOOK_MAX_RETRIES; attempt++) {
      const start = Date.now();
      try {
        const { statusCode, body } = await request(url, {
          method: 'POST',
          headers: {
            'content-type': 'application/json',
            authorization: `Bearer ${this.env.API_KEY}`,
            'user-agent': 'whatsapp-baileys-daemon/0.1',
            'x-baileys-event': payload.event,
            'x-baileys-instance': payload.instance_id,
          },
          body: JSON.stringify(fullPayload),
          bodyTimeout: this.env.WEBHOOK_TIMEOUT_MS,
          headersTimeout: this.env.WEBHOOK_TIMEOUT_MS,
        });

        webhookLatencyHistogram.observe({ event: payload.event }, Date.now() - start);
        await body.dump();

        if (statusCode >= 200 && statusCode < 300) {
          webhookDispatchCounter.inc({ event: payload.event, outcome: attempt === 1 ? 'ok' : 'retried' });
          return;
        }

        if (!RETRYABLE_STATUS.has(statusCode) && statusCode < 500) {
          webhookDispatchCounter.inc({ event: payload.event, outcome: 'failed_permanent' });
          this.logger.warn(
            { statusCode, attempt, instance_id: payload.instance_id, event: payload.event },
            'webhook permanent failure',
          );
          return;
        }

        lastError = new Error(`HTTP ${statusCode}`);
      } catch (err) {
        lastError = err;
        webhookLatencyHistogram.observe({ event: payload.event }, Date.now() - start);
      }

      if (attempt < this.env.WEBHOOK_MAX_RETRIES) {
        const backoff = this.env.WEBHOOK_BACKOFF_BASE_MS * 3 ** (attempt - 1);
        const jitter = Math.floor(Math.random() * 200);
        await sleep(backoff + jitter);
      }
    }

    webhookDispatchCounter.inc({ event: payload.event, outcome: 'failed_permanent' });
    this.logger.error(
      { err: lastError, attempts: this.env.WEBHOOK_MAX_RETRIES, event: payload.event, instance_id: payload.instance_id },
      'webhook dispatch falhou após todas as tentativas',
    );
  }
}
