import { describe, expect, it, vi } from 'vitest';

/**
 * Regression test pro fix history sync (bug Wagner 2026-05-13 "mensagens não vêm").
 *
 * Garante:
 *   1. WebhookEvent type aceita 'history.sync' (TS compile + runtime)
 *   2. Configuração canônica Baileys 2026: syncFullHistory true + callback +
 *      Browsers Desktop está presente no código (test estrutural)
 *   3. Chunking 100 msgs/batch funciona (anti-overflow webhook body 2MB)
 *
 * NÃO testa: integração end-to-end Baileys real (requer mock muito pesado
 * do makeWASocket). Cobertura E2E vai pra smoke test biz=164 (Martinho)
 * em prod após PR-B deploy.
 */

describe('history sync handler — fix bug "mensagens não vêm" (2026-05-13)', () => {
  it('R-HS-001 — WebhookEvent type inclui history.sync', async () => {
    // Smoke: arquivo WebhookDispatcher.ts deve exportar a string como
    // membro do union type WebhookEvent. Se removido, TS compile quebra
    // em Instance.ts ao chamar webhook.dispatch({event: 'history.sync'}).
    const dispatcherSource = await import('node:fs').then((fs) =>
      fs.readFileSync(
        new URL('../webhook/WebhookDispatcher.ts', import.meta.url),
        'utf8'
      )
    );
    expect(dispatcherSource).toContain("'history.sync'");
    expect(dispatcherSource).toMatch(/export type WebhookEvent[\s\S]+'history\.sync'/);
  });

  it('R-HS-002 — Instance.ts configura syncFullHistory:true + Desktop browser (Baileys #11951)', async () => {
    const source = await import('node:fs').then((fs) =>
      fs.readFileSync(new URL('./Instance.ts', import.meta.url), 'utf8')
    );
    expect(source).toContain('syncFullHistory: true');
    expect(source).toContain('shouldSyncHistoryMessage: () => true');
    expect(source).toContain("Browsers.appropriate('Desktop')");
    // Garante que NÃO tem mais o config bugado pre-fix
    expect(source).not.toContain('syncFullHistory: false,');
  });

  it('R-HS-003 — Instance.ts registra listener messaging-history.set', async () => {
    const source = await import('node:fs').then((fs) =>
      fs.readFileSync(new URL('./Instance.ts', import.meta.url), 'utf8')
    );
    expect(source).toContain("sock.ev.on('messaging-history.set'");
    expect(source).toContain("event: 'history.sync'");
  });

  it('R-HS-004 — Chunking 100 msgs/batch presente (anti-overflow body 2MB)', async () => {
    const source = await import('node:fs').then((fs) =>
      fs.readFileSync(new URL('./Instance.ts', import.meta.url), 'utf8')
    );
    // CHUNK constant pra chunk size 100
    expect(source).toMatch(/const CHUNK = 100/);
    expect(source).toContain('chunk_index');
    expect(source).toContain('chunk_total');
  });

  it('R-HS-005 — Smoke chunking lógico — 250 msgs viram 3 chunks (0/100, 100/200, 200/250)', () => {
    const CHUNK = 100;
    const messages = Array.from({ length: 250 }, (_, i) => ({ id: i }));
    const chunks: { index: number; size: number }[] = [];
    for (let i = 0; i < messages.length; i += CHUNK) {
      const slice = messages.slice(i, i + CHUNK);
      chunks.push({ index: Math.floor(i / CHUNK), size: slice.length });
    }
    expect(chunks).toEqual([
      { index: 0, size: 100 },
      { index: 1, size: 100 },
      { index: 2, size: 50 },
    ]);
    expect(Math.ceil(messages.length / CHUNK)).toBe(3);
  });
});
