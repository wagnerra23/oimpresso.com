#!/usr/bin/env node
// SELF-TEST — prova que charter-refs.mjs MORDE (acima do teto → exit 1) e LIBERA
// (no teto → exit 0), e que --fix corrige só off-by-one seguro sem tocar link bom
// nem ref genuinamente morta. Monta repo-fixture temporário (mesmo code path cwd-based).
// Rodar: node scripts/governance/charter-refs.test.mjs — exit 0 = passa.

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'charter-refs.mjs');

let fails = 0;
const check = (name, cond) => { console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}`); if (!cond) fails++; };

// ── fixture: 1 charter em Pages/Foo/ (precisa de 4 "../" pra alcançar a raiz) ──
const root = mkdtempSync(join(tmpdir(), 'charterrefs-'));
mkdirSync(join(root, 'memory'), { recursive: true });
mkdirSync(join(root, 'governance'), { recursive: true });
mkdirSync(join(root, 'resources', 'js', 'Pages', 'Foo'), { recursive: true });
writeFileSync(join(root, 'memory', 'x.md'), '# alvo real\n');

const charter = join(root, 'resources', 'js', 'Pages', 'Foo', 'Index.charter.md');
const body = [
  '---', 'tier: A', '---', '',
  '- [bom](../../../../memory/x.md)',      // 4 "../" = correto → NÃO quebrado (control)
  '- [fixavel](../../../memory/x.md)',     // 3 "../" = raso por 1 → quebrado, +1 resolve
  '- [morto](../../../memory/ghost.md)',   // alvo não existe em nenhuma profundidade
  '',
].join('\n');
writeFileSync(charter, body);

const setCeiling = (n) => writeFileSync(join(root, 'governance', 'charter-refs-baseline.json'), JSON.stringify({ ceiling: n }));
const run = (extra = []) => spawnSync('node', [SCRIPT, ...extra], { cwd: root, encoding: 'utf8' });

// 1. MORDE: teto 0, mas há 2 quebradas (fixavel + morto) → exit 1
setCeiling(0);
const over = run(['--check']);
check('--check MORDE acima do teto (exit 1)', over.status === 1);
check('--check aponta a tela quebrada', /Foo\/Index\.charter\.md/.test(over.stdout + over.stderr));

// 2. LIBERA: teto 2 = exatamente as 2 quebradas → exit 0
setCeiling(2);
const atCeil = run(['--check']);
check('--check LIBERA no teto exato (exit 0)', atCeil.status === 0);

// 3. --fix corrige só o off-by-one; deixa bom e morto intactos
run(['--fix']);
const fixed = readFileSync(charter, 'utf8');
check('--fix corrige o fixável (+1 "../")', fixed.includes('[fixavel](../../../../memory/x.md)'));
check('--fix NÃO toca o link bom', fixed.includes('[bom](../../../../memory/x.md)'));
check('--fix NÃO toca a ref morta', fixed.includes('[morto](../../../memory/ghost.md)'));

// 4. pós-fix: só 1 quebrada (a morta) → abaixo do teto 2 → exit 0 + sugere descer
const afterFix = run(['--check']);
check('pós-fix passa abaixo do teto (exit 0)', afterFix.status === 0);
check('pós-fix sugere DESCER o baseline (2→1)', /pode DESCER|2.?.?1/.test(afterFix.stdout));

console.log(fails === 0 ? '\n✓ todos os checks passaram' : `\n✗ ${fails} check(s) falharam`);
process.exit(fails === 0 ? 0 : 1);
