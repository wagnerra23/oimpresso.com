#!/usr/bin/env node
// @ts-check
/**
 * screen-coverage-map.mjs — mapa de cobertura de QA por tela + baseline da catraca.
 *
 * Cruza as 4 camadas de garantia que uma tela Inertia pode ter:
 *   1. CHARTER   — contrato vivo (<Tela>.charter.md ao lado do .tsx)
 *   2. E2E       — referência da tela em tests/Browser/**.php (Pest 4 Browser/Playwright)
 *   3. SCORECARD — nota persistida (memory/governance/scorecards/screens/*.yaml)
 *   4. A11Y      — referência da tela em teste que injeta axe (heurística: "axe" no arquivo E2E)
 *
 * Saídas:
 *   - stdout: resumo por módulo + agregados (read-only, sem efeito colateral)
 *   - --json: escreve memory/governance/screen-coverage-baseline.json (o que a CATRACA lê)
 *
 * É o Passo 0 da sobrevivência (ADR proposto screen-qa-specialist-sustentavel):
 * a catraca de cobertura compara o estado de um PR contra este baseline e
 * BLOQUEIA se qualquer agregado regredir (telas cobertas só sobem).
 *
 * Uso:
 *   node scripts/qa/screen-coverage-map.mjs                    # mapa agregado (todas as telas)
 *   node scripts/qa/screen-coverage-map.mjs --json             # + grava baseline
 *   node scripts/qa/screen-coverage-map.mjs --check            # falha (exit 1) se regrediu vs baseline
 *   node scripts/qa/screen-coverage-map.mjs --screen Mod/Tela  # TODOS os arquivos de UMA tela + linkagem
 *                                                              #   (resolver: trio+scorecard+e2e+UC↔teste+
 *                                                              #    RUNBOOK/visual-comparison/proto-baseline com
 *                                                              #    FLAG de ambiguidade — reporta, não adivinha)
 *                                                              #   + PODE LIGAR? (charter `status:` × sinal de prod,
 *                                                              #     regra importada de scripts/lib/charter-signal.mjs)
 */

import { readFileSync, readdirSync, statSync, writeFileSync, existsSync } from 'node:fs';
import { join, relative, sep, basename } from 'node:path';
import assert from 'node:assert/strict';
import { isAuxiliaryPagePath, isPageScreenPath } from './page-path.mjs';
import { ucsDeclaredInCasos } from '../lib/uc-regex.mjs';
// Fonte ÚNICA da regra "o charter tem sinal de prod?" — a MESMA que o gate
// `charter-live-signal` (pune live sem sinal) e o `charter-promote-signal`
// (promove draft com sinal) consomem. Importada, nunca reimplementada: 3 cópias
// da mesma regra é a doença que `uc-regex.mjs` já documenta (4 drifts).
import { frontmatterDe, campo, sinalDe, carregarFontes, temPlaceholderAberto } from '../lib/charter-signal.mjs';

const ROOT = process.cwd();
const PAGES_DIR = join(ROOT, 'resources', 'js', 'Pages');
const BROWSER_DIR = join(ROOT, 'tests', 'Browser');
const VISREG_MANIFEST = join(BROWSER_DIR, 'visreg-screens.json');
const SCORECARD_DIR = join(ROOT, 'memory', 'governance', 'scorecards', 'screens');
const REQ_DIR = join(ROOT, 'memory', 'requisitos'); // onde vivem RUNBOOK/visual-comparison/proto-baseline (nome drifta)
const BASELINE = join(ROOT, 'memory', 'governance', 'screen-coverage-baseline.json');

const flags = new Set(process.argv.slice(2));

/** Lista recursiva de arquivos sob `dir` cujo nome casa `match`. */
function walk(dir, match, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    const st = statSync(full);
    if (st.isDirectory()) walk(full, match, acc);
    else if (match(full)) acc.push(full);
  }
  return acc;
}

export function isAuxiliaryScreenPath(relTsx) {
  return isAuxiliaryPagePath(relTsx);
}

// 1. Universo de telas: Pages/**/*.tsx, exceto diretórios auxiliares e testes.
const isScreen = (f) => isPageScreenPath(relative(PAGES_DIR, f));
const screens = walk(PAGES_DIR, isScreen);

