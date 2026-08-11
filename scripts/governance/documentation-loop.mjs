#!/usr/bin/env node
/**
 * documentation-loop.mjs — recibo determinístico do ciclo documental.
 *
 * Este arquivo NÃO cria uma régua nova. Ele compõe três donos existentes:
 *   - memory-health.mjs                 (integridade/fatos/links)
 *   - briefing-code-staleness.mjs       (porta × código)
 *   - doc-freshness-score.mjs           (radar/priorização)
 *
 * Contrato do ciclo:
 *   snapshot ANTES -> correção no dono -> snapshot DEPOIS -> mesmo ID sumiu.
 * Tocar arquivo, aumentar parcialmente uma nota ou bumpar data não fecha recibo.
 *
 * Uso:
 *   node scripts/governance/documentation-loop.mjs --snapshot [--json]
 *   node scripts/governance/documentation-loop.mjs --compare-ref origin/main [--json]
 *   node scripts/governance/documentation-loop.mjs --compare-ref origin/main --expect <id>[,<id>]
 *   node scripts/governance/documentation-loop.mjs --impact-ref origin/main [--head-ref HEAD] [--json]
 *   acrescente --enforce-activation para bloquear módulo novo incompleto
 *   acrescente --require-clean no recibo final, depois do commit
 *   node scripts/governance/documentation-loop.mjs --selftest
 *
 * O comparativo cria worktree temporária sob os.tmpdir(), mede o ref e remove a
 * worktree no finally. Nunca edita o ref nem persiste baseline auto-declarado.
 *
 * Refs: ADR 0270 (batimento/consolidação) · ADR 0314 (higiene advisory) ·
 * scripts/governance/ZELADOR.md · memory/proibicoes.md §5 (presença ≠ correção).
 */

