#!/usr/bin/env node
// fingerprint-harness.mjs — Onda 3a do roadmap estado-da-arte (2026-07-08): DRIVER do
// style-fingerprint. Onde o fingerprint media 1 viewport/1 tema por vez (injeção manual do
// SNIPPET no console, salvar 2 JSON, --compare), o harness AUTOMATIZA a matriz: dirige o
// Playwright (já é dep — @playwright/test) por proto×prod em N viewports × M temas, injeta o
// SNIPPET em cada célula, e roda o comparador por célula. Destrava RESPONSIVO (o eixo que era
// "1 viewport por run", incl. MOBILE 375 — altura vira de telefone) e, na Onda 3a.2 (2026-07-17,
// chip C-F2), os ESTADOS hover/focus/active: força a pseudo-classe por elemento interativo
// (Playwright hover/focus/mouse-down) e anexa, por elemento, o CONJUNTO de propriedades que
// REAGEM (a afordância) — o comparador (compararEstados) diffa esses conjuntos entre proto×prod.
//
// ⚠ FRONTEIRA (ADR 0290): isto é LOCAL/dispatch — captura proto×prod NA MÁQUINA, NUNCA render
// pareado em CI (render pareado passa verde quando os dois lados quebram). É o loop de FIDELIDADE
// proto×prod (design-to-code), distinto do pixel-VRT required (visual-regression.yml, prod×própria).
//
// Reusa a infra do smoke visual (#3956, scripts/screen-smoke/smoke.mjs): mesmo chromium, mesmo
// padrão de login por form, mesmo page.evaluate. ZERO dependência nova (Onda 3b — backstop
// perceptual SSIM pra ícones/sparklines sem âncora — fica FORA: puxa dep de imagem → exige ADR).
//
// MODOS:
//   node prototipo-ui/fingerprint-harness.mjs --proto <url> --prod <url> [--viewports 375,1280,1440]
//        [--themes light,dark] [--out dir] [--user U --pass P] [--sel-proto CSS] [--sel-prod CSS]
//        [--estados] [--root CSS]
//     → captura a matriz nos 2 lados, compara célula-a-célula, imprime relatório + verdito por
//       célula + agregado (qual célula tem regressão que as outras não têm). Exit 1 se houver
//       DIVERGE/triagem em qualquer célula. NÃO substitui o olho do Wagner — acha as diferenças.
//       `--estados` (opt-in, mais lento) captura hover/focus/active por elemento interativo;
//       `--root <CSS>` escopa a captura à REGIÃO da tela (mata o ruído de shell/sidebar — RUNBOOK).
//   node prototipo-ui/fingerprint-harness.mjs --selftest
//     → prova a ORQUESTRAÇÃO (matriz + pareamento por célula + agregação + regressão viewport/
//       tema-específica) com capturas fixas injetadas, SEM browser (a cola Playwright espelha o
//       smoke.mjs comprovado). O selftest do comparador em si vive em style-fingerprint.mjs.
//
// ⚠ EVIDÊNCIA (Tier 0): rodar --selftest prova a orquestração, NÃO prova a captura real. Uma
// corrida live proto×prod (com screenshot/relatório) é obrigatória antes de declarar "funciona
// end-to-end" — igual o fingerprint sempre foi rodado à mão. Este arquivo não faz essa alegação.

