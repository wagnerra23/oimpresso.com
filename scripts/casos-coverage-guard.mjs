#!/usr/bin/env node
// scripts/casos-coverage-guard.mjs — Gate G-1 (trio-de-tela) + G-2 (rastreabilidade caso↔teste)
//                                    da Governança executável (ADR 0264).
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// Wagner 2026-06-09: "se não tiver testes vai desandar… se isso ficar apenas em memória
// sem uma regra obrigando fazer vai morrer no tempo." As 4 camadas que seguram drift
// (charter/casos/teste/proibições) viram MÁQUINA, não disciplina (ADR 0264, lei da
// catraca ADR 0256). Censo @main 2026-06-09: 277 páginas roteadas, 138 charter, 1 casos.md.
//
// Este guard cobre 4 camadas:
//   G-1 TRIO-DE-TELA  : toda .tsx ROTEADA em resources/js/Pages/** (exclui _components/)
//                       DEVE ter, ao lado, <Nome>.charter.md E <Nome>.casos.md.
//   G-2 RASTREABILIDADE: todo UC-* declarado num *.casos.md DEVE ser citado por >=1 teste
//                       (string do ID em Tests/**, tests/**, ou spec Playwright e2e/**).
//                       UC órfão (caso no papel sem teste que o defenda) = violação.
//   G-5 METADATA VIVA : cada *.casos.md DEVE carregar `owner` (quem fez) + `last_run`
//                       (quando, data) + `Status:` por UC (se está ativa/passa). É o que
//                       faz a spec "saber de si" e não apodrecer. Trava PRESENÇA.
//   G-6 FRESCOR       : se o <Nome>.tsx tem commit MAIS NOVO que o `last_run`, os casos
//                       estão STALE (tela mudou sem revalidar). Sinal amarrado à mudança
//                       de CÓDIGO via git (melhor que wall-clock). Resolve "last_run mente".
//   G-7 STATUS DERIVADO (Salto #2): o `Status: ✅` declarado por UC tem que bater com o
//                       VEREDITO REAL do teste (manifesto scripts/casos-test-results.json,
//                       gerado por `casos:results` a partir do JUnit). ✅ + teste-falhou =
//                       lies; ✅ sem teste verde = unverified. Fecha "o Status pode mentir".
//
// =====================================================================================
// RATCHET / BASELINE — gêmeo de pageheader-migration-guard.mjs + no-mock-in-prod.mjs
// =====================================================================================
// O baseline (scripts/casos-coverage-baseline.json) fotografa as violações ATUAIS (o
// débito legado). O gate NÃO obriga limpar o legado de uma vez (quebraria o repo — são
// 276 telas sem casos.md). Só impede PIORAR: violação NOVA (não no baseline) → exit 1.
// Tela nova nasce com trio; UC novo nasce com teste. Encolher o débito é sempre OK
// (F3 ratchet: cada tela tocada fecha o trio dela → baseline cai até 0).
//
// MODOS (idioma dos guards existentes):
//   node scripts/casos-coverage-guard.mjs                  # valida vs baseline (exit 1 se piorou)
//   node scripts/casos-coverage-guard.mjs --write-baseline  # (re)grava baseline (uso consciente)
//   node scripts/casos-coverage-guard.mjs --check-baseline-shrink <ref.json>  # só-desce: baseline commitado não pode crescer vs referência (Onda Q2)
//   node scripts/casos-coverage-guard.mjs --report          # imprime o relatório de dívida (humano)
//   node scripts/casos-coverage-guard.mjs --json            # saída JSON pra CI
//
// Refs: ADR 0264 (governança executável G-1/G-2) · ADR 0261 (enforcement faseado) ·
//       ADR 0256 (catraca knowledge-survival) · ADR 0243 casos.md (Index.casos.md Oficina).

import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { resolve, join, dirname, basename, relative } from 'node:path';
import { execSync } from 'node:child_process';

const ROOT = process.cwd();
const PAGES_DIR = resolve(ROOT, 'resources/js/Pages');
const BASELINE_PATH = resolve(ROOT, 'scripts/casos-coverage-baseline.json');
const MANIFEST_PATH = resolve(ROOT, 'scripts/casos-test-results.json'); // G-7: veredito por-UC (Salto #2)
const TEST_DIRS = ['Modules', 'tests', 'app', 'e2e']; // onde um UC-id pode ser referenciado por teste

