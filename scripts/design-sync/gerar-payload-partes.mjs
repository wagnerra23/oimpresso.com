#!/usr/bin/env node
// @ts-check
/**
 * gerar-payload-partes.mjs — emite snapshot/delta Design v2 em partes de até 256 KiB.
 *
 * O manifesto descreve o estado-alvo completo. Sem `--previous`, transporta um snapshot;
 * com o manifesto anterior, transporta somente bytes added/modified e declara deleted.
 * Arquivos grandes são divididos em chunks SHA-256 remontáveis pelo consumidor.
 *
 * ONDE RODA: na máquina que TEM os arquivos em disco (o lado do design/Cowork). NÃO roda do
 * lado do agente consumidor: lá o conteúdo chegaria por `DesignSync.get_file`, que entrega no
 * CONTEXTO do agente — e escrever de lá é transcrição, a classe que causou o STALE de
 * 2026-08-11. Aqui nenhum byte passa por prosa: readFileSync -> JSON.stringify -> writeFileSync.
 *
 * POR QUE EM PARTES: o consumidor busca o payload com `DesignSync.get_file`, que corta em
 * 256 KiB e devolve `"truncated": true`. Payload único de ~3,5 MB volta cortado e é inútil.
 *
 * FIDELIDADE: a parte 01 traz o manifesto-alvo; todas repetem identidade/base/quantidade.
 * O consumidor valida sequência, SHA-256 de manifesto, mudanças, chunks e grafo inteiro antes
 * de tocar nos destinos. O arquivo `bundle.manifest.json` é a base do próximo delta.
 *
 * Uso:
 *   node scripts/design-sync/gerar-payload-partes.mjs --root <dir-do-vivo> --out sync
 *   ... [--previous sync-anterior/bundle.manifest.json]
 *       [--entry oimpresso.com.html] [--cap 262144] [--chunk-bytes 131072]
 *
 * Do outro lado:
 *   node scripts/design-sync/aplicar-payload.mjs sync/payload.part*.json --require-complete-shell
 */
import { readFileSync, writeFileSync, existsSync, mkdirSync, statSync, unlinkSync } from 'node:fs';
import { join } from 'node:path';
import { payloadDependencyGraph, normalizePayloadPath } from './payload-dependency-graph.mjs';
import {
  BUNDLE_SCHEMA, changesDigest, createManifest, manifestDigest, sha256, validateManifest,
} from './bundle-contract.mjs';

const args = process.argv.slice(2);
const opt = (nome, padrao = null) => {
  const i = args.indexOf(nome);
  return i >= 0 && args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : padrao;
};
const ROOT = opt('--root', process.cwd());
const OUT = opt('--out', 'sync');
const ENTRY = opt('--entry', 'oimpresso.com.html');
const CAP = Number(opt('--cap', '262144'));
// Piso de PERSISTÊNCIA do consumidor (ver o bloco do empacotamento). 60 KiB fica acima do maior
// inline medido (41 KB) e abaixo do menor persistido (87 KB). `--piso 0` desliga o aviso.
const PISO = Number(opt('--piso', '61440'));
const CHUNK_BYTES = Number(opt('--chunk-bytes', '131072'));
const PREVIOUS = opt('--previous', null);
const EXCLUDES = args.reduce((acc, a, i) => (a === '--exclude' && args[i + 1] ? [...acc, args[i + 1]] : acc), []);

if (!existsSync(join(ROOT, ENTRY))) {
  console.error(`\u2717 entry nao encontrado: ${join(ROOT, ENTRY)}`);
  console.error(`  rode este script na maquina que TEM os arquivos em disco (lado do design).`);
  process.exit(2);
}
if (!Number.isInteger(CAP) || CAP < 4096 || !Number.isInteger(CHUNK_BYTES) || CHUNK_BYTES < 1) {
  console.error('✗ --cap/--chunk-bytes inválido');
  process.exit(2);
}

