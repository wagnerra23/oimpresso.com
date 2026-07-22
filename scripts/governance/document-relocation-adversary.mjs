#!/usr/bin/env node
// Validador read-only de planos de realocacao documental.
//
// O classificador propoe. Este adversario tenta reprovar o plano ANTES de qualquer
// `git mv`: historia imutavel, portas conhecidas, gerados, colisao, placement errado,
// SHA velho e relinks incompletos. Nao escreve no repositorio e nao executa movimentos.
// Node puro, sem rede/DB/dependencias.
//
// Uso:
//   node scripts/governance/document-relocation-adversary.mjs --plan plano.json
//   node scripts/governance/document-relocation-adversary.mjs --plan plano.json --json
//   node scripts/governance/document-relocation-adversary.mjs --selftest

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFileSync, readdirSync, realpathSync } from 'node:fs';
import { join, posix, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const SCHEMA_VERSION = 2;
export const MIN_AUTO_CONFIDENCE = 0.9;
// Revisores humanos autorizados (memory/regras-time.md). A aprovacao so vale
// amarrada ao hash do plano (approvalDigest) — nunca campo solto auto-declarado.
export const REVIEWERS = new Set(['W', 'F', 'M', 'L', 'E']);
// Modulos cujo conteudo e IA-OS (processo), nao produto — fonte unica, o classificador importa.
export const IA_OS_MODULES = new Set(['ADS', 'Brief', 'Governance', 'KB', 'MemCofre', 'TeamMcp']);
const DOOR_CONSTITUICAO = 'memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md';

const ALLOWED_KINDS = new Set([
  'tutorial', 'how-to', 'reference', 'explanation', 'briefing', 'runbook',
  'audit', 'research', 'other', 'decision', 'session', 'handoff',
]);
const ALLOWED_LIFECYCLES = new Set(['active', 'historical', 'archived']);
// 'business-knowledge' = corpus (a) da ADR 0334 (dominios/ + clientes/): conhecimento
// do negocio que serve ERP e Jana — nao e produto nem processo. Ratificacao [W] no merge.
const ALLOWED_LAYERS = new Set(['product-erp', 'product-ai', 'ia-os', 'business-knowledge']);
const ALLOWED_REF_KINDS = new Set(['markdown-link', 'code-span', 'literal-path']);
const SKIP_DIRS = new Set(['.git', '.worktrees', 'node_modules', 'vendor']);
const TEXT_EXTENSIONS = /\.(?:cjs|css|html|js|json|jsx|md|mjs|php|ps1|py|sh|toml|ts|tsx|txt|xml|ya?ml)$/i;

const PROTECTED_EXACT = new Set([
  'README.md', 'AGENTS.md', 'CLAUDE.md', 'DESIGN.md', 'INFRA.md', 'TEAM.md',
  'CODE_NOTES.md', 'MEMORY_TEAM_ONBOARDING.md', 'memory/proibicoes.md',
  'memory/08-handoff.md', 'memory/INDEX.md', 'memory/what-oimpresso.md',
  'memory/governance/AUTOMATIONS.md',
]);

const posixPath = (value) => String(value ?? '').replace(/\\/g, '/');
const issue = (severity, code, message, operation = null, evidence = null) => ({
  severity, code, message, ...(operation == null ? {} : { operation }),
  ...(evidence == null ? {} : { evidence }),
});

function normalizePlanPath(value) {
  if (typeof value !== 'string' || value.trim() === '') return null;
  const raw = value.trim();
  if (raw.includes('\\') || /^[a-zA-Z]:/.test(raw) || raw.startsWith('/')) return null;
  const normalized = posix.normalize(raw);
  if (normalized === '.' || normalized === '..' || normalized.startsWith('../')) return null;
  return normalized;
}

function splitDestination(raw) {
  let value = String(raw ?? '').trim();
  if (value.startsWith('<')) {
    const end = value.indexOf('>');
    value = end >= 0 ? value.slice(1, end) : value;
  } else {
    value = value.split(/\s+["']/)[0];
  }
  const hashAt = value.indexOf('#');
  const queryAt = value.indexOf('?');
  const cut = [hashAt, queryAt].filter((n) => n >= 0).sort((a, b) => a - b)[0] ?? value.length;
  const path = value.slice(0, cut);
  const fragment = hashAt >= 0 ? value.slice(hashAt + 1).split('?')[0] : '';
  return { path, fragment };
}

function isExternal(value) {
  return /^(?:[a-z][a-z0-9+.-]*:|#|\/\/)/i.test(String(value).trim());
}

export function resolveReference(fromFile, rawDestination) {
  if (!rawDestination || isExternal(rawDestination)) return null;
  const { path } = splitDestination(rawDestination);
  if (!path) return null;
  let decoded;
  try { decoded = decodeURIComponent(path); } catch { decoded = path; }
  const rootRelative = decoded.startsWith('/');
  const candidate = rootRelative
    ? posix.normalize(decoded.slice(1))
    : posix.normalize(posix.join(posix.dirname(fromFile), decoded));
  if (candidate === '..' || candidate.startsWith('../')) return null;
  return candidate;
}

function referenceCandidates(fromFile, rawDestination, kind) {
  const candidates = new Set();
  const relative = resolveReference(fromFile, rawDestination);
  if (relative) candidates.add(relative);
  // Code-spans e strings de config deste repo normalmente usam paths a partir da raiz,
  // mesmo quando aparecem em memory/**. Markdown-link continua obedecendo semantica relativa.
  if (kind === 'code-span' || kind === 'literal-path') {
    const { path } = splitDestination(rawDestination);
    let decoded;
    try { decoded = decodeURIComponent(path); } catch { decoded = path; }
    const rootPath = normalizePlanPath(decoded);
    if (rootPath) candidates.add(rootPath);
  }
  return [...candidates];
}

export function extractDocumentReferences(content, file) {
  const refs = [];
  const seen = new Set();
  const add = (kind, raw) => {
    for (const target of referenceCandidates(file, raw, kind)) {
      if (!target.toLowerCase().endsWith('.md')) continue;
      const key = `${kind}\u0000${raw}\u0000${target}`;
      if (seen.has(key)) continue;
      seen.add(key);
      refs.push({ file, kind, raw, target, fragment: splitDestination(raw).fragment });
    }
  };

  for (const match of content.matchAll(/!?\[[^\]]*\]\(([^)]+)\)/g)) add('markdown-link', match[1].trim());
  for (const match of content.matchAll(/`([^`\r\n]+)`/g)) add('code-span', match[1].trim());
  return refs;
}

export function extractLiteralReferences(content, file, sources, structured = []) {
  const refs = [];
  for (const source of sources) {
    if (!content.includes(source)) continue;
    // Se o mesmo path ja foi entendido como link/code-span, um unico rewrite textual
    // naquele arquivo cobre as ocorrencias iguais; nao exige duas declaracoes cerimoniais.
    const alreadyStructured = structured.some((ref) => ref.target.toLowerCase() === source.toLowerCase() && ref.raw.includes(source));
    if (alreadyStructured) continue;
    refs.push({ file, kind: 'literal-path', raw: source, target: source, fragment: '' });
  }
  return refs;
}

export function collectMarkdownFiles(root) {
  const out = [];
  const walk = (absolute, rel = '') => {
    for (const entry of readdirSync(absolute, { withFileTypes: true })) {
      if (entry.isDirectory() && (SKIP_DIRS.has(entry.name) || (rel === '.claude' && entry.name === 'worktrees'))) continue;
      const childRel = rel ? `${rel}/${entry.name}` : entry.name;
      const childAbs = join(absolute, entry.name);
      if (entry.isDirectory()) walk(childAbs, childRel);
      else if (entry.isFile() && entry.name.toLowerCase().endsWith('.md')) out.push(posixPath(childRel));
    }
  };
  walk(root);
  return out.sort();
}

export function collectIncomingReferences(root, markdownFiles, sources, textFiles = []) {
  const wanted = new Set(sources.map((p) => p.toLowerCase()));
  const bySource = new Map(sources.map((p) => [p.toLowerCase(), []]));
  for (const file of markdownFiles) {
    let content;
    try { content = readFileSync(join(root, file), 'utf8'); } catch { continue; }
    const structured = extractDocumentReferences(content, file);
    for (const ref of [...structured, ...extractLiteralReferences(content, file, sources, structured)]) {
      const key = ref.target.toLowerCase();
      if (wanted.has(key)) bySource.get(key).push(ref);
    }
  }
  // Geradores, configs e scripts podem descobrir documentos por string literal. Escaneia
  // somente arquivos textuais versionados e nao duplica o que o parser Markdown ja cobre.
  const markdownLower = new Set(markdownFiles.map((p) => p.toLowerCase()));
  for (const file of textFiles.filter((p) => TEXT_EXTENSIONS.test(p) && !markdownLower.has(p.toLowerCase()))) {
    let content;
    try { content = readFileSync(join(root, file), 'utf8'); } catch { continue; }
    for (const ref of extractLiteralReferences(content, file, sources)) bySource.get(ref.target.toLowerCase()).push(ref);
  }
  return bySource;
}

function protectedReason(path) {
  if (PROTECTED_EXACT.has(path)) return 'porta ou contrato conhecido de caminho estavel';
  if (/^memory\/(?:decisions|sessions|handoffs)\//.test(path)) return 'historico append-only/decisao imutavel';
  if (/^(?:\.claude|\.github)\//.test(path)) return 'contrato de automacao por caminho';
  if (/(?:^|\/)AGENTS\.md$/.test(path)) return 'instrucao de agente descoberta por caminho';
  if (/^memory\/requisitos\/[^/]+\/BRIEFING\.md$/.test(path)) return 'porta unica do modulo (ADR 0270)';
  if (/^Modules\/[^/]+\/SCOPE\.md$/.test(path)) return 'contrato de catalogo do modulo';
  return null;
}

function isImmutableHistory(path) {
  return /^memory\/(?:decisions|sessions|handoffs)\//.test(path);
}

function looksGenerated(path, content) {
  const name = posix.basename(path).toUpperCase();
  if (name === 'SUPERFICIE.MD' || name.includes('-GENERATED')) return true;
  const header = content.slice(0, 2500);
  if (/(?:AUTO[- ]GENERATED|DO NOT EDIT)/i.test(header)) return true;
  if (/(?:GERADO|GERADA)[^\n]{0,160}(?:N[ÃA]O EDITAR|n[aã]o editar)/i.test(header)) return true;
  if (/arquivo gerado automaticamente por `?module:requirements`?/i.test(content)) return true;
  return false;
}

function expectedPrefix(owner) {
  if (owner === 'reference') return 'memory/reference/';
  if (owner === 'governance') return 'memory/governance/';
  if (owner === 'research') return 'memory/research/';
  if (owner === 'audit') return 'memory/audits/';
  if (owner === 'domain') return 'memory/dominios/';
  if (owner === 'client') return 'memory/clientes/';
  if (typeof owner === 'string' && owner.startsWith('module:')) {
    const moduleName = owner.slice('module:'.length);
    if (/^[A-Za-z][A-Za-z0-9_-]*$/.test(moduleName)) return `memory/requisitos/${moduleName}/`;
  }
  return null;
}

// Registro canonico owner -> {layer, door}. Fonte unica da matriz owner x layer x door;
// o classificador importa daqui e o adversario valida a COMBINACAO, nao campos isolados.
export function ownerRules(owner, target) {
  if (['reference', 'governance', 'research', 'audit'].includes(owner)) {
    return { layer: 'ia-os', door: DOOR_CONSTITUICAO };
  }
  if (owner === 'domain') return { layer: 'business-knowledge', door: 'memory/dominios/_overview.md' };
  if (owner === 'client') {
    const m = typeof target === 'string' ? target.match(/^memory\/clientes\/([^/]+)\//) : null;
    // Porta do cliente = PERFIL.md dele; sem PERFIL o plano nao aprova (porta antes do move).
    return { layer: 'business-knowledge', door: m ? `memory/clientes/${m[1]}/PERFIL.md` : null };
  }
  if (typeof owner === 'string' && owner.startsWith('module:')) {
    const name = owner.slice('module:'.length);
    if (name === 'Jana') return { layer: 'product-ai', door: 'memory/requisitos/Jana/BRIEFING.md' };
    if (IA_OS_MODULES.has(name)) return { layer: 'ia-os', door: DOOR_CONSTITUICAO };
    return { layer: 'product-erp', door: `memory/requisitos/${name}/BRIEFING.md` };
  }
  return null;
}

function validateClassification(op, index, existingLower, issues) {
  const c = op.classification;
  if (!c || typeof c !== 'object' || Array.isArray(c)) {
    issues.push(issue('error', 'CLASSIFICATION_REQUIRED', 'classification e obrigatoria', index));
    return;
  }
  if (!ALLOWED_KINDS.has(c.kind)) issues.push(issue('error', 'KIND_INVALID', `kind invalido: ${c.kind ?? '(ausente)'}`, index));
  if (!ALLOWED_LIFECYCLES.has(c.lifecycle)) issues.push(issue('error', 'LIFECYCLE_INVALID', `lifecycle invalido: ${c.lifecycle ?? '(ausente)'}`, index));
  if (!ALLOWED_LAYERS.has(c.layer)) issues.push(issue('error', 'LAYER_INVALID', `layer invalida: ${c.layer ?? '(ausente)'}; use o canon da ADR 0334`, index));
  if (typeof c.door !== 'string' || !c.door.endsWith('.md')) {
    issues.push(issue('error', 'CANONICAL_DOOR_REQUIRED', 'door deve apontar para a porta-mae .md da camada', index));
  } else if (!existingLower.has(c.door.toLowerCase())) {
    issues.push(issue('error', 'CANONICAL_DOOR_MISSING', `porta-mae nao existe: ${c.door}`, index));
  }
  if (typeof c.slug !== 'string' || !/^[a-z0-9][a-z0-9-]*$/.test(c.slug)) {
    issues.push(issue('error', 'SLUG_INVALID', 'slug deve estar em kebab-case', index));
  }
  const prefix = expectedPrefix(c.owner);
  if (!prefix) issues.push(issue('error', 'OWNER_INVALID', `owner invalido: ${c.owner ?? '(ausente)'}`, index));
  else if (op.target && !op.target.startsWith(prefix)) {
    issues.push(issue('error', 'OWNER_TARGET_MISMATCH', `owner ${c.owner} exige destino sob ${prefix}`, index, op.target));
  }
  // Matriz owner x layer x door: cada campo isolado pode ser valido e a COMBINACAO errada
  // (review 2026-07-22: owner=governance + layer=product-erp + door=Financeiro passava).
  const rules = ownerRules(c.owner, op.target);
  if (rules && ALLOWED_LAYERS.has(c.layer) && c.layer !== rules.layer) {
    issues.push(issue('error', 'LAYER_OWNER_MISMATCH', `owner ${c.owner} pertence a camada ${rules.layer}, nao ${c.layer}`, index));
  }
  if (rules && rules.door && typeof c.door === 'string' && c.door.toLowerCase() !== rules.door.toLowerCase()) {
    issues.push(issue('error', 'DOOR_OWNER_MISMATCH', `owner ${c.owner} exige porta-mae ${rules.door}`, index, c.door));
  }
  if (typeof c.owner === 'string' && c.owner.startsWith('module:') && prefix) {
    const moduleDir = prefix.slice(0, -1).toLowerCase();
    const exists = [...existingLower].some((p) => p.startsWith(`${moduleDir}/`));
    if (!exists) issues.push(issue('error', 'UNKNOWN_MODULE_OWNER', `modulo do owner nao existe: ${c.owner}`, index));
  }
  if (['decision', 'session', 'handoff'].includes(c.kind)) {
    issues.push(issue('error', 'IMMUTABLE_KIND', `a maquina nao realoca documentos do tipo ${c.kind}`, index));
  }
  if (c.kind === 'briefing' && (!op.target || posix.basename(op.target) !== 'BRIEFING.md' || !String(c.owner).startsWith('module:'))) {
    issues.push(issue('error', 'BRIEFING_PLACEMENT', 'briefing exige owner module:<Nome> e destino BRIEFING.md', index));
  }
  if (c.kind === 'runbook' && op.target && !posix.basename(op.target).startsWith('RUNBOOK-')) {
    issues.push(issue('error', 'RUNBOOK_NAMING', 'runbook deve preservar o prefixo RUNBOOK-', index, op.target));
  }
  if (c.kind === 'audit' && c.owner !== 'audit') issues.push(issue('error', 'AUDIT_OWNER', 'kind audit exige owner audit', index));
  if (c.kind === 'research' && c.owner !== 'research') issues.push(issue('error', 'RESEARCH_OWNER', 'kind research exige owner research', index));
}

function validateRewrites(op, index, refs, finalPaths, issues) {
  if (!Array.isArray(op.rewrites)) {
    issues.push(issue('error', 'REWRITES_REQUIRED', 'rewrites deve ser um array, inclusive quando vazio', index));
    return;
  }
  const actual = new Map(refs.map((r) => [`${r.file}\u0000${r.kind}\u0000${r.raw}`, r]));
  const declared = new Map();
  for (const [rewriteIndex, rewrite] of op.rewrites.entries()) {
    const key = `${rewrite?.file}\u0000${rewrite?.kind}\u0000${rewrite?.from}`;
    if (!rewrite || typeof rewrite !== 'object') {
      issues.push(issue('error', 'REWRITE_INVALID', `rewrite ${rewriteIndex} invalido`, index));
      continue;
    }
    if (!ALLOWED_REF_KINDS.has(rewrite.kind)) issues.push(issue('error', 'REWRITE_KIND_INVALID', `kind invalido no rewrite ${rewriteIndex}`, index));
    if (declared.has(key)) issues.push(issue('error', 'DUPLICATE_REWRITE', `rewrite duplicado: ${rewrite.file} -> ${rewrite.from}`, index));
    declared.set(key, rewrite);
    const found = actual.get(key);
    if (!found) {
      issues.push(issue('error', 'REWRITE_NOT_FOUND', 'rewrite declarado nao corresponde a referencia real', index, rewrite));
      continue;
    }
    if (isImmutableHistory(rewrite.file)) {
      issues.push(issue('error', 'IMMUTABLE_REFERRER', 'relink exigiria editar ADR/session/handoff append-only; preserve o path ou retire a operacao', index, rewrite.file));
    }
    const expectedFrom = found.expectedFrom ?? op.source;
    const expectedTo = found.expectedTo ?? op.target;
    if (!referenceCandidates(rewrite.file, rewrite.from, rewrite.kind).some((p) => p.toLowerCase() === expectedFrom.toLowerCase())) {
      issues.push(issue('error', 'REWRITE_FROM_WRONG_SOURCE', `from nao resolve para ${expectedFrom}`, index, rewrite));
    }
    const finalRefFile = finalPaths.get(rewrite.file.toLowerCase()) ?? rewrite.file;
    if (!referenceCandidates(finalRefFile, rewrite.to, rewrite.kind).some((p) => p.toLowerCase() === expectedTo.toLowerCase())) {
      issues.push(issue('error', 'REWRITE_TO_WRONG_TARGET', `to nao resolve para ${expectedTo} a partir de ${finalRefFile}`, index, rewrite));
    }
    if (splitDestination(rewrite.from).fragment !== splitDestination(rewrite.to).fragment) {
      issues.push(issue('error', 'ANCHOR_NOT_PRESERVED', 'relink alterou/removeu a ancora', index, rewrite));
    }
  }
  for (const [key, ref] of actual) {
    if (!declared.has(key)) issues.push(issue('error', 'MISSING_REWRITE', 'referencia de entrada ficou fora do plano', index, ref));
  }
}

// Hash canonico do plano SEM approvals/generated_at: e o que o revisor humano assina.
// Editar qualquer operacao invalida a assinatura — o dente e o hash, nao o campo.
export function approvalDigest(plan) {
  const strip = (value) => {
    if (Array.isArray(value)) return value.map(strip);
    if (!value || typeof value !== 'object') return value;
    return Object.fromEntries(Object.entries(value)
      .filter(([key]) => key !== 'approvals' && key !== 'generated_at')
      .sort(([left], [right]) => left.localeCompare(right))
      .map(([key, nested]) => [key, strip(nested)]));
  };
  return createHash('sha256').update(JSON.stringify(strip(plan))).digest('hex');
}

export function validatePlan(plan, context) {
  const issues = [];
  const existingFiles = (context.existingFiles ?? []).map(posixPath);
  const existingLower = new Set(existingFiles.map((p) => p.toLowerCase()));
  const trackedLower = new Set((context.trackedFiles ?? existingFiles).map((p) => posixPath(p).toLowerCase()));
  const readSource = context.readSource ?? ((path) => readFileSync(join(context.root, path), 'utf8'));

  if (!plan || typeof plan !== 'object' || Array.isArray(plan)) {
    return { verdict: 'REJECT', safe_to_apply: false, summary: { operations: 0, errors: 1, reviews: 0 }, issues: [issue('error', 'PLAN_INVALID', 'plano deve ser um objeto JSON')] };
  }
  if (plan.schema_version !== SCHEMA_VERSION) issues.push(issue('error', 'SCHEMA_VERSION', `schema_version deve ser ${SCHEMA_VERSION}`));
  if (typeof plan.base_sha !== 'string' || !/^[0-9a-f]{40}$/i.test(plan.base_sha)) {
    issues.push(issue('error', 'BASE_SHA_REQUIRED', 'base_sha completo (40 hex) e obrigatorio'));
  } else if (context.currentSha && plan.base_sha.toLowerCase() !== context.currentSha.toLowerCase()) {
    issues.push(issue('error', 'STALE_PLAN', 'base_sha difere do HEAD; reclassifique antes de mover', null, { base_sha: plan.base_sha, head: context.currentSha }));
  }
  if (!Array.isArray(plan.operations) || plan.operations.length === 0) {
    issues.push(issue('error', 'OPERATIONS_REQUIRED', 'operations deve conter ao menos uma operacao'));
  }

  // Aprovacao humana verificavel: reviewer autorizado + sha256 do plano canonico.
  // Aprovacao presente mas invalida e ERRO ruidoso (nunca silenciosamente ignorada).
  let approvedByHuman = false;
  const approvals = plan.approvals ?? [];
  if (!Array.isArray(approvals)) {
    issues.push(issue('error', 'APPROVAL_INVALID', 'approvals deve ser um array de {reviewer, plan_sha256, date}'));
  } else {
    const digest = approvalDigest(plan);
    for (const entry of approvals) {
      const okReviewer = REVIEWERS.has(entry?.reviewer);
      const okDigest = typeof entry?.plan_sha256 === 'string' && entry.plan_sha256.toLowerCase() === digest;
      if (okReviewer && okDigest) approvedByHuman = true;
      else issues.push(issue('error', 'APPROVAL_INVALID', `aprovacao invalida: reviewer ${entry?.reviewer ?? '(ausente)'} nao autorizado ou hash nao corresponde ao plano (esperado ${digest})`, null, entry));
    }
  }

  const operations = Array.isArray(plan.operations) ? plan.operations : [];
  const seenSources = new Set();
  const seenTargets = new Set();
  const finalPaths = new Map();
  const sourceContents = new Map();
  const normalized = operations.map((raw, index) => {
    const source = normalizePlanPath(raw?.source);
    const target = normalizePlanPath(raw?.target);
    const op = { ...(raw && typeof raw === 'object' ? raw : {}), source, target };
    if (!source) issues.push(issue('error', 'SOURCE_PATH_INVALID', 'source deve ser relativo, posix e sem traversal', index, raw?.source));
    if (!target) issues.push(issue('error', 'TARGET_PATH_INVALID', 'target deve ser relativo, posix e sem traversal', index, raw?.target));
    if (source && target) finalPaths.set(source.toLowerCase(), target);
    return op;
  });

  for (const [index, op] of normalized.entries()) {
    if (!op.source || !op.target) continue;
    const sourceLower = op.source.toLowerCase();
    const targetLower = op.target.toLowerCase();
    if (!op.source.toLowerCase().endsWith('.md') || !op.target.toLowerCase().endsWith('.md')) {
      issues.push(issue('error', 'MARKDOWN_ONLY', 'source e target devem ser .md', index));
    }
    if (sourceLower === targetLower) issues.push(issue('error', 'NOOP_OR_CASE_ONLY', 'movimento vazio ou apenas troca de caixa nao e seguro', index));
    if (seenSources.has(sourceLower)) issues.push(issue('error', 'DUPLICATE_SOURCE', `source repetido: ${op.source}`, index));
    if (seenTargets.has(targetLower)) issues.push(issue('error', 'DUPLICATE_TARGET', `target repetido: ${op.target}`, index));
    seenSources.add(sourceLower);
    seenTargets.add(targetLower);
    if (!existingLower.has(sourceLower)) issues.push(issue('error', 'SOURCE_MISSING', `source nao existe: ${op.source}`, index));
    if (!trackedLower.has(sourceLower)) issues.push(issue('error', 'SOURCE_UNTRACKED', `source nao e versionado: ${op.source}`, index));
    if (existingLower.has(targetLower)) issues.push(issue('error', 'TARGET_COLLISION', `target ja existe (inclusive por caixa): ${op.target}`, index));
    const targetParent = posix.dirname(op.target).toLowerCase();
    if (targetParent !== '.' && ![...existingLower].some((path) => path.startsWith(`${targetParent}/`))) {
      issues.push(issue('error', 'TARGET_PARENT_MISSING', `diretorio de destino nao existe: ${posix.dirname(op.target)}`, index));
    }
    const protectedBy = protectedReason(op.source);
    if (protectedBy) issues.push(issue('error', 'PROTECTED_SOURCE', `${op.source}: ${protectedBy}`, index));
    if (/^memory\/(?:decisions|sessions|handoffs)\//.test(op.target)) {
      issues.push(issue('error', 'PROTECTED_TARGET', 'a maquina nao promove/move documentos para historico imutavel', index, op.target));
    }
    let content = '';
    try { content = readSource(op.source); } catch (error) {
      issues.push(issue('error', 'SOURCE_UNREADABLE', `nao foi possivel ler source: ${error.message}`, index));
    }
    sourceContents.set(sourceLower, content);
    if (content && looksGenerated(op.source, content)) issues.push(issue('error', 'GENERATED_ARTIFACT', 'artefato gerado deve ser movido pela fonte geradora, nunca pelo realocador', index, op.source));
    if (typeof op.reason !== 'string' || op.reason.trim().length < 20) {
      issues.push(issue('error', 'REASON_WEAK', 'reason deve explicar a decisao em pelo menos 20 caracteres', index));
    }
    if (typeof op.confidence !== 'number' || op.confidence < 0 || op.confidence > 1) {
      issues.push(issue('error', 'CONFIDENCE_INVALID', 'confidence deve estar entre 0 e 1', index));
    } else if (op.confidence < MIN_AUTO_CONFIDENCE && !approvedByHuman) {
      issues.push(issue('review', 'LOW_CONFIDENCE', `confidence ${op.confidence} < ${MIN_AUTO_CONFIDENCE}; exige aprovacao humana assinada (approvals + approvalDigest), nunca auto-apply`, index));
    }
    validateClassification(op, index, existingLower, issues);
  }

  const incoming = context.incomingReferences ?? collectIncomingReferences(
    context.root,
    context.markdownFiles ?? existingFiles.filter((p) => p.toLowerCase().endsWith('.md')),
    normalized.filter((o) => o.source).map((o) => o.source),
    context.textFiles ?? context.trackedFiles ?? [],
  );
  for (const [index, op] of normalized.entries()) {
    if (!op.source || !op.target) continue;
    const inbound = (incoming.get(op.source.toLowerCase()) ?? []).map((ref) => ({
      ...ref,
      role: 'inbound',
      expectedFrom: op.source,
      expectedTo: op.target,
    }));
    const content = sourceContents.get(op.source.toLowerCase()) ?? '';
    const structured = extractDocumentReferences(content, op.source);
    const outboundCandidates = [
      ...structured,
      ...extractLiteralReferences(content, op.source, existingFiles, structured),
    ];
    const outbound = [];
    for (const ref of outboundCandidates) {
      if (!existingLower.has(ref.target.toLowerCase())) continue;
      const finalTarget = finalPaths.get(ref.target.toLowerCase()) ?? ref.target;
      const stillValid = referenceCandidates(op.target, ref.raw, ref.kind)
        .some((candidate) => candidate.toLowerCase() === finalTarget.toLowerCase());
      if (stillValid) continue;
      outbound.push({
        ...ref,
        role: 'outbound',
        expectedFrom: ref.target,
        expectedTo: finalTarget,
      });
    }
    const required = new Map();
    for (const ref of [...inbound, ...outbound]) {
      const key = `${ref.file}\u0000${ref.kind}\u0000${ref.raw}`;
      const prior = required.get(key);
      if (prior && (prior.expectedFrom !== ref.expectedFrom || prior.expectedTo !== ref.expectedTo)) {
        issues.push(issue('error', 'AMBIGUOUS_REWRITE', 'a mesma referencia exigiria dois destinos diferentes', index, { prior, current: ref }));
      } else required.set(key, ref);
    }
    validateRewrites(op, index, [...required.values()], finalPaths, issues);
  }

  const errors = issues.filter((i) => i.severity === 'error').length;
  const reviews = issues.filter((i) => i.severity === 'review').length;
  const verdict = errors ? 'REJECT' : (reviews ? 'REVIEW' : 'APPROVE');
  return {
    verdict,
    safe_to_apply: verdict === 'APPROVE',
    summary: { operations: operations.length, errors, reviews },
    issues,
  };
}

function git(root, args) {
  return execFileSync('git', ['-C', root, ...args], { encoding: 'utf8' }).trim();
}

export function validatePlanAtRoot(plan, root = process.cwd()) {
  const repo = resolve(root);
  const currentSha = git(repo, ['rev-parse', 'HEAD']);
  const trackedFiles = git(repo, ['ls-files', '-z']).split('\0').filter(Boolean).map(posixPath);
  const markdownFiles = collectMarkdownFiles(repo);
  return validatePlan(plan, {
    root: repo, currentSha, trackedFiles, existingFiles: markdownFiles,
    markdownFiles, textFiles: trackedFiles,
  });
}

function runSelftest() {
  const sha = 'a'.repeat(40);
  const files = new Map([
    ['docs/legacy.md', '# Guia legado\n\n## Uso\n\n[Canon](../memory/reference/existing.md#regra)\n'],
    ['README.md', '[Guia](docs/legacy.md#uso)\n'],
    ['docs/ops.md', 'Consulte `legacy.md#uso`.\n'],
    ['docs/generated.md', '<!-- GERADO por scripts/x.mjs. NAO EDITAR A MAO. -->\n'],
    ['memory/decisions/0001-regra.md', '# ADR\n'],
    ['memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md', '# Constituicao\n'],
    ['memory/reference/existing.md', '# Existe\n'],
    ['memory/requisitos/Financeiro/BRIEFING.md', '# Porta\n'],
    ['memory/governance/ENFORCEMENT.md', '# Governanca\n'],
    ['memory/dominios/_overview.md', '# Dominios\n'],
    ['docs/fiscal-notas.md', '# Regras fiscais do dominio\n'],
  ]);
  const context = {
    currentSha: sha,
    existingFiles: [...files.keys()],
    trackedFiles: [...files.keys()],
    readSource: (path) => {
      if (!files.has(path)) throw new Error('ausente');
      return files.get(path);
    },
  };
  const incoming = new Map([['docs/legacy.md', [
    ...extractDocumentReferences(files.get('README.md'), 'README.md'),
    ...extractDocumentReferences(files.get('docs/ops.md'), 'docs/ops.md'),
  ].filter((r) => r.target === 'docs/legacy.md')]]);
  const base = {
    schema_version: 2,
    base_sha: sha,
    operations: [{
      source: 'docs/legacy.md',
      target: 'memory/reference/guia-legado.md',
      classification: {
        kind: 'how-to', owner: 'reference', lifecycle: 'active', slug: 'guia-legado',
        layer: 'ia-os', door: 'memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md',
      },
      confidence: 0.98,
      reason: 'Guia transversal deve viver junto das referencias tecnicas.',
      rewrites: [
        { file: 'README.md', kind: 'markdown-link', from: 'docs/legacy.md#uso', to: 'memory/reference/guia-legado.md#uso' },
        { file: 'docs/ops.md', kind: 'code-span', from: 'legacy.md#uso', to: '../memory/reference/guia-legado.md#uso' },
        { file: 'docs/legacy.md', kind: 'markdown-link', from: '../memory/reference/existing.md#regra', to: 'existing.md#regra' },
      ],
    }],
  };
  const clone = (value) => JSON.parse(JSON.stringify(value));
  const evaluate = (plan, extra = {}) => validatePlan(plan, { ...context, incomingReferences: incoming, ...extra });
  const cases = [];
  const check = (name, condition, evidence) => cases.push({ name, ok: Boolean(condition), evidence });

  const good = evaluate(base);
  check('SOLTA: plano completo e coerente', good.verdict === 'APPROVE', good);
  check('ENXERGA: code-span raiz citado de dentro de memory/**', extractDocumentReferences('Veja `ROOT.md`.', 'memory/x.md').some((r) => r.target === 'ROOT.md'), extractDocumentReferences('Veja `ROOT.md`.', 'memory/x.md'));
  check('ENXERGA: path literal fora de Markdown', extractLiteralReferences("const doc = 'ROOT.md'", 'scripts/x.mjs', ['ROOT.md']).some((r) => r.kind === 'literal-path'), extractLiteralReferences("const doc = 'ROOT.md'", 'scripts/x.mjs', ['ROOT.md']));
  const missing = clone(base); missing.operations[0].rewrites.pop();
  check('MORDE: link de saida quebraria apos o move', evaluate(missing).issues.some((i) => i.code === 'MISSING_REWRITE'), evaluate(missing));
  const missingInbound = clone(base); missingInbound.operations[0].rewrites.shift();
  check('MORDE: backlink omitido', evaluate(missingInbound).issues.some((i) => i.code === 'MISSING_REWRITE'), evaluate(missingInbound));
  const anchor = clone(base); anchor.operations[0].rewrites[0].to = 'memory/reference/guia-legado.md';
  check('MORDE: ancora perdida', evaluate(anchor).issues.some((i) => i.code === 'ANCHOR_NOT_PRESERVED'), evaluate(anchor));
  const stale = clone(base); stale.base_sha = 'b'.repeat(40);
  check('MORDE: plano stale', evaluate(stale).issues.some((i) => i.code === 'STALE_PLAN'), evaluate(stale));
  const collision = clone(base); collision.operations[0].target = 'memory/reference/existing.md';
  check('MORDE: colisao de destino', evaluate(collision).issues.some((i) => i.code === 'TARGET_COLLISION'), evaluate(collision));
  const traversal = clone(base); traversal.operations[0].target = '../fora.md';
  check('MORDE: path traversal', evaluate(traversal).issues.some((i) => i.code === 'TARGET_PATH_INVALID'), evaluate(traversal));
  const missingParent = clone(base); missingParent.operations[0].target = 'memory/reference/nova-pasta/guia.md';
  check('MORDE: diretorio de destino inexistente', evaluate(missingParent).issues.some((i) => i.code === 'TARGET_PARENT_MISSING'), evaluate(missingParent));
  const low = clone(base); low.operations[0].confidence = 0.7;
  check('SEGURA: baixa confianca vira REVIEW e nunca auto-apply', evaluate(low).verdict === 'REVIEW' && !evaluate(low).safe_to_apply, evaluate(low));
  const decision = clone(base); decision.operations[0].source = 'memory/decisions/0001-regra.md'; decision.operations[0].rewrites = [];
  const decisionResult = evaluate(decision, { incomingReferences: new Map([['memory/decisions/0001-regra.md', []]]) });
  check('MORDE: historico imutavel', decisionResult.issues.some((i) => i.code === 'PROTECTED_SOURCE'), decisionResult);
  const generated = clone(base); generated.operations[0].source = 'docs/generated.md'; generated.operations[0].rewrites = [];
  const generatedResult = evaluate(generated, { incomingReferences: new Map([['docs/generated.md', []]]) });
  check('MORDE: artefato gerado', generatedResult.issues.some((i) => i.code === 'GENERATED_ARTIFACT'), generatedResult);
  const immutableRef = clone(base);
  immutableRef.operations[0].rewrites = [{ file: 'memory/sessions/2026-01-01.md', kind: 'code-span', from: 'docs/legacy.md', to: 'memory/reference/guia-legado.md' }];
  const immutableIncoming = new Map([['docs/legacy.md', [{ file: 'memory/sessions/2026-01-01.md', kind: 'code-span', raw: 'docs/legacy.md', target: 'docs/legacy.md', fragment: '' }]]]);
  const immutableRefResult = evaluate(immutableRef, { incomingReferences: immutableIncoming });
  check('MORDE: relink tentaria reescrever historico append-only', immutableRefResult.issues.some((i) => i.code === 'IMMUTABLE_REFERRER'), immutableRefResult);
  const placement = clone(base); placement.operations[0].classification.owner = 'governance';
  check('MORDE: owner e destino discordam', evaluate(placement).issues.some((i) => i.code === 'OWNER_TARGET_MISMATCH'), evaluate(placement));
  const noLayer = clone(base); delete noLayer.operations[0].classification.layer;
  check('MORDE: plano sem camada ADR 0334', evaluate(noLayer).issues.some((i) => i.code === 'LAYER_INVALID'), evaluate(noLayer));
  const noDoor = clone(base); delete noDoor.operations[0].classification.door;
  check('MORDE: plano sem porta-mae', evaluate(noDoor).issues.some((i) => i.code === 'CANONICAL_DOOR_REQUIRED'), evaluate(noDoor));
  // Caso exato do review 2026-07-22 que APROVAVA: owner=governance + layer=product-erp + door=Financeiro.
  const matrix = clone(base);
  matrix.operations[0].target = 'memory/governance/guia-legado.md';
  matrix.operations[0].classification.owner = 'governance';
  matrix.operations[0].classification.layer = 'product-erp';
  matrix.operations[0].classification.door = 'memory/requisitos/Financeiro/BRIEFING.md';
  matrix.operations[0].rewrites = matrix.operations[0].rewrites.map((r) => ({ ...r, to: r.to.replace('memory/reference/', 'memory/governance/') }));
  const matrixResult = evaluate(matrix);
  check('MORDE: matriz owner x layer x door incoerente', matrixResult.issues.some((i) => i.code === 'LAYER_OWNER_MISMATCH') && matrixResult.issues.some((i) => i.code === 'DOOR_OWNER_MISMATCH'), matrixResult);
  const domainGood = clone(base);
  domainGood.operations[0].source = 'docs/fiscal-notas.md';
  domainGood.operations[0].target = 'memory/dominios/fiscal-notas.md';
  domainGood.operations[0].classification = { kind: 'reference', owner: 'domain', lifecycle: 'active', slug: 'fiscal-notas', layer: 'business-knowledge', door: 'memory/dominios/_overview.md' };
  domainGood.operations[0].rewrites = [];
  const domainGoodResult = evaluate(domainGood, { incomingReferences: new Map([['docs/fiscal-notas.md', []]]) });
  check('SOLTA: owner domain coerente (corpus de negocio ADR 0334)', domainGoodResult.verdict === 'APPROVE', domainGoodResult);
  const domainWrong = clone(domainGood);
  domainWrong.operations[0].target = 'memory/reference/fiscal-notas.md';
  const domainWrongResult = evaluate(domainWrong, { incomingReferences: new Map([['docs/fiscal-notas.md', []]]) });
  check('MORDE: negocio (domain) desviado pra processo (reference)', domainWrongResult.issues.some((i) => i.code === 'OWNER_TARGET_MISMATCH'), domainWrongResult);
  const lowApproved = clone(base); lowApproved.operations[0].confidence = 0.7;
  lowApproved.approvals = [{ reviewer: 'W', date: '2026-07-22', plan_sha256: approvalDigest(lowApproved) }];
  const lowApprovedResult = evaluate(lowApproved);
  check('SOLTA: baixa confianca COM aprovacao humana assinada (hash do plano)', lowApprovedResult.verdict === 'APPROVE', lowApprovedResult);
  const badApproval = clone(base); badApproval.operations[0].confidence = 0.7;
  badApproval.approvals = [{ reviewer: 'W', date: '2026-07-22', plan_sha256: 'f'.repeat(64) }];
  const badApprovalResult = evaluate(badApproval);
  check('MORDE: aprovacao com hash que nao corresponde ao plano', badApprovalResult.issues.some((i) => i.code === 'APPROVAL_INVALID') && badApprovalResult.verdict === 'REJECT', badApprovalResult);

  for (const row of cases) console.log(`${row.ok ? '[OK]  ' : '[FAIL]'} ${row.name}`);
  const failed = cases.filter((row) => !row.ok);
  console.log(`\n${failed.length ? 'SELFTEST FALHOU' : 'SELFTEST OK'} - ${cases.length - failed.length}/${cases.length} vetores passaram; controle positivo solta e controles negativos mordem.`);
  if (failed.length) for (const row of failed) console.log(JSON.stringify(row.evidence, null, 2));
  return failed.length ? 1 : 0;
}

