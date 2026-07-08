#!/usr/bin/env node
// fingerprint-harness.mjs — Onda 3a do roadmap estado-da-arte (2026-07-08): DRIVER do
// style-fingerprint. Onde o fingerprint media 1 viewport/1 tema por vez (injeção manual do
// SNIPPET no console, salvar 2 JSON, --compare), o harness AUTOMATIZA a matriz: dirige o
// Playwright (já é dep — @playwright/test) por proto×prod em N viewports × M temas, injeta o
// SNIPPET em cada célula, e roda o comparador por célula. Destrava RESPONSIVO (o eixo que era
// "1 viewport por run") e prepara ESTADOS (hover/focus — TODO Onda 3a.2 abaixo).
//
// Reusa a infra do smoke visual (#3956, scripts/screen-smoke/smoke.mjs): mesmo chromium, mesmo
// padrão de login por form, mesmo page.evaluate. ZERO dependência nova (Onda 3b — backstop
// perceptual SSIM pra ícones/sparklines sem âncora — fica FORA: puxa dep de imagem → exige ADR).
//
// MODOS:
//   node prototipo-ui/fingerprint-harness.mjs --proto <url> --prod <url> [--viewports 1280,1440]
//        [--themes light,dark] [--out dir] [--user U --pass P] [--sel-proto CSS] [--sel-prod CSS]
//     → captura a matriz nos 2 lados, compara célula-a-célula, imprime relatório + verdito por
//       célula + agregado (qual célula tem regressão que as outras não têm). Exit 1 se houver
//       DIVERGE/triagem em qualquer célula. NÃO substitui o olho do Wagner — acha as diferenças.
//   node prototipo-ui/fingerprint-harness.mjs --selftest
//     → prova a ORQUESTRAÇÃO (matriz + pareamento por célula + agregação + regressão viewport/
//       tema-específica) com capturas fixas injetadas, SEM browser (a cola Playwright espelha o
//       smoke.mjs comprovado). O selftest do comparador em si vive em style-fingerprint.mjs.
//
// ⚠ EVIDÊNCIA (Tier 0): rodar --selftest prova a orquestração, NÃO prova a captura real. Uma
// corrida live proto×prod (com screenshot/relatório) é obrigatória antes de declarar "funciona
// end-to-end" — igual o fingerprint sempre foi rodado à mão. Este arquivo não faz essa alegação.

import { comparar, veredictoNL, triagemSO, SNIPPET } from './style-fingerprint.mjs';

export function chaveCelula(viewport, tema) { return `${viewport}|${tema}`; }

// orquestra: recebe os mapas de fingerprint {cellKey -> fp} dos dois lados e compara célula a
// célula (só as células presentes NOS DOIS). Devolve uma linha por célula com o veredito agregado.
export function orquestrar(protoMap, prodMap) {
  const celulas = [];
  const chaves = [...new Set([...Object.keys(protoMap || {}), ...Object.keys(prodMap || {})])].sort();
  for (const cell of chaves) {
    const a = protoMap?.[cell], b = prodMap?.[cell];
    if (!a || !b) { celulas.push({ cell, status: a ? 'SO_PROTO_CELL' : 'SO_PROD_CELL', tally: {}, verdito: 'célula presente só de um lado (captura faltou)', rows: [] }); continue; }
    const { rows, tally } = comparar(a, b);
    celulas.push({ cell, status: 'COMPARADA', tally, verdito: veredictoNL(rows), triagem: triagemSO(rows).length, rows });
  }
  return celulas;
}

