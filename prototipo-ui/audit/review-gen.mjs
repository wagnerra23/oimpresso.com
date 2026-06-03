#!/usr/bin/env node
// review-gen.mjs — `design:review <tela>` · gerador DETERMINÍSTICO do `<Tela>.review.md`.
//
// Fecha o gap #2 da fila Cowork (COWORK_NOTES → "Gerador design:review"): o
// `<Tela>.review.md` era one-off de 2026-05-17 e nunca era regenerado → tela nova
// (ex: Jana/Pro) nascia SEM relatório de tarefas. Este script ESTENDE a máquina já
// existente (NÃO recria — anti L-11):
//   - score-mechanized.mjs  → Fase 1 (regex R1..R10 + ds/*), 1 design-report.json/tela
//   - ESTE script           → renderiza o report num `<Tela>.review.md` append-only,
//                             ancorado por `measured_against_sha` (anti-stale, gap #3)
//   - consolidate.mjs       → rollup cross-tela (inalterado)
//
// É a "charter page viva": charter (spec) + review (nota viva + backlog de tarefas).
// A Fase 2 (juiz LLM: R5/R8/R10 + nota holística + best_of_class) é refino pago [W];
// este gerador entrega o backlog mecanizado SOZINHO, custo zero de agente.
//
// Uso:
//   node prototipo-ui/audit/review-gen.mjs Jana/Pro          # 1 tela (lógico)
//   node prototipo-ui/audit/review-gen.mjs resources/js/Pages/Jana/Pro.tsx
//   node prototipo-ui/audit/review-gen.mjs --missing-live    # bootstrap: só telas live sem review

