#!/usr/bin/env node
// a11y-alvo.mjs — PR-A4 do plano COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO: axe-core + as sondas que
// o axe nao faz, medidos NOS DOIS LADOS (protótipo e tela), com ratchet (so desce).
//
// ── O QUE ESTE ARQUIVO **NAO** E (LC-19 — 16 ocorrencias no ledger) ────────────────────────
// a11y neste repo ja tem 3 fases COM dono, e nenhuma delas e reaberta aqui:
//   Fase 1  `scripts/a11y-ratchet.mjs` + a11y-gate.yml — jsx-a11y ESTATICO no lint. Nao renderiza.
//   Fase 2  `tests/a11y-primitives.test.tsx` + a11y-axe-gate.yml — axe em jsdom, componentes canon.
//   Fase 3  `tests/Browser/CoreScreens/A11yAxeBrowserTest.php` — axe em Chromium real, nas telas
//           declaradas com "a11y": true em visreg-screens.json, piso level 0 (CRITICAL).
// O vao que sobra, e que e o objeto deste arquivo, sao DUAS coisas que nenhuma das tres cobre:
//   (a) o LADO PROTOTIPO — as tres medem so o produto. A regra do protocolo e que o que falha no
//       prototipo se corrige no BUILD do prototipo, e nao vira pedido pro Code; sem medir aquele
//       lado, nao da pra saber de quem e o defeito.
//   (b) as 7 SONDAS que o axe nao faz (ver docblock de a11y-sondas.mjs).
// O axe roda aqui AO LADO das sondas — para dar o mesmo veredito nos dois lados com uma regua so,
// nao pra competir com a Fase 3. Esta lane e ADVISORY; a Fase 3 continua sendo a que fala do prod.
//
// ── FRONTEIRA ADR 0290 (render de prototipo em CI foi RECUSADO) ────────────────────────────
// A 0290 matou o gate que renderiza os dois lados e COMPARA, porque "passa VERDE quando os dois
// lados renderizam erro (tela de login vs CDN 429)". Este arquivo NAO compara os dois lados — mede
// cada um contra o proprio baseline — mas herda o risco de origem: o shell do prototipo depende de
// 3 CDNs (React, Babel, Tailwind) e, se elas caem, o DOM fica vazio e as sondas devolvem ZERO
// violacoes, que e indistinguivel de "esta tudo certo" (LC-13, e o `0 failed` de suite que nao
// rodou). Duas travas, por isso:
//   1. `--medir` recusa rodar sob CI (exit 4), reusando `renderPermitido()` do dono do render.
//   2. Guard de sanidade: DOM abaixo de `--min-nos` (default 150) ou axe que nao injetou =>
//      exit 2 NAO MEDI. "Nao consegui medir" nunca vira um estado do objeto medido (§5 2026-07-29).
// O que roda em CI e o `--check`: HERMETICO, compara dois JSON versionados, zero browser, zero rede.
//
// ── MODOS ──────────────────────────────────────────────────────────────────────────────────
//   --medir --lado proto --rota <id> [--porta 0]      mede o prototipo servido de prototipo-ui/cowork/Wagner
//   --medir --lado tela  --url <url>                  mede uma tela ja servida (preview/local autenticado)
//     [--out <arq.json>]  [--min-nos <n>]  [--viewport 1280]
//   --check [--baseline <arq>] [--atual <arq>]        RATCHET hermetico: so pode cair. Sem browser.
//   --baseline-write                                   (re)grava o baseline a partir de --atual
//   --selftest [--browser]                             partes puras · com --browser, o bite-test real
//
// Exit: 0 limpo · 1 ratchet reprovou · 2 NAO CONSEGUI MEDIR · 4 render proibido no ambiente.

/* global window, document, localStorage */
// ^ os identificadores acima NAO existem neste processo: eles aparecem dentro das closures
// entregues a `page.evaluate()`, que o Playwright serializa e executa NA PAGINA. Sem esta
// linha o eslint os conta como no-undef e infla o config/eslint-baseline.json (que so desce).
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, resolve, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { SONDAS_SOURCE } from './a11y-sondas.mjs';
import { contraste, exigirSanidade } from './a11y-contraste.mjs';
import { servirEstatico, renderPermitido } from '../design/render-proto-baseline.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..', '..');
const MIRROR = join(ROOT, 'prototipo-ui', 'cowork', 'Wagner');
const BASELINE = join(ROOT, 'governance', 'a11y-alvo-baseline.json');
const require_ = createRequire(import.meta.url);

