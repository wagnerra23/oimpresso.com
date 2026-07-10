#!/usr/bin/env node
// block-memory-drift.mjs — PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1).
// BLOQUEIA edits em canon paths sem branch claude/* + workflow PR.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// Constituição v2 (ADR 0094) Art. 3 + ADR 0061/0130 + proibições Tier 0
// "ADRs CANON são append-only". Regras (em ordem):
//   G) CONSTITUTION.md em qualquer branch → BLOCK (supremo, só Wagner via ADR + bump)
//   B) Edit em ADR EXISTENTE (NNNN já usado) → BLOCK sempre (append-only IRREVOGÁVEL;
//      crie nova com supersedes: [NNNN])
//   D) Write criando ADR NOVA → ALLOW só em branch claude/*
//   C) Edit em handoff EXISTENTE → BLOCK sempre (ADR 0130 append-only)
//   E) Write criando handoff NOVO → ALLOW (qualquer branch — documenta a sessão)
//   F/A) Outros canon (governance/*, proibicoes, regras-time, what/why/how) →
//      BLOCK em main/master e em qualquer branch != claude/*
// Editáveis fora de escopo: decisions/proposals/**, sessions/**, reference/**, requisitos/**.
// Override emergencial Wagner Tier 0: OIMPRESSO_MEMORY_OVERRIDE=1 (warning loud +
// PR follow-up obrigatório).
//
// ── POR QUE .mjs (triagem 2026-07-09, classe Tier-0-esquecido) ───────────────
// O CI governance-gate cobre o MERGE; este hook cobre o EDIT local em runtime — a
// maratona WhatsApp 14-15/mai catalogou 5 drifts de origem PR-less. O .ps1 só rodava
// no Windows do Wagner: time MCP (Felipe/Maiara/Luiz) em Mac/Linux editaria ADR
// aceita inline sem resistência nenhuma, e o canon servido pelo MCP viraria mentira.
//
// Fail-open: qualquer erro/parse-fail → exit 0 (NUNCA trava sessão).
// Selftest: node .claude/hooks/block-memory-drift.test.mjs
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { spawnSync } from 'node:child_process';
import { existsSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const WRITE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit']);

/** classifica o path canon a partir do sufixo memory/... (lowercase, fwd slash). */
export function classifyPath(filePath) {
  const pathFwd = String(filePath || '').replace(/\\/g, '/');
  const pathLower = pathFwd.toLowerCase();
  const idx = pathLower.indexOf('memory/');
  if (idx < 0) return null; // fora de memory/ — out of scope
  const relPath = pathLower.slice(idx);
  const relOriginal = pathFwd.slice(idx);
  const adrMatch = relPath.match(/^memory\/decisions\/(\d{4})-[a-z0-9-]+\.md$/);
  return {
    relPath,
    relOriginal,
    adrNnnn: adrMatch ? adrMatch[1] : null,
    isAdrPath: !!adrMatch,
    isAdrProposal: /^memory\/decisions\/proposals\//.test(relPath),
    isHandoff: /^memory\/handoffs\/\d{4}-\d{2}-\d{2}[a-z0-9-]*\.md$/.test(relPath),
    isConstitution: /^memory\/governance\/constitution\.md$/.test(relPath),
    isGovernanceCanon: /^memory\/governance\/(trust-tiers|enforcement|architecture|identity-mesh)\.md$/.test(relPath),
    isGovernanceSrs: /^memory\/governance\/srs\//.test(relPath),
    isRootCanon: /^memory\/(proibicoes|regras-time|what-oimpresso|why-oimpresso|how-trabalhar)\.md$/.test(relPath),
  };
}

const OVERRIDE_HINT = `Override emergencial Wagner Tier 0:
  OIMPRESSO_MEMORY_OVERRIDE=1 no ambiente antes do Edit. PR follow-up obrigatorio.`;

/**
 * veredito PURO (branch e existência injetados — testável sem git/fs).
 * @returns {null | {rule: string, message: string}}
 */
export function decide({ toolName, filePath, branch, exists }) {
  if (!WRITE_TOOLS.has(toolName)) return null;
  const c = classifyPath(filePath);
  if (!c) return null;
  if (c.isAdrProposal) return null; // rascunhos editáveis até promoção
  const isCanon = c.isAdrPath || c.isHandoff || c.isConstitution || c.isGovernanceCanon || c.isGovernanceSrs || c.isRootCanon;
  if (!isCanon) return null;

  // Regra G — CONSTITUTION.md em qualquer branch
  if (c.isConstitution) {
    return { rule: 'G', message: `[block-memory-drift] ${toolName} em '${c.relOriginal}' BLOQUEADO.

REGRA: memory/governance/CONSTITUTION.md eh o documento SUPREMO da Constituicao v2 (ADR 0094).
Caminho correto: ADR nova de emendation + PR + Wagner aprova + version bump no MESMO PR.

${OVERRIDE_HINT}` };
  }

  // Regras B/D — ADR
  if (c.isAdrPath) {
    if (exists) {
      return { rule: 'B', message: `[block-memory-drift] ${toolName} em '${c.relOriginal}' BLOQUEADO.

REGRA: ADRs CANON sao APPEND-ONLY IRREVOGAVEIS (Constituicao v2 Art. 3, ADR 0094).
NUNCA editar ADR aceita inline — mesmo correcoes de typo viram nova ADR.
Caminho correto: nova ADR memory/decisions/<proxNNNN>-<slug>.md com 'supersedes: [${c.adrNnnn}]'
+ PR + Wagner aprova. Ajuste editorial (link quebrado)? Abrir PR e Wagner decide.

${OVERRIDE_HINT}` };
    }
    if (!/^claude\//.test(branch || '')) {
      return { rule: 'D', message: `[block-memory-drift] ${toolName} em '${c.relOriginal}' BLOQUEADO.

Voce esta criando uma ADR nova na branch '${branch}'. Convencao: toda mudanca canonica
vai por PR a partir de branch claude/<slug>.
Caminho correto: git checkout -b claude/<slug-descritivo> → criar ADR → PR + Wagner aprova.` };
    }
    return null; // branch claude/* + ADR nova OK
  }

  // Regras C/E — handoff
  if (c.isHandoff) {
    if (exists) {
      return { rule: 'C', message: `[block-memory-drift] ${toolName} em '${c.relOriginal}' BLOQUEADO.

REGRA: Handoffs sao APPEND-ONLY (ADR 0130) — mudar handoff antigo apaga historico.
Caminho correto: handoff NOVO em memory/handoffs/YYYY-MM-DD-HHMM-<slug>.md
+ snapshot 'Estado MCP no momento do fechamento' + 1 linha no topo de memory/08-handoff.md.

${OVERRIDE_HINT}` };
    }
    return null; // handoff novo OK em qualquer branch (documenta a sessão)
  }

  // Regras F + A — outros canon exigem branch claude/*
  if (branch === 'main' || branch === 'master') {
    return { rule: 'A', message: `[block-memory-drift] ${toolName} em '${c.relOriginal}' BLOQUEADO.

REGRA: Canon paths nao se editam direto em '${branch}'. Toda mudanca canon vai por PR
(time MCP entra — sem PR review, drift de canon servido pelo MCP fica indetectavel).
Caminho correto: git checkout -b claude/<slug> → editar → PR + Wagner aprova.

${OVERRIDE_HINT}` };
  }
  if (!/^claude\//.test(branch || '')) {
    return { rule: 'F', message: `[block-memory-drift] ${toolName} em '${c.relOriginal}' BLOQUEADO.

Branch ativa: '${branch}'. Canon paths editaveis SO em branch 'claude/<slug>'.
Caminho correto: git stash (ou commit) → git checkout -b claude/<slug> origin/main →
reaplicar → PR + Wagner aprova.

${OVERRIDE_HINT}` };
  }
  return null; // branch claude/* + canon → ALLOW (vai pra PR)
}

// ── adaptadores de ambiente (git branch + raiz do repo + existência) ─────────────

/** raiz do repo: sobe do dir do hook até achar memory/ (worktrees têm .git FILE — ok). */
export function findRepoRoot(startDir) {
  let dir = startDir;
  for (let i = 0; i < 12 && dir; i++) {
    try { if (statSync(join(dir, 'memory')).isDirectory()) return dir; } catch { /* sobe */ }
    const parent = dirname(dir);
    if (!parent || parent === dir) break;
    dir = parent;
  }
  return null;
}

function currentBranch(cwd) {
  try {
    const r = spawnSync('git', ['rev-parse', '--abbrev-ref', 'HEAD'], { cwd, encoding: 'utf8' });
    return r.status === 0 ? (r.stdout || '').trim() : '';
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
  let tool = '';
  let path = '';
  let cwd = '';
  try {
    const payload = JSON.parse(raw);
    tool = String((payload && payload.tool_name) || '');
    path = String((payload && payload.tool_input && payload.tool_input.file_path) || '');
    cwd = String((payload && payload.cwd) || '') || process.cwd();
  } catch { process.exit(0); }        // parse-fail → fail-open

  if (process.env.OIMPRESSO_MEMORY_OVERRIDE === '1') {
    process.stderr.write(`[block-memory-drift] OVERRIDE ATIVO (OIMPRESSO_MEMORY_OVERRIDE=1).
[block-memory-drift] Edit em '${path}' liberado sob responsabilidade Wagner Tier 0.
[block-memory-drift] PR follow-up imediato OBRIGATORIO. Constituicao v2 Art. 3.\n`);
    process.exit(0);
  }

  const c = classifyPath(path);
  if (!c) process.exit(0);
  const root = findRepoRoot(dirname(fileURLToPath(import.meta.url)));
  const exists = root ? existsSync(join(root, c.relPath)) : false;
  const verdict = decide({ toolName: tool, filePath: path, branch: currentBranch(cwd), exists });
  if (verdict) { process.stderr.write(verdict.message + '\n'); process.exit(2); }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-memory-drift.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
