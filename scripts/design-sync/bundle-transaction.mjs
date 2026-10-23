// @ts-check
/**
 * Recibos executáveis por tela (ADR 0384 · emenda 0390) — o que sobrou do bundle-transaction.
 *
 * Até 2026-10-23 este arquivo também APLICAVA o bundle Design v2 vindo do Cowork: copiava os
 * destinos para staging, aplicava o delta, verificava e trocava os diretórios. A ADR 0438 D5
 * tirou a aplicação de bundle/payload (onda 2c-3): o protótipo é escrito direto no git.
 *
 * FICA o registro de recibos por tela — comparação, aplicação, teste e smoke — e o relatório
 * `state/application-report.json`, que o `design-diff-lote`, o `design-smoke-ci`, o
 * `detectar-telas` e o painel `status.mjs` consomem. A comparação protótipo × produção é
 * mantida pela ADR 0438 D6, e estes recibos são o estado dela por tela.
 *
 * RESÍDUO DECLARADO: o denominador de telas do relatório ainda é o ÚLTIMO bundle promovido
 * (`state/active-bundle.json`, congelado no handoff 55). Protótipo novo escrito no git não
 * entra no relatório até o denominador passar a ser derivado da árvore — trabalho próprio,
 * fora desta onda.
 */
import { existsSync, mkdirSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { basename, dirname, isAbsolute, join, resolve, sep } from 'node:path';
import { execFileSync } from 'node:child_process';
import { normalizePayloadPath } from './payload-dependency-graph.mjs';
import { roleForPath, sha256, validateManifest } from './bundle-contract.mjs';
import { buildManifest as detectarTelas } from '../../scripts/design/detectar-telas.mjs';
import { verificarMapa } from '../governance/design-code-map-check.mjs';

export const DEFAULT_PATHS = {
  cowork: 'prototipo-ui/cowork/Wagner',
  runtime: 'prototipo-ui/design-system',
  state: 'scripts/design-sync/state',
};

export function pathsForOwner(owner = 'Wagner') {
  if (!['Wagner', 'Felipe'].includes(owner)) throw new Error(`dono de bundle inválido: ${owner}`);
  return owner === 'Wagner' ? { ...DEFAULT_PATHS } : {
    ...DEFAULT_PATHS, cowork: 'prototipo-ui/cowork/Felipe', state: 'scripts/design-sync/state/Felipe',
  };
}

/**
 * Onde o smoke foi renderizado (ADR 0390, emenda ao D-6 da 0384). `producao` = oimpresso.com
 * com login humano; `staging-ct100` = clone anonimizado no CT 100; `ci` = app efêmero do
 * próprio GitHub Actions com o seed biz=1/2 do visual-regression. O tenant continua 1 em
 * todos — biz=4 segue recusado (ADR 0358). O enum vive aqui E em bundle.schema.json
 * (`$defs.smokeReceipt.host`): quem mudar um, muda o outro.
 */
export const SMOKE_HOSTS = ['producao', 'staging-ct100', 'ci'];

function resolveInside(root, path) {
  const absRoot = resolve(root);
  const abs = resolve(root, path);
  if (abs !== absRoot && !abs.startsWith(absRoot + sep)) throw new Error(`path sai do root da transação: ${path}`);
  return abs;
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
    // Recibo anterior à ADR 0390 não tem `host`: era produção por construção (único valor
    // que o D-6 original admitia). Valor presente e fora do enum não prova nada.
    if (smoke.host !== undefined && !SMOKE_HOSTS.includes(String(smoke.host))) return false;
    return !!repoEvidence(root, smoke.screenshot, smoke.screenshotSha256);
  }) : [];

  return { record, sourceSha256: sourceFile.sha256, targetSha256, compared, comparisonEvidence, application, tests, smokes };
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
    // `tested` = EXISTE recibo verde amarrado a estes hashes — não "esta tela foi testada".
    // Com `runner: 'ci'` o recibo certifica um job de LANE, e 3 telas podem compartilhar o
    // mesmo `--job` (medido 2026-09-18). É presença, não comportamento (LC-11); o que o recibo
    // prova e o que não prova está no docblock de `recordTestEvidence`, incluindo o elo por
    // tela que É derivável (UC do `.casos.md` → veredito em `scripts/casos-test-results.json`).
    const tested = applied && (evidence?.tests.length || 0) > 0;
    const smoked = tested && (evidence?.smokes.length || 0) > 0;
    // Smoke de CI/staging prova que a tela renderizou naquele ambiente. Só produção fecha
    // o funil: chamar CI de `validated` escondia precisamente a etapa que ainda faltava.
    // Recibos legados sem `host` continuam sendo produção porque esse era o único host
    // aceito antes da ADR 0390 (mesma regra de compatibilidade de currentEvidenceRecord).
    const productionSmokes = smoked
      ? evidence.smokes.filter((smoke) => smoke.host === undefined || smoke.host === 'producao')
      : [];
    const ciSmokes = smoked ? evidence.smokes.filter((smoke) => smoke.host === 'ci') : [];
    const stagingSmokes = smoked ? evidence.smokes.filter((smoke) => smoke.host === 'staging-ct100') : [];
    const validated = productionSmokes.length > 0;
    const lifecycleState = app.state === 'blocked' || app.state === 'to-create'
      ? app.state
      : validated ? 'validated'
      : stagingSmokes.length ? 'smoked-staging'
      : ciSmokes.length ? 'smoked-ci'
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
      validated,
      applicationEvidence: evidence ? {
        comparison: evidence.comparisonEvidence,
        application: evidence.application,
        tests: evidence.tests,
        smokes: evidence.smokes,
        targetSha256: evidence.targetSha256,
        proofChain: {
          bundleId: manifest.bundleId,
          sourceSha256: evidence.sourceSha256,
          mapSha256: evidence.comparisonEvidence?.mapSha256 || null,
          targetSha256: evidence.targetSha256,
          testOutputSha256: evidence.tests.map((test) => test.outputSha256),
          deploySha: evidence.smokes.map((smoke) => smoke.deploySha),
          smokeScreenshotSha256: evidence.smokes.map((smoke) => smoke.screenshotSha256),
          productionValidated: validated,
        },
      } : null,
      nextAction: lifecycleState === 'validated' ? 'aplicação, teste e smoke válidos para os hashes atuais'
        : lifecycleState === 'smoked-ci' ? 'smoke de CI registrado; executar e registrar smoke no deploy de produção'
        : lifecycleState === 'smoked-staging' ? 'smoke de staging registrado; executar e registrar smoke no deploy de produção'
        : lifecycleState === 'tested' ? 'registrar smoke com rota, deploy, screenshot e host (producao · staging-ct100 · ci — ADR 0390)'
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
      smokedCi: screens.filter((screen) => screen.lifecycleState === 'smoked-ci').length,
      smokedStaging: screens.filter((screen) => screen.lifecycleState === 'smoked-staging').length,
      validated: screens.filter((screen) => screen.lifecycleState === 'validated').length,
      lifecycle: byLifecycle,
      ...byState,
    },
    transportChanges,
    screens,
    manifestFiles: manifest.files,
  };
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