import { readFileSync, writeFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { join, dirname, basename, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execSync } from 'node:child_process';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = join(HERE, '..', '..');
const PAGES = join(REPO, 'resources/js/Pages');
const REPORTS = join(HERE, 'reports');

const argv = process.argv.slice(2);
const FLAG = (f) => argv.includes(f);
const norm = (p) => p.replace(/\\/g, '/');

// ── helpers de descoberta ─────────────────────────────────────────────────────

function gitSha(absFile) {
  try {
    const rel = norm(relative(REPO, absFile));
    const out = execSync(`git log -1 --format=%h -- "${rel}"`, { cwd: REPO }).toString().trim();
    return out || 'sem-commit';
  } catch {
    return 'sem-commit';
  }
}

// frontmatter mínimo (sem dep externa): pega `chave: valor` do bloco --- ... ---.
function frontmatter(src) {
  const m = src.match(/^---\s*\n([\s\S]*?)\n---/);
  if (!m) return {};
  const fm = {};
  for (const line of m[1].split('\n')) {
    const kv = line.match(/^([A-Za-z_][\w-]*):\s*(.*)$/);
    if (kv) fm[kv[1]] = kv[2].trim();
  }
  return fm;
}

// extrai bullets `- ❌ ...` de uma seção cujo heading começa com `## <titulo>`,
// até o próximo heading `## ` (varredura por linha — NÃO usa `$`/flag-m, que com
// multiline casa fim-de-LINHA e truncava a seção no 1º bullet).
function sectionBullets(src, titulo) {
  const head = new RegExp(`^##+\\s*${titulo}`, 'i');
  let inSec = false;
  const buf = [];
  for (const ln of src.split('\n')) {
    if (head.test(ln)) { inSec = true; continue; }
    if (inSec && /^##\s/.test(ln)) break; // próximo heading fecha a seção
    if (inSec) buf.push(ln);
  }
  return [...buf.join('\n').matchAll(/^\s*-\s*❌\s*(.+)$/gm)].map((x) => x[1].trim());
}

// localiza o RUNBOOK DA TELA (não do módulo): memory/requisitos/<Mod>/RUNBOOK-<base>.md
// (case-insensitive, aceita sufixo). Sem match exato da tela → null (anti-falso-positivo:
// NÃO atribui o RUNBOOK de outra tela só por estar no mesmo módulo).
function findRunbook(modulo, base) {
  const dir = join(REPO, 'memory/requisitos', modulo);
  if (!existsSync(dir) || !base) return null;
  try {
    const re = new RegExp(`^RUNBOOK-${base}(?:-.*)?\\.md$`, 'i');
    const f = readdirSync(dir).find((n) => re.test(n));
    return f ? norm(`memory/requisitos/${modulo}/${f}`) : null;
  } catch {
    return null;
  }
}

// ── Fase 1: roda score-mechanized pra UMA tela e lê o report ───────────────────

function loadReport(screen) {
  const slug = screen.replace(/\//g, '__');
  const reportPath = join(REPORTS, `${slug}.design-report.json`);
  try {
    execSync(`node "${join(HERE, 'score-mechanized.mjs')}" --only "${screen}"`, { cwd: REPO, stdio: 'pipe' });
  } catch (e) {
    console.warn(`[review-gen] score-mechanized falhou pra ${screen}: ${e.message}`);
  }
  if (!existsSync(reportPath)) {
    throw new Error(`design-report.json não gerado pra ${screen} (esperado ${norm(relative(REPO, reportPath))})`);
  }
  return JSON.parse(readFileSync(reportPath, 'utf8'));
}

// ── prioridade P0-P3 por peso da regra (GOLDEN-REFERENCE) ──────────────────────

const RULE_PRIO = { R1: 'P0', R2: 'P0', R7: 'P1', R9: 'P1', R10: 'P1', R3: 'P2', R4: 'P2', R5: 'P2', R6: 'P2', R8: 'P2' };
const RULE_NOME = {
  R1: 'cor crua → tokens DS (roxo 295)', R2: 'componente nativo → shared (@/ui)', R3: 'localStorage prefixado oimpresso.*',
  R4: 'ícone fora de lucide-react / svg inline', R5: 'gradient decorativo (julgado)', R6: 'emoji em UI',
  R7: 'status bg-fill → dot+texto (Stripe)', R8: 'copy em inglês (julgado)', R9: '<main> aninhado', R10: 'overflow-chain',
};

// ── render do bloco de 1 round ─────────────────────────────────────────────────

function renderRound(round, report, ctx) {
  const failed = report.rules.filter((r) => r.mechanized && r.status === 'fail');
  const judged = report.rules.filter((r) => !r.mechanized);
  const L = [];

  L.push(round === 1 ? `# Review — \`${report.screen}.tsx\` (Round 1)` : `\n## Round ${round} — ${report.scored_at} (design:review mecanizado)`);
  L.push('');
  if (round === 1) {
    L.push('> Append-only. Próximos rounds entram ABAIXO (NUNCA editar/remover blocos anteriores).');
    L.push('> Gerado por `prototipo-ui/audit/review-gen.mjs` (Fase 1 mecanizada). Fase 2 (juiz LLM) refina R5/R8/R10 + nota holística.');
    L.push('');
  }
  L.push(`**Nota mecanizada:** ${report.nota}/100 (${report.nivel}) · **medido vs SHA** \`${report.measured_against_sha}\` · **ds/\\*:** ${report.ds_violations?.total ?? 0}`);
  L.push('');

  L.push('## Sinais técnicos');
  L.push('');
  for (const s of ctx.sinais) L.push(`- ${s}`);
  L.push('');

  L.push('## Riscos / Guardrails Tier 0');
  L.push('');
  if (ctx.riscos.length) {
    for (const r of ctx.riscos) L.push(`- ${r}`);
  } else {
    L.push('- (charter sem Non-Goals/Anti-hooks explícitos — Fase 2 LLM avalia riscos de domínio.)');
  }
  L.push('');

  L.push('## Top recomendações (backlog de tarefas — worst-first)');
  L.push('');
  let n = 1;
  if (failed.length) {
    for (const r of failed) {
      L.push(`${n++}. **${RULE_PRIO[r.id] || 'P2'} — ${r.id}** ${RULE_NOME[r.id] || ''} — evidência: \`${r.evidence}\`. (GOLDEN-REFERENCE)`);
    }
  }
  if ((report.ds_violations?.total ?? 0) > 0) {
    L.push(`${n++}. **P0 — zerar ds/\\* (${report.ds_violations.total})** via componentes do DS (ESLint baseline, ADR 0209).`);
  }
  if (!failed.length && (report.ds_violations?.total ?? 0) === 0) {
    L.push(`${n++}. **OK mecanizado** — 0 regra mecanizada falhando + ds/\\*=0. Resta a **Fase 2 LLM** (refino).`);
  }
  L.push(`${n++}. **P2 — Fase 2 LLM** pendente (R5 gradient · R8 PT-BR · R10 overflow-chain = \`${judged.map((j) => j.id).join('/')}\`) + nota holística + \`best_of_class\` — gate \`$\` [W] (cadência real-mode, ADR ratchet 0236).`);
  if (report.top_gaps?.[0]?.best_of_class) {
    L.push('');
    L.push(`> **Benchmark (best-of-class):** ${report.top_gaps[0].best_of_class}.`);
  }
  L.push('');
  return L.join('\n');
}

// ── monta o contexto (sinais + riscos) a partir do .tsx + charter ──────────────

function buildContext(tsxAbs, charterAbs, modulo) {
  const base = basename(tsxAbs, '.tsx');
  const tsx = existsSync(tsxAbs) ? readFileSync(tsxAbs, 'utf8') : '';
  const charter = charterAbs && existsSync(charterAbs) ? readFileSync(charterAbs, 'utf8') : '';
  const cfm = frontmatter(charter);

  const sinais = [];
  sinais.push(/AppShellV2/.test(tsx) ? 'Shell `AppShellV2` ✓ (Cockpit V2)' : '⚠️ não importa `AppShellV2` — confirmar shell.');
  sinais.push(/from\s+['"]lucide-react['"]/.test(tsx) ? 'Ícones `lucide-react` ✓ (R4)' : '⚠️ nenhum import lucide-react.');
  const hooks = (tsx.match(/\buse(?:State|Effect|Memo|Callback|Ref)\b/g) || []);
  if (hooks.length) sinais.push(`Hooks React: ${[...new Set(hooks)].join(', ')}`);
  if (/@inertiajs\/react/.test(tsx)) sinais.push('Inertia `@inertiajs/react` ✓');
  if (charter) sinais.push(`Charter ✓ (\`status: ${cfm.status || '?'}\`${cfm.related_adrs ? ` · ADRs ${cfm.related_adrs.replace(/[[\]]/g, '')}` : ''})`);
  const runbook = findRunbook(modulo, base);
  sinais.push(runbook ? `RUNBOOK ✓ (\`${runbook}\`)` : 'RUNBOOK da tela — ausente');

  // Riscos = Non-Goals + Anti-hooks do charter (guardrails que viram GUARD se houver Pest)
  const riscos = [];
  for (const b of sectionBullets(charter, 'Non-Goals')) riscos.push(`Guardrail (NÃO faz): ${b}`);
  for (const b of sectionBullets(charter, 'Automation Anti-hooks')) riscos.push(`Anti-hook: ${b}`);
  // temas Tier 0 canônicos evidenciados no código
  if (/business_?[iI]d/.test(tsx)) riscos.push('Multi-tenant Tier 0: `business_id` sempre da sessão (ADR 0093) — confirmar nenhum cross-tenant.');
  if (/localStorage/.test(tsx)) riscos.push('Persistência `localStorage` presente — prefixo `oimpresso.<modulo>.*` (R3/ADR 0093).');

  return { sinais, riscos, charterPresent: !!charter, charterFm: cfm };
}

// ── gera 1 review ──────────────────────────────────────────────────────────────

function genOne(screen) {
  const tsxAbs = join(PAGES, `${screen}.tsx`);
  if (!existsSync(tsxAbs)) {
    console.error(`[review-gen] .tsx não encontrado: ${norm(relative(REPO, tsxAbs))}`);
    return false;
  }
  const dir = dirname(tsxAbs);
  const base = basename(tsxAbs, '.tsx');
  const charterAbs = join(dir, `${base}.charter.md`);
  const reviewAbs = join(dir, `${base}.review.md`);
  const modulo = screen.split('/')[0];

  const report = loadReport(screen);
  report.measured_against_sha = gitSha(tsxAbs); // sha do ÚLTIMO commit que tocou o .tsx (anti-stale)
  const ctx = buildContext(tsxAbs, charterAbs, modulo);

  const exists = existsSync(reviewAbs);
  let round = 1;
  let prev = '';
  if (exists) {
    prev = readFileSync(reviewAbs, 'utf8');
    const rounds = [...prev.matchAll(/(?:^review_round:\s*(\d+)|^##\s*Round\s*(\d+))/gm)].map((m) => parseInt(m[1] || m[2], 10));
    round = (rounds.length ? Math.max(...rounds) : 0) + 1;
  }

  const body = renderRound(round, report, ctx);
  const runbook = findRunbook(modulo, base);

  let out;
  if (round === 1) {
    const fm = [
      '---',
      `review_round: 1`,
      `review_type: static-analysis`,
      `reviewer: design:review (Fase 1 mecanizada)`,
      `review_at: ${report.scored_at}`,
      `page: ${screen}`,
      `file: ${report.file}`,
      `measured_against_sha: ${report.measured_against_sha}`,
      `nota: ${report.nota}`,
      `nivel: ${report.nivel}`,
      `charter_present: ${ctx.charterPresent}`,
      ...(ctx.charterPresent ? [`charter_file: ${base}.charter.md`] : []),
      `runbook_present: ${!!runbook}`,
      ...(runbook ? [`runbook_file: ${runbook}`] : []),
      `append_only: true`,
      '---',
      '',
    ].join('\n');
    out = fm + body + '\n';
  } else {
    // append-only: mantém o conteúdo anterior INTACTO, acrescenta o round novo.
    out = prev.replace(/\s*$/, '') + '\n' + body + '\n';
  }

  writeFileSync(reviewAbs, out, 'utf8');
  console.log(`[review-gen] ${exists ? `round ${round} apenso` : 'round 1 criado'}: ${norm(relative(REPO, reviewAbs))} · nota ${report.nota} (${report.nivel}) · sha ${report.measured_against_sha}`);
  return true;
}

// ── descobre telas live sem review (bootstrap) ─────────────────────────────────

function walk(dir, acc = []) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) walk(p, acc);
    else if (name.endsWith('.charter.md')) acc.push(p);
  }
  return acc;
}

function missingLiveScreens() {
  const out = [];
  for (const ch of walk(PAGES)) {
    const src = readFileSync(ch, 'utf8');
    if (!/^status:\s*live\b/m.test(src)) continue;
    const dir = dirname(ch);
    const base = basename(ch, '.charter.md');
    if (existsSync(join(dir, `${base}.review.md`))) continue;
    if (!existsSync(join(dir, `${base}.tsx`))) continue;
    out.push(norm(relative(PAGES, join(dir, base))));
  }
  return out;
}

// ── main ────────────────────────────────────────────────────────────────────

function resolveScreen(arg) {
  let s = norm(arg).replace(/^.*resources\/js\/Pages\//, '').replace(/\.tsx$/, '').replace(/\.charter$/, '');
  return s;
}

function main() {
  if (FLAG('--missing-live')) {
    const screens = missingLiveScreens();
    console.log(`[review-gen] ${screens.length} tela(s) live SEM review — gerando round 1:`);
    let ok = 0;
    for (const s of screens) if (genOne(s)) ok++;
    console.log(`[review-gen] ${ok}/${screens.length} reviews geradas.`);
    return;
  }
  const positional = argv.filter((a) => !a.startsWith('--'));
  if (!positional.length) {
    console.error('Uso: node review-gen.mjs <Mod/Tela | path.tsx> | --missing-live');
    process.exit(2);
  }
  for (const arg of positional) genOne(resolveScreen(arg));
}

main();
