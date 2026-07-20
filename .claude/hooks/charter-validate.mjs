#!/usr/bin/env node
// charter-validate.mjs — PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1, advisory).
// AVISA (strict opcional bloqueia) ao Editar Pages/<Mod>/<Tela>.tsx que TEM `.charter.md` vivo
// sem ter chamado charter-fetch antes — reflexo Charter > Spec.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// Princípio #3 da Constituição V2 (Charter > Spec — ADR 0094/0101). Skill charter-first.
// O adversário 2026-07-20 REFUTOU a aposentadoria: block-mwart cobre RUNBOOK, block-ancora
// cobre PNG — NENHUM cobre "editou a Page sem ler o charter" (personas-resolve declara este
// hook como bind de enforcement). Por isso PORTAR, não deletar.
//
// ── POR QUE .mjs (US-GOV-052) ─ o .ps1 só roda no Windows; no Mac/Linux o aviso evapora.
// Supersede charter-validate.ps1 + charter-validate.sh (gêmeo).
// ADVISORY default (allow). Strict (env CHARTER_VALIDATE_STRICT=1) → deny. Fail-open.
// Selftest: node .claude/hooks/charter-validate.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';
import { existsSync, readFileSync } from 'node:fs';

const WRITE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit']);
const BACKSLASH = String.fromCharCode(92);
const EXEMPT_MOD = new Set(['_Showcase', '_components', '_internal']);
const EXEMPT_TELA = new Set(['App', 'Layout']);

export function toFwd(p) { return String(p || '').split(BACKSLASH).join('/'); }

/** {modulo, tela} se é uma Page .tsx elegível (top-level ou 1 subdir), senão null. */
export function matchPage(filePath) {
  const m = /resources\/js\/Pages\/([^/_][^/]*)\/(?:[^/]+\/)?([A-Za-z][A-Za-z0-9]*)\.tsx$/.exec(toFwd(filePath));
  if (!m) return null;
  const modulo = m[1];
  const tela = m[2];
  if (EXEMPT_MOD.has(modulo)) return null;
  if (tela.startsWith('_') || EXEMPT_TELA.has(tela)) return null;
  return { modulo, tela };
}

/** caminho do charter irmão (<path sem .tsx>.charter.md). */
export function charterPathFor(filePath) {
  return String(filePath).slice(0, -4) + '.charter.md';
}

/** lê o `status:` do frontmatter do charter (primeiras ~30 linhas). */
export function readCharterStatus(charterPath) {
  try {
    const head = readFileSync(charterPath, 'utf8').split('\n').slice(0, 30);
    for (const ln of head) {
      const m = /^status:\s*(\S+)/.exec(ln);
      if (m) return m[1].replace(/['"]/g, '').trim();
    }
  } catch { /* fall through */ }
  return 'unknown';
}

export function buildOutput({ tool, pathFwd, charterRelative, charterStatus, strict }) {
  let msg = `[charter-first] ${tool} em '${pathFwd}' — esta tela TEM contrato vivo em '${charterRelative}' (status: ${charterStatus}). ` +
    `Constituição V2 #3 (Charter > Spec — ADR 0094/0101): chame a tool MCP charter-fetch ANTES de editar ` +
    `pra carregar Mission/Goals/Non-Goals/UX targets/Anti-hooks. Skill charter-first. `;
  if (strict) {
    msg += 'Modo STRICT (env CHARTER_VALIDATE_STRICT=1) — Edit BLOQUEADO.';
    return { hookSpecificOutput: { hookEventName: 'PreToolUse', permissionDecision: 'deny', permissionDecisionReason: msg } };
  }
  msg += 'Modo warning (P1 — vira bloqueante quando ROI provado em >=5 sessões).';
  return { hookSpecificOutput: { hookEventName: 'PreToolUse', permissionDecision: 'allow', permissionDecisionReason: msg } };
}

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
    let tool = '', path = '';
    try {
      const p = JSON.parse(raw);
      tool = String((p && p.tool_name) || '');
      path = String((p && p.tool_input && p.tool_input.file_path) || '');
    } catch { process.exit(0); }
    if (!WRITE_TOOLS.has(tool) || !path) process.exit(0);
    if (!matchPage(path)) process.exit(0);
    const charterPath = charterPathFor(path);
    if (!existsSync(charterPath)) process.exit(0);
    const out = buildOutput({
      tool, pathFwd: toFwd(path), charterRelative: toFwd(charterPath),
      charterStatus: readCharterStatus(charterPath), strict: process.env.CHARTER_VALIDATE_STRICT === '1',
    });
    process.stdout.write(JSON.stringify(out) + '\n');
    process.exit(0);
  } catch { process.exit(0); }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./charter-validate.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
