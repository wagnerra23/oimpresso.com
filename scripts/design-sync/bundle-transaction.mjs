// @ts-check
/**
 * Aplicação transacional de um bundle Design v2.
 *
 * A transação copia os quatro destinos para staging, aplica o delta, verifica o estado-alvo
 * inteiro e só então troca os diretórios. Falha durante a promoção executa rollback dos swaps
 * já feitos; o cache `_ds` é apenas um dos destinos derivados, nunca o estado do protocolo.
 */
import {
  cpSync, existsSync, mkdirSync, readFileSync, renameSync, rmSync, writeFileSync,
} from 'node:fs';
import { basename, dirname, isAbsolute, join, relative, resolve, sep } from 'node:path';
import { payloadDependencyGraph, normalizePayloadPath } from './payload-dependency-graph.mjs';
import { dsRuntimeRelPath } from '../governance/cowork-mirror-freshness.mjs';
import {
  changesDigest, createManifest, manifestDigest, roleForPath, sha256,
  validateBundleParts, validateManifest,
} from './bundle-contract.mjs';
import { buildManifest as detectarTelas } from '../../prototipo-ui/detectar-telas.mjs';

export const DEFAULT_PATHS = {
  cowork: 'prototipo-ui/cowork',
  docs: 'prototipo-ui/design-docs',
  runtime: 'scripts/design-sync/mirror-snapshot',
  state: 'scripts/design-sync/state',
};

const BINARY = /\.(?:woff2?|ttf|otf|eot|png|jpe?g|gif|webp|avif|ico|pdf|mp4|webm|zip)$/i;

function resolveInside(root, path) {
  const absRoot = resolve(root);
  const abs = resolve(root, path);
  if (abs !== absRoot && !abs.startsWith(absRoot + sep)) throw new Error(`path sai do root da transação: ${path}`);
  return abs;
}

function targetForLogical(path, roots) {
  const rel = normalizePayloadPath(path);
  if (roleForPath(rel) === 'preview-cache') return { key: 'runtime', rel: dsRuntimeRelPath(rel), root: roots.runtime };
  if (roleForPath(rel) === 'design-doc') return { key: 'docs', rel, root: roots.docs };
  return { key: 'cowork', rel, root: roots.cowork };
}

function assertTransient(path, target, kind) {
  const parent = dirname(target);
  const name = basename(path);
  const expected = `.${basename(target)}.${kind}-`;
  if (dirname(path) !== parent || !name.startsWith(expected)) throw new Error(`recusa remover path transitório fora do contrato: ${path}`);
}

function removeTransient(path, target, kind) {
  if (!existsSync(path)) return;
  assertTransient(path, target, kind);
  rmSync(path, { recursive: true, force: true });
}

function makeStages(root, paths, id) {
  const roots = Object.fromEntries(Object.entries(paths).map(([key, rel]) => [key, resolveInside(root, rel)]));
  const entries = Object.entries(roots).map(([key, target]) => {
    const parent = dirname(target);
    mkdirSync(parent, { recursive: true });
    const stage = join(parent, `.${basename(target)}.stage-${id}`);
    const backup = join(parent, `.${basename(target)}.backup-${id}`);
    removeTransient(stage, target, 'stage');
    removeTransient(backup, target, 'backup');
    if (existsSync(target)) cpSync(target, stage, { recursive: true, force: false, errorOnExist: true });
    else mkdirSync(stage, { recursive: true });
    return { key, target, stage, backup, originalExists: existsSync(target) };
  });
  return { roots, entries, staged: Object.fromEntries(entries.map((entry) => [entry.key, entry.stage])) };
}

function cleanStages(entries) {
  for (const entry of entries) {
    removeTransient(entry.stage, entry.target, 'stage');
    removeTransient(entry.backup, entry.target, 'backup');
  }
}