import { pathToFileURL } from 'node:url';
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
  const mkFp = (tema, over = {}, fpOver = {}) => ({
    tema,
    elementos: [
      { tag: 'button', texto: 'Salvar', w: 100, h: 32, xnorm: 0.1, ynorm: 0.1, linhas: 1, overflowX: false,
        fontSize: '14px', fontWeight: '500', letterSpacing: 'normal', lineHeight: '20px', textTransform: 'none',
        fontFamily: 'Inter', color: 'oklch(0.9 0 0)', bgEfetivo: 'oklch(0.5 0.1 295)', bgProprio: 'oklch(0.5 0.1 295)',
        bgImage: 'none', radius: '6px', borderW: '1px', borderColor: 'oklch(0.45 0.15 295)', boxShadow: 'none',
        padding: '6px 10px 6px 10px', opacity: '1', transform: 'none', display: 'inline-flex', ...over },
    ],
    divisorias: [], containers: [], compostos: [],
    ...fpOver,
  });
  // Onda 3a.2 — afordância de estado do botão: reage no hover (bg+sombra) e no active. `estOk` = o
  // proto (e a prod íntegra); `estFlat` = a prod que PERDEU a reação de hover (afordância sumiu).
  const estOk = [{ tag: 'button', texto: 'Salvar', hover: ['bg', 'boxShadow'], focus: ['outline'], active: ['bg', 'boxShadow'] }];
  const estFlat = [{ tag: 'button', texto: 'Salvar', hover: [], focus: ['outline'], active: [] }];
  // matriz 3 viewports (incl. MOBILE 375) × 2 temas. TODAS idênticas, MENOS:
  //  · 1280|dark — botão "Salvar" quebrou em 2 linhas na prod (regressão de recorte, viewport/tema).
  //  · 375|light — a afordância de HOVER do botão SUMIU na prod (regressão de ESTADO, só no mobile).
  const proto = {}, prod = {};
  for (const vp of [1440, 1280, 375]) for (const tema of ['light', 'dark']) {
    const k = `${vp}|${tema}`;
    proto[k] = mkFp(tema, {}, { estados: estOk });
    prod[k] = mkFp(tema, {}, { estados: estOk });
  }
  prod['1280|dark'] = mkFp('dark', { linhas: 2, w: 60, h: 44 }, { estados: estOk });
  prod['375|light'] = mkFp('light', {}, { estados: estFlat }); // hover sumiu SÓ no mobile
  const celulas = orquestrar(proto, prod);
  const ag = agregar(celulas);
  const cell = (k) => celulas.find((c) => c.cell === k);
  let fails = 0;
  const check = (label, got, exp) => {
    const ok = JSON.stringify(got) === JSON.stringify(exp);
    if (!ok) fails++;
    console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${label} → esperado ${JSON.stringify(exp)}, obtido ${JSON.stringify(got)}`);
  };
  check('6 células comparadas (3 viewports incl. mobile 375 × 2 temas)', celulas.length, 6);
  check('células limpas não acusam (estados idênticos NÃO falso-positivam)', (cell('1440|light').tally.DIVERGE || 0), 0);
  check('1280|dark acusa a quebra (regra 7 linhas)', (cell('1280|dark').tally.DIVERGE || 0), 1);
  check('375|light acusa afordância de hover sumida (estado, só no mobile)', (cell('375|light').tally.DIVERGE || 0), 1);
  check('só 1280|dark e 375|light têm problema (regressão de recorte)', ag.celulasComProblema, ['1280|dark', '375|light']);
  check('agrega detecta regressão viewport/tema-específica (mobile-only + tema-only)', ag.regressaoEspecifica, true);
  check('agregado NÃO ok (tem regressão)', ag.ok, false);
  // a linha ESTADO nomeia o estado que sumiu (hover) — prova que os estados FLUEM pro comparador.
  const rEst = cell('375|light').rows.find((r) => String(r.chave).startsWith('ESTADO '));
  check('375|light: linha ESTADO presente nomeando o hover', !!(rEst && rEst.campos.some((c) => c.startsWith('hover:'))), true);
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

// ── Onda 3a.2 (2026-07-17, chip C-F2) — captura de ESTADOS hover/focus/active ───
// O SNIPPET (in-page) mede o estado DEFAULT: dentro de page.evaluate JS NÃO consegue forçar
// :hover (pseudo-classe só existe sob interação real). Por isso os estados vivem AQUI, no driver:
// o Playwright HOVERA/FOCA/PRESSIONA cada elemento interativo de verdade (o que o usuário vê) e
// relê o computed style. Anexa, por elemento, o CONJUNTO de propriedades que MUDARAM vs o default
// (a "afordância"). O comparador (compararEstados, style-fingerprint) casa por texto+tag e diffa
// os conjuntos: proto reage nas MESMAS propriedades que a prod? Botão que escurece+eleva no proto
// e não faz NADA na prod ⇒ DIVERGE. Cego ao VALOR base da cor (o default pass já é dono disso).
const ESTADO_SEL = 'button, a[href], [role=tab], [role=button], input:not([type=hidden]), select, textarea, summary';
const PROPS_ESTADO = ['bg', 'color', 'borderColor', 'boxShadow', 'outline', 'opacity', 'transform', 'textDecoration'];
// lê o sub-vetor de estado do elemento (as props que uma afordância costuma mexer + o anel de foco).
function subvetorEstado(handle) {
  return handle.evaluate((el) => {
    const c = getComputedStyle(el);
    return {
      bg: c.backgroundColor, color: c.color, borderColor: c.borderTopColor, boxShadow: c.boxShadow,
      outline: `${c.outlineStyle} ${c.outlineWidth} ${c.outlineColor}`, // anel de foco (comum via outline)
      opacity: c.opacity, transform: c.transform, textDecoration: c.textDecorationLine,
    };
  });
}
function propsMudaram(base, estado) {
  const out = [];
  for (const p of PROPS_ESTADO) if (String(base[p]) !== String(estado[p])) out.push(p);
  return out;
}
// captura, por elemento interativo visível, o conjunto de props que reagem em hover/focus/active.
// Cap 60 elementos (dedup por tag|texto) pra limitar o custo — é dispatch local, não CI.
async function capturarEstados(page, rootSel) {
  const raiz = rootSel ? page.locator(rootSel).first().locator(ESTADO_SEL) : page.locator(ESTADO_SEL);
  let handles = [];
  try { handles = await raiz.elementHandles(); } catch { return []; }
  const estados = [];
  const vistos = new Set();
  for (const h of handles) {
    if (estados.length >= 60) { await h.dispose().catch(() => {}); continue; }
    try {
      if (!(await h.isVisible())) continue;
      const info = await h.evaluate((el) => ({ tag: el.tagName.toLowerCase(), texto: (el.textContent || '').trim().slice(0, 80) }));
      if (info.texto.length < 2 && !['input', 'select', 'textarea'].includes(info.tag)) continue;
      const k = info.tag + '|' + info.texto;
      if (vistos.has(k)) continue;
      vistos.add(k);
      const base = await subvetorEstado(h);
      let hover = [], focus = [], active = [];
      // hover real (move o mouse pro elemento); depois limpa (move pra 0,0).
      try { await h.hover({ timeout: 1500 }); hover = propsMudaram(base, await subvetorEstado(h)); } catch {}
      try { await page.mouse.move(0, 0); } catch {}
      // focus real (programático — ElementHandle.focus() não recebe opções); depois blur.
      try { await h.focus(); focus = propsMudaram(base, await subvetorEstado(h)); } catch {}
      try { await h.evaluate((el) => el.blur && el.blur()); } catch {}
      // active = pressionado (mouse-down segurado). Inclui o hover (elemento pressionado também está
      // sob o cursor) — é o estado visual real do "pressed"; documentado como delta-vs-default.
      try {
        const box = await h.boundingBox();
        if (box) {
          await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
          await page.mouse.down();
          active = propsMudaram(base, await subvetorEstado(h));
          await page.mouse.up();
        }
      } catch { try { await page.mouse.up(); } catch {} }
      try { await page.mouse.move(0, 0); } catch {}
      estados.push({ tag: info.tag, texto: info.texto, hover, focus, active });
    } catch { /* elemento sumiu/destacou entre passos — pula */ }
    finally { await h.dispose().catch(() => {}); }
  }
  return estados;
}

async function capturarLado(context, url, viewports, temas, selVisao, comEstados, rootSel) {
  const map = {};
  const page = await context.newPage();
  try {
    for (const vp of viewports) {
      // altura viewport-aware: MOBILE (<600) usa altura de telefone (812), desktop 900. Media
      // queries disparam por LARGURA — 375 exercita o layout mobile sem precisar de device emul.
      await page.setViewportSize({ width: vp, height: vp < 600 ? 812 : 900 });
      for (const tema of temas) {
        await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
        // força o tema (best-effort: data-theme no <html> + localStorage — o SNIPPET lê data-theme).
        await page.evaluate((t) => { try { localStorage.setItem('theme', t); } catch {} document.documentElement.setAttribute('data-theme', t); }, tema);
        // --root escopa a captura à região da tela (o SNIPPET lê window.__ROOT__; RUNBOOK "região≠página").
        if (rootSel) { try { await page.evaluate((r) => { window.__ROOT__ = r; }, rootSel); } catch {} }
        if (selVisao) { try { await page.locator(selVisao).first().click({ timeout: 5000 }); } catch {} }
        await page.waitForTimeout(400); // deixa o restyle reativo assentar
        const fp = JSON.parse(await page.evaluate(SNIPPET.trim()));
        // estados (opt-in via --estados): força hover/focus/active por elemento e anexa a afordância.
        if (comEstados) fp.estados = await capturarEstados(page, rootSel);
        map[chaveCelula(vp, tema)] = fp;
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
  const comEstados = !!args.estados; // opt-in: hover/focus/active por elemento (mais lento)
  const rootSel = (typeof args.root === 'string' && args.root) || null; // escopo de região (RUNBOOK)
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  try {
    if (args.user && args.pass) { const origin = new URL(args.prod).origin; await login(context, origin, args.user, args.pass); }
    console.log(`[harness] capturando proto (${args.proto}) e prod (${args.prod}) em ${viewports.join('/')} × ${temas.join('/')}${comEstados ? ' × estados(hover/focus/active)' : ''}${rootSel ? ` · root=${rootSel}` : ''} …`);
    const protoMap = await capturarLado(context, args.proto, viewports, temas, args['sel-proto'], comEstados, rootSel);
    const prodMap = await capturarLado(context, args.prod, viewports, temas, args['sel-prod'], comEstados, rootSel);
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

// Guard "sou o entrypoint?" (mesmo pattern do style-fingerprint Onda 3): render-proto-baseline
// IMPORTA este módulo (chaveCelula/orquestrar) — sem o guard, o import dispararia live() com o
// argv do importador.
const ehEntrypoint = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
const argv = process.argv.slice(2);
if (!ehEntrypoint) { /* importado como módulo: não roda CLI */ }
else if (argv.includes('--selftest')) selftest();
else live(parseArgs(argv));
