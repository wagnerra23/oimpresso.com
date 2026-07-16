#!/usr/bin/env node
// block-destructive.mjs — PreToolUse:Bash (PORTE cross-plataforma do .ps1).
// BLOQUEIA comandos Bash destrutivos sem confirmação humana.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// US-COPI-085 (Cycle 01, guardrails Bash) + ADR 0063 (composer.lock sem drift)
// + proibições §Ambiente ("nunca composer update sem --lock em prod"). 8 categorias:
//   1. rm -rf fora da whitelist (/tmp, node_modules, vendor, caches de build)
//   2. git push --force / -f (qualquer force exige confirmação explícita Wagner)
//   3. git reset --hard origin/* (descarta trabalho local não-pushed)
//   4. DROP TABLE/DATABASE/SCHEMA
//   5. DELETE FROM sem WHERE  ·  6. DELETE WHERE 1=1
//   7. composer update sem --lock (ADR 0063)
//   8. php artisan migrate:fresh/reset/wipe  ·  TRUNCATE
//
// FIX DE FIDELIDADE À REGRA (documentado no PR do porte): o regex do .ps1 pra
// "DELETE sem WHERE" sofria backtracking (`\w+` recuava e o lookahead negativo
// nunca via o WHERE) — na prática bloqueava TODO `DELETE FROM`, com ou sem WHERE.
// O porte implementa a regra COMO ESCRITA no contrato: `DELETE FROM x WHERE id=1`
// passa; sem WHERE (ou WHERE 1=1) bloqueia.
//
// ── POR QUE .mjs (triagem 2026-07-09, classe Tier-0-esquecido) ───────────────
// Irreversibilidade não tem retry: rm -rf/DROP/force-push destroem trabalho e dado
// de prod SEM caminho de volta, em QUALQUER sistema operacional. O .ps1 só rodava no
// Windows do Wagner — time MCP (Felipe/Maiara/Luiz) em Mac/Linux ficaria sem o
// guardrail em silêncio. Nenhum gate CI substitui (o vetor é runtime, pré-commit).
// grade.mjs (régua R-canon) referencia este hook — baseline 33% preservado.
//
// Fail-open: qualquer erro/parse-fail → exit 0 (NUNCA trava sessão).
// PS `-match` era case-insensitive por default → todos os padrões levam /i (fidelidade).
// Selftest: node .claude/hooks/block-destructive.test.mjs
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

/** normaliza espaços múltiplos pra regex consistente (fidelidade ao .ps1). */
export function normalizeCmd(cmd) {
  return String(cmd || '').replace(/\s+/g, ' ').trim();
}

/** whitelist rm -rf: caches/artefatos de build reconstruíveis (âncora: comentário US-COPI-085). */
const RM_WHITELIST = [
  /^rm -rf \/tmp\//i,
  /^rm -rf ~\/\.cache\//i,
  /^rm -rf node_modules\b/i,
  /^rm -rf vendor\b/i,
  /^rm -rf storage\/framework\/(views|cache|sessions)\//i,
  /^rm -rf bootstrap\/cache\//i,
  /^rm -rf public\/build/i,
  /^rm -rf \.next\//i,
  /^rm -rf dist\//i,
  /^rm -rf coverage\//i,
];

