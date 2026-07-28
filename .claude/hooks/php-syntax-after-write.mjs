#!/usr/bin/env node
// php-syntax-after-write.mjs — PostToolUse:Write|Edit|MultiEdit.
// Roda `php -l` no .php que o agente ACABOU de escrever e devolve o erro de
// sintaxe NA HORA, no lugar onde ele nasceu.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// A máquina já existia e SEMPRE existiu: `.github/workflows/ci.yml` roda
//   find app Modules -name '*.php' | xargs -n1 -P4 php -l
// no job REQUIRED `PHP / Pest (Unit)`. O defeito nunca foi cobertura — é LATÊNCIA:
// medido 2026-07-28 no PR #4905, o veredito chega em **7m11s** e só DEPOIS do push.
// Entre o Write e o veredito cabem commit, push, espera e (foi o caso) três rodadas
// de diagnóstico à mão. Este hook fecha essa distância pra ~0,3s.
//
// Incidente raiz (2026-07-28, chip SDD TeamMcp): um docblock que documentava a
// própria varredura colou o glob `Modules/*/Tests/*` no comentário — o `*/` do glob
// FECHA o comentário PHP ali, 31 linhas antes do fim. O parser então morria sempre
// na linha 54, longe do texto que causou o erro; dois "fixes" trataram sintoma
// (`const`→`function`, depois inline) porque o erro NÃO ANDAVA DE LINHA — sinal,
// não pista. `php -l` apontaria em 0,3s.
//
// ── POR QUE ESTE GATE ESCAPA DAS 4 LÁPIDES DE GUARD SINTÁTICO (§5) ───────────
// proibicoes.md §5 mata guard que decide por FORMA inventada — allowlist-de-pasta
// (2026-06-30), `@scope` (2026-07-09), vocabulário-de-enforcement (130 FP,
// 2026-07-16), `toHaveKey` (100% FP, 2026-07-26). Todos tinham o mesmo defeito:
// o critério era um palpite do autor sobre o que "parece" errado.
// Aqui o critério é o PRÓPRIO PARSER do PHP — o mesmo oráculo que o CI usa, o mesmo
// que decide se o arquivo roda em produção. Não há espaço pra palpite: ou compila
// ou não. FP MEDIDO ANTES de instalar (regra dura do §5), no corpus real:
//
//   4.477 arquivos (app/ + Modules/, sem vendor) · 142s · **0 erros = 0 FP**
//
// ── FAIL-OPEN EM TUDO QUE NÃO FOR ERRO PROVADO ──────────────────────────────
// Sem PHP na máquina (Mac/Linux do time MCP sem Herd) → exit 0 SILENCIOSO. Um hook
// que reclama de ferramenta ausente vira ruído e ensina a ignorar hook. A rede de
// segurança de quem não tem PHP local continua sendo o CI — que não mudou.
// NÃO viola ADR 0062 (teste só no CT 100): `php -l` é lint de sintaxe, não roda a
// suíte, não toca DB, não boota o app. O hook `block-test-fora-ct100` casa
// `php artisan test`/pest/phpstan — não casa `php -l`.
//
// Modos (env OIMPRESSO_PHP_LINT_MODE): warn (DEFAULT) | off.
// Override emergencial Tier 0: OIMPRESSO_PHP_LINT_OVERRIDE=1.
// Selftest (bite-test): node .claude/hooks/php-syntax-after-write.mjs --selftest
//
// Exit: 0 = silêncio | 2 = erro de sintaxe (stderr vira o feedback pro Claude).

import { spawnSync } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir, homedir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

/** Só .php importa. Nada de vendor/node_modules (não é código nosso). */
export function shouldFire(tool, toolInput) {
  if (!/^(Write|Edit|MultiEdit)$/.test(String(tool || ''))) return false;
  const p = String((toolInput && toolInput.file_path) || '');
  if (!/\.php$/i.test(p)) return false;
  if (/[\\/](vendor|node_modules)[\\/]/.test(p)) return false;
  return true;
}

/**
 * Acha o binário do PHP. Ordem: env explícito → PATH → Herd (Win/macOS).
 * Devolve null se não houver — e null significa SILÊNCIO, nunca reclamação.
 */
