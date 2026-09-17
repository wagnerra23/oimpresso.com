#!/usr/bin/env node
// alvo.mjs — PR-A1 do protocolo de export: o ALVO de uma seção vira MEDIDA executável.
//
// Doc: memory/reference/prototipo-ui/PROTOCOL.md · COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md
// Origem: pedido [W] 2026-09-03 — "automatizar o ciclo MAPA → ALVO → EXPORT → PR → PLACAR".
// Aposenta: eu medindo à mão e ditando números no chat (LC-06 — comparação é MEDIDA, nunca no olho).
//
// ── O QUE ESTE ARQUIVO **NÃO** É (LC-19: máquina paralela a dono existente) ──────────────
// Ele NÃO tem sonda própria de tela. A sonda canônica é `scripts/design/design-diff.mjs`
// (dimensões D1–D9, por PAPEL) e ela é consumida aqui pelo **contrato público** dela —
// `node scripts/design/design-diff.mjs --probe`, subprocesso, medido rc=0/17318 bytes em
// 2026-09-03. Não importamos o módulo: ele é CLI-only (importar dispara o CLI e sai 2).
// O que ESTE arquivo acrescenta é a camada que o dono não cobre: medida **por SELETOR**
// (nós · filhos · ordem das classes · computed style · truncamento · retângulo), que é o
// que o protocolo de export chama de ALVO de uma seção.
//
// ── ONDE GRAVA, E POR QUE NÃO EM governance/design/contracts/ ─────────────────────────────────
// `governance/design/contracts/` já tem dono: `contract.schema.json` + `scripts/contrato-de-tela.mjs`
// (gate estático, sem render) — e lá a chave `alvo` **já significa outra coisa**: "dirs/arquivos
// de produção checados". Gravar `<tela>.alvo.json` naquela pasta colidiria de vocabulário e de
// dono (LC-22: mudar artefato que a máquina lê sem rodar a máquina). Destino: `governance/design/targets/`.
//
// ── MODOS ────────────────────────────────────────────────────────────────────────────────
//   --mapa <url> [--raiz <sel>]        Colhe filhos diretos da raiz + classes repetidas.
//                                      **stdout only, NUNCA grava** (mapa é comando, ADR 0256).
//   --alvo <url> --tela <slug> --secoes <arq.json>
//                                      Mede e grava governance/design/targets/<slug>.alvo.json.
//   --saida <arq>                      (com --alvo) grava AQUI em vez do destino canônico. É o que o
//                                      `secao-check` (PR-A3) usa pra medir um render sem
//                                      re-baselinar o alvo versionado.
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
// ── CÁLCULO DERIVADO (PR-A2): SANIDADE ANTES DE QUALQUER NÚMERO ───────────────────────
// Contraste/luminância/razão só são calculados DEPOIS que `garantirSanidade()` prova, num caso
// de valor conhecido, que a conversão de cor funciona. Se não bater, o script ABORTA (exit 1) —
// nunca devolve número plausível. Medido em 2026-09-03, e é por isso que existe: a 1ª sonda leu
// `oklch(0.94 0.005 90)` com regex de `rgb()` e deu contraste 2,62 no `.fj-title`; a "correção"
// via `canvas.fillStyle` NÃO converte oklch e repetiu o MESMO número, parecendo confirmação. Só a
// 3ª (OKLCH→OKLab→sRGB) vale: 10,84. Número errado e plausível não se denuncia — o instrumento
// tem de PROVAR que sabe converter antes de derivar.
//
// Exit: 0 limpo · 1 falhou · 2 NÃO CONSEGUI MEDIR (browser/sonda ausente).
// O 2 é separado de propósito: "não medi" nunca pode se passar por "está são" (§5 2026-07-29).

