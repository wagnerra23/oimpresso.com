#!/usr/bin/env node
// block-test-fora-ct100.mjs — PreToolUse:Bash|PowerShell (PORTE cross-plataforma do .ps1).
//
// Enforcement do feedback Wagner 2026-06-01 (testes/PHPStan rodam no CT 100, NUNCA na
// máquina local / Hostinger): bloqueia execução de Pest / PHPStan / PHPUnit /
// 'php artisan test' fora do CT 100. Lugar correto: container oimpresso-staging (CT 100).
//
// ── POR QUE .mjs (card #2 "Enforcement cross-platform", dossiê grade-das-réguas 2026-07-09) ──
// O .ps1 legado SÓ roda no Windows do Wagner. O time MCP (Felipe/Maiara/Luiz) entra em
// Mac/Linux — e lá o `powershell -File` some, o blocker Tier-0 evapora em silêncio. Node é
// cross-plataforma (os hooks .mjs já rodam no Windows do Wagner também). Este é o porte 1/N
// dos blockers Tier-0 .ps1→.mjs (SPEC US-GOV-052 / P24). Supersede block-test-fora-ct100.ps1.
//
// Wagner textual: "os testes não devem ser feito local, as maquinas não suportariam faça no
// ct 100 obrigatoriamente la tem recursos para isso." Ref: ADR 0062 +
// memory/reference/feedback-testes-no-ct100-nao-local.md.
//
// Fail-open: qualquer erro/parse-fail → exit 0 (NUNCA trava sessão).
// Escape valve: incluir 'test-local-override' no comando (Wagner aprovou emergência).
// Selftest: node .claude/hooks/block-test-fora-ct100.mjs --selftest
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

// ── classificadores PUROS (exportados → testáveis sem stdin/spawn) ───────────────

/** escape valve explícito (Wagner aprovou rodar local desta vez). */
export function hasOverride(cmd) { return /test-local-override/.test(cmd); }

/** é uma EXECUÇÃO de teste/análise estática (runner), não leitura de arquivo? */
export function isRunner(cmd) {
  return (
    /(?<![\w.-])php\s+artisan\s+test(\s|$|")/.test(cmd) ||
    /vendor[/\\]bin[/\\](phpstan|pest|phpunit)(\.phar)?(\s|$|")/.test(cmd) ||
    /(?<![\w/\\.-])(phpstan|pest|phpunit)\s+(analyse|analyze|--|tests?\b)/.test(cmd) ||
    /composer\s+(run\s+)?(test|pest|phpstan|phpunit|larastan)(\s|$|@|")/.test(cmd)
  );
}

/** já aponta pro CT 100 (tailscale ssh / docker exec staging|mcp / ct100)? → liberado. */
export function isCt100(cmd) {
  return (
    /tailscale\s+ssh/.test(cmd) ||
    /docker\s+exec\s+\S*oimpresso-(staging|mcp)/.test(cmd) ||
    /ssh\s+root@(100\.99\.207\.66|ct100)/.test(cmd) ||
    /ct100-mcp/.test(cmd)
  );
}

/** veredito único: bloqueia? (runner local, sem override, fora do CT 100). */
export function shouldBlock(cmd) {
  if (!cmd) return false;
  if (hasOverride(cmd)) return false;
  if (!isRunner(cmd)) return false;
  if (isCt100(cmd)) return false;
  return true;
}

export const BLOCK_MESSAGE = `[FEEDBACK 2026-06-01 / ADR 0062] BLOQUEADO: teste/PHPStan na maquina LOCAL.

Wagner (textual): "os testes nao devem ser feito local, as maquinas nao
suportariam, faca no ct 100 obrigatoriamente la tem recursos para isso."

RODE NO CT 100 (container oimpresso-staging, DB sqlite :memory: isolado):

  # Pest (filtro ou arquivo):
  tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test --filter=NomeDoTeste"

  # PHPStan (analise estatica):
  tailscale ssh root@ct100-mcp "docker exec oimpresso-staging vendor/bin/phpstan analyse <path> --memory-limit=1G --no-progress"

  # Levar codigo de um branch/PR pro staging antes (se preciso):
  tailscale ssh root@ct100-mcp "cd /opt/oimpresso-staging/code && git fetch origin && git checkout <branch> && git reset --hard origin/<branch>"

Por que: a workstation/Herd NAO aguenta a suite (3000+ testes); o CT 100 tem
CPU/RAM + stack completo (OTel SDK, larastan em require-dev). CI GitHub continua
sendo o gate de merge.

Ref: memory/reference/feedback-testes-no-ct100-nao-local.md
Escape (so se Wagner aprovou explicito): inclua 'test-local-override' no comando.`;

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
  try {
    const payload = JSON.parse(raw);
    cmd = String((payload && payload.tool_input && payload.tool_input.command) || '');
  } catch { process.exit(0); }        // parse-fail → fail-open
  if (shouldBlock(cmd)) { process.stderr.write(BLOCK_MESSAGE + '\n'); process.exit(2); }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-test-fora-ct100.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
