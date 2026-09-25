#!/usr/bin/env node
// secao-check.mjs — PR-A3 do protocolo de export: o ALVO versionado vira GATE.
//
// Doc: memory/reference/prototipo-ui/PROTOCOL.md · prototipo-ui/cowork/Wagner/COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO.md
// Insumo: governance/design/targets/<slug>.alvo.json (PR-A1, `scripts/design-sync/alvo.mjs`).
// Aposenta: regressão de seção vizinha ser DESCOBERTA em review (T6) em vez de reprovar no CI.
//
// ── O QUE ELE **NÃO** É (LC-19: máquina paralela a dono existente) ────────────────────────
// Ele não mede e não serve nada por conta própria. Os dois donos já existem e são chamados:
//   · MEDIR  → `scripts/design-sync/alvo.mjs --alvo ... --saida <tmp>` (subprocesso; aquele
//              módulo executa main() no import e é CLI-only por construção).
//   · SERVIR → `servirEstatico` de `scripts/design/render-proto-baseline.mjs` (import dinâmico,
//              só no modo --servir-espelho: o dono da rota `/_ds/` é ele, ADR 0401 E2).
// O que ESTE arquivo acrescenta é o que nenhum dos dois faz: COMPARAR alvo × render e reprovar
// NOMEANDO o ausente.
//
// ── O QUE BLOQUEIA, E POR QUE SÓ ISSO (FP MEDIDO ANTES DE LIGAR) ─────────────────────────
// Medido em 2026-09-17, espelho servido × `jana--index.alvo.json` (medido em 2026-09-07):
// 14 campos divergiram — `base.assinatura` + `nos_totais` (2) e `rect.w/h` (12). Estrutura de
// seção (`nos` · `filhos` · `ordemClasses` · `estilo` · `truncado` · `ausente`): **ZERO**.
// A causa dos 14 é o espelho ter sido reimportado (o shell perdeu a sidebar → conteúdo de 972
// para 1176 px). Ou seja: um predicado "tudo igual" nasceria VERMELHO HERDADO numa árvore
// limpa — 100% de falso-positivo, PR-independente, que é a lápide §5 2026-08-24 (predicado
// ABSOLUTO em vez de DELTA) e o gate-de-teatro que se aprende a ignorar.
// Então BLOQUEIA só o que é SLOT (o que a seção tem de ter):
//     ausente · nos · filhos · ordemClasses (ordem!) · estilo (tokens resolvidos) · truncado
// e REPORTA sem bloquear o que é consequência de viewport/shell: `rect` e o `base` da página.
// Geometria não é slot; medi-la como slot mede a janela, não a tela.
//
// ── "NÃO MEDI" NUNCA VIRA VEREDITO (§5 2026-07-29) ───────────────────────────────────────
// Se TODAS as seções vierem ausentes, isso é render errado/rota errada/página não carregada —
// e sai 2 (NÃO MEDI), nunca 1 (regrediu). Colapsar os dois faria o gate acusar o PR por um
// defeito de medição, que é a LC-33.
//
// ── O QUE ELE NÃO AFIRMA ─────────────────────────────────────────────────────────────────
// Verde aqui NÃO é "está igual ao design" (T7). T7 exige a fonte provada fresca
// (`cowork-mirror-freshness --compare <snap>`, que precisa de `DesignSync.get_file` — auth
// interativa, ADR 0315: NÃO roda em CI) + `design-diff --compare --check` nos dois renders.
// Este gate responde outra pergunta, e só ela: "o render ainda tem os slots que o alvo declara?"
//
// Uso:
//   node scripts/qa/secao-check.mjs --tela <slug> --url <url>        # mede e compara
//   node scripts/qa/secao-check.mjs --tela <slug> --medido <arq>     # compara medida pronta
//   node scripts/qa/secao-check.mjs --todos --servir-espelho         # o modo do CI
//   node scripts/qa/secao-check.mjs --selftest                     # puro, sem browser
//
// O --selftest e PURO de proposito: o bite-test com browser de verdade e o do dono da medida
// (`alvo.mjs --selftest --browser`). Aqui a unidade sob teste e a COMPARACAO, e ela nao precisa
// de render — anunciar um --browser que nao existe seria LC-15.
//
// Exit: 0 conforme · 1 regrediu · 2 NÃO CONSEGUI MEDIR.

