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
import { topLines, buildOutput } from './handoff-inline.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const HOOK = join(__dirname, 'handoff-inline.mjs');

let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── lógica pura: topLines ──
const cem = Array.from({ length: 100 }, (_, i) => `linha ${i + 1}`).join('\n');
check('topLines pega as PRIMEIRAS 40', topLines(cem) === Array.from({ length: 40 }, (_, i) => `linha ${i + 1}`).join('\n'));
check('topLines tolera CRLF', topLines('a\r\nb\r\nc', 2) === 'a\nb');
check('topLines descarta trailing newline (não conta linha vazia)', topLines('a\nb\nc\n', 2) === 'a\nb');
check('topLines strip BOM inicial', topLines('﻿primeira\nsegunda', 2) === 'primeira\nsegunda');
check('topLines com menos linhas que n devolve tudo', topLines('so\numa', 40) === 'so\numa');

// ── REGRESSÃO (2026-08-21): a CAPACIDADE, não o mecanismo ──────────────────────
// O teste antigo afirmava 'pega as últimas 40' e ficou VERDE por ~3 meses enquanto o
// hook servia handoffs de 2026-05-10/11. Um índice real é mais-recente-no-topo: o que
// importa é que o MAIS NOVO apareça e o MAIS VELHO não empurre ele pra fora.
const indice = ['# 08 — Handoff (índice)', '', '## Últimos handoffs', '']
  .concat(Array.from({ length: 200 }, (_, i) => `- [2026-08-${String(20 - (i % 20)).padStart(2, '0')} — entrada ${i + 1}](handoffs/h${i + 1}.md)`))
  .concat(['', '## Como fechar uma sessão', 'RODAPÉ QUE NÃO É HANDOFF'])
  .join('\n');
const saida = buildOutput(indice);
check('REGRESSÃO: mostra a entrada MAIS RECENTE (topo)', saida.includes('entrada 1](handoffs/h1.md)'));
check('REGRESSÃO: NÃO mostra a mais antiga (fim da lista)', !saida.includes('entrada 200](handoffs/h200.md)'));
check('REGRESSÃO: NÃO vaza o rodapé de instrução no lugar dos handoffs', !saida.includes('RODAPÉ QUE NÃO É HANDOFF'));

// ── lógica pura: buildOutput ──
const comHandoff = buildOutput('h1\nh2\nh3');
check('buildOutput com texto imprime header do handoff', comHandoff.includes('=== memory/08-handoff.md (topo — 40 linhas mais recentes) ==='));
check('buildOutput com texto imprime o conteúdo', comHandoff.includes('h1') && comHandoff.includes('h3'));
check('buildOutput SEMPRE imprime lembrete tasks/cycles', comHandoff.includes('=== Estado vivo de tasks/cycles ===') && comHandoff.includes('cycles-active'));
const semHandoff = buildOutput(null);
check('buildOutput null NÃO imprime header do handoff', !semHandoff.includes('linhas mais recentes'));
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
check('E2E sem handoff: NÃO quebra, sem header do handoff', !rB.stdout.includes('linhas mais recentes'));
check('E2E sem handoff: ainda imprime lembrete tasks/cycles', rB.stdout.includes('cycles-active'));
rmSync(tmpB, { recursive: true, force: true });

console.log('');
if (fails === 0) {
  console.log('[PASS] handoff-inline: TOPO correto + fail-open + lembrete sempre presente.');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) — o porte do handoff-inline regrediu.`);
process.exit(1);
