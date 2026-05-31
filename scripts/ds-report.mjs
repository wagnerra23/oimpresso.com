#!/usr/bin/env node
// scripts/ds-report.mjs — placar de adoção do Design System (ds/* por regra × módulo)
// + checklist da fila (--worklist / --write) que o [CC] Cowork lê pra saber o pendente.
//
// O baseline (scripts/eslint-baseline.mjs) agrega TODO ds/* sob `no-restricted-syntax`;
// este quebra por mensagem (`ds/no-native-select`…) e por módulo. Com `--write`, grava um
// "placar de tarefas" vivo no DS_ADOCAO_INDICE.md entre marcadores — é o canal §10.2 que o
// Cowork lê (Sync now) pra saber o que o Code JÁ executou (✅) e o que falta (☐).
//
// Uso:
//   node scripts/ds-report.mjs                 # tabela por regra + módulo
//   node scripts/ds-report.mjs --json          # JSON (CI / scorecard)
//   node scripts/ds-report.mjs --module Sells  # filtra um módulo (tabela)
//   node scripts/ds-report.mjs --module Sells --json [--target N]  # CARTÃO DE EVIDÊNCIA (ADR 0240):
//        {module,total,by_rule,target,pass,measured_against_sha} — o artefato que FECHA a tarefa
//   node scripts/ds-report.mjs --worklist      # checklist da fila (✅/☐) no stdout
//   node scripts/ds-report.mjs --write         # idem + grava no DS_ADOCAO_INDICE.md
//
// Refs: ADR 0209 (ratchet), ADR 0239 (gov DS git=SSOT), ADR 0240 (evidência fecha task), PROTOCOL §10.

import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

const TARGET = 'resources/js';
const AS_JSON = process.argv.includes('--json');
const AS_WORKLIST = process.argv.includes('--worklist');
const DO_WRITE = process.argv.includes('--write');
const modIdx = process.argv.indexOf('--module');
const ONLY_MODULE = modIdx !== -1 ? process.argv[modIdx + 1] : null;
const tgtIdx = process.argv.indexOf('--target');
const TARGET_DS = tgtIdx !== -1 ? Number(process.argv[tgtIdx + 1]) : 0; // alvo de fechamento (default 0)

// Fila canônica de execução (PR-C-WORKLIST.md — módulo = ID, "C#" deprecado).
// Ordem = prioridade de migração. ✅ quando ds/*=0 nesse módulo (todas as fases limpas).
const WORKLIST = [
  'Sells', 'RecurringBilling', 'OficinaAuto', 'Repair', 'Purchase',
  'Admin', 'Whatsapp', 'Settings', 'Financeiro', 'Cliente',
];

const INDICE_PATH = resolve(process.cwd(), 'prototipo-ui/DS_ADOCAO_INDICE.md');
const MARK_START = '<!-- ds:worklist:start (auto · npm run ds:report -- --write) -->';
const MARK_END = '<!-- ds:worklist:end -->';

function runEslint() {
  const cmd = `npx --no-install eslint "${TARGET}" --format=json --max-warnings=999999`;
  try {
    return JSON.parse(execSync(cmd, {
      encoding: 'utf8', maxBuffer: 100 * 1024 * 1024,
      stdio: ['ignore', 'pipe', 'ignore'], shell: true,
    }));
  } catch (err) {
    if (err.stdout) return JSON.parse(err.stdout);
    throw err;
  }
}

