// @ts-check
/**
 * Contrato do MANIFESTO v2 (`oimpresso-design-manifest/2`) — o que sobrou do transporte.
 *
 * Até 2026-10-23 este arquivo também validava as PARTES do bundle que vinham do Cowork. A ADR
 * 0438 tirou a aplicação de bundle (onda 2c-3), e com ela `validateBundleParts`, os digests de
 * lote e a troca de ref do DS. Fica o manifesto: o `state/active-bundle.json` ainda é o
 * denominador de telas do relatório de recibos (`bundle-transaction.mjs`), e ele precisa ser
 * validado ao ler e construído nas fixtures de teste.
 */
import { createHash } from 'node:crypto';
import { normalizePayloadPath } from './payload-dependency-graph.mjs';

export const MANIFEST_SCHEMA = 'oimpresso-design-manifest/2';
/**
 * FONTE ÚNICA de "que extensão é conteúdo do espelho Cowork".
 *
 * Por que aqui: até 2026-09-16 a mesma regra vivia em TRÊS cópias que já tinham drifado —
 * este contrato (com `json`/`php`), o `aplicar-payload.mjs` (sem os dois) e o `ESPELHO_EXTS`
 * do `importar-bundle.mjs` (sem imagem nem fonte). Medido no dia, contra o espelho versionado
 * (719 arquivos) e o manifesto ativo (695 fora de `_ds/`): **42 arquivos divergiam** —
 * 25 `.json` (os `contrato/*.contract.json`, que o gate `contrato-de-tela` e o
 * `visual-regression` consomem) e 17 `.php` (o corpo dos playbooks do `cowork-inbox/`).
 * Aceitos por duas rotas, recusados pela terceira — e no modo legado a recusa derruba o
 * LOTE INTEIRO, não só o arquivo. Unificado pela UNIÃO por decisão [W] (2026-09-16):
 * ninguém deixa de entrar, 42 param de ser recusados pela rota que os recusava.
 *
 * A lista é a fonte; a regex DERIVA dela. Sincronizar duas cópias à mão é o padrão que criou
 * este caso (§5 2026-08-02: consertar uma e a outra seguir errada).
 *
 * ⚠️ Isto NÃO governa `_ds/**` (é `preview-cache`, decidido por prefixo logo abaixo) nem o
 * `exportPlan` do `cowork-mirror-freshness` — aquele é um 4º dono, com `RE_BUILD_SOURCE`
 * próprio (sem `md`/`json`/`php`) e bite-test pinando decisão [W] de 2026-09-11. Igualá-lo
 * exige resolver a precedência entre aquela decisão e a 0398 D2 (2026-09-13); é do [W].
 *
 * `md` entrou em 2026-09-13 (decisão [W], ADR 0398 D2).
 */
export const ESPELHO_EXTENSOES = Object.freeze([
  'js', 'jsx', 'ts', 'tsx', 'mjs', 'cjs',
  'css', 'html', 'svg',
  'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico',
  'woff', 'woff2', 'ttf', 'otf', 'eot',
  'md', 'json', 'php',
]);
export const BUILD_SOURCE_RE = new RegExp(String.raw`\.(?:${ESPELHO_EXTENSOES.join('|')})$`, 'i');

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
  if (!BUILD_SOURCE_RE.test(rel)) {
    throw new Error(`arquivo fora do contrato build-only: ${rel} (documentos e artefatos de canon não são importados)`);
  }
  return 'cowork-source';
}

