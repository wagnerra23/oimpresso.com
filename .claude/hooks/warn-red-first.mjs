#!/usr/bin/env node
// warn-red-first.mjs — PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1).
// ADVISORY red-first: avisa quando Edit/Write toca arquivo de PRODUÇÃO
// (app/**, Modules/**/{Services,Entities,Http}/**) sem nenhum teste (*Test.php)
// tocado/criado na sessão recente. Ensina red → green → refactor.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// Plano-mãe memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md
// (Semana 0, frente FV, passo T0) + audit 2026-06-12 P1 item 5. Teste escrito
// DEPOIS do código tende a copiar o comportamento atual (tautológico —
// proibicoes.md §"Ideias descartadas" 2026-06-05).
// Par com dentes (lado do TESTE): block-test-without-red.
//
// ── POR QUE .mjs (US-GOV-052 — port cross-plataforma dos hooks .ps1) ─────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o advisory
// evapora em silêncio. Supersede warn-red-first.ps1.
//
// ADVISORY DE NASCENÇA: exit 0 SEMPRE, nunca bloqueia nesta fase.
// Fase WARN deliberadamente LENIENTE: QUALQUER *Test.php tocado na sessão conta.
// Fail-open em qualquer erro (exit 0).
//
// Env: OIMPRESSO_REDFIRST_MODE         = warn (default) | off
//      OIMPRESSO_REDFIRST_REPO_ROOT    = raiz do repo git (default: 2 níveis acima deste script)
//      OIMPRESSO_REDFIRST_WINDOW_HOURS = janela "sessão recente" pra commits (default 4)
//
// Selftest: node .claude/hooks/warn-red-first.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL, fileURLToPath } from 'node:url';
import { dirname } from 'node:path';

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

/** arquivo de PRODUÇÃO? (app/**.php ou Modules/<Mod>/{Services,Entities,Http}/**.php;
 *  teste e markdown NUNCA disparam). */
export function isProdFile(rel) {
  if (/test\.php$/i.test(rel)) return false;
  if (/\.md$/i.test(rel)) return false;
  return /^app\/.+\.php$/i.test(rel) || /^Modules\/[^/]+\/(Services|Entities|Http)\/.+\.php$/i.test(rel);
}

export function windowHours(env = process.env) {
  const v = String(env.OIMPRESSO_REDFIRST_WINDOW_HOURS || '');
  return /^\d+$/.test(v) ? parseInt(v, 10) : 4;
}

/** algum *Test.php tocado na sessão recente? (uncommitted via status -uall,
 *  senão commitado na janela via git log). */
export function testTouchedRecently(root, hours) {
  const status = spawnSync('git', ['-C', root, 'status', '--porcelain', '-uall'], { encoding: 'utf8' });
  if ((status.stdout || '').split('\n').some((l) => /test\.php$/i.test(l))) return true;
  const log = spawnSync(
    'git', ['-C', root, 'log', `--since=${hours} hours ago`, '--name-only', '--pretty=format:'],
    { encoding: 'utf8' },
  );
  return (log.stdout || '').split('\n').some((l) => /test\.php$/i.test(l));
}

export function warnLines(rel) {
  const base = rel.replace(/^.*\//, '').replace(/\.php$/i, '');
  return [
    '',
    '[RED-FIRST - advisory / SDD FV-T0]',
    `  Voce vai editar codigo de PRODUCAO (${rel}) sem nenhum teste tocado nesta sessao.`,
    `  Escreva o teste que FALHA primeiro: crie/ajuste ${base}Test.php, rode, veja`,
    '  VERMELHO, e so entao escreva o codigo que o faz passar (red -> green -> refactor).',
    '  Teste escrito DEPOIS do codigo tende a copiar o comportamento atual (tautologico).',
    '  Aviso advisory - exit 0, nao bloqueia nada nesta fase.',
    '  Ref: plano SDD memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (FV-T0).',
    '',
  ];
}

// ── stdin wrapper (fail-open em TUDO; SEMPRE exit 0 — advisory) ──────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  try {
    if (process.env.OIMPRESSO_REDFIRST_MODE === 'off') process.exit(0);
    let raw;
    try { raw = await readStdin(); } catch { process.exit(0); }
    if (!raw) process.exit(0);
    let path = '';
    try {
      const payload = JSON.parse(raw);
      path = String((payload && payload.tool_input && payload.tool_input.file_path) || '');
    } catch { process.exit(0); }
    if (!path) process.exit(0);

    const root = repoRoot();
    const rel = relPath(path, root);
    if (!isProdFile(rel)) process.exit(0);
    if (testTouchedRecently(root, windowHours())) process.exit(0);

    process.stdout.write(warnLines(rel).join('\n') + '\n');
    process.exit(0);
  } catch { process.exit(0); }
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./warn-red-first.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
