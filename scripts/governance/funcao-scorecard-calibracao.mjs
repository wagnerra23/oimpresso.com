#!/usr/bin/env node
// @ts-check
/**
 * funcao-scorecard-calibracao.mjs — calibração NÃO-CIRCULAR do juiz funcao-scorecard.
 *
 * POR QUE (o gargalo da grade 2026-07-21, dimensão validação-não-circular = 4/10): o bite-test
 * de 2026-07-21 foi INVALIDADO por circular (chamou código de produção de "defeito plantado",
 * pré-declarou o veredito, e o juiz tinha a resposta no contexto). Esta fixture fecha isso do
 * jeito que o §5 do FUNCAO-SCORECARD-METODO já especifica: twins SINTÉTICOS (código fabricado,
 * não o repo real → o juiz não sabe a resposta do contexto), rótulo = a MUTAÇÃO (objetivo,
 * label_source: mutation), gabarito SELADO, juiz CEGO.
 *
 * INTEGRA (não reinventa): padrão corruptor .php.txt (tests/governance-fixtures/), contrato
 * bom/ruim do gate-selftest (--selftest morde/libera), regra _quem_monta_nao_exibe do ledger.
 *
 * Uso:
 *   node scripts/governance/funcao-scorecard-calibracao.mjs --pack       (emite o PACK CEGO p/ o juiz — SEM rótulos)
 *   node scripts/governance/funcao-scorecard-calibracao.mjs --score <verdicts.json>  (pontua o juiz vs o selado)
 *   node scripts/governance/funcao-scorecard-calibracao.mjs --selftest   (o runner morde: juiz-perfeito PASSA, juiz-carimbo FALHA)
 *
 * Refs: FUNCAO-SCORECARD-METODO §5 · grade 2026-07-21 · gate-selftest.mjs · sdd-verification-ledger.
 */
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const ROOT = process.cwd();
const FIX = 'tests/governance-fixtures/funcao-scorecard';
const TWINS_DIR = join(ROOT, FIX, 'twins');
const MANIFEST = join(ROOT, FIX, 'manifesto-SELADO.json');

/** Lê o selado. */
function selado() {
  return JSON.parse(readFileSync(MANIFEST, 'utf8')).twins;
}
/** Lê os twins (id → código), sem os rótulos. */
function twins() {
  const out = {};
  for (const f of readdirSync(TWINS_DIR).sort()) {
    if (f.endsWith('.php.txt')) out[f.replace('.php.txt', '')] = readFileSync(join(TWINS_DIR, f), 'utf8');
  }
  return out;
}

/**
 * Remove os "tells" em PROSA — comentários `//` e `/* ... *​/` (bloco NÃO-docblock) — para o juiz
 * cego julgar a ESTRUTURA + o CONTRATO, nunca um comentário que nomeia o veredito. Blindagem contra
 * a inflação de κ apontada pela revisão adversarial 2026-07-21 (o pack antigo emitia os comentários
 * verbatim → κ media "transcrever comentário", não "discriminar defeito").
 * PRESERVA docblocks `/** ... *​/` DE PROPÓSITO: eles são contrato/schema-fact que um juiz real teria
 * do código/migração/SPEC (`@return`, `@covered-by`, "X é tabela tenant-owned"), não um tell do veredito.
 * Nota: heurística simples (não trata `//` dentro de string literal — os twins não têm nenhum).
 */
