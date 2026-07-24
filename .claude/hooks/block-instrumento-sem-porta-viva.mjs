#!/usr/bin/env node
// block-instrumento-sem-porta-viva.mjs — PreToolUse:Glob|Grep.
//
// Bloqueia o INSTRUMENTO ERRADO quando existe PORTA VIVA registrada pra aquela pergunta.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// LICOES_CODE LC-08 (classe `afirmar-sem-medir-fonte-certa`, 7 ocorrências) +
// memory/proibicoes.md §5 (2026-07-15, 07-16, 07-17 ×3, 07-22 ×2).
// A lei positiva das lápides: "pergunte ao sistema que sabe" — o banco via SELECT,
// o scheduler via runsInEnvironment(), o mapa de tela via porta viva derivada.
//
// ── POR QUE ESTA FORMA (e não as que o §5 já matou) ──────────────────────────
// NÃO é gate de vocabulário ("afirmou sem medir") — essa forma foi MEDIDA e deu
// 130 falso-positivos em árvore limpa (§5 2026-07-16). NÃO é presence-gate
// (§5 2026-07-09). NÃO é mapa novo (§5 2026-07-23): a tabela de pares vive DENTRO
// do hook, como o block-test-fora-ct100 sabe "php artisan test → CT 100".
// O chokepoint é a TOOL CALL real — o instrumento que a lápide flagrou sendo usado.
//
// ── PARES (só entra par MEDIDO — tabela especulativa é o erro que isto mata) ──
//  P1 mapa-de-artefatos-de-tela → `npm run screen:files <Mod>/<Tela>`
//     MEDIDO 2026-07-24: a porta responde a MESMA pergunta e melhor (scorecard, e2e,
//     RUNBOOK, visual-comparison, proto-baseline, UC↔teste, veredito). Glob perdeu
//     todos esses em 07-22. Discriminação anti-FP: só morde com WILDCARD (varredura
//     = "montar mapa"); path literal (ler UM charter) passa.
//  P2 o-que-roda/rota/config → oráculo de runtime (schedule:list / route:list / config:show)
//     Enumerado literalmente em proibicoes.md §5 (2026-07-17, "deduzir quem roda").
//     FP conhecido e ACEITO: quem vai EDITAR o Kernel também grepa por schedule.
//     Mitigação = escape na própria mensagem (1 env var). Reversível: se der FP em
//     série, estreitar o pattern ou demover o par — não apagar o hook.
//
// Fail-open: qualquer erro/parse-fail → exit 0 (NUNCA trava sessão).
// Escape valve: OIMPRESSO_PORTA_VIVA_OK=1 (quando o instrumento cru É mesmo o certo).
// Selftest: node .claude/hooks/block-instrumento-sem-porta-viva.mjs --selftest
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { pathToFileURL, fileURLToPath } from 'node:url';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

// ── classificadores PUROS (exportados → testáveis sem stdin) ─────────────────

/** escape valve explícito (o instrumento cru é mesmo o certo desta vez). */
export function hasOverride(env = process.env) {
  return env.OIMPRESSO_PORTA_VIVA_OK === '1';
}

/** o pattern varre (wildcard/alternância) em vez de apontar UM arquivo? */
export function isVarredura(pattern) {
  return /[*?{}]|\*\*/.test(String(pattern || ''));
}

/**
 * P1 — pergunta "quais artefatos/arquivos tem a tela?" feita por Glob/Grep.
 * Só morde em VARREDURA: `Produto/Edit.charter.md` (literal) é leitura legítima.
 */
export function isMapaDeTela(pattern) {
  const p = String(pattern || '');
  if (!isVarredura(p)) return false;
  return /\.charter\.md|\.casos\.md|scorecards[/\\]screens|proto-baseline|visual-comparison/.test(p);
}

/**
 * P2 — pergunta "o que roda / qual rota / qual config?" feita por leitura estática.
 * Exige as DUAS pernas: o arquivo-fonte-errado E o vocabulário da pergunta-de-runtime.
 */
