#!/usr/bin/env node
// junit-corpus.test.mjs — selftest do agregador do corpus LC-13.
//
// O que este selftest PROVA (e o que ele deliberadamente não prova):
//  · a classificação dos 3 estados, incluindo os DOIS caminhos de NAO_MEDIDO
//  · a propriedade que é a razão de ser do script: lane NÃO-EXECUTADA fica FORA do
//    denominador (senão a taxa infla com o caso mais comum do repo — skip-as-pass)
//  · taxa `null` quando nada executou — 0/0 não é "taxa zero", é ausência de medição
//  · o campo do dono (`provou_algo`) é RESPEITADO, não recalculado
//  · E2E: dir vazio/ausente → exit 1 (corpus vazio não sai verde)
//
// NÃO prova que o `--check-assertions` deve ser armado — isso é o passo (3) do LC-13 e
// depende do NÚMERO que este script produz contra o corpus real, mais decisão [W].
import { mkdtempSync, writeFileSync, mkdirSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname } from 'node:path';
import { classificar, arquivosMudos, agregar, lerDir, formatar, ESTADOS } from './junit-corpus.mjs';

const AQUI = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(AQUI, 'junit-corpus.mjs');
let fails = 0;
const check = (nome, ok) => { console.log(`${ok ? '[OK]  ' : '[FALHOU]'} ${nome}`); if (!ok) fails++; };

// ── fixtures no formato REAL do junit-summary/v1 ─────────────────────────────────────
const provou = {
  schema: 'junit-summary/v1', n_testcases: 20, n_testcases_declared: 20, coherent: true,
  provou_algo: true, totals: { assertions: 84, tests: 20, passed: 20, failed: 0, errors: 0, skipped: 0 },
  files: [{ file: 'tests/Feature/A.php', tests: 20, passed: 20, failed: 0, errors: 0, skipped: 0, assertions: 84 }],
};
const semProvar = {
  schema: 'junit-summary/v1', n_testcases: 4, n_testcases_declared: 4, coherent: true,
  provou_algo: false, totals: { assertions: 0, tests: 4, passed: 0, failed: 0, errors: 0, skipped: 4 },
  files: [{ file: 'tests/Feature/B.php', tests: 4, passed: 0, failed: 0, errors: 0, skipped: 4, assertions: 0 }],
};
const marcadorInvalid = {
  schema: 'fullsuite-summary-invalid/v1', invalid: true, reason: 'artefato_ausente',
  source: 'test-results/pest-x-junit.xml', detail: 'XML nao existe',
};
const incoerente = {
  schema: 'junit-summary/v1', n_testcases: 7, n_testcases_declared: 9, coherent: false,
  provou_algo: true, totals: { assertions: 12 }, files: [],
};
// lane que PROVOU no agregado mas carrega um arquivo 100% skipped dentro — o caso real
// catalogado no comentário do nfebrasil-pest (Fiscal/NfseCockpitControllerTest 0/4).
const provouComArquivoMudo = {
  schema: 'junit-summary/v1', n_testcases: 24, n_testcases_declared: 24, coherent: true,
  provou_algo: true, totals: { assertions: 84, tests: 24, skipped: 4 },
  files: [
    { file: 'tests/Feature/A.php', tests: 20, passed: 20, skipped: 0, assertions: 84 },
    { file: 'tests/Feature/Fiscal/NfseCockpitControllerTest.php', tests: 4, passed: 0, skipped: 4, assertions: 0 },
  ],
};

// ── classificação ────────────────────────────────────────────────────────────────────
check('classifica PROVOU', classificar(provou).estado === ESTADOS.PROVOU);
check('classifica EXECUTOU_SEM_PROVAR (candidato LC-13)',
  classificar(semProvar).estado === ESTADOS.EXECUTOU_SEM_PROVAR);
check('NAO_MEDIDO via marcador {invalid} do dono',
  classificar(marcadorInvalid).estado === ESTADOS.NAO_MEDIDO && classificar(marcadorInvalid).razao === 'artefato_ausente');