// SHA do commit onde a medição foi feita — anti-stale (§10.4): a evidência diz contra o quê foi medida.
function gitSha() {
  try {
    return execSync('git rev-parse HEAD', { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  } catch { return 'unknown'; }
}

const RULE_RE = /^(ds\/[a-z0-9-]+)/;
function moduleOf(path) {
  const m = path.replace(/\\/g, '/').match(/\/(?:Pages|Modules)\/([^/]+)/);
  return m ? m[1] : '(outros)';
}

function collect() {
  const cwd = process.cwd().replace(/\\/g, '/');
  const results = runEslint();
  const byRule = {}, byModule = {}, byModuleRule = {};
  let total = 0;
  for (const result of results) {
    const path = result.filePath.replace(/\\/g, '/').replace(`${cwd}/`, '');
    const mod = moduleOf(path);
    if (ONLY_MODULE && mod.toLowerCase() !== ONLY_MODULE.toLowerCase()) continue;
    for (const msg of result.messages) {
      const mm = (msg.message || '').match(RULE_RE);
      if (!mm) continue;
      byRule[mm[1]] = (byRule[mm[1]] || 0) + 1;
      byModule[mod] = (byModule[mod] || 0) + 1;
      byModuleRule[`${mod}|${mm[1]}`] = (byModuleRule[`${mod}|${mm[1]}`] || 0) + 1;
      total++;
    }
  }
  return { total, byRule, byModule, byModuleRule };
}

// markdown do checklist — a "fila viva" que o [CC] lê pra saber o pendente
function worklistMarkdown({ total, byModule }) {
  const stamp = new Date().toISOString().slice(0, 16).replace('T', ' ') + ' UTC';
  const done = WORKLIST.filter((m) => (byModule[m] || 0) === 0).length;
  const L = [];
  L.push(MARK_START);
  L.push('## Status da fila — placar de execução (auto)');
  L.push('');
  L.push(`> Gerado por \`npm run ds:report -- --write\` · ${stamp} · **total \`ds/*\` = ${total}** · fila ${done}/${WORKLIST.length} ✅.`);
  L.push('> Derivado do `ds/*` real por módulo: **✅ = 0 (concluído)** · **☐ = pendente**. `[CC]` lê isto (Sync now) pra saber o que `[CL]` JÁ executou e o que falta — sem regerar o já-feito.');
  L.push('');
  L.push('| # | Módulo (fila) | `ds/*` | Status |');
  L.push('|---|---|---:|---|');
  WORKLIST.forEach((mod, i) => {
    const n = byModule[mod] || 0;
    L.push(`| ${i + 1} | ${mod} | ${n} | ${n === 0 ? '✅ concluído' : '☐ pendente'} |`);
  });
  const extras = Object.entries(byModule)
    .filter(([m, n]) => n > 0 && !WORKLIST.includes(m) && m !== '(outros)')
    .sort((a, b) => b[1] - a[1]);
  if (extras.length) {
    L.push('');
    L.push('**Fora da fila (pendentes · ordem por contagem):** ' +
      extras.map(([m, n]) => `${m} (${n})`).join(' · '));
  }
  const next = WORKLIST.find((m) => (byModule[m] || 0) > 0);
  L.push('');
  L.push(`**Próximo da fila:** ${next ? `${next} (${byModule[next]})` : '— tudo zerado 🎉'}`);
  L.push(MARK_END);
  return L.join('\n');
}

function writeIndice(md) {
  if (!existsSync(INDICE_PATH)) { console.error(`nao achei ${INDICE_PATH}`); process.exit(1); }
  let txt = readFileSync(INDICE_PATH, 'utf8');
  const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  if (txt.includes(MARK_START) && txt.includes(MARK_END)) {
    txt = txt.replace(new RegExp(`${esc(MARK_START)}[\\s\\S]*?${esc(MARK_END)}`), md);
  } else {
    txt = txt.replace(/(^#[^\n]*\n)/, `$1\n${md}\n`);
  }
  writeFileSync(INDICE_PATH, txt);
  console.log('checklist escrito em prototipo-ui/DS_ADOCAO_INDICE.md');
}

function printReport({ total, byRule, byModule }) {
  const pad = (s, n) => String(s).padEnd(n);
  const lpad = (s, n) => String(s).padStart(n);
  console.log(`\n  DS adoption · ds/* = ${total}${ONLY_MODULE ? ` · módulo ${ONLY_MODULE}` : ''}\n`);
  console.log('  Por regra');
  console.log('  ' + '-'.repeat(48));
  const rules = Object.entries(byRule).sort((a, b) => b[1] - a[1]);
  if (!rules.length) console.log('  (nenhuma)');
  for (const [rule, n] of rules) {
    const pct = total ? ((n / total) * 100).toFixed(1) : '0.0';
    console.log(`  ${pad(rule, 30)} ${lpad(n, 5)}  ${lpad(pct, 5)}%`);
  }
  console.log('\n  Por módulo (top 20)');
  console.log('  ' + '-'.repeat(48));
  const mods = Object.entries(byModule).sort((a, b) => b[1] - a[1]).slice(0, 20);
  if (!mods.length) console.log('  (nenhum)');
  for (const [mod, n] of mods) console.log(`  ${pad(mod, 30)} ${lpad(n, 5)}`);
  console.log(`\n  Meta: ds/* -> 0. Hoje: ${total}.`);
  if (total === 0) console.log('  ZERO — adocao completa.');
  console.log('');
}

function main() {
  const data = collect();
  // cartão de EVIDÊNCIA de fechamento (ADR 0240): `--module=X --json` ⇒ o artefato que FECHA a migração DS.
  // pass=true só quando ds/* <= target (default 0). measured_against_sha amarra a evidência ao commit (§10.4).
  if (AS_JSON && ONLY_MODULE) {
    console.log(JSON.stringify({
      module: ONLY_MODULE,
      total: data.total,
      by_rule: data.byRule,
      target: TARGET_DS,
      pass: data.total <= TARGET_DS,
      measured_against_sha: gitSha(),
      generated_at: new Date().toISOString(),
    }, null, 2));
    return;
  }
  if (AS_JSON) { console.log(JSON.stringify({ ...data, generated_at: new Date().toISOString() }, null, 2)); return; }
  if (DO_WRITE) { const md = worklistMarkdown(data); writeIndice(md); console.log('\n' + md + '\n'); return; }
  if (AS_WORKLIST) { console.log('\n' + worklistMarkdown(data) + '\n'); return; }
  printReport(data);
}

main();
