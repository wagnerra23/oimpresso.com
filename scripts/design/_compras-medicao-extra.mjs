#!/usr/bin/env node
// Descartavel: so existe na branch claude/compras-lote6-medir-nao-mergear. Nao mergear.
// Mede o que o design-diff-lote nao mecaniza: D1 (rede), D3 (icones), D5 (rodape) e a tela
// Purchase/Edit, que nao tem ancora. Mesma sonda canonica, mesmo auth-bridge, mesmo viewport.
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const UID = process.env.VISREG_UID;
const PID = process.env.PURCHASE_ID;
const OUT = 'governance/design/targets/medidas/_extra';
mkdirSync(OUT, { recursive: true });
const probe = execFileSync(process.execPath, ['scripts/design/design-diff.mjs', '--probe'], { encoding: 'utf8', maxBuffer: 1 << 24 });
const salvar = (n, d) => writeFileSync(join(OUT, n), JSON.stringify(d, null, 2) + '\n');

const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await ctx.newPage();
await page.goto(`${BASE}/_visreg-login/${UID}?to=${encodeURIComponent('/compras')}`, { waitUntil: 'domcontentloaded' });

async function estavel() {
  await page.waitForLoadState('networkidle').catch(() => {});
  let ant = -1;
  for (let i = 0; i < 30; i++) {
    const n = await page.evaluate(() => document.querySelectorAll('*').length);
    if (n === ant) return n;
    ant = n;
    await page.waitForTimeout(700);
  }
  return ant;
}
async function escuro() {
  await page.evaluate(() => { document.documentElement.setAttribute('data-theme', 'dark'); document.documentElement.classList.add('dark'); });
  await page.waitForTimeout(300);
}
async function ir(rota) {
  await page.goto(`${BASE}${rota}`, { waitUntil: 'domcontentloaded' });
  const nos = await estavel();
  const url = page.url();
  const componente = await page.evaluate(() => {
    const a = document.getElementById('app');
    const s = document.querySelector('script[data-page]');
    try { return JSON.parse(a?.getAttribute('data-page') || s?.textContent || 'null')?.component || null; } catch { return null; }
  });
  return { rota, url, nos, componente };
}
const fotografar = async (n) => page.screenshot({ path: join(OUT, n + '.png'), fullPage: true });

/** D1: uma interacao de filtro, com marcador na window e captura das requisicoes. */
async function d1(rota, acao) {
  const info = await ir(rota);
  await page.evaluate(() => { window.__d1Marker = 'vivo'; });
  const reqs = [];
  const h = (r) => {
    const t = r.resourceType();
    if (t === 'document' || t === 'xhr' || t === 'fetch') {
      const hs = r.headers();
      reqs.push({ tipo: t, url: r.url().replace(BASE, ''), inertia: !!hs['x-inertia'], partialData: hs['x-inertia-partial-data'] || null, partialComponent: hs['x-inertia-partial-component'] || null });
    }
  };
  page.on('request', h);
  let erro = null;
  try { await acao(); await estavel(); } catch (e) { erro = String(e.message).slice(0, 200); }
  page.off('request', h);
  const marcadorSobreviveu = await page.evaluate(() => window.__d1Marker === 'vivo').catch(() => false);
  return { ...info, acaoErro: erro, urlDepois: page.url(), marcadorSobreviveu, reqs };
}

/** D3 + D5 da pagina corrente. */
const inventario = () => page.evaluate(() => {
  const nome = (s) => [...s.classList].find((c) => c.startsWith('lucide-') && c !== 'lucide') || s.getAttribute('data-icon') || 'svg-sem-nome';
  const main = document.querySelector('main') || document.body;
  const zona = (sel) => [...main.querySelectorAll(sel)].map((el) => ({
    tag: el.tagName, texto: (el.textContent || '').trim().slice(0, 60), icones: [...el.querySelectorAll('svg')].map(nome),
  }));
  return {
    h1: [...main.querySelectorAll('h1')].map((e) => e.textContent.trim()),
    botoes: zona('button').slice(0, 40),
    cabecalhoTabela: [...main.querySelectorAll('table thead th')].map((e) => e.textContent.trim()),
    linhas: main.querySelectorAll('table tbody tr').length,
    tfoot: [...main.querySelectorAll('table tfoot tr')].map((tr) => [...tr.children].map((c) => c.textContent.trim())),
    somatorios: [...main.querySelectorAll('[data-contract*="summary"], [data-contract*="total"], .cmp-summary, .cmp-sum-cell')].map((e) => e.textContent.trim().slice(0, 120)),
    secoes: [...main.querySelectorAll('[data-slot=card-title], h2, h3')].map((e) => e.textContent.trim().slice(0, 60)),
    tema: { html: document.documentElement.getAttribute('data-theme'), cockpit: document.querySelector('.cockpit')?.getAttribute('data-theme') ?? null, bg: getComputedStyle(document.body).backgroundColor },
  };
});

const resultado = { d1: {}, inventario: {}, paginas: {} };

resultado.d1.comprasAba = await d1('/compras', async () => { await page.locator('[data-contract="compras-abas"] > a').nth(1).click(); });
resultado.d1.purchasesData = await d1('/purchases', async () => {
  await page.locator('main .sticky input[type="date"]').first().fill('2026-06-01');
});

for (const [nome, rota] of [['compras', '/compras'], ['purchases', '/purchases'], ['create', '/purchases/create'], ['show', `/purchases/${PID}`], ['edit', `/purchases/${PID}/edit`]]) {
  resultado.paginas[nome] = await ir(rota);
  await escuro();
  resultado.inventario[nome] = await inventario();
  await fotografar(nome);
}

// Purchase/Edit nao tem ancora. Comparacao de irmas: o charter diz "identico ao Create".
for (const [arq, rota] of [['edit-prod.json', `/purchases/${PID}/edit`], ['create-prod.json', '/purchases/create']]) {
  await ir(rota);
  await escuro();
  await page.evaluate(() => { window.__DD_ROLES = { title: 'h1', primary: 'main button[type=submit]', filterControls: 'main [data-contract] input, main [data-contract] button[role=combobox]', tableRow: 'main table tbody tr' }; });
  salvar(arq, await page.evaluate(probe));
}

salvar('resultado.json', resultado);
await browser.close();
console.log('extra medido:', Object.keys(resultado.paginas).join(', '));
