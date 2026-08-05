#!/usr/bin/env node
/**
 * documentation-loop.mjs — recibo determinístico do ciclo documental.
 *
 * Este arquivo NÃO cria uma régua nova. Ele compõe três donos existentes:
 *   - memory-health.mjs                 (integridade/fatos/links)
 *   - briefing-code-staleness.mjs       (porta × código)
 *   - doc-freshness-score.mjs           (radar/priorização)
 *
 * Contrato do ciclo:
 *   snapshot ANTES -> correção no dono -> snapshot DEPOIS -> mesmo ID sumiu.
 * Tocar arquivo, aumentar parcialmente uma nota ou bumpar data não fecha recibo.
 *
 * Uso:
 *   node scripts/governance/documentation-loop.mjs --snapshot [--json]
 *   node scripts/governance/documentation-loop.mjs --compare-ref origin/main [--json]
 *   node scripts/governance/documentation-loop.mjs --compare-ref origin/main --expect <id>[,<id>]
 *   node scripts/governance/documentation-loop.mjs --impact-ref origin/main [--head-ref HEAD] [--json]
 *   acrescente --require-clean no recibo final, depois do commit
 *   node scripts/governance/documentation-loop.mjs --selftest
 *
 * O comparativo cria worktree temporária sob os.tmpdir(), mede o ref e remove a
 * worktree no finally. Nunca edita o ref nem persiste baseline auto-declarado.
 *
 * Refs: ADR 0270 (batimento/consolidação) · ADR 0314 (higiene advisory) ·
 * scripts/governance/ZELADOR.md · memory/proibicoes.md §5 (presença ≠ correção).
 */

