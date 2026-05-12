import { collectDefaultMetrics, Counter, Gauge, Histogram, Registry } from 'prom-client';

export const registry = new Registry();
registry.setDefaultLabels({ app: 'whatsapp-baileys-daemon' });
collectDefaultMetrics({ register: registry, prefix: 'whatsapp_baileys_' });

export const sessionStateGauge = new Gauge({
  name: 'whatsapp_baileys_session_state',
  help: '1 = connected, 0.5 = qr_required, 0 = disconnected/banned',
  labelNames: ['instance_id', 'business_id'] as const,
  registers: [registry],
});

export const sessionAgeGauge = new Gauge({
  name: 'whatsapp_baileys_session_age_seconds',
  help: 'Idade da sessão Whatsapp Web atual.',
  labelNames: ['instance_id'] as const,
  registers: [registry],
});

export const messageLagHistogram = new Histogram({
  name: 'whatsapp_baileys_message_lag_ms',
  help: 'Tempo daemon → Whatsapp Web → ack em ms.',
  labelNames: ['instance_id'] as const,
  buckets: [50, 100, 250, 500, 1_000, 2_500, 5_000, 10_000, 30_000],
  registers: [registry],
});

export const sendCounter = new Counter({
  name: 'whatsapp_baileys_send_total',
  help: 'Mensagens outbound: status sent | failed | banned.',
  labelNames: ['instance_id', 'status', 'kind'] as const,
  registers: [registry],
});

export const recvCounter = new Counter({
  name: 'whatsapp_baileys_recv_total',
  help: 'Mensagens inbound recebidas pelo daemon.',
  labelNames: ['instance_id'] as const,
  registers: [registry],
});

export const banDetectedCounter = new Counter({
  name: 'whatsapp_baileys_ban_detected_total',
  help: 'Bans Meta detectados (cross-tenant alarm).',
  labelNames: ['instance_id'] as const,
  registers: [registry],
});

export const webhookDispatchCounter = new Counter({
  name: 'whatsapp_baileys_webhook_dispatch_total',
  help: 'Webhook outbound pro Hostinger: outcome ok | retried | failed_permanent.',
  labelNames: ['event', 'outcome'] as const,
  registers: [registry],
});

export const webhookLatencyHistogram = new Histogram({
  name: 'whatsapp_baileys_webhook_latency_ms',
  help: 'Latência POST webhook outbound.',
  labelNames: ['event'] as const,
  buckets: [50, 100, 250, 500, 1_000, 2_500, 5_000, 10_000, 30_000],
  registers: [registry],
});

export const mediaDecryptCounter = new Counter({
  name: 'whatsapp_baileys_media_decrypt_total',
  help: 'Decrypt de URL Baileys inbound: status ok | invalid_body | decrypt_failed | cdn_unreachable.',
  labelNames: ['status', 'type'] as const,
  registers: [registry],
});

export const mediaDecryptLatencyHistogram = new Histogram({
  name: 'whatsapp_baileys_media_decrypt_latency_ms',
  help: 'Tempo de decrypt de mídia inbound (download CDN + AES).',
  labelNames: ['type'] as const,
  buckets: [50, 100, 250, 500, 1_000, 2_500, 5_000, 10_000, 30_000],
  registers: [registry],
});