const BINARIO = /\.(woff2?|ttf|otf|eot|png|jpe?g|gif|webp|avif|ico|pdf|mp4|webm|zip)$/i;

/**
 * glob simples: `*` dentro de um segmento, `**` em qualquer profundidade.
 *
 * A ordem importa e foi errada na 1a versao: escapar a string INTEIRA antes de fatiar nao
 * escapa o `*` (ele nao esta na classe), entao o split por `**` nao casava nada e o RegExp
 * saia com `**` cru -> "Nothing to repeat". Fatia PRIMEIRO nos curingas, escapa DEPOIS so os
 * pedacos literais. Pego pelo teste com `--exclude '_ds/**\/_ds_bundle.js'`.
 */
function casaExclude(p) {
  const esc = (s) => s.replace(/[.+?^${}()|[\]\\]/g, '\\$&');
  return EXCLUDES.some((g) => {
    const re = new RegExp('^' + g.split('**').map((seg) => seg.split('*').map(esc).join('[^/]*')).join('.*') + '$');
    return re.test(p);
  });
}

// -- 1) FECHAMENTO TRANSITIVO a partir do entry ---------------------------------------------
// Usa o MESMO grafo do applier: manifesto DERIVADO, nunca lista curada. Se eu enumerasse a mao,
// o payload e a verificacao do outro lado usariam regua diferente — e a divergencia so apareceria
// no `--require-complete-shell`, depois de tudo pronto.
const lidos = new Map();
const ausentes = new Set();
const excluidos = new Set();

function carregar(rel) {
  if (lidos.has(rel) || ausentes.has(rel) || excluidos.has(rel)) return;
  if (casaExclude(rel)) { excluidos.add(rel); return; }
  const abs = join(ROOT, rel);
  if (!existsSync(abs) || !statSync(abs).isFile()) { ausentes.add(rel); return; }
  lidos.set(rel, readFileSync(abs));
}

carregar(ENTRY);
for (let passada = 0; passada < 40; passada++) {
  const grafo = payloadDependencyGraph([...lidos].map(([path, buf]) => ({
    path,
    binary: BINARIO.test(path),
    content: BINARIO.test(path) ? null : buf.toString('utf8'),
  })));
  const novos = grafo.missing.filter((r) => !ausentes.has(r) && !excluidos.has(r) && !lidos.has(r));
  if (!novos.length) break;
  novos.forEach(carregar);
}

// -- 2) MANIFESTO DO ESTADO-ALVO + DELTA ----------------------------------------------------
const missing = [...new Set([...ausentes, ...excluidos])].sort();
let previous = null;
if (PREVIOUS) {
  if (!existsSync(PREVIOUS)) { console.error(`✗ manifesto anterior não encontrado: ${PREVIOUS}`); process.exit(2); }
  try { previous = validateManifest(JSON.parse(readFileSync(PREVIOUS, 'utf8'))); }
  catch (error) { console.error(`✗ manifesto anterior inválido: ${error.message}`); process.exit(2); }
}

const sourceFiles = [...lidos]
  .map(([path, buffer]) => ({ path: normalizePayloadPath(path), buffer }))
  .sort((a, b) => a.path.localeCompare(b.path));
const generatedAt = new Date().toISOString();
const manifest = createManifest({
  source: `cowork:${ENTRY}`,
  entry: ENTRY,
  files: sourceFiles.map((file) => ({ path: file.path, bytes: file.buffer.length, sha256: sha256(file.buffer) })),
  missing,
  previous,
  generatedAt,
});

const changed = new Set([...manifest.changes.added, ...manifest.changes.modified]);
const chunks = [];
for (const file of sourceFiles.filter((item) => changed.has(item.path))) {
  const count = Math.max(1, Math.ceil(file.buffer.length / CHUNK_BYTES));
  for (let index = 0; index < count; index++) {
    const offset = index * CHUNK_BYTES;
    const buffer = file.buffer.subarray(offset, Math.min(file.buffer.length, offset + CHUNK_BYTES));
    const chunk = {
      path: file.path,
      index: index + 1,
      count,
      offset,
      bytes: buffer.length,
      sha256: sha256(buffer),
      content: buffer.toString('base64'),
    };
    chunks.push({ chunk, custo: Buffer.byteLength(JSON.stringify(chunk), 'utf8') + 1 });
  }
}

