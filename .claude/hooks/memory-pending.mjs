#!/usr/bin/env node
// memory-pending.mjs — Stop (PORTE cross-plataforma do .ps1, advisory).
// Avisa (nunca bloqueia) quando há arquivo em memory/ ou na governança raiz
// modificado/novo SEM push — sinal de que falta /sync-mem antes de encerrar.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// O webhook GitHub→MCP só sincroniza após push; esquecer de pushar = o time
// (Eliana/Felipe/Maiara/Luiz) não enxerga a mudança via tools MCP. Skill memory-sync
// + how-trabalhar §"Ao terminar uma sessão". CURRENT.md/TASKS.md removidos (ADR 0070).
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o aviso
// evapora em silêncio. Supersede memory-pending.ps1 (padrão: block-automem.mjs).
//
// ADVISORY: exit 0 SEMPRE (nunca bloqueia). Fail-open em qualquer erro.
// Selftest: node .claude/hooks/memory-pending.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

// Paths canônicos de memória/governança (mesmos do .ps1 legado).
export const CANON_PATHS = ['memory/', 'MEMORY.md', 'TEAM.md', 'CLAUDE.md', 'DESIGN.md', 'INFRA.md', 'MANUAL_CLAUDE_CODE.md'];

/** roda git status --porcelain nos paths canônicos → array de linhas não-vazias. */
export function pendingLines(cwd) {
  const r = spawnSync('git', ['status', '--porcelain', '--', ...CANON_PATHS], { encoding: 'utf8', cwd: cwd || undefined });
  return (r.stdout || '').split('\n').map((l) => l.replace(/\r$/, '')).filter((l) => l !== '');
}

/** mensagem de aviso (pura, testável) — '' se nada pendente. */
export function formatMessage(lines) {
  if (!lines || lines.length === 0) return '';
  const head = lines.slice(0, 10).map((l) => `    ${l}`);
  const extra = lines.length > 10 ? [`    ... +${lines.length - 10} outros`] : [];
  return [
    '',
    `⚠️  ${lines.length} arquivo(s) em memory/governança sem push:`,
    ...head,
    ...extra,
    '',
    '→ Rode /sync-mem antes de encerrar pra propagar pro MCP server (team Eliana/Felipe enxergam via decisions-search).',
    '',
  ].join('\n');
}

// ── stdin wrapper (fail-open em TUDO; SEMPRE exit 0 — advisory) ──────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  try {
    let raw = '';
    try { raw = await readStdin(); } catch { /* stdin opcional — o hook roda git, não depende do payload */ }
    let cwd = '';
    try { cwd = String((JSON.parse(raw || '{}') || {}).cwd || ''); } catch { /* payload opcional */ }
    const msg = formatMessage(pendingLines(cwd));
    if (msg) process.stderr.write(msg + '\n');
    process.exit(0);
  } catch { process.exit(0); }
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./memory-pending.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