// Declaração de escopo impressa junto do total: o número sozinho é ambíguo (qual denominador?).
// MESMO conjunto que o casos-coverage-guard enumera desde a reconciliação 2026-07-27 — as duas
// portas partilham `isPageScreenPath` (page-path.mjs). Antes divergiam em 45 (280 vs 235), todos
// arquivos de dir auxiliar que o casos-guard contava como tela. Nenhum número de cobertura de
// tela deve ser citado sem este escopo ao lado.
const ESCOPO_TELAS =
  '  ESCOPO (fonte única scripts/qa/page-path.mjs · idêntico ao casos:report):\n' +
  '    inclui: resources/js/Pages/**/<Sub>/<Tela>.tsx (Page Inertia executável)\n' +
  '    exclui: dirs auxiliares (_*, components, partials, hooks, utils, lib, types,\n' +
  '            constants, schemas, stores, contexts) · .tsx na raiz de Pages/ · *.charter.tsx · *.test.tsx';

// 2. Corpus de E2E (conteúdo concatenado pra busca de referência).
const browserFiles = walk(BROWSER_DIR, (f) => f.endsWith('.php'));
const browserCorpus = browserFiles
  .map((f) => ({ file: f, body: readFileSync(f, 'utf8') }))
  .map((x) => ({ ...x, hasAxe: /axe|accessibilit/i.test(x.body) }));
const visregSources = new Set(
  JSON.parse(readFileSync(VISREG_MANIFEST, 'utf8')).map((entry) => entry.source),
);

// 3. Scorecards existentes (slug modulo-tela).
const scorecards = new Set(
  walk(SCORECARD_DIR, (f) => f.endsWith('.yaml') || f.endsWith('.yml')).map((f) =>
    basename(f).replace(/\.(ya?ml)$/, '').toLowerCase(),
  ),
);

/**
 * Uma tela é "referenciada" por um E2E se o caminho relativo do Page (Mod/Tela)
 * aparece LITERALMENTE no body do teste — ex: `'Sells/Create'` no mapa do Pest Browser.
 * NÃO casamos por basename solto ('Index', 'Create', 'Cockpit'): um único teste citando
 * '/Index' creditaria todas as ~90 telas homônimas. Esse termo gerava 106 falsos-positivos
 * (e2e inflado de 4 → 110); a contagem honesta é só quem cita o caminho exato. Ver ADR 0249.
 */
