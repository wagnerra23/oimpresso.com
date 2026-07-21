#!/usr/bin/env node
// @ts-check
/**
 * funcao-scorecard-outcome-probe.mjs — PROTÓTIPO de validação-por-OUTCOME do funcao-scorecard.
 *
 * ⚠️ PROTÓTIPO DE MEDIÇÃO — advisory, read-only, NÃO é gate (não bloqueia nada). Serve à
 *    ADR-proposta 2026-07-21 "outcome-log do funcao-scorecard" (barra = CodeScene Code Health).
 *
 * A PERGUNTA (traduzindo a PREMISSA do CodeScene, não copiando a solução):
 *   o funcao-scorecard hoje prova que o JUIZ discrimina defeito mecânico (fixture sintética,
 *   κ=1,0) — mas NÃO que o veredito numa função REAL correlaciona com aquela função de fato
 *   gerar incidente. Este probe faz o spot-check RETROSPECTIVO: cruza a contagem de `discordo`
 *   por função (sinal de NOTA) com os defeitos REAIS que materializaram naquela função (sinal
 *   de OUTCOME), medindo se função mal-avaliada de fato gerou mais incidente.
 *
 * FONTE DE NOTA (parseada, não de memória):
 *   memory/governance/scorecards/funcoes/app-utils-productutil.yaml — vereditos C1..C8 por função.
 *
 * FONTE DE OUTCOME (3 sinais OBJETIVOS + AUDITÁVEIS, não curados à mão — anti-LC-08):
 *   (F) fix-commits: git log de ProductUtil.php, subject ~ /fix|incidente|hotfix/i (excl. restaura/squash/merge),
 *       hunk LOCALIZADO mapeado à função pelo intervalo de linhas.
 *   (T) incident-tests: arquivos em tests/ marcados como guard de regressão (docblock ~ /incidente|REGRESSÃO|GUARD MECANIZADO/i)
 *       que NOMEIAM a função no cabeçalho (nome da classe / @see / causa) — não simples uso como helper.
 *   (L) ledger §5: proibicoes.md região §5 ("Ideias avaliadas e DESCARTADAS") nomeando a função.
 *   Uma função é MATERIALIZED se aparece em ≥1 dos 3. Tudo reproduzível: re-rode e confira.
 *
 * LIMITES HONESTOS (declarados, não escondidos — a razão de ser da proposta):
 *   - O repo aqui é SHALLOW (git rev-parse --is-shallow-repository = true): o sinal (F) só vê a
 *     janela de história do clone, NÃO a história real de defeito. Por isso (F) é fraco e o probe
 *     não depende dele — é justamente o argumento pró-outcome-log persistente (vs arqueologia git).
 *   - N_materialized é minúsculo (a nota cobre 1 arquivo, atribuída HOJE): isto é SPOT-CHECK, não
 *     significância. O CodeScene teve 30.737 arquivos × anos de Jira; nós temos 37 funções × 1 dia.
 *     O número aqui NÃO reproduz o r=−0,58 deles — mede o que dá pra medir HOJE + fixa o método.
 *   - CONFOUNDER (o mesmo do Code Red): tamanho/fan-in. O probe imprime fan-in (C8) e testes
 *     diretos ao lado, pra o leitor ver se a correlação não é só "função grande apanha mais".
 *
 * Uso:  node scripts/governance/funcao-scorecard-outcome-probe.mjs [--json]
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { execSync } from 'node:child_process';

const ROOT = process.cwd();
const SCORECARD = 'memory/governance/scorecards/funcoes/app-utils-productutil.yaml';
const TARGET_FILE = 'app/Utils/ProductUtil.php';
const PROIBICOES = 'memory/proibicoes.md';
const TESTS_DIR = 'tests';

// ─────────────────────────────────────────────────────────────────────────────
// 1) SINAL DE NOTA — parse do scorecard YAML (contagem de vereditos por função).
// ─────────────────────────────────────────────────────────────────────────────
/** @returns {Array<{fn:string, line:number, discordo:number, incerto:number, concordo:number, na:number, fanIn:number|null, testesDiretos:number|null}>} */
function parseScorecard() {
  const txt = readFileSync(join(ROOT, SCORECARD), 'utf8');
  // quebra em blocos por "- function: NOME"
  const blocks = txt.split(/^\s+- function:\s*/m).slice(1);
  const fns = [];
  for (const b of blocks) {
    const fn = (b.match(/^([A-Za-z_][A-Za-z0-9_]*)/) || [])[1];
    if (!fn) continue;
    const line = parseInt((b.match(/^\s*line:\s*(\d+)/m) || [])[1] || '0', 10);
    // conta status por critério (C1..C7); C8 não tem status (é contagem)
    const count = (re) => (b.match(re) || []).length;
    const discordo = count(/status:\s*discordo/g);
    const incerto = count(/status:\s*incerto/g);
    const concordo = count(/status:\s*concordo/g);
    const na = count(/status:\s*n\/a/g) + count(/status:\s*na\b/g);
    // fan-in (C8 consumidores) + testes diretos
    const fanIn = (() => { const m = b.match(/consumidores:\s*(\d+)/); return m ? parseInt(m[1], 10) : null; })();
    const testesDiretos = (() => { const m = b.match(/testes_diretos:\s*(\d+)/) || b.match(/testes:\s*(\d+)/); return m ? parseInt(m[1], 10) : null; })();
    fns.push({ fn, line, discordo, incerto, concordo, na, fanIn, testesDiretos });
  }
  // ordena por linha pra derivar o intervalo [line, próximaLine) de cada função
  fns.sort((a, b) => a.line - b.line);
  for (let i = 0; i < fns.length; i++) {
    fns[i].lineEnd = i + 1 < fns.length ? fns[i + 1].line - 1 : Number.MAX_SAFE_INTEGER;
  }
  return fns;
}

