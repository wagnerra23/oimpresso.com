#!/usr/bin/env node
// ─────────────────────────────────────────────────────────────────────────────
// Smoke visual REAL pós-deploy (ADR 0164 fase C) — roda em runner ubuntu do GitHub.
// Playwright headless navega prod, loga (form real), screenshota 1440+1280, coleta
// console errors + os 4 sinais de render determinísticos, e a OpenAI (vision, o
// OPENAI_API_KEY que já existe no repo) julga se a tela renderizou ou está QUEBRADA.
// Sem CT 100, sem claude-in-chrome MCP, sem secret novo. Wagner 2026-07-08: "use o openai".
//
// Escreve: screenshots em storage/screen-smoke/<label>/, <Tela>.review.md (append
// round N, ao lado do .tsx quando a rota tem `source`) e um smoke-log consolidado.
// Exit 1 se qualquer rota vier QUEBRADA (deploy quebrou algo) → job vermelho + review.
//
// Env: SMOKE_BASE_URL, SMOKE_PROD_USER, SMOKE_PROD_PASS (biz=99 FAKE), OPENAI_API_KEY,
//      SMOKE_VISION_MODEL (default gpt-4o-mini), SCREENS, GITHUB_RUN_ID.
// ─────────────────────────────────────────────────────────────────────────────
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const HERE = path.dirname(new URL(import.meta.url).pathname);
const cfg = JSON.parse(fs.readFileSync(path.join(HERE, 'routes.json'), 'utf8'));

const BASE = process.env.SMOKE_BASE_URL || cfg.base_url || 'https://oimpresso.com';
const USER = process.env.SMOKE_PROD_USER || '';
const PASS = process.env.SMOKE_PROD_PASS || '';
const OPENAI_KEY = process.env.OPENAI_API_KEY || '';
const MODEL = process.env.SMOKE_VISION_MODEL || 'gpt-4o-mini';
const OUT = process.env.SMOKE_OUT_DIR || 'storage/screen-smoke';
const SCREENS = process.env.SCREENS || '__NAV__';
const RUN_ID = process.env.GITHUB_RUN_ID || 'local';
const TS = new Date().toISOString();
const VIEWPORTS = cfg.viewports || [{ tag: '1440', width: 1440, height: 900 }, { tag: '1280', width: 1280, height: 720 }];

const log = (...a) => console.log('[smoke]', ...a);

// ── quais rotas smokar ──────────────────────────────────────────────────────
// __MANUAL__ → todas · __NAV__ → só nav_critical · lista de .tsx → nav_critical + as
// rotas cujo `source` está na lista (precisão quando dá, robustez sempre).
function selectRoutes() {
  const changed = SCREENS.split(',').map((s) => s.trim()).filter(Boolean);
  const manual = SCREENS === '__MANUAL__';
  const navOnly = SCREENS === '__NAV__';
  return cfg.routes.filter((r) => {
    if (manual) return true;
    if (r.nav_critical) return true;
    if (navOnly) return false;
    return r.source && changed.includes(r.source);
  });
}

async function login(context) {
  if (!USER || !PASS) return false;
  const page = await context.newPage();
  try {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle', timeout: 45000 });
    const userField = page.getByLabel(/usu[aá]rio|username|e-?mail/i)
      .or(page.locator('input[name="username"], input[name="email"]')).first();
    const passField = page.getByLabel(/senha|password/i)
      .or(page.locator('input[type="password"]')).first();
    await userField.fill(USER);
    await passField.fill(PASS);
    await page.getByRole('button', { name: /entrar|login|sign in/i }).click();
    await page.waitForLoadState('networkidle', { timeout: 45000 });
    // Sucesso se saiu de /login.
    const ok = !page.url().includes('/login');
    log(ok ? 'login OK' : 'login FALHOU (ainda em /login)');
    return ok;
  } catch (e) {
    log('login erro:', e.message);
    return false;
  } finally {
    await page.close();
  }
}

// ── 4 sinais determinísticos (feedback-deploy-smoke-browser-obrigatorio §1) ──
async function deterministicSignals(page) {
  return page.evaluate(() => {
    const titulo = !!(document.querySelector('h1')?.textContent?.trim()) || !!document.title?.trim();
    const texto = (document.body?.innerText || '').replace(/\s+/g, ' ').trim();
    const conteudo = texto.length > 40; // não é tela branca / skeleton vazio
    const shell = !!document.querySelector('nav, aside, [role="navigation"], [data-sidebar], header');
    return { titulo, conteudo, shell, textoLen: texto.length };
  });
}

