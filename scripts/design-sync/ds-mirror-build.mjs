#!/usr/bin/env node
// ds-mirror-build.mjs — montador determinístico do colors_and_type.css do espelho a partir do git.
//
// Inverso do ds-token-diff.mjs: pega o colors_and_type.css ATUAL do espelho (o scaffold — header,
// @font-face, aliases, estilos de elemento) e, POR ESCOPO, troca o VALOR de cada token que o git
// também define pelo valor do git (resources/css/tokens/_generated-*.css). Preserva tudo o mais
// (comentários, tokens só-do-espelho/aliases, estrutura). É a perna 2 do runbook design-sync-push.md.
//
// ── POR QUE não tem workflow nem `.test.mjs` com o nome dela (medido 2026-09-13) ──
// Ela NÃO é órfã. É um transformador puro — lê o scaffold + os `_generated-*.css` e escreve
// só em stdout (zero `writeFileSync`) — e é o PASSO 1 do `ds-push.mjs`, que a invoca em
// `ds-push.mjs:76` via `node('ds-mirror-build.mjs', [scaffold, tokensDir])`. Quem a roda:
//   · CI   — `scripts/design-sync/ds-push.test.mjs`, step em `governance-script-tests.yml:631`
//            (medido 2026-09-13: a lane declara `pull_request: branches:[main]` SEM `paths:`,
//             logo o step nasce em todo PR. Se ela é required, quem responde é
//             `governance/required-checks-baseline.json` — não esta linha).
//   · à mão — `npm run ds:push` / `ds:push:write` (package.json:79-80) e o runbook
//            `.claude/runbooks/design-sync-push.md` (passos 1-3).
// O bite-test dela é o do INVOCADOR, e isso é de propósito: §5 2026-07-30 manda exercitar o
// CLI de fora, não uma função-satélite. Provado por mutação em 2026-09-13 — inserir `return line`
// logo após o `changed++` faz `ds-push.test.mjs` sair rc=1 com 2 asserts vermelhos ("dry-run
// sai 0" e "VALOR:0"); restaurado, rc=0. Um `.test.mjs` próprio seria régua paralela (LC-19).
//
// ⚠️ AS DUAS SONDAS ÓBVIAS DE ORFANDADE ERRAM NELA — não conclua "morta" a partir delas:
//   · `git grep "design-sync/ds-mirror-build"` erra porque o `ds-push` a chama por BASENAME
//     (o helper `node()` resolve contra `__dirname`) — o prefixo de diretório nunca aparece.
//   · `git grep -E "(from|require|node) .*ds-mirror-build\.mjs"` erra porque é `node(` —
//     chamada de helper, não `node` seguido de espaço.
//   A sonda que acha é o basename cru: `git grep -F "ds-mirror-build" origin/main`.
//   (Família já catalogada: em 10/09/2026 o `documentacao-page.jsx` retratou ter declarado que
//    o próprio `ds-push.mjs` "não existia", pela mesma doença — §5 2026-07-28, LC-08.)
//
// ── O que ela conserta, e por que o sentinela sozinho não basta (fato datado) ──
// `ds-mirror-drift` MEDE o drift git↔espelho; ela é o único caminho de BAIXAR esse drift.
// Em 2026-09-13, em `origin/main@4129c5e970`, o sentinela media 8 divergências contra baseline 0
// (`--color-success/warning-foreground` em light+dark, de #7043→8224b49850; `--accent-soft`,
// `--pos`, `--neg`, `--warn` em cockpit-dark, de bf28a6f0ad) e este script reconciliava
// exatamente as 8, com `ds-push` fechando VALOR:0. O número de HOJE não se lê aqui:
// `node scripts/governance/ds-mirror-drift.mjs`.
// Baixar o drift de fato é ato de [W]: `ds-push --write` reescreve o DS canônico e o passo 4
// (upload DesignSync) exige login claude.ai e opt-in explícito (ADR 0315/0328).
//
// NÃO acrescenta tokens git-only (mudança estrutural = decisão humana) — só reconcilia valores.
// Uso:
//   node scripts/design-sync/ds-mirror-build.mjs <mirror-atual.css> [tokensDir] > <reconciliado.css>
//   tokensDir default = resources/css/tokens

import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const GIT_FILES = {
  light: ['_generated-inertia-theme.css', '_generated-foundations-light.css'],
  dark: ['_generated-inertia-dark.css', '_generated-foundations-dark.css'],
  'cockpit-light': ['_generated-cockpit-light.css'],
  'cockpit-dark': ['_generated-cockpit-dark.css'],
};
const norm = (v) => v.trim().replace(/\s+/g, ' ').replace(/;+$/, '').trim();

function extractVars(body) {
  const out = new Map();
  const re = /(--[a-z0-9-]+)\s*:\s*([^;]+);/gi;
  let m;
  while ((m = re.exec(body)) !== null) out.set(m[1], norm(m[2]));
  return out;
}
function scopeOf(selector) {
  const s = selector.toLowerCase();
  const dark = s.includes('[data-theme="dark"]') || /\.dark\b/.test(s);
  const cockpit = s.includes('.cockpit');
  if (cockpit && dark) return 'cockpit-dark';
  if (cockpit) return 'cockpit-light';
  if (dark) return 'dark';
  if (s.includes(':root') || s.includes('@theme')) return 'light';
  return null;
}
function gitScope(tokensDir, scope) {
  const map = new Map();
  for (const f of GIT_FILES[scope]) {
    let css; try { css = readFileSync(join(tokensDir, f), 'utf8'); } catch { continue; }
    for (const [k, v] of extractVars(css)) map.set(k, v);
  }
  return map;
}

const [mirrorPath, tokensDir = 'resources/css/tokens'] = process.argv.slice(2);
if (!mirrorPath) { console.error('uso: node ds-mirror-build.mjs <mirror-atual.css> [tokensDir]'); process.exit(1); }
const css = readFileSync(mirrorPath, 'utf8');

let changed = 0, blocks = 0;
// Reescreve bloco a bloco: dentro de cada { }, troca o valor dos tokens que o git define nesse escopo.
const out = css.replace(/([^{}]+)\{([^{}]*)\}/g, (full, sel, body) => {
  const scope = scopeOf(sel);
  if (!scope) return full;
  blocks++;
  const git = gitScope(tokensDir, scope);
  const newBody = body.replace(/(--[a-z0-9-]+)(\s*:\s*)([^;]+)(;)/gi, (line, name, sep, val, semi) => {
    if (!git.has(name)) return line;                 // token só-do-espelho/alias → intacto
    const gv = git.get(name);
    if (norm(val) === gv) return line;               // já bate → intacto
    changed++;
    return `${name}${sep}${gv}${semi}`;              // troca só o valor; comentário pós-';' preservado
  });
  return `${sel}{${newBody}}`;
});

process.stderr.write(`ds-mirror-build: ${blocks} blocos de escopo · ${changed} valores reconciliados a partir do git\n`);
process.stdout.write(out);
