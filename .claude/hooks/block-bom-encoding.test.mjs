#!/usr/bin/env node
// Teste do PORTE cross-plataforma block-bom-encoding.mjs (ex-.ps1). Cada caso deriva
// do CONTRATO canônico (proibicoes.md §Ambiente BOM PS 5.1 + post-mortem v4 anti-pattern A),
// NÃO do output do .ps1 legado. Roda em Linux/CI (o .test.ps1 não rodava).
//
// Rodar: node .claude/hooks/block-bom-encoding.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { getMode, isCoveredPath, extractContents, hasBom, shouldFire, fireMessage } from './block-bom-encoding.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-bom-encoding.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

const BOM = '﻿';

// ── FIRE: BOM em arquivo de código (o incidente PR #984 era .php) ────────────────
check('FIRE: Write .php com BOM (o caso do incidente)', shouldFire('Write', { file_path: 'Modules/Cms/Http/Controllers/CmsController.php', content: BOM + '<?php' }) === true);
check('FIRE: Edit .tsx new_string com BOM', shouldFire('Edit', { file_path: 'resources/js/Pages/Sells/Create.tsx', new_string: BOM + 'export' }) === true);
check('FIRE: MultiEdit com BOM num dos edits', shouldFire('MultiEdit', { file_path: 'app/Util.php', edits: [{ new_string: 'ok' }, { new_string: BOM + 'x' }] }) === true);
check('FIRE: .mjs também coberto', shouldFire('Write', { file_path: '.claude/hooks/x.mjs', content: BOM + '#!' }) === true);

// ── NÃO dispara: fora do contrato ────────────────────────────────────────────────
check('SKIP: sem BOM', shouldFire('Write', { file_path: 'app/A.php', content: '<?php' }) === false);
check('SKIP: .md (BOM legível, sem crash PHP)', shouldFire('Write', { file_path: 'memory/x.md', content: BOM + '# t' }) === false);
check('SKIP: .json fora do contrato', shouldFire('Write', { file_path: 'composer.json', content: BOM + '{}' }) === false);
check('SKIP: fixture .bom. explícita', shouldFire('Write', { file_path: 'tests/f.bom.php', content: BOM + 'x' }) === false);
check('SKIP: fixture /fixtures/*bom*', shouldFire('Write', { file_path: 'tests/fixtures/utf8-bom.php', content: BOM + 'x' }) === false);
check('SKIP: Read não dispara', shouldFire('Read', { file_path: 'a.php', content: BOM }) === false);
check('SKIP: path vazio (fail-open)', shouldFire('Write', { file_path: '', content: BOM }) === false);
check('SKIP: BOM no MEIO não dispara (só início)', shouldFire('Write', { file_path: 'a.php', content: '<?php ' + BOM }) === false);

// ── unitários (redundância de defesa) ────────────────────────────────────────────
check('getMode default = warn', getMode({}) === 'warn');
check('getMode strict/off/lixo', getMode({ OIMPRESSO_BOM_HOOK_MODE: 'STRICT' }) === 'strict' && getMode({ OIMPRESSO_BOM_HOOK_MODE: 'off' }) === 'off' && getMode({ OIMPRESSO_BOM_HOOK_MODE: 'lixo' }) === 'warn');
check('isCoveredPath backslash Windows', isCoveredPath('Modules\\Crm\\Entities\\Lead.php') === true);
check('extractContents MultiEdit sem edits', extractContents('MultiEdit', {}).length === 0);
check('hasBom detecta U+FEFF', hasBom([BOM + 'x']) === true && hasBom(['x']) === false && hasBom(['']) === false);
check('mensagem cita post-mortem + override', /post-mortem v4/.test(fireMessage('Write', 'x')) && /OIMPRESSO_BOM_OVERRIDE/.test(fireMessage('Write', 'x')));

// ── E2E: stdin JSON → exit code (prova a MORDIDA + fail-open) ────────────────────
function runHook(stdin, env = {}) {
  return spawnSync(process.execPath, [HOOK], {
    input: stdin, encoding: 'utf8',
    env: { ...process.env, OIMPRESSO_BOM_HOOK_MODE: '', OIMPRESSO_BOM_OVERRIDE: '', ...env },
  });
}
const j = (tool, input) => JSON.stringify({ tool_name: tool, tool_input: input });
const bomPhp = j('Write', { file_path: 'app/A.php', content: BOM + '<?php' });

const strict = runHook(bomPhp, { OIMPRESSO_BOM_HOOK_MODE: 'strict' });
check('E2E MORDIDA: strict + BOM .php → exit 2', strict.status === 2);
check('E2E MORDIDA: razão no stderr', /BOM/.test(strict.stderr));
const warn = runHook(bomPhp);
check('E2E: warn (default) + BOM → exit 0 com aviso stderr', warn.status === 0 && /modo warn/.test(warn.stderr));
check('E2E: strict + override env → exit 0', runHook(bomPhp, { OIMPRESSO_BOM_HOOK_MODE: 'strict', OIMPRESSO_BOM_OVERRIDE: '1' }).status === 0);
check('E2E: off ignora BOM → exit 0', runHook(bomPhp, { OIMPRESSO_BOM_HOOK_MODE: 'off' }).status === 0);
check('E2E: legítimo sem BOM → exit 0 silencioso', (() => { const r = runHook(j('Write', { file_path: 'app/A.php', content: '<?php' }), { OIMPRESSO_BOM_HOOK_MODE: 'strict' }); return r.status === 0 && !r.stderr; })());
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('', { OIMPRESSO_BOM_HOOK_MODE: 'strict' }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava sessão)', runHook('{lixo', { OIMPRESSO_BOM_HOOK_MODE: 'strict' }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs detecta BOM em código nos 3 OS, morde em strict, avisa em warn; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