import { readFileSync, existsSync, readdirSync, mkdtempSync, rmSync } from 'node:fs';
import { dirname, resolve, join, basename } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { spawn } from 'node:child_process';
import { tmpdir } from 'node:os';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..', '..');
const DIR_ALVOS = join(ROOT, 'governance', 'design', 'targets');
const ALVO_MJS = join(ROOT, 'scripts', 'design-sync', 'alvo.mjs');
const PORTA_PADRAO = 5550;          // a porta gravada na proveniência do jana--index.alvo.json

const argv = process.argv.slice(2);
const flag = (n) => argv.includes(n);
const val = (n, d = null) => { const i = argv.indexOf(n); return i >= 0 && argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[i + 1] : d; };

/* ── comparação ─────────────────────────────────────────────────────────────────────────── */

/** Campos que são SLOT (bloqueiam). `rect`/`base` ficam de fora de propósito — ver docblock. */
export const CAMPOS_SLOT = ['nos', 'filhos', 'ordemClasses', 'truncado'];

const mesmo = (a, b) => JSON.stringify(a) === JSON.stringify(b);

/**
 * Compara o alvo versionado com o render medido.
 * @returns {{achados:Array, informativos:Array, secoes:number, ausentesTotais:number}}
 */
export function comparar(esperado, medido) {
  const achados = [], informativos = [];
  const secoesE = esperado.secoes || {}, secoesM = medido.secoes || {};
  const ids = Object.keys(secoesE);
  let ausentesTotais = 0;

  for (const id of ids) {
    const e = secoesE[id], m = secoesM[id];

    if (!m) { achados.push({ secao: id, campo: '(seção)', esperado: 'medida', obtido: 'não veio no render' }); continue; }
    if (m.ausente && !e.ausente) { ausentesTotais++; achados.push({ secao: id, campo: 'ausente', esperado: `presente (${e.seletor})`, obtido: 'seletor não casou no DOM' }); continue; }
    if (e.ausente) continue;   // alvo já declarava ausente: nada a cobrar

    for (const c of CAMPOS_SLOT) {
      if (e[c] === undefined) continue;
      if (!mesmo(e[c], m[c])) achados.push({ secao: id, campo: c, esperado: e[c], obtido: m[c] });
    }
    // estilo: token a token, pra nomear QUAL token saiu (não "o estilo mudou")
    for (const [k, v] of Object.entries(e.estilo || {})) {
      const got = (m.estilo || {})[k];
      if (!mesmo(v, got)) achados.push({ secao: id, campo: `estilo.${k}`, esperado: v, obtido: got === undefined ? '(não medido)' : got });
    }
    if (e.rect && m.rect && !mesmo(e.rect, m.rect)) informativos.push({ secao: id, campo: 'rect', esperado: e.rect, obtido: m.rect });
  }

  if (esperado.nos_totais !== medido.nos_totais) informativos.push({ secao: '(página)', campo: 'nos_totais', esperado: esperado.nos_totais, obtido: medido.nos_totais });
  if ((esperado.base || {}).assinatura !== (medido.base || {}).assinatura) informativos.push({ secao: '(página)', campo: 'base.assinatura', esperado: '(texto da página)', obtido: 'divergiu' });

  return { achados, informativos, secoes: ids.length, ausentesTotais };
}

/** Todas as seções ausentes = render/rota errada. É NÃO MEDI, nunca "regrediu" (§5 2026-07-29). */
export function ehNaoMedi(r) { return r.secoes > 0 && r.ausentesTotais === r.secoes; }

/**
 * O ALVO e DERIVADO do `<slug>.secoes.json`; a DECLARACAO e que manda. Se a declaracao tem
 * secao que o alvo nao carrega, o alvo esta DEFASADO -- e o buraco e SILENCIOSO por construcao,
 * porque `comparar()` itera `Object.keys(esperado.secoes)`, ou seja as secoes do ALVO: secao
 * declarada e nao medida nunca entra na comparacao.
 *
 * MEDIDO em 2026-09-22 com `jana--index.secoes.json` declarando 10 e o alvo carregando 9:
 *   secao-check -> `OK 9 secao(oes) conforme`      exit 0
 *   placar      -> `entregue 9 de 9 (100%)`        exit 0
 * Os dois ficaram cegos, e o placar publicou 100% sobre um denominador errado. E a familia da
 * lapide 5 de 2026-08-10 (catraca cujo universo vem do lado mutavel) numa porta nova: aqui o
 * universo vem do artefato DERIVADO em vez da declaracao. O README de targets/ ja nomeava o
 * vetor ("editar o alvo e a forma de burlar o check") e a defesa que ele declarava -- o path no
 * gatilho da lane -- nao o fecha: rodar o comparador nao ajuda se o universo dele e o alvo.
 *
 * FP = 0 por construcao, e isto e o que torna o predicado seguro: a sonda emite TODA secao
 * declarada, com `ausente: true` quando o seletor nao casa (ALVO_PROBE_SOURCE) -- entao secao
 * declarada SEMPRE aterrissa no alvo, e divergencia de conjunto so acontece com alvo defasado.
 * Chave que comeca com `_` e nota/`_ausentes`, nao secao (mesma convencao que a sonda ignora).
 */
