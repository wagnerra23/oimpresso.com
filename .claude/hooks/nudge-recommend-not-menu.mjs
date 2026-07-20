#!/usr/bin/env node
// nudge-recommend-not-menu.mjs — Stop (PORTE cross-plataforma do .ps1, advisory · R13/ADR 0233).
// Detecta resposta terminando em MENU de decisão técnica SEM recomendação cravada.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// memory/reference/feedback-recomendar-nao-menu.md (R13): em cálculo técnico
// (ROI/prioridade/sequência/arquitetura) o agente CRAVA a recomendação e pede só
// validação — Wagner valida, não calcula. Menu só vale pra gosto/preferência.
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o nudge
// evapora em silêncio. Supersede nudge-recommend-not-menu.ps1.
//
// ADVISORY: exit 0 SEMPRE (nunca bloqueia/loopa). Fail-open em qualquer erro.
// Selftest: node .claude/hooks/nudge-recommend-not-menu.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';
import { readFileSync, existsSync } from 'node:fs';

export const NUDGE = "[R13] Sua resposta parece terminar com MENU de decisao. Se for calculo tecnico (ROI/prioridade/sequencia/arquitetura), CRAVE uma recomendacao com razao e peca so validacao (Wagner valida, nao calcula). Menu so vale pra gosto/preferencia. Ref: memory/reference/feedback-recomendar-nao-menu.md";

/** classificador PURO: a resposta termina em menu-de-escolha sem recomendação cravada? */
export function shouldNudge(text) {
  if (!text) return false;
  const hasRecommend = /recomend|minha recomenda|sugiro cravad/i.test(text);
  const hasMenuList = /^\s*(\d[.)]|[-*]|\|)\s/m.test(text);
  const hasChoiceQ = /(qual (voc[eê]|prefere|escolh|deles|op[cç][aã]o)|o que (voc[eê] )?prefere|voc[eê] (decide|escolhe|quem decide)|prefere\?|qual (e|é) (a )?melhor)/i.test(text);
  return hasMenuList && hasChoiceQ && !hasRecommend;
}

/** último texto de mensagem assistant no transcript JSONL (últimas 50 linhas). */
export function lastAssistantText(transcriptPath) {
  if (!transcriptPath || !existsSync(transcriptPath)) return '';
  let lines;
  try { lines = readFileSync(transcriptPath, 'utf8').split('\n').filter(Boolean); } catch { return ''; }
  const tail = lines.slice(-50);
  for (let i = tail.length - 1; i >= 0; i--) {
    let o;
    try { o = JSON.parse(tail[i]); } catch { continue; }
    if (o && o.type === 'assistant' && o.message && Array.isArray(o.message.content)) {
      const t = o.message.content.filter((c) => c && c.type === 'text').map((c) => c.text).join('\n');
      if (t) return t;
    }
  }
  return '';
}

// ── stdin wrapper (fail-open em TUDO; SEMPRE exit 0 — advisory) ──────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  try {
    let raw;
    try { raw = await readStdin(); } catch { process.exit(0); }
    if (!raw) process.exit(0);
    let tp = '';
    try { tp = String((JSON.parse(raw) || {}).transcript_path || ''); } catch { process.exit(0); }
    const text = lastAssistantText(tp);
    if (shouldNudge(text)) process.stdout.write(NUDGE + '\n');
    process.exit(0);
  } catch { process.exit(0); }
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./nudge-recommend-not-menu.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
