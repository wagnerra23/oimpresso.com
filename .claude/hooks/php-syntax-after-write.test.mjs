#!/usr/bin/env node
// Selftest de php-syntax-after-write.mjs — BITE-TEST.
// Um gate que nunca fica vermelho é teatro (§5: drift-sentinel tautológico,
// foundation-ratchet "0 failures em 300+ runs"). Então: fixture RUIM tem que
// morder (exit 2) e fixture BOA tem que passar em SILÊNCIO (exit 0, stderr vazio).
// A fixture ruim é o incidente REAL de 2026-07-28, não um caso inventado.

import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'php-syntax-after-write.mjs');
const dir = mkdtempSync(join(tmpdir(), 'php-syntax-test-'));
let falhas = 0;

function roda(payload, env = {}) {
  const r = spawnSync(process.execPath, [HOOK], {
    input: JSON.stringify(payload), encoding: 'utf8', timeout: 30000,
    env: { ...process.env, ...env },
  });
  return { code: r.status, err: String(r.stderr || '') };
}
function ok(nome, cond, detalhe = '') {
  if (cond) { console.log(`  ok   ${nome}`); return; }
  falhas++; console.log(`  FALHA ${nome} ${detalhe}`);
}

// O PHP existe nesta máquina? Sem ele, os casos de mordida não podem ser provados
// — e dizer "passou" sem o oráculo seria verde por não-execução (§5 2026-07-24).
const temPhp = (async () => {
  const m = await import('./php-syntax-after-write.mjs');
  return m.resolvePhp() !== null;
})();

const arquivo = (nome, conteudo) => { const p = join(dir, nome); writeFileSync(p, conteudo); return p; };

// ── fixture RUIM: o incidente real — glob com `*/` dentro do docblock ────────
const RUIM = arquivo('Ruim.php', `<?php
/**
 * Varre \`Modules/*/Tests/*\` procurando a lane.
 * Esta linha JÁ ESTÁ FORA do comentário — o \`*/\` do glob fechou o bloco acima.
 */
class Ruim { public function y(): void {} }
`);
// ── fixture BOA: mesmo texto, glob sem o par que fecha ───────────────────────
const BOA = arquivo('Boa.php', `<?php
/**
 * Varre os testes de cada módulo procurando a lane (glob citado sem o par).
 */
class Boa { public function y(): void {} }
`);

const main = async () => {
  const php = await temPhp;
  console.log(`php-syntax-after-write — bite-test${php ? '' : ' (SEM PHP local)'}`);

  // No CI o oráculo TEM que existir. Sem isso, pular a mordida e imprimir "verde"
  // seria verde por não-execução — a armadilha do §5 (2026-07-24): o teste mediria
  // a ausência da operação e chamaria de aprovação.
  if (!php && process.env.CI) {
    console.log('  FALHA: CI sem PHP — a mordida não pôde ser provada (setup-php ausente no job).');
    process.exit(1);
  }

  if (php) {
    const r1 = roda({ tool_name: 'Write', tool_input: { file_path: RUIM } });
    ok('MORDE: docblock fechado por `*/` de glob → exit 2', r1.code === 2, `(exit=${r1.code})`);
    ok('MORDE: a mensagem cita a linha do parser', /on line \d+/.test(r1.err), `(err=${r1.err.slice(0, 80)})`);
    ok('MORDE: a mensagem ensina o sintoma "erro não anda de linha"', /NÃO ANDA DE LINHA/.test(r1.err));

    const r2 = roda({ tool_name: 'Write', tool_input: { file_path: BOA } });
    ok('SILÊNCIO: arquivo que compila → exit 0 sem ruído', r2.code === 0 && r2.err === '', `(exit=${r2.code} err=${r2.err.slice(0, 60)})`);

    const r3 = roda({ tool_name: 'Edit', tool_input: { file_path: RUIM } });
    ok('MORDE em Edit também (não só Write)', r3.code === 2, `(exit=${r3.code})`);
  }

  // ── controles negativos: tudo que NÃO é erro de sintaxe provado = silêncio ──
  const cn = [
    ['não-php (.ts) é ignorado', { tool_name: 'Write', tool_input: { file_path: join(dir, 'x.ts') } }, {}],
    ['vendor/ é ignorado', { tool_name: 'Write', tool_input: { file_path: join(dir, 'vendor', 'a.php') } }, {}],
    ['tool não-escritora (Read) é ignorada', { tool_name: 'Read', tool_input: { file_path: RUIM } }, {}],
    ['arquivo inexistente → silêncio', { tool_name: 'Write', tool_input: { file_path: join(dir, 'nao-existe.php') } }, {}],
    ['payload sem tool_input → silêncio', { tool_name: 'Write' }, {}],
    ['modo off → silêncio mesmo no ruim', { tool_name: 'Write', tool_input: { file_path: RUIM } }, { OIMPRESSO_PHP_LINT_MODE: 'off' }],
    ['override Tier 0 → silêncio mesmo no ruim', { tool_name: 'Write', tool_input: { file_path: RUIM } }, { OIMPRESSO_PHP_LINT_OVERRIDE: '1' }],
  ];
  for (const [nome, payload, env] of cn) {
    const r = roda(payload, env);
    ok(`SILÊNCIO: ${nome}`, r.code === 0 && r.err === '', `(exit=${r.code} err=${r.err.slice(0, 70)})`);
  }

  // stdin inválido → fail-open
  const r4 = spawnSync(process.execPath, [HOOK], { input: 'nao é json', encoding: 'utf8', timeout: 15000 });
  ok('SILÊNCIO: stdin inválido → fail-open', r4.status === 0 && String(r4.stderr || '') === '');

  // ── "máquina sem PHP" pela UNIDADE ────────────────────────────────────────
  // Zerar o PATH num subprocesso não simula isso: resolvePhp tem fallback pro Herd
  // em homedir(), que existe nesta máquina. Provar pelo subprocesso daria um verde
  // por não-execução (§5 2026-07-24) — então o ramo é exercido direto.
  const m = await import('./php-syntax-after-write.mjs');
  ok('resolvePhp: sem PATH e com home vazio → null (não inventa binário)',
    m.resolvePhp({ PATH: '', Path: '' }, join(dir, 'home-que-nao-existe')) === null);
  ok('lint(null, …) → ok:true (sem oráculo, silêncio — nunca acusa)',
    m.lint(null, RUIM).ok === true);
  ok('lint: saída inesperada do parser não vira acusação',
    m.lint(join(dir, 'binario-inexistente'), RUIM).ok === true);

  console.log(falhas === 0 ? '\n✓ bite-test verde' : `\n✗ ${falhas} falha(s)`);
  process.exit(falhas === 0 ? 0 : 1);
};

main();