export function conferirDeclaracao(declaracao, alvo) {
  const declaradas = Object.keys(declaracao || {}).filter((k) => !k.startsWith('_'));
  const noAlvo = new Set(Object.keys((alvo && alvo.secoes) || {}));
  return declaradas.filter((id) => !noAlvo.has(id)).map((id) => ({
    secao: id,
    campo: '(alvo defasado)',
    esperado: 'secao medida no alvo (ela esta declarada no .secoes.json)',
    obtido: 'ausente do .alvo.json -- re-rode `npm run alvo:medir`',
  }));
}

/* ── medição (delegada ao dono) ─────────────────────────────────────────────────────────── */

async function medirVia(alvo, url) {
  const secoesArq = join(DIR_ALVOS, `${slugDe(alvo)}.secoes.json`);
  if (!existsSync(secoesArq)) return { erro: `sem ${basename(secoesArq)} — o alvo não é re-executável sem as seções`, naoMedi: true };
  const tmp = mkdtempSync(join(tmpdir(), 'secao-check-'));
  const saida = join(tmp, 'medido.json');
  const args = [ALVO_MJS, '--alvo', url, '--tela', alvo.tela, '--secoes', secoesArq, '--saida', saida];
  // a proveniência do alvo é parte da medida: sem as MESMAS flags não se reproduz o arquivo
  if (alvo.aguardou_sumir) args.push('--aguardar-sumir', alvo.aguardou_sumir);
  if (alvo.quieto_ms) args.push('--quieto-ms', String(alvo.quieto_ms));
  // thread 15 da sidebar: o modo e o viewport também são proveniência — sem eles o render
  // cairia no auto-rail de 1280 e o alvo do expanded seria comparado contra um rail.
  if (alvo.viewport) args.push('--viewport', alvo.viewport);
  if (alvo.sb_mode) args.push('--sb-mode', alvo.sb_mode);
  // thread 07 do Financeiro: rota do protótipo + clique que abre o drawer também são proveniência
  if (alvo.rota) args.push('--rota', alvo.rota);
  if (alvo.clicar) args.push('--clicar', alvo.clicar);

  // spawn ASSÍNCRONO de propósito: no modo --servir-espelho o servidor roda NESTE processo, e
  // `spawnSync` travaria o event loop — o subprocesso pediria a página e ninguém responderia.
  // Medido em 2026-09-17: com spawnSync o goto estourava 30 s de timeout, 100% das vezes.
  const r = await new Promise((ok) => {
    const p = spawn(process.execPath, args, { stdio: ['ignore', 'pipe', 'pipe'] });
    let out = '', err = '';
    p.stdout.on('data', (d) => { out += d; });
    p.stderr.on('data', (d) => { err += d; });
    p.on('error', (e) => ok({ status: -1, err: e.message }));
    p.on('close', (status) => ok({ status, out, err }));
  });

  if (r.status !== 0 || !existsSync(saida)) {
    try { rmSync(tmp, { recursive: true, force: true }); } catch {}
    // QUALQUER falha do subprocesso é falha de MEDIÇÃO, nunca regressão: sem medida não há o que
    // comparar, e acusar o PR por um crash da sonda é a LC-33 (falso-positivo por não-medição).
    // O veredito "regrediu" só pode nascer da comparação, mais abaixo.
    return { erro: `alvo.mjs saiu ${r.status}: ${String(r.err || r.out).trim().slice(0, 300)}`, naoMedi: true };
  }
  const medido = JSON.parse(readFileSync(saida, 'utf8'));
  try { rmSync(tmp, { recursive: true, force: true }); } catch {}
  return { medido };
}

