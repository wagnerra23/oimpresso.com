#!/usr/bin/env node
// @ts-check
/** Lista operacional do último bundle: o que mudou, onde aplicar e por que está bloqueado. */
import { existsSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import {
  recordApplicationEvidence, recordComparisonEvidence, recordSmokeEvidence,
  recordTestEvidence, refreshApplicationReport,
} from './bundle-transaction.mjs';

const args = process.argv.slice(2);
const rootArg = args.indexOf('--root');
const ROOT = resolve(rootArg >= 0 && args[rootArg + 1] ? args[rootArg + 1] : process.cwd());
const reportPath = join(ROOT, 'scripts/design-sync/state/application-report.json');
const json = args.includes('--json');
const checkMapping = args.includes('--check-mapping');
const valueOf = (name) => {
  const index = args.indexOf(name);
  return index >= 0 && args[index + 1] && !args[index + 1].startsWith('--') ? args[index + 1] : null;
};

let report;
try {
  if (valueOf('--mark-compared')) {
    const source = valueOf('--mark-compared');
    const target = valueOf('--target');
    const map = valueOf('--map');
    if (!target || !map) throw new Error('--target e --map são obrigatórios com --mark-compared');
    ({ report } = await recordComparisonEvidence({ root: ROOT, source, target, map }));
  } else if (valueOf('--mark-applied')) {
    const source = valueOf('--mark-applied');
    const target = valueOf('--target');
    const evidence = valueOf('--evidence');
    if (!target) throw new Error('--target é obrigatório com --mark-applied');
    ({ report } = await recordApplicationEvidence({ root: ROOT, source, target, evidence }));
  } else if (valueOf('--run-test')) {
    const source = valueOf('--run-test');
    const target = valueOf('--target');
    const runner = valueOf('--runner');
    const commandJson = valueOf('--command-json');
    if (!target || !runner || !commandJson) throw new Error('--target, --runner e --command-json são obrigatórios com --run-test');
    let command;
    try { command = JSON.parse(commandJson); } catch { throw new Error('--command-json deve ser um array JSON'); }
    if (!Array.isArray(command) || !command.length) throw new Error('--command-json deve ser um array JSON não vazio');
    const run = spawnSync(String(command[0]), command.slice(1).map(String), {
      cwd: ROOT, encoding: 'utf8', shell: false, maxBuffer: 64 * 1024 * 1024,
    });
    const output = `${run.stdout || ''}${run.stderr || ''}`;
    if (run.error) throw new Error(`não foi possível executar teste: ${run.error.message}`);
    ({ report } = await recordTestEvidence({
      root: ROOT, source, target, command, exitCode: run.status ?? 1, output, runner,
    }));
  } else if (valueOf('--record-smoke')) {
    const source = valueOf('--record-smoke');
    const target = valueOf('--target');
    const route = valueOf('--route');
    const deploySha = valueOf('--deploy-sha');
    const screenshot = valueOf('--screenshot');
    const tenant = valueOf('--tenant');
    if (!target || !route || !deploySha || !screenshot || !tenant) {
      throw new Error('--target, --route, --deploy-sha, --screenshot e --tenant são obrigatórios com --record-smoke');
    }
    ({ report } = await recordSmokeEvidence({ root: ROOT, source, target, route, deploySha, screenshot, tenant }));
  } else if (args.includes('--refresh')) report = await refreshApplicationReport({ root: ROOT });
  else {
    if (!existsSync(reportPath)) throw new Error('relatório ausente; aplique um bundle ou rode com --refresh');
    report = JSON.parse(readFileSync(reportPath, 'utf8'));
  }
} catch (error) {
  console.error(`✗ design-sync status: ${error.message}`);
  process.exit(2);
}

if (json) console.log(JSON.stringify(report, null, 2));
else {
  const summary = report.summary || {};
  console.log(`\nDESIGN-SYNC · bundle ${report.bundle?.id || '?'}`);
  console.log(`  transporte: ${report.bundle?.transportComplete ? 'COMPLETO' : 'INCOMPLETO'} · modo ${report.bundle?.mode || '?'}`);
  console.log(`  mudanças: ${summary.transportChanges || 0} · telas: ${summary.screens || 0} · aplicadas: ${summary.applied || 0} · testadas: ${summary.tested || 0} · smoke: ${summary.smoked || 0} · pendentes: ${summary.pending || 0} · bloqueadas: ${summary.blocked || 0} · a criar: ${summary['to-create'] || 0}\n`);

  console.log('ARQUIVOS MODIFICADOS NO DESIGN');
  if (!report.transportChanges?.length) console.log('  (nenhuma mudança de bytes)');
  for (const file of report.transportChanges || []) {
    console.log(`  [${String(file.change).toUpperCase().padEnd(8)}] ${file.path} · ${file.role} · ${file.bytes ?? '?'} bytes`);
  }

  console.log('\nAPLICAÇÃO NAS TELAS/MÓDULOS');
  const actionable = (report.screens || []).filter((screen) => screen.bundleChange !== 'unchanged' || screen.applicationState !== 'applied');
  if (!actionable.length) console.log('  (nenhuma ação pendente)');
  for (const screen of actionable) {
    console.log(`  [${String(screen.lifecycleState || screen.applicationState).toUpperCase().padEnd(10)}] ${screen.source}`);
    console.log(`      → ${screen.target} · módulo ${screen.module || '?'} · ${screen.bundleChange}/${screen.comparison}`);
    if (screen.applicationEvidence?.comparison) console.log(`      mapa: ${screen.applicationEvidence.comparison.map}`);
    if (screen.applicationEvidence?.application) console.log(`      aplicação: ${screen.applicationEvidence.application.evidence}`);
    if (screen.applicationEvidence?.tests?.length) console.log(`      testes: ${screen.applicationEvidence.tests.length} recibo(s) verde(s)`);
    if (screen.applicationEvidence?.smokes?.length) console.log(`      smoke: ${screen.applicationEvidence.smokes.length} recibo(s) de produção`);
    console.log(`      ${screen.nextAction}`);
  }
  console.log(`\n  relatório canônico: ${reportPath}\n`);
}

const blocked = (report.screens || []).filter((screen) => screen.applicationState === 'blocked');
if (checkMapping && blocked.length) {
  console.error(`✗ ${blocked.length} tela(s) sem destino inequívoco; aplicação no produto permanece fail-closed.`);
  process.exit(1);
}
if (args.includes('--check-lifecycle')) {
  const source = valueOf('--source');
  const module = valueOf('--module');
  const minimum = valueOf('--minimum') || 'anchored';
  const order = ['blocked', 'to-create', 'review', 'received', 'anchored', 'compared', 'applied', 'tested', 'validated'];
  if (!source && !module) {
    console.error('✗ --check-lifecycle exige --source ou --module; o legado não é bloqueado globalmente.');
    process.exit(2);
  }
  if (!order.includes(minimum)) {
    console.error(`✗ estado mínimo inválido: ${minimum}`);
    process.exit(2);
  }
  const selected = (report.screens || []).filter((screen) => (!source || screen.source === source) && (!module || screen.module === module));
  const failing = selected.filter((screen) => order.indexOf(screen.lifecycleState || 'review') < order.indexOf(minimum));
  if (!selected.length || failing.length) {
    console.error(`✗ lifecycle ${source || module}: ${failing.length || 'nenhuma'} tela abaixo de ${minimum}.`);
    process.exit(1);
  }
}
process.exit(0);
