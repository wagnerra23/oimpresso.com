#!/usr/bin/env node
// @ts-check
/**
 * Bite/release dos RECIBOS por tela (ADR 0384 · 0390) — o que ficou do bundle-transaction.
 *
 * Até 2026-10-23 este teste cobria a aplicação do bundle Design v2 (sequência, delta, SHA,
 * staging, rollback, grafo de `_ds`, dsRequires). A aplicação saiu com a ADR 0438 (onda 2c-3);
 * ficam o ciclo de vida dos recibos (comparação → aplicação → teste → smoke, com invalidação em
 * cascata por hash) e o aviso de base envelhecida antes do recibo (LC-20).
 *
 * O estado inicial agora é SEMEADO direto (`semear`): espelho + `state/active-bundle.json` +
 * `refreshApplicationReport`. É o mesmo estado que a aplicação deixava, sem a transação.
 */
import { existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { createManifest, sha256 } from './bundle-contract.mjs';
import { avaliarBaseParaRecibo, refreshApplicationReport } from './bundle-transaction.mjs';

const STATUS = fileURLToPath(new URL('./status.mjs', import.meta.url));

let failures = 0;
function check(name, condition, detail = '') {
  console.log(`[${condition ? 'OK' : 'FAIL'}] ${name}${condition ? '' : ` → ${detail}`}`);
  if (!condition) failures++;
}

function put(root, path, content) {
  const abs = join(root, path);
  mkdirSync(join(abs, '..'), { recursive: true });
  writeFileSync(abs, content);
}

const DS_MARCADOR = ':root{--ds:posto-pela-rota-do-projeto-DS}\n';

function charter(target, source) {
  return `---\ncomponent: ${target}\nbundle_source: ${source}\n---\n# fixture\n`;
}

function sandbox() {
  const root = mkdtempSync(join(tmpdir(), 'design-bundle-txn-'));
  const targets = [
    'resources/js/Pages/Financeiro/Unificado/Index.tsx',
    'Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx',
    'Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/Index.tsx',
    'Modules/Officeimpresso/Resources/js/Pages/officeimpresso/Logs/Index.tsx',
    'Modules/Officeimpresso/Resources/js/Pages/officeimpresso/Logs/Timeline.tsx',
  ];
  for (const target of targets) put(root, target, `export default function Fixture(){return <div>${target}</div>}\n`);
  put(root, 'resources/js/Pages/Financeiro/Unificado/Index.charter.md', charter(targets[0], 'financeiro-page.jsx'));
  put(root, 'Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.charter.md', charter(targets[1], 'superadmin-page.jsx'));
  put(root, 'Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/Index.charter.md', charter(targets[2], 'superadmin-page.jsx'));
  put(root, 'Modules/Officeimpresso/Resources/js/Pages/officeimpresso/Logs/Index.charter.md', charter(targets[3], 'officeimpresso-page.jsx'));
  put(root, 'Modules/Officeimpresso/Resources/js/Pages/officeimpresso/Logs/Timeline.charter.md', charter(targets[4], 'officeimpresso-page.jsx'));
  // O DS existe no repo, posto pela rota do PROJETO DS (`--export-from --ds`) — nunca por este
  // transporte. O marcador permite asserir o que importa: que o export de telas NÃO o sobrescreve.
  // (PR-A9 também exige que ele exista: pacote que declara `dsRequires` e não acha o arquivo é
  // recusado, e é o comportamento desejado.)
  put(root, 'prototipo-ui/design-system/colors_and_type.css', DS_MARCADOR);
  return root;
}

function sourceSnapshot(label = 'v1') {
  return new Map([
    // ⚠️ O shell NÃO referencia `_ds/**` por path literal (mudou em 2026-09-17, decisão [W]:
    // "importação do protótipo não era para alterar o design system"). O host real resolve a base
    // em runtime (`__OI_DS_BASE__`), como o pacote 25 faz. Ref literal a `_ds/` agora é RECUSADA
    // pelo grafo — há bite-test próprio pra isso no fim do arquivo.
    ['oimpresso.com.html', Buffer.from([
      '<link rel="stylesheet" href="styles.css">',
      '<script src="financeiro-page.jsx"></script>',
      '<script src="superadmin-page.jsx"></script>',
      '<script src="officeimpresso-page.jsx"></script>',
    ].join('\n'))],
    ['styles.css', Buffer.from(`:root{--fixture:${label}}\n`)],
    ['financeiro-page.jsx', Buffer.from(`export const Financeiro='${label}';\n`)],
    ['superadmin-page.jsx', Buffer.from(`export const Superadmin='${label}';\n`)],
    ['officeimpresso-page.jsx', Buffer.from(`export const Officeimpresso='${label}';\n`)],
    ['removido.js', Buffer.from(`export const Removido='${label}';\n`)],
    ['_ds/ds-live/colors_and_type.css', Buffer.from(`:root{--ds:${label}}\n`)],
  ]);
}

function manifestFor(buffers, previous = null, mirrorScope = null) {
  return createManifest({
    source: 'cowork:fixture',
    files: [...buffers].map(([path, buffer]) => ({ path, bytes: buffer.length, sha256: sha256(buffer) })),
    previous,
    mirrorScope,
    generatedAt: previous ? '2026-08-23T12:00:00.000Z' : '2026-08-23T11:00:00.000Z',
  });
}

/** Estado que a aplicação do bundle deixava: espelho com os arquivos de tela + o manifesto
 *  promovido + o relatório recalculado. `_ds/**` não pousa no espelho (ia para o DS). */
async function semear(root, manifest, buffers) {
  for (const [path, buffer] of buffers) {
    if (path.startsWith('_ds/')) continue;
    put(root, `prototipo-ui/cowork/Wagner/${path}`, buffer);
  }
  put(root, 'scripts/design-sync/state/active-bundle.json', JSON.stringify(manifest, null, 2) + '\n');
  await refreshApplicationReport({ root });
}


console.log('\n=== estados exigem recibos reais e invalidam em cascata por hash ===');
{
  const root = sandbox();
  const before = sourceSnapshot('v1');
  const m1 = manifestFor(before);
  await semear(root, m1, before);
  const target = 'Modules/Officeimpresso/Resources/js/Pages/officeimpresso/Logs/Index.tsx';
  put(root, 'memory/requisitos/Officeimpresso/logs.map.json', JSON.stringify({
    version: '1', tela: 'Officeimpresso/Logs', prototipo_sha: 'sem-historico',
    mapping: { source: 'officeimpresso-page.jsx', target },
    partes: [{
      id: 'root', prototipo: { arquivo: 'prototipo-ui/cowork/Wagner/officeimpresso-page.jsx', linhas: '1' },
      vivo: { arquivo: target, linhas: '1', ancora: false }, status: 'mapeado', acao: 'aplicar',
    }],
  }, null, 2));
  put(root, 'memory/requisitos/Officeimpresso/aplicacao.md', '# Evidência de aplicação\n');
  put(root, 'memory/evidence/officeimpresso-smoke.png', 'png-fixture');

  let prematureApplicationRejected = false;
  try {
    execFileSync(process.execPath, [
      STATUS, '--root', root,
      '--mark-applied', 'officeimpresso-page.jsx', '--target', target,
      '--evidence', 'memory/requisitos/Officeimpresso/aplicacao.md',
    ], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  } catch (error) { prematureApplicationRejected = /não pode preceder comparação válida/.test(String(error.stderr || '')); }
  check('controle negativo: aplicação antes da comparação é recusada', prematureApplicationRejected);

  execFileSync(process.execPath, [
    STATUS, '--root', root,
    '--mark-compared', 'officeimpresso-page.jsx',
    '--target', target,
    '--map', 'memory/requisitos/Officeimpresso/logs.map.json',
  ], { encoding: 'utf8' });
  let recorded = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  let applied = recorded.screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('map real + hashes atuais levam a COMPARADA', applied.lifecycleState === 'compared' && applied.compared);

  execFileSync(process.execPath, [
    STATUS, '--root', root,
    '--mark-applied', 'officeimpresso-page.jsx',
    '--target', target,
    '--evidence', 'memory/requisitos/Officeimpresso/aplicacao.md',
  ], { encoding: 'utf8' });
  recorded = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  applied = recorded.screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('arquivo de evidência com hash leva a APLICADA', applied.lifecycleState === 'applied' && !applied.tested);

  const ledgerPath = join(root, 'scripts/design-sync/state/applications.json');
  const ledgerWithoutProofHash = JSON.parse(readFileSync(ledgerPath, 'utf8'));
  delete ledgerWithoutProofHash.applications[0].application.evidenceSha256;
  writeFileSync(ledgerPath, JSON.stringify(ledgerWithoutProofHash, null, 2));
  execFileSync(process.execPath, [STATUS, '--root', root, '--refresh'], { encoding: 'utf8' });
  recorded = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  applied = recorded.screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('controle negativo: evidência sem SHA não conta como aplicação', applied.lifecycleState === 'compared' && !applied.tested);
  execFileSync(process.execPath, [
    STATUS, '--root', root,
    '--mark-applied', 'officeimpresso-page.jsx', '--target', target,
    '--evidence', 'memory/requisitos/Officeimpresso/aplicacao.md',
  ], { encoding: 'utf8' });

  let failedTestRejected = false;
  try {
    execFileSync(process.execPath, [
      STATUS, '--root', root,
      '--run-test', 'officeimpresso-page.jsx', '--target', target, '--runner', 'local',
      '--command-json', JSON.stringify([process.execPath, '-e', 'process.exit(7)']),
    ], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  } catch (error) { failedTestRejected = /teste falhou/.test(String(error.stderr || '')); }
  recorded = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  applied = recorded.screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('controle negativo: comando vermelho não grava recibo verde', failedTestRejected && applied.lifecycleState === 'applied' && !applied.tested);

  execFileSync(process.execPath, [
    STATUS, '--root', root,
    '--run-test', 'officeimpresso-page.jsx', '--target', target, '--runner', 'local',
    '--command-json', JSON.stringify([process.execPath, '-e', "process.stdout.write('teste-ok')"]),
  ], { encoding: 'utf8' });
  recorded = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  applied = recorded.screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('só comando realmente executado leva a TESTADA', applied.lifecycleState === 'tested' && applied.tested && !applied.smoked);

  let tenant4Rejected = false;
  try {
    execFileSync(process.execPath, [
      STATUS, '--root', root,
      '--record-smoke', 'officeimpresso-page.jsx', '--target', target, '--route', '/officeimpresso/logs',
      '--deploy-sha', 'a'.repeat(40), '--screenshot', 'memory/evidence/officeimpresso-smoke.png', '--tenant', '4',
    ], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  } catch (error) { tenant4Rejected = /biz=4 é proibido/.test(String(error.stderr || '')); }
  check('controle negativo: smoke biz=4 é recusado', tenant4Rejected);

  execFileSync(process.execPath, [
    STATUS, '--root', root,
    '--record-smoke', 'officeimpresso-page.jsx', '--target', target, '--route', '/officeimpresso/logs',
    '--deploy-sha', 'a'.repeat(40), '--screenshot', 'memory/evidence/officeimpresso-smoke.png', '--tenant', '1',
  ], { encoding: 'utf8' });
  recorded = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  applied = recorded.screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('rota + deploy + screenshot + tenant 1 levam a VALIDADA', applied.lifecycleState === 'validated' && applied.smoked);
  check('ledger durável fica fora de _ds', existsSync(join(root, 'scripts/design-sync/state/applications.json')));

  put(root, 'memory/evidence/officeimpresso-smoke.png', 'png-fixture-alterado');
  execFileSync(process.execPath, [STATUS, '--root', root, '--refresh'], { encoding: 'utf8' });
  recorded = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  applied = recorded.screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('screenshot alterado invalida só o smoke', applied.lifecycleState === 'tested' && !applied.smoked);

  // ADR 0390 — `host` do smoke: enum fechado (producao · staging-ct100 · ci). Controle negativo
  // primeiro (valor fora do enum não vira recibo), depois o host `ci` provando só aquele
  // ambiente. Produção precisa de recibo próprio.
  let hostInvalidoRejeitado = false;
  try {
    execFileSync(process.execPath, [
      STATUS, '--root', root,
      '--record-smoke', 'officeimpresso-page.jsx', '--target', target, '--route', '/officeimpresso/logs',
      '--deploy-sha', 'b'.repeat(40), '--screenshot', 'memory/evidence/officeimpresso-smoke.png', '--tenant', '1',
      '--host', 'laptop-do-agente',
    ], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  } catch (error) { hostInvalidoRejeitado = /--host deve ser producao, staging-ct100, ci/.test(String(error.stderr || '')); }
  recorded = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  applied = recorded.screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('controle negativo ADR 0390: host fora do enum é recusado e a tela segue TESTADA', hostInvalidoRejeitado && applied.lifecycleState === 'tested' && !applied.smoked);

  execFileSync(process.execPath, [
    STATUS, '--root', root,
    '--record-smoke', 'officeimpresso-page.jsx', '--target', target, '--route', '/officeimpresso/logs',
    '--deploy-sha', 'b'.repeat(40), '--screenshot', 'memory/evidence/officeimpresso-smoke.png', '--tenant', '1',
    '--host', 'ci',
  ], { encoding: 'utf8' });
  recorded = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  applied = recorded.screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  const reciboCi = JSON.parse(readFileSync(ledgerPath, 'utf8')).applications
    .find((item) => item.source === 'officeimpresso-page.jsx' && item.target === target)?.smokes?.at(-1);
  check('smoke com host ci grava o recibo mas NÃO declara produção validada',
    applied.lifecycleState === 'smoked-ci' && applied.smoked && !applied.validated && reciboCi?.host === 'ci' && reciboCi?.tenant === 1,
    JSON.stringify(reciboCi));
  check('cadeia de prova carrega bundle/source/map/target/test/deploy/screenshot por tela',
    applied.applicationEvidence?.proofChain?.bundleId
      && /^[a-f0-9]{64}$/.test(applied.applicationEvidence.proofChain.sourceSha256 || '')
      && /^[a-f0-9]{64}$/.test(applied.applicationEvidence.proofChain.mapSha256 || '')
      && /^[a-f0-9]{64}$/.test(applied.applicationEvidence.proofChain.targetSha256 || '')
      && applied.applicationEvidence.proofChain.testOutputSha256.every((sha) => /^[a-f0-9]{64}$/.test(sha))
      && applied.applicationEvidence.proofChain.deploySha.includes('b'.repeat(40))
      && applied.applicationEvidence.proofChain.smokeScreenshotSha256.every((sha) => /^[a-f0-9]{64}$/.test(sha))
      && applied.applicationEvidence.proofChain.productionValidated === false,
    JSON.stringify(applied.applicationEvidence?.proofChain));

  execFileSync(process.execPath, [
    STATUS, '--root', root,
    '--record-smoke', 'officeimpresso-page.jsx', '--target', target, '--route', '/officeimpresso/logs',
    '--deploy-sha', 'c'.repeat(40), '--screenshot', 'memory/evidence/officeimpresso-smoke.png', '--tenant', '1',
    '--host', 'producao',
  ], { encoding: 'utf8' });
  recorded = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  applied = recorded.screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('somente smoke de produção leva a VALIDADA',
    applied.lifecycleState === 'validated' && applied.validated
      && applied.applicationEvidence?.proofChain?.productionValidated === true,
    JSON.stringify(applied));

  const mapPath = join(root, 'memory/requisitos/Officeimpresso/logs.map.json');
  const changedMap = JSON.parse(readFileSync(mapPath, 'utf8'));
  changedMap._revisao = 'comparação refeita';
  writeFileSync(mapPath, JSON.stringify(changedMap, null, 2));
  execFileSync(process.execPath, [
    STATUS, '--root', root,
    '--mark-compared', 'officeimpresso-page.jsx',
    '--target', target,
    '--map', 'memory/requisitos/Officeimpresso/logs.map.json',
  ], { encoding: 'utf8' });
  recorded = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  applied = recorded.screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('map alterado invalida aplicação e teste anteriores', applied.lifecycleState === 'compared' && !applied.tested);

  const after = sourceSnapshot('v2');
  const m2 = manifestFor(after, m1);
  await semear(root, m2, after);
  const invalidated = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'))
    .screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('mudança no hash da fonte invalida comparação, aplicação, teste e smoke',
    invalidated.lifecycleState === 'anchored' && invalidated.applicationState === 'pending' && !invalidated.tested && !invalidated.smoked);

  execFileSync(process.execPath, [
    STATUS, '--root', root,
    '--mark-compared', 'officeimpresso-page.jsx',
    '--target', target,
    '--map', 'memory/requisitos/Officeimpresso/logs.map.json',
  ], { encoding: 'utf8' });
  const recomparison = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'))
    .screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('recomparar hash novo não ressuscita aplicação/teste/smoke antigos',
    recomparison.lifecycleState === 'compared' && !recomparison.tested && !recomparison.smoked);
}

// ── LC-20 · aviso pré-recibo de base envelhecida (bite/release num repo git real) ─────────
{
  const g = (cwd, args) => execFileSync('git', args, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  const semGit = mkdtempSync(join(tmpdir(), 'design-base-nogit-'));
  const r0 = avaliarBaseParaRecibo({ root: semGit, arquivos: ['a.txt'] });
  check('LC-20 sem repo git → medido:false (nunca "ok" silencioso)', r0.medido === false && r0.atrasados.length === 0);

  const repo = mkdtempSync(join(tmpdir(), 'design-base-git-'));
  g(repo, ['init', '-q', '-b', 'main']);
  g(repo, ['config', 'user.email', 'test@example.com']); g(repo, ['config', 'user.name', 'test']);
  put(repo, 'alvo.tsx', 'v1\n'); put(repo, 'outro.tsx', 'v1\n');
  g(repo, ['add', '.']); g(repo, ['commit', '-q', '-m', 'base']);
  g(repo, ['checkout', '-q', '-b', 'trabalho']);
  g(repo, ['checkout', '-q', 'main']);
  put(repo, 'alvo.tsx', 'v2 no main\n'); g(repo, ['commit', '-q', '-am', 'main anda no alvo']);
  g(repo, ['update-ref', 'refs/remotes/origin/main', 'main']); // simula origin/main sem rede
  g(repo, ['checkout', '-q', 'trabalho']);
  const r1 = avaliarBaseParaRecibo({ root: repo, arquivos: ['alvo.tsx', 'outro.tsx'] });
  check('BITE LC-20: main andou em alvo.tsx depois da base → 1 atrasado (só ele)',
    r1.medido === true && r1.atrasados.length === 1 && r1.atrasados[0].arquivo === 'alvo.tsx' && r1.atrasados[0].commits === 1,
    JSON.stringify(r1));
  g(repo, ['merge', '-q', 'main']);
  const r2 = avaliarBaseParaRecibo({ root: repo, arquivos: ['alvo.tsx', 'outro.tsx'] });
  check('RELEASE LC-20: depois de trazer o main → 0 atrasados', r2.medido === true && r2.atrasados.length === 0, JSON.stringify(r2));
  put(repo, 'outro.tsx', 'edicao legitima no branch\n'); g(repo, ['commit', '-q', '-am', 'feature edita outro']);
  const r3 = avaliarBaseParaRecibo({ root: repo, arquivos: ['outro.tsx'] });
  check('FP LC-20: branch editar o alvo NÃO é base envelhecida (critério é main à frente, não "difere")', r3.atrasados.length === 0, JSON.stringify(r3));
}

console.log(failures ? `\n✗ ${failures} falha(s)` : '\n✓ recibos por tela: ciclo de vida + invalidação em cascata por hash + aviso de base envelhecida');
process.exit(failures ? 1 : 0);