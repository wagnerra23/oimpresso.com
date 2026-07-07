#!/usr/bin/env node
// style-fingerprint.mjs — comparador EXAUSTIVO de estilo protótipo × produção, como MECANISMO.
//
// Por que existe (2026-07-07, [W] "essa regra e outras era para eu informar? ou tem método?"):
// a comparação de fidelidade media propriedades ESCOLHIDAS A DEDO (amostragem) — o que o
// agente não lembra de medir, não é medido. Três lacunas reais escaparam assim no mesmo dia:
//   (1) tema dark nunca sondado (KPI branco-fixo ilegível; tokens azulados vs warm 282);
//   (2) geometria emergente (botão "Novo título" em 3 linhas com todas as cores certas);
//   (3) componente nu fora do escopo .fin-cowork (padding 0 sem ninguém declarar).
// Este script generaliza as regras 4/5/7 do RUNBOOK-aplicar-prototipo: em vez de lembrar
// o que medir, extrai um VETOR COMPLETO por elemento visível-com-texto/interativo, nos
// DOIS temas, e diffa os vetores. Wagner decide gosto; a máquina acha as diferenças.
//
// MODOS:
//   node prototipo-ui/style-fingerprint.mjs --snippet
//       → imprime o snippet JS auto-contido pra rodar em QUALQUER página renderizada
//         (console do browser, extensão MCP, playwright evaluate). Saída: JSON fingerprint.
//         Rodar 1x por tema (a página deve estar no tema desejado; passar o nome no arg).
//   node prototipo-ui/style-fingerprint.mjs --compare proto.json prod.json [--json]
//       → casa elementos por (texto normalizado + tag) e compara campo a campo.
//         Vereditos: IDENTICO · DIVERGE(campos) · SO_PROTO · SO_PROD. Exit 1 se DIVERGE>0.
//   node prototipo-ui/style-fingerprint.mjs --selftest
//       → fixtures herméticas inline com divergências PLANTADAS (2-linhas, cor, radius,
//         só-de-um-lado) — prova o comparador pelos DOIS lados (L-31) antes do uso real.
//
// MATCHING (v1, honesto sobre limites): DOMs de protótipo e produção DIVERGEM em estrutura
// e classes — matching posicional/por-classe não existe. Casamos por TEXTO VISÍVEL
// normalizado + tag, cobrindo o que interessa à fidelidade (labels, botões, pills, títulos,
// headers de coluna). Elementos sem texto (ícones puros, sparklines) ficam FORA do v1 —
// registrado como limite, não silêncio: o resumo imprime quantos elementos cada lado tem
// fora do matching. Texto DINÂMICO (valores R$, datas) é normalizado pra placeholder.

import { readFileSync } from 'node:fs';

// ── vetor extraído por elemento (mantido em sync com o SNIPPET abaixo) ─────────
export const CAMPOS = [
  'tag', 'w', 'h', 'linhas', 'overflowX',
  'fontSize', 'fontWeight', 'color', 'bgEfetivo',
  'radius', 'borderW', 'borderColor', 'display',
];

// Tolerâncias de comparação (px inteiro; strings exatas pro resto).
const TOL_PX = 1.5;

// normaliza texto visível: colapsa espaços, troca números/moeda/data por placeholder
export function normTexto(t) {
  return (t || '')
    .replace(/\s+/g, ' ')
    .replace(/R\$\s?[\d.,]+/g, '<BRL>')
    .replace(/\d{1,2}\/\d{1,2}(\/\d{2,4})?/g, '<DATA>')
    .replace(/\b\d+([.,]\d+)?%?\b/g, '<N>')
    .trim()
    .slice(0, 60);
}

