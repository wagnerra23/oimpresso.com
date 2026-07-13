#!/usr/bin/env node
// Adversário: procura contraprovas ao contrato, sem aceitar justificativa em prosa.
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = resolve(fileURLToPath(new URL('.', import.meta.url)));
const ROOT = resolve(HERE, '..');
const lineOf = (text, index) => text.slice(0, index).split('\n').length;

export function adversariar(contract, source, file = '(alvo)') {
  const findings = [];
  const legacy = /router\.visit\(\s*['"`]([^'"`]*\/novo[^'"`]*)['"`]/g;
  for (let match; (match = legacy.exec(source)); ) findings.push({ severity: 'critical', file, line: lineOf(source, match.index), code: 'rota-legada', message: `ação navega para rota legada ${match[1]}` });
  const expected = Number(contract.cobertura_minima ?? 1);
  if (contract.fluxos.length < expected) findings.push({ severity: 'medium', file: contract.charter ?? '(charter)', line: 1, code: 'cobertura-insuficiente', message: `contrato declara ${contract.fluxos.length}/${expected} fluxos críticos` });
  for (const flow of contract.fluxos) {
    if (!Array.isArray(flow.nao_pode_conter) || !flow.nao_pode_conter.length) findings.push({ severity: 'medium', file: contract.charter ?? '(charter)', line: 1, code: 'sem-contraprova', message: `fluxo ${flow.id} não declara caminho proibido` });
  }
  return findings;
}

const arg = (name) => { const i = process.argv.indexOf(name); return i >= 0 ? process.argv[i + 1] : null; };
const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invokedDirectly) {
  const contractArg = arg('--contract');
  if (!contractArg) { console.error('uso: node scripts/adversario-intencao-fluxo.mjs --contract <arquivo.intent.json> [--json] [--strict]'); process.exit(2); }
  const contractPath = resolve(ROOT, contractArg);
  if (!existsSync(contractPath)) { console.error(`X contrato não encontrado: ${contractArg}`); process.exit(2); }
  const contract = JSON.parse(readFileSync(contractPath, 'utf8'));
  const targets = (contract.alvo ?? []).map((path) => resolve(ROOT, path));
  const missing = targets.filter((path) => !existsSync(path));
  if (missing.length) { console.error(`X alvo ausente: ${missing.join(', ')}`); process.exit(2); }
  const findings = targets.flatMap((path) => adversariar(contract, readFileSync(path, 'utf8'), path.slice(ROOT.length + 1).replaceAll('\\', '/')));
  const report = { tela: contract.tela, contract: contractArg, findings };
  if (process.argv.includes('--json')) console.log(JSON.stringify(report, null, 2));
  else if (!findings.length) console.log(`OK ${contract.tela}: adversário não encontrou contraprova`);
  else for (const finding of findings) console.log(`${finding.severity.toUpperCase()} ${finding.file}:${finding.line} [${finding.code}] ${finding.message}`);
  if (process.argv.includes('--strict') && findings.some((finding) => finding.severity === 'critical')) process.exit(1);
}
