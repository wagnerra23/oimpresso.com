#!/usr/bin/env node
// block-test-without-red.mjs — PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1).
// Barra (modo block) / avisa (modo warn, DEFAULT) a criação de TESTE NOVO (*Test.php)
// sem evidência de ter FALHADO vermelho antes. Red-first com dentes (SDD FV-T0).
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// proibicoes.md §"Ideias avaliadas e DESCARTADAS" 2026-06-05: teste derivado do
// código é tautológico — passa verde mesmo errado e TRAVA o desvio em vez de
// pegá-lo. A evidência de red prova que o teste DISCRIMINA certo de errado.
// Origem: plano-mãe memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md
// (Semana 0, frente FV, passo T0) + audit 2026-06-12 P1 item 5.
// Complementa: warn-red-first (advisory, lado da PRODUÇÃO) e
// nudge-test-contract-anchor (advisory, "ancore a asserção num contrato").
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o guard
// evapora em silêncio. Supersede block-test-without-red.ps1.
//
// NASCE EM MODO WARN (default) — gates novos nascem advisory (ADR 0224).
// Armar = OIMPRESSO_REDFIRST_BLOCK_MODE=block (1 env, VISÍVEL — nunca silencioso;
// promoção = PR + ADR própria, ver critério no .ps1/plano-mãe).
//
// EVIDÊNCIA DE RED (qualquer UMA destrava a criação do teste novo):
//   1. cabeçalho no próprio teste:  // red-first: rodei <cmd>, FALHOU com <erro> antes de implementar
//   2. arquivo .claude/run/red-evidence-*.txt modificado <60min (saída do run vermelho)
//   3. override legítimo no conteúdo: red-first-override: <razão>
//      (characterization de legado, golden/snapshot, regressão pós-bug onde o RED foi o bug report)
//
// Só morde TESTE NOVO (Write de *Test.php não-rastreado no git). Edit de teste
// existente NÃO re-exige red. Fail-open em qualquer erro (exit 0).
//
// Env: OIMPRESSO_REDFIRST_BLOCK_MODE = warn (default) | block | off
//      OIMPRESSO_REDFIRST_REPO_ROOT  = raiz do repo git (default: 2 níveis acima deste script)
//      OIMPRESSO_REDFIRST_EVID_MINUTES = janela do red-evidence-*.txt (default 60)
//
// Selftest: node .claude/hooks/block-test-without-red.mjs --selftest
// Exit: 0 = continua | 2 = bloqueia (modo block; stderr vira a razão pro Claude).

