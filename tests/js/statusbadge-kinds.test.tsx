/**
 * `shared/StatusBadge` — os kinds `sla` · `frescor` · `atendimento` e as props `rel` / `tone`
 * (playbook `ds-atomos` thread 05 · decisão D-SB-KINDS, [W] 2026-09-24).
 *
 * De onde vêm os literais deste arquivo — NÃO do componente:
 *   - chave e rótulo: `MAP` do StatusBadge em `prototipo-ui/design-system/_ds_bundle.js`;
 *   - token de cor: a tabela C da thread 05, que o [W] aprovou ("cores próprias", Atrasado ≠
 *     Vencido), e que usa os tokens que JÁ existem em `tokens/_generated-cockpit-*.css`.
 *
 * ⚠️ O que este arquivo NÃO mede: cor computada. O vitest roda em jsdom com `css: false`, e o
 * jsdom não resolve `var()` de classe Tailwind. "Atrasado ≠ Vencido" é provado aqui pelas duas
 * pernas que o jsdom alcança — (a) as pílulas apontam para tokens DIFERENTES e (b) esses tokens
 * têm valores DIFERENTES no light e no dark, lidos do CSS gerado. A cor renderizada foi medida
 * no browser com o CSS buildado, e o número está no recibo `_saida-05.md`.
 */
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { render } from '@testing-library/react';
import StatusBadge from '@/Components/shared/StatusBadge';

const raiz = resolve(__dirname, '../..');
const ler = (p: string) => readFileSync(resolve(raiz, p), 'utf8');

const badge = (el: HTMLElement) => el.querySelector('[data-slot="badge"]') as HTMLElement;
const classes = (el: HTMLElement) => badge(el).className.split(/\s+/);

// kind · chave · rótulo literal do DS · como pinta
const TOKEN = (fundo: string, texto: string) => ({ fundo, texto });
const CASOS: Array<[string, string, string, { variant: string } | { fundo: string; texto: string }]> = [
  ['sla', 'fresh', 'No prazo', TOKEN('--sla-fresh-soft', '--sla-fresh')],
  ['sla', 'aging', 'Vencendo', TOKEN('--sla-aging-soft', '--sla-aging')],
  ['sla', 'late', 'Atrasado', TOKEN('--sla-late-soft', '--sla-late')],
  ['sla', 'expired', 'Vencido', TOKEN('--sla-expired-soft', '--sla-expired')],
  ['frescor', 'recente', 'recente', { variant: 'success' }],
  ['frescor', 'fresc', 'fresc', { variant: 'warning' }],
  ['frescor', 'frio', 'frio', { variant: 'danger' }],
  ['frescor', 'distante', 'distante', { variant: 'danger' }],
  ['atendimento', 'email', 'E-mail', TOKEN('--canal-email-tint', '--canal-email-fg')],
  ['atendimento', 'instagram', 'Instagram', TOKEN('--canal-ig-tint', '--canal-ig-fg')],
  ['atendimento', 'facebook', 'Facebook', TOKEN('--canal-fb-tint', '--canal-fb-fg')],
  ['atendimento', 'mercadolivre', 'Mercado Livre', TOKEN('--canal-ml-tint', '--canal-ml-fg')],
  ['atendimento', 'whatsapp', 'WhatsApp', TOKEN('--sla-fresh-soft', '--sla-fresh')],
];

/** Valor de um token no bloco light (`.cockpit`) ou dark (`.cockpit[data-theme="dark"]`). */
function valorToken(tema: 'light' | 'dark', token: string): string {
  const css = ler(`resources/css/tokens/_generated-cockpit-${tema}.css`);
  const m = css.match(new RegExp(`${token.replace(/-/g, '\\-')}:\\s*([^;]+);`));
  return m?.[1]?.trim() ?? '';
}

describe('StatusBadge — as 13 chaves do DS (1)', () => {
  it.each(CASOS)('%s.%s → "%s"', (kind, value, rotulo, cor) => {
    const { container } = render(<StatusBadge kind={kind} value={value} />);
    const el = badge(container);

    expect(el.textContent).toBe(rotulo);
    // dot ligado, como em todo status deste componente (AP7)
    expect(el.querySelector('[data-slot="badge-dot"]')).not.toBeNull();

    if ('variant' in cor) {
      expect(el.getAttribute('data-variant')).toBe(cor.variant);
    } else {
      expect(el.getAttribute('data-variant')).toBe('outline');
      expect(classes(container)).toContain(`bg-[var(${cor.fundo})]`);
      expect(classes(container)).toContain(`text-[var(${cor.texto})]`);
      // o tom do `outline` não pode sobrar por baixo do token
      expect(classes(container)).not.toContain('text-foreground');
      expect(classes(container)).not.toContain('border-border');
    }
  });

  it('nenhuma chave usa fill sólido (AP7) nem o chip sólido do canal', () => {
    for (const [kind, value] of CASOS) {
      const { container } = render(<StatusBadge kind={kind} value={value} />);
      const cls = classes(container).join(' ');
      expect(cls).not.toMatch(/canal-[a-z]+-bg/);
      expect(badge(container).getAttribute('data-variant')).not.toMatch(/^(default|destructive|secondary)$/);
    }
  });

  it('todos os tokens usados existem no light e no dark (0 token novo)', () => {
    const tokens = CASOS.flatMap(([, , , c]) => ('fundo' in c ? [c.fundo, c.texto] : []));
    for (const tema of ['light', 'dark'] as const) {
      for (const t of tokens) expect(valorToken(tema, t), `${t} no ${tema}`).not.toBe('');
    }
  });
});