/**
 * Aviso PRÉ-RECIBO — base envelhecida (LC-20, 3ª ocorrência em 2026-09-05).
 *
 * Todo recibo abaixo grava os hashes de HEAD. Se `origin/main` já tem commit(s) nesses
 * paths que HEAD não tem, o recibo NASCE stale — foi o caso de Arquivos em 01/09: sha da
 * fonte de 24/08 gravado quando o main estava em 27/08, e nada acusou até o `--refresh`
 * de 05/09. O `git-base-freshness-guard` mede UMA vez, no início da sessão; a deriva aqui
 * é pós-início e passiva, então a medida tem que acontecer NO INSTANTE do recibo.
 *
 * ADVISORY por desenho (ADR 0224/0344): devolve a lista, nunca lança — branch de feature
 * que legitimamente edita o alvo tem `HEAD..origin/main -- <path>` vazio (o critério é
 * "main andou neste path DEPOIS do meu ponto de base", não "o arquivo difere"), e sem git
 * ou sem `origin/main` o resultado é `medido:false`, jamais "ok". FP esperado ≈ 0: só
 * dispara quando existe commit em main, no path, ausente de HEAD — exatamente o vetor.
 *
 * @param {{root?: string, arquivos: string[]}} p
 * @returns {{medido: boolean, motivo?: string, atrasados: {arquivo: string, commits: number, ultimo: string}[]}}
 */
