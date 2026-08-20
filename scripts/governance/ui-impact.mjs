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
import { raizesDePages } from '../qa/page-path.mjs';

const ROOT = process.cwd();
const SCREEN_MANIFEST = 'tests/Browser/visreg-screens.json';
const SOURCE_EXT = String.raw`(?:[cm]?[jt]sx?|vue)`;
const ASSET_EXT = String.raw`(?:[cm]?js|css|scss|sass|less|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|otf)`;
const PAGE_AUX_DIR = /^(?:_.*|components?|partials?|hooks?|utils?|lib|types?|constants?|schemas?|stores?|contexts?)$/i;
const BACKEND_ROUTE = /^(?:routes\/.+|Modules\/[^/]+\/(?:(?:Routes|routes)\/.+|Http\/routes))\.php$/i;
const CONTENT_AWARE_BACKEND = /(?:Http\/Controllers\/.+\.php$|^routes\/.+\.php$|^Modules\/[^/]+\/(?:(?:Routes|routes)\/.+|Http\/routes)\.php$)/i;

export const normalizePath = (path) => String(path || '').replace(/\\/g, '/').replace(/^\.\//, '');

function pageScreen(path) {
  // Descasca a raiz (núcleo OU módulo dono) — o nome da tela é o namespace, não o local.
  const rel = path.replace(/^(?:Modules\/[^/]+\/[Rr]esources|resources)\/js\/Pages\//, '')
    .replace(new RegExp(`\\.${SOURCE_EXT}$`, 'i'), '');
  const parts = rel.split('/');
  const auxiliaryPart = parts.findIndex((part, index) => index < parts.length - 1 && PAGE_AUX_DIR.test(part));
  const visible = auxiliaryPart > 0 ? parts.slice(0, auxiliaryPart) : parts;
  if (visible.length > 1 && visible.at(-1)?.toLowerCase() === 'index') visible.pop();
  return visible.join('/');
}

// DUAS raízes desde 2026-08-12: a tela pode morar no núcleo OU dentro do módulo dono
// (`Modules/<X>/Resources/js/Pages/**`). O nome da tela é o NAMESPACE, que independe da raiz —
// sem isto, tela migrada some do impacto e o gate visual deixa de exercitá-la em silêncio.
const RAIZ_PAGES_RE = '(?:Modules/[^/]+/[Rr]esources|resources)/js/Pages';
const RAIZ_PAGES_STRIP = /^(?:Modules\/[^/]+\/[Rr]esources|resources)\/js\/Pages\//;

function isPageSource(path) {
  return new RegExp(`^${RAIZ_PAGES_RE}/.+\\.${SOURCE_EXT}$`, 'i').test(path);
}

function isPageAuxiliary(path) {
  const rel = path.replace(RAIZ_PAGES_STRIP, '').split('/');
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
  // As telas do módulo dono também são consumidoras — sem varrê-las, mexer num componente
  // compartilhado não acusaria as telas migradas, e o gate visual as pularia em silêncio.
  for (const raiz of raizesDePages(ROOT)) {
    if (!raiz.replace(/\\/g, '/').includes('/Modules/')) continue; // o núcleo já entrou acima
    if (existsSync(raiz)) visit(raiz);
  }
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

export function classifyChanges(changes, { readContent = () => '', consumerScreens = () => [], manifestoSoAdiciona = () => false } = {}) {
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

    // ADICIONAR baseline de tela NOVA nao e o vetor que o `contrato-visual`->global protege.
    //
    // O vetor real e REBASELINAR EM SILENCIO: trocar o .snap de uma tela que ja tinha
    // referencia, ou remove-lo. Nesses casos global segue certo — e o que o
    // baseline-tamper-guard vigia. Mas quando o .snap NASCE (status A), nao ha referencia
    // anterior pra sobrescrever, e as outras telas nao foram tocadas: rodar o nucleo-6
    // inteiro nao acrescenta protecao nenhuma.
    //
    // O CUSTO de nao distinguir era um beco sem saida ESTRUTURAL, medido em 2026-08-20 no
    // #6027: pra criar a baseline de uma tela nova voce e OBRIGADO a tocar o manifesto + o
    // .snap (o proprio gate manda faze-lo no MESMO commit); isso vira global; global exige o
    // nucleo-6 limpo; e o nucleo-6 nao estava limpo porque o #5995 mudou Produto/Unificado
    // sem regenerar a baseline dele. Resultado: NINGUEM conseguia adicionar tela nova
    // enquanto qualquer outra tela do nucleo estivesse com drift — de quem quer que fosse.
    //
    // A cobertura NAO cai: se o mesmo PR mexer em algo compartilhado, esse arquivo dispara
    // global pela regra DELE (frontend-compartilhado / componente-de-page-compartilhado /
    // fundacao-visual). O .snap forcar global era protecao REDUNDANTE.
    //
    // MEDIDO nos 300 commits mais recentes de main que tocam baseline de pixel: 29 commits,
    // dos quais 11 so ADICIONAM (viram targeted) e 18 modificam/removem (seguem global).
    if (hit?.reason === 'contrato-visual') {
      const nasceu = status.startsWith('A') && /^tests\/\.pest\/snapshots\/Browser\//i.test(rawPath);
      const manifestoAditivo = rawPath === SCREEN_MANIFEST && manifestoSoAdiciona(rawPath);
      if (nasceu || manifestoAditivo) {
        hit = { ...hit, scope: 'targeted', reason: 'contrato-visual-adicao' };
      }
    }
    if (hit?.scope === 'global' && /^resources\/js\//i.test(rawPath)) {
      const consumers = consumerScreens(rawPath);
      if (consumers.length) {
        const screens = [...new Set([...(hit.screens ?? []), ...consumers])].sort();
        // PROVENIÊNCIA (import real) VENCE O PALPITE DE PATH — 2026-07-16.
        // Pra um auxiliar, `screen` vem de pageScreen(), que corta o path no 1º diretório
        // aux: `Pages/<Mod>/<Sub>/_components/x.ts` → `<Mod>/<Sub>`. Isso só coincide com a
        // tela quando ela é `<Sub>/Index.tsx` (pageScreen poda "index"). Se a tela tem nome
        // próprio — `OficinaAuto/ServiceOrders/Board.tsx`, cujo Index foi aposentado — o
        // recorte gera uma tela FANTASMA (`OficinaAuto/ServiceOrders`) que não existe, nunca
        // tem contrato, e derruba o fail-closed do main() mesmo com a tela real já coberta.
        // Quando os imports resolvem os consumidores, eles são a proveniência REAL: usar o
        // palpite junto só adiciona fantasma. NÃO perde cobertura — `scope` continua 'global'
        // (a matriz inteira roda) e a tela real segue em `screens`. Sem consumidores resolvidos
        // o palpite é mantido (conservador, comportamento de sempre).
        // Doutrina: proveniência é o que o artefato declara, não a string do path.
        hit = hit.reason === 'componente-de-page-compartilhado'
          ? { ...hit, screen: undefined, screens }
          : { ...hit, screens };
      }
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
export function validateExecution({
  visualRequired,
  mode,
  pixelOutcome,
  uncoveredScreens = [],
  scope,
  expected,
  executed,
  compared,
}) {
  if (!['true', 'false'].includes(visualRequired)) return ['classificador de impacto não produziu decisão booleana'];
  // Ver o comentário no fail-closed do run(): em `global` o pixel roda o núcleo-6 e ignora
  // `screens`, então cobrar contrato do raio informativo é inalcançável (o shell resolve
  // ~190 consumidores). Em `targeted` a lista É a comparação — segue mordendo aqui E no
  // PixelBaselineTest. `scope` ausente ⇒ trata como targeted (conservador: sem informação,
  // mantém o fail-closed de sempre).
  if (scope !== 'global' && uncoveredScreens.length > 0) return [`telas afetadas sem contrato visreg: ${uncoveredScreens.join(', ')}`];
  if (visualRequired !== mode) return [`impacto=${visualRequired}, mas modo pesado=${mode}`];
  if (visualRequired === 'true' && !['success', 'failure'].includes(pixelOutcome)) {
    return ['impacto visual detectado, mas nenhum pixel-diff executou (verde vazio)'];
  }
  if (visualRequired === 'true') {
    const counts = [expected, executed, compared].map(Number);
    if (!counts.every(Number.isInteger) || counts[0] < 1 || counts.slice(1).some((count) => count < 0)) {
      return ['pixel-diff não publicou contagens válidas de expected/executed/compared'];
    }
    if (counts[0] !== counts[1] || counts[0] !== counts[2]) {
      return [`pixel-diff incompleto: expected=${counts[0]}, executed=${counts[1]}, compared=${counts[2]}`];
    }
  }
  return [];
}

/**
 * Escolhe a NARRATIVA do comentário de falha do gate visual — e NOMEIA o step que reprovou.
 *
 * O predicado de `scope` NÃO nasce aqui: é o MESMO de validateExecution() acima — targeted ⇒ a
 * lista de uncovered É a cobrança; global ⇒ o pixel roda o núcleo-6 e a lista é raio
 * informativo. O comentário era o único consumidor que a ignorava, então em `global` (onde a
 * lista quase nunca é vazia) toda falha virava "tela sem contrato". Medido 2026-08-20 no PR
 * #5976 · job 96505067528 — detalhe no corpo do PR desta mudança.
 *
 * Sem step instrumentado em `failure`, devolve `indeterminado`: DIZ que não sabe, em vez de
 * escolher a narrativa plausível — e sem calar.
 */
export function explainFailure({ scope, uncoveredScreens = [], steps = [], grayZone = [], runUrl = '' }) {
  const passo = steps.find((step) => step?.outcome === 'failure')?.nome || null;
  const linhaPasso = passo
    ? `**Step que reprovou:** \`${passo}\``
    : '**Step que reprovou:** não identificado entre os steps instrumentados.';
  const aprovacao = 'Mudança intencional? **aprovação visual do [W] (gate F1.5)** no PR + baseline regenerada pelo **modo update** (`workflow_dispatch`), no MESMO PR. Regressão? corrija o código.';

  // MESMO predicado de validateExecution: em `global` a lista é informativa, não cobrança.
  if (scope !== 'global' && uncoveredScreens.length > 0) {
    return { modo: 'sem-contrato', passo, corpo: [
      '## 🚧 Tela sem contrato visual (fail-closed)', '', linhaPasso, '',
      `**Não é diff de pixel — não há baseline pra comparar.** Escopo \`${scope || 'targeted'}\`, logo esta lista É a comparação. Telas afetadas sem entrada em \`tests/Browser/visreg-screens.json\`: \`${JSON.stringify(uncoveredScreens)}\`.`,
      '', '### Como resolver',
      '1. Rode o **modo update** (`workflow_dispatch`) na sua branch — gera a baseline no runner canônico.',
      '2. Do artifact `pixel-snapshots`, copie **apenas** o `.snap` NOVO da sua tela (o update regenera TODAS as baselines de pixel — sobrescrever as outras muda em silêncio a referência de telas que seu PR não toca).',
      '3. **Manifesto e `.snap` no MESMO commit** — meia unidade quebra o gate em TODO PR do repo.',
      '4. Aprovação visual do [W] (gate F1.5) registrada no PR.',
    ] };
  }

  if (!passo) {
    return { modo: 'indeterminado', passo: null, corpo: [
      '## ❓ Gate visual vermelho — causa NÃO determinada', '',
      'Nenhum step instrumentado (classificador · Pest Browser · pixel-diff · matriz de estados · fluxos Financeiro/Compras/Sells · canário) reporta `failure`.',
      '', 'Normalmente isso é falha em step **não instrumentado** (setup PHP/Node, dependências, seed do tenant, build do Inertia) ou cancelamento do job.',
      '', `**Não vou escolher uma narrativa que não medi.** Abra o run e veja o primeiro step vermelho${runUrl ? `: ${runUrl}` : '.'}`,
    ] };
  }

  const cinza = grayZone.filter((item) => item?.screen);
  if (cinza.length > 0) {
    return { modo: 'zona-cinza', passo, corpo: [
      '## 🟡 Gate visual — ZONA CINZA (bloqueia até revisão do [W])', '', linhaPasso, '',
      `**${cinza.length} tela(s)** ficaram ENTRE os limiares (τ_baixo..τ_alto). Zona cinza não é regressão clara: bloqueia porque exige olho humano.`,
      '', '| tela | diff medido |', '|---|---|',
      ...cinza.map((item) => `| \`${item.screen}\` | ${(Number(item.ratio) * 100).toFixed(4)}% |`),
      '', '### Como resolver',
      '1. Baixe o artifact `pixel-diff-views` e olhe o diff-view de cada tela acima.',
      '2. Aprovada pelo [W]? aplique o label `visreg-gray-approved` — ou regenere a baseline pelo **modo update**.',
      `3. ${aprovacao}`,
      '', '_Se ALÉM da zona cinza houver tela acima de τ_alto (regressão clara), ela está no log do step — esta lista cobre só a faixa do meio._',
    ] };
  }

  return { modo: 'step-nomeado', passo, corpo: [
    `## 🔴 Gate visual reprovou em \`${passo}\``, '', linhaPasso, '',
    'Causas possíveis deste step (o gate **não** escolhe uma sem medir): **regressão clara** (> τ_alto) · **dimensões divergentes** entre baseline e render · **baseline ausente** · a tela/fluxo **não montou**.',
    '', '### Como resolver',
    '1. Abra o log do step acima — ele nomeia a tela e o diff medido.',
    '2. Baixe o artifact `pixel-diff-views` para comparar lado-a-lado.',
    `3. ${aprovacao}`,
  ] };
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
  // O manifesto e UM arquivo, entao adicionar tela nele e sempre status 'M' — o status do
  // diff nao distingue "entrou tela nova" de "mexeram numa que ja existia". Quem distingue e
  // o CONTEUDO: se toda entrada da base sobreviveu IDENTICA e o head so tem entradas a mais,
  // a mudanca e puramente aditiva e nada foi rebaselinado em silencio.
  const entradasPorSource = (texto) => {
    try {
      const j = JSON.parse(texto);
      const xs = Array.isArray(j) ? j : (j?.screens ?? []);
      return new Map(xs.filter((e) => e?.source).map((e) => [e.source, JSON.stringify(e)]));
    } catch { return null; }
  };
  const manifestoSoAdiciona = (path) => {
    if (path !== SCREEN_MANIFEST) return false;
    let antes = '';
    try { antes = git(['show', `${diffBase}:${path}`]); } catch { return false; }
    // O head vem do GIT, nao do disco: `--head` pode ser uma ref (outro branch) e ái o disco
    // é de outra coisa. No CI dá no mesmo (checkout == head); localmente, ler o disco mente.
    let depois = '';
    try { depois = git(['show', `${head}:${path}`]); }
    catch { const disk = join(ROOT, path); if (!existsSync(disk)) return false; depois = readFileSync(disk, 'utf8'); }
    const a = entradasPorSource(antes);
    const b = entradasPorSource(depois);
    // JSON ilegivel de qualquer lado -> conservador (global). Nao inventa aditivo.
    if (!a || !b) return false;
    for (const [source, json] of a) {
      if (b.get(source) !== json) return false;   // sumiu ou mudou => NAO e aditivo
    }
    return b.size > a.size;
  };

  const manifest = JSON.parse(readFileSync(join(ROOT, SCREEN_MANIFEST), 'utf8'));
  const manifestErrors = validateScreenManifest(manifest, {
    baselineExists: (baseline) => existsSync(join(ROOT, 'tests/.pest/snapshots/Browser/CoreScreens/PixelBaselineTest', baseline)),
    // O manifesto declara a tela pelo NAMESPACE; o arquivo pode estar em qualquer uma das raízes.
    sourceExists: (source) => ['.tsx', '/Index.tsx', '.jsx', '/Index.jsx', '.ts', '/Index.ts', '.js', '/Index.js', '.vue', '/Index.vue']
      .some((suffix) => raizesDePages(ROOT).some((raiz) => existsSync(join(raiz, `${source}${suffix}`)))),
    componentExists: (component) => ['.tsx', '.jsx', '.ts', '.js', '.vue']
      .some((suffix) => raizesDePages(ROOT).some((raiz) => existsSync(join(raiz, `${component}${suffix}`)))),
  });
  if (manifestErrors.length) throw new Error(`contrato ${SCREEN_MANIFEST} invalido: ${manifestErrors.join('; ')}`);
  const needsConsumerGraph = changes.some((change) => /^resources\/js\//i.test(normalizePath(change.path)));
  const consumerScreens = needsConsumerGraph ? createRepositoryConsumerResolver() : () => [];
  const impact = classifyChanges(changes, { readContent, consumerScreens, manifestoSoAdiciona });
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
  // Cobrança de contrato só faz sentido em `targeted` — é lá que `screens` VIRA a lista
  // de comparação (PixelBaselineTest.php:97 filtra por VISREG_SCREENS e ele mesmo lança
  // "Telas sem contrato visreg" pra tela pedida sem contrato: a mordida vive lá, não aqui).
  // Em `global` o teste IGNORA VISREG_SCREENS e roda o manifesto inteiro (núcleo-6) — a
  // doutrina no topo deste arquivo: "mudanças compartilhadas/fundacionais exigem o núcleo
  // global". Exigir contrato do raio informativo travava o shell: `AppShellV2.tsx` resolve
  // ~190 consumidores × 6 com contrato → fail-closed inalcançável, com o pixel nunca
  // rodando (PIXEL_OUTCOME=skipped). Nenhuma mudança de shell atravessou o canário desde
  // que ele endureceu (2026-07-16, #4342/#4349); o último toque no AppShellV2 é de 06-17.
  if (result.scope === 'targeted' && result.uncovered_screens.length) {
    throw new Error(`telas afetadas sem contrato em ${SCREEN_MANIFEST}: ${result.uncovered_screens.join(', ')}`);
  }
  return result;
}

const norm = (path) => { try { return realpathSync(path).replace(/\\/g, '/').toLowerCase(); } catch { return normalizePath(path).toLowerCase(); } };
const isEntry = !!process.argv[1] && norm(fileURLToPath(import.meta.url)) === norm(process.argv[1]);

function selfTest() {
  const chr10 = String.fromCharCode(10);
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
    // Tela no MÓDULO dono (2026-08-12): o relativo `../../Layouts` resolveria DENTRO do módulo,
    // então quem mora fora do núcleo alcança o compartilhado pelo alias `@/` — que é o que o
    // código real faz (1.785 imports contra 83 relativos, medido no dia da migração).
    ['Modules/Cms/Resources/js/Pages/Site/Home.tsx', "import SiteLayout from '@/Layouts/SiteLayout';"],
    ['resources/js/Lib/money.ts', 'export const money = 1;'],
    ['resources/js/Pages/Sells/Create.tsx', "export { money } from '@/Lib/money';"],
  ]));
  assert.deepEqual(consumers('resources/js/Components/Site/Hero.tsx'), ['Site/Home']);
  assert.deepEqual(consumers('resources/js/Lib/money.ts'), ['Sells/Create']);
  const shared = classifyFiles(['resources/js/Components/Site/Hero.tsx'], { consumerScreens: consumers });
  assert.deepEqual([shared.scope, shared.screens], ['global', ['Site/Home']]);

  // Auxiliar de Page cuja tela tem NOME PRÓPRIO (não Index): o palpite de path recorta em
  // `Mod/Sub` (tela fantasma, sem contrato) — a proveniência por import tem que vencer.
  // Repro do fail-closed de 2026-07-16 (run 29522631495: uncovered=['OficinaAuto/ServiceOrders']
  // com a tela real já coberta no mesmo diff). Par de fixtures: com consumidor e sem.
  const auxConsumers = createConsumerResolver(new Map([
    ['resources/js/Pages/Mod/Sub/_components/tone.ts', 'export const tone = 1;'],
    ['resources/js/Pages/Mod/Sub/Board.tsx', "import { tone } from './_components/tone';"],
    ['resources/js/Pages/Mod/Orfao/_components/solto.ts', 'export const solto = 1;'],
  ]));
  assert.deepEqual(auxConsumers('resources/js/Pages/Mod/Sub/_components/tone.ts'), ['Mod/Sub/Board']);
  const auxHit = classifyFiles(['resources/js/Pages/Mod/Sub/_components/tone.ts'], { consumerScreens: auxConsumers });
  assert.deepEqual(
    [auxHit.scope, auxHit.screens],
    ['global', ['Mod/Sub/Board']],
    'auxiliar com consumidor resolvido não pode emitir a tela fantasma Mod/Sub',
  );
  // Controle-negativo: SEM consumidor resolvido o palpite continua (conservador — não
  // silencia o fail-closed; só deixa de inventar tela quando há proveniência melhor).
  const orfao = classifyFiles(['resources/js/Pages/Mod/Orfao/_components/solto.ts'], { consumerScreens: auxConsumers });
  assert.deepEqual([orfao.scope, orfao.screens], ['global', ['Mod/Orfao']], 'sem consumidor, mantém o palpite de path');
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
  assert.ok(validateExecution({ visualRequired: 'true', mode: 'false', pixelOutcome: 'success', expected: 1, executed: 1, compared: 1 }).length > 0);
  assert.ok(validateExecution({ visualRequired: 'true', mode: 'true', pixelOutcome: 'skipped', expected: 1, executed: 1, compared: 1 }).length > 0);
  assert.ok(validateExecution({ visualRequired: 'true', mode: 'true', pixelOutcome: 'success', expected: 2, executed: 2, compared: 1 }).length > 0);
  assert.deepEqual(validateExecution({ visualRequired: 'true', mode: 'true', pixelOutcome: 'success', expected: 2, executed: 2, compared: 2 }), []);

  // ── uncovered × scope (2026-07-16) — o par que prova que a catraca MORDE e LIBERA certo.
  // Em `targeted` a lista de telas É a comparação → sem contrato, reprova (aqui e no PHP).
  // Em `global` o pixel ignora `screens` e roda o núcleo-6 → cobrar contrato do raio
  // informativo é inalcançável (o shell resolve ~190 consumidores) e matava o job ANTES
  // do pixel (PIXEL_OUTCOME=skipped) — verde-vazio às avessas: vermelho-vazio.
  assert.ok(
    validateExecution({ visualRequired: 'true', mode: 'true', pixelOutcome: 'success', scope: 'targeted', uncoveredScreens: ['Cliente'], expected: 1, executed: 1, compared: 1 }).length > 0,
    'targeted com tela sem contrato TEM que reprovar (a mordida não pode sumir)',
  );
  assert.deepEqual(
    validateExecution({ visualRequired: 'true', mode: 'true', pixelOutcome: 'success', scope: 'global', uncoveredScreens: ['Admin', 'Home'], expected: 6, executed: 6, compared: 6 }),
    [],
    'global roda o núcleo-6 e ignora screens — raio informativo não pode travar o shell',
  );
  assert.ok(
    validateExecution({ visualRequired: 'true', mode: 'true', pixelOutcome: 'success', uncoveredScreens: ['Cliente'], expected: 1, executed: 1, compared: 1 }).length > 0,
    'scope ausente = conservador: mantém o fail-closed de sempre',
  );
  // O resto do canário continua mordendo em global (não virou passe-livre):
  assert.ok(
    validateExecution({ visualRequired: 'true', mode: 'true', pixelOutcome: 'skipped', scope: 'global', uncoveredScreens: ['Admin'], expected: 6, executed: 6, compared: 6 }).length > 0,
    'global com pixel skipped segue reprovando (verde vazio)',
  );
  assert.ok(
    validateExecution({ visualRequired: 'true', mode: 'true', pixelOutcome: 'success', scope: 'global', uncoveredScreens: ['Admin'], expected: 6, executed: 6, compared: 3 }).length > 0,
    'global com pixel incompleto segue reprovando',
  );
  // ADICAO x MODIFICACAO de contrato visual (2026-08-20) — o beco sem saida do #6027.
  // BITE dos dois lados: adicionar tela nova NAO precisa do nucleo-6; rebaselinar precisa.
  {
    const SNAP = 'tests/.pest/snapshots/Browser/CoreScreens/PixelBaselineTest/it_X.snap';
    const nasce = classifyChanges([{ status: 'A', path: SNAP }]);
    assert.deepEqual([nasce.scope, nasce.impacted[0].reason], ['targeted', 'contrato-visual-adicao']);

    const rebaseline = classifyChanges([{ status: 'M', path: SNAP }]);
    assert.deepEqual([rebaseline.scope, rebaseline.impacted[0].reason], ['global', 'contrato-visual']);

    const removida = classifyChanges([{ status: 'D', path: SNAP }]);
    assert.equal(removida.scope, 'global');

    // O manifesto e 1 arquivo: adicionar tela nele e sempre 'M'. Quem decide e o CONTEUDO,
    // via resolver — e sem resolver o default e conservador (global).
    const semResolver = classifyChanges([{ status: 'M', path: SCREEN_MANIFEST }]);
    assert.equal(semResolver.scope, 'global');

    const aditivo = classifyChanges([{ status: 'M', path: SCREEN_MANIFEST }], { manifestoSoAdiciona: () => true });
    assert.deepEqual([aditivo.scope, aditivo.impacted[0].reason], ['targeted', 'contrato-visual-adicao']);

    const alterou = classifyChanges([{ status: 'M', path: SCREEN_MANIFEST }], { manifestoSoAdiciona: () => false });
    assert.equal(alterou.scope, 'global');

    // CONTROLE: adicionar .snap NAO apaga o global que vem de arquivo compartilhado no
    // mesmo PR — a protecao continua vindo da regra CERTA.
    const junto = classifyChanges([
      { status: 'A', path: SNAP },
      { status: 'M', path: 'resources/css/cockpit.css' },
    ]);
    assert.equal(junto.scope, 'global');
  }


  // ── explainFailure: a narrativa segue o MESMO scope do canário ───────────────────────
  // REPRODUÇÃO do caso medido (PR #5976 · job 96505067528): scope=global + 8 uncovered, e o
  // único step não-success foi o pixel-diff, por ZONA CINZA. O comentário dizia "sem contrato
  // visual" e mandava baselinar 8 telas — 2 impossíveis (Financeiro/Dashboard é deprecada;
  // Cliente/Show só renderiza em rollback de canary). Trocar o predicado pelo antigo
  // (`uncoveredScreens.length > 0`, sem o scope) deixa este bloco VERMELHO.
  const caso5976 = explainFailure({
    scope: 'global',
    uncoveredScreens: ['Cliente/Show', 'Financeiro/Dashboard', 'Nfse', 'Ponto/Dashboard'],
    steps: [{ nome: 'Classificar impacto visual do diff', outcome: 'success' }, { nome: 'Pixel-diff', outcome: 'failure' }],
    grayZone: [{ screen: 'Produto/Unificado', ratio: 0.008261 }, { screen: 'Jana/Chat', ratio: 0.007743 }],
  });
  const texto5976 = caso5976.corpo.join(chr10);
  assert.notEqual(caso5976.modo, 'sem-contrato', 'global NUNCA escolhe a narrativa de contrato (o bug de 2026-08-20)');
  assert.equal(caso5976.modo, 'zona-cinza');
  assert.equal(caso5976.passo, 'Pixel-diff', 'o comentário tem que NOMEAR o step que reprovou');
  assert.ok(texto5976.includes('0.8261%'), 'zona cinza tem que trazer o ratio medido');
  assert.ok(!texto5976.includes('Financeiro/Dashboard'), 'não pode cobrar baseline de tela que não reprovou');
  // Controle POSITIVO: em targeted a cobrança de contrato TEM que continuar aparecendo.
  assert.equal(explainFailure({ scope: 'targeted', uncoveredScreens: ['Cliente'], steps: [{ nome: 'Canário', outcome: 'failure' }] }).modo, 'sem-contrato');
  assert.equal(explainFailure({ uncoveredScreens: ['Cliente'], steps: [] }).modo, 'sem-contrato', 'scope ausente = conservador, igual ao canário');
  // Sem failure instrumentado → diz que não sabe; nem cala, nem inventa.
  const indet = explainFailure({ scope: 'global', steps: [{ nome: 'Pixel-diff', outcome: 'skipped' }] });
  assert.equal(indet.modo, 'indeterminado');
  assert.ok(indet.corpo.join(chr10).includes('NÃO determinada'));
  // Step nomeado sem zona cinza → lista as causas possíveis, não escolhe uma.
  const claro = explainFailure({ scope: 'global', steps: [{ nome: 'Fluxos Compras', outcome: 'failure' }] });
  assert.equal(claro.modo, 'step-nomeado');
  assert.ok(claro.corpo.join(chr10).includes('Fluxos Compras'));

  console.log('ui-impact selftest: sensibilidade, especificidade e fail-closed passaram');
}

if (isEntry) {
  const argv = process.argv.slice(2);
  if (argv.includes('--selftest')) {
    try { selfTest(); } catch (error) { console.error(error); process.exitCode = 1; }
  } else if (argv.includes('--explain-failure')) {
    const { corpo, modo, passo } = explainFailure({
      scope: argValue(argv, 'scope'),
      uncoveredScreens: jsonArray(argValue(argv, 'uncovered-screens', '[]')),
      grayZone: jsonArray(argValue(argv, 'gray-zone', '[]')),
      steps: jsonArray(argValue(argv, 'steps', '[]')),
      runUrl: argValue(argv, 'run-url', ''),
    });
    console.error(`explain-failure: modo=${modo} passo=${passo || '(não identificado)'}`);
    console.log([...corpo, '', '_Ver [ADR 0108](../../memory/decisions/0108-regressao-visual-pest-browser-tier-2.md)._'].join('\n'));
  } else if (argv.includes('--assert-execution')) {
    const errors = validateExecution({
      visualRequired: argValue(argv, 'visual-required'),
      mode: argValue(argv, 'mode'),
      pixelOutcome: argValue(argv, 'pixel-outcome'),
      uncoveredScreens: jsonArray(argValue(argv, 'uncovered-screens', '[]')),
      scope: argValue(argv, 'scope'),
      expected: argValue(argv, 'expected'),
      executed: argValue(argv, 'executed'),
      compared: argValue(argv, 'compared'),
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
