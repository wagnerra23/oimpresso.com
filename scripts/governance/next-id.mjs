#!/usr/bin/env node
// @ts-check
/**
 * next-id.mjs — aloca o próximo número de ADR/US **ciente de trabalho em voo** (ADR 0304).
 *
 * A colisão crônica de número (14 ADRs em _INDEX-LIFECYCLE.md + US-IDs sem rede) nasce
 * de uma alocação CEGA: escolher "próximo livre" lendo só a `main` canônica, sem ver
 * branches/PRs não-mergeados que já reivindicaram um número. Como o append-only (ADR 0257)
 * torna a colisão permanente uma vez commitada, a alavanca é a alocação — não a detecção.
 *
 * Este alocador lê DUAS fontes, não uma:
 *   1. working tree — o checkout atual (canonical: memory/decisions/ + os SPEC.md);
 *   2. PRs abertos  — branches dos PRs abertos (via `gh`, lidos do git local já fetchado).
 * Ignora de propósito os milhares de branches stale do repo: incluí-los empurraria o contador
 * pra frente à toa (um número abandonado não é "em voo"). Retorna o menor número acima de ambas.
 *
 * Uso (na raiz do repo):
 *   node scripts/governance/next-id.mjs adr           # → ex: 0305
 *   node scripts/governance/next-id.mjs us GOV        # → ex: US-GOV-045
 *   flags: --explain (fontes no stderr) · --no-refs (só working tree, p/ debug)
 *
 * NB: lê refs JÁ fetchadas — rode `git fetch origin` antes pra frescor máximo. Não
 * elimina 100% a corrida (duas sessões no mesmo minuto); o resíduo é pego pelo
 * memory-health (Check A p/ ADR · Check N p/ US). Refs: ADR 0304 · 0028 · 0180 · 0257 · 0298.
 */
import { execSync } from 'node:child_process';
import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const argv = process.argv.slice(2);
const KIND = argv[0];
const MOD = argv[1] && !argv[1].startsWith('--') ? argv[1] : null;
const EXPLAIN = argv.includes('--explain');
const NO_REFS = argv.includes('--no-refs');

function sh(cmd) {
  try { return execSync(cmd, { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }); }
  catch { return ''; }
}

// Branches dos PRs ABERTOS (o trabalho em voo real — não os milhares de refs stale do repo).
// `gh` identifica os PRs; o git local lê as árvores já fetchadas (origin/<branch>). Dedup por
// SHA (evita re-escanear árvore idêntica). Sem gh/sem PRs → [] + aviso (degrada p/ canonical).
let GH_WARNED = false;
function openPrTrees() {
  if (NO_REFS) return [];
  let out;
  try {
    out = execSync('gh pr list --state open --limit 300 --json headRefName --jq ".[].headRefName"',
      { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
  } catch {
    if (!GH_WARNED) { process.stderr.write('[next-id] aviso: gh indisponível — usando só o working tree (canonical). Autentique o gh p/ incluir PRs em voo.\n'); GH_WARNED = true; }
    return [];
  }
  // 0 PRs abertos = estado válido (out vazio) → [] sem aviso.
  const seen = new Set(); const refs = [];
  for (const name of out.split('\n').map((s) => s.trim()).filter(Boolean)) {
    const ref = `origin/${name}`;
    const sha = sh(`git rev-parse --verify --quiet ${ref}`).trim();
    if (sha && !seen.has(sha)) { seen.add(sha); refs.push(ref); }
  }
  return refs;
}

function bump(maxObj, n, src) {
  if (n > maxObj.n) { maxObj.n = n; maxObj.src = src; }
}

// ── ADR: maior NNNN em memory/decisions/NNNN-*.md (working tree + cada ref) ──
function nextAdr() {
  const max = { n: 0, src: '(nenhum)' };
  const dir = 'memory/decisions';
  const isAdrNum = (n) => n >= 1 && n < 1900; // ADRs são 0001-0999; exclui anos (proposals 2026-*)
  if (existsSync(join(ROOT, dir))) {
    for (const f of readdirSync(join(ROOT, dir))) { // não-recursivo: ignora proposals/
      const m = f.match(/^(\d{4})-.+\.md$/);
      if (m && isAdrNum(+m[1])) bump(max, +m[1], `worktree:${f}`);
    }
  }
  for (const ref of openPrTrees()) {
    for (const line of sh(`git ls-tree -r --name-only ${ref} -- memory/decisions/`).split('\n')) {
      const m = line.match(/^memory\/decisions\/(\d{4})-[^/]*\.md$/); // top-level só; exclui proposals/
      if (m && isAdrNum(+m[1])) bump(max, +m[1], `${ref}:${m[1]}`);
    }
  }
  if (EXPLAIN) process.stderr.write(`[next-id adr] max=${String(max.n).padStart(4, '0')} (${max.src})\n`);
  return String(max.n + 1).padStart(4, '0');
}

// ── US: maior NNN em US-<PREFIX>-NNN nos SPEC.md (working tree + cada ref) ──
// Escaneia TODOS os SPEC.md (US-ID é único por prefixo, independe do dir do módulo).
function nextUs(prefix) {
  const P = prefix.toUpperCase();
  const re = new RegExp(`US-${P}-(\\d+)`, 'g');
  const max = { n: 0, src: '(nenhum)' };
  const scan = (txt, src) => { let m; while ((m = re.exec(txt))) bump(max, +m[1], src); };
  // working tree: todos os memory/requisitos/*/SPEC.md
  const reqRoot = 'memory/requisitos';
  if (existsSync(join(ROOT, reqRoot))) {
    for (const d of readdirSync(join(ROOT, reqRoot), { withFileTypes: true })) {
      const sp = `${reqRoot}/${d.name}/SPEC.md`;
      if (d.isDirectory() && existsSync(join(ROOT, sp))) scan(readFileSync(join(ROOT, sp), 'utf8'), `worktree:${sp}`);
    }
  }
  // refs: git grep do prefixo nos SPEC.md (1 chamada por ref)
  for (const ref of openPrTrees()) {
    const hits = sh(`git grep -hoE "US-${P}-[0-9]+" ${ref} -- "memory/requisitos/*/SPEC.md"`);
    if (hits) scan(hits, ref);
  }
  if (EXPLAIN) process.stderr.write(`[next-id us ${P}] max=${max.n} (${max.src})\n`);
  return `US-${P}-${String(max.n + 1).padStart(3, '0')}`;
}

if (KIND === 'adr') {
  process.stdout.write(nextAdr() + '\n');
} else if (KIND === 'us') {
  if (!MOD) { process.stderr.write('uso: node scripts/governance/next-id.mjs us <PREFIXO>  (ex: GOV, NFSE, WA)\n'); process.exit(2); }
  process.stdout.write(nextUs(MOD) + '\n');
} else {
  process.stderr.write('uso: node scripts/governance/next-id.mjs <adr|us> [PREFIXO] [--explain] [--no-refs]\n');
  process.exit(2);
}
