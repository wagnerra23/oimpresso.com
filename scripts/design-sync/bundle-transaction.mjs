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
import { verificarMapa } from '../governance/design-code-map-check.mjs';

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
  if (!existsSync(path)) return { schema: 'oimpresso-design-applications/2', applications: [] };
  const ledger = JSON.parse(readFileSync(path, 'utf8'));
  if (!['oimpresso-design-applications/1', 'oimpresso-design-applications/2'].includes(ledger?.schema) || !Array.isArray(ledger.applications)) {
    throw new Error('ledger applications.json inválido');
  }
  if (ledger.schema === 'oimpresso-design-applications/2') return ledger;
  return {
    schema: 'oimpresso-design-applications/2',
    applications: ledger.applications.map((item) => ({
      source: item.source,
      target: item.target,
      sourceSha256: item.sourceSha256,
      targetSha256: item.targetSha256,
      application: {
        evidence: item.evidence,
        recordedAt: item.recordedAt,
        legacyUnverified: true,
      },
      legacyTests: item.tests || [],
      tests: [],
      smokes: [],
    })),
  };
}

function repoEvidence(root, path, expectedSha256 = null) {
  if (!path || isAbsolute(path)) return null;
  let abs;
  try { abs = resolveInside(root, String(path).replace(/\\/g, '/')); } catch { return null; }
  if (!existsSync(abs)) return null;
  const digest = sha256(readFileSync(abs));
  if (expectedSha256 && digest !== expectedSha256) return null;
  return { path: String(path).replace(/\\/g, '/'), sha256: digest };
}

function mapRelates(map, source, target) {
  if (map?.mapping?.source === source && map?.mapping?.target === target) return true;
  const sourceBase = basename(source);
  const parts = Array.isArray(map?.partes) ? map.partes : [];
  const hasSource = parts.some((part) => basename(String(part?.prototipo?.arquivo || '')) === sourceBase);
  const hasTarget = parts.some((part) => String(part?.vivo?.arquivo || '').replace(/\\/g, '/') === target);
  return hasSource && hasTarget;
}

function currentEvidenceRecord({ root, source, target, manifest, ledger, comparison }) {
  const sourceFile = manifest.files.find((file) => file.path === source);
  const record = (ledger?.applications || []).find((item) => item.source === source && item.target === target);
  if (!sourceFile || !record || record.sourceSha256 !== sourceFile.sha256) return null;
  let targetAbs;
  try { targetAbs = resolveInside(root, target); } catch { return null; }
  if (!existsSync(targetAbs)) return null;
  const targetSha256 = sha256(readFileSync(targetAbs));
  if (record.targetSha256 !== targetSha256) return null;

  let compared = comparison !== 'SEMANTICO';
  let comparisonEvidence = null;
  if (record.comparison && /^[a-f0-9]{64}$/.test(String(record.comparison.mapSha256 || ''))) {
    const mapFile = repoEvidence(root, record.comparison.map, record.comparison.mapSha256);
    if (mapFile) {
      try {
        const map = JSON.parse(readFileSync(resolveInside(root, mapFile.path), 'utf8'));
        const verification = verificarMapa(map, { root });
        if (!verification.drift.length && mapRelates(map, source, target)) {
          compared = true;
          comparisonEvidence = { ...record.comparison, mapSha256: mapFile.sha256 };
        }
      } catch { /* mapa inválido não prova comparação */ }
    }
  }

  let application = null;
  if (compared && record.application && !record.application.legacyUnverified
    && /^[a-f0-9]{64}$/.test(String(record.application.evidenceSha256 || ''))) {
    const proof = repoEvidence(root, record.application.evidence, record.application.evidenceSha256);
    if (proof) application = { ...record.application, evidenceSha256: proof.sha256 };
  }

  const tests = application ? (record.tests || []).filter((test) =>
    test.exitCode === 0 && Array.isArray(test.command) && test.command.length > 0
    && test.sourceSha256 === sourceFile.sha256 && test.targetSha256 === targetSha256
    && /^[a-f0-9]{64}$/.test(String(test.outputSha256 || ''))
  ) : [];
  const smokes = tests.length ? (record.smokes || []).filter((smoke) => {
    if (smoke.result !== 'passed' || String(smoke.tenant) !== '1' || smoke.targetSha256 !== targetSha256) return false;
    if (!/^[a-f0-9]{40,64}$/.test(String(smoke.deploySha || ''))) return false;
    return !!repoEvidence(root, smoke.screenshot, smoke.screenshotSha256);
  }) : [];

  return { record, targetSha256, compared, comparisonEvidence, application, tests, smokes };
}

