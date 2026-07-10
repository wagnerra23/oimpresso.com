#!/usr/bin/env node
// post-merge-ui-smoke-required.mjs — PostToolUse:Bash + PreToolUse:Bash|browser-MCP
// (PORTE cross-plataforma do .ps1). Smoke visual pós-merge UI obrigatório (R1).
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// proibicoes.md §"Claim sem evidência", bullet Tier 0 pós-merge UI + PROTOCOLO-WAGNER R1
// (smoke real, não narração): APÓS qualquer merge de PR que mexa em UI (.tsx/.css/
// .blade.php), Claude OBRIGATORIAMENTE abre browser MCP + screenshot ANTES de declarar
// "pronto"/"deployed"/"funcionando"/"ao vivo"/"live em prod". Sem screenshot post-deploy
// = não está pronto. Wagner (reincidente): "sempre estou tendo que fazer isso, os metodos
// de memória ainda não estão sendo garantidos".
//
// MECÂNICA (3 casos, mesmo desenho do .ps1):
//   1. PostToolUse Bash: `gh pr merge --admin` de PR que tocou UI → grava flag com
//      timestamp (tmpdir/oimpresso-ui-merge-pending.flag — mesmo nome que grade.mjs limpa).
//   2. PreToolUse browser MCP (screenshot/navigate/read_page/js/get_page_text/find):
//      Claude está olhando de verdade → limpa a flag.
//   3. PreToolUse Bash: flag existe + fresca (<5min) + comando carrega claim
//      ("pronto|deployed|funcionando|ao vivo|live em prod|smoke ok|…") → BLOQUEIA (exit 2).
// Bloqueio é legítimo pela ADR 0224: gatilho determinístico (flag mecânica + tool_name),
// não regex semântica solta — a flag só existe se um merge UI real acabou de acontecer.
//
// Escape valves: PR body `<!-- no-ui-smoke: <razão> -->` (não grava flag) ·
// env OIMPRESSO_UI_SMOKE_OVERRIDE=1 (desativa global, justifique no chat).
// Env de teste: OIMPRESSO_UI_SMOKE_FLAG=<path> isola a flag em selftest (nunca em prod).
//
// ── POR QUE .mjs (leva Tier-0 .ps1→.mjs, SPEC US-GOV-052 / P24) ──────────────
// O .ps1 só roda no Windows do Wagner ($env:TEMP nem existe no Mac/Linux do time MCP —
// lá o R1 evaporava em silêncio). Node os.tmpdir() é cross-plataforma. Mantém o fix F7
// 2026-07-08 (tool real é mcp__claude-in-chrome__* minúscula/hífen; [-_] cobre ambos).
// Supersede post-merge-ui-smoke-required.ps1 (pattern: #4025).
//
// Fail-open: qualquer erro/parse-fail/gh-fail → exit 0 (NUNCA trava sessão).
// Selftest: node .claude/hooks/post-merge-ui-smoke-required.test.mjs
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync, unlinkSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

export const FLAG_TTL_MIN = 5;

export function flagPath(env = process.env) {
  return env.OIMPRESSO_UI_SMOKE_FLAG || join(tmpdir(), 'oimpresso-ui-merge-pending.flag');
}

// ── classificadores PUROS (exportados → testáveis sem stdin/gh) ──────────────────

/** é merge admin de PR? (o gatilho do caso 1) */
export function isAdminMerge(cmd) { return /gh\s+pr\s+merge[\s\S]*--admin/.test(cmd); }

/** número do PR no comando de merge (null se não extraível). */
export function extractPrNumber(cmd) {
  const m = /gh\s+pr\s+merge\s+(\d+)/.exec(cmd);
  return m ? m[1] : null;
}

/** path é superfície UI (lista canônica da proibicoes §pós-merge UI)? */
export function isUiFile(p) {
  const f = String(p).replace(/\\/g, '/');
  return (
    /resources\/(js|css)\/.+\.(tsx?|css)$/.test(f) ||
    /resources\/views\/.+\.blade\.php$/.test(f) ||
    /Modules\/.+\/Resources\/views\/.+\.blade\.php$/.test(f)
  );
}

/** tool de browser MCP que prova que Claude está OLHANDO (caso 2 — limpa flag).
 *  F7 2026-07-08: nome real é mcp__claude-in-chrome__* (minúscula, hífen); [-_] cobre ambos. */
export function isBrowserSmokeTool(toolName) {
  const t = String(toolName || '');
  return (
    /^mcp__(computer-use|claude[-_]in[-_]chrome|Windows-MCP)__/i.test(t) &&
    /(screenshot|navigate|read_page|javascript_tool|get_page_text|find)/i.test(t)
  );
}

/** comando carrega declaração de pronto? (lista canônica: proibicoes bloqueia
 *  "pronto|deployed|funcionando|ao vivo|live em prod" + variantes catalogadas). */
