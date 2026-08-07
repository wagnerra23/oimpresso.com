#!/usr/bin/env node
// @ts-check
/**
 * ancora-codigo-sync.mjs — AUTO-SYNC da âncora doc→CÓDIGO (o mecanismo do Swimm, traduzido).
 *
 * ## O que o Swimm faz, e o que dele vale AQUI (LC-09 — traduzir premissa, não copiar solução)
 *
 * O Swimm acopla a doc a trechos de código, detecta quando o trecho muda e **regrava a doc**.
 * Três das quatro pernas traduzem; a terceira NÃO:
 *
 *   | perna                    | premissa do Swimm            | vale aqui? |
 *   |--------------------------|------------------------------|------------|
 *   | doc aponta pro código    | ref é endereço real          | SIM        |
 *   | detectar que o alvo moveu| código muda, doc não sabe    | SIM        |
 *   | REGRAVAR a doc           | doc é PROSA explicativa      | **NÃO**    |
 *   | mostrar no IDE           | dev lê no editor             | fora daqui |
 *
 * A terceira não traduz porque `<Tela>.casos.md` **não é prosa** — é CONTRATO que julga o
 * código (UC + teste que o cita, G-2 required). Máquina que reescreve a asserção pra casar
 * com o código produz contrato TAUTOLÓGICO: passa mesmo quando o comportamento está errado.
 * É o erro já catalogado em `memory/proibicoes.md` §5 2026-06-05 ("teste que deriva do
 * CÓDIGO") e a razão de o UC derivar do SDD/CU, nunca do `.tsx`.
 *
 * **Logo este script só mexe no PONTEIRO, nunca na AFIRMAÇÃO.** Ele reescreve `arquivo:443`
 * para `arquivo:517` quando o trecho ancorado migrou de linha — e não toca em nenhuma outra
 * palavra do documento. Auto-sync de endereço é conserto; auto-sync de asserção é anistia.
 *
 * ## O git é o token store (não precisamos inventar um)
 *
 * O Swimm guarda um "smart token" do trecho. Nós já temos algo melhor e versionado: o sha.
 * Uma ref carimbada `Arquivo.php:443 (verificado@a1b2c3d)` diz onde o trecho estava E em que
 * estado do mundo — então o drift se resolve por comparação, não por heurística:
 *
 *   linha 443 em <sha>  ==  linha 443 hoje   →  OK
 *   difere, e o texto antigo aparece 1× hoje →  MOVEU (propõe a nova linha)
 *   difere, e não aparece                    →  PERDIDO (humano decide)
 *   difere, e aparece 2+ vezes               →  AMBIGUO (não escreve — nunca chuta)
 *
 * ## FP zero na ESCRITA, por construção
 *
 * `--sync` só reescreve no caso MOVEU, e MOVEU exige ocorrência **única** do texto original.
 * AMBIGUO e PERDIDO nunca são escritos — viram relatório pro humano. Mesma disciplina de
 * duas pernas do `block-instrumento-sem-porta-viva` (P4): a segunda perna é MEDIÇÃO, não
 * heurística, então quem não diverge passa calado.
 *
 * ## Fronteira com quem já existe (não duplica régua — §5 2026-07-09)
 *
 *   - `sdd-output-lint` C1  → DETECTA ref `arquivo:NNN` **sem sha**. Mede a ausência do carimbo.
 *   - `doc-auto-relink`     → cura link-rot **doc↔doc** (o doc moveu, conserta quem apontava).
 *   - **este**              → cura ref-rot **doc↔código** (o CÓDIGO moveu, conserta a âncora).
 *
 * Nenhum check aqui responde pergunta que os outros dois já respondem.
 *
 * Uso:
 *   node scripts/governance/ancora-codigo-sync.mjs --measure   # censo do corpus (roda isto primeiro)
 *   node scripts/governance/ancora-codigo-sync.mjs --check     # drift das refs carimbadas
 *   node scripts/governance/ancora-codigo-sync.mjs --check --require-stamp --doc <arquivo.md>
 *                                                            # recibo estrito, escopado ao doc
 *   node scripts/governance/ancora-codigo-sync.mjs --stamp     # carimba verificado@HEAD nas sem sha
 *   node scripts/governance/ancora-codigo-sync.mjs --sync      # reescreve SÓ as MOVEU
 *   node scripts/governance/ancora-codigo-sync.mjs --selftest  # bite-test
 */

