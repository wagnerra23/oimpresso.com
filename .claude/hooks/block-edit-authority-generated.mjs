#!/usr/bin/env node
// block-edit-authority-generated.mjs — PreToolUse:Write|Edit|MultiEdit.
// BLOQUEIA edição-à-mão de arquivo DERIVADO (frontmatter `authority: generated`).
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// ADR 0256 (knowledge survival): "derivado+enforçado SOBREVIVE; escrito+lembrado
// APODRECE". Um arquivo gerado editado à mão é um derivado que virou memória — o
// próximo `--write` do gerador sobrescreve a edição, e enquanto isso o índice mente.
// Grade de guardrails 2026-07-22: a classe "editar gerado à mão" tinha o gerador
// CERTO (module-surface --all --check faz regenera+diff) mas ZERO trava no vetor
// runtime — nenhum dos hooks PreToolUse barrava Edit de `authority: generated`.
// Este hook fecha esse vetor (o único 100% aberto), content-agnostic sobre os ~41
// arquivos gerados (SUPERFICIE.md + PAINEL-SISTEMA.md + COMECE-AQUI.md + irmãos).
//
// A DECISÃO é por PROVENIÊNCIA (o frontmatter declara `authority: generated`),
// NÃO por nome/pasta (§5 2026-06-30: allowlist-de-pasta é "incompleto por construção").
//
// ── POR QUE não pega o gerador ───────────────────────────────────────────────
// O gerador (module-surface.mjs) grava via `fs.writeFileSync` rodado por BASH, NÃO
// pelo tool Write/Edit — então este PreToolUse(Write|Edit|MultiEdit) NÃO o intercepta.
// Só o vetor-agente (Edit/Write do .md à mão) é barrado. O caminho certo continua
// livre: `node scripts/governance/module-surface.mjs <Mod> --write`.
//
// Escape consciente: env OIMPRESSO_ALLOW_GENERATED_EDIT=1 (ex: corrigir bug do
// PRÓPRIO gerador no header, com intenção explícita).
// Fail-open: qualquer erro/parse-fail/arquivo-inexistente → exit 0 (NUNCA trava).
// Read continua permitido. Só Write/Edit/MultiEdit em arquivo JÁ gerado bloqueia.
// Selftest: node .claude/hooks/block-edit-authority-generated.mjs --selftest
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { readFileSync, existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { pathToFileURL, fileURLToPath } from 'node:url';

const WRITE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit']);

/** Extrai o bloco de frontmatter YAML (entre o 1º par de ---). Vazio se não houver. */
export function extractFrontmatter(content) {
  const m = /^﻿?---\s*\r?\n([\s\S]*?)\r?\n---\s*(\r?\n|$)/.exec(String(content || ''));
  return m ? m[1] : '';
}

/** Verdadeiro só se o FRONTMATTER (não o corpo) declara authority: generated. */
export function declaresGenerated(content) {
  const fm = extractFrontmatter(content);
  return /^\s*authority:\s*["']?generated["']?\s*$/im.test(fm);
}

/** Deriva o comando de regeneração pelo path (SUPERFICIE tem gerador nomeado). */
export function regeneratorHint(filePath) {
  const p = String(filePath || '').replace(/\\/g, '/');
  const sup = /\/memory\/requisitos\/([^/]+)\/SUPERFICIE\.md$/i.exec(p);
  if (sup) return `node scripts/governance/module-surface.mjs ${sup[1]} --write`;
  return 'rode o GERADOR dono deste arquivo (o campo `description`/header do frontmatter diz qual) — não edite o .md à mão';
}

/**
 * Veredito único (testável, sem I/O): dado o tool, o path e o CONTEÚDO ATUAL do
 * arquivo-alvo, bloqueia? Bloqueia sse: é Write/Edit/MultiEdit + o conteúdo atual
 * declara authority: generated + sem escape. `currentContent` null = arquivo não
 * existe (criação nova) → NÃO bloqueia (não é edição de gerado).
 */
export function shouldBlock(toolName, filePath, currentContent, env = process.env) {
  if (!WRITE_TOOLS.has(toolName)) return false;
  if (!filePath) return false;
  if (env && env.OIMPRESSO_ALLOW_GENERATED_EDIT === '1') return false;
  if (currentContent == null) return false; // arquivo novo — criação, não edição de gerado
  return declaresGenerated(currentContent);
}

export function blockMessage(toolName, filePath) {
  return `[block-edit-authority-generated] ${toolName} em '${filePath}' BLOQUEADO.

Este arquivo é DERIVADO (frontmatter \`authority: generated\`) — não se edita à mão.
Editar aqui apodrece: o próximo run do gerador SOBRESCREVE sua edição e, até lá, o
índice mente (ADR 0256 — derivado+enforçado sobrevive; escrito+lembrado apodrece).

Caminho certo (regenera da árvore):
  ${regeneratorHint(filePath)}

Se a árvore mudou (código novo), rode o gerador — ele recalcula. Se o CONTEÚDO
do gerado está errado, o bug é no GERADOR (scripts/governance/), conserte lá.

Escape consciente (ex: corrigir o próprio gerador/header): rode de novo com
  OIMPRESSO_ALLOW_GENERATED_EDIT=1`;
}

// ── stdin wrapper (fail-open em TUDO) ────────────────────────────────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

/** Lê o conteúdo atual do alvo; null se não existir/erro (fail-open → não bloqueia). */
function readTargetSafe(filePath) {
  try {
    if (!filePath || !existsSync(filePath)) return null;
    // Só o topo importa (frontmatter) — mas readFileSync é barato e robusto.
    return readFileSync(filePath, 'utf8');
  } catch {
    return null;
  }
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
  } catch { process.exit(0); }          // parse-fail → fail-open
  if (!WRITE_TOOLS.has(tool) || !path) process.exit(0);
  const current = readTargetSafe(path);
  if (shouldBlock(tool, path, current)) {
    process.stderr.write(blockMessage(tool, path) + '\n');
    process.exit(2);
  }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = fileURLToPath(new URL('./block-edit-authority-generated.test.mjs', import.meta.url));
    const r = spawnSync(process.execPath, [test], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
