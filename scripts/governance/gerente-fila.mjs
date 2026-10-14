#!/usr/bin/env node
// gerente-fila.mjs — UMA varredura da fila de PRs, classificada em LOTES de ação.
//
// POR QUE EXISTE: a gerência da fila de merges (handoffs `gestao-fila-merges-*`, out/2026)
// rodava em scripts FORA do repo (`D:/oimpresso.com/.claude/gestor-fila-scripts/`,
// `noite-0710/`), reescritos a cada gerente. Cada gerência gastava tokens lendo PR a PR
// (`gh pr view` por número) e chegou a esgotar a cota GraphQL com ~47 vigias de 60 s
// (handoff 2026-10-14 03:00). Este script troca isso por UMA chamada por rodada: o agente lê
// ~30 linhas de lote e age em bloco. É a versão versionada daquelas ferramentas — não duplica
// dono no repo (nenhum script aqui classifica a fila; `dup-detector` mede sobreposição de
// arquivos entre PRs, `placar.mjs` mede threads de playbook — ambos são CONSUMIDOS, não refeitos).
//
// ── LOTES (precedência de cima pra baixo; cada PR cai em UM) ─────────────────────────────
//   W         decisão do [W]: toca arquivo de VALOR/ESTOQUE (paths de
//             .claude/rules/calculo-valor-estoque.md, menos o glob amplo de Controllers),
//             migration com drop, ou título/label marcado [W]. O gerente NÃO mergeia.
//   RASCUNHO  draft.
//   CONFLITO  mergeable_state=dirty. Sub-tipo `regenerar` quando o PR só toca derivados
//             (SUPERFICIE.md, enviados-cowork*.json, .cowork-freshness-ledger.json) — conserto
//             mecânico; `real` nos demais.
//   MAIN      required vermelho que TAMBÉM está vermelho no head do main — não é do PR;
//             consertar o main primeiro destrava todos (lição 13/10: SDD ratchet, G-6).
//   VERMELHO  required com falha só no PR.
//   ESPERA    required ausente ou em execução.
//   MERGE     todos os required do baseline concluídos ok. Ordenados 1 POR MÓDULO por rodada
//             (o resto vai pra `MERGE·fila`): mergear 2 do mesmo módulo de uma vez gera ciclo de
//             conflito no SUPERFICIE.md (lição 13/10).
//
// ── O QUE NÃO MEDE (pra verde não mentir) ────────────────────────────────────────────────
// • Lanes advisory que não re-rodam em synchronize (69 workflows, emenda 2026-09-08 do
//   proibicoes §Sempre fazer): aqui só os REQUIRED decidem. Advisory é leitura do gerente.
// • `skipped` em required conta como ok (é o que a branch protection aceita) mas é CONTADO
//   à parte — verde por não-execução é LC-13.
// • Cutover de flag (`=> true` em config) não é detectado: é decisão [W] por título/label.
//
// ── CUSTO ────────────────────────────────────────────────────────────────────────────────
// Só REST (cota 5000/h; a GraphQL foi a que esgotou). Por rodada: 1 lista + 1 check-runs do
// main + por PR: 1 detalhe + 1 check-runs + arquivos (CACHEADOS por head SHA em .claude/run/,
// gitignored — só rebaixa quando o head muda). Cadência mínima recomendada: 300 s.
//
// Uso:
//   node scripts/governance/gerente-fila.mjs              lotes da fila (texto curto)
//   node scripts/governance/gerente-fila.mjs --placar     + próximas threads dos playbooks por módulo
//   node scripts/governance/gerente-fila.mjs --json       tudo, pra máquina
//   node scripts/governance/gerente-fila.mjs --pr 9341    só estes PRs (aceita fechados — smoke)
//   node scripts/governance/gerente-fila.mjs --selftest
// Exit: 0 mediu · 2 NÃO CONSEGUI MEDIR (gh/baseline ausente) — "não medi" nunca vira fila vazia
// (§5 2026-07-29, fail-open que vira frase falsa).

