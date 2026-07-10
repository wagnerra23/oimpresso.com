#!/usr/bin/env node
// charter-live-signal.mjs — gate de SINAL pra charter `status: live` (proposta SDD 2026-06-24).
//
// POR QUE EXISTE: na reconciliação do Cliente (2026-06-24) um charter foi promovido a
// `status: live` SEM prova de produção — as telas eram flag-gated (MWART_CLIENTE_*, default
// OFF, fallback Blade) e a máquina deu verde (o check de zumbi do anchor-lint é CEGO a flag:
// `existsSync`+render-graph dizem "existe", não "está live pra tenant real"). Quem pegou foi o
// adversário humano + o Wagner confirmando "biz=4 está no react". Conhecimento tribal, não sinal.
//
// O QUE FAZ: pra cada `*.charter.md` com `status: live`, exige um SINAL de que a tela está viva
// em produção — ou o `component` listado em governance/prod-flags.json `live` (>=1 business_id),
// ou hits>0 em governance/route-hits.json `pages` (execução REAL — ledger do middleware
// ContadorHitsRota, 2026-07-09), ou um campo `smoke:` no frontmatter (ref a um smoke datado).
// Sem nenhum → `live_sem_sinal`. `live = evidência, não palavra`. fs-puro (2 JSON + walk). Sem deps/DB/PHP.
//
// NÃO substitui anchor-lint (âncora spec<->código) nem doneness-lint (status:done × âncora no
// SPEC). Concern próprio (SoC): a HONESTIDADE do `status: live` do CHARTER contra prod.
//
// USO (na raiz do repo):
//   node scripts/governance/charter-live-signal.mjs              # full-tree, report humano (exit 0)
//   node scripts/governance/charter-live-signal.mjs --json       # JSON determinístico (sem timestamp/sha)
//   node scripts/governance/charter-live-signal.mjs <charter ...> # diff-aware: só os charters passados
//   node scripts/governance/charter-live-signal.mjs --check [<charter ...>]  # exit 1 se live_sem_sinal
//
// ADVISORY DE NASCENÇA (ADR 0271/0275): no CI roda DIFF-AWARE (`--check` só nos charters TOCADOS
// no PR) — morde só `status: live` NOVO/TOCADO sem sinal (no-new-lie); os ~54 live legados sem
// sinal NÃO avermelham (grandfather por não-toque). Cron/full-tree = report (exit 0, dívida visível).
// Promoção a required = flip do Wagner por calendário (ADR 0275 §5). Teto ADR 0298: estende, não cria.

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join, resolve, relative } from 'node:path';

const ROOT = process.cwd();
const JSON_OUT = process.argv.includes('--json');
const CHECK = process.argv.includes('--check');
const PAGES = join(ROOT, 'resources', 'js', 'Pages');
const FLAGS_PATH = join(ROOT, 'governance', 'prod-flags.json');

function loadLive() {
  if (!existsSync(FLAGS_PATH)) return {};
  try { return JSON.parse(readFileSync(FLAGS_PATH, 'utf8')).live || {}; }
  catch (e) { console.error(`charter-live-signal: prod-flags.json não parseia (${e.message})`); process.exit(2); }
}
const LIVE = loadLive();

// 3ª FONTE de sinal (2026-07-09 · grade v3 "verificação runtime"): ledger de
// execução real governance/route-hits.json (`php artisan route-hits:export
// --write` no prod — coleta middleware ContadorHitsRota). pages[<component>]
// com hits>0 na janela = a tela foi de fato SERVIDA — sinal mais forte que
// flag ligada (flag diz "pode servir"; hit diz "serviu"). Aditivo: só cria
// caminho NOVO pra live_ok, nunca avermelha o que hoje passa. Ausente = {}.
const HITS_PATH = join(ROOT, 'governance', 'route-hits.json');
function loadHitsPages() {
  if (!existsSync(HITS_PATH)) return {};
  try { return JSON.parse(readFileSync(HITS_PATH, 'utf8')).pages || {}; }
  catch (e) { console.error(`charter-live-signal: route-hits.json não parseia (${e.message})`); process.exit(2); }
}
const HITS_PAGES = loadHitsPages();

