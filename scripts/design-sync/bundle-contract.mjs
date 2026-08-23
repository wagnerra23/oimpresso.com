// @ts-check
/**
 * Contrato v2 do transporte Design → git.
 *
 * O manifesto descreve o ESTADO-ALVO inteiro; as partes carregam somente os bytes novos ou
 * modificados. Cada parte repete a identidade do lote e a quantidade total de partes, portanto
 * qualquer subconjunto (inclusive sem a part01) é recusado antes de tocar o espelho.
 */
import { createHash } from 'node:crypto';
import { normalizePayloadPath } from './payload-dependency-graph.mjs';

export const MANIFEST_SCHEMA = 'oimpresso-design-manifest/2';
export const BUNDLE_SCHEMA = 'oimpresso-design-bundle/2';

export function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

export function stableJson(value) {
  if (Array.isArray(value)) return `[${value.map(stableJson).join(',')}]`;
  if (value && typeof value === 'object') {
    return `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${stableJson(value[key])}`).join(',')}}`;
  }
  return JSON.stringify(value);
}

export function roleForPath(path) {
  const rel = normalizePayloadPath(path);
  if (rel.startsWith('_ds/')) return 'preview-cache';
  if (rel.toLowerCase().endsWith('.md')) return 'design-doc';
  return 'cowork-source';
}

function normalizedFiles(files) {
  const seen = new Set();
  const out = files.map((file) => {
    const path = normalizePayloadPath(file.path);
    if (seen.has(path)) throw new Error(`path duplicado no manifesto: ${path}`);
    seen.add(path);
    const bytes = Number(file.bytes);
    if (!Number.isInteger(bytes) || bytes < 0) throw new Error(`bytes inválidos no manifesto: ${path}`);
    if (!/^[a-f0-9]{64}$/.test(String(file.sha256 || ''))) throw new Error(`sha256 inválido no manifesto: ${path}`);
    return { path, bytes, sha256: String(file.sha256), role: roleForPath(path) };
  });
  return out.sort((a, b) => a.path.localeCompare(b.path));
}

export function diffManifests(previous, currentFiles) {
  const before = new Map((previous?.files || []).map((f) => [f.path, f]));
  const after = new Map(currentFiles.map((f) => [f.path, f]));
  const added = [], modified = [], deleted = [], unchanged = [];
  for (const file of currentFiles) {
    const old = before.get(file.path);
    if (!old) added.push(file.path);
    else if (old.sha256 !== file.sha256 || old.bytes !== file.bytes) modified.push(file.path);
    else unchanged.push(file.path);
  }
  for (const path of before.keys()) if (!after.has(path)) deleted.push(path);
  return { added, modified, deleted, unchanged };
}

export function createManifest({ source, entry = 'oimpresso.com.html', files, missing = [], previous = null, generatedAt = new Date().toISOString() }) {
  const list = normalizedFiles(files);
  const cleanMissing = [...new Set(missing.map(normalizePayloadPath))].sort();
  if (previous) validateManifest(previous);
  const changes = diffManifests(previous, list);
  const identity = {
    schema: MANIFEST_SCHEMA,
    source,
    entry: normalizePayloadPath(entry),
    files: list,
    missing: cleanMissing,
  };
  const bundleId = sha256(stableJson(identity));
  return {
    ...identity,
    bundleId,
    baseBundleId: previous?.bundleId || null,
    mode: previous ? 'delta' : 'snapshot',
    generatedAt,
    totals: {
      files: list.length,
      bytes: list.reduce((sum, file) => sum + file.bytes, 0),
    },
    changes: {
      added: changes.added,
      modified: changes.modified,
      deleted: changes.deleted,
      unchanged: changes.unchanged.length,
    },
  };
}

export function validateManifest(manifest) {
  if (!manifest || manifest.schema !== MANIFEST_SCHEMA) throw new Error(`schema de manifesto inválido: ${manifest?.schema || 'ausente'}`);
  const files = normalizedFiles(manifest.files || []);
  const expectedIdentity = {
    schema: MANIFEST_SCHEMA,
    source: manifest.source,
    entry: normalizePayloadPath(manifest.entry),
    files,
    missing: [...new Set((manifest.missing || []).map(normalizePayloadPath))].sort(),
  };
  const expectedId = sha256(stableJson(expectedIdentity));
  if (manifest.bundleId !== expectedId) throw new Error(`bundleId divergente: declarado ${manifest.bundleId || '?'} · calculado ${expectedId}`);
  if (!['snapshot', 'delta'].includes(manifest.mode)) throw new Error(`modo inválido: ${manifest.mode}`);
  if (manifest.mode === 'snapshot' && manifest.baseBundleId) throw new Error('snapshot não pode declarar baseBundleId');
  if (manifest.mode === 'delta' && !/^[a-f0-9]{64}$/.test(String(manifest.baseBundleId || ''))) throw new Error('delta sem baseBundleId válido');
  const totals = manifest.totals || {};
  if (totals.files !== files.length || totals.bytes !== files.reduce((n, f) => n + f.bytes, 0)) {
    throw new Error('totais do manifesto não batem com files[]');
  }
  for (const key of ['added', 'modified', 'deleted']) {
    if (!Array.isArray(manifest.changes?.[key])) throw new Error(`changes.${key} ausente`);
    const normalized = manifest.changes[key].map(normalizePayloadPath);
    if (new Set(normalized).size !== normalized.length) throw new Error(`changes.${key} contém duplicata`);
  }
  return { ...manifest, files };
}

export function manifestDigest(manifest) {
  return sha256(stableJson(manifest));
}

export function changesDigest(changes) {
  return sha256(stableJson(changes));
}

function sameList(a, b) {
  return a.length === b.length && a.every((value, index) => value === b[index]);
}

export function validateBundleParts(parts, previous = null) {
  if (!Array.isArray(parts) || !parts.length) throw new Error('nenhuma parte recebida');
  if (parts.some((part) => part?.schema !== BUNDLE_SCHEMA)) throw new Error('lote mistura schema v2 com payload legado');
  const firstCore = stableJson(parts[0].bundle);
  if (parts.some((part) => stableJson(part.bundle) !== firstCore)) throw new Error('partes pertencem a bundles diferentes');
  const core = parts[0].bundle || {};
  const total = Number(core.parts);
  if (!Number.isInteger(total) || total < 1) throw new Error('bundle.parts inválido');
  if (parts.length !== total) throw new Error(`lote incompleto: recebeu ${parts.length}/${total} partes`);
  const indices = parts.map((part) => Number(part.part)).sort((a, b) => a - b);
  const expected = Array.from({ length: total }, (_, i) => i + 1);
  if (!sameList(indices, expected)) throw new Error(`sequência de partes inválida: recebeu [${indices.join(',')}], esperava [${expected.join(',')}]`);
  if (new Set(parts.map((part) => part.part)).size !== parts.length) throw new Error('parte duplicada');
  if (parts.some((part) => part.parts !== total)) throw new Error('campo parts diverge entre envelopes');

  const partOne = parts.find((part) => part.part === 1);
  if (!partOne?.targetManifest) throw new Error('part01 sem targetManifest');
  if (parts.some((part) => part.part !== 1 && part.targetManifest)) throw new Error('targetManifest deve existir somente na part01');
  const manifest = validateManifest(partOne.targetManifest);
  if (core.id !== manifest.bundleId) throw new Error('bundle.id não bate com targetManifest.bundleId');
  if (core.baseId !== manifest.baseBundleId) throw new Error('bundle.baseId não bate com targetManifest.baseBundleId');
  if (core.mode !== manifest.mode) throw new Error('bundle.mode não bate com targetManifest.mode');
  if (core.manifestSha256 !== manifestDigest(manifest)) throw new Error('manifestSha256 divergente');
  if (core.changesSha256 !== changesDigest(manifest.changes)) throw new Error('changesSha256 divergente');
  if (core.files !== manifest.totals.files || core.bytes !== manifest.totals.bytes) throw new Error('totais repetidos no bundle divergem do manifesto');
  if ((manifest.missing || []).length) throw new Error(`manifesto incompleto: ${manifest.missing.length} ausente(s): ${manifest.missing.join(', ')}`);

  if (manifest.mode === 'delta') {
    if (!previous) throw new Error(`delta exige estado-base ${manifest.baseBundleId}, mas nenhum estado ativo existe`);
    const validatedPrevious = validateManifest(previous);
    if (validatedPrevious.bundleId !== manifest.baseBundleId) throw new Error(`base divergente: ativo ${validatedPrevious.bundleId} · delta exige ${manifest.baseBundleId}`);
    const expectedChanges = diffManifests(validatedPrevious, manifest.files);
    for (const key of ['added', 'modified', 'deleted']) {
      if (!sameList(expectedChanges[key], manifest.changes[key])) throw new Error(`changes.${key} não corresponde ao diff base→alvo`);
    }
    if (expectedChanges.unchanged.length !== manifest.changes.unchanged) throw new Error('changes.unchanged não corresponde ao diff base→alvo');
  } else {
    const allPaths = manifest.files.map((file) => file.path);
    if (!sameList(allPaths, manifest.changes.added) || manifest.changes.modified.length || manifest.changes.deleted.length || manifest.changes.unchanged !== 0) {
      throw new Error('snapshot precisa declarar todos os arquivos como added');
    }
  }

  const chunks = parts.flatMap((part) => Array.isArray(part.chunks) ? part.chunks : []);
  const changedPaths = [...manifest.changes.added, ...manifest.changes.modified].sort();
  const grouped = new Map();
  for (const chunk of chunks) {
    const path = normalizePayloadPath(chunk.path);
    if (!changedPaths.includes(path)) throw new Error(`chunk extra fora do delta: ${path}`);
    const compact = String(chunk.content || '').replace(/\s+/g, '');
    if ((!compact && chunk.bytes !== 0) || !/^[A-Za-z0-9+/]*={0,2}$/.test(compact) || compact.length % 4 !== 0) throw new Error(`base64 inválido: ${path}#${chunk.index}`);
    const bytes = Buffer.from(compact, 'base64');
    if (bytes.length !== chunk.bytes) throw new Error(`bytes do chunk divergem: ${path}#${chunk.index}`);
    if (sha256(bytes) !== chunk.sha256) throw new Error(`sha256 do chunk diverge: ${path}#${chunk.index}`);
    const list = grouped.get(path) || [];
    list.push({ ...chunk, path, decoded: bytes });
    grouped.set(path, list);
  }

  if (!sameList([...grouped.keys()].sort(), changedPaths)) throw new Error(`conteúdo do delta incompleto: recebeu [${[...grouped.keys()].sort().join(',')}], esperava [${changedPaths.join(',')}]`);
  const buffers = new Map();
  const targetByPath = new Map(manifest.files.map((file) => [file.path, file]));
  for (const path of changedPaths) {
    const list = grouped.get(path).sort((a, b) => a.index - b.index);
    const count = list[0]?.count;
    if (!Number.isInteger(count) || count < 1 || list.length !== count) throw new Error(`chunks incompletos: ${path} (${list.length}/${count || '?'})`);
    if (!sameList(list.map((chunk) => chunk.index), Array.from({ length: count }, (_, i) => i + 1))) throw new Error(`sequência de chunks inválida: ${path}`);
    let offset = 0;
    for (const chunk of list) {
      if (chunk.offset !== offset || chunk.count !== count) throw new Error(`offset/count de chunk inválido: ${path}#${chunk.index}`);
      offset += chunk.bytes;
    }
    const buffer = Buffer.concat(list.map((chunk) => chunk.decoded));
    const target = targetByPath.get(path);
    if (!target || buffer.length !== target.bytes || sha256(buffer) !== target.sha256) throw new Error(`arquivo remontado diverge do manifesto: ${path}`);
    buffers.set(path, buffer);
  }
  return { manifest, buffers };
}