export function avaliarBaseParaRecibo({ root = process.cwd(), arquivos = [] }) {
  const git = (args, timeout = 12000) => {
    try {
      return execFileSync('git', args, { cwd: root, encoding: 'utf8', timeout, stdio: ['ignore', 'pipe', 'ignore'] }).trim();
    } catch { return null; }
  };
  if (git(['rev-parse', '--is-inside-work-tree']) !== 'true') return { medido: false, motivo: 'fora de repositório git', atrasados: [] };
  git(['fetch', 'origin', '+refs/heads/main:refs/remotes/origin/main', '--quiet']); // best-effort; offline segue com a ref local
  if (!git(['rev-parse', '--verify', '--quiet', 'origin/main'])) return { medido: false, motivo: 'origin/main ausente', atrasados: [] };
  const atrasados = [];
  for (const arquivo of arquivos.filter(Boolean)) {
    const log = git(['log', '--oneline', 'HEAD..origin/main', '--', arquivo]);
    if (log) {
      const linhas = log.split('\n').filter(Boolean);
      atrasados.push({ arquivo, commits: linhas.length, ultimo: linhas[0] });
    }
  }
  return { medido: true, atrasados };
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

/**
 * recordTestEvidence — grava recibo de TESTE no ledger, indexado por TELA (ADR 0384 D-5).
 *
 * O QUE O RECIBO PROVA — e é menos do que a chave por tela sugere. O par
 * `sourceSha256`/`targetSha256` amarra o recibo ao CONTEÚDO: mudou o `.tsx` ou a fonte do
 * bundle, `currentWritableRecord` recusa e o recibo antigo morre. Isso é FRESCOR, e é real.
 * O que NÃO existe neste escritor é vínculo de RELEVÂNCIA: `command` é livre-forma, validado
 * só por "não-vazio" — nada aqui relaciona o comando à tela do `target`.
 *
 * MEDIDO 2026-09-18 nos 4 recibos existentes (`state/applications.json`): `Fiscal/Config`,
 * `Fiscal/Eventos` e `Fiscal/Sped` carregam o MESMO `gh run view --job 103424388127` — três
 * telas, um recibo idêntico, e o `.tsx` não aparece no comando. Esse job é
 * `PHP / Pest (NfeBrasil · MySQL)`: o recorte não é nem por módulo, é por LANE (a lane do
 * NfeBrasil lista `Modules/Fiscal/Tests`). O predicado satisfeito é "o job da lane saiu verde";
 * ele seria byte-idêntico se a lane não rodasse um único teste que toca aquela tela. É PRESENÇA,
 * não comportamento — LC-11.
 *
 * O QUE O JOB VERDE AINDA GARANTE, e não se deve jogar fora ao endurecer isto: verificado
 * 2026-09-18 em `nfebrasil-pest.yml:315`, o step `Suite provou algo? (--check-assertions · LC-13)`
 * roda SEM `continue-on-error`; ele é guardado por `if: steps.changes.outputs.nfe == 'true'`,
 * então a transitividade "job verde ⇒ assertions > 0" vale enquanto o seletor continuar exigindo
 * que o step de Pest tenha EXECUTADO (`escolherRun`, em recibos-ci.mjs — arquivo que o PR #7509
 * estava alterando nesta data; confira o estado dele antes de citar este parágrafo).
 *
 * O ELO POR TELA É DERIVÁVEL, sem convenção humana nova e sem régua paralela (LC-19): a cadeia
 * já existe e tem dono. `<Tela>.casos.md` declara UC-ids; `casos-results-collect.mjs` lê o UC-id
 * do atributo `name` do `<testcase>` do JUnit e publica o VEREDITO REAL em
 * `scripts/casos-test-results.json` (G-7 da ADR 0264) — isto é comportamento, não presença.
 * Medido 2026-09-18: 30 dos 55 alvos `.tsx` do funil têm ≥1 UC com veredito no manifesto
 * (16 não têm `.casos.md`: desses não há prova por tela a extrair). O risco de creditar a tela
 * errada por colisão de id (§5 2026-09-04) está medido: 4 de 826 UC-ids são declarados por mais
 * de um `.casos.md` — prefixo `UC-CFG`, ZERO deles nas telas que têm recibo.
 *
 * POR QUE NÃO FOI LIGADO AQUI (fato datado, não veredito perene): em 2026-09-18 o manifesto de
 * main tinha `_meta.generated_at = 2026-09-08T14:42Z` e 668 das 743 entradas com `ran_at: null`,
 * e o auto-PR que o renova (#7117, aberto em 2026-09-09) estava OPEN com `autoMerge: false`.
 * Consumir esse snapshot como RECIBO trocaria um recibo vago-porém-fresco por um
 * específico-porém-velho, embutindo estado de 10 dias atrás num ledger de evidência. Re-meça
 * antes de tratar isto como estado de hoje:
 *   node -e "console.log(require('./scripts/casos-test-results.json')._meta.generated_at)"
 *   gh pr view 7117 --json state,autoMergeRequest
 */
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
  root = process.cwd(), source, target, route, deploySha, screenshot, tenant, host = 'producao', paths = DEFAULT_PATHS,
}) {
  if (!String(route || '').startsWith('/')) throw new Error('--route deve começar com /');
  if (!/^[a-f0-9]{40,64}$/.test(String(deploySha || ''))) throw new Error('--deploy-sha deve ser SHA git válido');
  if (String(tenant) !== '1') throw new Error('smoke usa exclusivamente tenant 1 em qualquer host; biz=4 é proibido');
  if (!SMOKE_HOSTS.includes(String(host))) throw new Error(`--host deve ser ${SMOKE_HOSTS.join(', ')} (ADR 0390); recebido: ${host}`);
  const shot = repoEvidence(resolve(root), String(screenshot || '').trim());
  if (!shot) throw new Error('--screenshot deve apontar para arquivo durável existente dentro do repositório');
  const { absRoot, manifest, sourceFile, normalizedTarget, targetSha256, ledger, previous } = currentWritableRecord({ root, source, target, paths });
  const current = currentEvidenceRecord({ root: absRoot, source: sourceFile.path, target: normalizedTarget, manifest, ledger, comparison: 'SEMANTICO' });
  if (!current?.tests.length) throw new Error('smoke não pode preceder teste verde válido');
  const receipt = {
    route: String(route), deploySha: String(deploySha), tenant: 1, host: String(host),
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
