#!/usr/bin/env node
// Selftest do tier-a-banner.mjs (US-GOV-052 — padrão gate-selftest).
//
// Contrato-âncora (NÃO deriva do código do banner):
//  · os 5 slugs de núcleo Tier A são os que o gerador skills-index-generate.mjs ASSERTA
//    presentes no banner (banner.includes(slug), linhas ~89-97) — se um sumir, o gerador MORDE.
//    O teste fixa esses slugs como contrato: o banner TEM que citá-los literalmente.
//  · de-numeração (adversário 2026-07-20): o header NÃO restatea contador "5"/"6"
//    (proibicoes.md §5 2026-07-17 — número que o frontmatter/CLAUDE.md sabe melhor).
//
// Rodar: node .claude/hooks/tier-a-banner.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { banner } from './tier-a-banner.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const HOOK = join(__dirname, 'tier-a-banner.mjs');

// Slugs de núcleo Tier A — contrato (mesma lista do frontmatter tier:A enabled≠false).
const NUCLEO = [
  'multi-tenant-patterns',
  'commit-discipline',
  'incident-done-checklist',
  'memory-first-secret-search',
  'hostinger-dns-autonomy',
];

let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

const txt = banner();

// ── presença dos slugs de núcleo (o que o gerador guarda) ──
for (const slug of NUCLEO) {
  check(`banner cita o slug de núcleo '${slug}' (gerador asserta presença)`, txt.includes(slug));
}

// ── de-numeração: header sem contador ──
check('header NÃO restatea "5 SKILLS TIER A"', !/\b5\s+SKILLS\s+TIER\s+A/i.test(txt));
check('header NÃO restatea "6 AUTO-TRIGGER"', !/\b6\s+AUTO-?TRIGGER/i.test(txt));
check('header ainda nomeia TIER A e AUTO-TRIGGER (sem número)', /SKILLS TIER A/.test(txt) && /AUTO-TRIGGER/.test(txt));

// ── seções estruturais preservadas ──
check('seção INVIOLAVEL presente', txt.includes('=== INVIOLAVEL (Tier 0 sem ADR mae nova) ==='));
check('cita PROTOCOLO WAGNER', txt.includes('PROTOCOLO WAGNER'));
check('cita ADRs canon (0094/0095/0225/0104)', ['0094', '0095', '0225', '0104'].every((a) => txt.includes(a)));
check('banner é ASCII puro (compat terminal, igual origem)', !/[^\x00-\x7F]/.test(txt));

// ── E2E: subprocess imprime e sai 0 ──
const r = spawnSync(process.execPath, [HOOK], { encoding: 'utf8' });
check('E2E: exit 0', r.status === 0);
check('E2E: stdout tem os 5 slugs de núcleo', NUCLEO.every((s) => r.stdout.includes(s)));
check('E2E: stdout tem seção INVIOLAVEL', r.stdout.includes('INVIOLAVEL'));

console.log('');
if (fails === 0) {
  console.log(`[PASS] tier-a-banner: ${NUCLEO.length} slugs de núcleo presentes + de-numerado + estrutura intacta.`);
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) — o porte do tier-a-banner regrediu.`);
process.exit(1);
