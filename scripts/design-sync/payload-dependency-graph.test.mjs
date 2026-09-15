#!/usr/bin/env node
// @ts-check
/**
 * payload-dependency-graph.test.mjs — MORDE/SOLTA do grafo de dependências do payload.
 *
 * Hermético: monta as árvores de mentira EM MEMÓRIA (o módulo recebe `{path, content}`, não lê
 * disco), então não toca repo, rede, auth do DesignSync nem PowerShell. Só built-ins do node.
 *
 * Por que existe: este é o MOTOR compartilhado de 5 consumidores — `aplicar-payload`,
 * `bundle-contract`, `bundle-transaction`, `gerar-payload-partes` e `render-proto-baseline` —
 * e era o único deles sem teste próprio. Era exercitado só de lado, pelos testes dos
 * consumidores: uma regressão aqui aparecia longe da causa, ou não aparecia.
 *
 * Cada caso tem par morde/solta. Sem o par, uma guarda que diz "sim" pra tudo passa por
 * não-execução (LC-13) — e o par mais importante deste arquivo é o §3: `react` e `s.css` têm a
 * MESMA forma (nome nu, sem `./`) e vereditos opostos conforme quem declara. É o guard que
 * impede um pacote npm de ser contado como arquivo ausente do bundle.
 *
 * Contratos travados aqui:
 *   · caminho de payload é POSIX e relativo — barra invertida do Windows normaliza, `..` e
 *     absoluto LANÇAM (o applier escreve no disco a partir disto: zip-slip mora aqui);
 *   · nome nu em JS é pacote/bundler (external), em HTML/CSS é arquivo — o guard `fromJs`;
 *   · `?v=1` e `#frag` não entram no nome do arquivo (o shell do Cowork versiona por query);
 *   · binário é FOLHA: `binary: true` não é lido, mesmo com extensão textual;
 *   · ciclo não trava o fechamento (a↔b termina);
 *   · presente-porém-inalcançável vai pra `extra` e NÃO derruba `complete` — quem derruba é
 *     missing/unsafe/duplicate/entry ausente;
 *   · `reachable` só lista o que EXISTE: entry ausente não pode sair como alcançado (afirmar
 *     o que não se mediu é o LC-08 de sempre).
 */
import { normalizePayloadPath, rawDependencyRefs, payloadDependencyGraph } from './payload-dependency-graph.mjs';

const BARRA_INVERTIDA = String.fromCharCode(92); // literal no fonte colapsa no transporte (LC-26)

let falhas = 0;
const ok = (cond, nome) => {
  console.log(`  ${cond ? 'ok  ' : 'FALHOU'} ${nome}`);
  if (!cond) falhas++;
};
const lanca = (fn, trecho, nome) => {
  let msg = null;
  try { fn(); } catch (e) { msg = e.message; }
  ok(msg !== null && msg.includes(trecho), `${nome} (msg: ${msg ? msg.slice(0, 60) : 'NAO LANCOU'})`);
};
const mesmoConjunto = (a, b) => a.length === b.length && [...a].sort().join('|') === [...b].sort().join('|');
const html = (corpo) => ({ path: 'oimpresso.com.html', content: corpo });

