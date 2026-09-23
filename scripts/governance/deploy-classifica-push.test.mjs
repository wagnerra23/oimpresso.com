#!/usr/bin/env node
// Prova de comportamento de scripts/deploy/classifica-push.sh (usado pelo job `build`
// do deploy.yml pra escolher deploy completo × sync leve).
//
// Roda o script de verdade num repo git hermético, com um `gh` falso que simula a
// API de runs. O cenário RELEASE é o incidente de 2026-09-23: push com migration
// teve o deploy CANCELADO pela concurrency, o push seguinte não tocava runtime e foi
// pelo sync leve (sem migrate) → 500 em /financeiro/dre.
//
// Cada BITE aplica uma mutação plausível no script e exige que um caso caia.
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync, chmodSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const SCRIPT = resolve('scripts/deploy/classifica-push.sh');
const ORIGINAL = readFileSync(SCRIPT, 'utf8');
const sandbox = mkdtempSync(join(tmpdir(), 'classifica-push-'));

const gitEnv = {
  ...process.env,
  GIT_AUTHOR_NAME: 't', GIT_AUTHOR_EMAIL: 't@t', GIT_COMMITTER_NAME: 't', GIT_COMMITTER_EMAIL: 't@t',
};
const git = (...args) => execFileSync('git', args, { cwd: sandbox, env: gitEnv, encoding: 'utf8' }).trim();

function commit(msg, files, remove = []) {
  for (const [p, c] of Object.entries(files)) {
    mkdirSync(join(sandbox, p, '..'), { recursive: true });
    writeFileSync(join(sandbox, p), c);
    git('add', '--', p);
  }
  for (const p of remove) git('rm', '-q', '--', p);
  git('commit', '-q', '-m', msg);
  return git('rev-parse', 'HEAD');
}

git('init', '-q', '-b', 'main');
const base0 = commit('base deployada', { 'app/Models/X.php': '<?php // v1\n', 'README.md': '# r\n' });
const migra = commit('migration (deploy CANCELADO)', { 'database/migrations/2026_09_23_add_col.php': '<?php // add col\n' });
const soDocs = commit('push sem runtime', { 'memory/nota.md': 'nota\n', 'scripts/x.mjs': '//\n' });
const soDocs2 = commit('mais docs', { 'docs/y.txt': 'y\n' });
const tocaVolta = commit('altera runtime', { 'app/Models/X.php': '<?php // v2\n' });
const revertido = commit('reverte runtime', { 'app/Models/X.php': '<?php // v1\n' });
git('mv', 'app/Models/X.php', 'docs/X.php');
git('commit', '-q', '-m', 'rename runtime → docs');
const aposRename = git('rev-parse', 'HEAD');

// gh falso. Cada linha do fixture: "<id> <sha> <conclusion>". Honra o filtro de
// conclusion SÓ se o script pedir `select(.conclusion=="success")` no --jq — assim a
// mutação que tira o filtro faz o run cancelado virar base (e o BITE morde).
const GH = join(sandbox, 'gh-fake');
writeFileSync(GH, [
  '#!/usr/bin/env bash',
  '[ -n "${GH_FAIL:-}" ] && { echo "gh: HTTP 502" >&2; exit 1; }',
  'for a in "$@"; do case "$a" in --status) echo "gh-fake: --status proibido (índice atrasado)" >&2; exit 9;; esac; done',
  'FILTRA=0; for a in "$@"; do case "$a" in *\'select(.conclusion=="success")\'*) FILTRA=1;; esac; done',
  'while read -r ID SHA CONC; do',
  '  [ -z "$ID" ] && continue',
  '  if [ "$FILTRA" = 1 ] && [ "$CONC" != success ]; then continue; fi',
  '  echo "$ID $SHA"',
  'done < "$GH_FIXTURE"',
  '',
].join('\n'));
chmodSync(GH, 0o755);

function roda({ script = SCRIPT, sha, event = 'push', runId = '999', runs, ghFail = false }) {
  const fixture = join(sandbox, 'runs.txt');
  writeFileSync(fixture, runs.map((r) => r.join(' ')).join('\n') + '\n');
  const out = join(sandbox, 'gh_output');
  writeFileSync(out, '');
  const r = spawnSync('bash', [script.replaceAll('\\', '/')], {
    cwd: sandbox,
    encoding: 'utf8',
    env: {
      ...process.env, SHA: sha, EVENT_NAME: event, RUN_ID: runId,
      GH_BIN: './gh-fake', GH_FIXTURE: fixture.replaceAll('\\', '/'), GITHUB_OUTPUT: out.replaceAll('\\', '/'),
      ...(ghFail ? { GH_FAIL: '1' } : {}),
    },
  });
  const saida = readFileSync(out, 'utf8');
  const get = (k) => (saida.match(new RegExp(`^${k}=(.*)$`, 'm')) || [])[1];
  return { runtime: get('runtime_changed'), frontend: get('frontend_changed'), rc: r.status, log: r.stdout + r.stderr };
}

