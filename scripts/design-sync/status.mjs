#!/usr/bin/env node
// @ts-check
/** Lista operacional do último bundle: o que mudou, onde aplicar e por que está bloqueado. */
import { existsSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { recordApplicationEvidence, refreshApplicationReport } from './bundle-transaction.mjs';

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
  if (valueOf('--mark-applied')) {
    const source = valueOf('--mark-applied');
    const target = valueOf('--target');
    const evidence = valueOf('--evidence');
    const tests = args.flatMap((arg, index) => arg === '--test' && args[index + 1] ? [args[index + 1]] : []);
    if (!target) throw new Error('--target é obrigatório com --mark-applied');
    ({ report } = await recordApplicationEvidence({ root: ROOT, source, target, evidence, tests }));
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
  console.log(`  mudanças: ${summary.transportChanges || 0} · telas: ${summary.screens || 0} · aplicadas: ${summary.applied || 0} · testadas: ${summary.tested || 0} · pendentes: ${summary.pending || 0} · bloqueadas: ${summary.blocked || 0} · a criar: ${summary['to-create'] || 0}\n`);

  console.log('ARQUIVOS MODIFICADOS NO DESIGN');
  if (!report.transportChanges?.length) console.log('  (nenhuma mudança de bytes)');
  for (const file of report.transportChanges || []) {
    console.log(`  [${String(file.change).toUpperCase().padEnd(8)}] ${file.path} · ${file.role} · ${file.bytes ?? '?'} bytes`);
  }

  console.log('\nAPLICAÇÃO NAS TELAS/MÓDULOS');
  const actionable = (report.screens || []).filter((screen) => screen.bundleChange !== 'unchanged' || screen.applicationState !== 'applied');
  if (!actionable.length) console.log('  (nenhuma ação pendente)');
  for (const screen of actionable) {
    console.log(`  [${String(screen.applicationState).toUpperCase().padEnd(9)}] ${screen.source}`);
    console.log(`      → ${screen.target} · módulo ${screen.module || '?'} · ${screen.bundleChange}/${screen.comparison}`);
    if (screen.applicationEvidence) console.log(`      evidência: ${screen.applicationEvidence.evidence} · testes: ${screen.applicationEvidence.tests.join(', ') || 'não registrados'}`);
    console.log(`      ${screen.nextAction}`);
  }
  console.log(`\n  relatório canônico: ${reportPath}\n`);
}

const blocked = (report.screens || []).filter((screen) => screen.applicationState === 'blocked');
if (checkMapping && blocked.length) {
  console.error(`✗ ${blocked.length} tela(s) sem destino inequívoco; aplicação no produto permanece fail-closed.`);
  process.exit(1);
}
process.exit(0);
