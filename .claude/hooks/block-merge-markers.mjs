#!/usr/bin/env node
// block-merge-markers.mjs — PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1).
// BLOQUEIA Write/Edit que contenha git merge conflict marker não-resolvido.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// memory/reference/post-mortem-v4-go-live.md §anti-pattern A ("encoding silencioso"):
//   linha 24 — "Conflito merge SrsMemoryReader.php foi pra prod (parse error `<<<<<<<`)" (#1000)
//   linha 42 — "Manifestações: (...) git merge markers em arquivos PHP (#1000 + #1001)"
// Conflito não-resolvido em código = SEMPRE bug (PHP morre com `syntax error, unexpected
// token "<<"`). Por isso o modo default é strict: não existe falso-positivo legítimo em
// coluna 0 — doc que ensina o padrão cita `<<<<<<<` em backtick, nunca no início da linha.
//
// ── POR QUE .mjs (porte dos blockers restantes — chip da grade 2026-07-17) ───
// O .ps1 legado SÓ roda no Windows do Wagner: em Mac/Linux o `powershell -File` vira
// "command not found" (exit 127) e o Claude Code trata exit≠2 como não-bloqueante — ou
// seja, o blocker evapora EM SILÊNCIO. O time MCP (Felipe/Maiara/Eliana/Luiz) entra em
// Mac/Linux, e conflito de merge acontece em qualquer plataforma.
// Medido 2026-07-17 (harness da casa: JSON em arquivo + `< payload` via cmd): o .ps1
// BLOQUEIA conflito (deny) e SOLTA conteúdo limpo — este porte preserva os dois lados.
// Pattern-setter: block-automem.mjs (#4028) / block-test-fora-ct100.mjs (#4025).
//
// ── O QUE ESTE PORTE **NÃO** MUDA ───────────────────────────────────────────
// Enforcement é decisão [W], não do porte: segue strict-by-default, mesmos env de modo e
// override, mesmas isenções. Único delta de forma: bloqueio via exit 2 (convenção .mjs da
// casa) em vez de deny-JSON — os dois bloqueiam; exit 2 manda o motivo pelo stderr.
//
// Modo (env OIMPRESSO_MERGE_HOOK_MODE): strict (DEFAULT) | warn | off
// Override emergencial (Tier 0 Wagner): env OIMPRESSO_MERGE_OVERRIDE=1
// Fail-open: qualquer erro/parse-fail → exit 0 (NUNCA trava sessão).
// Selftest: node .claude/hooks/block-merge-markers.test.mjs
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

const WRITE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit']);

// Binário: conteúdo não é código; o marker seria byte solto, não conflito.
const BIN_EXT = ['.png', '.jpg', '.jpeg', '.gif', '.webp', '.ico', '.pdf', '.zip', '.tar', '.gz', '.exe', '.dll', '.so'];

// Git padrão: `<<<<<<< label`, `=======`, `>>>>>>> label`; diff3 acrescenta `||||||| base`.
// Âncora em INÍCIO DE LINHA (multiline) — é o que distingue conflito real de doc que
// explica o padrão (backtick/indentado). 7 chars exatos, como o git emite.
const MERGE_RE = /^(<{7} |={7}$|>{7} |\|{7} )/m;

/** normaliza path pra comparação (forward slash + lowercase — Windows é case-blind). */
export function normalizePath(p) {
  return String(p || '').replace(/\\/g, '/').toLowerCase();
}

/** paths isentos: binário, fixture que documenta marker, o próprio hook, o post-mortem. */
export function isExempt(pathLower) {
  if (BIN_EXT.some((e) => pathLower.endsWith(e))) return true;
  if (/\/fixtures\//.test(pathLower) || /\.fixture\./.test(pathLower)) return true;
  // self-exempt: este hook e seu teste CITAM o padrão literalmente (auto-citação).
  if (/\/\.claude\/hooks\/block-merge-markers\.(mjs|test\.mjs)$/.test(pathLower)) return true;
  // o post-mortem documenta o anti-pattern com os markers em exemplo.
  if (/\/post-mortem-v4-go-live\.md$/.test(pathLower)) return true;
  return false;
}

/** conteúdo que o tool vai gravar (Write→content, Edit→new_string, MultiEdit→todos). */
export function extractContent(toolName, toolInput) {
  const ti = toolInput || {};
  if (toolName === 'Write') return String(ti.content || '');
  if (toolName === 'Edit') return String(ti.new_string || '');
  if (toolName === 'MultiEdit') {
    return (Array.isArray(ti.edits) ? ti.edits : []).map((e) => String((e && e.new_string) || '')).join('\n');
  }
  return '';
}

/** o conteúdo carrega marker de conflito não-resolvido? */
export function hasMergeMarker(content) {
  return MERGE_RE.test(String(content || ''));
}

/** modo vigente (env). strict = default: conflito é sempre bug. */
export function currentMode(env = process.env) {
  return String(env.OIMPRESSO_MERGE_HOOK_MODE || 'strict').toLowerCase();
}

/** veredito único: 'block' | 'warn' | 'allow'. */
export function verdict(toolName, filePath, toolInput, env = process.env) {
  if (!WRITE_TOOLS.has(toolName)) return 'allow';
  if (!filePath) return 'allow';
  const mode = currentMode(env);
  if (mode === 'off') return 'allow';
  if (String(env.OIMPRESSO_MERGE_OVERRIDE || '') === '1') return 'allow';
  if (isExempt(normalizePath(filePath))) return 'allow';
  if (!hasMergeMarker(extractContent(toolName, toolInput))) return 'allow';
  return mode === 'warn' ? 'warn' : 'block';
}

export function blockMessage(toolName, filePath) {
  return `[block-merge-markers] ${toolName} em '${filePath}' contém git conflict marker não-resolvido.

Anti-pattern A do post-mortem v4 — o incidente #1000 levou '<<<<<<<' pra PROD e o PHP
morreu com: syntax error, unexpected token "<<".

Resolva o conflito ANTES de salvar: escolha 'ours'/'theirs' ou faça o merge à mão, e
remova as linhas '<<<<<<<', '|||||||', '=======', '>>>>>>>'.

Override emergencial (Tier 0): env OIMPRESSO_MERGE_OVERRIDE=1`;
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
  let tool = '';
  let path = '';
  let toolInput = {};
  try {
    const payload = JSON.parse(raw);
    tool = String((payload && payload.tool_name) || '');
    toolInput = (payload && payload.tool_input) || {};
    path = String(toolInput.file_path || '');
  } catch { process.exit(0); }        // parse-fail → fail-open
  const v = verdict(tool, path, toolInput);
  if (v === 'block') { process.stderr.write(blockMessage(tool, path) + '\n'); process.exit(2); }
  if (v === 'warn') { process.stderr.write(blockMessage(tool, path) + '\n[modo warn — prosseguindo]\n'); process.exit(0); }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-merge-markers.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
