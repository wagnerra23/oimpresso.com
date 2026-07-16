#!/usr/bin/env node
// pii-redactor.mjs — PreToolUse:Bash (PORTE cross-plataforma do .ps1).
// BLOQUEIA `git commit` que levaria PII real (CPF/CNPJ/cartão) pro repo.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// US-COPI-086 (Cycle 01) + LGPD Art. 7º (minimização) + proibições Tier 0
// "PII reais (CPF/CNPJ cliente) NUNCA em PR/commit/log — use [REDACTED]".
//
// Escopo OPÇÃO B (decisão 2026-06-13, memory/sessions/2026-06-13-pii-redactor-
// opcao-b-commit-only.md): SÓ inspeciona `git commit` — a mensagem (texto do
// comando) + o staged diff. Ambos entram no histórico git → MCP server → time.
// Comandos NÃO-commit (mysql/grep/ssh/cat...) passam SEM inspeção: num ERP
// brasileiro, debug legítimo por CPF/CNPJ é operação normal.
//
// Bypass: adicione --allow-pii ao git commit (E confirme com Wagner).
// Whitelist: fixtures fake bem conhecidos (CPFs de placeholder + cartões de teste Visa/MC).
//
// ── POR QUE .mjs (triagem 2026-07-09, classe Tier-0-esquecido) ───────────────
// PII vazada em commit é irreversível sem filter-repo (reincidência BRL 2026-06-08
// custou reescrever 5.033 commits + force-push no main). O .ps1 só rodava no
// Windows do Wagner — time MCP (Felipe/Maiara/Luiz) em Mac/Linux commitaria sem o
// guardrail LGPD. Nenhum gate CI substitui o pré-commit local (quando o CI vê, a
// PII já está pushed).
//
// Fail-open: qualquer erro/parse-fail/git-fail → escaneia o que der, nunca trava.
// Selftest: node .claude/hooks/pii-redactor.test.mjs
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

/** só age em git commit (opção B — commit-only). */
export function isGitCommit(cmd) {
  return /^\s*git\s+commit\b/.test(String(cmd || ''));
}

/** bypass justificado (exige confirmação Wagner fora de banda). */
export function hasBypass(cmd) {
  return /--allow-pii/.test(String(cmd || ''));
}

/** fixtures fake bem conhecidos — nunca bloqueiam (âncora: whitelist do contrato). */
const FAKE_WHITELIST = [
  /123\.456\.789-09/,
  /111\.111\.111-11/,
  /000\.000\.000-00/,
  /11\.222\.333\/0001-81/,
  /00\.000\.000\/0000-00/,
  /4111[\s-]?1111[\s-]?1111[\s-]?1111/,   // Visa test
  /5555[\s-]?5555[\s-]?5555[\s-]?4444/,   // Mastercard test
];

/** padrões de PII real (formato BR). */
const PII_PATTERNS = [
  ['cpf', /\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/g],
  ['cnpj', /\b\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}\b/g],
  ['cartao', /\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/g],
];

/** dado um texto, retorna [{tipo, valor}] de PIIs detectadas (não-whitelisted). */
export function findPii(text) {
  const found = [];
  const t = String(text || '');
  for (const [tipo, pattern] of PII_PATTERNS) {
    for (const m of t.matchAll(pattern)) {
      const valor = m[0];
      if (FAKE_WHITELIST.some((w) => w.test(valor))) continue;
      found.push({ tipo, valor });
    }
  }
  return found;
}

export function blockMessage(found) {
  const first = found[0];
  const exemplo = first.valor.slice(0, 6) + '...';
  const tipos = found.map((f) => f.tipo).join(', ');
  return `[pii-redactor] git commit BLOQUEADO: ${first.tipo} '${exemplo}' (mensagem ou staged diff).
LGPD Art. 7º — o commit contém ${found.length} PII real (${tipos}) na mensagem e/ou no staged diff.
Antes de commitar:
  1) remova a PII da mensagem do commit;
  2) git restore --staged <arquivo> + edite (use [REDACTED] ou fixtures fake) + re-stage.
Bypass justificado: adicione --allow-pii ao comando E confirme com Wagner.`;
}

/** staged diff via git — fail-open (não é repo/sem git → escaneia só a mensagem). */
export function stagedDiff(cwd) {
  try {
    const r = spawnSync('git', ['diff', '--staged'], {
      cwd: cwd || process.cwd(), encoding: 'utf8', maxBuffer: 64 * 1024 * 1024,
    });
    return r.status === 0 ? (r.stdout || '') : '';
  } catch { return ''; }
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
    if (String((payload && payload.tool_name) || '') !== 'Bash') process.exit(0);
    cmd = String((payload && payload.tool_input && payload.tool_input.command) || '');
    cwd = String((payload && payload.cwd) || '');
  } catch { process.exit(0); }        // parse-fail → fail-open
  if (!cmd || !isGitCommit(cmd) || hasBypass(cmd)) process.exit(0);
  const texto = cmd + '\n' + stagedDiff(cwd);
  const found = findPii(texto);
  if (found.length > 0) { process.stderr.write(blockMessage(found) + '\n'); process.exit(2); }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./pii-redactor.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
