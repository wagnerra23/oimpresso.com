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
  ownerRules,
  resolveReference,
  validatePlanAtRoot,
} from './document-relocation-adversary.mjs';

const ROOT = resolve(fileURLToPath(new URL('../..', import.meta.url)));
const PROTECTED = new Set([
  'README.md', 'AGENTS.md', 'CLAUDE.md', 'DESIGN.md', 'INFRA.md', 'TEAM.md',
  'CODE_NOTES.md', 'MEMORY_TEAM_ONBOARDING.md', 'memory/proibicoes.md',
  'memory/08-handoff.md', 'memory/INDEX.md', 'memory/what-oimpresso.md',
]);
// Enum canonico de lifecycle (ADR 0270): dialetos PT/EN normalizam; desconhecido nao vira 'active'.
const LIFECYCLE_MAP = new Map([
  ['active', 'active'], ['ativo', 'active'], ['ativa', 'active'],
  ['historical', 'historical'], ['historico', 'historical'], ['histórico', 'historical'],
  ['archived', 'archived'], ['arquivado', 'archived'], ['arquivada', 'archived'],
]);

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

// Corpus de NEGOCIO (ADR 0334 corpus a): dominios/ e clientes/ — decide por path,
// ANTES de qualquer heuristica de processo. Sem isso 94% do negocio virava 'reference'
// (medicao 2026-07-22: 410/434 docs de dominios/clientes classificados como processo).
function businessOwner(source) {
  if (/^memory\/dominios?\//.test(source)) return 'domain';
  if (/^memory\/clientes(?:-legacy)?\//.test(source)) return 'client';
  return null;
}

export function classifyDocument({ source, text, modules, targetOverride }) {
  const meta = frontmatter(text);
  const warnings = [];
  const kind = inferKind(source, text, meta);
  const moduleName = inferModule(source, meta, modules);
  // Metadado declarado so vale se resolve contra o mundo real (review 2026-07-22:
  // `module: Financeirro` inexistente dava 0.97). Invalido = alerta + confianca no chao.
  const moduleDeclaredInvalid = Boolean(meta.module) && !modules.some((m) => m.toLowerCase() === String(meta.module).toLowerCase());
  if (moduleDeclaredInvalid) warnings.push(`frontmatter module: ${meta.module} nao corresponde a nenhum modulo real`);
  const typeDeclaredValid = Boolean(meta.type) && kind !== 'other';
  if (meta.type && kind === 'other') warnings.push(`frontmatter type: ${meta.type} nao reconhecido pelos kinds canonicos`);

  const corpus = businessOwner(source);
  let owner = corpus ?? (moduleName ? `module:${moduleName}` : 'reference');
  if (!corpus) {
    if (kind === 'audit') owner = 'audit';
    else if (kind === 'research') owner = 'research';
    else if (!moduleName && /governan|claude|agente|hook|gate|workflow|ci\b/i.test(`${source}\n${text.slice(0, 2500)}`)) owner = 'governance';
  }

  const slug = slugify(meta.slug || posix.basename(source));
  const prefix = owner.startsWith('module:') ? `memory/requisitos/${moduleName}/`
    : owner === 'audit' ? 'memory/audits/'
      : owner === 'research' ? 'memory/research/'
        : owner === 'governance' ? 'memory/governance/'
          : owner === 'domain' ? 'memory/dominios/'
            : owner === 'client' ? 'memory/clientes/' : 'memory/reference/';
  // Corpus de negocio preserva a subarvore (nunca achatar wr-comercial/modulos/...).
  let target;
  if (targetOverride) target = targetOverride;
  else if (owner === 'domain') target = `memory/dominios/${source.replace(/^memory\/dominios?\//, '')}`;
  else if (owner === 'client') target = `memory/clientes/${source.replace(/^memory\/clientes(?:-legacy)?\//, '')}`;
  else target = `${prefix}${kind === 'briefing' ? 'BRIEFING' : kind === 'runbook' ? `RUNBOOK-${slug}` : slug}.md`;

  // layer e door saem da MESMA matriz que o adversario valida (fonte unica, sem drift).
  const rules = ownerRules(owner, target);
  const layer = rules?.layer ?? 'ia-os';
  const door = rules?.door ?? '';
  if (!door) warnings.push('porta-mae indeterminada (ex.: cliente sem PERFIL.md); exige decisao humana');

  const rawLifecycle = String(meta.lifecycle ?? '').trim().toLowerCase();
  const lifecycle = rawLifecycle ? LIFECYCLE_MAP.get(rawLifecycle) : 'active';
  if (rawLifecycle && !lifecycle) warnings.push(`lifecycle desconhecido no frontmatter: ${meta.lifecycle}`);

  const staleSignals = [/branch 6\.7-react/i, /session\('business\.id'\)/, /memory\/07-roadmap\.md/i]
    .filter((pattern) => pattern.test(text)).length;
  if (staleSignals) warnings.push(`${staleSignals} sinal(is) de receita possivelmente stale; exige revisao humana`);
  // Consolidacao de pasta duplicada (dominio/->dominios/, clientes-legacy/->clientes/):
  // owner e destino determinados por PATH; o move e mecanico e certo, a incerteza de
  // KIND e irrelevante. Nao inflar alem disso — os rebaixamentos abaixo ainda mordem.
  const canonicalConsolidation = Boolean(corpus) && !targetOverride && !source.startsWith(prefix) && target.startsWith(prefix);
  let confidence = canonicalConsolidation ? 0.95
    : (typeDeclaredValid || (meta.module && !moduleDeclaredInvalid)) ? 0.97 : kind === 'other' ? 0.72 : 0.93;
  if (moduleDeclaredInvalid) confidence = Math.min(confidence, 0.6);
  if (rawLifecycle && !lifecycle) confidence = Math.min(confidence, 0.85);
  if (staleSignals) confidence = Math.min(confidence, 0.82);
  if (targetOverride) confidence = Math.min(confidence, 0.89);
  const reason = canonicalConsolidation
    ? `Consolidacao de pasta: ${source} -> prefixo canonico ${prefix} (mesmo owner ${owner}, subarvore preservada)`
    : `Classificacao por tipo, dono e camada ADR 0334; ${source} esta fora do prefixo canonico ${prefix}`;
  return {
    classification: { kind, owner, lifecycle: lifecycle ?? 'active', slug, layer, door },
    target, confidence,
    already_canonical: target === source,
    reason,
    warnings,
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

// finalPaths: mapa lower(source)->target de TODOS os moves do lote. Sem ele, o comportamento
// e per-source (default). Com ele, um relink so e gerado quando de fato quebraria: se o
// referrer e o alvo movem juntos preservando a distancia relativa, o link continua valido.
function rewritesFor(source, target, files, finalPaths = new Map([[source.toLowerCase(), target]])) {
  const markdown = files.filter((p) => p.endsWith('.md'));
  const incoming = collectIncomingReferences(ROOT, markdown, [source], files).get(source.toLowerCase()) || [];
  const text = readFileSync(join(ROOT, source), 'utf8');
  const structured = extractDocumentReferences(text, source);
  const finalOf = (p) => finalPaths.get(p.toLowerCase()) ?? p;
  // outbound: source aponta pra alvo; alvo pode mover (expectedTo = final do alvo).
  const outbound = [...structured, ...extractLiteralReferences(text, source, files, structured)]
    .filter((ref) => files.some((p) => p.toLowerCase() === ref.target.toLowerCase()))
    .map((ref) => ({ ...ref, expectedTo: finalOf(ref.target) }))
    .filter((ref) => !stillResolves(target, ref.raw, ref.kind, ref.expectedTo));
  // inbound: alguem aponta pra source; se o referrer move junto e o link segue resolvendo, pula.
  const inbound = incoming
    .map((ref) => ({ ...ref, expectedTo: target }))
    .filter((ref) => !stillResolves(finalOf(ref.file), ref.raw, ref.kind, target));
  const all = [...inbound, ...outbound];
  const seen = new Set();
  // count = ocorrencias exatas do `from` no arquivo NO MOMENTO do plano; o executor
  // confere na hora do apply (drift entre plano e apply aborta a transacao).
  const contentCache = new Map();
  const countIn = (file, from) => {
    if (!contentCache.has(file)) {
      try { contentCache.set(file, readFileSync(join(ROOT, file), 'utf8')); } catch { contentCache.set(file, ''); }
    }
    const content = contentCache.get(file);
    return content ? content.split(from).length - 1 : 0;
  };
  return all.filter((ref) => !seen.has(`${ref.file}\0${ref.kind}\0${ref.raw}`) && seen.add(`${ref.file}\0${ref.kind}\0${ref.raw}`))
    .map((ref) => ({
      file: ref.file,
      kind: ref.kind,
      from: ref.raw,
      to: destination(ref.file.toLowerCase() === source.toLowerCase() ? target : finalOf(ref.file), ref.expectedTo, ref.kind, ref.fragment),
      count: countIn(ref.file, ref.raw),
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
  // Documento ja no prefixo canonico do owner: nada a mover — sinal, nao plano.
  // (Sem isso, um doc de dominios/ viraria um move achatado sem sentido.)
  if (inferred.already_canonical && !targetOverride) {
    return {
      schema_version: SCHEMA_VERSION,
      base_sha: git(['rev-parse', 'HEAD']),
      already_canonical: true,
      source: normalized,
      classification: inferred.classification,
      note: 'documento ja mora no prefixo canonico do owner; nenhum movimento necessario',
      review: inferred.warnings,
    };
  }
  return {
    schema_version: SCHEMA_VERSION,
    base_sha: git(['rev-parse', 'HEAD']),
    generated_at: new Date().toISOString(),
    operations: [{ source: normalized, target: inferred.target, classification: inferred.classification,
      confidence: inferred.confidence, reason: inferred.reason, rewrites: rewritesFor(normalized, inferred.target, files) }],
    review: inferred.warnings,
  };
}

const isAppendOnlyPath = (p) => /^memory\/(?:decisions|sessions|handoffs)\//.test(p);

// Plano COESO de lote: classifica N sources juntos, resolve os relinks internos com
// finalPaths compartilhado (arquivos que se referenciam movem juntos sem conflito) e
// EXCLUI automaticamente quem so poderia mover reescrevendo historico append-only.
export function buildBatchPlan(sources) {
  const files = tracked();
  const modules = files.map((p) => p.match(/^Modules\/([^/]+)\/module\.json$/)?.[1]).filter(Boolean);
  const normalized = sources.map((s) => s.replaceAll('\\', '/').replace(/^\.\//, ''));
  for (const s of normalized) {
    if (!files.includes(s)) throw new Error(`source nao versionado: ${s}`);
    if (PROTECTED.has(s) || /^(?:\.claude|\.github|memory\/(?:decisions|sessions|handoffs))\//.test(s)) {
      throw new Error(`source protegido por caminho: ${s}`);
    }
  }
  const classified = normalized.map((s) => ({ source: s, inferred: classifyDocument({ source: s, text: readFileSync(join(ROOT, s), 'utf8'), modules }) }));
  const alreadyCanonical = classified.filter((c) => c.inferred.already_canonical).map((c) => c.source);
  let active = classified.filter((c) => !c.inferred.already_canonical);
  const excluded = [];
  // Exclui em cadeia quem geraria relink em append-only (recomputa finalPaths a cada remocao).
  for (let guard = active.length; guard >= 0; guard -= 1) {
    const finalPaths = new Map(active.map((c) => [c.source.toLowerCase(), c.inferred.target]));
    const offender = active.find((c) => rewritesFor(c.source, c.inferred.target, files, finalPaths).some((r) => isAppendOnlyPath(r.file)));
    if (!offender) break;
    excluded.push({ source: offender.source, reason: 'referrer append-only (relink exigiria editar ADR/session/handoff); preserve o path' });
    active = active.filter((c) => c !== offender);
  }
  const finalPaths = new Map(active.map((c) => [c.source.toLowerCase(), c.inferred.target]));
  const operations = active.map((c) => ({
    source: c.source, target: c.inferred.target, classification: c.inferred.classification,
    confidence: c.inferred.confidence, reason: c.inferred.reason,
    rewrites: rewritesFor(c.source, c.inferred.target, files, finalPaths),
  }));
  return {
    schema_version: SCHEMA_VERSION,
    base_sha: git(['rev-parse', 'HEAD']),
    generated_at: new Date().toISOString(),
    operations,
    review: active.flatMap((c) => c.inferred.warnings),
    excluded,
    already_canonical: alreadyCanonical,
  };
}

function selftest() {
  const modules = ['Financeiro', 'Jana', 'Governance'];
  const dominio = classifyDocument({ source: 'memory/dominios/wr-comercial/tabelas/AGENDA.md', text: '# Tabela AGENDA', modules });
  const clienteLegacy = classifyDocument({ source: 'memory/clientes-legacy/rota-livre.md', text: '# ROTA LIVRE', modules });
  const cases = [
    ['ERP', classifyDocument({ source: 'x/guia.md', text: '---\nmodule: Financeiro\ntype: guide\n---\n# Guia', modules }).classification.layer === 'product-erp'],
    ['Jana', classifyDocument({ source: 'x/guia.md', text: '---\nmodule: Jana\ntype: guide\n---\n# Guia', modules }).classification.layer === 'product-ai'],
    ['IA-OS', classifyDocument({ source: 'docs/hooks.md', text: '# Guia de hooks', modules }).classification.layer === 'ia-os'],
    ['stale-review', classifyDocument({ source: 'x.md', text: '# Como usar\nbranch 6.7-react', modules }).confidence < 0.9],
    // Review 2026-07-22: module inexistente dava 0.97 — agora metadado invalido derruba pra REVIEW.
    ['modulo-inexistente-nao-boost', classifyDocument({ source: 'x/guia.md', text: '---\nmodule: Financeirro\ntype: guide\n---\n# Guia', modules }).confidence < 0.9],
    // Corpus de negocio (0334): dominios/ e owner domain + business-knowledge, e ja-canonico nao gera move.
    ['dominio-e-business-knowledge', dominio.classification.owner === 'domain' && dominio.classification.layer === 'business-knowledge' && dominio.already_canonical === true],
    // clientes-legacy migra pra clientes/ preservando nome (owner client), nunca pra reference/.
    ['cliente-legacy-vira-client', clienteLegacy.classification.owner === 'client' && clienteLegacy.target === 'memory/clientes/rota-livre.md'],
    // Review 2026-07-22: historical voltava como active — agora o enum normaliza e preserva.
    ['lifecycle-historical-preservado', classifyDocument({ source: 'x/velho.md', text: '---\ntype: guide\nlifecycle: historical\n---\n# Guia antigo', modules }).classification.lifecycle === 'historical'],
    ['lifecycle-desconhecido-derruba-confianca', classifyDocument({ source: 'x/g.md', text: '---\ntype: guide\nlifecycle: vigente\n---\n# Guia', modules }).confidence < 0.9],
    // Consolidacao de pasta duplicada: move mecanico certo (owner por path) — NAO e baixa
    // confianca. Sem frontmatter, dominio/ (singular) -> dominios/ deve ser >=0.9 e APPROVAVEL.
    ['consolidacao-dominio-alta-confianca', classifyDocument({ source: 'memory/dominios/vendas.md', text: '# Vendas', modules }).confidence >= 0.9
      && classifyDocument({ source: 'memory/dominios/vendas.md', text: '# Vendas', modules }).target === 'memory/dominios/vendas.md'],
    // Consolidacao stale AINDA cai (o rebaixamento morde acima da inflacao de consolidacao).
    ['consolidacao-stale-ainda-cai', classifyDocument({ source: 'memory/dominio/x.md', text: '# X\nbranch 6.7-react', modules }).confidence < 0.9],
  ];
  for (const [name, ok] of cases) console.log(`${ok ? '[OK]' : '[FALHA]'} ${name}`);
  if (cases.some(([, ok]) => !ok)) process.exit(1);
  console.log(`SELFTEST OK - ${cases.length}/${cases.length}`);
}

function main() {
  const args = process.argv.slice(2);
  if (args.includes('--selftest')) return selftest();
  const option = (name) => { const at = args.indexOf(name); return at >= 0 ? args[at + 1] : null; };
  // Lote coeso: --dir <prefixo> pega os .md versionados sob o prefixo; --batch a,b,c lista explicita.
  const dir = option('--dir');
  const batch = option('--batch');
  if (dir || batch) {
    const sources = dir
      ? tracked().filter((p) => p.startsWith(dir.replace(/\/?$/, '/')) && p.endsWith('.md'))
      : batch.split(',').map((s) => s.trim()).filter(Boolean);
    if (!sources.length) throw new Error(`nenhum .md versionado em ${dir || batch}`);
    const plan = buildBatchPlan(sources);
    console.log(JSON.stringify(args.includes('--validate') ? { plan, adversary: validatePlanAtRoot(plan, ROOT) } : plan, null, 2));
    return;
  }
  const source = args[args.indexOf('--source') + 1];
  if (!source || args.indexOf('--source') < 0) throw new Error('uso: --source <arquivo.md> [--target <destino.md>] | --dir <prefixo> | --batch a,b,c [--validate]');
  const targetIndex = args.indexOf('--target');
  const plan = buildPlan(source, { targetOverride: targetIndex >= 0 ? args[targetIndex + 1] : undefined });
  if (plan.already_canonical) { console.log(JSON.stringify(plan, null, 2)); return; }
  console.log(JSON.stringify(args.includes('--validate') ? { plan, adversary: validatePlanAtRoot(plan, ROOT) } : plan, null, 2));
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try { main(); } catch (error) { console.error(`CLASSIFIER ERROR: ${error.message}`); process.exit(2); }
}
