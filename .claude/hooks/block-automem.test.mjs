#!/usr/bin/env node
// Teste do PORTE cross-plataforma block-automem.mjs (ex-.ps1). Cada caso deriva do
// CONTRATO canônico (ADR 0061 + ADR 0131 3-tiers + proibicoes.md §Memória/governança),
// NÃO do output do .ps1 legado. Roda em Linux/CI (o .test.ps1 não rodava).
// Complementa scripts/governance/settings-automem-mwart-registration.test.mjs (REGISTRO).
//
// Rodar: node .claude/hooks/block-automem.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { shouldBlock, isOimpressoLocal, isLegacyAutomem, blockMessage } from './block-automem.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-automem.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── BLOCK: auto-mem privada legada (ADR 0061 — "ZERO auto-mem privada") ─────────
// O contrato vale em QUALQUER plataforma — a razão de ser do porte.
const BLOCK = [
  ['Windows fwd-slash', 'Write', 'C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/foo.md'],
  ['Windows backslash', 'Write', 'C:\\Users\\wagne\\.claude\\projects\\D--oimpresso-com\\memory\\foo.md'],
  ['Linux (Felipe/Maiara/Luiz)', 'Write', '/home/felipe/.claude/projects/oimpresso/memory/nota.md'],
  ['macOS', 'Edit', '/Users/maiara/.claude/projects/oimpresso/memory/MEMORY.md'],
  ['MultiEdit também bloqueia', 'MultiEdit', 'C:/Users/wagne/.claude/projects/x/memory/y.md'],
  ['AppData Local', 'Write', 'C:/Users/wagne/AppData/Local/claude/.claude-code/memory/f.md'],
  ['AppData Roaming', 'Write', 'C:/Users/wagne/AppData/Roaming/x.claude/memory/f.md'],
  ['subpasta dentro de memory/', 'Write', '/home/luiz/.claude/projects/p/memory/sub/f.md'],
];
for (const [nome, tool, path] of BLOCK) check(`BLOCK: ${nome}`, shouldBlock(tool, path) === true);

// ── ALLOW: os 3 tiers oficiais + fora de escopo (ADR 0131) ──────────────────────
const ALLOW = [
  ['tier 2 oimpresso-local (escape valve ADR 0131)', 'Write', 'C:/Users/wagne/.claude/oimpresso-local/tasks-pessoais.md'],
  ['tier 2 oimpresso-local Linux', 'Write', '/home/felipe/.claude/oimpresso-local/notas.md'],
  ['tier 1 canônico git memory/', 'Write', 'D:/oimpresso.com/memory/decisions/0131-tiering-memoria.md'],
  ['tier 1 canônico em worktree', 'Edit', 'D:/oimpresso.com/.claude/worktrees/w/memory/sessions/2026-07-09-x.md'],
  ['Read NÃO bloqueia (migração lê legado)', 'Read', 'C:/Users/wagne/.claude/projects/x/memory/y.md'],
  ['não-.md sob projects/memory fora do contrato', 'Write', 'C:/Users/wagne/.claude/projects/x/memory/y.json'],
  ['código normal do repo', 'Edit', 'Modules/Jana/Services/Foo.php'],
  ['path vazio (fail-open)', 'Write', ''],
];
for (const [nome, tool, path] of ALLOW) check(`ALLOW: ${nome}`, shouldBlock(tool, path) === false);

// ── discriminadores unitários (redundância de defesa) ───────────────────────────
check('isOimpressoLocal pega tier 2, solta projects/', isOimpressoLocal('c:/u/w/.claude/oimpresso-local/x.md') && !isOimpressoLocal('c:/u/w/.claude/projects/p/memory/x.md'));
check('isLegacyAutomem pega projects/memory, solta git memory/', isLegacyAutomem('/home/f/.claude/projects/p/memory/x.md') && !isLegacyAutomem('d:/oimpresso.com/memory/decisions/x.md'));
check('mensagem cita os 3 tiers (ADR 0131)', /oimpresso-local/.test(blockMessage('Write', 'x')) && /Vaultwarden/.test(blockMessage('Write', 'x')));

// ── E2E: stdin JSON → exit code (prova o wrapper + fail-open de parse) ───────────
function runHook(stdin) {
  return spawnSync(process.execPath, [HOOK], { input: stdin, encoding: 'utf8' }).status;
}
const j = (tool, path) => JSON.stringify({ tool_name: tool, tool_input: { file_path: path } });
check('E2E: auto-mem legada → exit 2 (BLOQUEIA)', runHook(j('Write', '/home/felipe/.claude/projects/p/memory/f.md')) === 2);
check('E2E: oimpresso-local → exit 0 (escape valve)', runHook(j('Write', '/home/felipe/.claude/oimpresso-local/f.md')) === 0);
check('E2E: git canônico → exit 0', runHook(j('Write', 'D:/oimpresso.com/memory/decisions/0001-x.md')) === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('') === 0);
check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava sessão)', runHook('{lixo não-json') === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs bloqueia auto-mem legada em Win/Mac/Linux, libera os 3 tiers ADR 0131; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
