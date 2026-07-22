#!/usr/bin/env node
// Executor transacional de planos documentais aprovados. Dry-run por padrao.
// `--apply` usa git mv + relink; `--commit` grava recibos Document-Move no Git.

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { validatePlanAtRoot } from './document-relocation-adversary.mjs';

const DEFAULT_ROOT = resolve(fileURLToPath(new URL('../..', import.meta.url)));
const git = (root, args, options = {}) => execFileSync('git', ['-C', root, ...args], {
  encoding: 'utf8', maxBuffer: 64 * 1024 * 1024, ...options,
}).trim();
const posix = (value) => String(value).replaceAll('\\', '/');

const REFRESHERS = Object.freeze({
  'system-map': Object.freeze({
    command: Object.freeze(['node', 'scripts/governance/system-map.mjs']),
    outputs: Object.freeze([
      'memory/reference/PAINEL-SISTEMA.md',
      'memory/reference/ONBOARDING-AGENTE-GERADO.md',
    ]),
  }),
});

function canonicalPlan(plan) {
  const normalize = (value) => {
    if (Array.isArray(value)) return value.map(normalize);
    if (!value || typeof value !== 'object') return value;
    return Object.fromEntries(Object.entries(value)
      .filter(([key]) => key !== 'generated_at')
      .sort(([left], [right]) => left.localeCompare(right))
      .map(([key, nested]) => [key, normalize(nested)]));
  };
  return normalize(plan);
}

function refreshersFor(plan) {
  const names = plan.refresh ?? [];
  if (!Array.isArray(names)) throw new Error('refresh deve ser uma lista de regeneradores conhecidos');
  const unknown = names.filter((name) => !Object.hasOwn(REFRESHERS, name));
  if (unknown.length) throw new Error(`regenerador desconhecido no plano: ${unknown.join(', ')}`);
  return [...new Set(names)].map((name) => ({ name, ...REFRESHERS[name] }));
}

export function planDigest(plan) {
  return createHash('sha256').update(JSON.stringify(canonicalPlan(plan))).digest('hex');
}

export function commitTrailers(plan) {
  return [
    `Document-Plan-SHA256: ${planDigest(plan)}`,
    `Document-Base-SHA: ${plan.base_sha}`,
    ...plan.operations.map((op) => `Document-Move: ${op.source} => ${op.target}`),
  ];
}

export function replaceExact(text, from, to) {
  const count = text.split(from).length - 1;
  if (!count) throw new Error(`referencia nao encontrada durante relink: ${from}`);
  return { text: text.split(from).join(to), count };
}

function assertClean(root) {
  const status = git(root, ['status', '--porcelain=v1', '--untracked-files=all']);
  if (status) throw new Error(`executor exige worktree limpa; estado atual:\n${status}`);
}

function finalFile(plan, file) {
  const moved = plan.operations.find((op) => op.source.toLowerCase() === file.toLowerCase());
  return moved?.target || file;
}

function affectedPaths(plan) {
  return [...new Set([...plan.operations.flatMap((op) => [
    op.source, op.target, ...op.rewrites.flatMap((rewrite) => [rewrite.file, finalFile(plan, rewrite.file)]),
  ]), ...refreshersFor(plan).flatMap((refresher) => refresher.outputs)])];
}

function snapshot(root, plan) {
  const files = new Map();
  for (const path of affectedPaths(plan)) {
    const absolute = join(root, path);
    if (existsSync(absolute)) files.set(path, readFileSync(absolute));
  }
  return files;
}

function rollback(root, plan, before) {
  const paths = affectedPaths(plan);
  try { git(root, ['restore', '--staged', '--', ...paths]); } catch { /* best effort; worktree era limpa */ }
  for (const path of paths) {
    const absolute = join(root, path);
    if (existsSync(absolute)) rmSync(absolute, { force: true });
  }
  for (const [path, content] of before) {
    mkdirSync(dirname(join(root, path)), { recursive: true });
    writeFileSync(join(root, path), content);
  }
}

