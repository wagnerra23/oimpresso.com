#!/usr/bin/env node
// hook-replay.mjs — testa hook contra TELEMETRIA REAL (advisory, exit 0 sempre).
//
// ── POR QUE EXISTE ──────────────────────────────────────────────────────────
// O `modulo-preflight-warning` tinha selftest com 26 asserts VERDE — e o meu próprio
// controle-negativo (transcript vazio) TAMBÉM passava — enquanto o hook ficava mudo em
// 116 de 116 pares reais. Os dois testes estavam certos e os dois enganavam: a fixture
// era limpa e inventada. O que quebrava o hook era o transcript REAL — linhas de 37 mil
// chars carregando o system prompt inteiro, onde `<mod>.*charter` casava 6.490 chars
// adiante. A fixture não era pequena demais; era IRREAL.
//
// ── ÂNCORA EXTERNA ──────────────────────────────────────────────────────────
// Continuous control validation / BAS: não se confia que o controle dispararia — injeta-se
// a condição em cadência e verifica-se que ele DE FATO dispara, com telemetria sintética
// mas REALISTA. Aqui a telemetria nem precisa ser sintética: o corpus real está no disco.
//
// ── A REGRA DURA (senão vira tautologia) ────────────────────────────────────
// O ESPERADO vem do CONTRATO e é calculado por um oráculo INDEPENDENTE do critério do
// hook. Se o esperado fosse derivado do que o hook faz, o teste concordaria 100% com
// qualquer implementação, inclusive a quebrada — o erro §5 2026-06-05 (teste tautológico).
//
// Uso: node scripts/governance/hook-replay.mjs [--hook <nome>] [--json] [--selftest]

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { homedir } from 'node:os';
import { join, dirname } from 'node:path';

const EDIT_TOOLS = new Set(['Edit', 'Write', 'MultiEdit']);
// Edit/MultiEdit contam como LEITURA porque o tool EXIGE Read previo do arquivo
// ("You must Read the file in this conversation before editing"). Write NAO conta:
// criar arquivo novo nao implica ter lido briefing nenhum.
// Ajuste feito 2026-07-26 DEPOIS de investigar as 2 divergencias do 1o run: a sessao
// Jana tinha 12 Edit em memory/requisitos/Jana/ e zero Read — o oraculo e' que estava
// estreito, nao o hook. Correcao ancorada no CONTRATO do tool, nao em "bater com o hook".
const READ_TOOLS = new Set(['Read', 'Glob', 'Grep', 'NotebookRead', 'Edit', 'MultiEdit']);

/** extrai da sessão os eventos ESTRUTURADOS (tool_use), não texto. */
export function parseSessao(texto) {
  const edits = [], leituras = [];
  for (const ln of String(texto || '').split('\n')) {
    if (!ln.includes('"tool_use"')) continue;
    let o; try { o = JSON.parse(ln); } catch { continue; }
    for (const b of (o?.message?.content || [])) {
      if (b?.type !== 'tool_use') continue;
      const alvo = String(b.input?.file_path || b.input?.pattern || b.input?.path || '').split('\\').join('/');
      if (!alvo) continue;
      // NAO e' exclusivo: Edit entra nos DOIS (e uma escrita E uma leitura implicita).
      // O `else if` de antes fazia Edit nunca chegar em `leituras` e produzia 2
      // divergencias falsas — bug meu, achado ao investigar o 1o run.
      if (EDIT_TOOLS.has(b.name)) edits.push(alvo);
      if (READ_TOOLS.has(b.name)) leituras.push(alvo);
    }
  }
  return { edits, leituras };
}

