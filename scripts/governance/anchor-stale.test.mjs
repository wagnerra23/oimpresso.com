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
  // 1 stale (andou@sha0) + 2 fresco (parado@sha0 pelo sha declarado, parado@0000000 pela base
  // derivada). O 3º deixou de ser `unknown` quando a base derivada entrou — de propósito.
  ok('CONTAGEM: 1 stale + 2 fresco + 0 unknown', s.anchor_stale_total === 1 && s.anchor_stale_fresco_total === 2 && s.anchor_stale_unknown_total === 0,
    `stale=${s.anchor_stale_total} fresco=${s.anchor_stale_fresco_total} unknown=${s.anchor_stale_unknown_total}`);
  ok('BASE DERIVADA: sha que não resolve, mas cujo CARIMBO está no main → medido (não some em unknown)',
    unknownReasons['US-FAKE-003'] === undefined && s.anchor_stale_via_base_derivada_total >= 1,
    `reason=${unknownReasons['US-FAKE-003']} via_base=${s.anchor_stale_via_base_derivada_total}`);
  ok('RASTRO: medir pela base NÃO apaga o motivo original', (s.anchor_stale_via_base_motivos || {}).sha_ausente >= 1,
    `motivos=${JSON.stringify(s.anchor_stale_via_base_motivos)}`);

  // ── US-GOV-058: o squash come o commit da BRANCH, não come o commit em que a LINHA entrou.
  // Os 3 casos que a base derivada tem que separar: morde · solta · não sabe.
  git('checkout -q -b lateral');
  escrever('Modules/Fake/lateral.php', '<?php // so na branch\n');
  git('add -A');
  git('commit -q -m lateral');
  const shaLateral = git('rev-parse --short=7 HEAD');
  git('checkout -q -');
  escrever('memory/requisitos/Fake/SPEC.md', [
    spec('US-FAKE-004', `\`Modules/Fake/parado.php\` · verificado@${shaLateral} (2026-07-17)`),
    spec('US-FAKE-005', `\`Modules/Fake/andou.php\` · verificado@${shaLateral} (2026-07-17)`),
  ].join('\n'));
  git('add -A');
  git('commit -q -m spec2'); // <- é ESTE commit (ancestral) que vira a base das duas
  escrever('Modules/Fake/andou.php', '<?php // v3 — mexeram DEPOIS do carimbo entrar no main\n');
  git('add -A');
  git('commit -q -m t2');

  const r2 = rodar();
  const stale2 = r2.modules.flatMap((m) => m.anchor_stale.map((x) => x.us));
  const motivos2 = Object.fromEntries(r2.modules.flatMap((m) => m.anchor_stale_unknown.map((x) => [x.us, x.reason])));
  ok('BITE: sha de branch (squash) + código andou DEPOIS do merge → stale via base derivada',
    stale2.includes('US-FAKE-005'), `stale=${JSON.stringify(stale2)} unknown=${JSON.stringify(motivos2)}`);
  ok('RELEASE: sha de branch (squash) + path parado → fresco, não stale',
    !stale2.includes('US-FAKE-004') && motivos2['US-FAKE-004'] === undefined,
    `stale=${JSON.stringify(stale2)} reason=${motivos2['US-FAKE-004']}`);
  ok('PROVENIÊNCIA: a stale registra que veio da base derivada',
    r2.modules.flatMap((m) => m.anchor_stale).find((x) => x.us === 'US-FAKE-005')?.via === 'base_derivada');

  // GUARD (fail-safe): carimbo que o pickaxe NÃO acha no histórico — é o caso do PR ainda não
  // mergeado, onde a linha existe só na árvore de trabalho. "não sei" nunca vira "fresco".
  // O carimbo tem que ser um que NUNCA esteve no histórico — o pickaxe casa a string
  // `verificado@<sha>`, então reusar um sha já commitado acharia a base do commit anterior
  // (foi o que este próprio assert pegou na 1ª escrita).
  escrever('memory/requisitos/Fake/SPEC.md',
    spec('US-FAKE-006', `\`Modules/Fake/parado.php\` · verificado@abcdef1 (2026-07-17)`) + '\n<!-- ainda não commitado -->\n');
  const r3 = rodar();
  const motivos3 = Object.fromEntries(r3.modules.flatMap((m) => m.anchor_stale_unknown.map((x) => [x.us, x.reason])));
  ok('GUARD: carimbo fora do histórico (PR não mergeado) → unknown, NUNCA "fresco"',
    motivos3['US-FAKE-006'] === 'sha_ausente' && r3.summary.anchor_stale_fresco_total === 0,
    `reason=${motivos3['US-FAKE-006']} fresco=${r3.summary.anchor_stale_fresco_total}`);
  git('checkout -q -- memory/requisitos/Fake/SPEC.md');

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