import { readFileSync, writeFileSync, existsSync, mkdirSync, openSync, closeSync, unlinkSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..', '..');
const REPO = process.env.GERENTE_REPO || 'wagnerra23/oimpresso.com';
const CACHE = join(ROOT, '.claude', 'run', 'gerente-fila-arquivos.json');

export class NaoMedi extends Error {}

// ── Insumos derivados (nada restateado à mão) ───────────────────────────────────────────
export function requiredDoBaseline(json) {
  const a = json?.classic_protection?.contexts ?? [];
  const b = json?.rulesets?.contexts ?? [];
  const todos = [...new Set([...a, ...b])];
  if (!todos.length) throw new NaoMedi('baseline sem contexts required');
  return todos;
}

export function pathsDeValor(ruleText) {
  const fm = ruleText.split('---')[1] ?? '';
  return [...fm.matchAll(/^\s*-\s*"([^"]+)"/gm)].map((m) => m[1])
    // o glob de Controllers é gatilho de LEITURA da rule, não sinal de mudança de valor:
    // mandaria toda tela de módulo pro [W] e esvaziaria o lote MERGE.
    .filter((p) => !p.startsWith('Modules/**'));
}

function globRe(g) {
  const s = g.replace(/[.+^${}()|[\]\\]/g, '\\$&').replace(/\*\*/g, '\u0000').replace(/\*/g, '[^/]*').replace(/\u0000/g, '.*');
  return new RegExp(`^${s}$`);
}

const DERIVADOS = [/(^|\/)SUPERFICIE\.md$/, /enviados-cowork[^/]*\.json$/, /\.cowork-freshness-ledger\.json$/];
const OK = new Set(['success', 'neutral', 'skipped']);
const RUIM = new Set(['failure', 'cancelled', 'timed_out', 'action_required', 'startup_failure', 'stale']);

export function moduloDe(files) {
  for (const f of files) {
    const m = f.match(/^Modules\/([^/]+)\//) || f.match(/^resources\/js\/Pages\/([^/]+)\//) || f.match(/^memory\/requisitos\/([^/]+)\//);
    if (m) return m[1];
  }
  return '(núcleo)';
}

/** Último run por nome (re-runs geram vários; vale o mais recente). */
export function porNome(runs) {
  const m = new Map();
  for (const r of runs) {
    const prev = m.get(r.name);
    if (!prev || (r.started_at || '') >= (prev.started_at || '')) m.set(r.name, r);
  }
  return m;
}

/** Pura: decide o lote de UM PR. */
export function classificar(pr, ctx) {
  const files = pr.files.map((f) => f.filename ?? f);
  const patches = pr.files.map((f) => f.patch ?? '').join('\n');
  const base = { n: pr.number, titulo: pr.title, modulo: moduloDe(files), criado: pr.created_at };
  const valorRes = ctx.valor.map(globRe);
  const tocaValor = files.filter((f) => valorRes.some((r) => r.test(f)));
  const dropMig = files.some((f) => /Database\/Migrations\//.test(f)) && /^\+.*(dropColumn|dropIfExists|Schema::drop|->drop\()/m.test(patches);
  const marcaW = /\[W\]/.test(pr.title) || (pr.labels || []).some((l) => /(^|-)w$|decisao-w|precisa-w/i.test(l.name ?? l));
  if (tocaValor.length || dropMig || marcaW) {
    return { ...base, lote: 'W', motivo: [tocaValor.length && `valor: ${tocaValor.join(', ')}`, dropMig && 'migration com drop', marcaW && 'marcado [W]'].filter(Boolean).join(' · ') };
  }
  if (pr.draft) return { ...base, lote: 'RASCUNHO', motivo: '' };
  if (pr.mergeable_state === 'dirty') {
    const soDerivado = files.length > 0 && files.every((f) => DERIVADOS.some((r) => r.test(f)));
    return { ...base, lote: 'CONFLITO', motivo: soDerivado ? 'regenerar (só derivados)' : 'real' };
  }
  const runs = porNome(pr.checkRuns);
  const ruins = [], faltam = [], pulados = [];
  for (const nome of ctx.required) {
    const r = runs.get(nome);
    if (!r || r.status !== 'completed') { faltam.push(nome); continue; }
    if (RUIM.has(r.conclusion)) ruins.push(nome);
    else if (r.conclusion === 'skipped') pulados.push(nome);
    else if (!OK.has(r.conclusion)) faltam.push(nome);
  }
  if (ruins.length) {
    const doMain = ruins.filter((n) => ctx.ruinsNoMain.has(n));
    if (doMain.length === ruins.length) return { ...base, lote: 'MAIN', motivo: doMain.join(' · ') };
    return { ...base, lote: 'VERMELHO', motivo: ruins.filter((n) => !ctx.ruinsNoMain.has(n)).join(' · ') };
  }
  if (faltam.length) return { ...base, lote: 'ESPERA', motivo: `${faltam.length} required pendente(s)` };
  return { ...base, lote: 'MERGE', motivo: pulados.length ? `${pulados.length} required skipped` : '' };
}

/** 1 MERGE por módulo por rodada, o mais antigo primeiro; o resto vira MERGE·fila. */
export function serializar(itens) {
  const vistos = new Set();
  return [...itens].sort((a, b) => (a.criado || '').localeCompare(b.criado || '')).map((i) => {
    if (i.lote !== 'MERGE') return i;
    if (vistos.has(i.modulo)) return { ...i, lote: 'MERGE·fila', motivo: `espera o ${i.modulo} da rodada` };
    vistos.add(i.modulo);
    return i;
  });
}

// ── I/O ──────────────────────────────────────────────────────────────────────────────────
// Paginação PRÓPRIA por `page=N`: o `--paginate` do gh segue o `Link: next`, que a API
// devolve como `repositories/{id}/...` — e há proxy que recusa essa forma (medido 2026-10-14,
// HTTP 403 no ambiente cloud). Por página explícita funciona nos dois.
function ghUma(path) {
  let out;
  try {
    out = execFileSync('gh', ['api', path], { encoding: 'utf8', maxBuffer: 64 << 20, stdio: ['ignore', 'pipe', 'pipe'] });
  } catch (e) { throw new NaoMedi(`gh api ${path}: ${(e.stderr || e.message).toString().split('\n')[0]}`); }
  try { return JSON.parse(out); } catch { throw new NaoMedi(`resposta ilegível de ${path}`); }
}

function gh(path) {
  if (!/per_page=(\d+)/.test(path)) { const r = ghUma(path); return Array.isArray(r) ? r : [r]; }
  const per = Number(path.match(/per_page=(\d+)/)[1]);
  const todos = [];
  for (let page = 1; page <= 50; page++) {
    const r = ghUma(`${path}&page=${page}`);
    const itens = Array.isArray(r) ? r : (r.check_runs ?? []);
    todos.push(...itens);
    if (itens.length < per) return todos;
  }
  throw new NaoMedi(`${path}: mais de 50 páginas — recuso truncar em silêncio`);
}

function lerCache() { try { return JSON.parse(readFileSync(CACHE, 'utf8')); } catch { return {}; } }

export function medirFila(prsFiltro) {
  const baselinePath = join(ROOT, 'governance', 'required-checks-baseline.json');
  if (!existsSync(baselinePath)) throw new NaoMedi('governance/required-checks-baseline.json ausente');
  const required = requiredDoBaseline(JSON.parse(readFileSync(baselinePath, 'utf8')));
  const valor = pathsDeValor(readFileSync(join(ROOT, '.claude', 'rules', 'calculo-valor-estoque.md'), 'utf8'));

  const mainSha = gh(`repos/${REPO}/commits/main`)[0]?.sha;
  if (!mainSha) throw new NaoMedi('sha do main ilegível');
  const mainRuns = porNome(gh(`repos/${REPO}/commits/${mainSha}/check-runs?per_page=100`));
  const ruinsNoMain = new Set(required.filter((n) => RUIM.has(mainRuns.get(n)?.conclusion)));

  const lista = prsFiltro?.length
    ? prsFiltro.map((n) => gh(`repos/${REPO}/pulls/${n}`)[0])
    : gh(`repos/${REPO}/pulls?state=open&per_page=100`);
  const cache = lerCache();
  const itens = [];
  for (const p of lista) {
    const det = prsFiltro?.length ? p : gh(`repos/${REPO}/pulls/${p.number}`)[0];
    const sha = det.head.sha;
    let files = cache[sha];
    if (!files) {
      files = gh(`repos/${REPO}/pulls/${det.number}/files?per_page=100`).map((f) => ({ filename: f.filename, patch: (f.patch || '').slice(0, 20000) }));
      cache[sha] = files;
    }
    const checkRuns = gh(`repos/${REPO}/commits/${sha}/check-runs?per_page=100`);
    itens.push(classificar({ ...det, files, checkRuns }, { required, valor, ruinsNoMain }));
  }
  try { mkdirSync(dirname(CACHE), { recursive: true }); writeFileSync(CACHE, JSON.stringify(cache)); } catch { /* cache é otimização */ }
  return { mainSha, ruinsNoMain: [...ruinsNoMain], itens: serializar(itens) };
}

export function medirPlacar() {
  // Saída do filho vai pra ARQUIVO, não pipe: medido 2026-10-14, `placar.mjs --todos --json`
  // por pipe chega TRUNCADO em 145811 bytes (sai por process.exit antes de esvaziar o stdout
  // assíncrono); redirecionado para arquivo sai inteiro e parseia.
  const tmp = join(ROOT, '.claude', 'run', `gerente-fila-placar-${process.pid}.json`);
  mkdirSync(dirname(tmp), { recursive: true });
  const fd = openSync(tmp, 'w');
  try {
    execFileSync('node', [join(ROOT, 'scripts', 'qa', 'placar.mjs'), '--todos', '--json'], { stdio: ['ignore', fd, 'ignore'] });
  } catch { /* --check-less: sai ≠0 quando a lista não fecha; o JSON sai igual */ } finally { closeSync(fd); }
  const out = readFileSync(tmp, 'utf8');
  try { unlinkSync(tmp); } catch { /* scratch */ }
  let j;
  try { j = JSON.parse(out); } catch (e) { throw new NaoMedi(`placar --todos --json ilegível (${e.message.slice(0, 80)}; ${out.length} bytes)`); }
  let feito = 0, total = 0;
  const proximos = [];
  for (const m of j.modulos) {
    feito += m.feito; total += m.total;
    const ids = m.linhas.filter((l) => l.estado === 'proximo').map((l) => l.id);
    if (ids.length) proximos.push({ modulo: m.modulo, ids });
  }
  return { feito, total, proximos };
}

const ORDEM = ['MAIN', 'MERGE', 'CONFLITO', 'VERMELHO', 'W', 'ESPERA', 'MERGE·fila', 'RASCUNHO'];
const ACAO = {
  MAIN: 'consertar o main primeiro (bloqueia todos)',
  MERGE: 'mergear (1 por módulo)',
  CONFLITO: 'merge do main + regenerar derivados; `real` = ler',
  VERMELHO: 'ler o log do required que falhou',
  W: 'não mergear — listar pro [W]',
  ESPERA: 'nada; próxima rodada',
  'MERGE·fila': 'próxima rodada',
  RASCUNHO: 'ignorar',
};

export function emitirTexto(fila, placar) {
  const linhas = [`fila: ${fila.itens.length} PR(s) medidos · main ${fila.mainSha.slice(0, 9)}${fila.ruinsNoMain.length ? ` · VERMELHO NO MAIN: ${fila.ruinsNoMain.join(' · ')}` : ''}`];
  for (const lote of ORDEM) {
    const its = fila.itens.filter((i) => i.lote === lote);
    if (!its.length) continue;
    linhas.push(`${lote} (${its.length}) → ${ACAO[lote]}`);
    for (const i of its) linhas.push(`  #${i.n} [${i.modulo}] ${i.titulo.slice(0, 70)}${i.motivo ? ` — ${i.motivo}` : ''}`);
  }
  if (placar) {
    linhas.push(`placar: ${placar.feito} de ${placar.total} (${(100 * placar.feito / placar.total).toFixed(1)}%) · threads "próximo" por módulo (1 sessão por módulo):`);
    for (const p of placar.proximos) linhas.push(`  ${p.modulo}: ${p.ids.join(', ')}`);
  }
  return linhas.join('\n');
}

// ── selftest (funções puras, fixtures-armadilha) ────────────────────────────────────────
function selftest() {
  let falhas = 0;
  const ok = (c, msg) => { if (!c) { falhas++; console.error('FALHOU:', msg); } else console.log('ok -', msg); };
  const required = ['A', 'B'];
  const ctx = { required, valor: ['app/Utils/Util.php', 'resources/js/Pages/Sells/_components/*.tsx'], ruinsNoMain: new Set(['B']) };
  const run = (name, conclusion, status = 'completed') => ({ name, conclusion, status, started_at: '2026-10-14T00:00:00Z' });
  const pr = (o) => ({ number: 1, title: 't', draft: false, mergeable_state: 'clean', labels: [], files: ['Modules/Crm/x.php'], checkRuns: [run('A', 'success'), run('B', 'success')], created_at: '2026-10-14', ...o });
  ok(classificar(pr({}), ctx).lote === 'MERGE', 'tudo verde → MERGE');
  ok(classificar(pr({ files: ['app/Utils/Util.php'] }), ctx).lote === 'W', 'valor → W');
  ok(classificar(pr({ files: ['resources/js/Pages/Sells/_components/Cart.tsx'] }), ctx).lote === 'W', 'glob de valor casa');
  ok(classificar(pr({ files: ['Modules/Crm/Http/Controllers/XController.php'] }), ctx).lote === 'MERGE', 'controller de módulo NÃO é valor (glob amplo filtrado)');
  ok(classificar(pr({ files: [{ filename: 'Modules/X/Database/Migrations/a.php', patch: '+ $t->dropColumn("y");' }] }), ctx).lote === 'W', 'migration com drop → W');
  ok(classificar(pr({ files: [{ filename: 'Modules/X/Database/Migrations/a.php', patch: '- $t->dropColumn("y");' }] }), ctx).lote === 'MERGE', 'drop REMOVIDO não é drop');
  ok(classificar(pr({ title: 'liga flag [W]' }), ctx).lote === 'W', 'título [W] → W');
  ok(classificar(pr({ mergeable_state: 'dirty', files: ['Modules/Crm/SUPERFICIE.md'] }), ctx).motivo.startsWith('regenerar'), 'conflito só em derivado → regenerar');
  ok(classificar(pr({ mergeable_state: 'dirty' }), ctx).motivo === 'real', 'conflito em código → real');
  ok(classificar(pr({ checkRuns: [run('A', 'success'), run('B', 'failure')] }), ctx).lote === 'MAIN', 'falha que o main também tem → MAIN');
  ok(classificar(pr({ checkRuns: [run('A', 'failure'), run('B', 'success')] }), ctx).lote === 'VERMELHO', 'falha só do PR → VERMELHO');
  ok(classificar(pr({ checkRuns: [run('A', 'success')] }), ctx).lote === 'ESPERA', 'required ausente → ESPERA (não MERGE)');
  ok(classificar(pr({ checkRuns: [run('A', 'success'), run('B', null, 'in_progress')] }), ctx).lote === 'ESPERA', 'em execução → ESPERA');
  const rerun = [{ ...run('A', 'failure'), started_at: '2026-10-14T00:00:00Z' }, { ...run('A', 'success'), started_at: '2026-10-14T01:00:00Z' }, run('B', 'success')];
  ok(classificar(pr({ checkRuns: rerun }), ctx).lote === 'MERGE', 're-run mais recente vale');
  ok(classificar(pr({ checkRuns: [run('A', 'skipped'), run('B', 'success')] }), ctx).motivo.includes('skipped'), 'skipped é contado à parte');
  ok(classificar(pr({ draft: true }), ctx).lote === 'RASCUNHO', 'draft');
  const s = serializar([
    { n: 2, lote: 'MERGE', modulo: 'Crm', criado: '2026-10-14b' },
    { n: 1, lote: 'MERGE', modulo: 'Crm', criado: '2026-10-14a' },
    { n: 3, lote: 'MERGE', modulo: 'Ponto', criado: '2026-10-14c' },
  ]);
  ok(s.find((i) => i.n === 1).lote === 'MERGE' && s.find((i) => i.n === 2).lote === 'MERGE·fila' && s.find((i) => i.n === 3).lote === 'MERGE', '1 MERGE por módulo, o mais antigo');
  let lancou = false; try { requiredDoBaseline({}); } catch (e) { lancou = e instanceof NaoMedi; }
  ok(lancou, 'baseline vazio → NaoMedi (nunca "0 required = tudo verde")');
  const real = pathsDeValor(readFileSync(join(ROOT, '.claude', 'rules', 'calculo-valor-estoque.md'), 'utf8'));
  ok(real.includes('app/Utils/TransactionUtil.php') && !real.some((p) => p.startsWith('Modules/**')), 'paths de valor derivados da rule real');
  const realReq = requiredDoBaseline(JSON.parse(readFileSync(join(ROOT, 'governance', 'required-checks-baseline.json'), 'utf8')));
  ok(realReq.length > 10, `required derivados do baseline real (${realReq.length})`);
  console.log(falhas ? `${falhas} falha(s)` : 'selftest verde');
  process.exit(falhas ? 1 : 0);
}

const isMain = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isMain) {
  const argv = process.argv.slice(2);
  if (argv.includes('--selftest')) selftest();
  const i = argv.indexOf('--pr');
  const prs = i >= 0 ? argv.slice(i + 1).filter((a) => /^\d+$/.test(a)).map(Number) : null;
  try {
    const fila = medirFila(prs);
    const placar = argv.includes('--placar') ? medirPlacar() : null;
    if (argv.includes('--json')) console.log(JSON.stringify({ ...fila, placar }, null, 2));
    else console.log(emitirTexto(fila, placar));
  } catch (e) {
    if (e instanceof NaoMedi) { console.error(`NÃO MEDI: ${e.message}`); process.exit(2); }
    throw e;
  }
}