import { writeFileSync, mkdirSync, readFileSync, existsSync } from 'node:fs';
import { dirname, resolve, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
// Parse de cor tem DONO: `parseCor` (sRGB/hex/oklch/oklab → OKLab, Björn Ottosson) em
// scripts/design/style-fingerprint.mjs — importável de verdade (guard `ehEntrypoint`, L1430;
// medido nesta sessão). NÃO reescrevo aqui (LC-19: máquina paralela a dono existente).
import { parseCor } from '../design/style-fingerprint.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));            // scripts/design-sync/
const ROOT = resolve(HERE, '..', '..');                          // raiz do repo
const DIR_ALVOS = join(ROOT, 'governance', 'design', 'targets');
const DESIGN_DIFF = join(ROOT, 'scripts', 'design', 'design-diff.mjs');

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

/* ── PR-A2 · CÁLCULO DERIVADO — luminância, razão, contraste ────────────────────────────────
   O que o dono (`parseCor`) já fazia: qualquer notação CSS → OKLab. O que NÃO existia no repo e
   nasce aqui: a VOLTA, OKLab → sRGB linear (inversa de Ottosson) — sem ela não há luminância
   relativa WCAG, e foi exatamente esse elo que faltou nas duas primeiras sondas de 2026-09-03.
   Varredura antes de escrever: `4.0767416621` (coeficiente da inversa) = 0 ocorrências no repo. */

// OKLab → sRGB LINEAR. É ESTA matriz que o aceite do PR-A2 manda sabotar.
export function oklabParaLinear([L, a, b]) {
  const l = (L + 0.3963377774 * a + 0.2158037573 * b) ** 3;
  const m = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3;
  const s = (L - 0.0894841775 * a - 1.2914855480 * b) ** 3;
  return [
    4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
    -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
    -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s,
  ];
}

// Y relativo (WCAG 2.x). `null` = não é cor medível (`none`, `color-mix()`, palavra-chave).
// null NUNCA vira número: o chamador emite `motivo`, porque "não medi" ≠ "medi e deu isso".
// Sufixo `Crua` = SEM o guard — só a própria sanidade pode chamá-las (senão recursão infinita).
export function luminanciaCrua(css) {
  const c = parseCor(css);
  if (!c) return null;
  const [r, g, b] = oklabParaLinear(c.lab);
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
export function razaoCrua(fg, bg) {
  const A = luminanciaCrua(fg), B = luminanciaCrua(bg);
  if (A == null || B == null) return null;
  return (Math.max(A, B) + 0.05) / (Math.min(A, B) + 0.05);
}

/* Casos de VALOR CONHECIDO. O alvo de cada um vem de FORA deste arquivo — se viesse do meu
   código, a "sanidade" mediria auto-consistência, que é o alarme tautológico do §5 2026-07-17:
   · Y de #f00/#0f0/#00f = 0,2126/0,7152/0,0722 — são os COEFICIENTES da própria WCAG.
   · #fff × #000 = 21 — exato por definição: (1+0,05)/(0+0,05).
   · #777 × #fff = 4,478 — conferível à mão: 119/255 → linear ((0,46667+0,055)/1,055)^2,4 = 0,18447.
   · os DOIS últimos são o que pega o bug de 2026-09-03, e são dois de propósito: MEDIDO nesta
     sessão, mutar o coeficiente de `a` (0,3963→0,30) deixa o BRANCO intacto em 1,000000 e só
     move o vermelho (0,21260→0,21403). Acromático sozinho NÃO discrimina — precisa de croma.
   Tolerância 1e-4 em Y: 20× o meu erro numérico medido (5e-6) e 14× menor que o desvio do
   mutante acima (1,4e-3), ou seja, pega mutação de ~1,7% pra cima naquele coeficiente. */
export const SANIDADE = [
  { nome: 'Y(#000000) = 0', tipo: 'Y', entrada: '#000000', esperado: 0, tol: 1e-9 },
  { nome: 'Y(#ffffff) = 1', tipo: 'Y', entrada: '#ffffff', esperado: 1, tol: 1e-4 },
  { nome: 'Y(#ff0000) = 0,2126 (coef. WCAG R)', tipo: 'Y', entrada: '#ff0000', esperado: 0.2126, tol: 1e-4 },
  { nome: 'Y(#00ff00) = 0,7152 (coef. WCAG G)', tipo: 'Y', entrada: '#00ff00', esperado: 0.7152, tol: 1e-4 },
  { nome: 'Y(#0000ff) = 0,0722 (coef. WCAG B)', tipo: 'Y', entrada: '#0000ff', esperado: 0.0722, tol: 1e-4 },
  { nome: 'razão #fff × #000 = 21 (exato)', tipo: 'R', entrada: '#ffffff', contra: '#000000', esperado: 21, tol: 1e-6 },
  { nome: 'razão #777 × #fff = 4,478', tipo: 'R', entrada: '#777777', contra: '#ffffff', esperado: 4.478, tol: 5e-3 },
  { nome: 'OKLCH acromático: oklch(1 0 0) ≡ #ffffff', tipo: 'Y', entrada: 'oklch(1 0 0)', esperado: 1, tol: 1e-4 },
  { nome: 'OKLCH cromático: oklch(0.62796 0.25768 29.234) ≡ #f00', tipo: 'Y', entrada: 'oklch(0.62796 0.25768 29.234)', esperado: 0.2126, tol: 1e-4 },
];

// Roda a tabela. `lum`/`raz` são parâmetros PRA O BITE-TEST poder injetar um mutante e provar
// que a sanidade morde — sem isso o "aceite falsificável" seria promessa, não teste.
export function conferirSanidade(lum = luminanciaCrua, raz = razaoCrua) {
  const falhas = [];
  for (const c of SANIDADE) {
    const obtido = c.tipo === 'Y' ? lum(c.entrada) : raz(c.entrada, c.contra);
    const ok = obtido != null && Number.isFinite(obtido) && Math.abs(obtido - c.esperado) <= c.tol;
    if (!ok) falhas.push(`${c.nome} — esperado ${c.esperado} ±${c.tol}, obtido ${obtido == null ? 'null' : Number(obtido.toFixed(6))}`);
  }
  return falhas;
}

let _sanidade = null; // memo do VEREDITO (função pura: passou uma vez, passa sempre no processo).
export function garantirSanidade() {
  if (_sanidade === null) _sanidade = conferirSanidade();
  if (_sanidade.length) {
    // FALHOU (exit 1), não "NÃO MEDI" (exit 2): conversão quebrada é DEFEITO do instrumento, e
    // defeito de instrumento é pior que ausência de medida — ele produz número, e número convence.
    throw new Error(`SANIDADE DA COR REPROVOU (${_sanidade.length} de ${SANIDADE.length}) — não derivo contraste com conversão quebrada:\n  · ${_sanidade.join('\n  · ')}`);
  }
}
export const luminancia = (css) => { garantirSanidade(); return luminanciaCrua(css); };
export const contraste = (fg, bg) => { garantirSanidade(); return razaoCrua(fg, bg); };

/* Fundo EFETIVO + razão, a partir da cadeia de ancestrais que a sonda traz em bruto.
   Alfa parcial não vira número: compor exigiria a pilha inteira, e chutar aqui seria a mesma
   família do bug que este PR fecha — plausível e errado. */
export function derivarContraste(cor, cadeia) {
  garantirSanidade();
  const fim = Array.isArray(cadeia) && cadeia.length ? cadeia[cadeia.length - 1] : null;
  if (!cor || !fim) return { motivo: 'sem cor de texto ou sem cadeia de fundo' };
  const ct = parseCor(cor), cf = parseCor(fim.bg);
  if (!ct) return { motivo: `cor do texto não é medível: ${cor}` };
  if (!cf) return { motivo: `fundo não é cor medível: ${fim.bg}` };
  if (ct.alfa < 1) return { motivo: `texto com alfa ${ct.alfa} — composição exigiria a pilha inteira` };
  if (cf.alfa < 1) return { motivo: `nenhum ancestral com fundo opaco (último: ${fim.bg}, alfa ${cf.alfa})` };
  return {
    texto: cor, fundo: fim.bg, fundo_de: fim.de,
    y_texto: Number(luminanciaCrua(cor).toFixed(6)),
    y_fundo: Number(luminanciaCrua(fim.bg).toFixed(6)),
    razao: Number(razaoCrua(cor, fim.bg).toFixed(2)),
  };
}

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
  // A cadeia vem CRUA: a sonda nao decide nada de cor. Quem parseia alfa e deriva razao e o
  // Node, sob o guard de sanidade — aqui dentro nao ha como abortar, e calculo sem guard e
  // exatamente o que o PR-A2 existe pra impedir. Para no 1o fundo que nao e o transparente
  // canonico do Chromium (comparacao de STRING: zero regex no template, LC-26).
  const TRANSPARENTE = ['rgba(0, 0, 0, 0)', 'transparent'];
  const fundoCadeia = (el) => {
    const cadeia = [];
    for (let n = el; n && cadeia.length < 12; n = n.parentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      cadeia.push({ de: n === el ? 'proprio' : primeiraClasse(n), bg });
      if (!TRANSPARENTE.includes(bg)) break;
    }
    return cadeia;
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
      fundoCadeia: fundoCadeia(el),
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
    // PR-A2: o caso de valor conhecido roda AQUI, antes do primeiro numero derivado. Reprovou,
    // aborta a medicao inteira (exit 1) — o alvo nao sai com contraste que eu nao sei calcular.
    garantirSanidade();
    for (const s of Object.values(medidoSecoes)) {
      if (s.ausente) continue;
      s.contraste = derivarContraste(s.estilo && s.estilo.color, s.fundoCadeia);
    }
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

  /* ── PR-A2 · o cálculo derivado só corre depois de PROVAR que sabe converter ─────────────
     Tudo aqui é puro → roda na lane de CI (design-memory-gate, que não instala chromium).
     A ordem importa: primeiro o controle POSITIVO (íntegro passa), depois os MUTANTES — sem o
     positivo, "nenhum mutante passou" seria compatível com a tabela nunca ter rodado (LC-13). */
  const falhasIntegro = conferirSanidade();
  ok('sanidade da cor passa ÍNTEGRA (controle positivo — 0 falhas)',
    falhasIntegro.length === 0, falhasIntegro.join(' | '));
  ok('contraste #fff × #000 = 21 (exato, por definição WCAG)',
    Math.abs(contraste('#ffffff', '#000000') - 21) < 1e-6);
  ok('OKLCH é convertido DE VERDADE: oklch(1 0 0) ≡ rgb(255,255,255)',
    Math.abs(luminancia('oklch(1 0 0)') - luminancia('rgb(255, 255, 255)')) < 1e-6);

  // Os 3 mutantes: o do ACEITE (matriz sabotada) + os DOIS erros históricos de 2026-09-03.
  const _lin = (c) => (c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
  const mutMatriz = (css) => {           // sabota o coeficiente de `a` no eixo l' (0,3963 → 0,30)
    const c = parseCor(css); if (!c) return null;
    const [L, a, b] = c.lab;
    const l = (L + 0.30 * a + 0.2158037573 * b) ** 3;
    const m = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const s = (L - 0.0894841775 * a - 1.2914855480 * b) ** 3;
    return 0.2126 * (4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s)
      + 0.7152 * (-1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s)
      + 0.0722 * (-0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s);
  };
  const mutRegexRgb = (css) => {         // a 1ª sonda: regex de `rgb()`, resto vira números soltos
    const m = String(css).match(/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)/);
    const n = m ? [+m[1], +m[2], +m[3]] : (String(css).match(/[\d.]+/g) || [0, 0, 0]).slice(0, 3).map(Number);
    return 0.2126 * _lin(n[0] / 255) + 0.7152 * _lin(n[1] / 255) + 0.0722 * _lin(n[2] / 255);
  };
  let _ultima = '#000000';               // a 2ª: `canvas.fillStyle` REJEITA o valor que não entende
  const mutCanvas = (css) => {           // e mantém o anterior — daí "repetiu o mesmo número"
    if (/^(#|rgb)/.test(String(css))) _ultima = String(css);
    return luminanciaCrua(_ultima);
  };
  const razDe = (lum) => (a, b) => {
    const A = lum(a), B = lum(b);
    if (A == null || B == null) return null;
    return (Math.max(A, B) + 0.05) / (Math.min(A, B) + 0.05);
  };
  for (const [nome, mut] of [['matriz OKLab→sRGB sabotada', mutMatriz], ['regex de rgb() (1ª sonda 2026-09-03)', mutRegexRgb], ['canvas.fillStyle (2ª sonda 2026-09-03)', mutCanvas]]) {
    const f = conferirSanidade(mut, razDe(mut));
    ok(`MUTANTE reprova: ${nome}`, f.length > 0, `${f.length} falha(s) · 1ª: ${f[0] || '—'}`);
  }

  // O que torna a tabela DISCRIMINANTE — e é medição, não crença: contra a matriz sabotada o
  // caso ACROMÁTICO passa (a = 0, o branco nem se move) e só o CROMÁTICO acusa. Uma sanidade
  // só com branco/preto seria carimbo: verde que não pode ficar vermelho (§5 2026-07-17).
  const fMat = conferirSanidade(mutMatriz, razDe(mutMatriz));
  ok('sanidade discrimina: contra a matriz sabotada, o caso CROMÁTICO é que acusa',
    fMat.some((x) => x.includes('cromático')) && !fMat.some((x) => x.includes('acromático')),
    fMat.join(' | ') || 'nenhuma falha');

  // CONTROLE NEGATIVO — sem a sanidade o número errado PASSARIA: o mutante devolve razão
  // finita e plausível pro caso de 2026-09-03. Medido aqui: 1,14 onde o certo é 14,60.
  const razMut = razDe(mutRegexRgb)('oklch(0.94 0.005 90)', '#1a1a1a');
  const razCerta = contraste('oklch(0.94 0.005 90)', '#1a1a1a');
  ok('controle negativo: o mutante devolve número PLAUSÍVEL (é por isso que a sanidade existe)',
    Number.isFinite(razMut) && razMut >= 1 && razMut <= 21 && Math.abs(razMut - razCerta) > 5,
    `mutante ${razMut.toFixed(2)} × certo ${razCerta.toFixed(2)}`);

  // derivarContraste: o que NÃO é medível sai como `motivo`, nunca como número plausível.
  ok('derivarContraste: fundo com alfa parcial vira motivo, não número',
    derivarContraste('#ffffff', [{ de: 'proprio', bg: 'rgba(0, 0, 0, 0.5)' }]).razao === undefined);
  ok('derivarContraste: cor não-medível (color-mix) vira motivo, não número',
    derivarContraste('color-mix(in srgb, red, blue)', [{ de: 'x', bg: '#000000' }]).razao === undefined);
  const dc = derivarContraste('oklch(0.985 0.003 90)', [{ de: 'proprio', bg: 'rgba(0, 0, 0, 0)' }, { de: 'jc-page', bg: 'oklch(0.165 0.008 282)' }]);
  ok('derivarContraste: cadeia com fundo opaco no ancestral resolve a razão',
    dc.razao > 1 && dc.fundo_de === 'jc-page', `razao=${dc.razao} de=${dc.fundo_de}`);

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
    if (!url || !tela || !arq) { console.error('uso: --alvo <url> --tela <slug> --secoes <arq.json> [--injetar-falha <sel>] [--aguardar-sumir <sel>] [--saida <arq>]'); return 2; }
    if (!existsSync(arq)) { console.error(`--secoes: arquivo não encontrado: ${arq}`); return 2; }
    const secoes = JSON.parse(readFileSync(arq, 'utf8'));
    const medido = await medirAlvo({ url, tela, secoes, injetar: val('--injetar-falha'), sumir: val('--aguardar-sumir'), quietoMs: Number(val('--quieto-ms', 400)) });
    // --saida: grava FORA do destino canônico. Existe pro `secao-check` (PR-A3) medir um render
    // sem sobrescrever o alvo versionado — medir não pode ter o efeito colateral de re-baselinar.
    const saida = val('--saida');
    const destino = saida ? resolve(saida) : join(DIR_ALVOS, `${tela.replace(/[^a-z0-9-]/gi, '-').toLowerCase()}.alvo.json`);
    mkdirSync(dirname(destino), { recursive: true });
    writeFileSync(destino, serializar(medido));
    console.log(`alvo gravado: ${destino.replace(ROOT, '.')}`);
    console.log(`  seções medidas: ${Object.keys(medido.secoes).length} · ausentes no DOM: ${Object.values(medido.secoes).filter((s) => s.ausente).length}`);
    return 0;
  }

  console.error('uso: --mapa <url> [--aguardar-sumir <sel>] [--quieto-ms <n>] | --alvo <url> --tela <slug> --secoes <arq.json> [--aguardar-sumir <sel>] [--quieto-ms <n>] [--saida <arq>] | --selftest [--browser]');
  return 2;
}

main()
  .then((rc) => process.exit(rc))
  .catch((e) => { console.error(`${e.naoMedi ? 'NÃO MEDI' : 'FALHOU'}: ${e.message}`); process.exit(e.naoMedi ? 2 : 1); });
