#!/usr/bin/env node
// scripts/no-mock-in-prod.mjs — Frente 6 (plano anti-duplicacao 2026-06-06)
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// Sessao 2026-05-09 (LICOES_F3_FINANCEIRO_REJEITADO.md): controllers de PRODUCAO foram
// entregues com mock data / rand() / mutacoes NO-OP / stub disfarcado de feature
// (anti-padroes T-AP-12, T-AP-13, M-AP-2). Nenhum gate pegava isso — visualmente
// plausivel, mas o botao "Aceitar" nao mutava nada, ou o KPI mudava a cada refresh.
//
// Este script escaneia controllers de PRODUCAO e flagra esses sinais. Segue a lei do
// plano (ADR 0240): "derivado + enforcado sobrevive / escrito + lembrado apodrece".
// Por isso NAO ha lista manual — tudo deriva do codigo real, com RATCHET (catraca).
//
// =====================================================================================
// ALVO
// =====================================================================================
//   Modules/**/Http/Controllers/**/*.php   (controllers de modulo)
//   app/Http/Controllers/**/*.php          (controllers UPOS core)
//
// =====================================================================================
// HEURISTICAS (cada achado = { arquivo, linha, regra, trecho })
// =====================================================================================
//   - nondeterminismo  : rand( / mt_rand( / fake() / Faker / faker  (T-AP-12)
//                        gera nao-determinismo: quebra cache, "vs ontem", teste E2E.
//   - stub-marker      : "@memcofre status: stub" / "stub-mock-data" / "// TODO[CL]"
//                        / "// TODO:" / "FIXME"                       (M-AP-2)
//                        marcadores honestos de codigo incompleto entregue como feature.
//   - noop-mutation    : metodo publico de mutacao (store/update/destroy/aceitar/
//                        desfazer/baixar/...) cujo corpo SO retorna back()/redirect()
//                        sem tocar Model/DB/Service no meio              (T-AP-13)
//   - mock-array       : (heuristica LEVE, opcional) array hardcoded grande retornado
//                        direto pra view/Inertia — sinal de dados de mentira.
//
// =====================================================================================
// MODOS
// =====================================================================================
//   node scripts/no-mock-in-prod.mjs                 # default: lista + compara baseline.
//                                                    #   exit 1 SO se contagem por regra
//                                                    #   AUMENTAR vs baseline; exit 0 se =/<.
//   node scripts/no-mock-in-prod.mjs --write-baseline  # (re)grava baseline (uso consciente).
//   node scripts/no-mock-in-prod.mjs --json          # saida JSON pra CI.
//
// =====================================================================================
// RATCHET / BASELINE — como funciona e como ajustar
// =====================================================================================
// O baseline (scripts/no-mock-baseline.json) fotografa a CONTAGEM atual de achados POR
// REGRA (o debito legado existente). O gate NAO obriga limpar o legado — so impede
// PIORAR: se uma regra passar de N pra N+1 achados, falha. Igual/menor → passa.
//
// Como ajustar (RISCO DE FALSO-POSITIVO — o baseline absorve os legitimos atuais):
//   - Achado legitimo no codigo legado: ja esta absorvido no baseline (contagem fotografada).
//   - Regra explodindo (ex: >500 "// TODO"): torne-a mais conservadora aqui no script
//     (ajuste os regex/heuristica abaixo) e regrave o baseline com --write-baseline.
//   - Achado NOVO e legitimo (raro): justifique e regrave baseline conscientemente.
//
// Falsos-positivos conhecidos / mitigados:
//   - `rand` dentro de palavra (ex: "brand", "Strand") → exigimos `rand(` colado em `(`
//     com boundary antes, evitando substring.
//   - noop-mutation: heuristica CONSERVADORA — so dispara se o corpo do metodo tem
//     <=3 linhas efetivas E nenhuma de :: / ->save / ->update / ->create / ->delete /
//     DB:: / $this-> / dispatch( / ->fill( etc. Comentarios e linhas em branco ignoradas.
//   - stub-marker "// TODO:" é o mais ruidoso; se explodir, considere remove-lo do set.
//
// Refs: LICOES_F3_FINANCEIRO_REJEITADO.md (T-AP-12/13, M-AP-2),
//       memory/sessions/2026-06-06-plano-inventario-anti-duplicacao.md (Frente 6),
//       ADR 0209 (baseline ratchet), ADR 0240 (derivado+enforcado sobrevive).

