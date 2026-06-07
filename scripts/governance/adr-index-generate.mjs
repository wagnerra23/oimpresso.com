#!/usr/bin/env node
// @ts-check
/**
 * adr-index-generate.mjs — GERADOR determinístico do índice de ADR (modelo Log4brains).
 *
 * Pesquisa estado-da-arte 2026 (Log4brains/adr-tools/AWS): o índice de ADR deve ser
 * GERADO dos próprios arquivos, nunca mantido à mão — senão drifta. O oimpresso tinha
 * 4 índices manuais (README/INDEX_TEMATICO/_INDEX-LIFECYCLE/INDEX) que mentiam vs disco.
 * Este gerador os substitui por 1 fonte: lê todo memory/decisions/[0-9]*.md, extrai o
 * frontmatter, normaliza status/lifecycle no LEITOR (sem editar arquivo — append-only),
 * e emite memory/decisions/_INDEX-GENERATED.md. Determinístico (mesmo input→mesmo output).
 *
 * Uso:
 *   node scripts/governance/adr-index-generate.mjs           (dry-run: imprime resumo)
 *   node scripts/governance/adr-index-generate.mjs --write   (grava _INDEX-GENERATED.md)
 *   node scripts/governance/adr-index-generate.mjs --check   (CI: exit 1 se o gerado ≠ commitado = drift)
 *
 * Refs: ADR 0256 (survival, fonte única gerada) · ADR 0257 (status/lifecycle/kind) · ADR 0180 (colisões)
 *       · estado-da-arte 2026 (Log4brains/adr-tools/AWS Prescriptive Guidance).
 */
import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const DIR = 'memory/decisions';
const OUT = 'memory/decisions/_INDEX-GENERATED.md';
const MODE = process.argv.includes('--write') ? 'write' : process.argv.includes('--check') ? 'check' : 'dry';

// Normalização no LEITOR (não edita arquivo): grafia/enum → canônico (ADR 0257).
const NORM_STATUS = { accepted: 'aceito', aceita: 'aceito', proposed: 'proposto' };
const NORM_LIFECYCLE = { active: 'ativo', canon: 'ativo', feature_wish: 'historical' };

