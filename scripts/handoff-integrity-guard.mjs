#!/usr/bin/env node
// scripts/handoff-integrity-guard.mjs — catraca de Integridade do Handoff (PROCESSO_MEMORIA_CC.md §16 · IT8).
//
// A fila de handoff (prototipo-ui/COWORK_NOTES.md, ACIMA da linha d'água) e os prompts duráveis
// (prototipo-ui/PROMPT_PARA_CODE_*.md) têm que casar. Dois apodrecimentos invisíveis que ninguém
// vê na hora — e que hoje nada trava:
//   • ÓRFÃO     — PROMPT_PARA_CODE_*.md existe no dir mas NÃO é citado acima da linha → tarefa invisível.
//   • REF MORTA — citação de PROMPT_PARA_CODE_*.md acima da linha que NÃO existe no dir → [CL] erra o PR.
//   • FUNDIDO   — linha (fila ativa OU prompt) que funde dois cabeçalhos numa só (`:** > **`) → bloco ilegível (C3).
//
// Ratchet (gêmeo de scripts/pageheader-migration-guard.mjs e scripts/tests/foundation-ratchet.mjs):
// o baseline CONGELA a dívida atual; só ÓRFÃO/REF-MORTA NOVO (fora do baseline) falha. Abaixo da
// linha d'água = histórico append-only, ignorado.
//
// Marcador (em COWORK_NOTES.md):  <!-- LINHA-DAGUA-HANDOFF -->   (acima = ativo · abaixo = processado)
//
// Uso:
//   node scripts/handoff-integrity-guard.mjs                  # CI: NOVO órfão/ref-morta → exit 1
//   node scripts/handoff-integrity-guard.mjs --report         # lista tudo (diagnóstico), não falha
//   node scripts/handoff-integrity-guard.mjs --write-baseline # congela o estado atual (dívida)
//   node scripts/handoff-integrity-guard.mjs --json           # saída JSON (data-only, exit 0)
// Flags de teste (controle-negativo): --root <dir> [--queue <p>] [--handoff-dir <p>] [--baseline <p>]
//
// Refs: PROCESSO_MEMORIA_CC.md §16 · IT8 · ADR 0239 (git=SSOT, mata URL efêmera) · ADR 0271/0275 (advisory).

import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { resolve, relative, isAbsolute } from 'node:path';

const argv = process.argv.slice(2);
const has = (f) => argv.includes(f);
const val = (f, d) => { const i = argv.indexOf(f); return i >= 0 && argv[i + 1] ? argv[i + 1] : d; };

const ROOT = resolve(val('--root', process.cwd()));
const abs = (flag, def) => { const v = val(flag, def); return isAbsolute(v) ? v : resolve(ROOT, v); };

const QUEUE = abs('--queue', 'prototipo-ui/COWORK_NOTES.md');
const HANDOFF_DIR = abs('--handoff-dir', 'prototipo-ui');
const BASELINE = abs('--baseline', 'config/handoff-integrity-baseline.json');

const MODE_WRITE = has('--write-baseline');
const MODE_REPORT = has('--report');
const MODE_JSON = has('--json');

const WATERLINE = 'LINHA-DAGUA-HANDOFF';
const PROMPT_FILE = /^PROMPT_PARA_CODE_[A-Za-z0-9._-]+\.md$/; // arquivo no dir
const CITE_RE = /PROMPT_PARA_CODE_[A-Za-z0-9._-]+\.md/g;      // citação no texto (ignora `*` de glob)

function hardFail(msg) { console.error(msg); process.exit(2); }

// --- coleta -----------------------------------------------------------------
if (!existsSync(QUEUE)) hardFail(`❌ fila de handoff ausente: ${relative(ROOT, QUEUE)}`);
const queueRaw = readFileSync(QUEUE, 'utf8');

// Região ativa = tudo ANTES do marcador. Sem marcador → fila inteira é ativa (+ aviso).
const wIdx = queueRaw.indexOf(WATERLINE);
const hasMarker = wIdx >= 0;
const activeRegion = hasMarker ? queueRaw.slice(0, wIdx) : queueRaw;

const cited = new Set(activeRegion.match(CITE_RE) || []);