function normalizedFiles(files) {
  const seen = new Set();
  const sourceBySha = new Map();
  const out = files.map((file) => {
    const path = normalizePayloadPath(file.path);
    if (seen.has(path)) throw new Error(`path duplicado no manifesto: ${path}`);
    seen.add(path);
    const bytes = Number(file.bytes);
    if (!Number.isInteger(bytes) || bytes < 0) throw new Error(`bytes inválidos no manifesto: ${path}`);
    if (!/^[a-f0-9]{64}$/.test(String(file.sha256 || ''))) throw new Error(`sha256 inválido no manifesto: ${path}`);
    const sha = String(file.sha256);
    const role = roleForPath(path);
    if (role === 'cowork-source') {
      const original = sourceBySha.get(sha);
      if (original) throw new Error(`conteúdo duplicado no bundle: ${original} == ${path}`);
      sourceBySha.set(sha, path);
    }
    return { path, bytes, sha256: sha, role };
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

/** Normaliza o contrato de DS: paths POSIX, sha minusculo, ordem estavel (o `bundleId` e hash
 *  de JSON estavel — ordem instavel geraria id diferente pro mesmo conteudo). */
export function normalizeDsRequires(dsRequires) {
  if (!dsRequires || typeof dsRequires !== 'object') throw new Error('dsRequires invalido');
  const owner = String(dsRequires.owner || '').trim();
  if (!owner) throw new Error('dsRequires.owner vazio');
  // ⚠️ `slug` e OPCIONAL, e `null` e a forma LEGITIMA do Felipe — nao erro. Medido 2026-09-17:
  // `Felipe/venda-v3/index.html:38,39,64` carrega `_ds/colors_and_type.css` (sem slug), enquanto
  // o bind do Wagner carimba `_ds/<slug>/…`. Exigir slug reprovaria um dono inteiro.
  const slug = dsRequires.slug == null || String(dsRequires.slug).trim() === ''
    ? null
    : String(dsRequires.slug).trim();
  const arquivos = (dsRequires.arquivos || [])
    .map((item) => ({ path: normalizePayloadPath(item.path), sha256: String(item.sha256).toLowerCase() }))
    .sort((a, b) => a.path.localeCompare(b.path));
  return { owner, slug, arquivos };
}

/** Normaliza `transforms`: paths POSIX, sha minusculo, ordem estavel por path. */
export function normalizeTransforms(transforms) {
  if (!Array.isArray(transforms)) throw new Error('transforms: esperado array');
  return transforms
    .map((t) => {
      if (!t || typeof t.path !== 'string' || !t.path) throw new Error('transforms[].path invalido');
      if (t.regra !== 'ds-ref') throw new Error(`transforms[].regra desconhecida: ${t.regra}`);
      if (typeof t.de !== 'string' || !t.de || typeof t.para !== 'string' || !t.para) {
        throw new Error(`transforms[] de/para invalido: ${t.path}`);
      }
      if (!/^[a-f0-9]{64}$/.test(String(t.shaDepois || ''))) {
        throw new Error(`transforms[].shaDepois invalido: ${t.path}`);
      }
      return { path: normalizePayloadPath(t.path), regra: t.regra, de: t.de, para: t.para, shaDepois: String(t.shaDepois).toLowerCase() };
    })
    .sort((a, b) => a.path.localeCompare(b.path));
}

export function createManifest({ source, entry = 'oimpresso.com.html', files, missing = [], previous = null, generatedAt = new Date().toISOString(), mirrorScope = null, dsRequires = null, transforms = null }) {
  if (mirrorScope !== null && mirrorScope !== 'tree') throw new Error(`escopo de espelho inválido: ${mirrorScope}`);
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
    ...(mirrorScope ? { mirrorScope } : {}),
  };
  const bundleId = sha256(stableJson(identity));
  return {
    ...identity,
    bundleId,
    baseBundleId: previous?.bundleId || null,
    mode: previous ? 'delta' : 'snapshot',
    generatedAt,
    // PR-A9 — FORA da identidade, por decisao [W] (§D do pedido de 2026-09-17).
    // Classificacao: identidade = O QUE POUSA; `dsRequires` = PRE-CONDICAO de aterrissagem.
    // Metadado, ao lado de `mode`/`totals`/`changes`, com shape-check no `validateManifest`.
    // ⚠️ O motivo TECNICO citado no pedido ("quebra a cadeia de delta e forca snapshot dos 702")
    // foi MEDIDO e nao se sustenta: com o campo na identidade o pacote 27 real deu `mode=delta`,
    // `baseBundleId == ativo.bundleId`, delta de 0 arquivos. A cadeia usa o `baseBundleId`
    // DECLARADO, e o ativo nao muda. O que sustenta a decisao e a CLASSIFICACAO, nao aquele
    // efeito — e classificacao e do dono.
    ...(dsRequires ? { dsRequires: normalizeDsRequires(dsRequires) } : {}),
    // `transforms` — a REGRA de aterrissagem, tambem fora da identidade (mesma classificacao).
    // Cada item carrega `shaDepois`: o sha do arquivo DEPOIS da conversao, que e contra o que o
    // `--compare-bundle` comparava (modo removido em 2026-10-23, onda 2c-3). O campo segue no
    // manifesto porque os `active-bundle.json` já gravados o carregam e a validação os lê.
    ...(transforms ? { transforms: normalizeTransforms(transforms) } : {}),
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
  if (manifest.mirrorScope !== undefined && manifest.mirrorScope !== 'tree') throw new Error(`escopo de espelho inválido: ${manifest.mirrorScope}`);
  const files = normalizedFiles(manifest.files || []);
  const expectedIdentity = {
    schema: MANIFEST_SCHEMA,
    source: manifest.source,
    entry: normalizePayloadPath(manifest.entry),
    files,
    missing: [...new Set((manifest.missing || []).map(normalizePayloadPath))].sort(),
    ...(manifest.mirrorScope ? { mirrorScope: manifest.mirrorScope } : {}),
  };
  const expectedId = sha256(stableJson(expectedIdentity));
  if (manifest.bundleId !== expectedId) throw new Error(`bundleId divergente: declarado ${manifest.bundleId || '?'} · calculado ${expectedId}`);
  // PR-A9 — `dsRequires` fica FORA da identidade (decisão [W], §D), então NÃO entra no hash acima.
  // Mas ficar fora não é ficar SEM checagem: shape-check aqui. Sem isto, campo malformado só
  // apareceria lá na frente, na aplicação — e o pedido é explícito em não relaxar o validador
  // para campo desconhecido (porta do drift silencioso).
  if (manifest.dsRequires !== undefined && manifest.dsRequires !== null) {
    const d = manifest.dsRequires;
    if (typeof d !== 'object' || Array.isArray(d)) throw new Error('dsRequires: esperado objeto');
    if (!String(d.owner || '').trim()) throw new Error('dsRequires.owner vazio');
    if (d.slug !== null && d.slug !== undefined && !String(d.slug).trim()) throw new Error('dsRequires.slug vazio (use null para bind sem slug)');
    if (!Array.isArray(d.arquivos)) throw new Error('dsRequires.arquivos: esperado array');
    for (const item of d.arquivos) {
      if (!item || typeof item.path !== 'string' || !item.path) throw new Error('dsRequires.arquivos[].path invalido');
      if (!/^[a-f0-9]{64}$/.test(String(item.sha256 || ''))) throw new Error(`dsRequires.arquivos[].sha256 invalido: ${item.path}`);
    }
  }
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
