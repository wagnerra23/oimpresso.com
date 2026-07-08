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
// Onda 2 (2026-07-08) acrescentou 2 passadas: (3) CONTAINERS de layout — a CAUSA do "elemento
//      moveu" é a regra do PAI (display/justify-content/gap/direção), inventariada por assinatura;
//      (4) COMPOSTOS/CARDS com >2 filhos (furo 3) — superfície ancorada em texto agregado (um card
//      que ACHATA perde sombra+padding e agora CONSTA, antes era invisível ao vetor de texto).
// Ainda FORA (Onda 3, precisa driver/dep): estados hover/focus/active e responsivo multi-viewport
//      (harness Playwright), backstop perceptual pra ícones/sparklines sem âncora (dep SSIM → ADR).

import { readFileSync } from 'node:fs';
import { pathToFileURL, fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { resolve, dirname } from 'node:path';

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
function agruparPorChave(elems, keyFn = chave) {
  const m = new Map();
  for (const e of elems || []) {
    const k = keyFn(e);
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

// comparador GENÉRICO de duas listas ancoradas por texto: agrupa por chave (keyFn), casa
// candidatos ambíguos por posição (furo 4), e diffa campo-a-campo (diffFn). Reusado por
// elementos (1ª passada) E compostos/cards (furo 3, Onda 2) — mesma mecânica, âncora diferente.
export function compararGrupos(as, bs, keyFn, diffFn) {
  const grpA = agruparPorChave(as, keyFn);
  const grpB = agruparPorChave(bs, keyFn);
  const rows = [];
  for (const k of new Set([...grpA.keys(), ...grpB.keys()])) {
    const la = grpA.get(k) || [], lb = grpB.get(k) || [];
    if (!lb.length) { for (const _ of la) rows.push({ chave: k, veredito: 'SO_PROTO', campos: [] }); continue; }
    if (!la.length) { for (const _ of lb) rows.push({ chave: k, veredito: 'SO_PROD', campos: [] }); continue; }
    const { pares, soA, soB } = casarPorPosicao(la, lb);
    for (const [a, b] of pares) {
      const campos = diffFn(a, b);
      rows.push({ chave: k, veredito: campos.length ? 'DIVERGE' : 'IDENTICO', campos });
    }
    for (const _ of soA) rows.push({ chave: k, veredito: 'SO_PROTO', campos: [] });
    for (const _ of soB) rows.push({ chave: k, veredito: 'SO_PROD', campos: [] });
  }
  return rows;
}

// ── Onda 2: CONTAINERS de layout (a CAUSA) ─────────────────────────────────────
// furo 6 via o SINTOMA (o filho moveu); a CAUSA é a regra do PAI (justify-content/gap/direção).
// Inventário por assinatura de layout (como as divisórias, furo 1): um container re-gapado/
// re-justificado aparece como SO_PROTO(regra velha)+SO_PROD(nova) — o olho vê a causa direto.
export function chaveContainer(c) {
  return 'CTR ' + [c.display, c.dir, c.justify, c.align, c.gap, 'span' + c.span, 'f' + c.filhos].join('|');
}
export function compararContainers(a = [], b = []) {
  const mapA = new Map((a || []).map((c) => [chaveContainer(c), c]));
  const mapB = new Map((b || []).map((c) => [chaveContainer(c), c]));
  const rows = [];
  for (const [k] of mapA) rows.push({ chave: k, veredito: mapB.has(k) ? 'IDENTICO' : 'SO_PROTO', campos: [] });
  for (const [k] of mapB) if (!mapA.has(k)) rows.push({ chave: k, veredito: 'SO_PROD', campos: [] });
  return rows;
}

// ── Onda 2: COMPOSTOS/CARDS (furo 3) ───────────────────────────────────────────
// Controles/cards com >2 filhos escapam do vetor de texto (childElementCount<=2 na 1ª passada).
// Captura a SUPERFÍCIE do composto e casa por texto AGREGADO normalizado (+ posição, furo 4).
const CAMPOS_COMPOSTO = ['w', 'h', 'xnorm', 'ynorm', 'bgProprio', 'bgImage', 'radius', 'borderW', 'borderColor', 'boxShadow', 'padding', 'filhos'];
export function chaveComposto(c) { return 'CARD ' + c.tag + '|' + normTexto(c.texto); }
export function diffComposto(a, b) {
  const campos = [];
  for (const c of CAMPOS_COMPOSTO) {
    const va = a[c], vb = b[c];
    if (['w', 'h', 'xnorm', 'ynorm', 'borderW', 'filhos'].includes(c)) {
      const na = parseFloat(va) || 0, nb = parseFloat(vb) || 0;
      const tol = (c === 'xnorm' || c === 'ynorm') ? 0.04 : c === 'filhos' ? 0.01 : TOL_PX;
      if (Math.abs(na - nb) > tol) campos.push(`${c}: ${va} → ${vb}`);
    } else if (String(va ?? '') !== String(vb ?? '')) campos.push(`${c}: ${va} → ${vb}`);
  }
  return campos;
}

export function comparar(fpA, fpB) {
  // 1ª passada — elementos com texto (âncora texto+tag, pareados por posição no ambíguo).
  const rows = compararGrupos(fpA.elementos, fpB.elementos, chave, diffElemento);
  // furo 1 — divisórias/bordas sem texto (inventário lado+cor+espessura+span).
  rows.push(...compararDivisorias(fpA.divisorias, fpB.divisorias));
  // Onda 2 — containers de layout (a causa) por inventário de assinatura.
  rows.push(...compararContainers(fpA.containers, fpB.containers));
  // Onda 2 — compostos/cards (>2 filhos) por superfície ancorada em texto agregado.
  rows.push(...compararGrupos(fpA.compostos, fpB.compostos, chaveComposto, diffComposto));
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
      // divisória (furo 1) e container de layout (Onda 2) recolorido/re-gapado: sempre estrutural.
      if (r.chave.startsWith('DIV ') || r.chave.startsWith('CTR ')) return true;
      const txt = r.chave.slice(r.chave.indexOf('|') + 1);
      const real = txt.replace(/<BRL>|<N>|<DATA>/g, '').trim();
      return real.length >= 2; // copy real além de placeholders dinâmicos (cobre CARD e elementos)
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
  padding: 'espaçamento', w: 'tamanho', h: 'tamanho', filhos: 'estrutura',
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

// ── TRAVA DE ÂNCORA (compare-time) ─────────────────────────────────────────────
// Por que existe (2026-07-08, [W] "as máquinas não estão funcionando em conjunto com os hooks"):
// o hook block-ancora-no-olho só vê `Read` de png; anchoring errado acontece no Chrome (servir/
// navegar/colar snippet) — superfície NÃO-hookável. E ancora.mjs (que TEM a resposta) era advisory.
// Resultado: comparei o Financeiro contra o shell `oimpresso.com.html` (âncora podre já pega em
// 07-06) e nada mecânico barrou até o Wagner. A cola tem que viver NA MÁQUINA, não no hook:
//   · resolverAncora — consulta o resolvedor canônico ancora.mjs por SUBPROCESSO (não import:
//     se ancora.mjs quebrar, LANÇA e o --compare RECUSA — fail-CLOSED, nunca âncora não verificada).
//   · verificarAncora — o --compare exige que a captura DECLARE a âncora (assada por --snippet
//     <tela>) e que ela BATA com o related_prototype do charter. Sem declaração → recusa;
//     divergente → recusa; opt-out só com `--sem-ancora <razão>` (explícito, logado).
// RESÍDUO HONESTO (declarado na ADR proposta 'trava-ancora-compare'): a declaração é um CLAIM —
// `--snippet <tela>` assa a âncora certa, mas quem cola pode colar na página errada; o browser é
// não-hookável e não há oráculo formal acima do charter. Reduz + torna auditável; NÃO blinda o
// Chrome. É o mesmo teto honesto que o próprio block-ancora-no-olho confessa.
function resolverAncora(tela) {
  const script = fileURLToPath(new URL('./ancora.mjs', import.meta.url));
  const r = spawnSync(process.execPath, [script, tela], { encoding: 'utf8' });
  if (r.status !== 0 || !r.stdout) throw new Error(`ancora.mjs falhou pra "${tela}": ${(r.stderr || '').trim() || 'sem saída'}`);
  const m = r.stdout.match(/âncora ✓:\s*\[[^\]]*\]\s*(\S+)/);
  if (!m) throw new Error(`ancora.mjs não devolveu related_prototype pra "${tela}"`);
  return m[1];
}
export function verificarAncora(protoFp, esperada) {
  const decl = protoFp && protoFp.ancora;
  if (!decl) return { ok: false, motivo: 'captura do proto SEM âncora declarada — gere com `--snippet <Mod/Tela>` (assa a âncora resolvida por ancora.mjs), ou opt-out com `--sem-ancora <razão>`' };
  if (decl !== esperada) return { ok: false, motivo: `âncora DIVERGENTE — a captura declara "${decl}", mas o charter quer "${esperada}" (comparando contra a fonte errada?)` };
  return { ok: true, motivo: `âncora ✓ ${esperada}` };
}

// ── F5 (2026-07-08, revisão adversarial): a trava acima só confere um CLAIM — a âncora é assada
// do ARGUMENTO `--snippet <tela>`, não do DOM. Colar o snippet ancorado no tab do SHELL passa
// (declara certo, DOM errado). Este check transforma o claim em EVIDÊNCIA FRACA-PORÉM-REAL:
// extrai os RÓTULOS distintivos do .jsx da âncora e mede quantos aparecem no texto capturado —
// overlap baixo ⇒ o DOM NÃO veio daquele arquivo (recusa). RESÍDUO: JSX não renderiza offline
// (sem computed styles) → só overlap de TEXTO, não fidelidade visual; rótulos vindos de dados
// (outro arquivo) não contam. Heurística CONSERVADORA (alta precisão): só considera rótulos
// multi-palavra OU acentuados (rótulo de UI PT-BR real, não identificador de código), e só recusa
// quando há rótulos SUFICIENTES e QUASE NENHUM aparece.
const STOPWORDS_ROT = new Set(['true', 'false', 'null', 'undefined', 'function', 'return', 'import', 'export', 'default', 'className']);
// Extração RAFINADA (fix 2026-07-08, pego pelo teste-do-processo): a v1 ("multi-palavra OU
// acentuado") pegava CÓDIGO com espaço (`: dt.toLocaleString(`, `+ (n / 1000)`) e classNames
// (`os-btn ghost fin-hero`) — inflava o denominador com strings que NUNCA aparecem no DOM
// renderizado. Numa captura REAL do financeiro-page.jsx isso derrubava o overlap de 20% (limpo,
// 82 rótulos) pra 7% (poluído, 237) → FALSO-REFUSE. Aqui só passa NATURAL-LANGUAGE de UI:
// começa com letra, sem operadores/estruturais de código, sem kebab-case (className) nem tailwind,
// ≤5 palavras, não termina em `,`/`:`.
export function rotulosDistintivos(src) {
  const out = new Set();
  const re = /["']([^"'\n]{4,40})["']/g;
  let m;
  while ((m = re.exec(src || ''))) {
    const s = m[1].trim();
    if (!/^[\p{L}]/u.test(s)) continue;                                   // começa com letra
    if (!/[a-zà-ÿ]/i.test(s)) continue;                                   // tem letra
    if (!(/\s/.test(s) || /[À-ÿ]/.test(s))) continue;                     // multi-palavra OU acentuado
    if (/[=<>{}()/\\;$`[\]|&]/.test(s)) continue;                         // sem operadores/estruturais
    if (/-[\d.]/.test(s)) continue;                                       // sem px-1.5 / py-0.5
    if (/\b[a-zà-ÿ]+-[a-zà-ÿ]/i.test(s)) continue;                        // sem kebab-case (className)
    if (/\btolocale/i.test(s)) continue;                                  // sem chamadas de método
    if (/[,:]$/.test(s)) continue;                                        // não termina em pontuação de código
    if (s.split(/\s+/).length > 5) continue;                             // rótulo curto
    if (STOPWORDS_ROT.has(s.toLowerCase())) continue;
    out.add(s.toLowerCase());
  }
  return [...out];
}
const normSimples = (t) => (t || '').toLowerCase().replace(/\s+/g, ' ').trim();
// overlapConteudo — recebe os rótulos da âncora + os textos da captura. Devolve {ok,motivo,...}.
// Pulado (ok) se rótulos < minRotulos (âncora sem material distintivo pra julgar).
// fracaoMin 0.08 (calibrado 2026-07-08 contra captura REAL: financeiro-page.jsx renderizado dá
// ~20% dos rótulos limpos presentes; o shell dá ~0%. 8% separa com folga e tolera telas esparsas
// — o objetivo é pegar o caso GROSSO (página errada, ~0%), não fidelidade fina).
export function overlapConteudo(rotulos, protoTexts, minRotulos = 6, fracaoMin = 0.08) {
  const rs = (rotulos || []).filter(Boolean);
  if (rs.length < minRotulos) return { ok: true, motivo: `conteúdo: só ${rs.length} rótulos distintivos na âncora — check pulado (sem material)`, achados: 0, total: rs.length };
  const hay = (protoTexts || []).map(normSimples).join('  ');
  const achados = rs.filter((r) => hay.includes(normSimples(r))).length;
  const fracao = achados / rs.length;
  if (fracao < fracaoMin) return { ok: false, motivo: `conteúdo SUSPEITO — só ${achados}/${rs.length} (${Math.round(fracao * 100)}%) dos rótulos da âncora aparecem na captura → o DOM capturado provavelmente NÃO é do arquivo da âncora (colou na página errada?). Override: --sem-ancora <razão>.`, achados, total: rs.length };
  return { ok: true, motivo: `conteúdo ✓ ${achados}/${rs.length} rótulos da âncora presentes (${Math.round(fracao * 100)}%)`, achados, total: rs.length };
}

// ── snippet auto-contido (roda DENTRO da página; espelho do vetor CAMPOS) ──────
// Exportado (Onda 3) pro harness Playwright injetar via page.evaluate(SNIPPET) — a mesma
// string que o modo --snippet imprime pro console/MCP. Fonte única: aqui.
export const SNIPPET = String.raw`
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
  // ── 3ª passada — CONTAINERS de layout (Onda 2: a CAUSA do "elemento moveu" é a regra do PAI).
  // Inventaria flex/grid com >=2 filhos por assinatura (display|dir|justify|align|gap|span|filhos).
  // Um container re-gapado/re-justificado vira SO_PROTO(velho)+SO_PROD(novo) — a causa fica visível.
  const containers = [];
  const vistosC = new Set();
  for (const el of document.querySelectorAll('*')) {
    const cs = getComputedStyle(el);
    const disp = cs.display;
    if (disp !== 'flex' && disp !== 'inline-flex' && disp !== 'grid' && disp !== 'inline-grid') continue;
    if (el.childElementCount < 2 || !vis(el)) continue;
    const r = el.getBoundingClientRect();
    if (r.width < 40) continue;
    const item = {
      display: disp,
      dir: cs.flexDirection || cs.gridAutoFlow || '',
      justify: cs.justifyContent, align: cs.alignItems, gap: cs.gap,
      span: Math.round(r.width / 20) * 20, filhos: el.childElementCount,
      banda: Math.round(r.top + window.scrollY),
    };
    const k = [item.display, item.dir, item.justify, item.align, item.gap, item.span, item.filhos].join('|');
    if (vistosC.has(k)) continue;
    vistosC.add(k);
    containers.push(item);
  }
  // ── 4ª passada — COMPOSTOS/CARDS (furo 3, Onda 2): >2 filhos escapam do vetor de texto.
  // Só os que SÃO uma superfície (bg próprio OU borda OU sombra) — capturam bbox+superfície,
  // ancorados pelo texto AGREGADO normalizado (casados por posição no ambíguo, furo 4).
  const compostos = [];
  const vistosK = new Set();
  for (const el of document.querySelectorAll('div, section, article, li, form, header, aside')) {
    if (el.childElementCount <= 2 || !vis(el)) continue; // <=2 já é 1ª passada
    const cs = getComputedStyle(el);
    const temSuperficie = bgProprio(el) !== 'none'
      || (parseFloat(cs.borderTopWidth) || 0) >= 0.4
      || (cs.boxShadow && cs.boxShadow !== 'none');
    if (!temSuperficie) continue;
    const r = el.getBoundingClientRect();
    if (r.width < 40 || r.height < 20) continue;
    const texto = (el.textContent || '').replace(/\s+/g, ' ').trim();
    const item = {
      tag: el.tagName.toLowerCase(), texto,
      w: Math.round(r.width), h: Math.round(r.height),
      xnorm: Math.round((r.left / (document.documentElement.clientWidth || 1)) * 100) / 100,
      ynorm: Math.round(((r.top + window.scrollY) / (document.documentElement.scrollHeight || 1)) * 100) / 100,
      bgProprio: bgProprio(el), bgImage: normBgImg(cs.backgroundImage),
      radius: cs.borderRadius, borderW: cs.borderTopWidth, borderColor: cs.borderTopColor,
      boxShadow: normShadow(cs.boxShadow),
      padding: [cs.paddingTop, cs.paddingRight, cs.paddingBottom, cs.paddingLeft].join(' '),
      filhos: el.childElementCount,
    };
    const k = item.tag + '|' + texto.slice(0, 40);
    if (vistosK.has(k)) continue;
    vistosK.add(k);
    compostos.push(item);
  }
  // ancora — DECLARACAO de qual related_prototype esta captura representa (trava de compare-time,
  // 2026-07-08). Fica null a menos que a captura tenha sido gerada com "--snippet Mod/Tela", que
  // assa window.__ANCORA__ (resolvido por ancora.mjs) ANTES do snippet. O --compare exige que ela
  // bata com o charter. Sem isso, o proto x prod roda contra a fonte errada (ancora podre, 07-06/08).
  return JSON.stringify({ tema: TEMA, url: location.pathname, ancora: (window.__ANCORA__ || null), elementos, divisorias, containers, compostos }, null, 1);
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

  // ── Onda 2 — CONTAINERS (a causa do layout): a period bar era right-justify+gap16 no proto e
  // virou left-packed+gap0 na prod → SO_PROTO(regra velha)+SO_PROD(nova). O grid dos KPIs é igual.
  proto.containers = [
    { display: 'flex', dir: 'row', justify: 'flex-end', align: 'center', gap: '16px', span: 1200, filhos: 5, banda: 100 },
    { display: 'grid', dir: 'row', justify: 'normal', align: 'normal', gap: '12px', span: 1200, filhos: 4, banda: 300 },
  ];
  prod.containers = [
    { display: 'flex', dir: 'row', justify: 'flex-start', align: 'center', gap: '0px', span: 1200, filhos: 5, banda: 100 },
    { display: 'grid', dir: 'row', justify: 'normal', align: 'normal', gap: '12px', span: 1200, filhos: 4, banda: 300 },
  ];
  // ── Onda 2 — COMPOSTOS/CARDS (furo 3): o card "Resumo do mês" tem 6 filhos (invisível ao vetor
  // de texto). Ele ACHATOU na prod (perdeu sombra + padding) → DIVERGE. E um card órfão só no proto.
  proto.compostos = [
    { tag: 'div', texto: 'Resumo do mês R$ 1.234', w: 300, h: 120, xnorm: 0.1, ynorm: 0.4, bgProprio: 'oklch(0.238 0.01 282)', bgImage: 'none', radius: '8px', borderW: '1px', borderColor: 'oklch(0.335 0.012 282)', boxShadow: '0 1px 3px 0 rgba(0,0,0,0.2)', padding: '16px 16px 16px 16px', filhos: 6 },
    { tag: 'article', texto: 'Card orfao', w: 200, h: 80, xnorm: 0.7, ynorm: 0.4, bgProprio: 'oklch(0.24 0.01 282)', bgImage: 'none', radius: '8px', borderW: '0px', borderColor: 'rgba(0, 0, 0, 0)', boxShadow: 'none', padding: '8px 8px 8px 8px', filhos: 3 },
  ];
  prod.compostos = [
    { tag: 'div', texto: 'Resumo do mês R$ 9.999', w: 300, h: 120, xnorm: 0.1, ynorm: 0.4, bgProprio: 'oklch(0.238 0.01 282)', bgImage: 'none', radius: '8px', borderW: '1px', borderColor: 'oklch(0.335 0.012 282)', boxShadow: 'none', padding: '0px 0px 0px 0px', filhos: 6 },
  ];

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

  // ── Onda 2 — containers (a CAUSA do layout) + compostos/cards (furo 3) ──
  const ctrOk = by('CTR flex|row|flex-end')?.veredito === 'SO_PROTO'
    && by('CTR flex|row|flex-start')?.veredito === 'SO_PROD'
    && by('CTR grid')?.veredito === 'IDENTICO';
  if (!ctrOk) fails++;
  console.log(`  [${ctrOk ? 'PASS' : 'FAIL'}] Onda 2 container: period bar re-gapada = SO_PROTO+SO_PROD (a causa), grid igual`);
  const card = by('CARD div|Resumo do mês');
  const cardOk = card?.veredito === 'DIVERGE'
    && card.campos.some((c) => c.startsWith('boxShadow:'))
    && card.campos.some((c) => c.startsWith('padding:'));
  if (!cardOk) fails++;
  console.log(`  [${cardOk ? 'PASS' : 'FAIL'}] furo 3: card composto (6 filhos) achatou — boxShadow+padding (${card?.veredito || '—'})`);
  const cardOrfaoOk = by('CARD article|Card orfao')?.veredito === 'SO_PROTO';
  if (!cardOrfaoOk) fails++;
  console.log(`  [${cardOrfaoOk ? 'PASS' : 'FAIL'}] furo 3: card órfão só-proto não some (${by('CARD article|Card orfao')?.veredito || '—'})`);
  const triCtrOk = triagemSO(rows).some((r) => r.chave.startsWith('CTR '));
  if (!triCtrOk) fails++;
  console.log(`  [${triCtrOk ? 'PASS' : 'FAIL'}] furo 5: container SO_* entra na triagem obrigatória`);

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
  // ── trava de âncora (verificarAncora) — a MÁQUINA exige âncora declarada e batendo (2026-07-08).
  const ANC = 'prototipo-ui/cowork/financeiro-page.jsx', PODRE = 'oimpresso.com.html';
  for (const [label, got, exp] of [
    ['âncora: captura SEM declaração → recusa', verificarAncora({}, ANC).ok, false],
    ['âncora: declaração PODRE (shell) → recusa', verificarAncora({ ancora: PODRE }, ANC).ok, false],
    ['âncora: declaração BATE o charter → passa', verificarAncora({ ancora: ANC }, ANC).ok, true],
  ]) {
    const ok = got === exp;
    if (!ok) fails++;
    console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${label} → esperado ${exp}, obtido ${got}`);
  }
  // ── F5: conteúdo (overlap de rótulos .jsx × captura) — pega "declarou certo, colou na página errada".
  // jsxFake mistura RÓTULOS reais com CÓDIGO/className (a poluição que o teste-do-processo pegou):
  // operador, chamada de método, className kebab, classe tailwind — TÊM que ser excluídos.
  const jsxFake = `const a='A receber'; b='Saldo previsto'; label('Fluxo de caixa'); const t='Novo título'; x='Visão unificada'; y='A pagar'; op='a === b'; mth=': dt.toLocaleString('; cls='os-btn ghost fin-hero'; tw='px-1.5 py-px';`;
  const rot = rotulosDistintivos(jsxFake);
  const poluicao = ['a === b', 'os-btn ghost fin-hero', 'px-1.5 py-px'].some((p) => rot.includes(p.toLowerCase()));
  const txtCerto = ['A receber', 'A pagar', 'Saldo previsto', 'Fluxo de caixa', 'Novo título', 'Visão unificada'];
  const txtShell = ['Chat', 'Tarefas', 'Clientes', 'Configurações', 'Perfil', 'Início'];
  for (const [label, got, exp] of [
    ['F5 rótulos: mantém os 6 rótulos reais', rot.length >= 6 && rot.includes('a receber') && rot.includes('novo título'), true],
    ['F5 rótulos: EXCLUI código/className/tailwind (anti-poluição)', poluicao, false],
    ['F5 conteúdo: captura CERTA (rótulos presentes) → passa', overlapConteudo(rot, txtCerto).ok, true],
    ['F5 conteúdo: captura do SHELL (rótulos ausentes) → recusa', overlapConteudo(rot, txtShell).ok, false],
    ['F5 conteúdo: âncora sem material (poucos rótulos) → pula (ok)', overlapConteudo(['a b'], txtShell).ok, true],
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
// Guard "sou o entrypoint?" (Onda 3): o harness IMPORTA este módulo — sem o guard, o dispatch
// abaixo rodaria no import (disparando o selftest do módulo com o argv do harness). Só executa
// a CLI quando o arquivo é chamado direto (`node style-fingerprint.mjs ...`).
const ehEntrypoint = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
const argv = process.argv.slice(2);
if (!ehEntrypoint) { /* importado como módulo: não roda CLI */ }
else if (argv.includes('--selftest')) selftest();
else if (argv.includes('--snippet')) {
  // --snippet [<Mod/Tela>] — se a tela vier, ASSA a âncora (resolvida por ancora.mjs) num preâmbulo
  // `window.__ANCORA__=...` ANTES do snippet, pra a captura DECLARAR contra o que ela é comparável.
  // Sem tela: preâmbulo ausente → captura fica com ancora:null → o --compare recusa (fail-closed).
  const si = argv.indexOf('--snippet');
  const tela = argv[si + 1] && !argv[si + 1].startsWith('--') ? argv[si + 1] : null;
  if (tela) {
    let anc;
    try { anc = resolverAncora(tela); } catch (e) { console.error('⛔ ' + e.message); process.exit(2); }
    console.error(`# âncora assada na captura: ${anc} (tela ${tela})`);
    console.log(`window.__ANCORA__=${JSON.stringify(anc)};`);
  } else {
    console.error('# ⚠ sem <Mod/Tela>: captura NÃO declara âncora → o --compare vai recusar (use `--snippet <Mod/Tela>`).');
  }
  console.log(SNIPPET.trim());
} else if (argv.includes('--compare')) {
  const i = argv.indexOf('--compare');
  const [fa, fb] = [argv[i + 1], argv[i + 2]];
  if (!fa || !fb) { console.error('uso: --compare proto.json prod.json (--tela <Mod/Tela> | --sem-ancora <razão>)'); process.exit(2); }
  const A = JSON.parse(readFileSync(fa, 'utf8'));
  const B = JSON.parse(readFileSync(fb, 'utf8'));
  // ── TRAVA DE ÂNCORA (fail-closed, 2026-07-08) — exige `--tela <X>` (verifica a captura contra o
  // charter) OU `--sem-ancora <razão>` (opt-out explícito e logado). Comparar contra a fonte errada
  // (âncora podre) foi o incidente 07-06/07-08 que nenhum hook pegou — a trava vive aqui, na máquina.
  const telaIdx = argv.indexOf('--tela');
  const semIdx = argv.indexOf('--sem-ancora');
  const telaCmp = telaIdx >= 0 ? argv[telaIdx + 1] : null;
  const semRazao = semIdx >= 0 ? argv[semIdx + 1] : null;
  if (!telaCmp && !semRazao) {
    console.error('⛔ trava de âncora: passe `--tela <Mod/Tela>` (verifica a captura do proto contra o related_prototype do charter) OU `--sem-ancora <razão>` (opt-out explícito, logado). Comparar contra a fonte errada = âncora podre (incidente 2026-07-06/07-08).');
    process.exit(3);
  }
  if (telaCmp) {
    let esperada;
    try { esperada = resolverAncora(telaCmp); } catch (e) { console.error('⛔ ' + e.message); process.exit(3); }
    const v = verificarAncora(A, esperada); // A = proto (1º arg) é o lado da âncora de design
    if (!v.ok) { console.error('⛔ ' + v.motivo); process.exit(3); }
    console.error('# ' + v.motivo);
    // F5 — evidência fraca-porém-real: o DOM capturado tem os rótulos do .jsx da âncora? (pega o
    // "declarou certo, colou na página errada" que a âncora-claim sozinha deixa passar). Leitura
    // fail-OPEN (bônus — a âncora-claim já passou); overlap suspeito = recusa fail-CLOSED.
    let srcAnc = null;
    try {
      const raizRepo = resolve(dirname(fileURLToPath(import.meta.url)), '..');
      srcAnc = readFileSync(resolve(raizRepo, esperada), 'utf8');
    } catch (e) { console.error(`# ⚠ conteúdo não checado (âncora ilegível: ${String(e.message).slice(0, 60)})`); }
    if (srcAnc != null) {
      const textos = [...A.elementos.map((e) => e.texto), ...(A.compostos || []).map((c) => c.texto)];
      const c = overlapConteudo(rotulosDistintivos(srcAnc), textos);
      if (!c.ok) { console.error('⛔ ' + c.motivo); process.exit(3); }
      console.error('# ' + c.motivo);
    }
  } else {
    console.error(`# ⚠ OVERRIDE — âncora NÃO verificada. Razão: ${semRazao}. (Logado; o browser é não-hookável — ver ADR trava-ancora-compare. Não é bloqueio físico.)`);
  }
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
  console.log('uso: --snippet [<Mod/Tela>] | --compare proto.json prod.json (--tela <Mod/Tela> | --sem-ancora <razão>) [--json] | --selftest');
}
