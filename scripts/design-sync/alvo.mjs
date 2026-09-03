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
async function esperarEstavel(page) {
  await page.waitForLoadState('domcontentloaded');
  // Sinal que a app publica, quando publica. Ausente → não é erro; o laço abaixo é a garantia real.
  await page.waitForFunction(() => window.__oiLazyDone === true, null, { timeout: 8000 }).catch(() => {});
  let anterior = -1;
  for (let i = 0; i < 25; i++) {
    const atual = await page.evaluate(() => document.querySelectorAll('*').length);
    if (atual === anterior && atual > 0) return atual;
    anterior = atual;
    await page.waitForTimeout(200);
  }
  throw Object.assign(new Error('DOM não estabilizou em 25 leituras — medida seria retrato de meio-caminho'), { naoMedi: true });
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
async function rodarMapa(url, raiz) {
  const { browser, page } = await abrirPagina(url);
  try {
    await esperarEstavel(page);
    if (raiz) await page.evaluate((r) => { window.__ALVO_RAIZ = r; }, raiz);
    const mapa = await page.evaluate(MAPA_PROBE_SOURCE);
    // stdout only — mapa é COMANDO, não arquivo (ADR 0256 · L-42).
    console.log(serializar({ url, ...mapa }));
  } finally { await browser.close(); }
}

async function medirAlvo({ url, tela, secoes, injetar }) {
  const probe = sondaCanonica();
  const { browser, page } = await abrirPagina(url);
  try {
    const nos = await esperarEstavel(page);
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
    return { tela, url, nos_totais: nos, base, secoes: medidoSecoes, ausentes: [] };
  } finally { await browser.close(); }
}

/* ── selftest ───────────────────────────────────────────────────────────────────────────── */
const FIXTURE = `<!doctype html><meta charset="utf-8"><title>alvo fixture</title>
<style>.cartao{display:flex;gap:8px;padding:12px;border-radius:6px}.item{width:40px}</style>
<main><section class="cartao"><div class="item a"></div><div class="item b"></div><div class="item c"></div></section></main>
<script>window.__oiLazyDone = true;</script>`;

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
    if (!url) { console.error('uso: --mapa <url> [--raiz <seletor>]'); return 2; }
    await rodarMapa(url, val('--raiz'));
    return 0;
  }

  if (flag('--alvo')) {
    const url = val('--alvo'), tela = val('--tela'), arq = val('--secoes');
    if (!url || !tela || !arq) { console.error('uso: --alvo <url> --tela <slug> --secoes <arq.json> [--injetar-falha <sel>]'); return 2; }
    if (!existsSync(arq)) { console.error(`--secoes: arquivo não encontrado: ${arq}`); return 2; }
    const secoes = JSON.parse(readFileSync(arq, 'utf8'));
    const medido = await medirAlvo({ url, tela, secoes, injetar: val('--injetar-falha') });
    mkdirSync(DIR_ALVOS, { recursive: true });
    const destino = join(DIR_ALVOS, `${tela.replace(/[^a-z0-9-]/gi, '-').toLowerCase()}.alvo.json`);
    writeFileSync(destino, serializar(medido));
    console.log(`alvo gravado: ${destino.replace(ROOT, '.')}`);
    console.log(`  seções medidas: ${Object.keys(medido.secoes).length} · ausentes no DOM: ${Object.values(medido.secoes).filter((s) => s.ausente).length}`);
    return 0;
  }

  console.error('uso: --mapa <url> | --alvo <url> --tela <slug> --secoes <arq.json> | --selftest [--browser]');
  return 2;
}

main()
  .then((rc) => process.exit(rc))
  .catch((e) => { console.error(`${e.naoMedi ? 'NÃO MEDI' : 'FALHOU'}: ${e.message}`); process.exit(e.naoMedi ? 2 : 1); });
