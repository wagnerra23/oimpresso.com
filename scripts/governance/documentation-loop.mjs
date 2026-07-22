#!/usr/bin/env node
/**
 * documentation-loop.mjs — recibo determinístico do ciclo documental.
 *
 * Este arquivo NÃO cria uma régua nova. Ele compõe três donos existentes:
 *   - memory-health.mjs                 (integridade/fatos/links)
 *   - briefing-code-staleness.mjs       (porta × código)
 *   - doc-freshness-score.mjs           (radar/priorização)
 *
 * Contrato do ciclo:
 *   snapshot ANTES -> correção no dono -> snapshot DEPOIS -> mesmo ID sumiu.
 * Tocar arquivo, aumentar parcialmente uma nota ou bumpar data não fecha recibo.
 *
 * Uso:
 *   node scripts/governance/documentation-loop.mjs --snapshot [--json]
 *   node scripts/governance/documentation-loop.mjs --compare-ref origin/main [--json]
 *   node scripts/governance/documentation-loop.mjs --compare-ref origin/main --expect <id>[,<id>]
 *   node scripts/governance/documentation-loop.mjs --selftest
 *
 * O comparativo cria worktree temporária sob os.tmpdir(), mede o ref e remove a
 * worktree no finally. Nunca edita o ref nem persiste baseline auto-declarado.
 *
 * Refs: ADR 0270 (batimento/consolidação) · ADR 0314 (higiene advisory) ·
 * scripts/governance/ZELADOR.md · memory/proibicoes.md §5 (presença ≠ correção).
 */