export function isPerguntaDeRuntime(pattern, path) {
  const p = String(pattern || '');
  const alvo = String(path || '');
  const fonteEstatica = /Kernel\.php|routes[/\\][\w-]+\.php|\.env|config[/\\][\w-]+\.php/.test(alvo + ' ' + p);
  if (!fonteEstatica) return false;
  return /schedule|environments|runsInEnvironment|->(daily|hourly|cron|weekly)|QUEUE_CONNECTION|Route::(get|post)|config\(/.test(p);
}

/** veredito único: qual par mordeu? (null = passa) */
export function classificar({ pattern, path } = {}, env = process.env) {
  if (hasOverride(env)) return null;
  if (isMapaDeTela(pattern)) return 'P1';
  if (isPerguntaDeRuntime(pattern, path)) return 'P2';
  return null;
}

export const MENSAGENS = {
  P1: `[LC-08 / proibicoes §5 2026-07-22] BLOQUEADO: mapa de artefatos de tela por Glob/Grep.

O mapa de "quais arquivos cada tela tem" e um COMANDO derivado da arvore, nao uma busca
por nome. Glob-por-nome e incompleto POR CONSTRUCAO: em 2026-07-22 ele perdeu os
scorecards, os visual-comparison, o veredito de trio e a ambiguidade de resolucao.

PORTA VIVA (responde a mesma pergunta, e melhor):

  npm run screen:files <Mod>/<Tela>        # ex: npm run screen:files Produto/Edit
  npm run screen-coverage:report           # panorama de todas as telas
  npm run casos:report                     # cobertura de casos/UC

Ref: memory/how-trabalhar.md "Mapa de arquivos por tela — e COMANDO, nao arquivo".
Se o glob cru E mesmo o certo aqui: OIMPRESSO_PORTA_VIVA_OK=1`,

  P2: `[LC-08 / proibicoes §5 2026-07-17] BLOQUEADO: deduzir runtime lendo fonte estatica.

"O que roda / quem invoca / qual rota / qual config" NAO se responde por grep em
Kernel.php / routes / .env — num app modular a fonte e MULTIPLA (ServiceProviders de
modulo registram os proprios), entao analise file-scoped e incompleta por construcao.
Em 2026-07-17 errei 3x parseando e acertei na 1a vez que perguntei ao runtime.

PERGUNTE AO SISTEMA QUE SABE (no CT 100 — ADR 0062):

  tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan schedule:list"
  ... php artisan route:list        # rota
  ... php artisan config:show <k>   # config (nao leia o .env)
  Event::runsInEnvironment()        # a MESMA funcao que o schedule:run usa pra filtrar

Sem oraculo disponivel? Meca pela CONSEQUENCIA (artefato gerado, fila vazia, log com
timestamp) — nunca pela declaracao.
Se voce vai EDITAR o Kernel/rotas (nao perguntar o que roda): OIMPRESSO_PORTA_VIVA_OK=1`,
};

// ── selftest (fixtures BOA/RUIM — controle-negativo prova que morde) ─────────
function selftest() {
  const casos = [
    // RUIM (deve bloquear)
    ['P1 varredura de charter', { pattern: '**/*.charter.md' }, 'P1'],
    ['P1 varredura de casos', { pattern: 'resources/js/Pages/**/*.casos.md' }, 'P1'],
    ['P1 scorecards por tela', { pattern: 'memory/governance/scorecards/screens/*.yaml' }, 'P1'],
    ['P2 grep schedule no Kernel', { pattern: '->daily\\(', path: 'app/Console/Kernel.php' }, 'P2'],
    ['P2 grep environments', { pattern: 'environments', path: 'app/Console/Kernel.php' }, 'P2'],
    ['P2 config pelo .env', { pattern: 'QUEUE_CONNECTION', path: '.env' }, 'P2'],
    // BOA (deve passar — se qualquer uma bloquear, o hook backfira)
    ['ler UM charter literal', { pattern: 'resources/js/Pages/Produto/Edit.charter.md' }, null],
    ['glob de tsx comum', { pattern: 'resources/js/Pages/**/*.tsx' }, null],
    ['grep de negocio no Kernel', { pattern: 'PiiRedactor', path: 'app/Console/Kernel.php' }, null],
    ['grep schedule fora do Kernel', { pattern: 'schedule', path: 'Modules/Jana/README.md' }, null],
    ['glob de migrations', { pattern: '**/Database/Migrations/*.php' }, null],
    ['charter literal em Grep', { pattern: 'Non-Goals', path: 'Produto/Edit.charter.md' }, null],
  ];
  let ok = 0;
  const falhas = [];
  for (const [nome, input, esperado] of casos) {
    const got = classificar(input, {});
    if (got === esperado) ok++;
    else falhas.push(`  ✗ ${nome}: esperado ${esperado}, veio ${got}`);
  }
  // controle-negativo do escape
  const comOverride = classificar({ pattern: '**/*.charter.md' }, { OIMPRESSO_PORTA_VIVA_OK: '1' });
  if (comOverride === null) ok++;
  else falhas.push('  ✗ escape valve nao liberou');

  // ── INVOCAÇÃO REAL (não só o classificador puro) ──────────────────────────
  // Por que existe: em 2026-07-24 este selftest deu 13/13 VERDE enquanto o hook
  // NÃO mordia — `require()` em ESM lançava, o fail-open engolia e virava exit 0.
  // Classificador correto ≠ hook que morde (§5 "correção-do-mecanismo ≠ invocação").
  // Estes 2 casos rodam o PRÓPRIO script por stdin e conferem o EXIT CODE.
  const eu = fileURLToPath(import.meta.url);
  const roda = (payload) => spawnSync(process.execPath, [eu], {
    input: JSON.stringify(payload), encoding: 'utf8',
    env: { ...process.env, OIMPRESSO_PORTA_VIVA_OK: '' },
  });
  const bloqueio = roda({ tool_name: 'Glob', tool_input: { pattern: '**/*.charter.md' } });
  if (bloqueio.status === 2 && /PORTA VIVA/.test(bloqueio.stderr || '')) ok++;
  else falhas.push(`  ✗ invocacao real: esperado exit 2 + razao, veio exit ${bloqueio.status}`);

  const passagem = roda({ tool_name: 'Glob', tool_input: { pattern: 'resources/js/Pages/**/*.tsx' } });
  if (passagem.status === 0) ok++;
  else falhas.push(`  ✗ invocacao real (controle-negativo): esperado exit 0, veio ${passagem.status}`);

  const total = casos.length + 3;
  console.log(`block-instrumento-sem-porta-viva selftest: ${ok}/${total}`);
  if (falhas.length) { console.error(falhas.join('\n')); process.exit(1); }
  console.log('  MORDE: varredura de charter/casos/scorecard + pergunta-de-runtime em fonte estatica');
  console.log('  PASSA: path literal, glob comum, grep de negocio, escape valve');
  console.log('  INVOCACAO REAL: exit 2 com razao no bloqueio, exit 0 na passagem');
  process.exit(0);
}

// ── entrypoint ───────────────────────────────────────────────────────────────
function main() {
  let raw = '';
  try { raw = readFileSync(0, 'utf8'); } catch { process.exit(0); }
  let par = null;
  try {
    const ev = JSON.parse(raw);
    const inp = ev.tool_input || {};
    par = classificar({ pattern: inp.pattern, path: inp.path }, process.env);
  } catch { process.exit(0); } // fail-open
  if (!par) process.exit(0);
  console.error(MENSAGENS[par]);
  process.exit(2);
}

if (import.meta.url === pathToFileURL(process.argv[1] || '').href) {
  if (process.argv.includes('--selftest')) selftest();
  else main();
}
