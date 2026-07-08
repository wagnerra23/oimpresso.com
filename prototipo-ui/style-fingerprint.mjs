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
//       → casa elementos por (texto normalizado + tag) E divisórias por inventário, e
//         compara campo a campo. Vereditos: IDENTICO · DIVERGE(campos) · SO_PROTO · SO_PROD.
//   node prototipo-ui/style-fingerprint.mjs --selftest
//       → fixtures herméticas inline com divergências PLANTADAS (2-linhas, cor, radius,
//         só-de-um-lado, glifo de header, divisória recolorida) — prova pelos DOIS lados (L-31).
//
// MATCHING (v2, honesto sobre limites): DOMs de protótipo e produção DIVERGEM em estrutura
// e classes — matching posicional/por-classe não existe. DUAS passadas:
//  (1) ELEMENTOS COM TEXTO — casados por TEXTO VISÍVEL normalizado + tag (glifos de
//      ordenação ⇅ e afins são strippados: furo 2, 2026-07-08). Cobre labels/botões/pills/
//      títulos/headers de coluna. Texto DINÂMICO (R$, datas) vira placeholder. O vetor
//      carrega POSIÇÃO horizontal normalizada (xnorm: furo 6, 2026-07-08 [W]) — mesmo
//      elemento em lugar diferente (alinhamento) vira DIVERGE, não IDENTICO mentiroso.
//  (2) DIVISÓRIAS/BORDAS SEM TEXTO — 2ª passada estrutural (furo 1, 2026-07-08 [W]
//      "deveria constar tudo"): toda borda visível vira entrada, casada por INVENTÁRIO
//      (lado+cor+espessura+span). Linha/régua/divisória agora CONSTAM (antes eram invisíveis).
// AMBIGUIDADE de chave (furo 4, Onda 1): quando N elementos casam a MESMA chave (2 KPIs → <BRL>),
//      pareiam por PROXIMIDADE de posição (xnorm,ynorm), não por colisão de Map — o KPI deixa de
//      ser medido contra o vizinho errado. Sobra vira SO_*.
// SO_* NÃO é ruído (furo 5, Onda 1): copy estável presente só de um lado (+ toda divisória
//      recolorida) é diferença ESTRUTURAL — triagemSO() força a triagem e reprova o exit-code.
// Onda 1 (estado-da-arte 2026-07-08) aprofundou o vetor 14→25 campos: elevação (box-shadow),
//      superfície própria vs herdada (bgProprio), padding, tipografia fina, bg-image/gradiente,
//      posição vertical (ynorm), opacity/transform. + captioning-lite (veredictoNL, verdito 1-linha).
// Ainda FORA (roadmap): a CAUSA do layout (justify-content/gap do container — Onda 2), estados
//      hover/focus/active e responsivo multi-viewport (Onda 3), ícones/sparklines sem âncora.

import { readFileSync } from 'node:fs';

// ── vetor extraído por elemento (mantido em sync com o SNIPPET abaixo) ─────────
// Onda 1 (2026-07-08, estado-da-arte fingerprint-vs-SOTA): aprofundado de 14 → 25 campos pra
// fechar os pontos cegos mapeados. Novos eixos:
//   ynorm (posição VERTICAL — o par do xnorm/furo 6);
//   letterSpacing/lineHeight/textTransform/fontFamily (tipografia FINA — antes só size/weight);
//   bgProprio (superfície PRÓPRIA vs herdada: 'none' = herda o fundo do ancestral → deixa
//     dizível "FALTA camada", que o walk-up de bgEfetivo escondia como mero delta de cor);
//   bgImage (gradiente/imagem — bgEfetivo só lia cor sólida);
//   boxShadow (ELEVAÇÃO — o "flutuar" que separa um painel do fundo; a causa do "retângulo
//     atrás do filtro" ter aparecido só como cor);
//   padding (respiro interno — antes só a caixa externa w/h);
//   opacity/transform (estado de composição).
export const CAMPOS = [
  'tag', 'w', 'h', 'xnorm', 'ynorm', 'linhas', 'overflowX',
  'fontSize', 'fontWeight', 'letterSpacing', 'lineHeight', 'textTransform', 'fontFamily',
  'color', 'bgEfetivo', 'bgProprio', 'bgImage',
  'radius', 'borderW', 'borderColor', 'boxShadow', 'padding', 'opacity', 'transform', 'display',
];

