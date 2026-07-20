#!/usr/bin/env node
// Teste do PORTE charter-validate.mjs (ex-.ps1). Deriva do CONTRATO (Charter > Spec: editar
// Page com charter vivo sem charter-fetch antes → avisa), NÃO do .ps1. Rodar: node ...test.mjs

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { toFwd, matchPage, charterPathFor, readCharterStatus, buildOutput } from './charter-validate.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'charter-validate.mjs');
const BS = String.fromCharCode(92);
let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };

// ── matchPage (puro, backslash-safe) ─────────────────────────────────────────────
check('matchPage: Page top-level', (() => { const p = matchPage('resources/js/Pages/Sells/Index.tsx'); return p && p.modulo === 'Sells' && p.tela === 'Index'; })());
check('matchPage: Page em subdir', (() => { const p = matchPage('resources/js/Pages/Financeiro/Cockpit/View.tsx'); return p && p.tela === 'View'; })());
check('matchPage: backslash Windows', (() => { const p = matchPage('resources' + BS + 'js' + BS + 'Pages' + BS + 'Sells' + BS + 'Index.tsx'); return p && p.tela === 'Index'; })());
check('matchPage: _components exempt', matchPage('resources/js/Pages/_components/Foo.tsx') === null);
check('matchPage: tela _Private exempt', matchPage('resources/js/Pages/Sells/_Private.tsx') === null);
check('matchPage: App/Layout exempt', matchPage('resources/js/Pages/Sells/App.tsx') === null);
check('matchPage: fora de Pages → null', matchPage('resources/js/Components/Foo.tsx') === null);

// ── charterPathFor + readCharterStatus + buildOutput ─────────────────────────────
check('charterPathFor troca .tsx por .charter.md', charterPathFor('a/b/Index.tsx') === 'a/b/Index.charter.md');
const tmp = mkdtempSync(join(tmpdir(), 'cv-'));
const cp = join(tmp, 'Index.charter.md');
writeFileSync(cp, '---\nstatus: live\ntitle: x\n---\n# Charter');
check('readCharterStatus lê status: live', readCharterStatus(cp) === 'live');
check('readCharterStatus inexistente → unknown', readCharterStatus(join(tmp, 'no.md')) === 'unknown');
check('buildOutput warning → allow', buildOutput({ tool: 'Edit', pathFwd: 'x', charterRelative: 'y', charterStatus: 'live', strict: false }).hookSpecificOutput.permissionDecision === 'allow');
check('buildOutput strict → deny', buildOutput({ tool: 'Edit', pathFwd: 'x', charterRelative: 'y', charterStatus: 'live', strict: true }).hookSpecificOutput.permissionDecision === 'deny');

// ── E2E ──────────────────────────────────────────────────────────────────────────
const pagesDir = join(tmp, 'resources', 'js', 'Pages', 'Sells');
mkdirSync(pagesDir, { recursive: true });
const tsx = join(pagesDir, 'Index.tsx');
writeFileSync(tsx, 'export default function(){}');
writeFileSync(join(pagesDir, 'Index.charter.md'), '---\nstatus: live\n---\n');
function run(input, env = {}) { return spawnSync(process.execPath, [HOOK], { input: JSON.stringify(input), encoding: 'utf8', env: { ...process.env, CHARTER_VALIDATE_STRICT: '', ...env } }); }
const w = (fp) => ({ tool_name: 'Edit', tool_input: { file_path: fp } });

const warned = run(w(tsx));
check('E2E: Edit Page com charter → exit 0 + JSON allow', warned.status === 0 && (() => { try { return JSON.parse(warned.stdout).hookSpecificOutput.permissionDecision === 'allow'; } catch { return false; } })());
check('E2E: strict → JSON deny', (() => { const r = run(w(tsx), { CHARTER_VALIDATE_STRICT: '1' }); try { return JSON.parse(r.stdout).hookSpecificOutput.permissionDecision === 'deny'; } catch { return false; } })());
const tsxNoCharter = join(pagesDir, 'Other.tsx');
writeFileSync(tsxNoCharter, 'x');
check('E2E: Page SEM charter → exit 0 silencioso', (() => { const r = run(w(tsxNoCharter)); return r.status === 0 && !r.stdout.trim(); })());
check('E2E: não-Page → exit 0 silencioso', (() => { const r = run(w('resources/js/Components/Foo.tsx')); return r.status === 0 && !r.stdout.trim(); })());
check('E2E: stdin vazio → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '', encoding: 'utf8' }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '{lixo', encoding: 'utf8' }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs avisa charter-first em Page com charter vivo, deny em strict, advisory default; fail-open provado.');
process.exit(fails ? 1 : 0);