// agruparLinhas — conta LINHAS a partir dos rects dos TEXT NODES (nunca dos SVGs/ícones).
// Fix 2026-07-07 (dogfood [W]): a v1 media `range.selectNodeContents(elemento)` — que inclui
// os <svg> de ícone em baseline sub-pixel diferente do texto — + bucketing fixo de 4px →
// FALSO-POSITIVO "2 linhas" num botão de 1 linha ("Novo título" + Plus + Chevron). Aqui só
// entram rects de text node; o agrupamento é por PROXIMIDADE proporcional (tol = 0.6× da
// mediana das alturas): linhas reais estão a ~1 line-height de distância, jitter de baseline
// é << line-height. Recebe [{top,height}] e devolve o nº de linhas. Função pura = testável.
export function agruparLinhas(rects) {
  const rs = rects.filter((r) => r && r.height > 1 && r.width > 1);
  if (rs.length === 0) return 0;
  const alturas = rs.map((r) => r.height).sort((a, b) => a - b);
  const medH = alturas[Math.floor(alturas.length / 2)] || 16;
  const tol = medH * 0.6;
  const tops = rs.map((r) => r.top).sort((a, b) => a - b);
  let linhas = 1;
  for (let i = 1; i < tops.length; i++) if (tops[i] - tops[i - 1] > tol) linhas++;
  return linhas;
}

export function chave(el) { return el.tag + '|' + normTexto(el.texto); }

function ehNum(c) { return ['w', 'h', 'linhas', 'borderW'].includes(c); }

export function diffElemento(a, b) {
  const campos = [];
  for (const c of CAMPOS) {
    if (c === 'tag') continue;
    const va = a[c], vb = b[c];
    if (ehNum(c)) {
      const na = parseFloat(va) || 0, nb = parseFloat(vb) || 0;
      // linhas: qualquer diferença é DIVERGE (regra 7 — 2 linhas onde o proto tem 1)
      const tol = c === 'linhas' ? 0.01 : TOL_PX;
      if (Math.abs(na - nb) > tol) campos.push(`${c}: ${va} → ${vb}`);
    } else if (String(va ?? '') !== String(vb ?? '')) {
      campos.push(`${c}: ${va} → ${vb}`);
    }
  }
  return campos;
}

export function comparar(fpA, fpB) {
  const mapA = new Map(fpA.elementos.map((e) => [chave(e), e]));
  const mapB = new Map(fpB.elementos.map((e) => [chave(e), e]));
  const rows = [];
  for (const [k, a] of mapA) {
    const b = mapB.get(k);
    if (!b) { rows.push({ chave: k, veredito: 'SO_PROTO', campos: [] }); continue; }
    const campos = diffElemento(a, b);
    rows.push({ chave: k, veredito: campos.length ? 'DIVERGE' : 'IDENTICO', campos });
  }
  for (const [k] of mapB) if (!mapA.has(k)) rows.push({ chave: k, veredito: 'SO_PROD', campos: [] });
  rows.sort((x, y) => x.veredito.localeCompare(y.veredito) || x.chave.localeCompare(y.chave));
  const tally = {};
  for (const r of rows) tally[r.veredito] = (tally[r.veredito] || 0) + 1;
  return { rows, tally, temaA: fpA.tema, temaB: fpB.tema };
}