// ─────────────────────────────────────────────────────────────────────────────
// 2) SINAL DE OUTCOME — 3 fontes objetivas.
// ─────────────────────────────────────────────────────────────────────────────
function git(cmd) {
  try { return execSync(`git ${cmd}`, { cwd: ROOT, encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] }); }
  catch { return ''; }
}

/** (F) fix-commits localizados em ProductUtil.php, mapeados à função pelo intervalo de linhas. */
function fixCommits(fns) {
  const shallow = git('rev-parse --is-shallow-repository').trim() === 'true';
  const log = git(`log --format=%H%x09%s -- ${TARGET_FILE}`).trim().split('\n').filter(Boolean);
  const hits = {}; // fn -> [{hash, subject}]
  const notas = [];
  for (const linha of log) {
    const [hash, subject] = linha.split('\t');
    if (!/\b(fix|incidente|hotfix|corrige)\b/i.test(subject)) continue;
    if (/restaura|squash|revert|merge/i.test(subject)) continue; // exclui commits de massa/recovery
    // pega os números de linha alterados no diff DESTE commit em ProductUtil.php
    const diff = git(`show ${hash} --unified=0 --format= -- ${TARGET_FILE}`);
    const changedLines = [];
    for (const m of diff.matchAll(/^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/gm)) {
      const start = parseInt(m[1], 10); const len = m[2] ? parseInt(m[2], 10) : 1;
      for (let l = start; l < start + Math.max(len, 1); l++) changedLines.push(l);
    }
    // hunk gigante (rewrite do arquivo) = não é fix localizado → ignora
    if (changedLines.length > 60) { notas.push(`(ignorado hunk grande ${changedLines.length} linhas: ${hash.slice(0, 9)} ${subject.slice(0, 50)})`); continue; }
    for (const f of fns) {
      if (changedLines.some((l) => l >= f.line && l <= f.lineEnd)) {
        (hits[f.fn] = hits[f.fn] || []).push({ hash: hash.slice(0, 9), subject });
      }
    }
  }
  return { hits, shallow, notas };
}

/** (G) guard-tests: função é SUJEITO de teste guard/golden — via referência QUALIFICADA
 *  `ProductUtil::<fn>` no docblock (precisa: pega o método sob teste, NÃO helper de setup usado
 *  no corpo). NÃO é "materialized" (defeito real que aconteceu) — é "há guard sobre o risco".
 *  Reportado SEPARADO do materialized (F∪L). Residual honesto: subject-vs-helper de prosa é
 *  heurística (mesma classe de fragilidade do allowlist §5 2026-06-30); por isso guard≠materialized. */