async function visionJudge(imgPath, label) {
  if (!OPENAI_KEY) return { ok: null, severidade: 'aviso', problemas: ['OPENAI_API_KEY ausente'], resumo: 'sem juízo visual' };
  const b64 = fs.readFileSync(imgPath).toString('base64');
  const body = {
    model: MODEL,
    temperature: 0,
    max_tokens: 400,
    messages: [{
      role: 'user',
      content: [
        { type: 'text', text: `Esta é a tela "${label}" de um ERP em produção logo após um deploy. Ela renderizou CORRETAMENTE ou está QUEBRADA (tela branca, mensagem de erro visível, layout destruído, componentes/dados faltando)? Responda SÓ com JSON, sem texto fora dele: {"ok": true|false, "severidade": "ok"|"aviso"|"quebrado", "problemas": ["..."], "resumo": "uma frase curta em pt-BR"}.` },
        { type: 'image_url', image_url: { url: `data:image/png;base64,${b64}` } },
      ],
    }],
  };
  try {
    const r = await fetch('https://api.openai.com/v1/chat/completions', {
      method: 'POST',
      headers: { Authorization: `Bearer ${OPENAI_KEY}`, 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    if (!r.ok) return { ok: null, severidade: 'aviso', problemas: [`OpenAI HTTP ${r.status}`], resumo: 'juízo visual indisponível' };
    const j = await r.json();
    let txt = (j.choices?.[0]?.message?.content || '').trim();
    txt = txt.replace(/^```json\s*/i, '').replace(/^```\s*/i, '').replace(/```$/i, '').trim();
    const parsed = JSON.parse(txt);
    return {
      ok: !!parsed.ok,
      severidade: ['ok', 'aviso', 'quebrado'].includes(parsed.severidade) ? parsed.severidade : 'aviso',
      problemas: Array.isArray(parsed.problemas) ? parsed.problemas : [],
      resumo: String(parsed.resumo || '').slice(0, 240),
    };
  } catch (e) {
    return { ok: null, severidade: 'aviso', problemas: [`parse/rede: ${e.message}`], resumo: 'juízo visual falhou' };
  }
}

function appendReview(source, label, res) {
  const reviewPath = source.replace(/\.tsx$/, '.review.md');
  let prev = fs.existsSync(reviewPath)
    ? fs.readFileSync(reviewPath, 'utf8')
    : `# Review visual — ${label}\n\n> Smoke visual pós-deploy automático (ADR 0164 fase C). Append-only por round. Wagner (Act): edite \`decisão:\` no round.\n`;
  const round = (prev.match(/^## Round /gm) || []).length + 1;
  const det = res.det ? `titulo=${res.det.titulo} conteudo=${res.det.conteudo} shell=${res.det.shell}` : 'n/d';
  const block = [
    ``,
    `## Round ${round} — ${TS}`,
    ``,
    `- **status:** pending-wagner`,
    `- **run:** ${RUN_ID} · rota \`${res.pathUrl}\``,
    `- **determinístico (4 sinais):** ${det} · console-errors=${res.consoleErrors.length}`,
    `- **openai (${MODEL}):** ${res.vision.severidade} — ${res.vision.resumo}${res.vision.problemas.length ? ' · ' + res.vision.problemas.join('; ') : ''}`,
    `- **veredito:** ${res.verdict}`,
    `- **screenshots:** 1440 + 1280 no artifact \`screen-smoke-${RUN_ID}\``,
    `- **decisão Wagner:** _aguardando — edite para \`approved\` | \`rejected\` | \`iterate\`_`,
    ``,
  ].join('\n');
  fs.mkdirSync(path.dirname(reviewPath), { recursive: true });
  fs.writeFileSync(reviewPath, prev + block);
  return reviewPath;
}

async function run() {
  const routes = selectRoutes();
  log(`SCREENS=${SCREENS} → ${routes.length} rota(s):`, routes.map((r) => r.label).join(', '));
  fs.mkdirSync(OUT, { recursive: true });

  const browser = await chromium.launch();
  const context = await browser.newContext({ locale: 'pt-BR', ignoreHTTPSErrors: false });
  const loggedIn = await login(context);
  if (!loggedIn && routes.some((r) => r.auth)) {
    log('⚠️ sem login (SMOKE_PROD_USER/PASS ausentes ou inválidos) — rotas auth serão puladas.');
  }

  const results = [];
  for (const route of routes) {
    if (route.auth && !loggedIn) {
      results.push({ ...route, skipped: true });
      continue;
    }
    const consoleErrors = [];
    const page = await context.newPage();
    page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text().slice(0, 300)); });
    page.on('pageerror', (e) => consoleErrors.push(`pageerror: ${e.message}`.slice(0, 300)));

    let det = null; const shots = {};
    let loadErr = null;
    try {
      await page.goto(`${BASE}${route.path}`, { waitUntil: 'networkidle', timeout: 60000 });
      for (const vp of VIEWPORTS) {
        await page.setViewportSize({ width: vp.width, height: vp.height });
        await page.waitForTimeout(600);
        const dir = path.join(OUT, route.label);
        fs.mkdirSync(dir, { recursive: true });
        const p = path.join(dir, `${vp.tag}.png`);
        await page.screenshot({ path: p });
        shots[vp.tag] = p;
      }
      det = await deterministicSignals(page);
    } catch (e) {
      loadErr = e.message;
    } finally {
      await page.close();
    }

    const vision = shots['1440'] ? await visionJudge(shots['1440'], route.label) : { ok: null, severidade: 'quebrado', problemas: [loadErr || 'sem screenshot'], resumo: 'falha ao carregar' };

    // Veredito: QUEBRADO se não carregou, ou tela branca, ou console errors, ou OpenAI=quebrado.
    const brokenDet = !det || !det.conteudo || !det.titulo;
    const broken = !!loadErr || brokenDet || consoleErrors.length > 0 || vision.severidade === 'quebrado';
    const verdict = broken ? 'QUEBRADO' : (vision.severidade === 'aviso' ? 'AVISO' : 'OK');

    const res = { ...route, pathUrl: route.path, det, consoleErrors, vision, verdict, loadErr };
    if (route.source) res.reviewPath = appendReview(route.source, route.label, res);
    results.push(res);
    log(`${route.label}: ${verdict}${loadErr ? ' (' + loadErr + ')' : ''} · console=${consoleErrors.length} · openai=${vision.severidade}`);
  }

  await browser.close();

  // smoke-log consolidado
  const worst = results.some((r) => r.verdict === 'QUEBRADO') ? 'QUEBRADO'
    : results.some((r) => r.verdict === 'AVISO') ? 'AVISO' : 'OK';
  const summaryPath = path.join(OUT, `smoke-${RUN_ID}.md`);
  const lines = [
    `# Smoke visual pós-deploy — run ${RUN_ID}`,
    ``,
    `- **quando:** ${TS}`,
    `- **base:** ${BASE} · **login:** ${loggedIn ? 'ok' : 'não'} · **modelo:** ${MODEL}`,
    `- **veredito geral:** ${worst}`,
    ``,
    `| rota | veredito | console | openai | resumo |`,
    `|---|---|---:|---|---|`,
    ...results.map((r) => r.skipped
      ? `| ${r.label} | PULADA (sem login) | – | – | rota auth sem credencial |`
      : `| ${r.label} | ${r.verdict} | ${r.consoleErrors.length} | ${r.vision.severidade} | ${r.vision.resumo} |`),
    ``,
    `_Screenshots 1440+1280 no artifact \`screen-smoke-${RUN_ID}\`. review.md por tela (append round) ao lado do .tsx._`,
    ``,
  ];
  fs.writeFileSync(summaryPath, lines.join('\n'));
  log(`resumo → ${summaryPath} · geral=${worst}`);

  // GitHub step summary (aparece no run)
  if (process.env.GITHUB_STEP_SUMMARY) fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, lines.join('\n'));

  if (worst === 'QUEBRADO') {
    console.error('[smoke] ::error::Smoke QUEBRADO — ao menos uma tela não renderizou. Ver review.md + artifact.');
    process.exit(1);
  }
}

run().catch((e) => { console.error('[smoke] fatal:', e); process.exit(1); });
