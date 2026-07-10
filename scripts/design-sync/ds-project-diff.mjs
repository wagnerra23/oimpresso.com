#!/usr/bin/env node
// ds-project-diff.mjs — DIFF-FIRST do loop design→code: canon do git × DS vivo (claude.ai/design).
//
// POR QUE EXISTE (proposta loop otimizado 2026-07-10): a fricção nº1 medida do loop Cowork↔Code
// é o PROMPT STALE (L-42) — o Cowork descreve um estado que já divergiu do git ("adicione os 56
// tokens" quando já existiam; "ds-v6 deletado" quando o mirror ainda o tem). Hoje cada prompt
// custa validação MANUAL contra git+DesignSync. Este script é o passo que MECANIZA essa validação:
// dado o canon do git (companion `cockpit_domains.css`, gerado do SSOT) e o inventário do DS vivo
// (lido via DesignSync `get_file`), reporta o DELTA real → só delta vira ação. O prompt stale é
// auto-refutado antes de virar trabalho.
//
// Uso:
//   node scripts/design-sync/ds-project-diff.mjs --companion <cockpit_domains.css> --live <snapshot.txt>
//   node scripts/design-sync/ds-project-diff.mjs --selftest
//
// `--live` = arquivo com os nomes de token (`--nome`, um por linha OU um CSS) do DS vivo. Produzido
// por um passo DesignSync `get_file colors_and_type.css` (leitura é livre; ADR 0315). Não é
// hardcode: é o retrato da fonte viva no momento da rodada.
//
// Regex de token INCLUI MAIÚSCULA (`--origin-CRM-bg`) — o furo repetido de `[a-z0-9-]+` (2026-07-10,
// pego 2× nesta própria sessão) fica travado pelo selftest abaixo.
//
// Exit 0 = em sincronia (delta 0). Exit 2 = há delta (ação: push/pull). Exit 1 = uso.

import { readFileSync } from 'node:fs';

const TOKEN_RE = /(--[A-Za-z0-9-]+)\s*:/g;       // : inclui maiúscula (origin-CRM)
export function tokensDe(css) {
  const out = new Set();
  let m;
  while ((m = TOKEN_RE.exec(css)) !== null) out.add(m[1]);
  return out;
}
export function nomesDe(txt) {
  // aceita "um --nome por linha" OU um CSS (extrai via TOKEN_RE)
  const linhas = txt.split(/\r?\n/).map((l) => l.trim()).filter((l) => l.startsWith('--') && !l.includes(':'));
  if (linhas.length) return new Set(linhas);
  return tokensDe(txt);
}

const GRUPOS = ['origin', 'stage', 'sla', 'canal', 'kpi-feature', 'kind', 'vip'];
export function diff(companionCss, liveTxt) {
  const canon = [...tokensDe(companionCss)].sort();
  const vivo = nomesDe(liveTxt);
  const ausentes = canon.filter((t) => !vivo.has(t));
  const presentes = canon.filter((t) => vivo.has(t));
  const porGrupo = {};
  for (const g of GRUPOS) {
    const n = ausentes.filter((t) => t.slice(2).toLowerCase().startsWith(g)).length;
    if (n) porGrupo[g] = n;
  }
  return { total: canon.length, presentes, ausentes, porGrupo };
}

function selftest() {
  let fails = 0;
  const ok = (label, got, exp) => { const p = JSON.stringify(got) === JSON.stringify(exp); if (!p) fails++; console.log(`  [${p ? 'PASS' : 'FAIL'}] ${label} → ${JSON.stringify(got)}${p ? '' : ' ≠ ' + JSON.stringify(exp)}`); };
  const comp = '.cockpit {\n --origin-CRM-bg: oklch(0.92 0.06 220);\n --kind-customer: oklch(0.4 0.11 145);\n --kind-customer-soft: oklch(0.94 0.04 145);\n --sla-fresh-dot: oklch(0.52 0.13 145);\n}';
  // ANTI-REGRESSÃO do furo lowercase: --origin-CRM-bg (maiúscula) TEM que ser extraído.
  ok('extrai token com MAIÚSCULA (--origin-CRM-bg)', tokensDe(comp).has('--origin-CRM-bg'), true);
  const live = '--kind-customer\n--vip';
  const d = diff(comp, live);
  ok('companion count', d.total, 4);
  ok('presente = kind-customer', d.presentes, ['--kind-customer']);
  ok('ausentes = 3 (origin-CRM-bg, kind-customer-soft, sla-fresh-dot)', d.ausentes.length, 3);
  ok('porGrupo detecta origin ausente', d.porGrupo.origin, 1);
  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — diff-first provado (incl. anti-regressão do regex maiúscula).');
  process.exit(fails ? 1 : 0);
}

const argv = process.argv.slice(2);
if (argv.includes('--selftest')) selftest();
const opt = (f) => { const i = argv.indexOf(f); return i >= 0 ? argv[i + 1] : null; };
const compPath = opt('--companion'), livePath = opt('--live');
if (!compPath || !livePath) { console.error('uso: --companion <cockpit_domains.css> --live <snapshot> | --selftest'); process.exit(1); }
const d = diff(readFileSync(compPath, 'utf8'), readFileSync(livePath, 'utf8'));
console.log(`DIFF-FIRST · canon(git) × DS vivo`);
console.log(`  companion: ${d.total} · já no vivo: ${d.presentes.length} · AUSENTES: ${d.ausentes.length}`);
for (const [g, n] of Object.entries(d.porGrupo)) console.log(`    --${g}-* ausente: ${n}`);
if (d.ausentes.length) console.log(`  >>> AÇÃO: ${d.ausentes.length} tokens faltam no DS vivo → push cockpit_domains.css (design-sync)`);
else console.log(`  ✓ em sincronia — nada a fazer`);
process.exit(d.ausentes.length ? 2 : 0);
