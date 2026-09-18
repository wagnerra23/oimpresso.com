#!/usr/bin/env node
// scripts/tests/foundation-ratchet.mjs — catracas "só diminui" da fundação de testes (SDD Semana 0 · FV-Q1).
//
// POR QUE (memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md §4):
// a full-suite nunca rodou verde em DB real. Antes da nightly diagnóstica medir, congelamos
// 3 contadores REAIS (medidos no repo, não no plano — regra anti-stale) pra nenhum PR piorar:
//   n_quarantine        — marcadores `legacy-quarantine` (burn-down: subir = regressão)
//   n_refresh_database  — ARQUIVOS que APLICAM o trait RefreshDatabase — `uses(...RefreshDatabase::class)`
//                         ou `use [...\]RefreshDatabase;` (alvo: DatabaseTransactions). MENÇÃO da palavra
//                         em comentário/docstring/string de skip NÃO conta (conserto raiz FV-Q1, ADR 0275).
//   n_business_first    — ocorrências de Business::first() cru em teste (alvo: trait WithSeededTenant)
//
// CONVENÇÃO QUARENTENA (hard-fail, independe de baseline): todo marcador exige
// `quarantine-reason: <motivo>` a ≤3 linhas. Quarentena sem razão escrita é proibida.
//
// ⚠️ HOMÔNIMO — `n_quarantine` nomeia DOIS contadores no repo, e eles NÃO são comparáveis.
// Medido 2026-09-18, os dois rodados no mesmo commit (`--json` daqui × `sdd-scorecard.mjs`):
//   • ESTE (foundation-ratchet) = 126. Unidade = MARCADOR: incrementa uma vez por LINHA que
//     casa o MARKER (`measure()` abaixo faz `lines.forEach`), logo um arquivo com quarentena
//     granular conta várias vezes. Só `legacy-quarantine`, e só como ANOTAÇÃO ancorada.
//     Espalhados em ~25 arquivos — número que o comentário do scorecard confirma por outra
//     via, ao registrar que a medição PRÉ-flip dele (só `legacy-quarantine`) dava 25 arquivos.
//   • scripts/governance/sdd-scorecard.mjs::measureQuarantine = 250. Unidade = ARQUIVO, e o
//     critério é substring crua `legacy-quarantine` OU `era-sqlite` em qualquer lugar do .php,
//     sem exigir razão escrita. Decomposto: 25 arquivos pelo `legacy-quarantine` (os mesmos
//     que este ratchet cobre) + 225 que entram SÓ por `era-sqlite`, marcador que este aqui
//     não conta por desenho (ampliação decidida por [W] em 2026-08-17).
// Como a unidade difere (marcador × arquivo), "126 < 250" não diz nada sobre severidade —
// são grandezas distintas com o mesmo nome, e mexer numa não move a outra.
// Um TERCEIRO contador vizinho, `n_lane_quarantine` (42), é o da exclusão de lane — está
// documentado no bloco `laneQuarantineFiles` mais abaixo e não se confunde com nenhum destes.
// Qual deles alimenta check required é pergunta pro governance/required-checks-baseline.json,
// não pra este comentário.
//
// Determinístico, Node puro, sem MySQL, segundos. Espelha os ratchets do projeto (a11y/reuse/no-mock).
// SUBIR baseline = SÓ `--write --force` (diff visível no PR — ex.: quarentena em massa Q3 planejada).
//
// USO:
//   node scripts/tests/foundation-ratchet.mjs            # gate vs baseline (+ job summary se em CI)
//   node scripts/tests/foundation-ratchet.mjs --json     # contadores em JSON
//   node scripts/tests/foundation-ratchet.mjs --write    # (re)grava baseline — só pra DESCER
//   ... --root <dir> --baseline <file>                   # fixtures/selftest (mesmo code path)

