// Camada META — testa o teste (controle-negativo do conformance-gate · Camada 1).
//
// Regra do MÉTODO (METODO_TELA_ANTI-REGRESSAO.md §controle-negativo): "todo ✅ tem que ter
// sido visto falhar". Um gate que nunca foi visto vermelho não vale. Este suite INJETA o bug
// (cor crua nova em regra de tela) e EXIGE que a contagem suba — e prova o lado oposto
// (token-driven / :root / exceção / não-tela NÃO acusam inocente).
//
// Sensibilidade  → pega o bug (cor crua nova conta).
// Especificidade → não acusa inocente (var()/:root/.vd-trans/não-tela não contam).
//
// Refs: PROMPT_PARA_CODE_CONFORMANCE-GATE.md (Camada META) · ADR 0209 (ratchet gêmeo).

import { describe, it, expect } from 'vitest';
import { readFileSync, readdirSync } from 'node:fs';
import { rawColorHits, accentHueViolations, tokenRoleViolations, fontRampHits, FS_RAMP } from '../scripts/conformance-gate.mjs';

describe('conformance-gate — SENSIBILIDADE (injeta bug → conta sobe)', () => {
  it('cor crua oklch(<hue numérico>) NOVA em regra de tela é contada', () => {
    const limpo = `.sells-cowork .vd-kpi { color: var(--accent-fg); }`;
    const comBug = `.sells-cowork .vd-kpi { color: oklch(0.72 0.18 155); }`; // verde-155 cru (drift D-02)
    expect(rawColorHits(limpo).length).toBe(0);
    expect(rawColorHits(comBug).length).toBeGreaterThan(rawColorHits(limpo).length);
  });

  it('#hex cru NOVO em regra de tela é contado', () => {
    const limpo = `.sells-cowork .os-kpi { background: var(--surface); }`;
    const comBug = `.sells-cowork .os-kpi { background: #fff; }`;
    expect(rawColorHits(limpo).length).toBe(0);
    expect(rawColorHits(comBug).length).toBe(1);
  });
});

describe('conformance-gate — ESPECIFICIDADE (não acusa inocente)', () => {
  it('oklch(...var()) token-driven NÃO conta', () => {
    const css = `.sells-cowork .vd-pill { background: oklch(from var(--accent) l c h / 0.12); }`;
    expect(rawColorHits(css).length).toBe(0);
  });

  it(':root / [data-theme] (defs de token) NÃO contam — é onde a cor crua mora legitimamente', () => {
    expect(rawColorHits(`:root { --accent: oklch(0.55 0.15 295); }`).length).toBe(0);
    expect(rawColorHits(`[data-theme="dark"] { --surface: #1a1a1a; }`).length).toBe(0);
  });

  it('exceções declaradas (.vd-trans transcript A4 · .vd-pres apresentação) NÃO contam', () => {
    expect(rawColorHits(`.vd-trans { color: #000; background: #fff; }`).length).toBe(0);
    expect(rawColorHits(`.vd-pres-slide { background: oklch(0.18 0.02 270); }`).length).toBe(0);
  });

  it('seletor que NÃO é de tela (fora do vocabulário Sells/Cowork) NÃO conta', () => {
    expect(rawColorHits(`.algum-componente-generico { color: #abc; }`).length).toBe(0);
  });
});

describe('conformance-gate — está VIVO (não passa vacuamente em 0)', () => {
  it('o sells-cowork.css real do repo tem cor crua > 0 (senão o gate seria fajuto)', () => {
    // Path relativo à raiz do repo (vitest roda de lá) — cross-platform, sem fileURLToPath
    // (que quebra no Windows: "The URL must be of scheme file"). Bug pego no run real 2026-06-03.
    const css = readFileSync('resources/css/sells-cowork.css', 'utf8');
    expect(rawColorHits(css).length).toBeGreaterThan(100);
  });
});

// Invariante do TOKEN de marca (a metade do verde×roxo que o ratchet de cor-crua NÃO pega:
// redefinição do --accent em :root pra um oklch verde). Determinístico, sem browser → fecha UC-V09 no CSS.
describe('accent-hue guard — SENSIBILIDADE (token verde é pego)', () => {
  it('--accent redefinido pra verde-155 é violação', () => {
    expect(accentHueViolations(`:root { --accent: oklch(0.72 0.18 155); }`).length).toBe(1);
  });
  it('--accent-soft/-line verde também são pegos', () => {
    expect(accentHueViolations(`:root { --accent-line: oklch(0.86 0.05 140); }`).length).toBe(1);
  });
});

describe('accent-hue guard — ESPECIFICIDADE (roxo canônico não acusa)', () => {
  it('--accent roxo 295 canônico = 0 violações', () => {
    expect(accentHueViolations(`:root { --accent: oklch(0.55 0.15 295); --accent-soft: oklch(0.95 0.04 295); }`).length).toBe(0);
  });
  it('oklch(from var(--accent) ...) (sem hue numérico) NÃO é avaliado', () => {
    expect(accentHueViolations(`.x { background: oklch(from var(--accent) l c h / 0.12); }`).length).toBe(0);
  });
  it('o cockpit.css real (token canônico) está em roxo — 0 violações', () => {
    expect(accentHueViolations(readFileSync('resources/css/cockpit.css', 'utf8')).length).toBe(0);
  });
});

