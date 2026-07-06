#!/usr/bin/env node
// Self-test mv-metabolismo — prova as regras vs o CONTRATO (arte 2026-07-05 §3.4/§3.6),
// NÃO vs a implementação. Contrato ancorado:
//   §3.6 regra 2: tela verde+fresca (nota≥80, casos+charter, não-stale) PULA o ciclo
//   §3.6 regra 3: gate humano pendente → não empilha batch novo
//   §3.4: batimento — dinheiro_fiscal re-entra em 1d, vertical 3d, resto 7d
//   budget: corta a fila, nunca ultrapassa
// Roda: node scripts/qa/mv-metabolismo.test.mjs
import { verdeFresca, estadoBatches, modDevido, selecionaBatch, acaoProposta, fmScalar, BATIMENTO_DIAS, batchesInconsistentes, jaHouveBatchHoje, boostPrototipo, PROTOTIPO_BOOST } from './mv-metabolismo.mjs';

let fails = 0;
const check = (n, c, extra = '') => { console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : '  → ' + extra}`); if (!c) fails++; };

const tela = (over) => ({ screen: 'X/A', mod: 'X', classe: 'resto', nota: 50, stale: false, casos: false, charter: false, prioridade: 100, ...over });

// 1. Verde+fresca pula (§3.6 r2): nota 85 + casos + charter + fresca → fora da fila.
{
  check('nota85+casos+charter fresca → pula', verdeFresca(tela({ nota: 85, casos: true, charter: true })) === true);
  check('nota85 SEM casos → NÃO pula (contrato-first)', verdeFresca(tela({ nota: 85, casos: false, charter: true })) === false);
  check('nota85 completa mas STALE → NÃO pula (frescor manda)', verdeFresca(tela({ nota: 85, casos: true, charter: true, stale: true })) === false);
  check('nota 79 → NÃO pula', verdeFresca(tela({ nota: 79, casos: true, charter: true })) === false);
}

// 2. Gate humano pendente (§3.6 r3): batch proposto OU aprovado bloqueia; executado/rejeitado libera.
{
  const b = (status) => [{ file: 'x.md', date: '2026-07-04', status, modulos: ['Financeiro'] }];
  check('proposto → pendente', estadoBatches(b('proposto')).pendente !== null);
  check('aprovado (mergeado, não executado) → ainda pendente', estadoBatches(b('aprovado')).pendente !== null);
  check('executado → livre', estadoBatches(b('executado')).pendente === null);
  check('rejeitado → livre', estadoBatches(b('rejeitado')).pendente === null);
}

// 3. Batimento por classe (§3.4): dinheiro re-entra no dia seguinte; resto precisa de 7d.
{
  const uc = new Map([['Sells', '2026-07-04'], ['kb', '2026-07-04']]);
  check('dinheiro 1d depois → devido', modDevido('Sells', 'dinheiro_fiscal', uc, '2026-07-05') === true);
  check('resto 1d depois → NÃO devido', modDevido('kb', 'resto', uc, '2026-07-05') === false);
  check('resto 7d depois → devido', modDevido('kb', 'resto', uc, '2026-07-11') === true);
  check('módulo nunca ciclado → devido', modDevido('Novo', 'resto', new Map(), '2026-07-05') === true);
}

// 4. Budget corta a fila (§3.6 r1): 10 telas devidas, budget 3 → 3 selecionadas, na ordem da fila.
{
  const fila = Array.from({ length: 10 }, (_, i) => tela({ screen: `M/T${i}`, mod: `M${i}`, prioridade: 1000 - i }));
  const sel = selecionaBatch(fila, new Map(), '2026-07-05', 3);
  check('budget 3 → 3 telas, maiores prioridades primeiro', sel.length === 3 && sel[0].screen === 'M/T0' && sel[2].screen === 'M/T2', JSON.stringify(sel.map((t) => t.screen)));
}

// 5. Seleção composta: verde-fresca e fora-de-batimento saem; próxima da fila entra no lugar.
{
  const fila = [
    tela({ screen: 'A/verde', mod: 'A', nota: 90, casos: true, charter: true }),           // pula (verde+fresca)
    tela({ screen: 'B/recem', mod: 'B', classe: 'resto', prioridade: 500 }),               // fora de batimento (ciclado ontem)
    tela({ screen: 'C/entra', mod: 'C', prioridade: 400 }),
  ];
  const sel = selecionaBatch(fila, new Map([['B', '2026-07-04']]), '2026-07-05', 5);
  check('verde pula + fora-de-batimento pula → só C entra', sel.length === 1 && sel[0].screen === 'C/entra', JSON.stringify(sel.map((t) => t.screen)));
}

// 6. Ação proposta deriva do gap (contrato-first): sem prontuário → ciclo completo; sem casos → contrato primeiro.
{
  check('sem scorecard → ciclo completo', acaoProposta(tela({ nota: null })).includes('COMPLETO'));
  check('sem casos → propõe casos.md + Pest ancorado', acaoProposta(tela({ nota: 70, charter: true })).includes('casos.md'));
  check('stale → propõe re-grade', acaoProposta(tela({ nota: 82, casos: true, charter: true, stale: true })).includes('re-grade'));
}

// 7. fmScalar lê o frontmatter controlado (status/date/modulos).
{
  const md = '---\ndate: "2026-07-05"\nstatus: proposto\nmodulos: [Financeiro, Sells]\n---\n# x';
  check('frontmatter: date/status/modulos', fmScalar(md, 'date') === '2026-07-05' && fmScalar(md, 'status') === 'proposto' && fmScalar(md, 'modulos') === '[Financeiro, Sells]');
}

// 8. Sanidade do contrato §3.4: constantes de batimento são as da arte (mudança = decisão consciente).
{
  check('batimento = {dinheiro 1, vertical 3, resto 7}', BATIMENTO_DIAS.dinheiro_fiscal === 1 && BATIMENTO_DIAS.vertical_prod === 3 && BATIMENTO_DIAS.resto === 7);
}

// 9. Consistência batch↔scorecard (adversário 2026-07-06 V2): scorecard 'mv-batch-<date>'
//    emitido com batch ainda proposto/aprovado = status mentindo → detectado. Counterfactual
//    do incidente real: o batch 2026-07-06 ficou 'proposto' em main enquanto o scorecard do
//    Impostos (source: mv-batch-2026-07-06) já tinha sido mergeado via #3858.
{
  const b = (status) => [{ file: '2026-07-06.md', date: '2026-07-06', status, modulos: ['Financeiro'] }];
  const srcExec = ['mv-batch-2026-07-06', 'regrade-onda1-2026-07-05'];
  check('proposto + scorecard emitido → mentira detectada', batchesInconsistentes(b('proposto'), srcExec).length === 1);
  check('aprovado + scorecard emitido → também detectada (execução sem registro)', batchesInconsistentes(b('aprovado'), srcExec).length === 1);
  check('executado + scorecard emitido → consistente (ok)', batchesInconsistentes(b('executado'), srcExec).length === 0);
  check('proposto SEM scorecard da data → ok (ainda aguardando)', batchesInconsistentes(b('proposto'), ['regrade-onda1-2026-07-05']).length === 0);
}

// 10. 1 batch/dia máximo (bug do dogfood 2026-07-06): batch EXECUTADO do dia não abre espaço
//     pra segundo batch na mesma data — sobrescreveria o histórico (append-only).
{
  const arqs = [{ file: '2026-07-06.md', date: '2026-07-06', status: 'executado', modulos: ['Financeiro'] }];
  check('batch executado hoje → NÃO gera segundo batch hoje', jaHouveBatchHoje(arqs, '2026-07-06') === true);
  check('sem batch hoje → gera normalmente', jaHouveBatchHoje(arqs, '2026-07-07') === false);
}

// 11. Boost de protótipo (Wagner 2026-07-06): tela 1-ciclo com protótipo real sobe na fila
//     (blindá-la desbloqueia a aplicação do visual). Multiplicativo, não override.
{
  const fila = [
    { screen: 'A/comum', prioridade: 200, _prototipo: false },
    { screen: 'B/proto', prioridade: 150 },
    { screen: 'C/comum', prioridade: 100 },
  ];
  const out = boostPrototipo(fila, ['B/proto'], 1.6);
  const b = out.find((t) => t.screen === 'B/proto');
  check('protótipo boostado 150→240 (×1.6)', b.prioridade === 240 && b._prototipo === true, JSON.stringify(b));
  check('boost RE-ORDENA: B/proto passa A/comum', out[0].screen === 'B/proto', out.map((t) => t.screen).join(','));
  check('tela não-protótipo intacta', out.find((t) => t.screen === 'A/comum').prioridade === 200);
  check('não muta a fila original', fila[1].prioridade === 150);
}

// 12. Boost NÃO atropela dinheiro Tier-0 quebrado (multiplicativo respeita a base): tela comum
//     de prioridade 600 (dinheiro sem prontuário) ainda vence protótipo 150×1.6=240.
{
  const fila = [
    { screen: 'Fin/critico', prioridade: 600 },
    { screen: 'Proto/tela', prioridade: 150 },
  ];
  const out = boostPrototipo(fila, ['Proto/tela'], 1.6);
  check('dinheiro-600 ainda > protótipo-boostado-240', out[0].screen === 'Fin/critico');
}

// 13. Constante do boost declarada (mudança = decisão consciente).
check('PROTOTIPO_BOOST = 1.6', PROTOTIPO_BOOST === 1.6);

// 14. acaoProposta marca o motivo do protótipo.
check('ação de tela _prototipo cita desbloqueio', acaoProposta({ screen: 'X', nota: 70, casos: false, charter: true, _prototipo: true }).includes('protótipo'));

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ contrato do metabolismo preservado');
process.exit(fails ? 1 : 0);
