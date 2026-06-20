#!/usr/bin/env node
// @ts-check
/**
 * plans-index.mjs — GERADOR determinístico do Índice de Planos Vivos (ADR 0294 + 0256).
 *
 * Espelha adr-index-generate.mjs (modelo Log4brains, fonte-única gerada). O índice de
 * planos deve ser GERADO dos próprios planos, nunca mantido à mão — senão drifta (era
 * `generated: false` v1 à mão). Lê o bloco `## Status vivo` (convenção ADR 0294) de cada
 * plano e emite memory/requisitos/_processo/PLANS-INDEX-GENERATED.md.
 *
 * Descoberta (2 categorias):
 *   - REGISTRADOS  → arquivo com bloco `## Status vivo` (em memory/requisitos/** ou
 *                    memory/sessions/**). Dados completos parseados do bloco.
 *   - PENDENTES    → arquivo cujo basename casa /plan/i em memory/requisitos/** SEM o
 *                    bloco. Listado com ⚠️ pra dirigir o backfill (a sentinela plan-health
 *                    cobra; aqui só expõe).
 *
 * Determinístico: NÃO computa "stale" (depende de now → quebraria --check). Frescor/órfão
 * é trabalho da sentinela plan-health (runtime, advisory) — aqui só ecoa reviewed_at.
 *
 * Uso:
 *   node scripts/governance/plans-index.mjs           (dry-run: resumo)
 *   node scripts/governance/plans-index.mjs --write    (grava PLANS-INDEX-GENERATED.md)
 *   node scripts/governance/plans-index.mjs --check     (CI: exit 1 se gerado ≠ commitado)
 *   node scripts/governance/plans-index.mjs --json      (saída JSON pro brief/sentinela)
 *
 * Refs: ADR 0294 (dual-track + Status vivo) · ADR 0256 (survival, fonte única gerada)
 *       · ADR 0070 (execução no MCP via parent_plan) · ADR 0274 (slug).
 */
import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join, basename, relative } from 'node:path';

const ROOT = process.cwd();
const OUT = 'memory/requisitos/_processo/PLANS-INDEX-GENERATED.md';
const SCAN_DIRS = ['memory/requisitos', 'memory/sessions'];
// os próprios índices NÃO são planos (e o hand-index tem o template `## Status vivo` num code-fence)
const SELF = new Set([OUT, 'memory/requisitos/_processo/PLANS-INDEX.md']);
// remove code-fences antes de detectar/parsear (exemplos de template não contam)
const stripFences = (t) => t.replace(/```[\s\S]*?```/g, '').replace(/^~~~[\s\S]*?^~~~/gm, '');
const MODE = process.argv.includes('--write') ? 'write'
  : process.argv.includes('--check') ? 'check'
  : process.argv.includes('--json') ? 'json' : 'dry';

const STATUS_OK = new Set(['proposto', 'ativo', 'em-execução', 'pausado', 'concluído', 'abandonado', 'superseded', 'revisar']);

// ── walk recursivo (espelha memory-health.listFiles) ────────────────────────
function listFiles(dir, filterFn) {
  const out = [];
  const walk = (d) => {
    let entries;
    try { entries = readdirSync(join(ROOT, d), { withFileTypes: true }); } catch { return; }
    for (const e of entries) {
      const rel = `${d}/${e.name}`;
      if (e.isDirectory()) walk(rel);
      else if (filterFn(rel)) out.push(rel);
    }
  };
  walk(dir);
  return out.sort();
}