describe('Atrasado ≠ Vencido (1b) e canais distintos (1c)', () => {
  it('late e expired apontam para tokens diferentes, com valores diferentes nos dois temas', () => {
    const late = render(<StatusBadge kind="sla" value="late" />).container;
    const expired = render(<StatusBadge kind="sla" value="expired" />).container;
    const corDe = (c: HTMLElement) => classes(c).find((k) => k.startsWith('text-[var(')) as string;

    expect(corDe(late)).not.toBe(corDe(expired));
    for (const tema of ['light', 'dark'] as const) {
      expect(valorToken(tema, '--sla-late')).not.toBe(valorToken(tema, '--sla-expired'));
      expect(valorToken(tema, '--sla-late-soft')).not.toBe(valorToken(tema, '--sla-expired-soft'));
    }
  });

  it('as 4 pílulas de canal têm cores distintas entre si, nos dois temas', () => {
    const canais = ['email', 'ig', 'fb', 'ml'];
    for (const tema of ['light', 'dark'] as const) {
      const fg = canais.map((c) => valorToken(tema, `--canal-${c}-fg`));
      const tint = canais.map((c) => valorToken(tema, `--canal-${c}-tint`));
      expect(new Set(fg).size, `fg no ${tema}`).toBe(4);
      expect(new Set(tint).size, `tint no ${tema}`).toBe(4);
    }
  });
});

describe('guarda — kinds que já existiam não mudam (2)', () => {
  // Um valor por kind antigo. O que se asserta é contrato observável — o markup não ganha nó
  // novo nem classe de token — e não o rótulo, que viria copiado do próprio componente.
  const ANTIGOS: Array<[string, string]> = [
    ['intercorrencia', 'pendente'], ['aprovacao', 'aprovada'], ['prioridade', 'urgente'],
    ['documento', 'ordered'], ['producao', 'rascunho'], ['os', 'packed'], ['payment', 'paid'],
    ['financeiro_titulo', 'aberto'], ['importacao', 'erro'], ['nfse', 'emitida'],
    ['rep', 'REP_P'], ['vehicle', 'in_service'], ['ads_destination', 'queued'],
    ['ads_risco', 'Crítico'], ['mcp_status', 'ok'], ['admin_health', 'red'],
    ['admin_reachable', 'online'], ['licenca', 'ativa'], ['licenca_no_acesso', 'bloqueada'],
    ['arquivo_prazo', 'vencido'], ['ajuste_estoque', 'abnormal'], ['transferencia_estoque', 'in_transit'],
  ];

  it.each(ANTIGOS)('%s.%s: só dot + texto, sem sufixo nem token de sla/canal', (kind, value) => {
    const { container } = render(<StatusBadge kind={kind} value={value} />);
    const el = badge(container);
    expect(el.children).toHaveLength(1);
    expect(el.children[0]?.getAttribute('data-slot')).toBe('badge-dot');
    expect(el.querySelector('[data-slot="status-rel"]')).toBeNull();
    expect(el.className).not.toMatch(/--(sla|canal)-/);
  });
});

describe('rel (3)', () => {
  it('aparece depois do rótulo, separado por ·', () => {
    const { container } = render(<StatusBadge kind="frescor" value="recente" rel="há 1 sem" />);
    const el = badge(container);
    expect(el.textContent).toBe('recente·há 1 sem');
    const filhos = Array.from(el.childNodes);
    const iRel = filhos.findIndex((n) => (n as HTMLElement).dataset?.slot === 'status-rel');
    const iRotulo = filhos.findIndex((n) => n.textContent === 'recente');
    expect(iRotulo).toBeGreaterThan(-1);
    expect(iRel).toBeGreaterThan(iRotulo);
    expect(el.querySelector('[data-slot="status-rel-sep"]')?.getAttribute('aria-hidden')).toBe('true');
  });

  it('vale também no fallback (valor fora do mapa)', () => {
    const { container } = render(<StatusBadge kind="sla" value="desconhecido" rel="ontem" />);
    expect(badge(container).textContent).toBe('Desconhecido·ontem');
  });

  it('sem rel, o texto é só o rótulo', () => {
    const { container } = render(<StatusBadge kind="frescor" value="recente" />);
    expect(badge(container).textContent).toBe('recente');
    expect(badge(container).querySelector('[data-slot="status-rel-sep"]')).toBeNull();
  });
});

describe('tone (4)', () => {
  it('troca a variante do mapa e tira a cor de token da entrada', () => {
    const { container } = render(<StatusBadge kind="sla" value="late" tone="info" />);
    expect(badge(container).getAttribute('data-variant')).toBe('info');
    expect(badge(container).className).not.toContain('--sla-late');
    expect(badge(container).textContent).toBe('Atrasado');
  });

  it('troca a variante do fallback', () => {
    const { container } = render(<StatusBadge kind="sla" value="xyz" tone="warning" />);
    expect(badge(container).getAttribute('data-variant')).toBe('warning');
  });

  it('tom fora do DS não compila', () => {
    // @ts-expect-error — 'roxo' não é Variant do badgeVariants
    const el = <StatusBadge kind="sla" value="late" tone="roxo" />;
    expect(el).toBeTruthy();
  });
});

describe('ausências (5)', () => {
  const fonte = ler('resources/js/Components/shared/StatusBadge.tsx');
  it('fora do escopo da D-SB-KINDS: fiscal, tipo PJ/PF e o chip sólido de canal', () => {
    expect(fonte).not.toMatch(/^\s*fiscal:/m);
    expect(fonte).not.toContain('tipo-pj');
    expect(fonte).not.toContain('canal-email-bg');
  });
});
