#!/usr/bin/env node
// Teste do PORTE cross-plataforma post-merge-ui-smoke-required.mjs (ex-.ps1). Cada caso
// deriva do CONTRATO canônico (proibicoes.md §"Claim sem evidência" bullet pós-merge UI +
// PROTOCOLO-WAGNER R1 smoke real: merge UI → browser MCP + screenshot ANTES de declarar
// "pronto|deployed|funcionando|ao vivo|live em prod"), NÃO do output do .ps1 legado.
// Flag isolada via OIMPRESSO_UI_SMOKE_FLAG (tmpdir hermético). Roda em Linux/CI.
// Complementa scripts/governance/settings-evidence-smoke-registration.test.mjs (REGISTRO).
//
// Rodar: node .claude/hooks/post-merge-ui-smoke-required.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, writeFileSync, existsSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { isAdminMerge, extractPrNumber, isUiFile, isBrowserSmokeTool, isClaim, flagIsFresh, parseFlag, blockMessage } from './post-merge-ui-smoke-required.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'post-merge-ui-smoke-required.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── caso 1 (gatilho): merge admin + superfície UI ────────────────────────────────
check('merge admin detectado', isAdminMerge('gh pr merge 4028 --admin --squash'));
check('merge sem --admin não gatilha caso 1', !isAdminMerge('gh pr merge 4028 --squash'));
check('extrai PR number', extractPrNumber('gh pr merge 4028 --admin') === '4028');
check('UI: Page .tsx', isUiFile('resources/js/Pages/Sells/Index.tsx'));
check('UI: Component .tsx', isUiFile('resources/js/Components/Button.tsx'));
check('UI: css', isUiFile('resources/css/app.css'));
check('UI: blade core', isUiFile('resources/views/sale_pos/create.blade.php'));
check('UI: blade de módulo', isUiFile('Modules/Repair/Resources/views/kanban.blade.php'));
check('não-UI: Controller PHP', !isUiFile('Modules/Jana/Http/Controllers/Foo.php'));
check('não-UI: teste .ts fora de resources', !isUiFile('scripts/governance/foo.ts'));

// ── caso 2: browser MCP prova que Claude está OLHANDO (F7: minúscula/hífen real) ─
check('smoke: mcp__claude-in-chrome__navigate (nome REAL pós-F7)', isBrowserSmokeTool('mcp__claude-in-chrome__navigate'));
check('smoke: mcp__Claude_in_Chrome__read_page (grafia antiga segue coberta)', isBrowserSmokeTool('mcp__Claude_in_Chrome__read_page'));
check('smoke: mcp__computer-use__screenshot', isBrowserSmokeTool('mcp__computer-use__screenshot'));
check('não-smoke: tabs_close não é olhar a tela', !isBrowserSmokeTool('mcp__claude-in-chrome__tabs_close_mcp'));
check('não-smoke: tool comum', !isBrowserSmokeTool('Bash'));

// ── caso 3: claims canônicos (proibicoes: pronto|deployed|funcionando|ao vivo|live) ─
for (const c of ['echo "pronto"', 'echo deployed com sucesso', 'echo "funcionando em prod"', 'echo "ao vivo"', 'echo "live em prod"', 'echo "smoke ok"']) {
  check(`claim: ${c}`, isClaim(c));
}
check('não-claim: git status', !isClaim('git status'));
check('não-claim: npm run build', !isClaim('npm run build'));

// ── flag: parse + TTL 5min ───────────────────────────────────────────────────────
const now = Date.now();
const iso = (msAgo) => new Date(now - msAgo).toISOString();
check('flag fresca (30s) vale', flagIsFresh(`${iso(30_000)}|4028`, now));
check('flag velha (6min) expira', !flagIsFresh(`${iso(360_000)}|4028`, now));
check('flag corrompida não vale (fail-open)', !flagIsFresh('lixo-sem-pipe', now) && parseFlag('lixo') === null);
check('mensagem cita R1 + browser MCP + escape valves', (() => {
  const m = blockMessage('4028', 12, 'echo "pronto"');
  return /R1/.test(m) && /claude-in-chrome/.test(m) && /no-ui-smoke/.test(m) && /OIMPRESSO_UI_SMOKE_OVERRIDE/.test(m);
})());

// ── E2E: stdin JSON → exit code, flag hermética via OIMPRESSO_UI_SMOKE_FLAG ──────
const dir = mkdtempSync(join(tmpdir(), 'ui-smoke-fixture-'));
const FLAG = join(dir, 'pending.flag');
function runHook(payload, env = {}) {
  return spawnSync(process.execPath, [HOOK], {
    input: typeof payload === 'string' ? payload : JSON.stringify(payload),
    encoding: 'utf8',
    env: { ...process.env, OIMPRESSO_UI_SMOKE_FLAG: FLAG, ...env },
  });
}
const preBash = (cmd) => ({ hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: cmd } });

// bite: flag fresca + claim → exit 2
writeFileSync(FLAG, `${new Date().toISOString()}|4028`);
check('E2E bite: claim com merge UI pendente → exit 2 (BLOQUEIA)', runHook(preBash('echo "pronto"')).status === 2);
check('E2E: comando neutro com flag fresca → exit 0 (só claim bloqueia)', runHook(preBash('git status')).status === 0);
// release: browser MCP limpa a flag e o claim passa
check('E2E release: browser MCP → exit 0 e flag LIMPA', (() => {
  const r = runHook({ hook_event_name: 'PreToolUse', tool_name: 'mcp__claude-in-chrome__navigate', tool_input: {} });
  return r.status === 0 && !existsSync(FLAG);
})());
check('E2E: claim SEM flag (smoke já feito) → exit 0', runHook(preBash('echo "pronto"')).status === 0);
// TTL: flag velha expira e é removida
writeFileSync(FLAG, `${new Date(Date.now() - 10 * 60 * 1000).toISOString()}|4028`);
check('E2E: flag >5min → exit 0 e flag removida (TTL)', runHook(preBash('echo "pronto"')).status === 0 && !existsSync(FLAG));
// override global
writeFileSync(FLAG, `${new Date().toISOString()}|4028`);
check('E2E: OIMPRESSO_UI_SMOKE_OVERRIDE=1 → exit 0 mesmo com flag fresca', runHook(preBash('echo "pronto"'), { OIMPRESSO_UI_SMOKE_OVERRIDE: '1' }).status === 0);
rmSync(FLAG, { force: true });
// caso 1 sem gh de verdade: comando não-merge → não grava flag
check('E2E: PostToolUse não-merge → exit 0 e sem flag', (() => {
  const r = runHook({ hook_event_name: 'PostToolUse', tool_name: 'Bash', tool_input: { command: 'git status' } });
  return r.status === 0 && !existsSync(FLAG);
})());
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('').status === 0);
check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava sessão)', runHook('{lixo').status === 0);

rmSync(dir, { recursive: true, force: true });

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs enforça R1 (merge UI → browser MCP antes de claim) com flag mecânica cross-plataforma; bite/release/TTL/override/fail-open provados (E2E).');
process.exit(fails ? 1 : 0);