function guardTests(fns) {
  const names = new Set(fns.map((f) => f.fn));
  const hits = {}; // fn -> [file]
  const walk = (dir) => {
    for (const e of readdirSync(join(ROOT, dir))) {
      const p = join(dir, e);
      const abs = join(ROOT, p);
      if (statSync(abs).isDirectory()) { walk(p); continue; }
      if (!/Test\.php$/.test(e)) continue;
      const txt = readFileSync(abs, 'utf8');
      const doc = (txt.match(/\/\*\*[\s\S]*?\*\//) || [''])[0]; // 1º docblock só
      for (const m of doc.matchAll(/ProductUtil::([A-Za-z_][A-Za-z0-9_]*)/g)) {
        if (names.has(m[1])) (hits[m[1]] = hits[m[1]] || []).push(p.replace(/\\/g, '/'));
      }
    }
  };
  walk(TESTS_DIR);
  return hits;
}

/** (L) ledger §5 do proibicoes.md nomeando a função. */
function ledgerRefs(fns) {
  const txt = readFileSync(join(ROOT, PROIBICOES), 'utf8');
  const i = txt.indexOf('Ideias avaliadas e DESCARTADAS');
  const sec5 = i >= 0 ? txt.slice(i) : txt;
  const hits = {};
  for (const f of fns) {
    // \b não casa bem com _; usa lookaround por não-identificador
    const re = new RegExp(`(?<![A-Za-z0-9_])${f.fn}(?![A-Za-z0-9_])`, 'g');
    const n = (sec5.match(re) || []).length;
    if (n > 0) hits[f.fn] = n;
  }
  return hits;
}

// ─────────────────────────────────────────────────────────────────────────────
// 3) ESTATÍSTICA — correlação NOTA↔OUTCOME + 2×2 + confounder à vista.
// ─────────────────────────────────────────────────────────────────────────────
/** Correlação de Pearson (usada como point-biserial quando y é 0/1). */
export function pearson(xs, ys) {
  const n = xs.length; if (n === 0) return null;
  const mx = xs.reduce((a, b) => a + b, 0) / n, my = ys.reduce((a, b) => a + b, 0) / n;
  let num = 0, dx = 0, dy = 0;
  for (let i = 0; i < n; i++) { const a = xs[i] - mx, b = ys[i] - my; num += a * b; dx += a * a; dy += b * b; }
  return (dx === 0 || dy === 0) ? null : Math.round((num / Math.sqrt(dx * dy)) * 1000) / 1000;
}
/** Spearman ρ = Pearson dos ranks (ties → rank médio). */
export function spearman(xs, ys) {
  const rank = (v) => { const s = [...v].map((x, i) => [x, i]).sort((a, b) => a[0] - b[0]); const r = new Array(v.length);
    let k = 0; while (k < s.length) { let j = k; while (j + 1 < s.length && s[j + 1][0] === s[k][0]) j++; const avg = (k + j) / 2 + 1; for (let t = k; t <= j; t++) r[s[t][1]] = avg; k = j + 1; } return r; };
  return pearson(rank(xs), rank(ys));
}

function main() {
  const fns = parseScorecard();
  const { hits: F, shallow, notas } = fixCommits(fns);
  const G = guardTests(fns);
  const L = ledgerRefs(fns);

  const rows = fns.map((f) => {
    // MATERIALIZED = defeito REAL que aconteceu = fix-commit (F) OU ledger §5 (L). Sinais duros.
    const src = [];
    if (F[f.fn]) src.push('F');
    if (L[f.fn]) src.push('L');
    return { ...f, outcomeSrc: src, materialized: src.length > 0 ? 1 : 0,
      guarded: G[f.fn] ? 1 : 0, // sinal SEPARADO: há golden/guard sobre o risco (≠ incidente)
      fixEv: (F[f.fn] || []).map((c) => `${c.hash} ${c.subject}`),
      guardEv: G[f.fn] || [], ledgerN: L[f.fn] || 0 };
  });

  const discordo = rows.map((r) => r.discordo);
  const materialized = rows.map((r) => r.materialized);
  const rPB = pearson(discordo, materialized);
  const rho = spearman(discordo, materialized);

  // 2×2: flagged (discordo≥1) × materialized
  const flagged = rows.filter((r) => r.discordo >= 1);
  const clean = rows.filter((r) => r.discordo === 0);
  const matFns = rows.filter((r) => r.materialized === 1);
  const tp = flagged.filter((r) => r.materialized === 1).length;
  const fn_ = matFns.length - tp;
  const fp = flagged.length - tp; // "latentes" — NÃO false-positive (ver nota)
  const tn = clean.filter((r) => r.materialized === 0).length;
  const recall = matFns.length ? tp / matFns.length : null;
  const precision = flagged.length ? tp / flagged.length : null;
  const specificity = clean.length ? tn / clean.length : null;

  // confounder: fan-in médio materializado vs clean-alto-fan-in
  const meanFanInMat = matFns.length ? matFns.reduce((a, r) => a + (r.fanIn || 0), 0) / matFns.length : null;
  const cleanHiFanIn = clean.filter((r) => (r.fanIn || 0) >= 10).map((r) => `${r.fn}(fanIn=${r.fanIn},testes=${r.testesDiretos})`);

  const out = {
    populacao: rows.length,
    sinal_nota: { discordo_total: discordo.reduce((a, b) => a + b, 0), funcoes_flagged: flagged.length, funcoes_clean: clean.length },
    sinal_outcome: {
      materialized: matFns.map((r) => ({ fn: r.fn, discordo: r.discordo, fanIn: r.fanIn, src: r.outcomeSrc, ev: [...r.fixEv, ...(r.ledgerN ? [`§5×${r.ledgerN}`] : [])] })),
      guarded: rows.filter((r) => r.guarded).map((r) => ({ fn: r.fn, discordo: r.discordo, ev: r.guardEv })),
      git_shallow: shallow, notas_fix: notas,
    },
    correlacao: { pointbiserial_discordo_vs_materialized: rPB, spearman_rho: rho, N: rows.length, N_materialized: matFns.length },
    matriz_2x2: { flagged: flagged.length, clean: clean.length, tp, fn_missed: fn_, fp_latent: fp, tn, recall_materializados: recall, precision_flagged: precision, specificity_clean: specificity },
    confounder_fanin: { fanin_medio_materializado: meanFanInMat, clean_alto_fanin: cleanHiFanIn },
  };

  if (process.argv.includes('--json')) { console.log(JSON.stringify(out, null, 2)); return; }

  const p = (x) => x === null ? 'n/a' : x;
  console.log('═══ funcao-scorecard OUTCOME-PROBE (retrospectivo · PROTÓTIPO advisory) ═══\n');
  console.log(`População (funções graduadas): ${out.populacao}   |   git shallow=${shallow} (⇒ sinal (F) truncado; ver LIMITES no cabeçalho)`);
  console.log(`Sinal de NOTA:    ${out.sinal_nota.funcoes_flagged} funções com ≥1 discordo (${out.sinal_nota.discordo_total} discordos) · ${out.sinal_nota.funcoes_clean} clean (0 discordo)`);
  console.log(`\nSinal de OUTCOME — MATERIALIZADO (defeito REAL aconteceu · fix-commit F ∪ ledger §5 L):`);
  for (const m of out.sinal_outcome.materialized)
    console.log(`  • ${m.fn}  discordo=${m.discordo} fanIn=${m.fanIn}  [${m.src.join('+')}]  ${m.ev.join(' | ')}`);
  if (out.sinal_outcome.notas_fix.length) out.sinal_outcome.notas_fix.forEach((n) => console.log(`  ${n}`));
  console.log(`\nSinal SECUNDÁRIO — GUARDED (golden/guard sobre o risco, ≠ incidente · ref qualificada ProductUtil::<fn>):`);
  for (const g of out.sinal_outcome.guarded)
    console.log(`  • ${g.fn}  discordo=${g.discordo}  ${g.ev.join(' | ')}`);
  console.log(`\n── CORRELAÇÃO (o número real) ──`);
  console.log(`  point-biserial r (discordo-count × materialized 0/1) = ${p(rPB)}`);
  console.log(`  Spearman ρ                                          = ${p(rho)}`);
  console.log(`  N=${out.correlacao.N} funções · N_materialized=${out.correlacao.N_materialized}  ⇒ SPOT-CHECK, não significância (ver LIMITES)`);
  console.log(`\n── 2×2 (flagged = discordo≥1) ──`);
  console.log(`  recall (materializados que foram flagged) = ${tp}/${matFns.length} = ${p(recall)}`);
  console.log(`  specificity (clean que ficou clean)       = ${tn}/${clean.length} = ${p(specificity)}`);
  console.log(`  precision (flagged que materializou)      = ${tp}/${flagged.length} = ${p(precision)}   ⚠ os outros ${fp} são LATENTES, não false-positive (precisam de TEMPO/prospectivo)`);
  console.log(`\n── CONFOUNDER fan-in (Code Red: código grande apanha mais) ──`);
  console.log(`  fan-in médio dos materializados = ${p(meanFanInMat)}`);
  console.log(`  clean COM alto fan-in (≥10) e 0 discordo = ${cleanHiFanIn.join(', ') || '(nenhum)'}`);
  console.log(`  ⇒ se os de maior fan-in estão CLEAN, o discordo trilha DEFEITO, não tamanho.`);
}

if (import.meta.url === pathToFileURL(process.argv[1] || '').href) main();
