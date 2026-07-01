#!/usr/bin/env node
// diag-pretooluse-trace.mjs — INSTRUMENTO DE DIAGNÓSTICO (NÃO é um gate).
//
// PROPÓSITO: provar mecanicamente se o harness entrega o evento PreToolUse pra
// tool NATIVA `DesignSync` (e `Skill`/`design-login`). Fecha o furo #1 da ADR 0315
// (gate por hook PreToolUse matcher "DesignSync" = no-op em runtime — baseline
// 2026-06-30). Decisão adversarial: "diagnóstico-primeiro" (12 agentes, conf. alta).
//
// ⚠️ INERTE POR PADRÃO: este arquivo NÃO faz nada até ser REGISTRADO em
// `.claude/settings.json`. Só loga e sai 0 — nunca bloqueia. Cross-platform (.mjs).
//
// ── COMO RODAR (próxima sessão FRESCA — settings.json carrega no startup) ──
// 1. Registrar em settings.json, grupo PreToolUse, matcher wildcard:
//      { "matcher": "*", "hooks": [
//        { "type": "command", "command": "node .claude/hooks/diag-pretooluse-trace.mjs" } ] }
// 2. REBOOTAR a sessão (sem reboot, hook novo não carrega).
// 3. PROBE 1 (controle positivo): rodar `Read` ou `Bash` triviais → confirmar que
//    aparecem no trace (prova que o hook RODA — distingue "não-entrega" de "hook quebrado").
// 4. Disparar `DesignSync.list_projects` (leitura) e `DesignSync.finalize_plan`
//    (escrita, projectId inexistente 00000000-... pra zero efeito).
//    CRITÉRIO: se `DesignSync` JAMAIS aparece no trace enquanto Read/Bash aparecem
//    → NÃO-ENTREGA PROVADA. Se aparece com OUTRO nome → corrigir o matcher do gate.
// 5. PROBE 3: invocar a SKILL `/design-sync` → ver se `Skill` aparece no trace
//    (valida gate-skill-design-sync como ponto de intercepção real).
// 6. PROBE 4: `/design-login` → ver se emite PreToolUse (Gap residual 3).
// 7. DES-REGISTRAR este hook (remover do settings.json) ao terminar — wildcard `*`
//    loga TODO tool call e não deve ficar ligado em sessões normais.
// 8. Anexar o trace + veredito na ADR 0315 (§Decisão adversarial, regra de fechamento).
//
// Exit: SEMPRE 0 (nunca bloqueia — é sonda, não gate).

import { stdin } from 'node:process';
import { join } from 'node:path';
import { appendFileSync, mkdirSync } from 'node:fs';

const LOG = join(process.cwd(), '.claude', 'run', 'pretooluse-trace.log');

async function readStdin() {
  const chunks = [];
  for await (const c of stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  let raw = '';
  try {
    raw = await readStdin();
  } catch {
    process.exit(0);
  }

  let p = {};
  try {
    p = raw ? JSON.parse(raw) : {};
  } catch {
    /* payload não-JSON: loga cru abaixo */
  }

  // ISO sem Date.now()/new Date() proibidos? aqui é runtime de hook real (não workflow),
  // então new Date() é permitido — é o instante real do tool call, parte do dado.
  const line =
    [
      new Date().toISOString(),
      p.hook_event_name || '?event',
      p.tool_name || '?tool',
      typeof p?.tool_input?.method === 'string' ? `method=${p.tool_input.method}` : '',
    ]
      .filter(Boolean)
      .join('\t') + '\n';

  try {
    mkdirSync(join(process.cwd(), '.claude', 'run'), { recursive: true });
    appendFileSync(LOG, line, 'utf8');
  } catch {
    /* silent — diagnóstico nunca quebra o fluxo */
  }

  process.exit(0);
}

main();
