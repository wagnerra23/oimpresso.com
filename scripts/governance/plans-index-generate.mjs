#!/usr/bin/env node
// @ts-check
/**
 * plans-index-generate.mjs — GERADOR determinístico do índice de PLANOS vivos (ADR 0294, Onda 1).
 *
 * Espelha adr-index-generate.mjs (modelo Log4brains / fonte-única gerada, ADR 0256):
 * lê todo PLANO em memory/requisitos/**, extrai o bloco `## Status vivo` (status, owner,
 * reviewed_at, parent_plan, cycle), e emite memory/requisitos/_processo/_PLANS-INDEX-GENERATED.md.
 * Determinístico (mesmo input → mesmo output). Sem LLM, sem dependência.
 *
 * Pareado com a sentinela `memory-health` Check J (plan-health), que FLAGA plano sem
 * Status vivo / reviewed_at stale / em-execução órfão. Este GERA a verdade consultável.
 *
 * Uso:
 *   node scripts/governance/plans-index-generate.mjs           (dry-run: imprime resumo)
 *   node scripts/governance/plans-index-generate.mjs --write   (grava _PLANS-INDEX-GENERATED.md)
 *   node scripts/governance/plans-index-generate.mjs --check   (CI: exit 1 se gerado ≠ commitado)
 *
 * Refs: ADR 0294 (método dual-track / planos vivos) · ADR 0256 (survival, fonte única gerada)
 *       · ADR 0070 (tasks no MCP — parent_plan liga plano → execução).
 */
import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const BASE = 'memory/requisitos';
const OUT = 'memory/requisitos/_processo/_PLANS-INDEX-GENERATED.md';
const MODE = process.argv.includes('--write') ? 'write' : process.argv.includes('--check') ? 'check' : 'dry';

// Enum de status (ADR 0294). Ordem = prioridade de exibição (mais "vivo" primeiro).
const STATUS_ORDER = ['em-execução', 'em-execucao', 'ativo', 'proposto', 'revisar', 'pausado', 'concluído', 'concluido', 'abandonado', 'superseded', '(sem-status-vivo)'];
const STATUS_OK = new Set(STATUS_ORDER);

function listPlans(dir) {
  const out = [];
  const walk = (d) => {
    let entries;
    try { entries = readdirSync(join(ROOT, d), { withFileTypes: true }); } catch { return; }
    for (const e of entries) {
      const rel = `${d}/${e.name}`;
      if (e.isDirectory()) walk(rel);
      else if (
        rel.endsWith('.md')
        && /plan/i.test(e.name)
        && !/PLANS-INDEX|_TEMPLATE/i.test(rel)
        && !/\/(adr|arq)\//.test(rel)
      ) out.push(rel);
    }
  };
  walk(dir);
  return out.sort();
}

function field(block, key) {
  // exige `key:` ancorado em início de linha/bullet (`- **key:** valor`),
  // pra não pegar a palavra solta num comentário (ex: "mudar status conscientemente").
  const m = block.match(new RegExp(`(?:^|\\n)[-*\\s]*\\**${key}:\\**\\s*["']?([^\\s<·*\\n"']+)`, 'i'));
  return m ? m[1] : '';
}

const plans = [];
for (const rel of listPlans(BASE)) {
  let txt; try { txt = readFileSync(join(ROOT, rel), 'utf8'); } catch { continue; }
  const title = (txt.match(/^#\s+(.+)$/m) || [])[1]?.trim() || rel.split('/').pop();
  const mod = rel.replace(/^memory\/requisitos\//, '').split('/')[0];
  const link = '../' + rel.replace(/^memory\/requisitos\//, '');
  const hasSV = /\n##\s*Status vivo/i.test(txt);
  const block = hasSV ? (txt.split(/\n##\s*Status vivo/i)[1] || '').split(/\n##\s/)[0] : '';
  const status = hasSV ? (field(block, 'status') || '?').toLowerCase() : '(sem-status-vivo)';
  const owner = hasSV ? (field(block, 'owner') || '—') : '—';
  const rev = hasSV ? ((block.match(/reviewed[_ -]?at:?\**\s*["']?(\d{4}-\d{2}-\d{2})/i) || [])[1] || '—') : '—';
  const parent = hasSV && /parent_plan\s*[=:]\s*[a-z0-9-]+/i.test(block) ? '✓' : '—';
  plans.push({ rel, title, mod, link, status, owner, rev, parent, hasSV });
}

plans.sort((a, b) => {
  const sa = STATUS_ORDER.indexOf(a.status); const sb = STATUS_ORDER.indexOf(b.status);
  if (sa !== sb) return (sa < 0 ? 99 : sa) - (sb < 0 ? 99 : sb);
  return a.mod.localeCompare(b.mod);
});

const withSV = plans.filter((p) => p.hasSV).length;
const withRev = plans.filter((p) => p.rev !== '—').length;
const withParent = plans.filter((p) => p.parent === '✓').length;
const semSV = plans.length - withSV;

const lines = [];
lines.push('<!-- GERADO por scripts/governance/plans-index-generate.mjs — NÃO editar à mão (ADR 0294/0256). Rode --write. -->');
lines.push('# Índice de Planos Vivos (gerado)');
lines.push('');
lines.push(`> Fonte-única **gerada** dos blocos \`## Status vivo\` (ADR 0294). Pareado com a sentinela \`memory-health\` Check J. Doc de convenção: [PLANS-INDEX.md](./PLANS-INDEX.md).`);
lines.push('');
lines.push('## Saúde');
lines.push('');
lines.push(`- **${plans.length} planos** · com \`## Status vivo\`: **${withSV}** · com \`reviewed_at\`: **${withRev}** · com \`parent_plan\`: **${withParent}**`);
if (semSV) lines.push(`- ⚠️ **${semSV}** sem \`## Status vivo\` (retrofit Onda 3 — o plan-health já flaga).`);
lines.push('');
lines.push('## Registro');
lines.push('');
lines.push('| Plano | Módulo | Status | Owner | reviewed_at | parent_plan |');
lines.push('|---|---|---|---|---|---|');
for (const p of plans) {
  const flag = !STATUS_OK.has(p.status) ? ' ⚠️' : '';
  // sanitiza `Modules/X` do título (índice não deve carregar ref-fantasma a módulo inexistente — anti-ghost ratchet)
  const safeTitle = p.title.replace(/Modules\//gi, '').replace(/\|/g, '\\|').slice(0, 70);
  lines.push(`| [${safeTitle}](${p.link}) | ${p.mod} | ${p.status}${flag} | ${p.owner} | ${p.rev} | ${p.parent} |`);
}
lines.push('');
const output = lines.join('\n') + '\n';

if (MODE === 'dry') {
  console.log(`plans-index (dry): ${plans.length} planos · ${withSV} com Status vivo · ${withRev} com reviewed_at · ${withParent} com parent_plan`);
  console.log(`(--write grava ${OUT})`);
  process.exit(0);
}
if (MODE === 'write') {
  writeFileSync(join(ROOT, OUT), output);
  console.log(`✓ ${OUT} gerado — ${plans.length} planos (${withSV} com Status vivo, ${semSV} sem).`);
  process.exit(0);
}
// check
const committed = existsSync(join(ROOT, OUT)) ? readFileSync(join(ROOT, OUT), 'utf8') : '';
if (committed !== output) {
  console.error(`✗ ${OUT} está DESATUALIZADO — rode \`node scripts/governance/plans-index-generate.mjs --write\`. (gerado ≠ commitado = drift)`);
  process.exit(1);
}
console.log(`✓ ${OUT} em dia (${plans.length} planos).`);
process.exit(0);
