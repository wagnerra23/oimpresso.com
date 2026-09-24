#!/usr/bin/env node
// scripts/pageheader-migration-guard.mjs — F4 do roadmap de convergência UI (MANUAL-CSS-JS.md §5)
//
// Congela o crescimento do PageHeader ANTIGO (`@/Components/shared/PageHeader`) enquanto a
// migração pro canon novo (`@/Components/PageHeader`, v3.8 · ADR 0189/0190) acontece tela-a-tela.
//
//   (a) RATCHET: o nº de telas importando o header antigo só pode CAIR (migração) — nunca subir.
//       → nenhuma tela NOVA adota o `shared/PageHeader`; header novo = sempre o canon.
//   (b) Migrar uma tela (antigo→canon) baixa o contador (= progresso); o baseline acompanha no --write.
//
// NÃO toca pixel — é guard de import. A migração visual em si (104 telas) é incremental e exige
// aprovação visual por PR (gate MWART / PR UI Judge), fora deste script.
//
// Comandos (gêmeo do idioma scripts/css-size-baseline.mjs):
//   node scripts/pageheader-migration-guard.mjs           # valida: falha se algum import novo do antigo
//   node scripts/pageheader-migration-guard.mjs --write    # grava baseline do estado atual
//
// Refs: MANUAL-CSS-JS.md §5 (F4) · ADR 0189/0190 (PageHeader canon v3) · INDEX-DESIGN-MEMORIAS.md

import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { resolve, join, relative } from 'node:path';

const ROOT = process.cwd();
// 2 RAIZES de telas Inertia. O #5686 (2026-08-12) moveu as telas pro modulo dono e o
// scanner NAO foi junto: o baseline ja carregava 24 paths sob Modules/ que este guard
// nunca visitava. Medido em 2026-09-22: 23 adotantes reais em Modules/, 17 no baseline,
// 6 FORA — invisiveis a um gate required. Mesmo eixo que o eslint-gate fechou em
// 2026-08-28 ("2a raiz de telas Inertia. Sem ela o gate ficaria MUDO justamente na
// superficie"), e que aqui seguia aberto.
function modulesResourcesJs() {
  const base = resolve(ROOT, 'Modules');
  if (!existsSync(base)) return [];
  return readdirSync(base, { withFileTypes: true })
    .filter((e) => e.isDirectory())
    .map((e) => resolve(base, e.name, 'Resources', 'js'))
    .filter((dir) => existsSync(dir));
}

const SCAN_DIRS = [resolve(ROOT, 'resources/js'), ...modulesResourcesJs()];
const BASELINE_PATH = resolve(ROOT, 'config/pageheader-shared-baseline.json');
const MODE_WRITE = process.argv.includes('--write');

// Import EXATO do componente antigo (não os irmãos PageHeaderActions/ModuleNav/Tabs).
const OLD_IMPORT = /from\s+['"]@\/Components\/shared\/PageHeader['"]/;

/**
 * Arquivos tocados no diff — a UNIDADE da ADR 0409 ("alterar a unidade acorda a divida").
 *
 * Base VIVA, nunca `pull_request.base.sha`: aquele campo congela no instante em que o PR
 * abriu e envelhece contra o merge ref, entao o range passa a incluir o que veio do main
 * (§5 2026-09-15). `GITHUB_BASE_REF` honra PR que mira branch != main.
 *
 * Devolve `null` quando NAO CONSEGUIU MEDIR — e o chamador sai 2, nunca 0. Colapsar
 * "nao medi" em "nada tocado" daria verde por nao-execucao (§5 2026-07-29 · LC-33).
 */
function arquivosTocados() {
  const baseRef = process.env.GITHUB_BASE_REF ? 'origin/' + process.env.GITHUB_BASE_REF : 'origin/main';
  try {
    execSync('git rev-parse --git-dir', { stdio: 'ignore' });
  } catch {
    return null;
  }
  try {
    execSync('git fetch --no-tags --quiet ' + baseRef.replace('origin/', 'origin '), { stdio: 'ignore' });
  } catch {
    // offline / sem permissao: segue com a ref local que houver
  }
  let base = '';
  try {
    base = execSync('git merge-base ' + baseRef + ' HEAD', { encoding: 'utf8' }).trim();
  } catch {
    return null;
  }
  if (!base) return null;
  try {
    const out = execSync('git diff --name-only ' + base + '...HEAD', { encoding: 'utf8' });
    return new Set(out.split(String.fromCharCode(10)).map((s) => s.trim()).filter(Boolean));
  } catch {
    return null;
  }
}

function listTsx(dir) {
  const out = [];
  if (!existsSync(dir)) return out;
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) out.push(...listTsx(full));
    else if (entry.isFile() && /\.(tsx|ts|jsx|js)$/.test(entry.name)) out.push(full);
  }
  return out;
}

function findOldAdopters() {
  const files = [];
  for (const f of SCAN_DIRS.flatMap(listTsx)) {
    if (OLD_IMPORT.test(readFileSync(f, 'utf8'))) {
      files.push(relative(ROOT, f).replace(/\\/g, '/'));
    }
  }
  return files.sort((a, b) => a.localeCompare(b));
}

