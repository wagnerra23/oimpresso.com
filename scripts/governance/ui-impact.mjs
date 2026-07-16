#!/usr/bin/env node
/**
 * Fonte única do skip-as-pass do visual-regression.
 *
 * Classifica o diff por impacto visual. Mudanças diretas de Page produzem uma
 * lista de telas; mudanças compartilhadas/fundacionais exigem o núcleo global.
 * Paths dentro de raízes de UI desconhecidos falham de forma conservadora:
 * rodam o visual em vez de ganhar um verde vazio.
 */
import { appendFileSync, existsSync, readFileSync, realpathSync, readdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join, posix, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const ROOT = process.cwd();
const SCREEN_MANIFEST = 'tests/Browser/visreg-screens.json';
const SOURCE_EXT = String.raw`(?:[cm]?[jt]sx?|vue)`;
const ASSET_EXT = String.raw`(?:[cm]?js|css|scss|sass|less|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|otf)`;
const PAGE_AUX_DIR = /^(?:_.*|components?|partials?|hooks?|utils?|lib|types?|constants?|schemas?|stores?|contexts?)$/i;
const BACKEND_ROUTE = /^(?:routes\/.+|Modules\/[^/]+\/(?:(?:Routes|routes)\/.+|Http\/routes))\.php$/i;
const CONTENT_AWARE_BACKEND = /(?:Http\/Controllers\/.+\.php$|^routes\/.+\.php$|^Modules\/[^/]+\/(?:(?:Routes|routes)\/.+|Http\/routes)\.php$)/i;

export const normalizePath = (path) => String(path || '').replace(/\\/g, '/').replace(/^\.\//, '');

function pageScreen(path) {
  const rel = path.replace(/^resources\/js\/Pages\//, '').replace(new RegExp(`\\.${SOURCE_EXT}$`, 'i'), '');
  const parts = rel.split('/');
  const auxiliaryPart = parts.findIndex((part, index) => index < parts.length - 1 && PAGE_AUX_DIR.test(part));
  const visible = auxiliaryPart > 0 ? parts.slice(0, auxiliaryPart) : parts;
  if (visible.length > 1 && visible.at(-1)?.toLowerCase() === 'index') visible.pop();
  return visible.join('/');
}

function isPageSource(path) {
  return new RegExp(`^resources/js/Pages/.+\\.${SOURCE_EXT}$`, 'i').test(path);
}

function isPageAuxiliary(path) {
  const rel = path.replace(/^resources\/js\/Pages\//, '').split('/');
  return rel.slice(0, -1).some((part) => PAGE_AUX_DIR.test(part));
}

function importSpecifiers(content) {
  const found = [];
  const patterns = [
    /\b(?:import|export)\s+(?:type\s+)?(?:[^;'\"]*?\s+from\s+)?['\"]([^'\"]+)['\"]/g,
    /\b(?:import|require)\s*\(\s*['\"]([^'\"]+)['\"]\s*\)/g,
  ];
  for (const pattern of patterns) {
    for (const match of String(content || '').matchAll(pattern)) found.push(match[1]);
  }
  return [...new Set(found)];
}

function resolveImport(fromPath, specifier, knownPaths) {
  let base;
  if (specifier.startsWith('@/')) base = `resources/js/${specifier.slice(2)}`;
  else if (specifier.startsWith('.')) base = posix.normalize(posix.join(posix.dirname(fromPath), specifier));
  else return null;

  const extensions = ['.tsx', '.ts', '.jsx', '.js', '.mjs', '.cjs', '.vue'];
  const candidates = [base];
  for (const extension of extensions) candidates.push(`${base}${extension}`, `${base}/index${extension}`);
  return candidates.map(normalizePath).find((candidate) => knownPaths.has(candidate)) ?? null;
}

/** Grafo reverso importado → Pages consumidoras, incluindo wrappers transitivos. */
export function createConsumerResolver(sourceEntries) {
  const sources = new Map([...sourceEntries].map(([path, content]) => [normalizePath(path), String(content || '')]));
  const knownPaths = new Set(sources.keys());
  const reverse = new Map();
  for (const [consumer, content] of sources) {
    for (const specifier of importSpecifiers(content)) {
      const imported = resolveImport(consumer, specifier, knownPaths);
      if (!imported) continue;
      if (!reverse.has(imported)) reverse.set(imported, new Set());
      reverse.get(imported).add(consumer);
    }
  }

  return (rawPath) => {
    const queue = [normalizePath(rawPath)];
    const visited = new Set(queue);
    const screens = new Set();
    while (queue.length) {
      const imported = queue.shift();
      for (const consumer of reverse.get(imported) ?? []) {
        if (visited.has(consumer)) continue;
        visited.add(consumer);
        if (isPageSource(consumer) && !isPageAuxiliary(consumer)) screens.add(pageScreen(consumer));
        queue.push(consumer);
      }
    }
    return [...screens].sort();
  };
}

export function createRepositoryConsumerResolver() {
  const entries = new Map();
  const visit = (directory) => {
    for (const item of readdirSync(directory, { withFileTypes: true })) {
      const fullPath = join(directory, item.name);
      if (item.isDirectory()) visit(fullPath);
      else if (new RegExp(`\\.${SOURCE_EXT}$`, 'i').test(item.name)) {
        entries.set(normalizePath(relative(ROOT, fullPath)), readFileSync(fullPath, 'utf8'));
      }
    }
  };
  visit(join(ROOT, 'resources/js'));
  return createConsumerResolver(entries);
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
  if (isPageSource(path) && isPageAuxiliary(path)) {
    return { path, scope: 'global', reason: 'componente-de-page-compartilhado', screen: pageScreen(path) };
  }
  if (isPageSource(path)) {
    return { path, scope: 'targeted', reason: 'page-inertia', screen: pageScreen(path) };
  }
  if (/^resources\/js\//i.test(path)) return { path, scope: 'global', reason: 'frontend-compartilhado' };
  if (/^resources\/(?:css|views|lang|images?|fonts?)\//i.test(path)) return { path, scope: 'global', reason: 'fundacao-visual' };
  if (/^lang\//i.test(path)) return { path, scope: 'global', reason: 'traducao-visivel' };
  if (/^Modules\/[^/]+\/Resources\/(?:js|css|views|lang|images?|fonts?|menus)\//i.test(path)) {
    return { path, scope: 'global', reason: 'ui-de-modulo' };
  }
  if (new RegExp(`^public/.+\\.${ASSET_EXT}$`, 'i').test(path)) {
    return { path, scope: 'global', reason: 'asset-publico' };
  }
  if (/^(?:app|Modules\/[^/]+)\/Http\/Controllers\/.+\.php$/i.test(path) && inertia) {
    return { path, scope: 'global', reason: 'controller-inertia', screens: inertiaScreens(content) };
  }
  if (BACKEND_ROUTE.test(path) && (/(?:^|\/)web\.php$/i.test(path) || inertia || /\/Http\/routes\.php$/i.test(path))) {
    return { path, scope: 'global', reason: 'rota-inertia', screens: inertiaScreens(content) };
  }
  if (/^(?:app|Modules\/[^/]+)\/Http\/Middleware\/HandleInertiaRequests\.php$/i.test(path)
    || /^app\/Services\/.*(?:Menu|Navigation|Nav|Shell|Inertia|Frontend).*\.php$/i.test(path)
    || /^app\/View\//i.test(path)
    || /^app\/Providers\/(?:App|Route)ServiceProvider\.php$/i.test(path)
    || /^config\/(?:app|inertia|view|vite)\.php$/i.test(path)) {
    return { path, scope: 'global', reason: 'backend-apresentacao' };
  }
  if (/^tests\/Browser\//i.test(path) || /^tests\/\.pest\/snapshots\/Browser\//i.test(path)) {
    return { path, scope: 'global', reason: 'contrato-visual' };
  }
  if (lower === '.github/workflows/visual-regression.yml' || lower === 'scripts/governance/ui-impact.mjs' || lower === 'lighthouserc.json') {
    return { path, scope: 'global', reason: 'infra-visual' };
  }
  if (/^(?:package(?:-lock)?\.json|pnpm-lock\.yaml|yarn\.lock|composer\.(?:json|lock))$/i.test(path)
    || /(?:^|\/)vite(?:\.[^/]+)*\.config\.[^/]+$/i.test(path)
    || /(?:^|\/)(?:webpack\.mix\.js|tsconfig(?:\.[^/]+)?\.json|tailwind\.config\.[^/]+|postcss\.config\.[^/]+)$/i.test(path)) {
    return { path, scope: 'global', reason: 'toolchain-frontend' };
  }
  // Raiz de UI conhecida, extensão nova/desconhecida: conservador por desenho.
  if (/^resources\//i.test(path) || /^Modules\/[^/]+\/Resources\//i.test(path)) {
    return { path, scope: 'global', reason: 'ui-desconhecida-conservadora' };
  }
  return null;
}

function summarizeImpact(impacted) {
  const screens = [...new Set(impacted.flatMap((item) => [item.screen, ...(item.screens ?? [])]).filter(Boolean))].sort();
  const scope = impacted.some((item) => item.scope === 'global') ? 'global' : impacted.length ? 'targeted' : 'none';
  return { visual_required: impacted.length > 0, scope, screens, impacted };
}

export function classifyChanges(changes, { readContent = () => '', consumerScreens = () => [] } = {}) {
  const impacted = [];
  const seen = new Set();
  for (const change of changes) {
    const rawPath = normalizePath(change?.path);
    const status = String(change?.status || 'M');
    if (!rawPath || seen.has(`${status}:${rawPath}`)) continue;
    seen.add(`${status}:${rawPath}`);

    if (status.startsWith('D')) {
      const removed = classifyFile(rawPath, readContent(rawPath));
      if (removed) impacted.push({ path: rawPath, scope: 'global', reason: `${removed.reason}-removido` });
      continue;
    }

    const content = CONTENT_AWARE_BACKEND.test(rawPath) ? readContent(rawPath) : '';
    let hit = classifyFile(rawPath, content);
    if (hit?.scope === 'global' && /^resources\/js\//i.test(rawPath)) {
      const consumers = consumerScreens(rawPath);
      if (consumers.length) hit = { ...hit, screens: [...new Set([...(hit.screens ?? []), ...consumers])].sort() };
    }
    if (status.startsWith('R') && change.oldPath) {
      const oldPath = normalizePath(change.oldPath);
      const oldHit = classifyFile(oldPath, readContent(oldPath));
      if (oldHit) impacted.push({ path: oldPath, scope: 'global', reason: `${oldHit.reason}-renomeado` });
    }
    if (hit) impacted.push(hit);
  }
  return summarizeImpact(impacted);
}

export function classifyFiles(files, options = {}) {
  return classifyChanges([...new Set(files.map(normalizePath).filter(Boolean))].map((path) => ({ status: 'M', path })), options);
}

export function parseNameStatusZ(raw) {
  const fields = String(raw || '').split('\0');
  if (fields.at(-1) === '') fields.pop();
  const changes = [];
  for (let index = 0; index < fields.length;) {
    const status = fields[index++];
    if (!status) continue;
    if (status.startsWith('R') || status.startsWith('C')) {
      const oldPath = normalizePath(fields[index++]);
      const path = normalizePath(fields[index++]);
      if (!oldPath || !path) throw new Error(`diff --name-status truncado em ${status}`);
      changes.push({ status, oldPath, path });
    } else {
      const path = normalizePath(fields[index++]);
      if (!path) throw new Error(`diff --name-status truncado em ${status}`);
      changes.push({ status, path });
    }
  }
  return changes;
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

export function validateScreenManifest(entries, {
  baselineExists = () => true,
  sourceExists = () => true,
  componentExists = () => true,
} = {}) {
  if (!Array.isArray(entries) || entries.length === 0) return ['manifesto visreg vazio ou invalido'];
  const errors = [];
  const uniqueFields = Object.fromEntries(
    ['screen', 'source', 'component', 'route', 'baseline'].map((field) => [field, new Set()]),
  );
  for (const [index, entry] of entries.entries()) {
    for (const field of ['screen', 'source', 'component', 'route', 'anchor', 'baseline']) {
      if (typeof entry?.[field] !== 'string' || entry[field].trim() === '') errors.push(`entrada ${index}: ${field} ausente`);
    }
    if (entry?.route && !entry.route.startsWith('/')) errors.push(`entrada ${index}: route deve comecar com /`);
    for (const [field, values] of Object.entries(uniqueFields)) {
      if (entry?.[field] && values.has(entry[field])) errors.push(`${field} duplicado: ${entry[field]}`);
      if (entry?.[field]) values.add(entry[field]);
    }
    if (entry?.baseline && !baselineExists(entry.baseline)) errors.push(`baseline ausente: ${entry.baseline}`);
    if (entry?.source && !sourceExists(entry.source)) errors.push(`source Inertia ausente: ${entry.source}`);
    if (entry?.component && !componentExists(entry.component)) errors.push(`componente Inertia ausente: ${entry.component}`);
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
  const diffBase = git(['merge-base', base, head]).trim();
  if (!diffBase) throw new Error(`merge-base ausente entre ${base} e ${head}`);
  const changes = parseNameStatusZ(git(['diff', '--name-status', '-z', '--find-renames', '--diff-filter=ACDMRTUXB', diffBase, head, '--']));
  const readContent = (path) => {
    const disk = join(ROOT, path);
    const current = existsSync(disk) ? readFileSync(disk, 'utf8') : '';
    let previous = '';
    try { previous = git(['show', `${diffBase}:${path}`]); } catch { /* arquivo novo */ }
    return `${previous}\n${current}`;
  };
  const manifest = JSON.parse(readFileSync(join(ROOT, SCREEN_MANIFEST), 'utf8'));
  const manifestErrors = validateScreenManifest(manifest, {
    baselineExists: (baseline) => existsSync(join(ROOT, 'tests/.pest/snapshots/Browser/CoreScreens/PixelBaselineTest', baseline)),
    sourceExists: (source) => ['.tsx', '/Index.tsx', '.jsx', '/Index.jsx', '.ts', '/Index.ts', '.js', '/Index.js', '.vue', '/Index.vue']
      .some((suffix) => existsSync(join(ROOT, 'resources/js/Pages', `${source}${suffix}`))),
    componentExists: (component) => ['.tsx', '.jsx', '.ts', '.js', '.vue']
      .some((suffix) => existsSync(join(ROOT, 'resources/js/Pages', `${component}${suffix}`))),
  });
  if (manifestErrors.length) throw new Error(`contrato ${SCREEN_MANIFEST} invalido: ${manifestErrors.join('; ')}`);
  const needsConsumerGraph = changes.some((change) => /^resources\/js\//i.test(normalizePath(change.path)));
  const consumerScreens = needsConsumerGraph ? createRepositoryConsumerResolver() : () => [];
  const impact = classifyChanges(changes, { readContent, consumerScreens });
  const result = {
    base,
    diff_base: diffBase,
    head,
    changed_files: changes.length,
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
  const publicComponent = classifyFile('resources/js/Pages/Compras/components/Drawer.tsx');
  assert.deepEqual([publicComponent?.scope, publicComponent?.screen], ['global', 'Compras']);

  for (const path of [
    'resources/js/Components/shared/PageHeader.tsx',
    'resources/css/cockpit.css',
    'resources/views/auth/login.blade.php',
    'lang/pt/messages.php',
    'public/fonts/inter.woff2',
    'public/favicon.ico',
    'public/vendor/myfatoorah/css/style.css',
    'tests/.pest/snapshots/Browser/CoreScreens/foo.snap',
    'package-lock.json',
    'composer.lock',
    'vite.inertia.config.mjs',
    'Modules/Sells/vite.config.js',
    'config/inertia.php',
    'app/Http/Middleware/HandleInertiaRequests.php',
    'app/Services/ShellMenuBuilder.php',
    'app/View/Helpers/Form.php',
    'scripts/governance/ui-impact.mjs',
    '.github/workflows/visual-regression.yml',
  ]) assert.equal(classifyFile(path)?.scope, 'global', path);

  const controller = classifyFile('app/Http/Controllers/X.php', 'return Inertia::render("Dashboard/Index");');
  assert.deepEqual([controller?.reason, controller?.screens], ['controller-inertia', ['Dashboard']]);
  assert.equal(classifyFile('app/Http/Controllers/Api.php', 'return response()->json([]);'), null);
  assert.equal(classifyFile('resources/js/Pages/Sells/Index.charter.md'), null);

  const targeted = classifyFiles(['resources/js/Pages/Sells/Create.tsx', 'resources/js/Pages/Sells/Create.tsx']);
  assert.deepEqual([targeted.visual_required, targeted.scope, targeted.screens], [true, 'targeted', ['Sells/Create']]);
  const route = classifyFiles(['routes/web.php'], { readContent: () => "return inertia('Tarefas/Index');" });
  assert.deepEqual(route.screens, ['Tarefas']);
  const moduleRoute = classifyFiles(['Modules/KB/Http/routes.php'], { readContent: () => "return Inertia::render('KB/Index');" });
  assert.deepEqual(moduleRoute.screens, ['KB']);
  assert.deepEqual(parseNameStatusZ('D\0resources/js/Pages/Old/Index.tsx\0'), [
    { status: 'D', path: 'resources/js/Pages/Old/Index.tsx' },
  ]);
  assert.deepEqual(parseNameStatusZ('R100\0resources/js/Pages/Old.tsx\0resources/js/Pages/New.tsx\0'), [
    { status: 'R100', oldPath: 'resources/js/Pages/Old.tsx', path: 'resources/js/Pages/New.tsx' },
  ]);
  const deleted = classifyChanges([{ status: 'D', path: 'resources/js/Pages/Old/Index.tsx' }]);
  assert.deepEqual([deleted.scope, deleted.screens], ['global', []]);
  const consumers = createConsumerResolver(new Map([
    ['resources/js/Components/Site/Hero.tsx', 'export default function Hero() {}'],
    ['resources/js/Layouts/SiteLayout.tsx', "import Hero from '@/Components/Site/Hero';"],
    ['resources/js/Pages/Site/Home.tsx', "import SiteLayout from '../../Layouts/SiteLayout';"],
    ['resources/js/Lib/money.ts', 'export const money = 1;'],
    ['resources/js/Pages/Sells/Create.tsx', "export { money } from '@/Lib/money';"],
  ]));
  assert.deepEqual(consumers('resources/js/Components/Site/Hero.tsx'), ['Site/Home']);
  assert.deepEqual(consumers('resources/js/Lib/money.ts'), ['Sells/Create']);
  const shared = classifyFiles(['resources/js/Components/Site/Hero.tsx'], { consumerScreens: consumers });
  assert.deepEqual([shared.scope, shared.screens], ['global', ['Site/Home']]);
  assert.equal(classifyFiles(['resources/css/inertia.css']).scope, 'global');
  assert.equal(classifyFiles(['docs/arquitetura.md']).visual_required, false);

  const contract = [{ screen: 'Venda', source: 'Sells/Create', component: 'Sells/Create', route: '/sells/create', anchor: 'Venda', baseline: 'venda.snap' }];
  assert.deepEqual(validateScreenManifest(contract), []);
  assert.ok(validateScreenManifest([]).length > 0);
  assert.ok(validateScreenManifest([...contract, contract[0]]).length > 0);
  for (const field of ['screen', 'component', 'route', 'baseline']) {
    const duplicate = { ...contract[1], [field]: contract[0][field] };
    assert.ok(
      validateScreenManifest([contract[0], duplicate]).some((error) => error.includes(`${field} duplicado`)),
      `manifesto deve rejeitar ${field} duplicado`,
    );
  }
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