// Cenários (fixture = estado da API, mais novo primeiro).
const CASOS = [
  ['RELEASE: push-runtime cancelado → push não-runtime ⇒ DEPLOY COMPLETO', 'true',
    { sha: soDocs, runs: [['2', migra, 'cancelled'], ['1', base0, 'success']] }],
  ['CONTROLE: só não-runtime desde o último deploy OK ⇒ sync leve', 'false',
    { sha: soDocs2, runs: [['3', soDocs, 'success'], ['2', migra, 'cancelled'], ['1', base0, 'success']] }],
  ['altera+reverte runtime com base ATRASADA ⇒ completo (monotônico)', 'true',
    { sha: revertido, runs: [['4', soDocs2, 'success']] }],
  ['rename app/ → docs/ esconde remoção servida ⇒ completo', 'true',
    { sha: aposRename, runs: [['5', revertido, 'success']] }],
  ['gh falhou ⇒ fail-closed', 'true', { sha: soDocs2, ghFail: true, runs: [['1', base0, 'success']] }],
  ['nenhum sucesso ancestral ⇒ fail-closed', 'true', { sha: soDocs2, runs: [['9', aposRename, 'success']] }],
  ['workflow_dispatch ⇒ completo', 'true', { sha: soDocs2, event: 'workflow_dispatch', runs: [['3', soDocs, 'success']] }],
  ['o próprio run não serve de base', 'true', { sha: soDocs2, runId: '7', runs: [['7', soDocs2, 'success'], ['1', base0, 'success']] }],
];

let fails = 0;
const check = (nome, ok, extra = '') => { console.log(`${ok ? '✓' : '✗'} ${nome}${ok ? '' : `  ${extra}`}`); if (!ok) fails++; };

function bateria(script) {
  return CASOS.map(([nome, esperado, opts]) => {
    const r = roda({ script, ...opts });
    return { nome, ok: r.runtime === esperado && r.rc === 0, r, esperado };
  });
}

try {
  for (const { nome, ok, r, esperado } of bateria(SCRIPT)) {
    check(nome, ok, `esperado runtime_changed=${esperado}, veio ${r.runtime} (rc=${r.rc})\n${r.log}`);
  }
  const fe = roda({ sha: soDocs, runs: [['1', base0, 'success']] });
  check('frontend_changed=false quando resources/js não mudou desde a base', fe.frontend === 'false', JSON.stringify(fe));

  const mutantes = [
    ['BITE: sem filtro de conclusion (cancelado vira base)', ORIGINAL.replace('select(.conclusion=="success") | ', ''), 'RELEASE'],
    ['BITE: git diff nas pontas no lugar de git log (perde o revert)',
      ORIGINAL.replace('git log --no-renames --format= --name-only -m "${BASE}..${SHA}"', 'git diff --name-only "$BASE" "$SHA"'), 'altera+reverte'],
    ['BITE: sem --no-renames (rename esconde a remoção)', ORIGINAL.replace('git log --no-renames ', 'git log '), 'rename'],
    ['BITE: fallback vira sync leve', ORIGINAL.replace('RUNTIME_CHANGED=true\nSOBRA=""', 'RUNTIME_CHANGED=false\nSOBRA=""'), 'gh falhou'],
    ['BITE: o próprio run vira base', ORIGINAL.replace('[ -n "$RUN_ID" ] && [ "$ID" = "$RUN_ID" ] && continue', ':'), 'próprio run'],
    ['BITE: filtra status no servidor (índice atrasado)', ORIGINAL.replace('--event push --limit 100', '--event push --status success --limit 100'), 'CONTROLE'],
  ];
  for (const [nome, texto, alvo] of mutantes) {
    if (texto === ORIGINAL) { check(nome, false, 'mutação não aplicou — âncora sumiu do script'); continue; }
    const mut = join(sandbox, 'mutante.sh');
    writeFileSync(mut, texto);
    const caiu = bateria(mut).filter((c) => !c.ok).map((c) => c.nome);
    check(nome, caiu.some((n) => n.includes(alvo)), `nenhum caso "${alvo}" caiu (caíram: ${caiu.join(' | ') || 'nenhum'})`);
  }

  // Wiring: o workflow decide pelo script, não por github.event.before.
  const wf = readFileSync(resolve('.github/workflows/deploy.yml'), 'utf8');
  check('WIRING: deploy.yml chama scripts/deploy/classifica-push.sh', /bash scripts\/deploy\/classifica-push\.sh/.test(wf));
  check('WIRING: deploy.yml não decide mais por github.event.before', !/\$\{\{\s*github\.event\.before/.test(wf));
} finally {
  rmSync(sandbox, { recursive: true, force: true });
}

console.log(fails ? `\n${fails} falha(s)` : '\nOK — classificação por último deploy bem-sucedido: RELEASE + controles + 6 mutações que mordem.');
process.exit(fails ? 1 : 0);