function e2eFor(relTsx) {
  const key = relTsx.replace(/\.tsx$/, ''); // ex: Sells/Create
  const keyAlt = key.replace(/\//g, '\\');  // idem em refs com separador Windows
  return browserCorpus.filter((b) => b.body.includes(key) || b.body.includes(keyAlt));
}

export function inertiaSourcesFor(relTsx) {
  const pageSource = relTsx.replace(/\.tsx$/, '');
  return pageSource.endsWith('/Index')
    ? [pageSource, pageSource.slice(0, -'/Index'.length)]
    : [pageSource];
}

export function coverageRegressions(current, previous, currentCovered = {}, previousCovered = {}) {
  const decode = (value) => new Set((value ?? '').split('|').filter(Boolean));

  return ['charter', 'e2e', 'a11y', 'scorecard'].filter((key) => {
    if (current[key] < previous[key]) return true;
    const now = decode(currentCovered[key]);
    return [...decode(previousCovered[key])].some((screen) => !now.has(screen));
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// RESOLVER POR-TELA (--screen <Mod/Tela>) — "achar TODOS os arquivos da tela"
// ─────────────────────────────────────────────────────────────────────────────
// POR QUE: perguntar "quais arquivos a tela X TEM, e como linkam?" não tinha
// resposta única. O mapa agregado (acima) mede charter/e2e/scorecard EM MASSA, mas
// os artefatos de UMA tela vivem FRAGMENTADOS em 4+ ferramentas + convenções de nome
// que DRIFTAM (scorecard=full-path · RUNBOOK=tela · proto-baseline=rota · visual-
// comparison=livre). Refutado no caso real Produto/Create + Financeiro/Unificado
// (2026-07-18): 8 arquivos existem, o mapa acha 3 por 3 chaves diferentes; RUNBOOK
// tem 2 candidatos (index E unificado). Este modo UNIFICA + é HONESTO sobre o que não
// resolve por nome: reporta CANDIDATOS + FLAG de ambiguidade, NUNCA adivinha "o"
// arquivo (lápide §5 2026-06-30: proveniência vem da declaração do charter, não do
// filename). NÃO é gate — é resolver/report (reporta, humano decide).

/** screenSlug — slug do scorecard: caminho da tela em kebab. PURO. */
export function screenSlug(relTsx) {
  return relTsx.replace(/\.tsx$/, '').replace(/\//g, '-').toLowerCase();
}

/**
 * classifyArtifact — dado os candidatos (por nome) de UM tipo de artefato, diz se
 * resolve ÚNICO, está AUSENTE, ou é AMBÍGUO (>1 candidato = a máquina NÃO sabe qual é
 * "o" arquivo da tela → precisa DECLARAÇÃO, não adivinhação). PURO/testável — é o
 * núcleo que o selftest exercita (ambiguidade é o defeito que NADA mais pega hoje).
 * @param {string[]} candidates
 * @returns {{ status:'unique'|'missing'|'ambiguous', candidates:string[] }}
 */
export function classifyArtifact(candidates) {
  const status = candidates.length === 0 ? 'missing' : candidates.length === 1 ? 'unique' : 'ambiguous';
  return { status, candidates };
}

/**
 * resolveArtifact — proveniência EXPLÍCITA vence adivinhação por nome (lápide §5
 * 2026-06-30: a fonte de um artefato é o que o charter DECLARA, nunca a string do
 * filename). Se o charter declara o caminho → autoritativo (`declared`), e se o
 * caminho declarado NÃO existe → `declared-missing` (defeito REAL: linkagem quebrada,
 * que o name-match jamais pega). Sem declaração → cai pro name-match + fica marcado
 * `source:'name'` (o resolver diz "declare no charter pra travar"). PURO/testável.
 * @param {string|null} declaredPath
 * @param {boolean} declaredExists  o caminho declarado existe no disco?
 * @param {string[]} nameCandidates candidatos achados por nome (fallback)
 * @returns {{ source:'declared'|'name', status:string, candidates:string[] }}
 */
export function resolveArtifact(declaredPath, declaredExists, nameCandidates) {
  if (declaredPath) {
    return { source: 'declared', status: declaredExists ? 'declared' : 'declared-missing', candidates: [declaredPath] };
  }
  return { source: 'name', ...classifyArtifact(nameCandidates) };
}

/**
 * UCs declarados num casos.md (heading "## UC-XX ..."; ~~UC~~ tachado = retirado, não conta).
 * DELEGA pra fonte única `scripts/lib/uc-regex.mjs` — não reimplementar aqui. PURO.
 *
 * POR QUE DELEGA (2026-07-27): esta função tinha regex PRÓPRIO
 * (`/^(UC-[A-Z0-9]{0,8}-?\d{1,3})\b/i`) que DRIFOU da lib — faltava o `[a-zA-Z]?` do
 * sufixo, então `UC-DSR-08b` não casava NADA (o `\b` falha antes do `b`) e o UC sumia
 * INTEIRO, não truncado. MEDIDO no corpus (44 .casos.md): lib 181 UC × este 178, 1 arquivo
 * divergente (governance/DsRollout: UC-DSR-01b/04b/08b invisíveis). Efeito: dois gates
 * REQUIRED discordando do mesmo fato — o `casos-gate` (que usa a lib) cobrava Status+teste
 * dos 3, e o `npm run screen:files` não os enxergava. O `git grep ucHeadRe` da migração
 * anterior não achou este consumidor justamente porque ele NUNCA importou a lib.
 */
export function ucsFromCasos(content) {
  return ucsDeclaredInCasos(content || '');
}

const norm = (p) => relative(ROOT, p).replace(/\\/g, '/');

// Corpus de teste AMPLO (tests/ e2e/ Modules/**/Tests app/) — inclui o Pest de
// CONTRATO em tests/Feature (que o e2e-Browser do mapa não vê). Lazy: só no --screen.
function testCorpusText() {
  const dirs = ['tests', 'e2e', 'Modules', 'app'];
  let corpus = '';
  for (const d of dirs) {
    for (const f of walk(join(ROOT, d), (p) => /Test\.php$|\.spec\.[tj]sx?$|\.test\.[tj]sx?$/.test(p))) {
      try { corpus += '\n' + readFileSync(f, 'utf8'); } catch { /* ignore */ }
    }
  }
  return corpus;
}

/**
 * resolveScreenFiles — dado a tela, acha TODOS os arquivos + linkagem. Impuro (lê FS).
 * @param {string} relTsx caminho relativo a Pages/ com ou sem `.tsx` (ex: "Produto/Create")
 */
export function resolveScreenFiles(relTsx) {
  relTsx = relTsx.replace(/\.tsx$/, '') + '.tsx';
  const segs = relTsx.replace(/\.tsx$/, '').split('/');
  const mod = segs[0];
  const telaK = segs[segs.length - 1].toLowerCase();
  // Tela aninhada (Mod/Sub/Index): o nome REAL do artefato costuma ser o dir "Sub"
  // (rota/sub-view), não "index". Casar AMBAS as chaves surfa o drift — ex.
  // Financeiro/Unificado/Index: proto-baseline é `unificado.*`, não `index.*`.
  const nameKeys = segs.length >= 3 ? [telaK, segs[segs.length - 2].toLowerCase()] : [telaK];
  const abs = join(PAGES_DIR, relTsx);
  const charterPath = abs.replace(/\.tsx$/, '.charter.md');
  const casosPath = abs.replace(/\.tsx$/, '.casos.md');

  // Trio (siblings — RESOLUÇÃO CONFIÁVEL por caminho exato).
  const trio = { tsx: existsSync(abs), charter: existsSync(charterPath), casos: existsSync(casosPath) };

  // Scorecard (slug — confiável, mesma chave do mapa agregado).
  const slug = screenSlug(relTsx);
  const scorecard = classifyArtifact(
    walk(SCORECARD_DIR, (f) => /\.ya?ml$/.test(f) && basename(f).replace(/\.ya?ml$/, '').toLowerCase() === slug).map(norm),
  );

  // E2E Browser (path-literal — o que o mapa agregado credita; SÓ tests/Browser).
  const key = relTsx.replace(/\.tsx$/, '');
  const e2eBrowser = walk(BROWSER_DIR, (f) => f.endsWith('.php'))
    .filter((f) => { const b = readFileSync(f, 'utf8'); return b.includes(key) || b.includes(key.replace(/\//g, '\\')); })
    .map(norm);

  // UCs do casos.md + teste que cita o id (corpus AMPLO — inclui Pest de contrato).
  let ucLink = [];
  if (trio.casos) {
    const ucs = ucsFromCasos(readFileSync(casosPath, 'utf8'));
    const corpus = testCorpusText();
    ucLink = ucs.map((uc) => ({ uc, tested: corpus.includes(uc) }));
  }

  // O charter DECLARA seus artefatos? (proveniência explícita > adivinhação por nome).
  let relatedPrototype = null, declared = { runbook: null, visual_comparison: null, proto_baseline: null };
  // Eixo "pode ligar?" — status do charter + sinal de prod (regra importada, ver import).
  let prontidao = prontidaoDeLigar({ temCharter: false });
  if (trio.charter) {
    const ch = readFileSync(charterPath, 'utf8');
    const field = (k) => { const m = ch.match(new RegExp(`^${k}:\\s*(.+)$`, 'm')); return m ? m[1].trim() : null; };
    relatedPrototype = field('related_prototype') || '(ausente)';
    declared = { runbook: field('related_runbook'), visual_comparison: field('related_visual_comparison'), proto_baseline: field('related_proto_baseline') };
    const fm = frontmatterDe(ch);
    prontidao = prontidaoDeLigar({
      temCharter: true,
      status: campo(fm, 'status'),
      sinal: sinalDe(fm, carregarFontes(ROOT)),
      placeholder: temPlaceholderAberto(ch),
    });
  }

  // Resolução: DECLARAÇÃO primeiro (autoritativa; pega declaração QUEBRADA); fallback name-match + FLAG.
  const modFiles = existsSync(join(REQ_DIR, mod)) ? walk(join(REQ_DIR, mod), () => true).map(norm) : [];
  const nameHas = (f) => nameKeys.some((k) => basename(f).toLowerCase().includes(k));
  const declExists = (p) => !!p && existsSync(join(ROOT, p));
  const runbook = resolveArtifact(declared.runbook, declExists(declared.runbook), modFiles.filter((f) => /RUNBOOK/i.test(basename(f)) && nameHas(f)));
  const visualcomp = resolveArtifact(declared.visual_comparison, declExists(declared.visual_comparison), modFiles.filter((f) => /visual-comparison\.md$/.test(f) && nameHas(f)));
  const protobaseline = resolveArtifact(declared.proto_baseline, declExists(declared.proto_baseline), modFiles.filter((f) => /\.proto-baseline\.json$/.test(f) && nameHas(f)));

  return { screen: relTsx, mod, trio, scorecard, e2eBrowser, ucLink, runbook, visualcomp, protobaseline, relatedPrototype, prontidao };
}

/**
 * prontidaoDeLigar — "a tela pode ir pro ar?" no eixo CHARTER × SINAL DE PROD. PURO/testável.
 *
 * POR QUE AQUI: esta porta já responde "quais arquivos a tela tem"; o que faltava era o
 * eixo de ROLLOUT — `status:` do charter cruzado com o sinal de prod. As duas metades já
 * tinham dono (o trio/artefatos aqui; o sinal no `charter-live-signal`/`charter-promote-signal`),
 * mas ninguém cruzava as duas POR TELA — e responder isso à mão custava ler 4 fontes.
 * Estende o dono do tema em vez de abrir painel paralelo (é a regra do §5 2026-07-23).
 *
 * NÃO É GATE e NÃO AFIRMA VERDE: `--screen` continua saindo 0 em qualquer estado. "Ligada"
 * aqui significa *o charter diz live E a máquina tem sinal de prod* — nunca "funciona"
 * (isso é veredito de lane/smoke, que nenhum gerador pode afirmar).
 *
 * @returns {{estado:string, rotulo:string, comoDestravar:string|null}}
 */
export function prontidaoDeLigar({ temCharter, status = null, sinal = null, placeholder = false }) {
  if (!temCharter) {
    return { estado: 'sem_charter', rotulo: '✗ sem charter', comoDestravar: 'criar o charter da tela (node scripts/governance/criar-tela.mjs)' };
  }
  const fonte = sinal && sinal.fonte;
  const detalhe = !fonte ? ''
    : fonte === 'prod-flags' ? `prod-flags:biz=${sinal.biz.join(',')}`
    : fonte === 'route-hits' ? `route-hits:${sinal.hits}hit@${sinal.ultima_data}`
    : `smoke:${sinal.smoke}`;
  if (status === 'live') {
    return fonte
      ? { estado: 'ligada', rotulo: `✓ LIGADA (charter live · sinal ${detalhe})`, comoDestravar: null }
      // Mesmo buraco que o gate `charter-live-signal` persegue: diz live, a máquina não tem prova.
      : { estado: 'live_sem_sinal', rotulo: '⚠ diz LIVE mas SEM sinal de prod', comoDestravar: 'node scripts/governance/charter-live-signal.mjs --check <charter>' };
  }
  if (!fonte) {
    return {
      estado: 'sem_sinal',
      rotulo: `✗ NÃO pode ligar (charter \`${status ?? 'sem status'}\` · zero sinal de prod)`,
      comoDestravar: 'um dos 3: governance/prod-flags.json `live[<key>]` · hits>0 em governance/route-hits.json (route-hits:export) · campo `smoke:` datado no charter',
    };
  }
  if (placeholder) {
    // Mesma trava do promote: placeholder aberto espera revisão humana dos Non-Goals/Anti-hooks.
    return { estado: 'bloqueado_placeholder', rotulo: `⚠ tem sinal (${detalhe}) mas o charter tem placeholder aberto`, comoDestravar: '[W] preencher Non-Goals/Anti-hooks (TODO Wagner / ❌ TODO) antes de promover' };
  }
  return { estado: 'promovivel', rotulo: `→ PROMOVÍVEL (sinal ${detalhe})`, comoDestravar: 'node scripts/governance/charter-promote-signal.mjs --apply <charter>' };
}

// --screen <Mod/Tela> — resolve UMA tela e imprime tudo + linkagem, depois sai.
if (flags.has('--screen')) {
  const argIdx = process.argv.indexOf('--screen');
  const target = process.argv[argIdx + 1];
  if (!target) { console.error('uso: --screen <Mod/Tela>  (ex: Produto/Create)'); process.exit(2); }
  const r = resolveScreenFiles(target);
  const mark = (b) => (b ? '✓' : '✗');
  const artLine = (name, a) => {
    const body =
      a.status === 'declared' ? '✓ ' + a.candidates[0] + '  [declarado no charter — autoritativo]'
      : a.status === 'declared-missing' ? '✗ DECLARAÇÃO QUEBRADA: charter aponta pra ' + a.candidates[0] + ' (não existe)'
      : a.status === 'unique' ? '✓ ' + a.candidates[0] + '  [por nome — declare no charter pra travar]'
      : a.status === 'missing' ? '✗ ausente'
      : '⚠ AMBÍGUO (' + a.candidates.length + '): ' + a.candidates.join(' · ') + '  [por nome — declare no charter pra resolver]';
    return `  ${name.padEnd(18)} ${body}`;
  };
  console.log(`\n=== Arquivos da tela · ${r.screen} ===\n`);
  console.log('  TRIO (siblings, resolução confiável):');
  console.log(`    ${mark(r.trio.tsx)} .tsx   ${mark(r.trio.charter)} .charter.md   ${mark(r.trio.casos)} .casos.md`);
  console.log('');
  console.log(artLine('scorecard', r.scorecard));
  console.log(`  ${'e2e (Browser)'.padEnd(18)} ${r.e2eBrowser.length ? r.e2eBrowser.join(' · ') : '✗ nenhum teste Browser cita o path'}`);
  console.log(artLine('RUNBOOK', r.runbook));
  console.log(artLine('visual-comparison', r.visualcomp));
  console.log(artLine('proto-baseline', r.protobaseline));
  console.log('');
  console.log('  UC ↔ teste (corpus amplo, inclui Pest de contrato):');
  if (!r.ucLink.length) console.log('    (sem casos.md ou sem UC declarado)');
  for (const u of r.ucLink) console.log(`    ${mark(u.tested)} ${u.uc}${u.tested ? '' : '  ← ÓRFÃO (nenhum teste cita o id)'}`);
  console.log('');
  console.log(`  LINKAGEM charter → related_prototype: ${r.relatedPrototype ?? '(sem charter)'}`);
  console.log('');
  console.log('  PODE LIGAR? (charter × sinal de prod — NÃO é veredito de funcionamento):');
  console.log(`    ${r.prontidao.rotulo}`);
  if (r.prontidao.comoDestravar) console.log(`    destrava com: ${r.prontidao.comoDestravar}`);
  // Veredito honesto de COMPLETUDE + o que a máquina NÃO resolve sozinha.
  const artefatos = [['RUNBOOK', r.runbook], ['visual-comparison', r.visualcomp], ['proto-baseline', r.protobaseline]];
  const ambiguos = artefatos.filter(([, a]) => a.status === 'ambiguous').map(([n]) => n);
  const quebrados = artefatos.filter(([, a]) => a.status === 'declared-missing').map(([n]) => n);
  const orfaos = r.ucLink.filter((u) => !u.tested).map((u) => u.uc);
  console.log('\n  VEREDITO:');
  console.log(`    trio completo: ${r.trio.tsx && r.trio.charter && r.trio.casos ? '✓' : '✗ INCOMPLETO'}`);
  if (quebrados.length) console.log(`    ✗ DECLARAÇÃO QUEBRADA (charter aponta pra arquivo inexistente): ${quebrados.join(', ')}`);
  if (ambiguos.length) console.log(`    ⚠ nome ambíguo (resolva por declaração no charter, não por nome): ${ambiguos.join(', ')}`);
  if (orfaos.length) console.log(`    ⚠ UC órfão (sem teste): ${orfaos.join(', ')}`);
  if (!ambiguos.length && !orfaos.length && !quebrados.length) console.log('    ✓ sem declaração quebrada, ambiguidade de nome, nem UC órfão');
  process.exit(0);
}

if (flags.has('--selftest')) {
  assert.deepEqual(inertiaSourcesFor('Financeiro/Unificado/Index.tsx'), [
    'Financeiro/Unificado/Index',
    'Financeiro/Unificado',
  ]);
  assert.deepEqual(inertiaSourcesFor('Sells/Create.tsx'), ['Sells/Create']);
  assert.equal(isAuxiliaryScreenPath('Compras/components/Drawer.tsx'), true);
  assert.equal(isAuxiliaryScreenPath('Cliente/_drawer/AuditoriaTab.tsx'), true);
  assert.equal(isAuxiliaryScreenPath('Compras/Index.tsx'), false);
  assert.deepEqual(
    coverageRegressions(
      { charter: 10, e2e: 1, a11y: 1, scorecard: 10 },
      { charter: 10, e2e: 2, a11y: 1, scorecard: 10 },
    ),
    ['e2e'],
  );
  assert.deepEqual(
    coverageRegressions(
      { charter: 10, e2e: 2, a11y: 1, scorecard: 10 },
      { charter: 10, e2e: 2, a11y: 1, scorecard: 10 },
      { e2e: 'B.tsx|C.tsx' },
      { e2e: 'A.tsx|B.tsx' },
    ),
    ['e2e'],
  );
  // --- Resolver por-tela: classifyArtifact é o núcleo que MORDE (detecta ambiguidade) ---
  assert.equal(classifyArtifact([]).status, 'missing');
  assert.equal(classifyArtifact(['a.yaml']).status, 'unique');
  // CONTROLE-NEGATIVO (o defeito REAL Financeiro/RUNBOOK: 2 candidatos p/ 1 tela).
  // Sem esta asserção a detecção de ambiguidade poderia quebrar calada = teatro.
  assert.equal(classifyArtifact(['RUNBOOK-index.md', 'RUNBOOK-unificado.md']).status, 'ambiguous');
  // resolveArtifact: DECLARAÇÃO vence nome; declaração QUEBRADA é defeito real (só ela pega).
  assert.equal(resolveArtifact('memory/requisitos/X/RUNBOOK-x.md', true, ['a', 'b']).status, 'declared'); // declarado + existe → autoritativo (ignora os 2 candidatos por nome)
  assert.equal(resolveArtifact('memory/requisitos/X/RUNBOOK-x.md', false, []).status, 'declared-missing'); // CONTROLE-NEGATIVO: charter aponta pra arquivo inexistente = linkagem quebrada
  assert.equal(resolveArtifact(null, false, ['a', 'b']).status, 'ambiguous'); // sem declaração → cai pro name-match (2 = ambíguo)
  assert.equal(resolveArtifact(null, false, ['a', 'b']).source, 'name');
  // --- PODE LIGAR? (charter × sinal) — o eixo de rollout, com bite-test e controles ---
  const semSinal = { fonte: null, biz: [], hits: null, ultima_data: null, smoke: null };
  const comHit = { fonte: 'route-hits', biz: [], hits: 20, ultima_data: '2026-07-25', smoke: null };
  const comFlag = { fonte: 'prod-flags', biz: ['1'], hits: null, ultima_data: null, smoke: null };
  // MORDE: o estado REAL de 7 das 8 telas do Produto — charter draft, zero sinal → não pode ligar.
  assert.equal(prontidaoDeLigar({ temCharter: true, status: 'draft', sinal: semSinal }).estado, 'sem_sinal');
  // CONTROLE-NEGATIVO 1: draft COM sinal é promovível — se caísse em 'sem_sinal', o painel
  // esconderia tela pronta e viraria carimbo de "nada pode ligar".
  assert.equal(prontidaoDeLigar({ temCharter: true, status: 'draft', sinal: comHit }).estado, 'promovivel');
  // CONTROLE-NEGATIVO 2: live COM sinal é o estado bom (Produto/Index hoje) — não pode alarmar.
  assert.equal(prontidaoDeLigar({ temCharter: true, status: 'live', sinal: comFlag }).estado, 'ligada');
  // MORDE: live SEM sinal é o buraco que o gate charter-live-signal persegue — não pode virar '✓'.
  assert.equal(prontidaoDeLigar({ temCharter: true, status: 'live', sinal: semSinal }).estado, 'live_sem_sinal');
  // CONTROLE-NEGATIVO 3: placeholder aberto trava a promoção mesmo COM sinal (mesma regra do promote).
  assert.equal(prontidaoDeLigar({ temCharter: true, status: 'draft', sinal: comHit, placeholder: true }).estado, 'bloqueado_placeholder');
  assert.equal(prontidaoDeLigar({ temCharter: false }).estado, 'sem_charter');
  // O rótulo NUNCA afirma funcionamento — só charter+sinal (o veredito de verde é da lane).
  assert.equal(/funciona|verde|testad/i.test(prontidaoDeLigar({ temCharter: true, status: 'live', sinal: comFlag }).rotulo), false);
  // A regra de sinal vem da lib (fonte única) — 3 fontes, ordem prod-flags > route-hits > smoke.
  assert.equal(sinalDe('component: resources/js/Pages/X/Y.tsx\nsmoke: 2026-07-01', { live: {}, hits: {} }).fonte, 'smoke');
  assert.equal(sinalDe('component: resources/js/Pages/X/Y.tsx', { live: { 'X/Y': ['1'] }, hits: { 'X/Y': { hits: 3 } } }).fonte, 'prod-flags');
  assert.equal(sinalDe('component: resources/js/Pages/X/Y.tsx', { live: {}, hits: { 'X/Y': { hits: 0 } } }).fonte, null); // hits:0 NÃO é sinal
  assert.equal(temPlaceholderAberto('## Non-Goals\n- TODO Wagner\n'), true);
  assert.equal(temPlaceholderAberto('## Non-Goals\n- ❌ CRUD inline\n'), false); // ❌ sozinho é Non-Goal preenchido, não placeholder
  assert.equal(screenSlug('Financeiro/Unificado/Index.tsx'), 'financeiro-unificado-index');
  assert.equal(screenSlug('Produto/Create.tsx'), 'produto-create');
  // UC: heading conta; tachado ~~UC~~ (retirado, padrão da Maiara em Create.casos.md) NÃO conta.
  assert.deepEqual(ucsFromCasos('## UC-PCAD-01 x\n## ~~UC-PCAD-02~~ retirado\n## UC-PCAD-04 y\n'), ['UC-PCAD-01', 'UC-PCAD-04']);
  // CONTROLE-NEGATIVO do drift que o regex local tinha (2026-07-27): sufixo de LETRA.
  // Sem `[a-zA-Z]?` o `\b` falha antes do `b` e o UC some INTEIRO (não truncado) — era o
  // caso real governance/DsRollout (UC-DSR-01b/04b/08b: 3 invisíveis aqui, visíveis no
  // casos-gate). Esta asserção é o que faz a delegação pra scripts/lib/uc-regex.mjs MORDER:
  // reintroduzir regex local sem o sufixo derruba o gate em vez de sumir com UC calado.
  assert.deepEqual(ucsFromCasos('## UC-DSR-08b acesso\n## UC-DSR-09 outro\n'), ['UC-DSR-08B', 'UC-DSR-09']);
  // Prefixo hifenado longo (UC-FORJA-01/UC-KBV2-01) — a razão de a lib existir (4 regex drifados).
  assert.deepEqual(ucsFromCasos('## UC-KBV2-01 x\n## UC-FORJA-01 y\n'), ['UC-KBV2-01', 'UC-FORJA-01']);
  console.log('screen-coverage selftest: aliases Inertia + resolver por-tela (classifyArtifact/screenSlug/ucsFromCasos) passaram');
  process.exit(0);
}

const rows = screens.map((abs) => {
  const relTsx = relative(PAGES_DIR, abs).split(sep).join('/');
  const mod = relTsx.split('/')[0];
  const charter = existsSync(abs.replace(/\.tsx$/, '.charter.md'));
  const e2e = e2eFor(relTsx);
  const hasVisregContract = inertiaSourcesFor(relTsx).some((source) => visregSources.has(source));
  const slug = screenSlug(relTsx);
  return {
    screen: relTsx,
    module: mod,
    charter,
    e2e: e2e.length > 0 || hasVisregContract,
    a11y: e2e.some((b) => b.hasAxe),
    scorecard: scorecards.has(slug),
  };
});

// Agregados.
const total = rows.length;
const pct = (n) => (total ? Math.round((n / total) * 1000) / 10 : 0);
const agg = {
  total,
  charter: rows.filter((r) => r.charter).length,
  e2e: rows.filter((r) => r.e2e).length,
  a11y: rows.filter((r) => r.a11y).length,
  scorecard: rows.filter((r) => r.scorecard).length,
};
const coveredScreens = Object.fromEntries(
  ['charter', 'e2e', 'a11y', 'scorecard'].map((key) => [
    key,
    rows.filter((row) => row[key]).map((row) => row.screen).sort().join('|'),
  ]),
);

// Por módulo.
const byModule = {};
for (const r of rows) {
  const m = (byModule[r.module] ??= { total: 0, charter: 0, e2e: 0, a11y: 0, scorecard: 0 });
  m.total++;
  if (r.charter) m.charter++;
  if (r.e2e) m.e2e++;
  if (r.a11y) m.a11y++;
  if (r.scorecard) m.scorecard++;
}

// --- Relatório stdout ---
console.log(`\n=== Mapa de cobertura QA-de-tela · ${total} telas ===\n`);
console.log(ESCOPO_TELAS + '\n');
console.log(`  CHARTER (contrato)   : ${agg.charter}/${total}  (${pct(agg.charter)}%)`);
console.log(`  E2E (Pest Browser)   : ${agg.e2e}/${total}  (${pct(agg.e2e)}%)`);
console.log(`  A11Y (axe no E2E)    : ${agg.a11y}/${total}  (${pct(agg.a11y)}%)`);
console.log(`  SCORECARD (nota)     : ${agg.scorecard}/${total}  (${pct(agg.scorecard)}%)\n`);

const mods = Object.entries(byModule).sort((a, b) => b[1].total - a[1].total);
console.log('  Módulo'.padEnd(22) + 'Telas  Charter  E2E  Score');
for (const [m, s] of mods) {
  console.log(
    '  ' + m.padEnd(20) + String(s.total).padStart(5) + String(s.charter).padStart(9) + String(s.e2e).padStart(5) + String(s.scorecard).padStart(7),
  );
}

// --- Baseline / catraca ---
const snapshot = {
  generated_note: 'baseline da catraca de cobertura — NÃO editar à mão',
  aggregates: agg,
  covered_screens: coveredScreens,
  by_module: byModule,
};

if (flags.has('--json')) {
  writeFileSync(BASELINE, JSON.stringify(snapshot, null, 2) + '\n');
  console.log(`\n✓ baseline gravado em ${relative(ROOT, BASELINE)}`);
}

if (flags.has('--check')) {
  if (!existsSync(BASELINE)) {
    console.error('\n✗ baseline ausente — rode com --json primeiro.');
    process.exit(2);
  }
  const previousSnapshot = JSON.parse(readFileSync(BASELINE, 'utf8'));
  const prev = previousSnapshot.aggregates;
  const regress = coverageRegressions(agg, prev, coveredScreens, previousSnapshot.covered_screens);
  if (regress.length) {
    console.error(`\n✗ CATRACA: cobertura regrediu em ${regress.join(', ')} (vs baseline). PR bloqueado.`);
    for (const k of regress) console.error(`   ${k}: ${prev[k]} → ${agg[k]}`);
    process.exit(1);
  }
  console.log('\n✓ CATRACA: nenhuma regressão de cobertura.');
}
