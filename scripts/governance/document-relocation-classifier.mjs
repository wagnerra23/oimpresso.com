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
  isGateGuarded,
  ownerRules,
  resolveReference,
  validatePlanAtRoot,
} from './document-relocation-adversary.mjs';

const ROOT = resolve(fileURLToPath(new URL('../..', import.meta.url)));

// Registro DECLARADO de placement por area (scripts/governance/document-placement.json).
// O classificador CONSULTA este registro em vez de adivinhar por regex qual familia dona a
// area. Area nao declarada = 'review' (nunca adivinha). Ratificado por [W] no merge.
const PLACEMENT = (() => {
  try { return JSON.parse(readFileSync(join(ROOT, 'scripts/governance/document-placement.json'), 'utf8')).areas || []; }
  catch { return []; }
})();
export function resolvePlacement(source, areas = PLACEMENT) {
  let best = null;
  for (const a of areas) {
    if (source.startsWith(a.prefix) && (!best || a.prefix.length > best.prefix.length)) best = a;
  }
  return best || { prefix: '', rule: 'review', note: 'area nao declarada no registro de placement' };
}

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
  // comparativos/ (Capterra/mercado/estado-da-arte) = research (owner decidido por [W] 2026-07-22:
  // arvore-alvo §II.3 dobra comparativo dentro de research; nao ha owner 'comparison' wired).
  if (/research|comparativ/.test(explicit) || source.includes('/research/') || source.includes('/comparativos/')) return 'research';
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
  // dominioS/ (PLURAL) = conhecimento de negocio. dominio/ (SINGULAR) = dicionario de
  // enum (domain-dict-guard, ADR 0264 G-4) — NAO e corpus, e protegido (nao move).
  if (/^memory\/dominios\//.test(source)) return 'domain';
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
  // Qualquer doc sob memory/requisitos/<area>/ pertence AQUELA area — mesmo que <area> nao
  // seja modulo nWidart (Sells/Produto/Estoque/Infra/_DesignSystem sao core UltimatePOS ou
  // pseudo-pastas, sem Modules/<X>/module.json). Sem isso o classificador misroteava ~200
  // docs de modulo pra governance (incidente scan 2026-07-22). owner=module:<area> => prefixo
  // memory/requisitos/<area>/ => alreadyInFamily => NAO move.
  const requisitosArea = source.match(/^memory\/requisitos\/([^/]+)\//)?.[1];
  let owner = corpus ?? (requisitosArea ? `module:${requisitosArea}` : moduleName ? `module:${moduleName}` : 'reference');
  if (!corpus && !requisitosArea) {
    if (kind === 'audit') owner = 'audit';
    else if (kind === 'research') owner = 'research';
    else if (!moduleName && /governan|claude|agente|hook|gate|workflow|ci\b/i.test(`${source}\n${text.slice(0, 2500)}`)) owner = 'governance';
  }

  const slug = slugify(meta.slug || posix.basename(source));
  // Modulo do owner (parseado do proprio owner) — pode nao ser nWidart (requisitosArea).
  const ownerModule = owner.startsWith('module:') ? owner.slice('module:'.length) : null;
  const prefix = ownerModule ? `memory/requisitos/${ownerModule}/`
    : owner === 'audit' ? 'memory/audits/'
      : owner === 'research' ? 'memory/research/'
        : owner === 'governance' ? 'memory/governance/'
          : owner === 'domain' ? 'memory/dominios/'
            : owner === 'client' ? 'memory/clientes/' : 'memory/reference/';
  // Doc JA sob o prefixo do owner (mesmo em subpasta) esta na familia certa: NAO move.
  // Sem isso, memory/research/<sub>/X.md seria achatado pra memory/research/x.md, destruindo
  // a subarvore e colidindo (incidente teste 2026-07-22: 98 docs em subpastas em risco).
  // Renomear-pra-slug dentro da familia e curadoria, nao realocacao — nao e trabalho da maquina.
  const alreadyInFamily = !targetOverride && source.startsWith(prefix);
  // Corpus de negocio preserva a subarvore (nunca achatar wr-comercial/modulos/...).
  let target;
  if (targetOverride) target = targetOverride;
  else if (alreadyInFamily) target = source;
  else if (owner === 'domain') target = `memory/dominios/${source.replace(/^memory\/dominios\//, '')}`;
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
  // Podridao AUTO-DECLARADA no cabecalho: banner `⚠️ **STALE ...**` ou "mantido por
  // compatibilidade historica". A declaracao do proprio doc vale mais que a heuristica —
  // review adversarial 2026-07-22: 03-architecture dizia "STALE / PontoWr2-era" no header
  // e mesmo assim saia APPROVE 0.93. Marcador medido no corpus: 3/3221 hits, todos reais.
  const header = text.slice(0, 600);
  const selfDeclaredStale = /⚠️?[^\n]{0,40}\bSTALE\b/.test(header)
    || /mantido por compatibilidade hist[oó]rica/i.test(header);
  if (selfDeclaredStale) warnings.push('documento se auto-declara STALE/legado no cabecalho; candidato a tombstone, nunca move automatico');
  // Consolidacao de pasta duplicada (dominio/->dominios/, clientes-legacy/->clientes/):
  // owner e destino determinados por PATH; o move e mecanico e certo, a incerteza de
  // KIND e irrelevante. Nao inflar alem disso — os rebaixamentos abaixo ainda mordem.
  const canonicalConsolidation = Boolean(corpus) && !targetOverride && !source.startsWith(prefix) && target.startsWith(prefix);
  let confidence = canonicalConsolidation ? 0.95
    : (typeDeclaredValid || (meta.module && !moduleDeclaredInvalid)) ? 0.97 : kind === 'other' ? 0.72 : 0.93;
  if (moduleDeclaredInvalid) confidence = Math.min(confidence, 0.6);
  if (selfDeclaredStale) confidence = Math.min(confidence, 0.6);
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

// Referrer NAO-RELINKAVEL = append-only (Tier 0) OU sob gate diff-aware (memory/requisitos/**,
// charter — lapide 2026-07-12: relinkar acorda anchor-lint/schema/distiller). Fonte da regra
// gate-guarded = adversario (importada), sem drift. O stub do tombstone serve os dois.
const isUnrelinkablePath = (p) => /^memory\/(?:decisions|sessions|handoffs)\//.test(p) || isGateGuarded(p);

// move-with-tombstone (proposal estrutura-canon-memoria §II.5 passo 7): separa os relinks que
// NAO podem ser feitos (vao pro STUB no path antigo) dos livres (relinkados pro canonico).
// So sob --tombstone; sem ele o comportamento e EXCLUIR o source (buildBatchPlan).
function partitionForTombstone(rewrites) {
  return {
    immutable: rewrites.filter((r) => isUnrelinkablePath(r.file)),
    mutable: rewrites.filter((r) => !isUnrelinkablePath(r.file)),
  };
}

export function buildPlan(source, { targetOverride, tombstone = false } = {}) {
  const normalized = source.replaceAll('\\', '/').replace(/^\.\//, '');
  const files = tracked();
  if (!files.includes(normalized)) throw new Error(`source nao versionado: ${normalized}`);
  if (PROTECTED.has(normalized) || /^(?:\.claude|\.github|memory\/(?:decisions|sessions|handoffs)|memory\/dominio)\//.test(normalized)) {
    throw new Error(`source protegido por caminho: ${normalized}`);
  }
  // Registro de placement (declarado, [W]-ratificado) vence o heuristico.
  const placement = resolvePlacement(normalized);
  if (placement.rule === 'protected') throw new Error(`source protegido (registro placement): ${normalized}`);
  if (placement.rule === 'never') {
    return {
      schema_version: SCHEMA_VERSION, base_sha: git(['rev-parse', 'HEAD']),
      skipped: true, rule: 'never', source: normalized,
      note: placement.note || 'area marcada como nao-migrar no registro de placement', review: [],
    };
  }
  const text = readFileSync(join(ROOT, normalized), 'utf8');
  const modules = files.map((p) => p.match(/^Modules\/([^/]+)\/module\.json$/)?.[1]).filter(Boolean);
  const inferred = classifyDocument({ source: normalized, text, modules, targetOverride });
  // Area declarada 'canonical': fica onde esta, mesmo que o heuristico ache outra familia.
  if (placement.rule === 'canonical' && !targetOverride) {
    return {
      schema_version: SCHEMA_VERSION, base_sha: git(['rev-parse', 'HEAD']),
      already_canonical: true, source: normalized, classification: inferred.classification,
      note: `area declarada canonical no registro de placement (${placement.prefix}); nao move`,
      review: inferred.warnings,
    };
  }
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
  const rewrites = rewritesFor(normalized, inferred.target, files);
  const { immutable, mutable } = partitionForTombstone(rewrites);
  const useTombstone = tombstone && immutable.length > 0;
  // Area 'review' (ou nao declarada): o registro NAO decide a familia — o heuristico nao pode
  // auto-mover. Cap a confianca => adversario forca REVIEW (decisao humana por arquivo).
  const needsHumanPlacement = placement.rule === 'review';
  const confidence = needsHumanPlacement ? Math.min(inferred.confidence, 0.5) : inferred.confidence;
  const review = needsHumanPlacement
    ? [...inferred.warnings, `placement da area '${placement.prefix || '(raiz)'}' nao declarado/em review — destino do heuristico e palpite; exige decisao humana`]
    : inferred.warnings;
  return {
    schema_version: SCHEMA_VERSION,
    base_sha: git(['rev-parse', 'HEAD']),
    generated_at: new Date().toISOString(),
    operations: [{ source: normalized, target: inferred.target, classification: inferred.classification,
      confidence, reason: inferred.reason,
      ...(useTombstone ? { tombstone: true } : {}),
      rewrites: useTombstone ? mutable : rewrites }],
    review,
  };
}

// Plano COESO de lote: classifica N sources juntos, resolve os relinks internos com
// finalPaths compartilhado (arquivos que se referenciam movem juntos sem conflito).
// Quem so poderia mover reescrevendo historico append-only e, por default, EXCLUIDO;
// sob { tombstone: true } (§II.5 passo 7) esses migram deixando stub no path antigo —
// os referrers imutaveis resolvem pelo stub, os mutaveis sao relinkados pro canonico.
export function buildBatchPlan(sources, { tombstone = false } = {}) {
  const files = tracked();
  const modules = files.map((p) => p.match(/^Modules\/([^/]+)\/module\.json$/)?.[1]).filter(Boolean);
  const normalized = sources.map((s) => s.replaceAll('\\', '/').replace(/^\.\//, ''));
  for (const s of normalized) {
    if (!files.includes(s)) throw new Error(`source nao versionado: ${s}`);
    if (PROTECTED.has(s) || /^(?:\.claude|\.github|memory\/(?:decisions|sessions|handoffs)|memory\/dominio)\//.test(s)) {
      throw new Error(`source protegido por caminho: ${s}`);
    }
  }
  const classified = normalized.map((s) => ({ source: s, inferred: classifyDocument({ source: s, text: readFileSync(join(ROOT, s), 'utf8'), modules }) }));
  const alreadyCanonical = classified.filter((c) => c.inferred.already_canonical).map((c) => c.source);
  let active = classified.filter((c) => !c.inferred.already_canonical);
  const excluded = [];
  // Sem tombstone: exclui em cadeia quem geraria relink NAO-RELINKAVEL (append-only OU sob gate
  // diff-aware; recomputa finalPaths a cada remocao). Com tombstone: NAO exclui — todos migram
  // (o stub cobre os nao-relinkaveis).
  if (!tombstone) {
    for (let guard = active.length; guard >= 0; guard -= 1) {
      const finalPaths = new Map(active.map((c) => [c.source.toLowerCase(), c.inferred.target]));
      const offender = active.find((c) => rewritesFor(c.source, c.inferred.target, files, finalPaths).some((r) => isUnrelinkablePath(r.file)));
      if (!offender) break;
      excluded.push({ source: offender.source, reason: 'referrer nao-relinkavel (append-only ou sob gate diff-aware); preserve o path ou use --tombstone' });
      active = active.filter((c) => c !== offender);
    }
  }
  const finalPaths = new Map(active.map((c) => [c.source.toLowerCase(), c.inferred.target]));
  const operations = active.map((c) => {
    const rewrites = rewritesFor(c.source, c.inferred.target, files, finalPaths);
    const { immutable, mutable } = partitionForTombstone(rewrites);
    const useTombstone = tombstone && immutable.length > 0;
    return {
      source: c.source, target: c.inferred.target, classification: c.inferred.classification,
      confidence: c.inferred.confidence, reason: c.inferred.reason,
      ...(useTombstone ? { tombstone: true } : {}),
      rewrites: useTombstone ? mutable : rewrites,
    };
  });
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
  // Fixture hipotetico (nao le arquivo — text inline): alias neutro pra o teste nao
  // apontar pra um doc real que a propria maquina relocaria (senao o relink quebra este selftest).
  const clienteLegacy = classifyDocument({ source: 'memory/clientes-legacy/exemplo-cliente.md', text: '# EXEMPLO', modules });
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
    ['cliente-legacy-vira-client', clienteLegacy.classification.owner === 'client' && clienteLegacy.target === 'memory/clientes/exemplo-cliente.md'],
    // Review 2026-07-22: historical voltava como active — agora o enum normaliza e preserva.
    ['lifecycle-historical-preservado', classifyDocument({ source: 'x/velho.md', text: '---\ntype: guide\nlifecycle: historical\n---\n# Guia antigo', modules }).classification.lifecycle === 'historical'],
    ['lifecycle-desconhecido-derruba-confianca', classifyDocument({ source: 'x/g.md', text: '---\ntype: guide\nlifecycle: vigente\n---\n# Guia', modules }).confidence < 0.9],
    // Consolidacao de pasta duplicada: move mecanico certo (owner por path) — NAO e baixa
    // confianca. clientes-legacy/ (nao-canonico) -> clientes/ deve ser >=0.9. NB: paths aqui
    // sao SINTETICOS (fixtures nunca usam path de doc real — o relink os reescreveria).
    // Atencao: dominio/ SINGULAR NAO entra aqui (e dicionario protegido, nao corpus).
    ['consolidacao-cliente-legacy-alta-confianca', classifyDocument({ source: 'memory/clientes-legacy/fixture-x.md', text: '# Exemplo', modules }).confidence >= 0.9
      && classifyDocument({ source: 'memory/clientes-legacy/fixture-x.md', text: '# Exemplo', modules }).target === 'memory/clientes/fixture-x.md'],
    // Consolidacao stale AINDA cai (o rebaixamento morde acima da inflacao de consolidacao).
    ['consolidacao-stale-ainda-cai', classifyDocument({ source: 'memory/clientes-legacy/y.md', text: '# Y\nbranch 6.7-react', modules }).confidence < 0.9],
    // Registro de placement (Fase 1): resolve por prefixo mais longo; area nao declarada = review.
    ['placement-prefixo-mais-longo-vence', (() => { const A = [{ prefix: 'memory/', rule: 'review' }, { prefix: 'memory/reference/', rule: 'canonical' }]; return resolvePlacement('memory/reference/x.md', A).rule === 'canonical'; })()],
    ['placement-never-respeitado', resolvePlacement('memory/modulos/x.md', [{ prefix: 'memory/modulos/', rule: 'never' }]).rule === 'never'],
    ['placement-nao-declarado-e-review', resolvePlacement('qualquer/coisa/x.md', []).rule === 'review'],
    // Incidente teste 2026-07-22 (#4691): doc JA sob memory/research/<sub>/ era ACHATADO —
    // fallback geral do already-in-family (complementa o registro declarado).
    ['ja-na-familia-nao-achata', (() => { const c = classifyDocument({ source: 'memory/research/2026-05-x/00-INDEX.md', text: '# Index', modules }); return c.already_canonical === true && c.target === 'memory/research/2026-05-x/00-INDEX.md'; })()],
    // Owner [W] 2026-07-22: comparativos/ (Capterra/mercado) e research, nunca governance/reference.
    // Path SINTETICO (fixture nunca usa doc real — o relink o reescreveria, incl. este script).
    ['comparativo-vira-research', (() => { const c = classifyDocument({ source: 'memory/comparativos/fixture-capterra.md', text: '# comparativo fixture', modules }); return c.classification.kind === 'research' && c.classification.owner === 'research' && c.target.startsWith('memory/research/'); })()],
    // Review adversarial 2026-07-22: 03-architecture auto-declarava "⚠️ STALE / PontoWr2-era"
    // no header e saia APPROVE 0.93 — a auto-declaracao agora derruba pra <0.9 (REVIEW) mesmo
    // com frontmatter valido que daria boost 0.97.
    ['banner-stale-autodeclarado-nunca-approve', classifyDocument({ source: 'memory/03-arch.md', text: '---\ntype: reference\n---\n# 03 — Arquitetura\n\n> ⚠️ **STALE / PontoWr2-era (legado — "Laravel 10").** Mantido por compatibilidade histórica.', modules }).confidence < 0.9],
    // Controle negativo: mencao a "stale" em prosa (sem banner no header) nao dispara.
    ['mencao-stale-em-prosa-nao-dispara', classifyDocument({ source: 'x/feedback.md', text: '---\ntype: reference\n---\n# Feedback — prompt pode vir stale\n\nO cache STALE do sync e discutido aqui.', modules }).confidence >= 0.9],
    // Consolidacao + banner auto-declarado: o cap 0.6 morde acima do 0.95 da consolidacao.
    ['consolidacao-banner-stale-tambem-cai', classifyDocument({ source: 'memory/clientes-legacy/z.md', text: '# Z\n\n> ⚠️ **STALE (histórico).**', modules }).confidence < 0.9],
    // move-with-tombstone (§II.5 passo 7): a particao separa o relink NAO-RELINKAVEL (append-only
    // OU sob gate diff-aware — vai pro stub) do livre (relinkado). Dente do modo tombstone no lado
    // do classificador. memory/requisitos/**/SPEC.md e gate-guarded (acorda anchor-lint/distiller).
    ['tombstone-particao-separa-nao-relinkavel', (() => {
      const { immutable, mutable } = partitionForTombstone([
        { file: 'memory/decisions/0001-x.md' }, { file: 'README.md' }, { file: 'memory/sessions/2026-01-01-y.md' },
        { file: 'memory/requisitos/Financeiro/SPEC.md' }, { file: 'memory/reference/z.md' },
      ]);
      return immutable.length === 3 && mutable.length === 2 && mutable.every((r) => r.file === 'README.md' || r.file === 'memory/reference/z.md');
    })()],
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
  const tombstone = args.includes('--tombstone');
  if (dir || batch) {
    const sources = dir
      ? tracked().filter((p) => p.startsWith(dir.replace(/\/?$/, '/')) && p.endsWith('.md'))
      : batch.split(',').map((s) => s.trim()).filter(Boolean);
    if (!sources.length) throw new Error(`nenhum .md versionado em ${dir || batch}`);
    const plan = buildBatchPlan(sources, { tombstone });
    console.log(JSON.stringify(args.includes('--validate') ? { plan, adversary: validatePlanAtRoot(plan, ROOT) } : plan, null, 2));
    return;
  }
  const source = args[args.indexOf('--source') + 1];
  if (!source || args.indexOf('--source') < 0) throw new Error('uso: --source <arquivo.md> [--target <destino.md>] [--tombstone] | --dir <prefixo> | --batch a,b,c [--tombstone] [--validate]');
  const targetIndex = args.indexOf('--target');
  const plan = buildPlan(source, { targetOverride: targetIndex >= 0 ? args[targetIndex + 1] : undefined, tombstone });
  if (plan.already_canonical) { console.log(JSON.stringify(plan, null, 2)); return; }
  console.log(JSON.stringify(args.includes('--validate') ? { plan, adversary: validatePlanAtRoot(plan, ROOT) } : plan, null, 2));
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try { main(); } catch (error) { console.error(`CLASSIFIER ERROR: ${error.message}`); process.exit(2); }
}