// ── parse do bloco `## Status vivo` ──────────────────────────────────────────
function parseStatusVivo(txt) {
  const m = txt.match(/^##\s+Status vivo\s*$([\s\S]*?)(?=^##\s|^---\s*$)/m);
  if (!m) return null;
  const blk = m[1];
  const grab = (re) => { const x = blk.match(re); return x ? x[1].trim().replace(/\s*<!--.*$/, '').trim() : ''; };
  return {
    status: (grab(/\*\*status:\*\*\s*([^\s<·]+)/i) || '').toLowerCase(),
    owner: grab(/\*\*owner:\*\*\s*([A-Z](?:\/[A-Z])*)/),
    criado: grab(/\*\*criado:\*\*\s*(\d{4}-\d{2}-\d{2})/),
    reviewed_at: grab(/\*\*reviewed_at:\*\*\s*(\d{4}-\d{2}-\d{2})/),
    proxima_revisao: grab(/\*\*pr[óo]xima-revis[ãa]o:\*\*\s*(\d{4}-\d{2}-\d{2})/),
    cycle: grab(/\*\*cycle:\*\*\s*([^·\n]+)/),
    parent_plan: grab(/parent_plan=([a-z0-9-]+)/),
    gate_saida: grab(/\*\*gate-de-sa[íi]da[^:]*:\*\*\s*([^\n<]+)/),
    kill: grab(/\*\*kill-condition:\*\*\s*([^\n<]+)/),
  };
}

function moduleOf(rel) {
  // memory/requisitos/<Mod>/... → <Mod> ; memory/sessions/... → (sessão)
  const mr = rel.match(/^memory\/requisitos\/([^/]+)\//);
  if (mr) return mr[1] === '_processo' ? '_processo' : mr[1];
  if (rel.startsWith('memory/sessions/')) return '(sessão)';
  return '?';
}
function title(rel, txt) {
  const h1 = (txt.match(/^#\s+(.+)$/m) || [])[1];
  return (h1 || basename(rel, '.md')).replace(/\|/g, '/').slice(0, 70);
}

// ── descoberta ───────────────────────────────────────────────────────────────
const registered = [];
const withVivo = new Set();
for (const dir of SCAN_DIRS) {
  for (const rel of listFiles(dir, (p) => p.endsWith('.md'))) {
    if (SELF.has(rel)) continue;
    let txt; try { txt = readFileSync(join(ROOT, rel), 'utf8'); } catch { continue; }
    const body = stripFences(txt);
    if (!/^##\s+Status vivo\s*$/m.test(body)) continue;
    const sv = parseStatusVivo(body);
    if (!sv) continue;
    withVivo.add(rel);
    registered.push({ rel, mod: moduleOf(rel), title: title(rel, txt), ...sv });
  }
}
registered.sort((a, b) => (a.mod + a.rel).localeCompare(b.mod + b.rel));

// pendentes: arquivo *plan* em requisitos SEM bloco Status vivo
const pending = listFiles('memory/requisitos', (p) => p.endsWith('.md') && /plan/i.test(basename(p)))
  .filter((rel) => !withVivo.has(rel) && !SELF.has(rel))
  .map((rel) => { let txt = ''; try { txt = readFileSync(join(ROOT, rel), 'utf8'); } catch {} return { rel, mod: moduleOf(rel), title: title(rel, txt) }; });

// ── validação leve (não-bloqueante; reportada) ──────────────────────────────
const issues = [];
for (const p of registered) {
  if (!STATUS_OK.has(p.status)) issues.push(`${p.rel}: status inválido "${p.status || '(vazio)'}" (enum ADR 0294)`);
  if (!p.reviewed_at) issues.push(`${p.rel}: sem reviewed_at`);
}

// ── render ────────────────────────────────────────────────────────────────────
const dash = (s) => (s && s.length ? s : '—');
const withReviewed = registered.filter((p) => p.reviewed_at).length;
const withParent = registered.filter((p) => p.parent_plan).length;
const byStatus = registered.reduce((o, p) => ((o[p.status || '(vazio)'] = (o[p.status || '(vazio)'] || 0) + 1), o), {});
const fmtTally = (o) => Object.entries(o).sort((x, y) => y[1] - x[1]).map(([k, v]) => `${k} ${v}`).join(' · ') || '—';

let md = `# Índice de Planos Vivos — GERADO (não editar à mão)

> ⚙️ **Auto-gerado** por \`scripts/governance/plans-index.mjs\` a partir do bloco \`## Status vivo\` de cada plano (ADR 0294). Regenerar: \`node scripts/governance/plans-index.mjs --write\`.
> Fonte única: o plano é a verdade, este índice é derivado ([ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)). Execução mora no MCP via \`parent_plan\` ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md)). Frescor/órfão = sentinela \`plan-health\` (memory-health Check J).

## Saúde (derivada)
- **${registered.length}** planos registrados (com \`## Status vivo\`) · **${pending.length}** pendentes de backfill (arquivo *plan* sem bloco)
- reviewed_at preenchido: **${withReviewed}/${registered.length}** · vinculados a MCP (\`parent_plan\`): **${withParent}/${registered.length}**
- Por status: ${fmtTally(byStatus)}
- Inconsistências de schema: ${issues.length}${issues.length ? ' — ver final' : ''}

## Registrados (${registered.length})
| Plano | Módulo | Status | Owner | reviewed_at | parent_plan | gate-de-saída |
|---|---|---|---|---|---|---|
${registered.map((p) => `| [${p.title}](${relative('memory/requisitos/_processo', p.rel).replace(/\\/g, '/')}) | ${p.mod} | ${dash(p.status)} | ${dash(p.owner)} | ${dash(p.reviewed_at)} | ${p.parent_plan ? '`' + p.parent_plan + '`' : '—'} | ${dash(p.gate_saida).slice(0, 60)} |`).join('\n') || '_(nenhum)_'}

## Pendentes de \`## Status vivo\` (${pending.length}) — backfill dirigido pela sentinela
${pending.length ? '| Plano | Módulo |\n|---|---|\n' + pending.map((p) => `| [${p.title}](${relative('memory/requisitos/_processo', p.rel).replace(/\\/g, '/')}) | ${p.mod} |`).join('\n') : '_(nenhum — todos os planos registrados)_'}

${issues.length ? `## Inconsistências de schema (${issues.length})\n${issues.map((i) => `- ⚠️ ${i}`).join('\n')}\n` : ''}`;

// ── saída ─────────────────────────────────────────────────────────────────────
if (MODE === 'json') {
  console.log(JSON.stringify({ registered, pending, issues, counts: { registered: registered.length, pending: pending.length, withReviewed, withParent } }, null, 2));
  process.exit(0);
}
if (MODE === 'check') {
  const cur = existsSync(join(ROOT, OUT)) ? readFileSync(join(ROOT, OUT), 'utf8') : '';
  if (cur.trim() !== md.trim()) {
    console.error(`✗ ${OUT} DESATUALIZADO — rode --write. (índice gerado ≠ commitado = drift)`);
    process.exit(1);
  }
  console.log(`✓ ${OUT} em dia (${registered.length} registrados, ${pending.length} pendentes).`);
  process.exit(0);
}
if (MODE === 'write') {
  writeFileSync(join(ROOT, OUT), md);
  console.log(`✓ ${OUT} gerado — ${registered.length} registrados, ${pending.length} pendentes, ${issues.length} inconsistências.`);
} else {
  console.log(md.split('\n').slice(0, 28).join('\n'));
  console.log(`\n[dry-run] ${registered.length} registrados · ${pending.length} pendentes · ${issues.length} inconsistências. Rode --write.`);
}
