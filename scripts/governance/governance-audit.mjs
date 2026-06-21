#!/usr/bin/env node
// governance-audit.mjs — AGREGADOR da bateria de sentinelas (auditoria de sentinelas
// 2026-06-20). Resolve o buraco "não tem um botão": antes a verdade da governança
// vivia espalhada em ~8 comandos soltos. Aqui é UM comando → UM scorecard.
//
// Roda a bateria inteira e devolve {ok, results[]}. Cobre os sentinelas Node
// (determinísticos, sem infra) + os health-checks PHP (best-effort: skip gracioso
// se php/app não bootar — eles têm enforcement próprio via cron prod + bite-tests).
//
// EXIT CODE só morde nos sentinelas `required` DETERMINÍSTICOS (node core), pra ser
// confiável em qualquer ambiente (CI, dev, prod). Os PHP entram como advisory aqui
// porque dependem de DB/Langfuse de prod — a mordida real deles é o cron + Pest.
//
// USO (na raiz do repo):
//   node scripts/governance/governance-audit.mjs            # tabela
//   node scripts/governance/governance-audit.mjs --json     # scorecard JSON (Daily Brief)
//   node scripts/governance/governance-audit.mjs --node-only # pula os PHP
//
// Node puro (spawnSync). Sem deps.

import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const JSON_OUT = process.argv.includes('--json');
const NODE_ONLY = process.argv.includes('--node-only');
const stripAnsi = (s) => s.replace(/\x1b\[[0-9;]*m/g, '');

// runtime: node = process.execPath <script>; php = php artisan <cmd>
// kind: required (entra no exit) | advisory (só reporta)
const BATTERY = [
  { id: 'memory-health',       runtime: 'node', kind: 'required', cmd: ['scripts/governance/memory-health.mjs', '--json'] },
  { id: 'gate-selftest',       runtime: 'node', kind: 'required', cmd: ['scripts/governance/gate-selftest.mjs', '--json'] },
  { id: 'integrity-check',     runtime: 'node', kind: 'advisory', cmd: ['prototipo-ui/integrity-check.mjs'] },
  { id: 'knowledge-drift',     runtime: 'node', kind: 'advisory', cmd: ['scripts/governance/knowledge-drift.mjs', '--check'] },
  { id: 'ds-guard',            runtime: 'node', kind: 'advisory', cmd: ['prototipo-ui/ds-guard.mjs', '--all'] },
  { id: 'plan-health',         runtime: 'node', kind: 'advisory', cmd: ['scripts/governance/plan-health.mjs', '--json'] },
  // PHP health-checks: advisory aqui (dependem de infra prod) — enforcement = cron + Pest bite-tests.
  { id: 'jana:plan-drift',     runtime: 'php',  kind: 'advisory', cmd: ['jana:plan-drift', '--json'] }, // ADR 0294 Onda 2 — drift status-do-plano ≠ tasks MCP (par do plan-health node)
  { id: 'jana:health-check',   runtime: 'php',  kind: 'advisory', cmd: ['jana:health-check', '--json'] },
  { id: 'jana:system-audit',   runtime: 'php',  kind: 'advisory', cmd: ['jana:system-audit', '--json'] },
  { id: 'jana:validate-memory',runtime: 'php',  kind: 'advisory', cmd: ['jana:validate-memory', '--json'] },
  { id: 'mem:audit',           runtime: 'php',  kind: 'advisory', cmd: ['mem:audit', '--candidates-only'] },
];

function run(entry) {
  if (entry.runtime === 'node') {
    const scriptPath = join(ROOT, entry.cmd[0]);
    if (!existsSync(scriptPath)) return { status: 'skip', summary: `script ausente: ${entry.cmd[0]}` };
    const r = spawnSync(process.execPath, [scriptPath, ...entry.cmd.slice(1)], {
      cwd: ROOT, encoding: 'utf8', env: { ...process.env, GITHUB_STEP_SUMMARY: '' },
    });
    return interpret(entry, r);
  }
  // php
  if (NODE_ONLY) return { status: 'skip', summary: '--node-only' };
  // shell no Windows pra resolver php.bat (Herd); Linux/CI tem php binário no PATH.
  const r = spawnSync('php', ['artisan', ...entry.cmd], {
    cwd: ROOT, encoding: 'utf8', shell: process.platform === 'win32',
  });
  if (r.error) return { status: 'skip', summary: `php indisponível (${r.error.code || r.error.message})` };
  return interpret(entry, r);
}

function interpret(entry, r) {
  const out = stripAnsi((r.stdout || '') + (r.stderr || ''));
  // Prefere o campo ok do JSON quando o sentinela o emite; senão usa exit code.
  let ok = r.status === 0;
  let summary = '';
  const jStart = out.indexOf('{');
  if (jStart !== -1) {
    try {
      const j = JSON.parse(out.slice(jStart));
      if (typeof j.ok === 'boolean') ok = j.ok;
      if (Array.isArray(j.fails) || Array.isArray(j.warns)) {
        summary = `${(j.fails || []).length} fail · ${(j.warns || []).length} warn`;
      } else if (Array.isArray(j.cases)) {
        const bad = j.cases.filter((c) => !c.ok).length;
        summary = `${j.cases.length} casos · ${bad} falhos`;
      } else if (Array.isArray(j.checks)) {
        const bad = j.checks.filter((c) => !c.ok && !(c.advisory)).length;
        summary = `${j.checks.length} checks · ${bad} duros falhos`;
      } else if (Array.isArray(j.findings)) {
        summary = `${j.planos ?? '?'} planos · ${j.fail ?? 0} fail · ${j.warn ?? 0} warn`;
      }
    } catch { /* não era JSON — cai no fallback */ }
  }
  if (!summary) {
    const lines = out.split('\n').map((l) => l.trim()).filter(Boolean);
    summary = (lines[lines.length - 1] || '').slice(0, 90);
  }
  return { status: ok ? 'pass' : 'fail', summary, exit: r.status };
}

const results = BATTERY.map((e) => ({ id: e.id, kind: e.kind, runtime: e.runtime, ...run(e) }));

// Exit morde só nos required determinísticos que falharam (não skip).
const blocking = results.filter((r) => r.kind === 'required' && r.status === 'fail');
const ok = blocking.length === 0;

if (JSON_OUT) {
  console.log(JSON.stringify({ ok, ran_at: new Date().toISOString(), results }, null, 2));
  process.exit(ok ? 0 : 1);
}

const icon = { pass: '✅', fail: '❌', skip: '⊘' };
console.log(`\n  GOVERNANCE-AUDIT — bateria de sentinelas (${results.length} sentinelas)\n`);
for (const r of results) {
  const tag = r.kind === 'required' ? 'REQ' : 'adv';
  console.log(`  ${icon[r.status]} ${r.id.padEnd(22)} [${tag}] ${r.summary}`);
}
console.log('');
if (!ok) {
  console.error(`  ✗ ${blocking.length} sentinela(s) REQUIRED falharam: ${blocking.map((r) => r.id).join(', ')}\n`);
  process.exit(1);
}
console.log(`  ✓ core determinístico verde. (sentinelas PHP são best-effort — verdade de prod via cron + Pest.)\n`);
process.exit(0);