const files = existsSync(HANDOFF_DIR)
  ? readdirSync(HANDOFF_DIR).filter((n) => PROMPT_FILE.test(n))
  : [];
const fileSet = new Set(files);

const orphans = files.filter((f) => !cited.has(f)).sort();              // existe, não citado
const deadRefs = [...cited].filter((c) => !fileSet.has(c)).sort();      // citado, não existe

// C3 — cabeçalho de bloco fundido: `:** > **` numa linha (dois headers colados por edição).
// Varre a fila ATIVA (acima da linha) + cada PROMPT_PARA_CODE_*.md. Abaixo da linha = ignorado.
const C3_RE = /:\*\*\s*>\s*\*\*/;
const fusedIn = (label, text) => text.split(/\r?\n/)
  .filter((l) => C3_RE.test(l))
  .map((l) => `${label}::${l.trim().slice(0, 100)}`);
const fused = [
  ...fusedIn('COWORK_NOTES.md', activeRegion),
  ...files.flatMap((f) => fusedIn(f, readFileSync(resolve(HANDOFF_DIR, f), 'utf8'))),
].sort();

// --- write baseline ---------------------------------------------------------
if (MODE_WRITE) {
  const out = {
    _meta: {
      generated_at: new Date().toISOString(),
      orphans: orphans.length,
      dead_refs: deadRefs.length,
      fused_headers: fused.length,
      note: 'Integridade do handoff (PROCESSO_MEMORIA_CC.md §16 · IT8). Congela a dívida atual de órfãos/refs-mortas/cabeçalhos-fundidos acima da linha d\'água em COWORK_NOTES.md + prompts. Só NOVO trava. Marcador: LINHA-DAGUA-HANDOFF.',
    },
    orphans,
    dead_refs: deadRefs,
    fused_headers: fused,
  };
  writeFileSync(BASELINE, JSON.stringify(out, null, 2) + '\n');
  console.log(`✅ Baseline gravado: ${orphans.length} órfão(s) + ${deadRefs.length} ref(s) morta(s) + ${fused.length} fundido(s) → ${relative(ROOT, BASELINE)}`);
  process.exit(0);
}

// --- baseline + diff --------------------------------------------------------
const baseline = existsSync(BASELINE) ? JSON.parse(readFileSync(BASELINE, 'utf8')) : { orphans: [], dead_refs: [] };
const baseOrphans = new Set(baseline.orphans || []);
const baseDead = new Set(baseline.dead_refs || []);
const baseFused = new Set(baseline.fused_headers || []);

const newOrphans = orphans.filter((o) => !baseOrphans.has(o));
const newDeadRefs = deadRefs.filter((d) => !baseDead.has(d));
const newFused = fused.filter((f) => !baseFused.has(f));
const fixedOrphans = [...baseOrphans].filter((o) => !orphans.includes(o));
const fixedDead = [...baseDead].filter((d) => !deadRefs.includes(d));
const fixedFused = [...baseFused].filter((f) => !fused.includes(f));

if (MODE_JSON) {
  console.log(JSON.stringify({
    has_marker: hasMarker, files: files.length, cited: cited.size,
    orphans, dead_refs: deadRefs, fused_headers: fused,
    new_orphans: newOrphans, new_dead_refs: newDeadRefs, new_fused: newFused,
    fixed_orphans: fixedOrphans, fixed_dead_refs: fixedDead, fixed_fused: fixedFused,
  }, null, 2));
  process.exit(0);
}

