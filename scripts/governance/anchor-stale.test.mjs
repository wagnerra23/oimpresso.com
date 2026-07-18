#!/usr/bin/env node
// Teste do 5º eixo da âncora — TEMPORAL (`verificado@sha` × HEAD · US-GOV-055 · chip C8).
//
// ÂNCORA EXTERNA (não derivado do código — proibicoes §5 anti-tautológico):
//   SPEC memory/requisitos/Governance/SPEC.md US-GOV-055 DoD D.1/D.2/D.3 — "âncora cujo(s)
//   path(s) foram tocados entre o `verificado@<sha>` e o HEAD vira `anchor_stale`; âncora sem
//   movimento no path NÃO vira; e todo caso ambíguo (sha ausente / não-ancestral / checkout
//   shallow) vira `unknown`, NUNCA `fresco`". Gramática do campo: ADR 0273 §1.
//   Origem: grade de réguas 2026-07-17 — o SHA era exigido pela gramática, capturado em
//   2 arquivos/5 sites e usado só como PRESENÇA (deveFecharPorAncora); ninguém comparava com HEAD.
//
// Roda o script REAL como subprocess contra um REPO GIT DE VERDADE (comportamento, não presença;
// fixture em memória provaria só o matcher). Cada caso tem seu par bite/release — sem
// controle-negativo um eixo quebrado passa verde "por não medir nada", que foi como o harness
// deste próprio chip mentiu 2× em 2026-07-17 (split que não dividia + `2>/dev/null` no cmd.exe).
//
// @covers-us US-GOV-055
import { execFileSync, execSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const LINT = join(dirname(fileURLToPath(import.meta.url)), 'anchor-lint.mjs');
let fails = 0;
const ok = (nome, cond, extra = '') => {
  console.log(`  ${cond ? '[OK]' : '[FAIL]'} ${nome}${cond ? '' : ` ${extra}`}`);
  if (!cond) fails++;
};

const repo = mkdtempSync(join(tmpdir(), 'anchor-stale-'));
const git = (args) => execSync(`git ${args}`, { cwd: repo, stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
const escrever = (rel, txt) => { mkdirSync(join(repo, dirname(rel)), { recursive: true }); writeFileSync(join(repo, rel), txt); };

function spec(us, anchor) {
  return `---\nmodule: Fake\n---\n\n### ${us} · caso de teste\n\n**DoD:** o eixo temporal responde.\n\n**Implementado em:** ${anchor}\n`;
}
function rodar() {
  const out = execFileSync(process.execPath, [LINT, '--stale', '--json'], { cwd: repo, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  return JSON.parse(out);
}

try {
  git('init -q');
  git('config user.email teste@oimpresso.local');
  git('config user.name teste');
  git('config commit.gpgsign false');

  // t0: dois arquivos nascem. O `parado.php` NUNCA mais é tocado (o controle-negativo).
  escrever('Modules/Fake/andou.php', '<?php // v1\n');
  escrever('Modules/Fake/parado.php', '<?php // congelado\n');
  git('add -A');
  git('commit -q -m t0');
  const sha0 = git('rev-parse --short=7 HEAD');

  // t1: SÓ o andou.php muda. A partir daqui, âncora em andou.php@sha0 está stale; em parado.php@sha0 não.
  escrever('Modules/Fake/andou.php', '<?php // v2 — mexeram aqui depois da verificacao\n');
  git('add -A');
  git('commit -q -m t1');

  escrever('memory/requisitos/Fake/SPEC.md', [
    spec('US-FAKE-001', `\`Modules/Fake/andou.php\` · verificado@${sha0} (2026-07-17)`),
    spec('US-FAKE-002', `\`Modules/Fake/parado.php\` · verificado@${sha0} (2026-07-17)`),
    spec('US-FAKE-003', `\`Modules/Fake/parado.php\` · verificado@0000000 (2026-07-17)`),
  ].join('\n'));
  git('add -A');
  git('commit -q -m spec');

  const r = rodar();
  const s = r.summary;
  const staleUs = r.modules.flatMap((m) => m.anchor_stale.map((x) => x.us));
  const unknownReasons = Object.fromEntries(r.modules.flatMap((m) => m.anchor_stale_unknown.map((x) => [x.us, x.reason])));

  ok('BITE: path tocado depois do verificado@sha → anchor_stale', staleUs.includes('US-FAKE-001'), `stale=${JSON.stringify(staleUs)}`);
  ok('RELEASE: path NÃO tocado desde o verificado@sha → não é stale', !staleUs.includes('US-FAKE-002'), `stale=${JSON.stringify(staleUs)}`);
  ok('CONTAGEM: exatamente 1 stale + 1 fresco', s.anchor_stale_total === 1 && s.anchor_stale_fresco_total === 1,
    `stale=${s.anchor_stale_total} fresco=${s.anchor_stale_fresco_total}`);
  ok('GUARD: sha inexistente → unknown/sha_ausente (NUNCA "fresco")', unknownReasons['US-FAKE-003'] === 'sha_ausente',
    `reason=${unknownReasons['US-FAKE-003']}`);
  ok('GUARD: unknown NÃO é contado como fresco', s.anchor_stale_fresco_total === 1 && s.anchor_stale_unknown_total === 1,
    `fresco=${s.anchor_stale_fresco_total} unknown=${s.anchor_stale_unknown_total}`);

  // GUARD do squash-merge: sha que EXISTE mas não é ancestral do HEAD (commit de branch
  // que o merge comeu). `git log <sha>..HEAD` ali não erra — MENTE (mede desde o merge-base).
  git('checkout -q -b lateral');
  escrever('Modules/Fake/lateral.php', '<?php // so na branch\n');
  git('add -A');
  git('commit -q -m lateral');
  const shaLateral = git('rev-parse --short=7 HEAD');
  git('checkout -q -');
  escrever('memory/requisitos/Fake/SPEC.md', spec('US-FAKE-004', `\`Modules/Fake/parado.php\` · verificado@${shaLateral} (2026-07-17)`));
  git('add -A');
  git('commit -q -m spec2');
  const r2 = rodar();
  const motivos2 = Object.fromEntries(r2.modules.flatMap((m) => m.anchor_stale_unknown.map((x) => [x.us, x.reason])));
  ok('GUARD: sha fora da ancestralidade (squash-merge) → unknown, não "fresco"',
    motivos2['US-FAKE-004'] === 'sha_fora_da_ancestralidade', `reason=${motivos2['US-FAKE-004']}`);
  ok('GUARD: nesse estado NADA é declarado fresco', r2.summary.anchor_stale_fresco_total === 0,
    `fresco=${r2.summary.anchor_stale_fresco_total}`);

  // INVARIANTE fs-puro do caminho REQUIRED: sem --stale o eixo não roda e o exit não muda.
  const semFlag = JSON.parse(execFileSync(process.execPath, [LINT, '--json'], { cwd: repo, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }));
  ok('INVARIANTE: sem --stale o eixo fica desligado (anchor_stale_on=false, totais null)',
    semFlag.summary.anchor_stale_on === false && semFlag.summary.anchor_stale_total === null);
  ok('INVARIANTE: --stale NÃO altera anchor_coverage (é sinal, não veredito)',
    semFlag.summary.anchor_coverage_pct === r2.summary.anchor_coverage_pct,
    `${semFlag.summary.anchor_coverage_pct} vs ${r2.summary.anchor_coverage_pct}`);
} finally {
  rmSync(repo, { recursive: true, force: true });
}

console.log(fails
  ? `\n  ${fails} FALHA(S) — o eixo temporal da âncora não está honesto.\n`
  : `\n  OK — morde (código andou), solta (código parado), e todo caso ambíguo vira unknown em vez de "fresco".\n`);
process.exit(fails ? 1 : 0);
