import { describe, expect, it } from 'vitest';
import { sendMediaBody } from './schemas.js';

// ------------------------------------------------------------------------------------------------
// P0 #2 (2026-05-12) — Zod normalization de `mime` vs `mimetype` no sendMediaBody.
//
// Bug: `SendMediaJob.php` (Laravel/Hostinger) historicamente enviava chave
// `mime` enquanto o schema esperava `mimetype`. Zod strippava `mime` →
// `mimetype` ficava undefined → áudio/doc outbound chegava sem MIME no
// WhatsApp. Schema agora aceita ambas e normaliza pra `mimetype`.
//
// Janela de migração: 30d (até 2026-06-12). Depois remover suporte a `mime`.
// ------------------------------------------------------------------------------------------------

const baseBody = {
  to: '5548999872822',
  media_url: 'https://oimpresso.com/storage/whatsapp/1/2026-05/foo.ogg',
  type: 'audio' as const,
};

describe('sendMediaBody — normalização mime → mimetype (P0 #2)', () => {
  it('aceita `mimetype` canônico (caminho moderno)', () => {
    const parsed = sendMediaBody.parse({
      ...baseBody,
      mimetype: 'audio/ogg',
    });
    expect(parsed.mimetype).toBe('audio/ogg');
    // chave legacy `mime` não persiste após transform
    expect((parsed as Record<string, unknown>).mime).toBeUndefined();
  });

  it('aceita `mime` legacy e normaliza pra `mimetype` (compat janela 30d)', () => {
    const parsed = sendMediaBody.parse({
      ...baseBody,
      mime: 'audio/ogg',
    });
    expect(parsed.mimetype).toBe('audio/ogg');
    expect((parsed as Record<string, unknown>).mime).toBeUndefined();
  });

  it('quando ambos presentes, `mimetype` tem precedência (canônico ganha)', () => {
    const parsed = sendMediaBody.parse({
      ...baseBody,
      mime: 'audio/legacy',
      mimetype: 'audio/canonico',
    });
    expect(parsed.mimetype).toBe('audio/canonico');
  });

  it('ausência de ambos é válida (campo opcional)', () => {
    const parsed = sendMediaBody.parse(baseBody);
    expect(parsed.mimetype).toBeUndefined();
  });

  it('preserva demais campos após transform (to, media_url, type, caption, filename)', () => {
    const parsed = sendMediaBody.parse({
      ...baseBody,
      type: 'document',
      caption: 'Contrato',
      filename: 'contrato.pdf',
      mime: 'application/pdf',
    });
    expect(parsed.to).toBe('5548999872822');
    expect(parsed.media_url).toBe(baseBody.media_url);
    expect(parsed.type).toBe('document');
    expect(parsed.caption).toBe('Contrato');
    expect(parsed.filename).toBe('contrato.pdf');
    expect(parsed.mimetype).toBe('application/pdf');
  });

  it('rejeita type inválido', () => {
    expect(() =>
      sendMediaBody.parse({
        ...baseBody,
        type: 'pdf' as unknown as 'document',
      }),
    ).toThrow();
  });

  it('rejeita media_url não-URL', () => {
    expect(() =>
      sendMediaBody.parse({
        ...baseBody,
        media_url: 'not-a-url',
      }),
    ).toThrow();
  });
});
