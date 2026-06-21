#!/usr/bin/env node
// plan-health.mjs — sentinela de PLANOS órfãos/podres (ADR 0294 Onda 1 · catraca da
// membrana dual-track). Fecha o buraco "meus planos se perdem": valida o Índice de
// Planos Vivos (memory/requisitos/_processo/PLANS-INDEX.md) + o bloco `## Status vivo`
// de cada plano. Espelha memory-health.mjs (ADR 0256) apontado pros planos.
//
// Flaga (por ADR 0294 + PLANS-INDEX §2):
//   - status ausente / fora do enum
//   - reviewed_at ausente ('nunca'/'—') ou > 30 dias (frescor)
//   - órfão: status em-execução SEM parent_plan (não ligado ao MCP)
//   - superseded SEM ponteiro (verdade-viva → novo)
//   - índice apontando pra arquivo inexistente (dangling)
//   - plano registrado sem o bloco `## Status vivo`
// (drift status≠tasks fica de fora: precisa do MCP — Onda 2.)
//
// No-op gracioso se PLANS-INDEX.md ausente (ainda não mergeado no main) → exit 0.
// Advisory por padrão (exit 0); --check morde (exit 1) pra virar ratchet depois.
//
// USO (raiz do repo):
//   node scripts/governance/plan-health.mjs            # relatório (exit 0)
//   node scripts/governance/plan-health.mjs --json     # pro Daily Brief / agregador
//   node scripts/governance/plan-health.mjs --check    # exit 1 se houver achado (CI ratchet)
// Node puro, sem deps, sem rede.

import { readFileSync, existsSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';

const ROOT = process.cwd();
const JSON_OUT = process.argv.includes('--json');
const CHECK = process.argv.includes('--check');
const STALE_DAYS = 30;
const STATUS_ENUM = ['proposto', 'ativo', 'em-execução', 'em-execucao', 'pausado', 'concluído', 'concluido', 'abandonado', 'superseded', 'revisar'];
const INDEX_REL = 'memory/requisitos/_processo/PLANS-INDEX.md';

const findings = []; // {plan, level: 'warn'|'fail', issue}
const add = (plan, issue, level = 'warn') => findings.push({ plan, issue, level });

const indexPath = join(ROOT, INDEX_REL);
if (!existsSync(indexPath)) {
  const msg = `PLANS-INDEX ausente (${INDEX_REL}) — nada a checar (ainda não mergeado no main?).`;
  if (JSON_OUT) console.log(JSON.stringify({ ok: true, skipped: true, reason: msg, findings: [] }, null, 2));
  else console.log(`\n  plan-health — ⊘ ${msg}\n`);
  process.exit(0);
}

const indexDir = dirname(indexPath);
const indexBody = readFileSync(indexPath, 'utf8');

// Extrai os planos da tabela do índice: 1ª coluna `[label](relpath)`.
const rows = indexBody.split('\n').filter((l) => /^\|/.test(l) && /\]\(/.test(l));
const plans = [];
for (const row of rows) {
  const m = row.match(/\[([^\]]+)\]\(([^)]+)\)/);
  if (!m) continue;
  plans.push({ label: m[1].trim(), rel: m[2].trim(), abs: resolve(indexDir, m[2].trim()) });
}

const today = new Date();
const daysSince = (iso) => {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  return Math.floor((today - d) / 86_400_000);
};

for (const p of plans) {
  if (!existsSync(p.abs)) {
    add(p.label, `índice aponta pra arquivo inexistente: ${p.rel}`, 'fail');
    continue;
  }
  const body = readFileSync(p.abs, 'utf8');

  // Região do bloco `## Status vivo` — heading ANCORADO em início de linha (senão
  // casa com menções inline tipo "ver `## Status vivo` abaixo" → falso negativo).
  const blines = body.split(/\r?\n/);
  const hi = blines.findIndex((l) => /^##\s+Status vivo\b/i.test(l.trim()));
  if (hi === -1) {
    add(p.label, 'sem bloco `## Status vivo` (não rastreável — ADR 0294)');
    continue; // sem bloco, os demais campos não fazem sentido
  }
  let raw = '';
  for (let i = hi + 1; i < blines.length && !/^##\s/.test(blines[i].trim()); i++) raw += blines[i] + '\n';
  // Normaliza markdown: tira comentários HTML (carregam # e · que sujam o parse),
  // negrito e backticks → os campos `status:`/`reviewed_at:` ficam parseáveis.
  const bloco = raw.replace(/<!--[\s\S]*?-->/g, '').replace(/\*\*/g, '').replace(/`/g, '');

  // status
  const statusM = bloco.match(/status:\s*([^\n·]+)/i);
  const status = statusM ? statusM[1].trim().toLowerCase().split(/\s/)[0] : null;
  if (!status) add(p.label, 'status ausente no bloco Status vivo');
  else if (!STATUS_ENUM.includes(status)) add(p.label, `status fora do enum: "${status}"`);

  // reviewed_at (frescor)
  const revM = bloco.match(/reviewed_at:\s*([0-9]{4}-[0-9]{2}-[0-9]{2}|nunca|—|-)/i);
  const rev = revM ? revM[1].trim() : null;
  if (!rev || /nunca|—|^-$/i.test(rev)) {
    add(p.label, 'reviewed_at ausente (frescor não rastreado)');
  } else {
    const d = daysSince(rev);
    if (d !== null && d > STALE_DAYS) add(p.label, `reviewed_at stale: ${d}d (> ${STALE_DAYS}d)`);
  }

  // órfão: em-execução sem parent_plan
  const emExec = status && status.startsWith('em-execu');
  const temParent = /parent_plan\s*=?\s*\S/i.test(bloco);
  if (emExec && !temParent) add(p.label, 'órfão: em-execução SEM parent_plan (não ligado ao MCP)', 'fail');

  // superseded sem ponteiro
  if (status === 'superseded' && !/verdade-viva[\s\S]*?\]\(|superseded_by|→\s*\[/i.test(bloco)) {
    add(p.label, 'superseded SEM ponteiro pro plano novo');
  }
}

const fails = findings.filter((f) => f.level === 'fail');
const warns = findings.filter((f) => f.level === 'warn');
const ok = CHECK ? findings.length === 0 : fails.length === 0;

if (JSON_OUT) {
  console.log(JSON.stringify({
    ok, planos: plans.length, fail: fails.length, warn: warns.length, findings,
  }, null, 2));
  process.exit(ok ? 0 : 1);
}

console.log(`\n  plan-health — ${plans.length} planos · ${fails.length} 🔴 · ${warns.length} 🟡\n`);
for (const f of fails) console.error(`  🔴 ${f.plan}: ${f.issue}`);
for (const f of warns) console.log(`  🟡 ${f.plan}: ${f.issue}`);
if (!findings.length) console.log('  ✓ todos os planos saudáveis (status + frescor + ligação MCP).');
console.log('');

if (CHECK && findings.length) { console.error(`  ✗ ${findings.length} achado(s) — modo --check morde.\n`); process.exit(1); }
if (fails.length) { console.error(`  ✗ ${fails.length} 🔴 — planos quebrados (índice dangling / órfão em-execução).\n`); process.exit(1); }
process.exit(0);
