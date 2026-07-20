#!/usr/bin/env node
// Selftest do handoff-inline.mjs (US-GOV-052 — padrão gate-selftest: caso bom passa,
// caso limite se comporta; crash != comportar). Lógica pura (import) + E2E (subprocess).
//
// Contrato-âncora: memory/08-handoff.md índice append-only (ADR 0130) + lembrete tools MCP (ADR 0070).
// Rodar: node .claude/hooks/handoff-inline.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { tailLines, buildOutput } from './handoff-inline.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const HOOK = join(__dirname, 'handoff-inline.mjs');

let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── lógica pura: tailLines ──
const cem = Array.from({ length: 100 }, (_, i) => `linha ${i + 1}`).join('\n');
check('tailLines pega as últimas 40', tailLines(cem) === Array.from({ length: 40 }, (_, i) => `linha ${i + 61}`).join('\n'));
check('tailLines tolera CRLF', tailLines('a\r\nb\r\nc', 2) === 'b\nc');
check('tailLines descarta trailing newline (não conta linha vazia)', tailLines('a\nb\nc\n', 2) === 'b\nc');
check('tailLines strip BOM inicial', tailLines('﻿primeira\nsegunda', 2) === 'primeira\nsegunda');
check('tailLines com menos linhas que n devolve tudo', tailLines('so\numa', 40) === 'so\numa');

// ── lógica pura: buildOutput ──
const comHandoff = buildOutput('h1\nh2\nh3');
check('buildOutput com texto imprime header do handoff', comHandoff.includes('=== memory/08-handoff.md (últimas 40 linhas) ==='));
check('buildOutput com texto imprime o conteúdo', comHandoff.includes('h1') && comHandoff.includes('h3'));
check('buildOutput SEMPRE imprime lembrete tasks/cycles', comHandoff.includes('=== Estado vivo de tasks/cycles ===') && comHandoff.includes('cycles-active'));
const semHandoff = buildOutput(null);
check('buildOutput null NÃO imprime header do handoff', !semHandoff.includes('últimas 40 linhas'));
check('buildOutput null AINDA imprime lembrete tasks/cycles', semHandoff.includes('cycles-active') && semHandoff.includes('ADR 0070'));

// ── E2E: subprocess com cwd temporário ──
function runIn(cwd) {
  return spawnSync(process.execPath, [HOOK], { cwd, encoding: 'utf8' });
}
// (a) cwd COM memory/08-handoff.md
const tmpA = mkdtempSync(join(tmpdir(), 'handoff-inline-A-'));
mkdirSync(join(tmpA, 'memory'), { recursive: true });
writeFileSync(join(tmpA, 'memory', '08-handoff.md'), 'topo\n- item recente do handoff\nrodapé\n', 'utf8');
const rA = runIn(tmpA);
check('E2E com handoff: exit 0', rA.status === 0);
check('E2E com handoff: stdout tem o conteúdo do arquivo', rA.stdout.includes('item recente do handoff'));
check('E2E com handoff: stdout tem lembrete tasks/cycles', rA.stdout.includes('cycles-active'));
rmSync(tmpA, { recursive: true, force: true });

// (b) cwd SEM o arquivo (fail-open gracioso)
const tmpB = mkdtempSync(join(tmpdir(), 'handoff-inline-B-'));
const rB = runIn(tmpB);
check('E2E sem handoff: exit 0 (fail-open)', rB.status === 0);
check('E2E sem handoff: NÃO quebra, sem header do handoff', !rB.stdout.includes('últimas 40 linhas'));
check('E2E sem handoff: ainda imprime lembrete tasks/cycles', rB.stdout.includes('cycles-active'));
rmSync(tmpB, { recursive: true, force: true });

console.log('');
if (fails === 0) {
  console.log('[PASS] handoff-inline: tail correto + fail-open + lembrete sempre presente.');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) — o porte do handoff-inline regrediu.`);
process.exit(1);
