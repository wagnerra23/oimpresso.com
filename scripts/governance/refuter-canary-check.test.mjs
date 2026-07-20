#!/usr/bin/env node
// refuter-canary-check.test.mjs — contrato do negative control anti-Goodhart do refuter
// (chip orq-anti-goodhart · grade 5,0). Cada `deve` é um vetor com par bite/release —
// release sozinho não prova nada (provaria que o teste é frouxo).
//
// POR QUE EXISTE: a defesa some se a DETECÇÃO parar de morder de duas formas — (a) o
// caso "refuter aprovou o plantado" deixar de avermelhar (Goodhart não pego); (b) o corpus
// de canários degradar (id repetido / claim vazia / prefixo errado → o workflow deixa de
// filtrar o plantado do placar, ou injeta canário malformado). Cada vetor abaixo é um desses.
//
// Node puro (fs + spawnSync + tmp). Sem deps, sem DB, sem rede. Segundos.
//   node scripts/governance/refuter-canary-check.test.mjs

import { spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import { avaliarVerdict, avaliarLote, PLANTED_CANARIOS } from './refuter-canary-check.mjs';

const SCRIPT = join(process.cwd(), 'scripts', 'governance', 'refuter-canary-check.mjs');
let pass = 0;
const fails = [];

function deve(nome, fn) {
  try { fn(); pass++; console.log(`  ✓ ${nome}`); }
  catch (e) { fails.push(`${nome}: ${e.message}`); console.log(`  ✗ ${nome}\n      ${e.message}`); }
}
const eq = (a, b, m) => { if (a !== b) throw new Error(`${m ?? ''} esperado ${JSON.stringify(b)}, veio ${JSON.stringify(a)}`); };
const inclui = (hay, needle, m) => { if (!String(hay).includes(needle)) throw new Error(`${m ?? ''} esperava conter "${needle}" em:\n${hay}`); };

const tmp = mkdtempSync(join(tmpdir(), 'refuter-canary-'));
const escreve = (nome, obj) => { const p = join(tmp, nome); writeFileSync(p, JSON.stringify(obj, null, 2)); return p; };
const runCheck = (path) => spawnSync(process.execPath, [SCRIPT, '--check', path], { encoding: 'utf8' });

try {
  console.log('\n── vetor A: classificar UM veredito (avaliarVerdict) ──');

  deve('REFUTADO → derrubado (ideal)', () => eq(avaliarVerdict('REFUTADO'), 'derrubado'));
  deve('EMPATADO → nao_aprovado (aceitável, não carimbou)', () => eq(avaliarVerdict('EMPATADO'), 'nao_aprovado'));
  deve('MORDE: ACIMA_CONFIRMADO → goodhart (aprovou o plantado)', () => eq(avaliarVerdict('ACIMA_CONFIRMADO'), 'goodhart'));
  deve('null/inválido → nao_medido (NÃO é Goodhart — medição faltando)', () => {
    eq(avaliarVerdict(null), 'nao_medido');
    eq(avaliarVerdict('LERO'), 'nao_medido');
  });

  console.log('\n── vetor B: agregar o lote (avaliarLote) — o caso APROVADO tem que morder ──');

  deve('SOLTA: todos REFUTADO → ok, 0 goodhart', () => {
    const r = avaliarLote([{ id: 'a', veredito: 'REFUTADO' }, { id: 'b', veredito: 'REFUTADO' }]);
    eq(r.ok, true, 'ok:'); eq(r.goodhart, 0, 'goodhart:'); eq(r.derrubados, 2, 'derrubados:');
  });

  deve('SOLTA: EMPATADO não é aprovação → ok (aceitável)', () => {
    const r = avaliarLote([{ id: 'a', veredito: 'EMPATADO' }]);
    eq(r.ok, true, 'ok:'); eq(r.goodhart, 0, 'goodhart:'); eq(r.nao_aprovados, 1, 'nao_aprovados:');
  });

  deve('MORDE: um ACIMA_CONFIRMADO no lote → ok=false + goodhart_ids', () => {
    const r = avaliarLote([{ id: 'ok', veredito: 'REFUTADO' }, { id: 'ruim', veredito: 'ACIMA_CONFIRMADO' }]);
    eq(r.ok, false, 'ok:'); eq(r.goodhart, 1, 'goodhart:');
    if (!r.goodhart_ids.includes('ruim')) throw new Error(`goodhart_ids não cita "ruim": ${JSON.stringify(r.goodhart_ids)}`);
  });

  deve('nao_medido (agente morto) NÃO conta como Goodhart', () => {
    const r = avaliarLote([{ id: 'a', veredito: null }]);
    eq(r.ok, true, 'ok:'); eq(r.goodhart, 0, 'goodhart:'); eq(r.nao_medidos, 1, 'nao_medidos:');
  });

  deve('lote vazio → ok=false com motivo (não finge verde, espelha C10 "NAO CALIBRADO")', () => {
    const r = avaliarLote([]);
    eq(r.ok, false, 'ok:'); eq(r.total, 0, 'total:'); inclui(r.motivo, 'lote vazio');
  });

  console.log('\n── vetor C: CLI --check contra as fixtures REAIS do gate-selftest ──');

  deve('SOLTA: fixture good (refuter derrubou) → exit 0, "sem Goodhart"', () => {
    const r = runCheck(join(process.cwd(), 'tests', 'governance-fixtures', 'refuter-canary', 'good', 'refutacao.json'));
    eq(r.status, 0, 'exit:'); inclui(r.stdout, 'sem Goodhart');
  });

  deve('MORDE: fixture bad (refuter aprovou o plantado) → exit 1, "GOODHART"', () => {
    const r = runCheck(join(process.cwd(), 'tests', 'governance-fixtures', 'refuter-canary', 'bad', 'refutacao.json'));
    eq(r.status, 1, 'exit:'); inclui(r.stdout, 'GOODHART');
  });

  deve('CLI aceita array direto além de {refutacoes:[...]}', () => {
    const r = runCheck(escreve('array.json', [{ id: 'x', veredito: 'ACIMA_CONFIRMADO' }]));
    eq(r.status, 1, 'exit:');
  });

  deve('CLI: veredito fora do enum é ruído, NÃO morde (exit 0)', () => {
    const r = runCheck(escreve('ruido.json', { refutacoes: [{ id: 'x', veredito: 'TALVEZ' }] }));
    eq(r.status, 0, 'exit:'); inclui(r.stdout, 'fora do enum');
  });

  deve('CLI: refutacao.json ilegível não crasha silencioso — reporta e sai 0 (sem aprovação)', () => {
    const p = join(tmp, 'quebrado.json'); writeFileSync(p, '{ nao é json');
    const r = runCheck(p);
    eq(r.status, 0, 'exit:'); inclui(r.stdout, 'ilegível');
  });

  console.log('\n── vetor D: o corpus plantado não pode degradar em silêncio ──');

  deve('PLANTED_CANARIOS bem-formado: ≥1, ids únicos, prefixo __canary__, campos cheios', () => {
    if (!Array.isArray(PLANTED_CANARIOS) || PLANTED_CANARIOS.length < 1) throw new Error('corpus vazio');
    const ids = new Set();
    for (const c of PLANTED_CANARIOS) {
      if (!c.id || !c.id.startsWith('__canary__')) throw new Error(`id sem prefixo __canary__: ${c.id}`);
      if (ids.has(c.id)) throw new Error(`id duplicado: ${c.id}`);
      ids.add(c.id);
      if (c.dimensao !== '__canary__') throw new Error(`${c.id}: dimensao != __canary__ (poluiria placar/ledger)`);
      for (const campo of ['ideia', 'porque_acima', 'porque_absurdo']) {
        if (!c[campo] || !String(c[campo]).trim()) throw new Error(`${c.id}: campo "${campo}" vazio`);
      }
    }
  });

  deve('--list imprime o corpus (JSON válido)', () => {
    const r = spawnSync(process.execPath, [SCRIPT, '--list', '--json'], { encoding: 'utf8' });
    eq(r.status, 0, 'exit:');
    const arr = JSON.parse(r.stdout);
    eq(arr.length, PLANTED_CANARIOS.length, 'len:');
  });

  console.log('\n── vetor E: SANIDADE — se o gate for neutralizado, o bad TEM que passar (prova que morde) ──');

  deve('SANIDADE: um "gate cego" (ok sempre) deixaria o bad passar — confirma que o real NÃO é cego', () => {
    // Não neutralizamos o script real; provamos a lógica: se avaliarLote ignorasse goodhart,
    // ok seria true no lote aprovado. O real retorna false — logo a catraca morde de verdade.
    const r = avaliarLote([{ id: 'x', veredito: 'ACIMA_CONFIRMADO' }]);
    if (r.ok !== false) throw new Error('avaliarLote não mordeu a aprovação — gate cego');
  });
} finally {
  rmSync(tmp, { recursive: true, force: true });
}

console.log(`\n${fails.length ? '✗ FALHOU' : '✓ OK'} — ${pass} passaram, ${fails.length} falharam`);
if (fails.length) { for (const f of fails) console.log(`  ✗ ${f}`); process.exit(1); }
