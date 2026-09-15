#!/usr/bin/env node
// @ts-check
/**
 * baseline-folga.mjs — trava o ganho que ninguém lembrou de travar (auto-APERTO de baseline).
 *
 * ── O PROBLEMA (medido 2026-09-09, [W]: "baseline só incomoda, sempre fica desatualizado") ──
 * Catraca com baseline drifta nas DUAS direções, e o projeto só defende UMA:
 *
 *   AFROUXAR (teto sobe / item novo grandfatherado)  → `baseline-tamper-guard.mjs` BARRA.
 *                                                      É o Gap 2 do blueprint SDD (ADR 0256/0258):
 *                                                      regressão entrando verde no mesmo PR.
 *   APERTAR  (o mundo melhorou, o baseline ficou     → NINGUÉM. O gate imprime "considere
 *             mais frouxo que a realidade)              re-gravar" e torce pra alguém lembrar.
 *
 * A segunda é "escrito+lembrado apodrece" (ADR 0256) na forma mais pura: o instrumento
 * DETECTA o ganho e delega a ação a memória humana. Medido no dia: **4 de 16** gates
 * sondados estavam frouxos, com ~25 unidades de dívida já paga e não travada —
 * `perf-static-guard` (8→6 e 20→11), `cowork-pele-paralela` (12 dívidas pagas),
 * `deadlink-gate` (1 arquivo) e `a11y-ratchet` (246→245).
 *
 * ── POR QUE APERTAR PODE SER AUTOMÁTICO E AFROUXAR NÃO ──────────────────────
 * Assimetria, não conveniência: apertar **nunca mascara regressão** — ele só torna o
 * gate MAIS estrito do que estava. O vetor que o tamper-guard fecha (dívida nova entrando
 * escondida no baseline) é, por construção, inalcançável por um mecanismo que só reduz teto
 * e só remove item. Por isso este script é o COMPLEMENTO do tamper-guard, na direção oposta —
 * não um segundo dono do mesmo tema (LC-19).
 *
 * ── COMO ELE DECIDE A DIREÇÃO (artefato, nunca prosa) ───────────────────────
 * NÃO parseia a mensagem do gate. Parsear "melhorou ✅" seria presence-gate sobre texto
 * livre — a classe LC-11, com 11 ocorrências no ledger. O que ele faz:
 *   1. exige o baseline LIMPO no git (working tree sujo ⇒ PULA, nunca destrói — LC-23);
 *   2. roda o regenerador REAL daquele baseline (o mesmo comando que um humano rodaria);
 *   3. compara o JSON regravado com o commitado e classifica a direção pelo DADO;
 *   4. se não for APERTOU puro, REVERTE o arquivo e reporta.
 * Só `APERTOU` sobrevive. `MISTO` (apertou num eixo, afrouxou noutro) é revertido de
 * propósito: apertar metade e afrouxar a outra é exatamente o que o tamper-guard proíbe.
 *
 * ── FAIL-CLOSED ─────────────────────────────────────────────────────────────
 * Regenerador que falha, não existe, ou não escreve NADA vira `NAO_MEDIDO` — jamais
 * "sem folga". Colapsar não-medição em estado-do-objeto é a lápide §5 2026-07-29
 * (o watchdog que dizia "✓ todos os 24 crons ok" tendo medido zero). O relatório sempre
 * diz QUANTOS foram medidos, e o exit code separa os dois casos.
 *
 * Uso:
 *   node scripts/governance/baseline-folga.mjs --check      # mede e reporta (não escreve)
 *   node scripts/governance/baseline-folga.mjs --apply      # aperta os FOLGA (escreve)
 *   node scripts/governance/baseline-folga.mjs --json       # saída maquinal
 *   node scripts/governance/baseline-folga.mjs --selftest   # bite-test: morde e libera
 *
 * Exit: 0 = nada a apertar · 1 = há FOLGA (em --check) · 2 = NAO_MEDIDO (falha de medição).
 *
 * @see scripts/governance/baseline-tamper-guard.mjs  (o irmão — direção AFROUXAR)
 * @see memory/decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md
 */
import { readFileSync, writeFileSync, existsSync, mkdtempSync, mkdirSync, rmSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join, dirname } from 'node:path';
import { tmpdir } from 'node:os';

const ROOT = process.cwd();

/**
 * REGISTRO — baseline → comando que o regenera.
 *
 * Cada linha foi MEDIDA (lendo a flag no próprio script), nunca suposta. A flag difere
 * por script de propósito: quem escreveu cada um escolheu a sua, e uniformizar isso seria
 * mexer em 4 donos pra conveniência deste. Registro novo só entra com a flag lida no fonte.
 *
 * `nota` documenta ONDE a flag foi lida, pra a próxima sessão conferir sem re-descobrir.
 */
