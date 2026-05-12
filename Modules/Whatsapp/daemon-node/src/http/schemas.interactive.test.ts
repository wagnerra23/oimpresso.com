import { describe, expect, it } from 'vitest';
import { sendInteractiveBody } from './schemas';

// ------------------------------------------------------------------------------------------------
// US-WA-045/046 — Zod schema da rota /instances/:id/interactive
//
// Garante:
//  - buttons discriminated union (1..3)
//  - list sections (1..10) com items (1..10) — description opcional
//  - cta_url NÃO aceito (Meta Cloud-only)
//  - limites de tamanho (label 20, title 24, description 72, body 1024)
// ------------------------------------------------------------------------------------------------

describe('sendInteractiveBody — Zod discriminated union', () => {
  it('aceita buttons com 1 botão', () => {
    const parsed = sendInteractiveBody.parse({
      to: '5548999872822',
      body: 'Confirmar?',
      interactive: { type: 'buttons', buttons: [{ id: 'sim', label: 'Sim' }] },
    });
    expect(parsed.interactive.type).toBe('buttons');
  });

  it('aceita buttons com 3 botões (limite máximo)', () => {
    const parsed = sendInteractiveBody.parse({
      to: '5548999872822',
      body: 'Escolha:',
      interactive: {
        type: 'buttons',
        buttons: [
          { id: 'a', label: 'A' },
          { id: 'b', label: 'B' },
          { id: 'c', label: 'C' },
        ],
      },
    });
    if (parsed.interactive.type === 'buttons') {
      expect(parsed.interactive.buttons).toHaveLength(3);
    }
  });

  it('rejeita buttons com label > 20 chars (limite Whatsapp)', () => {
    expect(() =>
      sendInteractiveBody.parse({
        to: '5548999872822',
        body: 'X',
        interactive: {
          type: 'buttons',
          buttons: [{ id: 'a', label: 'a'.repeat(25) }],
        },
      }),
    ).toThrow();
  });

  it('aceita list com 3 sections × 5 items each', () => {
    const sections = Array.from({ length: 3 }, (_, i) => ({
      title: `Seção ${i + 1}`,
      items: Array.from({ length: 5 }, (_, j) => ({
        id: `s${i}-i${j}`,
        title: `Item ${j + 1}`,
        description: j % 2 === 0 ? `Detalhe ${j}` : undefined,
      })),
    }));
    const parsed = sendInteractiveBody.parse({
      to: '5548999872822',
      body: 'Cardápio:',
      interactive: { type: 'list', button_label: 'Ver', sections },
    });
    if (parsed.interactive.type === 'list') {
      expect(parsed.interactive.sections).toHaveLength(3);
      expect(parsed.interactive.sections[0]?.items).toHaveLength(5);
    }
  });

  it('rejeita type=cta_url (Meta Cloud-only)', () => {
    expect(() =>
      sendInteractiveBody.parse({
        to: '5548999872822',
        body: 'X',
        interactive: { type: 'cta_url', button_label: 'Abrir', url: 'https://example.com' },
      }),
    ).toThrow();
  });

  it('rejeita body > 1024 chars', () => {
    expect(() =>
      sendInteractiveBody.parse({
        to: '5548999872822',
        body: 'x'.repeat(2000),
        interactive: { type: 'buttons', buttons: [{ id: 'a', label: 'A' }] },
      }),
    ).toThrow();
  });

  it('rejeita list com 0 sections', () => {
    expect(() =>
      sendInteractiveBody.parse({
        to: '5548999872822',
        body: 'X',
        interactive: { type: 'list', button_label: 'X', sections: [] },
      }),
    ).toThrow();
  });

  it('rejeita list com 11 items numa section (excede 10)', () => {
    const items = Array.from({ length: 11 }, (_, j) => ({
      id: `i${j}`,
      title: `Item ${j}`,
    }));
    expect(() =>
      sendInteractiveBody.parse({
        to: '5548999872822',
        body: 'X',
        interactive: {
          type: 'list',
          button_label: 'X',
          sections: [{ title: 'S', items }],
        },
      }),
    ).toThrow();
  });
});