function printHuman(result) {
  const icon = result.verdict === 'APPROVE' ? 'OK' : (result.verdict === 'REVIEW' ? 'REVISAO' : 'REJEITADO');
  console.log(`${icon} - ${result.verdict} - ${result.summary.operations} operacao(oes), ${result.summary.errors} erro(s), ${result.summary.reviews} revisao(oes)`);
  for (const item of result.issues) {
    const op = item.operation == null ? '' : ` op#${item.operation + 1}`;
    console.log(`  [${item.severity.toUpperCase()}] ${item.code}${op}: ${item.message}`);
    if (item.evidence != null) console.log(`    ${JSON.stringify(item.evidence)}`);
  }
  if (result.safe_to_apply) console.log('  Plano liberado pelo backstop deterministico; o adversario semantico ainda deve revisar o conjunto.');
  else console.log('  NENHUM git mv deve ser executado com este veredito.');
}

const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); } catch { return false; }
})();

if (isMain) {
  const args = process.argv.slice(2);
  if (args.includes('--selftest')) process.exit(runSelftest());
  const option = (name) => { const at = args.indexOf(name); return at >= 0 ? args[at + 1] : null; };
  const planPath = option('--plan');
  const root = resolve(option('--root') ?? process.cwd());
  if (!planPath) {
    console.error('uso: document-relocation-adversary.mjs --plan <plano.json> [--root <repo>] [--json] | --selftest');
    process.exit(2);
  }
  let plan;
  try { plan = JSON.parse(readFileSync(resolve(planPath), 'utf8')); }
  catch (error) { console.error(`plano ilegivel: ${error.message}`); process.exit(2); }
  if (args.includes('--digest')) { console.log(approvalDigest(plan)); process.exit(0); }
  let result;
  try {
    result = validatePlanAtRoot(plan, root);
  } catch (error) {
    console.error(`root nao e um repositorio Git legivel: ${error.message}`);
    process.exit(2);
  }
  if (args.includes('--json')) console.log(JSON.stringify(result, null, 2));
  else printHuman(result);
  process.exit(result.safe_to_apply ? 0 : 1);
}