const REGISTRO = [
  {
    baseline: 'governance/deadlink-baseline.json',
    cmd: 'node scripts/governance/deadlink-gate.mjs --write-baseline',
    nota: 'flag lida em deadlink-gate.mjs:368 (lista de modes)',
  },
  {
    baseline: 'scripts/perf-static-baseline.json',
    cmd: 'node scripts/perf-static-guard.mjs --write-baseline',
    nota: 'flag lida em perf-static-guard.mjs:88',
  },
  {
    baseline: 'config/a11y-baseline.json',
    cmd: 'node scripts/a11y-ratchet.mjs --write',
    nota: 'flag lida em a11y-ratchet.mjs:52 — é --write, NAO --write-baseline',
  },
  {
    baseline: 'scripts/qa/cowork-pele-paralela.baseline.json',
    cmd: 'node scripts/qa/cowork-pele-paralela.mjs --update-baseline',
    nota: 'flag lida em cowork-pele-paralela.mjs:332',
  },
];

/** Campos de metadata que mudam a cada regeneração sem significar direção nenhuma. */
const METADATA = /^(generated_?(by|at)|timestamp|updated_?at|data|date|_meta|sha|commit|version)$/i;

function git(cmd, opts = {}) {
  try {
    return execSync(`git ${cmd}`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], cwd: opts.cwd || ROOT }).trim();
  } catch {
    return '';
  }
}

const parseJson = (txt) => { try { return JSON.parse(txt); } catch { return null; } };

/**
 * Achata um JSON em dois mapas comparáveis:
 *   nums    — { caminho: número }        (teto por chave)
 *   membros — Set de "caminho[]=valor"   (item de lista / chave-presença)
 * Metadata fica de fora. Estrutura desconhecida vira membro (presença), que é o
 * tratamento conservador: some = aperta, aparece = afrouxa.
 */
function achatar(valor, prefixo, nums, membros) {
  if (valor === null || valor === undefined) return;
  if (typeof valor === 'number') { nums.set(prefixo, valor); return; }
  if (typeof valor === 'string' || typeof valor === 'boolean') { membros.add(`${prefixo}=${valor}`); return; }
  if (Array.isArray(valor)) {
    for (const item of valor) {
      if (item === null || typeof item === 'object') achatar(item, `${prefixo}[]`, nums, membros);
      else membros.add(`${prefixo}[]=${String(item)}`);
    }
    return;
  }
  for (const [k, v] of Object.entries(valor)) {
    if (METADATA.test(k)) continue;
    achatar(v, prefixo ? `${prefixo}.${k}` : k, nums, membros);
  }
}

/**
 * Direção da mudança do baseline commitado → regenerado.
 * APERTOU = todo delta reduz teto ou remove item. AFROUXOU = algum delta relaxa.
 * MISTO = os dois ao mesmo tempo (revertido — ver docblock).
 */
export function direcao(commitado, novo) {
  const nA = new Map(), mA = new Set(), nB = new Map(), mB = new Set();
  achatar(commitado, '', nA, mA);
  achatar(novo, '', nB, mB);

  const aperta = [], afrouxa = [];

  for (const [k, vA] of nA) {
    if (!nB.has(k)) { aperta.push(`${k}: teto ${vA} saiu`); continue; }
    const vB = /** @type {number} */ (nB.get(k));
    if (vB < vA) aperta.push(`${k}: ${vA}→${vB}`);
    else if (vB > vA) afrouxa.push(`${k}: ${vA}→${vB}`);
  }
  for (const [k, vB] of nB) if (!nA.has(k)) afrouxa.push(`${k}: teto novo ${vB}`);

  for (const m of mA) if (!mB.has(m)) aperta.push(`item saiu: ${m}`);
  for (const m of mB) if (!mA.has(m)) afrouxa.push(`item novo: ${m}`);

  let veredito = 'IGUAL';
  if (aperta.length && afrouxa.length) veredito = 'MISTO';
  else if (aperta.length) veredito = 'APERTOU';
  else if (afrouxa.length) veredito = 'AFROUXOU';

  return { veredito, aperta, afrouxa };
}