// -- 3) EMPACOTA CHUNKS; arquivo grande deixa de ser teto -----------------------------------
const coreBase = {
  id: manifest.bundleId,
  baseId: manifest.baseBundleId,
  mode: manifest.mode,
  source: manifest.source,
  generatedAt: manifest.generatedAt,
  entry: manifest.entry,
  parts: 0,
  files: manifest.totals.files,
  bytes: manifest.totals.bytes,
  manifestSha256: manifestDigest(manifest),
  changesSha256: changesDigest(manifest.changes),
};
// A reserva TEM de espelhar o envelope REAL montado lá embaixo, campo por campo. A versão
// anterior omitia `chunkCount`, `fileCount`, `totalBytes` e `missing` — ~60 bytes contra uma
// margem de +32. Resultado medido em 2026-08-24: `✗ parte 5 excede o cap depois do envelope:
// 262165 > 262144` — estouro de 21 bytes, e o gerador saía rc=2 DEPOIS de já ter escrito o
// `bundle.manifest.json`. Quem não conferisse o exit code (foi o meu caso) aplicava um bundle
// TRUNCADO: 242 de 247 arquivos, com os 7 do CRM/impressão fora do espelho e o manifesto
// declarando `missing: []`. Os placeholders são propositalmente largos (99999) — reserva a
// mais custa bytes, reserva a menos custa um bundle incompleto que se apresenta como completo.
const RESERVA_CAMPOS = { chunkCount: 99999, fileCount: 99999, totalBytes: 9999999999, missing };
const firstReserve = Buffer.byteLength(JSON.stringify({
  schema: BUNDLE_SCHEMA, bundle: { ...coreBase, parts: 9999 }, part: 1, parts: 9999,
  ...RESERVA_CAMPOS, targetManifest: manifest, chunks: [],
}), 'utf8') + 64;
const otherReserve = Buffer.byteLength(JSON.stringify({
  schema: BUNDLE_SCHEMA, bundle: { ...coreBase, parts: 9999 }, part: 9999, parts: 9999,
  ...RESERVA_CAMPOS, chunks: [],
}), 'utf8') + 64;
if (firstReserve > CAP) {
  console.error(`✗ targetManifest sozinho excede o cap: ${firstReserve} > ${CAP} bytes`);
  process.exit(2);
}

const lotes = [[]];
let used = firstReserve;
for (const item of chunks) {
  const reserve = lotes.length === 1 ? firstReserve : otherReserve;
  if (item.custo + otherReserve > CAP) {
    console.error(`✗ chunk não cabe no cap; reduza --chunk-bytes (${item.chunk.path}#${item.chunk.index})`);
    process.exit(2);
  }
  if (used + item.custo > CAP) {
    lotes.push([]);
    used = otherReserve;
  } else if (!lotes.at(-1).length) used = reserve;
  lotes.at(-1).push(item);
  used += item.custo;
}

if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true });
writeFileSync(join(OUT, 'bundle.manifest.json'), JSON.stringify(manifest, null, 2) + '\n');
const largura = Math.max(2, String(lotes.length).length);
const tamanhosEscritos = [];

function padForTransport(envelope) {
  let text = JSON.stringify(envelope);
  let bytes = Buffer.byteLength(text, 'utf8');
  if (PISO > 0 && PISO <= CAP && bytes < PISO) {
    envelope.transportPadding = ' '.repeat(Math.max(0, PISO - bytes));
    text = JSON.stringify(envelope);
    bytes = Buffer.byteLength(text, 'utf8');
    if (bytes < PISO) {
      envelope.transportPadding += ' '.repeat(PISO - bytes);
      text = JSON.stringify(envelope);
      bytes = Buffer.byteLength(text, 'utf8');
    }
  }
  return { text, bytes };
}

