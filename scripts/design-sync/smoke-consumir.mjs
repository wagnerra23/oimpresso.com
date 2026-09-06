#!/usr/bin/env node
// @ts-check
/**
 * smoke-consumir.mjs — transporta o smoke renderizado no CI até o recibo do Design Sync.
 *
 * POR QUE EXISTE (ADR 0390, emenda ao D-6 da ADR 0384): o estado `validated` exigia smoke
 * de PRODUÇÃO, e produção exige login humano que o agente não digita — 0/93 telas validadas
 * por construção. O workflow `design-smoke-ci.yml` sobe o app efêmero do próprio CI (mesmo
 * seed biz=1/2 do visual-regression), renderiza as telas `tested|validated` logado como o
 * admin do biz=1 e publica os PNGs + `manifest.json` na branch órfã `governance/design-smokes`.
 * Este script fecha o elo do outro lado: baixa a órfã, casa cada smoke com o alvo ATUAL e
 * chama o registrador oficial (`status.mjs --record-smoke … --host ci`). Ele NÃO grava no
 * ledger por conta própria — o único escritor de recibo continua sendo o `status.mjs`
 * (D-1 da 0384: uma projeção, nenhum ledger paralelo).
 *
 * O CASAMENTO É POR CONTEÚDO, NÃO POR DATA: o manifesto carrega o blob git do `.tsx` no
 * instante do render (`targetBlob`, via `git ls-tree` — imune ao mangling MSYS de `ref:path`,
 * §5 2026-08-23). Se o alvo local tem outro blob, o smoke é de outra tela e é PULADO com o
 * motivo impresso; gravar seria recibo de um `.tsx` que já não existe (D-7, invalidação em
 * cascata, feita ANTES de existir o recibo).
 *
 * MODOS
 *   (sem flag)                    fetch órfã → manifest → copia PNG p/ state/smokes/ → --record-smoke
 *   --dry                         mesmo caminho, sem copiar e sem gravar (imprime o plano)
 *   --select [--json]             lado CI: telas elegíveis (tested|validated) + rota derivada
 *   --manifest --selection F --dir D --sha SHA --out F   lado CI: monta manifest.json dos PNGs
 *   --selftest                    funções puras (slug · rota do charter · seleção · casamento)
 *   --root DIR                    raiz do repo (default: cwd)
 *
 * DERIVAÇÃO DA ROTA (nunca inventada): `screen.route` do application-report se existir; senão
 * o charter ao lado do `.tsx`, na ordem `route:` → `url:` → `page:`, só quando o valor começa
 * com `/` (nome de rota como `kb.index` não é path) e não tem parâmetro `{…}` (a tela exige
 * um id que o seed não garante). Sem rota derivável = fora da seleção, com o motivo listado.
 */
import { existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, copyFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, dirname, join, resolve } from 'node:path';
import { execFileSync, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

export const SMOKE_BRANCH = 'governance/design-smokes';
export const SMOKE_MANIFEST_SCHEMA = 'oimpresso-design-smokes/1';
export const SMOKE_STATE_DIR = 'scripts/design-sync/state/smokes';
const ELEGIVEIS = new Set(['tested', 'validated']);

/* ── funções puras ───────────────────────────────────────────────────────────────────────── */

/** `resources/js/Pages/Fiscal/Config.tsx` → `fiscal-config`; Pages de módulo perdem só o prefixo `Modules/X/Resources/js/Pages/`. */
export function screenSlug(target) {
  const rel = String(target || '').replace(/\\/g, '/')
    .replace(/^resources\/js\/Pages\//i, '')
    .replace(/^Modules\/[^/]+\/Resources\/js\/Pages\//i, '')
    .replace(/\.tsx$/i, '');
  return rel.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

/** Rota derivada do frontmatter do charter (`route:` → `url:` → `page:`), ou `{ route: null, motivo }`. */
export function routeFromCharter(text) {
  const fm = String(text || '').match(/^---\r?\n([\s\S]*?)\r?\n---/);
  if (!fm) return { route: null, motivo: 'charter sem frontmatter' };
  const linhas = fm[1].split(/\r?\n/);
  const valor = (chave) => {
    const linha = linhas.find((l) => new RegExp(`^${chave}:\\s*`).test(l));
    return linha ? linha.replace(new RegExp(`^${chave}:\\s*`), '').trim().replace(/^["']|["']$/g, '') : null;
  };
  const candidatos = [];
  for (const chave of ['route', 'url', 'page']) {
    const v = valor(chave);
    if (v) candidatos.push({ chave, v });
  }
  if (!candidatos.length) return { route: null, motivo: 'charter sem route:/url:/page:' };
  for (const { chave, v } of candidatos) {
    const path = v.replace(/\s*\(.*$/, '').trim(); // "/advisor/login (POST /advisor/login)" → "/advisor/login"
    if (!path.startsWith('/')) continue; // nome de rota (kb.index) não é path
    if (/\{[^}]*\}/.test(path)) return { route: null, motivo: `rota com parâmetro em ${chave}: ${path}` };
    return { route: path, origem: `charter.${chave}` };
  }
  return { route: null, motivo: `nenhum candidato começa com / (${candidatos.map((c) => `${c.chave}=${c.v}`).join(' · ')})` };
}

/** Seleciona as telas elegíveis do relatório e resolve a rota. `readCharter(target)` devolve texto ou null. */
export function selectScreens(report, readCharter) {
  const selecionadas = [];
  const foraDaSelecao = [];
  for (const screen of report?.screens || []) {
    if (!ELEGIVEIS.has(screen.lifecycleState)) continue;
    const base = { source: screen.source, target: screen.target, module: screen.module || null, slug: screenSlug(screen.target), lifecycleState: screen.lifecycleState };
    if (typeof screen.route === 'string' && screen.route.startsWith('/')) {
      selecionadas.push({ ...base, route: screen.route, routeOrigin: 'report.route' });
      continue;
    }
    const charterPath = String(screen.target || '').replace(/\.tsx$/i, '.charter.md');
    const texto = readCharter(charterPath);
    if (texto == null) { foraDaSelecao.push({ ...base, motivo: `charter ausente: ${charterPath}` }); continue; }
    const r = routeFromCharter(texto);
    if (!r.route) { foraDaSelecao.push({ ...base, motivo: r.motivo }); continue; }
    selecionadas.push({ ...base, route: r.route, routeOrigin: r.origem });
  }
  selecionadas.sort((a, b) => a.slug.localeCompare(b.slug));
  return { selecionadas, foraDaSelecao };
}

/** Monta o manifesto do lado CI: só entra tela cuja PNG existe. `blobOf(target)` = blob git do alvo renderizado. */
export function buildManifest({ selection, pngs, deploySha, blobOf, capturedAt = new Date().toISOString(), host = 'ci' }) {
  const presentes = new Set(pngs);
  const smokes = [];
  const semPng = [];
  for (const tela of selection) {
    if (!presentes.has(`${tela.slug}.png`)) { semPng.push(tela.slug); continue; }
    smokes.push({
      tela: tela.slug, source: tela.source, target: tela.target, route: tela.route,
      png: `${tela.slug}.png`, targetBlob: blobOf(tela.target), deploySha, host, capturedAt,
    });
  }
  return { schema: SMOKE_MANIFEST_SCHEMA, deploySha, host, generatedAt: capturedAt, smokes, semPng };
}

/** Casa cada smoke do manifesto com o alvo atual. Só grava quando o blob do `.tsx` é o renderizado. */
export function matchEntries({ manifest, report, blobOf }) {
  const gravar = [];
  const pular = [];
  if (manifest?.schema !== SMOKE_MANIFEST_SCHEMA) return { gravar, pular: [{ tela: '*', motivo: `manifesto com schema inesperado: ${manifest?.schema}` }] };
  if (manifest.host !== 'ci') return { gravar, pular: [{ tela: '*', motivo: `manifesto com host ${manifest.host}; este consumidor só grava host ci` }] };
  for (const smoke of manifest.smokes || []) {
    const screen = (report?.screens || []).find((s) => s.target === smoke.target && s.source === smoke.source);
    if (!screen) { pular.push({ tela: smoke.tela, motivo: 'par fonte→alvo não está mais no application-report' }); continue; }
    if (!ELEGIVEIS.has(screen.lifecycleState)) { pular.push({ tela: smoke.tela, motivo: `tela está em ${screen.lifecycleState}; smoke exige tested|validated (teste antes do smoke, D-6)` }); continue; }
    if (!/^[a-f0-9]{40,64}$/.test(String(smoke.deploySha || ''))) { pular.push({ tela: smoke.tela, motivo: 'deploySha inválido no manifesto' }); continue; }
    const atual = blobOf(smoke.target);
    if (!atual) { pular.push({ tela: smoke.tela, motivo: `alvo ausente localmente: ${smoke.target}` }); continue; }
    if (!smoke.targetBlob || atual !== smoke.targetBlob) { pular.push({ tela: smoke.tela, motivo: `o .tsx mudou depois do render (blob ${String(smoke.targetBlob || '?').slice(0, 12)} → ${atual.slice(0, 12)}); regravar no próximo push de main` }); continue; }
    gravar.push(smoke);
  }
  return { gravar, pular };
}

/* ── I/O ─────────────────────────────────────────────────────────────────────────────────── */

function git(root, args, opts = {}) {
  return execFileSync('git', args, { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], ...opts }).trim();
}

/** Blob git do arquivo NO DISCO (hash-object): é o que o manifesto compara. Nunca `ref:path`. */
function blobOfWorkingFile(root) {
  return (target) => {
    const abs = join(root, String(target).replace(/\\/g, '/'));
    if (!existsSync(abs)) return null;
    try { return git(root, ['hash-object', '--', abs]); } catch { return null; }
  };
}

function readReport(root) {
  const path = join(root, 'scripts/design-sync/state/application-report.json');
  if (!existsSync(path)) throw new Error(`application-report.json ausente em ${path}`);
  return JSON.parse(readFileSync(path, 'utf8'));
}

function selectFromDisk(root) {
  return selectScreens(readReport(root), (charterPath) => {
    const abs = join(root, charterPath);
    return existsSync(abs) ? readFileSync(abs, 'utf8') : null;
  });
}

function fetchOrphan(root) {
  git(root, ['fetch', '--no-tags', '--depth=1', 'origin', SMOKE_BRANCH]);
  const dir = mkdtempSync(join(tmpdir(), 'design-smokes-'));
  const tar = join(dir, 'smokes.tar');
  git(root, ['archive', '--format=tar', '-o', tar, 'FETCH_HEAD']); // sem `ref:path` — MSYS mangleia o `:`
  const extraido = join(dir, 'tree');
  mkdirSync(extraido, { recursive: true });
  // Caminhos RELATIVOS ao tmp (cwd), nunca absolutos: no Windows o `tar` do Git Bash lê
  // `C:\…` como host remoto ("Cannot connect to C: resolve failed") — medido 2026-09-06 no
  // 1º consumo real. Relativo funciona igual em Linux/macOS/Windows (§5 2026-08-07, plataforma).
  execFileSync('tar', ['-xf', 'smokes.tar', '-C', 'tree'], { cwd: dir, stdio: ['ignore', 'pipe', 'pipe'] });
  const manifestPath = join(extraido, 'manifest.json');
  if (!existsSync(manifestPath)) throw new Error(`manifest.json ausente na branch ${SMOKE_BRANCH}`);
  return { dir, tree: extraido, manifest: JSON.parse(readFileSync(manifestPath, 'utf8')) };
}

async function consumir({ root, dry }) {
  const STATUS = fileURLToPath(new URL('./status.mjs', import.meta.url));
  const { dir, tree, manifest } = fetchOrphan(root);
  try {
    const report = readReport(root);
    const { gravar, pular } = matchEntries({ manifest, report, blobOf: blobOfWorkingFile(root) });
    console.log(`\nDESIGN-SMOKE · órfã ${SMOKE_BRANCH} · deploySha ${String(manifest.deploySha).slice(0, 12)} · ${manifest.smokes?.length || 0} smoke(s)${dry ? ' · DRY' : ''}`);
    for (const p of pular) console.log(`  [PULA ] ${p.tela} — ${p.motivo}`);
    let gravados = 0;
    let falhas = 0;
    for (const smoke of gravar) {
      const png = join(tree, smoke.png);
      const destinoRel = `${SMOKE_STATE_DIR}/${smoke.tela}.png`;
      if (!existsSync(png)) { console.log(`  [PULA ] ${smoke.tela} — PNG ${smoke.png} ausente na órfã`); continue; }
      if (dry) { console.log(`  [PLANO] ${smoke.tela} → ${destinoRel} · --record-smoke ${smoke.source} --target ${smoke.target} --route ${smoke.route} --host ci`); continue; }
      mkdirSync(join(root, SMOKE_STATE_DIR), { recursive: true });
      copyFileSync(png, join(root, destinoRel)); // um por tela, sobrescrito (ADR 0390)
      const run = spawnSync(process.execPath, [
        STATUS, '--root', root,
        '--record-smoke', smoke.source, '--target', smoke.target, '--route', smoke.route,
        '--deploy-sha', smoke.deploySha, '--screenshot', destinoRel, '--tenant', '1', '--host', 'ci',
      ], { encoding: 'utf8', cwd: root });
      if (run.status === 0) { gravados++; console.log(`  [GRAVA] ${smoke.tela} → ${destinoRel} (validated)`); }
      else { falhas++; console.log(`  [FALHA] ${smoke.tela} — status.mjs exit ${run.status}: ${String(run.stderr || run.stdout).trim().split('\n').pop()}`); }
    }
    console.log(`\n  gravados ${gravados} · pulados ${pular.length} · falhas ${falhas}\n`);
    return falhas ? 1 : 0;
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

/* ── selftest (puro) ─────────────────────────────────────────────────────────────────────── */

function selftest() {
  let failures = 0;
  const check = (name, cond, detail = '') => { console.log(`[${cond ? 'OK' : 'FAIL'}] ${name}${cond ? '' : ` → ${detail}`}`); if (!cond) failures++; };

  check('slug core', screenSlug('resources/js/Pages/Fiscal/Config.tsx') === 'fiscal-config', screenSlug('resources/js/Pages/Fiscal/Config.tsx'));
  check('slug módulo', screenSlug('Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx') === 'superadmin-dashboard-index');
  check('slug barra invertida + Index', screenSlug('resources\\js\\Pages\\Arquivos\\Index.tsx') === 'arquivos-index');

  check('charter: url quando route ausente', routeFromCharter('---\npage: /fiscal/config\nurl: /fiscal/config\n---\n# x').route === '/fiscal/config');
  check('charter: route vence url', routeFromCharter('---\nroute: /a\nurl: /b\n---').route === '/a');
  check('charter: route nomeada cai pro url', routeFromCharter('---\nroute: kb.index\nurl: /kb\n---').route === '/kb');
  check('charter: sufixo entre parênteses é descartado', routeFromCharter('---\nroute: /advisor/login (POST /advisor/login)\n---').route === '/advisor/login');
  check('charter: parâmetro {name} não vira rota', routeFromCharter('---\nroute: /governance/module-grades/{name}\n---').route === null);
  check('charter: sem frontmatter → null com motivo', /frontmatter/.test(routeFromCharter('# só corpo').motivo));
  check('charter: só nome de rota → null com motivo', /começa com/.test(routeFromCharter('---\nroute: kb.index\n---').motivo));

  const report = { screens: [
    { source: 'fiscal-page.jsx', target: 'resources/js/Pages/Fiscal/Config.tsx', lifecycleState: 'tested', module: 'Fiscal' },
    { source: 'fiscal-page.jsx', target: 'resources/js/Pages/Fiscal/Sped.tsx', lifecycleState: 'validated', module: 'Fiscal', route: '/fiscal/sped' },
    { source: 'arquivos-page.jsx', target: 'resources/js/Pages/Arquivos/Index.tsx', lifecycleState: 'applied', module: 'Arquivos' },
    { source: 'kb-page.jsx', target: 'resources/js/Pages/kb/Index.tsx', lifecycleState: 'tested', module: 'kb' },
    { source: 'x-page.jsx', target: 'resources/js/Pages/X/Index.tsx', lifecycleState: 'tested', module: 'X' },
  ] };
  const charters = {
    'resources/js/Pages/Fiscal/Config.charter.md': '---\npage: /fiscal/config\nurl: /fiscal/config\n---',
    'resources/js/Pages/kb/Index.charter.md': '---\nroute: kb.index\n---',
  };
  const sel = selectScreens(report, (p) => charters[p] ?? null);
  check('seleção: só tested|validated entram', sel.selecionadas.length === 2 && sel.selecionadas.every((s) => ['tested', 'validated'].includes(s.lifecycleState)), JSON.stringify(sel));
  check('seleção: report.route vence charter', sel.selecionadas.find((s) => s.slug === 'fiscal-sped')?.routeOrigin === 'report.route');
  check('seleção: charter.url deriva a rota', sel.selecionadas.find((s) => s.slug === 'fiscal-config')?.route === '/fiscal/config');
  check('seleção: applied fica fora sem constar como erro', !sel.foraDaSelecao.some((s) => s.slug === 'arquivos-index'));
  check('seleção: rota nomeada e charter ausente saem COM motivo', sel.foraDaSelecao.length === 2 && sel.foraDaSelecao.every((s) => s.motivo));

  const blobs = { 'resources/js/Pages/Fiscal/Config.tsx': 'a'.repeat(40), 'resources/js/Pages/Fiscal/Sped.tsx': 'b'.repeat(40) };
  const manifest = buildManifest({ selection: sel.selecionadas, pngs: ['fiscal-config.png'], deploySha: 'c'.repeat(40), blobOf: (t) => blobs[t], capturedAt: '2026-09-06T00:00:00.000Z' });
  check('manifest: só tela com PNG entra; a sem PNG é listada', manifest.smokes.length === 1 && manifest.smokes[0].tela === 'fiscal-config' && manifest.semPng.includes('fiscal-sped'), JSON.stringify(manifest));
  check('manifest: campos do recibo presentes', (({ tela, route, deploySha, host, capturedAt, targetBlob }) => tela && route && deploySha && host === 'ci' && capturedAt && targetBlob)(manifest.smokes[0]));

  const m1 = matchEntries({ manifest, report, blobOf: (t) => blobs[t] });
  check('casamento: blob igual → grava', m1.gravar.length === 1 && m1.pular.length === 0, JSON.stringify(m1));
  const m2 = matchEntries({ manifest, report, blobOf: () => 'z'.repeat(40) });
  check('casamento: .tsx mudou depois do render → pula com motivo', m2.gravar.length === 0 && /mudou depois do render/.test(m2.pular[0]?.motivo || ''), JSON.stringify(m2));
  const reportRegredido = { screens: [{ ...report.screens[0], lifecycleState: 'applied' }] };
  const m3 = matchEntries({ manifest, report: reportRegredido, blobOf: (t) => blobs[t] });
  check('casamento: tela que voltou a applied não recebe smoke (teste antes do smoke)', m3.gravar.length === 0 && /tested\|validated/.test(m3.pular[0]?.motivo || ''));
  const m4 = matchEntries({ manifest: { ...manifest, host: 'producao' }, report, blobOf: (t) => blobs[t] });
  check('casamento: manifesto com host ≠ ci é recusado inteiro', m4.gravar.length === 0 && /só grava host ci/.test(m4.pular[0]?.motivo || ''));
  const m5 = matchEntries({ manifest: { ...manifest, smokes: [{ ...manifest.smokes[0], deploySha: 'xyz' }] }, report, blobOf: (t) => blobs[t] });
  check('casamento: deploySha inválido → pula', m5.gravar.length === 0 && /deploySha/.test(m5.pular[0]?.motivo || ''));

  console.log(failures ? `\n✗ ${failures} falha(s)` : '\n✓ smoke-consumir: slug + rota do charter + seleção + manifesto + casamento por blob provados');
  return failures ? 1 : 0;
}

/* ── CLI ─────────────────────────────────────────────────────────────────────────────────── */

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const args = process.argv.slice(2);
  const valueOf = (name) => { const i = args.indexOf(name); return i >= 0 && args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : null; };
  const root = resolve(valueOf('--root') || process.cwd());
  try {
    if (args.includes('--selftest')) process.exit(selftest());
    if (args.includes('--select')) {
      const { selecionadas, foraDaSelecao } = selectFromDisk(root);
      if (args.includes('--json')) console.log(JSON.stringify(selecionadas, null, 2));
      else {
        console.log(`\nDESIGN-SMOKE · ${selecionadas.length} tela(s) elegível(is) (tested|validated com rota derivável)`);
        for (const s of selecionadas) console.log(`  [${s.lifecycleState.toUpperCase().padEnd(9)}] ${s.slug} · ${s.route} (${s.routeOrigin}) ← ${s.source}`);
        for (const s of foraDaSelecao) console.log(`  [FORA     ] ${s.slug} — ${s.motivo}`);
        console.log('');
      }
      process.exit(0);
    }
    if (args.includes('--manifest')) {
      const selectionPath = valueOf('--selection');
      const dir = valueOf('--dir');
      const sha = valueOf('--sha');
      const out = valueOf('--out');
      if (!selectionPath || !dir || !sha || !out) throw new Error('--manifest exige --selection F --dir D --sha SHA --out F');
      const selection = JSON.parse(readFileSync(resolve(root, selectionPath), 'utf8'));
      const absDir = resolve(root, dir);
      const pngs = existsSync(absDir) ? readdirSync(absDir).filter((f) => f.endsWith('.png')) : [];
      const manifest = buildManifest({ selection, pngs, deploySha: sha, blobOf: blobOfWorkingFile(root) });
      mkdirSync(dirname(resolve(root, out)), { recursive: true });
      writeFileSync(resolve(root, out), JSON.stringify(manifest, null, 2) + '\n');
      console.log(`manifest: ${manifest.smokes.length} smoke(s) · sem PNG: ${manifest.semPng.join(', ') || 'nenhum'} → ${out}`);
      process.exit(0);
    }
    process.exit(await consumir({ root, dry: args.includes('--dry') }));
  } catch (error) {
    console.error(`✗ smoke-consumir: ${error.message}`);
    process.exit(2);
  }
}
