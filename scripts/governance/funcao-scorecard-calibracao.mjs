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
 * BLIND-POR-LABEL (rodada 6, 2026-07-21): o ID do twin (`t15-atomicidade-bad`, `t02-unscoped-find`)
 * NOMEAVA o veredito no cabeçalho do pack — leak de circularidade presente em TODAS as rodadas 2-5.
 * `--blind` emite rótulos OPACOS `L01..LNN` em ordem de HASH (sha256 do id) — some o tell do id E
 * a adjacência dos pares bom/ruim. O runner recomputa a mesma ordem determinística pra pontuar
 * (nenhum arquivo de mapa é gravado ao lado dos twins — a "resposta" não fica perto).
 *
 * Uso:
 *   node scripts/governance/funcao-scorecard-calibracao.mjs --pack [--blind] [--set frontier]
 *   node scripts/governance/funcao-scorecard-calibracao.mjs --score <verdicts.json> [--set frontier]
 *   node scripts/governance/funcao-scorecard-calibracao.mjs --kappa-inter <a.json> <b.json> [--set frontier]
 *   node scripts/governance/funcao-scorecard-calibracao.mjs --selftest
 *
 *   --score auto-detecta rótulo OPACO (`L\d+`) e traduz pro id real antes de pontuar.
 *
 * Refs: FUNCAO-SCORECARD-METODO §5 · grade 2026-07-21 · gate-selftest.mjs · sdd-verification-ledger.
 */
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { createHash } from 'node:crypto';

const ROOT = process.cwd();
const FIX = 'tests/governance-fixtures/funcao-scorecard';

/** Resolve as paths do "set" de fixture (default = raiz; `frontier` = subdir próprio). */
function paths(set) {
  const base = set && set !== 'twins' ? join(ROOT, FIX, set) : join(ROOT, FIX);
  return { twinsDir: join(base, 'twins'), manifest: join(base, 'manifesto-SELADO.json') };
}

/** Lê o selado do set. */
function selado(set) {
  return JSON.parse(readFileSync(paths(set).manifest, 'utf8')).twins;
}
/** Lê os twins (id → código) do set, sem os rótulos. */
function twins(set) {
  const out = {};
  const dir = paths(set).twinsDir;
  for (const f of readdirSync(dir).sort()) {
    if (f.endsWith('.php.txt')) out[f.replace('.php.txt', '')] = readFileSync(join(dir, f), 'utf8');
  }
  return out;
}

/**
 * Remove os "tells" em PROSA — comentários `//` e `/* ... *​/` (bloco NÃO-docblock) — para o juiz
 * cego julgar a ESTRUTURA + o CONTRATO, nunca um comentário que nomeia o veredito. Blindagem contra
 * a inflação de κ apontada pela revisão adversarial 2026-07-21 (o pack antigo emitia os comentários
 * verbatim → κ media "transcrever comentário", não "discriminar defeito").
 * SANITIZA docblocks: preserva só tags estruturadas de CONTRATO (`@return`, `@param`, `@transactional`,
 * `@covered-by`), removendo a narrativa que poderia entregar o veredito.
 * ⚠️ `@covered-by` é EVIDÊNCIA de golden, NÃO um tell: o critério C2 pergunta "tem prova?" — o juiz
 * PRECISA ver a cobertura pra distinguir t03 (golden → concordo) de t04 (sem golden → discordo).
 * Removê-lo faz um juiz FUTURO carimbar t03 (bom) como discordo (regressão #4644 corrigida 2026-07-21).
 * O nome do teste deve nomear a OPERAÇÃO (`RebateGoldenTest`), nunca o veredito.
 * Nota: heurística simples (não trata `//` dentro de string literal — os twins não têm nenhum).
 */
export function stripTells(code) {
  const semProsaEmDocblock = code.replace(/\/\*\*[\s\S]*?\*\//g, (block) => {
    const tags = block
      .split('\n')
      .map((line) => line.replace(/^\s*\/\*\*?\s?/, '').replace(/^\s*\*\s?/, '').replace(/\s*\*\/$/, '').trim())
      .filter((line) => /^@(param|return|throws|var|template|implements|extends|method|property|transactional|table|covered-by)\b/.test(line))
      .map((line) => line.replace(/^(@(?:param|return|throws|var|template|implements|extends|method|property|transactional|table|covered-by)\b\s+\S+).*/, '$1'));
    return tags.length ? `/**\n${tags.map((line) => ` * ${line}`).join('\n')}\n */` : '';
  });
  return semProsaEmDocblock
    .replace(/\/\*(?!\*)[\s\S]*?\*\//g, '') // /* ... */ inline, MAS não /** docblock */
    .replace(/\/\/[^\n]*/g, '')             // // até o fim da linha
    .split('\n')
    .map((l) => l.replace(/[ \t]+$/, ''))
    .filter((l, i, a) => !(l.trim() === '' && (i === 0 || (a[i - 1] || '').trim() === '')))
    .join('\n')
    .trimEnd();
}