import { spawnSync } from 'node:child_process';
import { pathToFileURL, fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { readdirSync, statSync, existsSync } from 'node:fs';

/** modo efetivo a partir do env (warn default). */
export function getMode(env = process.env) {
  const m = String(env.OIMPRESSO_REDFIRST_BLOCK_MODE || 'warn').toLowerCase();
  return m === 'block' || m === 'off' ? m : 'warn';
}

/** é arquivo de teste PHP? (*Test.php, qualquer caminho, case-insensitive). */
export function isTestFile(filePath) {
  return /test\.php$/i.test(String(filePath || '').replace(/\\/g, '/'));
}

/** raiz do repo (override por env pra teste isolado em repo temporário). */
export function repoRoot(env = process.env) {
  if (env.OIMPRESSO_REDFIRST_REPO_ROOT) return env.OIMPRESSO_REDFIRST_REPO_ROOT;
  const hooksDir = dirname(fileURLToPath(import.meta.url));
  return dirname(dirname(hooksDir));
}

/** caminho relativo à raiz (se absoluto dentro do repo). */
export function relPath(filePath, root) {
  const norm = String(filePath || '').replace(/\\/g, '/');
  const rootNorm = String(root || '').replace(/\\/g, '/').replace(/\/+$/, '');
  if (rootNorm && norm.toLowerCase().startsWith(rootNorm.toLowerCase() + '/')) {
    return norm.slice(rootNorm.length + 1);
  }
  return norm;
}

/** teste já rastreado no git? (Write sobre tracked = overwrite, não re-exige red). */
export function isTracked(rel, root) {
  const r = spawnSync('git', ['-C', root, 'ls-files', '--error-unmatch', '--', rel], { encoding: 'utf8' });
  return r.status === 0 && Boolean((r.stdout || '').trim());
}

/** evidência 3: override legítimo no conteúdo → {razao} ou null. */
export function findOverride(content) {
  const m = /^.*red-first-override:\s*(\S.*)$/im.exec(String(content || ''));
  return m ? m[1].trim() : null;
}

/** evidência 1: cabeçalho de red no próprio teste. */
export function hasRedHeader(content) {
  return /red-first:\s*\S/im.test(String(content || ''));
}

/** evidência 2: .claude/run/red-evidence-*.txt modificado dentro da janela. */
export function hasFreshEvidence(root, evidMinutes, now = Date.now()) {
  const runDir = join(root, '.claude', 'run');
  if (!existsSync(runDir)) return false;
  const cutoff = now - evidMinutes * 60_000;
  return readdirSync(runDir).some((f) => {
    if (!/^red-evidence-.*\.txt$/.test(f)) return false;
    try { return statSync(join(runDir, f)).mtimeMs > cutoff; } catch { return false; }
  });
}

export function evidMinutes(env = process.env) {
  const v = String(env.OIMPRESSO_REDFIRST_EVID_MINUTES || '');
  return /^\d+$/.test(v) ? parseInt(v, 10) : 60;
}

export function fireLines(rel, minutes) {
  const base = rel.replace(/^.*\//, '').replace(/\.php$/i, '');
  return [
    '',
    '[RED-FIRST - teste novo sem evidencia de vermelho / SDD FV-T0]',
    `  Teste NOVO (${base} em ${rel}) sem prova de ter FALHADO vermelho antes (red-first evita teste tautologico).`,
    '  Satisfaca QUALQUER UMA:',
    `    1. cabecalho no ${base} : // red-first: rodei <cmd>, FALHOU com <erro> antes de implementar`,
    `    2. .claude/run/red-evidence-*.txt salvo nos ultimos ${minutes} min (saida do run vermelho)`,
    '    3. caso legitimo (characterization/golden/regressao pos-bug): red-first-override: <razao>',
    '  Ref: memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (FV-T0).',
  ];
}

// ── stdin wrapper (fail-open em TUDO) ────────────────────────────────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  try {
    const mode = getMode();
    if (mode === 'off') process.exit(0);

    let raw;
    try { raw = await readStdin(); } catch { process.exit(0); }
    if (!raw) process.exit(0);
    let tool = '';
    let path = '';
    let content = '';
    try {
      const payload = JSON.parse(raw);
      tool = String((payload && payload.tool_name) || '');
      path = String((payload && payload.tool_input && payload.tool_input.file_path) || '');
      content = String((payload && payload.tool_input && payload.tool_input.content) || '');
    } catch { process.exit(0); }

    if (!path || !isTestFile(path)) process.exit(0);
    // Só a CRIAÇÃO conta: Edit/MultiEdit operam sobre arquivo já existente.
    if (tool !== 'Write') process.exit(0);

    const root = repoRoot();
    const rel = relPath(path, root);
    if (isTracked(rel, root)) process.exit(0);

    const ov = findOverride(content);
    if (ov !== null) {
      process.stdout.write(`[RED-FIRST/block - override aceito] ${rel}\n  razao: ${ov}\n`);
      process.exit(0);
    }
    if (hasRedHeader(content)) process.exit(0);

    const minutes = evidMinutes();
    if (hasFreshEvidence(root, minutes)) process.exit(0);

    const lines = fireLines(rel, minutes);
    if (mode === 'block') {
      process.stderr.write(lines.join('\n') + '\n  Modo BLOCK: criacao barrada (exit 2). Use override acima ou OIMPRESSO_REDFIRST_BLOCK_MODE=off.\n');
      process.exit(2);
    }
    // warn (default): avisa, NÃO barra
    process.stdout.write(lines.join('\n') + '\n  Modo WARN (default): aviso advisory - exit 0, nao bloqueia. Promocao a block via ADR + env (ver cabecalho).\n\n');
    process.exit(0);
  } catch { process.exit(0); }
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./block-test-without-red.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