// agrega a matriz: soma por veredito, e — o payoff do multi-viewport — detecta REGRESSÃO
// VIEWPORT/TEMA-ESPECÍFICA: uma célula com DIVERGE/triagem que outra(s) NÃO têm (ex.: quebra só
// no 1280 da Larissa, ou só no dark). É o que o "1 viewport por run" nunca via.
export function agregar(celulas) {
  const totais = {};
  for (const c of celulas) for (const [k, v] of Object.entries(c.tally || {})) totais[k] = (totais[k] || 0) + v;
  const comProblema = celulas.filter((c) => (c.tally?.DIVERGE || 0) > 0 || (c.triagem || 0) > 0 || c.status !== 'COMPARADA');
  const limpas = celulas.filter((c) => c.status === 'COMPARADA' && !(c.tally?.DIVERGE > 0) && !(c.triagem > 0));
  // regressão específica = tem célula limpa E célula com problema → o problema é de UM recorte.
  const especifica = comProblema.length > 0 && limpas.length > 0;
  return {
    totais,
    celulasComProblema: comProblema.map((c) => c.cell),
    celulasLimpas: limpas.map((c) => c.cell),
    regressaoEspecifica: especifica,
    ok: comProblema.length === 0,
  };
}

// ── selftest da orquestração (sem browser) ─────────────────────────────────────
function selftest() {
  const mkFp = (tema, over = {}) => ({
    tema,
    elementos: [
      { tag: 'button', texto: 'Salvar', w: 100, h: 32, xnorm: 0.1, ynorm: 0.1, linhas: 1, overflowX: false,
        fontSize: '14px', fontWeight: '500', letterSpacing: 'normal', lineHeight: '20px', textTransform: 'none',
        fontFamily: 'Inter', color: 'oklch(0.9 0 0)', bgEfetivo: 'oklch(0.5 0.1 295)', bgProprio: 'oklch(0.5 0.1 295)',
        bgImage: 'none', radius: '6px', borderW: '1px', borderColor: 'oklch(0.45 0.15 295)', boxShadow: 'none',
        padding: '6px 10px 6px 10px', opacity: '1', transform: 'none', display: 'inline-flex', ...over },
    ],
    divisorias: [], containers: [], compostos: [],
  });
  // matriz 2 viewports × 2 temas. TODAS as células idênticas, MENOS 1280|dark: lá o botão
  // "Salvar" quebrou em 2 linhas na prod (regressão que só aparece no 1280 escuro).
  const proto = {
    '1440|light': mkFp('light'), '1440|dark': mkFp('dark'),
    '1280|light': mkFp('light'), '1280|dark': mkFp('dark'),
  };
  const prod = {
    '1440|light': mkFp('light'), '1440|dark': mkFp('dark'),
    '1280|light': mkFp('light'), '1280|dark': mkFp('dark', { linhas: 2, w: 60, h: 44 }),
  };
  const celulas = orquestrar(proto, prod);
  const ag = agregar(celulas);
  const cell = (k) => celulas.find((c) => c.cell === k);
  let fails = 0;
  const check = (label, got, exp) => {
    const ok = JSON.stringify(got) === JSON.stringify(exp);
    if (!ok) fails++;
    console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${label} → esperado ${JSON.stringify(exp)}, obtido ${JSON.stringify(got)}`);
  };
  check('4 células comparadas (2 viewports × 2 temas)', celulas.length, 4);
  check('células limpas não acusam', (cell('1440|light').tally.DIVERGE || 0), 0);
  check('1280|dark acusa a quebra (regra 7 linhas)', (cell('1280|dark').tally.DIVERGE || 0), 1);
  check('só 1280|dark tem problema (regressão específica de recorte)', ag.celulasComProblema, ['1280|dark']);
  check('agrega detecta regressão viewport/tema-específica', ag.regressaoEspecifica, true);
  check('agregado NÃO ok (tem regressão)', ag.ok, false);
  // célula faltando de um lado (captura falhou) = SO_*_CELL, não silêncio.
  const proto2 = { '1280|light': mkFp('light') };
  const prod2 = {};
  const c2 = orquestrar(proto2, prod2);
  // célula existe só no proto (a captura da prod faltou) → SO_PROTO_CELL, não silêncio.
  check('captura faltando vira célula SO_ (não some)', c2[0].status, 'SO_PROTO_CELL');
  // o SNIPPET importado é a MESMA fonte (não fork): confere que veio string e tem o vetor novo.
  const snipOk = typeof SNIPPET === 'string' && SNIPPET.includes('ynorm') && SNIPPET.includes('compostos');
  if (!snipOk) fails++;
  console.log(`  [${snipOk ? 'PASS' : 'FAIL'}] SNIPPET importado do módulo (fonte única, com vetor Onda 1/2)`);
  console.log(fails ? `\nHARNESS SELFTEST FALHOU (${fails})` : '\nHARNESS SELFTEST OK — orquestração provada (matriz + pareamento + agregação).');
  process.exit(fails ? 1 : 0);
}

// ── captura live via Playwright (espelha scripts/screen-smoke/smoke.mjs) ────────
function parseArgs(argv) {
  const a = {};
  for (let i = 0; i < argv.length; i++) {
    const k = argv[i];
    if (k.startsWith('--')) { const v = argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[++i] : true; a[k.slice(2)] = v; }
  }
  return a;
}

async function capturarLado(context, url, viewports, temas, selVisao) {
  const map = {};
  const page = await context.newPage();
  try {
    for (const vp of viewports) {
      await page.setViewportSize({ width: vp, height: 900 });
      for (const tema of temas) {
        await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
        // força o tema (best-effort: data-theme no <html> + localStorage — o SNIPPET lê data-theme).
        await page.evaluate((t) => { try { localStorage.setItem('theme', t); } catch {} document.documentElement.setAttribute('data-theme', t); }, tema);
        if (selVisao) { try { await page.locator(selVisao).first().click({ timeout: 5000 }); } catch {} }
        await page.waitForTimeout(400); // deixa o restyle reativo assentar
        const json = await page.evaluate(SNIPPET.trim());
        map[chaveCelula(vp, tema)] = JSON.parse(json);
      }
    }
  } finally { await page.close(); }
  return map;
}

async function login(context, base, user, pass) {
  if (!user || !pass) return;
  const page = await context.newPage();
  try {
    await page.goto(`${base}/login`, { waitUntil: 'networkidle', timeout: 45000 });
    await page.locator('input[name="username"], input[name="email"]').first().fill(user);
    await page.locator('input[type="password"]').first().fill(pass);
    await page.getByRole('button', { name: /entrar|login|sign in/i }).click();
    await page.waitForLoadState('networkidle', { timeout: 45000 });
  } catch (e) { console.error('[harness] login falhou:', e.message); } finally { await page.close(); }
}

async function live(args) {
  const { chromium } = await import('@playwright/test');
  const viewports = String(args.viewports || '1280,1440').split(',').map((s) => parseInt(s.trim(), 10)).filter(Boolean);
  const temas = String(args.themes || 'light,dark').split(',').map((s) => s.trim()).filter(Boolean);
  if (!args.proto || !args.prod) { console.error('uso: --proto <url> --prod <url> [--viewports 1280,1440] [--themes light,dark]'); process.exit(2); }
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  try {
    if (args.user && args.pass) { const origin = new URL(args.prod).origin; await login(context, origin, args.user, args.pass); }
    console.log(`[harness] capturando proto (${args.proto}) e prod (${args.prod}) em ${viewports.join('/')} × ${temas.join('/')} …`);
    const protoMap = await capturarLado(context, args.proto, viewports, temas, args['sel-proto']);
    const prodMap = await capturarLado(context, args.prod, viewports, temas, args['sel-prod']);
    const celulas = orquestrar(protoMap, prodMap);
    const ag = agregar(celulas);
    for (const c of celulas) {
      const t = Object.entries(c.tally).map(([k, v]) => `${k}=${v}`).join(' · ') || c.status;
      console.log(`\n  ▸ ${c.cell}  [${t}]`);
      console.log(`    verdito: ${c.verdito}`);
    }
    console.log(`\n  ═ agregado: ${ag.ok ? 'FIEL em toda a matriz' : `problema em ${ag.celulasComProblema.join(', ')}`}`);
    if (ag.regressaoEspecifica) console.log(`  ⚠ regressão de RECORTE — ${ag.celulasComProblema.join(', ')} quebra(m) onde ${ag.celulasLimpas.join(', ')} está(ão) fiel(is).`);
    process.exit(ag.ok ? 0 : 1);
  } finally { await context.close(); await browser.close(); }
}

const argv = process.argv.slice(2);
if (argv.includes('--selftest')) selftest();
else live(parseArgs(argv));