const argv = process.argv.slice(2);
const flag = (n) => argv.includes(n);
const val = (n, d = null) => { const i = argv.indexOf(n); return i >= 0 && argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[i + 1] : d; };
const naoMedi = (msg) => Object.assign(new Error(msg), { naoMedi: true });

/* ── classificacao das sondas: GATE x REPORT ──────────────────────────────────────────────
 * REPORT nao entra no ratchet. Duas razoes distintas, e nenhuma delas e "o numero e alto":
 *  - S7 (alvo de toque): o proprio plano lista "alvo de toque em ERP denso" em **O que NAO se
 *    automatiza — decisoes [W]**. Gatear isso seria automatizar julgamento que o dono reservou.
 *  - S2 nao-grave (svg anonimo em controle que JA tem nome): e ruido para leitor de tela, nao
 *    perda de capacidade — o controle continua nomeado. Fica visivel sem travar merge.
 *  - S6 (contraste): o plano pedia sonda propria porque "o axe nao faz contraste OKLCH". MEDIDO
 *    em 2026-09-17 e a premissa e FALSA: axe-core 4.12 resolve oklch corretamente (na sonda de
 *    3 casos conhecidos ele devolveu 1.21 e 4.06 onde esta matriz devolve 1.21 e 4.07, e deixou
 *    passar o par de contraste 21). Logo o dono do contraste e o axe — manter as duas no ratchet
 *    seria contagem DUPLA da mesma violacao (§5 2026-07-09, duplica regua consolidada). S6 fica
 *    REPORT, pelo detalhe por-elemento que o axe nao da (ele agrega em `nodes`), e o modulo do
 *    PR-A2 ganha a funcao que ninguem mais faz: ser o CONTROLE POSITIVO do axe (ver abaixo).
 * O corte esta aqui, num lugar so, porque espalha-lo pelas sondas faria o criterio sumir. */
export const SONDAS_REPORT = new Set(['S7-alvo-pequeno', 'S6-contraste']);
export function ehGate(achado) {
  if (SONDAS_REPORT.has(achado.sonda)) return false;
  if (achado.sonda === 'S2-svg-anonimo') return achado.grave === true;
  return true;
}

/* ── S6: o calculo de contraste roda AQUI, sob a sanidade abortiva do PR-A2 ───────────────── */
export function violacoesDeContraste(pares) {
  exigirSanidade();                       // aborta antes de medir; nunca devolve numero plausivel
  const out = [];
  for (const p of pares) {
    const r = contraste(p.cor, p.fundo);
    if (r === null) continue;             // cor nao medivel: nao medi != esta bom
    const grande = p.px >= 24 || (p.px >= 18.66 && p.peso >= 700);
    const minimo = grande ? 3 : 4.5;
    if (r + 0.005 < minimo) {
      out.push({ sonda: 'S6-contraste', seletor: p.seletor, texto: p.texto,
        motivo: `contraste ${r.toFixed(2)} abaixo do minimo AA ${minimo} (${Math.round(p.px)}px peso ${p.peso})` });
    }
  }
  return out;
}

/* ── contagem canonica: a unica forma de dizer "quantas violacoes" ────────────────────────── */
export function resumir(achados) {
  const porSonda = {};
  let gate = 0, report = 0;
  for (const a of achados) {
    const k = a.sonda;
    if (!porSonda[k]) porSonda[k] = { gate: 0, report: 0 };
    if (ehGate(a)) { porSonda[k].gate++; gate++; } else { porSonda[k].report++; report++; }
  }
  return { total_gate: gate, total_report: report, por_sonda: Object.keys(porSonda).sort()
    .reduce((o, k) => { o[k] = porSonda[k]; return o; }, {}) };
}

