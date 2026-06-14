#!/usr/bin/env node
// TESTE DE REGRESSÃO — prova que ghost-fix.mjs NUNCA toca **/adr/** (append-only, ADR 0094 Art.3).
// Monta um repo-fixture temporário e exercita o MESMO code path do codemod real (cwd-based).
// Origem: um ADR de rename CITA o nome antigo como FATO (ex: MemCofre/adr/0008-rename-docvault…);
// um --write cego o corromperia. Rodar: node scripts/governance/ghost-fix.test.mjs — exit 0 = passa.

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'ghost-fix.mjs');

let fails = 0;
const check = (name, cond) => { console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}`); if (!cond) fails++; };

// ── repo-fixture: Modules/Jana existe (to); Copiloto é ghost (from); um SPEC e dois ADRs citam Copiloto ──
const root = mkdtempSync(join(tmpdir(), 'ghostfix-'));
mkdirSync(join(root, 'Modules', 'Jana'), { recursive: true });
mkdirSync(join(root, 'governance'), { recursive: true });
mkdirSync(join(root, 'memory', 'requisitos', 'Foo', 'adr', 'sub'), { recursive: true });
writeFileSync(join(root, 'governance', 'ghost-rename-map.json'),
  JSON.stringify({ version: 'test', renames: { Copiloto: { to: 'Jana' } }, excluded: {} }));
const spec = join(root, 'memory', 'requisitos', 'Foo', 'SPEC.md');
const adr = join(root, 'memory', 'requisitos', 'Foo', 'adr', '0001-rename.md');
const adrNested = join(root, 'memory', 'requisitos', 'Foo', 'adr', 'sub', '0002.md');
writeFileSync(spec, 'Ver Modules/Copiloto/Service.\n');
writeFileSync(adr, 'ADR de rename: Modules/Copiloto virou outra coisa (FATO historico).\n');
writeFileSync(adrNested, 'Modules/Copiloto citado em adr aninhado.\n');

const run = (extra = []) => spawnSync('node', [SCRIPT, ...extra], { cwd: root, encoding: 'utf8' });

// 1. dry-run --json: conta SÓ o SPEC.md (1 ocorrência), NUNCA os 2 arquivos sob adr/.
const j = JSON.parse(run(['--json']).stdout);
check('dry-run conta só fora de adr/ (1 ocorrência)', j.summary.occurrences_mapped === 1);
check('scope declara a exclusão de adr/', /adr/.test(j.summary.scope));

// 2. --write reescreve o SPEC mas deixa os 2 ADRs byte-idênticos (append-only intacto).
run(['--write']);
const specTxt = readFileSync(spec, 'utf8');
check('SPEC.md reescrito (Copiloto→Jana)', specTxt.includes('Modules/Jana') && !specTxt.includes('Modules/Copiloto'));
check('ADR intacto (append-only)', readFileSync(adr, 'utf8').includes('Modules/Copiloto'));
check('ADR aninhado intacto', readFileSync(adrNested, 'utf8').includes('Modules/Copiloto'));

console.log(fails ? `\n${fails} FALHA(S)` : '\nTODOS OS TESTES PASSARAM');
process.exit(fails ? 1 : 0);
