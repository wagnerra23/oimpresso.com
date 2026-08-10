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
import { fileURLToPath, pathToFileURL } from 'node:url';

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

/** extrai o --body inline de um gh pr create (se houver).
 *
 *  BUG MEDIDO 2026-07-30 (PR #5068): a versão anterior era só /--body[\s=]+["']…["']/ e
 *  o padrão real de invocação do agente é `--body "$(cat <<'EOF' … EOF)"`. A aspa simples
 *  do `<<'EOF'` fechava o grupo → devolvia 8 chars ("$(cat <<") e hasEvidence dava FALSE
 *  mesmo com o corpo cheio de evidência. Resultado: o hook ficou cego pro corpo de TODO
 *  PR aberto por heredoc — falso negativo silencioso desde o porte .ps1→.mjs.
 *  Ordem importa: heredoc PRIMEIRO (o caso quoted casaria errado nele).
 */
export function extractInlineBody(cmd) {
  const s = String(cmd || '');
  // 1) heredoc: --body "$(cat <<'MARK' … MARK)"  — com ou sem aspas no marcador
  const here = /--body[\s=]+["']?\$\(\s*cat\s*<<-?\s*['"]?([A-Za-z_][A-Za-z0-9_]*)['"]?\s*\r?\n([\s\S]*?)\r?\n\1/.exec(s);
  if (here) return here[2];
  // 2) quoted simples: --body "…" / --body '…'
  const m = /--body[\s=]+["']([\s\S]+?)["']/.exec(s);
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

// ── 3ª dimensão — COMPLETUDE DO CORPO DE PR (2026-07-30) ─────────────────────────
// ORIGEM MEDIDA: PR #5068 desta sessão. O corpo passou por ~25 gates VERDES e ficou
// incompleto do ponto de vista HUMANO. [M] cobrou 3× exatamente o que nenhum gate cobra:
//   (1) "se eu ligar isso hoje, o que a Larissa deixaria de conseguir fazer?"
//   (2) "o que exatamente muda no Blade?" → diff medido, não "não deve afetar"
//   (3) "em que empresa foi validada a informação?"
// As duas dimensões acima só acordam se o diff toca path específico (infra web / CT100).
// PR de doc/skill/governança passa batido — foi o caso do #5068. Esta acorda em QUALQUER
// `gh pr create` cujo corpo seja legível.
//
// Regras espelham nudge-auditoria-resposta.mjs (BLAST/TENANT/BACKLOG) — aquele só inspeciona
// resposta de chat; corpo de PR ficava sem dono. Não reimplementa: mesma âncora, outro alvo.
// Mesma natureza ADVISORY (ADR 0224): exit 0 sempre, fail-open.

export const COMPLETUDE_CHECKS = [
  {
    id: 'BLAST',
    // Antídoto: diff MEDIDO (saída real do git), não alegação em prosa.
    tem: /git diff --stat|\d+ files? changed|\d+ insertions?|zero linhas|ZERO linhas|nenhum arquivo (tocad|alterad)/i,
    falta: 'BLAST: sem diff MEDIDO. Cole a saida de `git diff --stat origin/main...HEAD` (ou enumere o que entra e o que NAO entra). "1 arquivo, N linhas" em prosa nao e medicao.',
  },
  {
    id: 'CLIENTE',
    // Antídoto: respondeu o impacto em quem USA a tela.
    // CALIBRAÇÃO 2026-07-30: `biz=4` foi REMOVIDO daqui. O PR #5040 passava casando com
    // `biz=4` que aparece no corpo dele só porque ele DESCREVE a regra TENANT do hook —
    // vocabulário, não resposta. Token que passa por citar a regra não mede nada.
    tem: /larissa|cliente|quem usa a tela|rota livre|usu[aá]ri/i,
    falta: 'CLIENTE: nao respondeu "se ligar hoje, o que a pessoa que usa a tela deixaria de conseguir fazer?". Se a resposta e "nada", diga E mostre por que (zero linhas em Blade/rota/tsx).',
  },
  {
    id: 'VALIDACAO',
    // Antídoto: onde/como validou — lane nomeada, contagem, exit code, saída literal.
    // CALIBRAÇÃO 2026-07-30: `biz=\d`, `staging` e `ct100` REMOVIDOS (mesmo motivo do
    // CLIENTE: citar o nome do ambiente ≠ colar o que ele devolveu). Ficaram só marcadores
    // que exigem SAÍDA: lane nomeada, contagem N/N, exit=0, ✓, assertions.
    tem: /\blane\b|gh pr checks|exit ?= ?0|exit 0|TODOS OS TESTES|\d+\/\d+|assertion|SEEDED_TENANT_ID|✓/i,
    falta: 'VALIDACAO: nao disse ONDE validou. Nomeie a lane/gate + cole a saida literal (contagem, exit=0, ✓). "rodei e passou" nao e prova (LC-13: teste que pula sai exit 0).',
  },
  {
    id: 'BACKLOG',
    // Antídoto: citou US/task — ou disse explicitamente que nao existe.
    tem: /US-[A-Z]{2,6}-\d{3}|tasks-list|Refs:|n[aã]o existe US|nenhuma task|sem US/i,
    falta: 'BACKLOG: nao citou US/task nem disse que NAO existe. Consulte `tasks-list` e declare — em branco nao distingue "nao tem" de "nao olhei".',
  },
];

/** classificador PURO: ids de completude ausentes no corpo ([] = corpo completo). */
export function faltasCompletude(body) {
  const t = String(body || '');
  if (!t.trim()) return [];               // corpo ilegível (--body-file etc) → fail-open silencioso
  return COMPLETUDE_CHECKS.filter((c) => !c.tem.test(t)).map((c) => c.id);
}

/** veredito puro da dimensão completude.
 *  Retorna: 'silent' | 'ok' | 'override' | 'advisory'. */
export function evaluateCompletude({ command, envOverride }) {
  if (!command) return 'silent';
  if (envOverride) return 'override';
  if (!isTrigger(command)) return 'silent';
  const body = extractInlineBody(command);
  if (!String(body || '').trim()) return 'silent';   // não dá pra julgar o que não se lê
  if (findOverride(body)) return 'override';
  return faltasCompletude(body).length === 0 ? 'ok' : 'advisory';
}

export function advisoryCompletudeMessage(faltas) {
  const linhas = COMPLETUDE_CHECKS.filter((c) => faltas.includes(c.id)).map((c) => `  - ${c.falta}`);
  return `
================================================================================
[ADVISORY — completude do corpo de PR] ${faltas.length} pendencia(s): ${faltas.join(', ')}
================================================================================
${linhas.join('\n')}

Por que isso existe: PR #5068 (30/jul/2026) passou ~25 gates VERDES e ficou incompleto
pra quem le. [M] cobrou 3x o que nenhum gate cobra. Gate verde != PR completo.

Referencia de corpo bom: PR #5040. Template canonico: .github/PULL_REQUEST_TEMPLATE.md
(atencao: \`gh pr create --body\` SUBSTITUI o template silenciosamente).
Escape valve: "<!-- evidence-override: razao -->" no corpo.
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
  // 3ª dimensão: completude do corpo de PR — NÃO depende de path (advisory, exit 0 sempre)
  const compVerdict = evaluateCompletude({ command: cmd, envOverride });
  if (compVerdict === 'advisory') process.stderr.write(advisoryCompletudeMessage(faltasCompletude(extractInlineBody(cmd))) + '\n');
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-claim-without-evidence.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [fileURLToPath(test)], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