/* ── RATCHET — so desce. Compara por SONDA, nao so o total: um total estavel pode esconder
 * uma sonda subindo enquanto outra cai, e e a sonda que aponta o defeito. ─────────────────── */
export function ratchet(baseline, atual) {
  const falhas = [], ganhos = [];
  const sondas = new Set([...Object.keys(baseline.por_sonda || {}), ...Object.keys(atual.por_sonda || {})]);
  for (const s of [...sondas].sort()) {
    const b = (baseline.por_sonda || {})[s]?.gate ?? 0;
    const a = (atual.por_sonda || {})[s]?.gate ?? 0;
    if (a > b) falhas.push(`${s}: ${b} -> ${a} (+${a - b})`);
    else if (a < b) ganhos.push(`${s}: ${b} -> ${a} (-${b - a})`);
  }
  return { ok: falhas.length === 0, falhas, ganhos };
}

/* ── medicao ──────────────────────────────────────────────────────────────────────────────── */
async function abrir(url, { rota = null, viewport = 1280 } = {}) {
  let chromium;
  try { ({ chromium } = await import('@playwright/test')); }
  catch { throw naoMedi('@playwright/test indisponivel — rode `npm ci`'); }
  let browser;
  try { browser = await chromium.launch(); }
  catch (e) { throw naoMedi(`chromium nao instalado: ${e.message.slice(0, 160)}`); }
  const ctx = await browser.newContext({ viewport: { width: Number(viewport), height: 900 } });
  // a rota do shell e lida de localStorage NO BOOT do app.jsx — tem que estar la ANTES (mesma
  // mecanica do render-proto-baseline; localStorage nao existe em file://, dai servir por http).
  if (rota) await ctx.addInitScript((r) => { try { localStorage.setItem('oimpresso.route', r); } catch { /* file:// nao tem storage: a rota cai no default do app */ } }, rota);
  const page = await ctx.newPage();
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  return { browser, page };
}

// Nunca medir durante o lazy-load: duas leituras iguais do nº de nos (§5 2026-08-24).
async function esperarEstavel(page, quietoMs = 600) {
  await page.waitForFunction(() => window.__oiLazyDone === true, null, { timeout: 8000 }).catch(() => {});
  const PASSO = 200, precisa = Math.max(2, Math.ceil(quietoMs / PASSO));
  let anterior = -1, iguais = 0;
  for (let i = 0; i < 30 + precisa; i++) {
    const atual = await page.evaluate(() => document.querySelectorAll('*').length);
    iguais = atual === anterior && atual > 0 ? iguais + 1 : 0;
    if (iguais + 1 >= precisa) return atual;
    anterior = atual;
    await page.waitForTimeout(PASSO);
  }
  throw naoMedi(`DOM nao ficou quieto por ${quietoMs} ms — a medida seria retrato de meio-caminho`);
}

/* ── CONTROLE POSITIVO DO AXE (§5 2026-08-01 · 2026-09-16) ─────────────────────────────────
 * "axe rodou e achou N" so vira veredito depois de provar que ELE morde nesta pagina. Injeta um
 * par de contraste com veredito CONHECIDO — calculado pela matriz do PR-A2, nao escrito a mao —
 * e exige que o axe reprove o ruim e aprove o bom. Divergiu: NAO MEDI, nunca "0 violacoes". */
export const PAR_CONTROLE = { bom: 'oklch(1 0 0)', ruim: 'oklch(0.22 0 0)', fundo: 'oklch(0 0 0)' };