const slugDe = (alvo) => String(alvo.tela).replace(/[^a-z0-9-]/gi, '-').toLowerCase();

async function servirEspelho(porta) {
  // pathToFileURL, nunca o path cru: no Windows `import('D:\\...')` morre com "protocol 'd:'"
  // (§5 2026-08-07 — literal de path não é portátil entre plataformas).
  const url = (...p) => pathToFileURL(join(ROOT, ...p)).href;
  const mod = await import(url('scripts', 'design', 'render-proto-baseline.mjs'));
  if (typeof mod.servirEstatico !== 'function') throw Object.assign(new Error('render-proto-baseline não exporta servirEstatico'), { naoMedi: true });
  const { MIRROR_DIR } = await import(url('scripts', 'design', 'protocolo.config.mjs'));
  return mod.servirEstatico(MIRROR_DIR, porta);
}

/* ── relatório ──────────────────────────────────────────────────────────────────────────── */

function reportar(nome, r) {
  const linha = (a) => `    · ${a.secao} → ${a.campo}: esperado ${JSON.stringify(a.esperado)} · obtido ${JSON.stringify(a.obtido)}`;
  if (r.achados.length === 0) console.log(`  ✓ ${nome} — ${r.secoes} seção(ões) conforme`);
  else {
    console.log(`  ✗ ${nome} — ${r.achados.length} divergência(s) de SLOT em ${r.secoes} seção(ões):`);
    for (const a of r.achados) console.log(linha(a));
  }
  if (r.informativos.length) {
    console.log(`    (informativo, não bloqueia — geometria/página: ${r.informativos.length})`);
    for (const a of r.informativos.slice(0, 4)) console.log(`      ~ ${a.secao} → ${a.campo}`);
  }
}

/* ── selftest ───────────────────────────────────────────────────────────────────────────── */

const BASE = {
  tela: 'fixture', nos_totais: 100, base: { assinatura: 'x' },
  secoes: {
    a: { seletor: '.a', nos: 1, filhos: 3, ordemClasses: ['x', 'y', 'z'], estilo: { color: 'oklch(0.5 0 0)' }, truncado: false, rect: { w: 972, h: 10 } },
    b: { seletor: '.b', nos: 1, filhos: 2, ordemClasses: ['p', 'q'], estilo: { fontSize: '11px' }, truncado: false, rect: { w: 972, h: 20 } },
  },
};
const clone = (o) => JSON.parse(JSON.stringify(o));

