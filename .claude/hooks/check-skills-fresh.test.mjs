#!/usr/bin/env node
// Teste do PORTE check-skills-fresh.mjs (ex-.ps1). Deriva do CONTRATO (skill nova entre
// sessões → avisa /sync-skills), NÃO do .ps1. Advisory: SEMPRE exit 0.
// Rodar: node .claude/hooks/check-skills-fresh.test.mjs

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { extractSlugs, formatMessage } from './check-skills-fresh.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'check-skills-fresh.mjs');
let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };

// ── extractSlugs (puro) ──────────────────────────────────────────────────────────
check('extractSlugs: pega slug de SKILL.md', JSON.stringify(extractSlugs(['.claude/skills/foo/SKILL.md', '.claude/skills/bar/SKILL.md'])) === JSON.stringify(['bar', 'foo']));
check('extractSlugs: dedup', extractSlugs(['.claude/skills/foo/SKILL.md', '.claude/skills/foo/SKILL.md']).length === 1);
check('extractSlugs: ignora linha nao-SKILL', extractSlugs(['app/x.php', '.claude/skills/z/SKILL.md']).length === 1);
check('extractSlugs: lista vazia → []', extractSlugs([]).length === 0);

// ── formatMessage (puro) ─────────────────────────────────────────────────────────
check('formatMessage vazio quando sem slugs', formatMessage([]) === '');
check('formatMessage conta + cita /sync-skills', (() => { const m = formatMessage(['a', 'b']); return /2 skill/.test(m) && /sync-skills/.test(m); })());
check('formatMessage trunca em 8 + "+N outras"', (() => { const m = formatMessage(Array.from({ length: 11 }, (_, i) => `s${i}`)); return /11 skill/.test(m) && /\+3 outras/.test(m); })());

// ── E2E: primeira execução (sem state) escreve state e sai exit 0 silencioso ──────
const tmp = mkdtempSync(join(tmpdir(), 'csf-'));
mkdirSync(join(tmp, '.claude', 'skills'), { recursive: true });
const r = spawnSync(process.execPath, [HOOK], { encoding: 'utf8', cwd: tmp });
check('E2E: primeira exec (state novo) → exit 0 silencioso', r.status === 0 && !r.stderr.trim());
// sem .claude/skills → exit 0
const tmp2 = mkdtempSync(join(tmpdir(), 'csf2-'));
check('E2E: sem dir de skills → exit 0', spawnSync(process.execPath, [HOOK], { encoding: 'utf8', cwd: tmp2 }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs extrai slugs, avisa /sync-skills, first-run silencioso, advisory exit 0.');
process.exit(fails ? 1 : 0);
