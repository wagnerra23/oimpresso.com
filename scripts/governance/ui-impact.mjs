#!/usr/bin/env node
/**
 * Fonte única do skip-as-pass do visual-regression.
 *
 * Classifica o diff por impacto visual. Mudanças diretas de Page produzem uma
 * lista de telas; mudanças compartilhadas/fundacionais exigem o núcleo global.
 * Paths dentro de raízes de UI desconhecidos falham de forma conservadora:
 * rodam o visual em vez de ganhar um verde vazio.
 */
import { appendFileSync, existsSync, readFileSync, realpathSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const ROOT = process.cwd();
const SCREEN_MANIFEST = 'tests/Browser/visreg-screens.json';
const SOURCE_EXT = String.raw`(?:[cm]?[jt]sx?|vue)`;
const ASSET_EXT = String.raw`(?:[cm]?js|css|scss|sass|less|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|otf)`;

export const normalizePath = (path) => String(path || '').replace(/\\/g, '/').replace(/^\.\//, '');

function pageScreen(path) {
  const rel = path.replace(/^resources\/js\/Pages\//, '').replace(new RegExp(`\\.${SOURCE_EXT}$`, 'i'), '');
  const parts = rel.split('/');
  const privatePart = parts.findIndex((part) => part.startsWith('_'));
  const visible = privatePart > 0 ? parts.slice(0, privatePart) : parts;
  if (visible.length > 1 && visible.at(-1)?.toLowerCase() === 'index') visible.pop();
  return visible.join('/');
}

function normalizeScreenName(screen) {
  const parts = screen.split('/');
  if (parts.length > 1 && parts.at(-1)?.toLowerCase() === 'index') parts.pop();
  return parts.join('/');
}

function inertiaScreens(content) {
  const screens = [];
  const patterns = [
    /Inertia::render\s*\(\s*(['"])([^'"]+)\1/g,
    /\binertia\s*\(\s*(['"])([^'"]+)\1/gi,
  ];
  for (const pattern of patterns) {
    for (const match of content.matchAll(pattern)) screens.push(normalizeScreenName(match[2]));
  }
  return [...new Set(screens)];
}

/** Classificador puro de um path. `content` cobre Controllers/routes Inertia. */
export function classifyFile(rawPath, content = '') {
  const path = normalizePath(rawPath);
  const lower = path.toLowerCase();
  const inertia = /(?:Inertia::render|\binertia\s*\()/i.test(content);

  // Contratos e metadados ao lado da UI não alteram o render por si sós.
  if (/^(?:resources|Modules\/[^/]+\/Resources)\/.*\.(?:md|mdx)$/i.test(path)) return null;
  if (/^resources\/css\/tokens\/(?:version\.json|changelog\.json)$/i.test(path)) return null;
  if (new RegExp(`^resources/js/Pages/.+\\.${SOURCE_EXT}$`, 'i').test(path) && /\/_.*\//.test(path)) {
    return { path, scope: 'global', reason: 'componente-de-page-compartilhado', screen: pageScreen(path) };
  }
  if (new RegExp(`^resources/js/Pages/.+\\.${SOURCE_EXT}$`, 'i').test(path)) {
    return { path, scope: 'targeted', reason: 'page-inertia', screen: pageScreen(path) };
  }
  if (/^resources\/js\//i.test(path)) return { path, scope: 'global', reason: 'frontend-compartilhado' };
  if (/^resources\/(?:css|views|lang|images?|fonts?)\//i.test(path)) return { path, scope: 'global', reason: 'fundacao-visual' };
  if (/^Modules\/[^/]+\/Resources\/(?:js|css|views)\//i.test(path)) return { path, scope: 'global', reason: 'ui-de-modulo' };
  if (new RegExp(`^public/(?:css|js|img|images|fonts)/.+\\.${ASSET_EXT}$`, 'i').test(path)) {
    return { path, scope: 'global', reason: 'asset-publico' };
  }
  if (/^(?:app|Modules\/[^/]+)\/Http\/Controllers\/.+\.php$/i.test(path) && inertia) {
    return { path, scope: 'global', reason: 'controller-inertia', screens: inertiaScreens(content) };
  }
  if (/^(?:routes\/web\.php|Modules\/[^/]+\/(?:Routes|routes)\/web\.php)$/i.test(path) || (/^routes\/.+\.php$/i.test(path) && inertia)) {
    return { path, scope: 'global', reason: 'rota-inertia', screens: inertiaScreens(content) };
  }
  if (/^tests\/Browser\//i.test(path) || /^tests\/\.pest\/snapshots\/Browser\//i.test(path)) {
    return { path, scope: 'global', reason: 'contrato-visual' };
  }
  if (lower === '.github/workflows/visual-regression.yml' || lower === 'lighthouserc.json') {
    return { path, scope: 'global', reason: 'infra-visual' };
  }
  if (/^(?:package(?:-lock)?\.json|pnpm-lock\.yaml|yarn\.lock|vite\.config\.|tailwind\.config\.|postcss\.config\.)/i.test(path)) {
    return { path, scope: 'global', reason: 'toolchain-frontend' };
  }
  // Raiz de UI conhecida, extensão nova/desconhecida: conservador por desenho.
  if (/^resources\//i.test(path) || /^Modules\/[^/]+\/Resources\//i.test(path)) {
    return { path, scope: 'global', reason: 'ui-desconhecida-conservadora' };
  }
  return null;
}

export function classifyFiles(files, { readContent = () => '' } = {}) {
  const impacted = [];
  for (const rawPath of [...new Set(files.map(normalizePath).filter(Boolean))]) {
    let hit = classifyFile(rawPath);
    if (!hit && /(?:Http\/Controllers\/|^routes\/).+\.php$/i.test(rawPath)) {
      hit = classifyFile(rawPath, readContent(rawPath));
    }
    if (hit) impacted.push(hit);
  }
  const screens = [...new Set(impacted.flatMap((item) => [item.screen, ...(item.screens ?? [])]).filter(Boolean))].sort();
  const scope = impacted.some((item) => item.scope === 'global') ? 'global' : impacted.length ? 'targeted' : 'none';
  return { visual_required: impacted.length > 0, scope, screens, impacted };
}

/** Contraprova do required check: impacto, modo e execução precisam concordar. */
export function validateExecution({ visualRequired, mode, pixelOutcome, uncoveredScreens = [] }) {
  if (!['true', 'false'].includes(visualRequired)) return ['classificador de impacto não produziu decisão booleana'];
  if (uncoveredScreens.length > 0) return [`telas afetadas sem contrato visreg: ${uncoveredScreens.join(', ')}`];
  if (visualRequired !== mode) return [`impacto=${visualRequired}, mas modo pesado=${mode}`];
  if (visualRequired === 'true' && !['success', 'failure'].includes(pixelOutcome)) {
    return ['impacto visual detectado, mas nenhum pixel-diff executou (verde vazio)'];
  }
  return [];
}

export function validateScreenManifest(entries, { baselineExists = () => true, sourceExists = () => true } = {}) {
  if (!Array.isArray(entries) || entries.length === 0) return ['manifesto visreg vazio ou invalido'];
  const errors = [];
  const sources = new Set();
  for (const [index, entry] of entries.entries()) {
    for (const field of ['screen', 'source', 'route', 'anchor', 'baseline']) {
      if (typeof entry?.[field] !== 'string' || entry[field].trim() === '') errors.push(`entrada ${index}: ${field} ausente`);
    }
    if (entry?.route && !entry.route.startsWith('/')) errors.push(`entrada ${index}: route deve comecar com /`);
    if (entry?.source && sources.has(entry.source)) errors.push(`source duplicado: ${entry.source}`);
    if (entry?.source) sources.add(entry.source);
    if (entry?.baseline && !baselineExists(entry.baseline)) errors.push(`baseline ausente: ${entry.baseline}`);
    if (entry?.source && !sourceExists(entry.source)) errors.push(`source Inertia ausente: ${entry.source}`);
  }
  return errors;
}

export function coverageForScreens(screens, entries) {
  const contracted = new Set(entries.map((entry) => entry.source));
  return {
    covered_screens: screens.filter((screen) => contracted.has(screen)),
    uncovered_screens: screens.filter((screen) => !contracted.has(screen)),
  };
}

function git(args) {
  return execFileSync('git', args, { cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
}

const argValue = (argv, name, fallback = '') =>
  (argv.find((arg) => arg.startsWith(`--${name}=`)) || `--${name}=${fallback}`).slice(name.length + 3);

function jsonArray(value) {
  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? parsed : [`valor nao-array: ${value}`];
  } catch {
    return [`JSON invalido: ${value}`];
  }
}

function run(argv) {
  const base = argValue(argv, 'base', 'origin/main');
  const head = argValue(argv, 'head', 'HEAD');
  const githubOutput = argValue(argv, 'github-output');
  const files = git(['diff', '--name-only', '--diff-filter=ACDMRTUXB', base, head, '--'])
    .split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
  const readContent = (path) => {
    const disk = join(ROOT, path);
    const current = existsSync(disk) ? readFileSync(disk, 'utf8') : '';
    let previous = '';
    try { previous = git(['show', `${base}:${path}`]); } catch { /* arquivo novo */ }
    return `${previous}\n${current}`;
  };
  const manifest = JSON.parse(readFileSync(join(ROOT, SCREEN_MANIFEST), 'utf8'));
  const manifestErrors = validateScreenManifest(manifest, {
    baselineExists: (baseline) => existsSync(join(ROOT, 'tests/.pest/snapshots/Browser/CoreScreens/PixelBaselineTest', baseline)),
    sourceExists: (source) => ['.tsx', '/Index.tsx', '.jsx', '/Index.jsx', '.ts', '/Index.ts', '.js', '/Index.js', '.vue', '/Index.vue']
      .some((suffix) => existsSync(join(ROOT, 'resources/js/Pages', `${source}${suffix}`))),
  });
  if (manifestErrors.length) throw new Error(`contrato ${SCREEN_MANIFEST} invalido: ${manifestErrors.join('; ')}`);
  const impact = classifyFiles(files, { readContent });
  const result = {
    base,
    head,
    changed_files: files.length,
    ...impact,
    ...coverageForScreens(impact.screens, manifest),
  };

  console.log(JSON.stringify(result, null, 2));
  if (githubOutput) {
    appendFileSync(githubOutput, `visual_required=${result.visual_required}\n`);
    appendFileSync(githubOutput, `scope=${result.scope}\n`);
    appendFileSync(githubOutput, `screens=${JSON.stringify(result.screens)}\n`);
    appendFileSync(githubOutput, `uncovered_screens=${JSON.stringify(result.uncovered_screens)}\n`);
    appendFileSync(githubOutput, `impacted_count=${result.impacted.length}\n`);
  }
  if (result.uncovered_screens.length) {
    throw new Error(`telas afetadas sem contrato em ${SCREEN_MANIFEST}: ${result.uncovered_screens.join(', ')}`);
  }
  return result;
}

const norm = (path) => { try { return realpathSync(path).replace(/\\/g, '/').toLowerCase(); } catch { return normalizePath(path).toLowerCase(); } };
const isEntry = !!process.argv[1] && norm(fileURLToPath(import.meta.url)) === norm(process.argv[1]);

function selfTest() {
  assert.equal(normalizePath('resources\\css\\cockpit.css'), 'resources/css/cockpit.css');
  assert.equal(classifyFile('resources/js/Pages/Sells/Create.tsx')?.screen, 'Sells/Create');
  assert.equal(classifyFile('resources/js/Pages/Index.tsx')?.screen, 'Index');
  const privatePart = classifyFile('resources/js/Pages/Sells/_components/Card.tsx');
  assert.deepEqual([privatePart?.scope, privatePart?.screen], ['global', 'Sells']);

  for (const path of [
    'resources/js/Components/shared/PageHeader.tsx',
    'resources/css/cockpit.css',
    'resources/views/auth/login.blade.php',
    'public/fonts/inter.woff2',
    'tests/.pest/snapshots/Browser/CoreScreens/foo.snap',
    'package-lock.json',
    '.github/workflows/visual-regression.yml',
  ]) assert.equal(classifyFile(path)?.scope, 'global', path);

  const controller = classifyFile('app/Http/Controllers/X.php', 'return Inertia::render("Dashboard/Index");');
  assert.deepEqual([controller?.reason, controller?.screens], ['controller-inertia', ['Dashboard']]);
  assert.equal(classifyFile('app/Http/Controllers/Api.php', 'return response()->json([]);'), null);
  assert.equal(classifyFile('resources/js/Pages/Sells/Index.charter.md'), null);

  const targeted = classifyFiles(['resources/js/Pages/Sells/Create.tsx', 'resources/js/Pages/Sells/Create.tsx']);
  assert.deepEqual([targeted.visual_required, targeted.scope, targeted.screens], [true, 'targeted', ['Sells/Create']]);
  assert.equal(classifyFiles(['resources/css/inertia.css']).scope, 'global');
  assert.equal(classifyFiles(['docs/arquitetura.md']).visual_required, false);

  const contract = [{ screen: 'Venda', source: 'Sells/Create', route: '/sells/create', anchor: 'Venda', baseline: 'venda.snap' }];
  assert.deepEqual(validateScreenManifest(contract), []);
  assert.ok(validateScreenManifest([]).length > 0);
  assert.ok(validateScreenManifest([...contract, contract[0]]).length > 0);
  assert.ok(validateScreenManifest(contract, { baselineExists: () => false }).length > 0);
  assert.deepEqual(coverageForScreens(['Cliente', 'Sells/Create'], contract).uncovered_screens, ['Cliente']);
  assert.ok(validateExecution({ visualRequired: 'true', mode: 'false', pixelOutcome: 'success' }).length > 0);
  assert.ok(validateExecution({ visualRequired: 'true', mode: 'true', pixelOutcome: 'skipped' }).length > 0);
  assert.deepEqual(validateExecution({ visualRequired: 'true', mode: 'true', pixelOutcome: 'success' }), []);
  console.log('ui-impact selftest: sensibilidade, especificidade e fail-closed passaram');
}

if (isEntry) {
  const argv = process.argv.slice(2);
  if (argv.includes('--selftest')) {
    try { selfTest(); } catch (error) { console.error(error); process.exitCode = 1; }
  } else if (argv.includes('--assert-execution')) {
    const errors = validateExecution({
      visualRequired: argValue(argv, 'visual-required'),
      mode: argValue(argv, 'mode'),
      pixelOutcome: argValue(argv, 'pixel-outcome'),
      uncoveredScreens: jsonArray(argValue(argv, 'uncovered-screens', '[]')),
    });
    if (errors.length) {
      for (const error of errors) console.error(`::error::${error}`);
      process.exitCode = 1;
    } else console.log('canário anti-verde-vazio: coerente');
  } else {
    try { run(argv); }
    catch (error) {
      console.error(`ui-impact: não foi possível classificar o diff — fail-closed: ${error.message}`);
      process.exitCode = 1;
    }
  }
}
