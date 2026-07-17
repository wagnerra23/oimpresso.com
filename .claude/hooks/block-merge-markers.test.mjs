#!/usr/bin/env node
// Teste do PORTE cross-plataforma block-merge-markers.mjs (ex-.ps1). Cada caso deriva do
// CONTRATO canônico (memory/reference/post-mortem-v4-go-live.md §anti-pattern A, incidentes
// #1000/#1001), NÃO do output do .ps1 legado — teste derivado da implementação é tautológico
// e trava o desvio em vez de pegá-lo (proibicoes.md §5, 2026-06-05).
// Roda em Linux/CI (o .test.ps1 não rodava).
//
// NOTA: os markers são montados em RUNTIME ('<'.repeat(7)) pra este arquivo não conter
// conflito literal em coluna 0 — senão o próprio hook registrado bloquearia escrevê-lo
// (a auto-isenção do .ps1 legado cobria só .ps1/.test.ps1). Mesmo idioma do FAKE_CPF do
// prompt-injection-corpus.
//
// Rodar: node .claude/hooks/block-merge-markers.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { verdict, isExempt, hasMergeMarker, extractContent, blockMessage } from './block-merge-markers.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-merge-markers.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// Markers montados em runtime (ver NOTA acima).
const OURS = '<'.repeat(7) + ' HEAD';
const SEP = '='.repeat(7);
const THEIRS = '>'.repeat(7) + ' branch-x';
const BASE = '|'.repeat(7) + ' base';           // diff3
const CONFLITO = `<?php\n${OURS}\n$a=1;\n${SEP}\n$a=2;\n${THEIRS}\n`;
const LIMPO = '<?php\n$a = 1;\n';

const NOENV = {}; // env vazio → modo default (strict), sem override

// ── BLOCK: conflito não-resolvido (contrato: "conflito em código = SEMPRE bug") ──
const BLOCK = [
  ['Write .php com conflito (o incidente #1000)', 'Write', 'D:/oimpresso.com/app/Foo.php', { content: CONFLITO }],
  ['Edit new_string com conflito', 'Edit', '/home/felipe/oimpresso/app/Bar.php', { new_string: CONFLITO }],
  ['MultiEdit: 1 edit sujo entre limpos', 'MultiEdit', '/Users/maiara/o/app/Baz.php', { edits: [{ new_string: LIMPO }, { new_string: CONFLITO }] }],
  ['diff3 base marker (|||||||)', 'Write', '/home/luiz/o/app/D.php', { content: `<?php\n${OURS}\nx\n${BASE}\ny\n${SEP}\nz\n${THEIRS}\n` }],
  ['.tsx também (não é só PHP)', 'Write', '/home/f/o/resources/js/Pages/X.tsx', { content: `export {}\n${OURS}\na\n${SEP}\nb\n${THEIRS}\n` }],
  ['separador sozinho em coluna 0', 'Write', '/home/f/o/app/E.php', { content: `<?php\n${SEP}\n` }],
];
for (const [nome, tool, path, ti] of BLOCK) check(`BLOCK: ${nome}`, verdict(tool, path, ti, NOENV) === 'block');

// ── ALLOW: o contrato afirma "zero falso-positivo legítimo" — estes provam ───────
const ALLOW = [
  ['conteúdo limpo', 'Write', '/home/f/o/app/Ok.php', { content: LIMPO }],
  ['doc cita marker em BACKTICK (não coluna 0)', 'Write', '/home/f/o/memory/x.md', { content: 'use \\`' + OURS + '\\` pra marcar\n' }],
  ['marker indentado (não é conflito do git)', 'Write', '/home/f/o/app/F.php', { content: `<?php\n   ${OURS}\n` }],
  ['======= dentro de linha (divisória de doc)', 'Write', '/home/f/o/memory/y.md', { content: `titulo ${SEP} fim\n` }],
  ['Read não é Write/Edit', 'Read', '/home/f/o/app/G.php', { content: CONFLITO }],
  ['binário (.png) isento', 'Write', '/home/f/o/public/i.png', { content: CONFLITO }],
  ['fixture que documenta marker', 'Write', '/home/f/o/tests/fixtures/conflito.php', { content: CONFLITO }],
  ['self-exempt: o próprio hook', 'Write', 'D:/oimpresso.com/.claude/hooks/block-merge-markers.mjs', { content: CONFLITO }],
  ['self-exempt: o próprio teste', 'Write', 'D:/oimpresso.com/.claude/hooks/block-merge-markers.test.mjs', { content: CONFLITO }],
  ['post-mortem documenta o anti-pattern', 'Write', '/home/f/o/memory/reference/post-mortem-v4-go-live.md', { content: CONFLITO }],
  ['path vazio (fail-open)', 'Write', '', { content: CONFLITO }],
];
for (const [nome, tool, path, ti] of ALLOW) check(`ALLOW: ${nome}`, verdict(tool, path, ti, NOENV) === 'allow');

