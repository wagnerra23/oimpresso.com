// UC-JPAIN-20 — o KPI do Painel é RÉPLICA do `.jc-kpi` da âncora, não o `KpiCard` PT-04.
//
// Âncora: `prototipo-ui/cowork/jana-merge.jsx` §`data.kpis.map` → `KPICard`
// (markup em `chat-jana.jsx`, estilo em `chat-jana.css` §`── KPIs ──`; re-localize
// com `grep -n "jc-kpi" prototipo-ui/cowork/chat-jana.css`). Precedência de FORMA:
// protótipo > teste > casos > charter > SPEC (ADR UI-0029), sob ADR 0388 §D-1.
//
// O que este teste trava, e por quê cada um é o "feio" que [W] reportou em 2026-09-03:
//   1. moldura r8 (`rounded-lg`), não r12 — a âncora usa `--r-2`, e `--radius-lg` = 8px
//   2. rótulo MONO 10px/700 `.06em`, não sans 11px/600
//   3. ícone INLINE de 15px — a caixa 36x36 `bg-muted` do PT-04 NÃO existe na âncora
//   4. `emph` com tinta SÓLIDA (`bg-destructive-soft`), não `bg-destructive/5`
//   5. `emph` sobe o valor pro degrau `--fs-8`
//   6. os DOIS EIXOS separados (`emphasis` x `valueTone`), como a âncora
//   7. zero cor crua de palette
//
// Método (ADR 0258 — "todo ✅ tem que ter sido visto falhar"): controle-negativo do
// detector de cor crua + controle-negativo do detector de caixa de ícone.

import * as React from 'react';
import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import JanaKpiCard from '@/Pages/Jana/_components/JanaKpiCard';

afterEach(cleanup);

/** Cor CRUA do palette Tailwind — o que NUNCA pode aparecer na réplica. */
function hasRawPaletteColor(className: string): boolean {
  return /\b(?:bg|text|border|ring)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}\b/.test(
    className,
  );
}

/** A "caixa de ícone" do PT-04: contêiner com fundo + tamanho fixo em volta do svg. */
function hasIconBox(root: HTMLElement): boolean {
  return Array.from(root.querySelectorAll<HTMLElement>('div')).some(
    (el) =>
      /\bbg-(?:muted|destructive\/10|success\/10|warning\/10|info\/10)\b/.test(el.className) &&
      /\b[hw]-\d/.test(el.className),
  );
}

function renderCard(props: Partial<React.ComponentProps<typeof JanaKpiCard>> = {}) {
  const { container } = render(
    <JanaKpiCard label="A receber vencido" value="R$ 4,5M" icon="alert-triangle" {...props} />,
  );
  const card = container.querySelector<HTMLElement>('[data-slot="jana-kpi"]');
  if (!card) throw new Error('JanaKpiCard nao renderizou ([data-slot="jana-kpi"] ausente)');
  return { container, card };
}

describe('detectores — controle negativo (ADR 0258: o teste tem de poder falhar)', () => {
  it('SENSIBILIDADE: cor crua e caixa de ícone são detectadas quando existem', () => {
    expect(hasRawPaletteColor('rounded-lg bg-red-100 text-red-800')).toBe(true);

    const box = document.createElement('div');
    box.innerHTML = '<div class="flex h-9 w-9 rounded-lg bg-muted"><svg></svg></div>';
    expect(hasIconBox(box)).toBe(true);
  });

  it('ESPECIFICIDADE: token semântico e ícone inline NÃO casam', () => {
    expect(hasRawPaletteColor('rounded-lg border-destructive/20 bg-destructive-soft')).toBe(false);

    const inline = document.createElement('div');
    inline.innerHTML = '<div class="flex items-center justify-between"><span>X</span><svg></svg></div>';
    expect(hasIconBox(inline)).toBe(false);
  });
});

