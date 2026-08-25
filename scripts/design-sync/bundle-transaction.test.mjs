#!/usr/bin/env node
// @ts-check
/** Bite/release do transporte v2: sequência, delta, SHA, staging, rollback e plano modular. */
import {
  existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import {
  BUNDLE_SCHEMA, changesDigest, createManifest, manifestDigest, sha256,
} from './bundle-contract.mjs';
import { applyBundleTransaction } from './bundle-transaction.mjs';

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
  return root;
}

function sourceSnapshot(label = 'v1') {
  return new Map([
    ['oimpresso.com.html', Buffer.from([
      '<link rel="stylesheet" href="styles.css">',
      '<link rel="stylesheet" href="_ds/ds-live/colors_and_type.css">',
      '<script src="financeiro-page.jsx"></script>',
      '<script src="superadmin-page.jsx"></script>',
      '<script src="officeimpresso-page.jsx"></script>',
    ].join('\n'))],
    ['styles.css', Buffer.from(`:root{--fixture:${label}}\n`)],
    ['financeiro-page.jsx', Buffer.from(`export const Financeiro='${label}';\n`)],
    ['superadmin-page.jsx', Buffer.from(`export const Superadmin='${label}';\n`)],
    ['officeimpresso-page.jsx', Buffer.from(`export const Officeimpresso='${label}';\n`)],
    ['_ds/ds-live/colors_and_type.css', Buffer.from(`:root{--ds:${label}}\n`)],
    ['cowork-inbox/LEIAME.md', Buffer.from(`# ${label}\n`)],
  ]);
}

