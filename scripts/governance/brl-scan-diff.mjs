#!/usr/bin/env node
/**
 * brl-scan-diff — varre as LINHAS ADICIONADAS de um PR procurando valor BRL não-redigido.
 *
 * ── POR QUE ESTA CAMADA EXISTE (o hook não bastava, e isso foi MEDIDO) ──────────
 * `.claude/hooks/block-brl-values-in-memory.mjs` defende a mesma regra desde 2026-07-09.
 * Mesmo assim, 4 commits levaram ~22 linhas com R$ para `memory/` depois disso. A causa
 * foi medida e NÃO é indisciplina:
 *   - Bash escreve sem passar por hook (heredoc, tee, sed -i, python -c)
 *   - dos 56 worktrees do repo, 7 não têm `.claude/settings.json` e 1 não registra o hook
 *     → worktree criado de branch anterior a 09/07 simplesmente NÃO TEM a defesa
 *   - a escape valve OIMPRESSO_BRL_OK foi usada 0 vezes (ninguém driblou de propósito)
 * O hook mora no runtime do agente. Este gate mora no CI: independe de worktree, de
 * runtime e de autor — é a única camada que cobre PR de outra pessoa e commit direto.
 * Comparação que motivou: multi-tenant, PII e segredo têm gate required; dinheiro tinha zero.
 *
 * ── DIFF-ONLY POR CONSTRUÇÃO (não acorda o legado) ─────────────────────────────
 * Varre SÓ linhas `+` de `git diff BASE...HEAD`. O passivo (84 tokens em 27 arquivos)
 * fica intocado — tocar legado em massa é o big-bang que morre no CI (§5 2026-07-12).
 *
 * ── FONTE ÚNICA DO PREDICADO ───────────────────────────────────────────────────
 * Importa `scanBrlLeak` do próprio hook. Hook e gate NUNCA divergem: um conserto no
 * predicado (ex.: a exceção de `R$ 0`) vale nos dois de graça.
 *
 * ── ADVISORY DE NASCENÇA (ADR 0271/0275) ───────────────────────────────────────
 * FP medido na janela de 90 dias: 61 linhas em 24 commits; ~40% vazamento genuíno,
 * ~60% vetor de teste do incidente `num_uf` e mock de protótipo — que nenhum regex
 * separa de dinheiro real. FP esperado 55-60% → alto demais para required. Nasce
 * advisory; promoção exige o bite-log da ADR 0336 DR-2 DEPOIS que o allowlist
 * absorver a classe vetor/mock. Sai exit 1 quando acha (vermelho visível e honesto),
 * mas o job não está em branch protection — não bloqueia merge.
 *
 * Allowlist: `.github/brl-scan-allowlist.txt` (uma substring por linha, `#` = comentário).
 * Precedente literal: `.github/pii-scan-allowlist.txt`.
 *
 * Uso:
 *   node scripts/governance/brl-scan-diff.mjs --base <sha>       # varre o diff
 *   node scripts/governance/brl-scan-diff.mjs --stdin            # varre texto (PR body)
 *   node scripts/governance/brl-scan-diff.mjs --selftest
 */

import { execFileSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { scanBrlLeak } from '../../.claude/hooks/block-brl-values-in-memory.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const ALLOWLIST = join(ROOT, '.github', 'brl-scan-allowlist.txt');

/** Substrings que isentam a linha (uma por linha do arquivo; `#` comenta). */
export function carregarAllowlist(path = ALLOWLIST) {
  if (!existsSync(path)) return [];
  return readFileSync(path, 'utf8')
    .split('\n')
    .map((l) => l.trim())
    .filter((l) => l && !l.startsWith('#'));
}

/** Extrai só as linhas ADICIONADAS de um diff unificado, com o arquivo de origem. */
export function linhasAdicionadas(diffText) {
  const out = [];
  let arquivo = null;
  for (const raw of String(diffText || '').split('\n')) {
    if (raw.startsWith('+++ b/')) { arquivo = raw.slice(6); continue; }
    if (raw.startsWith('+++') || raw.startsWith('---')) continue;
    if (!raw.startsWith('+')) continue;
    out.push({ arquivo, linha: raw.slice(1) });
  }
  return out;
}

/** Aplica o predicado do HOOK (fonte única) + allowlist. */
export function acharVazamentos(adicionadas, allow = []) {
  const hits = [];
  for (const { arquivo, linha } of adicionadas) {
    if (allow.some((a) => linha.includes(a))) continue;
    // scanBrlLeak espera texto; passamos a linha isolada. Fence não se aplica a
    // linha solta de diff — e isso é DELIBERADO: um fence aberto noutro hunk não
    // deve virar bypass do gate (o hook já tolera fence no fluxo local).
    if (scanBrlLeak(linha).blocked) hits.push({ arquivo, linha: linha.trim() });
  }
  return hits;
}

/** Mascara o valor — o relatório do CI é público no log do Actions. */
export function mascarar(linha) {
  return String(linha).replace(/R\$\s?\d[\d.,]*/g, 'R$ <valor>');
}

function selftest() {
  const casos = [
    ['acha valor em linha adicionada', '+++ b/memory/x.md\n+custo R$ 1.234,56', 1],
    ['IGNORA linha removida', '+++ b/memory/x.md\n-custo R$ 1.234,56', 0],
    ['IGNORA linha de contexto', '+++ b/memory/x.md\n custo R$ 1.234,56', 0],
    ['IGNORA cabecalho +++', '+++ b/memory/R$ 1.txt\n+ok', 0],
    ['R$ 0 NAO acusa (exceção de zero, herdada do hook)', '+++ b/memory/x.md\n+total R$ 0 hoje', 0],
    ['redacted NAO acusa', '+++ b/memory/x.md\n+valor R$ [redacted Tier 0]', 0],
    ['acha em .yaml tambem', '+++ b/memory/g/x.yaml\n+t: R$ 999,00', 1],
    ['acha FORA de memory/ (gate cobre o repo, nao so memory)', '+++ b/README.md\n+preco R$ 50,00', 1],
    ['multiplas linhas', '+++ b/a.md\n+R$ 1,00\n+ok\n+R$ 2,00', 2],
  ];
  let ok = 0;
  const falhas = [];
  for (const [nome, diff, esperado] of casos) {
    const n = acharVazamentos(linhasAdicionadas(diff), []).length;
    if (n === esperado) ok++;
    else falhas.push(`  x ${nome}: esperado ${esperado}, veio ${n}`);
  }
  // allowlist isenta
  const comAllow = acharVazamentos(linhasAdicionadas('+++ b/a.md\n+fixture R$ 9,99 # brl-allowlist'), ['# brl-allowlist']);
  if (comAllow.length === 0) ok++; else falhas.push('  x allowlist deveria isentar');
  // mascaramento nao vaza
  if (!/1\.234/.test(mascarar('custo R$ 1.234,56'))) ok++;
  else falhas.push('  x mascarar() deixou o valor passar');
  // fonte unica: o predicado vem MESMO do hook
  if (typeof scanBrlLeak === 'function' && scanBrlLeak('R$ 5,00').blocked) ok++;
  else falhas.push('  x predicado do hook nao importado');

  const total = casos.length + 3;
  console.log(`brl-scan-diff selftest: ${ok}/${total}`);
  if (falhas.length) { console.error(falhas.join('\n')); process.exit(1); }
  console.log('  ACHA: linha + com valor, em qualquer extensao, dentro e fora de memory/');
  console.log('  IGNORA: linha removida/contexto, R$ 0, redacted, allowlist');
  process.exit(0);
}

function main() {
  const argv = process.argv.slice(2);
  if (argv.includes('--selftest')) selftest();

  const allow = carregarAllowlist();
  let adicionadas = [];

  if (argv.includes('--stdin')) {
    // PR body / commit subjects — cada linha conta como "adicionada".
    const txt = readFileSync(0, 'utf8');
    adicionadas = txt.split('\n').map((l) => ({ arquivo: '(texto)', linha: l }));
  } else {
    const i = argv.indexOf('--base');
    const base = i >= 0 ? argv[i + 1] : null;
    if (!base) {
      console.error('brl-scan-diff: falta --base <sha> (ou --stdin). Falha visível, não exit 0 silencioso.');
      process.exit(2);
    }
    let diff;
    try {
      diff = execFileSync('git', ['diff', '--unified=0', `${base}...HEAD`], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
    } catch (e) {
      console.error(`brl-scan-diff: git diff falhou (${e.message}). Falha visível.`);
      process.exit(2);
    }
    adicionadas = linhasAdicionadas(diff);
  }

  const hits = acharVazamentos(adicionadas, allow);
  console.log(`brl-scan-diff: ${adicionadas.length} linha(s) adicionada(s) varrida(s); allowlist com ${allow.length} entrada(s).`);

  if (!hits.length) {
    console.log('OK — nenhum valor BRL não-redigido nas linhas novas.');
    process.exit(0);
  }

  console.error(`\n${hits.length} linha(s) com valor BRL não-redigido (valor MASCARADO abaixo):\n`);
  for (const h of hits) console.error(`  ${h.arquivo}: ${mascarar(h.linha)}`);
  console.error(`
REGRA (memory/proibicoes.md, Tier 0): valores monetários NUNCA vão pra git.
Só Wagner/Eliana veem valores; o resto do time vê escopo e contagem, não R$.
Reincidência já custou 'git filter-repo' em 5.033 commits + force-push em main.

COMO RESOLVER:
  - troque pelo sentinela:  R$ [redacted Tier 0]
  - ou comunique fora-banda (chat direto), não no git
  - contagens/escopo (108 subs, 1311 invoices) são OK — o gate só pega R$<número>
  - vetor de teste/fixture legítimo: adicione a substring em .github/brl-scan-allowlist.txt

ADVISORY: este job NÃO bloqueia merge (ADR 0271/0275 — gate novo nasce advisory).
Vermelho aqui é sinal honesto, não trava.`);
  process.exit(1);
}

main();
