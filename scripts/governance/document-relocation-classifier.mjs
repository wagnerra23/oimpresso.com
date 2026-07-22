#!/usr/bin/env node
// Classificador conservador de documentos. Produz plano v2 pinado ao HEAD;
// nao move nem edita arquivos. O adversario continua sendo o juiz.

import { execFileSync } from 'node:child_process';
import { readFileSync, statSync } from 'node:fs';
import { join, posix, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  SCHEMA_VERSION,
  collectIncomingReferences,
  extractDocumentReferences,
  extractLiteralReferences,
  resolveReference,
  validatePlanAtRoot,
} from './document-relocation-adversary.mjs';

const ROOT = resolve(fileURLToPath(new URL('../..', import.meta.url)));
const PROTECTED = new Set([
  'README.md', 'AGENTS.md', 'CLAUDE.md', 'DESIGN.md', 'INFRA.md', 'TEAM.md',
  'CODE_NOTES.md', 'MEMORY_TEAM_ONBOARDING.md', 'memory/proibicoes.md',
  'memory/08-handoff.md', 'memory/INDEX.md', 'memory/what-oimpresso.md',
]);
const IA_OS_MODULES = new Set(['ADS', 'Brief', 'Governance', 'KB', 'MemCofre', 'TeamMcp']);

const git = (args) => execFileSync('git', ['-C', ROOT, ...args], { encoding: 'utf8' }).trim();
const tracked = () => git(['ls-files', '-z']).split('\0').filter(Boolean).map((p) => p.replaceAll('\\', '/'));
const slugify = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
  .replace(/\.md$/i, '').replace(/([a-z0-9])([A-Z])/g, '$1-$2')
  .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

function frontmatter(text) {
  const body = text.match(/^---\s*\r?\n([\s\S]*?)\r?\n---/)?.[1] || '';
  const field = (name) => body.match(new RegExp(`^${name}:\\s*["']?([^\\r\\n"']+)`, 'mi'))?.[1]?.trim();
  return { type: field('type') || field('kind'), module: field('module'), lifecycle: field('lifecycle'), slug: field('slug') };
}

