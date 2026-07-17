#!/usr/bin/env node
// Teste do PORTE cross-plataforma block-routes-string-legacy.mjs (ex-.ps1). Cada caso deriva do
// CONTRATO canônico (.claude/rules/routes.md §"FQCN obrigatório" + incidente PR #843 +
// post-mortem-v4 anti-pattern A), NÃO do output do .ps1 legado — teste derivado da
// implementação é tautológico e trava o desvio em vez de pegá-lo (proibicoes.md §5, 2026-06-05).
// Roda em Linux/CI (o .test.ps1 não rodava).
//
// Rodar: node .claude/hooks/block-routes-string-legacy.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { verdict, isRoutesFile, isExempt, findLegacyMatches, extractContent, blockMessage } from './block-routes-string-legacy.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-routes-string-legacy.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// A forma exata do incidente #843: string legacy como 2º argumento da rota.
const LEGACY = `<?php\nRoute::get('/vendas', 'SellController@index');\n`;
const FQCN = `<?php\nuse Modules\\Sells\\Http\\Controllers\\SellController;\nRoute::get('/vendas', [SellController::class, 'index']);\n`;
const NOENV = {}; // env vazio → modo default (strict), sem override

// ── BLOCK: string legacy em arquivo de rotas (contrato: quebra route:cache) ──────
const BLOCK = [
  ['routes/web.php raiz (o incidente #843)', 'Write', 'D:/oimpresso.com/routes/web.php', { content: LEGACY }],
  ['routes/api.php', 'Write', '/home/felipe/oimpresso/routes/api.php', { content: LEGACY }],
  ['Modules/<X>/routes/web.php', 'Write', '/home/maiara/o/Modules/Sells/routes/web.php', { content: LEGACY }],
  ['Modules/<X>/Http/routes.php (layout nWidart antigo)', 'Write', '/Users/luiz/o/Modules/Jana/Http/routes.php', { content: LEGACY }],
  ['Edit new_string', 'Edit', '/home/f/o/routes/web.php', { new_string: `Route::post('/x', "FooController@store");` }],
  ['MultiEdit: 1 edit sujo entre limpos', 'MultiEdit', '/home/f/o/routes/web.php', { edits: [{ new_string: FQCN }, { new_string: LEGACY }] }],
  ['aspas duplas também', 'Write', '/home/f/o/routes/web.php', { content: `Route::get('/y', "BarController@show");` }],
  ['case-blind no path (Windows)', 'Write', 'D:/OImpresso.com/Routes/Web.php', { content: LEGACY }],
];
for (const [nome, tool, path, ti] of BLOCK) check(`BLOCK: ${nome}`, verdict(tool, path, ti, NOENV) === 'block');

// ── ALLOW: o que o contrato manda fazer + fora de escopo ─────────────────────────
const ALLOW = [
  ['FQCN [Class::class, method] — a forma correta', 'Write', '/home/f/o/routes/web.php', { content: FQCN }],
  ['closure', 'Write', '/home/f/o/routes/web.php', { content: `Route::get('/z', fn () => view('z'));` }],
  ['comentário // que ensina o padrão', 'Write', '/home/f/o/routes/web.php', { content: `<?php\n// NUNCA: 'FooController@index'\nRoute::get('/a', [A::class, 'i']);` }],
  ['comentário PHPDoc * que cita o padrão', 'Write', '/home/f/o/routes/web.php', { content: `<?php\n/**\n * legado: 'FooController@index'\n */\n` }],
  ['comentário # que cita o padrão', 'Write', '/home/f/o/routes/web.php', { content: `<?php\n# antes era 'FooController@index'\n` }],
  ['Controller FORA de routes/ (não é o vetor)', 'Write', '/home/f/o/app/Http/Controllers/FooController.php', { content: LEGACY }],
  ['Modules/<X>/Http/Controllers/*.php', 'Write', '/home/f/o/Modules/X/Http/Controllers/Y.php', { content: LEGACY }],
  ['self-exempt: o próprio hook', 'Write', 'D:/oimpresso.com/.claude/hooks/block-routes-string-legacy.mjs', { content: LEGACY }],
  ['self-exempt: o próprio teste', 'Write', 'D:/oimpresso.com/.claude/hooks/block-routes-string-legacy.test.mjs', { content: LEGACY }],
  ['rule routes.md cita o padrão em backtick', 'Write', 'D:/oimpresso.com/.claude/rules/routes.md', { content: LEGACY }],
  ['Read não é Write/Edit', 'Read', '/home/f/o/routes/web.php', { content: LEGACY }],
  ['path vazio (fail-open)', 'Write', '', { content: LEGACY }],
  ['routes/ mas não .php', 'Write', '/home/f/o/routes/readme.md', { content: LEGACY }],
];
for (const [nome, tool, path, ti] of ALLOW) check(`ALLOW: ${nome}`, verdict(tool, path, ti, NOENV) === 'allow');

