// Fidelidade protótipo↔componente — a pílula de STATUS canoniza em <Badge variant="…">
// (@/Components/ui/badge) e não pode divergir do `.cli-status-pill` do protótipo Cowork
// (prototipo-ui/cowork/clientes-page.css) NEM regredir o contrato dark-aware.
//
// CAUSA que este teste fecha (mesmo vetor do tab-nav 2026-07): a cor de status ser
// hand-rolada em palette CRU quebra o dark sem alarme. O corolário de cor da ADR
// proposta tab-nav/componente-por-papel manda: "valor de cor mora em token dark-aware
// (-soft/-fg), nunca hardcoded". Este render-test trava, por variante:
//
//   border-radius : rounded-full        → .cli-status-pill é border-radius:999px (pill)
//   background    : bg-<tone>-soft       → token dark-aware (troca light/dark sozinho)
//   text          : text-<tone>-fg       → idem
//   (proibido)    : cor CRUA do palette   → NENHUM bg/text-<verde|vermelho|âmbar>-<n>
//
// Método (ADR 0258 — "todo ✅ tem que ter sido visto falhar"): controle-negativo do
// detector de cor-crua (sensibilidade: casa `bg-green-100`; especificidade: o className
// tokenizado NÃO casa) + especificidade entre variantes (success ≠ danger).
//
// Refs: ADR proposta tab-nav/componente-por-papel (§D-2 corolário de cor) · ADR 0338
// (eixo valor-vs-token) · clientes-page.css §.cli-status-pill · REGISTRY_DS_COMPONENTES.md.

import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import { Badge } from '@/Components/ui/badge';

afterEach(cleanup);

// Pares semânticos -soft/-fg (inertia.css) por tone de status. Fonte da verdade da
// fidelidade: se badge.tsx trocar um par por cor crua, o teste quebra CONSCIENTEMENTE.
const TONE_TOKENS: Record<string, { bg: string; fg: string; border: string }> = {
  success: { bg: 'bg-success-soft', fg: 'text-success-fg', border: 'border-success/20' },
  warning: { bg: 'bg-warning-soft', fg: 'text-warning-fg', border: 'border-warning/20' },
  danger: { bg: 'bg-destructive-soft', fg: 'text-destructive-fg', border: 'border-destructive/20' },
  info: { bg: 'bg-info-soft', fg: 'text-info-fg', border: 'border-info/20' },
};

// Detector de cor CRUA do palette (o que NÃO pode aparecer numa pílula de status).
// É a peça que "quebra se alguém trocar o token por bg-green-100".
function hasRawPaletteColor(className: string): boolean {
  return /\b(?:bg|text|border|ring)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}\b/.test(
    className,
  );
}

function renderBadge(variant: string) {
  const { container } = render(<Badge variant={variant as never}>rótulo</Badge>);
  const el = container.querySelector<HTMLElement>('[data-slot="badge"]');
  if (!el) throw new Error('Badge não renderizou ([data-slot="badge"] ausente)');
  return el;
}

// ── CONTROLE-NEGATIVO do detector de cor-crua (ADR 0258 — não vacuoso) ──────────
describe('hasRawPaletteColor — sensibilidade + especificidade', () => {
  it('SENSIBILIDADE: casa cor crua do palette (bg-green-100 / text-red-500)', () => {
    expect(hasRawPaletteColor('inline-flex rounded-full px-2 bg-green-100 text-green-800')).toBe(true);
    expect(hasRawPaletteColor('text-red-500')).toBe(true);
  });
  it('ESPECIFICIDADE: className tokenizado (-soft/-fg) NÃO casa', () => {
    expect(hasRawPaletteColor('rounded-full px-2 py-0.5 bg-success-soft text-success-fg border-success/20')).toBe(false);
  });
});

// ── FIDELIDADE da pílula de status vs .cli-status-pill + contrato dark-aware ─────
describe('Badge (papel status-badge) — pílula fiel ao protótipo, dark-aware, não regride', () => {
  it('shape base: rounded-full (pill 999px) + px + border + text-xs + font-medium', () => {
    const el = renderBadge('success');
    const c = el.className;
    expect(c).toMatch(/\brounded-full\b/); // .cli-status-pill: border-radius:999px
    expect(c).toMatch(/\bpx-2\b/);
    expect(c).toMatch(/\bborder\b/);
    expect(c).toMatch(/\btext-xs\b/);
    expect(c).toMatch(/\bfont-medium\b/);
  });

  for (const [variant, tok] of Object.entries(TONE_TOKENS)) {
    it(`variant="${variant}" carrega o par dark-aware ${tok.bg}/${tok.fg} (sem cor crua)`, () => {
      const el = renderBadge(variant);
      const c = el.className;
      expect(el.getAttribute('data-variant')).toBe(variant);
      expect(c).toContain(tok.bg);
      expect(c).toContain(tok.fg);
      expect(c).toContain(tok.border);
      // corolário de cor (ADR §D-2): NENHUMA cor crua do palette — token dark-aware.
      expect(hasRawPaletteColor(c)).toBe(false);
    });
  }

  it('neutral usa muted/border (não inventa tone)', () => {
    const c = renderBadge('neutral').className;
    expect(c).toContain('bg-muted');
    expect(c).toContain('text-muted-foreground');
    expect(hasRawPaletteColor(c)).toBe(false);
  });

  // ESPECIFICIDADE (não vacuoso): tones distintos não colapsam.
  it('success ≠ danger — variantes distintas carregam tokens distintos', () => {
    const ok = renderBadge('success').className;
    expect(ok).toContain('bg-success-soft');
    expect(ok).not.toContain('destructive-soft');
    const dg = renderBadge('danger').className;
    expect(dg).toContain('bg-destructive-soft');
    expect(dg).not.toContain('success-soft');
  });
});