function runPostChecks(root, plan) {
  for (const op of plan.operations) {
    if (existsSync(join(root, op.source))) throw new Error(`source ainda existe apos git mv: ${op.source}`);
    if (!existsSync(join(root, op.target))) throw new Error(`target ausente apos git mv: ${op.target}`);
  }
  git(root, ['diff', '--cached', '--check']);
  const allowed = new Set(affectedPaths(plan).map((p) => p.toLowerCase()));
  const changed = git(root, ['diff', '--cached', '--name-only', '-z']).split('\0').filter(Boolean).map(posix);
  const unexpected = changed.filter((path) => !allowed.has(path.toLowerCase()));
  if (unexpected.length) throw new Error(`executor tocou paths fora do plano: ${unexpected.join(', ')}`);
  const checks = [
    ['scripts/governance/onboarding-paths-check.mjs', []],
    ['scripts/governance/system-map.mjs', ['--check']],
    ['scripts/governance/memory-health.mjs', ['--json', '--warn-only']],
  ];
  for (const [script, args] of checks) if (existsSync(join(root, script))) {
    execFileSync('node', [join(root, script), ...args], { cwd: root, encoding: 'utf8', stdio: 'pipe' });
  }
  return changed;
}

function runRefreshers(root, plan) {
  for (const refresher of refreshersFor(plan)) {
    const [command, ...args] = refresher.command;
    execFileSync(command, args, { cwd: root, encoding: 'utf8', stdio: 'pipe' });
    git(root, ['add', '--', ...refresher.outputs]);
  }
}

export function executePlan(plan, { root = DEFAULT_ROOT, apply = false, commit = false } = {}) {
  const repo = resolve(root);
  refreshersFor(plan);
  const adversary = validatePlanAtRoot(plan, repo);
  if (!adversary.safe_to_apply) return { status: 'REFUSED', applied: false, committed: false, adversary };
  const receipt = { digest: planDigest(plan), trailers: commitTrailers(plan) };
  if (!apply) return { status: 'DRY_RUN_OK', applied: false, committed: false, adversary, receipt };
  assertClean(repo);
  const before = snapshot(repo, plan);
  try {
    for (const op of plan.operations) git(repo, ['mv', '--', op.source, op.target]);
    const relinkCounts = [];
    for (const op of plan.operations) for (const rewrite of op.rewrites) {
      const path = finalFile(plan, rewrite.file);
      const absolute = join(repo, path);
      const result = replaceExact(readFileSync(absolute, 'utf8'), rewrite.from, rewrite.to);
      writeFileSync(absolute, result.text, 'utf8');
      git(repo, ['add', '--', path]);
      relinkCounts.push({ file: path, from: rewrite.from, to: rewrite.to, replacements: result.count });
    }
    runRefreshers(repo, plan);
    const changed = runPostChecks(repo, plan);
    let commitSha = null;
    if (commit) {
      const body = ['Realocacao aprovada pelo adversario documental.', '', ...receipt.trailers].join('\n');
      git(repo, ['commit', '-m', 'docs: realoca documentacao classificada [CC]', '-m', body]);
      commitSha = git(repo, ['rev-parse', 'HEAD']);
    }
    return { status: commit ? 'COMMITTED' : 'APPLIED_STAGED', applied: true, committed: commit, commit_sha: commitSha,
      adversary, receipt, changed, relinks: relinkCounts };
  } catch (error) {
    rollback(repo, plan, before);
    throw new Error(`transacao revertida: ${error.message}`);
  }
}

export function movementHistory(root = DEFAULT_ROOT) {
  const raw = git(resolve(root), ['log', '--grep=^Document-Move:', '--date=iso-strict', '--format=%H%x1f%ad%x1f%B%x1e']);
  return raw.split('\x1e').filter(Boolean).flatMap((record) => {
    const [commit, date, ...bodyParts] = record.replace(/^\s+|\s+$/g, '').split('\x1f');
    const body = bodyParts.join('\x1f');
    return [...body.matchAll(/^Document-Move:\s*(.+?)\s*=>\s*(.+)$/gm)]
      .map((match) => ({ commit, date, source: match[1], target: match[2] }));
  });
}