// Tolerâncias de comparação (px inteiro; strings exatas pro resto).
const TOL_PX = 1.5;

// normaliza texto visível: colapsa espaços, troca números/moeda/data por placeholder,
// e TIRA glifos de afordância de UI (setas de ordenação/chevrons) — são controle, não
// conteúdo, e coláveis ao label: header "Vencimento⇅" (prod) × "Vencimento" (proto)
// FORKA a chave → o par cai em SO_PROTO/SO_PROD e o diff real (tamanho/peso do header)
// nunca é computado. Furo 2 do fingerprint v1 (dogfood [W] 2026-07-08). Strip vem ANTES
// do slice pra não truncar em cima do glifo.
const GLIFOS_UI = /[↑↓↕⇅⇵▲▼▴▾⌃⌄‹›◂▸]/g;
export function normTexto(t) {
  return (t || '')
    .replace(GLIFOS_UI, '')
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

function ehNum(c) { return ['w', 'h', 'xnorm', 'ynorm', 'linhas', 'borderW', 'opacity'].includes(c); }

export function diffElemento(a, b) {
  const campos = [];
  for (const c of CAMPOS) {
    if (c === 'tag') continue;
    const va = a[c], vb = b[c];
    if (ehNum(c)) {
      const na = parseFloat(va) || 0, nb = parseFloat(vb) || 0;
      // linhas: qualquer diferença é DIVERGE (regra 7). xnorm/ynorm: fração 0-1 →
      // tolerância 0.04 (4%) — abaixo é ruído de sub-pixel, acima é MUDANÇA de lugar
      // (furo 6: mesmo elemento, posição diferente = DIVERGE, não IDENTICO mentiroso).
      // opacity: 0-1, tolerância 0.02.
      const tol = c === 'linhas' ? 0.01
        : (c === 'xnorm' || c === 'ynorm') ? 0.04
        : c === 'opacity' ? 0.02
        : TOL_PX;
      if (Math.abs(na - nb) > tol) campos.push(`${c}: ${va} → ${vb}`);
    } else if (String(va ?? '') !== String(vb ?? '')) {
      campos.push(`${c}: ${va} → ${vb}`);
    }
  }
  return campos;
}

// furo 1 — divisórias/bordas: chave de INVENTÁRIO (lado+cor+espessura+span), sem posição.
export function chaveDiv(d) { return 'DIV ' + d.side + '|' + d.color + '|' + d.w + '|span' + d.span; }

// Compara os inventários de divisória: matched = IDENTICO; presente só de um lado = SO_*.
// Uma divisória recolorida (ex.: warm 282 → frio 240) aparece como SO_PROTO + SO_PROD —
// é assim que o furo 1 faz o protocolo "constar" a linha que o vetor de texto não via.
export function compararDivisorias(divA = [], divB = []) {
  const mapA = new Map((divA || []).map((d) => [chaveDiv(d), d]));
  const mapB = new Map((divB || []).map((d) => [chaveDiv(d), d]));
  const rows = [];
  for (const [k, a] of mapA) rows.push({ chave: k, veredito: mapB.has(k) ? 'IDENTICO' : 'SO_PROTO', campos: [], banda: a.banda });
  for (const [k, b] of mapB) if (!mapA.has(k)) rows.push({ chave: k, veredito: 'SO_PROD', campos: [], banda: b.banda });
  return rows;
}

// furo 4 (2026-07-08) — a MESMA chave (tag|texto normalizado) pode ter VÁRIOS elementos: dois
// KPIs viram ambos 'button|<BRL>'. O Map antigo colidia → um KPI sumia e o outro era medido
// contra o vizinho errado (fonte/tamanho não-confiável). Agrupo por chave e caso os candidatos
// pela PROXIMIDADE de posição (xnorm,ynorm): o par certo é o mais perto, não "o último do Map".
function agruparPorChave(elems) {
  const m = new Map();
  for (const e of elems || []) {
    const k = chave(e);
    if (!m.has(k)) m.set(k, []);
    m.get(k).push(e);
  }
  return m;
}
function distPos(a, b) {
  const dx = (parseFloat(a.xnorm) || 0) - (parseFloat(b.xnorm) || 0);
  const dy = (parseFloat(a.ynorm) || 0) - (parseFloat(b.ynorm) || 0);
  return Math.hypot(dx, dy);
}
// casa arrays as×bs por vizinho-mais-próximo (greedy). Sobra de cada lado vira SO_*.
export function casarPorPosicao(as, bs) {
  const pares = [];
  const restoB = (bs || []).slice();
  const soA = [];
  for (const a of as || []) {
    let melhor = -1, md = Infinity;
    for (let i = 0; i < restoB.length; i++) {
      const d = distPos(a, restoB[i]);
      if (d < md) { md = d; melhor = i; }
    }
    if (melhor >= 0) pares.push([a, restoB.splice(melhor, 1)[0]]);
    else soA.push(a);
  }
  return { pares, soA, soB: restoB };
}

export function comparar(fpA, fpB) {
  const grpA = agruparPorChave(fpA.elementos);
  const grpB = agruparPorChave(fpB.elementos);
  const rows = [];
  const chaves = new Set([...grpA.keys(), ...grpB.keys()]);
  for (const k of chaves) {
    const as = grpA.get(k) || [];
    const bs = grpB.get(k) || [];
    if (!bs.length) { for (const _ of as) rows.push({ chave: k, veredito: 'SO_PROTO', campos: [] }); continue; }
    if (!as.length) { for (const _ of bs) rows.push({ chave: k, veredito: 'SO_PROD', campos: [] }); continue; }
    const { pares, soA, soB } = casarPorPosicao(as, bs);
    for (const [a, b] of pares) {
      const campos = diffElemento(a, b);
      rows.push({ chave: k, veredito: campos.length ? 'DIVERGE' : 'IDENTICO', campos });
    }
    for (const _ of soA) rows.push({ chave: k, veredito: 'SO_PROTO', campos: [] });
    for (const _ of soB) rows.push({ chave: k, veredito: 'SO_PROD', campos: [] });
  }
  // furo 1 — anexa o diff de divisórias (bordas/linhas sem texto) ao mesmo relatório.
  rows.push(...compararDivisorias(fpA.divisorias, fpB.divisorias));
  rows.sort((x, y) => x.veredito.localeCompare(y.veredito) || x.chave.localeCompare(y.chave));
  const tally = {};
  for (const r of rows) tally[r.veredito] = (tally[r.veredito] || 0) + 1;
  return { rows, tally, temaA: fpA.tema, temaB: fpB.tema };
}

// resumoCampos — HISTOGRAMA de qual PROPRIEDADE diverge mais entre os DIVERGE. É a
// diferença entre a máquina cuspir 57 linhas cruas e a máquina DIZER "borda e superfície
// são o erro dominante (56/57)". Sem isso o humano conta na mão (2026-07-08 [W]: "quero
// a máquina pegar o erro"). Devolve [[campo, nº de elementos que divergem nele], ...] desc.
export function resumoCampos(rows) {
  const freq = {};
  for (const r of rows) {
    if (r.veredito !== 'DIVERGE') continue;
    for (const c of r.campos) {
      const campo = String(c).split(':')[0].trim();
      if (campo) freq[campo] = (freq[campo] || 0) + 1;
    }
  }
  return Object.entries(freq).sort((a, b) => b[1] - a[1]);
}

// furo 5 (2026-07-08) — SO_* não é ruído: um elemento presente só de um lado, com COPY ESTÁVEL
// (não só <BRL>/<N>/<DATA>), é diferença ESTRUTURAL que EXIGE triagem — não "joga fora". E toda
// divisória SO_* (recolorida/ausente) é estrutural por definição (é o payoff do furo 1). Devolve
// as rows que a máquina obriga a olhar; alimenta o exit-code (falha se houver).
export function triagemSO(rows) {
  return (rows || []).filter((r) => r.veredito === 'SO_PROTO' || r.veredito === 'SO_PROD')
    .filter((r) => {
      if (r.chave.startsWith('DIV ')) return true; // divisória: sempre estrutural
      const txt = r.chave.slice(r.chave.indexOf('|') + 1);
      const real = txt.replace(/<BRL>|<N>|<DATA>/g, '').trim();
      return real.length >= 2; // copy real além de placeholders dinâmicos
    });
}

// captioning-lite (2026-07-08) — verdito em UMA linha (PT), derivado do diff ESTRUTURADO. É a
// direção 2026 (semantic change captioning, arXiv 2607.01728) quase de graça: não é AI, é regra
// sobre resumoCampos + triagemSO, agrupando campos em FAMÍLIAS legíveis (superfície/borda/
// elevação/tipografia/espaçamento/posição). Diz o que o humano leria: "superfície+borda frias".
const FAMILIA_CAMPO = {
  bgEfetivo: 'superfície', bgProprio: 'superfície', bgImage: 'superfície',
  borderColor: 'borda', borderW: 'borda', radius: 'borda',
  boxShadow: 'elevação',
  color: 'texto', fontSize: 'tipografia', fontWeight: 'tipografia', letterSpacing: 'tipografia',
  lineHeight: 'tipografia', textTransform: 'tipografia', fontFamily: 'tipografia',
  padding: 'espaçamento', w: 'tamanho', h: 'tamanho',
  xnorm: 'posição', ynorm: 'posição', linhas: 'quebra', overflowX: 'overflow',
  opacity: 'opacidade', transform: 'transform', display: 'display',
};
export function veredictoNL(rows) {
  const rc = resumoCampos(rows);
  const tally = {};
  for (const r of rows || []) tally[r.veredito] = (tally[r.veredito] || 0) + 1;
  const div = tally.DIVERGE || 0;
  const soT = triagemSO(rows).length;
  if (!div && !soT) return 'fiel: nenhuma divergência nem miss estrutural.';
  const fam = {};
  for (const [campo, n] of rc) { const f = FAMILIA_CAMPO[campo] || campo; fam[f] = (fam[f] || 0) + n; }
  const famOrd = Object.entries(fam).sort((a, b) => b[1] - a[1]).slice(0, 3).map(([f]) => f);
  const partes = [];
  if (div) partes.push(`${div} elemento(s) divergem — dominante: ${famOrd.join(' + ') || '—'}`);
  if (soT) partes.push(`${soT} miss estrutural(is) pra triar`);
  return partes.join('; ') + '.';
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
  // bgProprio — o fundo DECLARADO no próprio elemento (sem walk-up). 'none' = herda do ancestral.
  // É o que distingue "este elemento TEM painel" de "empresta o fundo da página" (furo superfície).
  const bgProprio = (el) => {
    const b = getComputedStyle(el).backgroundColor;
    return (b && b !== 'rgba(0, 0, 0, 0)' && b !== 'transparent') ? b : 'none';
  };
  // normaliza background-image: só o TIPO importa pra fidelidade (sólido × gradiente × imagem),
  // não a data-URI gigante. bgEfetivo lê só cor sólida; isto acusa "virou/deixou de ser gradiente".
  const normBgImg = (v) => {
    if (!v || v === 'none') return 'none';
    if (v.startsWith('linear-gradient')) return 'linear-gradient';
    if (v.startsWith('radial-gradient')) return 'radial-gradient';
    if (v.startsWith('conic-gradient')) return 'conic-gradient';
    if (v.startsWith('url')) return 'url';
    return v.slice(0, 24);
  };
  // box-shadow colapsado (espaços normalizados, cap 60) — presença+cor+offset da ELEVAÇÃO.
  const normShadow = (v) => (!v || v === 'none') ? 'none' : v.replace(/\s+/g, ' ').slice(0, 60);
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
      // xnorm — posição horizontal como FRAÇÃO da largura da viewport (furo 6): captura
      // ONDE o elemento senta. Mesmo elemento em lugar diferente (ex.: pill de período
      // right-justify no proto × left-packed na prod) vira DIVERGE em vez de IDENTICO.
      xnorm: Math.round((r.left / (document.documentElement.clientWidth || 1)) * 100) / 100,
      // ynorm — o par VERTICAL do xnorm (Onda 1): posição no eixo Y como fração da altura
      // TOTAL do documento (inclui scroll), pra ordem/espaçamento vertical deixar de ser cego.
      ynorm: Math.round(((r.top + window.scrollY) / (document.documentElement.scrollHeight || 1)) * 100) / 100,
      linhas: nLinhas(el),
      overflowX: el.scrollWidth > el.clientWidth + 1,
      fontSize: c.fontSize, fontWeight: c.fontWeight,
      letterSpacing: c.letterSpacing, lineHeight: c.lineHeight, textTransform: c.textTransform,
      fontFamily: (c.fontFamily || '').split(',')[0].replace(/["']/g, '').trim(), // 1ª família só
      color: c.color, bgEfetivo: bgEfetivo(el), bgProprio: bgProprio(el), bgImage: normBgImg(c.backgroundImage),
      radius: c.borderRadius, borderW: c.borderTopWidth, borderColor: c.borderTopColor,
      boxShadow: normShadow(c.boxShadow),
      padding: [c.paddingTop, c.paddingRight, c.paddingBottom, c.paddingLeft].join(' '),
      opacity: c.opacity, transform: c.transform,
      display: c.display,
    };
    const k = item.tag + '|' + texto.replace(/\s+/g, ' ').slice(0, 60);
    if (vistos.has(k)) continue; // 1ª ocorrência representa (listas repetem)
    vistos.add(k);
    elementos.push(item);
  }
  // ── 2ª passada — DIVISÓRIAS/BORDAS (furo 1: o vetor de texto NÃO vê linha/borda/régua).
  // Varre TODA borda visível (>=0.4px, cor não-transparente) e a inventaria por
  // lado + cor + espessura + span (bucket 20px). Chave = inventário (NÃO posição): robusto
  // a drift de layout. Uma divisória que muda de cor aparece como SO_PROTO(cor velha) +
  // SO_PROD(cor nova) — o olho vê o delta. 'banda' fica só como contexto de onde está.
  const transpD = (c) => !c || c === 'rgba(0, 0, 0, 0)' || c === 'transparent';
  const divisorias = [];
  const vistosD = new Set();
  for (const el of document.querySelectorAll('*')) {
    const r = el.getBoundingClientRect();
    if (r.width < 40 || r.height > 500 || !vis(el)) continue;
    const dc = getComputedStyle(el);
    for (const side of ['Top', 'Bottom', 'Left', 'Right']) {
      const w = parseFloat(dc['border' + side + 'Width']) || 0;
      const col = dc['border' + side + 'Color'];
      if (w < 0.4 || transpD(col)) continue;
      const isH = side === 'Top' || side === 'Bottom';
      const pos = side === 'Top' ? r.top : side === 'Bottom' ? r.bottom : side === 'Left' ? r.left : r.right;
      const item = { side, w: Math.round(w * 100) / 100, color: col, span: Math.round((isH ? r.width : r.height) / 20) * 20, banda: Math.round(pos) };
      const k = side + '|' + col + '|' + item.w + '|' + item.span;
      if (vistosD.has(k)) continue;
      vistosD.add(k);
      divisorias.push(item);
    }
  }
  return JSON.stringify({ tema: TEMA, url: location.pathname, elementos, divisorias }, null, 1);
})()`;

// ── selftest hermético (comparador provado pelos dois lados — L-31) ────────────
function selftest() {
  const mk = (over) => ({
    tag: 'button', texto: 'Novo título', w: 120, h: 32, xnorm: 0.1, ynorm: 0.1, linhas: 1, overflowX: false,
    fontSize: '12.5px', fontWeight: '500', letterSpacing: 'normal', lineHeight: '20px',
    textTransform: 'none', fontFamily: 'Inter', color: 'oklch(0.99 0 0)',
    bgEfetivo: 'oklch(0.55 0.15 295)', bgProprio: 'oklch(0.55 0.15 295)', bgImage: 'none',
    radius: '6px', borderW: '1px', borderColor: 'oklch(0.45 0.15 295)',
    boxShadow: 'none', padding: '6px 10px 6px 10px', opacity: '1', transform: 'none',
    display: 'inline-flex', ...over,
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
  // furo 2 (glifo ⇅ de ordenação cola no header e forka a chave): proto "Vencimento" ×
  // prod "Vencimento⇅" TÊM que parear (normTexto tira o glifo) e divergir no fontSize —
  // sem o strip iam pra SO_PROTO/SO_PROD e o diff de tamanho sumia (2026-07-08 [W]).
  proto.elementos.push(mk({ tag: 'th', texto: 'Vencimento', fontSize: '10.5px' }));
  prod.elementos.push(mk({ tag: 'th', texto: 'Vencimento⇅', fontSize: '10px' }));
  // furo 6 (posição/alinhamento): a pill "Dia" é o MESMO elemento (estilo idêntico), mas o
  // proto right-justify (xnorm 0.85) e a prod left-packed (xnorm 0.34) → tem que DIVERGIR em
  // xnorm, não virar IDENTICO mentiroso (2026-07-08 [W] "por que o protocolo não pegou?").
  proto.elementos.push(mk({ texto: 'Dia', xnorm: 0.85 }));
  prod.elementos.push(mk({ texto: 'Dia', xnorm: 0.34 }));
  // resumoCampos: 3 elementos divergindo SÓ em borderColor (warm 282 → frio 240) → a máquina
  // tem que NOMEAR 'borderColor' como o campo dominante, sem o humano contar (2026-07-08 [W]
  // "quero a máquina pegar o erro"). Espelha o caso real: 56/57 divergem em borda+superfície.
  for (const t of ['B1', 'B2', 'B3']) {
    proto.elementos.push(mk({ texto: t, borderColor: 'oklch(0.335 0.012 282)' }));
    prod.elementos.push(mk({ texto: t, borderColor: 'oklch(0.28 0.008 240)' }));
  }
  // furo 1 (divisórias/bordas sem texto): a linha neutra é igual; a divisória de accent é
  // roxo BRILHANTE 0.7 no proto e vira FRIA 240 na prod → o inventário mostra SO_PROTO +
  // SO_PROD (o "constar tudo" que o vetor de texto nunca via). 2026-07-08 [W].
  proto.divisorias = [
    { side: 'Bottom', w: 1, color: 'oklch(0.335 0.012 282)', span: 1200, banda: 150 }, // neutra warm — igual dos 2 lados
    { side: 'Bottom', w: 1, color: 'oklch(0.7 0.15 295)',   span: 100,  banda: 200 }, // accent roxo brilhante — só proto
  ];
  prod.divisorias = [
    { side: 'Bottom', w: 1, color: 'oklch(0.335 0.012 282)', span: 1200, banda: 150 }, // neutra warm — idem
    { side: 'Bottom', w: 1, color: 'oklch(0.28 0.008 240)',  span: 100,  banda: 200 }, // frio 240 — só prod (warm-miss)
  ];

  // ── Onda 1 (2026-07-08): cada EIXO NOVO ganha uma divergência plantada, provada dos 2 lados.
  // box-shadow / elevação — o "flutuar" do painel: proto tem sombra, prod achatou (o "falta camada").
  proto.elementos.push(mk({ texto: 'Painel filtro', boxShadow: '0 1px 3px 0 rgba(0,0,0,0.2)' }));
  prod.elementos.push(mk({ texto: 'Painel filtro', boxShadow: 'none' }));
  // padding / respiro interno — proto respira, prod colou (0).
  proto.elementos.push(mk({ texto: 'Cartão KPI', padding: '12px 16px 12px 16px' }));
  prod.elementos.push(mk({ texto: 'Cartão KPI', padding: '0px 0px 0px 0px' }));
  // bgProprio — superfície PRÓPRIA vs herdada: proto TEM painel (0.238), prod herda o fundo ('none').
  // bgEfetivo pode até ficar perto; é o bgProprio que denuncia "FALTA a camada".
  proto.elementos.push(mk({ texto: 'Faixa', bgProprio: 'oklch(0.238 0.01 282)', bgEfetivo: 'oklch(0.238 0.01 282)' }));
  prod.elementos.push(mk({ texto: 'Faixa', bgProprio: 'none', bgEfetivo: 'oklch(0.21 0.01 282)' }));
  // tipografia fina — letter-spacing + text-transform (antes invisíveis).
  proto.elementos.push(mk({ texto: 'CABECALHO', letterSpacing: '0.5px', textTransform: 'uppercase' }));
  prod.elementos.push(mk({ texto: 'CABECALHO', letterSpacing: 'normal', textTransform: 'none' }));
  // bg-image — proto usa gradiente, prod virou cor sólida ('none').
  proto.elementos.push(mk({ texto: 'Hero', bgImage: 'linear-gradient' }));
  prod.elementos.push(mk({ texto: 'Hero', bgImage: 'none' }));
  // ynorm — mesmo elemento, posição VERTICAL diferente (o par do furo 6).
  proto.elementos.push(mk({ texto: 'Nota rodape', ynorm: 0.9 }));
  prod.elementos.push(mk({ texto: 'Nota rodape', ynorm: 0.55 }));
  // furo 4 — DOIS KPIs viram ambos 'button|<BRL>'. Têm que parear por POSIÇÃO (xnorm), não colidir:
  // o da esquerda (0.2) mudou fontSize 28→22; o da direita (0.8) ficou igual. Antes o Map colidia
  // e um sumia / era medido contra o errado.
  proto.elementos.push(mk({ texto: 'R$ 1.234', xnorm: 0.2, ynorm: 0.3, fontSize: '28px' }));
  proto.elementos.push(mk({ texto: 'R$ 5.678', xnorm: 0.8, ynorm: 0.3, fontSize: '28px' }));
  prod.elementos.push(mk({ texto: 'R$ 9.999', xnorm: 0.2, ynorm: 0.3, fontSize: '22px' }));
  prod.elementos.push(mk({ texto: 'R$ 0.000', xnorm: 0.8, ynorm: 0.3, fontSize: '28px' }));

  const { rows, tally } = comparar(proto, prod);
  const by = (t) => rows.find((r) => r.chave.includes(t));
  const checks = [
    ['idêntico não acusa',            rows.find((r) => r.chave === 'button|Novo título')?.veredito, 'IDENTICO'],
    ['pill radius diverge',           by('Recebido')?.veredito, 'DIVERGE'],
    ['toggle cor diverge',            by('Só atrasados')?.veredito, 'DIVERGE'],
    ['regra 7: linhas 1→2 diverge',   by('Confirmar rejeição')?.veredito, 'DIVERGE'],
    ['só-proto não some',             by('Ver todo o período')?.veredito, 'SO_PROTO'],
    ['só-prod não some',              by('Resolver')?.veredito, 'SO_PROD'],
    ['header glifo ⇅ pareia (furo 2)', by('Vencimento')?.veredito, 'DIVERGE'],
    ['divisória neutra idêntica (furo 1)', by('DIV Bottom|oklch(0.335 0.012 282)')?.veredito, 'IDENTICO'],
    ['divisória accent 0.7 só-proto (furo 1)', by('oklch(0.7 0.15 295)|1|span100')?.veredito, 'SO_PROTO'],
    ['divisória fria 240 só-prod = o miss (furo 1)', by('oklch(0.28 0.008 240)')?.veredito, 'SO_PROD'],
    ['furo 6: mesmo elemento, posição (xnorm) diverge', by('button|Dia')?.veredito, 'DIVERGE'],
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
  // furo 2: o header ⇅ pareou (checado acima) E o diff de tamanho aparece nos campos.
  const hg = by('Vencimento');
  const temFs = hg?.campos.some((c) => c.startsWith('fontSize:'));
  console.log(`  [${temFs ? 'PASS' : 'FAIL'}] header ⇅ recuperado: fontSize listado (não virou SO_*)`);
  if (!temFs) fails++;
  // furo 6: o campo xnorm aparece no diff (prova que é a POSIÇÃO que difere, não o estilo).
  const f6 = by('button|Dia');
  const temXnorm = f6?.campos.some((c) => c.startsWith('xnorm:'));
  console.log(`  [${temXnorm ? 'PASS' : 'FAIL'}] furo 6: xnorm listado no diff (posição capturada)`);
  if (!temXnorm) fails++;
  // resumoCampos: a MÁQUINA nomeia o campo dominante (borderColor, 3 elementos) sem contar na mão.
  const rc = resumoCampos(rows);
  const dominante = rc[0] && rc[0][0] === 'borderColor' && rc[0][1] === 3;
  console.log(`  [${dominante ? 'PASS' : 'FAIL'}] resumoCampos aponta campo dominante (${rc[0] ? rc[0].join('=') : '—'})`);
  if (!dominante) fails++;

  // ── Onda 1 — cada EIXO NOVO prova que a máquina enxerga o que era cego (DIVERGE + campo) ──
  const temCampo = (t, campo) => (by(t)?.campos || []).some((c) => c.startsWith(campo + ':'));
  for (const [label, t, campo] of [
    ['box-shadow/elevação capturado',                    'Painel filtro', 'boxShadow'],
    ['padding interno capturado',                        'Cartão KPI',    'padding'],
    ['superfície própria (bgProprio) vê "falta camada"', 'Faixa',         'bgProprio'],
    ['tipografia fina: letter-spacing',                  'CABECALHO',     'letterSpacing'],
    ['tipografia fina: text-transform',                  'CABECALHO',     'textTransform'],
    ['bg-image/gradiente capturado',                     'Hero',          'bgImage'],
    ['ynorm: posição vertical capturada',                'Nota rodape',   'ynorm'],
  ]) {
    const ok = by(t)?.veredito === 'DIVERGE' && temCampo(t, campo);
    if (!ok) fails++;
    console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${label} → esperado DIVERGE+${campo} (obtido ${by(t)?.veredito || '—'})`);
  }
  // furo 4 — os DOIS KPIs <BRL> pareiam por POSIÇÃO (não colidem): 1 DIVERGE (fontSize) + 1 IDENTICO.
  const brl = rows.filter((r) => r.chave === 'button|<BRL>');
  const brlOk = brl.length === 2
    && brl.filter((r) => r.veredito === 'DIVERGE').length === 1
    && brl.filter((r) => r.veredito === 'IDENTICO').length === 1
    && brl.find((r) => r.veredito === 'DIVERGE')?.campos.some((c) => c.startsWith('fontSize:'));
  if (!brlOk) fails++;
  console.log(`  [${brlOk ? 'PASS' : 'FAIL'}] furo 4: 2 KPIs <BRL> pareados por posição (n=${brl.length}, ${brl.map((r) => r.veredito).join('/')})`);
  // furo 5 — triagemSO OBRIGA a olhar SO_* estruturais (copy estável + toda divisória recolorida).
  const tri = triagemSO(rows);
  const triOk = tri.some((r) => r.chave.includes('Resolver'))
    && tri.some((r) => r.chave.includes('Ver todo o período'))
    && tri.some((r) => r.chave.startsWith('DIV ') && r.chave.includes('0.28 0.008 240'));
  if (!triOk) fails++;
  console.log(`  [${triOk ? 'PASS' : 'FAIL'}] furo 5: triagemSO força SO_* estruturais (n=${tri.length})`);
  // captioning-lite — verdito em 1 linha, não-vazio, nomeia divergência + miss estrutural.
  const nl = veredictoNL(rows);
  const nlOk = typeof nl === 'string' && /divergem/.test(nl) && /triar/.test(nl);
  if (!nlOk) fails++;
  console.log(`  [${nlOk ? 'PASS' : 'FAIL'}] captioning-lite: "${nl}"`);

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
    // A máquina NOMEIA o padrão dominante — qual propriedade erra na maioria dos elementos.
    const rc = resumoCampos(rows);
    const totalDiv = tally.DIVERGE || 0;
    if (rc.length && totalDiv) {
      console.log('\n  padrão dominante (campo · elementos que divergem nele):');
      for (const [campo, n] of rc.slice(0, 8)) {
        const sist = n >= totalDiv * 0.7 ? '  ⚠ SISTEMÁTICO' : '';
        console.log(`      ${campo.padEnd(12)} ${n}/${totalDiv}${sist}`);
      }
    }
    // furo 5 — SO_* estruturais NÃO são ruído: força a triagem (copy estável + toda divisória).
    const tri = triagemSO(rows);
    if (tri.length) {
      console.log(`\n  ⚠ triagem obrigatória — ${tri.length} miss estrutural(is) (presente só de um lado):`);
      for (const r of tri.slice(0, 12)) console.log(`      [${r.veredito.replace('SO_', 'só ')}] ${r.chave}`);
      if (tri.length > 12) console.log(`      … +${tri.length - 12}`);
    }
    // captioning-lite — o verdito em uma linha (direção 2026: semantic change captioning).
    console.log('\n  verdito: ' + veredictoNL(rows));
  }
  // furo 5 — falha o exit-code também quando há miss estrutural pra triar (não só DIVERGE).
  process.exit(((tally.DIVERGE || 0) > 0 || triagemSO(rows).length > 0) ? 1 : 0);
} else {
  console.log('uso: --snippet | --compare a.json b.json [--json] | --selftest');
}
