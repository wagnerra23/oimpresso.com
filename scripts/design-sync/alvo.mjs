#!/usr/bin/env node
// alvo.mjs — PR-A1 do protocolo de export: o ALVO de uma seção vira MEDIDA executável.
//
// Doc: prototipo-ui/PROTOCOL.md · COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md
// Origem: pedido [W] 2026-09-03 — "automatizar o ciclo MAPA → ALVO → EXPORT → PR → PLACAR".
// Aposenta: eu medindo à mão e ditando números no chat (LC-06 — comparação é MEDIDA, nunca no olho).
//
// ── O QUE ESTE ARQUIVO **NÃO** É (LC-19: máquina paralela a dono existente) ──────────────
// Ele NÃO tem sonda própria de tela. A sonda canônica é `prototipo-ui/design-diff.mjs`
// (dimensões D1–D9, por PAPEL) e ela é consumida aqui pelo **contrato público** dela —
// `node prototipo-ui/design-diff.mjs --probe`, subprocesso, medido rc=0/17318 bytes em
// 2026-09-03. Não importamos o módulo: ele é CLI-only (importar dispara o CLI e sai 2).
// O que ESTE arquivo acrescenta é a camada que o dono não cobre: medida **por SELETOR**
// (nós · filhos · ordem das classes · computed style · truncamento · retângulo), que é o
// que o protocolo de export chama de ALVO de uma seção.
//
// ── ONDE GRAVA, E POR QUE NÃO EM prototipo-ui/contrato/ ─────────────────────────────────
// `prototipo-ui/contrato/` já tem dono: `contract.schema.json` + `scripts/contrato-de-tela.mjs`
// (gate estático, sem render) — e lá a chave `alvo` **já significa outra coisa**: "dirs/arquivos
// de produção checados". Gravar `<tela>.alvo.json` naquela pasta colidiria de vocabulário e de
// dono (LC-22: mudar artefato que a máquina lê sem rodar a máquina). Destino: `prototipo-ui/alvos/`.
//
// ── MODOS ────────────────────────────────────────────────────────────────────────────────
//   --mapa <url> [--raiz <sel>]        Colhe filhos diretos da raiz + classes repetidas.
//                                      **stdout only, NUNCA grava** (mapa é comando, ADR 0256).
//   --alvo <url> --tela <slug> --secoes <arq.json>
//                                      Mede e grava prototipo-ui/alvos/<slug>.alvo.json.
//   --injetar-falha <seletor>          (com --alvo) remove o último filho direto antes de medir.
//                                      É o aceite falsificável T5: o JSON TEM de mudar.
//   --aguardar-sumir <seletor>         (com --mapa ou --alvo) só mede DEPOIS que o seletor sair do
//                                      DOM (esqueleto/placeholder). Sem ele, "duas leituras iguais"
//                                      aprova um esqueleto que fica parado >400 ms — medido 2026-09-06
//                                      no Painel da Jana: `jm-sk` vive 650 ms (jana-merge.jsx:907) e o
//                                      --mapa devolveu 4 `jm-sk-card` como se fossem a tela. Se o
//                                      seletor nunca sumir em 15 s → exit 2 (NÃO MEDI), nunca "0 filhos".
//   --quieto-ms <n>                    (com --mapa ou --alvo) janela MÍNIMA sem mudança no nº de nós
//                                      antes de medir (default 400 = as "duas leituras iguais" originais).
//                                      Página com fases encadeadas de carga (nota → esqueleto → conteúdo →
//                                      re-carga quando a empresa do shell chega, jana-merge.jsx:904-908)
//                                      fica estável por 400 ms em CADA fase; só a janela longa separa
//                                      "parou" de "parou entre duas fases". Medido 2026-09-06: com 400 ms
//                                      o mesmo comando deu 719 nós num run e 1011 no seguinte.
//   --selftest                         Partes puras (serialização estável · args). Sem browser.
//   --selftest --browser               Bite-test real: 2 runs byte-idênticos + injeção muda.
//
// Exit: 0 limpo · 1 falhou · 2 NÃO CONSEGUI MEDIR (browser/sonda ausente).
// O 2 é separado de propósito: "não medi" nunca pode se passar por "está são" (§5 2026-07-29).

