// @ts-check
/**
 * Grafo estático das dependências LOCAIS alcançáveis a partir de um shell HTML.
 *
 * Não tenta adivinhar dependências de runtime (fetch/API, pacote npm, URL externa ou valor
 * calculado). Cobre o que o browser/bundler declara como arquivo: HTML src/link, CSS
 * @import/url e JS import/export/import()/require()/new URL(..., import.meta.url), incluindo
 * src/poster literais em JSX. O fechamento é iterativo: arquivos alcançados também são lidos.
 */
import { posix } from 'node:path';

const TEXT_EXT = /\.(?:html?|css|m?js|jsx|ts|tsx)$/i;
const EXTERNAL = /^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i;

export function normalizePayloadPath(value) {
  const raw = String(value || '').replace(/\\/g, '/').replace(/^\.\//, '');
  const normalized = posix.normalize(raw).replace(/^\.\//, '');
  if (!normalized || normalized === '.' || normalized.startsWith('../') || normalized.startsWith('/')) {
    throw new Error(`caminho inseguro no payload: "${value}"`);
  }
  return normalized;
}

function cssRefs(text) {
  const refs = [];
  for (const re of [
    /@import\s+(?:url\(\s*)?["']?([^"')\s;]+)["']?\s*\)?/gi,
    /url\(\s*["']?([^"')]+?)["']?\s*\)/gi,
  ]) {
    re.lastIndex = 0;
    let m;
    while ((m = re.exec(text))) refs.push(m[1]);
  }
  return refs;
}

function jsRefs(text) {
  const refs = [];
  for (const re of [
    /\b(?:import|export)\s+(?:[^"']*?\s+from\s*)?["']([^"']+)["']/g,
    /\bimport\s*\(\s*["']([^"']+)["']\s*\)/g,
    /\brequire\s*\(\s*["']([^"']+)["']\s*\)/g,
    /\bnew\s+URL\s*\(\s*["']([^"']+)["']\s*,\s*import\.meta\.url\s*\)/g,
    /\b(?:src|poster)\s*=\s*["']([^"']+)["']/g,
  ]) {
    re.lastIndex = 0;
    let m;
    while ((m = re.exec(text))) refs.push(m[1]);
  }
  return refs;
}

function htmlRefs(text) {
  const refs = [];
  for (const re of [
    /<(?:script|img|iframe|audio|video|source|track|embed|input)\b[^>]*?\b(?:src|poster)=["']([^"']+)["']/gi,
    /<link\b[^>]*?\bhref=["']([^"']+)["']/gi,
    /<(?:img|source)\b[^>]*?\bsrcset=["']([^"']+)["']/gi,
  ]) {
    re.lastIndex = 0;
    let m;
    while ((m = re.exec(text))) {
      if (/srcset=/i.test(m[0])) {
        for (const candidate of m[1].split(',')) refs.push(candidate.trim().split(/\s+/)[0]);
      } else refs.push(m[1]);
    }
  }
  for (const m of text.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/gi)) refs.push(...cssRefs(m[1]));
  for (const m of text.matchAll(/\bstyle=["']([^"']+)["']/gi)) refs.push(...cssRefs(m[1]));
  for (const m of text.matchAll(/<script\b(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/gi)) refs.push(...jsRefs(m[1]));
  return refs;
}

/** Referências cruas declaradas por um arquivo textual. */
export function rawDependencyRefs(path, content) {
  if (typeof content !== 'string' || !TEXT_EXT.test(path)) return [];
  if (/\.html?$/i.test(path)) return htmlRefs(content);
  if (/\.css$/i.test(path)) return cssRefs(content);
  return jsRefs(content);
}

function resolveRef(from, raw, available) {
  let ref = String(raw || '').trim();
  if (!ref || EXTERNAL.test(ref)) return { external: true, raw: ref };
  ref = ref.split('#')[0].split('?')[0].trim();
  if (!ref) return { external: true, raw };

  // Em JS, nome nu e alias são pacote/bundler, não arquivo do projeto.
  const fromJs = /\.(?:m?js|jsx|ts|tsx)$/i.test(from);
  if (fromJs && !ref.startsWith('.') && !ref.startsWith('/')) {
    return { external: true, raw: ref };
  }

  const joined = ref.startsWith('/') ? ref.slice(1) : posix.join(posix.dirname(from), ref);
  let base;
  try { base = normalizePayloadPath(joined); }
  catch { return { unsafe: true, raw: ref }; }

  const candidates = posix.extname(base)
    ? [base]
    : [base, ...['.js', '.jsx', '.ts', '.tsx', '.css', '.json'].map((e) => base + e),
        ...['index.js', 'index.jsx', 'index.ts', 'index.tsx'].map((f) => posix.join(base, f))];
  return { path: candidates.find((c) => available.has(c)) || candidates[0], raw: ref };
}

/**
 * Fecha o grafo a partir de `entry`. `files` aceita `{path, content, binary?}`; binários são
 * folhas. Um arquivo presente mas inalcançável é relatado, não usado para maquiar dependência.
 */
export function payloadDependencyGraph(files, { entry = 'oimpresso.com.html' } = {}) {
  const byPath = new Map();
  const duplicates = [];
  for (const file of files || []) {
    const path = normalizePayloadPath(file.path);
    if (byPath.has(path)) duplicates.push(path);
    byPath.set(path, { ...file, path });
  }
  const entryPath = normalizePayloadPath(entry);
  const available = new Set(byPath.keys());
  const queue = [entryPath], visited = new Set(), edges = [], missing = new Set(), unsafe = [];
  const external = [];

  while (queue.length) {
    const from = queue.shift();
    if (visited.has(from)) continue;
    visited.add(from);
    const file = byPath.get(from);
    if (!file) { missing.add(from); continue; }
    if (file.binary || typeof file.content !== 'string') continue;

    for (const raw of rawDependencyRefs(from, file.content)) {
      const resolved = resolveRef(from, raw, available);
      if (resolved.external) { external.push({ from, ref: resolved.raw }); continue; }
      if (resolved.unsafe) { unsafe.push({ from, ref: resolved.raw }); continue; }
      edges.push({ from, ref: raw, to: resolved.path });
      if (!available.has(resolved.path)) missing.add(resolved.path);
      else if (!visited.has(resolved.path)) queue.push(resolved.path);
    }
  }

  return {
    entry: entryPath,
    entryPresent: byPath.has(entryPath),
    complete: byPath.has(entryPath) && missing.size === 0 && unsafe.length === 0 && duplicates.length === 0,
    reachable: [...visited].filter((p) => byPath.has(p)).sort(),
    missing: [...missing].sort(),
    unsafe,
    duplicates: [...new Set(duplicates)].sort(),
    external,
    edges,
    extra: [...available].filter((p) => !visited.has(p)).sort(),
  };
}