import { readFileSync, writeFileSync, existsSync, readdirSync, statSync, mkdirSync, rmSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join, relative, basename, resolve } from 'node:path';
import { tmpdir } from 'node:os';

const args = process.argv.slice(2);
const rootFlag = args.indexOf('--root');
const ROOT = rootFlag >= 0 ? args[rootFlag + 1] : process.cwd();
const REQUIRE_STAMP = args.includes('--require-stamp');
const DOC_SCOPE_REQUESTED = args.includes('--doc');

const explicitDocs = args.flatMap((arg, index) => {
  if (arg !== '--doc' || !args[index + 1]) return [];
  const rel = relative(resolve(ROOT), resolve(ROOT, args[index + 1])).split('\\').join('/');
  return rel.startsWith('../') || rel === '..' ? [] : [rel];
});

const MODE = args.includes('--selftest') ? 'selftest'
  : args.includes('--sync') ? 'sync'
  : args.includes('--stamp') ? 'stamp'
  : args.includes('--check') ? 'check'
  : 'measure';

/** Docs que carregam âncora pra código. */
const CORPUS_DIRS = ['resources/js/Pages', 'memory/requisitos'];
const CORPUS_RE = /\.(casos|charter)\.md$/;

/**
 * Docs que o `--stamp` NÃO toca, e o motivo de cada um.
 *
 * Carimbar re-emite a linha inteira no diff. Num arquivo que carrega dívida
 * PRÉ-EXISTENTE, isso acorda gate diff-aware sobre dívida que não é deste PR e que
 * o agente não pode pagar sozinho (§5 2026-07-12 + emenda 2026-07-27). Já custou
 * dois vermelhos: recuei à mão no #5159 e o `--stamp` seguinte pegou os MESMOS dois
 * arquivos de novo — porque o recuo era estado de branch, não regra. Agora é regra.
 *
 * Sai desta lista quando a dívida for paga por quem pode: redigir o valor / declarar
 * a US é decisão [W], não deste script.
 */
const NAO_CARIMBAR = new Map([
  ['resources/js/Pages/Produto/SellingPrices.charter.md',
   'BRL scan: 14 ocorrências de R$ pré-existentes (valores reais) — redigir é decisão [W]'],
  ['resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md',
   'charter-us-lint: sem `related_us` — só [W] sabe qual US a tela atende'],
]);

/** `Arquivo.php:443` ou `path/Arquivo.tsx:12-20`, com carimbo opcional logo depois. */
const RE_REF = /\b([\w./-]+\.(?:php|tsx?|jsx?|mjs|blade\.php)):(\d+)(?:-(\d+))?\b(\s*\(verificado@([0-9a-f]{7,40})\))?/g;