export async function conferirAxe(page) {
  exigirSanidade();                                       // a matriz do A2 esta sa?
  const cBom = contraste(PAR_CONTROLE.bom, PAR_CONTROLE.fundo);
  const cRuim = contraste(PAR_CONTROLE.ruim, PAR_CONTROLE.fundo);
  if (!(cBom >= 4.5 && cRuim < 4.5)) throw naoMedi(`par de controle mal escolhido (bom=${cBom}, ruim=${cRuim})`);
  const r = await page.evaluate(async (par) => {
    const box = document.createElement('div');
    box.id = '__a11y_controle';
    // DENTRO da viewport de proposito: o axe PULA elemento fora de tela, e um controle que
    // ele nunca avalia daria 'nao acusou o ruim' sempre (medido 2026-09-17 com left:-9999px).
    box.setAttribute('style', 'position:fixed;left:0;top:0;z-index:2147483647;padding:2px;background:' + par.fundo);
    box.innerHTML = '<p id="__ctrl_bom" style="font-size:16px;color:' + par.bom + '">controle bom</p>'
      + '<p id="__ctrl_ruim" style="font-size:16px;color:' + par.ruim + '">controle ruim</p>';
    document.body.appendChild(box);
    const res = await window.axe.run(box, { runOnly: ['color-contrast'], resultTypes: ['violations'] });
    const alvos = res.violations.flatMap((v) => v.nodes.map((n) => String(n.target)));
    box.remove();
    return { pegouRuim: alvos.some((a) => a.includes('__ctrl_ruim')), acusouBom: alvos.some((a) => a.includes('__ctrl_bom')) };
  }, PAR_CONTROLE);
  if (!r.pegouRuim) throw naoMedi(`axe NAO acusou o par de controle ruim (contraste ${cRuim.toFixed(2)}) — o instrumento nao esta medindo contraste nesta pagina`);
  if (r.acusouBom) throw naoMedi(`axe acusou o par de controle BOM (contraste ${cBom.toFixed(2)}) — instrumento inconsistente`);
  return { par_de_controle_ok: true, controle_bom: Number(cBom.toFixed(2)), controle_ruim: Number(cRuim.toFixed(2)) };
}

async function rodarAxe(page) {
  let fonte;
  try { fonte = readFileSync(require_.resolve('axe-core'), 'utf8'); }
  catch (e) { throw naoMedi(`axe-core nao resolvido: ${e.message.slice(0, 120)}`); }
  await page.addScriptTag({ content: fonte });
  const injetou = await page.evaluate(() => typeof window.axe !== 'undefined');
  if (!injetou) throw naoMedi('axe nao ficou disponivel na pagina apos a injecao');
  return page.evaluate(async () => {
    const r = await window.axe.run(document, { resultTypes: ['violations'] });
    return r.violations.map((v) => ({ sonda: 'AXE-' + v.impact, motivo: v.id + ': ' + v.help,
      seletor: String((v.nodes[0] && v.nodes[0].target) || ['?']).slice(0, 80), texto: '', nodes: v.nodes.length }));
  });
}

export async function medir({ url, rota, lado, minNos = 150, viewport = 1280 }) {
  const { browser, page } = await abrir(url, { rota, viewport });
  try {
    const nos = await esperarEstavel(page);
    // GUARD DE SANIDADE — o que a ADR 0290 ensina: DOM vazio (CDN 429, login, erro de boot) daria
    // "0 violacoes" e passaria por saude. Aqui vira exit 2, que e outra coisa.
    if (nos < minNos) {
      throw naoMedi(`render insuficiente: ${nos} nos (< ${minNos}). Provavel CDN/boot falho — ` +
        `"0 violacoes" aqui seria ausencia de medicao, nao ausencia de defeito.`);
    }
    const bruto = await page.evaluate(SONDAS_SOURCE);
    const axe = await rodarAxe(page);
    const controle = await conferirAxe(page);   // so aqui o "axe achou N" vira veredito
    const achados = [...bruto.achados, ...bruto.alvos, ...violacoesDeContraste(bruto.pares), ...axe];
    return { lado, url, rota: rota || null, viewport: Number(viewport), nos, medido_em: new Date().toISOString().slice(0, 10),
      pares_de_cor_medidos: bruto.pares.length, ...controle, ...resumir(achados), achados };
  } finally { await browser.close(); }
}

export async function medirProto({ rota, porta = 0, ...resto }) {
  if (!existsSync(join(MIRROR, 'oimpresso.com.html'))) throw naoMedi(`shell do prototipo ausente em ${MIRROR}`);
  const srv = await servirEstatico(MIRROR, porta);
  const url = `http://127.0.0.1:${srv.address().port}/oimpresso.com.html`;
  try { return await medir({ url, rota, lado: 'proto', ...resto }); }
  finally { srv.close(); }
}

