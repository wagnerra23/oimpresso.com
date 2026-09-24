#!/usr/bin/env node
// Teste do PORTE cross-plataforma commit-discipline-check.mjs (ex-.ps1). Cada caso deriva
// do CONTRATO (skill commit-discipline + ADR 0094 §5 + regras-time PII), NÃO do output do
// .ps1 legado. Advisory nasce SEMPRE exit 0 — o teste prova o CLASSIFICADOR de avisos.
//
// Rodar: node .claude/hooks/commit-discipline-check.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { isGitWriteCmd, isUnsafeForcePush, isCommit, hasPiiPattern, buildWarnings } from './commit-discipline-check.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'commit-discipline-check.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── unitários dos classificadores puros ──────────────────────────────────────────
check('isGitWriteCmd: commit/add/push', isGitWriteCmd('git commit -m x') && isGitWriteCmd('git add .') && isGitWriteCmd('git push') && !isGitWriteCmd('git status') && !isGitWriteCmd('npm test'));
check('isUnsafeForcePush: --force sem lease', isUnsafeForcePush('git push --force') === true);
check('isUnsafeForcePush: --force-with-lease é seguro', isUnsafeForcePush('git push --force-with-lease') === false);
check('isUnsafeForcePush: push normal', isUnsafeForcePush('git push') === false);
check('isCommit', isCommit('git commit -m x') === true && isCommit('git add .') === false);
check('hasPiiPattern: CPF formatado', hasPiiPattern('cliente 123.456.789-09 aqui') === true); // pii-allowlist (CPF sintetico fixture)
check('hasPiiPattern: CNPJ formatado', hasPiiPattern('12.345.678/0001-99') === true); // pii-allowlist (CNPJ sintetico fixture)
check('hasPiiPattern: sem PII', hasPiiPattern('id 12345 sem formato') === false);

// ── buildWarnings: injeta medições (sem tocar git) ───────────────────────────────
check('buildWarnings: force sem lease avisa', buildWarnings('git push --force').some((w) => /force push/i.test(w)));
check('buildWarnings: commit >300 linhas avisa', buildWarnings('git commit -m x', { insertions: 500 }).some((w) => /500 linhas/.test(w)));
check('buildWarnings: commit <=300 não avisa de tamanho', !buildWarnings('git commit -m x', { insertions: 50 }).some((w) => /linhas/.test(w)));
check('buildWarnings: PII avisa', buildWarnings('git commit -m x', { pii: true }).some((w) => /PII/.test(w)));
check('buildWarnings: add não checa diff (só commit)', buildWarnings('git add .', { insertions: 999, pii: true }).length === 0);
check('buildWarnings: commit limpo → zero avisos', buildWarnings('git commit -m x', { insertions: 10, pii: false }).length === 0);

