#!/usr/bin/env node
// junit-corpus.mjs — agrega N summaries `junit-summary/v1` na DISTRIBUIÇÃO de assertions
//                     por lane. É o passo (2) do plano escrito no campo `Gate:` do LC-13.
// (caminho: scripts/tests/junit-corpus.mjs)
//
// =====================================================================================
// POR QUE EXISTE (e por que NÃO é máquina nova pro tema)
// =====================================================================================
// O LC-13 ("VERDE por NÃO-EXECUÇÃO") tem o discriminador candidato PRONTO desde 2026-07-29:
// o flag `--check-assertions` do `junit-summary.mjs` MORDE (par bite/libera no selftest
// dele) e está DESARMADO de propósito. O plano em 3 passos está escrito no `Gate:` do LC-13:
//
//   (1) ligar o REPORT nas lanes  → é o que CRIA o corpus   ......... FECHADO (12/12 lanes)
//   (2) com o corpus, medir a taxa de FP  ......................... ← ESTE SCRIPT
//   (3) só então armar  .................................. decisão [W], com o número na mão
//
// Armar antes de (2) seria adivinhar com passos extras — o anti-padrão que o §5 mata 4×
// (allowlist-de-pasta 06-30 · guard `@scope` 07-09 · vocabulário 130 FP 07-16 · `toHaveKey`
// 100% FP 07-26). Este script NÃO arma nada e NÃO reprova nada: ele MEDE.
//
// =====================================================================================
// ONDE ELE RODA — e por que não nasceu um workflow novo
// =====================================================================================
// O `casos-results-publish.yml` (cron diário 07:30 BRT) JÁ colhe o JUnit das lanes
// derivadas por `scripts/governance/junit-lanes.mjs` para dentro de `test-results/`, com
// `actions: read` e escolhendo "o run mais recente que TENHA o artifact" (não "o último
// verde" — as lanes são failing-first por desenho, ADR 0351). O corpus do LC-13 é uma
// SEGUNDA LEITURA dessa mesma colheita.
//
// Logo: ESTENDER o dono, nunca abrir paralelo (§5 2026-07-09 "duplica régua consolidada" ·
// LC-19 "autorar máquina PARALELA a um tema que já tem dono"). Sem workflow novo, o Check M
// (teto de governança, ADR 0298) não é sequer acionado — não há gate nascendo aqui.
//
// =====================================================================================
// A PERGUNTA QUE ELE RESPONDE (e as duas que ele RECUSA responder)
// =====================================================================================
// RESPONDE: "quando a lane DE FATO EXECUTOU, com que frequência ela não provou nada?"
//   — é essa taxa, e só ela, que decide se `--check-assertions` pode ser armado.
//
// RECUSA (1): não conta lane NÃO-EXECUTADA como candidata. Medido 2026-08-16: as 12 lanes
//   wiram o step do sumário sob `if: always()`, então ele roda até quando o skip-as-pass
//   (ADR 0271 onda 2) impediu o Pest de rodar — e aí o XML nem existe. Lane corretamente
//   não-executada NÃO é "verde por não-execução"; é ausência de trabalho, não de prova.
//   Confundir as duas inflaria a taxa com o caso mais comum do repo.
//
// RECUSA (2): não emite NOTA/ÍNDICE agregando os três estados num número só. Eles não são
//   comensuráveis (§5 2026-07-17 "razão de fidelidade") — `NAO_MEDIDO` mede a colheita,
//   `EXECUTOU_SEM_PROVAR` mede a suíte. O denominador sai SEMPRE declarado ao lado.
//
// =====================================================================================
// CONTRATO
// =====================================================================================
//   Uso: node scripts/tests/junit-corpus.mjs --dir <dir-de-summaries> [--json]
//     lê todo *.json do dir; aceita `junit-summary/v1` e o marcador
//     `fullsuite-summary-invalid/v1` (que junit-summary grava quando o XML morreu).
//
//   Exit: 0 = MEDIU (achar candidato NÃO é falha — é o achado)
//         1 = NÃO CONSEGUI MEDIR (dir ausente/vazio/ilegível)
//
//   O exit 1 é a defesa do §5 2026-07-29: "não consegui medir" jamais pode sair verde,
//   senão o corpus vira `0 candidatos` num dia em que nada foi colhido — que é o MESMO
//   formato de `0 failed` numa suíte que não rodou (o próprio LC-13, um nível acima).
//
// Zero-dep (node >=18), puro fs, determinístico: mesmo input → mesmo output, sem timestamp
// no corpo (espelha floor-compute/nightly-diff, os irmãos do tema).
import { readdirSync, readFileSync, existsSync, statSync } from 'node:fs';
import { join, basename } from 'node:path';
import { fileURLToPath } from 'node:url';