function field(fm, key) {
  const m = fm.match(new RegExp(`^${key}:\\s*(.+)$`, 'mi'));
  return m ? m[1].trim().replace(/^["']|["']$/g, '') : '';
}
function numbersFrom(fm, key) {
  // captura [0028] inline OU lista multilinha "- '0028'"
  const inline = fm.match(new RegExp(`^${key}:\\s*\\[([^\\]]*)\\]`, 'mi'));
  if (inline) return (inline[1].match(/\d{3,4}/g) || []);
  const block = fm.match(new RegExp(`^${key}:\\s*\\n((?:\\s*-\\s*.+\\n?)+)`, 'mi'));
  if (block) return (block[1].match(/\d{3,4}/g) || []);
  return [];
}

const adrs = [];
for (const file of readdirSync(join(ROOT, DIR)).sort()) {
  const m = file.match(/^(\d{4})-(.+)\.md$/);
  if (!m) continue;
  const [num, slug] = [m[1], `${m[1]}-${m[2]}`];
  const txt = readFileSync(join(ROOT, DIR, file), 'utf8');
  let rec = { num, slug, title: '', status: '', lifecycle: '', kind: 'decision', supersedes: [], superseded_by: [], hasFrontmatter: false };
  if (txt.startsWith('---')) {
    const end = txt.indexOf('\n---', 3);
    const fm = end === -1 ? txt : txt.slice(0, end);
    rec.hasFrontmatter = true;
    rec.title = field(fm, 'title');
    rec.status = (field(fm, 'status') || '').toLowerCase();
    rec.lifecycle = (field(fm, 'lifecycle') || '').toLowerCase();
    rec.kind = (field(fm, 'kind') || 'decision').toLowerCase();
    rec.supersedes = numbersFrom(fm, 'supersedes');
    rec.superseded_by = numbersFrom(fm, 'superseded_by');
  } else {
    // ADR formato-tabela legado (sem YAML frontmatter)
    rec.title = (txt.match(/^#\s*(.+)$/m) || [])[1] || slug;
    const st = txt.match(/\|\s*\*\*Status\*\*\s*\|\s*([A-Za-zçãíéó]+)/i);
    rec.status = (st ? st[1] : '').toLowerCase();
  }
  rec.statusN = NORM_STATUS[rec.status] || rec.status || '(vazio)';
  rec.lifecycleN = NORM_LIFECYCLE[rec.lifecycle] || rec.lifecycle || '(vazio)';
  adrs.push(rec);
}

// ── agregações ────────────────────────────────────────────────────────────
const byNum = {};
for (const a of adrs) (byNum[a.num] ??= []).push(a);
const collisions = Object.entries(byNum).filter(([, v]) => v.length > 1);
const uniqueNums = Object.keys(byNum).length;
const maxNum = Object.keys(byNum).sort().at(-1);

const tally = (arr, k) => arr.reduce((o, a) => ((o[a[k]] = (o[a[k]] || 0) + 1), o), {});
const byStatus = tally(adrs, 'statusN');
const byLifecycle = tally(adrs, 'lifecycleN');
const ativos = adrs.filter((a) => a.lifecycleN === 'ativo').length;
const semFrontmatter = adrs.filter((a) => !a.hasFrontmatter);

// integridade de supersessão
const exists = (n) => !!byNum[n];
const supWarn = [];
for (const a of adrs) {
  for (const t of a.supersedes) {
    if (!exists(t)) { supWarn.push(`${a.num} supersedes ${t} → ADR ${t} NÃO existe`); continue; }
    const target = byNum[t].some((x) => x.lifecycleN === 'substituido' || x.statusN === 'superseded');
    if (!target) supWarn.push(`${a.num} supersedes ${t} → ${t} NÃO está marcada substituido/superseded ⚠️`);
  }
  if ((a.statusN === 'superseded' || a.lifecycleN === 'substituido') && a.superseded_by.length === 0)
    supWarn.push(`${a.num} é superseded mas sem superseded_by (órfã)`);
}

// ── render ──────────────────────────────────────────────────────────────────
const fmtTally = (o) => Object.entries(o).sort((x, y) => y[1] - x[1]).map(([k, v]) => `${k} ${v}`).join(' · ');
let md = `# ADR Index — GERADO (não editar à mão)

> ⚙️ **Auto-gerado** por \`scripts/governance/adr-index-generate.mjs\` a partir de \`memory/decisions/[0-9]*.md\`.
> Fonte única (modelo Log4brains/adr-tools, estado-da-arte 2026). Regenerar: \`node scripts/governance/adr-index-generate.mjs --write\`.
> Status/lifecycle normalizados no leitor (ADR 0257) — não altera os arquivos (append-only).

## Resumo
- **${adrs.length}** arquivos · **${uniqueNums}** números únicos · máx **${maxNum}**
- **ADRs ATIVOS (lifecycle ativo): ${ativos}** ← resposta única a "quantos ADRs ativos"
- Por status: ${fmtTally(byStatus)}
- Por lifecycle: ${fmtTally(byLifecycle)}
- Sem frontmatter (formato-tabela legado): ${semFrontmatter.length}${semFrontmatter.length ? ' — ' + semFrontmatter.map((a) => a.num).join(', ') : ''}

## Colisões de número (${collisions.length}) — auto-detectadas
${collisions.length ? collisions.map(([n, v]) => `- **${n}** ×${v.length}: ${v.map((x) => x.slug).join(' · ')}`).join('\n') : '_(nenhuma)_'}

## Integridade de supersessão (${supWarn.length} alertas)
${supWarn.length ? supWarn.map((w) => `- ⚠️ ${w}`).join('\n') : '_(íntegra)_'}

## Todas as ADRs (${adrs.length})
| Nº | Status | Lifecycle | Kind | Título |
|---|---|---|---|---|
${adrs.map((a) => `| ${a.num} | ${a.statusN} | ${a.lifecycleN} | ${a.kind} | ${(a.title || a.slug).replace(/\|/g, '/').slice(0, 80)} |`).join('\n')}
`;

if (MODE === 'check') {
  const cur = existsSync(join(ROOT, OUT)) ? readFileSync(join(ROOT, OUT), 'utf8') : '';
  if (cur.trim() !== md.trim()) {
    console.error(`✗ ${OUT} está DESATUALIZADO — rode --write. (índice gerado ≠ commitado = drift)`);
    process.exit(1);
  }
  console.log(`✓ ${OUT} em dia (${adrs.length} ADRs).`);
  process.exit(0);
}
if (MODE === 'write') {
  writeFileSync(join(ROOT, OUT), md);
  console.log(`✓ ${OUT} gerado — ${adrs.length} ADRs, ${ativos} ativos, ${collisions.length} colisões, ${supWarn.length} alertas de supersessão.`);
} else {
  console.log(md.split('\n').slice(0, 30).join('\n'));
  console.log(`\n[dry-run] ${adrs.length} ADRs · ${ativos} ativos · ${collisions.length} colisões · ${supWarn.length} alertas. Rode --write pra gerar.`);
}
