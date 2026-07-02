#!/usr/bin/env node
// nightly-diff.mjs — tripwire de regressão QUALITATIVA do nightly (ROADMAP-SDD P15).
//
// POR QUE (achado da avaliação adversarial 2026-06-21 + crítico de completude):
// hoje só o `floor_count` AGREGADO é consumido (read-side sdd-scorecard.mjs). O floor é
// a INTERSEÇÃO de 3 runs (floor-compute.mjs) — DE PROPÓSITO estável, mas isso MASCARA a
// regressão de UMA noite: um arquivo de teste NOVO falhando, ou uma CLASSE de falha
// (ex.: QueryException SQLSTATE[42S02]) explodindo, só entra no floor depois de 3 noites
// — passa batido nesse meio-tempo. Este script faz o DIFF por-arquivo + por-classe entre
// a noite N e N-1 e expõe {newly_failing_files, recovered_files, by_failure_class}.
//
// ANTI-PII (igual junit-summary.mjs, repo é PÚBLICO): a CLASSE de falha é derivada do
// `type="..."` do <failure>/<error> do junit.xml (= nome FQ da exception, ESTÁVEL, sem
// texto livre) + token SQLSTATE[XXXXX] quando a exception é de banco. NUNCA a mensagem.
// O path do arquivo de teste já é público (vem do summary.json, que junit-summary já
// publica). Frame de stack é OPCIONAL e, quando usado, só o basename:linha — nunca o
// conteúdo. Default: NÃO extrai frame (só class+sqlstate) pra zero risco.
//
// ADVISORY POR CONSTRUÇÃO: este script SÓ COMPUTA o diff e o imprime. NÃO abre alerta
// (gh issue / mcp_alertas). O caminho de alerta fica atrás do env NIGHTLY_DIFF_ALERT
// (default OFF) — ligar SÓ após P03 estabilizar (US-GOV-021), senão é ruído de ambiente
// (suíte era-sqlite ainda corrompe tabela CORE → falha de infra, não regressão real).
//
// Determinístico: para o MESMO par de runs, MESMO output (sem timestamp no corpo).
// Espelha a estrutura de scripts/tests/floor-compute.mjs (validRuns + computeX + CLI guard).
// Zero-dep (node >=18). Roda SEM CT100 (lê artefatos já materializados).
//
// USO:
//   node scripts/tests/nightly-diff.mjs [--runs <dir>] [--out <file>]
//     compara os 2 últimos runs VÁLIDOS de <dir> (cada um = pasta YYYYMMDD-HHMMSS com
//     summary.json [+ junit.xml opcional p/ classe]).
//   node scripts/tests/nightly-diff.mjs --n <summaryN.json> --prev <summaryN-1.json>
//                                       [--junit-n <x.xml>] [--junit-prev <y.xml>] [--out <file>]
//     compara 2 summary.json explícitos (modo usado pelo meta-teste e por CI ad-hoc).
import { readdirSync, readFileSync, existsSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const arg = (k, d) => { const i = process.argv.indexOf(k); return i >= 0 ? process.argv[i + 1] : d; };

// ── leitura de um "run" (summary.json + junit.xml opcional) ──────────────────
// Aceita 2 formas: pasta de run (validRuns) OU caminhos explícitos (modo --n/--prev).
function loadRun({ summaryPath, junitPath = null, ts = null }) {
  if (!existsSync(summaryPath)) return null;
  let s; try { s = JSON.parse(readFileSync(summaryPath, 'utf8')); } catch { return null; }
  if (s.invalid) return null; // marcador explicito FV-F4 (US-GOV-045) — run morto declarado
  if (!s.coherent || !s.n_testcases || !Array.isArray(s.files)) return null;
  const failingFiles = s.files
    .filter((f) => (f.failed || 0) > 0 || (f.errors || 0) > 0)
    .map((f) => f.file);
  const xml = junitPath && existsSync(junitPath) ? safeRead(junitPath) : null;
  return {
    ts: ts || s.generated_at || null,
    totals: s.totals || {},
    failingFiles: [...new Set(failingFiles)].sort(),
    failureClasses: xml ? extractFailureClasses(xml) : null,
  };
}
function safeRead(p) { try { return readFileSync(p, 'utf8'); } catch { return null; } }

// runs válidos de um diretório, em ordem cronológica (nome = YYYYMMDD-HHMMSS).
// Espelha validRuns() do floor-compute.mjs; aqui também tenta casar o junit.xml ao lado.
export function validRuns(runsDir) {
  if (!existsSync(runsDir)) return [];
  const names = readdirSync(runsDir, { withFileTypes: true })
    .filter((e) => e.isDirectory() && /^\d{8}-\d{6}$/.test(e.name))
    .map((e) => e.name).sort();
  const out = [];
  for (const name of names) {
    const dir = join(runsDir, name);
    const run = loadRun({
      summaryPath: join(dir, 'summary.json'),
      junitPath: join(dir, 'junit.xml'),
      ts: name,
    });
    if (run) out.push(run);
  }
  return out;
}

// ── CLASSE de falha ESTÁVEL a partir do junit.xml (anti-PII) ─────────────────
// PHPUnit/Pest emitem <failure type="FQ\Exception\Class">msg</failure> (e <error type=...>).
// O `type` é o nome FULLY-QUALIFIED da exception — ESTÁVEL e SEM texto de usuário.
// Pra exceptions de banco (QueryException/PDOException), o SQLSTATE[XXXXX] no INÍCIO da
// mensagem é um CÓDIGO estável (não-PII), então anexamos só ESSE token. Nada além disso
// é lido da mensagem. Resultado: { "ExceptionClass": n } ou { "ExceptionClass#SQLSTATE[..]": n }.
const FAIL_TAG_RE = /<(failure|error)\b([^>]*)>([\s\S]*?)<\/\1>/g;
const SELFCLOSE_RE = /<(failure|error)\b([^>]*)\/>/g;
const TYPE_RE = /\btype="([^"]*)"/;
const SQLSTATE_RE = /SQLSTATE\[[0-9A-Z]{1,5}\]/; // ex.: SQLSTATE[42S02], SQLSTATE[HY000]

