// Anti-regressão — o KPI hero do Financeiro NÃO pode voltar a ser a "caixa preta".
//
// O hero `.fin-stat-hero` foi escuro por várias ondas (`oklch(0.22 0.01 80)` = var(--text),
// o tom "documento contábil"). Onda 28 (2026-06-16 · handoff Cowork CONSERTAR-ELO · aprovado
// por Wagner) virou pra superfície CLARA com gradiente da identidade (accent 295). Sem este
// lock, a próxima cópia/sweep do CSS pode reintroduzir o dark silenciosamente — era exatamente
// o "elo frágil" diagnosticado no handoff (Passo 4: "TRAVAR com teste de regressão").
//
// Assertion ESTÁTICA sobre o fonte (CSS + TSX) — mais robusta e barata que um computed-style
// e2e (que depende de build + browser + dados). Mirror do método tests/foundationGuard.spec.ts:
// injeta o bug (caixa preta) e EXIGE detecção (sensibilidade) + prova que o claro real passa
// (especificidade) + prova que não passa vacuamente (vivo).
//
// Refs: handoff PROMPT_PARA_CODE_FINANCEIRO-CONSERTAR-ELO-BUNDLE Passo 4 · ADR 0258 (gate só
// vale se o controle-negativo roda no CI) · ADR 0281 (.dark bridge data-theme→tokens).

import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';

const CSS = 'resources/css/fin-cowork.css';
const TSX = 'resources/js/Pages/Financeiro/_shared/FinStatStrip.tsx';

// Extrai o bloco BASE do hero (declaração do card até o primeiro `}` — sem nested braces).
function heroBaseBlock(css: string): string {
  const sel = '.fin-cowork .fin-curadoria .fin-stat-hero {';
  const i = css.indexOf(sel);
  if (i < 0) throw new Error(`seletor base do hero não encontrado em ${CSS}`);
  return css.slice(i, css.indexOf('}', i) + 1);
}

// Detector da "caixa preta": background charcoal warm-dark (oklch ~0.2x L) OU var(--text) cru.
function isCaixaPreta(heroBlock: string): boolean {
  return /background:[^;}]*\boklch\(\s*0\.2[0-3]\b/.test(heroBlock)   // charcoal ~0.22 L
    || /background:\s*var\(--text\)\s*;/.test(heroBlock);            // literal cru do protótipo
}

describe('fin-hero — SENSIBILIDADE (injeta a caixa preta → detecta)', () => {
  it('detecta o charcoal warm-dark oklch(0.22 …) como caixa preta', () => {
    expect(isCaixaPreta('.x{ background: oklch(0.22 0.01 80); }')).toBe(true);
  });
  it('detecta o literal var(--text) (cópia crua do protótipo) como caixa preta', () => {
    expect(isCaixaPreta('.x{ background: var(--text); }')).toBe(true);
  });
  it('NÃO acusa o gradiente claro (especificidade — não é falso-positivo)', () => {
    const claro =
      '.x{ background: linear-gradient(160deg, color-mix(in oklab, var(--accent) 8%, var(--surface)) 0%, var(--surface) 78%); }';
    expect(isCaixaPreta(claro)).toBe(false);
  });
});

describe('fin-hero — o hero REAL é claro (Onda 28), não a caixa preta', () => {
  const block = heroBaseBlock(readFileSync(CSS, 'utf8'));

  it('fin-cowork.css: o card base NÃO é a caixa preta', () => {
    expect(isCaixaPreta(block)).toBe(false);
  });
  it('fin-cowork.css: o card base usa gradiente claro sobre var(--surface)', () => {
    expect(block).toContain('linear-gradient');
    expect(block).toMatch(/var\(--surface\)/);
  });
  it('FinStatStrip.tsx (componente piloto): o hero não hardcoda fundo escuro nem texto branco', () => {
    const tsx = readFileSync(TSX, 'utf8');
    expect(tsx).not.toContain('bg-[oklch(0.22_0.01_80)]');
    expect(tsx).not.toContain('text-white');
  });

  // Regressão 2026-06-16: o <b> "realizado" dentro do .fin-stat-hint herdava o --fs-8 (28px)
  // da regra `.fin-stat-hero b` → ficava do tamanho do número-herói (dois números gigantes).
  it('fin-cowork.css: o <b> do hint é reduzido (não fica do tamanho do número-herói)', () => {
    const css = readFileSync(CSS, 'utf8');
    const rule = css.match(/\.fin-stat-hero \.fin-stat-hint b\s*\{([^}]*)\}/);
    expect(rule, 'falta a regra que reduz o <b> do hint').not.toBeNull();
    expect(rule![1]).toMatch(/font-size:\s*var\(--fs-[12]\)/); // pequeno (gabarito = --fs-2), nunca o --fs-8 do herói
    expect(rule![1]).not.toMatch(/--fs-8/);
  });
});

describe('fin-hero — está VIVO (não passa vacuamente)', () => {
  it('o hero claro real referencia a luz da identidade var(--accent)', () => {
    expect(heroBaseBlock(readFileSync(CSS, 'utf8'))).toContain('var(--accent)');
  });
});
