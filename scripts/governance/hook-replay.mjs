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
// ── HOOKS AVALIADOS E **REJEITADOS** (medido 2026-08-11 — NÃO re-propor sem refutar) ──
// Escalar de 1 pra N contratos esbarra numa restrição dura: nem todo hook admite oráculo
// independente. Onde ele não existe, o contrato seria tautológico — concordaria 100% com
// qualquer implementação, inclusive a quebrada (§5 2026-06-05). Os 4 abaixo foram MEDIDOS
// contra o corpus real e reprovados; a medição está aqui pra ninguém refazer o trabalho.
//
//  ✗ block-ancora-no-olho — SEM POPULAÇÃO + SEM ORÁCULO.
//    Corpus (374 sessões): 42 Read de imagem, **0 print-semântico**. O ramo que discrimina
//    (bloquear) tem ZERO casos, então o contrato jamais separaria hook vivo de hook morto.
//    E "é print de estado velho?" não tem sinal independente além do nome do arquivo — que
//    é o critério do próprio hook. A metade que TEM dono externo (`ancora.mjs`, âncora
//    declarada por charter) não diz nada sobre esses 42 (nenhum é âncora declarada).
//
//  ✗ block-instrumento-sem-porta-viva — ORÁCULO MEDIDO E REPROVADO.
//    P1 tem 7 casos com tool_result pareado. Oráculo candidato = o RESULTADO (quantas telas
//    distintas voltaram), que É independente da sintaxe do pattern. Medido: **5 de 7
//    divergem, e as 5 são artefato do oráculo** — 3 tiveram resultado VAZIO ("instrumento
//    errado" não depende de a busca ter achado algo) e 2 são `content`/`count`, que o hook
//    passa por decisão DOCUMENTADA e correta. Corrigir exige encodar modo+pattern = o
//    critério do hook. P3 (repo raso) e P4 (rodar as 2 formas do glob) são tautológicos por
//    construção: o critério do hook JÁ É a medição, então o oráculo seria a mesma medição.
//
//  ✗ doc-fora-do-rag — DONO JÁ EXISTE (§5 2026-07-09 "duplica régua consolidada").
//    O que um contrato aqui faria — comparar a allowlist do hook com a do indexador PHP —
//    já é feito por `doc-fora-do-rag.test.mjs` ("fica VERMELHO no dia da divergência"), e o
//    FP já foi medido contra o oráculo de RUNTIME (`mcp_memory_documents`): 0 FP / 0 FN.
//
//  ✗ block-brl-values-in-memory — TAUTOLÓGICO POR IMPORT.
//    O candidato a oráculo independente (`scripts/governance/brl-scan-diff.mjs`, o gate de
//    CI) **importa `scanBrlLeak` do próprio hook** — "FONTE ÚNICA DO PREDICADO: hook e gate
//    NUNCA divergem" (cabeçalho dele). O oráculo seria literalmente a mesma função.
//
//  ✗ block-bom-encoding — O CRITÉRIO **É** O GROUND TRUTH (avaliado 2026-08-11).
//    População farta (4.081 gatilhos em 376 sessões, todos com conteúdo no transcript), mas
//    o critério é `contents[0].charCodeAt(0) === 0xFEFF`: "este conteúdo começa com BOM?" é
//    o FATO, não um proxy dele. Não existe segundo sinal — BOM só se observa olhando os
//    bytes iniciais. Um oráculo faria a mesma pergunta com outro nome.
//    O oráculo de CONSEQUÊNCIA ("entrou BOM no repo?") existe e é útil, mas é GLOBAL, não
//    por-caso: os writes do corpus são de outros worktrees/temp, sem pareamento possível.
//
//  ✗ block-routes-string-legacy — MESMA RAZÃO (avaliado 2026-08-11).
//    59 gatilhos em 23 arquivos de rota. O critério é `findLegacyMatches(content)` — "este
//    conteúdo contém `'Controller@method'`?" é o fato que o contrato proíbe, não um proxy.
//    O oráculo de runtime (`route:list`/`route:cache` no CT 100) responde a CONSEQUÊNCIA do
//    mesmo padrão, e não roda contra conteúdo histórico de transcript.
//
//  ── O PADRÃO, pra não re-testar candidato por candidato ─────────────────────
//  Contrato de replay só existe quando o hook (a) RE-IMPLEMENTA regra com dono canônico
//  noutro lugar — o oráculo é o dono (foi o `mwart` × porta viva); ou (b) decide por PROXY
//  (texto, nome, sintaxe) de algo com ground truth — o oráculo é o ground truth (foi o
//  `preflight`: texto cru × evento estruturado). Quando o critério JÁ É a medição do fato,
//  não há nada acima dele e o contrato seria tautológico. Pra esses, os instrumentos certos
//  já existem e já rodam: `hook-bites` (disparou?) e o `--selftest` do próprio hook (morde
//  em fixture?). Somar contrato ali não mede nada novo.
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