import { createHash } from 'node:crypto';
import {
  existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, realpathSync, rmSync, writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { scan as scanBriefing, isBriefingCoverageGap } from './briefing-code-staleness.mjs';

const ROOT = process.cwd();
const JSON_OUT = process.argv.includes('--json');
const REQUIRE_CLEAN = process.argv.includes('--require-clean');
const SOURCE_PRIORITY = { 'memory-health': 0, 'briefing-code-staleness': 1, 'doc-freshness-score': 2, 'doc-nav': 3 };
const SHARED_RUNTIME_PATHS = /^(app|config|database|routes)\//;
const DOCUMENT_NAME = /^(BRIEFING|README|ARCHITECTURE|SPEC|SDD|RUNBOOK|SUPERFICIE|CHANGELOG|GLOSSARY)/i;

function stable(value) {
  if (Array.isArray(value)) return value.map(stable);
  if (!value || typeof value !== 'object') return value;
  return Object.fromEntries(Object.keys(value).sort().map((key) => [key, stable(value[key])]));
}

function digest(value) {
  return createHash('sha256').update(JSON.stringify(stable(value))).digest('hex').slice(0, 12);
}

function targetFromSample(sample) {
  if (sample == null) return { aggregate: true };
  if (typeof sample !== 'object') return { value: sample };
  const keys = ['file', 'link', 'doc', 'mod', 'module', 'screen', 'slug', 'id', 'indice', 'path', 'rel'];
  const picked = {};
  for (const key of keys) if (sample[key] != null) picked[key] = sample[key];
  return Object.keys(picked).length ? picked : stable(sample);
}

export function issueId(source, kind, target) {
  return `${source}:${kind}:${digest(target)}`;
}

function normalizeMemoryHealth(data) {
  const out = [];
  for (const [severity, rows] of [['fail', data.fails || []], ['warn', data.warns || []]]) {
    for (const row of rows) {
      const samples = Array.isArray(row.sample) && row.sample.length ? row.sample : [null];
      for (const sample of samples) {
        const target = targetFromSample(sample);
        out.push({
          id: issueId('memory-health', row.kind || row.check || 'unknown', target),
          source: 'memory-health', kind: row.kind || row.check || 'unknown', severity,
          target, metric: Number.isFinite(row.count) ? row.count : null,
          details: row.msg || '',
        });
      }
    }
  }
  return out;
}

function normalizeBriefing() {
  const rows = scanBriefing();
  const out = [];
  for (const row of rows.filter((item) => item.stale)) {
    const target = { mod: row.mod };
    out.push({
      id: issueId('briefing-code-staleness', 'porta-stale', target),
      source: 'briefing-code-staleness', kind: 'porta-stale', severity: 'warn', target,
      metric: row.gapDays ?? null,
      details: `${row.gapDays ?? '?'}d / ${row.commitsAhead ?? '?'} commits atrás do código`,
    });
  }
  for (const row of rows.filter((item) => isBriefingCoverageGap(item))) {
    const target = { mod: row.mod };
    out.push({
      id: issueId('briefing-code-staleness', 'porta-ausente', target),
      source: 'briefing-code-staleness', kind: 'porta-ausente', severity: 'warn', target,
      metric: 1, details: `módulo backend ${row.mod} sem BRIEFING.md`,
    });
  }
  return out;
}

function runJson(root, script, args) {
  const absolute = join(root, script);
  if (!existsSync(absolute)) throw new Error(`detector ausente: ${script} em ${root}`);
  const result = spawnSync(process.execPath, [absolute, ...args], {
    cwd: root, encoding: 'utf8', maxBuffer: 128 * 1024 * 1024,
    env: { ...process.env, GITHUB_STEP_SUMMARY: '' },
  });
  const raw = (result.stdout || '').trim();
  if (!raw) throw new Error(`${script} não produziu JSON (exit=${result.status}; stderr=${(result.stderr || '').trim().slice(0, 300)})`);
  try { return JSON.parse(raw); }
  catch { throw new Error(`${script} produziu JSON inválido: ${raw.slice(0, 300)}`); }
}

function normalizeFreshness(data) {
  return (data.docs || [])
    .filter((row) => Number(row.score) < 50)
    .map((row) => {
      const target = { doc: row.doc };
      return {
        id: issueId('doc-freshness-score', 'doc-podre', target),
        source: 'doc-freshness-score', kind: 'doc-podre', severity: 'warn', target,
        metric: Number(row.score),
        details: `score ${row.score}/100 · ${row.refsQuebradas || 0} refs quebradas · ${row.churnCommits || 0} commits de churn`,
      };
    });
}

function gitSha(root) {
  const result = spawnSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' });
  return result.status === 0 ? result.stdout.trim() : null;
}

function runGit(root, args) {
  const result = spawnSync('git', args, { cwd: root, encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
  if (result.status !== 0) throw new Error(`git ${args.join(' ')} falhou: ${(result.stderr || result.stdout || '').trim()}`);
  return result.stdout;
}

function normalizeFiles(raw) {
  return raw.split(/\r?\n/).map((line) => line.trim().replaceAll('\\', '/')).filter(Boolean);
}

function worktreeChangedFiles(root = ROOT) {
  return [...new Set([
    ...normalizeFiles(runGit(root, ['diff', '--name-only', 'HEAD'])),
    ...normalizeFiles(runGit(root, ['ls-files', '--others', '--exclude-standard'])),
  ])].sort();
}

function changedFiles(base, head = 'HEAD', root = ROOT) {
  const committed = normalizeFiles(runGit(root, ['diff', '--name-only', `${base}...${head}`]));
  const worktree = head === 'HEAD' ? worktreeChangedFiles(root) : [];
  return [...new Set([...committed, ...worktree])].sort();
}

function moduleFromPath(file) {
  const normalized = file.replaceAll('\\', '/');
  const match = normalized.match(/^(?:Modules|resources\/js\/Pages|memory\/requisitos|tests\/Feature)\/([^/]+)\//);
  const mod = match?.[1] || null;
  return mod && !mod.startsWith('_') ? mod : null;
}

function documentationInventory(root, mod) {
  return [`memory/requisitos/${mod}`, `Modules/${mod}`].flatMap((rel) => {
    const abs = join(root, rel);
    if (!existsSync(abs)) return [];
    return readdirSync(abs, { withFileTypes: true })
      .filter((entry) => entry.isFile() && entry.name.endsWith('.md') && DOCUMENT_NAME.test(entry.name))
      .map((entry) => `${rel}/${entry.name}`.replaceAll('\\', '/'));
  }).sort();
}

export function buildImpactReport(files, catalog = {}, root = ROOT) {
  const catalogModules = (catalog.nodes || []).filter((node) => node.type === 'module').map((node) => node.module);
  const canonicalModule = new Map(catalogModules.map((mod) => [mod.toLowerCase(), mod]));
  const direct = [...new Set(files.map(moduleFromPath).filter(Boolean)
    .map((mod) => canonicalModule.get(mod.toLowerCase()) || mod))].sort();
  const graphModules = new Set(catalogModules);
  const related = new Set();
  for (const edge of catalog.edges || []) {
    if (!['dependsOn', 'delegatesTo', 'migratesTo'].includes(edge.type)) continue;
    const from = String(edge.from).replace(/^module:/, '');
    const to = String(edge.to).replace(/^module:/, '');
    if (direct.includes(from) && !direct.includes(to)) related.add(to);
    if (direct.includes(to) && !direct.includes(from)) related.add(from);
  }
  const shared = files.filter((file) =>
    SHARED_RUNTIME_PATHS.test(file) || /^(composer\.(json|lock)|package(-lock)?\.json)$/.test(file));
  const unknown = direct.filter((mod) => !graphModules.has(mod));
  const relatedModules = [...related].sort();
  const fanout = relatedModules.length > 8;
  const modules = [...new Set([...direct, ...relatedModules])].sort();
  const businessChange = direct.length > 0 || shared.length > 0;
  return {
    schema_version: 1,
    changed_files: files,
    direct_modules: direct,
    related_modules: relatedModules,
    shared_runtime_files: shared,
    unknown_modules: unknown,
    decision: !businessChange ? 'sem-impacto-modular'
      : shared.length || unknown.length || fanout ? 'revisao-ampla'
        : 'revisao-modular',
    reasons: [
      ...(shared.length ? ['superficie-runtime-compartilhada'] : []),
      ...(unknown.length ? ['modulo-fora-do-catalogo'] : []),
      ...(fanout ? ['fanout-maior-que-8'] : []),
    ],
    documents: Object.fromEntries(modules.map((mod) => [mod, documentationInventory(root, mod)])),
  };
}

export function normalizeMeaningfulMarkdown(text) {
  const lines = String(text || '').replace(/\r\n/g, '\n').split('\n');
  let frontmatter = lines[0] === '---';
  return lines.filter((line, index) => {
    if (index === 0 && frontmatter) return false;
    if (frontmatter && line === '---') { frontmatter = false; return false; }
    if (frontmatter && /^\s*(date|data|updated_at|reviewed_at|distilled_at|last_updated|atualizado)\s*:/i.test(line)) return false;
    if (/^\s*>?\s*(?:\*\*)?(?:última atualização|atualizado em)(?:\*\*)?\s*:\s*\d{4}-\d{2}-\d{2}\s*$/i.test(line)) return false;
    return true;
  }).join('\n').replace(/\s+/g, ' ').trim();
}

export function applyReceiptEvidence(comparison, before, changed, documents = {}) {
  const rejected = [];
  const introduced = comparison.introduced || [];
  for (const id of comparison.expected || []) {
    if (!(comparison.resolved || []).some((issue) => issue.id === id)) continue;
    const issue = (before.issues || []).find((item) => item.id === id);
    const targetPath = issue?.target?.file || issue?.target?.doc;
    if (targetPath && !existsSync(join(ROOT, targetPath))) {
      rejected.push({ id, reason: 'alvo-removido', file: targetPath });
      continue;
    }
    if (issue?.source !== 'briefing-code-staleness' || issue.kind !== 'porta-stale') continue;
    const mod = issue.target?.mod;
    const doc = documents[mod];
    if (introduced.some((item) => item.source === 'briefing-code-staleness'
      && item.kind === 'porta-ausente' && item.target?.mod === mod)) {
      rejected.push({ id, reason: 'porta-removida', mod });
      continue;
    }
    if (!doc?.currentPath || doc.current == null) {
      rejected.push({ id, reason: 'porta-removida', mod });
      continue;
    }
    if (!changed.includes(doc.currentPath)) continue;
    const beforeBody = normalizeMeaningfulMarkdown(doc.before);
    const afterBody = normalizeMeaningfulMarkdown(doc.current);
    if (beforeBody === afterBody) rejected.push({ id, reason: 'somente-carimbo-de-data', mod, file: doc.currentPath });
    else if (afterBody.length < 40) rejected.push({ id, reason: 'conteudo-esvaziado', mod, file: doc.currentPath });
  }
  return {
    ...comparison,
    ok: comparison.ok && rejected.length === 0,
    receipt_evidence: { executed: true, changed_files: changed, rejected },
  };
}

function briefingDocuments(ref, before, expected, root = ROOT) {
  const result = {};
  for (const id of expected) {
    const issue = (before.issues || []).find((item) => item.id === id);
    const mod = issue?.target?.mod;
    if (issue?.source !== 'briefing-code-staleness' || !mod) continue;
    const paths = [`memory/requisitos/${mod}/BRIEFING.md`, `Modules/${mod}/BRIEFING.md`];
    const currentPath = paths.find((path) => existsSync(join(root, path))) || null;
    let beforePath = null;
    for (const path of paths) {
      const found = spawnSync('git', ['cat-file', '-e', `${ref}:${path}`], { cwd: root });
      if (found.status === 0) { beforePath = path; break; }
    }
    result[mod] = {
      currentPath,
      current: currentPath ? readFileSync(join(root, currentPath), 'utf8') : null,
      before: beforePath ? runGit(root, ['show', `${ref}:${beforePath}`]) : null,
    };
  }
  return result;
}

export function sortIssues(issues) {
  return [...issues].sort((a, b) => {
    if (a.severity !== b.severity) return a.severity === 'fail' ? -1 : 1;
    const source = (SOURCE_PRIORITY[a.source] ?? 9) - (SOURCE_PRIORITY[b.source] ?? 9);
    return source || a.id.localeCompare(b.id);
  });
}

/**
 * Órfão de navegação: doc que DECLAROU intenção de navegar (`nav_order`/`lente`) e ficou
 * sem `nav_group` — então some do rail em silêncio, e ninguém percebe.
 *
 * ESCOPO ESTREITO DE PROPÓSITO. O óbvio seria acusar "todo doc sem nav_group", mas o
 * campo é OPT-IN: os ~130 arquivos de referência legados não o têm, e não devem ter —
 * acusá-los seria ~130 falso-positivos por rodada, o gate-que-reprova-o-legítimo que
 * este projeto já enterrou quatro vezes (§5: allowlist-de-pasta, guard @scope,
 * vocabulário 130 FP, toHaveKey 100% FP). O predicado aqui é a INCOERÊNCIA declarada
 * pelo próprio autor: pôs metade dos campos e esqueceu justamente o que faz aparecer.
 *
 * @return {Array<object>} issues no mesmo formato das demais fontes
 */
export function normalizeNav(root = ROOT) {
  const dir = join(root, 'memory', 'reference');
  if (!existsSync(dir)) return [];

  const out = [];
  for (const nome of readdirSync(dir)) {
    if (!nome.endsWith('.md') || nome.startsWith('_') || nome === 'README.md') continue;

    const fm = readFileSync(join(dir, nome), 'utf8').match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n/);
    if (!fm) continue;

    const temGrupo = /^nav_group:\s*\S/m.test(fm[1]);
    const declarouNav = /^(nav_order|lente):\s*\S/m.test(fm[1]);

    if (declarouNav && !temGrupo) {
      const target = { file: `memory/reference/${nome}` };
      out.push({
        id: issueId('doc-nav', 'nav-orfao', target),
        source: 'doc-nav', kind: 'nav-orfao', severity: 'warn', target, metric: 1,
        details: 'declara nav_order/lente mas não tem nav_group — não aparece no rail',
      });
    }
  }
  return out;
}

export function buildSnapshot({ sha = null, memoryHealth = {}, briefingIssues = [], freshness = {}, navIssues = [] } = {}) {
  const rawIssues = [
    ...normalizeMemoryHealth(memoryHealth),
    ...briefingIssues,
    ...normalizeFreshness(freshness),
    ...navIssues,
  ];
  // O mesmo link pode aparecer repetido no sample do detector (ex.: índice que o
  // cita várias vezes). Recibo é por achado estável, não por ocorrência textual.
  const issues = sortIssues([...new Map(rawIssues.map((issue) => [issue.id, issue])).values()]);
  return {
    schema_version: 1,
    git_sha: sha,
    sources: {
      'memory-health': issues.filter((x) => x.source === 'memory-health').length,
      'briefing-code-staleness': issues.filter((x) => x.source === 'briefing-code-staleness').length,
      'doc-freshness-score': issues.filter((x) => x.source === 'doc-freshness-score').length,
    },
    issues,
  };
}

export function snapshot(root = ROOT) {
  const memoryHealth = runJson(root, 'scripts/governance/memory-health.mjs', ['--json', '--warn-only']);
  const freshness = runJson(root, 'scripts/governance/doc-freshness-score.mjs', ['--json']);
  // scanBriefing usa process.cwd() como raiz. No snapshot do checkout corrente ele
  // evita subprocesso; snapshots de ref chamam este CLI com cwd na worktree-ref.
  const briefingIssues = root === process.cwd() ? normalizeBriefing() : [];
  // normalizeNav lê só arquivo, então roda em qualquer root (inclusive fixture do selftest).
  return buildSnapshot({ sha: gitSha(root), memoryHealth, briefingIssues, freshness, navIssues: normalizeNav(root) });
}

export function compareSnapshots(before, after, expected = []) {
  const beforeMap = new Map((before.issues || []).map((issue) => [issue.id, issue]));
  const afterMap = new Map((after.issues || []).map((issue) => [issue.id, issue]));
  const resolved = [...beforeMap.keys()].filter((id) => !afterMap.has(id)).map((id) => beforeMap.get(id));
  const introduced = [...afterMap.keys()].filter((id) => !beforeMap.has(id)).map((id) => afterMap.get(id));
  const changed = [...beforeMap.keys()].filter((id) => afterMap.has(id))
    .map((id) => ({ id, before: beforeMap.get(id).metric, after: afterMap.get(id).metric }))
    .filter((row) => row.before !== row.after);
  const resolvedIds = new Set(resolved.map((issue) => issue.id));
  const missingExpected = expected.filter((id) => !resolvedIds.has(id));
  return {
    ok: missingExpected.length === 0,
    before_sha: before.git_sha || null,
    after_sha: after.git_sha || null,
    expected,
    missing_expected: missingExpected,
    resolved,
    introduced,
    changed,
  };
}

function parseExpected() {
  const idx = process.argv.indexOf('--expect');
  if (idx === -1 || !process.argv[idx + 1]) return [];
  return process.argv[idx + 1].split(',').map((x) => x.trim()).filter(Boolean);
}

function printSnapshot(data) {
  if (JSON_OUT) return console.log(JSON.stringify(data, null, 2));
  console.log(`\n  DOCUMENTATION LOOP — ${data.issues.length} achado(s) · sha ${data.git_sha || '—'}\n`);
  for (const issue of data.issues.slice(0, 20)) {
    console.log(`  ${issue.severity === 'fail' ? '🔴' : '🟡'} ${issue.id}`);
    console.log(`     ${issue.details}`);
  }
  if (data.issues.length > 20) console.log(`  … +${data.issues.length - 20}`);
  console.log('\n  Recibo fecha somente quando o mesmo ID desaparece no snapshot depois.\n');
}

function printComparison(data) {
  if (JSON_OUT) return console.log(JSON.stringify(data, null, 2));
  console.log(`\n  RECIBO DOCUMENTAL — ${data.before_sha || '—'} → ${data.after_sha || '—'}`);
  console.log(`  resolvidos ${data.resolved.length} · novos ${data.introduced.length} · métricas alteradas ${data.changed.length}`);
  for (const issue of data.resolved) console.log(`  ✅ ${issue.id}`);
  for (const id of data.missing_expected) console.log(`  ❌ esperado não fechou: ${id}`);
  for (const rejection of data.receipt_evidence?.rejected || []) {
    console.log(`  ❌ evidência recusada: ${rejection.id} (${rejection.reason})`);
  }
  if (!data.expected.length) {
    console.log('\n  ✓ comparativo concluído; nenhum ID foi declarado como recibo esperado.\n');
  } else {
    console.log(data.ok ? '\n  ✓ recibo comprovado pelo mesmo detector.\n' : '\n  ✗ recibo incompleto.\n');
  }
}

function selftest() {
  let failures = 0;
  const check = (name, condition) => { console.log(`  ${condition ? '[OK]' : '[FAIL]'} ${name}`); if (!condition) failures++; };
  const target = { file: 'memory/GUIA.md', link: 'morto.md' };
  const id = issueId('memory-health', 'link-quebrado', target);
  const issue = { id, source: 'memory-health', kind: 'link-quebrado', severity: 'warn', target, metric: 1, details: 'fixture' };
  const before = { git_sha: 'a', issues: [issue] };
  const unchanged = { git_sha: 'b', issues: [{ ...issue, metric: 0 }] };
  const fixed = { git_sha: 'c', issues: [] };
  const bite = compareSnapshots(before, unchanged, [id]);
  const release = compareSnapshots(before, fixed, [id]);
  check('ID é determinístico apesar da ordem das chaves', id === issueId('memory-health', 'link-quebrado', { link: 'morto.md', file: 'memory/GUIA.md' }));
  check('BITE: mudar métrica sem sumir não fecha recibo', !bite.ok && bite.missing_expected.includes(id));
  check('RELEASE: o mesmo ID ausente depois fecha recibo', release.ok && release.resolved[0]?.id === id);
  const newcomer = { ...issue, id: issueId('memory-health', 'link-quebrado', { file: 'novo.md' }) };
  const withNew = compareSnapshots(before, { git_sha: 'd', issues: [newcomer] }, [id]);
  check('Novo drift é reportado sem apagar o recibo resolvido', withNew.ok && withNew.introduced.length === 1);
  const staleId = issueId('briefing-code-staleness', 'porta-stale', { mod: 'Financeiro' });
  const stale = { id: staleId, source: 'briefing-code-staleness', kind: 'porta-stale', target: { mod: 'Financeiro' } };
  const comparison = compareSnapshots({ git_sha: 'a', issues: [stale] }, { git_sha: 'b', issues: [] }, [staleId]);
  const dateOnly = applyReceiptEvidence(comparison, { issues: [stale] }, ['memory/requisitos/Financeiro/BRIEFING.md'], {
    Financeiro: {
      currentPath: 'memory/requisitos/Financeiro/BRIEFING.md',
      before: '---\ndate: 2026-07-01\n---\n# Financeiro\nConteúdo real.',
      current: '---\ndate: 2026-07-29\n---\n# Financeiro\nConteúdo real.',
    },
  });
  check('BITE: mudar só carimbo não fecha BRIEFING stale', !dateOnly.ok && dateOnly.receipt_evidence.rejected[0]?.reason === 'somente-carimbo-de-data');
  const deleted = { ...issue, id: issueId('memory-health', 'link-quebrado', { file: 'memory/arquivo-removido.md' }),
    target: { file: 'memory/arquivo-removido.md' } };
  const deletedReceipt = compareSnapshots({ issues: [deleted] }, { issues: [] }, [deleted.id]);
  check('BITE: apagar o alvo não fecha o achado',
    !applyReceiptEvidence(deletedReceipt, { issues: [deleted] }, []).ok);
  const impact = buildImpactReport(['Modules/Financeiro/Services/FluxoService.php'], {
    nodes: [{ type: 'module', module: 'Financeiro' }, { type: 'module', module: 'Sells' }],
    edges: [{ type: 'dependsOn', from: 'module:Financeiro', to: 'module:Sells' }],
  });
  check('PILOTO Financeiro: diff encontra módulo, 1 salto e seus donos documentais',
    impact.direct_modules[0] === 'Financeiro'
      && impact.related_modules.includes('Sells')
      && impact.documents.Financeiro.some((path) => /SDD-/.test(path))
      && impact.documents.Financeiro.some((path) => /BRIEFING\.md$/.test(path)));
  check('Controle: arquivo runtime compartilhado exige revisão ampla',
    buildImpactReport(['app/Models/User.php'], { nodes: [], edges: [] }).decision === 'revisao-ampla');
  const fixture = mkdtempSync(join(resolve(tmpdir()), 'oimpresso-documentation-loop-selftest-'));
  try {
    runGit(fixture, ['init']);
    writeFileSync(join(fixture, 'README.md'), 'base\n');
    runGit(fixture, ['add', 'README.md']);
    runGit(fixture, ['-c', 'user.name=Documentation Loop', '-c', 'user.email=docs@example.invalid',
      'commit', '-m', 'base']);
    writeFileSync(join(fixture, 'README.md'), 'alterado\n');
    mkdirSync(join(fixture, 'Modules', 'Financeiro', 'Services'), { recursive: true });
    writeFileSync(join(fixture, 'Modules', 'Financeiro', 'Services', 'Novo.php'), '<?php\n');
    const dirty = changedFiles('HEAD', 'HEAD', fixture);
    check('BITE: impacto inclui arquivo rastreado e novo antes do commit',
      dirty.includes('README.md') && dirty.includes('Modules/Financeiro/Services/Novo.php'));
    const immutable = gitSha(fixture);
    check('Controle: comparação entre SHAs imutáveis ignora o worktree',
      changedFiles(immutable, immutable, fixture).length === 0);
  } finally {
    const resolvedFixture = resolve(fixture);
    const safePrefix = `${resolve(tmpdir())}${sep}`;
    if (!resolvedFixture.startsWith(safePrefix)) throw new Error(`fixture fora de os.tmpdir(): ${resolvedFixture}`);
    rmSync(resolvedFixture, { recursive: true, force: true });
  }
  console.log(failures ? `\n  ${failures} FALHA(S)\n` : '\n  SELFTEST OK — morde, solta e preserva IDs estáveis.\n');
  return failures ? 1 : 0;
}

function withRefSnapshot(ref) {
  const tmpRoot = resolve(tmpdir());
  const worktree = mkdtempSync(join(tmpRoot, 'oimpresso-documentation-loop-'));
  const resolvedWorktree = resolve(worktree);
  if (!resolvedWorktree.startsWith(tmpRoot)) throw new Error(`worktree temporária fora de os.tmpdir(): ${resolvedWorktree}`);
  const add = spawnSync('git', ['worktree', 'add', '--detach', resolvedWorktree, ref], { cwd: ROOT, encoding: 'utf8' });
  if (add.status !== 0) throw new Error(`não foi possível abrir ref ${ref}: ${(add.stderr || add.stdout || '').trim()}`);
  try {
    const script = realpathSync(fileURLToPath(import.meta.url));
    const result = spawnSync(process.execPath, [script, '--snapshot', '--json'], {
      cwd: resolvedWorktree, encoding: 'utf8', maxBuffer: 128 * 1024 * 1024,
    });
    if (result.status !== 0 || !(result.stdout || '').trim()) {
      throw new Error(`snapshot do ref ${ref} falhou: ${(result.stderr || result.stdout || '').trim().slice(0, 500)}`);
    }
    return JSON.parse(result.stdout);
  } finally {
    spawnSync('git', ['worktree', 'remove', '--force', resolvedWorktree], { cwd: ROOT, encoding: 'utf8' });
  }
}

function main() {
  if (process.argv.includes('--selftest')) return selftest();
  const impactIdx = process.argv.indexOf('--impact-ref');
  if (impactIdx !== -1) {
    const base = process.argv[impactIdx + 1];
    if (!base) throw new Error('--impact-ref exige um ref git');
    const headIdx = process.argv.indexOf('--head-ref');
    const head = headIdx === -1 ? 'HEAD' : process.argv[headIdx + 1];
    if (!head) throw new Error('--head-ref exige um ref git');
    const files = changedFiles(base, head);
    const worktreeFiles = head === 'HEAD' ? worktreeChangedFiles(ROOT) : [];
    const catalogPath = join(ROOT, 'memory/governance/catalog.json');
    const catalog = existsSync(catalogPath) ? JSON.parse(readFileSync(catalogPath, 'utf8')) : {};
    const report = {
      ...buildImpactReport(files, catalog),
      base_sha: runGit(ROOT, ['rev-parse', base]).trim(),
      head_sha: runGit(ROOT, ['rev-parse', head]).trim(),
      worktree_files: worktreeFiles,
      executed: true,
    };
    console.log(JSON_OUT ? JSON.stringify(report, null, 2)
      : `impacto documental: ${report.decision} · módulos diretos ${report.direct_modules.join(', ') || '—'} · relacionados ${report.related_modules.join(', ') || '—'}`);
    if (REQUIRE_CLEAN && worktreeFiles.length) {
      console.error(`documentation-loop: recibo final exige worktree limpo; ${worktreeFiles.length} arquivo(s) ainda não commitado(s)`);
      return 1;
    }
    return 0;
  }
  const compareIdx = process.argv.indexOf('--compare-ref');
  if (compareIdx !== -1) {
    const ref = process.argv[compareIdx + 1];
    if (!ref) throw new Error('--compare-ref exige um ref git');
    const before = withRefSnapshot(ref);
    const after = snapshot();
    const expected = parseExpected();
    const comparison = applyReceiptEvidence(
      compareSnapshots(before, after, expected),
      before,
      changedFiles(ref),
      briefingDocuments(ref, before, expected),
    );
    const worktreeFiles = worktreeChangedFiles(ROOT);
    comparison.receipt_evidence.worktree_files = worktreeFiles;
    if (REQUIRE_CLEAN && worktreeFiles.length) {
      comparison.ok = false;
      comparison.receipt_evidence.rejected.push({
        reason: 'worktree-nao-commitado',
        files: worktreeFiles,
      });
    }
    printComparison(comparison);
    return comparison.ok ? 0 : 1;
  }
  const data = snapshot();
  printSnapshot(data);
  return 0;
}

const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();

if (isMain) {
  try { process.exit(main()); }
  catch (error) { console.error(`documentation-loop: ${error.message}`); process.exit(1); }
}