/* ── selftest ─────────────────────────────────────────────────────────────────────────────── */
const FIXTURE_BOA = `<!doctype html><meta charset="utf-8"><title>a11y boa</title><html lang="pt-BR">
<style>body{background:#fff;color:#111}.cx{cursor:pointer}</style>
<body><main><h1>Relatorio</h1>
<div role="status" aria-live="polite">pronto</div>
<button type="button" aria-label="Fechar"><svg aria-hidden="true" width="24" height="24"></svg></button>
<div role="tablist"><button role="tab" aria-selected="true">Um</button><button role="tab" aria-selected="false">Dois</button></div>
</main><script>window.__oiLazyDone=true;</script></body></html>`;
// UMA violacao a mais que a boa, e so uma: o DIV clicavel sem papel. E o degrau do ratchet.
const FIXTURE_RUIM = FIXTURE_BOA.replace('<h1>Relatorio</h1>', '<h1>Relatorio</h1><div class="cx">Abrir</div>');

async function selftest(comBrowser) {
  const checks = [];
  const ok = (nome, cond, det = '') => checks.push({ nome, ok: !!cond, det });

  ok('sanidade de contraste do A2 passa (6/6)', (() => { try { exigirSanidade(); return true; } catch { return false; } })());
  ok('S7 e REPORT (decisao [W]: alvo de toque nao se automatiza)', !ehGate({ sonda: 'S7-alvo-pequeno' }));
  ok('S2 grave e GATE; S2 nao-grave e REPORT',
    ehGate({ sonda: 'S2-svg-anonimo', grave: true }) && !ehGate({ sonda: 'S2-svg-anonimo', grave: false }));
  ok('ratchet: subir 1 REPROVA', !ratchet({ por_sonda: { X: { gate: 1 } } }, { por_sonda: { X: { gate: 2 } } }).ok);
  ok('ratchet: descer 1 APROVA e registra o ganho', (() => {
    const r = ratchet({ por_sonda: { X: { gate: 2 } } }, { por_sonda: { X: { gate: 1 } } });
    return r.ok && r.ganhos.length === 1;
  })());
  ok('ratchet: sonda NOVA que nasce com violacao REPROVA',
    !ratchet({ por_sonda: {} }, { por_sonda: { Y: { gate: 1 } } }).ok);
  ok('ratchet: total estavel mas sonda SUBINDO reprova (nao mede so o total)',
    !ratchet({ por_sonda: { A: { gate: 2 }, B: { gate: 0 } } }, { por_sonda: { A: { gate: 1 }, B: { gate: 1 } } }).ok);
  ok('REPORT nao entra no ratchet', ratchet(
    { por_sonda: { 'S7-alvo-pequeno': { gate: 0, report: 3 } } },
    { por_sonda: { 'S7-alvo-pequeno': { gate: 0, report: 99 } } }).ok);

  if (comBrowser) {
    const escrever = (nome, html) => { const f = join(tmpdir(), `a11y-${nome}-${process.pid}.html`); writeFileSync(f, html); return pathToFileURL(f).href; };
    const boa = await medir({ url: escrever('boa', FIXTURE_BOA), lado: 'fixture', minNos: 5 });
    const ruim = await medir({ url: escrever('ruim', FIXTURE_RUIM), lado: 'fixture', minNos: 5 });

    // A MORDIDA: mesma pagina + 1 violacao => o ratchet contra a boa reprova.
    const r = ratchet(boa, ruim);
    ok('MORDE: subir 1 violacao deixa o ratchet VERMELHO', !r.ok, r.falhas.join(' | '));
    ok('MORDE: a violacao a mais e o DIV clicavel (S1), nao outra coisa',
      (ruim.por_sonda['S1-clicavel-sem-papel']?.gate ?? 0) - (boa.por_sonda['S1-clicavel-sem-papel']?.gate ?? 0) === 1);
    // CONTROLE NEGATIVO: a pagina boa contra si mesma nao pode reprovar.
    ok('CONTROLE NEGATIVO: pagina boa contra o proprio baseline fica VERDE', ratchet(boa, boa).ok,
      `gate=${boa.total_gate}`);
    ok('CONTROLE NEGATIVO: baixar 1 violacao fica VERDE', ratchet(ruim, boa).ok);
    // GUARD DE SANIDADE: DOM vazio nao pode virar "0 violacoes".
    let vazio = null;
    try { await medir({ url: escrever('vazio', '<!doctype html><title>x</title><p>so isso'), lado: 'fixture', minNos: 150 }); vazio = 'mediu'; }
    catch (e) { vazio = e.naoMedi ? 'naoMedi' : 'falhou'; }
    ok('GUARD: DOM abaixo do minimo vira NAO MEDI (exit 2), nunca "0 violacoes"', vazio === 'naoMedi', `rc=${vazio}`);
    ok('axe rodou de verdade na fixture (sonda AXE-* presente ou 0 violacoes reais)',
      boa.achados.every((a) => typeof a.sonda === 'string'));

    // O CONTROLE DO AXE morde? Um axe que nunca acusa tem de virar NAO MEDI — senao o controle
    // e decorativo e o "0 violacoes" de um instrumento quebrado passaria por saude (LC-13).
    const { chromium } = await import('@playwright/test');
    const br = await chromium.launch();
    try {
      const pg = await br.newPage();
      await pg.goto(escrever('ctrl', FIXTURE_BOA));
      await pg.evaluate(() => { window.axe = { run: async () => ({ violations: [] }) }; });  // axe CEGO
      let r1 = null;
      try { await conferirAxe(pg); r1 = 'passou'; } catch (e) { r1 = e.naoMedi ? 'naoMedi' : 'falhou'; }
      ok('CONTROLE DO AXE: axe que nunca acusa vira NAO MEDI', r1 === 'naoMedi', `rc=${r1}`);
      // e o inverso: axe que acusa TUDO (inclusive o par bom) tambem e instrumento inconsistente
      await pg.evaluate(() => { window.axe = { run: async () => ({ violations: [{ nodes: [{ target: ['#__ctrl_bom'] }, { target: ['#__ctrl_ruim'] }] }] }) }; });
      let r2 = null;
      try { await conferirAxe(pg); r2 = 'passou'; } catch (e) { r2 = e.naoMedi ? 'naoMedi' : 'falhou'; }
      ok('CONTROLE DO AXE: axe que acusa ate o par BOM vira NAO MEDI', r2 === 'naoMedi', `rc=${r2}`);
    } finally { await br.close(); }
  }

  for (const c of checks) console.log(`${c.ok ? 'ok  ' : 'X   '}${c.nome}${c.det ? ' — ' + c.det : ''}`);
  const falhou = checks.filter((c) => !c.ok).length;
  if (!comBrowser) console.log('\n(parte pura apenas — o bite-test real exige `--selftest --browser`)');
  console.log(`\n${checks.length - falhou}/${checks.length} ok`);
  return falhou ? 1 : 0;
}