import { readFileSync, writeFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { resolve, join } from 'node:path';

const ROOT = process.cwd();
const BASELINE_PATH = resolve(ROOT, 'scripts/no-mock-baseline.json');
const MODE_WRITE = process.argv.includes('--write-baseline');
const MODE_JSON = process.argv.includes('--json');

// Diretorios-alvo (controllers de PRODUCAO). Worktrees/vendor/node_modules ficam de fora.
const TARGET_DIRS = [
  { base: 'Modules', controllerSegment: ['Http', 'Controllers'] },
  { base: join('app', 'Http', 'Controllers'), controllerSegment: null },
];

const IGNORE_DIR = new Set(['vendor', 'node_modules', '.git', 'tests', 'Tests']);

// ---------------------------------------------------------------------------
// Coleta de arquivos-alvo
// ---------------------------------------------------------------------------
function walk(dir, acc) {
  let entries;
  try {
    entries = readdirSync(dir, { withFileTypes: true });
  } catch {
    return acc;
  }
  for (const e of entries) {
    if (e.isDirectory()) {
      if (IGNORE_DIR.has(e.name)) continue;
      walk(join(dir, e.name), acc);
    } else if (e.isFile() && e.name.endsWith('.php')) {
      acc.push(join(dir, e.name));
    }
  }
  return acc;
}

function collectTargets() {
  const files = new Set();

  // Modules/**/Http/Controllers/**/*.php
  const modulesDir = resolve(ROOT, 'Modules');
  if (existsSync(modulesDir)) {
    for (const all of [walk(modulesDir, [])]) {
      for (const f of all) {
        const norm = f.replace(/\\/g, '/');
        if (/\/Http\/Controllers\//.test(norm)) files.add(f);
      }
    }
  }

  // app/Http/Controllers/**/*.php
  const appCtrlDir = resolve(ROOT, 'app', 'Http', 'Controllers');
  if (existsSync(appCtrlDir)) {
    for (const f of walk(appCtrlDir, [])) files.add(f);
  }

  return [...files];
}

// ---------------------------------------------------------------------------
// Heuristicas
// ---------------------------------------------------------------------------

// nondeterminismo: rand( / mt_rand( / fake() / Faker / faker
const RE_NONDET = [
  /\bmt_rand\s*\(/,        // checa antes de rand pra atribuir a regra mais especifica
  /\brand\s*\(/,
  /\bfake\s*\(/,
  /\bFaker\b/,
  /\bfaker\b/,
];

// stub-markers
const STUB_MARKERS = [
  '@memcofre status: stub',
  'stub-mock-data',
  '// TODO[CL]',
  '// TODO:',
  'FIXME',
];

// nomes de metodo que cheiram a mutacao (POST/PUT/DELETE)
const MUTATION_NAMES = new Set([
  'store', 'update', 'destroy', 'delete', 'create', 'save',
  'aceitar', 'desfazer', 'baixar', 'estornar', 'cancelar', 'confirmar',
  'aprovar', 'rejeitar', 'anular', 'reverter', 'processar', 'finalizar',
  'conciliar', 'lancar', 'liquidar', 'pagar', 'receber', 'sync', 'importar',
]);

// sinais de que o corpo REALMENTE faz algo (toca Model/DB/Service)
const RE_REAL_WORK = /(::|->save|->update|->create|->delete|->fill|->push|->associate|->sync|DB::|\$this->|dispatch\s*\(|event\s*\(|->insert|->upsert|->increment|->decrement|->forceDelete|->restore|Storage::|->attach|->detach)/;

function isComment(line) {
  const t = line.trim();
  return t === '' || t.startsWith('//') || t.startsWith('*') || t.startsWith('/*') || t.startsWith('#');
}

// Detecta linhas de stub-marker e nondeterminismo (line-based)
function scanLineMarkers(lines, relPath, findings) {
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const lineNo = i + 1;
    const lineIsComment = isComment(line);

    // nondeterminismo — atribui a primeira regra que casar (mt_rand antes de rand).
    // Pula linhas-comentario: rand()/fake() em PROSA nao executa (evita FP tipo
    // "substitui o 0.85 fake (bug B1)"). So codigo executavel conta.
    if (!lineIsComment) {
      for (const re of RE_NONDET) {
        if (re.test(line)) {
          findings.push({
            arquivo: relPath,
            linha: lineNo,
            regra: 'nondeterminismo',
            trecho: line.trim().slice(0, 160),
          });
          break;
        }
      }
    }

    // stub-markers
    for (const marker of STUB_MARKERS) {
      if (line.includes(marker)) {
        findings.push({
          arquivo: relPath,
          linha: lineNo,
          regra: 'stub-marker',
          trecho: line.trim().slice(0, 160),
        });
        break;
      }
    }
  }
}

// Detecta mutacao NO-OP: metodo publico de mutacao cujo corpo so faz return back()/redirect()
// Heuristica conservadora — parser de chaves simples, sem AST.
function scanNoopMutations(content, lines, relPath, findings) {
  // Acha assinaturas: public function <nome>(
  const sigRe = /public\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/g;
  let m;
  while ((m = sigRe.exec(content)) !== null) {
    const name = m[1];
    if (!MUTATION_NAMES.has(name)) continue;

    // Acha a abertura de chave do corpo a partir do fim da assinatura
    let idx = content.indexOf('{', sigRe.lastIndex);
    if (idx === -1) continue;

    // Varre o corpo equilibrando chaves
    let depth = 0;
    let bodyStart = idx + 1;
    let bodyEnd = -1;
    for (let p = idx; p < content.length; p++) {
      const ch = content[p];
      if (ch === '{') depth++;
      else if (ch === '}') {
        depth--;
        if (depth === 0) { bodyEnd = p; break; }
      }
    }
    if (bodyEnd === -1) continue;

    const body = content.slice(bodyStart, bodyEnd);
    const bodyLines = body.split('\n').map((l) => l).filter((l) => !isComment(l));

    if (bodyLines.length === 0 || bodyLines.length > 3) continue;

    const joined = bodyLines.join('\n');
    // corpo precisa terminar/conter SO return back()/redirect(...) e NAO ter trabalho real
    const hasReturnBack = /return\s+(back\s*\(\s*\)|redirect\s*\()/.test(joined);
    if (!hasReturnBack) continue;
    if (RE_REAL_WORK.test(joined)) continue;

    // linha aproximada do metodo (linha da assinatura)
    const before = content.slice(0, m.index);
    const lineNo = before.split('\n').length;

    findings.push({
      arquivo: relPath,
      linha: lineNo,
      regra: 'noop-mutation',
      trecho: `public function ${name}() { ${joined.replace(/\s+/g, ' ').trim().slice(0, 120)} }`,
    });
  }
}

// Heuristica LEVE/opcional: array hardcoded grande retornado direto pra view/Inertia.
// Conservadora: dispara so quando ha return ... Inertia::render/view(...) e o arquivo
// tem um array-literal com >=8 elementos com chaves 'string' => ... no mesmo metodo.
// Mantida deliberadamente fraca pra nao explodir; o baseline absorve o legado.
function scanMockArrays(content, relPath, findings) {
  // Acha blocos `[ ... ]` com muitas chaves string => e que aparecem perto de render/view.
  // Heuristica simples: para cada Inertia::render( ou view(, olha 1500 chars a frente
  // procurando array literal denso.
  const renderRe = /(Inertia::render|->json|response\(\)->json|return\s+view)\s*\(/g;
  let m;
  while ((m = renderRe.exec(content)) !== null) {
    const window = content.slice(m.index, m.index + 2000);
    // conta pares 'chave' => dentro do array de props
    const pairs = (window.match(/['"][^'"]+['"]\s*=>/g) || []).length;
    // numeros hardcoded (valores monetarios/contagens) — sinal de dado de mentira
    const hardNums = (window.match(/=>\s*-?\d[\d_.,]*\b/g) || []).length;
    if (pairs >= 12 && hardNums >= 8) {
      const before = content.slice(0, m.index);
      const lineNo = before.split('\n').length;
      findings.push({
        arquivo: relPath,
        linha: lineNo,
        regra: 'mock-array',
        trecho: `render/view com ~${pairs} chaves e ~${hardNums} valores hardcoded`,
      });
    }
  }
}

// ---------------------------------------------------------------------------
// Scan
// ---------------------------------------------------------------------------
function scanFile(absPath) {
  const relPath = absPath.replace(/\\/g, '/').replace(`${ROOT.replace(/\\/g, '/')}/`, '');
  let content;
  try {
    content = readFileSync(absPath, 'utf8');
  } catch {
    return [];
  }
  const lines = content.split('\n');
  const findings = [];

  scanLineMarkers(lines, relPath, findings);
  scanNoopMutations(content, lines, relPath, findings);
  scanMockArrays(content, relPath, findings);

  return findings;
}

function buildCountsByRule(findings) {
  const counts = {};
  for (const f of findings) {
    counts[f.regra] = (counts[f.regra] || 0) + 1;
  }
  return counts;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
function main() {
  const targets = collectTargets();
  const allFindings = [];
  for (const f of targets) {
    allFindings.push(...scanFile(f));
  }

  const counts = buildCountsByRule(allFindings);
  const total = allFindings.length;

  if (MODE_JSON && !MODE_WRITE) {
    // saida pura pra CI
    const baseline = existsSync(BASELINE_PATH) ? JSON.parse(readFileSync(BASELINE_PATH, 'utf8')) : null;
    const baselineCounts = baseline?.counts || {};
    const regressions = [];
    for (const [rule, atual] of Object.entries(counts)) {
      const base = baselineCounts[rule] || 0;
      if (atual > base) regressions.push({ rule, base, atual, delta: atual - base });
    }
    console.log(JSON.stringify({
      total,
      counts,
      baseline_counts: baselineCounts,
      regressions,
      findings: allFindings,
      ok: regressions.length === 0,
    }, null, 2));
    process.exit(regressions.length === 0 ? 0 : 1);
  }

  console.log(`no-mock-in-prod · ${MODE_WRITE ? 'WRITE-BASELINE' : 'VALIDATE'} mode`);
  console.log(`Controllers escaneados: ${targets.length}`);
  console.log(`Total de achados: ${total}`);
  console.log('Por regra:');
  for (const [rule, n] of Object.entries(counts).sort((a, b) => b[1] - a[1])) {
    console.log(`   ${rule.padEnd(16)} ${n}`);
  }

  if (MODE_WRITE) {
    const out = {
      _meta: {
        generated_at: new Date().toISOString(),
        total_findings: total,
        controllers_scanned: targets.length,
        frente: 6,
        refs: ['LICOES_F3_FINANCEIRO_REJEITADO.md', 'ADR 0209', 'ADR 0240'],
        nota: 'Contagem por REGRA. Gate falha so se uma regra AUMENTAR vs este baseline.',
      },
      counts,
    };
    writeFileSync(BASELINE_PATH, JSON.stringify(out, null, 2) + '\n');
    console.log(`\n✅ Baseline gravado em ${BASELINE_PATH}`);
    return;
  }

  // VALIDATE
  if (!existsSync(BASELINE_PATH)) {
    console.error(`\n❌ Baseline nao existe em ${BASELINE_PATH}`);
    console.error('   Rode: node scripts/no-mock-in-prod.mjs --write-baseline');
    process.exit(1);
  }

  const baseline = JSON.parse(readFileSync(BASELINE_PATH, 'utf8'));
  const baselineCounts = baseline.counts || {};

  const regressions = [];
  for (const [rule, atual] of Object.entries(counts)) {
    const base = baselineCounts[rule] || 0;
    if (atual > base) regressions.push({ rule, base, atual, delta: atual - base });
  }

  if (regressions.length > 0) {
    console.error('');
    console.error(`❌ REGRESSAO — ${regressions.length} regra(s) com achados AUMENTADOS:`);
    for (const r of regressions.sort((a, b) => b.delta - a.delta)) {
      console.error(`   ${r.rule} · ${r.base} → ${r.atual} (Δ+${r.delta})`);
    }
    // mostra os achados novos por regra (ajuda a localizar)
    console.error('\nAchados na(s) regra(s) em regressao (file:line):');
    const regSet = new Set(regressions.map((r) => r.rule));
    for (const f of allFindings.filter((f) => regSet.has(f.regra)).slice(0, 40)) {
      console.error(`   ${f.arquivo}:${f.linha} · ${f.regra} · ${f.trecho}`);
    }
    console.error('\nSe a regressao for legitima (refator consciente): node scripts/no-mock-in-prod.mjs --write-baseline');
    process.exit(1);
  }

  console.log('\n✅ Sem regressoes vs baseline (nao piorou)');
}

main();