/**
 * Ordem CEGA determinística: os ids em ordem de sha256(id) → rótulos opacos L01..LNN.
 * A ordem por hash NÃO carrega informação de veredito (some o tell do id) E embaralha os pares
 * bom/ruim (some a adjacência t15/t16). Determinística ⇒ o `--score` recomputa igual, sem
 * precisar de arquivo de mapa gravado perto dos twins.
 * @returns {{label:string, id:string}[]}
 */
export function blindOrder(ids) {
  const h = (s) => createHash('sha256').update(s).digest('hex');
  const ordered = [...ids].sort((a, b) => (h(a) < h(b) ? -1 : h(a) > h(b) ? 1 : 0));
  return ordered.map((id, i) => ({ label: `L${String(i + 1).padStart(2, '0')}`, id }));
}

/** Se os verdicts vierem com rótulo opaco (`L\d+`), traduz pros ids reais do set. Idempotente. */
export function translateBlind(verdicts, ids) {
  const keys = Object.keys(verdicts);
  if (!keys.some((k) => /^L\d+$/.test(k))) return verdicts; // já são ids reais
  const map = Object.fromEntries(blindOrder(ids).map(({ label, id }) => [label, id]));
  const out = {};
  for (const [k, v] of Object.entries(verdicts)) out[map[k] ?? k] = v;
  return out;
}

/** Emite o PACK: código SEM tells em prosa + instrução, SEM rótulo de veredito. `blind` ⇒ labels opacos. */
export function pack(set = 'twins', blind = false) {
  const t = twins(set);
  const order = blind ? blindOrder(Object.keys(t)) : Object.keys(t).map((id) => ({ label: id, id }));
  const L = [];
  L.push('# PACK CEGO — calibração do juiz funcao-scorecard');
  if (blind) L.push('> Rótulos OPACOS (`L01..`): o id do caso NÃO nomeia o defeito (blind-por-label, rodada 6). Julgue só pelo código.');
  L.push('> Comentários e narrativa dos docblocks foram REMOVIDOS de propósito (stripTells): o juiz discrimina pela ESTRUTURA + tags contratuais mínimas (`@return`, `@param`, `@transactional`), não por texto que nomeie a resposta.');
  L.push('Julgue CADA função abaixo pelos critérios (FUNCAO-SCORECARD-METODO §1): C1 multi-tenant · C2 valor/estoque · C3 dado-ausente (o CONSUMIDOR distingue ausente de presente? pega sentinela ""/false/0) · C4 atomicidade · C5 N+1 · C6 SQL cru · C7a docblock/tipo declarado bate com o retorno REAL não-null · C7b retorno polimórfico ambíguo (false|array|string em caminhos diferentes) · C7c nullabilidade TIPADA (null só sob ?T / @return T|null declarado; ?T tipado é OK — NÃO carimbe) · C7d falha observável (sem catch vazio / erro engolido) · C8 cobertura. Veredito por critério ∈ {concordo, discordo, incerto, n/a} + citação. `incerto` OBRIGATÓRIO quando falta intenção externa. NÃO invente que algo é "defeito plantado".');
  L.push(`Devolva JSON: { "<label>": { "C1": "<v>", "C2": "<v>", ... }, ... } — labels EXATOS abaixo (${blind ? 'L01..' : 'id'}), só os critérios que se aplicam + o saliente.`);
  L.push('');
  for (const { label, id } of order) {
    L.push(`## ${label}`);
    L.push('```php');
    L.push(stripTells(t[id]));
    L.push('```');
    L.push('');
  }
  return L.join('\n');
}

