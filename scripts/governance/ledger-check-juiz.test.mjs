#!/usr/bin/env node
// ledger-check-juiz.test.mjs — contrato do tipo `juiz` (chip C10 · calibração do juiz).
//
// POR QUE ESTE TESTE EXISTE: o `ledger-check --enforce` roda dentro do job umbrella
// REQUIRED (promovido 2026-07-04). Somar um tipo de entry ali é mexer em gate que morde:
// os dois modos de falha são (a) a calibração passar a satisfazer um PR-de-lote — gate
// afrouxado, anti-gaming furado; (b) a calibração fabricar um número sem rótulo humano.
// Cada `deve` abaixo é um desses vetores, com o par bite/release (release sozinho não
// prova nada — provaria que o teste é frouxo).
//
// Node puro (fs + spawnSync + tmp). Sem deps, sem DB, sem rede. Segundos.
//   node scripts/governance/ledger-check-juiz.test.mjs

import { spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import { juizReport, validateJuizEntry } from './ledger-check.mjs';
import { measureBackfillErrorRate } from './sdd-scorecard.mjs';

const SCRIPT = join(process.cwd(), 'scripts', 'governance', 'ledger-check.mjs');
let pass = 0;
const fails = [];

function deve(nome, fn) {
  try { fn(); pass++; console.log(`  ✓ ${nome}`); }
  catch (e) { fails.push(`${nome}: ${e.message}`); console.log(`  ✗ ${nome}\n      ${e.message}`); }
}
const eq = (a, b, m) => { if (a !== b) throw new Error(`${m ?? ''} esperado ${JSON.stringify(b)}, veio ${JSON.stringify(a)}`); };
const inclui = (hay, needle, m) => {
  if (!String(hay).includes(needle)) throw new Error(`${m ?? ''} esperava conter "${needle}" em:\n${hay}`);
};

const tmp = mkdtempSync(join(tmpdir(), 'ledger-juiz-'));
const escreve = (nome, obj) => { const p = join(tmp, nome); writeFileSync(p, JSON.stringify(obj, null, 2)); return p; };

// 11 arquivos > threshold 10 → é PR-de-lote (mesma forma da fixture do gate-selftest)
const FILES = join(tmp, 'files.txt');
writeFileSync(FILES, Array.from({ length: 11 }, (_, i) => `memory/requisitos/DemoMod/doc-${i}.md`).join('\n'));

const rodadaValida = {
  tipo: 'juiz', lote_id: 'JUIZ-CAL-teste-r1', data: '2026-07-17', pr: 9999,
  juiz: 'ledger-refutador (fable-5)', rotulador: '[W]', cego: true,
  itens_rotulados: 20, concordancias: 17, concordancia_pct: 85,
  evidencia: 'memory/sessions/exemplo.md',
};
const loteValido = {
  pr: 9999, lote_id: 'lote-ok', data: '2026-07-17', tipo: 'anchors',
  gerador: 'opus', refutador: 'fable', sessao_fresca: true, amostra_pct: 100,
  itens_verificados: 40, erros_confirmados: 0, error_rate_pct: 0,
  pii_scan: true, pii_hits: 0, evidencia: 'x.md', veredito: 'aprovado',
};
const runGate = (ledgerPath) => spawnSync(process.execPath,
  [SCRIPT, '--pr', '9999', '--files-from', FILES, '--ledger', ledgerPath, '--enforce'],
  { encoding: 'utf8' });

try {
  console.log('\n── vetor A: calibração NÃO pode liberar merge de lote (anti-gaming) ──');

  deve('MORDE: PR-de-lote com SÓ entry juiz falha (juiz não é refutação)', () => {
    const r = runGate(escreve('so-juiz.json', { entries: [rodadaValida] }));
    eq(r.status, 1, 'exit code:');
    inclui(r.stdout, 'nao substitui refutar o lote');
  });

  deve('MORDE: entry juiz DEPOIS de um lote válido não mascara o lote reprovado', () => {
    // Vetor real: o casamento pega a ÚLTIMA entry do PR. Uma calibração registrada
    // depois de um lote REPROVADO não pode virar a "última" e liberar o merge.
    const reprovado = { ...loteValido, veredito: 'reprovado', erros_confirmados: 9, error_rate_pct: 22.5 };
    const r = runGate(escreve('mascara.json', { entries: [reprovado, rodadaValida] }));
    eq(r.status, 1, 'exit code:');
    inclui(r.stdout, 'veredito="reprovado"');
  });

  deve('SOLTA: lote válido + calibração no mesmo PR continua passando', () => {
    const r = runGate(escreve('lote-mais-juiz.json', { entries: [loteValido, rodadaValida] }));
    eq(r.status, 0, 'exit code:');
    inclui(r.stdout, 'entry valida no ledger');
  });

  console.log('\n── vetor B: o rótulo tem que ser HUMANO conhecido (allowlist) e às cegas ──');

  // CORPUS HOSTIL de propósito (achado do skeptic 2 + lápide §5 2026-06-30): a versão
  // antiga era ALLOWLIST implícita só de nomes Anthropic. Estes são os modelos que um
  // denylist de nome DEIXA PASSAR — incl. gpt-4o-mini, que JÁ é gerador neste ledger.
  // Se o check voltar a ser denylist, este teste avermelha.
  deve('MORDE: modelo NÃO-Anthropic como rotulador (denylist vazaria — allowlist pega)', () => {
    for (const m of ['gpt-4o-mini', 'GPT-4o', 'gemini-2.5', 'grok-4', 'llama-3', 'deepseek-v3', 'o3', 'claude-3']) {
      const v = validateJuizEntry({ ...rodadaValida, rotulador: m });
      if (!v.some((x) => x.includes('sem sigla de humano'))) {
        throw new Error(`rotulador="${m}" passou como humano — denylist de nome vaza (§5 2026-06-30)`);
      }
    }
  });

  deve('MORDE: nome de humano SEM sigla do time também é rejeitado (allowlist fechada)', () => {
    for (const nome of ['Wagner', 'wagnerra23', 'alguem', '']) {
      const v = validateJuizEntry({ ...rodadaValida, rotulador: nome });
      if (!v.some((x) => x.includes('sem sigla de humano') || x.includes('rotulador ausente'))) {
        throw new Error(`rotulador="${nome}" passou sem sigla do time`);
      }
    }
  });

  deve('MORDE: humano + copilot modelo ("[W] + sonnet") — assistência contamina', () => {
    const v = validateJuizEntry({ ...rodadaValida, rotulador: '[W] + copilot sonnet' });
    if (!v.some((x) => x.includes('mistura humano com MODELO'))) {
      throw new Error('copilot no loop de rotulagem passou');
    }
  });

  deve('SOLTA: cada sigla do time ([W]/[M]/[F]/[L]/[E]) é rótulo humano válido', () => {
    for (const s of ['[W]', '[M]', '[F]', '[L]', '[E]', '[W] Wagner']) {
      const v = validateJuizEntry({ ...rodadaValida, rotulador: s });
      if (v.length) throw new Error(`rotulador="${s}" foi rejeitado: ${v.join('; ')}`);
    }
  });

  deve('MORDE: cego != true (rotulou vendo o veredito = ancoragem)', () => {
    const v = validateJuizEntry({ ...rodadaValida, cego: false });
    if (!v.some((x) => x.includes('cego != true'))) throw new Error('não pegou');
  });

  deve('MORDE: taxa que não fecha com o denominador (17/20 declarado 95%)', () => {
    const v = validateJuizEntry({ ...rodadaValida, concordancia_pct: 95 });
    if (!v.some((x) => x.includes('nao fecha'))) throw new Error('número inventado passou');
  });

  deve('MORDE: concordancias > itens_rotulados (impossível)', () => {
    const v = validateJuizEntry({ ...rodadaValida, concordancias: 21, concordancia_pct: 105 });
    if (!v.some((x) => x.includes('impossivel'))) throw new Error('não pegou');
  });

  deve('SOLTA: rodada bem-formada não acusa nada', () => {
    eq(validateJuizEntry(rodadaValida).length, 0, 'violações:');
  });

  console.log('\n── vetor C: o report publica número com denominador, ou diz NÃO CALIBRADO ──');

  deve('zero rodadas = NÃO CALIBRADO (não fabrica 0% nem 100%)', () => {
    const r = juizReport(escreve('vazio.json', { entries: [loteValido] }));
    eq(r.calibrado, false, 'calibrado:');
    eq(r.concordancia_pct, null, 'pct:');
    inclui(r.motivo, 'NAO CALIBRADO');
  });

  deve('ledger ausente = NÃO CALIBRADO (nunca mente)', () => {
    const r = juizReport(join(tmp, 'nao-existe.json'));
    eq(r.calibrado, false, 'calibrado:');
    eq(r.concordancia_pct, null, 'pct:');
  });

  deve('rodada inválida NÃO entra no placar (fica listada como rejeitada)', () => {
    const podre = { ...rodadaValida, lote_id: 'podre', rotulador: 'fable-5' };
    const r = juizReport(escreve('podre.json', { entries: [podre] }));
    eq(r.calibrado, false, 'calibrado:');
    eq(r.rejeitadas.length, 1, 'rejeitadas:');
    inclui(r.motivo, 'todas invalidas');
  });

  deve('agrega N rodadas com denominador somado (17/20 + 8/10 = 25/30 = 83.3%)', () => {
    const r2 = {
      ...rodadaValida, lote_id: 'JUIZ-CAL-teste-r2',
      itens_rotulados: 10, concordancias: 8, concordancia_pct: 80,
    };
    const r = juizReport(escreve('duas.json', { entries: [rodadaValida, r2] }));
    eq(r.calibrado, true, 'calibrado:');
    eq(r.rodadas, 2, 'rodadas:');
    eq(r.itens_rotulados, 30, 'N:');
    eq(r.concordancias, 25, 'K:');
    eq(r.concordancia_pct, 83.3, 'pct:');
  });

  deve('--juiz-report é MEDIÇÃO, não portão: exit 0 mesmo sem calibração', () => {
    const r = spawnSync(process.execPath, [SCRIPT, '--juiz-report', '--ledger', join(tmp, 'vazio.json')], { encoding: 'utf8' });
    eq(r.status, 0, 'exit code:');
    inclui(r.stdout, 'NAO CALIBRADO');
  });

  console.log('\n── vetor D: calibração NÃO pode poluir o backfill_error_rate (ratchet required) ──');

  deve('juiz entry (canônica E malformada) NÃO move measureBackfillErrorRate (achado skeptic 1)', () => {
    const lote = { pr: 1, tipo: 'anchors', veredito: 'aprovado', error_rate_pct: 0 };
    const base = measureBackfillErrorRate(escreve('sc-base.json', { entries: [lote] })).value;
    eq(base, 0, 'baseline:');
    const juizCanon = { ...rodadaValida, pr: 2 };
    eq(measureBackfillErrorRate(escreve('sc-canon.json', { entries: [lote, juizCanon] })).value, 0, 'com juiz canônico:');
    // malformada: alguém copia campos de lote pra uma entry juiz por engano/ataque.
    // O filtro na BORDA (tipo!==juiz) tem que ignorar mesmo assim — senão o ratchet
    // required lê 99 e trava o repo inteiro.
    const juizPodre = { ...rodadaValida, pr: 3, veredito: 'aprovado', error_rate_pct: 99 };
    eq(measureBackfillErrorRate(escreve('sc-podre.json', { entries: [lote, juizPodre] })).value, 0, 'com juiz MALFORMADO:');
  });

  deve('ledger PODRE (entries:[null]) não derruba o report — exit 0 (achado skeptic 2)', () => {
    // JSON válido, mas com entry null. Antes do guard `e &&`, isto lançava TypeError
    // e saía != 0, quebrando a invariante "MEDIÇÃO, não portão".
    const r0 = juizReport(escreve('null-entry.json', { entries: [null, rodadaValida] }));
    eq(r0.calibrado, true, 'juizReport sobrevive ao null:');
    eq(r0.rodadas, 1, 'conta só a válida:');
    const cli = spawnSync(process.execPath, [SCRIPT, '--juiz-report', '--ledger', join(tmp, 'null-entry.json')], { encoding: 'utf8' });
    eq(cli.status, 0, 'CLI exit code no ledger podre:');
  });

  deve('--juiz-report no ledger canônico do repo não quebra (schema real)', () => {
    const r = spawnSync(process.execPath, [SCRIPT, '--juiz-report', '--json'], { encoding: 'utf8' });
    eq(r.status, 0, 'exit code:');
    JSON.parse(r.stdout); // tem que ser JSON válido
  });
} finally {
  rmSync(tmp, { recursive: true, force: true });
}

console.log(`\n${fails.length ? '✗ FALHOU' : '✓ OK'} — ${pass} passaram, ${fails.length} falharam`);
if (fails.length) { for (const f of fails) console.log(`  ✗ ${f}`); process.exit(1); }