lotes.forEach((lote, index) => {
  const bundle = { ...coreBase, parts: lotes.length };
  const envelope = {
    schema: BUNDLE_SCHEMA,
    bundle,
    part: index + 1,
    parts: lotes.length,
    chunkCount: lote.length,
    fileCount: new Set(lote.map((item) => item.chunk.path)).size,
    totalBytes: lote.reduce((sum, item) => sum + item.chunk.bytes, 0),
    missing,
    ...(index === 0 ? { targetManifest: manifest } : {}),
    chunks: lote.map((item) => item.chunk),
  };
  const { text, bytes } = padForTransport(envelope);
  if (bytes > CAP) {
    console.error(`✗ parte ${index + 1} excede o cap depois do envelope: ${bytes} > ${CAP}`);
    // O manifesto já foi escrito ANTES deste loop. Abortar deixando-o no disco entrega um
    // artefato que PARECE um bundle pronto — e o consumidor seguinte aplica um estado-alvo
    // truncado sem saber. Foi exatamente assim que 7 arquivos ficaram fora do espelho em
    // 2026-08-24. Falha não deixa recibo: limpa o que escreveu.
    for (const [arq] of tamanhosEscritos) { try { unlinkSync(arq); } catch { /* já não existe */ } }
    try { unlinkSync(join(OUT, 'bundle.manifest.json')); } catch { /* idem */ }
    console.error('  (manifesto e partes parciais REMOVIDOS — bundle incompleto não vira artefato)');
    process.exit(2);
  }
  const nome = join(OUT, `payload.part${String(index + 1).padStart(largura, '0')}.json`);
  writeFileSync(nome, text);
  tamanhosEscritos.push([nome, bytes]);
  console.log(`  ok ${nome} — ${String(lote.length).padStart(3)} chunk(s) · ${(bytes / 1024).toFixed(1)} KiB`);
});

const deltaBytes = [...changed].reduce((sum, path) => sum + sourceFiles.find((file) => file.path === path).buffer.length, 0);
console.log(`\n  BUNDLE v2: ${manifest.bundleId}`);
console.log(`  MODO: ${manifest.mode}${manifest.baseBundleId ? ` · base ${manifest.baseBundleId}` : ''}`);
console.log(`  ESTADO-ALVO: ${manifest.totals.files} arquivo(s) · ${(manifest.totals.bytes / 1048576).toFixed(2)} MB`);
console.log(`  DELTA: +${manifest.changes.added.length} ~${manifest.changes.modified.length} -${manifest.changes.deleted.length} =${manifest.changes.unchanged} · ${(deltaBytes / 1024).toFixed(1)} KiB baixáveis`);
console.log(`  PARTES: ${lotes.length} · CHUNKS: ${chunks.length} · manifesto: ${join(OUT, 'bundle.manifest.json')}`);

if (PISO > CAP) console.log(`\n  AVISO: piso ${PISO} > cap ${CAP}; padding de transporte impossível.`);
const pequenas = tamanhosEscritos.filter(([, bytes]) => PISO > 0 && bytes < PISO);
if (pequenas.length) console.log(`  AVISO: ${pequenas.length} parte(s) abaixo do piso de persistência.`);
if (excluidos.size) console.log(`  excluídos por --exclude: ${[...excluidos].join(', ')}`);
if (missing.length) {
  console.log(`  BLOQUEADO: missing (${missing.length}): ${missing.join(', ')}`);
  console.log('  O consumidor recusará o lote inteiro; o manifesto foi emitido para diagnóstico.');
} else console.log('  missing: [] — o grafo do shell fecha.');
console.log(`\n  aplicar:\n    node scripts/design-sync/aplicar-payload.mjs ${OUT}/payload.part*.json --require-complete-shell\n`);