/* ── CLI ──────────────────────────────────────────────────────────────────────────────────── */
function imprimir(m) {
  console.log(`# a11y-alvo · lado=${m.lado}${m.rota ? ' rota=' + m.rota : ''} · ${m.nos} nos · ${m.pares_de_cor_medidos} pares de cor`);
  console.log(`  GATE   ${m.total_gate} violacoes (entram no ratchet)`);
  console.log(`  REPORT ${m.total_report} (visiveis, fora do ratchet — ver SONDAS_REPORT)`);
  for (const [s, n] of Object.entries(m.por_sonda)) console.log(`    ${String(n.gate).padStart(4)} gate ${String(n.report).padStart(4)} report  ${s}`);
}

async function main() {
  if (flag('--selftest')) return selftest(flag('--browser'));

  if (flag('--check')) {
    const arqB = val('--baseline', BASELINE), arqA = val('--atual');
    if (!existsSync(arqB)) { console.error(`baseline ausente: ${arqB} — rode --medir e depois --baseline-write`); return 2; }
    if (!arqA || !existsSync(arqA)) { console.error('uso: --check --atual <medicao.json> [--baseline <arq>]'); return 2; }
    const b = JSON.parse(readFileSync(arqB, 'utf8')), a = JSON.parse(readFileSync(arqA, 'utf8'));
    const chave = `${a.lado}${a.rota ? ':' + a.rota : ''}`;
    const alvo = (b.lados || {})[chave];
    if (!alvo) { console.error(`baseline nao tem o lado "${chave}" — rode --baseline-write com esta medicao`); return 2; }
    const r = ratchet(alvo, a);
    for (const g of r.ganhos) console.log(`  ganho  ${g}`);
    if (r.ok) { console.log(`ok a11y-alvo ratchet [${chave}] — ${a.total_gate} violacoes gate (baseline ${alvo.total_gate}).`); return 0; }
    console.error(`X a11y-alvo ratchet [${chave}] REPROVOU — a11y so pode descer:`);
    for (const f of r.falhas) console.error(`    ${f}`);
    console.error('\nCorrija a violacao (nao suprima). Se o ganho for real, rode --baseline-write.');
    return 1;
  }

  if (flag('--baseline-write')) {
    const arqA = val('--atual');
    if (!arqA || !existsSync(arqA)) { console.error('uso: --baseline-write --atual <medicao.json>'); return 2; }
    const a = JSON.parse(readFileSync(arqA, 'utf8'));
    const b = existsSync(BASELINE) ? JSON.parse(readFileSync(BASELINE, 'utf8')) : { _nota: 'Ratchet do a11y-alvo (PR-A4). So DESCE. Gerado por scripts/qa/a11y-alvo.mjs --baseline-write.', lados: {} };
    // A porta do servidor do proto e EFEMERA (--porta 0): grava-la faria o baseline sujar o diff
    // a cada run, e ruido de diff e como catraca vira "regenera sempre" e para de ser catraca.
    const url = String(a.url).replace(/127\.0\.0\.1:\d+/, '127.0.0.1:<efemera>');
    b.lados[`${a.lado}${a.rota ? ':' + a.rota : ''}`] = {
      medido_em: a.medido_em, url, viewport: a.viewport, nos: a.nos,
      total_gate: a.total_gate, total_report: a.total_report, por_sonda: a.por_sonda,
    };
    mkdirSync(dirname(BASELINE), { recursive: true });
    writeFileSync(BASELINE, JSON.stringify(b, null, 2) + '\n');
    console.log(`baseline gravado: ${BASELINE.replace(ROOT, '.')} (lado ${a.lado}${a.rota ? ':' + a.rota : ''} = ${a.total_gate} gate)`);
    return 0;
  }

  if (flag('--medir')) {
    if (!renderPermitido()) {
      console.error('render de prototipo/tela em CI e REJEITADO (ADR 0290 — render pareado passa verde quando os dois lados quebram).');
      console.error('--medir roda LOCAL/dispatch; o CI so compara os JSON commitados (--check).');
      return 4;
    }
    const lado = val('--lado', 'proto'), out = val('--out');
    const comum = { minNos: Number(val('--min-nos', 150)), viewport: val('--viewport', 1280) };
    const m = lado === 'proto'
      ? await medirProto({ rota: val('--rota'), porta: Number(val('--porta', 0)), ...comum })
      : await medir({ url: val('--url'), lado: 'tela', ...comum });
    if (lado !== 'proto' && !val('--url')) { console.error('uso: --medir --lado tela --url <url>'); return 2; }
    imprimir(m);
    if (out) { writeFileSync(out, JSON.stringify(m, null, 2) + '\n'); console.log(`\nmedicao gravada: ${out}`); }
    return 0;
  }

  console.error('uso: --medir --lado proto --rota <id> [--out a.json] | --medir --lado tela --url <url> | --check --atual <a.json> | --baseline-write --atual <a.json> | --selftest [--browser]');
  return 2;
}

const ehEntrypoint = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
if (ehEntrypoint) {
  main()
    .then((rc) => process.exit(rc))
    .catch((e) => { console.error(`${e.naoMedi ? 'NAO MEDI' : 'FALHOU'}: ${e.message}`); process.exit(e.naoMedi ? 2 : 1); });
}