/** Uma entrada do registro, medida de ponta a ponta. */
function medirUm(entrada, { apply, cwd = ROOT }) {
  const abs = join(cwd, entrada.baseline);
  const base = { ...entrada, aperta: [], afrouxa: [] };

  if (!existsSync(abs)) {
    return { ...base, estado: 'NAO_MEDIDO', motivo: 'baseline não existe no disco' };
  }

  // LC-23: nunca rodar por cima de trabalho não-commitado.
  const sujo = git(`status --porcelain -- "${entrada.baseline}"`, { cwd });
  if (sujo) {
    return { ...base, estado: 'NAO_MEDIDO', motivo: `baseline com mudança não-commitada (${sujo.slice(0, 40)}) — PULADO pra não destruir` };
  }

  const antes = readFileSync(abs, 'utf8');
  const commitado = parseJson(antes);
  if (commitado === null) {
    return { ...base, estado: 'NAO_MEDIDO', motivo: 'baseline commitado não é JSON válido' };
  }

  let rc = 0;
  try {
    execSync(entrada.cmd, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], cwd, timeout: 300000 });
  } catch (e) {
    rc = /** @type {any} */ (e).status ?? -1;
  }

  const depois = readFileSync(abs, 'utf8');
  const restaurar = () => { if (depois !== antes) writeFileSync(abs, antes); };

  if (depois === antes) {
    // O regenerador não escreveu. Se ele ainda por cima falhou, não sabemos se
    // "nada mudou" ou "nem rodou" — e afirmar o primeiro seria o fail-open da §5 2026-07-29.
    if (rc !== 0) return { ...base, estado: 'NAO_MEDIDO', motivo: `regenerador saiu rc=${rc} e não escreveu — não dá pra distinguir "sem folga" de "não rodou"` };
    return { ...base, estado: 'APERTADO', motivo: 'regenerador rodou e não mudou nada' };
  }

  const novo = parseJson(depois);
  if (novo === null) {
    restaurar();
    return { ...base, estado: 'NAO_MEDIDO', motivo: 'regenerador escreveu JSON inválido — revertido' };
  }

  const d = direcao(commitado, novo);

  if (d.veredito === 'APERTOU') {
    if (!apply) restaurar();
    return { ...base, estado: 'FOLGA', aperta: d.aperta, afrouxa: [], motivo: apply ? 'apertado' : 'folga medida (revertido — use --apply)' };
  }

  restaurar();
  if (d.veredito === 'AFROUXOU') {
    return { ...base, estado: 'AFROUXARIA', aperta: [], afrouxa: d.afrouxa, motivo: 'regenerar AFROUXARIA o baseline — revertido (isso é dívida nova, decisão humana)' };
  }
  if (d.veredito === 'MISTO') {
    return { ...base, estado: 'MISTO', aperta: d.aperta, afrouxa: d.afrouxa, motivo: 'aperta num eixo e afrouxa noutro — revertido' };
  }
  return { ...base, estado: 'APERTADO', motivo: 'só metadata mudou' };
}