/** Cohen's κ entre esperado e observado (categorias concordo/discordo/incerto). Corrige o acaso. */
export function cohenKappa(pares) {
  const cats = ['concordo', 'discordo', 'incerto', 'n/a'];
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
  let overflagControle = 0, falsoDiscordoBom = 0;
  let incertoTotal = 0, incertoAchado = 0; // suporta N twins incerto (frontier), não só 1
  const paresK = []; // (esperado, observado) do critério saliente, p/ κ — só vereditos definidos
  for (const [id, lab] of Object.entries(sel)) {
    if (String(id).startsWith('_')) continue; // ignora chaves de meta/comentário
    if (lab.retired) continue; // twin aposentado (mislabel/circular exposto pela rodada 6) — fora das MÉTRICAS, mantido na ORDEM cega (labels estáveis)
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
      incertoTotal++;
      const ok = dado === 'incerto';
      if (ok) incertoAchado++;
      linhas.push(`${ok ? 'OK ' : '✗  '} ${id} [incerto] ${lab.criterio_salient}: esperado incerto, juiz=${dado ?? '(ausente)'}`);
    } else if (lab.veredito === 'concordo') {
      const ok = dado === 'concordo';
      if (!ok && dado === 'discordo') falsoDiscordoBom++;
      linhas.push(`${ok ? 'OK ' : '✗  '} ${id} [bom] ${lab.criterio_salient}: esperado concordo, juiz=${dado ?? '(ausente)'}`);
    } else if (lab.veredito === 'n/a') {
      // isca de FALSO-POSITIVO: o critério NÃO se aplica (n/a) — o defeito é o juiz CARIMBAR discordo.
      // n/a OU concordo = não-violação aceitável; só discordo é erro.
      const err = dado === 'discordo';
      if (err) falsoDiscordoBom++;
      linhas.push(`${err ? '✗  ' : 'OK '} ${id} [na-bait] ${lab.criterio_salient}: esperado n/a (ou concordo), juiz=${dado ?? '(ausente)'}`);
    }
  }
  const kappa = cohenKappa(paresK);
  const incertoOk = incertoTotal === 0 ? null : incertoAchado === incertoTotal;
  const minFamilias = Math.ceil(familiasTotal * 0.8); // ≥80% das famílias (com os twins difíceis)
  // incertoOk !== false: null (nenhum twin incerto no set — ex.: main pós-aposentadoria do t08) conta como satisfeito;
  // só FALSO (algum incerto errado) reprova. O incerto-de-INTENÇÃO migrou pro braço gold humano (#4626).
  const pass = familiasAchadas >= minFamilias && overflagControle === 0 && incertoOk !== false
    && falsoDiscordoBom === 0 && (kappa ?? 0) >= 0.6;
  return {
    pass,
    familiasAchadas, familiasTotal, minFamilias, overflagControle, incertoOk, incertoTotal, incertoAchado, falsoDiscordoBom, kappa,
    detalhe: linhas.join('\n'),
  };
}

/**
 * κ INTER-FAMÍLIA (gap #2 da grade): concordância entre DOIS juízes (famílias de modelo diferentes)
 * no critério SALIENTE de cada twin. Corrige o acaso. É diferente do κ-vs-selado do `pontuar`
 * (aquele mede o juiz vs o rótulo objetivo; este mede juiz-A vs juiz-B — "eles concordam ENTRE SI,
 * não só com o gabarito?"). κ alto entre famílias distintas refuta "concordou porque é o mesmo modelo".
 */
export function kappaInter(vA, vB, set) {
  const sel = selado(set);
  const ids = Object.keys(twins(set));
  const a = translateBlind(vA, ids), b = translateBlind(vB, ids);
  const pares = [], linhas = [];
  for (const [id, lab] of Object.entries(sel)) {
    if (String(id).startsWith('_')) continue;
    if (lab.retired) continue; // fora do κ inter-família também
    const c = lab.criterio_salient;
    if (c === 'controle') continue; // controle não tem veredito saliente único
    const va = (a[id] || {})[c], vb = (b[id] || {})[c];
    if (['concordo', 'discordo', 'incerto'].includes(va) && ['concordo', 'discordo', 'incerto'].includes(vb)) {
      pares.push([va, vb]);
      if (va !== vb) linhas.push(`  ≠ ${id} ${c}: A=${va} B=${vb}`);
    }
  }
  const k = cohenKappa(pares);
  const acordo = pares.filter(([x, y]) => x === y).length;
  return { kappa: k, n: pares.length, acordo, pctAcordo: pares.length ? Math.round((acordo / pares.length) * 1000) / 10 : null, divergencias: linhas };
}