// ── E2E: advisory SEMPRE exit 0 ──────────────────────────────────────────────────
function runHook(command, cwd) {
  return spawnSync(process.execPath, [HOOK], { input: JSON.stringify({ tool_name: 'Bash', tool_input: { command }, cwd }), encoding: 'utf8' });
}
check('E2E: force push sem lease → exit 0 + aviso stdout', (() => { const r = runHook('git push --force'); return r.status === 0 && /force push/i.test(r.stdout); })());
check('E2E: git status → exit 0 sem aviso', (() => { const r = runHook('git status'); return r.status === 0 && !r.stdout.trim(); })());
check('E2E: non-git → exit 0 silencioso', runHook('npm run build').status === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '', encoding: 'utf8' }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava sessão)', spawnSync(process.execPath, [HOOK], { input: '{lixo', encoding: 'utf8' }).status === 0);

// ── formas REAIS (2026-09-23): o hook reconhecia 51 de 5.561 commits do corpus ───────
// As regex ancoravam em `^\s*git`, e os testes acima so usavam `git commit -m x` no inicio do
// comando — a forma conveniente pro teste, 0,9% do uso real. Estes usam as formas medidas.
check('isCommit: depois de cd/add na mesma chamada', isCommit('cd /repo && git add a.md && git commit -m x') && isCommit('git add a\ngit commit -m x'));
check('isCommit: heredoc na mensagem', isCommit("git add a && git commit -q -F - <<'EOF'\nfix: x\nEOF"));
check('isCommit NEG: `git commit` dentro de heredoc nao e comando', !isCommit("cat <<'EOF'\ngit commit -m x\nEOF"));
check('isUnsafeForcePush: depois de outro comando', isUnsafeForcePush('git fetch && git push --force'));
check('isUnsafeForcePush NEG: texto da mensagem nao e push', !isUnsafeForcePush("git commit -F - <<'EOF'\nnunca use git push --force\nEOF"));
check('isGitWriteCmd: commit depois de cd', isGitWriteCmd('cd /x && git commit -m y'));

import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
const CPF_FAKE = '123.456.789-09'; // pii-allowlist (CPF sintetico fixture)
function repo() {
  const dir = mkdtempSync(join(tmpdir(), 'cdc-'));
  const g = (...a) => spawnSync('git', a, { cwd: dir, encoding: 'utf8' });
  g('init', '-q'); g('config', 'user.email', 't@t'); g('config', 'user.name', 't');
  writeFileSync(join(dir, 'base.txt'), 'base\n'); g('add', '-A'); g('commit', '-qm', 'base');
  return { dir, g };
}
const linhas = (n) => Array.from({ length: n }, (_, i) => 'linha ' + i).join('\n') + '\n';
{
  const { dir } = repo();
  writeFileSync(join(dir, 'dados.txt'), 'cliente ' + CPF_FAKE + '\n');
  const r = runHook('git add dados.txt && git commit -m x', dir);
  check('E2E PII: arquivo que o add da MESMA chamada leva e medido', r.status === 0 && /PII/.test(r.stdout));
}
{
  const { dir } = repo();
  writeFileSync(join(dir, 'dados.txt'), 'cliente ' + CPF_FAKE + '\n');
  writeFileSync(join(dir, 'outro.txt'), 'nada\n');
  const r = runHook('git add outro.txt && git commit -m x', dir);
  check('E2E PII controle: o que o add NAO leva nao conta', r.status === 0 && !/PII/.test(r.stdout));
}
{
  const { dir, g } = repo();
  writeFileSync(join(dir, 'dados.txt'), 'cliente ' + CPF_FAKE + '\n'); g('add', 'dados.txt');
  const r = runHook('cd ' + dir + ' && git commit -m x', dir);
  check('E2E PII: ja estagiado + commit depois de cd', /PII/.test(r.stdout));
}
{
  const { dir } = repo();
  writeFileSync(join(dir, 'base.txt'), 'base\ncliente ' + CPF_FAKE + '\n');
  const r = runHook('git commit -am x', dir);
  check('E2E PII: commit -a mede o working tree rastreado', /PII/.test(r.stdout));
}
{
  const { dir } = repo();
  writeFileSync(join(dir, 'grande.txt'), linhas(400));
  const r = runHook("git add -A && git commit -F - <<'EOF'\nfeat: grande\nEOF", dir);
  check('E2E TAMANHO: 400 linhas via `add -A` + heredoc avisam', /400 linhas/.test(r.stdout));
}
{
  const { dir } = repo();
  writeFileSync(join(dir, 'pequeno.txt'), linhas(10));
  const r = runHook('git add pequeno.txt && git commit -m x', dir);
  check('E2E TAMANHO controle: 10 linhas nao avisam', !/linhas/.test(r.stdout));
}
{
  const r = runHook('git add a && git commit -m x', join(tmpdir(), 'nao-existe-cdc-' + Date.now()));
  check('E2E fail-open: git falha (cwd inexistente) → exit 0 sem aviso', r.status === 0 && !r.stdout.trim());
}

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs classifica avisos (force/300/PII), advisory SEMPRE exit 0; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
