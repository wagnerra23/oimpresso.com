#!/usr/bin/env node
// hook-bites.mjs — DEAD MAN'S SWITCH dos hooks de runtime (advisory, exit 0 sempre).
//
// ── O BURACO QUE ISTO FECHA ─────────────────────────────────────────────────
// `gate-selftest` e os `*.test.mjs` provam que a defesa morde NA FIXTURE. Não provam
// que ela mordeu NO MUNDO. Caso real medido 2026-07-26: `modulo-preflight-warning`
// tinha selftest com 26 asserts VERDE (um deles afirmando o próprio bug) enquanto
// ficava silencioso em 116 de 116 pares (sessão, módulo) — 87 por casamento acidental.
// Descoberto por acaso numa conversa, não por aviso.
//
// ── ÂNCORA EXTERNA ──────────────────────────────────────────────────────────
// Padrão canônico de monitoração: dead man's switch / heartbeat — "a ausência de erro
// não é a presença de sucesso"; o alvo é "um processo VIVO que parou de fazer o que
// deveria". Mesmo eixo que o `cron-watchdog` já aplica a cron (liveness × entrega),
// agora no eixo runtime-hook, que nenhuma camada existente alcança.
//
// ── RESTRIÇÃO ESTRUTURAL (medida, não suposta) ──────────────────────────────
// O oráculo é o transcript LOCAL (~735MB em ~/.claude/projects), fora do repo, fora do
// CT 100, invisível pro CI. Por isso nenhum dos 34 required podia pegar isto.
// E o transcript só registra hook que FALOU: razão hook_success/chamada medida em
// 2026-07-26 = Edit 0,187 · Write 0,268 · Bash 0,023 (≪1). Silêncio não deixa rastro
// → LIVENESS por script NÃO é derivável; só ENTREGA é. Este script mede entrega.
//
// Uso:  node scripts/governance/hook-bites.mjs [--dias N] [--json] [--selftest]