const RAIZ = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

/** roda a PORTA VIVA (screen-coverage-map --screen) em SUBPROCESSO e devolve o runbook por tela.
 *
 * SUBPROCESSO, não import: `scripts/qa/screen-coverage-map.mjs` NÃO tem guard de entrypoint —
 * importá-lo executa o scan agregado no corpo top-level e, com `--check`, chama process.exit(2).
 * Um harness advisory não pode herdar o exit de outro script (§5 2026-08-08, mesmo residual).
 * O subprocesso isola isso e ainda devolve dado ESTRUTURADO (o `--screen` só imprime texto).
 */
export function portaVivaRunbook(telas, run = spawnSync, raiz = RAIZ) {
  if (!telas.length) return {};
  const alvo = pathToFileURL(join(raiz, 'scripts', 'qa', 'screen-coverage-map.mjs')).href;
  const snippet = `import { resolveScreenFiles } from ${JSON.stringify(alvo)};\n`
    + 'const o = {}; for (const s of process.argv.slice(1)) { try { o[s] = resolveScreenFiles(s).runbook; } catch { o[s] = null; } }\n'
    + "process.stdout.write('@@' + JSON.stringify(o) + '@@');";
  try {
    const r = run(process.execPath, ['--input-type=module', '-e', snippet, '--', ...telas],
      { cwd: raiz, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
    const m = /@@([\s\S]*)@@/.exec(String(r?.stdout || ''));
    return m ? JSON.parse(m[1]) : {};
  } catch {
    return {}; // porta viva indisponível → todos indeterminados (nunca "verde por não medir")
  }
}

const PAGE_TSX = /resources\/js\/Pages\/([^/_][^/]*)\/(?:([^/_][^/]*)\/)?([A-Za-z][A-Za-z0-9]*)\.tsx$/;
// a porta viva resolveu ALGUM runbook pra tela? `declared-missing` = charter aponta pra arquivo
// fantasma: o oráculo NÃO sabe se existe outro por nome (resolveArtifact retorna cedo e descarta
// os candidatos), então é INDETERMINADO — excluir é honesto, assumir fabricaria divergência.
const RUNBOOK_EXISTE = new Set(['declared', 'unique', 'ambiguous']);

// ── CONTRATOS DE REPLAY ─────────────────────────────────────────────────────
// 1 entrada por hook. `esperado` NUNCA chama o hook — é o oráculo independente.
// `impl` seleciona o export do hook que o harness injeta como observador.
// `preparar(sessoes)` (opcional) roda 1× e devolve o contexto do oráculo.
export const CONTRATOS = {
  'modulo-preflight-warning': {
    contrato: 'proibicoes.md REGRA PRIMARIA FASE 1: avisar ao Editar Modules/<X>/ '
      + 'sem ter LIDO o briefing do modulo nesta sessao.',
    oraculo: 'evento estruturado Read/Glob/Grep apontando pra memory/requisitos/<Mod>/, '
      + 'README do modulo ou charter dele — independente do criterio interno do hook.',
    impl: (mod) => mod.hasReadEvidence,
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

  'block-mwart-violation': {
    contrato: 'ADR 0104 §F1 PLAN + proibicoes.md §MWART: Edit/Write em '
      + 'Pages/<Mod>/<Tela>.tsx exige o RUNBOOK da tela ANTES de F3 FRONTEND.',
    oraculo: 'a PORTA VIVA `screen-coverage-map.mjs --screen` resolve o RUNBOOK da tela — '
      + 'DONO e IMPLEMENTACAO diferentes do hook (walk RECURSIVO + match por SUBSTRING + so a '
      + 'chave `related_runbook`, contra readdir FLAT + nome kebab EXATO + as duas chaves). '
      + 'Responde a MESMA pergunta do contrato ("existe RUNBOOK desta tela?") por outro caminho.',
    impl: (mod) => mod.decide,
    preparar(sessoes, deps = {}) {
      const telas = new Set();
      for (const { texto } of sessoes) {
        for (const p of parseSessao(texto).edits) {
          const m = PAGE_TSX.exec(p);
          if (m) telas.add(`${m[1]}/${m[2] ? m[2] + '/' : ''}${m[3]}.tsx`);
        }
      }
      return { runbook: (deps.portaViva || portaVivaRunbook)([...telas]) };
    },
    casos({ edits }) {
      const telas = new Set();
      for (const p of edits) {
        const m = PAGE_TSX.exec(p);
        if (m) telas.add(`${m[1]}/${m[2] ? m[2] + '/' : ''}${m[3]}.tsx`);
      }
      return [...telas].map((tela) => ({ modulo: tela, tela }));
    },
    esperado(caso, ctx) {
      const o = (ctx && ctx.runbook) ? ctx.runbook[caso.tela] : null;
      if (!o || !o.status || o.status === 'declared-missing') return null; // indeterminado
      return RUNBOOK_EXISTE.has(o.status) ? 'passa' : 'bloqueia';
    },
    observado(caso, texto, impl, raiz = RAIZ) {
      return impl('Edit', `resources/js/Pages/${caso.tela}`, raiz) ? 'bloqueia' : 'passa';
    },
  },
};

/** roda um contrato sobre um corpus. `impl` e `ctx` injetáveis = o harness é testável.
 *
 * Caso cujo oráculo devolve `null` é INDETERMINADO (o oráculo não sabe responder) e sai da
 * conta — nunca entra como acordo. Contar indeterminado a favor seria a isenção que esvazia o
 * conjunto (§5 2026-08-04); por isso ele é reportado à parte, não engolido. */
export function replay({ contrato, sessoes, impl, ctx = null }) {
  let total = 0, acordo = 0, indeterminados = 0;
  const divergencias = [];
  for (const { nome, texto } of sessoes) {
    const ev = parseSessao(texto);
    for (const caso of contrato.casos(ev)) {
      const esp = contrato.esperado(caso, ctx);
      if (esp === null || esp === undefined) { indeterminados++; continue; }
      total++;
      const obs = contrato.observado(caso, texto, impl);
      if (esp === obs) acordo++;
      else divergencias.push({ sessao: nome, modulo: caso.modulo, esperado: esp, observado: obs });
    }
  }
  return { total, acordo, indeterminados, divergencias, taxa: total ? acordo / total : null };
}

export function formatar(hook, c, r) {
  const L = ['', `=== hook-replay — ${hook} contra telemetria REAL (advisory) ===`];
  L.push(`  contrato: ${c.contrato}`);
  L.push(`  oraculo:  ${c.oraculo}`);
  L.push('');
  const indet = r.indeterminados
    ? ` · indeterminados (oraculo nao sabe, FORA da conta): ${r.indeterminados}` : '';
  if (!r.total) {
    L.push(`  nenhum caso no corpus — sem gatilho, sem veredito.${indet}`);
    L.push('');
    return L.join('\n');
  }
  L.push(`  casos reais: ${r.total} · acordo: ${r.acordo} (${(r.taxa * 100).toFixed(1)}%) · divergencias: ${r.divergencias.length}${indet}`);
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
    const mod = await import(pathToFileURL(join(RAIZ, '.claude', 'hooks', hook + '.mjs')).href);
    const ctx = c.preparar ? c.preparar(sessoes) : null;
    const r = replay({ contrato: c, sessoes, impl: c.impl(mod), ctx });
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