const current = findOldAdopters();
const count = current.length;

if (MODE_WRITE) {
  // ADR 0409 — "os arquivos de tolerancia [...] NAO PODEM CRESCER" e "regenerar uma
  // lista para acomodar a saida NAO e correcao". A guarda abaixo poe isso na maquina:
  // o --write so aceita gravar quando a divida DIMINUI (ou fica igual). Pra subir, o
  // caminho e curar a tela, nao regravar a lista.
  if (existsSync(BASELINE_PATH)) {
    const anterior = JSON.parse(readFileSync(BASELINE_PATH, 'utf8'));
    const antes = anterior._meta?.count ?? (anterior.files || []).length;
    if (count > antes) {
      console.error('');
      console.error('❌ --write RECUSADO: a divida SUBIRIA de ' + antes + ' para ' + count + '.');
      console.error('   ADR 0409: lista de tolerancia nao cresce, e regravar pra acomodar a');
      console.error('   saida nao e correcao. Migre a(s) tela(s) para @/Components/PageHeader.');
      process.exit(1);
    }
  }

  const out = {
    _meta: {
      generated_at: new Date().toISOString(),
      count,
      adr: '0409 — divida transitoria, nao tolerancia',
      note:
        'DIVIDA TRANSITORIA do PageHeader antigo (shared/PageHeader), nao selo de conformidade. ' +
        'Estar nesta lista ADIA a cura; nao perdoa: tocar a tela reprova o gate (ADR 0409). ' +
        'A lista so DESCE — o --write recusa crescimento. Meta: zero, e entao este arquivo e apagado.',
    },
    files: current,
  };
  writeFileSync(BASELINE_PATH, JSON.stringify(out, null, 2) + String.fromCharCode(10));
  console.log('✅ Divida regravada: ' + count + ' tela(s) no PageHeader antigo.');
  process.exit(0);
}

if (!existsSync(BASELINE_PATH)) {
  console.error(`❌ Baseline ausente (${relative(ROOT, BASELINE_PATH)}). Rode: npm run pageheader:guard:write`);
  process.exit(1);
}

const baseline = JSON.parse(readFileSync(BASELINE_PATH, 'utf8'));
const baseCount = baseline._meta?.count ?? (baseline.files || []).length;
const baseSet = new Set(baseline.files || []);
const naBaseline = current.filter((f) => baseSet.has(f));
const novos = current.filter((f) => !baseSet.has(f));

console.log('PageHeader migration guard · ' + count + ' telas no antigo (divida registrada: ' + baseCount + ')');

// ── REGRA 1 — DIVIDA NOVA NUNCA ENTRA (ADR 0409: "eles nao podem crescer") ──────
if (novos.length) {
  console.error('');
  console.error('❌ ' + novos.length + ' tela(s) NOVA(s) adotando o PageHeader ANTIGO:');
  for (const f of novos) console.error('  🆕 ' + f);
  console.error('');
  console.error("Tela nova usa o canon: import PageHeader from '@/Components/PageHeader' (v3.8, ADR 0189/0190).");
  console.error('Absorver no baseline NAO e correcao (ADR 0409). Migre a tela.');
  process.exit(1);
}

// ── NAO CONSEGUI MEDIR != NADA TOCADO (LC-33) ──────────────────────────────────
const tocados = arquivosTocados();
if (tocados === null) {
  console.error('');
  console.error('⚠️  NAO MEDI: sem git, sem base ou sem diff — nao da pra saber o que foi tocado.');
  console.error('   Sem isso o gate nao pode declarar conformidade (ADR 0409 §"Um gate so pode');
  console.error('   declarar conformidade quando executou o detector sobre o escopo declarado").');
  process.exit(2);
}

// ── REGRA 2 — TOCOU A UNIDADE, CURA NO MESMO PR (ADR 0409) ─────────────────────
const dividaTocada = naBaseline.filter((f) => tocados.has(f));
if (dividaTocada.length) {
  console.error('');
  console.error('❌ ' + dividaTocada.length + ' tela(s) com divida do header antigo foram TOCADAS neste PR:');
  for (const f of dividaTocada) console.error('  ✏️  ' + f);
  console.error('');
  console.error('ADR 0409 — "alterar a unidade acorda a divida e exige a cura no mesmo PR".');
  console.error('Estar na lista adia; nao perdoa. Migre para @/Components/PageHeader.');
  process.exit(1);
}

// ── REGRA 3 — DIVIDA NAO TOCADA: VISIVEL, SEM SELO (ADR 0409) ──────────────────
// A lista NAO concede conformidade. O verde aqui diz "este PR nao piorou nem tocou",
// nunca "o repo esta conforme" — por isso a divida e impressa toda vez.
if (naBaseline.length) {
  console.log('');
  console.log('📉 divida transitoria do header antigo: ' + naBaseline.length + ' tela(s) — nao tocadas neste PR.');
  console.log('   Visiveis por decisao (ADR 0409): a lista adia, nao concede conformidade.');
  console.log('   Meta: zero. Cada migracao para @/Components/PageHeader remove uma entrada.');
}

console.log('');
console.log('✅ Nenhuma adocao nova e nenhuma divida tocada neste PR.');
process.exit(0);