const git = (...a) => {
  try {
    return execFileSync('git', ['-c', `safe.directory=${resolve(ROOT)}`, '-C', ROOT, ...a], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
  } catch { return null; }
};

const walk = (dir, acc = []) => {
  const full = join(ROOT, dir);
  if (!existsSync(full)) return acc;
  for (const e of readdirSync(full)) {
    const p = join(full, e);
    let st; try { st = statSync(p); } catch { continue; }
    if (st.isDirectory()) walk(relative(ROOT, p), acc);
    else if (CORPUS_RE.test(e)) acc.push(relative(ROOT, p).split('\\').join('/'));
  }
  return acc;
};

/**
 * Resolve o alvo da ref num path real do repo. A ref costuma vir curta
 * (`ProductController.php:443`), então cai no basename — e basename ambíguo
 * é recusado, nunca chutado.
 */
const tracked = (() => {
  let cache = null;
  return () => (cache ??= (git('ls-files') || '').split('\n').filter(Boolean));
})();

function resolveAlvo(ref, doc, linha) {
  const direto = join(ROOT, ref);
  if (ref.includes('/') && existsSync(direto)) return { path: ref };
  const base = basename(ref);
  const hits = tracked().filter((f) => f.endsWith('/' + base) || f === base);
  if (hits.length === 1) return { path: hits[0] };
  if (hits.length === 0) return { erro: 'ALVO_INEXISTENTE' };

  // sufixo completo bate em exatamente um: resolvido sem heurística
  const exato = hits.filter((f) => f.endsWith('/' + ref) || f === ref);
  if (exato.length === 1) return { path: exato[0] };

  // IRMÃO COLOCADO — a mais forte, e é consequência direta da Opção B: o trio mora ao
  // lado do `.tsx`, então `Index.tsx:422` citado de `Pages/X/Y/Index.charter.md` é o
  // Index daquele MESMO diretório. Não é heurística: é o layout do repositório.
  const dirDoc = doc?.replace(/\/[^/]+$/, '');
  if (dirDoc) {
    const irmao = hits.find((f) => f === `${dirDoc}/${ref}`);
    if (irmao) return { path: irmao, via: 'irmao' };

    // RELATIVO ao diretório do doc e ao pai dele — cobre `Unificado/Index.tsx` citado
    // de `Pages/Financeiro/Dre/`, que é irmão de pasta dentro do mesmo módulo.
    const pai = dirDoc.replace(/\/[^/]+$/, '');
    for (const base of [dirDoc, pai]) {
      const rel = hits.filter((f) => f === `${base}/${ref}`);
      if (rel.length === 1) return { path: rel[0], via: 'relativo' };
    }
  }

  // Desambiguação por PROXIMIDADE de módulo — não é chute: `Index.tsx:422` citado de
  // `Pages/Produto/Edit.casos.md` só pode ser o Index do Produto. O módulo é o segmento
  // que o doc e o alvo compartilham. Se mais de um candidato empatar, RECUSA.
  const mod = doc?.match(/^resources\/js\/Pages\/([^/]+)\//)?.[1]
    ?? doc?.match(/^memory\/requisitos\/([^/]+)\//)?.[1];
  if (mod) {
    const perto = hits.filter((f) => new RegExp(`(^|/)${mod}(/|$)`, 'i').test(f));
    if (perto.length === 1) return { path: perto[0], via: 'modulo' };
  }

  // ÚLTIMA PERNA, e é MEDIÇÃO, não palpite: a ref cita uma linha. Um candidato que não
  // chega nessa linha não pode ser o alvo. Se sobrar exatamente um que a contém, o
  // próprio número desambigua — e continua recusando quando sobram dois.
  if (linha != null) {
    const cabe = hits.filter((f) => {
      try { return readFileSync(join(ROOT, f), 'utf8').split('\n').length >= linha; } catch { return false; }
    });
    if (cabe.length === 1) return { path: cabe[0], via: 'linha' };
  }
  return { erro: 'ALVO_AMBIGUO', n: hits.length };
}

const linhaDe = (txt, n) => {
  const ls = txt.split('\n');
  return n >= 1 && n <= ls.length ? ls[n - 1] : null;
};
const norm = (s) => (s ?? '').replace(/\s+/g, ' ').trim();

/** Classifica UMA ref. É aqui que o drift vira veredito. */
function classificar(ref, linha, sha, doc) {
  const alvo = resolveAlvo(ref, doc, linha);
  if (alvo.erro) return { estado: alvo.erro };

  const atualTxt = existsSync(join(ROOT, alvo.path)) ? readFileSync(join(ROOT, alvo.path), 'utf8') : null;
  if (atualTxt === null) return { estado: 'ALVO_INEXISTENTE', path: alvo.path };

  const total = atualTxt.split('\n').length;
  if (!sha) {
    // sem carimbo não há como comparar — só dá pra dizer se a linha sequer existe hoje.
    return { estado: linha > total ? 'FORA_DO_ARQUIVO' : 'SEM_CARIMBO', path: alvo.path, total };
  }

  const antigoTxt = git('show', `${sha}:${alvo.path}`);
  if (antigoTxt === null) return { estado: 'SHA_ILEGIVEL', path: alvo.path };

  // `null` = a linha NÃO existe naquele sha. String vazia = existe e está em branco —
  // são coisas diferentes, e confundi-las inventa drift onde não há (medido: 3 FP no
  // corpus real, todas refs apontando pra linha em branco legítima).
  const brutaAntiga = linhaDe(antigoTxt, linha);
  if (brutaAntiga === null) return { estado: 'CARIMBO_FORA_DO_ARQUIVO', path: alvo.path };
  const antiga = norm(brutaAntiga);

  if (norm(linhaDe(atualTxt, linha)) === antiga) return { estado: 'OK', path: alvo.path };

  // Âncora em linha vazia não tem conteúdo pra rastrear — o endereço é tudo o que há.
  // Reportar como fraca em vez de fingir que dá pra re-ancorar.
  if (antiga === '') return { estado: 'ANCORA_VAZIA', path: alvo.path };

  const ocorr = atualTxt.split('\n')
    .map((l, i) => (norm(l) === antiga ? i + 1 : 0))
    .filter(Boolean);

  if (ocorr.length === 1) return { estado: 'MOVEU', path: alvo.path, de: linha, para: ocorr[0], trecho: antiga };
  if (ocorr.length === 0) return { estado: 'PERDIDO', path: alvo.path, trecho: antiga };
  return { estado: 'AMBIGUO', path: alvo.path, n: ocorr.length, trecho: antiga };
}

function varrer() {
  // Sem --doc, preserva exatamente o corpus historico. Com --doc, aceita qualquer
  // Markdown explicitamente nomeado: o chamador (ex.: sdd-flow) ja delimitou a cadeia.
  const docs = DOC_SCOPE_REQUESTED
    ? [...new Set(explicitDocs)].filter((doc) => /\.md$/i.test(doc) && existsSync(join(ROOT, doc)))
    : CORPUS_DIRS.flatMap((d) => walk(d));
  const refs = [];
  for (const doc of docs) {
    const txt = readFileSync(join(ROOT, doc), 'utf8');
    for (const m of txt.matchAll(RE_REF)) {
      refs.push({ doc, bruto: m[0], ref: m[1], linha: Number(m[2]), fim: m[3] ? Number(m[3]) : null, sha: m[5] || null, idx: m.index });
    }
  }
  return { docs, refs };
}

const ICONE = {
  OK: '  ok    ', MOVEU: '  MOVEU ', PERDIDO: '  PERDI ', AMBIGUO: '  ambig ',
  SEM_CARIMBO: '  s/sha ', FORA_DO_ARQUIVO: '  FORA  ', ALVO_INEXISTENTE: '  s/alvo', ANCORA_VAZIA: '  vazia ',
  ALVO_AMBIGUO: '  ambig ', SHA_ILEGIVEL: '  sha?  ', CARIMBO_FORA_DO_ARQUIVO: '  FORA  ',
};

function relatorio(rows) {
  const por = {};
  rows.forEach((r) => { por[r.estado] = (por[r.estado] || 0) + 1; });
  return por;
}

function run() {
  const { docs, refs } = varrer();
  const rows = refs.map((r) => ({ ...r, ...classificar(r.ref, r.linha, r.sha, r.doc) }));
  const por = relatorio(rows);

  console.log(`[ancora-codigo-sync] ${docs.length} doc(s) do corpus · ${refs.length} ref(s) doc→código`);
  console.log('  ' + (Object.entries(por).map(([k, v]) => `${k}=${v}`).join(' · ') || '(nenhuma ref)'));

  if (MODE === 'measure') {
    for (const r of rows) {
      const extra = r.estado === 'MOVEU' ? ` ${r.de}→${r.para}`
        : r.estado === 'AMBIGUO' ? ` (${r.n}x)`
        : r.estado === 'FORA_DO_ARQUIVO' ? ` (linha ${r.linha} > ${r.total})` : '';
      console.log(`${ICONE[r.estado] || '  ?     '} ${r.doc}  →  ${r.ref}:${r.linha}${extra}`);
    }
    const semCarimbo = por.SEM_CARIMBO || 0;
    if (semCarimbo) {
      console.log(`\n  ${semCarimbo} ref(s) SEM carimbo — o drift delas é indetectável até rodar --stamp.`);
      console.log('  (o C1 do sdd-output-lint já mede essa ausência; aqui ela vira acionável)');
    }
    return 0;
  }

  if (MODE === 'check') {
    const ruins = rows.filter((r) => REQUIRE_STAMP
      ? r.estado !== 'OK'
      : ['MOVEU', 'PERDIDO', 'FORA_DO_ARQUIVO', 'CARIMBO_FORA_DO_ARQUIVO'].includes(r.estado));
    ruins.forEach((r) => console.log(`  ${r.estado}  ${r.doc}  →  ${r.ref}:${r.linha}` + (r.estado === 'MOVEU' ? ` (agora ${r.para}) — rode --sync` : '')));
    if (!ruins.length) console.log(REQUIRE_STAMP
      ? '  todas as refs possuem smart token Git SHA valido e conferem com o codigo.'
      : '  âncoras carimbadas conferem com o código.');
    return ruins.length ? 1 : 0;
  }

  // --stamp e --sync escrevem. Um doc por vez, do fim pro começo, pra não invalidar índices.
  const head = (git('rev-parse', 'HEAD') || '').trim().slice(0, 7);
  if (!head) { console.error('  sem HEAD legível — abortado'); return 2; }

  const alvo = (MODE === 'stamp'
    ? rows.filter((r) => !r.sha && ['SEM_CARIMBO'].includes(r.estado))
    : rows.filter((r) => r.estado === 'MOVEU' && !r.fim) // range: o fim nao e recalculavel com seguranca
  ).filter((r) => !NAO_CARIMBAR.has(r.doc));

  for (const [doc, razao] of NAO_CARIMBAR) {
    const n = rows.filter((r) => r.doc === doc && !r.sha).length;
    if (n) console.log(`  pulado: ${doc} — ${n} ref(s) · ${razao}`);
  }

  if (!alvo.length) { console.log(`  nada a ${MODE === 'stamp' ? 'carimbar' : 'sincronizar'}.`); return 0; }

  const porDoc = {};
  alvo.forEach((r) => (porDoc[r.doc] ??= []).push(r));

  let n = 0;
  for (const [doc, lista] of Object.entries(porDoc)) {
    let txt = readFileSync(join(ROOT, doc), 'utf8');
    for (const r of [...lista].sort((a, b) => b.idx - a.idx)) {
      // O RANGE faz parte do endereço. Sem re-emitir o `-NN`, o carimbo transformaria
      // `show.blade.php:66-82` em `:66` e apagaria informação do documento — que é
      // exatamente o que este script promete não fazer. Pego na verificação
      // "prove que só o ponteiro mudou", antes de qualquer commit.
      const faixa = r.fim ? `-${r.fim}` : '';
      const novo = MODE === 'stamp'
        ? `${r.ref}:${r.linha}${faixa} (verificado@${head})`
        : `${r.ref}:${r.para}${faixa} (verificado@${head})`;
      txt = txt.slice(0, r.idx) + novo + txt.slice(r.idx + r.bruto.length);
      n++;
    }
    writeFileSync(join(ROOT, doc), txt);
    console.log(`  ${doc} — ${lista.length} ref(s)`);
  }
  console.log(`\n  ${n} ref(s) ${MODE === 'stamp' ? 'carimbadas' : 're-ancoradas'} em @${head}.`);
  console.log('  NENHUMA palavra do contrato foi tocada — só o ponteiro.');
  return 0;
}

// ── selftest: prova que MORDE no caso real e RECUSA nos casos incertos ────────
function selftest() {
  const tmp = join(tmpdir(), 'ancora-sync-selftest-' + process.pid);
  const casos = [];
  const ok = (nome, cond) => { casos.push([nome, cond]); console.log(`  [${cond ? 'OK' : 'FALHOU'}] ${nome}`); };

  // `--check` sai 1 QUANDO acha drift — é o contrato dele, não erro. Capturar o stdout
  // dos dois lados é obrigatório, senão o selftest confunde "mordeu" com "quebrou".
  const rodar = (...flags) => {
    try {
      return execFileSync(process.execPath, [import.meta.filename, ...flags, '--root', tmp], { encoding: 'utf8' });
    } catch (e) {
      return String(e.stdout ?? '');
    }
  };

  rmSync(tmp, { recursive: true, force: true });
  mkdirSync(join(tmp, 'resources/js/Pages/Demo'), { recursive: true });
  const G = (...a) => execFileSync('git', ['-C', tmp, ...a], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
  G('init', '-q');
  G('config', 'user.email', 't@t'); G('config', 'user.name', 't');

  const alvo = join(tmp, 'resources/js/Pages/Demo/Tela.tsx');
  writeFileSync(alvo, ['import x', 'const A = 1', 'function alvoUnico() {}', 'const B = 2'].join('\n'));
  writeFileSync(join(tmp, 'resources/js/Pages/Demo/Tela.casos.md'), 'UC-1: ver Tela.tsx:3\n');
  G('add', '-A'); G('commit', '-qm', 'base');
  const sha = G('rev-parse', 'HEAD').trim().slice(0, 7);

  const semStamp = rodar('--check', '--require-stamp', '--doc', 'resources/js/Pages/Demo/Tela.casos.md');
  ok('check estrito MORDE ref sem smart token Git SHA', /SEM_CARIMBO/.test(semStamp));

  // carimba
  rodar('--stamp');
  const carimbado = readFileSync(join(tmp, 'resources/js/Pages/Demo/Tela.casos.md'), 'utf8');
  ok('stamp carimba a ref sem sha', /Tela\.tsx:3 \(verificado@[0-9a-f]{7}\)/.test(carimbado));
  ok('stamp NAO altera o texto do contrato', carimbado.startsWith('UC-1: ver '));
  const estritoOk = rodar('--check', '--require-stamp', '--doc', 'resources/js/Pages/Demo/Tela.casos.md');
  ok('check estrito escopado libera ref carimbada valida', /smart token Git SHA valido/.test(estritoOk));

  // o código move: 2 linhas entram antes do alvo
  writeFileSync(alvo, ['import x', 'const A = 1', 'const NOVO1 = 0', 'const NOVO2 = 0', 'function alvoUnico() {}', 'const B = 2'].join('\n'));
  G('add', '-A'); G('commit', '-qm', 'move');

  const out = rodar('--check');
  ok('check MORDE quando o alvo moveu (3→5)', /MOVEU/.test(out));

  rodar('--sync');
  const sincronizado = readFileSync(join(tmp, 'resources/js/Pages/Demo/Tela.casos.md'), 'utf8');
  ok('sync reescreve o PONTEIRO para a linha nova', /Tela\.tsx:5 \(verificado@/.test(sincronizado));
  ok('sync preserva a assercao intacta', sincronizado.startsWith('UC-1: ver '));

  const out2 = rodar('--check');
  ok('check LIBERA depois do sync (controle negativo)', !/MOVEU|PERDIDO/.test(out2));

  // RANGE preservado — regressão real: a 1ª versão reescrevia `:66-82` como `:66`,
  // apagando o fim do intervalo. Carimbo que come informação do doc é o oposto do
  // contrato deste script, e passou despercebido até a prova "só o ponteiro mudou".
  writeFileSync(join(tmp, 'resources/js/Pages/Demo/Tela.casos.md'), 'UC-2: ver Tela.tsx:1-3\n');
  G('add', '-A'); G('commit', '-qm', 'range');
  rodar('--stamp');
  const comRange = readFileSync(join(tmp, 'resources/js/Pages/Demo/Tela.casos.md'), 'utf8');
  ok('stamp PRESERVA o range da ref (:1-3 nao vira :1)', /Tela\.tsx:1-3 \(verificado@/.test(comRange));

  // ambiguidade: o trecho passa a existir 2x — nunca escreve
  writeFileSync(alvo, ['function alvoUnico() {}', 'const A = 1', 'const NOVO1 = 0', 'const NOVO2 = 0', 'function alvoUnico() {}', 'const B = 2'].join('\n'));
  writeFileSync(join(tmp, 'resources/js/Pages/Demo/Tela.casos.md'), `UC-1: ver Tela.tsx:3 (verificado@${sha})\n`);
  G('add', '-A'); G('commit', '-qm', 'dup');
  const out3 = rodar('--measure');
  ok('trecho duplicado vira AMBIGUO, nao MOVEU', /AMBIGUO/.test(out3));
  rodar('--sync');
  ok('sync RECUSA escrever no caso ambiguo', /Tela\.tsx:3/.test(readFileSync(join(tmp, 'resources/js/Pages/Demo/Tela.casos.md'), 'utf8')));

  rmSync(tmp, { recursive: true, force: true });
  const falhas = casos.filter(([, c]) => !c).length;
  console.log(falhas ? `\nSELFTEST FALHOU (${falhas}/${casos.length})` : `\nSELFTEST OK — ${casos.length}/${casos.length}: sincroniza o ponteiro, recusa o incerto, nunca toca a asserção.`);
  return falhas ? 1 : 0;
}

process.exit(MODE === 'selftest' ? selftest() : run());
