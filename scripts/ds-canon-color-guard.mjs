#!/usr/bin/env node
// scripts/ds-canon-color-guard.mjs — catraca: a camada canônica NÃO usa paleta crua
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// Auditoria sênior de maturidade do DS (2026-06-13, nota 61/100): a "arma fumegante"
// era a camada canônica SE AUTOCONTRADIZENDO. `ui/badge.tsx` hardcodava `bg-emerald-50`
// — exatamente a paleta crua que a regra `ds/no-adhoc-status-text` proíbe nas Pages.
//
// O buraco: os `ds/*` do eslint IGNORAM `Components/ui/**` (eslint.config.js:133) com a
// justificativa "camada canônica onde os padrões legitimamente vivem". A regra CONFIA
// que o canon é limpo — mas nada FORÇA. Foi assim que o badge cru passou meses.
//
// Este guard FECHA o buraco: o canon de cor (ui/ + shared/) consome TOKEN, não paleta.
// Lei do projeto (ADR 0240): "derivado + enforcado sobrevive / escrito + lembrado
// apodrece". Sem catraca, a autocontradição volta no próximo PR.
//
// =====================================================================================
// O QUE VALIDA
// =====================================================================================
// Varre `resources/js/Components/{ui,shared}/*.tsx` por shade numérico de paleta crua
// do Tailwind (emerald-500, sky-50, blue-600, slate-900…). Comentários são removidos
// antes da varredura (descrever cor antiga em doc-comment é legítimo). Teto = 0.
//
// ALLOWLIST: componentes AUTORAIS ("extrair, não repintar" — identidade desenhada
// verbatim de protótipo, tokenizar à força destruiria a arte). Entrada nova na
// allowlist = decisão consciente no MESMO PR (aparece no diff pro reviewer), com
// justificativa. Sem baseline JSON: a allowlist É o estado canônico.
//
// Comando local: npm run ds:canon:check
//
// Refs: auditoria-senior-maturidade-ds-oimpresso.md (Onda M1) · ADR 0209 (ds/* ratchet) ·
//       ADR 0240 (derivado+enforcado) · eslint.config.js:121-166 (escopo ds/* = telas)

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { resolve, join, relative } from 'node:path';

const ROOT = process.cwd();
const SCAN_DIRS = [
  'resources/js/Components/ui',
  'resources/js/Components/shared',
];

// ── ALLOWLIST · componentes autorais (identidade desenhada · "extrair, não repintar") ──
// Entrada nova = editar AQUI no mesmo PR, com justificativa. Caminho relativo ao ROOT.
const ALLOWED = new Map([
  [
    'resources/js/Components/shared/VendaDerivadaCard.tsx',
    'card autoral "OS gerou venda" — mapeamento verbatim do protótipo Cowork (tokens .ofc-venda-* · ADR 0192). Identidade desenhada (gradiente), não repintar; tokenização própria via extração futura.',
  ],
]);

// Paletas nomeadas do Tailwind (cor crua). NÃO inclui tokens semânticos (success,
// warning, info, destructive, primary, muted, border, foreground, accent, ring…).
const TW_PALETTES = [
  'slate', 'gray', 'zinc', 'neutral', 'stone',
  'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal',
  'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose',
];
const SHADES = '(?:50|100|200|300|400|500|600|700|800|900|950)';
// prefixo de cor (-? cobre arbitrary, mas aqui pegamos a forma `palette-shade` em
// qualquer utilitário: bg-/text-/border-/ring-/from-/to-/via-/fill-/stroke-/divide-/…)
const RAW_RE = new RegExp(`\\b(?:${TW_PALETTES.join('|')})-${SHADES}\\b`, 'g');

/** Remove comentários de bloco e de linha (cor crua em doc-comment é legítima). */
function stripComments(src) {
  return src
    .replace(/\/\*[\s\S]*?\*\//g, '')   // /* … */
    .replace(/^\s*\/\/.*$/gm, '')        // linha inteira //
    .replace(/([^:])\/\/.*$/gm, '$1');   // // no fim de linha (preserva https:// via [^:])
}

function tsxFiles(dir) {
  const abs = resolve(ROOT, dir);
  if (!existsSync(abs)) return [];
  return readdirSync(abs)
    .filter((f) => f.endsWith('.tsx') || f.endsWith('.ts'))
    .map((f) => join(dir, f).replace(/\\/g, '/'));
}

const offenders = [];
let scanned = 0;

for (const dir of SCAN_DIRS) {
  for (const rel of tsxFiles(dir)) {
    if (ALLOWED.has(rel)) continue;
    scanned++;
    const src = stripComments(readFileSync(resolve(ROOT, rel), 'utf8'));
    const hits = [...src.matchAll(RAW_RE)].map((m) => m[0]);
    if (hits.length) {
      const uniq = [...new Set(hits)];
      offenders.push({ rel, count: hits.length, sample: uniq.slice(0, 6) });
    }
  }
}

if (offenders.length) {
  console.error('\n✗ [ds-canon-color-guard] camada canônica usando PALETA CRUA (teto = 0):\n');
  for (const o of offenders) {
    console.error(`  ${o.rel} — ${o.count} ocorrência(s): ${o.sample.join(', ')}`);
  }
  console.error('\nO canon do DS consome TOKEN, não paleta. Troque por token semântico:');
  console.error('  status:  bg-success-soft/text-success-fg · bg-warning/10 · text-info · bg-destructive/5');
  console.error('  sólido:  bg-success text-success-foreground hover:bg-success/90 (ver StatusBadge admin_health)');
  console.error('  neutro:  bg-muted · text-muted-foreground · border-border · text-foreground');
  console.error('  marca:   bg-primary · ring-ring · bg-accent');
  console.error('\nComponente AUTORAL (identidade desenhada, "extrair não repintar")?');
  console.error('  → adicione à ALLOWLIST em scripts/ds-canon-color-guard.mjs no MESMO PR, com justificativa.');
  console.error('\nRefs: Onda M1 (maturidade DS) · ADR 0209 · ADR 0240\n');
  process.exit(1);
}

console.log(`✅ [ds-canon-color-guard] ${scanned} arquivo(s) canônico(s) (ui/ + shared/) sem paleta crua · ${ALLOWED.size} autoral allowlisted.`);
