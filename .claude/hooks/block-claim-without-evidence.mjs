#!/usr/bin/env node
// block-claim-without-evidence.mjs — PreToolUse:Bash (PORTE cross-plataforma do .ps1).
// ADVISORY: avisa quando `gh pr create`/`gh pr merge` toca infra crítica sem evidência
// curl/HTTP literal. NUNCA bloqueia (exit 0 sempre) — ver ADR 0224 abaixo.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// proibicoes.md §"Claim sem evidência" (sessão 2026-05-17, 3 PRs em cascata #1024/#1026/
// #1028 declarados "funcionando" sem curl prod): PR que modifica runtime crítico
// (.htaccess, Middleware, Kernel, routes/, ServiceProviders, bootstrap/app.php) exige
// evidência curl/HTTP literal — PR body "## Infra Contract", commit recente, ou
// .claude/run/curl-evidence-*.txt <30min. Escape valves: evidence-override em PR body/
// commit, ou env OIMPRESSO_EVIDENCE_OVERRIDE=1 (Tier 0 Wagner emergência).
//
// ── ADVISORY, não block (ADR 0224 — hooks block vs advisory) ─────────────────
// Detecção semântica por regex (infra-crítica + evidência) é frágil; o enforcement REAL
// é a Camada A CI .github/workflows/infra-contract-required.yml (não bypassável por
// --admin local) + skill Tier B smoke-prod-evidence (cultural). Critério canônico:
// hook BLOQUEIA só o determinístico-obrigatório; semântico vira advisory. Este porte
// PRESERVA a demoção da ADR 0224 (o .ps1 já era exit 0 sempre).
//
// ── POR QUE .mjs (leva Tier-0 .ps1→.mjs, SPEC US-GOV-052 / P24) ──────────────
// O .ps1 só roda no Windows do Wagner; no Mac/Linux do time MCP o aviso evapora em
// silêncio. Supersede block-claim-without-evidence.ps1 (pattern: #4025).
//
// Fail-open: qualquer erro/parse-fail/git-fail → exit 0 silencioso.
// Selftest: node .claude/hooks/block-claim-without-evidence.test.mjs
//
// Exit: 0 SEMPRE (advisory) — stderr carrega o aviso quando falta evidência.

