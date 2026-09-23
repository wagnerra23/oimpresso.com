#!/usr/bin/env node
// commit-discipline-check.mjs — PreToolUse:Bash (PORTE cross-plataforma do .ps1).
// ADVISORY da skill Tier A commit-discipline: avisa (nunca bloqueia) em git
// commit/add/push — diff staged >300 linhas, force push sem lease, possível PII.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// Skill .claude/skills/commit-discipline/SKILL.md + ADR 0094 §5 (SoC brutal):
// 1 PR = 1 intent, ≤300 linhas, conventional commits, sem PII em código/commit.
// regras-time.md: "PIIs reais (CPF/CNPJ cliente) NUNCA em PR ou commit."
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o advisory
// Tier A evapora em silêncio. Supersede commit-discipline-check.ps1.
//
// ADVISORY: exit 0 SEMPRE. Fail-open em qualquer erro.
// Selftest: node .claude/hooks/commit-discipline-check.mjs --selftest

import { spawnSync } from 'node:child_process';
import { readFileSync, statSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
// O parser de comando e o do hook irmao — UM parser so, nao um terceiro (LC-19). Ele ja trata
// comando composto, quebra de linha, heredoc da mensagem e o `git add` que vem antes do commit.
import { ehGitCommit, segmentos, argsDosAddsAntes, levaWorkingTree } from './maquinas-inventario-no-commit.mjs';

// ── POR QUE O PARSER MUDOU (medido 2026-09-23, corpus de transcripts) ─────────────
// Ate esta data as tres regex abaixo ancoravam em `^\s*git`: o commit so era visto se fosse a
// PRIMEIRA coisa do comando. De 5.561 comandos reais de commit, este hook reconhecia 51 — CEGO
// em 99,1%, porque o agente quase sempre faz `cd …` ou `git add … &&` antes. O aviso de PII
// (CPF/CNPJ no diff) e o de tamanho praticamente nunca rodaram. E, quando rodavam, mediam so o
// que ja estava no stage — nunca o que o `git add` do mesmo comando ia estagiar. O aviso de
// force push, que lia o comando inteiro, disparava com texto (heredoc, string): 6 falsos.

/** é git commit/add/push? (só esses ativam o check). */
export function isGitWriteCmd(cmd) {
  return segmentos(String(cmd || '')).some((s) => /^git\s+(?:-C\s+\S+\s+)?(commit|add|push)\b/.test(s));
}

/** force push SEM --force-with-lease? → aviso. So o segmento de push conta, nunca texto. */
export function isUnsafeForcePush(cmd) {
  return segmentos(String(cmd || '')).some((s) => /^git\s+(?:-C\s+\S+\s+)?push\b.*--force\b/.test(s) && !/--force-with-lease/.test(s));
}

/** é git commit? (dispara os checks de diff) — em qualquer posicao do comando. */
export function isCommit(cmd) {
  return ehGitCommit(String(cmd || ''));
}

/** diff contém CPF/CNPJ formatado? (possível PII — LGPD). */
export function hasPiiPattern(text) {
  return /\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/.test(text) || /\b\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}\b/.test(text);
}

const TUDO = ['-A', '--all', '.', ':/', '-u', '--update'];
const MAX_NOVO = 2 * 1024 * 1024;   // arquivo novo maior que isso nao entra na medicao (binario/gerado)

/**
 * O diff que ESTE commit vai levar: o stage de agora + o que o `git add` do mesmo comando vai
 * estagiar (ou o working tree, no `commit -a`). Arquivo novo entra com o conteudo inteiro como
 * linha adicionada. `{ texto, insercoes }`; lanca se o git falhar (o chamador e fail-open).
 */