const MODE_WRITE = process.argv.includes('--write-baseline');
// --check-baseline-shrink <old-baseline.json> — Onda Q2 (ratchet só-desce): o ARQUIVO
// de baseline commitado só pode ENCOLHER vs a referência (main). Crescimento consciente
// (refactor que move tela com dívida) = label `casos-baseline-grow-approved` no PR
// (o workflow pula este check). Git-free: o CI extrai a referência via `git show`.
const SHRINK_IDX = process.argv.indexOf('--check-baseline-shrink');
const SHRINK_REF_PATH = SHRINK_IDX >= 0 ? process.argv[SHRINK_IDX + 1] || null : null;
const MODE_REPORT = process.argv.includes('--report');
const MODE_JSON = process.argv.includes('--json');

const norm = (p) => relative(ROOT, p).replace(/\\/g, '/');

// ---------------------------------------------------------------------------
// Coleta de arquivos
// ---------------------------------------------------------------------------
function walk(dir, filter, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, e.name);
    if (e.isDirectory()) {
      if (e.name === 'node_modules' || e.name === 'vendor' || e.name === '.git') continue;
      walk(full, filter, acc);
    } else if (e.isFile() && filter(full, e.name)) {
      acc.push(full);
    }
  }
  return acc;
}

// "Página roteada" = .tsx em Pages/** que NÃO está sob /_components/ e não é um arquivo
// de teste/charter/casos. É a heurística literal do handoff (ADR 0264 G-1). O refino de
// "roteada de verdade" (cruzar com routes) é F3 — por ora o baseline absorve o conjunto.
function listPages() {
  const tsx = walk(PAGES_DIR, (full, name) => name.endsWith('.tsx') && !name.endsWith('.d.ts'));
  return tsx
    .filter((f) => !norm(f).includes('/_components/'))
    .map((f) => norm(f))
    .sort((a, b) => a.localeCompare(b));
}

// ---------------------------------------------------------------------------
// G-1 — trio-de-tela
// ---------------------------------------------------------------------------
function trioViolations(pages) {
  const violations = [];
  for (const page of pages) {
    const dir = dirname(page);
    const base = basename(page, '.tsx');
    const charter = `${dir}/${base}.charter.md`;
    const casos = `${dir}/${base}.casos.md`;
    if (!existsSync(resolve(ROOT, charter))) violations.push(`trio:missing-charter:${page}`);
    if (!existsSync(resolve(ROOT, casos))) violations.push(`trio:missing-casos:${page}`);
  }
  return violations;
}

// ---------------------------------------------------------------------------
// G-2 — rastreabilidade caso↔teste
// ---------------------------------------------------------------------------
// UC-id canônico: UC-<sufixo opcional letras><dígitos>[letra]. Ex: UC-01, UC-06, UC-V05,
// UC-F02, UC-10b. Conservador pra não capturar "UC-" solto ou prosa.
const UC_RE = /\bUC-[A-Z]{0,3}\d{1,3}[a-zA-Z]?\b/g;

function listCasosFiles() {
  return walk(PAGES_DIR, (full, name) => name.endsWith('.casos.md')).map(norm).sort();
}

function ucsInCasos(casosFiles) {
  // map: ucId -> casosFile (primeira ocorrência) — declarações de UC
  const out = [];
  for (const file of casosFiles) {
    const content = readFileSync(resolve(ROOT, file), 'utf8');
    const found = new Set();
    for (const m of content.matchAll(UC_RE)) found.add(m[0].toUpperCase());
    for (const uc of found) out.push({ uc, file });
  }
  return out;
}

function buildTestCorpus() {
  // Concatena conteúdo de todos os arquivos de teste/spec (string-search dos UC-ids).
  // Teste = *Test.php, *.test.*, *.spec.*  em Modules/**/Tests, tests/**, e2e/**.
  let corpus = '';
  for (const d of TEST_DIRS) {
    const base = resolve(ROOT, d);
    const files = walk(base, (full, name) =>
      /Test\.php$/.test(name) || /\.test\.[tj]sx?$/.test(name) || /\.spec\.[tj]sx?$/.test(name),
    );
    for (const f of files) {
      try { corpus += '\n' + readFileSync(f, 'utf8'); } catch { /* ignore */ }
    }
  }
  return corpus;
}

