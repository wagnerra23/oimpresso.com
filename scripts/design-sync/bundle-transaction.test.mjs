#!/usr/bin/env node
// @ts-check
/** Bite/release do transporte v2: sequência, delta, SHA, staging, rollback e plano modular. */
import {
  existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, writeFileSync, rmSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import {
  BUNDLE_SCHEMA, changesDigest, createManifest, manifestDigest, sha256,
} from './bundle-contract.mjs';
import { applyBundleTransaction, avaliarBaseParaRecibo, pathsForOwner, conferirDsRequires, vereditoDsRequires } from './bundle-transaction.mjs';

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

function partsFor(manifest, buffers, count = 1, chunkBytes = 1024 * 1024) {
  const changed = new Set([...manifest.changes.added, ...manifest.changes.modified]);
  const chunks = [];
  for (const [path, buffer] of buffers) {
    if (!changed.has(path)) continue;
    const total = Math.max(1, Math.ceil(buffer.length / chunkBytes));
    for (let index = 0; index < total; index++) {
      const offset = index * chunkBytes;
      const piece = buffer.subarray(offset, Math.min(buffer.length, offset + chunkBytes));
      chunks.push({
        path, index: index + 1, count: total, offset, bytes: piece.length,
        sha256: sha256(piece), content: piece.toString('base64'),
      });
    }
  }
  const buckets = Array.from({ length: Math.max(1, count) }, () => []);
  chunks.forEach((chunk, index) => buckets[index % buckets.length].push(chunk));
  const core = {
    id: manifest.bundleId,
    baseId: manifest.baseBundleId,
    mode: manifest.mode,
    source: manifest.source,
    generatedAt: manifest.generatedAt,
    entry: manifest.entry,
    parts: buckets.length,
    files: manifest.totals.files,
    bytes: manifest.totals.bytes,
    manifestSha256: manifestDigest(manifest),
    changesSha256: changesDigest(manifest.changes),
  };
  return buckets.map((bucket, index) => ({
    schema: BUNDLE_SCHEMA,
    bundle: core,
    part: index + 1,
    parts: buckets.length,
    ...(index === 0 ? { targetManifest: manifest } : {}),
    chunks: bucket,
  }));
}

async function rejects(name, fn, pattern) {
  try {
    await fn();
    check(name, false, 'não rejeitou');
  } catch (error) {
    check(name, pattern.test(error.message), error.message);
  }
}

console.log('\n=== snapshot completo + roteamento + plano modular ===');
{
  const root = sandbox();
  const buffers = sourceSnapshot();
  const manifest = manifestFor(buffers);
  const result = await applyBundleTransaction({ root, parts: partsFor(manifest, buffers, 2) });
  check('snapshot promove o espelho', readFileSync(join(root, 'prototipo-ui/cowork/Wagner/styles.css'), 'utf8').includes('v1'));
  // ⚠️ CONTRATO MUDOU em 2026-09-17 ([W]: "importação do protótipo não era para alterar o design
  // system"). Antes o `_ds/` pousava em `prototipo-ui/design-system/` — no-op quando os bytes
  // batiam, SOBRESCRITA quando não batiam. Agora o export de telas não escreve o DS em lugar
  // nenhum: quem manda ali é o projeto DS (#7096), pela rota `--export-from --ds`.
  check('_ds NÃO pousa — o DS do repo NÃO é sobrescrito pelo export de telas',
    readFileSync(join(root, 'prototipo-ui/design-system/colors_and_type.css'), 'utf8') === DS_MARCADOR &&
    !existsSync(join(root, 'prototipo-ui/cowork/Wagner/_ds')));
  check('estado ativo registra o bundle exato', JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/active-bundle.json'), 'utf8')).bundleId === manifest.bundleId);
  const report = result.report;
  const superadmin = report.screens.filter((screen) => screen.source === 'superadmin-page.jsx');
  const office = report.screens.filter((screen) => screen.source === 'officeimpresso-page.jsx');
  check('Superadmin preserva mapeamento 1:N', superadmin.length === 2 && superadmin.every((screen) => screen.module === 'Superadmin'));
  check('Officeimpresso preserva mapeamento 1:N', office.length === 2 && office.every((screen) => screen.module === 'Officeimpresso'));
  check('relatório diz o que precisa ser feito', report.screens.every((screen) => screen.applicationState === 'pending' && /aplicar/.test(screen.nextAction)));
  const status = execFileSync(process.execPath, [STATUS, '--root', root], { encoding: 'utf8' });
  check('CLI lista arquivos + módulos sem abrir JSON na mão', /ARQUIVOS MODIFICADOS/.test(status) && /Officeimpresso/.test(status) && /Superadmin/.test(status));
  check('CLI --check-mapping libera mapeamento completo',
    execFileSync(process.execPath, [STATUS, '--root', root, '--check-mapping'], { encoding: 'utf8' }).includes('DESIGN-SYNC'));
}

console.log('\n=== o contrato aceita o .md do Cowork e segue recusando extensão fora e duplicata ===');
{
  // O `.md` era recusado aqui e o caso de exemplo era `cowork-inbox/LEIAME.md` — ou seja, a
  // própria ordem de serviço do Cowork não cabia no bundle. Decisão [W] 2026-09-13: o espelho
  // recebe a conta como ela é. A recusa de extensão fora do espelho e a de duplicata FICAM.
  const comDocumento = sourceSnapshot();
  comDocumento.set('cowork-inbox/LEIAME.md', Buffer.from('# doc\n'));
  const mDoc = await manifestFor(comDocumento);
  check('BITE: o .md do Cowork entra no bundle', JSON.stringify(mDoc).includes('cowork-inbox/LEIAME.md'));

  const comBinario = sourceSnapshot();
  comBinario.set('relatorio.pdf', Buffer.from('%PDF\n'));
  await rejects('CONTROLE: extensão fora do espelho segue recusada',
    async () => manifestFor(comBinario), /fora do contrato build-only/);

  const comDuplicata = sourceSnapshot();
  comDuplicata.set('copia/styles.css', comDuplicata.get('styles.css'));
  await rejects('mesmos bytes em dois paths ativos são recusados',
    async () => manifestFor(comDuplicata), /conteúdo duplicado/);
}

console.log('\n=== transporte completo pode pousar, aplicação órfã continua bloqueada ===');
{
  const root = sandbox();
  const buffers = sourceSnapshot();
  buffers.set('mistero-page.jsx', Buffer.from('export const Mistero=true;\n'));
  buffers.set('oimpresso.com.html', Buffer.concat([
    buffers.get('oimpresso.com.html'), Buffer.from('\n<script src="mistero-page.jsx"></script>'),
  ]));
  const manifest = manifestFor(buffers);
  const result = await applyBundleTransaction({ root, parts: partsFor(manifest, buffers) });
  check('recepção do espelho não perde fonte órfã', existsSync(join(root, 'prototipo-ui/cowork/Wagner/mistero-page.jsx')));
  check('relatório marca destino desconhecido como blocked', result.report.screens.some((screen) => screen.source === 'mistero-page.jsx' && screen.applicationState === 'blocked'));
  let code = 0, output = '';
  try { execFileSync(process.execPath, [STATUS, '--root', root, '--check-mapping'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }); }
  catch (error) { code = error.status ?? 1; output = String(error.stderr || ''); }
  check('CLI fail-closed reprova aplicação com órfão', code === 1 && /sem destino inequívoco/.test(output), `code=${code} ${output}`);
}

console.log('\n=== estados exigem recibos reais e invalidam em cascata por hash ===');
{
  const root = sandbox();
  const before = sourceSnapshot('v1');
  const m1 = manifestFor(before);
  await applyBundleTransaction({ root, parts: partsFor(m1, before) });
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
  // primeiro (valor fora do enum não vira recibo), depois o host `ci` levando a VALIDADA — o
  // estado que ficou 0/93 por construção enquanto só produção contava.
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
  check('ADR 0390: smoke com host ci grava o host no recibo e leva a VALIDADA',
    applied.lifecycleState === 'validated' && applied.smoked && reciboCi?.host === 'ci' && reciboCi?.tenant === 1,
    JSON.stringify(reciboCi));

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
  await applyBundleTransaction({ root, parts: partsFor(m2, after) });
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

console.log('\n=== delta baixa só mudanças, remove owned e preserva unchanged ===');
{
  const root = sandbox();
  const before = sourceSnapshot('v1');
  const m1 = manifestFor(before);
  await applyBundleTransaction({ root, parts: partsFor(m1, before) });
  put(root, 'prototipo-ui/cowork/Wagner/unmanaged-local.txt', 'preservar\n');

  const after = sourceSnapshot('v2');
  after.set('nao-referenciado.js', Buffer.from('export const novo=true;\n'));
  after.delete('removido.js');
  // Mantém o Officeimpresso byte-idêntico para provar que não viaja no delta.
  after.set('officeimpresso-page.jsx', before.get('officeimpresso-page.jsx'));
  const m2 = manifestFor(after, m1);
  const parts = partsFor(m2, after);
  const transported = parts.flatMap((part) => part.chunks.map((chunk) => chunk.path));
  check('unchanged não viaja', !transported.includes('officeimpresso-page.jsx'));
  check('deleted não viaja como conteúdo', !transported.includes('removido.js'));
  await applyBundleTransaction({ root, parts });
  check('modified foi promovido', readFileSync(join(root, 'prototipo-ui/cowork/Wagner/styles.css'), 'utf8').includes('v2'));
  check('deleted owned foi removido', !existsSync(join(root, 'prototipo-ui/cowork/Wagner/removido.js')));
  check('arquivo fora do manifesto é preservado', readFileSync(join(root, 'prototipo-ui/cowork/Wagner/unmanaged-local.txt'), 'utf8') === 'preservar\n');
}

console.log('\n=== fail-closed: partes, base, hash, path e dry-run ===');
{
  console.log('\n=== árvore completa: poda de sobras, inclusão, dry-run e rollback ===');
  const root = sandbox();
  const before = sourceSnapshot('v1');
  const m1 = manifestFor(before);
  await applyBundleTransaction({ root, parts: partsFor(m1, before) });
  put(root, 'prototipo-ui/cowork/Wagner/velho/sub/sobra.md', 'arquivo nunca gerenciado\n');
  put(root, 'prototipo-ui/cowork/Wagner/.gitignore', 'regra local\n');
  put(root, 'prototipo-ui/design-system/canon-sentinela.css', 'não tocar no DS\n');
  const after = sourceSnapshot('v2');
  after.delete('removido.js');
  after.set('cowork-inbox/novo.md', Buffer.from('playbook novo\n'));
  const m2 = manifestFor(after, m1, 'tree');
  const parts = partsFor(m2, after);
  const sobra = join(root, 'prototipo-ui/cowork/Wagner/velho/sub/sobra.md');
  await applyBundleTransaction({ root, parts, dry: true });
  check('árvore dry-run não apaga sobra real', existsSync(sobra));
  await rejects('árvore rollback após swap mantém import anterior',
    () => applyBundleTransaction({ root, parts, failAfterSwap: 2 }), /falha injetada/);
  check('árvore rollback restaura arquivo não gerenciado', existsSync(sobra));
  await applyBundleTransaction({ root, parts });
  check('árvore completa remove sobra fora do manifesto e diretório vazio', !existsSync(join(root, 'prototipo-ui/cowork/Wagner/velho')));
  check('árvore completa remove arquivo gerenciado ausente', !existsSync(join(root, 'prototipo-ui/cowork/Wagner/removido.js')));
  check('árvore completa inclui playbook novo', existsSync(join(root, 'prototipo-ui/cowork/Wagner/cowork-inbox/novo.md')));
  check('árvore completa atualiza conteúdo', readFileSync(join(root, 'prototipo-ui/cowork/Wagner/styles.css'), 'utf8').includes('v2'));
  check('poda não alcança DS canônico', readFileSync(join(root, 'prototipo-ui/design-system/canon-sentinela.css'), 'utf8') === 'não tocar no DS\n');
  check('poda preserva guarda local .gitignore', existsSync(join(root, 'prototipo-ui/cowork/Wagner/.gitignore')));
  const m3 = manifestFor(after, m2, 'tree');
  await applyBundleTransaction({ root, parts: partsFor(m3, after) });
  check('reimportação regenerada de árvore é idempotente', !existsSync(sobra));
  const partial = manifestFor(sourceSnapshot('v3'), m3);
  await rejects('delta parcial não pode apagar playbooks depois de árvore completa',
    () => applyBundleTransaction({ root, parts: partsFor(partial, sourceSnapshot('v3')) }), /exige novo manifesto de árvore completa/);
  const forged = structuredClone(m1);
  forged.mirrorScope = 'tree';
  await rejects('escopo de poda não pode ser acrescentado sem mudar identidade',
    () => applyBundleTransaction({ root: sandbox(), parts: partsFor(forged, before) }), /bundleId divergente/);
}
{
  const producer = mkdtempSync(join(tmpdir(), 'design-tree-producer-'));
  for (const [path, buffer] of sourceSnapshot()) put(producer, path, buffer);
  put(producer, 'cowork-inbox/novo.md', 'playbook não alcançável pelo shell\n');
  put(producer, 'cowork-inbox/contrato.json', '{"fixture":"json"}\n');
  put(producer, 'sync/nao-realimentar.md', 'transporte velho\n');
  const out = join(producer, 'sync');
  execFileSync(process.execPath, [fileURLToPath(new URL('./gerar-payload-partes.mjs', import.meta.url)),
    '--root', producer, '--out', out, '--full-tree', '--piso', '0', '--owner', 'Wagner'], { encoding: 'utf8' });
  const manifest = JSON.parse(readFileSync(join(out, 'bundle.manifest.json'), 'utf8'));
  check('gerador de árvore declara escopo autenticado', manifest.mirrorScope === 'tree');
  check('gerador inclui playbook e contrato fora do shell', ['cowork-inbox/novo.md', 'cowork-inbox/contrato.json'].every((path) => manifest.files.some((file) => file.path === path)));
  check('gerador não realimenta sync', !manifest.files.some((file) => file.path.startsWith('sync/')));
  const root = sandbox();
  put(root, 'prototipo-ui/cowork/Wagner/sobra-primeiro-import.md', 'sobra\n');
  const parts = readdirSync(out).filter((name) => /^payload\.part.*\.json$/.test(name)).map((name) => JSON.parse(readFileSync(join(out, name), 'utf8')));
  await applyBundleTransaction({ root, parts });
  check('primeiro snapshot de árvore limpa sobra e importa JSON', !existsSync(join(root, 'prototipo-ui/cowork/Wagner/sobra-primeiro-import.md')) && existsSync(join(root, 'prototipo-ui/cowork/Wagner/cowork-inbox/contrato.json')));
  const collision = sandbox();
  put(collision, 'prototipo-ui/cowork/Felipe/fonte.md', 'playbook não alcançável pelo shell\n');
  put(collision, 'prototipo-ui/cowork/Wagner/antes.md', 'espelho anterior\n');
  await applyBundleTransaction({ root: collision, parts });
  check('bytes iguais entre contas são aceitos sem apagar Felipe', readFileSync(join(collision, 'prototipo-ui/cowork/Felipe/fonte.md'), 'utf8') === 'playbook não alcançável pelo shell\n');
  const wagnerState = readFileSync(join(collision, 'scripts/design-sync/state/active-bundle.json'));
  const felipeManifest = manifestFor(sourceSnapshot(), null, 'tree');
  await applyBundleTransaction({ root: collision, parts: partsFor(felipeManifest, sourceSnapshot()), paths: pathsForOwner('Felipe') });
  check('Felipe tem seu bundle ativo e não altera o estado de Wagner', existsSync(join(collision, 'scripts/design-sync/state/Felipe/active-bundle.json')) && readFileSync(join(collision, 'scripts/design-sync/state/active-bundle.json')).equals(wagnerState));
  check('importar Felipe não apaga os playbooks de Wagner', existsSync(join(collision, 'prototipo-ui/cowork/Wagner/cowork-inbox/novo.md')));
}
{
  const root = sandbox();
  const buffers = sourceSnapshot();
  const manifest = manifestFor(buffers);
  const parts = partsFor(manifest, buffers, 3);
  await rejects('part01 ausente é detectada por qualquer parte restante',
    () => applyBundleTransaction({ root, parts: parts.slice(1) }), /recebeu 2\/3 partes/);
  check('part01 ausente não cria estado', !existsSync(join(root, 'scripts/design-sync/state/active-bundle.json')));

  const corrupt = structuredClone(parts);
  corrupt[0].chunks[0].content = Buffer.from('corrompido').toString('base64');
  await rejects('chunk corrompido reprova por bytes/SHA',
    () => applyBundleTransaction({ root, parts: corrupt }), /bytes do chunk divergem|sha256 do chunk diverge/);
  check('corrupção não toca o destino', !existsSync(join(root, 'prototipo-ui/cowork/Wagner/oimpresso.com.html')));

  await rejects('path traversal é recusado no manifesto', async () => {
    const evil = new Map(buffers);
    evil.set('../fora.txt', Buffer.from('x'));
    manifestFor(evil);
  }, /caminho inseguro/);

  const dry = await applyBundleTransaction({ root, parts, dry: true });
  check('dry-run produz plano', dry.report.screens.length === 5);
  check('dry-run não promove nem estado nem espelho',
    !existsSync(join(root, 'scripts/design-sync/state/active-bundle.json')) &&
    !existsSync(join(root, 'prototipo-ui/cowork/Wagner/oimpresso.com.html')));
}

console.log('\n=== base divergente e rollback durante promoção ===');
{
  const root = sandbox();
  const before = sourceSnapshot('v1');
  const m1 = manifestFor(before);
  await applyBundleTransaction({ root, parts: partsFor(m1, before) });
  const originalSource = readFileSync(join(root, 'prototipo-ui/cowork/Wagner/styles.css'));
  const originalState = readFileSync(join(root, 'scripts/design-sync/state/active-bundle.json'));

  const after = sourceSnapshot('v2');
  const m2 = manifestFor(after, m1);
  const wrong = structuredClone(m2);
  wrong.baseBundleId = 'f'.repeat(64);
  const wrongParts = partsFor(wrong, after);
  await rejects('delta com base diferente do estado ativo é recusado',
    () => applyBundleTransaction({ root, parts: wrongParts }), /base divergente/);

  await rejects('falha após dois swaps dispara rollback',
    () => applyBundleTransaction({ root, parts: partsFor(m2, after), failAfterSwap: 2 }), /falha injetada/);
  check('rollback restaura bytes do espelho', readFileSync(join(root, 'prototipo-ui/cowork/Wagner/styles.css')).equals(originalSource));
  check('rollback restaura estado ativo', readFileSync(join(root, 'scripts/design-sync/state/active-bundle.json')).equals(originalState));
  const leftovers = [
    ...readdirSync(join(root, 'prototipo-ui')).filter((name) => /\.(?:stage|backup)-/.test(name)),
    ...readdirSync(join(root, 'scripts/design-sync')).filter((name) => /\.(?:stage|backup)-/.test(name)),
  ];
  check('rollback limpa staging/backup', leftovers.length === 0, leftovers.join(','));
}

console.log('\n=== --check-lifecycle: a catraca do escopo novo morde pelo CLI de fora ===');
// Um script com N modos é N gates (§5 2026-07-28): o lifecycle inteiro já era provado acima,
// mas o modo que GATEIA (--check-lifecycle) tinha 0 sondas pelo CLI. Três provas:
// abaixo do mínimo → exit 1 · no mínimo → exit 0 · sem escopo → exit 2 (a catraca RECUSA
// virar bloqueio global do legado — isso é contrato, não omissão).
{
  const root = sandbox();
  const buffers = sourceSnapshot();
  const manifest = manifestFor(buffers);
  await applyBundleTransaction({ root, parts: partsFor(manifest, buffers) });
  const report = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  const screen = report.screens.find((item) => item.source === 'officeimpresso-page.jsx');
  const state = screen.lifecycleState || 'review';
  check('pré-condição: tela da sandbox está abaixo de validated', state !== 'validated', `state=${state}`);

  const probe = (extra) => {
    try {
      execFileSync(process.execPath, [STATUS, '--root', root, '--check-lifecycle', ...extra],
        { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
      return 0;
    } catch (error) { return error.status ?? 1; }
  };
  check('BITE: tela abaixo do --minimum → exit 1',
    probe(['--source', 'officeimpresso-page.jsx', '--minimum', 'validated']) === 1);
  check('RELEASE: tela no estado mínimo → exit 0',
    probe(['--source', 'officeimpresso-page.jsx', '--minimum', state]) === 0);
  check('ESCOPO: sem --source/--module → exit 2 (legado não vira bloqueio global)',
    probe([]) === 2);
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

// ── DELETE de _ds/ NÃO atravessa pro Design System (incidente 2026-09-17) ─────────
// O pacote de telas 25 removeu o cache `_ds/` do projeto dele — legítimo DAQUELE lado — e a
// transação traduziu isso em apagar 10 arquivos do DS canônico aqui, incluindo
// `colors_and_type.css` e as 4 `ibm-plex-sans-*.woff2`. O passo [4] do receber-handoff já
// protegia a ESCRITA por dono; faltava o DELETE. Provado nos dois sentidos abaixo.
{
  const root = sandbox();
  const antes = sourceSnapshot('v1');
  const m1 = manifestFor(antes);
  await applyBundleTransaction({ root, parts: partsFor(m1, antes) });
  const alvoDs = join(root, 'prototipo-ui/design-system/colors_and_type.css');
  const alvoTela = join(root, 'prototipo-ui/cowork/Wagner/removido.js');
  // O DS é populado pela rota do PROJETO DS (`--export-from --ds`), não por este transporte —
  // por isso escrevo o arquivo à mão aqui em vez de esperar que o apply o crie.
  put(root, 'prototipo-ui/design-system/colors_and_type.css', ':root{--ds:posto-pela-rota-ds}\n');
  check('SETUP: o DS existe (posto por OUTRA rota) e a tela pousou pelo export',
    existsSync(alvoDs) && existsSync(alvoTela));

  // delta que apaga OS DOIS: um path `preview-cache` e um path de tela comum. O shell perde a ref
  // ao `_ds/` junto — é o caso REAL do pacote 25, onde o host passou a resolver a base em runtime
  // (`__OI_DS_BASE__`); sem isso o grafo acusaria `missing` e a transação recusaria antes do ponto
  // que este teste quer exercer.
  const depois = new Map(antes);
  depois.set('oimpresso.com.html', Buffer.from([
    '<link rel="stylesheet" href="styles.css">',
    '<script src="financeiro-page.jsx"></script>',
    '<script src="superadmin-page.jsx"></script>',
    '<script src="officeimpresso-page.jsx"></script>',
  ].join('\n')));
  depois.delete('_ds/ds-live/colors_and_type.css');
  depois.delete('removido.js');
  const m2 = manifestFor(depois, m1);
  check('SETUP: o manifesto de fato pede as 2 remoções',
    m2.changes.deleted.includes('_ds/ds-live/colors_and_type.css') && m2.changes.deleted.includes('removido.js'),
    JSON.stringify(m2.changes.deleted));
  await applyBundleTransaction({ root, parts: partsFor(m2, depois) });

  check('MORDE: remoção de _ds/ é IGNORADA — o Design System sobrevive ao export de telas',
    existsSync(alvoDs));
  // CONTROLE NEGATIVO: sem ele, uma transação que parasse de apagar QUALQUER coisa passaria.
  check('CONTROLE: remoção de path de TELA continua efetivada (não virou "não apaga nada")',
    !existsSync(alvoTela));
  rmSync(root, { recursive: true, force: true });
}

// ── host que depende de `_ds/` por path LITERAL é RECUSADO ───────────────────────
// Consequência desejada da regra acima, e o sinal que faltava: se o shell referencia o cache do
// DS por path fixo, o lote não entra — porque entrar exigiria escrever no `design-system/`, que
// este transporte não pode. O caminho certo é o host resolver a base em runtime
// (`__OI_DS_BASE__`), como o pacote 25 faz. Foi o pacote 26 que regrediu isso e forçou a escrita.
{
  const root = sandbox();
  const comRefLiteral = sourceSnapshot('v1');
  comRefLiteral.set('oimpresso.com.html', Buffer.from([
    '<link rel="stylesheet" href="styles.css">',
    '<link rel="stylesheet" href="_ds/ds-live/colors_and_type.css">',
    '<script src="financeiro-page.jsx"></script>',
    '<script src="superadmin-page.jsx"></script>',
    '<script src="officeimpresso-page.jsx"></script>',
  ].join('\n')));
  await rejects('MORDE: host com ref LITERAL a _ds/ é recusado (entrar exigiria escrever no DS)',
    () => applyBundleTransaction({ root, parts: partsFor(manifestFor(comRefLiteral), comRefLiteral) }),
    /grafo do estado-alvo incompleto/);
  rmSync(root, { recursive: true, force: true });

  // CONTROLE NEGATIVO: o MESMO lote, com o host resolvendo a base em runtime, ENTRA.
  // Sem ele, uma transação que recusasse tudo passaria no assert de cima.
  const root2 = sandbox();
  const comRuntime = sourceSnapshot('v1');
  await applyBundleTransaction({ root: root2, parts: partsFor(manifestFor(comRuntime), comRuntime) });
  check('CONTROLE: o mesmo lote com host sem ref literal ENTRA (não virou "recusa tudo")',
    existsSync(join(root2, 'prototipo-ui/cowork/Wagner/styles.css')));
  rmSync(root2, { recursive: true, force: true });

  // CONTROLE do FLAG: a rota LEGADA (`applyLegacySnapshotTransaction`, por onde o projeto DS
  // entrega o `_ds/**` com payload declarado) passa `permiteEscreverDs: true` e SEGUE escrevendo.
  // Sem este assert, o bloqueio mataria a única rota legítima de entrega do DS — foi o que
  // aconteceu na 1ª versão deste conserto, e só o `aplicar-payload.test.mjs` pegou.
  const root3 = sandbox();
  const comDs = sourceSnapshot('v1');
  comDs.set('oimpresso.com.html', Buffer.from([
    '<link rel="stylesheet" href="styles.css">',
    '<link rel="stylesheet" href="_ds/ds-live/colors_and_type.css">',
    '<script src="financeiro-page.jsx"></script>',
    '<script src="superadmin-page.jsx"></script>',
    '<script src="officeimpresso-page.jsx"></script>',
  ].join('\n')));
  await applyBundleTransaction({
    root: root3, parts: partsFor(manifestFor(comDs), comDs), permiteEscreverDs: true,
  });
  check('CONTROLE do FLAG: rota que DECLARA entrega de DS segue escrevendo o _ds/',
    existsSync(join(root3, 'prototipo-ui/design-system/colors_and_type.css')));
  rmSync(root3, { recursive: true, force: true });
}

// ── PR-A9: dsRequires — DS declarado e VERIFICADO (nunca escrito) ────────────────
// Os 5 aceites do plano, na ordem dele. A conferência é pura (recebe o leitor), então os 4
// primeiros rodam sem fs; o 5º (T5) é sobre a contagem nomear o arquivo.
{
  const contrato = {
    slug: 'ds-live',
    arquivos: [
      { path: '_ds_bundle.js', sha256: sha256(Buffer.from('bundle\n')) },
      { path: 'colors_and_type.css', sha256: sha256(Buffer.from('tokens\n')) },
      { path: 'assets/fonts/sans-400.woff2', sha256: sha256(Buffer.from('fonte\n')) },
    ],
  };
  const espelhoCompleto = (rel) => ({
    '_ds_bundle.js': Buffer.from('bundle\n'),
    'colors_and_type.css': Buffer.from('tokens\n'),
    'assets/fonts/sans-400.woff2': Buffer.from('fonte\n'),
  })[rel] ?? null;

  // 2. CONTROLE POSITIVO primeiro — sem ele, "recusa sempre" passaria no bite 1.
  const ok = conferirDsRequires(contrato, espelhoCompleto);
  check('A9 CONTROLE: DS completo ⇒ 3 de 3 conferem, 0 ausente, 0 divergente',
    ok.medido === true && ok.iguais === 3 && ok.ausentes.length === 0 && ok.divergentes.length === 0,
    JSON.stringify(ok));

  // 1. BITE ausência ⇒ o lote tem que ser recusado (aqui: lista o ausente nomeando path+sha).
  const semBundle = conferirDsRequires(contrato, (rel) => (rel === '_ds_bundle.js' ? null : espelhoCompleto(rel)));
  check('A9 BITE ausência: arquivo do DS faltando é ACUSADO com path e sha exigido',
    semBundle.ausentes.length === 1 && semBundle.ausentes[0].path === '_ds_bundle.js'
      && vereditoDsRequires(semBundle).join('\n').includes('⛔ AUSENTE  _ds_bundle.js'),
    JSON.stringify(semBundle));

  // 3. BITE divergência ⇒ RELATA e NÃO recusa (o espelho é o dono e pode estar à frente).
  const mutado = conferirDsRequires(contrato, (rel) => (rel === 'colors_and_type.css' ? Buffer.from('tokens-novos\n') : espelhoCompleto(rel)));
  const textoDiv = vereditoDsRequires(mutado).join('\n');
  check('A9 BITE divergência: RELATA, não acusa ausência, e diz que o espelho pode estar à frente',
    mutado.divergentes.length === 1 && mutado.ausentes.length === 0
      && /DIVERGE\s+colors_and_type\.css/.test(textoDiv) && /A FRENTE/.test(textoDiv),
    textoDiv);

  // 4. BITE `dsRequires` ausente (pacote legado) ⇒ NÃO MEDIDO, nunca "sem divergência" (LC-13).
  const legado = conferirDsRequires(null, espelhoCompleto);
  const textoLegado = vereditoDsRequires(legado).join('\n');
  check('A9 BITE legado: sem dsRequires o veredito é NAO MEDIDO (não "sem divergência")',
    legado.medido === false && /NAO MEDIDO/.test(textoLegado) && !/sem divergência/i.test(textoLegado),
    textoLegado);

  // 5. T5 — remover uma entrada faz a contagem cair NOMEANDO o arquivo que saiu.
  const menosUm = { ...contrato, arquivos: contrato.arquivos.filter((a) => a.path !== 'colors_and_type.css') };
  const r5 = conferirDsRequires(menosUm, espelhoCompleto);
  check('A9 T5: remover 1 entrada do contrato derruba o total de 3 para 2',
    ok.total === 3 && r5.total === 2 && !vereditoDsRequires(r5).join('\n').includes('colors_and_type.css'),
    `${ok.total} -> ${r5.total}`);
}

console.log(failures ? `\n✗ ${failures} falha(s)` : '\n✓ bundle v2: delta + staging + rollback + módulos + catraca lifecycle + dono do _ds (delete/escrita) + dsRequires provados');
process.exit(failures ? 1 : 0);
