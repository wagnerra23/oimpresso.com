// Camada META — testa o teste (controle-negativo do foundation-guard · Camada 3 · estrutura).
//
// Regra do MÉTODO ("todo ✅ tem que ter sido visto falhar"): um gate que nunca foi visto
// vermelho não vale. Este suite INJETA o bug (token-def fora da fundação / .css novo) e EXIGE
// rejeição — e prova o lado oposto (var() consumo · comentário "@theme" NÃO acusam inocente).
//
// Sensibilidade  → pega o bug (token-def num arquivo novo conta; .css fora da lista rejeita).
// Especificidade → não acusa inocente (var(--accent) e @theme em comentário não contam).
//
// Refs: foundation-guard.mjs · ADR UI-0013 (Camada Fundações) · ADR 0209 (ratchet gêmeo).

import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { tokenDefCount, stripComments } from '../scripts/foundation-guard.mjs';

// Sob a onda DTCG (ativada — PR #3230/#3356) a DEFINIÇÃO dos tokens do Shell saiu de inline no
// cockpit.css e migrou pra resources/css/tokens/_generated-cockpit-{light,dark}.css, importados no
// TOPO do cockpit.css via @import. O "home" de token virou o cockpit.css COMPOSTO (arquivo + o que
// ele puxa). Este helper soma os token-defs do .css MAIS os dos arquivos que ele importa (1 nível —
// o que o DTCG produz) pra o controle-negativo continuar honesto: se a geração DTCG zerar (import
// vazio/quebrado/removido), a conta cai a 0 e o teste "está VIVO" pega. Ler só o cru daria 0 (falso-fail).
function tokenDefCountComposed(entryPath: string): number {
  const css = readFileSync(entryPath, 'utf8');
  let total = tokenDefCount(css);
  const baseDir = dirname(entryPath);
  for (const stmt of stripComments(css).match(/@import\s+["']([^"']+)["']/g) || []) {
    const rel = stmt.match(/["']([^"']+)["']/)?.[1];
    if (rel) total += tokenDefCount(readFileSync(resolve(baseDir, rel), 'utf8'));
  }
  return total;
}

describe('foundation-guard — SENSIBILIDADE (injeta bug → conta sobe)', () => {
  it('@theme duplicado num arquivo conta como definição', () => {
    expect(tokenDefCount(`@theme { --x: 1; }`)).toBeGreaterThan(0);
  });
  it('--accent: redefinido (definição) conta', () => {
    const limpo = `.x { background: var(--accent); }`;          // consumo
    const comBug = `:root { --accent: oklch(0.72 0.18 155); }`; // definição (re-duplicada)
    expect(tokenDefCount(limpo)).toBe(0);
    expect(tokenDefCount(comBug)).toBeGreaterThan(tokenDefCount(limpo));
  });
  it('--color-primary: (definição) conta', () => {
    expect(tokenDefCount(`:root { --color-primary: oklch(0.55 0.15 295); }`)).toBe(1);
  });
});

describe('foundation-guard — ESPECIFICIDADE (não acusa inocente)', () => {
  it('var(--accent) (consumo, não definição) NÃO conta', () => {
    expect(tokenDefCount(`.btn { color: var(--accent-fg); background: var(--accent); }`)).toBe(0);
  });
  it('"@theme" dentro de comentário NÃO conta (strip de comentário)', () => {
    expect(tokenDefCount(`/* migrado pra @theme em foundations.css */ .x { color: red; }`)).toBe(0);
  });
  it('stripComments remove bloco /* */ antes da varredura', () => {
    expect(stripComments(`/* --accent: x; */ .y{}`)).not.toContain('--accent');
  });
});

describe('foundation-guard — está VIVO (não passa vacuamente em 0)', () => {
  it('o token-home real (cockpit.css + @imports DTCG, Shell na allowlist) TEM definição de token (@theme/--*) > 0', () => {
    // Se o home de token não definisse nada, o gate seria fajuto (protegeria um vazio). Sob DTCG a
    // def vive nos _generated-cockpit-*.css importados no topo do cockpit.css — por isso COMPOSTO
    // (cockpit.css + @imports), não o cru (que hoje dá 0). foundations.css também é home (allowlist)
    // e já existe no main, igualmente DTCG-composto; aqui aferimos o Shell (cockpit.css), home vigente.
    expect(tokenDefCountComposed('resources/css/cockpit.css')).toBeGreaterThan(0);
  });
  it('um bundle cowork legado real ainda redefine token (o que o ratchet congela)', () => {
    const css = readFileSync('resources/css/cowork-canon-financeiro-bundle.css', 'utf8');
    expect(tokenDefCount(css)).toBeGreaterThan(0);
  });
});
