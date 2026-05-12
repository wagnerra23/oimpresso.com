import { z } from 'zod';

export const instanceIdParam = z.object({
  id: z.string().regex(/^[A-Za-z0-9_-]{1,64}$/),
});

export const connectBody = z.object({
  business_uuid: z.string().uuid(),
  business_id: z.number().int().positive().optional(),
});

const phoneOrJid = z
  .string()
  .min(8)
  .max(64)
  .refine((v) => /^[0-9+]+$/.test(v) || v.includes('@'), {
    message: 'must be a phone (digits/+) or a JID (...@s.whatsapp.net)',
  });

export const sendTextBody = z.object({
  to: phoneOrJid,
  text: z.string().min(1).max(4096),
});

export const sendMediaBody = z.object({
  to: phoneOrJid,
  media_url: z.string().url(),
  type: z.enum(['image', 'document', 'audio', 'video']),
  caption: z.string().max(1024).optional(),
  filename: z.string().max(128).optional(),
  mimetype: z.string().max(128).optional(),
});

// ------------------------------------------------------------------------------------------------
// Decrypt de mídia inbound Baileys
//
// WhatsApp entrega mídias recebidas como URL `.enc` (AES-256-CBC + HKDF derivada de `mediaKey`).
// PHP no Hostinger não tem stack pra fazer o decrypt — encaminhamos pro daemon que usa o próprio
// SDK Baileys pra resolver. Stateless: não precisa instance conectada.
// ------------------------------------------------------------------------------------------------
export const decryptUrlBody = z.object({
  url: z.string().url(),
  mediaKey: z.string().min(32), // base64, mediaKey de 32 bytes vira ~44 chars em b64
  mimetype: z.string().max(128),
  fileSha256: z.string().optional(),
  fileLength: z.number().int().positive().optional(),
  type: z.enum(['image', 'audio', 'video', 'document', 'sticker']),
});

// ------------------------------------------------------------------------------------------------
// Import histórico Baileys (US-WA-080)
//
// Endpoint POST /instances/:id/history puxa mensagens antigas via
// `socket.fetchMessageHistory(count, oldestMsgKey, oldestMsgTimestamp)` da
// API Baileys 6.7.9. WhatsApp entrega o batch async via evento
// `messaging-history.set` correlacionado por `peerDataRequestSessionId`.
//
// Caller (PHP `whatsapp:import-history`) gerencia cursor + paginação +
// rate limit. Daemon responde 1 batch por chamada (no máx ~100 msgs).
// ------------------------------------------------------------------------------------------------
export const fetchHistoryBody = z.object({
  jid: z.string().min(8).max(64),
  count: z.number().int().min(1).max(100).default(50),
  before_id: z.string().min(1).max(128),
  before_ts: z.number().int().positive(), // unix ts em segundos
  from_me: z.boolean().optional().default(false),
  // Timeout pra esperar `messaging-history.set` (default 60s WhatsApp pode
  // demorar — não confundir com timeout HTTP do caller PHP).
  timeout_ms: z.number().int().min(5_000).max(120_000).optional().default(60_000),
});

export type ConnectBody = z.infer<typeof connectBody>;
export type SendTextBody = z.infer<typeof sendTextBody>;
export type SendMediaBody = z.infer<typeof sendMediaBody>;
export type DecryptUrlBody = z.infer<typeof decryptUrlBody>;
export type FetchHistoryBody = z.infer<typeof fetchHistoryBody>;
