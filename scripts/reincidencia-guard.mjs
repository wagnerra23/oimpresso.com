#!/usr/bin/env node
// scripts/reincidencia-guard.mjs — Caçador de reincidência (PROCESSO_MEMORIA_CC.md §16).
//
// Recusa, no instante do PR, as classes de erro git-gate que REINCIDEM no loop de handoff —
// "uma máquina que recusa no instante da ação" (§5 / NÚCLEO-5):
//   C3 — BLOCO FUNDIDO: linha de item que funde dois cabeçalhos (`**Artefato:** > **…**`) → ilegível.
//   C4 — ÓRFÃO:    PROMPT_PARA_CODE_*.md no dir sem citação na fila ATIVA → tarefa invisível.
//        REF MORTA: citação na fila ATIVA pra um PROMPT_PARA_CODE_*.md que não existe → [CL] erra o PR.
//   C5 — SEM CARIMBO: item ativo (`> … → [CL]`) sem `verificado vs main` no corpo → enfileirou às cegas.
//
// "Fila ATIVA" = tudo ACIMA do marcador <!-- LINHA-DAGUA-HANDOFF --> em prototipo-ui/COWORK_NOTES.md;
// abaixo (histórico/processado) é ignorado. Baseline (scripts/reincidencia-baseline.json) congela a
// dívida atual — SÓ o que entra NOVO trava (ratchet, idioma dos guards irmãos).
//
// ⚠️ SOBREPÕE scripts/handoff-integrity-guard.mjs (C3/C4 já mecanizados lá, mesmo arquivo + marcador).
//    Mantido como artefato standalone explícito por decisão [W] (Tarefa 2 do handoff). Consolidação
//    futura recomendada (Regra 7 — não criar paralelo). C5 é a parte nova: a heurística de "item ativo"
//    (`> … → [CL]`) ainda é PROPOSTA — a fila real usa bullets `- **…**`, então hoje o C5 casa ZERO
//    (no-op) até [W] confirmar a sintaxe (PROCESSO_MEMORIA_CC.md §16, linha C5 🔜).
//
// ADVISORY de nascença (gates novos nunca nascem required — ADR 0271/0275). Node puro, sem deps/DB/rede.
// Uso:   node scripts/reincidencia-guard.mjs            # valida (falha em NOVO C3/C4/C5)
//        node scripts/reincidencia-guard.mjs --write    # congela a dívida atual no baseline
//        node scripts/reincidencia-guard.mjs --json     # diagnóstico parseável
// Test:  node scripts/reincidencia-guard.test.mjs       # controle-negativo (prova que a catraca morde)

import { readFileSync, writeFileSync, readdirSync, existsSync } from 'node:fs';
import { resolve, join, relative } from 'node:path';

const argv = process.argv.slice(2);
const flag = (name) => argv.includes(name);
const opt = (name, def) => { const i = argv.indexOf(name); return i >= 0 && argv[i + 1] ? argv[i + 1] : def; };

const ROOT = resolve(opt('--root', process.cwd()));
const QUEUE = resolve(opt('--queue', join(ROOT, 'prototipo-ui/COWORK_NOTES.md')));
const DIR = resolve(opt('--dir', join(ROOT, 'prototipo-ui')));
const BASELINE = resolve(opt('--baseline', join(ROOT, 'scripts/reincidencia-baseline.json')));
const WATER = 'LINHA-DAGUA-HANDOFF'; // marcador real da fila (o sketch usava o placeholder "LINHA D'ÁGUA")
const MODE_WRITE = flag('--write');
const MODE_JSON = flag('--json');

const PROMPT_FILE = /^PROMPT_PARA_CODE_[\w.-]+\.md$/; // arquivo no dir
const CITE_RE = /PROMPT_PARA_CODE_[\w.-]+\.md/g;      // citação no texto

if (!existsSync(QUEUE)) { console.error(`❌ fila de handoff ausente: ${relative(ROOT, QUEUE)}`); process.exit(1); }
const notes = readFileSync(QUEUE, 'utf8');
const wIdx = notes.indexOf(WATER);
const active = wIdx >= 0 ? notes.slice(0, wIdx) : notes; // sem marcador → fila inteira tratada como ATIVA
const files = existsSync(DIR) ? readdirSync(DIR).filter((f) => PROMPT_FILE.test(f)) : [];

// --- detecção (lógica fiel ao sketch) ----------------------------------------
const cited = [...new Set(active.match(CITE_RE) || [])];
const orphans = files.filter((f) => !active.includes(f));                     // C4 órfão
const deadRefs = cited.filter((c) => !files.includes(c));                     // C4 ref morta
const fused = active.split('\n')                                             // C3 bloco fundido
  .filter((ln) => /\*\*Artefato:\*\*/.test(ln) && /:\*\*\s*>/.test(ln))
  .map((ln) => ln.trim());
const heads = [...active.matchAll(/^>\s.*→\s*\[CL\].*$/gm)].map((m) => m[0]); // C5 itens ativos
const semCarimbo = heads.filter(
  (h) => !/verificad[oa]\s+vs\s+main/i.test(active.slice(active.indexOf(h), active.indexOf(h) + 800)),
);

// chaves estáveis pro baseline (ratchet "só NOVO trava" em todas as 3 classes)
const keyC3 = (l) => `C3::${l}`;
const keyC5 = (h) => `C5::${h.trim().slice(0, 120)}`;
const findings = [
  ...orphans.map((f) => ({ key: f, msg: `C4 ÓRFÃO: ${f}` })),
  ...deadRefs.map((c) => ({ key: c, msg: `C4 REF MORTA: ${c}` })),
  ...fused.map((l) => ({ key: keyC3(l), msg: `C3 BLOCO FUNDIDO: ${l.slice(0, 80)}` })),
  ...semCarimbo.map((h) => ({ key: keyC5(h), msg: `C5 SEM CARIMBO vs-main: ${h.trim().slice(0, 60)}…` })),
];

// --- write baseline (congela a dívida atual) ---------------------------------
if (MODE_WRITE) {
  const frozen = [...new Set(findings.map((f) => f.key))].sort();
  writeFileSync(BASELINE, JSON.stringify(frozen, null, 2) + '\n');
  console.log(`✅ baseline reincidencia gravado: ${frozen.length} item(ns) congelado(s) → ${relative(ROOT, BASELINE)}`);
  process.exit(0);
}

const BASE = new Set(existsSync(BASELINE) ? JSON.parse(readFileSync(BASELINE, 'utf8')) : []);
const novos = findings.filter((f) => !BASE.has(f.key));

if (MODE_JSON) {
  console.log(JSON.stringify({
    files: files.length, cited: cited.length,
    findings: findings.map((f) => f.msg), novos: novos.map((f) => f.msg), baseline: BASE.size,
  }, null, 2));
  process.exit(novos.length ? 1 : 0);
}

if (novos.length) {
  console.error('reincidencia-guard 🔴 — reincidência NOVA (fora do baseline):\n');
  for (const f of novos) console.error('  ' + f.msg);
  console.error(`\nConserte na fila ATIVA (acima de <!-- ${WATER} --> em ${relative(ROOT, QUEUE)})`);
  console.error('ou, se a dívida muda legitimamente, no MESMO PR: node scripts/reincidencia-guard.mjs --write');
  console.error('Refs: PROCESSO_MEMORIA_CC.md §16 (C3/C4/C5).');
  process.exit(1);
}
console.log(`reincidencia-guard 🟢 (baseline ${BASE.size} · ${files.length} prompts · ${findings.length} dívida congelada)`);
process.exit(0);
