#!/usr/bin/env node
// Teste da LÓGICA de block-skill-design-sync-without-optin (ADR 0315 / Eixo B via matcher Skill).
// Prova: classificação da tool `Skill` (é design-sync? plugin-namespaced?), falso-positivo
// (outras skills passam), e o end-to-end real (spawn do hook → exit code).
// Complementa settings-design-sync-registration.test.mjs (que prova que o hook está REGISTRADO).
//
// Rodar: node .claude/hooks/block-skill-design-sync-without-optin.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { classifySkillCall } from './block-skill-design-sync-without-optin.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const HOOK = join(HERE, 'block-skill-design-sync-without-optin.mjs');

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK]   ' : '[FAIL] ') + name);
  if (!cond) fails++;
}

// ── 1. A skill design-sync é detectada (nome exato + variante plugin-namespaced) ─
check('skill "design-sync" é design-sync', classifySkillCall('Skill', { skill: 'design-sync' }).isDesignSyncSkill === true);
check('skill "plugin:design-sync" é design-sync (namespaced)', classifySkillCall('Skill', { skill: 'algum-plugin:design-sync' }).isDesignSyncSkill === true);
check('skill "  design-sync  " (trim) é design-sync', classifySkillCall('Skill', { skill: '  design-sync  ' }).isDesignSyncSkill === true);

// ── 2. Falso-positivo é veneno — outras skills passam ─────────────────────────
check('skill "commit-discipline" NÃO é design-sync', classifySkillCall('Skill', { skill: 'commit-discipline' }).isDesignSyncSkill === false);
check('skill "design-critique" NÃO é design-sync (não confundir)', classifySkillCall('Skill', { skill: 'design:design-critique' }).isDesignSyncSkill === false);
check('skill "design-system" NÃO é design-sync', classifySkillCall('Skill', { skill: 'design:design-system' }).isDesignSyncSkill === false);
check('skill "my-design-sync-helper" NÃO casa (segmento final != design-sync)', classifySkillCall('Skill', { skill: 'my-design-sync-helper' }).isDesignSyncSkill === false);
check('skill vazia NÃO é design-sync', classifySkillCall('Skill', { skill: '' }).isDesignSyncSkill === false);

// ── 3. Tool não-Skill passa reto ──────────────────────────────────────────────
check('tool Bash NÃO é Skill', classifySkillCall('Bash', { skill: 'design-sync' }).isSkill === false);
check('tool DesignSync (nativa) NÃO é Skill', classifySkillCall('DesignSync', { method: 'write_files' }).isSkill === false);

// ── 4. END-TO-END: spawn do hook, prova exit code real ────────────────────────
function runHook(payload, env = {}) {
  const r = spawnSync(process.execPath, [HOOK], {
    input: JSON.stringify(payload),
    encoding: 'utf8',
    env: { ...process.env, OIMPRESSO_DESIGN_SYNC_OK: '', ...env },
  });
  return { code: r.status, stderr: r.stderr || '' };
}

// /design-sync SEM opt-in → exit 2 (BLOQUEIA) — a porta oficial fechada
const e2eBlock = runHook({ hook_event_name: 'PreToolUse', tool_name: 'Skill', tool_input: { skill: 'design-sync' } });
check('E2E: skill design-sync sem opt-in → exit 2 (BLOQUEIA)', e2eBlock.code === 2);
check('E2E: stderr cita ADR 0315', /0315/.test(e2eBlock.stderr));

// variante namespaced SEM opt-in → exit 2
check('E2E: plugin:design-sync sem opt-in → exit 2', runHook({ hook_event_name: 'PreToolUse', tool_name: 'Skill', tool_input: { skill: 'x:design-sync' } }).code === 2);

// outra skill qualquer → exit 0 (não bloqueia o resto do sistema)
check('E2E: skill commit-discipline → exit 0 (livre)', runHook({ hook_event_name: 'PreToolUse', tool_name: 'Skill', tool_input: { skill: 'commit-discipline' } }).code === 0);

// design-sync COM opt-in (env) → exit 0
check('E2E: design-sync COM opt-in (env) → exit 0', runHook({ hook_event_name: 'PreToolUse', tool_name: 'Skill', tool_input: { skill: 'design-sync' } }, { OIMPRESSO_DESIGN_SYNC_OK: '1' }).code === 0);

// tool não-Skill → exit 0
check('E2E: tool Bash → exit 0 (não é Skill)', runHook({ hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: 'echo ok' } }).code === 0);

// evento não-PreToolUse → exit 0
check('E2E: UserPromptSubmit → exit 0 (só age em PreToolUse)', runHook({ hook_event_name: 'UserPromptSubmit', prompt: 'roda /design-sync' }).code === 0);

console.log('');
if (fails === 0) {
  console.log('[PASS] block-skill-design-sync: detecta a skill design-sync, ignora as outras, opt-in concede, E2E exit codes provados.');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s).`);
process.exit(1);
