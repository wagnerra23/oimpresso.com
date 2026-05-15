import { createHmac, randomUUID } from 'node:crypto';
import { setTimeout as sleep } from 'node:timers/promises';
import { request } from 'undici';
import { context, propagation, SpanKind, SpanStatusCode, trace } from '@opentelemetry/api';
import type { Logger } from 'pino';
import type { Env } from '../config/env.js';
import { webhookDispatchCounter, webhookLatencyHistogram } from '../observability/metrics.js';

// US-WA-083 — OTel tracing distribuído daemon ↔ Hostinger
const tracer = trace.getTracer('whatsapp-baileys-daemon-webhook', '1.0.0');

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
    // US-WA-083 — span pai cobre dispatch inteiro (todos os retries).
    // Hostinger Laravel extrai `traceparent` via PropagateTraceparent middleware
    // e correlaciona logs com este span (Tempo/Jaeger se OTel ativo).
    const span = tracer.startSpan('webhook.dispatch', {
      kind: SpanKind.CLIENT,
      attributes: {
        'whatsapp.event': payload.event,
        'whatsapp.instance_id': payload.instance_id,
        'whatsapp.business_uuid': payload.business_uuid,
      },
    });

    try {
      await context.with(trace.setSpan(context.active(), span), async () => {
        await this.dispatchWithSpan(payload, span);
      });
      span.setStatus({ code: SpanStatusCode.OK });
    } catch (err) {
      span.recordException(err instanceof Error ? err : new Error(String(err)));
      span.setStatus({ code: SpanStatusCode.ERROR });
      throw err;
    } finally {
      span.end();
    }
  }

  private async dispatchWithSpan(payload: Omit<WebhookPayload, 'ts'>, span: ReturnType<typeof tracer.startSpan>): Promise<void> {
    const fullPayload: WebhookPayload = { ...payload, ts: new Date().toISOString() };
    const url = `${this.env.WEBHOOK_BASE_URL.replace(/\/+$/, '')}/${encodeURIComponent(payload.business_uuid)}`;
    const bodyJson = JSON.stringify(fullPayload);

    // W3C Trace Context: injeta `traceparent` (+ `tracestate`) header pro Laravel
    // continuar o trace. Idempotente entre retries — Hostinger só correlaciona
    // o 1º arrival válido (nonce já garante dedup).
    const traceHeaders: Record<string, string> = {};
    propagation.inject(context.active(), traceHeaders);

    // US-WA-082 — Replay protection HMAC + nonce.
    // Hostinger middleware `VerifyBaileysWebhookHmac` valida:
    //   1. ts ≤5min skew (replay window)
    //   2. HMAC-SHA256(API_KEY, ts.nonce.body) constant-time compare
    //   3. nonce não-visto (INSERT IGNORE em webhook_nonces table)
    // Headers gerados UMA vez fora do retry loop — mesmo nonce em retries
    // pra Hostinger considerar replay (correto: retry deve ser idempotente
    // via dedup, não criar novo "first arrival").
    const nonce = randomUUID();
    const tsEpoch = Math.floor(Date.now() / 1000).toString();
    const signedPayload = `${tsEpoch}.${nonce}.${bodyJson}`;
    const signature = createHmac('sha256', this.env.API_KEY).update(signedPayload).digest('hex');

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
            'x-baileys-nonce': nonce,
            'x-baileys-ts': tsEpoch,
            'x-baileys-signature': signature,
            ...traceHeaders, // US-WA-083 traceparent (+tracestate)
          },
          body: bodyJson,
          bodyTimeout: this.env.WEBHOOK_TIMEOUT_MS,
          headersTimeout: this.env.WEBHOOK_TIMEOUT_MS,
        });

        span.setAttribute('http.status_code', statusCode);
        span.setAttribute('http.attempt', attempt);

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
