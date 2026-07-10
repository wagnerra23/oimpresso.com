#!/usr/bin/env node
// block-mwart-violation.mjs — PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1).
// BLOQUEIA Edit/Write em Pages/<Mod>/<Tela>.tsx sem o RUNBOOK da tela existir.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// ADR 0104 §Enforcement (processo MWART canônico — ÚNICO caminho): F1 PLAN (RUNBOOK +
// SPEC) acontece ANTES de F3 FRONTEND (codar a Page Inertia). Wagner 2026-05-08:
// "Falhas não são aceitáveis. Não pode ter 2 caminhos de desenvolvimento."
// proibicoes.md §MWART: Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx` SEM
// `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md` é PROIBIDO. Desde a ADR 0271 onda 2
// (mwart-gate.yml de CI deletado — era teatro continue-on-error) este hook runtime é o
// ÚNICO enforcement de RUNBOOK. Override: comentar '/mwart-override <razão>' em PR
// (vira ADR per-tela lifecycle:historical).
//
// Exempções (derivadas da regra — _components/helpers não são telas migráveis):
//   - Pages/_Showcase|_components|_internal/** (módulo utilitário, não vertical)
//   - Pages/<Mod>/_components/** e qualquer subpasta _* (privado do módulo)
//   - Telas iniciando com _ ou chamadas App/Layout (infra do shell, não tela)
//
// ── POR QUE .mjs (porte da leva Tier-0, SPEC US-GOV-052 / P24) ───────────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o enforcement
// evaporava em silêncio — e este é o ÚNICO gate de RUNBOOK desde a ADR 0271. Node é
// cross-plataforma. Nota Linux: o lookup do RUNBOOK é case-insensitive DE PROPÓSITO
// (Windows é case-blind; um RUNBOOK-Foo.md criado no Windows precisa contar no Linux).
// Supersede block-mwart-violation.ps1 (pattern-setter: block-test-fora-ct100.mjs, #4025).
//
// Fail-open: qualquer erro/parse-fail/fs-fail → exit 0 (NUNCA trava sessão).
// Selftest: node .claude/hooks/block-mwart-violation.test.mjs
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { readdirSync } from 'node:fs';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';

const WRITE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit']);
// Pages/<Mod>/<Tela>.tsx com no máx. 1 subpasta intermediária NÃO-underscore
// (Pages/<Mod>/_components/X.tsx NÃO casa → exempt por construção).
const PAGE_REGEX = /resources\/js\/Pages\/([^/_][^/]*)\/(?:[^/_][^/]*\/)?([A-Za-z][A-Za-z0-9]*)\.tsx$/;
const MOD_EXEMPT = new Set(['_showcase', '_components', '_internal']);
const TELA_EXEMPT = new Set(['App', 'Layout']);

/** PascalCase → kebab-case (NfceStatus → nfce-status). */
export function toKebab(tela) {
  return String(tela).replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
}

/** extrai {modulo, tela} se o path é uma tela migrável; null se fora de escopo/exempt. */
export function parsePagePath(filePath) {
  const fwd = String(filePath || '').replace(/\\/g, '/');
  const m = PAGE_REGEX.exec(fwd);
  if (!m) return null;
  const [, modulo, tela] = m;
  if (MOD_EXEMPT.has(modulo.toLowerCase())) return null;
  if (TELA_EXEMPT.has(tela)) return null;
  return { modulo, tela };
}

/** lookup case-insensitive: pasta do módulo em memory/requisitos + RUNBOOK-<kebab>.md.
 *  Retorna 'ok' | 'sem-pasta' | 'sem-runbook'. fs-fail → 'ok' (fail-open). */
export function runbookStatus(modulo, telaKebab, root = process.cwd()) {
  try {
    const base = join(root, 'memory', 'requisitos');
    const dirs = readdirSync(base, { withFileTypes: true });
    const modDir = dirs.find((d) => d.isDirectory() && d.name.toLowerCase() === modulo.toLowerCase());
    if (!modDir) return 'sem-pasta';
    const alvo = `runbook-${telaKebab}.md`.toLowerCase();
    const files = readdirSync(join(base, modDir.name));
    return files.some((f) => f.toLowerCase() === alvo) ? 'ok' : 'sem-runbook';
  } catch {
    return 'ok'; // fail-open — fs indisponível nunca trava a sessão
  }
}

/** veredito único: null (continua) ou a mensagem de bloqueio. */
export function decide(toolName, filePath, root = process.cwd()) {
  if (!WRITE_TOOLS.has(toolName)) return null;
  const page = parsePagePath(filePath);
  if (!page) return null;
  const kebab = toKebab(page.tela);
  const status = runbookStatus(page.modulo, kebab, root);
  if (status === 'ok') return null;
  const runbook = `memory/requisitos/${page.modulo}/RUNBOOK-${kebab}.md`;
  const causa = status === 'sem-pasta'
    ? `A pasta 'memory/requisitos/${page.modulo}/' nem existe — F1 (PLAN) nunca rolou.`
    : `RUNBOOK '${runbook}' ausente.`;
  return `[mwart-process] ${toolName} em '${filePath}' BLOQUEADO.
ADR 0104 §F1 PLAN exige RUNBOOK '${runbook}' antes de F3 FRONTEND (codar a Page). ${causa}
Rode '/cockpit-runbook /<rota>' pra gerar RUNBOOK + SPEC (~12min com IA-pair).
Override: comentar '/mwart-override <razão>' em PR (vira ADR per-tela lifecycle:historical).
Desde a ADR 0271 onda 2 este hook runtime é o ÚNICO enforcement de RUNBOOK (mwart-gate CI foi deletado).`;
}

// ── stdin wrapper (fail-open em TUDO) ────────────────────────────────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  let raw;
  try { raw = await readStdin(); } catch { process.exit(0); }
  if (!raw) process.exit(0);
  let tool = '';
  let path = '';
  try {
    const payload = JSON.parse(raw);
    tool = String((payload && payload.tool_name) || '');
    path = String((payload && payload.tool_input && payload.tool_input.file_path) || '');
  } catch { process.exit(0); }        // parse-fail → fail-open
  const veto = decide(tool, path);
  if (veto) { process.stderr.write(veto + '\n'); process.exit(2); }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-mwart-violation.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
