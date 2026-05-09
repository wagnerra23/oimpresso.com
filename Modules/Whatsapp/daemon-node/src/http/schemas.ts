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

export type ConnectBody = z.infer<typeof connectBody>;
export type SendTextBody = z.infer<typeof sendTextBody>;
export type SendMediaBody = z.infer<typeof sendMediaBody>;