function selftest() {
  const tests = [];
  const check = (name, ok) => { tests.push({ name, ok }); console.log(`${ok ? '[OK]' : '[FALHA]'} ${name}`); };
  check('digest deterministico', planDigest({ a: 1 }) === planDigest({ a: 1 }));
  check('digest ignora data volatil e ordenacao de chaves', planDigest({ generated_at: 'ontem', b: 2, a: 1 }) === planDigest({ a: 1, b: 2, generated_at: 'hoje' }));
  let unknownRefreshRejected = false;
  try { affectedPaths({ operations: [], refresh: ['comando-arbitrario'] }); } catch { unknownRefreshRejected = true; }
  check('regenerador arbitrario e rejeitado', unknownRefreshRejected);
  check('outputs gerados entram no escopo transacional', affectedPaths({ operations: [], refresh: ['system-map'] }).includes('memory/reference/PAINEL-SISTEMA.md'));
  check('relink exato conta ocorrencias', replaceExact('a X b X', 'X', 'Y').count === 2);
  const fixture = mkdtempSync(join(tmpdir(), 'oimpresso-doc-executor-'));
  try {
    git(fixture, ['init', '-q']); git(fixture, ['config', 'user.email', 'selftest@example.test']); git(fixture, ['config', 'user.name', 'Selftest']);
    mkdirSync(join(fixture, 'docs')); mkdirSync(join(fixture, 'memory/reference'), { recursive: true });
    writeFileSync(join(fixture, 'README.md'), '[Guia](docs/source.md#uso)\n');
    writeFileSync(join(fixture, 'docs/source.md'), '# Guia\n\n## Uso\n\n[Porta](../memory/reference/door.md)\n');
    writeFileSync(join(fixture, 'memory/reference/door.md'), '# Porta\n');
    git(fixture, ['add', '.']); git(fixture, ['commit', '-q', '-m', 'fixture']);
    const plan = { schema_version: 2, base_sha: git(fixture, ['rev-parse', 'HEAD']), operations: [{
      source: 'docs/source.md', target: 'memory/reference/source.md',
      classification: { kind: 'how-to', owner: 'reference', lifecycle: 'active', slug: 'source', layer: 'ia-os', door: 'memory/reference/door.md' },
      confidence: 0.99, reason: 'Guia transversal realocado para a pasta de referencia canonica.',
      rewrites: [
        { file: 'README.md', kind: 'markdown-link', from: 'docs/source.md#uso', to: 'memory/reference/source.md#uso' },
        { file: 'docs/source.md', kind: 'markdown-link', from: '../memory/reference/door.md', to: './door.md' },
      ],
    }] };
    check('dry-run nao escreve', executePlan(plan, { root: fixture }).status === 'DRY_RUN_OK' && existsSync(join(fixture, 'docs/source.md')));
    const result = executePlan(plan, { root: fixture, apply: true, commit: true });
    check('git mv + relink + commit', result.status === 'COMMITTED' && !existsSync(join(fixture, 'docs/source.md')) && readFileSync(join(fixture, 'README.md'), 'utf8').includes('memory/reference/source.md#uso'));
    check('historico duravel consultavel', movementHistory(fixture).some((row) => row.source === 'docs/source.md' && row.target === 'memory/reference/source.md'));
    const canonicalBranch = git(fixture, ['branch', '--show-current']);
    git(fixture, ['switch', '-q', '-c', 'nao-fundida']);
    git(fixture, ['commit', '--allow-empty', '-q', '-m', 'movimento lateral', '-m', 'Document-Move: docs/ghost.md => memory/reference/ghost.md']);
    git(fixture, ['switch', '-q', canonicalBranch]);
    check('historico ignora branch nao fundida', !movementHistory(fixture).some((row) => row.source === 'docs/ghost.md'));
  } finally {
    if (fixture.startsWith(tmpdir())) rmSync(fixture, { recursive: true, force: true });
  }
  const failed = tests.filter((test) => !test.ok);
  console.log(`SELFTEST ${failed.length ? 'FALHOU' : 'OK'} - ${tests.length - failed.length}/${tests.length}`);
  if (failed.length) process.exit(1);
}

function main() {
  const args = process.argv.slice(2);
  const option = (name) => { const at = args.indexOf(name); return at >= 0 ? args[at + 1] : null; };
  const root = resolve(option('--root') || process.cwd());
  if (args.includes('--selftest')) return selftest();
  if (args.includes('--history')) return console.log(JSON.stringify(movementHistory(root), null, 2));
  const planPath = option('--plan');
  if (!planPath) throw new Error('uso: --plan <arquivo.json|-> [--apply] [--commit] | --history | --selftest');
  const plan = JSON.parse(readFileSync(planPath === '-' ? 0 : resolve(planPath), 'utf8'));
  console.log(JSON.stringify(executePlan(plan, { root, apply: args.includes('--apply'), commit: args.includes('--commit') }), null, 2));
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try { main(); } catch (error) { console.error(`EXECUTOR ERROR: ${error.message}`); process.exit(2); }
}
