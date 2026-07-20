#!/usr/bin/env node
// commit-discipline-check.mjs — PreToolUse:Bash (PORTE cross-plataforma do .ps1).
// ADVISORY da skill Tier A commit-discipline: avisa (nunca bloqueia) em git
// commit/add/push — diff staged >300 linhas, force push sem lease, possível PII.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// Skill .claude/skills/commit-discipline/SKILL.md + ADR 0094 §5 (SoC brutal):
// 1 PR = 1 intent, ≤300 linhas, conventional commits, sem PII em código/commit.
// regras-time.md: "PIIs reais (CPF/CNPJ cliente) NUNCA em PR ou commit."
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o advisory
// Tier A evapora em silêncio. Supersede commit-discipline-check.ps1.
//
// ADVISORY: exit 0 SEMPRE. Fail-open em qualquer erro.
// Selftest: node .claude/hooks/commit-discipline-check.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

/** é git commit/add/push? (só esses ativam o check). */
export function isGitWriteCmd(cmd) {
  return /^\s*git\s+(commit|add|push)\b/.test(String(cmd || ''));
}

/** force push SEM --force-with-lease? → aviso. */
export function isUnsafeForcePush(cmd) {
  return /git\s+push\b.*--force\b/.test(cmd) && !/--force-with-lease/.test(cmd);
}

/** é git commit? (dispara os checks de diff staged). */
export function isCommit(cmd) {
  return /^\s*git\s+commit\b/.test(String(cmd || ''));
}

/** linhas inseridas no diff staged (null se não mediu). */
export function stagedInsertions(cwd) {
  const r = spawnSync('git', ['diff', '--cached', '--shortstat'], { encoding: 'utf8', cwd: cwd || undefined });
  const m = /(\d+) insertion/.exec(r.stdout || '');
  return m ? parseInt(m[1], 10) : null;
}

/** diff staged contém CPF/CNPJ formatado? (possível PII — LGPD). */
export function hasPiiPattern(text) {
  return /\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/.test(text) || /\b\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}\b/.test(text);
}

export function stagedDiffHasPii(cwd) {
  const r = spawnSync('git', ['diff', '--cached'], { encoding: 'utf8', cwd: cwd || undefined, maxBuffer: 32 * 1024 * 1024 });
  return hasPiiPattern(r.stdout || '');
}

/** avisos aplicáveis (puros exceto os medidores de git, injetáveis pro teste). */
export function buildWarnings(cmd, { insertions = null, pii = false } = {}) {
  const out = [];
  if (isUnsafeForcePush(cmd)) {
    out.push('[commit-discipline] AVISO: force push detectado SEM --force-with-lease.\n' +
      '  Best-practice: use --force-with-lease pra evitar sobrescrever trabalho de outros.');
  }
  if (isCommit(cmd)) {
    if (insertions !== null && insertions > 300) {
      out.push(`[commit-discipline] AVISO: diff staged tem ${insertions} linhas (alvo <=300).\n` +
        '  Considere dividir em PRs menores. Se for refactor amplo justificado, ok seguir.');
    }
    if (pii) {
      out.push('[commit-discipline] AVISO: POSSIVEL PII no diff (CPF/CNPJ formatado).\n' +
        '  LGPD: dados reais NUNCA em commit. Use [REDACTED] ou data fake (123.456.789-09).\n' +
        '  Se for fake (CPF invalido pra teste), seguir e OK.');
    }
  }
  return out;
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
    let cmd = '';
    let cwd = '';
    try {
      const payload = JSON.parse(raw);
      cmd = String((payload && payload.tool_input && payload.tool_input.command) || '');
      cwd = String((payload && payload.cwd) || '');
    } catch { process.exit(0); }
    if (!isGitWriteCmd(cmd)) process.exit(0);

    const measured = isCommit(cmd)
      ? { insertions: stagedInsertions(cwd), pii: stagedDiffHasPii(cwd) }
      : {};
    const warnings = buildWarnings(cmd, measured);
    if (warnings.length) process.stdout.write('\n' + warnings.join('\n\n') + '\n\n');
    process.exit(0);
  } catch { process.exit(0); }
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./commit-discipline-check.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
