#!/usr/bin/env node
// mcp-first-warning.mjs — PreToolUse:Read|Glob|Grep (PORTE cross-plataforma do .ps1, advisory).
// Avisa (permissionDecision=allow — nunca bloqueia) quando Read/Glob/Grep toca memory/*
// que teria uma tool MCP equivalente (mais barata + auditada).
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// Skill mcp-first (Tier B) + how-trabalhar §"tools MCP sempre antes de Read filesystem":
// decisions-search/fetch, sessions-recent, tasks-* são auditados em mcp_audit_log e
// custam ~73% menos tokens. Filesystem só se o MCP estiver fora do ar.
// CURRENT.md/TASKS.md removidos (ADR 0070).
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o aviso
// evapora em silêncio. Supersede mcp-first-warning.ps1.
//
// ADVISORY: emite hookSpecificOutput permissionDecision=allow (informa, não corta).
// Fail-open: qualquer erro/parse-fail → exit 0 sem output. Selftest: --selftest.

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

// Patterns de memory/ que têm equivalente MCP (mesmos do .ps1 legado).
export const MCP_PATTERNS = [
  /memory\/decisions\//,
  /memory\/sessions\//,
  /memory\/requisitos\/.*\.md/,
  /memory\/comparativos\//,
  /memory\/08-handoff/,
];

/** o alvo (file_path OU pattern) casa algum path com equivalente MCP? */
export function matches(target) {
  return Boolean(target) && MCP_PATTERNS.some((re) => re.test(target));
}

/** sugestão de tool MCP pelo tipo de path (ordem importa — 1ª que casa vence). */
export function suggestFor(target) {
  const t = String(target || '');
  const adr = /memory\/decisions\/(\d+-[\w-]+)\.md/.exec(t);
  if (adr) return `decisions-fetch slug:"${adr[1]}"`;
  if (/memory\/decisions\//.test(t)) return 'decisions-search query:"..."';
  if (/memory\/sessions\//.test(t)) return 'sessions-recent limit:5';
  if (/memory\/08-handoff/.test(t)) return 'cycles-active + my-work';
  if (/memory\/requisitos\/.*SPEC\.md/.test(t)) return 'tasks-list module:<Mod> ou tasks-detail task_id:<ID>';
  return 'decisions-search ou cc-search';
}

/** objeto hookSpecificOutput (schema PreToolUse 2026 — permissionDecision=allow). */
export function buildOutput(tool, target, suggestion) {
  return {
    hookSpecificOutput: {
      hookEventName: 'PreToolUse',
      permissionDecision: 'allow',
      permissionDecisionReason: `[oimpresso-mcp-first] Voce ia ${tool} em '${target}'. Considere tool MCP '${suggestion}' - auditado em mcp_audit_log + 73% menos tokens. Filesystem so se MCP fora do ar. Ver SKILL .claude/skills/oimpresso-mcp-first/SKILL.md.`,
    },
  };
}

// ── stdin wrapper (fail-open em TUDO; SEMPRE exit 0 — advisory) ──────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  try {
    let raw;
    try { raw = await readStdin(); } catch { process.exit(0); }
    if (!raw) process.exit(0);
    let tool = '';
    let target = '';
    try {
      const p = JSON.parse(raw);
      tool = String((p && p.tool_name) || '');
      const ti = (p && p.tool_input) || {};
      target = String(ti.file_path || ti.pattern || '');
    } catch { process.exit(0); }
    if (!matches(target)) process.exit(0);
    process.stdout.write(JSON.stringify(buildOutput(tool, target, suggestFor(target))) + '\n');
    process.exit(0);
  } catch { process.exit(0); }
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./mcp-first-warning.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