function inferKind(source, text, meta) {
  const name = posix.basename(source).toUpperCase();
  const title = text.match(/^#\s+(.+)$/m)?.[1] || '';
  const explicit = String(meta.type || '').toLowerCase();
  if (/^(?:adr|decision)$/.test(explicit) || /^\d{4}-/.test(name) && source.includes('/decisions/')) return 'decision';
  if (/session/.test(explicit) || source.startsWith('memory/sessions/')) return 'session';
  if (/handoff/.test(explicit) || /HANDOFF/.test(name)) return 'handoff';
  if (/briefing/.test(explicit) || name === 'BRIEFING.MD') return 'briefing';
  if (/runbook/.test(explicit) || name.startsWith('RUNBOOK')) return 'runbook';
  if (/audit/.test(explicit) || /AUDIT|AUDITORIA/.test(name)) return 'audit';
  if (/research/.test(explicit) || source.includes('/research/')) return 'research';
  if (/reference/.test(explicit)) return 'reference';
  if (/guide|how-to|manual/.test(explicit) || /\b(?:como|guia|manual)\b/i.test(title)) return 'how-to';
  if (/arquitetura|por que|explica/i.test(title)) return 'explanation';
  return 'other';
}

function inferModule(source, meta, modules) {
  const fromPath = source.match(/^(?:Modules|memory\/requisitos)\/([^/]+)\//)?.[1];
  const candidate = fromPath || meta.module;
  return modules.find((m) => m.toLowerCase() === String(candidate || '').toLowerCase()) || null;
}

export function classifyDocument({ source, text, modules, targetOverride }) {
  const meta = frontmatter(text);
  const kind = inferKind(source, text, meta);
  const moduleName = inferModule(source, meta, modules);
  let owner = moduleName ? `module:${moduleName}` : 'reference';
  if (kind === 'audit') owner = 'audit';
  else if (kind === 'research') owner = 'research';
  else if (!moduleName && /governan|claude|agente|hook|gate|workflow|ci\b/i.test(`${source}\n${text.slice(0, 2500)}`)) owner = 'governance';

  const layer = moduleName === 'Jana' ? 'product-ai'
    : (moduleName && !IA_OS_MODULES.has(moduleName) ? 'product-erp' : 'ia-os');
  const door = layer === 'product-ai' ? 'memory/requisitos/Jana/BRIEFING.md'
    : layer === 'product-erp' ? `memory/requisitos/${moduleName}/BRIEFING.md`
      : 'memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md';
  const slug = slugify(meta.slug || posix.basename(source));
  const prefix = owner.startsWith('module:') ? `memory/requisitos/${moduleName}/`
    : owner === 'audit' ? 'memory/audits/'
      : owner === 'research' ? 'memory/research/'
        : owner === 'governance' ? 'memory/governance/' : 'memory/reference/';
  const target = targetOverride || `${prefix}${kind === 'briefing' ? 'BRIEFING' : kind === 'runbook' ? `RUNBOOK-${slug}` : slug}.md`;
  const staleSignals = [/branch 6\.7-react/i, /session\('business\.id'\)/, /memory\/07-roadmap\.md/i]
    .filter((pattern) => pattern.test(text)).length;
  let confidence = meta.type || meta.module ? 0.97 : kind === 'other' ? 0.72 : 0.93;
  if (staleSignals) confidence = Math.min(confidence, 0.82);
  if (targetOverride) confidence = Math.min(confidence, 0.89);
  return {
    classification: { kind, owner, lifecycle: meta.lifecycle === 'arquivado' ? 'archived' : 'active', slug, layer, door },
    target, confidence,
    reason: `Classificacao por tipo, dono e camada ADR 0334; ${source} esta fora do prefixo canonico ${prefix}`,
    warnings: staleSignals ? [`${staleSignals} sinal(is) de receita possivelmente stale; exige revisao humana`] : [],
  };
}

function destination(fromFile, target, kind, fragment = '') {
  let path = target;
  if (kind === 'markdown-link') {
    path = relative(posix.dirname(fromFile), target).replaceAll('\\', '/');
    if (!path.startsWith('.')) path = `./${path}`;
  }
  return `${path}${fragment ? `#${fragment}` : ''}`;
}

function stillResolves(file, raw, kind, expected) {
  if (resolveReference(file, raw)?.toLowerCase() === expected.toLowerCase()) return true;
  return kind !== 'markdown-link' && raw.replace(/^\//, '').split('#')[0].toLowerCase() === expected.toLowerCase();
}

function rewritesFor(source, target, files) {
  const markdown = files.filter((p) => p.endsWith('.md'));
  const incoming = collectIncomingReferences(ROOT, markdown, [source], files).get(source.toLowerCase()) || [];
  const text = readFileSync(join(ROOT, source), 'utf8');
  const structured = extractDocumentReferences(text, source);
  const outbound = [...structured, ...extractLiteralReferences(text, source, files, structured)]
    .filter((ref) => files.some((p) => p.toLowerCase() === ref.target.toLowerCase()))
    .filter((ref) => !stillResolves(target, ref.raw, ref.kind, ref.target))
    .map((ref) => ({ ...ref, expectedTo: ref.target }));
  const all = [...incoming.map((ref) => ({ ...ref, expectedTo: target })), ...outbound];
  const seen = new Set();
  return all.filter((ref) => !seen.has(`${ref.file}\0${ref.kind}\0${ref.raw}`) && seen.add(`${ref.file}\0${ref.kind}\0${ref.raw}`))
    .map((ref) => ({
      file: ref.file,
      kind: ref.kind,
      from: ref.raw,
      to: destination(ref.file.toLowerCase() === source.toLowerCase() ? target : ref.file, ref.expectedTo, ref.kind, ref.fragment),
    }));
}

export function buildPlan(source, { targetOverride } = {}) {
  const normalized = source.replaceAll('\\', '/').replace(/^\.\//, '');
  const files = tracked();
  if (!files.includes(normalized)) throw new Error(`source nao versionado: ${normalized}`);
  if (PROTECTED.has(normalized) || /^(?:\.claude|\.github|memory\/(?:decisions|sessions|handoffs))\//.test(normalized)) {
    throw new Error(`source protegido por caminho: ${normalized}`);
  }
  const text = readFileSync(join(ROOT, normalized), 'utf8');
  const modules = files.map((p) => p.match(/^Modules\/([^/]+)\/module\.json$/)?.[1]).filter(Boolean);
  const inferred = classifyDocument({ source: normalized, text, modules, targetOverride });
  return {
    schema_version: SCHEMA_VERSION,
    base_sha: git(['rev-parse', 'HEAD']),
    generated_at: new Date().toISOString(),
    operations: [{ source: normalized, target: inferred.target, classification: inferred.classification,
      confidence: inferred.confidence, reason: inferred.reason, rewrites: rewritesFor(normalized, inferred.target, files) }],
    review: inferred.warnings,
  };
}

function selftest() {
  const modules = ['Financeiro', 'Jana', 'Governance'];
  const cases = [
    ['ERP', classifyDocument({ source: 'x/guia.md', text: '---\nmodule: Financeiro\ntype: guide\n---\n# Guia', modules }).classification.layer === 'product-erp'],
    ['Jana', classifyDocument({ source: 'x/guia.md', text: '---\nmodule: Jana\ntype: guide\n---\n# Guia', modules }).classification.layer === 'product-ai'],
    ['IA-OS', classifyDocument({ source: 'docs/hooks.md', text: '# Guia de hooks', modules }).classification.layer === 'ia-os'],
    ['stale-review', classifyDocument({ source: 'x.md', text: '# Como usar\nbranch 6.7-react', modules }).confidence < 0.9],
  ];
  for (const [name, ok] of cases) console.log(`${ok ? '[OK]' : '[FALHA]'} ${name}`);
  if (cases.some(([, ok]) => !ok)) process.exit(1);
  console.log(`SELFTEST OK - ${cases.length}/${cases.length}`);
}

function main() {
  const args = process.argv.slice(2);
  if (args.includes('--selftest')) return selftest();
  const source = args[args.indexOf('--source') + 1];
  if (!source || args.indexOf('--source') < 0) throw new Error('uso: --source <arquivo.md> [--target <destino.md>]');
  const targetIndex = args.indexOf('--target');
  const plan = buildPlan(source, { targetOverride: targetIndex >= 0 ? args[targetIndex + 1] : undefined });
  console.log(JSON.stringify(args.includes('--validate') ? { plan, adversary: validatePlanAtRoot(plan, ROOT) } : plan, null, 2));
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try { main(); } catch (error) { console.error(`CLASSIFIER ERROR: ${error.message}`); process.exit(2); }
}