const arg = (k, d) => { const i = process.argv.indexOf(k); return i >= 0 ? process.argv[i + 1] : d; };

// ── classificação de UM summary ──────────────────────────────────────────────────────
// Os três estados são DISJUNTOS e o nome de cada um diz o que ele mede. Só o do meio é
// candidato do LC-13; misturar os outros dois nele é o erro que este script existe pra
// não cometer.
export const ESTADOS = {
  NAO_MEDIDO: 'NAO_MEDIDO',                   // XML ausente/morto/incoerente → a colheita falhou
  EXECUTOU_SEM_PROVAR: 'EXECUTOU_SEM_PROVAR', // rodou testcase E 0 assertions → CANDIDATO LC-13
  PROVOU: 'PROVOU',                           // rodou e provou algo
};

export function classificar(s) {
  // marcador {invalid} do junit-summary, ou summary sem forma reconhecível
  if (!s || typeof s !== 'object') return { estado: ESTADOS.NAO_MEDIDO, razao: 'summary_ilegivel' };
  if (s.invalid) return { estado: ESTADOS.NAO_MEDIDO, razao: s.reason || 'invalid' };
  // `coherent` é o contrato do dono (contados === declarados && contados > 0). Coleta
  // incoerente não diz nada sobre assertions — diz que a coleta quebrou.
  if (!s.coherent) return { estado: ESTADOS.NAO_MEDIDO, razao: 'coleta_incoerente' };
  const n = Number(s.n_testcases) || 0;
  if (n <= 0) return { estado: ESTADOS.NAO_MEDIDO, razao: 'coleta_0_testcases' };

  // `provou_algo` é campo do dono — NÃO recalculo aqui. Recalcular seria um segundo
  // oráculo do mesmo fato, livre pra drifar do primeiro (§5 2026-07-17: doc/máquina não
  // restateia número que outro sistema sabe melhor). Só caio no fallback se o campo
  // não existir (summary de versão anterior ao 2026-07-29).
  const provou = typeof s.provou_algo === 'boolean'
    ? s.provou_algo
    : ((Number(s?.totals?.assertions) || 0) > 0);

  return provou
    ? { estado: ESTADOS.PROVOU, razao: null }
    : { estado: ESTADOS.EXECUTOU_SEM_PROVAR, razao: 'zero_assertions' };
}

// ── arquivos MUDOS dentro de uma lane que, no agregado, provou ───────────────────────
// Sinal mais fino que o do flag: a lane inteira quase nunca dá 0, mas ARQUIVO que roda
// 100% skipped é comum e é onde o LC-13 vive na prática — foi assim que o comentário do
// nfebrasil-pest catalogou `Fiscal/NfseCockpitControllerTest: passed=0, skipped=4`.
// Reportado à parte, NUNCA somado ao veredito da lane: são unidades diferentes (arquivo
// × lane) e agregá-las seria o número incomensurável que a RECUSA (2) proíbe.
export function arquivosMudos(s) {
  if (!Array.isArray(s?.files)) return [];
  return s.files
    .filter((f) => (Number(f.tests) || 0) > 0 && (Number(f.assertions) || 0) === 0)
    .map((f) => ({ file: f.file, tests: Number(f.tests) || 0, skipped: Number(f.skipped) || 0 }))
    .sort((a, b) => a.file.localeCompare(b.file));
}

// ── agregação ────────────────────────────────────────────────────────────────────────
export function agregar(entradas) {
  const lanes = entradas
    .map(({ lane, summary }) => {
      const { estado, razao } = classificar(summary);
      return {
        lane,
        estado,
        razao,
        n_testcases: Number(summary?.n_testcases) || 0,
        assertions: Number(summary?.totals?.assertions) || 0,
        // SÓ pra lane que PROVOU. Numa lane EXECUTOU_SEM_PROVAR todo arquivo é mudo por
        // definição — listá-los ali infla o sinal fino e faz a contagem discordar do que
        // o rótulo promete (peguei rodando, não lendo). O sinal só é NOTÍCIA quando o
        // arquivo está calado DENTRO de uma lane que, no agregado, parece saudável.
        arquivos_mudos: estado === ESTADOS.PROVOU ? arquivosMudos(summary) : [],
      };
    })
    .sort((a, b) => a.lane.localeCompare(b.lane));

  const por = (e) => lanes.filter((l) => l.estado === e);
  const naoMedido = por(ESTADOS.NAO_MEDIDO);
  const semProvar = por(ESTADOS.EXECUTOU_SEM_PROVAR);
  const provou = por(ESTADOS.PROVOU);

  // DENOMINADOR = só quem executou. Declarado junto do número, sempre — a lápide
  // §5 2026-07-27 mata "denominador inventado", e a de 07-29 mata superlativo sobre
  // população que não foi percorrida.
  const executaram = semProvar.length + provou.length;

  return {
    schema: 'junit-corpus/v1',
    lanes_lidas: lanes.length,
    denominador_executado: executaram,
    nao_medido: naoMedido.length,
    executou_sem_provar: semProvar.length,
    provou: provou.length,
    // null (não 0) quando nada executou: 0/0 não é "taxa zero", é ausência de medição.
    taxa_zero_assertions: executaram > 0 ? semProvar.length / executaram : null,
    arquivos_mudos_total: lanes.reduce((a, l) => a + l.arquivos_mudos.length, 0),
    lanes,
  };
}

