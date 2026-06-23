#!/usr/bin/env node
// detect-handoff.mjs — DETECTOR-EM-LOTE do G4 ("paste zip → 1 tarefa por tela").
// Dado o diff do `prototipo-ui/cowork/` (o sync do export), mapeia CADA tela mudada
// → seed (via seed-tela.mjs) e emite os CHIP-SPECS. O assistente lê o --json e cria
// 1 `spawn_task` por chip não-skip (o clique do Wagner = aceitar a tarefa → sessão
// limpa + worktree isolada). Fecha o G4 (o passo "detectar + montar a tarefa" deixa
// de ser manual). spawn_task é tool MCP (só o assistente chama) — por isso o script
// PARA no chip-spec; não dispara a sessão sozinho.
//
// Ponte file→tela = `visual_source:` dos charters (resources/js/Pages/**). Classificação
// 🔵/🟠/⚪ = FRESCOR-PRODUCAO-vs-PROTOTIPO.md (best-effort: 🔵 = produção à frente = NÃO vira chip).
//
// Uso:
//   node scripts/governance/detect-handoff.mjs                 (diff HEAD~1..HEAD — o último sync committado)
//   node scripts/governance/detect-handoff.mjs --base <ref>
//   node scripts/governance/detect-handoff.mjs --working       (working tree vs HEAD — teste/pré-commit)
//   node scripts/governance/detect-handoff.mjs --json
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join, relative } from 'node:path';
import { execSync } from 'node:child_process';

const ROOT = process.cwd();
const PAGES = 'resources/js/Pages';
const args = process.argv.slice(2);
const asJson = args.includes('--json');
const working = args.includes('--working');
const bi = args.indexOf('--base');
const base = bi >= 0 ? args[bi + 1] : 'HEAD~1';

function changedCowork() {
  const range = working ? 'HEAD' : `${base}..HEAD`;
  let out = '';
  try { out = execSync(`git diff --name-only --diff-filter=AM ${range} -- prototipo-ui/cowork`, { cwd: ROOT, encoding: 'utf8' }); } catch {}
  return out.split('\n').map((s) => s.trim()).filter((f) => /\.(jsx|tsx|css|html)$/.test(f));
}

function charterFiles(dir, out = []) {
  if (!existsSync(dir)) return out;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) charterFiles(p, out);
    else if (e.name.endsWith('.charter.md')) out.push(p);
  }
  return out;
}

// reverse map: prototipo-ui/cowork/<file> -> { charterRel, telaArg }
function buildMap() {
  const map = {};
  for (const abs of charterFiles(join(ROOT, PAGES))) {
    const fm = (readFileSync(abs, 'utf8').match(/^---\n([\s\S]*?)\n---/) || [, ''])[1];
    const m = fm.match(/^visual_source:\s*["']?(prototipo-ui\/cowork\/\S+?)["']?\s*$/m);
    if (!m) continue;
    const charterRel = relative(ROOT, abs).replace(/\\/g, '/');
    const telaArg = charterRel.replace(/^resources\/js\/Pages\//, '').replace(/\.charter\.md$/, '');
    map[m[1]] = { charterRel, telaArg };
  }
  return map;
}

function frescorTag(telaArg) {
  const f = join(ROOT, 'prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md');
  if (!existsSync(f)) return '?';
  const txt = readFileSync(f, 'utf8');
  const last = telaArg.split('/').pop();
  for (const line of txt.split('\n')) {
    if (line.includes(telaArg) || (last.length > 3 && line.includes(last))) {
      if (line.includes('🔵')) return '🔵 produção à frente (sem chip)';
      if (line.includes('🟠')) return '🟠 desenvolver';
      if (line.includes('⚪')) return '⚪ fundação (gated)';
    }
  }
  return '? (sem frescor — confirme antes)';
}

const changed = changedCowork();
const map = buildMap();
const screens = {}; const unmapped = [];
for (const f of changed) {
  if (map[f]) { const t = map[f].telaArg; (screens[t] ||= { tela: t, charter: map[f].charterRel, files: [] }).files.push(f); }
  else unmapped.push(f);
}
const chips = [];
for (const t of Object.keys(screens)) {
  const s = screens[t];
  const tag = frescorTag(t);
  let seed = null;
  try { seed = JSON.parse(execSync(`node scripts/governance/seed-tela.mjs "${t}" --json`, { cwd: ROOT, encoding: 'utf8' })); } catch {}
  chips.push({ tela: t, tag, skip: tag.startsWith('🔵'), changed_files: s.files, charter: s.charter, gap: seed?.gap || null, seed });
}

if (asJson) { console.log(JSON.stringify({ range: working ? 'working' : `${base}..HEAD`, changed: changed.length, chips, unmapped }, null, 2)); process.exit(0); }

console.log(`detect-handoff — diff cowork/ (${working ? 'working tree vs HEAD' : base + '..HEAD'})`);
console.log(`cowork/ mudados: ${changed.length} · telas mapeadas: ${chips.length} · não-mapeados: ${unmapped.length}\n`);
for (const c of chips) {
  console.log(`${c.skip ? '⏭️ SKIP' : '🔲 CHIP'}: ${c.tela}  [${c.tag}]`);
  console.log(`   arquivos: ${c.changed_files.join(', ')}`);
  console.log(`   charter: ${c.charter} · gap: ${c.gap || '_pendente_'}`);
}
if (unmapped.length) {
  console.log(`\n⚠️ mudaram mas SEM charter \`visual_source:\` apontando pra eles (não dá pra rotear → adicione visual_source no charter da tela):`);
  for (const u of unmapped) console.log(`   - ${u}`);
}
if (!changed.length) console.log('Nenhuma mudança no cowork/ neste range. (Sem export novo → o G4 puxa telas com gap aberto pelo FRESCOR, manualmente.)');
console.log(`\n→ assistente: pra cada 🔲 CHIP não-skip, criar 1 spawn_task (title=tela · prompt=seed.* · tldr). Rode com --json pra consumir.`);
