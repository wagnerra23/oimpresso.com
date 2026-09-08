#!/usr/bin/env node
// design-coverage.mjs — mapa de cobertura de DESIGN por tela + catraca (só sobe).
//
// Complementa screen-coverage-map.mjs (que cobre CHARTER/E2E/SCORECARD/A11Y) com a dimensão
// que faltava: a tela DECLARA sua FONTE DE DESIGN? (protótipo bespoke via related_prototype,
// OU "n/a — segue DS/padrão" explícito). Sem isso a tela é SILENCIOSA — ninguém sabe de onde
// veio o design nem qual Padrão de Tela ela herda (UI-0013). Isto é "a parte de Design" por tela.
//
// NÃO duplica ancora.mjs — CONSOME `ancora.mjs --list --json` (o resolvedor canônico de âncora).
// A catraca ratcheta `declared` (telas com fonte declarada) — só sobe, nunca regride.
//
// Uso:
//   node scripts/qa/design-coverage.mjs           # relatório (read-only)
//   node scripts/qa/design-coverage.mjs --json     # + grava baseline
//   node scripts/qa/design-coverage.mjs --check    # exit 1 se `declared` regrediu vs baseline
//
// Contrato: Constituição UI v2 (UI-0013 — camadas/herança de Padrão de Tela) + ADR 0299/ancora.

import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, relative } from 'node:path';
import { raizesDePages } from './page-path.mjs';

const ROOT = process.cwd();
const ANCORA = join(ROOT, 'prototipo-ui', 'ancora.mjs');
const PAGES = join(ROOT, 'resources', 'js', 'Pages');
const argi = process.argv.indexOf('--baseline');
const BASELINE = argi >= 0 && process.argv[argi + 1]
  ? process.argv[argi + 1]
  : join(ROOT, 'memory', 'governance', 'design-coverage-baseline.json');