check('NAO_MEDIDO via coleta incoerente (contados != declarados)',
  classificar(incoerente).estado === ESTADOS.NAO_MEDIDO && classificar(incoerente).razao === 'coleta_incoerente');
check('NAO_MEDIDO via summary ilegivel (JSON quebrado -> null)',
  classificar(null).estado === ESTADOS.NAO_MEDIDO);

// CONTROLE: o campo do dono manda. Summary com assertions>0 mas provou_algo=false segue
// candidato — recalcular aqui criaria um 2º oraculo do mesmo fato, livre pra drifar.
check('respeita provou_algo do dono em vez de recalcular',
  classificar({ ...semProvar, totals: { assertions: 99 }, provou_algo: false }).estado === ESTADOS.EXECUTOU_SEM_PROVAR);
// e o fallback so entra quando o campo NAO existe (summary pre-2026-07-29)
check('fallback por assertions quando provou_algo ausente (summary legado)',
  classificar({ schema: 'junit-summary/v1', n_testcases: 3, coherent: true, totals: { assertions: 5 } }).estado === ESTADOS.PROVOU);

// ── a propriedade central: denominador NÃO infla com lane não-executada ──────────────
const mix = agregar([
  { lane: 'financeiro', summary: provou },
  { lane: 'estoque', summary: semProvar },
  { lane: 'sells', summary: marcadorInvalid },   // skip-as-pass: Pest nem rodou
  { lane: 'jana', summary: marcadorInvalid },    // idem
]);
check('denominador conta SO quem executou (2, nao 4)', mix.denominador_executado === 2);
check('taxa = 1 candidato / 2 executadas = 50%', Math.abs(mix.taxa_zero_assertions - 0.5) < 1e-9);
check('NAO_MEDIDO reportado, nunca descartado em silencio', mix.nao_medido === 2);
check('lanes_lidas preserva o total bruto (4)', mix.lanes_lidas === 4);

// CONTROLE NEGATIVO da mesma propriedade: se as nao-executadas entrassem no denominador,
// a taxa cairia de 50% pra 25% — exatamente a diluicao que este desenho recusa.
check('CONTROLE: incluir nao-executadas daria 25% (a taxa errada que o desenho evita)',
  Math.abs((mix.executou_sem_provar / mix.lanes_lidas) - 0.25) < 1e-9);

// ── taxa null quando nada executou (0/0 nao e "zero") ────────────────────────────────
const sohMortas = agregar([{ lane: 'a', summary: marcadorInvalid }, { lane: 'b', summary: incoerente }]);
check('taxa e null (nao 0) quando nenhuma lane executou', sohMortas.taxa_zero_assertions === null);
check('formatar diz "n/a" em vez de 0% quando nada executou',
  /n\/a \(nenhuma lane executou\)/.test(formatar(sohMortas)));

// ── arquivos mudos: sinal fino, unidade ARQUIVO, nunca somado ao veredito da lane ────
const comMudo = agregar([{ lane: 'nfebrasil', summary: provouComArquivoMudo }]);
check('detecta arquivo 100% skipped dentro de lane que provou',
  comMudo.arquivos_mudos_total === 1
  && comMudo.lanes[0].arquivos_mudos[0].file.includes('NfseCockpitControllerTest'));
check('arquivo mudo NAO vira candidato de lane (unidades diferentes)',
  comMudo.executou_sem_provar === 0 && comMudo.provou === 1);
check('lane NAO_MEDIDA nao reporta arquivo mudo (nao ha o que medir)',
  agregar([{ lane: 'x', summary: marcadorInvalid }]).arquivos_mudos_total === 0);
// CONTROLE (bug pego rodando o step, nao lendo o codigo): numa lane que NAO provou, todo
// arquivo e mudo por definicao — conta-los inflaria o sinal fino e faria o rotulo
// ("dentro de lane que provou") mentir. O achado ali e a LANE, nao os arquivos dela.
check('lane que NAO provou nao duplica seus arquivos como mudos (a lane ja e o achado)',
  agregar([{ lane: 'estoque', summary: semProvar }]).arquivos_mudos_total === 0);