import { createHash } from 'node:crypto';
import { existsSync, mkdtempSync, realpathSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { scan as scanBriefing, isBriefingCoverageGap } from './briefing-code-staleness.mjs';

const ROOT = process.cwd();
const JSON_OUT = process.argv.includes('--json');
const SOURCE_PRIORITY = { 'memory-health': 0, 'briefing-code-staleness': 1, 'doc-freshness-score': 2 };

function stable(value) {
  if (Array.isArray(value)) return value.map(stable);
  if (!value || typeof value !== 'object') return value;
  return Object.fromEntries(Object.keys(value).sort().map((key) => [key, stable(value[key])]));
}

function digest(value) {
  return createHash('sha256').update(JSON.stringify(stable(value))).digest('hex').slice(0, 12);
}

function targetFromSample(sample) {
  if (sample == null) return { aggregate: true };
  if (typeof sample !== 'object') return { value: sample };
  const keys = ['file', 'link', 'doc', 'mod', 'module', 'screen', 'slug', 'id', 'indice', 'path', 'rel'];
  const picked = {};
  for (const key of keys) if (sample[key] != null) picked[key] = sample[key];
  return Object.keys(picked).length ? picked : stable(sample);
}

export function issueId(source, kind, target) {
  return `${source}:${kind}:${digest(target)}`;
}

function normalizeMemoryHealth(data) {
  const out = [];
  for (const [severity, rows] of [['fail', data.fails || []], ['warn', data.warns || []]]) {
    for (const row of rows) {
      const samples = Array.isArray(row.sample) && row.sample.length ? row.sample : [null];
      for (const sample of samples) {
        const target = targetFromSample(sample);
        out.push({
          id: issueId('memory-health', row.kind || row.check || 'unknown', target),
          source: 'memory-health', kind: row.kind || row.check || 'unknown', severity,
          target, metric: Number.isFinite(row.count) ? row.count : null,
          details: row.msg || '',
        });
      }
    }
  }
  return out;
}

function normalizeBriefing() {
  const rows = scanBriefing();
  const out = [];
  for (const row of rows.filter((item) => item.stale)) {
    const target = { mod: row.mod };
    out.push({
      id: issueId('briefing-code-staleness', 'porta-stale', target),
      source: 'briefing-code-staleness', kind: 'porta-stale', severity: 'warn', target,
      metric: row.gapDays ?? null,
      details: `${row.gapDays ?? '?'}d / ${row.commitsAhead ?? '?'} commits atrás do código`,
    });
  }
  for (const row of rows.filter((item) => isBriefingCoverageGap(item))) {
    const target = { mod: row.mod };
    out.push({
      id: issueId('briefing-code-staleness', 'porta-ausente', target),
      source: 'briefing-code-staleness', kind: 'porta-ausente', severity: 'warn', target,
      metric: 1, details: `módulo backend ${row.mod} sem BRIEFING.md`,
    });
  }
  return out;
}

function runJson(root, script, args) {
  const absolute = join(root, script);
  if (!existsSync(absolute)) throw new Error(`detector ausente: ${script} em ${root}`);
  const result = spawnSync(process.execPath, [absolute, ...args], {
    cwd: root, encoding: 'utf8', maxBuffer: 128 * 1024 * 1024,
    env: { ...process.env, GITHUB_STEP_SUMMARY: '' },
  });
  const raw = (result.stdout || '').trim();
  if (!raw) throw new Error(`${script} não produziu JSON (exit=${result.status}; stderr=${(result.stderr || '').trim().slice(0, 300)})`);
  try { return JSON.parse(raw); }
  catch { throw new Error(`${script} produziu JSON inválido: ${raw.slice(0, 300)}`); }
}

function normalizeFreshness(data) {
  return (data.docs || [])
    .filter((row) => Number(row.score) < 50)
    .map((row) => {
      const target = { doc: row.doc };
      return {
        id: issueId('doc-freshness-score', 'doc-podre', target),
        source: 'doc-freshness-score', kind: 'doc-podre', severity: 'warn', target,
        metric: Number(row.score),
        details: `score ${row.score}/100 · ${row.refsQuebradas || 0} refs quebradas · ${row.churnCommits || 0} commits de churn`,
      };
    });
}

function gitSha(root) {
  const result = spawnSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' });
  return result.status === 0 ? result.stdout.trim() : null;
}

export function sortIssues(issues) {
  return [...issues].sort((a, b) => {
    if (a.severity !== b.severity) return a.severity === 'fail' ? -1 : 1;
    const source = (SOURCE_PRIORITY[a.source] ?? 9) - (SOURCE_PRIORITY[b.source] ?? 9);
    return source || a.id.localeCompare(b.id);
  });
}

export function buildSnapshot({ sha = null, memoryHealth = {}, briefingIssues = [], freshness = {} } = {}) {
  const rawIssues = [
    ...normalizeMemoryHealth(memoryHealth),
    ...briefingIssues,
    ...normalizeFreshness(freshness),
  ];
  // O mesmo link pode aparecer repetido no sample do detector (ex.: índice que o
  // cita várias vezes). Recibo é por achado estável, não por ocorrência textual.
  const issues = sortIssues([...new Map(rawIssues.map((issue) => [issue.id, issue])).values()]);
  return {
    schema_version: 1,
    git_sha: sha,
    sources: {
      'memory-health': issues.filter((x) => x.source === 'memory-health').length,
      'briefing-code-staleness': issues.filter((x) => x.source === 'briefing-code-staleness').length,
      'doc-freshness-score': issues.filter((x) => x.source === 'doc-freshness-score').length,
    },
    issues,
  };
}

export function snapshot(root = ROOT) {
  const memoryHealth = runJson(root, 'scripts/governance/memory-health.mjs', ['--json', '--warn-only']);
  const freshness = runJson(root, 'scripts/governance/doc-freshness-score.mjs', ['--json']);
  // scanBriefing usa process.cwd() como raiz. No snapshot do checkout corrente ele
  // evita subprocesso; snapshots de ref chamam este CLI com cwd na worktree-ref.
  const briefingIssues = root === process.cwd() ? normalizeBriefing() : [];
  return buildSnapshot({ sha: gitSha(root), memoryHealth, briefingIssues, freshness });
}

export function compareSnapshots(before, after, expected = []) {
  const beforeMap = new Map((before.issues || []).map((issue) => [issue.id, issue]));
  const afterMap = new Map((after.issues || []).map((issue) => [issue.id, issue]));
  const resolved = [...beforeMap.keys()].filter((id) => !afterMap.has(id)).map((id) => beforeMap.get(id));
  const introduced = [...afterMap.keys()].filter((id) => !beforeMap.has(id)).map((id) => afterMap.get(id));
  const changed = [...beforeMap.keys()].filter((id) => afterMap.has(id))
    .map((id) => ({ id, before: beforeMap.get(id).metric, after: afterMap.get(id).metric }))
    .filter((row) => row.before !== row.after);
  const resolvedIds = new Set(resolved.map((issue) => issue.id));
  const missingExpected = expected.filter((id) => !resolvedIds.has(id));
  return {
    ok: missingExpected.length === 0,
    before_sha: before.git_sha || null,
    after_sha: after.git_sha || null,
    expected,
    missing_expected: missingExpected,
    resolved,
    introduced,
    changed,
  };
}

function parseExpected() {
  const idx = process.argv.indexOf('--expect');
  if (idx === -1 || !process.argv[idx + 1]) return [];
  return process.argv[idx + 1].split(',').map((x) => x.trim()).filter(Boolean);
}

function printSnapshot(data) {
  if (JSON_OUT) return console.log(JSON.stringify(data, null, 2));
  console.log(`\n  DOCUMENTATION LOOP — ${data.issues.length} achado(s) · sha ${data.git_sha || '—'}\n`);
  for (const issue of data.issues.slice(0, 20)) {
    console.log(`  ${issue.severity === 'fail' ? '🔴' : '🟡'} ${issue.id}`);
    console.log(`     ${issue.details}`);
  }
  if (data.issues.length > 20) console.log(`  … +${data.issues.length - 20}`);
  console.log('\n  Recibo fecha somente quando o mesmo ID desaparece no snapshot depois.\n');
}

function printComparison(data) {
  if (JSON_OUT) return console.log(JSON.stringify(data, null, 2));
  console.log(`\n  RECIBO DOCUMENTAL — ${data.before_sha || '—'} → ${data.after_sha || '—'}`);
  console.log(`  resolvidos ${data.resolved.length} · novos ${data.introduced.length} · métricas alteradas ${data.changed.length}`);
  for (const issue of data.resolved) console.log(`  ✅ ${issue.id}`);
  for (const id of data.missing_expected) console.log(`  ❌ esperado não fechou: ${id}`);
  if (!data.expected.length) {
    console.log('\n  ✓ comparativo concluído; nenhum ID foi declarado como recibo esperado.\n');
  } else {
    console.log(data.ok ? '\n  ✓ recibo comprovado pelo mesmo detector.\n' : '\n  ✗ recibo incompleto.\n');
  }
}

function selftest() {
  let failures = 0;
  const check = (name, condition) => { console.log(`  ${condition ? '[OK]' : '[FAIL]'} ${name}`); if (!condition) failures++; };
  const target = { file: 'memory/GUIA.md', link: 'morto.md' };
  const id = issueId('memory-health', 'link-quebrado', target);
  const issue = { id, source: 'memory-health', kind: 'link-quebrado', severity: 'warn', target, metric: 1, details: 'fixture' };
  const before = { git_sha: 'a', issues: [issue] };
  const unchanged = { git_sha: 'b', issues: [{ ...issue, metric: 0 }] };
  const fixed = { git_sha: 'c', issues: [] };
  const bite = compareSnapshots(before, unchanged, [id]);
  const release = compareSnapshots(before, fixed, [id]);
  check('ID é determinístico apesar da ordem das chaves', id === issueId('memory-health', 'link-quebrado', { link: 'morto.md', file: 'memory/GUIA.md' }));
  check('BITE: mudar métrica sem sumir não fecha recibo', !bite.ok && bite.missing_expected.includes(id));
  check('RELEASE: o mesmo ID ausente depois fecha recibo', release.ok && release.resolved[0]?.id === id);
  const newcomer = { ...issue, id: issueId('memory-health', 'link-quebrado', { file: 'novo.md' }) };
  const withNew = compareSnapshots(before, { git_sha: 'd', issues: [newcomer] }, [id]);
  check('Novo drift é reportado sem apagar o recibo resolvido', withNew.ok && withNew.introduced.length === 1);
  console.log(failures ? `\n  ${failures} FALHA(S)\n` : '\n  SELFTEST OK — morde, solta e preserva IDs estáveis.\n');
  return failures ? 1 : 0;
}

function withRefSnapshot(ref) {
  const tmpRoot = resolve(tmpdir());
  const worktree = mkdtempSync(join(tmpRoot, 'oimpresso-documentation-loop-'));
  const resolvedWorktree = resolve(worktree);
  if (!resolvedWorktree.startsWith(tmpRoot)) throw new Error(`worktree temporária fora de os.tmpdir(): ${resolvedWorktree}`);
  const add = spawnSync('git', ['worktree', 'add', '--detach', resolvedWorktree, ref], { cwd: ROOT, encoding: 'utf8' });
  if (add.status !== 0) throw new Error(`não foi possível abrir ref ${ref}: ${(add.stderr || add.stdout || '').trim()}`);
  try {
    const script = realpathSync(fileURLToPath(import.meta.url));
    const result = spawnSync(process.execPath, [script, '--snapshot', '--json'], {
      cwd: resolvedWorktree, encoding: 'utf8', maxBuffer: 128 * 1024 * 1024,
    });
    if (result.status !== 0 || !(result.stdout || '').trim()) {
      throw new Error(`snapshot do ref ${ref} falhou: ${(result.stderr || result.stdout || '').trim().slice(0, 500)}`);
    }
    return JSON.parse(result.stdout);
  } finally {
    spawnSync('git', ['worktree', 'remove', '--force', resolvedWorktree], { cwd: ROOT, encoding: 'utf8' });
  }
}

function main() {
  if (process.argv.includes('--selftest')) return selftest();
  const compareIdx = process.argv.indexOf('--compare-ref');
  if (compareIdx !== -1) {
    const ref = process.argv[compareIdx + 1];
    if (!ref) throw new Error('--compare-ref exige um ref git');
    const before = withRefSnapshot(ref);
    const after = snapshot();
    const comparison = compareSnapshots(before, after, parseExpected());
    printComparison(comparison);
    return comparison.ok ? 0 : 1;
  }
  const data = snapshot();
  printSnapshot(data);
  return 0;
}

const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();

if (isMain) {
  try { process.exit(main()); }
  catch (error) { console.error(`documentation-loop: ${error.message}`); process.exit(1); }
}
