#!/usr/bin/env node
// a11y-contraste.mjs — PR-A2 do plano COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO: cálculo derivado
// de cor com CASO DE SANIDADE OBRIGATÓRIO, consumido pelo a11y-alvo.mjs (PR-A4).
//
// ── POR QUE EXISTE (medido pelo [W] em 2026-09-03, transcrito do plano) ────────────────────
// A 1ª sonda de contraste leu `oklch(0.94 0.005 90)` com regex de `rgb()` e devolveu 2,62 no
// `.fj-title`. A "correção" via `canvas.fillStyle` NÃO converte oklch e repetiu o MESMO número —
// parecendo confirmação. Só a 3ª (OKLCH→OKLab→sRGB) vale: 10,84. Ou seja: o número plausível é o
// modo de falha, não o ruído. Por isso a sanidade aqui ABORTA em vez de avisar.
//
// ── LC-19: NÃO sou dono do parse de cor ───────────────────────────────────────────────────
// Quem lê `oklch()/oklab()/rgb()/#hex` é `scripts/design/style-fingerprint.mjs::parseCor` — já
// exportado, já com o par OKLCH%→0.4 do CSS Color 4. Reimplementar o parser aqui criaria uma 2ª
// gramática pra drifar no 1º ajuste (§5 2026-08-02). O que ELE não tem, e é o que acrescento:
// OKLab → sRGB LINEAR → luminância relativa WCAG → razão de contraste. `parseCor` para no OKLab
// (basta pro ΔEOK dele); WCAG exige luminância sRGB, que é outro espaço — não dá pra usar o L.
//
// Uso:  node scripts/qa/a11y-contraste.mjs --selftest
import { parseCor } from '../design/style-fingerprint.mjs';

// OKLab → sRGB linear (inversa de Björn Ottosson; a saída JÁ é linear, não reaplique gama).
export function oklabParaLinearSrgb([L, a, b]) {
  const l_ = L + 0.3963377774 * a + 0.2158037573 * b;
  const m_ = L - 0.1055613458 * a - 0.0638541728 * b;
  const s_ = L - 0.0894841775 * a - 1.2914855480 * b;
  const l = l_ * l_ * l_, m = m_ * m_ * m_, s = s_ * s_ * s_;
  return [
    4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
    -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
    -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s,
  ];
}

const clamp01 = (n) => (n < 0 ? 0 : n > 1 ? 1 : n);

// Luminância relativa WCAG 2.x sobre sRGB LINEAR (coeficientes da própria norma).
export function luminanciaWCAG(cor) {
  const [r, g, b] = oklabParaLinearSrgb(cor.lab).map(clamp01);
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

// Compõe cor com alfa sobre um fundo OPACO, em sRGB linear (é onde a mistura é fisicamente certa).
export function compor(frente, fundo) {
  if (frente.alfa >= 1) return frente;
  const f = oklabParaLinearSrgb(frente.lab), t = oklabParaLinearSrgb(fundo.lab);
  const a = frente.alfa;
  const rgb = [0, 1, 2].map((i) => clamp01(f[i] * a + t[i] * (1 - a)));
  return { lab: null, alfa: 1, _linear: rgb };
}

const lumDeLinear = (rgb) => 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
const lum = (c) => (c._linear ? lumDeLinear(c._linear) : luminanciaWCAG(c));

// Razão de contraste WCAG. `null` quando um dos lados NÃO é cor medível (color-mix, currentcolor,
// palavra-chave): null é "não medi", nunca 21 nem 1 — §5 2026-07-29 (não-medição ≠ estado do objeto).
export function contraste(corTexto, corFundo) {
  const t = typeof corTexto === 'string' ? parseCor(corTexto) : corTexto;
  const f = typeof corFundo === 'string' ? parseCor(corFundo) : corFundo;
  if (!t || !f) return null;
  const frente = t.alfa < 1 ? compor(t, f) : t;
  const a = lum(frente), b = lum(f);
  const [hi, lo] = a >= b ? [a, b] : [b, a];
  return (hi + 0.05) / (lo + 0.05);
}

// ── SANIDADE (o coração do A2) ────────────────────────────────────────────────────────────
// Roda ANTES de qualquer medição real. Os dois primeiros casos são o bite-test da classe:
// o par OKLCH tem de dar o MESMO 21 do par hex — é exatamente isso que o regex-de-rgb e o
// canvas.fillStyle não conseguem fazer, e é por onde o 2,62 entrou.
export const CASOS_SANIDADE = [
  // Os DOIS primeiros sao o bite-test da classe: o par OKLCH tem de dar o MESMO 21 do par hex.
  { nome: 'hex branco sobre preto', t: '#ffffff', f: '#000000', esperado: 21, tol: 0.01 },
  { nome: 'OKLCH branco sobre preto (tem de bater com o par hex acima)', t: 'oklch(1 0 0)', f: 'oklch(0 0 0)', esperado: 21, tol: 0.01 },
  // A COR DO INCIDENTE de 2026-09-03 (a que o regex-de-rgb leu como 2,62). Medida por DOIS
  // caminhos independentes em 2026-09-17: (a) esta matriz e (b) o motor do Chromium resolvendo
  // `color-mix(in srgb, oklch(0.94 0.005 90) 100%, white 0%)` -> srgb(0.926505 0.921508 0.907466),
  // linearizado e pesado por 0.2126/0.7152/0.0722. Y bateu em 1e-5 (0.830745 x 0.830774).
  { nome: 'OKLCH claro do incidente 2026-09-03 sobre preto', t: 'oklch(0.94 0.005 90)', f: '#000000', esperado: 17.61, tol: 0.02 },
  // Idem, dois caminhos: Chromium 5.1540 x esta matriz 5.1541. NAO estimar este numero de
  // cabeca -- a 1a redacao dizia 6,55 e era invencao (lapide 2026-07-28: numero so com o comando).
  { nome: 'OKLCH roxo canon sobre branco (ADR 0190)', t: 'oklch(0.55 0.15 295)', f: '#ffffff', esperado: 5.154, tol: 0.01 },
  { nome: 'cinza AA-limite #767676 sobre branco', t: '#767676', f: '#ffffff', esperado: 4.54, tol: 0.01 },
  { nome: 'cor NAO medivel devolve null (nao 21, nao 1)', t: 'color-mix(in srgb, red, blue)', f: '#fff', esperado: null },
];

export function conferirSanidade() {
  const falhas = [];
  for (const c of CASOS_SANIDADE) {
    const obtido = contraste(c.t, c.f);
    const ok = c.esperado === null ? obtido === null
      : (obtido !== null && Math.abs(obtido - c.esperado) <= c.tol);
    if (!ok) falhas.push(`${c.nome}: esperado ${c.esperado}, obtido ${obtido}`);
  }
  return falhas;
}

// Porta única: quem for medir contraste chama isto primeiro. ABORTA (não avisa) — o plano é
// explícito: "sabotar a conversão faz o script FALHAR, não retornar número plausível".
export function exigirSanidade() {
  const falhas = conferirSanidade();
  if (falhas.length) {
    throw Object.assign(
      new Error(`sanidade de contraste FALHOU (${falhas.length}/${CASOS_SANIDADE.length}):\n  - ${falhas.join('\n  - ')}`),
      { naoMedi: true },
    );
  }
}