// --- job summary (GitHub) ---------------------------------------------------
const sumFile = process.env.GITHUB_STEP_SUMMARY;
if (sumFile) {
  const rows = [
    '### Integridade do handoff (fila ↔ prompts)', '',
    '| Métrica | Atual (baseline) |', '|---|---|',
    `| Prompts no dir | ${files.length} |`,
    `| Citados acima da linha | ${cited.size} |`,
    `| Órfãos | ${orphans.length} (${baseOrphans.size}) |`,
    `| Refs mortas | ${deadRefs.length} (${baseDead.size}) |`,
    `| Cabeçalhos fundidos | ${fused.length} (${baseFused.size}) |`,
    `| 🔴 NOVO órfão | ${newOrphans.length} |`,
    `| 🔴 NOVA ref morta | ${newDeadRefs.length} |`,
    `| 🔴 NOVO fundido | ${newFused.length} |`,
  ];
  if (newOrphans.length) rows.push('', '**Órfãos novos:** ' + newOrphans.join(', '));
  if (newDeadRefs.length) rows.push('', '**Refs mortas novas:** ' + newDeadRefs.join(', '));
  if (newFused.length) rows.push('', '**Fundidos novos:** ' + newFused.join(' · '));
  try { writeFileSync(sumFile, rows.join('\n') + '\n', { flag: 'a' }); } catch { /* noop */ }
}

if (!hasMarker) {
  console.log(`⚠️  Marcador <!-- ${WATERLINE} --> ausente em ${relative(ROOT, QUEUE)} — fila inteira tratada como ATIVA. Adicione o marcador (PROCESSO_MEMORIA_CC.md §16).`);
}
console.log(`Handoff integrity · ${files.length} prompt(s) no dir · ${cited.size} citado(s) acima da linha · órfãos ${orphans.length}/${baseOrphans.size} · refs mortas ${deadRefs.length}/${baseDead.size} · fundidos ${fused.length}/${baseFused.size}`);

// --report: diagnóstico completo, sem falhar por baseline ---------------------
if (MODE_REPORT) {
  if (orphans.length) { console.log('\n🔴 ÓRFÃOS (existem no dir, não citados acima da linha):'); for (const o of orphans) console.log('  • ' + o + (baseOrphans.has(o) ? ' (baseline)' : ' 🆕')); }
  if (deadRefs.length) { console.log('\n🔴 REFS MORTAS (citadas acima da linha, não existem):'); for (const d of deadRefs) console.log('  • ' + d + (baseDead.has(d) ? ' (baseline)' : ' 🆕')); }
  if (fused.length) { console.log('\n🔴 CABEÇALHOS FUNDIDOS (`:** > **` numa linha):'); for (const f of fused) console.log('  • ' + f + (baseFused.has(f) ? ' (baseline)' : ' 🆕')); }
  if (!orphans.length && !deadRefs.length && !fused.length) console.log('✅ nada a reportar — fila e prompts casam.');
  process.exit(0);
}

// gate ----------------------------------------------------------------------
let failed = false;
if (newOrphans.length) {
  console.error(`\n❌ ${newOrphans.length} ÓRFÃO(S) NOVO(S) — PROMPT_PARA_CODE criado sem citação na fila ativa (tarefa invisível):`);
  for (const o of newOrphans) console.error('  🆕 ' + o);
  failed = true;
}
if (newDeadRefs.length) {
  console.error(`\n❌ ${newDeadRefs.length} REF(S) MORTA(S) NOVA(S) — citação acima da linha pra prompt inexistente ([CL] erra o PR):`);
  for (const d of newDeadRefs) console.error('  🆕 ' + d);
  failed = true;
}
if (newFused.length) {
  console.error(`\n❌ ${newFused.length} CABEÇALHO(S) FUNDIDO(S) NOVO(S) — dois blocos numa linha (\`:** > **\`); fila/prompt vira sopa ilegível (C3):`);
  for (const f of newFused) console.error('  🆕 ' + f);
  failed = true;
}

if (failed) {
  console.error(`\nConserte (PROCESSO_MEMORIA_CC.md §16): cite o prompt na fila ATIVA (acima de <!-- ${WATERLINE} -->) OU crie/arquive o arquivo. Se a mudança é legítima e muda a dívida, rode \`npm run handoff:baseline:write\` no mesmo PR.`);
  process.exit(1);
}

if (fixedOrphans.length || fixedDead.length || fixedFused.length) {
  console.log(`✅ dívida CAIU (−${fixedOrphans.length} órfão · −${fixedDead.length} ref morta · −${fixedFused.length} fundido). Trave o ganho: \`npm run handoff:baseline:write\`.`);
} else {
  console.log('✅ sem NOVO órfão / ref morta / fundido — fila e prompts seguem casando.');
}
process.exit(0);
