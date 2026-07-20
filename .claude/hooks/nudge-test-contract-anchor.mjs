#!/usr/bin/env node
// nudge-test-contract-anchor.mjs — PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1, advisory).
// Lembra de ancorar teste em CONTRATO (SPEC/ADR/proibicoes/charter), não no código.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// proibicoes.md §"Ideias avaliadas e DESCARTADAS" (2026-06-05) + Check 9 da skill
// module-completeness-audit. Teste que copia o comportamento atual é tautológico —
// trava o drift em vez de pegá-lo (pior que não ter). Origem: FsmAuthorizationFlagPropertyTest (#2271).
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o nudge
// evapora em silêncio. Supersede nudge-test-contract-anchor.ps1.
//
// ADVISORY: exit 0 SEMPRE (nunca bloqueia). Fail-open em qualquer erro.
// Selftest: node .claude/hooks/nudge-test-contract-anchor.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

/** dispara só em arquivo de teste PHP (*Test.php, qualquer caminho). */
export function isTestFile(filePath) {
  return /test\.php$/i.test(String(filePath || '').replace(/\\/g, '/'));
}

export const NUDGE_LINES = [
  '',
  '[ANCORA DE CONTRATO - Check 9 / anti-regressao Opcao B]',
  '  Antes de escrever este teste, confirme que a assercao deriva de um CONTRATO',
  '  externo (SPEC / ADR / proibicoes / charter), NAO do que a classe ja faz.',
  '  - Cite a fonte no cabecalho: @see ADR-XXXX + a regra em portugues.',
  '  - Teste que copia o comportamento atual = tautologico = trava o drift (pior que nao ter).',
  "  Ref: memory/proibicoes.md secao 'Ideias avaliadas e DESCARTADAS' (2026-06-05).",
  '',
];

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
    let path = '';
    try { path = String((JSON.parse(raw) || {}).tool_input?.file_path || ''); } catch { process.exit(0); }
    if (!isTestFile(path)) process.exit(0);
    process.stdout.write(NUDGE_LINES.join('\n') + '\n');
    process.exit(0);
  } catch { process.exit(0); }
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./nudge-test-contract-anchor.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