import { appendFileSync, existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';

const args = process.argv.slice(2);
const opt = (n) => { const i = args.indexOf(n); return i >= 0 ? args[i + 1] : null; };
const ROOT = opt('--root') || process.cwd();
const BASELINE = opt('--baseline') || join(ROOT, 'scripts/tests/baselines/foundation-ratchet-baseline.json');

// MARKER — o `@group legacy-quarantine` do PHPDoc só conta como ANOTAÇÃO real (início de linha
// de docblock: `* @group …`), NÃO como MENÇÃO em prosa ("…é @group legacy-quarantine, fora de lane"
// falando de OUTRO teste). Sem essa âncora, a menção em docstring inflava n_quarantine (FORMA, não
// USO — mesmo falso-positivo que o refreshDatabaseTraitUsed já corrige; ADR 0275 métrica honesta).
// Pego 2026-07-08: ClienteImport/MapInertiaTest citavam Wave1*Test no docblock → +2 falso-positivo.
const MARKER = /(?:^|\n)[ \t]*\*?[ \t]*@group[ \t]+legacy-quarantine\b|#\[Group\(['"]legacy-quarantine['"]\)\]|->group\(['"]legacy-quarantine['"]/;
const REASON = /quarantine-reason:\s*\S/;

// USO REAL do trait RefreshDatabase (≠ MENÇÃO). Conta só quem APLICA o trait:
//   `uses(...RefreshDatabase::class...)` (Pest) ou `use [...\]RefreshDatabase;` (import/trait-use).
// Remove comentários antes (docblock /* */ + linha //) pra não casar a docstring que explica POR QUE o
// teste EVITA o trait (padrão era-sqlite). String literal sobrevive ao strip (ex.: `->skip('… RefreshDatabase …')`)
// mas não casa `uses(`/`use …;`, então não conta. Conserta a raiz do FV-Q1: o `\bRefreshDatabase\b` cru
// contava ~50 falsos positivos (menção em comentário), medindo FORMA e não USO real (ADR 0275 — métrica honesta).
function refreshDatabaseTraitUsed(src) {
  const code = src.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/[^\n]*/g, '');
  return /\buses\s*\([^)]*\bRefreshDatabase\b/.test(code)
    || /^\s*use\s+[\w\\]*\bRefreshDatabase\b/m.test(code);
}

// Roots canônicos: tests/ + Modules/<X>/Tests/. Comparação case-insensitive + readdir
// (cada dir REAL visitado 1×) — imune ao alias NTFS Tests/tests que duplicaria contagem.
function testRoots(root) {
  const roots = [];
  if (existsSync(join(root, 'tests'))) roots.push(join(root, 'tests'));
  const mods = join(root, 'Modules');
  if (!existsSync(mods)) return roots;
  for (const m of readdirSync(mods, { withFileTypes: true })) {
    if (!m.isDirectory()) continue;
    for (const e of readdirSync(join(mods, m.name), { withFileTypes: true }))
      if (e.isDirectory() && e.name.toLowerCase() === 'tests') roots.push(join(mods, m.name, e.name));
  }
  return roots;
}
function* phpFiles(dir) {
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    if (e.isDirectory()) yield* phpFiles(join(dir, e.name));
    else if (e.name.endsWith('.php')) yield join(dir, e.name);
  }
}

/**
 * QUARENTENA DE LANE — a segunda quarentena, que não tinha catraca nenhuma.
 *
 * São DOIS mecanismos distintos, e até 2026-09-18 só um era vigiado:
 *   1. marcador `@group legacy-quarantine` DENTRO do .php — contado por `n_quarantine`,
 *      catracado aqui, exige `quarantine-reason:` a ≤3 linhas.
 *   2. exclusão de lane em `.github/*-quarantine.list` — o arquivo que a lane subtrai da
 *      árvore (`find Modules/<X>/Tests` MENOS esta lista). **Nada contava.**
 *
 * POR QUE IMPORTA (medido 2026-09-18): as duas listas vivas somam 42 testes fora do CI de PR,
 * e as duas são caminho de dinheiro — `financeiro-pest-quarantine.list` (22) e
 * `estoque-pest-quarantine.list` (20). Quatro entradas do Estoque são defeito REAL confirmado
 * aguardando decisão [W]: custo vazando para quem não tem `view_purchase_price`, corte
 * silencioso em 200 sem paginação, writer tratando ausência de chave como zero em
 * `enable_stock` (eixo ESTOQUE), e lote de bulk-edit revertido por `ErrorException` engolida
 * (eixo VALOR). Sem catraca, a lista pode crescer em silêncio — que é literalmente o que o
 * cabeçalho dela proíbe em prosa: *"TIRAR DAQUI é o movimento desejado (a lista deve
 * encolher). PÔR aqui exige motivo escrito na linha — sem isso vira gaveta de silenciar
 * vermelho."* A regra existia; nenhuma máquina a cobrava.
 *
 * FP MEDIDO ANTES DE ARMAR (regra "LIGUE A MÁQUINA" item 4): 42 de 42 entradas JÁ têm motivo
 * escrito na linha — a disciplina vinha sendo cumprida à mão. Logo a catraca nasce VERDE e
 * não reprova nada que exista hoje; ela só impede a PRÓXIMA entrada silenciosa.
 *
 * Crescimento legítimo continua possível pelo mesmo caminho dos outros 3 contadores:
 * `--write --force`, visível no diff do PR. Advisory, como o resto deste gate — a §5
 * 2026-07-01 proíbe re-promover o `foundation-ratchet` a required sem reabrir a ADR 0314.
 */
