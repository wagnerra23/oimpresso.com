#!/usr/bin/env node
// preflight-new-capability.mjs — PreToolUse:Write (PORTE cross-plataforma do .ps1, advisory).
// AVISA ao criar arquivo de CAPABILITY nova (Checker/Reconciler/Tool/Command/Service) sem
// checar o framework/registry que JÁ existe — anti-reinvenção.
//
// ── CONTRATO ─ Lição 2026-05-29: DriftChecker bespoke quando o framework ADR 0216 já existia
// (sem colisão de símbolo, reuse-gate não pega). Adversário 2026-07-20 REFUTOU a aposentadoria:
// reuse-check é dedup de SÍMBOLO, este é anti-reinvenção de FRAMEWORK. PORTAR, não deletar.
// ── POR QUE .mjs (US-GOV-052) ─ o .ps1 só roda no Windows. Supersede preflight-new-capability.ps1.
// ADVISORY (allow). Só ARQUIVO NOVO. Fail-open. Selftest: --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';
import { existsSync } from 'node:fs';

const BACKSLASH = String.fromCharCode(92);
export function toFwd(p) { return String(p || '').split(BACKSLASH).join('/'); }

export const CAPS = [
  [/Checker\.php$/, "DriftChecker (ADR 0216): implemente Modules/Governance/Contracts/DriftChecker + registre em config('governance.drift_checkers'). NAO crie comando/cron/alerta bespoke."],
  [/Reconciler\.php$/, "Reconciler JA existe (ChannelsReconcilerCommand WhatsApp); governance:audit ja orquestra."],
  [/Tool\.php$/, "MCP Tools vivem em Modules/Jana/Mcp/Tools/ + registry OimpressoMcpServer. Veja as existentes."],
  [/Command\.php$/, "rode decisions-search '<dominio>' + grep comando similar. Pode ja existir."],
  [/Service\.php$/, "grep Service similar em Modules/**/Services antes de criar."],
];

/** reason se o path (fwd) casa um cap pattern E está sob Modules/ ou app/, senão null (puro). */
export function capabilityReason(filePath) {
  const fwd = toFwd(filePath);
  if (!/(^|\/)(Modules|app)\//.test(fwd)) return null;
  for (const [re, reason] of CAPS) if (re.test(fwd)) return reason;
  return null;
}

export function buildOutput(filePath, reason) {
  return {
    hookSpecificOutput: {
      hookEventName: 'PreToolUse',
      permissionDecision: 'allow',
      permissionDecisionReason: `[oimpresso-anti-reinvencao] Criando capability NOVA: ${filePath}. ANTES de codar, saiba o que JA existe -> ${reason}  (licao 2026-05-29). Skills: como-integrar, mcp-first.`,
    },
  };
}

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
    let tool = '', path = '';
    try {
      const p = JSON.parse(raw);
      tool = String((p && p.tool_name) || '');
      path = String((p && p.tool_input && p.tool_input.file_path) || '');
    } catch { process.exit(0); }
    if (tool !== 'Write' || !path) process.exit(0);
    const reason = capabilityReason(path);
    if (!reason) process.exit(0);
    if (existsSync(path)) process.exit(0);
    process.stdout.write(JSON.stringify(buildOutput(path, reason)) + '\n');
    process.exit(0);
  } catch { process.exit(0); }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./preflight-new-capability.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