describe('UC-JPAIN-20 — a moldura e o rótulo vêm da âncora', () => {
  it('a caixa é r8 (rounded-lg), NUNCA o r12 do KpiCard PT-04', () => {
    const { card } = renderCard();
    expect(card.className).toContain('rounded-lg');
    expect(card.className).not.toContain('rounded-xl');
  });

  it('o padding é 12/14/14 e o gap 3px — não o p-4 do PT-04', () => {
    const { card } = renderCard();
    expect(card.className).toContain('pt-3');
    expect(card.className).toContain('pb-3.5');
    expect(card.className).toContain('gap-[3px]');
    expect(card.className).not.toMatch(/\bp-4\b/);
  });

  it('o rótulo é MONO 10px/700 uppercase com tracking .06em', () => {
    const { card } = renderCard();
    const header = card.querySelector<HTMLElement>('.font-mono');
    expect(header).not.toBeNull();
    expect(header!.className).toContain('text-[10px]');
    expect(header!.className).toContain('font-bold');
    expect(header!.className).toContain('uppercase');
    expect(header!.className).toContain('tracking-[0.06em]');
  });

  it('o ícone é INLINE de 15px — a caixa 36x36 do PT-04 não existe', () => {
    const { card } = renderCard();
    const svg = card.querySelector('svg');
    expect(svg).not.toBeNull();
    expect(svg!.getAttribute('width')).toBe('15');
    expect(hasIconBox(card)).toBe(false);
  });

  it('nenhuma cor crua de palette em nenhum nó da réplica', () => {
    const { card } = renderCard({
      emphasis: true,
      valueTone: 'negative',
      description: '76% do a receber',
    });
    for (const el of Array.from(card.querySelectorAll<HTMLElement>('*')).concat(card)) {
      expect(hasRawPaletteColor(typeof el.className === 'string' ? el.className : '')).toBe(false);
    }
  });
});

describe('UC-JPAIN-20 — `emph` é tinta SÓLIDA, e os dois eixos são independentes', () => {
  it('emphasis pinta o fundo com o token sólido, não com 5% de destructive', () => {
    const { card } = renderCard({ emphasis: true });
    expect(card.className).toContain('bg-destructive-soft');
    expect(card.className).toContain('border-destructive/20');
    expect(card.className).not.toContain('bg-destructive/5');
  });

  it('emphasis sobe o valor pro degrau --fs-8; sem ele o valor fica em --fs-7', () => {
    expect(renderCard({ emphasis: true }).card.querySelector('b')!.className).toContain(
      'text-[length:var(--fs-8)]',
    );
    cleanup();
    expect(renderCard().card.querySelector('b')!.className).toContain('text-[length:var(--fs-7)]');
  });

  it('EIXOS SEPARADOS: emphasis sozinho NÃO pinta o valor de vermelho', () => {
    // Na âncora quem pinta o valor é `deltaCls === "red big"` (`.jc-kpi-v.red`), e
    // `emphasize` só mexe em fundo/borda. O `KpiCard` shared fundiu os dois num `tone`
    // e registrou a dúvida como errata; aqui os eixos ficam declaráveis em separado.
    const valorSoEmph = renderCard({ emphasis: true }).card.querySelector('b')!;
    expect(valorSoEmph.className).toContain('text-foreground');
    expect(valorSoEmph.className).not.toContain('text-destructive');
    cleanup();

    const valorNegativo = renderCard({ valueTone: 'negative' }).card.querySelector('b')!;
    expect(valorNegativo.className).toContain('text-destructive');
  });
});

describe('UC-JPAIN-20 — o drill continua, e o card segue sendo DIV', () => {
  it('sem onClick não há botão nenhum (o card não nasce clicável à toa — UC-JPAIN-16)', () => {
    const { container } = renderCard();
    expect(container.querySelector('button')).toBeNull();
  });

  it('com onClick o CLICÁVEL é o wrapper e o `.jc-kpi` segue DIV, como na âncora', () => {
    const { container, card } = renderCard({ onClick: () => {} });
    const botao = container.querySelector('button');
    expect(botao).not.toBeNull();
    expect(botao!.getAttribute('aria-label')).toBe('Ver origem de A receber vencido');
    expect(card.tagName).toBe('DIV');
    expect(botao!.contains(card)).toBe(true);
  });
});

describe('UC-JPAIN-20 — o delta declara a unidade que o dado tem', () => {
  it('o percentual sai com o sinal E o `%`, como a âncora escreve', () => {
    // A âncora escreve `-68% vs mai/25`. O `Delta` do KpiCard shared imprimia
    // `+3 hoje vs ontem` — o número é percentual e o `%` sumia.
    const { card } = renderCard({ delta: { value: -22, label: 'em 4m' } });
    expect(card.textContent).toContain('-22% em 4m');
    cleanup();
    expect(renderCard({ delta: { value: 3, label: '7d' } }).card.textContent).toContain('+3% 7d');
  });
});
