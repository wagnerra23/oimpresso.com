#!/usr/bin/env node
// Teste do PORTE preflight-new-capability.mjs (ex-.ps1). Deriva do CONTRATO (anti-reinvenção
// de framework ao criar Checker/Reconciler/Tool/Command/Service novo), NÃO do .ps1.

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { toFwd, capabilityReason, buildOutput } from './preflight-new-capability.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'preflight-new-capability.mjs');
const BS = String.fromCharCode(92);
let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };

// ── capabilityReason (puro, backslash-safe) ──────────────────────────────────────
check('reason: Checker sob Modules → DriftChecker', /DriftChecker/.test(capabilityReason('Modules/Governance/FooChecker.php') || ''));
check('reason: Tool sob Modules → MCP Tools', /MCP Tools/.test(capabilityReason('Modules/Jana/Mcp/Tools/BarTool.php') || ''));
check('reason: Service sob app → grep Service', /grep Service/.test(capabilityReason('app/Services/BazService.php') || ''));
check('reason: backslash Windows', /DriftChecker/.test(capabilityReason('Modules' + BS + 'Gov' + BS + 'XChecker.php') || ''));
check('reason: Checker FORA de Modules/app → null', capabilityReason('lib/FooChecker.php') === null);
check('reason: arquivo comum → null', capabilityReason('Modules/Jana/Services/../Foo.php') === null || capabilityReason('Modules/Jana/Entities/Lead.php') === null);
check('reason: vazio → null', capabilityReason('') === null);
check('buildOutput: allow + cita o arquivo', (() => { const o = buildOutput('Modules/X/FooChecker.php', 'r'); return o.hookSpecificOutput.permissionDecision === 'allow' && /FooChecker/.test(o.hookSpecificOutput.permissionDecisionReason); })());

// ── E2E ──────────────────────────────────────────────────────────────────────────
const tmp = mkdtempSync(join(tmpdir(), 'pnc-'));
mkdirSync(join(tmp, 'Modules', 'Gov'), { recursive: true });
function run(input, cwd = tmp) { return spawnSync(process.execPath, [HOOK], { input: JSON.stringify(input), encoding: 'utf8', cwd }); }
const w = (fp) => ({ tool_name: 'Write', tool_input: { file_path: fp } });

const fired = run(w('Modules/Gov/NovoChecker.php'));
check('E2E: Write Checker novo → exit 0 + JSON allow', fired.status === 0 && (() => { try { return /DriftChecker/.test(JSON.parse(fired.stdout).hookSpecificOutput.permissionDecisionReason); } catch { return false; } })());
// arquivo JÁ existente → silencioso
const existente = join(tmp, 'Modules', 'Gov', 'VelhoChecker.php');
writeFileSync(existente, '<?php');
check('E2E: Checker que JÁ existe → exit 0 silencioso (só arquivo novo)', (() => { const r = run(w(existente)); return r.status === 0 && !r.stdout.trim(); })());
check('E2E: arquivo não-capability → exit 0 silencioso', (() => { const r = run(w('Modules/Gov/Lead.php')); return r.status === 0 && !r.stdout.trim(); })());
check('E2E: Edit (não Write) → exit 0', run({ tool_name: 'Edit', tool_input: { file_path: 'Modules/Gov/NovoChecker.php' } }).status === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '', encoding: 'utf8' }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '{lixo', encoding: 'utf8' }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs avisa anti-reinvenção em capability nova, só arquivo novo, advisory; fail-open provado.');
process.exit(fails ? 1 : 0);