function manifestFor(buffers, previous = null) {
  return createManifest({
    source: 'cowork:fixture',
    files: [...buffers].map(([path, buffer]) => ({ path, bytes: buffer.length, sha256: sha256(buffer) })),
    previous,
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
  check('snapshot promove o espelho', readFileSync(join(root, 'prototipo-ui/cowork/styles.css'), 'utf8').includes('v1'));
  check('_ds pousa apenas no runtime derivado',
    existsSync(join(root, 'scripts/design-sync/mirror-snapshot/colors_and_type.css')) &&
    !existsSync(join(root, 'prototipo-ui/cowork/_ds')));
  check('documento pousa fora do espelho build-only', existsSync(join(root, 'prototipo-ui/design-docs/cowork-inbox/LEIAME.md')));
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
  check('recepção do espelho não perde fonte órfã', existsSync(join(root, 'prototipo-ui/cowork/mistero-page.jsx')));
  check('relatório marca destino desconhecido como blocked', result.report.screens.some((screen) => screen.source === 'mistero-page.jsx' && screen.applicationState === 'blocked'));
  let code = 0, output = '';
  try { execFileSync(process.execPath, [STATUS, '--root', root, '--check-mapping'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }); }
  catch (error) { code = error.status ?? 1; output = String(error.stderr || ''); }
  check('CLI fail-closed reprova aplicação com órfão', code === 1 && /sem destino inequívoco/.test(output), `code=${code} ${output}`);
}

console.log('\n=== evidência aplicada/testada é durável e se invalida quando a fonte muda ===');
{
  const root = sandbox();
  const before = sourceSnapshot('v1');
  const m1 = manifestFor(before);
  await applyBundleTransaction({ root, parts: partsFor(m1, before) });
  const target = 'Modules/Officeimpresso/Resources/js/Pages/officeimpresso/Logs/Index.tsx';
  execFileSync(process.execPath, [
    STATUS, '--root', root,
    '--mark-applied', 'officeimpresso-page.jsx',
    '--target', target,
    '--evidence', 'PR-fixture',
    '--test', 'pest Officeimpresso',
  ], { encoding: 'utf8' });
  const recorded = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  const applied = recorded.screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('hashes atuais + evidência marcam aplicação', applied.applicationState === 'applied');
  check('teste registrado aparece no relatório', applied.tested && applied.applicationEvidence.tests.includes('pest Officeimpresso'));
  check('ledger durável fica fora de _ds', existsSync(join(root, 'scripts/design-sync/state/applications.json')));

  const after = sourceSnapshot('v2');
  const m2 = manifestFor(after, m1);
  await applyBundleTransaction({ root, parts: partsFor(m2, after) });
  const invalidated = JSON.parse(readFileSync(join(root, 'scripts/design-sync/state/application-report.json'), 'utf8'))
    .screens.find((screen) => screen.source === 'officeimpresso-page.jsx' && screen.target === target);
  check('mudança no hash da fonte invalida aplicação anterior', invalidated.applicationState === 'pending' && !invalidated.tested);
}

console.log('\n=== delta baixa só mudanças, remove owned e preserva unchanged ===');
{
  const root = sandbox();
  const before = sourceSnapshot('v1');
  const m1 = manifestFor(before);
  await applyBundleTransaction({ root, parts: partsFor(m1, before) });
  put(root, 'prototipo-ui/cowork/unmanaged-local.txt', 'preservar\n');

  const after = sourceSnapshot('v2');
  after.set('nao-referenciado.js', Buffer.from('export const novo=true;\n'));
  after.delete('cowork-inbox/LEIAME.md');
  // Mantém o Officeimpresso byte-idêntico para provar que não viaja no delta.
  after.set('officeimpresso-page.jsx', before.get('officeimpresso-page.jsx'));
  const m2 = manifestFor(after, m1);
  const parts = partsFor(m2, after);
  const transported = parts.flatMap((part) => part.chunks.map((chunk) => chunk.path));
  check('unchanged não viaja', !transported.includes('officeimpresso-page.jsx'));
  check('deleted não viaja como conteúdo', !transported.includes('cowork-inbox/LEIAME.md'));
  await applyBundleTransaction({ root, parts });
  check('modified foi promovido', readFileSync(join(root, 'prototipo-ui/cowork/styles.css'), 'utf8').includes('v2'));
  check('deleted owned foi removido', !existsSync(join(root, 'prototipo-ui/design-docs/cowork-inbox/LEIAME.md')));
  check('arquivo fora do manifesto é preservado', readFileSync(join(root, 'prototipo-ui/cowork/unmanaged-local.txt'), 'utf8') === 'preservar\n');
}

console.log('\n=== fail-closed: partes, base, hash, path e dry-run ===');
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
  check('corrupção não toca o destino', !existsSync(join(root, 'prototipo-ui/cowork/oimpresso.com.html')));

  await rejects('path traversal é recusado no manifesto', async () => {
    const evil = new Map(buffers);
    evil.set('../fora.txt', Buffer.from('x'));
    manifestFor(evil);
  }, /caminho inseguro/);

  const dry = await applyBundleTransaction({ root, parts, dry: true });
  check('dry-run produz plano', dry.report.screens.length === 5);
  check('dry-run não promove nem estado nem espelho',
    !existsSync(join(root, 'scripts/design-sync/state/active-bundle.json')) &&
    !existsSync(join(root, 'prototipo-ui/cowork/oimpresso.com.html')));
}

console.log('\n=== base divergente e rollback durante promoção ===');
{
  const root = sandbox();
  const before = sourceSnapshot('v1');
  const m1 = manifestFor(before);
  await applyBundleTransaction({ root, parts: partsFor(m1, before) });
  const originalSource = readFileSync(join(root, 'prototipo-ui/cowork/styles.css'));
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
  check('rollback restaura bytes do espelho', readFileSync(join(root, 'prototipo-ui/cowork/styles.css')).equals(originalSource));
  check('rollback restaura estado ativo', readFileSync(join(root, 'scripts/design-sync/state/active-bundle.json')).equals(originalState));
  const leftovers = [
    ...readdirSync(join(root, 'prototipo-ui')).filter((name) => /\.(?:stage|backup)-/.test(name)),
    ...readdirSync(join(root, 'scripts/design-sync')).filter((name) => /\.(?:stage|backup)-/.test(name)),
  ];
  check('rollback limpa staging/backup', leftovers.length === 0, leftovers.join(','));
}

console.log(failures ? `\n✗ ${failures} falha(s)` : '\n✓ bundle v2: delta + staging + rollback + módulos provados');
process.exit(failures ? 1 : 0);