// ── Papel de token (PACOTE-Q9 PR-3 · espelho estático do probe G3 do Cowork) ──────────────
// -fg é texto/ícone, -bg é superfície. Inversão (-fg em background/fill, -bg em color/stroke)
// foi a classe do erro 06-10a (barra de progresso marrom com --origin-MFG-fg de fill).
// Controle-negativo (L-31): visto 🔴 no bug injetado E 🟢 no limpo.
describe('token-role guard — SENSIBILIDADE (papel invertido é pego)', () => {
  it('-fg como background é violação', () => {
    expect(tokenRoleViolations(`.os-progress { background: var(--origin-MFG-fg); }`).length).toBe(1);
  });
  it('-fg como fill (SVG) é violação', () => {
    expect(tokenRoleViolations(`.os-bar rect { fill: var(--accent-fg); }`).length).toBe(1);
  });
  it('-fg como background-color com fallback é violação', () => {
    expect(tokenRoleViolations(`.vd-pill { background-color: var(--pos-fg, #fff); }`).length).toBe(1);
  });
  it('-bg como color é violação', () => {
    expect(tokenRoleViolations(`.vd-label { color: var(--surface-bg); }`).length).toBe(1);
  });
  it('-bg como stroke é violação', () => {
    expect(tokenRoleViolations(`.os-ring circle { stroke: var(--card-bg); }`).length).toBe(1);
  });
});

describe('token-role guard — ESPECIFICIDADE (papel correto não acusa)', () => {
  it('-fg em color (papel certo) = 0', () => {
    expect(tokenRoleViolations(`.vd-kpi { color: var(--accent-fg); }`).length).toBe(0);
  });
  it('-bg em background (papel certo) = 0', () => {
    expect(tokenRoleViolations(`.vd-card { background: var(--card-bg); }`).length).toBe(0);
  });
  it('token sem sufixo de papel (--accent/--pos) em qualquer propriedade = 0', () => {
    expect(tokenRoleViolations(`.vd-btn { background: var(--accent); color: var(--pos); }`).length).toBe(0);
  });
  it('definição de token (--x-fg: ...) não é uso — 0', () => {
    expect(tokenRoleViolations(`:root { --origin-MFG-fg: oklch(0.4 0.1 60); }`).length).toBe(0);
  });
});

describe('token-role guard — repo LIMPO (invariante absoluto vale hoje)', () => {
  it('todos os resources/css/*.css têm 0 inversões de papel', () => {
    const files = readdirSync('resources/css').filter((n) => n.endsWith('.css'));
    const hits = files.flatMap((f) => tokenRoleViolations(readFileSync(`resources/css/${f}`, 'utf8'), f));
    expect(hits).toEqual([]);
  });
});

// ── Type RAMP ratchet (F2 · [W] "vai" 2026-06-10) — controle-negativo (L-31) ──────────────
// fontRampHits conta `font-size: <N>px` com N FORA dos 9 degraus --fs-1..9 (foundations.css).
describe('fontramp guard — SENSIBILIDADE (px fora do ramp é pego)', () => {
  it('font-size:13px (fora do ramp) conta', () => {
    expect(fontRampHits(`.fin-x { font-size: 13px; }`).length).toBe(1);
  });
  it('font-size:16px e 14px (defaults Tailwind/browser fora do ramp) contam', () => {
    expect(fontRampHits(`.a { font-size: 16px; } .b { font-size: 14px; }`).length).toBe(2);
  });
});

describe('fontramp guard — ESPECIFICIDADE (não acusa inocente)', () => {
  it('os 9 degraus do ramp NÃO contam', () => {
    const css = FS_RAMP.map((v: number, i: number) => `.s${i} { font-size: ${v}px; }`).join('\n');
    expect(fontRampHits(css).length).toBe(0);
  });
  it('consumo via var(--fs-N) NÃO conta', () => {
    expect(fontRampHits(`.fin-x { font-size: var(--fs-4); }`).length).toBe(0);
  });
  it('definição do token (--fs-1: 10.5px) NÃO conta — propriedade não é font-size', () => {
    expect(fontRampHits(`:root { --fs-1: 10.5px; --fs-9: 38px; }`).length).toBe(0);
  });
  it('font-size px dentro de comentário NÃO conta', () => {
    expect(fontRampHits(`/* antes era font-size: 13px */ .x { color: var(--text); }`).length).toBe(0);
  });
  it('unidade relativa (rem/em) NÃO conta — ramp é âncora px', () => {
    expect(fontRampHits(`.x { font-size: 1rem; } .y { font-size: 0.875em; }`).length).toBe(0);
  });
});

describe('fontramp guard — foundations.css define o ramp completo', () => {
  it('os 9 tokens --fs-1..9 existem com os valores canônicos', () => {
    const css = readFileSync('resources/css/foundations.css', 'utf8');
    FS_RAMP.forEach((v: number, i: number) => {
      expect(css).toMatch(new RegExp(`--fs-${i + 1}:\\s*${String(v).replace('.', '\\.')}px`));
    });
  });
});