export function resolvePhp(env = process.env, home = homedir()) {
  const cand = [];
  if (env.OIMPRESSO_PHP_BIN) cand.push(env.OIMPRESSO_PHP_BIN);
  cand.push('php');
  cand.push(join(home, '.config', 'herd', 'bin', 'php.bat'));            // Herd Windows
  cand.push(join(home, 'Library', 'Application Support', 'Herd', 'bin', 'php')); // Herd macOS
  for (const c of cand) {
    if (c !== 'php' && !existsSync(c)) continue;
    const shell = /\.(bat|cmd)$/i.test(c);
    const r = spawnSync(c, ['-v'], { encoding: 'utf8', timeout: 8000, shell, windowsHide: true, env });
    if (r.status === 0 && /^PHP \d/.test(String(r.stdout || ''))) return c;
  }
  return null;
}

/**
 * Roda o parser. Devolve { ok:true } | { ok:false, msg } | { ok:true, skipped:true }.
 * Qualquer coisa que não seja "o parser disse que está errado" é ok — fail-open.
 */
export function lint(php, file) {
  if (!php) return { ok: true, skipped: 'sem-php' };
  const shell = /\.(bat|cmd)$/i.test(php);
  const r = spawnSync(php, ['-l', file], { encoding: 'utf8', timeout: 15000, shell, windowsHide: true });
  if (r.error || r.status === null) return { ok: true, skipped: 'sem-veredito' }; // timeout/spawn-fail
  const out = `${r.stdout || ''}${r.stderr || ''}`;
  if (/No syntax errors detected/i.test(out)) return { ok: true };
  const m = out.match(/(?:Parse|Fatal) error:.*/i);
  if (!m) return { ok: true, skipped: 'saida-inesperada' };   // não entendi → não acuso
  return { ok: false, msg: m[0].trim() };
}

export function mensagem(file, erro) {
  const linha = (erro.match(/on line (\d+)/i) || [])[1];
  const dica = linha
    ? `\n  Se o erro NÃO ANDA DE LINHA quando você mexe nessa linha, a causa está ANTES dela` +
      `\n  — o suspeito nº 1 é \`*/\` dentro de um docblock (glob tipo \`Modules/*/Tests/*\` em` +
      `\n  comentário fecha o bloco ali). Foi exatamente o incidente de 2026-07-28.`
    : '';
  return `[php-syntax] O arquivo que você acabou de escrever NÃO COMPILA:\n` +
         `  ${file}\n  ${erro}${dica}\n` +
         `  Oráculo: \`php -l\` — o mesmo do job required \`PHP / Pest (Unit)\`. Corrija agora:` +
         ` no CI esse veredito levaria ~7min e chegaria depois do push.`;
}

function main() {
  const mode = (process.env.OIMPRESSO_PHP_LINT_MODE || 'warn').toLowerCase();
  if (mode === 'off' || process.env.OIMPRESSO_PHP_LINT_OVERRIDE === '1') process.exit(0);

  let payload;
  try {
    payload = JSON.parse(readFileSync(0, 'utf8'));
  } catch { process.exit(0); }                                  // parse-fail → fail-open

  const tool = payload?.tool_name;
  const toolInput = payload?.tool_input;
  let fire = false;
  try { fire = shouldFire(tool, toolInput); } catch { process.exit(0); }
  if (!fire) process.exit(0);

  const file = String(toolInput.file_path);
  if (!existsSync(file)) process.exit(0);                       // sumiu/foi movido → não é problema meu

  let r;
  try { r = lint(resolvePhp(), file); } catch { process.exit(0); }
  if (r.ok) process.exit(0);                                    // compila, ou não pude decidir

  process.stderr.write(mensagem(file, r.msg) + '\n');
  process.exit(2);                                              // stderr vira feedback pro Claude
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./php-syntax-after-write.test.mjs', import.meta.url);
    const t = spawnSync(process.execPath, [fileURLToPath(test)], { stdio: 'inherit' });
    process.exit(t.status ?? 1);
  }
  if (process.argv.includes('--measure')) {
    // FP no corpus real: se o CI está verde, todo acusado aqui é falso-positivo.
    const php = resolvePhp();
    if (!php) { console.log('sem PHP local — nada a medir'); process.exit(0); }
    const dir = mkdtempSync(join(tmpdir(), 'phplint-'));
    writeFileSync(join(dir, 'ok.php'), '<?php class A {}\n');
    console.log('sanidade (arquivo trivial):', lint(php, join(dir, 'ok.php')).ok ? 'compila' : 'FALHOU');
    console.log('corpus: use o find do ci.yml — este modo só prova que o oráculo responde.');
    process.exit(0);
  }
  main();
}