// ── modos + override (contrato: strict é o DEFAULT; os outros são opt-in) ────────
const SUJO = ['Write', '/home/f/o/app/H.php', { content: CONFLITO }];
check('modo DEFAULT (env vazio) = strict → block', verdict(...SUJO, {}) === 'block');
check('OIMPRESSO_MERGE_HOOK_MODE=warn → warn (não bloqueia)', verdict(...SUJO, { OIMPRESSO_MERGE_HOOK_MODE: 'warn' }) === 'warn');
check('OIMPRESSO_MERGE_HOOK_MODE=off → allow', verdict(...SUJO, { OIMPRESSO_MERGE_HOOK_MODE: 'off' }) === 'allow');
check('OIMPRESSO_MERGE_OVERRIDE=1 (Tier 0) → allow', verdict(...SUJO, { OIMPRESSO_MERGE_OVERRIDE: '1' }) === 'allow');
check('modo é case-insensitive (STRICT)', verdict(...SUJO, { OIMPRESSO_MERGE_HOOK_MODE: 'STRICT' }) === 'block');

// ── discriminadores unitários (redundância de defesa) ────────────────────────────
check('hasMergeMarker: pega coluna 0, solta backtick', hasMergeMarker(CONFLITO) && !hasMergeMarker('`' + OURS + '`'));
check('hasMergeMarker: ======= exato (6 e 8 não contam)', hasMergeMarker(`x\n${SEP}\n`) && !hasMergeMarker('x\n======\n') && !hasMergeMarker('x\n========\n'));
check('isExempt: hook sim, código do repo não', isExempt('d:/oimpresso.com/.claude/hooks/block-merge-markers.mjs') && !isExempt('d:/oimpresso.com/app/foo.php'));
check('extractContent: MultiEdit junta todos os new_string', extractContent('MultiEdit', { edits: [{ new_string: 'a' }, { new_string: 'b' }] }) === 'a\nb');
check('blockMessage cita o incidente e o override', /#1000/.test(blockMessage('Write', 'x')) && /OIMPRESSO_MERGE_OVERRIDE/.test(blockMessage('Write', 'x')));

// ── E2E: stdin JSON → exit code (prova o wrapper + fail-open de parse) ───────────
function runHook(stdin, env) {
  return spawnSync(process.execPath, [HOOK], { input: stdin, encoding: 'utf8', env: { ...process.env, ...env } }).status;
}
const j = (tool, path, ti) => JSON.stringify({ hook_event_name: 'PreToolUse', tool_name: tool, tool_input: { file_path: path, ...ti } });
check('E2E: conflito → exit 2 (BLOQUEIA)', runHook(j('Write', '/home/f/o/app/I.php', { content: CONFLITO })) === 2);
check('E2E: limpo → exit 0 (SOLTA)', runHook(j('Write', '/home/f/o/app/I.php', { content: LIMPO })) === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', runHook('{lixo') === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('') === 0);
check('E2E: override respeitado no wrapper', runHook(j('Write', '/home/f/o/app/I.php', { content: CONFLITO }), { OIMPRESSO_MERGE_OVERRIDE: '1' }) === 0);

console.log('');
if (fails === 0) {
  console.log('[PASS] block-merge-markers.mjs — morde conflito, solta limpo, respeita modo/override.');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s).`);
process.exit(1);
