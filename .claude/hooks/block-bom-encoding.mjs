#!/usr/bin/env node
// block-bom-encoding.mjs — PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1).
// BLOQUEIA (modo strict) / AVISA (modo warn, default) Write/Edit que reintroduza
// UTF-8 BOM (EF BB BF) em arquivo de código.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// proibicoes.md §Ambiente: "PowerShell 5.1 Set-Content -Encoding utf8 grava UTF-8
// COM BOM (...) PHP não interpreta <?php quando há BOM antes → prod CRASHA com
// 'Namespace declaration statement has to be the very first statement'."
// Incidente raiz: PR #984 (2026-05-16) — 5 arquivos (CmsController + 4 Crm/Entities)
// quebraram oimpresso.com inteiro. Post-mortem v4 go-live, anti-pattern A.
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner. O time MCP (Felipe/Maiara/Luiz) entra
// em Mac/Linux — lá o `powershell -File` some e o guard evapora em silêncio. BOM pode
// nascer em qualquer plataforma (editor, codemod, template). Supersede
// block-bom-encoding.ps1 (pattern-setter: block-automem.mjs / block-test-fora-ct100.mjs).
// Delta consciente do porte: modo strict bloqueia via exit 2 + stderr (mecanismo
// canônico dos blockers .mjs deste repo) em vez do JSON deny do .ps1 — mesma semântica.
//
// Modos (env OIMPRESSO_BOM_HOOK_MODE): warn (DEFAULT) | strict | off.
// Override emergencial Tier 0 Wagner: OIMPRESSO_BOM_OVERRIDE=1.
// Fail-open: qualquer erro/parse-fail → exit 0 (NUNCA trava sessão).
// Selftest: node .claude/hooks/block-bom-encoding.mjs --selftest
//
// Exit: 0 = continua | 2 = bloqueia (strict; stderr vira a razão pro Claude).

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

const WRITE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit']);

/** extensões que crasham com BOM (PHP) ou que tooling reclama (JS/TS/CSS/Vue). */
export const CODE_EXTS = ['.php', '.js', '.ts', '.tsx', '.jsx', '.mjs', '.cjs', '.vue', '.css', '.scss'];

/** modo efetivo a partir do env (warn default — gates novos nascem advisory). */
export function getMode(env = process.env) {
  const m = String(env.OIMPRESSO_BOM_HOOK_MODE || 'warn').toLowerCase();
  return m === 'strict' || m === 'off' ? m : 'warn';
}

/** o path é arquivo de código coberto pelo contrato? (fixtures de BOM são skip). */
export function isCoveredPath(filePath) {
  const p = String(filePath || '').replace(/\\/g, '/').toLowerCase();
  if (!p) return false;
  if (!CODE_EXTS.some((ext) => p.endsWith(ext))) return false;
  if (/\.bom\./.test(p) || /\/fixtures\/.*bom/.test(p)) return false;
  return true;
}

/** conteúdo relevante do tool call (Write=content, Edit=new_string, MultiEdit=edits[]). */
export function extractContents(toolName, toolInput) {
  if (!toolInput) return [];
  if (toolName === 'Write') return [String(toolInput.content || '')];
  if (toolName === 'Edit') return [String(toolInput.new_string || '')];
  if (toolName === 'MultiEdit' && Array.isArray(toolInput.edits)) {
    return toolInput.edits.map((e) => String((e && e.new_string) || ''));
  }
  return [];
}

/** algum conteúdo começa com U+FEFF? (ConvertFrom-Json/JSON.parse decodam EF BB BF como U+FEFF). */
export function hasBom(contents) {
  return contents.some((c) => c.length > 0 && c.charCodeAt(0) === 0xfeff);
}

/** veredito único: dispara? (código coberto + BOM presente, tool de escrita). */
export function shouldFire(toolName, toolInput) {
  if (!WRITE_TOOLS.has(toolName)) return false;
  if (!isCoveredPath(toolInput && toolInput.file_path)) return false;
  return hasBom(extractContents(toolName, toolInput));
}

export function fireMessage(toolName, filePath) {
  return `[block-bom-encoding] ${toolName} em '${filePath}' contém UTF-8 BOM (EF BB BF) no início. ` +
    `Anti-pattern A do post-mortem v4 (incidente PR #984 quebrou prod). ` +
    `PHP/JS não aceitam bytes antes de <?php / shebang. ` +
    `Remova o BOM antes do primeiro caractere ou escreva UTF-8 sem BOM ` +
    `([System.IO.File]::WriteAllText com UTF8Encoding($false) / Set-Content -Encoding utf8NoBOM). ` +
    `Override emergencial: env OIMPRESSO_BOM_OVERRIDE=1.`;
}

// ── stdin wrapper (fail-open em TUDO) ────────────────────────────────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  const mode = getMode();
  if (mode === 'off') process.exit(0);
  let raw;
  try { raw = await readStdin(); } catch { process.exit(0); }
  if (!raw) process.exit(0);
  let tool = '';
  let toolInput = null;
  try {
    const payload = JSON.parse(raw);
    tool = String((payload && payload.tool_name) || '');
    toolInput = (payload && payload.tool_input) || null;
  } catch { process.exit(0); }        // parse-fail → fail-open
  let fire = false;
  try { fire = shouldFire(tool, toolInput); } catch { process.exit(0); }
  if (!fire) process.exit(0);
  const path = String((toolInput && toolInput.file_path) || '');
  if (process.env.OIMPRESSO_BOM_OVERRIDE === '1') {
    process.stderr.write(`[block-bom-encoding] OVERRIDE ATIVO — ${tool} em '${path}' liberado.\n`);
    process.exit(0);
  }
  const msg = fireMessage(tool, path);
  if (mode === 'strict') {
    process.stderr.write(msg + '\n');
    process.exit(2);
  }
  // warn (default): avisa no stderr, NÃO barra
  process.stderr.write(msg + '\n[block-bom-encoding] modo warn — prosseguindo (strict via env OIMPRESSO_BOM_HOOK_MODE).\n');
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-bom-encoding.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
