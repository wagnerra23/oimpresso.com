#!/usr/bin/env node
// Teste do PORTE cross-plataforma mcp-first-warning.mjs (ex-.ps1). Deriva do CONTRATO
// (skill mcp-first: memory/* tem tool MCP auditada + mais barata), NÃO do output do .ps1.
// Advisory: SEMPRE exit 0 — prova o CLASSIFICADOR (matches/suggestFor) + schema hookSpecificOutput.
//
// Rodar: node .claude/hooks/mcp-first-warning.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { matches, suggestFor, buildOutput } from './mcp-first-warning.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'mcp-first-warning.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── matches (puro) ───────────────────────────────────────────────────────────────
check('matches: memory/decisions/', matches('memory/decisions/0053-x.md') === true);
check('matches: memory/sessions/', matches('memory/sessions/2026-07-20-x.md') === true);
check('matches: memory/requisitos/*.md', matches('memory/requisitos/Jana/SPEC.md') === true);
check('matches: memory/08-handoff', matches('memory/08-handoff.md') === true);
check('matches: código normal NÃO casa', matches('Modules/Jana/Services/Foo.php') === false);
check('matches: alvo vazio NÃO casa', matches('') === false);

// ── suggestFor (ordem importa) ───────────────────────────────────────────────────
check('suggestFor: ADR slug → decisions-fetch slug', suggestFor('memory/decisions/0094-constituicao-v2-x.md') === 'decisions-fetch slug:"0094-constituicao-v2-x"');
check('suggestFor: decisions genérico → decisions-search', suggestFor('memory/decisions/') === 'decisions-search query:"..."');
check('suggestFor: sessions → sessions-recent', suggestFor('memory/sessions/x.md') === 'sessions-recent limit:5');
check('suggestFor: handoff → cycles-active + my-work', suggestFor('memory/08-handoff.md') === 'cycles-active + my-work');
check('suggestFor: SPEC → tasks-list/detail', /tasks-list module/.test(suggestFor('memory/requisitos/Jana/SPEC.md')));
check('suggestFor: requisitos não-SPEC → default', suggestFor('memory/requisitos/Jana/RUNBOOK-x.md') === 'decisions-search ou cc-search');

// ── buildOutput (schema PreToolUse 2026) ─────────────────────────────────────────
const out = buildOutput('Read', 'memory/decisions/0094-x.md', 'decisions-fetch slug:"0094-x"');
check('buildOutput: permissionDecision=allow (informa, não corta)', out.hookSpecificOutput.permissionDecision === 'allow');
check('buildOutput: hookEventName=PreToolUse', out.hookSpecificOutput.hookEventName === 'PreToolUse');
check('buildOutput: reason cita a tool e a sugestão', /Read/.test(out.hookSpecificOutput.permissionDecisionReason) && /decisions-fetch/.test(out.hookSpecificOutput.permissionDecisionReason));

// ── E2E: advisory SEMPRE exit 0 (JSON no stdout quando casa) ─────────────────────
function runHook(input) { return spawnSync(process.execPath, [HOOK], { input: JSON.stringify(input), encoding: 'utf8' }); }
const fired = runHook({ tool_name: 'Read', tool_input: { file_path: 'memory/decisions/0053-x.md' } });
check('E2E: Read em memory/decisions → exit 0 + JSON hookSpecificOutput', fired.status === 0 && (() => { try { return JSON.parse(fired.stdout).hookSpecificOutput.permissionDecision === 'allow'; } catch { return false; } })());
const glob = runHook({ tool_name: 'Grep', tool_input: { pattern: 'memory/sessions/2026' } });
check('E2E: Grep com pattern em memory/sessions → casa (usa pattern)', glob.status === 0 && /sessions-recent/.test(glob.stdout));
check('E2E: Read fora de memory/ → exit 0 silencioso', (() => { const r = runHook({ tool_name: 'Read', tool_input: { file_path: 'app/Foo.php' } }); return r.status === 0 && !r.stdout.trim(); })());
check('E2E: stdin vazio → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '', encoding: 'utf8' }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '{lixo', encoding: 'utf8' }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs sugere tool MCP pra memory/*, emite hookSpecificOutput allow, NUNCA corta; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