function promote(entries, failAfterSwap = 0) {
  const swapped = [];
  try {
    for (const entry of entries) {
      if (entry.originalExists) renameSync(entry.target, entry.backup);
      renameSync(entry.stage, entry.target);
      swapped.push(entry);
      if (failAfterSwap && swapped.length === failAfterSwap) throw new Error(`falha injetada após swap ${failAfterSwap}`);
    }
    for (const entry of swapped) removeTransient(entry.backup, entry.target, 'backup');
  } catch (error) {
    for (const entry of [...swapped].reverse()) {
      if (existsSync(entry.target)) {
        const failed = join(dirname(entry.target), `.${basename(entry.target)}.stage-rollback-${process.pid}-${Date.now()}`);
        renameSync(entry.target, failed);
        assertTransient(failed, entry.target, 'stage');
        rmSync(failed, { recursive: true, force: true });
      }
      if (entry.originalExists && existsSync(entry.backup)) renameSync(entry.backup, entry.target);
    }
    cleanStages(entries);
    throw error;
  }
}

function readState(root, paths) {
  const path = resolveInside(root, join(paths.state, 'active-bundle.json'));
  if (!existsSync(path)) return null;
  return validateManifest(JSON.parse(readFileSync(path, 'utf8')));
}

function moduleFromTarget(target) {
  const normalized = String(target || '').replace(/\\/g, '/');
  const moduleMatch = normalized.match(/^Modules\/([^/]+)\/Resources\/js\/Pages\/(.+)\.tsx$/i);
  if (moduleMatch) return moduleMatch[1];
  const coreMatch = normalized.match(/^resources\/js\/Pages\/([^/]+)\//i);
  return coreMatch ? coreMatch[1] : null;
}

function applicationState(status) {
  if (status === 'IDENTICO') return { state: 'applied', next: 'nenhuma ação; alvo byte-idêntico' };
  if (status === 'ALTERADO') return { state: 'pending', next: 'revisar diff e aplicar a mudança na Page React' };
  if (status === 'SEMANTICO') return { state: 'pending', next: 'gerar/consumir map.json e aplicar semanticamente na Page React' };
  if (status === 'A-CRIAR' || status === 'ALVO-PENDENTE') return { state: 'to-create', next: 'criar a Page pelo fluxo MWART antes de aplicar' };
  if (status === 'ORFAO' || status === 'AMBIGUO') return { state: 'blocked', next: 'declarar destino no charter/alias; aplicação fail-closed' };
  return { state: 'review', next: 'revisar classificação' };
}

function readApplicationLedger(root, paths) {
  const path = resolveInside(root, join(paths.state, 'applications.json'));
  if (!existsSync(path)) return { schema: 'oimpresso-design-applications/1', applications: [] };
  const ledger = JSON.parse(readFileSync(path, 'utf8'));
  if (ledger?.schema !== 'oimpresso-design-applications/1' || !Array.isArray(ledger.applications)) {
    throw new Error('ledger applications.json inválido');
  }
  return ledger;
}

function applicationEvidenceFor({ root, source, target, manifest, ledger }) {
  const sourceFile = manifest.files.find((file) => file.path === source);
  const evidence = (ledger?.applications || []).find((item) => item.source === source && item.target === target);
  if (!sourceFile || !evidence || evidence.sourceSha256 !== sourceFile.sha256) return null;
  let targetAbs;
  try { targetAbs = resolveInside(root, target); } catch { return null; }
  if (!existsSync(targetAbs)) return null;
  const targetSha256 = sha256(readFileSync(targetAbs));
  if (evidence.targetSha256 !== targetSha256) return null;
  return { ...evidence, targetSha256, tested: Array.isArray(evidence.tests) && evidence.tests.length > 0 };
}

export async function buildApplicationReport({ root, stagedCowork, manifest, previousReport = null, applicationLedger = null }) {
  const rows = await detectarTelas({ staging: stagedCowork, repoRoot: root });
  const changed = new Map();
  for (const path of manifest.changes.added) changed.set(path, 'added');
  for (const path of manifest.changes.modified) changed.set(path, 'modified');
  for (const path of manifest.changes.deleted) changed.set(path, 'deleted');
  const screens = rows.map((row) => {
    const app = applicationState(row.status);
    const evidence = applicationEvidenceFor({ root, source: row.arquivo, target: row.alvo, manifest, ledger: applicationLedger });
    return {
      source: row.arquivo,
      bundleChange: changed.get(row.arquivo) || 'unchanged',
      target: row.alvo,
      module: moduleFromTarget(row.alvo),
      mapping: row.via,
      comparison: row.status,
      applicationState: evidence ? 'applied' : app.state,
      tested: evidence?.tested || false,
      applicationEvidence: evidence ? {
        recordedAt: evidence.recordedAt,
        evidence: evidence.evidence,
        tests: evidence.tests || [],
        targetSha256: evidence.targetSha256,
      } : null,
      nextAction: evidence
        ? (evidence.tested ? 'aplicação e testes registrados para os hashes atuais' : 'aplicação registrada; falta evidência de teste')
        : app.next,
    };
  });
  const previousBySource = new Map((previousReport?.screens || []).map((screen) => [screen.source, screen]));
  for (const path of manifest.changes.deleted.filter((value) => /(?:-page\.jsx|\/Index\.tsx)$/i.test(value))) {
    const old = previousBySource.get(path);
    screens.push({
      source: path,
      bundleChange: 'deleted',
      target: old?.target || '—',
      module: old?.module || null,
      mapping: old?.mapping || 'fonte removida',
      comparison: 'SOURCE-REMOVED',
      applicationState: 'review',
      tested: false,
      applicationEvidence: null,
      nextAction: 'revisar o alvo React; remoção no Design nunca apaga produto automaticamente',
    });
  }
  screens.sort((a, b) => a.source.localeCompare(b.source) || a.target.localeCompare(b.target));
  const targetFiles = new Map(manifest.files.map((file) => [file.path, file]));
  const previousFiles = new Map((previousReport?.manifestFiles || []).map((file) => [file.path, file]));
  const transportChanges = [...changed].sort(([a], [b]) => a.localeCompare(b)).map(([path, change]) => {
    const file = targetFiles.get(path) || previousFiles.get(path) || {};
    return { path, change, bytes: file.bytes ?? null, sha256: file.sha256 ?? null, role: file.role || roleForPath(path) };
  });
  const byState = {};
  for (const screen of screens) byState[screen.applicationState] = (byState[screen.applicationState] || 0) + 1;
  return {
    schema: 'oimpresso-design-application-report/2',
    generatedAt: new Date().toISOString(),
    bundle: {
      id: manifest.bundleId,
      baseId: manifest.baseBundleId,
      mode: manifest.mode,
      source: manifest.source,
      transportComplete: true,
    },
    summary: {
      transportChanges: transportChanges.length,
      screens: screens.length,
      tested: screens.filter((screen) => screen.tested).length,
      ...byState,
    },
    transportChanges,
    screens,
    manifestFiles: manifest.files,
  };
}

function writeAndVerifyTarget({ root, staged, manifest, buffers, previous }) {
  const effectiveDeleted = new Set(manifest.changes.deleted);
  if (manifest.mode === 'snapshot' && previous) {
    const targetPaths = new Set(manifest.files.map((file) => file.path));
    for (const file of previous.files) if (!targetPaths.has(file.path)) effectiveDeleted.add(file.path);
  }
  for (const path of effectiveDeleted) {
    const target = targetForLogical(path, staged);
    const abs = resolveInside(target.root, target.rel);
    if (existsSync(abs)) rmSync(abs, { force: true });
  }
  for (const [path, buffer] of buffers) {
    const target = targetForLogical(path, staged);
    const abs = resolveInside(target.root, target.rel);
    mkdirSync(dirname(abs), { recursive: true });
    writeFileSync(abs, buffer);
  }

  const graphFiles = [];
  for (const file of manifest.files) {
    const target = targetForLogical(file.path, staged);
    const abs = resolveInside(target.root, target.rel);
    if (!existsSync(abs)) throw new Error(`estado-alvo ausente no staging: ${file.path}`);
    const buffer = readFileSync(abs);
    if (buffer.length !== file.bytes || sha256(buffer) !== file.sha256) throw new Error(`estado-alvo diverge no staging: ${file.path}`);
    graphFiles.push({ path: file.path, binary: BINARY.test(file.path), content: BINARY.test(file.path) ? null : buffer.toString('utf8') });
  }
  for (const path of effectiveDeleted) {
    const target = targetForLogical(path, staged);
    if (existsSync(resolveInside(target.root, target.rel))) throw new Error(`remoção não efetivada no staging: ${path}`);
  }
  const graph = payloadDependencyGraph(graphFiles, { entry: manifest.entry });
  if (!graph.entryPresent) throw new Error(`entry ausente no estado-alvo: ${manifest.entry}`);
  if (graph.missing.length) throw new Error(`grafo do estado-alvo incompleto: ${graph.missing.join(', ')}`);
  if (graph.unsafe.length) throw new Error(`referência insegura no estado-alvo: ${graph.unsafe.map((item) => `${item.from}→${item.ref}`).join(', ')}`);
  if (graph.duplicates.length) throw new Error(`paths duplicados no grafo: ${graph.duplicates.join(', ')}`);
  return graph;
}

export async function applyBundleTransaction({ root = process.cwd(), parts, dry = false, paths = DEFAULT_PATHS, failAfterSwap = 0 }) {
  const absRoot = resolve(root);
  const previous = readState(absRoot, paths);
  const { manifest, buffers } = validateBundleParts(parts, previous);
  const id = `${manifest.bundleId.slice(0, 12)}-${process.pid}-${Date.now()}`;
  const stages = makeStages(absRoot, paths, id);
  try {
    const graph = writeAndVerifyTarget({ root: absRoot, staged: stages.staged, manifest, buffers, previous });
    const currentReportPath = resolveInside(absRoot, join(paths.state, 'application-report.json'));
    const previousReport = existsSync(currentReportPath) ? JSON.parse(readFileSync(currentReportPath, 'utf8')) : null;
    const applicationLedger = readApplicationLedger(absRoot, paths);
    const report = await buildApplicationReport({ root: absRoot, stagedCowork: stages.staged.cowork, manifest, previousReport, applicationLedger });
    writeFileSync(join(stages.staged.state, 'active-bundle.json'), JSON.stringify(manifest, null, 2) + '\n');
    writeFileSync(join(stages.staged.state, 'application-report.json'), JSON.stringify(report, null, 2) + '\n');
    if (dry) cleanStages(stages.entries);
    else promote(stages.entries, failAfterSwap);
    return { manifest, report, graph, dry };
  } catch (error) {
    cleanStages(stages.entries);
    throw error;
  }
}

export async function refreshApplicationReport({ root = process.cwd(), paths = DEFAULT_PATHS }) {
  const absRoot = resolve(root);
  const manifest = readState(absRoot, paths);
  if (!manifest) throw new Error('nenhum bundle ativo; aplique um snapshot antes de atualizar o relatório');
  const reportPath = resolveInside(absRoot, join(paths.state, 'application-report.json'));
  const previousReport = existsSync(reportPath) ? JSON.parse(readFileSync(reportPath, 'utf8')) : null;
  const applicationLedger = readApplicationLedger(absRoot, paths);
  const report = await buildApplicationReport({
    root: absRoot,
    stagedCowork: resolveInside(absRoot, paths.cowork),
    manifest,
    previousReport,
    applicationLedger,
  });
  mkdirSync(dirname(reportPath), { recursive: true });
  const temp = `${reportPath}.tmp-${process.pid}-${Date.now()}`;
  writeFileSync(temp, JSON.stringify(report, null, 2) + '\n');
  renameSync(temp, reportPath);
  return report;
}

/** Registra evidência ligada aos hashes atuais; qualquer mudança futura a invalida no relatório. */
export async function recordApplicationEvidence({
  root = process.cwd(), source, target, evidence, tests = [], paths = DEFAULT_PATHS,
}) {
  const absRoot = resolve(root);
  const manifest = readState(absRoot, paths);
  if (!manifest) throw new Error('nenhum bundle ativo');
  const sourceFile = manifest.files.find((file) => file.path === normalizePayloadPath(source));
  if (!sourceFile) throw new Error(`fonte não pertence ao bundle ativo: ${source}`);
  const reportPath = resolveInside(absRoot, join(paths.state, 'application-report.json'));
  if (!existsSync(reportPath)) throw new Error('application-report.json ausente');
  const report = JSON.parse(readFileSync(reportPath, 'utf8'));
  const normalizedTarget = String(target).replace(/\\/g, '/');
  const mapped = (report.screens || []).some((screen) => screen.source === sourceFile.path && screen.target === normalizedTarget);
  if (!mapped) throw new Error(`destino não é o mapeamento vigente de ${sourceFile.path}: ${normalizedTarget}`);
  const targetAbs = resolveInside(absRoot, normalizedTarget);
  if (!existsSync(targetAbs)) throw new Error(`destino React ausente: ${normalizedTarget}`);
  if (!String(evidence || '').trim()) throw new Error('--evidence é obrigatório');
  const ledger = readApplicationLedger(absRoot, paths);
  const record = {
    source: sourceFile.path,
    target: normalizedTarget,
    sourceSha256: sourceFile.sha256,
    targetSha256: sha256(readFileSync(targetAbs)),
    evidence: String(evidence).trim(),
    tests: [...new Set(tests.map(String).map((value) => value.trim()).filter(Boolean))],
    recordedAt: new Date().toISOString(),
  };
  ledger.applications = ledger.applications.filter((item) => !(item.source === record.source && item.target === record.target));
  ledger.applications.push(record);
  ledger.applications.sort((a, b) => a.source.localeCompare(b.source) || a.target.localeCompare(b.target));
  const ledgerPath = resolveInside(absRoot, join(paths.state, 'applications.json'));
  mkdirSync(dirname(ledgerPath), { recursive: true });
  const temp = `${ledgerPath}.tmp-${process.pid}-${Date.now()}`;
  writeFileSync(temp, JSON.stringify(ledger, null, 2) + '\n');
  renameSync(temp, ledgerPath);
  const updatedReport = await refreshApplicationReport({ root: absRoot, paths });
  return { record, report: updatedReport };
}

/** Converte um lote completo legado, já validado, em snapshot transacional. */
export async function applyLegacySnapshotTransaction({ root = process.cwd(), prepared, source = 'legacy-payload', entry = 'oimpresso.com.html', dry = false, paths = DEFAULT_PATHS, failAfterSwap = 0 }) {
  const files = prepared.map((file) => ({ path: file.rel, bytes: file.conteudo.length, sha256: sha256(file.conteudo) }));
  const manifest = createManifest({ source, entry, files, missing: [], previous: null });
  const chunks = prepared.map((file) => ({
    path: file.rel, index: 1, count: 1, offset: 0, bytes: file.conteudo.length,
    sha256: sha256(file.conteudo), content: file.conteudo.toString('base64'),
  }));
  const core = {
    id: manifest.bundleId, baseId: null, mode: 'snapshot', source: manifest.source,
    generatedAt: manifest.generatedAt, entry: manifest.entry, parts: 1,
    files: manifest.totals.files, bytes: manifest.totals.bytes,
    manifestSha256: manifestDigest(manifest),
    changesSha256: changesDigest(manifest.changes),
  };
  return applyBundleTransaction({
    root,
    parts: [{ schema: 'oimpresso-design-bundle/2', bundle: core, part: 1, parts: 1, targetManifest: manifest, chunks }],
    dry,
    paths,
    failAfterSwap,
  });
}