function walk(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) walk(p, acc);
    else if (e.name.endsWith('.charter.md')) acc.push(p);
  }
  return acc;
}

function frontmatter(body) {
  const m = body.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  return m ? m[1] : '';
}
function field(fm, key) {
  const m = fm.match(new RegExp(`^${key}:\\s*(.+)$`, 'm'));
  return m ? m[1].trim().replace(/^["']|["']$/g, '') : null;
}
// component (resources/js/Pages/X.tsx) -> key "X"
function compKey(component) {
  if (!component) return null;
  const m = component.replace(/\\/g, '/').match(/resources\/js\/Pages\/(.+)\.tsx$/);
  return m ? m[1] : null;
}

// seleção: args posicionais (diff-aware) ou full-tree
const args = process.argv.slice(2).filter((a) => !a.startsWith('--'));
const files = args.length
  ? args.map((a) => resolve(ROOT, a)).filter((p) => p.endsWith('.charter.md') && existsSync(p))
  : walk(PAGES);

const rows = [];
for (const f of files.sort()) {
  const fm = frontmatter(readFileSync(f, 'utf8'));
  if (field(fm, 'status') !== 'live') continue; // só status:live entra
  const key = compKey(field(fm, 'component'));
  const smoke = field(fm, 'smoke');
  const biz = key && Array.isArray(LIVE[key]) ? LIVE[key] : [];
  const hit = key && HITS_PAGES[key] && HITS_PAGES[key].hits > 0 ? HITS_PAGES[key] : null;
  const signal = biz.length
    ? `prod-flags:biz=${biz.join(',')}`
    : (hit ? `route-hits:${hit.hits}hit@${hit.ultima_data}` : (smoke ? `smoke:${smoke}` : null));
  rows.push({ rel: relative(ROOT, f).replace(/\\/g, '/'), key, signal, state: signal ? 'live_ok' : 'live_sem_sinal' });
}
const semSinal = rows.filter((r) => r.state === 'live_sem_sinal');

if (JSON_OUT) {
  process.stdout.write(JSON.stringify({
    _meta: { lint: 'charter status:live precisa de sinal de prod (prod-flags.json, route-hits.json ou smoke:)', generator: 'scripts/governance/charter-live-signal.mjs', regra: 'live_ok = component em governance/prod-flags.json `live` (>=1 biz) OU hits>0 em governance/route-hits.json `pages` (execução REAL na janela do export — middleware ContadorHitsRota) OU campo smoke:. live_sem_sinal = nenhum. Fontes aditivas: route-hits só cria caminho novo pra live_ok, nunca avermelha. fs-puro, sem timestamp/sha (re-run sem mudança = diff vazio).', escopo: args.length ? 'diff-aware (args)' : 'full-tree' },
    summary: { live_total: rows.length, live_ok: rows.length - semSinal.length, live_sem_sinal: semSinal.length },
    rows,
  }, null, 2) + '\n');
  process.exit(0);
}

console.log(`\n  CHARTER LIVE SIGNAL — \`status: live\` precisa de sinal de prod (governance/prod-flags.json · governance/route-hits.json · \`smoke:\`) · escopo: ${args.length ? 'diff-aware' : 'full-tree'}`);
console.log(`  live: ${rows.length} · com sinal: ${rows.length - semSinal.length} · SEM sinal: ${semSinal.length}\n`);
for (const r of semSinal) console.log(`  ⚠️ ${r.rel} (${r.key}): \`status: live\` SEM sinal de prod → adicione \`${r.key}\` em governance/prod-flags.json \`live\`, OU campo \`smoke:\` datado no charter, OU gere o ledger de hits (route-hits:export) com a tela servida.`);
if (!semSinal.length) console.log('  ✓ todo charter `status: live` carrega sinal de prod (prod-flags.json, route-hits.json ou smoke).');
console.log(`\n  live_sem_sinal = "diz live mas a máquina não tem prova de prod" — o buraco que deixou promover charter a live sem evidência (reconciliação Cliente 2026-06-24). Nunca afirmar live sem sinal.\n`);

if (CHECK && semSinal.length > 0) process.exit(1);
process.exit(0);