// ── determinismo + ordenacao estavel ─────────────────────────────────────────────────
const entradas = [{ lane: 'z', summary: provou }, { lane: 'a', summary: semProvar }];
check('saida determinista (mesmo input -> mesmo JSON)',
  JSON.stringify(agregar(entradas)) === JSON.stringify(agregar(entradas)));
check('lanes ordenadas por nome (independe da ordem de leitura)',
  agregar(entradas).lanes.map((l) => l.lane).join(',') === 'a,z');

// ── E2E ──────────────────────────────────────────────────────────────────────────────
const tmp = mkdtempSync(join(tmpdir(), 'junit-corpus-'));
const rodar = (dir, extra = []) => {
  try {
    const out = execFileSync('node', [SCRIPT, '--dir', dir, ...extra], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
    return { code: 0, out };
  } catch (e) { return { code: e.status ?? -1, out: `${e.stdout || ''}${e.stderr || ''}` }; }
};

// (a) dir AUSENTE -> exit 1. E a razao importa: corpus vazio saindo verde seria o proprio
//     LC-13 um nivel acima ("0 candidatos" sem ter medido nada).
const ausente = rodar(join(tmp, 'nao-existe'));
check('E2E: dir ausente -> exit 1 (nao sai verde)', ausente.code === 1 && /NAO CONSEGUI MEDIR/.test(ausente.out));

// (b) dir VAZIO -> exit 1 tambem (colheita do dia falhou)
const vazio = join(tmp, 'vazio'); mkdirSync(vazio, { recursive: true });
check('E2E: dir vazio -> exit 1', rodar(vazio).code === 1);

// (c) caminho feliz -> exit 0 mesmo COM candidato (achado nao e falha)
const cheio = join(tmp, 'cheio'); mkdirSync(cheio, { recursive: true });
writeFileSync(join(cheio, 'financeiro.json'), JSON.stringify(provou));
writeFileSync(join(cheio, 'estoque.json'), JSON.stringify(semProvar));
writeFileSync(join(cheio, 'sells.json'), JSON.stringify(marcadorInvalid));
const feliz = rodar(cheio);
check('E2E: com candidato -> exit 0 (medir e o trabalho; achar nao e falhar)', feliz.code === 0);
check('E2E: nomeia a lane candidata', /estoque — 4 testcase\(s\), 0 assertions/.test(feliz.out));
check('E2E: declara o denominador ao lado da taxa', /denominador = 2 lane\(s\) que EXECUTARAM/.test(feliz.out));
check('E2E: declara a nao-medida em vez de omitir', /sells — artefato_ausente/.test(feliz.out));

// (d) JSON quebrado no dir nao derruba o run — vira NAO_MEDIDO declarado
writeFileSync(join(cheio, 'quebrado.json'), '{ nao e json valido');
const comQuebrado = rodar(cheio);
check('E2E: JSON quebrado vira NAO_MEDIDO declarado, nao crash', comQuebrado.code === 0 && /quebrado — summary_ilegivel/.test(comQuebrado.out));

// (e) --json emite o schema
const jsonOut = rodar(cheio, ['--json']);
check('E2E: --json emite schema junit-corpus/v1', /"schema": "junit-corpus\/v1"/.test(jsonOut.out));

// lerDir contract
check('lerDir devolve null em dir ausente', lerDir(join(tmp, 'nada')) === null);
check('lerDir le e nomeia a lane pelo basename', (lerDir(cheio) || []).some((e) => e.lane === 'financeiro'));

rmSync(tmp, { recursive: true, force: true });

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — corpus LC-13: 3 estados, denominador so-executadas, taxa null honesta, E2E exit 1 quando nao mede.');
process.exit(fails ? 1 : 0);