function selftest() {
  const checks = [];
  const ok = (nome, cond, det = '') => checks.push({ nome, ok: !!cond, det });

  // CONTROLE NEGATIVO — sem ele, um comparador que "reprova sempre" passaria em todo o resto.
  ok('CONTROLE: idêntico NÃO acusa', comparar(BASE, clone(BASE)).achados.length === 0);

  // CONTROLE DE FP — é a medição de 2026-09-17: rect/base drifam sozinhos e NÃO podem bloquear.
  const soGeometria = clone(BASE);
  soGeometria.nos_totais = 799;
  soGeometria.base.assinatura = 'outro shell';
  soGeometria.secoes.a.rect = { w: 1176, h: 12 };
  soGeometria.secoes.b.rect = { w: 1176, h: 20 };
  const g = comparar(BASE, soGeometria);
  ok('CONTROLE FP: só rect/base mudando NÃO bloqueia', g.achados.length === 0, `achados=${g.achados.length}`);
  ok('...mas aparece como informativo (não some calado)', g.informativos.length === 4, `inf=${g.informativos.length}`);

  // MORDIDAS
  const semFilho = clone(BASE); semFilho.secoes.a.filhos = 2;
  const m1 = comparar(BASE, semFilho);
  ok('MORDE: slot removido (filhos 3→2)', m1.achados.length === 1 && m1.achados[0].campo === 'filhos' && m1.achados[0].secao === 'a');

  const ordem = clone(BASE); ordem.secoes.a.ordemClasses = ['x', 'z', 'y'];
  ok('MORDE: ORDEM trocada (mesmo conjunto)', comparar(BASE, ordem).achados.some((a) => a.campo === 'ordemClasses'));

  const token = clone(BASE); token.secoes.a.estilo.color = 'oklch(0.9 0 0)';
  const m3 = comparar(BASE, token);
  ok('MORDE: token resolvido mudou, NOMEANDO o token', m3.achados.some((a) => a.campo === 'estilo.color'));

  const sumiu = clone(BASE); sumiu.secoes.b = { seletor: '.b', ausente: true };
  const m4 = comparar(BASE, sumiu);
  ok('MORDE: seção ausente do DOM, nomeando a seção', m4.achados.some((a) => a.secao === 'b' && a.campo === 'ausente'));
  ok('...e ausência parcial NÃO é NÃO MEDI', !ehNaoMedi(m4));

  // NÃO MEDI ≠ regrediu
  const tudoSumiu = clone(BASE);
  tudoSumiu.secoes = { a: { ausente: true }, b: { ausente: true } };
  ok('NÃO MEDI: TODAS ausentes = render errado, não regressão', ehNaoMedi(comparar(BASE, tudoSumiu)));

  // alvo que já declarava ausente não é cobrado
  const jaAusente = clone(BASE); jaAusente.secoes.b = { seletor: '.b', ausente: true };
  const esperadoAusente = clone(BASE); esperadoAusente.secoes.b = { seletor: '.b', ausente: true };
  ok('alvo com ausente:true declarado não vira achado', comparar(esperadoAusente, jaAusente).achados.length === 0);

  // ── ALVO DEFASADO (buraco medido em 2026-09-22) ─────────────────────────────────────
  // Sem esta perna, secao declarada no `.secoes.json` e ausente do `.alvo.json` saia
  // "conforme": o comparador itera o ALVO, nao a declaracao.
  const d1 = conferirDeclaracao({ _: 'nota', a: { seletor: '.a' }, b: { seletor: '.b' }, c: { seletor: '.c' } }, BASE);
  ok('MORDE: secao DECLARADA que o alvo nao carrega (alvo defasado), nomeando-a',
    d1.length === 1 && d1[0].secao === 'c', `achados=${d1.length}`);
  ok('...e o achado ENSINA o conserto (re-rodar a medida)', /alvo:medir/.test(JSON.stringify(d1)));
  ok('CONTROLE: declaracao em sincronia NAO acusa',
    conferirDeclaracao({ _: 'nota', a: {}, b: {} }, BASE).length === 0);
  ok('CONTROLE: chave de nota (`_`, `_ausentes`) nao e secao',
    conferirDeclaracao({ _: 'x', _ausentes: { z: { motivo: 'sem endpoint' } } }, BASE).length === 0);
  ok('CONTROLE: sem arquivo de declaracao NAO acusa (null)',
    conferirDeclaracao(null, BASE).length === 0);
  // FP=0 por construcao: secao cujo seletor nao casa entra no alvo com ausente:true, logo
  // ESTA no conjunto -- nao e defasagem. Sem este controle o predicado acusaria o legitimo.
  ok('CONTROLE: secao no alvo com ausente:true conta como PRESENTE (nao e defasagem)',
    conferirDeclaracao({ a: {}, b: {} }, esperadoAusente).length === 0);

  for (const c of checks) console.log(`${c.ok ? '  ok  ' : '  FALHOU '} ${c.nome}${c.det ? ' — ' + c.det : ''}`);
  const maus = checks.filter((c) => !c.ok).length;
  console.log(`\nselftest: ${checks.length - maus}/${checks.length}`);
  return maus ? 1 : 0;
}

/* ── CLI ────────────────────────────────────────────────────────────────────────────────── */

function alvosPedidos() {
  if (flag('--todos')) {
    if (!existsSync(DIR_ALVOS)) return [];
    return readdirSync(DIR_ALVOS).filter((f) => f.endsWith('.alvo.json')).map((f) => join(DIR_ALVOS, f));
  }
  const tela = val('--tela');
  if (!tela) return null;
  const slug = tela.replace(/[^a-z0-9-]/gi, '-').toLowerCase().replace(/\.alvo\.json$/, '');
  return [join(DIR_ALVOS, `${slug}.alvo.json`)];
}

