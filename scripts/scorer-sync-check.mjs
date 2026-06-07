#!/usr/bin/env node
// scripts/scorer-sync-check.mjs — guarda a SINCRONIA dos regex entre as duas implementações.
//
// POR QUE: o `UiDeterministicScorer.php` (juiz de PR · Onda 1) é CÓPIA FIEL dos regex de
// `score-mechanized.mjs` (scorer mecanizado das 222 telas). É uma duplicação CONSCIENTE
// (PHP não roda Node no workflow do PR Judge), mas duplicação É risco: se um regex mudar
// num arquivo e não no outro, o juiz-de-PR e o scorer mecanizado DIVERGEM silenciosamente.
//
// Este gate fecha esse risco (ironia honesta: nasceu numa onda anti-duplicação): verifica que
// as ASSINATURAS-NÚCLEO de cada regra (R1-R4) aparecem nos DOIS arquivos. Se uma existir só num
// → DRIFT → falha. Força quem editar um a editar o outro. Node puro (lê os 2 fontes), sem deps.
//
// NÃO é compare AST-perfeito — é o piso pragmático que pega o drift que importa (o literal do regex).
// Local: node scripts/scorer-sync-check.mjs

import { readFileSync } from 'node:fs';

const MJS_PATH = 'prototipo-ui/audit/score-mechanized.mjs';
const PHP_PATH = 'Modules/Jana/Ai/UiDeterministicScorer.php';

// Assinaturas que DEVEM existir verbatim nos dois fontes (substrings dos regex, escolhidas
// sem ambiguidade de escaping de slash). Drift = presente num, ausente no outro.
const SIGNATURES = {
  'R1 hex': '#[0-9a-fA-F]{3,8}',
  'R1 exceção fff/000': '#(?:fff|ffffff|000|000000)',
  'R1 color-fn': '(?:oklch|rgba?|hsla?)',
  'R2 elemento nativo': '(?:select|input|textarea|table)',
  'R3 localStorage': '(?:get|set|remove)Item',
  'R4 svg': '<svg',
  'R4 icon-lib externa': '(?:react-icons|@heroicons|@tabler',
};

let mjs, php;
try { mjs = readFileSync(MJS_PATH, 'utf8'); } catch { console.error(`✗ não achei ${MJS_PATH}`); process.exit(2); }
try { php = readFileSync(PHP_PATH, 'utf8'); } catch { console.error(`✗ não achei ${PHP_PATH}`); process.exit(2); }

const drift = [];
for (const [rule, sig] of Object.entries(SIGNATURES)) {
  const inMjs = mjs.includes(sig);
  const inPhp = php.includes(sig);
  if (inMjs !== inPhp) drift.push({ rule, sig, inMjs, inPhp });
}

if (drift.length === 0) {
  console.log(`✓ scorer sync OK — ${Object.keys(SIGNATURES).length} assinaturas R1-R4 presentes nos dois (${MJS_PATH} ↔ ${PHP_PATH}).`);
  process.exit(0);
}

console.error(`✗ DRIFT entre ${MJS_PATH} e ${PHP_PATH} — os regex divergiram:\n`);
for (const d of drift) {
  console.error(`  ${d.rule} ("${d.sig}"): score-mechanized.mjs=${d.inMjs ? 'SIM' : 'NÃO'} · UiDeterministicScorer.php=${d.inPhp ? 'SIM' : 'NÃO'}`);
}
console.error(`\nO scorer PHP do PR Judge é cópia fiel dos regex do .mjs. Se mudou um, mude o outro`);
console.error(`(ou ajuste a assinatura aqui se a mudança for intencional + sincronizada nos dois).`);
process.exit(1);