export function selftest() {
  console.log('\npayload-dependency-graph --selftest\n');

  // ── 1) normalizePayloadPath: POSIX relativo, ou lança ───────────────────────
  ok(normalizePayloadPath('a' + BARRA_INVERTIDA + 'b.css') === 'a/b.css', 'SOLTA: barra invertida do Windows vira POSIX');
  ok(normalizePayloadPath('./a.css') === 'a.css', 'SOLTA: prefixo ./ some');
  ok(normalizePayloadPath('a/./x/../b.css') === 'a/b.css', 'SOLTA: . e .. internos colapsam');
  lanca(() => normalizePayloadPath('../fora.css'), 'caminho inseguro', 'MORDE: .. escapa da raiz do payload');
  lanca(() => normalizePayloadPath('/etc/passwd'), 'caminho inseguro', 'MORDE: caminho absoluto');
  lanca(() => normalizePayloadPath(''), 'caminho inseguro', 'MORDE: vazio');
  lanca(() => normalizePayloadPath('.'), 'caminho inseguro', 'MORDE: o proprio diretorio');

  // ── 2) rawDependencyRefs: o que cada linguagem declara ──────────────────────
  ok(mesmoConjunto(rawDependencyRefs('a.css', '@import "t.css";\n.b{background:url(img/bg.png)}'), ['t.css', 'img/bg.png']),
    'SOLTA: CSS declara @import e url()');
  ok(mesmoConjunto(
    rawDependencyRefs('a.jsx', 'import R from "react";\nimport "./x.js";\nawait import("./lazy.js");\nrequire("./r.js");\nnew URL("./w.js", import.meta.url);\nconst e = <img src="./logo.png" poster="./p.png"/>;'),
    ['react', './x.js', './lazy.js', './r.js', './w.js', './logo.png', './p.png']),
    'SOLTA: JS declara import/dinamico/require/new URL e src|poster de JSX');
  ok(mesmoConjunto(
    rawDependencyRefs('i.html', '<link rel="stylesheet" href="s.css"><script src="a.jsx?v=1"></script><style>@import "dentro.css";</style><div style="background:url(d.png)"></div><script>import "./inline.js";</script>'),
    ['s.css', 'a.jsx?v=1', 'dentro.css', 'd.png', './inline.js']),
    'SOLTA: HTML declara link/script/style-bloco/style-atributo/script-inline');
  ok(mesmoConjunto(rawDependencyRefs('i.html', '<img srcset="a.png 1x, b.png 2x">'), ['a.png', 'b.png']),
    'SOLTA: srcset vira uma ref por candidato, sem o descritor 1x/2x');
  // Controle negativo: sem isto, um .png com bytes que PARECEM ref viraria dependência fantasma.
  ok(rawDependencyRefs('logo.png', '@import "fantasma.css";').length === 0, 'MORDE: extensao nao-textual nao e varrida');
  ok(rawDependencyRefs('a.css', Buffer.from('@import "fantasma.css";')).length === 0, 'MORDE: conteudo nao-string nao e varrido');

  // ── 3) O par discriminante: nome nu é pacote em JS, arquivo em HTML/CSS ─────
  const nu = payloadDependencyGraph([
    html('<link href="s.css"><script src="app.jsx"></script>'),
    { path: 's.css', content: '' },
    { path: 'app.jsx', content: 'import React from "react";\nimport "https://cdn.exemplo/x.js";' },
  ]);
  ok(mesmoConjunto(nu.external.map((e) => e.ref), ['react', 'https://cdn.exemplo/x.js']),
    'SOLTA: nome nu em JS e pacote npm -> external, nunca arquivo do bundle');
  ok(nu.missing.length === 0 && nu.complete, 'SOLTA: pacote npm NAO conta como arquivo ausente');
  ok(nu.reachable.includes('s.css'), 'MORDE: nome nu em HTML e arquivo (mesma forma, veredito oposto)');

  // ── 4) query/fragmento e raiz do payload ───────────────────────────────────
  const q = payloadDependencyGraph([
    html('<script src="app.jsx?v=1"></script><link href="/raiz.css#topo">'),
    { path: 'app.jsx', content: '' }, { path: 'raiz.css', content: '' },
  ]);
  ok(q.complete && mesmoConjunto(q.reachable, ['oimpresso.com.html', 'app.jsx', 'raiz.css']),
    'SOLTA: ?v=1 e #frag saem do nome; / inicial e a raiz do payload, nao do disco');

  // ── 5) fechamento transitivo, e extra NAO e defeito ────────────────────────
  const fechado = payloadDependencyGraph([
    html('<link href="css/base.css"><script src="app.jsx"></script>'),
    { path: 'css/base.css', content: '@import "tema.css";' },
    { path: 'css/tema.css', content: '' },
    { path: 'app.jsx', content: 'import "./util";' },
    { path: 'util.js', content: '' },
    { path: 'orfao.css', content: '' },
  ]);
  ok(mesmoConjunto(fechado.reachable, ['oimpresso.com.html', 'css/base.css', 'css/tema.css', 'app.jsx', 'util.js']),
    'SOLTA: fecha em profundidade e resolve ref relativa pelo dirname de QUEM declara');
  ok(mesmoConjunto(fechado.extra, ['orfao.css']), 'SOLTA: presente e inalcancavel vai pra extra');
  ok(fechado.complete, 'MORDE: extra NAO derruba complete (sobra nao e furo)');
  ok(fechado.edges.some((e) => e.from === 'app.jsx' && e.ref === './util' && e.to === 'util.js'),
    'SOLTA: extensao omitida resolve pela lista de candidatos');

  // ── 6) extensao omitida: index de pasta, e o que acontece sem ele ──────────
  const comIndex = payloadDependencyGraph([
    html('<script src="app.jsx"></script>'),
    { path: 'app.jsx', content: 'import "./hooks";' },
    { path: 'hooks/index.jsx', content: '' },
  ]);
  ok(comIndex.complete && comIndex.reachable.includes('hooks/index.jsx'), 'SOLTA: ./pasta resolve pra pasta/index.jsx');
  const semIndex = payloadDependencyGraph([
    html('<script src="app.jsx"></script>'),
    { path: 'app.jsx', content: 'import "./hooks";' },
  ]);
  ok(mesmoConjunto(semIndex.missing, ['hooks']) && !semIndex.complete,
    'MORDE: sem candidato nenhum, vira missing — nao e silenciado como external');

  // ── 7) os 4 jeitos de o payload estar incompleto ───────────────────────────
  const furo = payloadDependencyGraph([html('<script src="sumiu.js"></script>')]);
  ok(mesmoConjunto(furo.missing, ['sumiu.js']) && !furo.complete, 'MORDE: ref sem arquivo -> missing + complete=false');

  const fuga = payloadDependencyGraph([html('<link href="../fora.css">')]);
  ok(fuga.unsafe.length === 1 && fuga.unsafe[0].ref === '../fora.css' && !fuga.complete,
    'MORDE: ref que escapa da raiz -> unsafe + complete=false');
  ok(fuga.edges.length === 0 && fuga.missing.length === 0, 'MORDE: ref insegura nao maquia como aresta nem como missing');

  const dup = payloadDependencyGraph([
    html('<link href="d.css">'), { path: './d.css', content: '' }, { path: 'd.css', content: '' },
  ]);
  ok(mesmoConjunto(dup.duplicates, ['d.css']) && !dup.complete,
    'MORDE: mesmo arquivo por dois nomes (./d.css e d.css) -> duplicates + complete=false');

  const semEntry = payloadDependencyGraph([{ path: 'outro.html', content: '' }], { entry: 'nao-existe.html' });
  ok(!semEntry.entryPresent && !semEntry.complete, 'MORDE: entry ausente -> entryPresent=false + complete=false');
  ok(mesmoConjunto(semEntry.missing, ['nao-existe.html']), 'MORDE: entry ausente entra em missing');
  ok(semEntry.reachable.length === 0, 'MORDE: reachable nao lista o entry que nao existe');
  ok(mesmoConjunto(semEntry.extra, ['outro.html']), 'SOLTA: o resto da arvore vira extra, nao some do relatorio');

  // ── 8) binario e folha: nao se le o que nao se sabe ler ────────────────────
  const bin = payloadDependencyGraph([
    html('<link href="blob.css">'),
    { path: 'blob.css', binary: true, content: '@import "fantasma.css";' },
  ]);
  ok(bin.missing.length === 0 && bin.complete, 'MORDE: binary=true e folha mesmo com extensao textual');
  ok(bin.reachable.includes('blob.css'), 'SOLTA: folha binaria ainda conta como alcancada');
  // Contraprova: o MESMO conteúdo, sem a flag, acha o fantasma. Sem este par, o caso acima
  // passaria por engano se o varredor de CSS estivesse morto.
  const naoBin = payloadDependencyGraph([
    html('<link href="blob.css">'), { path: 'blob.css', content: '@import "fantasma.css";' },
  ]);
  ok(mesmoConjunto(naoBin.missing, ['fantasma.css']), 'SOLTA: sem a flag, o mesmo conteudo acusa o fantasma');

  // ── 9) ciclo nao trava o fechamento ────────────────────────────────────────
  const ciclo = payloadDependencyGraph([
    html('<script src="a.jsx"></script>'),
    { path: 'a.jsx', content: 'import "./b.jsx";' },
    { path: 'b.jsx', content: 'import "./a.jsx";' },
  ]);
  ok(ciclo.complete && mesmoConjunto(ciclo.reachable, ['oimpresso.com.html', 'a.jsx', 'b.jsx']),
    'SOLTA: a<->b termina e alcanca os dois (a fila nao reenfileira visitado)');

  // ── 10) entry customizado ──────────────────────────────────────────────────
  const outroEntry = payloadDependencyGraph([
    { path: 'preview/index.html', content: '<link href="p.css">' },
    { path: 'preview/p.css', content: '' },
    { path: 'oimpresso.com.html', content: '' },
  ], { entry: 'preview/index.html' });
  ok(outroEntry.entry === 'preview/index.html' && outroEntry.complete, 'SOLTA: entry customizado fecha a partir dele');
  ok(mesmoConjunto(outroEntry.extra, ['oimpresso.com.html']), 'MORDE: o shell padrao vira extra quando nao e o entry');

  console.log(`\n  ${falhas === 0 ? 'OK' : 'FALHAS: ' + falhas}\n`);
  if (falhas) process.exit(1);
}

if (process.argv[1] && process.argv[1].endsWith('payload-dependency-graph.test.mjs')) await selftest();
