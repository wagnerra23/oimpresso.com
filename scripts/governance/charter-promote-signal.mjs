#!/usr/bin/env node
// charter-promote-signal.mjs — passe REPETÍVEL de promoção draft→live guiado por SINAL de prod.
//
// POR QUE EXISTE: a cobertura de charters (2026-07-12) nasce 100% `status: draft` — de propósito,
// porque `live = evidência` e o gate required `charter-live-signal` bloqueia live sem prova de prod.
// A promoção então é GATED POR DADO (route-hits/prod-flags), não por vontade. Este script é o
// inverso do `charter-live-signal`: em vez de PUNIR live-sem-sinal, ele PROMOVE draft-COM-sinal —
// repetível: conforme o `route-hits.json` for alimentado em prod (`php artisan route-hits:export
// --write`, middleware ContadorHitsRota), rodar de novo promove o que passou a ter hit.
//
// FONTES DE SINAL (idênticas ao charter-live-signal — SoC: mesma verdade de prod):
//   - governance/prod-flags.json `live[<key>]` com >=1 business_id (flag ligada pra tenant real)
//   - governance/route-hits.json `pages[<key>].hits > 0` (execução REAL na janela do export)
//   - campo `smoke:` no frontmatter (ref a smoke datado)
// key = component "resources/js/Pages/X.tsx" -> "X" (mesma compKey do gate).
//
// SEGURANÇA: NÃO promove charter com placeholder não-preenchido (`TODO Wagner`/`❌ TODO`) — esses
// ainda esperam a revisão humana dos Non-Goals/Anti-hooks (anti-alucinação, skill charter-write).
//
// USO (na raiz do repo):
//   node scripts/governance/charter-promote-signal.mjs            # --report: o que É promovível hoje
//   node scripts/governance/charter-promote-signal.mjs --json      # JSON determinístico
//   node scripts/governance/charter-promote-signal.mjs --apply [--date YYYY-MM-DD]  # flipa draft→live
//   node scripts/governance/charter-promote-signal.mjs --apply resources/js/Pages/X.charter.md ...  # subset
//
// fs-puro (2 JSON + walk). Sem deps/DB/PHP. Não substitui o gate charter-live-signal (que segue
// vigiando que live=sinal); este só AUTOMATIZA a promoção do que já tem sinal.

import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join, resolve, relative } from 'node:path';

const ROOT = process.cwd();
const JSON_OUT = process.argv.includes('--json');
const APPLY = process.argv.includes('--apply');
const dateIdx = process.argv.indexOf('--date');
const DATE = dateIdx >= 0 && process.argv[dateIdx + 1]
  ? process.argv[dateIdx + 1]
  : new Date().toISOString().slice(0, 10);
const PAGES = join(ROOT, 'resources', 'js', 'Pages');

function loadJson(rel, prop) {
  const p = join(ROOT, rel);
  if (!existsSync(p)) return {};
  try { return JSON.parse(readFileSync(p, 'utf8'))[prop] || {}; }
  catch (e) { console.error(`charter-promote-signal: ${rel} não parseia (${e.message})`); process.exit(2); }
}
const LIVE = loadJson('governance/prod-flags.json', 'live');
const HITS = loadJson('governance/route-hits.json', 'pages');

function walk(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) walk(p, acc);
    else if (e.name.endsWith('.charter.md')) acc.push(p);
  }
  return acc;
}
const fm = (body) => (body.match(/^---\n([\s\S]*?)\n---/) || [null, ''])[1];
const field = (block, k) => {
  const m = block.match(new RegExp('^' + k + ':\\s*(.+)$', 'm'));
  return m ? m[1].trim().replace(/^["']|["']$/g, '') : null;
};
function compKey(component) {
  if (!component) return null;
  const m = component.replace(/\\/g, '/').match(/resources\/js\/Pages\/(.+)\.tsx$/);
  return m ? m[1] : null;
}
function signalOf(block) {
  const key = compKey(field(block, 'component'));
  const biz = key && Array.isArray(LIVE[key]) ? LIVE[key] : [];
  if (biz.length) return `prod-flags:biz=${biz.join(',')}`;
  if (key && HITS[key] && HITS[key].hits > 0) return `route-hits:${HITS[key].hits}`;
  if (field(block, 'smoke')) return `smoke:${field(block, 'smoke')}`;
  return null;
}

const argFiles = process.argv.slice(2).filter((a) => !a.startsWith('--') && a !== DATE);
const files = argFiles.length
  ? argFiles.map((a) => resolve(ROOT, a)).filter((p) => p.endsWith('.charter.md') && existsSync(p))
  : walk(PAGES);

const promovivel = [], bloqueado = [], nao_draft = [];
for (const f of files.sort()) {
  const body = readFileSync(f, 'utf8');
  const block = fm(body);
  const rel = relative(ROOT, f).replace(/\\/g, '/');
  if (field(block, 'status') !== 'draft') { nao_draft.push(rel); continue; }
  const signal = signalOf(block);
  const placeholder = /TODO Wagner|❌ TODO/.test(body);
  if (signal && !placeholder) promovivel.push({ rel, signal });
  else bloqueado.push({ rel, motivo: signal ? 'placeholder-nao-preenchido' : 'sem-sinal-de-prod' });
}

function promote(f) {
  let body = readFileSync(f, 'utf8');
  const signal = signalOf(fm(body));
  body = body.replace(/^status:\s*draft\s*$/m, 'status: live');
  body = body.replace(/^last_validated:\s*.+$/m, `last_validated: "${DATE}"`);
  body = body.replace(/^(#\s+Page Charter.*?)\(DRAFT\)/m, '$1(LIVE)');
  if (!/_Promovido draft→live/.test(body)) {
    body = body.replace(/\s*$/, `\n\n> _Promovido draft→live em ${DATE} por \`charter-promote-signal.mjs\` — sinal: ${signal}._\n`);
  }
  writeFileSync(f, body);
}

if (APPLY) {
  for (const { rel } of promovivel) promote(join(ROOT, rel));
}

if (JSON_OUT) {
  console.log(JSON.stringify({ date: DATE, applied: APPLY, promovivel, bloqueado_count: bloqueado.length, nao_draft_count: nao_draft.length }, null, 2));
  process.exit(0);
}

console.log('═══ charter-promote-signal (draft→live guiado por sinal de prod) ═══');
console.log(`drafts com sinal (promovíveis): ${promovivel.length}  ·  drafts bloqueados: ${bloqueado.length}  ·  já não-draft: ${nao_draft.length}`);
for (const p of promovivel) console.log(`  ${APPLY ? '✔ promovido' : '→ promovível'}: ${p.rel}  [${p.signal}]`);
if (!promovivel.length) console.log('  (nenhum draft com sinal de prod hoje — alimente governance/route-hits.json via `route-hits:export --write` no prod)');
const semSinal = bloqueado.filter((b) => b.motivo === 'sem-sinal-de-prod').length;
const ph = bloqueado.filter((b) => b.motivo === 'placeholder-nao-preenchido').length;
console.log(`\nbloqueados: ${semSinal} sem sinal de prod · ${ph} com placeholder pendente de Wagner`);
if (APPLY) console.log(`\n✔ ${promovivel.length} charter(s) flipado(s) pra live (data ${DATE}). Rode charter-live-signal --check pra confirmar.`);
else console.log(`\n(report — nada mudou. Use --apply pra flipar.)`);