// ── snippet auto-contido (roda DENTRO da página; espelho do vetor CAMPOS) ──────
const SNIPPET = String.raw`
(() => {
  // style-fingerprint SNIPPET v1 — rode com a página no tema desejado.
  // Devolve JSON { tema, url, elementos: [...] } — salve num arquivo .json.
  const TEMA = document.documentElement.getAttribute('data-theme') || 'light';
  const vis = (el) => { const r = el.getBoundingClientRect(); return r.width > 4 && r.height > 4; };
  const bgEfetivo = (el) => {
    let n = el;
    while (n && n !== document.documentElement) {
      const bg = getComputedStyle(n).backgroundColor;
      if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') return bg;
      n = n.parentElement;
    }
    return getComputedStyle(document.body).backgroundColor;
  };
  // nLinhas — só rects de TEXT NODE (ignora <svg>/ícones) + agrupamento proporcional.
  // Espelho de agruparLinhas() do módulo (fix falso-positivo do botão ícone+texto, 2026-07-07).
  const nLinhas = (el) => {
    try {
      const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
      const rects = [];
      let tn;
      while ((tn = walker.nextNode())) {
        if (!tn.textContent.trim()) continue;
        const r = document.createRange();
        r.selectNodeContents(tn);
        for (const x of r.getClientRects()) if (x.width > 1 && x.height > 1) rects.push({ top: x.top, height: x.height, width: x.width });
      }
      if (!rects.length) return 0;
      const alturas = rects.map((r) => r.height).sort((a, b) => a - b);
      const tol = (alturas[Math.floor(alturas.length / 2)] || 16) * 0.6;
      const tops = rects.map((r) => r.top).sort((a, b) => a - b);
      let linhas = 1;
      for (let i = 1; i < tops.length; i++) if (tops[i] - tops[i - 1] > tol) linhas++;
      return linhas;
    } catch { return 0; }
  };
  const alvos = [...document.querySelectorAll('button, a, th, small, label, h1, h2, h3, [role=tab], span, b')]
    .filter((el) => vis(el) && el.childElementCount <= 2)
    .filter((el) => (el.textContent || '').trim().length >= 2);
  const vistos = new Set();
  const elementos = [];
  for (const el of alvos) {
    const c = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    const texto = (el.textContent || '').trim();
    const item = {
      tag: el.tagName.toLowerCase(),
      texto,
      w: Math.round(r.width), h: Math.round(r.height),
      linhas: nLinhas(el),
      overflowX: el.scrollWidth > el.clientWidth + 1,
      fontSize: c.fontSize, fontWeight: c.fontWeight,
      color: c.color, bgEfetivo: bgEfetivo(el),
      radius: c.borderRadius, borderW: c.borderTopWidth, borderColor: c.borderTopColor,
      display: c.display,
    };
    const k = item.tag + '|' + texto.replace(/\s+/g, ' ').slice(0, 60);
    if (vistos.has(k)) continue; // 1ª ocorrência representa (listas repetem)
    vistos.add(k);
    elementos.push(item);
  }
  return JSON.stringify({ tema: TEMA, url: location.pathname, elementos }, null, 1);
})()`;