import { writeFileSync, mkdirSync, readFileSync, existsSync } from 'node:fs';
import { dirname, resolve, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';

const HERE = dirname(fileURLToPath(import.meta.url));            // scripts/design-sync/
const ROOT = resolve(HERE, '..', '..');                          // raiz do repo
const DIR_ALVOS = join(ROOT, 'prototipo-ui', 'alvos');
const DESIGN_DIFF = join(ROOT, 'prototipo-ui', 'design-diff.mjs');

const argv = process.argv.slice(2);
const flag = (n) => argv.includes(n);
const val = (n, d = null) => { const i = argv.indexOf(n); return i >= 0 && argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[i + 1] : d; };

/* ── serialização ESTÁVEL — sem isto o aceite "2 runs byte-idênticos" não existe ────────── */
export function estavel(v) {
  if (Array.isArray(v)) return v.map(estavel);
  if (v && typeof v === 'object') {
    return Object.keys(v).sort().reduce((o, k) => { o[k] = estavel(v[k]); return o; }, {});
  }
  return v;
}
const serializar = (obj) => JSON.stringify(estavel(obj), null, 2) + '\n';

/* ── a sonda canônica vem do DONO, por subprocesso (contrato público --probe) ───────────── */
export function sondaCanonica() {
  const r = spawnSync(process.execPath, [DESIGN_DIFF, '--probe'], { encoding: 'utf8' });
  // §5 2026-08-14: ler os DOIS streams; rc≠0 é falha de execução, nunca "vazio".
  if (r.status !== 0 || !r.stdout || r.stdout.length < 100) {
    const motivo = r.status !== 0 ? `rc=${r.status} stderr=${(r.stderr || '').slice(0, 200)}` : `stdout curto (${(r.stdout || '').length}b)`;
    throw Object.assign(new Error(`design-diff --probe não respondeu: ${motivo}`), { naoMedi: true });
  }
  return r.stdout;
}

/* ── a camada QUE O DONO NÃO COBRE: medida por SELETOR ──────────────────────────────────── */
export const ALVO_PROBE_SOURCE = `(() => {
  const cfg = window.__ALVO_SECOES || {};
  const CAMPOS_PADRAO = ['display','color','backgroundColor','fontSize','fontWeight','padding','gap','borderRadius'];
  const arred = (n) => Math.round(n);
  const primeiraClasse = (el) => {
    const c = (el.getAttribute('class') || '').trim().split(/\\s+/).filter(Boolean);
    return c.length ? c[0] : el.tagName.toLowerCase();
  };
  const out = {};
  for (const [id, spec] of Object.entries(cfg)) {
    if (id.startsWith('_')) continue; // chave de nota/proveniência do secoes.json, não é seção
    const sel = typeof spec === 'string' ? spec : spec.seletor;
    const campos = (typeof spec === 'object' && spec.campos) || CAMPOS_PADRAO;
    const els = [...document.querySelectorAll(sel)];
    const el = els[0] || null;
    if (!el) { out[id] = { seletor: sel, nos: 0, ausente: true }; continue; }
    const filhos = [...el.children];
    const cs = getComputedStyle(el);
    const rect = el.getBoundingClientRect();
    const estilo = {};
    for (const c of campos) estilo[c] = cs[c];
    out[id] = {
      seletor: sel,
      nos: els.length,
      filhos: filhos.length,
      ordemClasses: filhos.map(primeiraClasse),
      estilo,
      truncado: el.scrollWidth > el.clientWidth + 2,
      rect: { w: arred(rect.width), h: arred(rect.height) }
    };
  }
  return out;
})()`;

export const MAPA_PROBE_SOURCE = `(() => {
  const raiz = document.querySelector(window.__ALVO_RAIZ || 'main') || document.body;
  const cls = (el) => (el.getAttribute('class') || '').trim().split(/\\s+/).filter(Boolean);
  const filhos = [...raiz.children].map((el, i) => ({
    i, tag: el.tagName.toLowerCase(), classes: cls(el).slice(0, 4), filhos: el.children.length
  }));
  const cont = {};
  for (const el of raiz.querySelectorAll('*')) for (const c of cls(el)) cont[c] = (cont[c] || 0) + 1;
  const repetidas = Object.entries(cont).filter(([, n]) => n >= 3).sort((a, b) => b[1] - a[1]).slice(0, 25);
  return { raiz: raiz.tagName.toLowerCase(), filhos, repetidas };
})()`;

/* ── estabilidade: nunca medir durante o lazy-load (§5 2026-08-24) ──────────────────────── */
async function esperarEstavel(page, { sumir = null, quietoMs = 400 } = {}) {
  await page.waitForLoadState('domcontentloaded');
  if (sumir) {
    // Espera o esqueleto SAIR — e só então mede a estabilidade. Ordem importa: o esqueleto é
    // estável por construção, então "estável" antes de "sumiu" é o falso verde do §5 2026-08-24.
    // Primeiro espera o esqueleto APARECER (até 3 s; se a página não o monta, segue) — senão
    // "detached" é verdade antes de ele montar e a medida sai do estado anterior ao esqueleto
    // (medido 2026-09-06: 719 nós e 7 seções ausentes, com o mesmo comando que depois deu 1011).
    await page.waitForSelector(sumir, { state: 'attached', timeout: 3000 }).catch(() => {});
    try { await page.waitForSelector(sumir, { state: 'detached', timeout: 15000 }); }
    catch { throw Object.assign(new Error(`--aguardar-sumir: "${sumir}" ainda está no DOM após 15 s — não meço esqueleto`), { naoMedi: true }); }
  }
  // Sinal que a app publica, quando publica. Ausente → não é erro; o laço abaixo é a garantia real.
  await page.waitForFunction(() => window.__oiLazyDone === true, null, { timeout: 8000 }).catch(() => {});
  const PASSO = 200;
  const precisa = Math.max(2, Math.ceil(quietoMs / PASSO)); // leituras iguais SEGUIDAS que fecham a janela
  let anterior = -1, iguais = 0;
  for (let i = 0; i < 25 + precisa; i++) {
    const atual = await page.evaluate(() => document.querySelectorAll('*').length);
    iguais = atual === anterior && atual > 0 ? iguais + 1 : 0;
    if (iguais + 1 >= precisa) return atual;
    anterior = atual;
    await page.waitForTimeout(PASSO);
  }
  throw Object.assign(new Error(`DOM não ficou quieto por ${quietoMs} ms em ${25 + precisa} leituras — medida seria retrato de meio-caminho`), { naoMedi: true });
}

async function abrirPagina(url) {
  let chromium;
  try { ({ chromium } = await import('@playwright/test')); }
  catch { throw Object.assign(new Error('@playwright/test indisponível — rode `npm ci` (e `npm run e2e:install`)'), { naoMedi: true }); }
  let browser;
  try { browser = await chromium.launch(); }
  catch (e) { throw Object.assign(new Error(`chromium não instalado: ${e.message.slice(0, 160)}`), { naoMedi: true }); }
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await page.goto(url, { waitUntil: 'domcontentloaded' });
  return { browser, page };
}

/* ── modos ──────────────────────────────────────────────────────────────────────────────── */
async function rodarMapa(url, raiz, sumir = null, quietoMs = 400) {
  const { browser, page } = await abrirPagina(url);
  try {
    await esperarEstavel(page, { sumir, quietoMs });
    if (raiz) await page.evaluate((r) => { window.__ALVO_RAIZ = r; }, raiz);
    const mapa = await page.evaluate(MAPA_PROBE_SOURCE);
    // stdout only — mapa é COMANDO, não arquivo (ADR 0256 · L-42).
    console.log(serializar({ url, ...mapa }));
  } finally { await browser.close(); }
}

async function medirAlvo({ url, tela, secoes, injetar, sumir = null, quietoMs = 400 }) {
  const probe = sondaCanonica();
  const { browser, page } = await abrirPagina(url);
  try {
    const nos = await esperarEstavel(page, { sumir, quietoMs });
    if (injetar) {
      const mexeu = await page.evaluate((sel) => {
        const el = document.querySelector(sel);
        if (!el || !el.lastElementChild) return false;
        el.removeChild(el.lastElementChild);
        return true;
      }, injetar);
      if (!mexeu) throw new Error(`--injetar-falha: seletor "${injetar}" não tinha filho pra remover (a injeção seria no-op e o aceite viraria carimbo)`);
    }
    await page.evaluate((s) => { window.__ALVO_SECOES = s; }, secoes);
    const medidoSecoes = await page.evaluate(ALVO_PROBE_SOURCE);
    const base = await page.evaluate(probe);          // sonda do DONO, mesma string
    return { tela, url, nos_totais: nos, aguardou_sumir: sumir, quieto_ms: quietoMs, base, secoes: medidoSecoes, ausentes: [] };
  } finally { await browser.close(); }
}

/* ── selftest ───────────────────────────────────────────────────────────────────────────── */
const FIXTURE = `<!doctype html><meta charset="utf-8"><title>alvo fixture</title>
<style>.cartao{display:flex;gap:8px;padding:12px;border-radius:6px}.item{width:40px}</style>
<main><section class="cartao"><div class="item a"></div><div class="item b"></div><div class="item c"></div></section></main>
<script>window.__oiLazyDone = true;</script>`;
// Esqueleto que some SOZINHO depois de 700 ms — mais que os 400 ms de "duas leituras iguais".
// Sem --aguardar-sumir a medição pega o esqueleto; com ele, pega a tela.
const FIXTURE_SK = FIXTURE.replace('<div class="item c"></div>', '<div class="item c"></div><div class="sk"></div>')
  + `<script>setTimeout(() => document.querySelector('.sk').remove(), 700);</script>`;
// Duas fases encadeadas: 1 filho no load, +1 aos 300 ms, +1 aos 900 ms. Entre 300 e 900 o DOM fica
// parado 600 ms — "duas leituras iguais" (400 ms) mede 2 filhos; --quieto-ms 1500 mede os 3.
const FIXTURE_FASES = FIXTURE.replace('<div class="item b"></div><div class="item c"></div>', '')
  + `<script>const s=document.querySelector('.cartao');const add=(k)=>{const d=document.createElement('div');d.className='item '+k;s.appendChild(d);};
setTimeout(()=>add('b'),300);setTimeout(()=>add('c'),900);</script>`;

async function selftest(comBrowser) {
  const checks = [];
  const ok = (nome, cond, detalhe = '') => checks.push({ nome, ok: !!cond, detalhe });

  // puras — sempre rodam, sem browser
  ok('estavel() ordena chaves (determinismo do JSON)',
    JSON.stringify(estavel({ b: 1, a: { d: 2, c: 3 } })) === '{"a":{"c":3,"d":2},"b":1}');
  ok('serializar() é idempotente',
    serializar({ x: [3, 1] }) === serializar(estavel({ x: [3, 1] })));
  try { ok('sondaCanonica() lê o contrato público do design-diff', sondaCanonica().length > 1000); }
  catch (e) { ok('sondaCanonica() lê o contrato público do design-diff', false, e.message); }

  if (comBrowser) {
    const f = join(tmpdir(), `alvo-fixture-${process.pid}.html`);
    writeFileSync(f, FIXTURE);
    const url = 'file://' + f.split('\\').join('/');
    const secoes = { cartao: { seletor: '.cartao' } };
    const a = await medirAlvo({ url, tela: 'fixture', secoes });
    const b = await medirAlvo({ url, tela: 'fixture', secoes });
    ok('2 runs seguidas dão byte-idêntico', serializar(a) === serializar(b));
    const c = await medirAlvo({ url, tela: 'fixture', secoes, injetar: '.cartao' });
    ok('--injetar-falha MUDA o JSON (o alvo não é carimbo)', serializar(c) !== serializar(a));
    ok('injeção some 1 filho (3 → 2)', c.secoes.cartao.filhos === 2 && a.secoes.cartao.filhos === 3,
      `a=${a.secoes.cartao.filhos} c=${c.secoes.cartao.filhos}`);

    // --aguardar-sumir: mede DEPOIS do esqueleto (bite-test) + controle negativo (nunca some → NÃO MEDI)
    const fsk = join(tmpdir(), `alvo-fixture-sk-${process.pid}.html`);
    writeFileSync(fsk, FIXTURE_SK);
    const urlSk = 'file://' + fsk.split('\\').join('/');
    const d = await medirAlvo({ url: urlSk, tela: 'fixture-sk', secoes: { ...secoes, sk: { seletor: '.sk' } }, sumir: '.sk' });
    ok('--aguardar-sumir mede a tela, não o esqueleto (3 filhos, .sk ausente)',
      d.secoes.cartao.filhos === 3 && d.secoes.sk.ausente === true && d.aguardou_sumir === '.sk',
      `filhos=${d.secoes.cartao.filhos} sk.ausente=${d.secoes.sk.ausente}`);
    let negRc = null;
    try { await medirAlvo({ url: urlSk, tela: 'fixture-sk', secoes, sumir: '.nunca-some-mas-existe' }); negRc = 'mediu'; }
    catch (e) { negRc = e.naoMedi ? 'naoMedi' : 'falhou'; }
    // seletor inexistente já está "detached" — o controle negativo real é um que EXISTE e fica:
    let negRc2 = null;
    try { await medirAlvo({ url, tela: 'fixture', secoes, sumir: '.cartao' }); negRc2 = 'mediu'; }
    catch (e) { negRc2 = e.naoMedi ? 'naoMedi' : 'falhou'; }
    ok('--aguardar-sumir em seletor que NUNCA sai → NÃO MEDI (exit 2), nunca "0 filhos"', negRc2 === 'naoMedi', `rc=${negRc2} (inexistente=${negRc})`);

    // --quieto-ms: carga em fases encadeadas — a janela longa mede o estado FINAL
    const ff = join(tmpdir(), `alvo-fixture-fases-${process.pid}.html`);
    writeFileSync(ff, FIXTURE_FASES);
    const urlF = 'file://' + ff.split(String.fromCharCode(92)).join('/');
    const g = await medirAlvo({ url: urlF, tela: 'fixture-fases', secoes, quietoMs: 1500 });
    ok('--quieto-ms 1500 mede o estado final de carga em fases (3 filhos)', g.secoes.cartao.filhos === 3 && g.quieto_ms === 1500,
      `filhos=${g.secoes.cartao.filhos}`);
  }

  for (const c of checks) console.log(`${c.ok ? 'ok  ' : 'X   '}${c.nome}${c.detalhe ? ' — ' + c.detalhe : ''}`);
  const falhou = checks.filter((c) => !c.ok).length;
  if (!comBrowser) console.log('\n(parte pura apenas — o bite-test real exige `--selftest --browser`)');
  console.log(`\n${checks.length - falhou}/${checks.length} ok`);
  return falhou ? 1 : 0;
}

/* ── CLI ────────────────────────────────────────────────────────────────────────────────── */
async function main() {
  if (flag('--selftest')) return selftest(flag('--browser'));

  if (flag('--mapa')) {
    const url = val('--mapa');
    if (!url) { console.error('uso: --mapa <url> [--raiz <seletor>] [--aguardar-sumir <seletor>]'); return 2; }
    await rodarMapa(url, val('--raiz'), val('--aguardar-sumir'), Number(val('--quieto-ms', 400)));
    return 0;
  }

  if (flag('--alvo')) {
    const url = val('--alvo'), tela = val('--tela'), arq = val('--secoes');
    if (!url || !tela || !arq) { console.error('uso: --alvo <url> --tela <slug> --secoes <arq.json> [--injetar-falha <sel>] [--aguardar-sumir <sel>]'); return 2; }
    if (!existsSync(arq)) { console.error(`--secoes: arquivo não encontrado: ${arq}`); return 2; }
    const secoes = JSON.parse(readFileSync(arq, 'utf8'));
    const medido = await medirAlvo({ url, tela, secoes, injetar: val('--injetar-falha'), sumir: val('--aguardar-sumir'), quietoMs: Number(val('--quieto-ms', 400)) });
    mkdirSync(DIR_ALVOS, { recursive: true });
    const destino = join(DIR_ALVOS, `${tela.replace(/[^a-z0-9-]/gi, '-').toLowerCase()}.alvo.json`);
    writeFileSync(destino, serializar(medido));
    console.log(`alvo gravado: ${destino.replace(ROOT, '.')}`);
    console.log(`  seções medidas: ${Object.keys(medido.secoes).length} · ausentes no DOM: ${Object.values(medido.secoes).filter((s) => s.ausente).length}`);
    return 0;
  }

  console.error('uso: --mapa <url> [--aguardar-sumir <sel>] [--quieto-ms <n>] | --alvo <url> --tela <slug> --secoes <arq.json> [--aguardar-sumir <sel>] [--quieto-ms <n>] | --selftest [--browser]');
  return 2;
}

main()
  .then((rc) => process.exit(rc))
  .catch((e) => { console.error(`${e.naoMedi ? 'NÃO MEDI' : 'FALHOU'}: ${e.message}`); process.exit(e.naoMedi ? 2 : 1); });
