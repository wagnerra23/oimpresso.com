#!/usr/bin/env node
// block-serving-branch-switch.mjs — PreToolUse:Bash (PORTE cross-plataforma do .ps1).
// BLOQUEIA troca de branch no checkout MAIN que serve o oimpresso.test (Herd).
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// R8 (PROTOCOLO-WAGNER-SEMPRE) + ADR 0233: trabalho de feature vai em worktree
// isolado (.claude/worktrees/*). Trocar a branch no checkout MAIN muda o que o
// cliente vê no oimpresso.test e quebra código-x-banco (erro da sessão 2026-05-29).
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner. O time MCP (Felipe/Maiara/Luiz) entra
// em Mac/Linux — lá o `powershell -File` some e o blocker R8 evapora em silêncio.
// Um checkout main "que serve" existe em qualquer plataforma (Herd Mac inclusive).
// Supersede block-serving-branch-switch.ps1 (pattern-setter: block-automem.mjs).
//
// Fail-open: qualquer erro/parse-fail → exit 0 (NUNCA trava sessão).
// Escape valve: incluir 'serving-branch-override' no comando (Wagner aprovou).
// Selftest: node .claude/hooks/block-serving-branch-switch.mjs --selftest
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

/** escape valve explícito (Wagner aprovou trocar branch no main desta vez). */
export function hasOverride(cmd) { return /serving-branch-override/.test(cmd); }

/** é uma TROCA de branch? (git switch <x>, git switch -c, git checkout -b, git checkout <ref>). */
export function isBranchSwitch(cmd) {
  if (/git\s+checkout\s+--(\s|$)/.test(cmd)) return false; // restaurar arquivo, não troca
  return (
    /git\s+switch\s+\S/.test(cmd) ||
    /git\s+checkout\s+-b\s/.test(cmd) ||
    /git\s+checkout\s+(?!--)(?!-)\S/.test(cmd)
  );
}

/** caminho efetivo do comando: 'cd <path>' explícito no comando OU cwd do payload. */
export function effectivePath(cmd, cwd) {
  const m = /cd\s+"?([A-Za-z]:[\\/][^"&;|]+|\/[^"&;|]+)"?/.exec(cmd || '');
  const p = (m && m[1].trim()) || String(cwd || '');
  return p.replace(/\\/g, '/').replace(/\/+$/, '');
}

/** worktree linkado (.claude/worktrees/*) → é o lugar certo de trabalhar. */
export function isLinkedWorktree(pathNorm) {
  return /\.claude\/worktrees\//.test(pathNorm);
}

/** checkout MAIN (raiz que serve o Herd): path termina em oimpresso.com. */
export function isServingCheckout(pathNorm) {
  return /oimpresso\.com$/.test(pathNorm);
}

/** veredito único: bloqueia? (troca de branch no checkout MAIN, sem override). */
export function shouldBlock(cmd, cwd) {
  if (!cmd) return false;
  if (hasOverride(cmd)) return false;
  if (!isBranchSwitch(cmd)) return false;
  const p = effectivePath(cmd, cwd);
  if (!p) return false;
  if (isLinkedWorktree(p)) return false;
  return isServingCheckout(p);
}

export function blockMessage(pathNorm) {
  return `[R8 / ADR 0233] BLOQUEADO: troca de branch no checkout MAIN (${pathNorm}).
Esse é o checkout que serve o oimpresso.test (Herd). Trocar a branch aqui muda
o que o cliente vê e quebra código-x-banco (foi o erro da sessão 2026-05-29).

FAÇA NUM WORKTREE ISOLADO:
  cd D:\\oimpresso.com
  git worktree add -b feat/<slug> .claude/worktrees/<slug> origin/main
  # trabalhe, commite e abra PR de dentro do worktree

Escape (só se Wagner aprovou explícito): inclua 'serving-branch-override' no comando.`;
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
  let cmd = '';
  let cwd = '';
  try {
    const payload = JSON.parse(raw);
    cmd = String((payload && payload.tool_input && payload.tool_input.command) || '');
    cwd = String((payload && payload.cwd) || '');
  } catch { process.exit(0); }        // parse-fail → fail-open
  let block = false;
  try { block = shouldBlock(cmd, cwd); } catch { process.exit(0); }
  if (block) {
    process.stderr.write(blockMessage(effectivePath(cmd, cwd)) + '\n');
    process.exit(2);
  }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-serving-branch-switch.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
