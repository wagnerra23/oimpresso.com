#!/usr/bin/env node
// Teste do PORTE cross-plataforma block-memory-drift.mjs (ex-.ps1). Cada caso deriva
// do CONTRATO canônico (Constituição v2 Art. 3 append-only + ADR 0130 handoffs +
// proibições "ADRs CANON são append-only"), NÃO do output do .ps1 legado.
// Núcleo decide() é PURO (branch/exists injetados) → determinístico em qualquer CI.
//
// Rodar: node .claude/hooks/block-memory-drift.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { decide, classifyPath, findRepoRoot } from './block-memory-drift.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-memory-drift.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };
const d = (o) => decide({ toolName: 'Edit', branch: 'claude/x', exists: true, ...o });

// ── Regra B: ADR existente = append-only em QUALQUER branch ─────────────────────
check('B: Edit em ADR existente bloqueia (claude/*)', d({ filePath: 'memory/decisions/0094-constituicao-v2.md' })?.rule === 'B');
check('B: Edit em ADR existente bloqueia até em claude/*, Windows backslash', d({ filePath: 'D:\\repo\\memory\\decisions\\0093-multi-tenant.md' })?.rule === 'B');
check('B: MultiEdit também bloqueia', decide({ toolName: 'MultiEdit', filePath: 'memory/decisions/0094-x.md', branch: 'main', exists: true })?.rule === 'B');
check('B: mensagem instrui supersedes: [NNNN]', /supersedes: \[0094\]/.test(d({ filePath: 'memory/decisions/0094-x.md' }).message));

// ── Regra D: ADR nova exige branch claude/* ─────────────────────────────────────
check('D: ADR nova em claude/* passa', d({ filePath: 'memory/decisions/9999-nova.md', exists: false }) === null);
check('D: ADR nova em main bloqueia', d({ filePath: 'memory/decisions/9999-nova.md', exists: false, branch: 'main' })?.rule === 'D');
check('D: ADR nova em feature/x bloqueia', d({ filePath: 'memory/decisions/9999-nova.md', exists: false, branch: 'feature/x' })?.rule === 'D');

// ── Regras C/E: handoff append-only / novo livre ────────────────────────────────
check('C: handoff existente bloqueia em qualquer branch', d({ filePath: 'memory/handoffs/2026-05-13-x.md', branch: 'main' })?.rule === 'C');
check('E: handoff novo passa em QUALQUER branch (documenta a sessão)', d({ filePath: 'memory/handoffs/2026-07-09-1200-novo.md', exists: false, branch: 'main' }) === null);

// ── Regra G: CONSTITUTION supremo ───────────────────────────────────────────────
check('G: CONSTITUTION.md bloqueia até em claude/*', d({ filePath: 'memory/governance/CONSTITUTION.md' })?.rule === 'G');

// ── Regras F/A: outros canon exigem claude/* ────────────────────────────────────
check('A: proibicoes.md em main bloqueia', d({ filePath: 'memory/proibicoes.md', branch: 'main' })?.rule === 'A');
check('F: regras-time.md em feature/x bloqueia', d({ filePath: 'memory/regras-time.md', branch: 'feature/x' })?.rule === 'F');
check('F/A: proibicoes.md em claude/* passa (vai pra PR)', d({ filePath: 'memory/proibicoes.md' }) === null);
check('F/A: governance/ENFORCEMENT.md em claude/* passa', d({ filePath: 'memory/governance/ENFORCEMENT.md' }) === null);
check('A: governance/srs/ em main bloqueia', d({ filePath: 'memory/governance/srs/doc.md', branch: 'main' })?.rule === 'A');

// ── Fora de escopo (editáveis por design) ───────────────────────────────────────
check('proposals/ é editável (rascunho)', d({ filePath: 'memory/decisions/proposals/0320-x.md', branch: 'main' }) === null);
check('sessions/ fora de escopo', d({ filePath: 'memory/sessions/2026-07-09-x.md', branch: 'main' }) === null);
check('requisitos/ (SPECs vivos) fora de escopo', d({ filePath: 'memory/requisitos/Jana/SPEC.md', branch: 'main' }) === null);
check('reference/ fora de escopo', d({ filePath: 'memory/reference/feedback-x.md', branch: 'main' }) === null);
check('código fora de memory/ fora de escopo', d({ filePath: 'Modules/Jana/Services/Foo.php', branch: 'main' }) === null);
check('Read não bloqueia', decide({ toolName: 'Read', filePath: 'memory/decisions/0094-x.md', branch: 'main', exists: true }) === null);

// ── classifyPath: discriminadores ───────────────────────────────────────────────
check('classifyPath extrai NNNN', classifyPath('memory/decisions/0143-fsm.md').adrNnnn === '0143');
check('classifyPath case-insensitive (CONSTITUTION vs constitution)', classifyPath('memory/governance/CONSTITUTION.md').isConstitution === true);
check('classifyPath acha memory/ em path absoluto de worktree', classifyPath('D:/oimpresso.com/.claude/worktrees/w/memory/proibicoes.md').isRootCanon === true);
check('findRepoRoot sobe do dir de hooks até a raiz com memory/', findRepoRoot(dirname(fileURLToPath(import.meta.url))) !== null);

// ── E2E: stdin JSON → exit code (casos independentes de branch) ─────────────────
function runHook(stdin, env = {}) {
  return spawnSync(process.execPath, [HOOK], { input: stdin, encoding: 'utf8', env: { ...process.env, ...env } });
}
const j = (tool, path) => JSON.stringify({ tool_name: tool, tool_input: { file_path: path } });
check('E2E: Edit em ADR real existente (0093) → exit 2 em qualquer branch', runHook(j('Edit', 'memory/decisions/0093-multi-tenant-isolation-tier-0.md')).status === 2);
check('E2E: Edit em CONSTITUTION.md → exit 2', runHook(j('Edit', 'memory/governance/CONSTITUTION.md')).status === 2);
check('E2E: Edit em sessions/ → exit 0', runHook(j('Edit', 'memory/sessions/2026-07-09-x.md')).status === 0);
check('E2E: override Wagner Tier 0 libera com warning loud', (() => {
  const r = runHook(j('Edit', 'memory/decisions/0093-multi-tenant-isolation-tier-0.md'), { OIMPRESSO_MEMORY_OVERRIDE: '1' });
  return r.status === 0 && /OVERRIDE ATIVO/.test(r.stderr || '');
})());
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('').status === 0);
check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava sessão)', runHook('{lixo').status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs aplica as 7 regras append-only/PR-only em Win/Mac/Linux, escapes (proposals/sessions/handoff-novo/override) preservados; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