// ── selftest ────────────────────────────────────────────────────────────────
function selftest() {
  let ok = 0, fail = 0;
  const t = (nome, cond) => { if (cond) { ok++; console.log(`  PASS ${nome}`); } else { fail++; console.log(`  FAIL ${nome}`); } };

  console.log('[baseline-folga --selftest]');

  // direção: o núcleo da decisão
  t('MORDE: teto que baixa = APERTOU', direcao({ a: 8 }, { a: 6 }).veredito === 'APERTOU');
  t('LIBERA: teto que sobe = AFROUXOU (nunca apertado)', direcao({ a: 6 }, { a: 8 }).veredito === 'AFROUXOU');
  t('MORDE: item que sai da lista = APERTOU', direcao({ v: ['x', 'y'] }, { v: ['x'] }).veredito === 'APERTOU');
  t('LIBERA: item novo na lista = AFROUXOU', direcao({ v: ['x'] }, { v: ['x', 'y'] }).veredito === 'AFROUXOU');
  t('MISTO nunca vira APERTOU', direcao({ a: 8, b: 2 }, { a: 6, b: 5 }).veredito === 'MISTO');
  t('metadata sozinha NÃO conta como direção', direcao({ generated_at: '1', a: 3 }, { generated_at: '2', a: 3 }).veredito === 'IGUAL');
  t('aninhado: teto que baixa dentro de objeto', direcao({ x: { y: 9 } }, { x: { y: 1 } }).veredito === 'APERTOU');

  // fail-closed de ponta a ponta, em sandbox
  const tmp = mkdtempSync(join(tmpdir(), 'bfolga-'));
  try {
    execSync('git init -q', { cwd: tmp, stdio: 'ignore' });
    execSync('git config user.email t@t && git config user.name t', { cwd: tmp, stdio: 'ignore', shell: true });
    mkdirSync(join(tmp, 'governance'), { recursive: true });

    const bl = 'governance/fake-baseline.json';
    writeFileSync(join(tmp, bl), JSON.stringify({ teto: 10 }, null, 2) + '\n');
    execSync('git add -A && git commit -qm base', { cwd: tmp, stdio: 'ignore', shell: true });

    // (a) regenerador que APERTA → FOLGA
    const apertador = `node -e "require('fs').writeFileSync('${bl}', JSON.stringify({teto:4}))"`;
    const rA = medirUm({ baseline: bl, cmd: apertador, nota: '' }, { apply: false, cwd: tmp });
    t('MORDE: regenerador que aperta vira FOLGA', rA.estado === 'FOLGA');
    t('e REVERTE sem --apply (não escreve escondido)', readFileSync(join(tmp, bl), 'utf8').includes('10'));

    // (b) regenerador que AFROUXA → nunca aplica
    const afrouxador = `node -e "require('fs').writeFileSync('${bl}', JSON.stringify({teto:99}))"`;
    const rB = medirUm({ baseline: bl, cmd: afrouxador, nota: '' }, { apply: true, cwd: tmp });
    t('LIBERA: regenerador que afrouxa NÃO é aplicado', rB.estado === 'AFROUXARIA');
    t('e o arquivo fica intacto mesmo com --apply', readFileSync(join(tmp, bl), 'utf8').includes('10'));

    // (c) regenerador quebrado → NAO_MEDIDO, jamais "sem folga"
    const quebrado = 'node -e "process.exit(3)"';
    const rC = medirUm({ baseline: bl, cmd: quebrado, nota: '' }, { apply: true, cwd: tmp });
    t('FAIL-CLOSED: regenerador quebrado = NAO_MEDIDO (nunca APERTADO)', rC.estado === 'NAO_MEDIDO');

    // (d) working tree sujo → PULA sem destruir
    writeFileSync(join(tmp, bl), JSON.stringify({ teto: 7, meu_trabalho: true }, null, 2));
    const rD = medirUm({ baseline: bl, cmd: apertador, nota: '' }, { apply: true, cwd: tmp });
    t('LC-23: baseline sujo = NAO_MEDIDO (pula)', rD.estado === 'NAO_MEDIDO');
    t('e o trabalho não-commitado SOBREVIVE', readFileSync(join(tmp, bl), 'utf8').includes('meu_trabalho'));
  } finally {
    try { rmSync(tmp, { recursive: true, force: true }); } catch { /* sandbox */ }
  }

  console.log(`\n[baseline-folga --selftest] ${ok} ok · ${fail} fail`);
  return fail === 0 ? 0 : 1;
}

// ── main ────────────────────────────────────────────────────────────────────
const argv = process.argv.slice(2);
if (argv.includes('--selftest')) process.exit(selftest());

const apply = argv.includes('--apply');
const asJson = argv.includes('--json');

const res = REGISTRO.map((e) => medirUm(e, { apply }));
const folga = res.filter((r) => r.estado === 'FOLGA');
const naoMedido = res.filter((r) => r.estado === 'NAO_MEDIDO');

if (asJson) {
  console.log(JSON.stringify({ medidos: res.length - naoMedido.length, total: res.length, folga: folga.length, naoMedido: naoMedido.length, res }, null, 2));
} else {
  console.log(`\n  BASELINE-FOLGA — ${res.length} baseline(s) no registro · ${res.length - naoMedido.length} MEDIDO(s) · ${folga.length} com folga · ${naoMedido.length} não medido(s)`);
  console.log(`  (direção medida no ARTEFATO regravado, nunca na mensagem do gate)\n`);
  for (const r of res) {
    const tag = { FOLGA: 'FOLGA     ', APERTADO: 'ok        ', NAO_MEDIDO: 'NAO MEDIDO', AFROUXARIA: 'AFROUXARIA', MISTO: 'MISTO     ' }[r.estado] || r.estado;
    console.log(`  ${tag} ${r.baseline}`);
    console.log(`             ${r.motivo}`);
    for (const a of r.aperta.slice(0, 4)) console.log(`             aperta: ${a}`);
    for (const a of r.afrouxa.slice(0, 4)) console.log(`             afrouxa: ${a}`);
  }
  if (folga.length && !apply) {
    console.log(`\n  → ${folga.length} ganho(s) esperando ser travado. Rode: node scripts/governance/baseline-folga.mjs --apply`);
  }
  if (apply && folga.length) {
    console.log(`\n  ✓ ${folga.length} baseline(s) APERTADO(s). Commite — o tamper-guard aprova apertar (ele só barra afrouxar).`);
  }
}

if (naoMedido.length) process.exit(2);
process.exit(folga.length && !apply ? 1 : 0);