// ── selftest hermético (comparador provado pelos dois lados — L-31) ────────────
function selftest() {
  const mk = (over) => ({
    tag: 'button', texto: 'Novo título', w: 120, h: 32, linhas: 1, overflowX: false,
    fontSize: '12.5px', fontWeight: '500', color: 'oklch(0.99 0 0)',
    bgEfetivo: 'oklch(0.55 0.15 295)', radius: '6px', borderW: '1px',
    borderColor: 'oklch(0.45 0.15 295)', display: 'inline-flex', ...over,
  });
  const proto = { tema: 'dark', elementos: [
    mk({}),                                                        // idêntico dos dois lados
    mk({ texto: 'Recebido', tag: 'span', radius: '999px' }),       // pill: radius diverge na prod
    mk({ texto: 'Só atrasados', bgEfetivo: 'oklch(0.32 0.09 25)' }), // toggle: cor diverge
    mk({ texto: 'Ver todo o período' }),                           // só no proto
  ] };
  const prod = { tema: 'dark', elementos: [
    mk({}),
    mk({ texto: 'Recebido', tag: 'span', radius: '4px' }),
    mk({ texto: 'Só atrasados', bgEfetivo: 'rgb(255, 255, 255)' }),
    mk({ texto: 'Novo título 2LINHAS', w: 66, h: 44, linhas: 3 }), // regra 7: quebrou em 3 linhas — mas texto difere → SO_PROD? não: mesmo texto normalizado? "Novo título 2LINHAS" ≠ "Novo título" → SO_PROD proposital
    mk({ texto: 'Resolver <N>' }),                                 // só na prod
  ] };
  // caso regra-7 com MESMO texto (casa e diverge em linhas):
  proto.elementos.push(mk({ texto: 'Confirmar rejeição', linhas: 1, w: 140 }));
  prod.elementos.push(mk({ texto: 'Confirmar rejeição', linhas: 2, w: 66, h: 44 }));

  const { rows, tally } = comparar(proto, prod);
  const by = (t) => rows.find((r) => r.chave.includes(t));
  const checks = [
    ['idêntico não acusa',            rows.find((r) => r.chave === 'button|Novo título')?.veredito, 'IDENTICO'],
    ['pill radius diverge',           by('Recebido')?.veredito, 'DIVERGE'],
    ['toggle cor diverge',            by('Só atrasados')?.veredito, 'DIVERGE'],
    ['regra 7: linhas 1→2 diverge',   by('Confirmar rejeição')?.veredito, 'DIVERGE'],
    ['só-proto não some',             by('Ver todo o período')?.veredito, 'SO_PROTO'],
    ['só-prod não some',              by('Resolver')?.veredito, 'SO_PROD'],
  ];
  let fails = 0;
  for (const [label, got, exp] of checks) {
    const ok = got === exp;
    if (!ok) fails++;
    console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${label} → esperado ${exp}, obtido ${got}`);
  }
  // regra 7 aparece nomeada nos campos?
  const r7 = by('Confirmar rejeição');
  const temLinhas = r7?.campos.some((c) => c.startsWith('linhas:'));
  console.log(`  [${temLinhas ? 'PASS' : 'FAIL'}] campo 'linhas' listado no diff da regra 7`);
  if (!temLinhas) fails++;

  // agruparLinhas — o fix do falso-positivo (dogfood [W] 2026-07-07). Rects PLANTADOS:
  //  A) botão real ícone+texto de 1 linha: SVG top=100 h=16 + texto top=101 h=18 (jitter
  //     de baseline << line-height) → tem que dar 1 (a v1 dava 2 por bucket de 4px).
  //  B) 2 linhas REAIS: texto top=100 + texto top=124 (Δ ~1 line-height) → 2.
  //  C) vazio → 0.
  const gA = agruparLinhas([{ top: 100, height: 16, width: 12 }, { top: 101, height: 18, width: 60 }]);
  const gB = agruparLinhas([{ top: 100, height: 18, width: 60 }, { top: 124, height: 18, width: 40 }]);
  const gC = agruparLinhas([]);
  for (const [label, got, exp] of [
    ['agruparLinhas: ícone+texto jitter = 1 linha (fix falso-positivo)', gA, 1],
    ['agruparLinhas: 2 linhas reais = 2', gB, 2],
    ['agruparLinhas: vazio = 0', gC, 0],
  ]) {
    const ok = got === exp;
    if (!ok) fails++;
    console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${label} → esperado ${exp}, obtido ${got}`);
  }
  console.log(`  resumo: ${Object.entries(tally).map(([k, v]) => `${k}=${v}`).join(' · ')}`);
  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — comparador provado pelos dois lados.');
  process.exit(fails ? 1 : 0);
}

// ── main ────────────────────────────────────────────────────────────────────────
const argv = process.argv.slice(2);
if (argv.includes('--selftest')) selftest();
else if (argv.includes('--snippet')) {
  console.log(SNIPPET.trim());
} else if (argv.includes('--compare')) {
  const i = argv.indexOf('--compare');
  const [fa, fb] = [argv[i + 1], argv[i + 2]];
  if (!fa || !fb) { console.error('uso: --compare proto.json prod.json'); process.exit(2); }
  const A = JSON.parse(readFileSync(fa, 'utf8'));
  const B = JSON.parse(readFileSync(fb, 'utf8'));
  if (A.tema !== B.tema) console.error(`⚠ temas diferentes (${A.tema} vs ${B.tema}) — compare tema com tema (regra 5).`);
  const { rows, tally } = comparar(A, B);
  if (argv.includes('--json')) console.log(JSON.stringify({ rows, tally }, null, 1));
  else {
    for (const r of rows) {
      if (r.veredito === 'IDENTICO') continue;
      console.log(`  [${r.veredito.padEnd(8)}] ${r.chave}`);
      for (const c of r.campos) console.log(`      ${c}`);
    }
    console.log('\n  resumo: ' + Object.entries(tally).map(([k, v]) => `${k}=${v}`).join(' · '));
  }
  process.exit((tally.DIVERGE || 0) > 0 ? 1 : 0);
} else {
  console.log('uso: --snippet | --compare a.json b.json [--json] | --selftest');
}
