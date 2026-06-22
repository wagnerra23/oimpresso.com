#!/usr/bin/env node
// @ts-check
// SELF-TEST — prova que component-registry-check.mjs DETECTA drift (--strict exit 1) e
// LIBERA quando o registro bate com o código (exit 0). Monta repo-fixture temporário com
// componentes React fake + um registry, e varia o registry pra exercitar cada modo de drift.
// Rodar: node scripts/governance/component-registry-check.test.mjs — exit 0 = passa.

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'component-registry-check.mjs');

let fails = 0;
const check = (name, cond) => { console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}`); if (!cond) fails++; };

// ── fixture: repo com 2 componentes ui + 1 barril de layout ──
const root = mkdtempSync(join(tmpdir(), 'compreg-'));
const uiDir = join(root, 'resources', 'js', 'Components', 'ui');
const layoutDir = join(root, 'resources', 'js', 'Components', 'layout');
mkdirSync(uiDir, { recursive: true });
mkdirSync(layoutDir, { recursive: true });
mkdirSync(join(root, 'prototipo-ui'), { recursive: true });

writeFileSync(join(uiDir, 'button.tsx'), `
export function Button() { return null }
export { Button as default }
export const buttonVariants = () => {}
`);
writeFileSync(join(uiDir, 'badge.tsx'), `export { Badge, badgeVariants }\n`);
// barril que re-exporta de outro arquivo (testa export-from)
writeFileSync(join(layoutDir, 'box.tsx'), `export function Box() { return null }\n`);
writeFileSync(join(layoutDir, 'index.ts'), `export { Box, type BoxProps } from "./box"\n`);

const REG = join(root, 'prototipo-ui', 'component-registry.json');
const writeReg = (entries) => writeFileSync(REG, JSON.stringify({ version: '1', entries }, null, 2));
const run = (extra = []) => spawnSync('node', [SCRIPT, '--root', root, '--registry', REG, ...extra], { encoding: 'utf8' });

// registro BOM (bate com tudo): Button (com re-export-from no barril), gap explícito
const goodEntries = [
  { bloco_prototipo: '.cw-btn-primary', componente_react: 'Button', import_path: '@/Components/ui/button', file: 'resources/js/Components/ui/button.tsx', exports: ['Button', 'buttonVariants', 'default'], status: 'mapped' },
  { bloco_prototipo: '.badge', componente_react: 'Badge', import_path: '@/Components/ui/badge', file: 'resources/js/Components/ui/badge.tsx', exports: ['Badge', 'badgeVariants'], status: 'mapped' },
  { bloco_prototipo: 'Box', componente_react: 'Box', import_path: '@/Components/layout', file: 'resources/js/Components/layout/index.ts', exports: ['Box'], status: 'mapped' },
  { bloco_prototipo: 'c-nba (buraco DS)', componente_react: null, import_path: null, file: null, exports: [], status: 'gap' },
];

// 1. LIBERA: registro íntegro → advisory exit 0 E strict exit 0
writeReg(goodEntries);
const okAdvisory = run(['--check']);
check('advisory passa com registro íntegro (exit 0)', okAdvisory.status === 0);
const okStrict = run(['--check', '--strict']);
check('strict passa com registro íntegro (exit 0)', okStrict.status === 0);
check('barril re-export-from resolve (Box via index.ts → box.tsx)', /íntegro/.test(okStrict.stdout));

// 2. DRIFT — export removido: registry pede símbolo que não existe
writeReg([{ ...goodEntries[0], exports: ['Button', 'FantasmaQueNaoExiste'] }]);
const missExport = run(['--check', '--strict']);
check('strict MORDE export ausente (exit 1)', missExport.status === 1);
check('aponta o export fantasma', /FantasmaQueNaoExiste/.test(missExport.stdout));
check('advisory NÃO morde (exit 0) mesmo com drift', run(['--check']).status === 0);

// 3. DRIFT — import_path quebrado (componente renomeado/movido)
writeReg([{ ...goodEntries[0], import_path: '@/Components/ui/botao-renomeado' }]);
const badImport = run(['--check', '--strict']);
check('strict MORDE import_path que não resolve (exit 1)', badImport.status === 1);
check('aponta o import quebrado', /não resolve/.test(badImport.stdout));

// 4. DRIFT — file não existe (componente deletado)
writeReg([{ ...goodEntries[0], file: 'resources/js/Components/ui/deletado.tsx', import_path: '@/Components/ui/deletado' }]);
const noFile = run(['--check', '--strict']);
check('strict MORDE file inexistente (exit 1)', noFile.status === 1);

// 5. DRIFT — gap FABRICADO: status gap mas com file/import (M-AP-6 violado)
writeReg([{ bloco_prototipo: 'c-id fabricado', componente_react: 'FichaId', import_path: '@/Components/ui/button', file: 'resources/js/Components/ui/button.tsx', exports: ['Button'], status: 'gap' }]);
const fabricatedGap = run(['--check', '--strict']);
check('strict MORDE gap com fabricação (exit 1)', fabricatedGap.status === 1);
check('aponta fabricação no gap', /fabricação/.test(fabricatedGap.stdout));

// 6. gap LEGÍTIMO (sem file/import/exports) NÃO morde
writeReg([{ bloco_prototipo: 'c-tl', componente_react: null, import_path: null, file: null, exports: [], status: 'gap' }]);
check('gap legítimo passa (exit 0)', run(['--check', '--strict']).status === 0);

console.log(fails === 0 ? '\n✓ todos os checks passaram' : `\n✗ ${fails} check(s) falharam`);
process.exit(fails === 0 ? 0 : 1);
