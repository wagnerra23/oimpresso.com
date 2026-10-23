#!/usr/bin/env node
// @ts-check
/**
 * demote-required.mjs — tira UM check required da proteção clássica de `main`.
 *
 * Par do `flip-required.mjs` (que só promove). Nasceu para a ADR 0438 D7 (2026-10-23), que
 * demove `espelho — mexeu depois de verificar`, e serve para qualquer demoção futura.
 *
 * POR QUE NÃO UM `gh api` NA MÃO
 *   · 2026-07-02 — payload inline no shell Windows re-encoda não-ASCII e o nome chega com
 *     mojibake. O nome deste context tem travessão (U+2014). Aqui o nome NUNCA é digitado:
 *     ele é DERIVADO como "o que está required no vivo e já não está no baseline".
 *   · O PUT de protection substitui tudo, e os checks de main estão vinculados a um app
 *     (registro de 2026-09-30, app_id). Reescrever a lista para tirar um arriscaria o vínculo
 *     dos outros. Este script usa DELETE .../required_status_checks/contexts, que remove só o
 *     nomeado — o mesmo caminho da demoção de 2026-08-26 (visual-regression).
 *
 * PRÉ-CONDIÇÃO: rodar num checkout cujo `governance/required-checks-baseline.json` JÁ não
 * lista o context (o PR da demoção). A diferença vivo − baseline tem de ser EXATAMENTE um
 * nome; zero ou mais de um aborta sem escrever.
 *
 * Uso:
 *   node scripts/governance/demote-required.mjs            # DRY-RUN: mostra o que sairia
 *   node scripts/governance/demote-required.mjs --apply    # aplica e confere string-exata
 * Depois: node scripts/governance/protection-drift.mjs (baseline × vivo, string-exata).
 */
import { readFileSync, writeFileSync, unlinkSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';

const REPO = 'wagnerra23/oimpresso.com';
const APPLY = process.argv.includes('--apply');
const gh = (a, opts = {}) => execFileSync('gh', a, { encoding: 'utf8', maxBuffer: 32 * 1024 * 1024, ...opts });

// ── 1. baseline (o PR) × vivo ───────────────────────────────────────────────────
const baseline = JSON.parse(readFileSync(join('governance', 'required-checks-baseline.json'), 'utf8'));
const doBaseline = new Set(baseline.classic_protection?.contexts || []);

let rsc;
try {
  rsc = JSON.parse(gh(['api', `repos/${REPO}/branches/main/protection/required_status_checks`]));
} catch (e) {
  // não-medi não vira veredito (§5 2026-07-29): sem ler o vivo, nada é aplicado
  console.error('✗ não consegui ler a proteção de main (gh/token/permissão). Nada foi feito.');
  process.exit(2);
}
const vivos = [...new Set([...(rsc.contexts || []), ...((rsc.checks || []).map((c) => c.context))])];
const sair = vivos.filter((c) => !doBaseline.has(c));

console.log(`vivo: ${vivos.length} contexts · baseline: ${doBaseline.size} · a sair: ${sair.length}`);
for (const c of sair) console.log(`  − ${JSON.stringify(c)}`);

if (sair.length === 0) { console.log('✓ nada a demover: vivo e baseline já batem.'); process.exit(0); }
if (sair.length > 1) {
  console.error('✗ mais de um context no vivo fora do baseline — demoção é um por vez, e um deles pode ser');
  console.error('  required NOVO ainda não incorporado (aí o certo é o baseline ganhar a linha). Nada foi feito.');
  process.exit(1);
}
const CONTEXT = sair[0];

if (!APPLY) {
  console.log('\n── DRY-RUN (nada escrito) ──');
  console.log('  Repita com --apply para remover. PRs abertos não travam: um required a MENOS não bloqueia ninguém.');
  process.exit(0);
}

// ── 2. aplicar por arquivo UTF-8 sem BOM, nunca inline ──────────────────────────
const tmp = join(process.cwd(), '.demote-required.payload.json');
writeFileSync(tmp, JSON.stringify([CONTEXT]), { encoding: 'utf8' });
if (readFileSync(tmp)[0] === 0xEF) { console.error('✗ payload saiu com BOM — abortado'); unlinkSync(tmp); process.exit(1); }
try {
  gh(['api', '-X', 'DELETE', `repos/${REPO}/branches/main/protection/required_status_checks/contexts`, '--input', tmp],
    { stdio: ['ignore', 'pipe', 'pipe'] });
} finally { unlinkSync(tmp); }

// ── 3. conferir pelo vivo, string-exata ─────────────────────────────────────────
const depois = JSON.parse(gh(['api', `repos/${REPO}/branches/main/protection/required_status_checks`]));
const vivosDepois = [...new Set([...(depois.contexts || []), ...((depois.checks || []).map((c) => c.context))])];
if (vivosDepois.includes(CONTEXT)) { console.error('\n✗ o context continua required depois do DELETE. Confira na UI.'); process.exit(1); }
if (vivosDepois.length !== vivos.length - 1) {
  console.error(`\n✗ esperava ${vivos.length - 1} contexts e há ${vivosDepois.length} — algo além do pedido mudou. Confira JÁ.`);
  process.exit(1);
}
console.log(`\n✓ DEMOÇÃO OK — ${vivos.length} → ${vivosDepois.length} contexts · ${JSON.stringify(CONTEXT)} saiu`);
console.log('  Agora: node scripts/governance/protection-drift.mjs');