export function isClaim(cmd) {
  return /pronto|deployed|funcionando|ao vivo|live em prod|confirma[cç][aã]o total|smoke ok|merge conclu[ií]do/i.test(String(cmd || ''));
}

/** parse da flag "ISO|PR". Retorna {ts, pr} ou null se corrompida. */
export function parseFlag(content) {
  const [iso, pr] = String(content || '').split('|');
  const ts = Date.parse(iso);
  return Number.isFinite(ts) ? { ts, pr: pr || '?' } : null;
}

/** flag ainda vale (idade < TTL)? */
export function flagIsFresh(content, nowMs = Date.now(), ttlMin = FLAG_TTL_MIN) {
  const f = parseFlag(content);
  return f !== null && (nowMs - f.ts) < ttlMin * 60 * 1000;
}

export function blockMessage(pr, ageSec, cmd) {
  return `[BLOCKED: Smoke visual pos-merge UI obrigatorio Tier 0 — R1]

PR #${pr} mergeado ha ${ageSec}s tocou arquivos UI (.tsx/.css/.blade.php).
Wagner regra IRREVOGAVEL (proibicoes §Claim sem evidencia, pos-merge UI):
  Apos merge UI, OBRIGATORIO browser MCP + screenshot ANTES de declarar
  'pronto/deployed/funcionando/ao vivo/live em prod/smoke ok'.

Comando bloqueado: ${cmd}

A FAZER (ordem):
  1. mcp__claude-in-chrome__navigate pra rota afetada (https://oimpresso.com/...)
  2. mcp__claude-in-chrome__* ou mcp__computer-use__screenshot
  3. Relatar o que viu no chat
  4. AI sim declarar 'pronto' / 'deployed'

Escape valve: OIMPRESSO_UI_SMOKE_OVERRIDE=1 (justifique no chat)
ou PR body com '<!-- no-ui-smoke: <razao> -->'.

Refs: memory/proibicoes.md §Claim sem evidencia · PROTOCOLO-WAGNER R1
      memory/reference/feedback-brave-mcp-primeiro-sempre.md`;
}

// ── casos (side-effects isolados; cada passo fail-open) ──────────────────────────

function handlePostMerge(cmd, flag) {
  if (!isAdminMerge(cmd)) return;
  const pr = extractPrNumber(cmd);
  if (!pr) return;
  try {
    const files = spawnSync('gh', ['pr', 'view', pr, '--json', 'files', '-q', '.files[].path'], { encoding: 'utf8' }).stdout || '';
    if (!files.split('\n').some(isUiFile)) return;
    const body = spawnSync('gh', ['pr', 'view', pr, '--json', 'body', '-q', '.body'], { encoding: 'utf8' }).stdout || '';
    if (/<!--\s*no-ui-smoke/.test(body)) return;
    writeFileSync(flag, `${new Date().toISOString()}|${pr}`);
    process.stdout.write(`[ui-smoke-required] PR #${pr} tocou UI files. Smoke browser MCP obrigatorio antes de declarar 'pronto'.\n`);
  } catch { /* gh indisponível → fail-open */ }
}

function handleClaimCheck(cmd, flag) {
  let content;
  try { content = readFileSync(flag, 'utf8'); } catch { return 0; }
  const parsed = parseFlag(content);
  if (!parsed || !flagIsFresh(content)) {
    try { unlinkSync(flag); } catch { /* */ }
    return 0;
  }
  if (!isClaim(cmd)) return 0;
  const ageSec = Math.round((Date.now() - parsed.ts) / 1000);
  process.stderr.write(blockMessage(parsed.pr, ageSec, cmd) + '\n');
  return 2;
}

// ── stdin wrapper (fail-open em TUDO) ────────────────────────────────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  let raw;
  try { raw = await readStdin(); } catch { process.exit(0); }
  if (!raw) process.exit(0);
  let payload;
  try { payload = JSON.parse(raw); } catch { process.exit(0); }
  if (process.env.OIMPRESSO_UI_SMOKE_OVERRIDE === '1') process.exit(0);

  const tool = String((payload && payload.tool_name) || '');
  const event = String((payload && payload.hook_event_name) || '');
  const cmd = String((payload && payload.tool_input && payload.tool_input.command) || '');
  const flag = flagPath();

  // Caso 1 — PostToolUse Bash: merge UI marca a flag
  if (event === 'PostToolUse' && tool === 'Bash') {
    if (cmd) handlePostMerge(cmd, flag);
    process.exit(0);
  }
  // Caso 2 — PreToolUse browser MCP: olhar de verdade limpa a flag
  if (event === 'PreToolUse' && isBrowserSmokeTool(tool)) {
    if (existsSync(flag)) { try { unlinkSync(flag); } catch { /* */ } }
    process.exit(0);
  }
  // Caso 3 — PreToolUse Bash: claim com flag fresca → bloqueia
  if (event === 'PreToolUse' && tool === 'Bash' && cmd) {
    process.exit(handleClaimCheck(cmd, flag));
  }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./post-merge-ui-smoke-required.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
