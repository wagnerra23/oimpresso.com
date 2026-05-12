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

// ------------------------------------------------------------------------------------------------
// Send media outbound — POST /instances/:id/media
//
// Schema canônico usa `mimetype` (alinhado com Baileys SDK + Hotfix B1 webhook).
//
// LEGACY KEY `mime` — `SendMediaJob.php` (Laravel/Hostinger) historicamente
// envia a chave `mime`. Pra evitar regressão silenciosa (Zod por padrão faz
// strip de chaves desconhecidas → `mimetype` ficava undefined → áudio outbound
// chegava no WhatsApp sem MIME), aceitamos ambas no preflight e normalizamos
// pra `mimetype` via `transform`. `SendMediaJob.php` foi migrado pra `mimetype`
// no mesmo PR — janela de migração 30 dias terminando 2026-06-12, depois disso
// remover suporte a `mime`.
// ------------------------------------------------------------------------------------------------
export const sendMediaBody = z
  .object({
    to: phoneOrJid,
    media_url: z.string().url(),
    type: z.enum(['image', 'document', 'audio', 'video']),
    caption: z.string().max(1024).optional(),
    filename: z.string().max(128).optional(),
    mimetype: z.string().max(128).optional(),
    // `mime` deprecated. Migration window 30d ending 2026-06-12.
    mime: z.string().max(128).optional(),
  })
  .transform(({ mime, mimetype, ...rest }) => ({
    ...rest,
    mimetype: mimetype ?? mime,
  }));

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

// ------------------------------------------------------------------------------------------------
// Mensagens interativas — POST /instances/:id/interactive (US-WA-045/046)
//
// Baileys 6.7 suporta nativamente buttons (até 3) e list messages. CTA URL é
// Meta Cloud-only — daemon não aceita (PHP `BaileysDriver::sendInteractive`
// já lança DriverDoesNotSupport antes de chegar aqui, mas mantemos a guarda
// no Zod schema também).
// ------------------------------------------------------------------------------------------------
const interactiveButton = z.object({
  id: z.string().min(1).max(64),
  label: z.string().min(1).max(20),
});

const interactiveListItem = z.object({
  id: z.string().min(1).max(64),
  title: z.string().min(1).max(24),
  description: z.string().max(72).optional(),
});

const interactiveListSection = z.object({
  title: z.string().min(1).max(24),
  items: z.array(interactiveListItem).min(1).max(10),
});

export const sendInteractiveBody = z.object({
  to: phoneOrJid,
  body: z.string().min(1).max(1024),
  interactive: z.discriminatedUnion('type', [
    z.object({
      type: z.literal('buttons'),
      buttons: z.array(interactiveButton).min(1).max(3),
    }),
    z.object({
      type: z.literal('list'),
      button_label: z.string().min(1).max(20),
      sections: z.array(interactiveListSection).min(1).max(10),
    }),
  ]),
});

export type ConnectBody = z.infer<typeof connectBody>;
export type SendTextBody = z.infer<typeof sendTextBody>;
export type SendMediaBody = z.infer<typeof sendMediaBody>;
export type SendInteractiveBody = z.infer<typeof sendInteractiveBody>;
export type DecryptUrlBody = z.infer<typeof decryptUrlBody>;
export type FetchHistoryBody = z.infer<typeof fetchHistoryBody>;