// 1. Fonte de design declarada por charter (via ancora --json)
let rows;
try {
  rows = JSON.parse(execFileSync('node', [ANCORA, '--list', '--json'], { encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 }));
} catch (e) {
  console.error(`design-coverage: falha ao rodar ancora.mjs --list --json: ${e.message}`);
  process.exit(2);
}
const totalCharters = rows.length;
const declared = rows.filter((r) => r.hasSource).length;
const silent = totalCharters - declared;
// ── 3o balde: `n/a` cuja PREMISSA CADUCOU (report-only, FORA da catraca) ───────────
// Por que existe (2026-08-26): `related_prototype: n/a (herda PT-0X)` conta como ✅ pra
// SEMPRE — mas é uma decisão DATADA, tomada quando a fonte de design não estava no git.
// O espelho recebe bundle novo toda semana (o de 24/08 trouxe 255 arquivos e 5 protótipos
// inéditos) e NINGUÉM re-pergunta a decisão quando a premissa dela deixa de valer. Medido:
// 20 commits em 90d religando âncora/ponteiro à mão, e 163 telas sem âncora resolvível.
//
// Isto SÓ REPORTA. Resolver âncora por convenção de NOME dentro de um `--check` é guard
// sintático — morto no §5 2026-06-30 — e o residual não é decidível por máquina (ex.:
// `Financeiro/Advisor/Login` ancorado em `financeiro-page.jsx` é julgamento humano).
//
// DÍVIDA DECLARADA: a convenção abaixo é a MESMA de `criar-tela.mjs`
// (`detectarPrototipoDoModulo`). Não importei porque aquele módulo
// NÃO tem guard de entrypoint — medido: `import()` dispara o CLI e sai 1. Unificar exige o
// guard, que reindenta ~160 linhas do bloco CLI e merece PR próprio.
const modDoCharter = (rel) => { const m = /(?:^|\/)Pages\/([^/]+)\//.exec(rel); return m ? m[1] : null; };
const naComFonteCandidata = rows.filter((r) => {
  if (!r.isNa || !r.charter) return false;
  const mod = modDoCharter(r.charter);
  return !!mod && existsSync(join(ROOT, 'prototipo-ui', 'cowork', mod.toLowerCase() + '-page.jsx'));
});


// ── EIXO PARIDADE (protótipo↔produção) — Onda 7 do programa-ondas ──────────────────
// A pergunta AQUI não é a de cima. Acima: "a tela declara DE ONDE veio seu design?".
// Aqui: "existe medição de que a tela BATE com esse design?" — o artefato é
// `memory/requisitos/<Mod>/<Tela>-visual-comparison.md` (PROTOCOLO-COMPARACAO-RUNTIME).
//
// MEDIDO 2026-09-08, e é por isso que o vínculo é LIDO DO CHARTER, nunca adivinhado:
//   84 inventários · só 24 têm campo `tela:` · destes, só 16 casam com charter existente.
//   Casar por NOME de arquivo seria guard sintático (§5 2026-06-30) — os nomes reais variam
//   entre `cockpit-`, `cliente-index-`, `ProducaoOficina-r2-…`, `CaixaUnificadaV4-`.
// O lado forte é o charter: `related_visual_comparison:` carrega um PATH verificável.
//
// REPORT-ONLY no que é dívida herdada (órfãos, quebrados). A catraca trava só
// `parityLinked`, e ela SÓ SOBE — forward-only: charter novo nasce declarando, o legado
// desce por onda, nunca por backfill em massa (§5 2026-07-12).
const CHAVES_VC = ['related_visual_comparison:', 'visual_comparison:'];
function vinculoDoCharter(txt) {
  for (const linha of txt.split(String.fromCharCode(10))) {
    const t = linha.trim();
    for (const k of CHAVES_VC) {
      if (t.startsWith(k)) {
        let v = t.slice(k.length).trim();
        if (v.startsWith('"') && v.endsWith('"')) v = v.slice(1, -1);
        if (v.startsWith('./')) v = v.slice(2);
        return v.trim() || null;
      }
    }
  }
  return null;
}
let inventarios = [];
try {
  inventarios = execFileSync('git', ['ls-files', 'memory/requisitos/**/*visual-comparison*.md'], { encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 })
    .split(String.fromCharCode(10)).map((x) => x.trim()).filter(Boolean);
} catch { /* sem git o eixo fica vazio — o relatório diz isso em vez de fingir zero */ }
const apontados = new Set();
const parityBroken = [];
let parityLinked = 0;
for (const r of rows) {
  if (!r.charter) continue;
  const abs = join(ROOT, r.charter);
  if (!existsSync(abs)) continue;
  const bruto = vinculoDoCharter(readFileSync(abs, 'utf8'));
  if (!bruto) continue;
  // o path pode vir da raiz do repo OU relativo ao charter — aceita os dois, sem adivinhar nome
  const daRaiz = join(ROOT, bruto);
  const doCharter = join(ROOT, r.charter, '..', bruto);
  const achou = existsSync(daRaiz) ? daRaiz : (existsSync(doCharter) ? doCharter : null);
  if (achou) { parityLinked += 1; apontados.add(relative(ROOT, achou).split(String.fromCharCode(92)).join('/')); }
  else parityBroken.push(r.charter + ' -> ' + bruto);
}
const parityOrphan = inventarios.filter((f) => !apontados.has(f));

// 2. Contexto: páginas SEM charter nenhum (gap mais fundo — nem contrato de design têm)
function walkTsx(dir) {
  const out = [];
  for (const e of readdirSync(dir)) {
    const p = join(dir, e);
    const st = statSync(p);
    if (st.isDirectory()) { if (e !== '_components' && e !== '_partials') out.push(...walkTsx(p)); }
    else if (e.endsWith('.tsx') && !e.endsWith('.charter.tsx') && !e.includes('.test.')) out.push(p);
  }
  return out;
}
// Duas raízes desde o PR #5686 (núcleo + `Modules/<X>/Resources/js/Pages`): varrer só o núcleo
// media 172 charters de 209. `raizesDePages` é o dono da lista — não reimplementar a 2ª aqui.
const pages = raizesDePages(ROOT).flatMap((raiz) => walkTsx(raiz));
const noCharter = pages.filter((p) => !existsSync(p.replace(/\.tsx$/, '.charter.md'))).length;

const pct = (n, d) => d ? Math.round((n / d) * 100) : 0;

// ── --json: grava baseline ──
if (process.argv.includes('--json')) {
  writeFileSync(BASELINE, JSON.stringify({ declared, totalCharters, parityLinked, note: 'Catraca de cobertura de design: `declared` (telas com fonte de design declarada) só sobe. Baixar exige decisão consciente.' }, null, 2) + '\n');
  console.log(`baseline gravado: declared=${declared}/${totalCharters}`);
  process.exit(0);
}

// ── --check: catraca ──
if (process.argv.includes('--check')) {
  if (!existsSync(BASELINE)) { console.error('design-coverage: baseline ausente — rode --json pra semear.'); process.exit(1); }
  const base = JSON.parse(readFileSync(BASELINE, 'utf8'));
  if (declared < base.declared) {
    console.error(`design-coverage: cobertura de design REGREDIU — declared ${declared} < baseline ${base.declared}. Uma tela perdeu a fonte de design declarada.`);
    process.exit(1);
  }
  // Back-compat deliberado: baseline semeado antes da Onda 7 nao tem `parityLinked`.
  // Ausente => eixo NAO cobrado (nunca lido como 0, que reprovaria o repo inteiro).
  if (typeof base.parityLinked === 'number' && parityLinked < base.parityLinked) {
    console.error(`design-coverage: PARIDADE regrediu — parityLinked ${parityLinked} < baseline ${base.parityLinked}. Um charter perdeu o vinculo com seu inventario de paridade.`);
    process.exit(1);
  }
  const eixoParidade = typeof base.parityLinked === 'number'
    ? ` · parityLinked ${parityLinked} ≥ ${base.parityLinked}`
    : ' · paridade: baseline sem o campo (eixo nao cobrado ainda)';
  console.log(`design-coverage: OK — declared ${declared} ≥ baseline ${base.declared} (catraca)${eixoParidade}.`);
  process.exit(0);
}

// ── relatório ──
console.log('═══ COBERTURA DE DESIGN (fonte declarada por tela · UI-0013) ═══');
console.log(`charters de página : ${totalCharters}`);
console.log(`  ✅ fonte declarada (protótipo ou "segue DS") : ${declared}  (${pct(declared, totalCharters)}%)`);
console.log(`  ⚠️  silenciosa (sem fonte declarada)          : ${silent}  (${pct(silent, totalCharters)}%)`);
console.log('  🕰️  n/a com fonte candidata JA no espelho    : ' + naComFonteCandidata.length + '  (report-only — a decisão n/a é datada; a fonte desceu depois)');
if (naComFonteCandidata.length) {
  console.log('     (revisar a decisão — a escolha final é humana, nunca automática):');
  for (const r of naComFonteCandidata.slice(0, 12)) console.log('       ' + r.charter);
  if (naComFonteCandidata.length > 12) console.log('       … +' + (naComFonteCandidata.length - 12));
}
console.log(`\ncontexto — páginas .tsx SEM charter algum      : ${noCharter} (gap mais fundo)`);
console.log(`\ncatraca: 'declared' só sobe. Fechar = declarar o Padrão de Tela / protótipo no charter (a parte de Design).`);

console.log('');
console.log('═══ EIXO PARIDADE (protótipo↔produção · Onda 7) ═══');
console.log(`inventários de paridade no repo               : ${inventarios.length}`);
console.log(`  🔗 vinculados a um charter (path existe)     : ${parityLinked}`);
console.log(`  🧩 órfãos — nenhum charter os aponta         : ${parityOrphan.length}  (report-only · dívida herdada)`);
console.log(`  ❌ vínculo QUEBRADO (charter aponta p/ nada) : ${parityBroken.length}`);
for (const b of parityBroken.slice(0, 8)) console.log('       ' + b);
if (parityBroken.length > 8) console.log('       … +' + (parityBroken.length - 8));
console.log('');
console.log('como fechar: declarar related_visual_comparison: no charter da tela (path do');
console.log('<Tela>-visual-comparison.md). O vínculo NÃO é adivinhado por nome de arquivo —');
console.log(`medido 2026-09-08: só 16 dos ${inventarios.length} inventários casariam por convenção.`);
console.log("catraca: 'parityLinked' só sobe. Órfão e quebrado são report-only (forward-only).");