import { readdirSync, readFileSync, existsSync, statSync, writeFileSync, mkdirSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { homedir } from 'node:os';
import { join, dirname } from 'node:path';

const RAIZ = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const DIR_HOOKS = join(RAIZ, '.claude', 'hooks');
const SETTINGS = join(RAIZ, '.claude', 'settings.json');

// Tags cujo nome DIVERGE do arquivo. Não é lista solta: cada par é VERIFICADO
// (o arquivo tem que conter a tag) — se alguém renomear, `--check-aliases` acusa.
// Hook NOVO não entra aqui: a convenção é usar `[<nome-do-arquivo>]`.
export const ALIASES = {
  'charter-validate': 'charter-first',
  'block-mwart-violation': 'mwart-process',
  'commit-discipline-check': 'commit-discipline',
  'post-merge-ui-smoke-required': 'ui-smoke-required',
  'preflight-new-capability': 'oimpresso-anti-reinvencao',
  // REMOVIDO 2026-08-05: `mcp-first-nudge` -> `oimpresso-mcp-first`. O arquivo NAO EXISTE
  // (o `mcp-first-warning` foi aposentado no #4587, 2026-07-20) — alias apontando pra
  // fantasma. Foi o `--check-aliases`, no seu primeiro uso, que achou. As 36 emissoes
  // historicas de `[oimpresso-mcp-first]` seguem no corpus e continuam listadas como
  // ORFAS — que e' o comportamento certo: o relatorio ja trata "tag de hook aposentado
  // que segue emitindo" como sinal util, nao como erro a silenciar.
  // `memory-schema-guard` JÁ emitia `[memory-schema]` em produção — a tag existe e é
  // distintiva, só não estava registrada aqui. Registrar o ALIAS (em vez de trocar a
  // mensagem) o torna observável RETROATIVAMENTE: as emissões históricas passam a
  // contar. Trocar a string zeraria esse rastro. Medido 2026-08-05.
  'memory-schema-guard': 'memory-schema',
  // idem: `php-syntax-after-write` já emite `[php-syntax]`. Alias preserva o histórico.
  'php-syntax-after-write': 'php-syntax',
  // Leva dos condicionais (2026-08-05). Estes 2 também já emitiam tag própria e só
  // faltava registrá-la — mesma forma (a) do #5314, mesmo ganho retroativo.
  // `tema-owner` tem rastro MEDIDO: 8 emissões no corpus, listadas como órfãs.
  'tema-owner-advisory': 'tema-owner',
  // `charter-da-tela` tem 0 no corpus, e o motivo é dispararem POUCO, não canal morto:
  // é PreToolUse:Read (32 attachments no corpus inteiro) com condição estreita. O canal
  // (stderr) é comprovadamente contável — block-destructive, stderr puro, tem 279.
  'charter-da-tela-que-o-controller-serve': 'charter-da-tela',
};

/** hooks wired no settings.json → [{arquivo, evento, matcher}] */
export function hooksWired(settingsJson) {
  const out = [];
  for (const [evento, grupos] of Object.entries(settingsJson?.hooks || {})) {
    for (const g of grupos || []) {
      for (const h of g.hooks || []) {
        const m = /hooks[\\/]([\w.-]+)\.mjs/.exec(String(h.command || ''));
        if (m) out.push({ arquivo: m[1], evento, matcher: g.matcher || '*' });
      }
    }
  }
  return out;
}

/** a tag de um hook: alias declarado (se o arquivo o contiver) ou o próprio nome. */
export function tagDe(arquivo, dirHooks = DIR_HOOKS) {
  const alvo = ALIASES[arquivo];
  const src = (() => { try { return readFileSync(join(dirHooks, arquivo + '.mjs'), 'utf8'); } catch { return ''; } })();
  if (alvo && src.includes('[' + alvo + ']')) return { tag: alvo, origem: 'alias' };
  if (src.includes('[' + arquivo + ']')) return { tag: arquivo, origem: 'convencao' };
  return { tag: null, origem: 'NAO-OBSERVAVEL' };
}

/** as 3 formas em que uma emissão aparece no transcript cru (validadas por controle). */
export function sondas(tag) {
  return [
    '{\\"systemMessage\\":\\"[' + tag + ']',
    'permissionDecisionReason\\":\\"[' + tag + ']',
    '"content":"[' + tag + ']',
  ];
}

/** conta ocorrências literais de cada sonda no texto (sem regex — evita escape frágil). */
export function contarNoTexto(texto, tag) {
  let n = 0;
  for (const s of sondas(tag)) {
    let i = 0;
    for (;;) { const p = texto.indexOf(s, i); if (p < 0) break; n++; i = p + s.length; }
  }
  return n;
}

function arquivosTranscript(dias) {
  const base = join(homedir(), '.claude', 'projects');
  if (!existsSync(base)) return [];
  const corte = dias ? Date.now() - dias * 86400000 : 0;
  const out = [];
  for (const d of readdirSync(base)) {
    if (!d.startsWith('D--oimpresso-com')) continue;
    let fs2; try { fs2 = readdirSync(join(base, d)); } catch { continue; }
    for (const f of fs2) {
      if (!f.endsWith('.jsonl')) continue;
      const p = join(base, d, f);
      try { if (statSync(p).mtimeMs >= corte) out.push(p); } catch { /* ignora */ }
    }
  }
  return out;
}

/** tags que EMITIRAM no corpus mas não pertencem a nenhum hook wired.
 *  O ponto cego INVERSO: hook desligado do settings que segue emitindo, script
 *  se passando por hook, ou tag de hook renomeado. Ruído conhecido do corpus
 *  (saída de script, não de hook) fica fora por lista explícita — e ela é
 *  pequena e verificável, não um filtro semântico. */
export const RUIDO = new Set(['ok', 'fail', 'pass', 'warn', 'erro', 'info', 'skip', 'done']);
export function tagsOrfas(texto, conhecidas) {
  const achadas = new Map();
  for (const marca of ['{\\"systemMessage\\":\\"[', 'permissionDecisionReason\\":\\"[', '"content":"[']) {
    let i = 0;
    for (;;) {
      const p = texto.indexOf(marca, i); if (p < 0) break;
      i = p + marca.length;
      const fim = texto.indexOf(']', i);
      if (fim < 0 || fim - i > 40) continue;
      const tag = texto.slice(i, fim);
      if (!/^[a-z][a-z0-9._-]{2,}$/.test(tag)) continue;   // descarta [OK], [1], [poll 1]
      if (RUIDO.has(tag) || conhecidas.has(tag)) continue;
      achadas.set(tag, (achadas.get(tag) || 0) + 1);
    }
  }
  return achadas;
}

export function relatorio({ wired, contagem, naoObservaveis, sessoes, segundos, orfas }) {
  const L = [];
  L.push('');
  L.push('=== hook-bites — a defesa mordeu NO MUNDO? (advisory · dead man\'s switch) ===');
  L.push(`  corpus: ${sessoes} sessoes locais · ${segundos}s`);
  const obs = wired.filter((h) => h.tag);
  const zero = obs.filter((h) => (contagem.get(h.tag) || 0) === 0);
  L.push(`  hooks wired: ${wired.length} · observaveis: ${obs.length} · sem tag: ${naoObservaveis.length}`);
  L.push('');
  for (const h of obs.slice().sort((a, b) => (contagem.get(b.tag) || 0) - (contagem.get(a.tag) || 0))) {
    const n = contagem.get(h.tag) || 0;
    L.push(`   ${String(n).padStart(5)}  ${h.arquivo}${h.tag !== h.arquivo ? ` [${h.tag}]` : ''}`);
  }
  if (zero.length) {
    L.push('');
    L.push(`  [!] ${zero.length} hook(s) wired com ZERO entrega na janela — OLHAR, nao e' falha:`);
    for (const h of zero) L.push(`        ${h.arquivo}  (${h.evento} ${h.matcher})`);
    L.push('        zero pode ser (a) condicao nunca satisfeita — legitimo, ex.: ninguem usou Figma');
    L.push('        ou (b) o hook nao morde mais. Distinguir exige bite-test com payload real.');
  }
  if (naoObservaveis.length) {
    L.push('');
    L.push(`  [?] ${naoObservaveis.length} hook(s) NAO-OBSERVAVEIS (mensagem sem [tag]) — silencio deles e' indistinguivel de morte:`);
    L.push('        ' + naoObservaveis.join(', '));
    L.push('        convencao: a mensagem comeca com [<nome-do-arquivo>]. Forward-only —');
    L.push('        hook novo ja nasce assim; legado entra quando for tocado por trabalho real.');
  }
  if (orfas && orfas.size) {
    L.push('');
    L.push(`  [~] ${orfas.size} tag(s) ORFA(S) — emitiram mas nao pertencem a hook wired:`);
    for (const [t, n] of [...orfas].sort((a, b) => b[1] - a[1]).slice(0, 8)) L.push(`        ${String(n).padStart(4)}  [${t}]`);
    L.push('        FP CONHECIDO E NAO-SEPARAVEL: a sonda `"content":"[` casa tanto saida de');
    L.push('        hook quanto RESULTADO de Bash — script de governanca que imprime [tag]');
    L.push('        (deadlink-gate, agent-cost-per-pr, catalog-graph...) cai aqui e NAO e hook.');
    L.push('        Sinal util: tag de hook APOSENTADO que segue emitindo (ex.: mcp-first-warning,');
    L.push('        removido 2026-07-20, deixou 86 emissoes historicas). Cada uma pede 1 olhada.');
  }
  L.push('');
  return L.join('\n');
}

/**
 * `--check-aliases`: confere que cada par de ALIASES ainda casa a realidade — o arquivo
 * existe E contém `[<alvo>]`. Alias que deixou de casar NÃO dá erro: o hook só volta,
 * calado, pra lista de não-observáveis. Ou seja, a garantia mais forte do mecanismo
 * (observabilidade retroativa) dependia de alguém reparar num item a mais numa lista.
 *
 * O cabeçalho dos ALIASES prometia este modo desde que nasceu; ele não existia. Fica
 * como lembrete de que anúncio sem teste de contrato apodrece calado (§5 2026-07-27).
 *
 * Retorna { ok, quebrados[] } — quem chama decide o exit code.
 */
export function checarAliases(dirHooks = DIR_HOOKS) {
  const quebrados = [];
  for (const [arquivo, alvo] of Object.entries(ALIASES)) {
    const p = join(dirHooks, arquivo + '.mjs');
    if (!existsSync(p)) { quebrados.push({ arquivo, alvo, motivo: 'arquivo nao existe' }); continue; }
    let src = ''; try { src = readFileSync(p, 'utf8'); } catch { /* ilegivel = quebrado */ }
    if (!src.includes('[' + alvo + ']')) quebrados.push({ arquivo, alvo, motivo: `nao emite mais [${alvo}]` });
  }
  return { ok: quebrados.length === 0, quebrados };
}

/**
 * Throttle do --heartbeat. Custo MEDIDO da análise: 1,9s (7d) / 3,2s (14d) sobre
 * ~735MB de transcript. Barato pra 1×/dia, caro pra toda sessão — e o SessionStart
 * já carrega 7 hooks. Estado em `.claude/run/` (gitignored, por-dev). Fail-open:
 * qualquer erro de I/O deixa passar (prefere rodar de novo a ficar mudo).
 */
function heartbeatJaRodou(root, horas) {
  const marca = join(root, '.claude', 'run', '.last-hook-bites');
  try {
    if (existsSync(marca)) {
      const idadeH = (Date.now() - statSync(marca).mtimeMs) / 36e5;
      if (idadeH < horas) return true;
    }
    mkdirSync(join(root, '.claude', 'run'), { recursive: true });
    writeFileSync(marca, new Date().toISOString());
  } catch { /* fail-open */ }
  return false;
}

function main() {
  const argv = process.argv.slice(2);
  if (argv.includes('--check-aliases')) {
    const { ok, quebrados } = checarAliases();
    if (ok) {
      console.log(`[hook-bites] --check-aliases OK — ${Object.keys(ALIASES).length}/${Object.keys(ALIASES).length} pares casam a realidade.`);
      process.exit(0);
    }
    console.error(`[hook-bites] --check-aliases: ${quebrados.length} alias(es) quebrado(s) — o hook volta a NAO-OBSERVAVEL calado:`);
    for (const q of quebrados) console.error(`   ${q.arquivo} -> [${q.alvo}]  (${q.motivo})`);
    process.exit(1);
  }
  const dias = (() => { const i = argv.indexOf('--dias'); return i >= 0 ? parseInt(argv[i + 1], 10) : 0; })();
  if (argv.includes('--heartbeat')) {
    const i = argv.indexOf('--throttle-horas');
    const horas = i >= 0 ? parseInt(argv[i + 1], 10) : 20;
    if (heartbeatJaRodou(RAIZ, horas)) process.exit(0);        // silencioso: já falou hoje
  }
  const t0 = Date.now();
  let settings = {};
  try { settings = JSON.parse(readFileSync(SETTINGS, 'utf8')); } catch { /* fail-open */ }
  const vistos = new Set();
  const wired = [];
  const naoObservaveis = [];
  for (const h of hooksWired(settings)) {
    if (vistos.has(h.arquivo)) continue;
    vistos.add(h.arquivo);
    const { tag } = tagDe(h.arquivo);
    if (!tag) naoObservaveis.push(h.arquivo);
    wired.push({ ...h, tag });
  }
  const arquivos = arquivosTranscript(dias);
  const contagem = new Map();
  const conhecidas = new Set(wired.map((h) => h.tag).filter(Boolean));
  const orfas = new Map();
  for (const f of arquivos) {
    let txt; try { txt = readFileSync(f, 'utf8'); } catch { continue; }
    for (const h of wired) {
      if (!h.tag) continue;
      const n = contarNoTexto(txt, h.tag);
      if (n) contagem.set(h.tag, (contagem.get(h.tag) || 0) + n);
    }
    for (const [t, n] of tagsOrfas(txt, conhecidas)) orfas.set(t, (orfas.get(t) || 0) + n);
  }
  const segundos = ((Date.now() - t0) / 1000).toFixed(1);
  if (argv.includes('--heartbeat')) {
    // Resumo de 4 linhas pro SessionStart. Existe porque a análise completa é boa e
    // NINGUÉM a invocava: o dead man's switch estava, ele próprio, órfão (medido em
    // 2026-07-27 e ainda em 2026-08-05). Sem invocador, "não sei se está funcionando"
    // vira o estado permanente — que é exatamente a queixa que isto responde.
    const semEntrega = wired.filter((h) => h.tag && !(contagem.get(h.tag) || 0));
    const comEntrega = wired.filter((h) => h.tag && (contagem.get(h.tag) || 0));
    const curto = (a) => a.replace(/\.mjs$/, '');
    console.log(`\n=== MAQUINAS: os hooks entregaram? (hook-bites · janela ${dias || 'toda'}d · ${segundos}s) ===`);
    console.log(`  ${comEntrega.length} entregaram · ${semEntrega.length} wired com ZERO entrega · ${naoObservaveis.length} nao-observaveis (silencio = indistinguivel de morte)`);
    if (semEntrega.length) {
      console.log(`  zero entrega: ${semEntrega.slice(0, 6).map((h) => curto(h.arquivo)).join(', ')}${semEntrega.length > 6 ? ` (+${semEntrega.length - 6})` : ''}`);
    }
    console.log(`  detalhe: node scripts/governance/hook-bites.mjs --dias ${dias || 14}\n`);
    process.exit(0);
  }
  if (argv.includes('--json')) {
    console.log(JSON.stringify({
      sessoes: arquivos.length, segundos: Number(segundos),
      hooks: wired.map((h) => ({ ...h, entregas: h.tag ? (contagem.get(h.tag) || 0) : null })),
      nao_observaveis: naoObservaveis, orfas: [...orfas],
    }, null, 2));
  } else {
    console.log(relatorio({ wired, contagem, naoObservaveis, orfas, sessoes: arquivos.length, segundos }));
  }
  process.exit(0);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const t = join(dirname(fileURLToPath(import.meta.url)), 'hook-bites.test.mjs');
    process.exit(spawnSync(process.execPath, [t], { stdio: 'inherit' }).status ?? 1);
  }
  main();
}
