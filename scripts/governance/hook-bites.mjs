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

import { readdirSync, readFileSync, existsSync, statSync } from 'node:fs';
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
  'mcp-first-nudge': 'oimpresso-mcp-first',
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

function main() {
  const argv = process.argv.slice(2);
  const dias = (() => { const i = argv.indexOf('--dias'); return i >= 0 ? parseInt(argv[i + 1], 10) : 0; })();
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