// ── modos + override (contrato: strict é o DEFAULT; os outros são opt-in) ────────
const SUJO = ['Write', '/home/f/o/routes/web.php', { content: LEGACY }];
check('modo DEFAULT (env vazio) = strict → block', verdict(...SUJO, {}) === 'block');
check('OIMPRESSO_ROUTES_HOOK_MODE=warn → warn', verdict(...SUJO, { OIMPRESSO_ROUTES_HOOK_MODE: 'warn' }) === 'warn');
check('OIMPRESSO_ROUTES_HOOK_MODE=off → allow', verdict(...SUJO, { OIMPRESSO_ROUTES_HOOK_MODE: 'off' }) === 'allow');
check('OIMPRESSO_ROUTES_OVERRIDE=1 (Tier 0) → allow', verdict(...SUJO, { OIMPRESSO_ROUTES_OVERRIDE: '1' }) === 'allow');

// ── discriminadores unitários (redundância de defesa) ────────────────────────────
check('isRoutesFile: routes/ sim, Controllers/ não', isRoutesFile('/o/routes/web.php') && !isRoutesFile('/o/app/http/controllers/x.php'));
check('isRoutesFile: Modules/<X>/routes/ sim', isRoutesFile('/o/modules/sells/routes/web.php'));
check('isExempt: rule routes.md sim, routes/web.php não', isExempt('/o/.claude/rules/routes.md') && !isExempt('/o/routes/web.php'));
check('findLegacyMatches: pega código, solta comentário', findLegacyMatches(`Route::get('/x','AController@i');`).length === 1 && findLegacyMatches(`// 'AController@i'`).length === 0);
check('findLegacyMatches: FQCN não casa', findLegacyMatches(`[SellController::class, 'index']`).length === 0);
check('findLegacyMatches: conta múltiplos (o #843 tinha 10)', findLegacyMatches(`'AController@i';\n'BController@j';`).length === 2);
check('extractContent: MultiEdit junta todos', extractContent('MultiEdit', { edits: [{ new_string: 'a' }, { new_string: 'b' }] }) === 'a\nb');
check('blockMessage cita #843, FQCN e override', /#843/.test(blockMessage('Write', 'x')) && /::class/.test(blockMessage('Write', 'x')) && /OIMPRESSO_ROUTES_OVERRIDE/.test(blockMessage('Write', 'x')));

// ── E2E: stdin JSON → exit code (prova o wrapper + fail-open de parse) ───────────
function runHook(stdin, env) {
  return spawnSync(process.execPath, [HOOK], { input: stdin, encoding: 'utf8', env: { ...process.env, ...env } }).status;
}
const j = (tool, path, ti) => JSON.stringify({ hook_event_name: 'PreToolUse', tool_name: tool, tool_input: { file_path: path, ...ti } });
check('E2E: string legacy → exit 2 (BLOQUEIA)', runHook(j('Write', '/home/f/o/routes/web.php', { content: LEGACY })) === 2);
check('E2E: FQCN → exit 0 (SOLTA)', runHook(j('Write', '/home/f/o/routes/web.php', { content: FQCN })) === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', runHook('{lixo') === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('') === 0);
check('E2E: override respeitado no wrapper', runHook(j('Write', '/home/f/o/routes/web.php', { content: LEGACY }), { OIMPRESSO_ROUTES_OVERRIDE: '1' }) === 0);

console.log('');
if (fails === 0) {
  console.log('[PASS] block-routes-string-legacy.mjs — morde string legacy, solta FQCN, respeita modo/override.');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s).`);
process.exit(1);