export function diffDoCommit(cmd, cwd) {
  const git = (args) => {
    const r = spawnSync('git', args, { encoding: 'utf8', cwd: cwd || undefined, maxBuffer: 64 * 1024 * 1024 });
    if (r.status !== 0) throw new Error('git ' + args[0] + ' falhou');
    return r.stdout || '';
  };
  const adds = argsDosAddsAntes(cmd);
  const addTudo = adds.some((a) => TUDO.includes(a));
  const soRastreados = adds.includes('-u') || adds.includes('--update') || (!adds.length && levaWorkingTree(cmd));
  const specs = adds.filter((a) => !a.startsWith('-'));
  let texto;
  let novos = [];
  if (addTudo || levaWorkingTree(cmd)) {
    texto = git(['diff', 'HEAD', '--no-color']);                       // stage + working tree rastreado
    if (!soRastreados) novos = git(['ls-files', '--others', '--exclude-standard']).split(/\r?\n/).filter(Boolean);
  } else if (specs.length) {
    // o que ja esta no stage FORA dos paths do add + a versao que o add vai levar desses paths
    texto = git(['diff', '--cached', '--no-color', '--', '.', ...specs.map((s) => ':(exclude)' + s)])
      + git(['diff', 'HEAD', '--no-color', '--', ...specs]);
    novos = git(['ls-files', '--others', '--exclude-standard', '--', ...specs]).split(/\r?\n/).filter(Boolean);
  } else {
    texto = git(['diff', '--cached', '--no-color']);
  }
  for (const f of novos) {
    try {
      const abs = resolve(cwd || '.', f);
      if (statSync(abs).size > MAX_NOVO) continue;
      // a quebra FINAL nao e uma linha (sem isto, 400 linhas contavam 401 — o `--shortstat` diz 400)
      texto += '\n' + readFileSync(abs, 'utf8').replace(/\r?\n$/, '').split(/\r?\n/).map((l) => '+' + l).join('\n');
    } catch { /* sumiu entre o ls-files e a leitura */ }
  }
  const insercoes = texto.split(/\r?\n/).filter((l) => l.startsWith('+') && !l.startsWith('+++')).length;
  return { texto, insercoes };
}

/** avisos aplicáveis (puros exceto os medidores de git, injetáveis pro teste). */
export function buildWarnings(cmd, { insertions = null, pii = false } = {}) {
  const out = [];
  if (isUnsafeForcePush(cmd)) {
    out.push('[commit-discipline] AVISO: force push detectado SEM --force-with-lease.\n' +
      '  Best-practice: use --force-with-lease pra evitar sobrescrever trabalho de outros.');
  }
  if (isCommit(cmd)) {
    if (insertions !== null && insertions > 300) {
      out.push(`[commit-discipline] AVISO: diff staged tem ${insertions} linhas (alvo <=300).\n` +
        '  Considere dividir em PRs menores. Se for refactor amplo justificado, ok seguir.');
    }
    if (pii) {
      out.push('[commit-discipline] AVISO: POSSIVEL PII no diff (CPF/CNPJ formatado).\n' +
        '  LGPD: dados reais NUNCA em commit. Use [REDACTED] ou data fake (123.456.789-09).\n' + // pii-allowlist (CPF fake didatico na mensagem de aviso)
        '  Se for fake (CPF invalido pra teste), seguir e OK.');
    }
  }
  return out;
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
    let cmd = '';
    let cwd = '';
    try {
      const payload = JSON.parse(raw);
      cmd = String((payload && payload.tool_input && payload.tool_input.command) || '');
      cwd = String((payload && payload.cwd) || '');
    } catch { process.exit(0); }
    if (!isGitWriteCmd(cmd)) process.exit(0);

    let measured = {};
    if (isCommit(cmd)) {
      try {
        const d = diffDoCommit(cmd, cwd);
        measured = { insertions: d.insercoes, pii: hasPiiPattern(d.texto) };
      } catch { measured = {}; }                        // git falhou: nao meço, nao aviso (fail-open)
    }
    const warnings = buildWarnings(cmd, measured);
    if (warnings.length) process.stdout.write('\n' + warnings.join('\n\n') + '\n\n');
    process.exit(0);
  } catch { process.exit(0); }
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./commit-discipline-check.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [fileURLToPath(test)], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
