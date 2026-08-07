#!/usr/bin/env node
// sdd-flow.mjs — recibo estrutural da cadeia:
// SPEC/US -> feature trio -> SDD/CU -> Page/charter/casos -> task de fechamento -> ancora.
//
// Nao substitui feature-lint, anchor-lint nem casos-gate. Ele usa o contrato do
// feature-lint e mostra, em uma porta unica, quais elos existem e qual maquina e
// autoridade para provar cada um.

import { existsSync, readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { basename, dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { lintFeature, parseFrontmatter, parseTasks } from './feature-lint.mjs';

const ROOT = process.cwd();
const GOVERNANCE_DIR = dirname(fileURLToPath(import.meta.url));

function escapeRegex(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export function featureDir(root, target) {
  const clean = String(target || '').replaceAll('\\', '/').replace(/^\.\//, '');
  if (clean.includes('/features/')) return resolve(root, clean);
  const [module, slug, ...rest] = clean.split('/').filter(Boolean);
  if (!module || !slug || rest.length) throw new Error('alvo invalido: use <Modulo>/<slug>');
  return join(root, 'memory', 'requisitos', module, 'features', slug);
}

export function smartAnchorDocs(report) {
  // SPEC e SDD sao compartilhados por varias features. Varre-los por inteiro faria
  // uma feature herdar divida de outra. Seus elos sao julgados por anchor-lint e
  // pela presenca exata dos CU; o smart token fica nos docs exclusivos da cadeia.
  const featureDocs = ['requirements.md', 'plan.md', 'tasks.md']
    .map((name) => `${report.feature.path}/${name}`);
  const screenDocs = report.screens.flatMap((screen) => [
    screen.ref.replace(/\.tsx$/, '.charter.md'),
    screen.ref.replace(/\.tsx$/, '.casos.md'),
  ]);
  return [...new Set([
    ...featureDocs,
    ...screenDocs,
  ])];
}

export function checkSmartAnchors(root, report) {
  const docs = smartAnchorDocs(report).filter((doc) => existsSync(join(root, doc)));
  const commandArgs = [
    join(GOVERNANCE_DIR, 'ancora-codigo-sync.mjs'),
    '--check',
    '--require-stamp',
    '--root', root,
    ...docs.flatMap((doc) => ['--doc', doc]),
  ];
  const result = spawnSync(process.execPath, commandArgs, { cwd: root, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  return {
    mechanism: 'smart-token-git-sha',
    ok: result.status === 0,
    status: result.status,
    docs,
    command: `node scripts/governance/ancora-codigo-sync.mjs --check --require-stamp ${docs.map((doc) => `--doc ${doc}`).join(' ')}`,
    output: `${result.stdout || ''}${result.stderr || ''}`.trim(),
  };
}

export function checkSpecHash(root, report) {
  const gitConfigIndex = Number(process.env.GIT_CONFIG_COUNT || 0);
  const env = {
    ...process.env,
    GIT_CONFIG_COUNT: String(gitConfigIndex + 1),
    [`GIT_CONFIG_KEY_${gitConfigIndex}`]: 'safe.directory',
    [`GIT_CONFIG_VALUE_${gitConfigIndex}`]: resolve(root),
  };
  const result = spawnSync(process.execPath, [
    join(GOVERNANCE_DIR, 'anchor-lint.mjs'),
    report.spec.path,
    '--stale',
    '--json',
  ], { cwd: root, encoding: 'utf8', env, maxBuffer: 64 * 1024 * 1024 });
  let parsed = null;
  try { parsed = JSON.parse(result.stdout || ''); } catch { /* diagnostic below */ }
  const moduleReport = parsed?.modules?.[0] || null;
  const states = report.spec.anchors.map((anchor) => {
    if (anchor.state !== 'verificada') return { us: anchor.us, state: 'not-applicable' };
    if (!moduleReport) return { us: anchor.us, state: 'unknown', reason: 'module-report-ausente' };
    const dead = moduleReport.dead?.find((entry) => entry.us === anchor.us);
    const zombie = moduleReport.zombie?.find((entry) => entry.us === anchor.us);
    const stale = moduleReport?.anchor_stale?.find((entry) => entry.us === anchor.us);
    const unknown = moduleReport?.anchor_stale_unknown?.find((entry) => entry.us === anchor.us);
    if (dead) return { us: anchor.us, state: 'dead', missing: dead.missing };
    if (zombie) return { us: anchor.us, state: 'zombie', paths: zombie.dead_screens };
    if (stale) return { us: anchor.us, state: 'stale', sha: stale.sha, paths: stale.paths };
    if (unknown) return { us: anchor.us, state: 'unknown', sha: unknown.sha, reason: unknown.reason };
    return { us: anchor.us, state: 'fresh' };
  });
  return {
    mechanism: 'anchor-lint-stale-base-derivada',
    ok: result.status === 0 && parsed != null && states.every((entry) => ['fresh', 'not-applicable'].includes(entry.state)),
    status: result.status,
    states,
    command: `node scripts/governance/anchor-lint.mjs ${report.spec.path} --stale --json`,
    output: parsed == null ? `${result.stdout || ''}${result.stderr || ''}`.trim() : null,
  };
}

export function attachSmartAnchorCheck(root, report) {
  const smartAnchors = checkSmartAnchors(root, report);
  const specHash = checkSpecHash(root, report);
  const blockers = [...report.receipt_blockers];
  if (!smartAnchors.ok) blockers.push('smart-anchor-git-sha-invalida');
  for (const entry of specHash.states) {
    if (!['fresh', 'not-applicable'].includes(entry.state)) {
      blockers.push(`spec-hash-${entry.us}-${entry.state}${entry.reason ? `:${entry.reason}` : ''}`);
    }
  }
  if (specHash.status !== 0 || specHash.output) blockers.push('spec-hash-nao-mensuravel');
  const receiptBlockers = [...new Set(blockers)];
  const stage = report.feature.errors.length ? 'contrato-invalido'
    : receiptBlockers.length === 0 ? 'rastreabilidade-estrutural-fechada'
      : report.spec.anchors.some((anchor) => anchor.state === 'parcial' || anchor.state === 'verificada') ? 'em-execucao'
        : 'planejado';
  return { ...report, stage, spec_hash: specHash, smart_anchors: smartAnchors, receipt_blockers: receiptBlockers };
}

export function extractUsAnchor(specText, us) {
  const heading = new RegExp(`^###\\s+${escapeRegex(us)}(?:\\s|\\b)`, 'm');
  const match = heading.exec(specText);
  if (!match) return { us, state: 'us-ausente', line: null, verified_at: null };
  const tail = specText.slice(match.index);
  const next = tail.slice(match[0].length).search(/^###\s+/m);
  const block = next === -1 ? tail : tail.slice(0, match[0].length + next);
  const line = (block.match(/^\*\*Implementado em:\*\*\s*(.+)$/m) || [])[1]?.trim() || null;
  let state = 'sem-ancora';
  if (line && /verificado@[0-9a-f]{7,40}\b/i.test(line)) state = 'verificada';
  else if (line && /_parcial_/i.test(line)) state = 'parcial';
  else if (line && /_pendente_/i.test(line)) state = 'pendente';
  else if (line) state = 'nao-classificada';
  const verifiedAt = (line?.match(/\((\d{4}-\d{2}-\d{2})\)/) || [])[1] || null;
  return { us, state, line, verified_at: verifiedAt };
}

function finalTaskReceipt(tasksText) {
  const { tasks } = parseTasks(tasksText);
  if (!tasks.length) return { ok: false, task: null, reasons: ['sem-task-final'] };
  const last = tasks[tasks.length - 1];
  const start = tasksText.search(new RegExp(`^###\\s+${escapeRegex(last.id)}\\b`, 'm'));
  const block = start === -1 ? '' : tasksText.slice(start);
  const reasons = [];
  if (!/Fechar o loop/i.test(last.title)) reasons.push('titulo-nao-fecha-loop');
  if (!/(Implementado em:|ncora da US)/i.test(block)) reasons.push('nao-cita-ancora-spec');
  if (!/anchor-lint\.mjs/i.test(block)) reasons.push('nao-cita-anchor-lint');
  if (!/smoke/i.test(block)) reasons.push('nao-cita-smoke');
  return { ok: reasons.length === 0, task: last.id, title: last.title, reasons };
}

export function buildFlowReport(root, target) {
  const dir = featureDir(root, target);
  if (!existsSync(dir)) throw new Error(`feature nao existe: ${dir}`);
  const requirementsPath = join(dir, 'requirements.md');
  const tasksPath = join(dir, 'tasks.md');
  const requirementsText = existsSync(requirementsPath) ? readFileSync(requirementsPath, 'utf8') : '';
  const tasksText = existsSync(tasksPath) ? readFileSync(tasksPath, 'utf8') : '';
  const fm = parseFrontmatter(requirementsText);
  const module = fm.module || basename(dirname(dirname(dir)));
  const specPath = join(root, 'memory', 'requisitos', module, 'SPEC.md');
  const specText = existsSync(specPath) ? readFileSync(specPath, 'utf8') : '';
  const lint = lintFeature(dir, { specText: existsSync(specPath) ? specText : undefined });
  const anchors = fm.us.map((us) => extractUsAnchor(specText, us));
  const sdds = fm.sdd.map((ref) => ({
    ref,
    exists: existsSync(join(root, ref)),
    cus: fm.relatedCus.filter((cu) => existsSync(join(root, ref)) && new RegExp(`\\b${escapeRegex(cu)}\\b`).test(readFileSync(join(root, ref), 'utf8'))),
  }));
  const screens = fm.screens.map((ref) => {
    const page = join(root, ref);
    const charter = page.replace(/\.tsx$/, '.charter.md');
    const casos = page.replace(/\.tsx$/, '.casos.md');
    const casosText = existsSync(casos) ? readFileSync(casos, 'utf8') : '';
    const casosSdd = (casosText.match(/^sdd:\s*([^\r\n]+)/m) || [])[1]?.trim().replace(/^['"]|['"]$/g, '') || null;
    return {
      ref,
      page: existsSync(page),
      charter: existsSync(charter),
      casos: existsSync(casos),
      casos_sdd: casosSdd,
      sdd_coerente: !fm.sdd.length || (casosSdd != null && fm.sdd.includes(casosSdd)),
    };
  });
  const closeLoop = finalTaskReceipt(tasksText);
  const structuralErrors = lint.issues.filter((issue) => issue.level === 'erro');
  const receiptBlockers = [];
  const linkContract = fm.raw && /^sdd:/m.test(fm.raw);
  if (linkContract && !fm.sdd.length) receiptBlockers.push('feature-sem-sdd');
  if (fm.sdd.length && !fm.relatedCus.length) receiptBlockers.push('feature-sem-cu');
  for (const anchor of anchors) {
    if (anchor.state !== 'verificada') receiptBlockers.push(`ancora-${anchor.us}-${anchor.state}`);
    else if (fm.created && anchor.verified_at && anchor.verified_at < fm.created) {
      receiptBlockers.push(`ancora-${anchor.us}-anterior-a-feature:${anchor.verified_at}<${fm.created}`);
    } else if (fm.created && !anchor.verified_at) {
      receiptBlockers.push(`ancora-${anchor.us}-sem-data-verificavel`);
    }
  }
  for (const screen of screens) {
    if (!screen.page) receiptBlockers.push(`screen-ausente:${screen.ref}`);
    if (!screen.charter) receiptBlockers.push(`charter-ausente:${screen.ref}`);
    if (!screen.casos) receiptBlockers.push(`casos-ausente:${screen.ref}`);
    if (screen.casos && !screen.sdd_coerente) receiptBlockers.push(`casos-sdd-divergente:${screen.ref}`);
  }
  if (!closeLoop.ok) receiptBlockers.push(...closeLoop.reasons.map((reason) => `task-final:${reason}`));
  receiptBlockers.push(...structuralErrors.map((issue) => `feature-lint:${issue.code}`));

  const stage = structuralErrors.length ? 'contrato-invalido'
    : receiptBlockers.length === 0 ? 'rastreabilidade-estrutural-fechada'
      : anchors.some((anchor) => anchor.state === 'parcial' || anchor.state === 'verificada') ? 'em-execucao'
        : 'planejado';

  return {
    schema_version: 1,
    target: `${module}/${fm.feature || basename(dir)}`,
    stage,
    spec: { path: specPath.slice(root.length + 1).replaceAll('\\', '/'), exists: existsSync(specPath), anchors },
    feature: {
      path: dir.slice(root.length + 1).replaceAll('\\', '/'),
      acs: lint.acs,
      tasks: lint.tasks,
      errors: structuralErrors,
      warnings: lint.issues.filter((issue) => issue.level === 'aviso'),
      close_loop: closeLoop,
    },
    sdds,
    related_cus: fm.relatedCus,
    screens,
    receipt_blockers: [...new Set(receiptBlockers)],
    authorities: {
      feature: `node scripts/governance/feature-lint.mjs ${module}/${fm.feature || basename(dir)} --check`,
      spec_anchor: `node scripts/governance/anchor-lint.mjs memory/requisitos/${module}/SPEC.md --stale --json`,
      smart_anchor: 'node scripts/governance/ancora-codigo-sync.mjs --check --require-stamp --doc <doc.md>',
      screen_contract: 'npm run casos:check',
      tests: 'lane Pest/PHPStan aplicavel no CT 100',
    },
  };
}

function printHuman(report) {
  console.log(`\n  SDD FLOW — ${report.target} — ${report.stage}\n`);
  console.log(`  SPEC/US  ${report.spec.anchors.map((anchor) => `${anchor.us}:${anchor.state}`).join(' | ') || '—'}`);
  console.log(`  FEATURE  ${report.feature.acs} AC | ${report.feature.tasks} tasks | fechamento ${report.feature.close_loop.ok ? 'ok' : 'pendente'}`);
  console.log(`  SDD/CU   ${report.sdds.map((sdd) => sdd.ref).join(', ') || '—'} | ${report.related_cus.join(', ') || '—'}`);
  console.log(`  TELAS    ${report.screens.length ? report.screens.map((screen) => `${screen.ref} [tsx:${screen.page ? 'ok' : 'x'} charter:${screen.charter ? 'ok' : 'x'} casos:${screen.casos ? 'ok' : 'x'}]`).join('\n           ') : '—'}`);
  if (report.smart_anchors) {
    const specStates = report.spec_hash?.states.map((entry) => `${entry.us}:${entry.state}`).join(' | ') || '—';
    console.log(`  HASH     SPEC ${specStates} | refs por linha ${report.smart_anchors.ok ? 'ok' : 'falhou'} (${report.smart_anchors.docs.length} doc(s))`);
  }
  if (report.feature.errors.length || report.feature.warnings.length) {
    console.log('\n  ACHADOS');
    for (const issue of [...report.feature.errors, ...report.feature.warnings]) console.log(`  ${issue.level === 'erro' ? 'ERRO' : 'AVISO'} [${issue.code}] ${issue.msg}`);
  }
  if (report.receipt_blockers.length) {
    console.log('\n  BLOQUEIOS DO RECIBO ESTRUTURAL');
    for (const blocker of report.receipt_blockers) console.log(`  - ${blocker}`);
  }
  if (report.smart_anchors && !report.smart_anchors.ok && report.smart_anchors.output) {
    console.log('\n  DIAGNOSTICO DAS ANCORAS HASH');
    for (const line of report.smart_anchors.output.split(/\r?\n/)) console.log(`  ${line}`);
  }
  if (report.spec_hash?.output) {
    console.log('\n  DIAGNOSTICO DO HASH DA SPEC');
    for (const line of report.spec_hash.output.split(/\r?\n/)) console.log(`  ${line}`);
  }
  console.log('\n  AUTORIDADES (nao duplicadas por este comando)');
  for (const [name, command] of Object.entries(report.authorities)) console.log(`  - ${name}: ${command}`);
  console.log('');
}

function main() {
  const args = process.argv.slice(2);
  const target = args.find((arg) => !arg.startsWith('--'));
  if (!target || args.includes('--help')) {
    console.log('Uso: npm run sdd:flow -- <Modulo>/<slug> [--json] [--check|--receipt]');
    console.log('Crie o trio: npm run sdd:init -- <Modulo>/<slug> --us US-MOD-001 --sdd auto --cu CU-MOD-01 --screen Mod/Tela');
    return target ? 0 : 1;
  }
  const report = attachSmartAnchorCheck(ROOT, buildFlowReport(ROOT, target));
  if (args.includes('--json')) console.log(JSON.stringify(report, null, 2));
  else printHuman(report);
  if (args.includes('--receipt')) return report.receipt_blockers.length ? 1 : 0;
  if (args.includes('--check')) return report.feature.errors.length || !report.smart_anchors.ok || !report.spec_hash.ok ? 1 : 0;
  return 0;
}

const isMain = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isMain) {
  try { process.exit(main()); }
  catch (error) { console.error(`sdd-flow: ${error.message}`); process.exit(2); }
}
