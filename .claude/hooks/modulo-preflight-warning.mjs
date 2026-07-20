#!/usr/bin/env node
// modulo-preflight-warning.mjs — PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1, advisory).
// AVISA (não bloqueia) quando Edit/Write em Modules/<X>/ sem ter lido o briefing do módulo X nesta sessão.
// FASE 1 PRÉ-FLIGHT da Regra Primária Tier 0 (proibicoes.md). Par da skill preflight-modulo (ADR 0225).
// Supersede modulo-preflight-warning.ps1 (US-GOV-052, triagem #17, lote C). ADVISORY: exit 0 sempre; fail-open.
// Selftest: node .claude/hooks/modulo-preflight-warning.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';
import { homedir } from 'node:os';
import { join } from 'node:path';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';

const WRITE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit']);
const BACKSLASH = String.fromCharCode(92); // evita literal duplo-escape frágil em heredoc

/** normaliza separadores pra forward-slash (Windows manda Modules\X\...). */
export function toFwd(p) {
  return String(p || '').split(BACKSLASH).join('/');
}

/** extrai o nome do módulo de um path Modules/<X>/... (ou null). */
export function extractModule(filePath) {
  const m = /Modules\/([A-Z][A-Za-z0-9]*)\//.exec(toFwd(filePath));
  return m ? m[1] : null;
}

/** patterns que indicam leitura do briefing do módulo na sessão. */
export function buildReadPatterns(moduleName) {
  const lower = moduleName.toLowerCase();
  return [
    `memory/requisitos/${moduleName}/`,
    `Modules/${moduleName}/README`,
    `${lower}.*charter`,
    `${lower}.*spec`,
    `decisions-search.*${lower}`,
    `como-integrar.*${lower}`,
  ];
}

/** o transcript mostra evidência de leitura do briefing do módulo? */
export function hasReadEvidence(content, moduleName) {
  if (!content) return false;
  return buildReadPatterns(moduleName).some((p) => new RegExp(p, 'i').test(content));
}

export function warningMessage(moduleName) {
  const lower = moduleName.toLowerCase();
  return `
⚠️  PRÉ-FLIGHT MISSING — Edit/Write em Modules/${moduleName}/ sem ter lido briefing do módulo nesta sessão.

Regra primária Tier 0 (memory/proibicoes.md): FASE 1 PRÉ-FLIGHT obrigatória ANTES de Edit em Modules/<X>/.
Leia ANTES: memory/requisitos/${moduleName}/SPEC.md · RUNBOOK*.md · CAPTERRA*.md · charter · decisions-search "${lower}".
(Hook é AVISO, não bloqueia — Edit prossegue, mas você foi informado.)`;
}

/** deriva a chave do projeto pro dir de transcript. O Claude Code sanitiza o path
 *  do projeto trocando \ : . / por '-' (ex.: D:\oimpresso.com → D--oimpresso-com).
 *  O .ps1 legado só trocava \ e : (bug: nunca casava o dir real no Windows); o porte
 *  corrige — e o caminho primário segue sendo payload.transcript_path (mais robusto). */
export function projectKey(projectDir) {
  return String(projectDir || '').split(BACKSLASH).join('-').split(':').join('-')
    .split('.').join('-').split('/').join('-').replace(/-+$/, '');
}

/** conteúdo do transcript: preferir payload.transcript_path; senão o mais recente no dir. */
export function readTranscript(transcriptPath, env = process.env) {
  if (transcriptPath && existsSync(transcriptPath)) {
    try { return readFileSync(transcriptPath, 'utf8'); } catch { return ''; }
  }
  const dir = join(homedir(), '.claude', 'projects', projectKey(env.CLAUDE_PROJECT_DIR || process.cwd()));
  if (!existsSync(dir)) return '';
  let newest = null;
  let newestMs = -1;
  try {
    for (const f of readdirSync(dir)) {
      if (!f.endsWith('.jsonl')) continue;
      const p = join(dir, f);
      const ms = statSync(p).mtimeMs;
      if (ms > newestMs) { newestMs = ms; newest = p; }
    }
  } catch { return ''; }
  if (!newest) return '';
  try { return readFileSync(newest, 'utf8'); } catch { return ''; }
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
    let tool = '', path = '', tp = '';
    try {
      const p = JSON.parse(raw);
      tool = String((p && p.tool_name) || '');
      path = String((p && p.tool_input && p.tool_input.file_path) || '');
      tp = String((p && p.transcript_path) || '');
    } catch { process.exit(0); }
    if (!WRITE_TOOLS.has(tool) || !path) process.exit(0);
    const mod = extractModule(path);
    if (!mod) process.exit(0);
    if (hasReadEvidence(readTranscript(tp), mod)) process.exit(0);
    process.stderr.write(warningMessage(mod) + '\n');
    process.exit(0);
  } catch { process.exit(0); }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./modulo-preflight-warning.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