// ── CONTRATOS DE REPLAY ─────────────────────────────────────────────────────
// 1 entrada por hook. `esperado` NUNCA chama o hook — é o oráculo independente.
export const CONTRATOS = {
  'modulo-preflight-warning': {
    contrato: 'proibicoes.md REGRA PRIMARIA FASE 1: avisar ao Editar Modules/<X>/ '
      + 'sem ter LIDO o briefing do modulo nesta sessao.',
    oraculo: 'evento estruturado Read/Glob/Grep apontando pra memory/requisitos/<Mod>/, '
      + 'README do modulo ou charter dele — independente do criterio interno do hook.',
    casos({ edits, leituras }) {
      const mods = new Set();
      for (const p of edits) { const m = /\/Modules\/([A-Z][A-Za-z0-9]*)\//.exec(p); if (m) mods.add(m[1]); }
      return [...mods].map((modulo) => {
        const alvo = new RegExp(`(memory/requisitos/${modulo}/|Modules/${modulo}/README|${modulo}[^\\s]*\\.charter\\.md)`, 'i');
        return { modulo, leuBriefing: leituras.some((l) => alvo.test(l)) };
      });
    },
    esperado(caso) { return caso.leuBriefing ? 'cala' : 'avisa'; },
    observado(caso, texto, impl) { return impl(texto, caso.modulo) ? 'cala' : 'avisa'; },
  },
};

/** roda um contrato sobre um corpus. `impl` injetável = o harness é testável. */
export function replay({ contrato, sessoes, impl }) {
  let total = 0, acordo = 0;
  const divergencias = [];
  for (const { nome, texto } of sessoes) {
    const ev = parseSessao(texto);
    for (const caso of contrato.casos(ev)) {
      total++;
      const esp = contrato.esperado(caso);
      const obs = contrato.observado(caso, texto, impl);
      if (esp === obs) acordo++;
      else divergencias.push({ sessao: nome, modulo: caso.modulo, esperado: esp, observado: obs });
    }
  }
  return { total, acordo, divergencias, taxa: total ? acordo / total : null };
}

export function formatar(hook, c, r) {
  const L = ['', `=== hook-replay — ${hook} contra telemetria REAL (advisory) ===`];
  L.push(`  contrato: ${c.contrato}`);
  L.push(`  oraculo:  ${c.oraculo}`);
  L.push('');
  if (!r.total) { L.push('  nenhum caso no corpus — sem gatilho, sem veredito.'); L.push(''); return L.join('\n'); }
  L.push(`  casos reais: ${r.total} · acordo: ${r.acordo} (${(r.taxa * 100).toFixed(1)}%) · divergencias: ${r.divergencias.length}`);
  if (r.divergencias.length) {
    L.push('');
    const porTipo = new Map();
    for (const d of r.divergencias) {
      const k = `esperava ${d.esperado}, hook ${d.observado}`;
      porTipo.set(k, (porTipo.get(k) || 0) + 1);
    }
    for (const [k, n] of porTipo) L.push(`   ${String(n).padStart(4)}  ${k}`);
    L.push('');
    for (const d of r.divergencias.slice(0, 6)) L.push(`        ${d.modulo}  (${d.sessao})`);
    if (r.divergencias.length > 6) L.push(`        ... +${r.divergencias.length - 6}`);
    L.push('');
    L.push('  DIVERGENCIA NAO E' + ' AUTOMATICAMENTE BUG DO HOOK: pode ser o oraculo mais');
    L.push('  estreito que o contrato (ex.: leitura via MCP que o oraculo nao ve). Cada uma');
    L.push('  pede 1 olhada — o valor esta em EXISTIR o confronto, nao em ele ser veredito.');
  }
  L.push('');
  return L.join('\n');
}

function corpusLocal() {
  const base = join(homedir(), '.claude', 'projects');
  if (!existsSync(base)) return [];
  const out = [];
  for (const d of readdirSync(base)) {
    if (!d.startsWith('D--oimpresso-com')) continue;
    let fs2; try { fs2 = readdirSync(join(base, d)); } catch { continue; }
    for (const f of fs2) {
      if (!f.endsWith('.jsonl')) continue;
      try { out.push({ nome: f.slice(0, 8), texto: readFileSync(join(base, d, f), 'utf8') }); } catch { /* ignora */ }
    }
  }
  return out;
}

async function main() {
  const argv = process.argv.slice(2);
  const alvo = (() => { const i = argv.indexOf('--hook'); return i >= 0 ? argv[i + 1] : null; })();
  const sessoes = corpusLocal();
  const saida = {};
  for (const [hook, c] of Object.entries(CONTRATOS)) {
    if (alvo && hook !== alvo) continue;
    const mod = await import(pathToFileURL(join(dirname(fileURLToPath(import.meta.url)), '..', '..', '.claude', 'hooks', hook + '.mjs')).href);
    const r = replay({ contrato: c, sessoes, impl: mod.hasReadEvidence });
    saida[hook] = r;
    if (!argv.includes('--json')) console.log(formatar(hook, c, r));
  }
  if (argv.includes('--json')) console.log(JSON.stringify({ sessoes: sessoes.length, resultado: saida }, null, 2));
  process.exit(0);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const t = join(dirname(fileURLToPath(import.meta.url)), 'hook-replay.test.mjs');
    process.exit(spawnSync(process.execPath, [t], { stdio: 'inherit' }).status ?? 1);
  }
  main();
}
