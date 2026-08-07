#!/usr/bin/env node
// block-ancora-no-olho.test.mjs — BITE-TEST do hook (PreToolUse Read).
//
// POR QUE EXISTE: medido em 2026-08-05, este era o ÚNICO dos 22 hooks com zero entrega
// em 14d que NÃO tinha prova nenhuma de que morde — sem `.test.mjs` e sem `--selftest`.
// Os outros 21 têm bite-test verde, então o zero-entrega deles significa "a condição não
// ocorreu" (legítimo). Aqui significava "não sei", e ele é BLOQUEADOR (exit 2).
//
// Pior: uma medição minha anterior reportou `--selftest OK` pra ele. Era FALSO — o hook
// não implementa esse modo, então ignorou o argumento, leu stdin vazio e saiu 0. "Exit 0"
// de um hook que só espera stdin não prova nada. É a classe LC-08 (medir a coisa errada),
// e é exatamente o que este arquivo passa a impedir.
//
// E2E de propósito (§5 LC-15: "assert sobre helper puro exportado não prova contrato de
// pipeline"): além de `decidir()`, cada caso roda o hook DE FORA por spawn, com payload
// real no stdin, lendo o exit code — que é o contrato que o Claude Code de fato consome.
//
// Uso: node .claude/hooks/block-ancora-no-olho.test.mjs

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-ancora-no-olho.mjs');
let falhas = 0;
const ok = (cond, nome, extra = '') => {
  console.log((cond ? '[OK]   ' : '[FALHOU] ') + nome + (cond ? '' : ` — ${extra}`));
  if (!cond) falhas++;
};

/** roda o hook de fora, como o Claude Code roda: payload JSON no stdin → exit code. */
function rodar(toolName, filePath, env = {}) {
  const r = spawnSync(process.execPath, [HOOK], {
    input: JSON.stringify({ tool_name: toolName, tool_input: { file_path: filePath } }),
    encoding: 'utf8',
    env: { ...process.env, ...env },
    timeout: 30000,
  });
  return { status: r.status, err: r.stderr || '' };
}

// ── MORDE: print semântico (estado velho sendo criticado) não declarado por charter ──
for (const nome of [
  'audit-financeiro.png',
  'critique-vendas.png',
  'tribunal-do-design.png',
  'Financeiro-old.png',
  'reavaliacao-cockpit.jpg',
  'C:/fora/do/repo/scrap-tela.png',   // "em QUALQUER lugar" — o fix v3 vale fora do repo
]) {
  const r = rodar('Read', nome);
  ok(r.status === 2, `MORDE: ${nome}`, `exit=${r.status}`);
}
{
  const r = rodar('Read', 'audit-financeiro.png');
  ok(/ÂNCORA|ancora\.mjs/i.test(r.err), 'a razão explica o que fazer (aponta ancora.mjs)', r.err.slice(0, 70));
}

// ── LIBERA: os controles negativos. Sem eles, um hook que bloqueia TUDO passaria ──
for (const [tool, fp, porque] of [
  ['Read', 'prototipo-ui/prototipos/financeiro/F1.html', 'design legítimo (.html) — a dor do [W]: zero backfire'],
  ['Read', 'Financeiro.png', 'imagem SEM termo de auditoria passa'],
  ['Read', 'ph-financeiro.png', 'prefixo de protótipo passa'],
  ['Read', 'memory/decisions/0369-audit-algo.md', 'NÃO é imagem — "audit" no nome de .md não conta'],
  ['Edit', 'audit-financeiro.png', 'tool diferente de Read não é escopo deste hook'],
]) {
  const r = rodar(tool, fp);
  ok(r.status === 0, `LIBERA: ${porque}`, `exit=${r.status} (${fp})`);
}

// ── ESCAPE declarado no cabeçalho: promessa tem que ser testada (§5 LC-15) ──
{
  const r = rodar('Read', 'audit-financeiro.png', { OIMPRESSO_ANCORA_OK: '1' });
  ok(r.status === 0, 'ESCAPE OIMPRESSO_ANCORA_OK=1 libera (a promessa do cabeçalho existe)', `exit=${r.status}`);
}

// ── FAIL-OPEN: entrada inválida não pode travar o agente ──
{
  const r = spawnSync(process.execPath, [HOOK], { input: 'não é json', encoding: 'utf8', timeout: 30000 });
  ok(r.status === 0, 'fail-open: stdin inválido → exit 0 (nunca trava por erro do próprio hook)', `exit=${r.status}`);
}

// ── O núcleo puro concorda com o E2E (mas NÃO substitui: ver cabeçalho) ──
{
  const { decidir } = await import('./block-ancora-no-olho.mjs');
  ok(decidir('Read', { file_path: 'audit-x.png' })?.nivel === 'block', 'decidir() bloqueia print semântico');
  ok(decidir('Read', { file_path: 'Financeiro.png' }) === null, 'decidir() libera imagem de design');
  ok(decidir('Write', { file_path: 'audit-x.png' }) === null, 'decidir() ignora tool != Read');
}

console.log(falhas ? `\n✗ ${falhas} falha(s)` : '\n✅ block-ancora-no-olho: MORDE print de auditoria, LIBERA design legítimo, escape funciona, fail-open preservado.');
process.exit(falhas ? 1 : 0);