import { createHash } from 'node:crypto';
import {
  existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, realpathSync, rmSync, writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { scan as scanBriefing, isBriefingCoverageGap } from './briefing-code-staleness.mjs';

const ROOT = process.cwd();
const JSON_OUT = process.argv.includes('--json');
const REQUIRE_CLEAN = process.argv.includes('--require-clean');
const ENFORCE_ACTIVATION = process.argv.includes('--enforce-activation');
const SOURCE_PRIORITY = { 'memory-health': 0, 'briefing-code-staleness': 1, 'doc-freshness-score': 2 };
const SHARED_RUNTIME_PATHS = /^(app|config|database|routes)\//;
const DOCUMENT_NAME = /^(BRIEFING|README|ARCHITECTURE|SPEC|SDD|RUNBOOK|SUPERFICIE|CHANGELOG|GLOSSARY)/i;
const GRAPH_EDGE_TYPES = new Set(['dependsOn', 'delegatesTo', 'migratesTo']);
const MODULE_ACTIVATION_FILES = [
  'module.json',
  'composer.json',
  // ADR 0375: SCOPE.md saiu de Modules/<X>/ — exigido abaixo, no bloco memory/requisitos.
  'Providers/RouteServiceProvider.php',
  'Http/Controllers/DataController.php',
  'Http/Controllers/InstallController.php',
  'Routes/web.php',
];

function stable(value) {
  if (Array.isArray(value)) return value.map(stable);
  if (!value || typeof value !== 'object') return value;
  return Object.fromEntries(Object.keys(value).sort().map((key) => [key, stable(value[key])]));
}

function digest(value) {
  return createHash('sha256').update(JSON.stringify(stable(value))).digest('hex').slice(0, 12);
}

function targetFromSample(sample) {
  if (sample == null) return { aggregate: true };
  if (typeof sample !== 'object') return { value: sample };
  const keys = ['file', 'link', 'doc', 'mod', 'module', 'screen', 'slug', 'id', 'indice', 'path', 'rel'];
  const picked = {};
  for (const key of keys) if (sample[key] != null) picked[key] = sample[key];
  return Object.keys(picked).length ? picked : stable(sample);
}

export function issueId(source, kind, target) {
  return `${source}:${kind}:${digest(target)}`;
}

function normalizeMemoryHealth(data) {
  const out = [];
  for (const [severity, rows] of [['fail', data.fails || []], ['warn', data.warns || []]]) {
    for (const row of rows) {
      const samples = Array.isArray(row.sample) && row.sample.length ? row.sample : [null];
      for (const sample of samples) {
        const target = targetFromSample(sample);
        out.push({
          id: issueId('memory-health', row.kind || row.check || 'unknown', target),
          source: 'memory-health', kind: row.kind || row.check || 'unknown', severity,
          target, metric: Number.isFinite(row.count) ? row.count : null,
          details: row.msg || '',
        });
      }
    }
  }
  return out;
}

function normalizeBriefing() {
  const rows = scanBriefing();
  const out = [];
  for (const row of rows.filter((item) => item.stale)) {
    const target = { mod: row.mod };
    out.push({
      id: issueId('briefing-code-staleness', 'porta-stale', target),
      source: 'briefing-code-staleness', kind: 'porta-stale', severity: 'warn', target,
      metric: row.gapDays ?? null,
      details: `${row.gapDays ?? '?'}d / ${row.commitsAhead ?? '?'} commits atrás do código`,
    });
  }
  for (const row of rows.filter((item) => isBriefingCoverageGap(item))) {
    const target = { mod: row.mod };
    out.push({
      id: issueId('briefing-code-staleness', 'porta-ausente', target),
      source: 'briefing-code-staleness', kind: 'porta-ausente', severity: 'warn', target,
      metric: 1, details: `módulo backend ${row.mod} sem BRIEFING.md`,
    });
  }
  return out;
}

function runJson(root, script, args) {
  const absolute = join(root, script);
  if (!existsSync(absolute)) throw new Error(`detector ausente: ${script} em ${root}`);
  const result = spawnSync(process.execPath, [absolute, ...args], {
    cwd: root, encoding: 'utf8', maxBuffer: 128 * 1024 * 1024,
    env: { ...process.env, GITHUB_STEP_SUMMARY: '' },
  });
  const raw = (result.stdout || '').trim();
  if (!raw) throw new Error(`${script} não produziu JSON (exit=${result.status}; stderr=${(result.stderr || '').trim().slice(0, 300)})`);
  try { return JSON.parse(raw); }
  catch { throw new Error(`${script} produziu JSON inválido: ${raw.slice(0, 300)}`); }
}

function normalizeFreshness(data) {
  return (data.docs || [])
    .filter((row) => Number(row.score) < 50)
    .map((row) => {
      const target = { doc: row.doc };
      return {
        id: issueId('doc-freshness-score', 'doc-podre', target),
        source: 'doc-freshness-score', kind: 'doc-podre', severity: 'warn', target,
        metric: Number(row.score),
        details: `score ${row.score}/100 · ${row.refsQuebradas || 0} refs quebradas · ${row.churnCommits || 0} commits de churn`,
      };
    });
}

function gitArgs(root, args) {
  // Worktrees temporárias e sandboxes Windows podem executar sob outro SID.
  // A exceção é local ao processo e ao diretório exato; não altera config global.
  const safeRoot = resolve(root).replaceAll('\\', '/');
  return ['-c', `safe.directory=${safeRoot}`, ...args];
}

function gitSha(root) {
  const result = spawnSync('git', gitArgs(root, ['rev-parse', 'HEAD']), { cwd: root, encoding: 'utf8' });
  return result.status === 0 ? result.stdout.trim() : null;
}

function runGit(root, args) {
  const result = spawnSync('git', gitArgs(root, args), { cwd: root, encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
  if (result.status !== 0) throw new Error(`git ${args.join(' ')} falhou: ${(result.stderr || result.stdout || '').trim()}`);
  return result.stdout;
}

// `normalizeFiles` (split por linha) foi REMOVIDO em 2026-08-07 junto com o fix de
// quotePath: ele só existia pros callers sem `-z`, e deixá-lo aqui convidaria a
// reintroduzir o bug no próximo caller. Caminho de git nesta base entra por NUL.
function normalizeNullFiles(raw) {
  return raw.split('\0').map((file) => file.replaceAll('\\', '/')).filter(Boolean);
}

function trackedFiles(root = ROOT) {
  return normalizeNullFiles(runGit(root, ['ls-files', '-z']));
}

// `-z` nas TRÊS chamadas, igual ao `trackedFiles` acima — e é obrigatório, não estilo.
// Sem `-z`, o git aplica `core.quotePath` e devolve caminho não-ASCII ENVELOPADO EM
// ASPAS com escape octal: `"…de_pixel__n\303\272cleo_6_.snap"`. Aí o `.replaceAll('\\','/')`
// do normalizeFiles (que existe pra barra do Windows) transforma `\303\272` em `/303/272`,
// e o caminho resultante não casa regra de dono nenhuma → `class: "unclassified"` →
// `--enforce-activation` recusa a ativação. O inventário nunca viu isso porque
// `ls-files -z` não passa pelo quotePath: os dois lados do mesmo script discordavam.
// Descoberto em 2026-08-07 (#5413): a baseline `it_Produto_Unificado_…núcleo_6_.snap` é o
// primeiro arquivo com acento ADICIONADO desde que o gate virou enforcing (#5327, 05/08) —
// os 12 snaps irmãos, com o mesmo `núcleo` no nome, entraram um dia ANTES e por isso
// nunca tropeçaram.
function worktreeChangedFiles(root = ROOT) {
  return [...new Set([
    ...normalizeNullFiles(runGit(root, ['diff', '--name-only', '-z', 'HEAD'])),
    ...normalizeNullFiles(runGit(root, ['ls-files', '--others', '--exclude-standard', '-z'])),
  ])].sort();
}

function changedFiles(base, head = 'HEAD', root = ROOT) {
  const committed = normalizeNullFiles(runGit(root, ['diff', '--name-only', '-z', `${base}...${head}`]));
  const worktree = head === 'HEAD' ? worktreeChangedFiles(root) : [];
  return [...new Set([...committed, ...worktree])].sort();
}

function moduleFromPath(file) {
  const normalized = file.replaceAll('\\', '/');
  const match = normalized.match(/^(?:Modules|resources\/js\/Pages|memory\/requisitos|tests\/Feature)\/([^/]+)\//);
  const mod = match?.[1] || null;
  return mod && !mod.startsWith('_') ? mod : null;
}

/**
 * Classifica todo path versionado sem tentar forçar tudo a ser "módulo".
 * O universo é o Git; módulo, compartilhado, governança e documentação são
 * contextos diferentes, todos explícitos. Fallback também é explícito para
 * nunca transformar arquivo desconhecido em silêncio.
 */
export function classifyRepositoryFile(file) {
  const normalized = String(file).replaceAll('\\', '/');
  const mod = moduleFromPath(normalized);
  if (mod) return { file: normalized, class: 'module', context: mod };
  if (SHARED_RUNTIME_PATHS.test(normalized)) return { file: normalized, class: 'shared-runtime', context: '_Geral' };
  if (/^resources\/(?:js\/(?:Components|Layouts|hooks|lib)|views\/(?:components|layouts))\//.test(normalized)) {
    return { file: normalized, class: 'shared-ui', context: '_Geral' };
  }
  if (/^(?:memory|docs?)\//.test(normalized)) return { file: normalized, class: 'documentation', context: 'Governance' };
  if (/^(?:tests|e2e)\//.test(normalized)) return { file: normalized, class: 'shared-test', context: '_Geral' };
  if (/^(?:\.github|\.claude|\.githooks|governance|scripts\/governance|bin|tools)\//.test(normalized)) {
    return { file: normalized, class: 'governance', context: 'Governance' };
  }
  if (/^(?:\.devcontainer|docker-host|docker|infra)\//.test(normalized)) {
    return { file: normalized, class: 'infrastructure', context: '_Geral' };
  }
  if (/^dist\//.test(normalized)) return { file: normalized, class: 'distribution-artifact', context: '_Geral' };
  if (/^(?:scripts|bootstrap|storage|public|resources|lang|lib-custom|prototipo-ui)\//.test(normalized)) {
    return { file: normalized, class: 'shared-support', context: '_Geral' };
  }
  if (!normalized.includes('/') || /^(?:composer|package|phpunit|vite|tsconfig|eslint|modules_statuses)\b/.test(normalized)) {
    return { file: normalized, class: 'repository-root', context: 'Governance' };
  }
  return { file: normalized, class: 'unclassified', context: null };
}

export function repositoryInventory(files) {
  const classified = files.map(classifyRepositoryFile);
  const byClass = {};
  const byContext = {};
  for (const item of classified) {
    byClass[item.class] = (byClass[item.class] || 0) + 1;
    const context = item.context || 'SEM_DONO';
    byContext[context] = (byContext[context] || 0) + 1;
  }
  return {
    total: classified.length,
    classified: classified.filter((item) => item.class !== 'unclassified').length,
    unclassified: classified.filter((item) => item.class === 'unclassified').map((item) => item.file),
    by_class: Object.fromEntries(Object.entries(byClass).sort()),
    by_context: Object.fromEntries(Object.entries(byContext).sort()),
  };
}

function graphRelatedModules(direct, catalog) {
  const adjacency = new Map();
  const add = (from, to) => {
    if (!adjacency.has(from)) adjacency.set(from, new Set());
    adjacency.get(from).add(to);
  };
  for (const edge of catalog.edges || []) {
    if (!GRAPH_EDGE_TYPES.has(edge.type)) continue;
    const from = String(edge.from).replace(/^module:/, '');
    const to = String(edge.to).replace(/^module:/, '');
    if (!from || !to || from === to) continue;
    // Impacto precisa olhar fornecedor e consumidor. A direção original segue
    // preservada no catalog.json; aqui calculamos o fechamento de revisão.
    add(from, to);
    add(to, from);
  }
  const distance = new Map(direct.map((mod) => [mod, 0]));
  const queue = [...direct];
  while (queue.length) {
    const current = queue.shift();
    for (const next of adjacency.get(current) || []) {
      if (distance.has(next)) continue;
      distance.set(next, distance.get(current) + 1);
      queue.push(next);
    }
  }
  return [...distance.entries()]
    .filter(([mod]) => !direct.includes(mod))
    .sort((a, b) => a[1] - b[1] || a[0].localeCompare(b[0]))
    .map(([module, depth]) => ({ module, depth }));
}

function existsAtRef(root, ref, file) {
  return spawnSync('git', gitArgs(root, ['cat-file', '-e', `${ref}:${file}`]), { cwd: root }).status === 0;
}

function detectNewModules(base, files, root = ROOT) {
  return [...new Set(files
    .filter((file) => /^Modules\/[^/]+\/module\.json$/.test(file))
    .filter((file) => existsSync(join(root, file)))
    .filter((file) => !existsAtRef(root, base, file))
    .map((file) => file.split('/')[1]))].sort();
}

function recursiveFiles(root, rel) {
  const abs = join(root, rel);
  if (!existsSync(abs)) return [];
  const out = [];
  for (const entry of readdirSync(abs, { withFileTypes: true })) {
    const child = (rel === '.' ? entry.name : `${rel}/${entry.name}`).replaceAll('\\', '/');
    if (entry.isDirectory()) out.push(...recursiveFiles(root, child));
    else out.push(child);
  }
  return out.sort();
}

export function inspectModuleActivation(mod, {
  root = ROOT,
  catalog = {},
  projectFiles = null,
} = {}) {
  const required = [
    ...MODULE_ACTIVATION_FILES.map((rel) => `Modules/${mod}/${rel}`),
    `memory/requisitos/${mod}/SCOPE.md`,
    `memory/requisitos/${mod}/BRIEFING.md`,
    `memory/requisitos/${mod}/SPEC.md`,
    `memory/requisitos/${mod}/SUPERFICIE.md`,
  ];
  const missing = required.filter((file) => !existsSync(join(root, file)));
  const violations = missing.map((file) => ({ kind: 'arquivo-obrigatorio-ausente', file }));
  let manifest = null;
  const manifestPath = join(root, 'Modules', mod, 'module.json');
  if (existsSync(manifestPath)) {
    try { manifest = JSON.parse(readFileSync(manifestPath, 'utf8')); }
    catch { violations.push({ kind: 'module-json-invalido', file: `Modules/${mod}/module.json` }); }
  }
  for (const provider of manifest?.providers || []) {
    const prefix = `Modules\\${mod}\\`;
    const rel = String(provider).startsWith(prefix)
      ? `Modules/${mod}/${String(provider).slice(prefix.length).replaceAll('\\', '/')}.php`
      : null;
    if (!rel || !existsSync(join(root, rel))) {
      violations.push({ kind: 'provider-ausente', provider, file: rel });
    }
  }
  const statusesPath = join(root, 'modules_statuses.json');
  try {
    const statuses = JSON.parse(readFileSync(statusesPath, 'utf8'));
    if (!Object.prototype.hasOwnProperty.call(statuses, mod)) {
      violations.push({ kind: 'modules-statuses-sem-modulo', file: 'modules_statuses.json' });
    }
  } catch {
    violations.push({ kind: 'modules-statuses-invalido', file: 'modules_statuses.json' });
  }
  const files = projectFiles || recursiveFiles(root, '.').filter((file) => !file.startsWith('./.git/'));
  const testPattern = new RegExp(`^(?:Modules/${mod}/Tests/|tests/(?:Feature|Unit)/${mod}/|tests/(?:Feature|Unit)/.*${mod}.*Test\\.php$)`, 'i');
  const tests = files.filter((file) => testPattern.test(file));
  if (!tests.length) violations.push({ kind: 'teste-de-ativacao-ausente', module: mod });
  const catalogNode = (catalog.nodes || []).find((node) =>
    node.type === 'module' && String(node.module).toLowerCase() === mod.toLowerCase()
      && node.catalog_status !== 'referenced-only');
  if (!catalogNode) violations.push({ kind: 'modulo-fora-do-catalogo-gerado', module: mod });
  return {
    module: mod,
    lifecycle: violations.length ? 'incompleto' : 'ativavel',
    required_files: required,
    missing_files: missing,
    tests,
    catalogued: Boolean(catalogNode),
    violations,
  };
}

export function inspectDocumentationFleet({
  root = ROOT,
  catalog = {},
  projectFiles = null,
} = {}) {
  // O diff inclui paths DELETADOS. A frota é o estado da árvore resultante,
  // então só um module.json que ainda existe pode registrar módulo.
  const files = (projectFiles || trackedFiles(root))
    .filter((file) => existsSync(join(root, file)));
  const modules = [...new Set(files
    .filter((file) => /^Modules\/[^/]+\/module\.json$/.test(file))
    .map((file) => file.split('/')[1]))].sort();
  const rows = modules.map((mod) => {
    const required = [
      `memory/requisitos/${mod}/SCOPE.md`,   // ADR 0375: saiu de Modules/
      `memory/requisitos/${mod}/BRIEFING.md`,
      `memory/requisitos/${mod}/SPEC.md`,
      `memory/requisitos/${mod}/SUPERFICIE.md`,
    ];
    const missing = required.filter((file) => !existsSync(join(root, file)));
    const testPattern = new RegExp(`^(?:Modules/${mod}/Tests/|tests/(?:Feature|Unit)/${mod}/|tests/(?:Feature|Unit)/.*${mod}.*Test\\.php$)`, 'i');
    const tests = files.filter((file) => testPattern.test(file));
    const catalogued = (catalog.nodes || []).some((node) =>
      node.type === 'module' && String(node.module).toLowerCase() === mod.toLowerCase()
        && node.catalog_status !== 'referenced-only');
    const violations = [
      ...missing.map((file) => ({ kind: 'contrato-documental-ausente', file })),
      ...(!tests.length ? [{ kind: 'prova-automatizada-ausente', module: mod }] : []),
      ...(!catalogued ? [{ kind: 'modulo-fora-do-catalogo-gerado', module: mod }] : []),
    ];
    return {
      module: mod,
      status: violations.length ? 'incompleto' : 'completo',
      files_in_module_root: files.filter((file) => file.startsWith(`Modules/${mod}/`)).length,
      required_files: required,
      missing_files: missing,
      tests,
      catalogued,
      violations,
    };
  });
  return {
    total_modules: rows.length,
    complete_modules: rows.filter((row) => row.status === 'completo').length,
    incomplete_modules: rows.filter((row) => row.status !== 'completo'),
    modules: rows,
  };
}

function documentationInventory(root, mod) {
  return [`memory/requisitos/${mod}`, `Modules/${mod}`].flatMap((rel) => {
    const abs = join(root, rel);
    if (!existsSync(abs)) return [];
    return readdirSync(abs, { withFileTypes: true })
      .filter((entry) => entry.isFile() && entry.name.endsWith('.md') && DOCUMENT_NAME.test(entry.name))
      .map((entry) => `${rel}/${entry.name}`.replaceAll('\\', '/'));
  }).sort();
}

export function buildImpactReport(files, catalog = {}, root = ROOT, options = {}) {
  const catalogModules = (catalog.nodes || []).filter((node) => node.type === 'module').map((node) => node.module);
  const canonicalModule = new Map(catalogModules.map((mod) => [mod.toLowerCase(), mod]));
  const direct = [...new Set(files.map(moduleFromPath).filter(Boolean)
    .map((mod) => canonicalModule.get(mod.toLowerCase()) || mod))].sort();
  const graphModules = new Set(catalogModules);
  const relatedWithDepth = graphRelatedModules(direct, catalog);
  const shared = files.filter((file) =>
    SHARED_RUNTIME_PATHS.test(file) || /^(composer\.(json|lock)|package(-lock)?\.json)$/.test(file));
  const unknown = direct.filter((mod) => !graphModules.has(mod));
  const relatedModules = relatedWithDepth.map((item) => item.module);
  const fanout = relatedModules.length > 8;
  const modules = [...new Set([...direct, ...relatedModules])].sort();
  const businessChange = direct.length > 0 || shared.length > 0;
  const projectFiles = options.projectFiles || (() => {
    try { return trackedFiles(root); } catch { return files; }
  })();
  const newModules = options.newModules || [];
  const activation = newModules.map((mod) => inspectModuleActivation(mod, {
    root, catalog, projectFiles: [...new Set([...projectFiles, ...files])],
  }));
  const documentationFleet = inspectDocumentationFleet({
    root, catalog, projectFiles: [...new Set([...projectFiles, ...files])],
  });
  return {
    schema_version: 2,
    changed_files: files,
    changed_file_classification: files.map(classifyRepositoryFile),
    repository_inventory: repositoryInventory(projectFiles),
    direct_modules: direct,
    related_modules: relatedModules,
    related_module_depth: relatedWithDepth,
    shared_runtime_files: shared,
    unknown_modules: unknown,
    new_modules: newModules,
    module_activation: activation,
    module_documentation_fleet: documentationFleet,
    decision: !businessChange ? 'sem-impacto-modular'
      : shared.length || unknown.length || fanout ? 'revisao-ampla'
        : 'revisao-modular',
    reasons: [
      ...(shared.length ? ['superficie-runtime-compartilhada'] : []),
      ...(unknown.length ? ['modulo-fora-do-catalogo'] : []),
      ...(fanout ? ['fanout-maior-que-8'] : []),
    ],
    documents: Object.fromEntries(modules.map((mod) => [mod, documentationInventory(root, mod)])),
  };
}

export function normalizeMeaningfulMarkdown(text) {
  const lines = String(text || '').replace(/\r\n/g, '\n').split('\n');
  let frontmatter = lines[0] === '---';
  return lines.filter((line, index) => {
    if (index === 0 && frontmatter) return false;
    if (frontmatter && line === '---') { frontmatter = false; return false; }
    if (frontmatter && /^\s*(date|data|updated_at|reviewed_at|distilled_at|last_updated|atualizado)\s*:/i.test(line)) return false;
    if (/^\s*>?\s*(?:\*\*)?(?:última atualização|atualizado em)(?:\*\*)?\s*:\s*\d{4}-\d{2}-\d{2}\s*$/i.test(line)) return false;
    return true;
  }).join('\n').replace(/\s+/g, ' ').trim();
}

export function applyReceiptEvidence(comparison, before, changed, documents = {}) {
  const rejected = [];
  const introduced = comparison.introduced || [];
  for (const id of comparison.expected || []) {
    if (!(comparison.resolved || []).some((issue) => issue.id === id)) continue;
    const issue = (before.issues || []).find((item) => item.id === id);
    const targetPath = issue?.target?.file || issue?.target?.doc;
    if (targetPath && !existsSync(join(ROOT, targetPath))) {
      rejected.push({ id, reason: 'alvo-removido', file: targetPath });
      continue;
    }
    if (issue?.source !== 'briefing-code-staleness' || issue.kind !== 'porta-stale') continue;
    const mod = issue.target?.mod;
    const doc = documents[mod];
    if (introduced.some((item) => item.source === 'briefing-code-staleness'
      && item.kind === 'porta-ausente' && item.target?.mod === mod)) {
      rejected.push({ id, reason: 'porta-removida', mod });
      continue;
    }
    if (!doc?.currentPath || doc.current == null) {
      rejected.push({ id, reason: 'porta-removida', mod });
      continue;
    }
    if (!changed.includes(doc.currentPath)) continue;
    const beforeBody = normalizeMeaningfulMarkdown(doc.before);
    const afterBody = normalizeMeaningfulMarkdown(doc.current);
    if (beforeBody === afterBody) rejected.push({ id, reason: 'somente-carimbo-de-data', mod, file: doc.currentPath });
    else if (afterBody.length < 40) rejected.push({ id, reason: 'conteudo-esvaziado', mod, file: doc.currentPath });
  }
  return {
    ...comparison,
    ok: comparison.ok && rejected.length === 0,
    receipt_evidence: { executed: true, changed_files: changed, rejected },
  };
}

function briefingDocuments(ref, before, expected, root = ROOT) {
  const result = {};
  for (const id of expected) {
    const issue = (before.issues || []).find((item) => item.id === id);
    const mod = issue?.target?.mod;
    if (issue?.source !== 'briefing-code-staleness' || !mod) continue;
    const paths = [`memory/requisitos/${mod}/BRIEFING.md`, `Modules/${mod}/BRIEFING.md`];
    const currentPath = paths.find((path) => existsSync(join(root, path))) || null;
    let beforePath = null;
    for (const path of paths) {
      const found = spawnSync('git', gitArgs(root, ['cat-file', '-e', `${ref}:${path}`]), { cwd: root });
      if (found.status === 0) { beforePath = path; break; }
    }
    result[mod] = {
      currentPath,
      current: currentPath ? readFileSync(join(root, currentPath), 'utf8') : null,
      before: beforePath ? runGit(root, ['show', `${ref}:${beforePath}`]) : null,
    };
  }
  return result;
}

export function sortIssues(issues) {
  return [...issues].sort((a, b) => {
    if (a.severity !== b.severity) return a.severity === 'fail' ? -1 : 1;
    const source = (SOURCE_PRIORITY[a.source] ?? 9) - (SOURCE_PRIORITY[b.source] ?? 9);
    return source || a.id.localeCompare(b.id);
  });
}

export function buildSnapshot({ sha = null, memoryHealth = {}, briefingIssues = [], freshness = {} } = {}) {
  const rawIssues = [
    ...normalizeMemoryHealth(memoryHealth),
    ...briefingIssues,
    ...normalizeFreshness(freshness),
  ];
  // O mesmo link pode aparecer repetido no sample do detector (ex.: índice que o
  // cita várias vezes). Recibo é por achado estável, não por ocorrência textual.
  const issues = sortIssues([...new Map(rawIssues.map((issue) => [issue.id, issue])).values()]);
  return {
    schema_version: 1,
    git_sha: sha,
    sources: {
      'memory-health': issues.filter((x) => x.source === 'memory-health').length,
      'briefing-code-staleness': issues.filter((x) => x.source === 'briefing-code-staleness').length,
      'doc-freshness-score': issues.filter((x) => x.source === 'doc-freshness-score').length,
    },
    issues,
  };
}

export function snapshot(root = ROOT) {
  const memoryHealth = runJson(root, 'scripts/governance/memory-health.mjs', ['--json', '--warn-only']);
  const freshness = runJson(root, 'scripts/governance/doc-freshness-score.mjs', ['--json']);
  // scanBriefing usa process.cwd() como raiz. No snapshot do checkout corrente ele
  // evita subprocesso; snapshots de ref chamam este CLI com cwd na worktree-ref.
  const briefingIssues = root === process.cwd() ? normalizeBriefing() : [];
  return buildSnapshot({ sha: gitSha(root), memoryHealth, briefingIssues, freshness });
}

export function compareSnapshots(before, after, expected = []) {
  const beforeMap = new Map((before.issues || []).map((issue) => [issue.id, issue]));
  const afterMap = new Map((after.issues || []).map((issue) => [issue.id, issue]));
  const resolved = [...beforeMap.keys()].filter((id) => !afterMap.has(id)).map((id) => beforeMap.get(id));
  const introduced = [...afterMap.keys()].filter((id) => !beforeMap.has(id)).map((id) => afterMap.get(id));
  const changed = [...beforeMap.keys()].filter((id) => afterMap.has(id))
    .map((id) => ({ id, before: beforeMap.get(id).metric, after: afterMap.get(id).metric }))
    .filter((row) => row.before !== row.after);
  const resolvedIds = new Set(resolved.map((issue) => issue.id));
  const missingExpected = expected.filter((id) => !resolvedIds.has(id));
  return {
    ok: missingExpected.length === 0,
    before_sha: before.git_sha || null,
    after_sha: after.git_sha || null,
    expected,
    missing_expected: missingExpected,
    resolved,
    introduced,
    changed,
  };
}

function parseExpected() {
  const idx = process.argv.indexOf('--expect');
  if (idx === -1 || !process.argv[idx + 1]) return [];
  return process.argv[idx + 1].split(',').map((x) => x.trim()).filter(Boolean);
}

function printSnapshot(data) {
  if (JSON_OUT) return console.log(JSON.stringify(data, null, 2));
  console.log(`\n  DOCUMENTATION LOOP — ${data.issues.length} achado(s) · sha ${data.git_sha || '—'}\n`);
  for (const issue of data.issues.slice(0, 20)) {
    console.log(`  ${issue.severity === 'fail' ? '🔴' : '🟡'} ${issue.id}`);
    console.log(`     ${issue.details}`);
  }
  if (data.issues.length > 20) console.log(`  … +${data.issues.length - 20}`);
  console.log('\n  Recibo fecha somente quando o mesmo ID desaparece no snapshot depois.\n');
}

function printComparison(data) {
  if (JSON_OUT) return console.log(JSON.stringify(data, null, 2));
  console.log(`\n  RECIBO DOCUMENTAL — ${data.before_sha || '—'} → ${data.after_sha || '—'}`);
  console.log(`  resolvidos ${data.resolved.length} · novos ${data.introduced.length} · métricas alteradas ${data.changed.length}`);
  for (const issue of data.resolved) console.log(`  ✅ ${issue.id}`);
  for (const id of data.missing_expected) console.log(`  ❌ esperado não fechou: ${id}`);
  for (const rejection of data.receipt_evidence?.rejected || []) {
    console.log(`  ❌ evidência recusada: ${rejection.id} (${rejection.reason})`);
  }
  if (!data.expected.length) {
    console.log('\n  ✓ comparativo concluído; nenhum ID foi declarado como recibo esperado.\n');
  } else {
    console.log(data.ok ? '\n  ✓ recibo comprovado pelo mesmo detector.\n' : '\n  ✗ recibo incompleto.\n');
  }
}

function runDerivationCheck(script, args, root = ROOT) {
  const result = spawnSync(process.execPath, [join(root, script), ...args], {
    cwd: root, encoding: 'utf8', maxBuffer: 32 * 1024 * 1024,
  });
  return {
    command: `node ${script} ${args.join(' ')}`.trim(),
    ok: result.status === 0,
    exit_code: result.status,
    output: `${result.stdout || ''}${result.stderr || ''}`.trim().slice(0, 4000),
  };
}

function activationDerivationChecks(modules, root = ROOT) {
  if (!modules.length) return [];
  return [
    ...modules.map((mod) => runDerivationCheck('scripts/governance/module-surface.mjs', [mod, '--check'], root)),
    runDerivationCheck('scripts/governance/catalog-graph.mjs', ['--check'], root),
    runDerivationCheck('scripts/governance/system-map.mjs', ['--check'], root),
  ];
}

function selftest() {
  let failures = 0;
  const check = (name, condition) => { console.log(`  ${condition ? '[OK]' : '[FAIL]'} ${name}`); if (!condition) failures++; };
  const target = { file: 'memory/GUIA.md', link: 'morto.md' };
  const id = issueId('memory-health', 'link-quebrado', target);
  const issue = { id, source: 'memory-health', kind: 'link-quebrado', severity: 'warn', target, metric: 1, details: 'fixture' };
  const before = { git_sha: 'a', issues: [issue] };
  const unchanged = { git_sha: 'b', issues: [{ ...issue, metric: 0 }] };
  const fixed = { git_sha: 'c', issues: [] };
  const bite = compareSnapshots(before, unchanged, [id]);
  const release = compareSnapshots(before, fixed, [id]);
  check('ID é determinístico apesar da ordem das chaves', id === issueId('memory-health', 'link-quebrado', { link: 'morto.md', file: 'memory/GUIA.md' }));
  check('BITE: mudar métrica sem sumir não fecha recibo', !bite.ok && bite.missing_expected.includes(id));
  check('RELEASE: o mesmo ID ausente depois fecha recibo', release.ok && release.resolved[0]?.id === id);
  const newcomer = { ...issue, id: issueId('memory-health', 'link-quebrado', { file: 'novo.md' }) };
  const withNew = compareSnapshots(before, { git_sha: 'd', issues: [newcomer] }, [id]);
  check('Novo drift é reportado sem apagar o recibo resolvido', withNew.ok && withNew.introduced.length === 1);
  const staleId = issueId('briefing-code-staleness', 'porta-stale', { mod: 'Financeiro' });
  const stale = { id: staleId, source: 'briefing-code-staleness', kind: 'porta-stale', target: { mod: 'Financeiro' } };
  const comparison = compareSnapshots({ git_sha: 'a', issues: [stale] }, { git_sha: 'b', issues: [] }, [staleId]);
  const dateOnly = applyReceiptEvidence(comparison, { issues: [stale] }, ['memory/requisitos/Financeiro/BRIEFING.md'], {
    Financeiro: {
      currentPath: 'memory/requisitos/Financeiro/BRIEFING.md',
      before: '---\ndate: 2026-07-01\n---\n# Financeiro\nConteúdo real.',
      current: '---\ndate: 2026-07-29\n---\n# Financeiro\nConteúdo real.',
    },
  });
  check('BITE: mudar só carimbo não fecha BRIEFING stale', !dateOnly.ok && dateOnly.receipt_evidence.rejected[0]?.reason === 'somente-carimbo-de-data');
  const deleted = { ...issue, id: issueId('memory-health', 'link-quebrado', { file: 'memory/arquivo-removido.md' }),
    target: { file: 'memory/arquivo-removido.md' } };
  const deletedReceipt = compareSnapshots({ issues: [deleted] }, { issues: [] }, [deleted.id]);
  check('BITE: apagar o alvo não fecha o achado',
    !applyReceiptEvidence(deletedReceipt, { issues: [deleted] }, []).ok);
  const impact = buildImpactReport(['Modules/Financeiro/Services/FluxoService.php'], {
    nodes: [
      { type: 'module', module: 'Financeiro' },
      { type: 'module', module: 'Sells' },
      { type: 'module', module: 'RecurringBilling' },
    ],
    edges: [
      { type: 'dependsOn', from: 'module:Financeiro', to: 'module:Sells' },
      { type: 'dependsOn', from: 'module:Sells', to: 'module:RecurringBilling' },
    ],
  });
  check('PILOTO Financeiro: diff encontra módulo, fechamento transitivo e donos documentais',
    impact.direct_modules[0] === 'Financeiro'
      && impact.related_modules.includes('Sells')
      && impact.related_modules.includes('RecurringBilling')
      && impact.related_module_depth.find((x) => x.module === 'RecurringBilling')?.depth === 2
      && impact.documents.Financeiro.some((path) => /SDD-/.test(path))
      && impact.documents.Financeiro.some((path) => /BRIEFING\.md$/.test(path)));
  const realCatalog = JSON.parse(readFileSync(join(ROOT, 'memory/governance/catalog.json'), 'utf8'));
  const realFinanceiro = buildImpactReport(['Modules/Financeiro/Services/FluxoService.php'], realCatalog);
  check('CATÁLOGO REAL: Financeiro alcança Sells sem aresta inventada no teste',
    realFinanceiro.related_modules.includes('Sells'));
  const realFleet = inspectDocumentationFleet({
    root: ROOT, catalog: realCatalog, projectFiles: trackedFiles(ROOT),
  });
  const realManifestCount = trackedFiles(ROOT)
    .filter((file) => /^Modules\/[^/]+\/module\.json$/.test(file)).length;
  check('FROTA REAL: todo module.json tem SCOPE, BRIEFING, SPEC, SUPERFICIE, teste e catálogo',
    realManifestCount > 0
      && realFleet.total_modules === realManifestCount
      && realFleet.complete_modules === realManifestCount
      && realFleet.incomplete_modules.length === 0);
  const deletedFleet = inspectDocumentationFleet({
    root: ROOT, catalog: {}, projectFiles: ['Modules/ModuloRemovido/module.json'],
  });
  check('BITE: module.json presente só no diff de deleção não ressuscita módulo na frota',
    deletedFleet.total_modules === 0);
  const inventoryFixture = repositoryInventory([
    'Modules/Financeiro/Services/FluxoService.php',
    'memory/requisitos/Financeiro/SPEC.md',
    '.github/workflows/module-surface.yml',
    'docs/index.html',
    'e2e/produto-show.spec.ts',
    '.devcontainer/devcontainer.json',
    'dist/js/app.js',
    'arquivo-sem-dono.xyz/sub.txt',
  ]);
  check('Inventário classifica módulo, docs, testes, infra e distribuição; desconhecido fica explícito',
    inventoryFixture.total === 8
      && inventoryFixture.classified === 7
      && inventoryFixture.unclassified[0] === 'arquivo-sem-dono.xyz/sub.txt');
  check('Controle: arquivo runtime compartilhado exige revisão ampla',
    buildImpactReport(['app/Models/User.php'], { nodes: [], edges: [] }).decision === 'revisao-ampla');
  const fixture = mkdtempSync(join(resolve(tmpdir()), 'oimpresso-documentation-loop-selftest-'));
  try {
    runGit(fixture, ['init']);
    writeFileSync(join(fixture, 'README.md'), 'base\n');
    runGit(fixture, ['add', 'README.md']);
    runGit(fixture, ['-c', 'user.name=Documentation Loop', '-c', 'user.email=docs@example.invalid',
      'commit', '-m', 'base']);
    writeFileSync(join(fixture, 'README.md'), 'alterado\n');
    mkdirSync(join(fixture, 'Modules', 'Financeiro', 'Services'), { recursive: true });
    writeFileSync(join(fixture, 'Modules', 'Financeiro', 'Services', 'Novo.php'), '<?php\n');
    const dirty = changedFiles('HEAD', 'HEAD', fixture);
    check('BITE: impacto inclui arquivo rastreado e novo antes do commit',
      dirty.includes('README.md') && dirty.includes('Modules/Financeiro/Services/Novo.php'));
    const immutable = gitSha(fixture);
    check('Controle: comparação entre SHAs imutáveis ignora o worktree',
      changedFiles(immutable, immutable, fixture).length === 0);

    // BITE do quotePath (#5413, 2026-08-07): sem `-z` o git devolve
    // `"…acentuado__núcleo_.snap"` com aspas + escape octal, e o caminho vira
    // `/303/272` no normalize → `unclassified` → `--enforce-activation` recusa.
    // Cobre os DOIS caminhos: arquivo novo (untracked, via worktreeChangedFiles) e
    // arquivo já commitado num diff base...head.
    const acentuado = 'tests/.pest/snapshots/it_baseline__núcleo_6_.snap';
    mkdirSync(join(fixture, 'tests', '.pest', 'snapshots'), { recursive: true });
    writeFileSync(join(fixture, acentuado), 'PNG\n');
    check('BITE: caminho com acento chega inteiro (untracked) — sem aspas nem escape octal',
      changedFiles('HEAD', 'HEAD', fixture).includes(acentuado));
    runGit(fixture, ['add', '--', acentuado]);
    runGit(fixture, ['-c', 'user.name=Documentation Loop', '-c', 'user.email=docs@example.invalid',
      'commit', '-m', 'acento']);
    const comAcento = gitSha(fixture);
    check('BITE: caminho com acento chega inteiro (commitado, base...head)',
      changedFiles(immutable, comAcento, fixture).includes(acentuado));
    check('Controle: caminho ASCII segue intacto e nenhum caminho volta com aspas/octal',
      changedFiles(immutable, comAcento, fixture).every((f) => !f.startsWith('"') && !f.includes('/303/')));

    const novo = 'NovoModulo';
    for (const dir of [
      `Modules/${novo}/Providers`,
      `Modules/${novo}/Http/Controllers`,
      `Modules/${novo}/Routes`,
      `Modules/${novo}/Tests/Feature`,
      `memory/requisitos/${novo}`,
    ]) mkdirSync(join(fixture, dir), { recursive: true });
    writeFileSync(join(fixture, `Modules/${novo}/module.json`), JSON.stringify({
      name: novo,
      providers: [`Modules\\${novo}\\Providers\\${novo}ServiceProvider`],
    }));
    writeFileSync(join(fixture, `Modules/${novo}/composer.json`), '{}\n');
    writeFileSync(join(fixture, `memory/requisitos/${novo}/SCOPE.md`), '---\nmodule: NovoModulo\n---\n');
    writeFileSync(join(fixture, `Modules/${novo}/Providers/${novo}ServiceProvider.php`), '<?php\n');
    writeFileSync(join(fixture, `Modules/${novo}/Providers/RouteServiceProvider.php`), '<?php\n');
    writeFileSync(join(fixture, `Modules/${novo}/Http/Controllers/DataController.php`), '<?php\n');
    writeFileSync(join(fixture, `Modules/${novo}/Http/Controllers/InstallController.php`), '<?php\n');
    writeFileSync(join(fixture, `Modules/${novo}/Routes/web.php`), '<?php\n');
    writeFileSync(join(fixture, `Modules/${novo}/Tests/Feature/ActivationTest.php`), '<?php\n');
    for (const doc of ['BRIEFING.md', 'SPEC.md', 'SUPERFICIE.md']) {
      writeFileSync(join(fixture, `memory/requisitos/${novo}/${doc}`), `# ${novo}\n`);
    }
    writeFileSync(join(fixture, 'modules_statuses.json'), JSON.stringify({ [novo]: false }));
    const activationFiles = recursiveFiles(fixture, '.');
    check('BITE/RELEASE: detector distingue module.json novo presente de path deletado no diff',
      detectNewModules('HEAD', [`Modules/${novo}/module.json`], fixture).includes(novo)
        && detectNewModules('HEAD', ['Modules/ModuloRemovido/module.json'], fixture).length === 0);
    const activation = inspectModuleActivation(novo, {
      root: fixture,
      projectFiles: activationFiles,
      catalog: { nodes: [{ type: 'module', module: novo, catalog_status: 'catalogued' }] },
    });
    check('RELEASE: módulo novo completo chega a ativável', activation.lifecycle === 'ativavel');
    rmSync(join(fixture, `memory/requisitos/${novo}/SPEC.md`));
    const brokenActivation = inspectModuleActivation(novo, {
      root: fixture,
      projectFiles: activationFiles,
      catalog: { nodes: [{ type: 'module', module: novo, catalog_status: 'catalogued' }] },
    });
    check('BITE: módulo novo sem SPEC é recusado',
      brokenActivation.violations.some((x) => x.kind === 'arquivo-obrigatorio-ausente' && /SPEC\.md$/.test(x.file)));
  } finally {
    const resolvedFixture = resolve(fixture);
    const safePrefix = `${resolve(tmpdir())}${sep}`;
    if (!resolvedFixture.startsWith(safePrefix)) throw new Error(`fixture fora de os.tmpdir(): ${resolvedFixture}`);
    rmSync(resolvedFixture, { recursive: true, force: true });
  }
  console.log(failures ? `\n  ${failures} FALHA(S)\n` : '\n  SELFTEST OK — morde, solta e preserva IDs estáveis.\n');
  return failures ? 1 : 0;
}

function withRefSnapshot(ref) {
  const tmpRoot = resolve(tmpdir());
  const worktree = mkdtempSync(join(tmpRoot, 'oimpresso-documentation-loop-'));
  const resolvedWorktree = resolve(worktree);
  if (!resolvedWorktree.startsWith(tmpRoot)) throw new Error(`worktree temporária fora de os.tmpdir(): ${resolvedWorktree}`);
  const add = spawnSync('git', gitArgs(ROOT, ['worktree', 'add', '--detach', resolvedWorktree, ref]), { cwd: ROOT, encoding: 'utf8' });
  if (add.status !== 0) throw new Error(`não foi possível abrir ref ${ref}: ${(add.stderr || add.stdout || '').trim()}`);
  try {
    const script = realpathSync(fileURLToPath(import.meta.url));
    const result = spawnSync(process.execPath, [script, '--snapshot', '--json'], {
      cwd: resolvedWorktree, encoding: 'utf8', maxBuffer: 128 * 1024 * 1024,
    });
    if (result.status !== 0 || !(result.stdout || '').trim()) {
      throw new Error(`snapshot do ref ${ref} falhou: ${(result.stderr || result.stdout || '').trim().slice(0, 500)}`);
    }
    return JSON.parse(result.stdout);
  } finally {
    spawnSync('git', gitArgs(ROOT, ['worktree', 'remove', '--force', resolvedWorktree]), { cwd: ROOT, encoding: 'utf8' });
  }
}

function main() {
  if (process.argv.includes('--selftest')) return selftest();
  const impactIdx = process.argv.indexOf('--impact-ref');
  if (impactIdx !== -1) {
    const base = process.argv[impactIdx + 1];
    if (!base) throw new Error('--impact-ref exige um ref git');
    const headIdx = process.argv.indexOf('--head-ref');
    const head = headIdx === -1 ? 'HEAD' : process.argv[headIdx + 1];
    if (!head) throw new Error('--head-ref exige um ref git');
    const files = changedFiles(base, head);
    const worktreeFiles = head === 'HEAD' ? worktreeChangedFiles(ROOT) : [];
    const catalogPath = join(ROOT, 'memory/governance/catalog.json');
    const catalog = existsSync(catalogPath) ? JSON.parse(readFileSync(catalogPath, 'utf8')) : {};
    const newModules = detectNewModules(base, files, ROOT);
    const report = {
      ...buildImpactReport(files, catalog, ROOT, {
        projectFiles: trackedFiles(ROOT),
        newModules,
      }),
      base_sha: runGit(ROOT, ['rev-parse', base]).trim(),
      head_sha: runGit(ROOT, ['rev-parse', head]).trim(),
      worktree_files: worktreeFiles,
      executed: true,
    };
    report.activation_derivation_checks = activationDerivationChecks(newModules, ROOT);
    report.activation_ok = report.module_activation.every((item) => item.lifecycle === 'ativavel')
      && report.activation_derivation_checks.every((item) => item.ok)
      && report.module_documentation_fleet.incomplete_modules.length === 0
      && report.changed_file_classification.every((item) => item.class !== 'unclassified');
    console.log(JSON_OUT ? JSON.stringify(report, null, 2)
      : `impacto documental: ${report.decision} · módulos diretos ${report.direct_modules.join(', ') || '—'} · relacionados ${report.related_modules.join(', ') || '—'}`);
    if (REQUIRE_CLEAN && worktreeFiles.length) {
      console.error(`documentation-loop: recibo final exige worktree limpo; ${worktreeFiles.length} arquivo(s) ainda não commitado(s)`);
      return 1;
    }
    if (ENFORCE_ACTIVATION && !report.activation_ok) {
      console.error('documentation-loop: ativação recusada; módulo novo, projeção derivada ou arquivo sem dono ficou incompleto');
      return 1;
    }
    return 0;
  }
  const compareIdx = process.argv.indexOf('--compare-ref');
  if (compareIdx !== -1) {
    const ref = process.argv[compareIdx + 1];
    if (!ref) throw new Error('--compare-ref exige um ref git');
    const before = withRefSnapshot(ref);
    const after = snapshot();
    const expected = parseExpected();
    const comparison = applyReceiptEvidence(
      compareSnapshots(before, after, expected),
      before,
      changedFiles(ref),
      briefingDocuments(ref, before, expected),
    );
    const worktreeFiles = worktreeChangedFiles(ROOT);
    comparison.receipt_evidence.worktree_files = worktreeFiles;
    if (REQUIRE_CLEAN && worktreeFiles.length) {
      comparison.ok = false;
      comparison.receipt_evidence.rejected.push({
        reason: 'worktree-nao-commitado',
        files: worktreeFiles,
      });
    }
    printComparison(comparison);
    return comparison.ok ? 0 : 1;
  }
  const data = snapshot();
  printSnapshot(data);
  return 0;
}

const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();

if (isMain) {
  try { process.exit(main()); }
  catch (error) { console.error(`documentation-loop: ${error.message}`); process.exit(1); }
}
