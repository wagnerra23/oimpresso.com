#!/usr/bin/env node
// check-skills-fresh.mjs — SessionStart (PORTE cross-plataforma do .ps1, advisory).
// Detecta skills modificadas/novas em .claude/skills/ desde o último start deste dev
// e avisa pra rodar /sync-skills.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// Claude Code só carrega skills no startup; se outro dev mergeou skill nova entre
// dois inícios, este dev fica desatualizado sem saber. Skill nova exige reiniciar
// pra ativar o matching. Estado por-dev em .claude/.last-skills-sync (gitignored).
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o aviso
// evapora em silêncio (e é justo com time multi-dev que o valor sobe). Supersede
// check-skills-fresh.ps1 (triagem #15, lote A).
//
// ADVISORY: exit 0 SEMPRE. Fail-open em qualquer erro. NÃO atualiza o state
// (só /sync-skills atualiza, após o dev ler — senão o aviso some sem ser visto).
// Selftest: node .claude/hooks/check-skills-fresh.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';
import { join } from 'node:path';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';

/** extrai slugs únicos das linhas de git-log (.claude/skills/<slug>/SKILL.md → <slug>). */
export function extractSlugs(touchedLines) {
  const set = new Set();
  for (const ln of (touchedLines || [])) {
    const m = /\.claude\/skills\/([^/]+)\/SKILL\.md/.exec(String(ln));
    if (m) set.add(m[1]);
  }
  return [...set].sort();
}

export function formatMessage(slugs) {
  if (!slugs.length) return '';
  const out = ['', `[skills] ${slugs.length} skill(s) modificada(s) desde sua ultima sessao:`];
  for (const s of slugs.slice(0, 8)) out.push(`    - ${s}`);
  if (slugs.length > 8) out.push(`    ... +${slugs.length - 8} outras`);
  out.push('', '-> Rode /sync-skills pra ver o que mudou e ler conteudo novo.',
    '-> Skills NOVAS exigem reiniciar Claude Code pra ativar matching automatico.', '');
  return out.join('\n');
}

/** linhas de SKILL.md tocadas por commits desde lastSync (via git log). */
export function touchedSince(lastSync, root) {
  const r = spawnSync('git', ['log', `--since=${lastSync}`, '--name-only', '--diff-filter=AMR',
    '--pretty=format:', '--', '.claude/skills/*/SKILL.md'], { encoding: 'utf8', cwd: root });
  return (r.stdout || '').split('\n').map((l) => l.trim()).filter(Boolean);
}

async function main() {
  try {
    const root = process.cwd();
    const skillsDir = join(root, '.claude', 'skills');
    const stateFile = join(root, '.claude', '.last-skills-sync');
    if (!existsSync(skillsDir)) process.exit(0);
    const now = new Date().toISOString().slice(0, 19);
    if (!existsSync(stateFile)) { try { writeFileSync(stateFile, now); } catch {} process.exit(0); }
    let lastSync = '';
    try { lastSync = readFileSync(stateFile, 'utf8').trim(); } catch {}
    if (!lastSync) { try { writeFileSync(stateFile, now); } catch {} process.exit(0); }
    const slugs = extractSlugs(touchedSince(lastSync, root));
    const msg = formatMessage(slugs);
    if (msg) process.stderr.write(msg + '\n');
    process.exit(0);
  } catch { process.exit(0); }
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./check-skills-fresh.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