// ── leitura do diretório ─────────────────────────────────────────────────────────────
export function lerDir(dir) {
  if (!existsSync(dir) || !statSync(dir).isDirectory()) return null;
  const arquivos = readdirSync(dir).filter((f) => f.endsWith('.json')).sort();
  if (!arquivos.length) return null;
  return arquivos.map((f) => {
    let summary = null;
    try { summary = JSON.parse(readFileSync(join(dir, f), 'utf8')); } catch { summary = null; }
    return { lane: basename(f, '.json'), summary };
  });
}

export function formatar(r) {
  const pct = r.taxa_zero_assertions === null
    ? 'n/a (nenhuma lane executou)'
    : `${(r.taxa_zero_assertions * 100).toFixed(1)}%`;
  const out = [
    'CORPUS LC-13 — distribuicao de assertions por lane',
    '',
    `  lanes lidas ................. ${r.lanes_lidas}`,
    `  NAO_MEDIDO .................. ${r.nao_medido}  (XML ausente/morto — a colheita falhou, nao a suite)`,
    `  EXECUTOU_SEM_PROVAR ......... ${r.executou_sem_provar}  <- candidato LC-13`,
    `  PROVOU ...................... ${r.provou}`,
    '',
    `  taxa zero-assertions ........ ${pct}  (denominador = ${r.denominador_executado} lane(s) que EXECUTARAM)`,
    `  arquivos mudos (100% skip) .. ${r.arquivos_mudos_total}  (sinal fino; unidade ARQUIVO, nao lane)`,
  ];
  if (r.executou_sem_provar) {
    out.push('', '  [!] lanes que rodaram e nao provaram nada:');
    for (const l of r.lanes.filter((x) => x.estado === ESTADOS.EXECUTOU_SEM_PROVAR)) {
      out.push(`      ${l.lane} — ${l.n_testcases} testcase(s), 0 assertions`);
    }
  }
  if (r.arquivos_mudos_total) {
    out.push('', '  arquivos 100% skipped dentro de lane que provou:');
    for (const l of r.lanes.filter((x) => x.arquivos_mudos.length)) {
      for (const f of l.arquivos_mudos.slice(0, 6)) {
        out.push(`      ${l.lane} :: ${f.file} (${f.tests} teste(s), ${f.skipped} skip)`);
      }
      if (l.arquivos_mudos.length > 6) out.push(`      ${l.lane} :: … +${l.arquivos_mudos.length - 6}`);
    }
  }
  if (r.nao_medido) {
    out.push('', '  nao medidas (NAO sao candidatas — declaradas p/ o denominador ficar honesto):');
    for (const l of r.lanes.filter((x) => x.estado === ESTADOS.NAO_MEDIDO)) {
      out.push(`      ${l.lane} — ${l.razao}`);
    }
  }
  out.push('', '  Report-only. Armar o --check-assertions e decisao [W] (passo 3 do LC-13),');
  out.push('  e exige step proprio guardado pela MESMA condicao do run de Pest — nao');
  out.push('  o step `if: always()` do sumario, que roda ate quando o Pest nao rodou.');
  return out.join('\n');
}

// ── CLI ──────────────────────────────────────────────────────────────────────────────
const ehCli = process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1];
if (ehCli) {
  const dir = arg('--dir', 'test-results');
  const entradas = lerDir(dir);
  if (!entradas) {
    console.error(`junit-corpus: NAO CONSEGUI MEDIR — '${dir}' ausente, vazio ou ilegivel.`);
    console.error('  (exit 1 de proposito: corpus vazio NAO pode sair verde como "0 candidatos" — §5 2026-07-29)');
    process.exit(1);
  }
  const r = agregar(entradas);
  console.log(process.argv.includes('--json') ? JSON.stringify(r, null, 2) : formatar(r));
  process.exit(0);
}
