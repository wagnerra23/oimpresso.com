#!/usr/bin/env node
// reguas-cross-model.test.mjs — contrato da lógica PURA do braço cross-model.
//
// POR QUE ESTE TESTE EXISTE: o valor do arm é o CONTROLE NEGATIVO (Opus-only ledger vs
// +cross-vendor). Se `classificarDivergencia` errar a direção, o arm mente no exato ponto
// que existe pra pegar — reportaria "concorda" quando o cross-vendor DERRUBOU (agreement-bias
// mascarado, o defeito 5,0). Fixtures boa/ruim: a direção morde (DERRUBA) e libera (CONCORDA).
// Node puro (sem fs/DB/rede/API). Roda no governance-script-tests.yml (advisory).
//   node scripts/governance/reguas-cross-model.test.mjs

import {
  classificarDivergencia,
  montarPromptRefuter,
  resumoControleNegativo,
  selecionarClaims,
} from './reguas-cross-model.mjs';

let pass = 0;
const fails = [];
function deve(nome, fn) { try { fn(); pass++; console.log(`  ✓ ${nome}`); } catch (e) { fails.push(`${nome}: ${e.message}`); console.log(`  ✗ ${nome}\n      ${e.message}`); } }
const eq = (a, b, m) => { if (JSON.stringify(a) !== JSON.stringify(b)) throw new Error(`${m ?? ''} esperado ${JSON.stringify(b)}, veio ${JSON.stringify(a)}`); };

// ── Direção da divergência (o coração do controle negativo) ──────────────────
deve('CONCORDA quando iguais (libera)', () => eq(classificarDivergencia('EMPATADO', 'EMPATADO'), 'CONCORDA'));
deve('DIVERGE_DERRUBA: ACIMA→REFUTADO é o achado forte (morde)', () => eq(classificarDivergencia('ACIMA_CONFIRMADO', 'REFUTADO'), 'DIVERGE_DERRUBA'));
deve('DIVERGE_DERRUBA: EMPATADO→REFUTADO também derruba', () => eq(classificarDivergencia('EMPATADO', 'REFUTADO'), 'DIVERGE_DERRUBA'));
deve('DIVERGE_DERRUBA: ACIMA→EMPATADO derruba um degrau', () => eq(classificarDivergencia('ACIMA_CONFIRMADO', 'EMPATADO'), 'DIVERGE_DERRUBA'));
deve('DIVERGE_ELEVA: cross mais otimista (não é o achado)', () => eq(classificarDivergencia('EMPATADO', 'ACIMA_CONFIRMADO'), 'DIVERGE_ELEVA'));
deve('INDEFINIDO com veredito fora do vocabulário (fail-safe)', () => eq(classificarDivergencia('EMPATADO', 'lixo'), 'INDEFINIDO'));

// ── Controle negativo agregado ───────────────────────────────────────────────
deve('resumoControleNegativo conta classes + taxa', () => {
  const r = resumoControleNegativo([
    { divergencia: 'CONCORDA' }, { divergencia: 'DIVERGE_DERRUBA' }, { divergencia: 'DIVERGE_DERRUBA' }, { divergencia: 'DIVERGE_ELEVA' },
  ]);
  eq([r.n, r.concorda, r.diverge_derruba, r.diverge_eleva, r.taxa_concordancia], [4, 1, 2, 1, 0.25]);
});

// ── Seleção do lote (não re-ataca o que o Opus JÁ derrubou por default) ──────
deve('selecionarClaims default = ACIMA+EMPATADO, exclui REFUTADO', () => {
  const sel = selecionarClaims([
    { id: 'a', refutador: 'ACIMA_CONFIRMADO' }, { id: 'b', refutador: 'EMPATADO' }, { id: 'c', refutador: 'REFUTADO' },
  ]);
  eq(sel.map((x) => x.id), ['a', 'b']);
});
deve('selecionarClaims respeita --only + --limit', () => {
  const sel = selecionarClaims(
    [{ id: 'a', refutador: 'ACIMA_CONFIRMADO' }, { id: 'b', refutador: 'ACIMA_CONFIRMADO' }],
    { only: ['ACIMA_CONFIRMADO'], limit: 1 },
  );
  eq(sel.map((x) => x.id), ['a']);
});

// ── Blind por construção (não vaza o peer/razão que o Opus achou → independência) ──
deve('prompt é blind: cita o título, NÃO vaza o peer do Opus', () => {
  const p = montarPromptRefuter({ titulo: 'Heartbeat lê a API do destino', dimensao: 'obs', refutador: 'REFUTADO', peer: 'OneUptime_dead_mans_switch' });
  if (!p.includes('Heartbeat lê a API do destino')) throw new Error('não citou o título');
  if (p.includes('OneUptime_dead_mans_switch')) throw new Error('vazou o peer do Opus — quebraria a independência do 2º modelo');
});

console.log(`\nreguas-cross-model: ${pass} ok, ${fails.length} falhas`);
if (fails.length) { console.error('\nFALHAS:\n' + fails.map((f) => ` - ${f}`).join('\n')); process.exit(1); }