import { spawnSync } from 'node:child_process';
import { readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

// ── classificadores PUROS (exportados → testáveis sem stdin/git) ─────────────────

/** comando é um trigger de publicação de PR? */
export function isTrigger(cmd) {
  return /(gh pr create|gh pr merge.*--admin|gh pr merge.*--squash)/.test(cmd);
}

/** path do diff é runtime crítico (lista canônica da proibicoes §Claim sem evidência)? */
export function isInfraPath(p) {
  const f = String(p).replace(/\\/g, '/');
  return (
    /\.htaccess/.test(f) ||
    /app\/Http\/Middleware/.test(f) ||
    /app\/Http\/Kernel\.php/.test(f) ||
    /^routes\//.test(f) ||
    /app\/Providers\/[A-Z][a-zA-Z]*ServiceProvider\.php/.test(f) ||
    /bootstrap\/app\.php/.test(f)
  );
}

/** extrai o --body inline de um gh pr create (se houver). */
export function extractInlineBody(cmd) {
  const m = /--body[\s=]+["']([\s\S]+?)["']/.exec(cmd);
  return m ? m[1] : '';
}

/** texto (PR body / commits) contém evidência curl/HTTP literal? */
export function hasEvidence(text) {
  return /curl -sv|< HTTP\/1\.[01]|HTTP\/2|## Infra Contract|## Valida|smoke prod ok|smoke real/.test(String(text || ''));
}

/** texto contém o escape valve evidence-override? Retorna a razão ou null. */
export function findOverride(text) {
  const t = String(text || '');
  let m = /<!--\s*evidence-override:\s*([^>]+?)\s*-->/.exec(t);
  if (m) return m[1];
  m = /#\s*evidence-override:\s*(.+)$/m.exec(t);
  return m ? m[1].trim() : null;
}

// ── P15 entrega 2 — evidência do AMBIENTE-ALVO (CT100/cron) ──────────────────────
// Adversário 2026-07-13 (wf_33e38126): 5/8 PRs de código pararam ANTES do ambiente-alvo
// (#4192 dry-run-only, #4193 smoke adiado, #4199 estreando direto na nightly). Paths de
// script CT100/cron não são "infra crítica web" (curl não prova nada neles) — a evidência
// certa é OUTPUT DE EXECUÇÃO NO ALVO (timestamp + host) ou a pendência DECLARADA via tag
// `<!-- alvo-pendente: razão + quando -->` (transforma pendência silenciosa em rastreável).
// Mesma natureza ADVISORY (ADR 0224): exit 0 sempre; escape valves preservadas.

/** path do diff exige evidência de execução no ambiente-alvo (CT100/cron)? */
export function isAlvoPath(p) {
  const f = String(p).replace(/\\/g, '/');
  return (
    /^scripts\/tests\/ct100-[^/]*\.sh$/.test(f) ||
    /^docker\/oimpresso-mcp\/scripts\//.test(f) ||
    /^app\/Console\/Kernel\.php$/.test(f) // schedule novo — detecção por path (advisory tolera o grão grosso)
  );
}

/** texto contém output de execução no alvo (timestamp + host juntos)? */
export function hasAlvoEvidence(text) {
  const t = String(text || '');
  const host = /\b(ct100|ct-100|oimpresso-mcp|oimpresso-staging|root@|hostinger)\b/i.test(t);
  const ts = /\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/.test(t);
  return host && ts;
}

/** texto contém a tag alvo-pendente (pendência declarada)? Retorna a razão ou null. */
export function findAlvoPendente(text) {
  const t = String(text || '');
  let m = /<!--\s*alvo-pendente:\s*([^>]+?)\s*-->/.exec(t);
  if (m) return m[1];
  m = /#\s*alvo-pendente:\s*(.+)$/m.exec(t);
  return m ? m[1].trim() : null;
}

/** veredito puro da dimensão alvo (paralela a evaluate — não mexe no contrato infra).
 *  Retorna: 'silent' | 'ok' (evidência) | 'declared' (alvo-pendente) | 'override' | 'advisory'. */
export function evaluateAlvo({ command, envOverride, diffFiles, commitsText }) {
  if (!command) return 'silent';
  if (envOverride) return 'override';
  if (!isTrigger(command)) return 'silent';
  const alvo = (diffFiles || []).filter(isAlvoPath);
  if (alvo.length === 0) return 'silent';
  const body = extractInlineBody(command);
  if (findOverride(body) || findOverride(commitsText)) return 'override';
  if (findAlvoPendente(body) || findAlvoPendente(commitsText)) return 'declared';
  if (hasAlvoEvidence(body) || hasAlvoEvidence(commitsText)) return 'ok';
  return 'advisory';
}

export function advisoryAlvoMessage(alvoFiles) {
  return `
================================================================================
[ADVISORY — P15/ADR 0224] PR toca script CT100/cron SEM evidencia do AMBIENTE-ALVO
================================================================================
Arquivos alvo no diff: ${alvoFiles.slice(0, 5).join(', ')}

Adversario 2026-07-13 (wf_33e38126): 5/8 PRs pararam antes do ambiente-alvo.
Antes de propor merge, providencie UM dos seguintes:
  [1] PR body/commit com OUTPUT de execucao no alvo — timestamp + host literais
      (ex: tailscale ssh root@ct100-mcp '...' colado com data/hora do run)
  [2] Pendencia DECLARADA: "<!-- alvo-pendente: razao + quando -->" no PR body
      ou "# alvo-pendente: razao" no commit (rastreavel > silenciosa)
  [3] Hotfix legitimo: "<!-- evidence-override: razao -->" (valve generica preservada)
  [4] Emergencia Tier 0 Wagner: OIMPRESSO_EVIDENCE_OVERRIDE=1

Origem: P15-done-comportamento-evidencia-alvo.md entrega 2 (placar 3/8 TESTADO_REAL).
================================================================================`;
}

/** veredito puro sobre inputs já coletados.
 *  Retorna: 'silent' (irrelevante) | 'ok' (tem evidência) | 'override' | 'advisory'. */
export function evaluate({ command, envOverride, diffFiles, commitsText, hasRecentEvidenceFile }) {
  if (!command) return 'silent';
  if (envOverride) return 'override';
  if (!isTrigger(command)) return 'silent';
  const infra = (diffFiles || []).filter(isInfraPath);
  if (infra.length === 0) return 'silent';
  const body = extractInlineBody(command);
  if (findOverride(body) || findOverride(commitsText)) return 'override';
  if (hasEvidence(body) || hasEvidence(commitsText) || hasRecentEvidenceFile) return 'ok';
  return 'advisory';
}

export function advisoryMessage(infraFiles) {
  return `
================================================================================
[ADVISORY — ADR 0224] PR toca infra critica SEM evidencia curl/HTTP
================================================================================
Arquivos infra no diff: ${infraFiles.slice(0, 5).join(', ')}

Antes de propor merge, providencie UM dos seguintes (proibicoes §Claim sem evidencia):
  [1] PR body com "## Infra Contract" (template memory/templates/INFRA-CONTRACT.md):
      comando "curl -sv https://oimpresso.com/<rota>" + status "< HTTP/1.1 NNN" literal
  [2] Commit recente (ultimos 5) com "curl -sv" ou status HTTP literal
  [3] Arquivo .claude/run/curl-evidence-*.txt criado nos ultimos 30 minutos
  [4] Hotfix legitimo: "<!-- evidence-override: razao -->" no PR body
      ou "# evidence-override: razao" no commit
  [5] Emergencia Tier 0 Wagner: OIMPRESSO_EVIDENCE_OVERRIDE=1

Enforcement REAL (gate de merge): CI infra-contract-required.yml (Camada A).
Origem: 3 PRs em cascata #1024/#1026/#1028 (17/mai/2026) declarados sem curl prod.
================================================================================`;
}

// ── coleta de contexto (git/fs — cada passo fail-open) ───────────────────────────

function collectDiffFiles() {
  for (const args of [['diff', '--name-only', 'origin/main...HEAD'], ['diff', '--name-only', 'HEAD']]) {
    try {
      const r = spawnSync('git', args, { encoding: 'utf8' });
      const files = (r.stdout || '').split('\n').map((s) => s.trim()).filter(Boolean);
      if (files.length) return files;
    } catch { /* fail-open */ }
  }
  return [];
}

function collectCommitsText() {
  try {
    const r = spawnSync('git', ['log', '-5', '--format=%B'], { encoding: 'utf8' });
    return r.stdout || '';
  } catch { return ''; }
}

function hasRecentEvidenceFile(dir = join(process.cwd(), '.claude', 'run'), maxAgeMin = 30) {
  try {
    const cutoff = Date.now() - maxAgeMin * 60 * 1000;
    return readdirSync(dir)
      .filter((f) => /^curl-evidence-.*\.txt$/.test(f))
      .some((f) => statSync(join(dir, f)).mtimeMs > cutoff);
  } catch { return false; }
}

// ── stdin wrapper (fail-open em TUDO; exit 0 SEMPRE — advisory ADR 0224) ─────────

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
    if (String(payload && payload.tool_name) !== 'Bash') process.exit(0);
    cmd = String((payload && payload.tool_input && payload.tool_input.command) || '');
  } catch { process.exit(0); }
  if (!cmd || !isTrigger(cmd)) process.exit(0); // early-out barato antes de git
  const envOverride = process.env.OIMPRESSO_EVIDENCE_OVERRIDE === '1';
  const diffFiles = envOverride ? [] : collectDiffFiles();
  const commitsText = collectCommitsText();
  const verdict = evaluate({
    command: cmd,
    envOverride,
    diffFiles,
    commitsText,
    hasRecentEvidenceFile: hasRecentEvidenceFile(),
  });
  if (verdict === 'override') process.stderr.write('[block-claim-without-evidence] evidence-override ativo — Wagner audita via governance:detect-drift.\n');
  if (verdict === 'advisory') process.stderr.write(advisoryMessage(diffFiles.filter(isInfraPath)) + '\n');
  // dimensão paralela P15: evidência do ambiente-alvo (CT100/cron) — advisory, exit 0 sempre
  const alvoVerdict = evaluateAlvo({ command: cmd, envOverride, diffFiles, commitsText });
  if (alvoVerdict === 'declared') process.stderr.write('[block-claim-without-evidence] alvo-pendente declarado — pendência rastreável (P15), Wagner audita no checkpoint quinzenal.\n');
  if (alvoVerdict === 'advisory') process.stderr.write(advisoryAlvoMessage(diffFiles.filter(isAlvoPath)) + '\n');
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-claim-without-evidence.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
