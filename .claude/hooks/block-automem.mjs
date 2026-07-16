#!/usr/bin/env node
// block-automem.mjs — PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1).
// BLOQUEIA Write/Edit em auto-mem privada legada do Claude Code.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// ADR 0061 (zero auto-mem privada — conhecimento canônico vive em git+MCP) refinada
// pela ADR 0131 (3 tiers de memória):
//   1. CANÔNICO → git memory/ → MCP (time inteiro vê)
//   2. LOCAL    → ~/.claude/oimpresso-local/ (só este dev — escape valve legítima)
//   3. SEGREDO  → Vaultwarden vault.oimpresso.com (NUNCA arquivo plain)
// proibicoes.md §Memória/governança: "Hook block-automem BLOQUEIA Write/Edit em
// ~/.claude/projects/*/memory/*.md. Escape valves legítimas (ADR 0131): (a)
// ~/.claude/oimpresso-local/** ; (b) Vaultwarden."
//
// ── POR QUE .mjs (porte 2/N dos blockers Tier-0, SPEC US-GOV-052 / P24) ──────
// O .ps1 legado SÓ roda no Windows do Wagner. O time MCP (Felipe/Maiara/Luiz) entra
// em Mac/Linux — lá o `powershell -File` some e o blocker Tier-0 evapora em silêncio.
// A auto-mem legada existe em qualquer plataforma (~/.claude/projects/*/memory/), então
// o bloqueio TEM que ser cross-plataforma. Supersede block-automem.ps1 (pattern-setter:
// block-test-fora-ct100.mjs, PR #4025).
//
// Fail-open: qualquer erro/parse-fail → exit 0 (NUNCA trava sessão).
// Read continua permitido (ler conteúdo legado durante migração) — só Write/Edit bloqueia.
// Selftest: node .claude/hooks/block-automem.test.mjs
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

const WRITE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit']);

/** normaliza path pra comparação (forward slash + lowercase — Windows é case-blind). */
export function normalizePath(p) {
  return String(p || '').replace(/\\/g, '/').toLowerCase();
}

/** escape valve ADR 0131 tier 2 — ~/.claude/oimpresso-local/ é zona pessoal explícita. */
export function isOimpressoLocal(pathLower) {
  return /\.claude\/oimpresso-local\//.test(pathLower);
}

/** auto-mem privada legada (qualquer plataforma: C:/Users, /home, /Users, AppData). */
export function isLegacyAutomem(pathLower) {
  return (
    /\.claude\/projects\/.*?\/memory\/.*\.md/.test(pathLower) ||
    /appdata\/local\/.*\.claude.*?\/memory\//.test(pathLower) ||
    /appdata\/roaming\/.*\.claude.*?\/memory\//.test(pathLower)
  );
}

/** sugestão de destino canônico (tier 1) conforme o tipo do arquivo legado (ADR 0131). */
export function suggestCanonTarget(pathLower) {
  if (/reference[_-]/.test(pathLower)) return 'memory/requisitos/{Modulo}/RUNBOOK-tema.md OU memory/decisions/';
  if (/feedback[_-]/.test(pathLower)) return 'memory/decisions/NNNN-slug.md (ADR pq decisão) OU memory/requisitos/{Modulo}/feedback.md';
  if (/project[_-]/.test(pathLower)) return 'memory/requisitos/{Modulo}/SPEC.md OU CHANGELOG.md';
  if (/session[_-]/.test(pathLower)) return 'memory/sessions/YYYY-MM-DD-slug.md';
  if (/comparativ/.test(pathLower)) return 'memory/comparativos/slug_capterra.md';
  if (/cliente[_-]|client[_-]/.test(pathLower)) return 'memory/requisitos/{Modulo}/quirks.md OU ADR';
  return 'memory/decisions/NNNN-slug.md (ADR Nygard)';
}

/** veredito único: bloqueia? (Write/Edit em auto-mem legada, fora da escape valve). */
export function shouldBlock(toolName, filePath) {
  if (!WRITE_TOOLS.has(toolName)) return false;
  if (!filePath) return false;
  const pathLower = normalizePath(filePath);
  if (isOimpressoLocal(pathLower)) return false;
  return isLegacyAutomem(pathLower);
}

export function blockMessage(toolName, filePath) {
  const alvo = suggestCanonTarget(normalizePath(filePath));
  return `[block-automem] ${toolName} em '${filePath}' BLOQUEADO.

REGRA (ADR 0061 + 0131 — 3 tiers de memória):
  1. CANONICO (time inteiro)  -> git memory/ -> MCP. Sugestão pra este path: ${alvo}
  2. MAQUINA-LOCAL (só você)  -> ~/.claude/oimpresso-local/ (livre, fora do git)
  3. SEGREDO (token/senha)    -> Vaultwarden vault.oimpresso.com (NUNCA em arquivo plain)

Critério (1 pergunta): este fato é segredo? só seu? ou o time precisa ver?

Auto-mem em ~/.claude/projects/*/memory/ é LEGADA — em migração via skill 'automem-classify'.
NÃO criar arquivos novos lá. Read continua permitido (ler conteúdo legado durante migração).`;
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
  try {
    const payload = JSON.parse(raw);
    tool = String((payload && payload.tool_name) || '');
    path = String((payload && payload.tool_input && payload.tool_input.file_path) || '');
  } catch { process.exit(0); }        // parse-fail → fail-open
  if (shouldBlock(tool, path)) { process.stderr.write(blockMessage(tool, path) + '\n'); process.exit(2); }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-automem.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