function shortClass(fq) {
  if (!fq) return '(sem-type)';
  // só nome da classe (último segmento do namespace), preserva caracteres seguros.
  const seg = fq.replace(/&amp;/g, '&').split(/[\\/]/).pop() || fq;
  return seg.replace(/[^\w.+#-]/g, '') || '(sem-type)';
}

export function extractFailureClasses(xml) {
  if (!xml) return {};
  const counts = Object.create(null);
  const bump = (attrs, body) => {
    const t = (attrs.match(TYPE_RE) || [])[1] || null;
    let key = shortClass(t);
    // SQLSTATE é código estável — só esse token, NUNCA o resto da mensagem.
    if (body) {
      const m = body.match(SQLSTATE_RE);
      if (m) key += `#${m[0]}`;
    }
    counts[key] = (counts[key] || 0) + 1;
  };
  for (const m of xml.matchAll(FAIL_TAG_RE)) bump(m[2], m[3]);
  for (const m of xml.matchAll(SELFCLOSE_RE)) bump(m[2], '');
  // ordena por chave pra output determinístico
  const ordered = Object.create(null);
  for (const k of Object.keys(counts).sort()) ordered[k] = counts[k];
  return ordered;
}

// ── DIFF entre noite N e N-1 ─────────────────────────────────────────────────
// newly_failing_files: falham em N e NÃO em N-1 (regressão por-arquivo de 1 noite).
// recovered_files:     falhavam em N-1 e NÃO em N (sinal de melhora).
// by_failure_class:    delta por classe estável { classe: { prev, curr, delta } }.
// tripwire:            há regressão? (>=1 arquivo novo falhando OU >=1 classe inflando).
export function diffRuns(curr, prev) {
  if (!curr || !prev) {
    return {
      schema: 'nightly-diff/v1 (ROADMAP-SDD P15)',
      comparable: false,
      newly_failing_files: [], recovered_files: [], by_failure_class: {},
      tripwire: false,
      note: 'precisa de 2 runs validos (summary.json coherent + n_testcases>0); sem comparacao',
    };
  }
  const cur = new Set(curr.failingFiles);
  const pre = new Set(prev.failingFiles);
  const newly = [...cur].filter((f) => !pre.has(f)).sort();
  const recovered = [...pre].filter((f) => !cur.has(f)).sort();

  const byClass = {};
  if (curr.failureClasses || prev.failureClasses) {
    const cc = curr.failureClasses || {};
    const pc = prev.failureClasses || {};
    for (const k of [...new Set([...Object.keys(cc), ...Object.keys(pc)])].sort()) {
      const c = cc[k] || 0, p = pc[k] || 0;
      if (c !== p) byClass[k] = { prev: p, curr: c, delta: c - p };
    }
  }
  const classInflated = Object.values(byClass).some((d) => d.delta > 0);
  const tripwire = newly.length > 0 || classInflated;
  return {
    schema: 'nightly-diff/v1 (ROADMAP-SDD P15)',
    comparable: true,
    runs: { curr: curr.ts, prev: prev.ts },
    newly_failing_files: newly,
    recovered_files: recovered,
    by_failure_class: byClass,
    tripwire,
    note: tripwire
      ? `regressao QUALITATIVA detectada: ${newly.length} arquivo(s) novo(s) falhando${classInflated ? ' + classe(s) de falha inflada(s)' : ''} (ADVISORY — alerta OFF; ligar so apos P03 estabilizar, US-GOV-021)`
      : 'sem regressao por-arquivo nem por-classe entre as 2 noites (no-op)',
  };
}

// ── alerta (DOCUMENTADO, atrás de flag DESLIGADO) ────────────────────────────
// Caminho de alerta deliberadamente NÃO implementado/ligado. Quando P03 (US-GOV-021,
// isolar os 18 corruptores era-sqlite) estabilizar, o nightly vai parar de gerar ruído
// de ambiente; SÓ AÍ liga-se o alerta — setando NIGHTLY_DIFF_ALERT=1 e ligando o canal
// (gh issue OU mcp_alertas) AQUI. Até lá, advisory puro (só imprime o diff).
function maybeAlert(diff) {
  if (process.env.NIGHTLY_DIFF_ALERT !== '1') return; // OFF por padrão (ligar só apos P03 — US-GOV-021)
  if (!diff.tripwire) return;
  // INTENCIONALMENTE NÃO-IMPLEMENTADO: ligar so apos P03 estabilizar (US-GOV-021).
  // Esperado: abrir/atualizar gh issue OU mcp_alertas com newly_failing_files +
  // by_failure_class (SEM mensagem — anti-PII; o diff já é anti-PII por construção).
  console.error('[nightly-diff] NIGHTLY_DIFF_ALERT=1 mas canal de alerta nao ligado (P15: advisory ate P03 — US-GOV-021).');
}

// ── CLI (só quando executado direto; importável p/ teste) ────────────────────
import { realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const isMain = (() => { try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); } catch { return false; } })();
if (isMain) {
  const OUT = arg('--out', null);
  let curr, prev;
  const nPath = arg('--n', null);
  if (nPath) {
    // modo explícito: 2 summary.json (+ junit opcional)
    curr = loadRun({ summaryPath: nPath, junitPath: arg('--junit-n', null), ts: 'N' });
    prev = loadRun({ summaryPath: arg('--prev', null) ?? '', junitPath: arg('--junit-prev', null), ts: 'N-1' });
  } else {
    // modo diretório: 2 últimos runs válidos
    const runs = validRuns(arg('--runs', '/opt/oimpresso-fullsuite/runs'));
    curr = runs[runs.length - 1] || null;
    prev = runs[runs.length - 2] || null;
  }
  const diff = diffRuns(curr, prev);
  const json = JSON.stringify(diff, null, 2) + '\n';
  if (OUT) writeFileSync(OUT, json, 'utf8');
  console.log(json.trimEnd());
  maybeAlert(diff);
  // ADVISORY: nunca derruba o build (exit 0 sempre — alerta vem do canal, post-P03).
  process.exit(0);
}