export function stripTells(code) {
  return code
    .replace(/\/\*(?!\*)[\s\S]*?\*\//g, '') // /* ... */ inline, MAS não /** docblock */
    .replace(/\/\/[^\n]*/g, '')             // // até o fim da linha
    .split('\n')
    .map((l) => l.replace(/[ \t]+$/, ''))
    .filter((l, i, a) => !(l.trim() === '' && (i === 0 || (a[i - 1] || '').trim() === '')))
    .join('\n')
    .trimEnd();
}

/** Emite o PACK CEGO: código SEM tells em prosa + instrução, SEM nenhum rótulo (o juiz nunca vê o selado). */
function pack() {
  const t = twins();
  const L = [];
  L.push('# PACK CEGO — calibração do juiz funcao-scorecard');
  L.push('> Comentários em prosa (`//`) foram REMOVIDOS de propósito (stripTells): o juiz discrimina pela ESTRUTURA + docblocks de contrato (`/** @return, @covered-by */`), não por comentário que nomeie a resposta.');
  L.push('Julgue CADA função abaixo pelos critérios (FUNCAO-SCORECARD-METODO §1): C1 multi-tenant · C2 valor/estoque · C3 dado-ausente (o CONSUMIDOR distingue ausente de presente? pega sentinela ""/false/0) · C4 atomicidade · C5 N+1 · C6 SQL cru · C7a docblock/tipo declarado bate com o retorno REAL não-null · C7b retorno polimórfico ambíguo (false|array|string em caminhos diferentes) · C7c nullabilidade TIPADA (null só sob ?T / @return T|null declarado; ?T tipado é OK — NÃO carimbe) · C7d falha observável (sem catch vazio / erro engolido) · C8 cobertura. Veredito por critério ∈ {concordo, discordo, incerto, n/a} + citação. `incerto` OBRIGATÓRIO quando falta intenção externa. NÃO invente que algo é "defeito plantado".');
  L.push('Devolva JSON: { "<id>": { "C1": "<v>", "C2": "<v>", ... }, ... } — só os critérios que se aplicam + o saliente.');
  L.push('');
  for (const [id, code] of Object.entries(t)) {
    L.push(`## ${id}`);
    L.push('```php');
    L.push(stripTells(code));
    L.push('```');
    L.push('');
  }
  return L.join('\n');
}

/** Cohen's κ entre esperado e observado (categorias concordo/discordo/incerto). Corrige o acaso. */
export function cohenKappa(pares) {
  const cats = ['concordo', 'discordo', 'incerto'];
  const n = pares.length;
  if (n === 0) return null;
  let acordo = 0;
  const eE = {}, eO = {};
  for (const c of cats) { eE[c] = 0; eO[c] = 0; }
  for (const [esp, obs] of pares) {
    if (esp === obs) acordo++;
    if (cats.includes(esp)) eE[esp]++;
    if (cats.includes(obs)) eO[obs]++;
  }
  const po = acordo / n;
  let pe = 0;
  for (const c of cats) pe += (eE[c] / n) * (eO[c] / n);
  return pe === 1 ? 1 : Math.round(((po - pe) / (1 - pe)) * 1000) / 1000;
}

/** Pontua os vereditos do juiz cego vs o selado. Retorna {pass, detalhe}. */
export function pontuar(verdicts, sel = selado()) {
  const linhas = [];
  let familiasAchadas = 0, familiasTotal = 0;
  let overflagControle = 0, incertoOk = null, falsoDiscordoBom = 0;
  const paresK = []; // (esperado, observado) do critério saliente, p/ κ — só vereditos definidos
  for (const [id, lab] of Object.entries(sel)) {
    if (String(id).startsWith('_')) continue; // ignora chaves de meta/comentário
    // coleta par p/ κ quando o esperado é um veredito definido (não o controle "sem-discordo")
    if (['concordo', 'discordo', 'incerto'].includes(lab.veredito)) {
      paresK.push([lab.veredito, (verdicts[id] || {})[lab.criterio_salient]]);
    }
    const v = (verdicts[id] || {});
    const dado = v[lab.criterio_salient];
    if (lab.familia) {
      familiasTotal++;
      const ok = dado === 'discordo';
      if (ok) familiasAchadas++;
      linhas.push(`${ok ? 'OK ' : '✗  '} ${id} [${lab.familia}] ${lab.criterio_salient}: esperado discordo, juiz=${dado ?? '(ausente)'}`);
    } else if (lab.veredito === 'sem-discordo') {
      // controle limpo: NENHUM discordo em nenhum critério.
      const discs = Object.entries(v).filter(([, x]) => x === 'discordo').map(([c]) => c);
      overflagControle = discs.length;
      linhas.push(`${discs.length === 0 ? 'OK ' : '✗  '} ${id} [controle] discordos=${discs.length ? discs.join(',') : '0'} (esperado 0)`);
    } else if (lab.veredito === 'incerto') {
      incertoOk = dado === 'incerto';
      linhas.push(`${incertoOk ? 'OK ' : '✗  '} ${id} [incerto] ${lab.criterio_salient}: esperado incerto, juiz=${dado ?? '(ausente)'}`);
    } else if (lab.veredito === 'concordo') {
      const ok = dado === 'concordo';
      if (!ok && dado === 'discordo') falsoDiscordoBom++;
      linhas.push(`${ok ? 'OK ' : '✗  '} ${id} [bom] ${lab.criterio_salient}: esperado concordo, juiz=${dado ?? '(ausente)'}`);
    }
  }
  const kappa = cohenKappa(paresK);
  const minFamilias = Math.ceil(familiasTotal * 0.8); // ≥80% das famílias (com os twins difíceis)
  const pass = familiasAchadas >= minFamilias && overflagControle === 0 && incertoOk === true
    && falsoDiscordoBom === 0 && (kappa ?? 0) >= 0.6;
  return {
    pass,
    familiasAchadas, familiasTotal, minFamilias, overflagControle, incertoOk, falsoDiscordoBom, kappa,
    detalhe: linhas.join('\n'),
  };
}

/** --selftest: prova que o RUNNER morde. Juiz-perfeito PASSA; juiz-carimbo (discorda de tudo) FALHA. */
function selftest() {
  const sel = selado();
  const perfeito = {}, carimbo = {};
  for (const [id, lab] of Object.entries(sel)) {
    const c = lab.criterio_salient;
    perfeito[id] = { [c]: lab.veredito === 'sem-discordo' ? 'concordo' : lab.veredito };
    if (lab.veredito === 'sem-discordo') perfeito[id] = { C1: 'concordo', C7a: 'concordo' };
    carimbo[id] = { [c]: 'discordo' }; // carimba discordo em tudo (o modo de falha)
  }
  const p = pontuar(perfeito, sel);
  const k = pontuar(carimbo, sel);
  const ok = p.pass === true && k.pass === false;
  console.log(`[selftest] juiz-perfeito → pass=${p.pass} (esperado true)`);
  console.log(`[selftest] juiz-carimbo  → pass=${k.pass} (esperado false; overflag=${k.overflagControle}, incertoOk=${k.incertoOk})`);
  console.log(ok ? '[selftest] OK — o runner morde e libera certo.' : '[selftest] ✗ FALHOU — o runner não discrimina.');
  return ok;
}

// ── main ──
const args = process.argv.slice(2);
function main() {
  if (args.includes('--pack')) { console.log(pack()); return; }
  if (args.includes('--selftest')) { process.exit(selftest() ? 0 : 1); }
  const si = args.indexOf('--score');
  if (si >= 0 && args[si + 1]) {
    const vf = args[si + 1];
    if (!existsSync(vf)) { console.error(`arquivo de vereditos não encontrado: ${vf}`); process.exit(2); }
    const r = pontuar(JSON.parse(readFileSync(vf, 'utf8')));
    console.log(r.detalhe);
    console.log(`\n=> famílias ${r.familiasAchadas}/${r.familiasTotal} (min ${r.minFamilias}) · κ ${r.kappa} (min 0.6) · overflag-controle ${r.overflagControle} · incerto ${r.incertoOk} · falso-discordo-bom ${r.falsoDiscordoBom}`);
    console.log(r.pass ? '\n✅ JUIZ CALIBRADO (cego, não-circular).' : '\n❌ JUIZ NÃO calibrado — ver linhas acima.');
    process.exit(r.pass ? 0 : 1);
  }
  console.error('Uso: --pack | --score <verdicts.json> | --selftest');
  process.exit(2);
}
if (import.meta.url === pathToFileURL(process.argv[1] || '').href) main();