export function laneQuarantineFiles(root) {
  const dir = join(root, '.github');
  if (!existsSync(dir)) return [];
  return readdirSync(dir, { withFileTypes: true })
    .filter((e) => e.isFile() && /-quarantine\.list$/.test(e.name))
    .map((e) => join(dir, e.name))
    .sort();
}

function measure(root) {
  const counters = { n_quarantine: 0, n_refresh_database: 0, n_business_first: 0, n_lane_quarantine: 0 };
  const semRazao = [];

  for (const lista of laneQuarantineFiles(root)) {
    const rel = relative(root, lista).replace(/\\/g, '/');
    readFileSync(lista, 'utf8').split('\n').forEach((linha, i) => {
      if (/^\s*(#|$)/.test(linha)) return;          // comentário e linha vazia não são entrada
      counters.n_lane_quarantine++;
      // MOTIVO = comentário na PRÓPRIA linha. É o que a lista exige de si mesma; sem ele a
      // entrada é indistinguível de "silenciei um vermelho e não disse por quê".
      if (!/#\s*\S/.test(linha)) semRazao.push(`${rel}:${i + 1}`);
    });
  }

  for (const tr of testRoots(root)) for (const f of phpFiles(tr)) {
    const src = readFileSync(f, 'utf8');
    if (refreshDatabaseTraitUsed(src)) counters.n_refresh_database++;
    counters.n_business_first += (src.match(/\bBusiness::first\s*\(/g) || []).length;
    if (!MARKER.test(src)) continue;
    const lines = src.split('\n');
    lines.forEach((line, i) => {
      if (!MARKER.test(line)) return;
      counters.n_quarantine++;
      if (!REASON.test(lines.slice(Math.max(0, i - 3), i + 4).join('\n')))
        semRazao.push(`${relative(root, f).replace(/\\/g, '/')}:${i + 1}`);
    });
  }
  return { counters, semRazao };
}

const { counters, semRazao } = measure(ROOT);

if (args.includes('--json')) {
  console.log(JSON.stringify({ counters, quarantine_sem_razao: semRazao }, null, 2));
  process.exit(0);
}

if (args.includes('--write')) {
  let prev = null;
  try { prev = JSON.parse(readFileSync(BASELINE, 'utf8')).counters; } catch { /* 1ª medição */ }
  const sobe = prev ? Object.keys(counters).filter((k) => counters[k] > prev[k]) : [];
  if (sobe.length && !args.includes('--force')) {
    console.error(`✗ --write recusado: ${sobe.join(', ')} SUBIRIA. Catraca só desce. Subida planejada (ex.: quarentena em massa Q3) = --write --force, visível no diff do PR.`);
    process.exit(1);
  }
  // PRESERVA AS CHAVES IRMÃS. O `--write` gravava `{generated_by, counters}` e descartava
  // TODO o resto — silenciosamente, com exit 0. Não era hipótese: em 2026-09-18 destruiu as 3
  // `nota_*` deste baseline em 5 branches de 3 sessões diferentes, e a ironia fecha o
  // diagnóstico — a nota apagada era exatamente a que DOCUMENTAVA este comportamento e
  // prescrevia a receita de restaurá-lo à mão. O aviso foi destruído pelo que ele avisava.
  //
  // Passou despercebido porque `git diff --stat` mostra `2 +-` (a contagem de LINHAS não
  // denuncia perda de CHAVE) — quem conferiu pelo `--stat` viu um diff inocente.
  //
  // Merge em vez de replace: `counters` e `generated_by` são DESTE script e se sobrescrevem;
  // qualquer outra chave é de quem a escreveu e sobrevive. A ordem (`generated_by, counters`,
  // depois as irmãs) mantém o diff mínimo contra o arquivo existente.
  let anterior = {};
  try { anterior = JSON.parse(readFileSync(BASELINE, 'utf8')); } catch { /* 1ª medição: sem irmãs */ }
  const { generated_by: _gb, counters: _c, ...irmas } = anterior;

  mkdirSync(dirname(BASELINE), { recursive: true });
  writeFileSync(BASELINE, JSON.stringify({
    generated_by: 'scripts/tests/foundation-ratchet.mjs --write',
    counters,
    ...irmas,
  }, null, 2) + '\n');
  const nomes = Object.keys(irmas);
  console.log(`✓ baseline gravado: ${JSON.stringify(counters)}${sobe.length ? ' (FORÇADO pra cima — justifique no PR)' : ''}`);
  // Dizer O QUE sobreviveu é metade do conserto: o defeito antigo era destrutivo E MUDO.
  if (nomes.length) console.log(`  ↳ ${nomes.length} chave(s) irmã(s) preservada(s): ${nomes.join(', ')}`);
  process.exit(0);
}

// gate (default)
let baseline;
try { baseline = JSON.parse(readFileSync(BASELINE, 'utf8')).counters; }
catch { console.error(`✗ baseline ausente/ilegível (${BASELINE}). Rode: node scripts/tests/foundation-ratchet.mjs --write`); process.exit(2); }

const rows = Object.keys(baseline).map((k) => {
  const cur = counters[k] ?? 0; const base = baseline[k];
  return { k, base, cur, status: cur > base ? 'SUBIU' : cur < base ? 'desceu' : 'ok' };
});
const pioras = rows.filter((r) => r.status === 'SUBIU');
const fail = pioras.length > 0 || semRazao.length > 0;

if (process.env.GITHUB_STEP_SUMMARY) {
  const md = ['## Foundation ratchet (advisory · FV-Q1)', '', '| contador | baseline | atual | Δ |', '|---|---:|---:|---|',
    ...rows.map((r) => `| ${r.k} | ${r.base} | ${r.cur} | ${r.cur > r.base ? `🔴 +${r.cur - r.base}` : r.cur < r.base ? `🟢 −${r.base - r.cur}` : '—'} |`),
    semRazao.length ? `\n🔴 **quarentena sem razão escrita** (${semRazao.length}) — \`quarantine-reason:\` no marcador, \`#\` na linha da \`*-quarantine.list\`: ${semRazao.join(' · ')}` : ''].join('\n');
  appendFileSync(process.env.GITHUB_STEP_SUMMARY, md + '\n');
}

for (const r of rows) console.log(`  ${r.status === 'SUBIU' ? '✗' : '✓'} ${r.k}: ${r.cur} (baseline ${r.base})`);
if (semRazao.length) console.error(`✗ quarentena SEM razão escrita (marcador legacy-quarantine: \`quarantine-reason:\` a ≤3 linhas · entrada de *-quarantine.list: comentário \`#\` na própria linha):\n  ${semRazao.join('\n  ')}`);
if (fail) {
  if (pioras.length) console.error(`✗ catraca FALHOU — fundação piorou: ${pioras.map((r) => `${r.k} ${r.base}→${r.cur}`).join(', ')}. Não adicione RefreshDatabase/Business::first() cru em teste novo (use DatabaseTransactions / trait de tenant seedado).`);
  process.exit(1);
}
const ganho = rows.filter((r) => r.status === 'desceu');
console.log(`✓ foundation ratchet OK${ganho.length ? ` — ↓ ${ganho.map((r) => r.k).join(', ')}: rode --write pra travar o ganho` : ''}.`);
