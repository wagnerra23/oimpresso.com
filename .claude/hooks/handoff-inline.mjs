#!/usr/bin/env node
// handoff-inline.mjs — SessionStart (PORTE cross-plataforma do comando PowerShell INLINE do settings.json).
// Imprime o TOPO (40 linhas) de memory/08-handoff.md (se existir) + o lembrete de estado
// vivo de tasks/cycles (CURRENT.md/TASKS.md removidos — ADR 0070).
//
// ── POR QUE .mjs (US-GOV-052) ─ o comando era `powershell -Command "... Get-Content -Tail 40 ..."`
// embutido no settings.json; `powershell` só existe no Windows do [W]. No Mac/Linux (time MCP
// Felipe/Maiara/Luiz) o comando evapora em silêncio → time abre sessão sem o índice de handoff.
// Node roda em todo OS. Supersede o comando inline (settings.json SessionStart, 2º hook).
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// memory/08-handoff.md é o índice append-only de handoffs (ADR 0130). O lembrete de tasks/cycles
// aponta pras tools MCP (ADR 0070). Fail-open total (exit 0 em qualquer erro) — hook decorativo
// de injeção de contexto, NUNCA bloqueia o SessionStart.
//
// ── POR QUE O TOPO, e não o fim (medido 2026-08-21) ─────────────────────────
// O porte herdou `Get-Content -Tail 40` do comando PowerShell original. O índice é
// mais-recente-NO-TOPO (ADR 0130), então ler o FIM devolve os handoffs mais ANTIGOS.
// Passou despercebido porque funcionava enquanto a lista cabia em 40 linhas — a regra
// 'truncar 5º item'. Quando a truncagem foi abandonada (custo aceito e declarado no
// .gitattributes do merge=union), o hook inverteu de sentido EM SILÊNCIO: em 2026-08-21,
// com 391 entradas, ele servia handoffs de 2026-05-10/11 (~3 meses de atraso).
// O teste antigo não pegava porque afirmava o MECANISMO ('pega as últimas 40'), não a
// CAPACIDADE ('mostra o handoff mais recente'). Há caso de regressão pra isso agora.
//
// Selftest: node .claude/hooks/handoff-inline.mjs --selftest

import { spawnSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { existsSync, readFileSync } from 'node:fs';

const HANDOFF = 'memory/08-handoff.md';
const TOPO = 40;

/** primeiras n linhas de um texto, tolerante a CRLF e BOM inicial.
  * O índice é mais-recente-no-topo, então 'primeiras' = 'mais recentes'. */
export function topLines(text, n = TOPO) {
  const semBom = String(text).replace(/^﻿/, '');
  const linhas = semBom.split(/\r?\n/);
  // um trailing newline vira uma última linha vazia — descarta pra não contar como linha.
  if (linhas.length && linhas[linhas.length - 1] === '') linhas.pop();
  return linhas.slice(0, n).join('\n');
}

/** monta o texto de saída — recebe o conteúdo do handoff (ou null se ausente). */
export function buildOutput(handoffText) {
  const partes = [];
  if (handoffText != null) {
    partes.push(`=== ${HANDOFF} (topo — ${TOPO} linhas mais recentes) ===`);
    partes.push(topLines(handoffText));
    partes.push('');
  }
  partes.push('=== Estado vivo de tasks/cycles ===');
  partes.push('Use tools MCP: cycles-active + my-work + my-inbox (CURRENT.md/TASKS.md removidos em 2026-05-04, ADR 0070)');
  return partes.join('\n');
}

function main() {
  try {
    let handoffText = null;
    if (existsSync(HANDOFF)) {
      try { handoffText = readFileSync(HANDOFF, 'utf8'); } catch { handoffText = null; }
    }
    process.stdout.write(buildOutput(handoffText) + '\n');
  } catch { /* fail-open */ }
  process.exit(0);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./handoff-inline.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [fileURLToPath(test)], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