function orphanUcViolations(ucDecls, testCorpus) {
  const violations = [];
  for (const { uc, file } of ucDecls) {
    // UC referenciado = string do id aparece no corpus de testes.
    if (!testCorpus.includes(uc)) violations.push(`uc-orphan:${file}#${uc}`);
  }
  return violations;
}

// ---------------------------------------------------------------------------
// G-5 — metadata viva (quem fez · quando · status por UC)
// ---------------------------------------------------------------------------
// É o que [W] mais valoriza na estrutura de spec: cada casos.md "sabe de si" — owner
// (quem), last_run (quando) e Status por UC (se está ativa/passa). Sem isso a spec
// apodrece (vira convenção esquecível). Aqui isso vira LEI (ratchet).
//
// Trava PRESENÇA, não FRESCOR: last_run é manual e PODE MENTIR (uma data antiga não
// significa que rodou). Carimbar a data automaticamente quando o teste roda é evolução
// futura (precisa o runner escrever de volta no arquivo) — fora do escopo deste guard.
const DATE_RE = /^["']?\d{4}-\d{2}-\d{2}["']?$/;

function frontmatterBlock(content) {
  const m = content.match(/^---\s*\n([\s\S]*?)\n---/);
  return m ? m[1] : null;
}
function fmField(fm, key) {
  if (!fm) return null;
  const m = fm.match(new RegExp(`^${key}\\s*:\\s*(.+)$`, 'm'));
  return m ? m[1].trim() : null;
}

function metadataViolations(casosFiles) {
  const violations = [];
  for (const file of casosFiles) {
    const content = readFileSync(resolve(ROOT, file), 'utf8');
    const fm = frontmatterBlock(content);
    const owner = fmField(fm, 'owner');
    const lastRun = fmField(fm, 'last_run');
    if (!owner) violations.push(`meta:missing-owner:${file}`);
    if (!lastRun || !DATE_RE.test(lastRun)) violations.push(`meta:missing-last_run:${file}`);

    // Status por UC — cada heading "## UC-XX ..." precisa de um "Status:" no bloco
    // (até o próximo "## "). É o "se está ativa / passa" que [W] valoriza.
    const blocks = content.split(/^##\s+/m).slice(1);
    for (const block of blocks) {
      const head = block.match(/^(UC-[A-Z]*\d+[a-zA-Z]?)\b/);
      if (!head) continue;
      if (!/Status\s*[:：]/.test(block)) violations.push(`meta:uc-no-status:${file}#${head[1].toUpperCase()}`);
    }
  }
  return violations;
}

// ---------------------------------------------------------------------------
// G-6 — FRESCOR (staleness) via git: a tela mudou DEPOIS dos casos serem validados?
// ---------------------------------------------------------------------------
// Resolve o "last_run pode mentir" (o limite honesto do G-5). Sinal de frescor amarrado
// à MUDANÇA DE CÓDIGO (não relógio de parede — melhor que o wall-clock à la Backstage):
// se o <Nome>.tsx tem commit MAIS NOVO que o `last_run` do <Nome>.casos.md, os casos estão
// STALE (o comportamento mudou sem revalidar). Força a regra de ouro do F3: "tocou a tela
// → bumpa o last_run (= revalidou os casos)".
//
// Degrada gracioso: sem git / histórico raso (shallow clone) / data ausente → PULA (zero
// falso-positivo). O CI roda com fetch-depth: 0 pro sinal funcionar (casos-gate.yml).
function gitCommitDate(relTsx) {
  try {
    const out = execSync(`git log -1 --format=%cs -- "${relTsx}"`, {
      cwd: ROOT,
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
    }).trim();
    return /^\d{4}-\d{2}-\d{2}$/.test(out) ? out : null;
  } catch {
    return null;
  }
}

function isShallowRepo() {
  try {
    return execSync('git rev-parse --is-shallow-repository', { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim() === 'true';
  } catch {
    return true; // sem git → trata como "sem sinal" (pula tudo).
  }
}

function stalenessViolations(casosFiles) {
  // Histórico raso (shallow clone) faz `git log` devolver a data do HEAD pra TODO arquivo →
  // falso-positivo em massa. Nesse caso PULA (o CI deve usar fetch-depth: 0 pra enforçar).
  if (isShallowRepo()) return [];

  const violations = [];
  for (const file of casosFiles) {
    const lastRunRaw = fmField(frontmatterBlock(readFileSync(resolve(ROOT, file), 'utf8')), 'last_run');
    if (!lastRunRaw) continue; // sem last_run → G-5 já pega; frescor não se aplica.
    const lastRun = lastRunRaw.replace(/["']/g, '');
    if (!/^\d{4}-\d{2}-\d{2}$/.test(lastRun)) continue;

    const tsx = file.replace(/\.casos\.md$/, '.tsx');
    if (!existsSync(resolve(ROOT, tsx))) continue; // trio incompleto → G-1 pega.

    const tsxDate = gitCommitDate(tsx);
    if (!tsxDate) continue; // sem sinal git (shallow/sem histórico) → pula gracioso.

    // Datas YYYY-MM-DD: comparação lexicográfica = cronológica.
    if (tsxDate > lastRun) violations.push(`stale:${file}`);
  }
  return violations;
}

// ---------------------------------------------------------------------------
// G-7 — STATUS DERIVADO DO VERDE (Salto #2): o `Status: ✅` declarado tem que bater
//       com o veredito REAL do teste (manifesto scripts/casos-test-results.json).
// ---------------------------------------------------------------------------
// Fecha o limite honesto do G-5 (trava presença do Status, mas o valor pode MENTIR). O
// veredito por-UC vem de RODAR a suíte (reporter JUnit → coletor casos:results → manifesto);
// este gate só LÊ o manifesto commitado (offline, rápido, required-safe — não roda teste,
// não acopla ao job lento; evita o deadlock que o ADR 0261 proíbe).
//
//   status:lies:<file>#<uc>          declara ✅ mas o teste FALHOU (mentira — alto sinal)
//   status:unverified:<file>#<uc>    declara ✅ mas nenhum teste verde provou (skip/sem entrada)
//   status:stale-results:<file>#<uc> ✅ provado, mas a tela mudou DEPOIS do teste (revalidar)
//
// Só ✅ é uma AFIRMAÇÃO que exige prova. 🧪/⬜/❌ são não-afirmações honestas → sem violação.
// Sem manifesto (bootstrap) → G-7 dorme (gracioso). F1 não-bloqueante: baseline absorve.
function loadManifest() {
  if (!existsSync(MANIFEST_PATH)) return null;
  try { return JSON.parse(readFileSync(MANIFEST_PATH, 'utf8')); } catch { return null; }
}

function declaredStatus(block) {
  const m = block.match(/Status\s*[:：]\s*([^\n]*)/);
  if (!m) return null;
  const line = m[1];
  if (line.includes('✅')) return 'green';
  if (line.includes('❌')) return 'broken';
  if (line.includes('🧪')) return 'testing';
  if (line.includes('⬜')) return 'unverified';
  return 'other';
}

function statusViolations(casosFiles, manifest) {
  if (!manifest) return []; // sem manifesto → gate dorme (gracioso, F1 bootstrap).
  const ucs = manifest.ucs || {};
  const shallow = isShallowRepo();
  const violations = [];
  for (const file of casosFiles) {
    const content = readFileSync(resolve(ROOT, file), 'utf8');
    const tsx = file.replace(/\.casos\.md$/, '.tsx');
    const tsxDate = (!shallow && existsSync(resolve(ROOT, tsx))) ? gitCommitDate(tsx) : null;
    const blocks = content.split(/^##\s+/m).slice(1);
    for (const block of blocks) {
      const head = block.match(/^(UC-[A-Z]*\d+[a-zA-Z]?)\b/);
      if (!head) continue;
      if (declaredStatus(block) !== 'green') continue; // só ✅ precisa de prova
      const uc = head[1].toUpperCase();
      const entry = ucs[uc];
      if (!entry || !entry.verdict || entry.verdict === 'skip') {
        violations.push(`status:unverified:${file}#${uc}`);
      } else if (entry.verdict === 'fail') {
        violations.push(`status:lies:${file}#${uc}`);
      } else if (entry.verdict === 'pass' && tsxDate && entry.ran_at && entry.ran_at < tsxDate) {
        violations.push(`status:stale-results:${file}#${uc}`);
      }
    }
  }
  return violations;
}

// ---------------------------------------------------------------------------
// Cálculo
// ---------------------------------------------------------------------------
function computeViolations() {
  const pages = listPages();
  const casosFiles = listCasosFiles();
  const ucDecls = ucsInCasos(casosFiles);
  const testCorpus = buildTestCorpus();

  const trio = trioViolations(pages);
  const orphans = orphanUcViolations(ucDecls, testCorpus);
  const meta = metadataViolations(casosFiles);
  const stale = stalenessViolations(casosFiles);
  const status = statusViolations(casosFiles, loadManifest());

  const all = [...trio, ...orphans, ...meta, ...stale, ...status].sort((a, b) => a.localeCompare(b));
  return {
    violations: all,
    stats: {
      pages: pages.length,
      casos_files: casosFiles.length,
      ucs_declared: ucDecls.length,
      missing_charter: trio.filter((v) => v.startsWith('trio:missing-charter')).length,
      missing_casos: trio.filter((v) => v.startsWith('trio:missing-casos')).length,
      orphan_ucs: orphans.length,
      metadata_issues: meta.length,
      stale_cases: stale.length,
      status_lies: status.filter((v) => v.startsWith('status:lies')).length,
      status_unverified: status.filter((v) => v.startsWith('status:unverified')).length,
      status_stale: status.filter((v) => v.startsWith('status:stale-results')).length,
    },
  };
}

function loadBaseline() {
  if (!existsSync(BASELINE_PATH)) return null;
  return JSON.parse(readFileSync(BASELINE_PATH, 'utf8'));
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
function main() {
  // Modo só-desce compara baseline ATUAL (commitado no PR) vs baseline de REFERÊNCIA
  // (main) — não varre o repo. Roda ANTES de computeViolations (barato, sem I/O de Pages).
  if (SHRINK_IDX >= 0) {
    if (!SHRINK_REF_PATH || !existsSync(SHRINK_REF_PATH)) {
      console.log('ℹ️ Baseline de referência ausente (bootstrap) — só-desce sem efeito. OK.');
      process.exit(0);
    }
    const cur = loadBaseline();
    if (!cur) {
      console.error(`❌ Baseline atual ausente (${norm(BASELINE_PATH)}). Rode: npm run casos:baseline:write`);
      process.exit(1);
    }
    const refSet = new Set(JSON.parse(readFileSync(SHRINK_REF_PATH, 'utf8')).violations || []);
    const grew = (cur.violations || []).filter((v) => !refSet.has(v));
    if (grew.length) {
      console.error(`\n❌ Baseline CRESCEU: ${grew.length} entrada(s) nova(s) vs referência (só-desce, Onda Q2):\n`);
      for (const v of grew.slice(0, 30)) console.error('  🆕 ' + v);
      if (grew.length > 30) console.error(`  … +${grew.length - 30}`);
      console.error(
        '\nO baseline de cobertura de casos é catraca: ENCOLHER é sempre OK, crescer não.' +
          '\nFeche o trio/teste da tela em vez de fotografar a dívida nova.' +
          '\nMudança consciente (refactor move tela com dívida legada): label `casos-baseline-grow-approved` no PR.',
      );
      process.exit(1);
    }
    const shrunk = refSet.size - (cur.violations || []).length;
    console.log(`✅ Baseline só-desce OK (${shrunk > 0 ? `caiu −${shrunk}` : 'estável'} vs referência).`);
    process.exit(0);
  }

  const { violations, stats } = computeViolations();

  if (MODE_JSON) {
    const baseline = loadBaseline();
    const baseSet = new Set(baseline?.violations || []);
    const novos = violations.filter((v) => !baseSet.has(v));
    console.log(JSON.stringify({ stats, total: violations.length, baseline: baseSet.size, novos, ok: novos.length === 0 }, null, 2));
    process.exit(novos.length === 0 ? 0 : 1);
  }

  if (MODE_REPORT) {
    console.log('# Relatório de dívida — casos:check (ADR 0264 G-1/G-2)\n');
    console.log(`Páginas roteadas (Pages/**, excl _components/): ${stats.pages}`);
    console.log(`Arquivos casos.md: ${stats.casos_files} · UCs declarados: ${stats.ucs_declared}\n`);
    console.log(`Telas SEM charter.md: ${stats.missing_charter}`);
    console.log(`Telas SEM casos.md:   ${stats.missing_casos}`);
    console.log(`UCs órfãos (sem teste): ${stats.orphan_ucs}`);
    console.log(`Metadata viva faltando (owner/last_run/Status por UC): ${stats.metadata_issues}`);
    console.log(`Casos STALE (tela mudou depois do last_run — frescor G-6): ${stats.stale_cases}`);
    console.log(`Status MENTE (✅ declarado vs teste FALHOU — G-7): ${stats.status_lies}`);
    console.log(`Status SEM PROVA (✅ declarado sem teste verde — G-7): ${stats.status_unverified}`);
    console.log(`Status com RESULTADO velho (✅ provado, tela mudou depois — G-7): ${stats.status_stale}`);
    console.log(`\nTOTAL de violações (débito): ${violations.length}`);
    console.log('\n→ F1 fotografa isso no baseline (não-bloqueante). F3 ratchet zera tela-a-tela.');
    process.exit(0);
  }

  if (MODE_WRITE) {
    const out = {
      _meta: {
        generated_at: new Date().toISOString(),
        gate: 'casos:check (ADR 0264 G-1 trio + G-2 rastreabilidade + G-5 metadata + G-6 frescor + G-7 status derivado)',
        stats,
        nota: 'Violações ATUAIS fotografadas (débito legado). Gate falha só em violação NOVA vs este baseline (ratchet). Encolher é sempre OK. Regravar conscientemente: npm run casos:baseline:write',
        refs: ['ADR 0264', 'ADR 0261', 'ADR 0256'],
      },
      violations,
    };
    writeFileSync(BASELINE_PATH, JSON.stringify(out, null, 2) + '\n');
    console.log(`✅ Baseline gravado: ${violations.length} violações (${stats.missing_casos} sem casos · ${stats.missing_charter} sem charter · ${stats.orphan_ucs} UC órfão) → ${norm(BASELINE_PATH)}`);
    process.exit(0);
  }

  // VALIDATE
  console.log(`casos:check · ${violations.length} violações (telas: ${stats.pages}, casos.md: ${stats.casos_files})`);
  const baseline = loadBaseline();
  if (!baseline) {
    console.error(`\n❌ Baseline ausente (${norm(BASELINE_PATH)}). Rode: npm run casos:baseline:write`);
    process.exit(1);
  }
  const baseSet = new Set(baseline.violations || []);
  const novos = violations.filter((v) => !baseSet.has(v));

  if (novos.length) {
    console.error(`\n❌ ${novos.length} violação(ões) NOVA(s) de trio/rastreabilidade (não no baseline):\n`);
    for (const v of novos.slice(0, 50)) console.error('  🆕 ' + v);
    if (novos.length > 50) console.error(`  … +${novos.length - 50}`);
    console.error(
      `\nTela NOVA precisa do trio: <Nome>.tsx + <Nome>.charter.md + <Nome>.casos.md (ADR 0264 G-1).` +
        `\nUC NOVO precisa de teste citando o id (ADR 0264 G-2).` +
        `\nmeta:* → o casos.md precisa de frontmatter \`owner:\` (quem) + \`last_run: "AAAA-MM-DD"\` (quando)` +
        `\n         e cada \`## UC-XX\` precisa de uma linha \`Status:\` (se está ativa/passa) — ADR 0264 G-5.` +
        `\nstale:* → a tela (.tsx) mudou DEPOIS do \`last_run\` — revalide os casos e bumpe o \`last_run\` (ADR 0264 G-6).` +
        `\nstatus:lies:* → o UC declara \`Status: ✅\` mas o teste FALHOU (manifesto). Conserte o teste ou seja honesto (❌). G-7.` +
        `\nstatus:unverified:* → \`Status: ✅\` sem teste verde que prove. Rode a suíte + \`npm run casos:results\`, ou baixe pra 🧪/⬜. G-7.` +
        `\nstatus:stale-results:* → ✅ provado, mas a tela mudou depois do teste — re-rode + \`npm run casos:results\`. G-7.` +
        `\nSe for legado movido/refatorado conscientemente: npm run casos:baseline:write`,
    );
    process.exit(1);
  }

  const delta = (baseline.violations?.length || 0) - violations.length;
  console.log(`✅ Sem violações novas (débito ${delta > 0 ? `caiu −${delta}` : 'estável'} vs baseline).`);
  process.exit(0);
}

main();
