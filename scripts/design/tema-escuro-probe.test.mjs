#!/usr/bin/env node
// Bite-test da sonda tema-escuro, pelo CLI de FORA (subprocesso) — não pelos helpers.
// Uma mordida que só existe na função pura não prova o pipeline (§5 2026-07-30).
//   ruim  → run com superfície clara fora do baseline  → exit 1
//   boa   → mesmo run, achado já no baseline           → exit 0
//   muda  → espelho inexistente                        → exit 2 "NÃO MEDI", nunca 0
import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const SONDA = join(dirname(fileURLToPath(import.meta.url)), 'tema-escuro-probe.mjs');
const dir = mkdtempSync(join(tmpdir(), 'tema-escuro-'));
const run = (...a) => spawnSync(process.execPath, [SONDA, ...a], { encoding: 'utf8' });

const runJson = join(dir, 'run.json');
writeFileSync(runJson, JSON.stringify([
  { rota: 'vendas', estado: 'ok', claros: [{ seletor: 'div.vd-tip', bg: 'oklch(0.95 0 0)', n: 1 }] },
  { rota: 'inbox', estado: 'ok', claros: [] },
]));
const vazio = join(dir, 'vazio.json');
writeFileSync(vazio, JSON.stringify({ achados: [] }));
const comAchado = join(dir, 'com.json');
writeFileSync(comAchado, JSON.stringify({ achados: ['vendas :: div.vd-tip'] }));

const casos = [
  ['selftest das partes puras', run('--selftest'), 0, null],
  ['RUIM: achado novo reprova', run('--de-json', runJson, '--baseline', vazio), 1, /NOVO vendas :: div\.vd-tip/],
  ['BOA: achado no baseline passa', run('--de-json', runJson, '--baseline', comAchado), 0, /1 com superfície clara/],
  ['MUDA: sem espelho sai 2 e diz NÃO MEDI', run('--espelho', join(dir, 'nao-existe')), 2, /NÃO MEDI/],
];

let falhou = 0;
for (const [nome, r, rc, padrao] of casos) {
  const saida = (r.stdout || '') + (r.stderr || '');
  const ok = r.status === rc && (!padrao || padrao.test(saida));
  if (!ok) falhou++;
  console.log(`${ok ? '✓' : '✗'} ${nome}${ok ? '' : ` — rc=${r.status} (esperado ${rc})\n${saida.slice(0, 400)}`}`);
}
rmSync(dir, { recursive: true, force: true });
console.log(`\n${casos.length - falhou}/${casos.length} ok`);
process.exit(falhou ? 1 : 0);