/** categorias proibidas — ordem determinística (primeiro match dá a mensagem). */
const PADROES = [
  {
    key: 'rm-rf-perigoso',
    regex: /(^|[\s;&|])rm\s+-[rRf]+\s+/i,
    razao: 'rm -rf pode apagar trabalho não commitado / config / dados de prod',
    sugestao: 'use rm com path específico, ou whitelist: /tmp/, node_modules, vendor, storage/framework/{views,cache}, public/build*',
  },
  {
    key: 'git-force-push',
    regex: /git\s+push\s+(--force\b|-f\b|.*\s--force(-with-lease)?\b)/i,
    razao: 'force push sobrescreve histórico remoto — risco de perder commits do time',
    sugestao: 'rebase local + push normal, OU usar --force-with-lease com confirmação explícita do Wagner',
  },
  {
    key: 'git-reset-hard-origin',
    regex: /git\s+reset\s+--hard\s+(origin|upstream)\//i,
    razao: 'reset --hard contra remote descarta TODO trabalho local não-pushed',
    sugestao: 'git stash primeiro, depois reset; OU criar branch backup antes',
  },
  {
    key: 'sql-drop-table',
    regex: /\bDROP\s+(TABLE|DATABASE|SCHEMA)\b/i,
    razao: 'DROP TABLE/DATABASE é irreversível — perde dados de produção',
    sugestao: 'rodar em staging primeiro, OU criar migration drop_*_table com plan mode + revisão Wagner',
  },
  {
    key: 'sql-delete-no-where',
    // \b após \w+ impede o backtracking que anulava o lookahead no .ps1 (ver header)
    regex: /\bDELETE\s+FROM\s+\w+\b(?!\s+WHERE\b)/i,
    razao: 'DELETE sem WHERE apaga TODA a tabela',
    sugestao: 'sempre adicionar WHERE explícito, mesmo que seja WHERE id IN (...)',
  },
  {
    key: 'sql-delete-where-1',
    regex: /\bDELETE\s+FROM\s+\w+\s+WHERE\s+1(\s*=\s*1)?\b/i,
    razao: 'DELETE WHERE 1=1 = wipe da tabela inteira',
    sugestao: 'usar filtro real (WHERE id < N OR created_at < ...)',
  },
  {
    key: 'sql-truncate',
    regex: /\bTRUNCATE\s+(TABLE\s+)?\w+/i,
    razao: 'TRUNCATE wipa a tabela inteira (mais rápido que DELETE, mesmo efeito)',
    sugestao: 'só em fixtures/seed locais; em prod usar migration formal',
  },
  {
    key: 'composer-update-sem-lock',
    regex: /(?<!#\s)composer\s+update(?!\s+--lock\b)(?!.*\s--lock\b)/i,
    razao: 'composer update sem --lock causa drift do composer.lock (ADR 0063)',
    sugestao: 'composer update --lock (atualiza só o lock sem instalar) OU composer require pacote:versao',
  },
  {
    key: 'artisan-migrate-fresh-prod',
    regex: /php\s+artisan\s+migrate:(fresh|reset|wipe|rollback\s+--step=\d{2,})/i,
    razao: 'migrate:fresh/reset/wipe DROPA todas as tabelas — apaga produção',
    sugestao: 'usar migrate:rollback --step=1 com revisão; OU em prod, criar migration formal com down() controlado',
  },
];

/** veredito único: {key, razao, sugestao} da primeira categoria que casar, ou null. */
export function matchDestructive(cmd) {
  const cmdNorm = normalizeCmd(cmd);
  if (!cmdNorm) return null;
  for (const p of PADROES) {
    if (!p.regex.test(cmdNorm)) continue;
    if (p.key === 'rm-rf-perigoso' && RM_WHITELIST.some((w) => w.test(cmdNorm))) continue;
    return p;
  }
  return null;
}

export function blockMessage(p) {
  return `[block-destructive] Bash BLOQUEADO (${p.key}). Motivo: ${p.razao}. Sugestão: ${p.sugestao}. Se for intencional e Wagner autorizou explicitamente, use abordagem alternativa OU peça Wagner pra rodar manualmente. NUNCA forçar bypass deste hook sem ADR justificando.`;
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
  try {
    const payload = JSON.parse(raw);
    if (String((payload && payload.tool_name) || '') !== 'Bash') process.exit(0);
    cmd = String((payload && payload.tool_input && payload.tool_input.command) || '');
  } catch { process.exit(0); }        // parse-fail → fail-open
  if (!cmd) process.exit(0);
  const p = matchDestructive(cmd);
  if (p) { process.stderr.write(blockMessage(p) + '\n'); process.exit(2); }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-destructive.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
