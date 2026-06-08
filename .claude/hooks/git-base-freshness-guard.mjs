#!/usr/bin/env node
// Hook SessionStart — GUARD de base fresca vs `origin/main`.
//
// **Cross-platform** (Node.js — Windows desktop / Linux CI / macOS). Node já é dep do projeto.
//
// ───────────────────────────────────────────────────────────────────────────
// POR QUE EXISTE (incidente 2026-05-31, Wagner: "isso nunca pode acontecer"):
//   O F0 "rotinas-design" foi feito inteiro — INCLUSIVE o gate §10.4 que existe
//   pra "validar contra o main" — lendo o working tree de um branch STALE
//   (`feat/staging-ct100`, −46 commits vs origin/main). Resultado: 3 achados
//   factualmente errados (`ds:report` "não existe", canais "stale", G3 "gap")
//   + edits que corromperiam os canais. Foi pego POR SORTE no "merge" (rodei
//   `git rev-list` por acaso), NÃO pelo gate.
//
//   Causa-raiz estrutural: §10.4 dizia "valida contra o main" mas NÃO definia
//   "main" = `origin/main` fresco (pós-fetch) nem FORÇAVA `git fetch`. "Main"
//   virou "o que está no meu disco". Um checkout stale passa silencioso.
//
//   A proteção NÃO pode depender do Wagner notar, nem de eu lembrar de checar
//   (Wagner: "não pode depender de mim"). Tem que ser MECÂNICA e disparar SOZINHA.
// ───────────────────────────────────────────────────────────────────────────
//
// O QUE FAZ (SessionStart, exit 0 sempre — nunca bloqueia a sessão):
//   1. `git fetch` (bounded, best-effort) só de refs/heads/main → origin/main
//   2. Conta `behind` = quantos commits origin/main está À FRENTE do HEAD
//   3. Se behind > 0 → emite BANNER ALTO (vira <system-reminder>): "BASE STALE,
//      NÃO valide canon contra o working tree, use `git show origin/main:`".
//   4. Se behind == 0 (base fresca) → SILÊNCIO (zero fricção).
//
//   Escape valve: env `OIMPRESSO_BASE_GUARD_OFF=1` → exit 0 imediato e silencioso.
//
// Refs: PROTOCOL.md §10.4 (Passo 0 — ancoragem em origin/main) · ADR 0114 · ADR 0239.

import { execFileSync } from 'node:child_process';
import { realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

/**
 * Lógica de decisão PURA (testável sem git/rede).
 * @param {{behind:number, ahead:number, branch:string}} info
 * @returns {string} banner markdown (vazio = base fresca = silêncio)
 */
export function buildBanner({ behind, ahead, branch }) {
  if (!Number.isFinite(behind) || behind <= 0) return ''; // fresca → silêncio
  const aheadTxt = Number.isFinite(ahead) && ahead > 0 ? ` (e ${ahead} à frente)` : '';
  return `⚠️ **BASE STALE — guard \`git-base-freshness-guard.mjs\` (SessionStart)**

Seu checkout (\`${branch}\`) está **${behind} commit(s) ATRÁS de \`origin/main\`**${aheadTxt}. Você **NÃO está no main** — o working tree NÃO é canon.

🚫 **NÃO valide canon contra o working tree.** Gate §10.4 ("comparar com o main"), checagens de existência ("ADR/arquivo/script X existe?", \`ls\`/\`Read\`/\`Glob\`/\`Grep\`) e leitura de SPEC/decisions/prototipo-ui — **TUDO** via \`origin/main\` fresco:
- \`git show origin/main:<caminho>\`  ·  \`git ls-tree origin/main <caminho>\`  ·  \`git log --oneline origin/main\`

✅ **Pra produzir/mergear:** trabalhe a PARTIR de \`origin/main\` fresco — \`git worktree add -b <branch> <path> origin/main\` — não deste branch stale.

Origem: incidente 2026-05-31 (F0 rotinas-design feito em checkout −46 → 3 achados errados, pego por sorte). Lei: PROTOCOL.md §10.4 Passo 0. Desligar (raro): \`OIMPRESSO_BASE_GUARD_OFF=1\`.`;
}

function git(args, timeoutMs = 12000) {
  try {
    return execFileSync('git', args, {
      encoding: 'utf8',
      timeout: timeoutMs,
      stdio: ['ignore', 'pipe', 'ignore'],
    }).trim();
  } catch {
    return null;
  }
}

/**
 * Mede a base atual contra origin/main (best-effort, tolerante a offline / sem repo).
 * @returns {{behind:number, ahead:number, branch:string} | null}
 */
function assessBase() {
  if (git(['rev-parse', '--is-inside-work-tree']) !== 'true') return null;
  // fetch bounded só do main (refspec explícito atualiza refs/remotes/origin/main)
  git(['fetch', 'origin', '+refs/heads/main:refs/remotes/origin/main', '--quiet'], 12000);
  if (!git(['rev-parse', '--verify', '--quiet', 'origin/main'])) return null; // sem ref → não dá pra medir
  const counts = git(['rev-list', '--left-right', '--count', 'origin/main...HEAD']);
  if (!counts) return null;
  const [behind, ahead] = counts.split(/\s+/).map((n) => parseInt(n, 10));
  const branch = git(['rev-parse', '--abbrev-ref', 'HEAD']) || '(detached)';
  return { behind, ahead, branch };
}

// Executa SÓ quando invocado direto (não no import do teste). Cross-platform.
let isMain = false;
try {
  isMain = realpathSync(process.argv[1]) === realpathSync(fileURLToPath(import.meta.url));
} catch {
  isMain = false;
}

if (isMain) {
  try {
    if (process.env.OIMPRESSO_BASE_GUARD_OFF === '1') process.exit(0); // escape valve
    const info = assessBase();
    if (info) {
      const banner = buildBanner(info);
      if (banner) process.stdout.write(banner);
    }
  } catch {
    // nunca bloqueia a sessão
  }
  process.exit(0);
}
