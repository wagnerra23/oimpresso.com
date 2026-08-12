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
import { fileURLToPath, pathToFileURL } from 'node:url';

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

// ── AVISO (advisory, NUNCA bloqueia): `git stash pop` consumindo entry alheia ──
//
// CLASSE: §5 2026-07-27 ("consumir estado GLOBAL do repo por posição"). 2ª
// ocorrência em 2026-08-11 — pela ADR 0344 two-strikes, vira defesa. O par
// candidato já vinha MEDIDO na própria lápide; isto o arma.
//
// O VETOR, que é contraintuitivo: `git stash -u` numa árvore LIMPA **não cria
// entry**. Então o `pop` seguinte consome `stash@{0}`, que é de quem empilhou por
// último — em repo com worktrees paralelos, quase sempre outra sessão. Nas duas
// ocorrências o que salvou foi o CONFLITO (o git preserva a entry); aplicando
// limpo, trabalho alheio entra na árvore em silêncio.
//
// POR QUE ADVISORY E NÃO BLOQUEIO (a lápide mediu e recusou a forma dura):
// `stash push` na branch A → `checkout` B → `pop` é fluxo LEGÍTIMO e comum, e
// nele o topo é sempre de outra branch. Bloquear puniria o uso correto — a
// doença dos guards sintáticos que o §5 já matou 5×. Então: informa de quem é o
// topo e deixa o humano decidir.
//
// POPULAÇÃO MEDIDA (653 transcripts, só `tool_use` de Bash/PowerShell — nunca
// prosa): 84 `pop|apply` executados, 79 (94%) sem entry explícita. O filtro
// "topo de outra branch" estreita isso, mas NÃO é medível retroativamente:
// depende do estado da pilha no instante do comando. Declarado, não estimado.

/** o comando consome o topo por POSIÇÃO (sem `stash@{N}` explícito)? */
export function consomeTopoPorPosicao(cmd) {
  const c = normalizeCmd(cmd);
  if (!/git\s+stash\s+(pop|apply)\b/i.test(c)) return false;
  return !/stash@\{\d+\}/.test(c); // com entry explícita, o autor sabe o que pega
}

/**
 * Função PURA (o estado do git entra por parâmetro, pra ser testável sem repo).
 * @param {string} cmd
 * @param {{branchAtual: string, topo: string|null}} ctx  topo = 1ª linha de `git stash list`
 * @returns {string|null} aviso, ou null quando não há o que avisar
 */
export function avisoStashPop(cmd, ctx) {
  if (!consomeTopoPorPosicao(cmd)) return null;
  const { branchAtual, topo } = ctx || {};
  if (!topo) {
    // pilha vazia: o pop vai falhar sozinho — nada a avisar (e nada a perder)
    return null;
  }
  // `git stash list` → "stash@{0}: On <branch>: msg" ou "... WIP on <branch>: sha msg"
  const m = /^stash@\{\d+\}:\s+(?:WIP on|On)\s+([^:]+):/i.exec(topo);
  const dono = m ? m[1].trim() : null;
  if (!dono || !branchAtual) return null;      // não sei dizer de quem é → calo
  if (dono === branchAtual) return null;       // topo é seu → silêncio (o caso comum e correto)
  return `[block-destructive] AVISO (nao bloqueia): "${normalizeCmd(cmd)}" consome o TOPO por posicao, e o topo NAO e desta branch.
  topo da pilha : ${topo.trim()}
  sua branch    : ${branchAtual}
Se a arvore estava LIMPA, o seu "git stash" nao criou entry — entao este pop pega trabalho de OUTRA sessao (§5 2026-07-27, 2 ocorrencias).
Confira com "git stash list" antes. Para consumir o SEU, empilhe com nome ("git stash push -m <marcador>") e passe a entry explicita.`;
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

  // advisory do stash — depois do bloqueio, e SEMPRE exit 0. O estado do git é
  // lido aqui (impuro) e passado pra função pura, que é quem o selftest exercita.
  if (consomeTopoPorPosicao(cmd)) {
    try {
      const git = (args) => spawnSync('git', args, { encoding: 'utf8', timeout: 4000 });
      const b = git(['branch', '--show-current']);
      const s = git(['stash', 'list']);
      // rc != 0 (fora de repo, git ausente) → não invento estado, apenas calo
      if (b.status === 0 && s.status === 0) {
        const aviso = avisoStashPop(cmd, {
          branchAtual: String(b.stdout || '').trim(),
          topo: String(s.stdout || '').split('\n')[0] || null,
        });
        if (aviso) process.stderr.write(aviso + '\n');
      }
    } catch { /* fail-open: aviso nunca trava sessão */ }
  }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-destructive.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [fileURLToPath(test)], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
