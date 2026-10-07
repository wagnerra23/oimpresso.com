#!/usr/bin/env node
// @ts-check
/**
 * tema-escuro-probe.mjs — superfície CLARA no tema escuro, em todas as rotas do espelho.
 * Origem: playbook `tema-escuro` thread 01 (Cowork 2026-10-05): peças INVERTIDAS (fundo `--text`)
 * e fundos FIXOS claros só aparecem no escuro, às vezes só em hover/seleção — nada media isso.
 *
 * Por que não estende o dono: `design-diff`/`style-fingerprint`/`fingerprint-harness`/
 * `design-diff-lote` medem PARIDADE design×produção por papel, nas telas ancoradas. Esta pergunta
 * é absoluta (sem par) e varre TODAS as rotas do menu. O que reusa: o espelho é servido pelo
 * `servirEspelho` do `design-diff-lote.mjs` (ADR 0401 — resolve `_ds/`; sem ele, render sem DS).
 *
 * Mede, por rota de `MOCK.MENU_FLAT` (já inclui ghosts e SUPERADMIN_MENU): `__go(rota)` →
 * `__oiLazyDone` + 2 leituras iguais do nº de nós → todo elemento visível de `main.main`
 * (≥10×6 px) com fundo claro = L(OKLab) > 0,78, croma < 0,1, alfa ≥ 0,5. Alfa baixo é tinta
 * sobre o escuro; croma alto é cor de dado (barra âmbar). Os 2 cortes e a allowlist foram
 * MEDIDOS no espelho antes de ligar. Também abre hover no 1º `[class*=tip]` e seleção da 1ª
 * linha com checkbox. Sanidade: um `<div>` branco injetado tem de ser detectado.
 *
 * Exit 0 = nada fora do baseline · 1 = achado NOVO (rota×seletor) · 2 = NÃO MEDI (sem
 * playwright/chromium/espelho, tema não escureceu, sanidade falhou ou ≥3 rotas sem medida).
 * NÃO MEDI nunca é verde. Este arquivo não declara se bloqueia merge (§5 2026-07-16): o dono é
 * `governance/required-checks-baseline.json`.
 *
 *   node scripts/design/tema-escuro-probe.mjs --selftest              # partes puras
 *   node scripts/design/tema-escuro-probe.mjs [--rota a,b] [--json out.json]
 *   node scripts/design/tema-escuro-probe.mjs --write-baseline        # grava o estado atual
 *   node scripts/design/tema-escuro-probe.mjs --tema light --rota vendas   # controle positivo
 *   node scripts/design/tema-escuro-probe.mjs --de-json run.json [--baseline b.json]  # sem browser
 *   node scripts/design/tema-escuro-probe.mjs --allowlist                  # JSON da ALLOWLIST (lido pelo Pest)
 *   node scripts/design/tema-escuro-probe.mjs --producao <dir> [--json o]  # julga as Pages (thread 02)
 */

import { existsSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../..');
const ESPELHO_PADRAO = join(ROOT, 'prototipo-ui/cowork/Wagner');
const SHELL_HTML = 'oimpresso.com.html';
const BASELINE_PADRAO = join(HERE, 'tema-escuro-baseline.json');

export const LIMIAR_L = 0.78;
export const ALFA_MIN = 0.5;
/** Croma ≥ isto é cor de DADO/ESTADO (barra âmbar, legenda amarela), não superfície — medido 06/10. */
export const CROMA_MAX = 0.1;
export const TIMEOUT_ROTA_MS = 15000;
export const MAX_TIMEOUTS = 3;

/** Fora do veredito: superfície que é clara DE PROPÓSITO. Declarada aqui, nunca inferida. */
export const ALLOWLIST = [
  '.vr-paper', '.vd-trans-page', '[class*=print]', '[class*=paper]', '[class*=-a4]',
  '[class*=termica]', '[class*=recibo]', '[class*=proof]', '[class*=pdf]',
  '[class*=plate]', '[class*=tag]',
  // knob de switch: branco no escuro é o padrão do controle (medido 06/10: 14×14 em 12 rotas)
  '[class*=knob]', 'label:has(input[type=checkbox]) span span',
];

/* ═════════════════════════════ PARTE PURA ═════════════════════════════ */

const lin = (c) => (c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);

/** {L, C} do OKLab a partir de sRGB 0..1. */
export function oklab(r, g, b) {
  const [R, G, B] = [lin(r), lin(g), lin(b)];
  const l = Math.cbrt(0.4122214708 * R + 0.5363325363 * G + 0.0514459929 * B);
  const m = Math.cbrt(0.2119034982 * R + 0.6806995451 * G + 0.1073969566 * B);
  const s = Math.cbrt(0.0883024619 * R + 0.2817188376 * G + 0.6299787005 * B);
  const oa = 1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s;
  const ob = 0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s;
  return { L: 0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s, C: Math.hypot(oa, ob) };
}

const num = (t, escala = 1) => (t.endsWith('%') ? parseFloat(t) / 100 : parseFloat(t) / escala);

/** `{L, C, a}` de cor computada (rgb/oklch/oklab/color(srgb)); null = não sei ler (≠ escuro). */
export function luminancia(css) {
  const s = String(css || '').trim().toLowerCase();
  if (!s || s === 'transparent') return { L: 0, C: 0, a: 0 };
  const m = s.match(/^([a-z]+)\((.*)\)$/);
  if (!m) return null;
  const [, fn, corpo] = m;
  const [cor, alfaTxt] = corpo.includes('/') ? corpo.split('/') : [corpo, null];
  const p = cor.replace(/,/g, ' ').trim().split(/\s+/);
  let a = 1;
  if (alfaTxt != null) a = num(alfaTxt.trim());
  if (fn === 'rgb' || fn === 'rgba') {
    if (p.length === 4 && alfaTxt == null) a = num(p[3]);
    const [r, g, b] = p.slice(0, 3).map((t) => (t.endsWith('%') ? parseFloat(t) / 100 : parseFloat(t) / 255));
    return { ...oklab(r, g, b), a };
  }
  if (fn === 'oklch') return { L: num(p[0]), C: num(p[1] || '0'), a };
  if (fn === 'oklab') return { L: num(p[0]), C: Math.hypot(num(p[1] || '0'), num(p[2] || '0')), a };
  if (fn === 'color' && (p[0] === 'srgb' || p[0] === 'srgb-linear')) {
    const [r, g, b] = p.slice(1, 4).map((t) => num(t));
    const srgb = p[0] === 'srgb' ? [r, g, b] : [r, g, b].map((c) => (c <= 0.0031308 ? 12.92 * c : 1.055 * c ** (1 / 2.4) - 0.055));
    return { ...oklab(...srgb), a };
  }
  return null;
}

/** É superfície clara que conta? */
export function ehClaro(css) {
  const v = luminancia(css);
  return !!v && v.a >= ALFA_MIN && v.L > LIMIAR_L && v.C < CROMA_MAX;
}

/** Chave estável de um achado (o que o baseline guarda). */
export const chave = (rota, seletor) => `${rota} :: ${seletor}`;

/** Run × baseline. Rota não medida (timeout/erro) não "some" nada do baseline. */
export function confrontar(resultados, baseline) {
  const medidas = new Set(resultados.filter((r) => r.estado === 'ok').map((r) => r.rota));
  const atuais = new Set(resultados.flatMap((r) => (r.claros || []).map((c) => chave(r.rota, c.seletor))));
  const base = new Set(baseline || []);
  const novos = [...atuais].filter((k) => !base.has(k)).sort();
  const sumidos = [...base].filter((k) => medidas.has(k.split(' :: ')[0]) && !atuais.has(k)).sort();
  return { novos, sumidos };
}

/* ═══════════════════ PRODUÇÃO (thread 02 — Pages Inertia) ═══════════════════
 * O render das Pages é do Pest Browser (`tests/Browser/TemaEscuro/TemaEscuroProducaoTest.php`):
 * ele loga no tenant fictício do CI, liga `ui_theme=dark`, coleta TODO fundo não-transparente de
 * `main.main-body` (já sem a ALLOWLIST, que ele lê de `--allowlist`) e grava 1 JSON por Page.
 * O veredito de "claro" mora AQUI, numa função pura — o PHP não tem cópia do corte L/C/alfa.
 *
 * Registro: { screen, route, estado, escuro, fundos:[{seletor,bg,n}], sanidade? }
 *   estado ≠ 'ok' ou escuro=false → a Page NÃO conta como medida (nunca vira "0 claros").
 */
export function julgarProducao(registros) {
  const linhas = [];
  let sanidadeVista = false;
  let sanidadeOk = false;
  for (const r of [...registros].sort((a, b) => String(a.screen).localeCompare(String(b.screen)))) {
    if (Array.isArray(r.sanidade)) {
      sanidadeVista = true;
      if (claros(r.sanidade).some((c) => c.seletor === 'div.tema-escuro-sanidade')) sanidadeOk = true;
    }
    const medida = r.estado === 'ok' && r.escuro === true;
    const achados = medida ? claros(r.fundos).sort((a, b) => b.n - a.n) : [];
    const motivo = medida ? null : (r.estado !== 'ok' ? String(r.estado || 'sem-estado') : 'nao-escureceu');
    linhas.push({ screen: r.screen, route: r.route, medida, motivo, claros: achados });
  }
  const semMedida = linhas.filter((l) => !l.medida);
  return { linhas, medidas: linhas.length - semMedida.length, semMedida, sanidadeVista, sanidadeOk };
}

/** Lê o diretório de registros do Pest, imprime 1 linha por Page e devolve o exit code. */
function producao(dir, jsonOut) {
  if (!existsSync(dir)) {
    console.error(`NÃO MEDI — diretório de registros ausente: ${dir} (o Pest Browser não rodou ou não gravou).`);
    return 2;
  }
  const registros = readdirSync(dir).filter((f) => f.endsWith('.json'))
    .map((f) => JSON.parse(readFileSync(join(dir, f), 'utf8')));
  const j = julgarProducao(registros);
  if (jsonOut) writeFileSync(jsonOut, JSON.stringify(j, null, 2) + '\n');
  console.log(`tema-escuro (produção): ${j.linhas.length} Page(s) · ${j.medidas} medida(s) · ${j.semMedida.length} sem medida · ${j.linhas.filter((l) => l.claros.length).length} com superfície clara fora de papel`);
  for (const l of j.linhas) {
    if (!l.medida) { console.log(`  ⏱ ${l.screen} ${l.route} · NÃO MEDIDA (${l.motivo})`); continue; }
    const top = l.claros.slice(0, 4).map((c) => `${c.seletor}×${c.n}`).join(' ');
    console.log(`  ${l.claros.length ? '✗' : '✓'} ${l.screen} ${l.route} · ${l.claros.length} superfície(s) clara(s)${top ? ` · ${top}` : ''}`);
  }
  if (!j.sanidadeVista || !j.sanidadeOk) {
    console.error('NÃO MEDI — sanidade ausente ou falhou: o fundo branco injetado não foi julgado claro. Exit 2.');
    return 2;
  }
  if (j.medidas === 0 || j.semMedida.length >= MAX_TIMEOUTS) {
    console.error(`NÃO MEDI — ${j.semMedida.length} Page(s) sem medida (limite ${MAX_TIMEOUTS}), ${j.medidas} medida(s). Exit 2.`);
    return 2;
  }
  return 0;
}

/* ═════════════════════════════ RENDER ═════════════════════════════ */

/** Roda NA PÁGINA: agrupa elementos visíveis de main.main com fundo não-transparente. */
function coletarNaPagina(allow) {
  const main = document.querySelector('main.main');
  if (!main) return null;
  const vistos = new Map();
  for (const e of main.querySelectorAll('*')) {
    const cs = getComputedStyle(e);
    if (cs.display === 'none' || cs.visibility === 'hidden' || +cs.opacity === 0) continue;
    const bg = cs.backgroundColor;
    if (!bg || bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') continue;
    const r = e.getBoundingClientRect();
    if (r.width < 10 || r.height < 6) continue;
    if (allow.some((s) => e.closest(s))) continue;
    const cls = [...e.classList].filter((c) => !/^\d/.test(c)).slice(0, 2).map((c) => '.' + CSS.escape(c)).join('');
    const sel = e.tagName.toLowerCase() + cls;
    const k = sel + '|' + bg;
    const v = vistos.get(k);
    if (v) v.n++; else vistos.set(k, { seletor: sel, bg, n: 1 });
  }
  return [...vistos.values()];
}

async function estavel(page, ms) {
  const fim = Date.now() + ms;
  let ant = -1;
  while (Date.now() < fim) {
    const n = await page.evaluate(() => (window.__oiLazyDone ? document.querySelectorAll('*').length : -1));
    if (n > 0 && n === ant) return true;
    ant = n;
    await page.waitForTimeout(250);
  }
  return false;
}

const claros = (lista) => {
  const por = new Map();
  for (const c of lista || []) {
    if (!ehClaro(c.bg)) continue;
    const v = por.get(c.seletor);
    if (v) v.n += c.n; else por.set(c.seletor, { ...c });
  }
  return [...por.values()];
};

async function medirRota(page, rota) {
  await page.evaluate((r) => window.__go(r), rota);
  if (!(await estavel(page, TIMEOUT_ROTA_MS))) return { rota, estado: 'timeout', claros: [] };
  const base = await page.evaluate(coletarNaPagina, ALLOWLIST);
  if (base === null) return { rota, estado: 'sem-main', claros: [] };
  const estados = [];
  let extra = [];
  // estado escondido 1: hover na primeira dica
  const tip = page.locator('main.main [class*=tip]').first();
  if (await tip.count().catch(() => 0)) {
    await tip.hover({ timeout: 2000 }).then(() => estados.push('hover-tip')).catch(() => {});
    await page.waitForTimeout(150);
    extra = extra.concat((await page.evaluate(coletarNaPagina, ALLOWLIST)) || []);
  }
  // estado escondido 2: seleção de uma linha (barra de lote)
  const cb = page.locator('main.main tbody input[type=checkbox]').first();
  if (await cb.count().catch(() => 0)) {
    await cb.check({ timeout: 2000 }).then(() => estados.push('selecao')).catch(() => {});
    await page.waitForTimeout(200);
    extra = extra.concat((await page.evaluate(coletarNaPagina, ALLOWLIST)) || []);
    await cb.uncheck({ timeout: 2000 }).catch(() => {});
  }
  return { rota, estado: 'ok', estados, claros: claros(base.concat(extra)) };
}

/** Sanidade: um fundo branco injetado TEM de ser detectado, senão a sonda está cega. */
async function sanidade(page) {
  const ok = await page.evaluate(() => {
    const main = document.querySelector('main.main');
    if (!main) return false;
    const d = document.createElement('div');
    d.className = 'tema-escuro-sanidade';
    d.style.cssText = 'background:#fff;width:40px;height:20px';
    main.prepend(d);
    return true;
  });
  const lista = ok ? await page.evaluate(coletarNaPagina, ALLOWLIST) : null;
  await page.evaluate(() => document.querySelector('.tema-escuro-sanidade')?.remove());
  return !!lista && claros(lista).some((c) => c.seletor === 'div.tema-escuro-sanidade');
}

async function rodar({ filtro, tema = 'dark', espelho = ESPELHO_PADRAO }) {
  const ESPELHO = espelho;
  if (!existsSync(join(ESPELHO, SHELL_HTML))) throw Object.assign(new Error(`espelho ausente: ${SHELL_HTML}`), { naoMedi: true });
  let chromium;
  try { ({ chromium } = await import('playwright')); }
  catch { try { ({ chromium } = await import('@playwright/test')); } catch { chromium = null; } }
  if (!chromium) { throw Object.assign(new Error('playwright indisponível — `npm ci` + `npx playwright install chromium`'), { naoMedi: true }); }
  const { servirEspelho } = await import('./design-diff-lote.mjs');
  const srv = await servirEspelho(ESPELHO, 0);
  let browser;
  try {
    browser = await chromium.launch().catch((e) => { throw Object.assign(new Error(`chromium não instalado: ${String(e.message).slice(0, 160)}`), { naoMedi: true }); });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await page.goto(`http://127.0.0.1:${srv.porta}/${SHELL_HTML}`, { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.__oiLazyDone === true && typeof window.__go === 'function' && !!window.MOCK, null, { timeout: 60000 })
      .catch(() => { throw Object.assign(new Error('o shell não ficou pronto (__oiLazyDone/__go/MOCK) em 60s'), { naoMedi: true }); });
    await page.evaluate((t) => { window.__setTweak ? window.__setTweak('theme', t) : document.documentElement.setAttribute('data-theme', t); }, tema);
    await page.waitForTimeout(300);
    if ((await page.evaluate(() => document.documentElement.dataset.theme)) !== tema) throw Object.assign(new Error(`data-theme não ficou "${tema}"`), { naoMedi: true });
    let rotas = await page.evaluate(() => [...new Set(window.MOCK.MENU_FLAT.map((i) => i.id))]);
    if (filtro) rotas = rotas.filter((r) => filtro.includes(r));
    if (!rotas.length) throw Object.assign(new Error('nenhuma rota no MENU_FLAT'), { naoMedi: true });
    await page.evaluate((r) => window.__go(r), rotas[0]);
    await estavel(page, TIMEOUT_ROTA_MS);
    if (!(await sanidade(page))) throw Object.assign(new Error('sanidade falhou: o fundo branco injetado não foi detectado — a sonda está cega'), { naoMedi: true });
    const resultados = [];
    for (const rota of rotas) {
      const r = await Promise.race([
        medirRota(page, rota).catch((e) => ({ rota, estado: 'erro', erro: String(e.message).slice(0, 120), claros: [] })),
        new Promise((ok) => setTimeout(() => ok({ rota, estado: 'timeout', claros: [] }), TIMEOUT_ROTA_MS + 8000)),
      ]);
      resultados.push(r);
      process.stderr.write(r.estado === 'ok' ? (r.claros.length ? 'x' : '.') : 't');
    }
    process.stderr.write('\n');
    return resultados;
  } finally {
    await browser?.close().catch(() => {});
    await srv.fechar();
  }
}

/* ═════════════════════════════ SELFTEST ═════════════════════════════ */

function selftest() {
  const casos = [
    ['branco rgb é claro', ehClaro('rgb(255, 255, 255)'), true],
    ['fundo escuro oklch não é claro', ehClaro('oklch(0.2 0.01 280)'), false],
    ['oklch 0.985 (KPI fixo) é claro', ehClaro('oklch(0.985 0 0)'), true],
    ['FP conhecido: tinta 10% sobre transparente NÃO acusa', ehClaro('oklch(0.95 0.01 280 / 0.1)'), false],
    ['rgba com alfa baixo NÃO acusa', ehClaro('rgba(255, 255, 255, 0.08)'), false],
    ['color(srgb 1 1 1) é claro', ehClaro('color(srgb 1 1 1)'), true],
    ['âmbar saturado (barra de gráfico) NÃO acusa', ehClaro('oklch(0.82 0.16 75)'), false],
    ['pílula pastel fixa (light mode vazado) acusa', ehClaro('oklch(0.95 0.04 150)'), true],
    ['sky-50 do Tailwind acusa', ehClaro('rgb(240, 249, 255)'), true],
    ['cor ilegível não vira claro', ehClaro('lab(99 0 0)'), false],
    ['luminancia de ilegível é null (não-lido ≠ escuro)', luminancia('lab(99 0 0)'), null],
    ['transparent é alfa 0', luminancia('transparent').a, 0],
    ['allowlist cobre knob de switch', ALLOWLIST.includes('label:has(input[type=checkbox]) span span'), true],
  ];
  const { novos, sumidos } = confrontar(
    [{ rota: 'a', estado: 'ok', claros: [{ seletor: 'div.x' }] }, { rota: 'b', estado: 'timeout', claros: [] }],
    ['a :: div.velho', 'b :: div.y'],
  );
  casos.push(['novo fora do baseline', novos.join(), 'a :: div.x']);
  casos.push(['some só de rota medida (timeout não some)', sumidos.join(), 'a :: div.velho']);
  const prod = julgarProducao([
    { screen: 'A', route: '/a', estado: 'ok', escuro: true, fundos: [{ seletor: 'div.kpi', bg: 'oklch(0.985 0 0)', n: 2 }, { seletor: 'div.ok', bg: 'oklch(0.2 0 0)', n: 9 }],
      sanidade: [{ seletor: 'div.tema-escuro-sanidade', bg: 'rgb(255, 255, 255)', n: 1 }] },
    { screen: 'B', route: '/b', estado: 'ok', escuro: false, fundos: [{ seletor: 'div.kpi', bg: 'rgb(255, 255, 255)', n: 1 }] },
    { screen: 'C', route: '/c', estado: 'login', escuro: true, fundos: [] },
  ]);
  casos.push(['produção: claro conta só o claro', prod.linhas[0].claros.map((c) => c.seletor).join(), 'div.kpi']);
  casos.push(['produção: tela que não escureceu NÃO vira medida', prod.linhas[1].motivo, 'nao-escureceu']);
  casos.push(['produção: tela que caiu no login NÃO vira "0 claros"', [prod.linhas[2].medida, prod.linhas[2].motivo], [false, 'login']]);
  casos.push(['produção: sanidade lida do registro', [prod.sanidadeVista, prod.sanidadeOk], [true, true]]);
  const cega = julgarProducao([{ screen: 'A', route: '/a', estado: 'ok', escuro: true, fundos: [], sanidade: [{ seletor: 'div.tema-escuro-sanidade', bg: 'rgb(20, 20, 20)', n: 1 }] }]);
  casos.push(['produção: sanidade escura = sonda cega', cega.sanidadeOk, false]);
  let falhou = 0;
  for (const [nome, obtido, esperado] of casos) {
    const ok = JSON.stringify(obtido) === JSON.stringify(esperado);
    if (!ok) falhou++;
    console.log(`${ok ? '✓' : '✗'} ${nome}${ok ? '' : ` — obtido ${JSON.stringify(obtido)}, esperado ${JSON.stringify(esperado)}`}`);
  }
  console.log(`\n${casos.length - falhou}/${casos.length} ok`);
  return falhou ? 1 : 0;
}

/* ═════════════════════════════ CLI ═════════════════════════════ */

async function main() {
  const argv = process.argv.slice(2);
  const val = (n) => { const i = argv.indexOf(n); return i >= 0 ? argv[i + 1] : null; };
  if (argv.includes('--selftest')) return selftest();
  if (argv.includes('--allowlist')) { console.log(JSON.stringify(ALLOWLIST)); return 0; }
  if (val('--producao')) return producao(resolve(val('--producao')), val('--json'));
  const filtro = val('--rota') ? val('--rota').split(',') : null;
  const BASELINE = val('--baseline') ? resolve(val('--baseline')) : BASELINE_PADRAO;
  let resultados;
  // `--de-json <arquivo>`: re-julga um run gravado (hermético — é o que o bite-test exercita).
  try {
    resultados = val('--de-json')
      ? JSON.parse(readFileSync(val('--de-json'), 'utf8'))
      : await rodar({ filtro, tema: val('--tema') || 'dark', espelho: val('--espelho') ? resolve(val('--espelho')) : ESPELHO_PADRAO });
  }
  catch (e) {
    console.error(`NÃO MEDI — ${e.message}`);
    console.error('Isto é falha de AMBIENTE, não "tema ok" nem achado. Exit 2.');
    return 2;
  }
  const timeouts = resultados.filter((r) => r.estado !== 'ok');
  const baseline = existsSync(BASELINE) ? JSON.parse(readFileSync(BASELINE, 'utf8')).achados || [] : [];
  const { novos, sumidos } = confrontar(resultados, baseline);
  if (val('--json')) writeFileSync(val('--json'), JSON.stringify(resultados, null, 2) + '\n');
  const comAchado = resultados.filter((r) => r.claros.length);
  console.log(`tema-escuro: ${resultados.length} rota(s) · ${resultados.length - timeouts.length} medida(s) · ${timeouts.length} sem medida · ${comAchado.length} com superfície clara`);
  for (const r of timeouts) console.log(`  ⏱ ${r.rota}: ${r.estado}${r.erro ? ` (${r.erro})` : ''}`);
  if (timeouts.length >= MAX_TIMEOUTS) {
    console.error(`NÃO MEDI — ${timeouts.length} rotas sem medida (limite ${MAX_TIMEOUTS}). Exit 2.`);
    return 2;
  }
  if (argv.includes('--write-baseline')) {
    const achados = resultados.flatMap((r) => r.claros.map((c) => chave(r.rota, c.seletor))).sort();
    writeFileSync(BASELINE, JSON.stringify({ gerado: new Date().toISOString().slice(0, 10), nota: 'só encolhe — entrada que some do run sai daqui no mesmo PR', achados }, null, 2) + '\n');
    console.log(`baseline gravado: ${achados.length} achado(s)`);
    return 0;
  }
  for (const k of sumidos) console.log(`  ↓ sumiu (tire do baseline): ${k}`);
  for (const k of novos) {
    const [rota, sel] = k.split(' :: ');
    const c = resultados.find((r) => r.rota === rota).claros.find((x) => x.seletor === sel);
    console.log(`  ✗ NOVO ${k}  bg=${c.bg} n=${c.n}`);
  }
  return novos.length ? 1 : 0;
}

const direto = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (direto) main().then((c) => process.exit(c), (e) => { console.error(`NÃO MEDI — ${e.stack || e}`); process.exit(2); });