/** --selftest: prova que o RUNNER morde. Juiz-perfeito PASSA; juiz-carimbo (discorda de tudo) FALHA. */
function selftest() {
  const sel = selado();
  const perfeito = {}, carimbo = {};
  for (const [id, lab] of Object.entries(sel)) {
    if (String(id).startsWith('_')) continue;
    const c = lab.criterio_salient;
    perfeito[id] = { [c]: lab.veredito === 'sem-discordo' ? 'concordo' : lab.veredito };
    if (lab.veredito === 'sem-discordo') perfeito[id] = { C1: 'concordo', C7a: 'concordo' };
    carimbo[id] = { [c]: 'discordo' }; // carimba discordo em tudo (o modo de falha)
  }
  const p = pontuar(perfeito, sel);
  const k = pontuar(carimbo, sel);
  // prova extra (rodada 6): o juiz-perfeito CEGO (verdicts em rótulo opaco L\d+) traduz e PASSA igual.
  const ids = Object.keys(twins());
  const cego = {};
  for (const { label, id } of blindOrder(ids)) cego[label] = perfeito[id];
  const pc = pontuar(translateBlind(cego, ids), sel);
  const ok = p.pass === true && k.pass === false && pc.pass === true;
  console.log(`[selftest] juiz-perfeito         → pass=${p.pass} (esperado true)`);
  console.log(`[selftest] juiz-carimbo          → pass=${k.pass} (esperado false; overflag=${k.overflagControle}, incertoOk=${k.incertoOk})`);
  console.log(`[selftest] juiz-perfeito CEGO(L) → pass=${pc.pass} (esperado true — translateBlind fecha)`);
  console.log(ok ? '[selftest] OK — o runner morde e libera certo.' : '[selftest] ✗ FALHOU — o runner não discrimina.');
  return ok;
}

// ── main ──
const args = process.argv.slice(2);
function flagVal(name) { const i = args.indexOf(name); return i >= 0 ? args[i + 1] : null; }
function main() {
  const set = flagVal('--set') || 'twins';
  if (args.includes('--pack')) { console.log(pack(set, args.includes('--blind'))); return; }
  if (args.includes('--selftest')) { process.exit(selftest() ? 0 : 1); }
  const ki = args.indexOf('--kappa-inter');
  if (ki >= 0 && args[ki + 1] && args[ki + 2]) {
    const [fa, fb] = [args[ki + 1], args[ki + 2]];
    for (const f of [fa, fb]) if (!existsSync(f)) { console.error(`arquivo não encontrado: ${f}`); process.exit(2); }
    const r = kappaInter(JSON.parse(readFileSync(fa, 'utf8')), JSON.parse(readFileSync(fb, 'utf8')), set);
    if (r.divergencias.length) console.log(r.divergencias.join('\n'));
    console.log(`\n=> κ inter-família ${r.kappa} · acordo ${r.acordo}/${r.n} (${r.pctAcordo}%)`);
    process.exit(0);
  }
  const si = args.indexOf('--score');
  if (si >= 0 && args[si + 1]) {
    const vf = args[si + 1];
    if (!existsSync(vf)) { console.error(`arquivo de vereditos não encontrado: ${vf}`); process.exit(2); }
    const raw = JSON.parse(readFileSync(vf, 'utf8'));
    const r = pontuar(translateBlind(raw, Object.keys(twins(set))), selado(set));
    console.log(r.detalhe);
    console.log(`\n=> famílias ${r.familiasAchadas}/${r.familiasTotal} (min ${r.minFamilias}) · κ ${r.kappa} (min 0.6) · overflag-controle ${r.overflagControle} · incerto ${r.incertoOk} · falso-discordo-bom ${r.falsoDiscordoBom}`);
    console.log(r.pass ? '\n✅ JUIZ CALIBRADO (cego, não-circular).' : '\n❌ JUIZ NÃO calibrado — ver linhas acima.');
    process.exit(r.pass ? 0 : 1);
  }
  console.error('Uso: --pack [--blind] [--set frontier] | --score <verdicts.json> [--set] | --kappa-inter <a> <b> [--set] | --selftest');
  process.exit(2);
}
if (import.meta.url === pathToFileURL(process.argv[1] || '').href) main();
