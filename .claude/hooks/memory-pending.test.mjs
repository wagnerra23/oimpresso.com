#!/usr/bin/env node
// Teste do PORTE cross-plataforma memory-pending.mjs (ex-.ps1). Deriva do CONTRATO
// (skill memory-sync: falta push → team não vê via MCP), NÃO do output do .ps1. Roda em Linux/CI.
// Advisory: SEMPRE exit 0 — o teste prova o CLASSIFICADOR (formatMessage) + E2E git real.
//
// Rodar: node .claude/hooks/memory-pending.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { CANON_PATHS, pendingLines, formatMessage } from './memory-pending.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'memory-pending.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── formatMessage (puro) ─────────────────────────────────────────────────────────
check('formatMessage: vazio → string vazia', formatMessage([]) === '' && formatMessage(null) === '');
check('formatMessage: conta arquivos + cita /sync-mem', (() => { const m = formatMessage([' M memory/x.md', '?? MEMORY.md']); return /2 arquivo/.test(m) && /sync-mem/.test(m); })());
check('formatMessage: trunca em 10 + "+N outros"', (() => { const m = formatMessage(Array.from({ length: 13 }, (_, i) => ` M memory/f${i}.md`)); return /13 arquivo/.test(m) && /\+3 outros/.test(m); })());
check('CANON_PATHS cobre memory/ + governança raiz', CANON_PATHS.includes('memory/') && CANON_PATHS.includes('CLAUDE.md') && CANON_PATHS.includes('MANUAL_CLAUDE_CODE.md'));

// ── pendingLines (git real, repo temporário) ─────────────────────────────────────
const repo = mkdtempSync(join(tmpdir(), 'mempend-'));
const git = (...a) => spawnSync('git', ['-C', repo, ...a], { encoding: 'utf8' });
git('init', '-q'); git('config', 'user.email', 't@t'); git('config', 'user.name', 't');
mkdirSync(join(repo, 'memory'), { recursive: true });
// memory/ precisa ser dir JÁ TRACKED (como no repo real) — senão git colapsa untracked
// pra "?? memory/" e o arquivo novo não aparece individualizado.
writeFileSync(join(repo, 'memory', 'existente.md'), '# ja versionado');
writeFileSync(join(repo, 'seed.txt'), 'x'); git('add', '.'); git('commit', '-qm', 'seed');
check('pendingLines: repo limpo → []', pendingLines(repo).length === 0);
writeFileSync(join(repo, 'memory', 'nota.md'), '# nova'); // untracked em memory/
writeFileSync(join(repo, 'CLAUDE.md'), 'muda');           // novo na raiz canônica
writeFileSync(join(repo, 'outro.txt'), 'fora do escopo'); // NÃO deve contar
const pend = pendingLines(repo);
check('pendingLines: pega memory/ + CLAUDE.md (2), ignora outro.txt', pend.length === 2 && pend.some((l) => /memory\/nota\.md/.test(l)) && pend.some((l) => /CLAUDE\.md/.test(l)) && !pend.some((l) => /outro\.txt/.test(l)));

// ── E2E: advisory SEMPRE exit 0 (aviso no stderr quando há pendência) ────────────
function runHook(cwd) {
  return spawnSync(process.execPath, [HOOK], { input: JSON.stringify({ cwd }), encoding: 'utf8', cwd });
}
const dirty = runHook(repo);
check('E2E: com pendência → exit 0 (advisory NUNCA bloqueia)', dirty.status === 0);
check('E2E: aviso /sync-mem no stderr', /sync-mem/.test(dirty.stderr));
// repo limpo
const clean = mkdtempSync(join(tmpdir(), 'mempend-clean-'));
const gitc = (...a) => spawnSync('git', ['-C', clean, ...a], { encoding: 'utf8' });
gitc('init', '-q'); gitc('config', 'user.email', 't@t'); gitc('config', 'user.name', 't');
writeFileSync(join(clean, 'a.txt'), 'x'); gitc('add', '.'); gitc('commit', '-qm', 's');
check('E2E: repo limpo → exit 0 silencioso', (() => { const r = runHook(clean); return r.status === 0 && !/sync-mem/.test(r.stderr); })());
check('E2E: stdin vazio → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '', encoding: 'utf8', cwd: clean }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '{lixo', encoding: 'utf8', cwd: clean }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs detecta pendência em memory/governança, avisa /sync-mem, NUNCA bloqueia; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
