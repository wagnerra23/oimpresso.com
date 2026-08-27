#!/usr/bin/env node
// scripts/scorer-sync-check.mjs — guarda a SINCRONIA dos regex entre as duas implementações.
//
// POR QUE: o `UiDeterministicScorer.php` (juiz de PR · Onda 1) é CÓPIA FIEL dos regex de
// `score-mechanized.mjs` (scorer mecanizado das 222 telas). É uma duplicação CONSCIENTE
// (PHP não roda Node no workflow do PR Judge), mas duplicação É risco: se um regex mudar
// num arquivo e não no outro, o juiz-de-PR e o scorer mecanizado DIVERGEM silenciosamente.
//
// Este gate fecha esse risco (ironia honesta: nasceu numa onda anti-duplicação): verifica que
// as ASSINATURAS-NÚCLEO de cada regra (R1-R4, R6, R7) aparecem nos DOIS arquivos. Se uma existir
// só num → DRIFT → falha. Força quem editar um a editar o outro. Node puro (lê os 2 fontes), sem deps.
//
// 2026-08-26 — R6 e R7 entraram. Até aqui o mapa parava em R4, então os regex de emoji e de
// status-fill podiam divergir entre as duas cópias SEM ninguém saber: os dois arquivos declaram
// "cópia fiel" e o guarda cobria 4 das 6 regras que eles compartilham. Achado ao re-mirar a R7.
//
// NÃO é compare AST-perfeito — é o piso pragmático que pega o drift que importa (o literal do regex).
// RESÍDUO DECLARADO: o predicado é `inMjs !== inPhp`, ou seja SINCRONIA, não PRESENÇA — assinatura
// ausente dos DOIS passa verde. É de propósito (senão viraria presence-gate sobre o texto do regex,
// LC-11); quem remover uma regra dos dois fontes deve remover a linha daqui também.
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
  // R6/R7 — o range do emoji NÃO pode entrar inteiro: JS escreve `\u{1F000}` e PCRE `\x{1F000}`,
  // então a assinatura verbatim tem que ser o pedaço comum (os code points). R7 é o trecho antes
  // das aspas, que é onde o escaping PHP (`["\']`) diverge do JS (`["']`).
  'R6 emoji range início': '1F000',
  'R6 emoji range fim': '1FAFF',
  'R7 badge fill sólido': '<Badge\\b[^>]*\\bvariant=',
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
  // A lista de regras sai do PRÓPRIO mapa — não se restateia "R1-R4" à mão, que apodrece
  // no primeiro par novo (foi o que aconteceu até 2026-08-26, com R6/R7 fora e a linha dizendo R1-R4).
  const regras = [...new Set(Object.keys(SIGNATURES).map((k) => k.split(' ')[0]))].join(', ');
  console.log(`✓ scorer sync OK — ${Object.keys(SIGNATURES).length} assinaturas (${regras}) presentes nos dois (${MJS_PATH} ↔ ${PHP_PATH}).`);
  process.exit(0);
}

console.error(`✗ DRIFT entre ${MJS_PATH} e ${PHP_PATH} — os regex divergiram:\n`);
for (const d of drift) {
  console.error(`  ${d.rule} ("${d.sig}"): score-mechanized.mjs=${d.inMjs ? 'SIM' : 'NÃO'} · UiDeterministicScorer.php=${d.inPhp ? 'SIM' : 'NÃO'}`);
}
console.error(`\nO scorer PHP do PR Judge é cópia fiel dos regex do .mjs. Se mudou um, mude o outro`);
console.error(`(ou ajuste a assinatura aqui se a mudança for intencional + sincronizada nos dois).`);
process.exit(1);
