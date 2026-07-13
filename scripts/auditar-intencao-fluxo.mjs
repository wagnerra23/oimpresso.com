#!/usr/bin/env node
// Catraca estática: a prosa declara a intenção, mas não mascara evidência ausente.
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = resolve(fileURLToPath(new URL('.', import.meta.url)));
const rootFlag = process.argv.indexOf('--root');
const ROOT = rootFlag >= 0 ? resolve(process.argv[rootFlag + 1]) : resolve(HERE, '..');
const log = (...args) => console.log(...args);
const ok = (...args) => log('OK ' + args.join(' '));
const fail = (...args) => log('X ' + args.join(' '));

function argument(name) { const i = process.argv.indexOf(name); return i >= 0 ? process.argv[i + 1] : null; }

export function validarContrato(contract) {
  if (!Array.isArray(contract?.alvo) || !contract.alvo.length) return 'campo `alvo` deve ser array não-vazio';
  if (!Array.isArray(contract?.fluxos) || !contract.fluxos.length) return 'campo `fluxos` deve ser array não-vazio';
  for (const flow of contract.fluxos) {
    if (!flow.id || !flow.intencao) return 'cada fluxo precisa de `id` e `intencao`';
    if (!Array.isArray(flow.deve_conter) || !flow.deve_conter.length) return `fluxo "${flow.id}" sem \`deve_conter\` não-vazio`;
    if (flow.nao_pode_conter !== undefined && !Array.isArray(flow.nao_pode_conter)) return `fluxo "${flow.id}": \`nao_pode_conter\` deve ser array`;
  }
  return null;
}

export function auditar(contract, source) {
  const issues = [];
  for (const flow of contract.fluxos) {
    for (const literal of flow.deve_conter) if (!source.includes(literal)) issues.push({ flow: flow.id, kind: 'ausente', literal });
    for (const literal of (flow.nao_pode_conter ?? [])) if (source.includes(literal)) issues.push({ flow: flow.id, kind: 'proibido', literal });
  }
  return issues;
}

function run(contractPath) {
  if (!contractPath) { fail('uso: node scripts/auditar-intencao-fluxo.mjs --contract <arquivo.intent.json>'); return 2; }
  const absoluteContract = resolve(ROOT, contractPath);
  if (!existsSync(absoluteContract)) { fail(`contrato não encontrado: ${contractPath}`); return 2; }
  let contract;
  try { contract = JSON.parse(readFileSync(absoluteContract, 'utf8')); } catch (error) { fail(`JSON inválido: ${error.message}`); return 2; }
  const invalid = validarContrato(contract);
  if (invalid) { fail(`contrato inválido: ${invalid}`); return 2; }
  const files = contract.alvo.map((path) => resolve(ROOT, path));
  const missing = files.filter((path) => !existsSync(path));
  if (missing.length) { fail(`alvo inexistente: ${missing.join(', ')}`); return 2; }
  const source = files.map((path) => readFileSync(path, 'utf8')).join('\n');
  const issues = auditar(contract, source);
  log(`tela: ${contract.tela ?? contractPath} · ${contract.fluxos.length} fluxo(s) · ${files.length} arquivo(s)`);
  for (const flow of contract.fluxos) {
    const local = issues.filter((issue) => issue.flow === flow.id);
    if (!local.length) ok(`fluxo "${flow.id}" — ${flow.intencao}`);
    for (const issue of local) fail(issue.kind === 'ausente'
      ? `fluxo "${flow.id}": evidência obrigatória ausente: ${JSON.stringify(issue.literal)}`
      : `fluxo "${flow.id}": caminho proibido encontrado: ${JSON.stringify(issue.literal)}`);
  }
  return issues.length ? 1 : 0;
}

const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invokedDirectly) process.exit(run(argument('--contract')));
