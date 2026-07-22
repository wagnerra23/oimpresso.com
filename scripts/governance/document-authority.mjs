// document-authority.mjs — identidade documental compartilhada pelo hook e pelo CI.
// Uma implementação, dois dentes: prevenção antes da escrita e verificação no merge.

import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

export const CANONICAL_ENTRYPOINT = 'README.md';
export const CANONICAL_ENTRYPOINT_MARKER = 'documentation-entrypoint: canonical';

const HISTORICAL_ROOTS = /^(?:memory\/(?:decisions|sessions|handoffs|sprints|research|audits)\/)/i;
const HISTORICAL_SEGMENTS = /(?:^|\/)(?:adr|adrs|audits|templates?|generated)(?:\/|$)/i;
const GENERATED_NAMES = /(?:^|\/)[^/]*(?:template|generated)[^/]*\.md$/i;

export function normalizeRelPath(value) {
  return String(value || '').replace(/\\/g, '/').replace(/^\.\//, '');
}

export function isLiveDocumentation(relPath) {
  const rel = normalizeRelPath(relPath);
  if (!/^memory\/.+\.md$/i.test(rel)) return false;
  return !HISTORICAL_ROOTS.test(rel) && !HISTORICAL_SEGMENTS.test(rel) && !GENERATED_NAMES.test(rel);
}

export function normalizeDocumentBody(content) {
  return String(content || '')
    .replace(/^\uFEFF/, '')
    .replace(/\r\n/g, '\n')
    .replace(/[ \t]+$/gm, '')
    .trim();
}

export function documentIdentity(content) {
  const text = String(content || '').replace(/^\uFEFF/, '');
  const frontmatter = text.match(/^---\s*\r?\n([\s\S]*?)\r?\n---\s*(?:\r?\n|$)/)?.[1] || '';
  const field = (name) => frontmatter.match(new RegExp(`^${name}:\\s*["']?([^\\r\\n"']+)`, 'mi'))?.[1]?.trim().toLowerCase() || '';
  const slug = field('slug');
  const type = field('type');
  return {
    body: normalizeDocumentBody(text),
    slug,
    type,
    authorityKey: slug && type ? `${type}|${slug}` : '',
  };
}

function walkMarkdown(rootDir, relDir, out) {
  let entries = [];
  try { entries = readdirSync(join(rootDir, relDir), { withFileTypes: true }); } catch { return out; }
  for (const entry of entries) {
    const rel = normalizeRelPath(join(relDir, entry.name));
    if (entry.isDirectory()) walkMarkdown(rootDir, rel, out);
    else if (entry.name.toLowerCase().endsWith('.md')) out.push(rel);
  }
  return out;
}

export function collectLiveDocuments(rootDir) {
  return walkMarkdown(rootDir, 'memory', [])
    .filter(isLiveDocumentation)
    .map((rel) => ({ rel, content: readFileSync(join(rootDir, rel), 'utf8') }));
}

export function findDocumentAuthorityConflicts({ rootDir, targetRel, content, documents }) {
  const target = normalizeRelPath(targetRel);
  if (!isLiveDocumentation(target)) return [];
  const candidate = documentIdentity(content);
  if (!candidate.body) return [];
  const existing = documents || collectLiveDocuments(rootDir);
  const conflicts = [];
  for (const doc of existing) {
    const rel = normalizeRelPath(doc.rel);
    if (rel.toLowerCase() === target.toLowerCase()) continue;
    const identity = documentIdentity(doc.content);
    if (identity.body === candidate.body) conflicts.push({ kind: 'conteudo-identico', file: rel });
    if (candidate.authorityKey && identity.authorityKey === candidate.authorityKey) {
      conflicts.push({ kind: 'autoridade-repetida', file: rel, authorityKey: candidate.authorityKey });
    }
  }
  return conflicts;
}

export function auditDocumentAuthority(rootDir) {
  const documents = collectLiveDocuments(rootDir);
  const byBody = new Map();
  const byAuthority = new Map();
  for (const doc of documents) {
    const identity = documentIdentity(doc.content);
    if (identity.body) (byBody.get(identity.body) || byBody.set(identity.body, []).get(identity.body)).push(doc.rel);
    if (identity.authorityKey) (byAuthority.get(identity.authorityKey) || byAuthority.set(identity.authorityKey, []).get(identity.authorityKey)).push(doc.rel);
  }

  const duplicates = [...byBody.values()].filter((files) => files.length > 1);
  const authorityCollisions = [...byAuthority.entries()]
    .filter(([, files]) => files.length > 1)
    .map(([authorityKey, files]) => ({ authorityKey, files }));

  const entryFiles = [CANONICAL_ENTRYPOINT, ...documents.map((doc) => doc.rel)];
  const canonicalMarkers = [];
  const parallelHeadings = [];
  for (const rel of entryFiles) {
    let text = '';
    try { text = readFileSync(join(rootDir, rel), 'utf8'); } catch { continue; }
    if (text.includes(CANONICAL_ENTRYPOINT_MARKER)) canonicalMarkers.push(rel);
    const isLocalReadme = /(?:^|\/)_?readme\.md$/i.test(rel);
    if (rel !== CANONICAL_ENTRYPOINT && !isLocalReadme && /^#{1,3}\s+.*\bcomece\s+aqui\b/im.test(text)) parallelHeadings.push(rel);
  }

  return { duplicates, authorityCollisions, canonicalMarkers, parallelHeadings };
}