async function main() {
  if (flag('--selftest')) return selftest();

  const arquivos = alvosPedidos();
  if (!arquivos) { console.error('uso: --tela <slug> [--url <url> | --medido <arq> | --servir-espelho] | --todos --servir-espelho | --selftest'); return 2; }
  if (arquivos.length === 0) { console.error('NÃO MEDI: nenhum .alvo.json em governance/design/targets/'); return 2; }
  if (flag('--todos') && val('--medido')) { console.error('--medido é de UM render; com --todos ele seria comparado contra todo alvo. Use --tela.'); return 2; }

  let servidor = null, urlBase = val('--url');
  const porta = Number(val('--porta', PORTA_PADRAO));
  if (flag('--servir-espelho')) {
    try { servidor = await servirEspelho(porta); urlBase = `http://127.0.0.1:${porta}/`; }
    catch (e) { console.error(`NÃO MEDI: não consegui servir o espelho — ${e.message}`); return 2; }
  }

  let pior = 0;
  try {
    for (const arq of arquivos) {
      if (!existsSync(arq)) { console.error(`NÃO MEDI: alvo inexistente: ${arq.replace(ROOT, '.')}`); pior = Math.max(pior, 2); continue; }
      const alvo = JSON.parse(readFileSync(arq, 'utf8'));
      const nome = basename(arq, '.alvo.json');

      let medido;
      const medidoArq = val('--medido');
      if (medidoArq) {
        if (!existsSync(medidoArq)) { console.error(`NÃO MEDI: --medido inexistente: ${medidoArq}`); pior = Math.max(pior, 2); continue; }
        medido = JSON.parse(readFileSync(medidoArq, 'utf8'));
      } else {
        if (!urlBase) { console.error('NÃO MEDI: informe --url, --medido ou --servir-espelho'); return 2; }
        const r = await medirVia(alvo, urlBase);
        if (r.erro) { console.error(`NÃO MEDI (${nome}): ${r.erro}`); pior = Math.max(pior, 2); continue; }
        medido = r.medido;
      }

      const res = comparar(alvo, medido);
      if (ehNaoMedi(res)) {
        console.error(`  ? ${nome} — NÃO MEDI: as ${res.secoes} seções vieram ausentes (rota/render errado, não regressão)`);
        pior = Math.max(pior, 2);
        continue;
      }
      // O alvo e derivado da declaracao; conferir o CONJUNTO fecha o buraco do alvo defasado,
      // que `comparar()` nao pode ver (ele itera as secoes do ALVO). Ver conferirDeclaracao().
      const declArq = join(DIR_ALVOS, `${nome}.secoes.json`);
      if (existsSync(declArq)) {
        let decl;
        try { decl = JSON.parse(readFileSync(declArq, 'utf8')); }
        catch (e) {
          // declaracao ilegivel e falha de MEDICAO, nunca regressao (LC-33).
          console.error(`NAO MEDI (${nome}): ${basename(declArq)} ilegivel -- ${e.message.slice(0, 120)}`);
          pior = Math.max(pior, 2);
          continue;
        }
        res.achados.push(...conferirDeclaracao(decl, alvo));
      }
      reportar(nome, res);
      if (res.achados.length) pior = Math.max(pior, 1);
    }
  } finally { if (servidor) servidor.close(); }

  console.log(pior === 0 ? '\nsecao-check: conforme' : pior === 1 ? '\nsecao-check: REGREDIU — um slot declarado sumiu do render, ou o alvo esta DEFASADO em relação ao .secoes.json' : '\nsecao-check: NÃO MEDI (o silêncio aqui não é "está são")');
  return pior;
}

// Guard de entrypoint: sem ele `import` deste arquivo RODA o CLI e MATA o processo do chamador.
// Medido em 2026-09-22: importar pra usar `comparar()` imprimia o texto de uso e saia 2, antes de
// qualquer linha do importador -- o mesmo trap que o docblock do alvo.mjs descreve pro design-diff,
// e que os 4 irmaos deste conjunto (alvo.mjs, design-diff.mjs, render-proto-baseline.mjs,
// style-fingerprint.mjs) e o placar.mjs ja tinham fechado. Este exporta 4 simbolos
// (`comparar`, `ehNaoMedi`, `conferirDeclaracao`, `CAMPOS_SLOT`) e era o unico sem o guard.
// O CLI segue identico: invocado por `node scripts/qa/secao-check.mjs`, argv[1] e este arquivo.
const ehEntrypoint = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
if (ehEntrypoint) {
  main()
    .then((rc) => process.exit(rc))
    .catch((e) => { console.error(`${e.naoMedi ? 'NÃO MEDI' : 'FALHOU'}: ${e.message}`); process.exit(e.naoMedi ? 2 : 1); });
}
