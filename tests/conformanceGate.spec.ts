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
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { rawColorHits } from '../scripts/conformance-gate.mjs';

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
    const css = readFileSync(
      fileURLToPath(new URL('../resources/css/sells-cowork.css', import.meta.url)),
      'utf8',
    );
    expect(rawColorHits(css).length).toBeGreaterThan(100);
  });
});
