#!/usr/bin/env node
// @ts-check
// tema-owner-advisory.mjs — PreToolUse:Write (ADVISORY, allow · ADR 0224/0314).
//
// DISPARA no FLUXO REAL de criar estrutura: um `Write` de um doc de tema NOVO sob
// memory/requisitos/ (tópico, BRIEFING, SPEC, ou qualquer .md). Extrai as ENTIDADES que o
// doc declara (tabelas/functions/models/telas — via anchors do frontmatter + corpo) e pergunta
// ao núcleo `scripts/governance/tema-owner.mjs`: esse ASSUNTO já tem dono? Se sim, avisa
// "estenda X em vez de criar paralelo". Se não, silencia (ou confirma tema novo).
//
// Responde à dor [W] 2026-07-21 ("como sei que a Maiara não está duplicando estrutura?").
// NÃO é o `dup-detector` (arquivo-EXATO em PR) nem `preflight-new-capability` (código por nome):
// este mede SOBREPOSIÇÃO DE TEMA por entidade declarada, na hora da criação do doc.
//
// Acoplamento PROVADO no caminho real (trava §5 2026-07-09 "chokepoint fantasma"): o gatilho é
// Write de .md sob memory/requisitos — exatamente o que cria estrutura. Prova por controle-negativo
// no selftest (--selftest): Write que casa DEVE emitir; Write fora do path NÃO pode emitir.
//
// ADVISORY: sempre permissionDecision 'allow', exit 0. Só ARQUIVO NOVO. Fail-open.

import { pathToFileURL, fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { existsSync } from 'node:fs';

const HOOK_DIR = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = join(HOOK_DIR, '..', '..');

const BACKSLASH = String.fromCharCode(92);
export function toFwd(p) { return String(p || '').split(BACKSLASH).join('/'); }

/**
 * O Write é criação de DOC DE ESTRUTURA sob memory/requisitos? (não charter/casos de tela —
 * esses já têm gate próprio). Retorna true só pra .md sob memory/requisitos/.
 */
export function isEstruturaDoc(filePath) {
  const fwd = toFwd(filePath);
  if (!/\.md$/i.test(fwd)) return false;
  return /(^|\/)memory\/requisitos\//.test(fwd);
}

/** Monta o payload advisory do hook (allow). null se nada a dizer (silêncio). */
export function buildOutput(filePath, advisoryText) {
  if (!advisoryText) return null;
  return {
    hookSpecificOutput: {
      hookEventName: 'PreToolUse',
      permissionDecision: 'allow',
      permissionDecisionReason:
        `[tema-owner] Criando doc de estrutura NOVO (${toFwd(filePath)}). ` +
        `Antes de criar paralelo, veja quem já é dono do tema:\n${advisoryText}`,
    },
  };
}

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

/**
 * Roda o núcleo com o CONTENT do doc sendo escrito. Import dinâmico das funções puras
 * (mesmo motor — não duplica lógica). Retorna o texto advisory OU '' (tema novo/sem sinal).
 */
export async function analyze(content) {
  const mod = await import(pathToFileURL(join(REPO_ROOT, 'scripts/governance/tema-owner.mjs')).href);
  const corpus = mod.loadCorpus(REPO_ROOT);
  const signals = mod.signalsFromDoc(content || '', corpus.catalogIndex.knownTables);
  if (!signals.keys.size) return ''; // sem entidade declarada → nada a medir, silencia
  const result = mod.findOwners(signals, corpus, null);
  // Só fala quando ENCONTRA dono (overlap de tópico OU dono no catálogo). Tema novo → silêncio
  // (evita ruído: o valor do hook é apontar duplicação, não confirmar o óbvio a cada Write).
  if (!result.topicoOverlaps.length && !result.catalogOwners.length) return '';
  return mod.renderAdvisory(result, 'este doc');
}

async function main() {
  try {
    let raw;
    try { raw = await readStdin(); } catch { process.exit(0); }
    if (!raw) process.exit(0);
    let tool = '', path = '', content = '';
    try {
      const p = JSON.parse(raw);
      tool = String((p && p.tool_name) || '');
      path = String((p && p.tool_input && p.tool_input.file_path) || '');
      content = String((p && p.tool_input && p.tool_input.content) || '');
    } catch { process.exit(0); }
    if (tool !== 'Write' || !path) process.exit(0);
    if (!isEstruturaDoc(path)) process.exit(0);
    if (existsSync(path)) process.exit(0); // só arquivo NOVO
    const advisory = await analyze(content);
    const out = buildOutput(path, advisory);
    if (out) process.stdout.write(JSON.stringify(out) + '\n');
    process.exit(0);
  } catch { process.exit(0); } // fail-open
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const { spawnSync } = await import('node:child_process');
    const test = new URL('./tema-owner-advisory.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
