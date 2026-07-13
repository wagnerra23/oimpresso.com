#!/usr/bin/env node
// Mapa Charter → Protótipo → Produção → Contrato de intenção.
// --check falha quando um elo está podre; --json é a entrada estável do briefing.
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { resolve, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { auditar, validarContrato } from './auditar-intencao-fluxo.mjs';

const HERE = resolve(fileURLToPath(new URL('.', import.meta.url)));
const ROOT = resolve(HERE, '..');
const dir = join(ROOT, 'prototipo-ui', 'contrato');
const files = existsSync(dir) ? readdirSync(dir).filter((name) => name.endsWith('.intent.json')).sort() : [];
const rows = [];

for (const name of files) {
  const contractPath = join(dir, name);
  const repoPath = relative(ROOT, contractPath).replaceAll('\\', '/');
  let contract, errors = [];
  try { contract = JSON.parse(readFileSync(contractPath, 'utf8')); } catch (error) { rows.push({ contract: repoPath, status: 'red', errors: [`JSON inválido: ${error.message}`] }); continue; }
  const invalid = validarContrato(contract);
  if (invalid) errors.push(invalid);
  const source = resolve(ROOT, contract.fonte ?? '');
  const charter = resolve(ROOT, contract.charter ?? '');
  const targets = (contract.alvo ?? []).map((path) => resolve(ROOT, path));
  if (!contract.charter) errors.push('contrato sem link para charter');
  if (!existsSync(source)) errors.push(`protótipo ausente: ${contract.fonte}`);
  if (!existsSync(charter)) errors.push(`charter ausente: ${contract.charter}`);
  if (existsSync(charter)) {
    const text = readFileSync(charter, 'utf8');
    if (!text.includes(`intent_contract: ${repoPath}`)) errors.push('charter não aponta para este contrato');
    if (!text.includes(contract.fonte)) errors.push('charter não aponta para o protótipo do contrato');
  }
  const missingTargets = targets.filter((path) => !existsSync(path));
  if (missingTargets.length) errors.push(`alvo ausente: ${missingTargets.map((path) => relative(ROOT, path)).join(', ')}`);
  if (!errors.length) errors.push(...auditar(contract, targets.map((path) => readFileSync(path, 'utf8')).join('\n')).map((issue) => `${issue.kind}: ${issue.flow} → ${issue.literal}`));
  rows.push({ tela: contract.tela, contract: repoPath, charter: contract.charter, prototype: contract.fonte, targets: contract.alvo, status: errors.length ? 'red' : 'green', errors });
}

const report = { generated_at: new Date().toISOString(), contracts: rows, health: { total: rows.length, green: rows.filter((r) => r.status === 'green').length, red: rows.filter((r) => r.status === 'red').length } };
if (process.argv.includes('--json')) console.log(JSON.stringify(report, null, 2));
else for (const row of rows) console.log(`${row.status === 'green' ? 'OK' : 'X'} ${row.tela ?? row.contract}${row.errors.length ? ` — ${row.errors.join('; ')}` : ''}`);
if (process.argv.includes('--check') && report.health.red) process.exit(1);