export async function buildApplicationReport({ root, stagedCowork, manifest, previousReport = null, applicationLedger = null }) {
  const rows = await detectarTelas({ staging: stagedCowork, repoRoot: root });
  const changed = new Map();
  for (const path of manifest.changes.added) changed.set(path, 'added');
  for (const path of manifest.changes.modified) changed.set(path, 'modified');
  for (const path of manifest.changes.deleted) changed.set(path, 'deleted');
  const screens = rows.map((row) => {
    const app = applicationState(row.status);
    const evidence = currentEvidenceRecord({ root, source: row.arquivo, target: row.alvo, manifest, ledger: applicationLedger, comparison: row.status });
    const applied = row.status === 'IDENTICO' || !!evidence?.application;
    const tested = applied && (evidence?.tests.length || 0) > 0;
    const smoked = tested && (evidence?.smokes.length || 0) > 0;
    const lifecycleState = app.state === 'blocked' || app.state === 'to-create'
      ? app.state
      : smoked ? 'validated'
      : tested ? 'tested'
      : applied ? 'applied'
      : evidence?.compared ? 'compared'
      : 'anchored';
    return {
      source: row.arquivo,
      bundleChange: changed.get(row.arquivo) || 'unchanged',
      target: row.alvo,
      module: moduleFromTarget(row.alvo),
      mapping: row.via,
      comparison: row.status,
      lifecycleState,
      applicationState: applied ? 'applied' : app.state,
      compared: !!evidence?.compared,
      tested,
      smoked,
      applicationEvidence: evidence ? {
        comparison: evidence.comparisonEvidence,
        application: evidence.application,
        tests: evidence.tests,
        smokes: evidence.smokes,
        targetSha256: evidence.targetSha256,
      } : null,
      nextAction: lifecycleState === 'validated' ? 'aplicação, teste e smoke de produção válidos para os hashes atuais'
        : lifecycleState === 'tested' ? 'registrar smoke de produção com rota, deploy e screenshot'
        : lifecycleState === 'applied' ? 'executar teste pelo registrador para produzir recibo verificável'
        : lifecycleState === 'compared' ? 'aplicar no alvo e registrar evidência durável'
        : lifecycleState === 'anchored' ? 'gerar/registrar map.json antes de aplicar semanticamente'
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
      lifecycleState: 'review',
      applicationState: 'review',
      compared: false,
      tested: false,
      smoked: false,
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
  const byLifecycle = {};
  for (const screen of screens) byLifecycle[screen.lifecycleState] = (byLifecycle[screen.lifecycleState] || 0) + 1;
  return {
    schema: 'oimpresso-design-application-report/3',
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
      smoked: screens.filter((screen) => screen.smoked).length,
      lifecycle: byLifecycle,
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
  root = process.cwd(), source, target, evidence, paths = DEFAULT_PATHS,
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
  const proof = repoEvidence(absRoot, String(evidence || '').trim());
  if (!proof) throw new Error('--evidence deve apontar para arquivo durável existente dentro do repositório');
  const ledger = readApplicationLedger(absRoot, paths);
  const previous = ledger.applications.find((item) => item.source === sourceFile.path && item.target === normalizedTarget);
  const current = currentEvidenceRecord({
    root: absRoot, source: sourceFile.path, target: normalizedTarget,
    manifest, ledger, comparison: 'SEMANTICO',
  });
  if (!current?.compared) throw new Error('aplicação não pode preceder comparação válida para os hashes atuais');
  const record = {
    ...previous,
    source: sourceFile.path,
    target: normalizedTarget,
    sourceSha256: sourceFile.sha256,
    targetSha256: sha256(readFileSync(targetAbs)),
    application: { evidence: proof.path, evidenceSha256: proof.sha256, recordedAt: new Date().toISOString() },
    tests: previous?.tests || [],
    smokes: previous?.smokes || [],
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

export async function recordComparisonEvidence({
  root = process.cwd(), source, target, map, paths = DEFAULT_PATHS,
}) {
  const absRoot = resolve(root);
  const manifest = readState(absRoot, paths);
  if (!manifest) throw new Error('nenhum bundle ativo');
  const sourceFile = manifest.files.find((file) => file.path === normalizePayloadPath(source));
  if (!sourceFile) throw new Error(`fonte não pertence ao bundle ativo: ${source}`);
  const normalizedTarget = String(target).replace(/\\/g, '/');
  const targetAbs = resolveInside(absRoot, normalizedTarget);
  if (!existsSync(targetAbs)) throw new Error(`destino React ausente: ${normalizedTarget}`);
  const mapFile = repoEvidence(absRoot, String(map || '').trim());
  if (!mapFile) throw new Error('--map deve apontar para map.json durável existente dentro do repositório');
  let parsed;
  try { parsed = JSON.parse(readFileSync(resolveInside(absRoot, mapFile.path), 'utf8')); }
  catch { throw new Error(`--map não é JSON válido: ${mapFile.path}`); }
  if (!mapRelates(parsed, sourceFile.path, normalizedTarget)) throw new Error(`map não relaciona ${sourceFile.path} → ${normalizedTarget}`);
  const verification = verificarMapa(parsed, { root: absRoot });
  if (verification.drift.length) throw new Error(`map não prova comparação válida: ${verification.drift.join('; ')}`);

  const ledger = readApplicationLedger(absRoot, paths);
  const previous = ledger.applications.find((item) => item.source === sourceFile.path && item.target === normalizedTarget);
  const targetSha256 = sha256(readFileSync(targetAbs));
  const sameHashes = previous?.sourceSha256 === sourceFile.sha256
    && previous?.targetSha256 === targetSha256
    && previous?.comparison?.map === mapFile.path
    && previous?.comparison?.mapSha256 === mapFile.sha256;
  const record = {
    ...(sameHashes ? previous : {}),
    source: sourceFile.path,
    target: normalizedTarget,
    sourceSha256: sourceFile.sha256,
    targetSha256,
    comparison: { map: mapFile.path, mapSha256: mapFile.sha256, recordedAt: new Date().toISOString() },
    tests: sameHashes ? (previous?.tests || []) : [],
    smokes: sameHashes ? (previous?.smokes || []) : [],
  };
  ledger.applications = ledger.applications.filter((item) => !(item.source === record.source && item.target === record.target));
  ledger.applications.push(record);
  ledger.applications.sort((a, b) => a.source.localeCompare(b.source) || a.target.localeCompare(b.target));
  writeApplicationLedger(absRoot, paths, ledger);
  return { record, report: await refreshApplicationReport({ root: absRoot, paths }) };
}

export async function recordTestEvidence({
  root = process.cwd(), source, target, command, exitCode, output, runner, paths = DEFAULT_PATHS,
}) {
  if (exitCode !== 0) throw new Error('teste falhou; recibo verde não foi gravado');
  if (!Array.isArray(command) || !command.length || command.some((part) => !String(part).trim())) throw new Error('comando de teste inválido');
  if (!['local', 'ct100', 'ci'].includes(runner)) throw new Error('--runner deve ser local, ct100 ou ci');
  const { absRoot, manifest, sourceFile, normalizedTarget, targetSha256, ledger, previous } = currentWritableRecord({ root, source, target, paths });
  const current = currentEvidenceRecord({ root: absRoot, source: sourceFile.path, target: normalizedTarget, manifest, ledger, comparison: 'SEMANTICO' });
  if (!current?.application) throw new Error('aplicação atual não possui comparação + evidência durável válidas');
  const receipt = {
    command: command.map(String), exitCode, runner,
    outputSha256: sha256(Buffer.from(String(output || ''), 'utf8')),
    sourceSha256: sourceFile.sha256, targetSha256, recordedAt: new Date().toISOString(),
  };
  const record = { ...previous, tests: [...(previous.tests || []), receipt], smokes: previous.smokes || [] };
  replaceApplication(ledger, record);
  writeApplicationLedger(absRoot, paths, ledger);
  return { receipt, report: await refreshApplicationReport({ root: absRoot, paths }) };
}

export async function recordSmokeEvidence({
  root = process.cwd(), source, target, route, deploySha, screenshot, tenant, paths = DEFAULT_PATHS,
}) {
  if (!String(route || '').startsWith('/')) throw new Error('--route deve começar com /');
  if (!/^[a-f0-9]{40,64}$/.test(String(deploySha || ''))) throw new Error('--deploy-sha deve ser SHA git válido');
  if (String(tenant) !== '1') throw new Error('smoke manual de produção usa exclusivamente tenant 1; biz=4 é proibido');
  const shot = repoEvidence(resolve(root), String(screenshot || '').trim());
  if (!shot) throw new Error('--screenshot deve apontar para arquivo durável existente dentro do repositório');
  const { absRoot, manifest, sourceFile, normalizedTarget, targetSha256, ledger, previous } = currentWritableRecord({ root, source, target, paths });
  const current = currentEvidenceRecord({ root: absRoot, source: sourceFile.path, target: normalizedTarget, manifest, ledger, comparison: 'SEMANTICO' });
  if (!current?.tests.length) throw new Error('smoke não pode preceder teste verde válido');
  const receipt = {
    route: String(route), deploySha: String(deploySha), tenant: 1,
    screenshot: shot.path, screenshotSha256: shot.sha256,
    result: 'passed', targetSha256, recordedAt: new Date().toISOString(),
  };
  const record = { ...previous, tests: previous.tests || [], smokes: [...(previous.smokes || []), receipt] };
  replaceApplication(ledger, record);
  writeApplicationLedger(absRoot, paths, ledger);
  return { receipt, report: await refreshApplicationReport({ root: absRoot, paths }) };
}

function currentWritableRecord({ root, source, target, paths }) {
  const absRoot = resolve(root);
  const manifest = readState(absRoot, paths);
  if (!manifest) throw new Error('nenhum bundle ativo');
  const sourceFile = manifest.files.find((file) => file.path === normalizePayloadPath(source));
  if (!sourceFile) throw new Error(`fonte não pertence ao bundle ativo: ${source}`);
  const normalizedTarget = String(target).replace(/\\/g, '/');
  const targetAbs = resolveInside(absRoot, normalizedTarget);
  if (!existsSync(targetAbs)) throw new Error(`destino React ausente: ${normalizedTarget}`);
  const targetSha256 = sha256(readFileSync(targetAbs));
  const ledger = readApplicationLedger(absRoot, paths);
  const previous = ledger.applications.find((item) => item.source === sourceFile.path && item.target === normalizedTarget);
  if (!previous || previous.sourceSha256 !== sourceFile.sha256 || previous.targetSha256 !== targetSha256) throw new Error('registro de aplicação ausente ou stale para os hashes atuais');
  return { absRoot, manifest, sourceFile, normalizedTarget, targetSha256, ledger, previous };
}

function replaceApplication(ledger, record) {
  ledger.applications = ledger.applications.filter((item) => !(item.source === record.source && item.target === record.target));
  ledger.applications.push(record);
  ledger.applications.sort((a, b) => a.source.localeCompare(b.source) || a.target.localeCompare(b.target));
}

function writeApplicationLedger(root, paths, ledger) {
  const ledgerPath = resolveInside(root, join(paths.state, 'applications.json'));
  mkdirSync(dirname(ledgerPath), { recursive: true });
  const temp = `${ledgerPath}.tmp-${process.pid}-${Date.now()}`;
  writeFileSync(temp, JSON.stringify(ledger, null, 2) + '\n');
  renameSync(temp, ledgerPath);
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
