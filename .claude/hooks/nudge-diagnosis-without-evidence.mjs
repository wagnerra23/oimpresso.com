#!/usr/bin/env node
// nudge-diagnosis-without-evidence.mjs — Stop (PORTE cross-plataforma do .ps1, advisory · estende R1).
// Detecta CAUSA/diagnóstico afirmado com certeza SEM evidência (grep/log/SQL/trace/curl/Read).
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// R1 (smoke real / claim sem evidência) + proibicoes.md §"Claim sem evidência".
// Origem: sessão 2026-05-29 chutou a causa do HTTP 500 2× antes de ler o log.
// Se ainda não há evidência, a fala honesta é "hipótese a confirmar".
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o nudge
// evapora em silêncio. Supersede nudge-diagnosis-without-evidence.ps1.
//
// ADVISORY: exit 0 SEMPRE (nunca bloqueia). Fail-open em qualquer erro.
// Selftest: node .claude/hooks/nudge-diagnosis-without-evidence.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';
import { readFileSync, existsSync } from 'node:fs';

export const NUDGE = "[R1+ / ADR 0233] Voce AFIRMOU uma causa/diagnostico. Mostre a EVIDENCIA (grep/log/SQL/trace/Read) que prova, antes de cravar. Nao chute (sessao 2026-05-29 chutou causa do 500 2x). Se ainda nao tem evidencia, diga 'hipotese a confirmar'.";

/** classificador PURO: afirmou causa/diagnóstico com certeza E sem marcador de evidência? */
export function shouldNudge(text) {
  if (!text) return false;
  const diag = /(a causa (e|é|raiz|foi)|o problema (e|é|foi)|isso (acontece|ocorre|quebra|d[aá]) porque|com certeza (e|é)|root cause|o motivo (e|é)|porque o banco|porque a tabela)/i.test(text);
  const evidence = /(grep|tail |laravel\.log|SQLSTATE|stack ?trace|\.php:\d|getComputedStyle|curl|HTTP \d{3}|migrate:status|Schema::has|linha \d|confirmad[oa]|verifiquei)/i.test(text);
  return diag && !evidence;
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
    const test = new URL('./nudge-diagnosis-without-evidence.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
