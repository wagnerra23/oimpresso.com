#!/usr/bin/env node
// @ts-check
/**
 * design-diff.mjs — comparador DETERMINÍSTICO design(Cowork vivo) × produção, por MEDIÇÃO.
 *
 * POR QUE EXISTE (Wagner 2026-07-07, strike 2): a comparação design×prod vinha sendo feita
 * "no olho" (screenshot + eu declaro "estruturalmente igual") e ERROU — perdeu o alinhamento
 * dos KPI (center na prod × left no design) e o dark-mode invisível. Essa classe de erro
 * ("comparação rasa / no olho") já tinha acontecido em 06/07 (gerou o PROTOCOLO-COMPARACAO-
 * RUNTIME) — repeti em 07/07. Pela regra two-strikes (LICOES_CODE §two-strikes, LC-06), strike 2
 * = vira DEFESA MECÂNICA. Esta é a defesa: o veredito vem de um DIFF MEDIDO, não de olhar.
 * Realiza o `/design-diff` PREVISTO na ADR 0299. Espelha o split do `cowork-mirror-freshness.mjs`.
 *
 * ── O SPLIT (o node não fala MCP; computed-style precisa de browser) ──────────────
 *   1. PROBE (browser):  `--probe` imprime a sonda JS CANÔNICA. O agente injeta ela via
 *      Chrome MCP `javascript_tool` em CADA aba (prod + design render), passando o mapa de
 *      papéis daquele lado em `window.__DD_ROLES` (as CLASSES diferem — `.fin-stat` na prod,
 *      `.os-stat` no design — mas o PAPEL é o mesmo). A sonda devolve um snapshot medido.
 *   2. COMPARE (node puro): `--compare prod.json design.json [--check]` → veredito POR DIMENSÃO,
 *      determinístico + testável. `--check` sai 1 se houver DIVERGE(bug).
 *
 *   A mesma sonda nos dois lados = ninguém "compara no olho": a régua é idêntica e medida.
 *
 * ── DIMENSÕES (do PROTOCOLO-COMPARACAO-RUNTIME; D8 é a que faltava, o buraco de 07/07) ──
 *   D2 layout      — nº de linhas visuais da barra de filtro · contagem de KPI · overflow-x
 *   D4 tipografia  — font-size/weight do título e do valor do KPI
 *   D6 cor         — bg do primary (accent) · cor do texto do KPI (contraste no tema)
 *   D8 ALINHAMENTO — text-align de label/valor do KPI + a TAG (button↔center-default × div↔left)
 *
 *   (D1 comportamento/rede, D3 ícones, D5 footer, D7 densidade ficam no protocolo como passos
 *    do agente — só as dimensões de computed-style puro são auto-diffáveis aqui hoje. Honesto:
 *    o tool NÃO substitui o protocolo, MECANIZA a parte medível dele. §"não-goals".)
 *
 * ── TOLERÂNCIA (chip G8, 2026-08-14) ─────────────────────────────────────────────
 *   As bandas destas dimensões estavam inline (±2px de título, ±8° de matiz, ±0,1 de luminância).
 *   Agora vêm de `TOLERANCIAS` (style-fingerprint.mjs) — UM dono para os dois comparadores, senão
 *   os dois números drifam no primeiro ajuste. Cada eixo lá carrega a RAZÃO do valor, e a fronteira
 *   (um caso logo abaixo, um logo acima) está travada no `--selftest` dos dois lados.
 *   Importar o módulo é seguro: ele só roda CLI quando é o entrypoint (guard `ehEntrypoint`).
 *
 * Uso:
 *   node prototipo-ui/design-diff.mjs --probe                       # imprime a sonda pra injetar
 *   node prototipo-ui/design-diff.mjs --compare prod.json design.json          # relatório
 *   node prototipo-ui/design-diff.mjs --compare prod.json design.json --check  # exit 1 se DIVERGE(bug)
 *   node prototipo-ui/design-diff.mjs --compare prod.json design.json --json   # saída JSON
 *   node prototipo-ui/design-diff.mjs --selftest                    # fixture hermético (reproduz 07/07)
 */

import { readFileSync } from 'node:fs';
import { TOLERANCIAS } from './style-fingerprint.mjs';

/* ─────────────────────────────────────────────────────────────────────────────
 * A SONDA CANÔNICA (roda no browser via Chrome MCP javascript_tool).
 * Config por lado em window.__DD_ROLES = { kpi, title, primary } (seletores CSS).
 * Devolve o snapshot medido — MESMA função nos dois lados.
 * Exportada como string pra `--probe` imprimir e o agente injetar igual nos dois.
 * ─────────────────────────────────────────────────────────────────────────── */
export const PROBE_SOURCE = /* js */ `(() => {
  const R = window.__DD_ROLES || {};
  const cs = (el) => el ? getComputedStyle(el) : null;
  const q = (sel) => sel ? document.querySelector(sel) : null;
  const qa = (sel) => sel ? [...document.querySelectorAll(sel)] : [];
  const visualRows = (els) => {
    // nº de linhas visuais = grupos distintos de top (arredondado) dos elementos
    const tops = new Set(els.map((e) => Math.round(e.getBoundingClientRect().top / 6) * 6).filter((t) => t >= 0));
    return tops.size;
  };
  // KPI
  const kpiEls = qa(R.kpi).slice(0, 8);
  const kpi = {
    count: kpiEls.length,
    tag: kpiEls[0] ? kpiEls[0].tagName : null,
    overflowX: (() => { const p = kpiEls[0] && kpiEls[0].parentElement; return p ? p.scrollWidth > p.clientWidth + 2 : null; })(),
    items: kpiEls.map((el) => {
      const c = cs(el); const small = el.querySelector('small,[class*="label"]');
      // O VALOR do KPI: tenta a marcação semântica e, se ela não existir, cai no
      // MAIOR texto-folha dentro do card. O fallback existe porque seletor de
      // classe é cego a utility-first: o KpiCard canon marca o valor com
      // 'text-2xl', sem "value" no nome, então [class*="value"] devolvia null e a
      // tipografia do VALOR saía NÃO-MEDIDA — ponto cego silencioso, que é pior
      // que divergência (o relatório fica igual ao de quem mediu e bateu).
      // "O valor é o maior texto do card" vale por construção nos dois lados.
      // Conservador: só roda quando o seletor falha — onde já funcionava, nada muda.
      let b = el.querySelector('b,[class*="value"]');
      if (!b) {
        b = [...el.querySelectorAll("*")]
          .filter((e) => (e.textContent || "").trim() && !e.children.length)
          .sort((x, y) => parseFloat(cs(y).fontSize) - parseFloat(cs(x).fontSize))[0] || null;
      }
      return {
        label: (el.textContent || '').replace(/\\s+/g, ' ').trim().slice(0, 14),
        textAlign: c.textAlign,
        alignItems: c.alignItems,
        textColor: c.color,
        smallAlign: small ? cs(small).textAlign : null,
        valueFontPx: b ? Math.round(parseFloat(cs(b).fontSize)) : null,
      };
    }),
  };
  // título
  const t = q(R.title); const tc = cs(t);
  const title = t ? { fontPx: Math.round(parseFloat(tc.fontSize)), weight: tc.fontWeight, color: tc.color } : null;
  // primary (accent)
  const p = q(R.primary); const pc = cs(p);
  const primary = p ? { bg: pc.backgroundColor, color: pc.color, border: pc.borderTopColor } : null;
  // filtro (barra) — nº de linhas visuais dos controles
  const filterEls = R.filterControls ? qa(R.filterControls) : [];
  return {
    url: location.href,
    theme: document.documentElement.getAttribute('data-theme') || (document.documentElement.classList.contains('dark') ? 'dark' : 'light'),
    roles: { kpi, title, primary, filterRows: filterEls.length ? visualRows(filterEls) : null },
  };
})()`;

/* ─────────────────────────────────────────────────────────────────────────────
 * COMPARADOR (node puro, determinístico). Cada dimensão é uma função pura que
 * recebe (prod.roles, design.roles) e devolve linhas de veredito.
 *   IGUAL            — medida idêntica
 *   DIVERGE (bug)    — medida difere E o design é a referência → prod está errada
 *   DIVERGE (tema)   — difere só por cor de texto que acompanha o tema (não é bug estrutural)
 *   SEM-DADO         — um dos lados não trouxe a medida (não mente por omissão)
 * ─────────────────────────────────────────────────────────────────────────── */

/** @param {any} prod @param {any} design */
function dimAlinhamento(prod, design) { // D8
  const rows = [];
  const pk = prod.kpi, dk = design.kpi;
  if (!pk || !dk) return [{ dim: 'D8', campo: 'kpi', prod: '—', design: '—', veredito: 'SEM-DADO' }];
  // a TAG explica a causa (button=center-default × div=left)
  if (pk.tag !== dk.tag) rows.push({ dim: 'D8', campo: 'kpi.tag', prod: pk.tag, design: dk.tag, veredito: pk.tag === 'BUTTON' && dk.tag !== 'BUTTON' ? 'DIVERGE (bug)' : 'DIVERGE (bug)' });
  const n = Math.max(pk.items.length, dk.items.length);
  let mismatch = 0;
  for (let i = 0; i < n; i++) {
    const a = pk.items[i], b = dk.items[i];
    if (!a || !b) continue;
    const pa = a.textAlign === 'start' ? 'left' : a.textAlign;
    const da = b.textAlign === 'start' ? 'left' : b.textAlign;
    if (pa !== da) mismatch++;
  }
  if (mismatch > 0) rows.push({ dim: 'D8', campo: 'kpi.text-align', prod: (pk.items[0] || {}).textAlign, design: (dk.items[0] || {}).textAlign, veredito: 'DIVERGE (bug)', detalhe: mismatch + '/' + n + ' KPIs desalinhados' });
  if (!rows.length) rows.push({ dim: 'D8', campo: 'kpi align', prod: 'ok', design: 'ok', veredito: 'IGUAL' });
  return rows;
}

/** @param {any} prod @param {any} design */
function dimLayout(prod, design) { // D2
  const rows = [];
  const pk = prod.kpi, dk = design.kpi;
  if (pk && dk && pk.count !== dk.count) rows.push({ dim: 'D2', campo: 'kpi.count', prod: pk.count, design: dk.count, veredito: 'DIVERGE (bug)' });
  if (pk && pk.overflowX === true) rows.push({ dim: 'D2', campo: 'kpi.overflowX', prod: 'estoura viewport', design: 'cabe', veredito: 'DIVERGE (bug)' });
  if (prod.filterRows != null && design.filterRows != null && prod.filterRows !== design.filterRows)
    rows.push({ dim: 'D2', campo: 'filtro linhas', prod: prod.filterRows, design: design.filterRows, veredito: 'DIVERGE (bug)' });
  if (!rows.length) rows.push({ dim: 'D2', campo: 'layout', prod: 'ok', design: 'ok', veredito: 'IGUAL' });
  return rows;
}

/** @param {any} prod @param {any} design */
function dimTipografia(prod, design) { // D4
  const rows = [];
  const a = prod.title, b = design.title;
  if (!a || !b) return [{ dim: 'D4', campo: 'título', prod: '—', design: '—', veredito: 'SEM-DADO' }];
  // banda declarada (TOLERANCIAS.tituloPx): 1px = o artefato máximo do Math.round da própria sonda.
  const dT = Math.abs(a.fontPx - b.fontPx);
  if (dT > TOLERANCIAS.tituloPx.valor) rows.push({ dim: 'D4', campo: 'título font-size', prod: a.fontPx + 'px', design: b.fontPx + 'px', veredito: 'DIVERGE (bug)', detalhe: `Δ ${dT}px · banda tituloPx ±${TOLERANCIAS.tituloPx.valor}px` });
  // O VALOR do KPI — o texto que o usuário de fato lê no card.
  //
  // Este bloco faltava, e a ausência era SILENCIOSA: a sonda MEDIA valueFontPx
  // desde sempre (linha ~96) e o comparador nunca lia o campo. Dado coletado que
  // nenhum consumidor consome não é neutro — é a mesma doutrina do CLAUDE.md
  // ("máquina que existe e ninguém invoca é bug, não neutralidade"), aqui no eixo
  // do CAMPO. Pior que divergir: o relatório saía com a mesma cara de quem mediu
  // e bateu, e a dimensão D4 se apresentava como "tipografia" medindo só o título.
  //
  // Medido em 2026-08-21 no Painel da Jana: prod 24px × design 22px. Sem este
  // bloco, os dois lados apareciam como IGUAL na linha de tipografia.
  const pv = ((prod.kpi && prod.kpi.items) || []).map((i) => i.valueFontPx).filter((x) => x != null);
  const dv = ((design.kpi && design.kpi.items) || []).map((i) => i.valueFontPx).filter((x) => x != null);
  if (pv.length && dv.length) {
    const dV = Math.abs(pv[0] - dv[0]);
    if (dV > TOLERANCIAS.tipografia.valor) {
      rows.push({ dim: 'D4', campo: 'kpi valor font-size', prod: pv[0] + 'px', design: dv[0] + 'px', veredito: 'DIVERGE (bug)', detalhe: 'Δ ' + dV + 'px · banda tipografia ±' + TOLERANCIAS.tipografia.valor + 'px' });
    }
  } else if (pv.length !== dv.length) {
    // Um lado mediu e o outro não: NÃO é igual, é NÃO-MEDIDO. Dizer "IGUAL" aqui
    // seria afirmar sobre o que não se conseguiu ler (§5 2026-07-29).
    rows.push({ dim: 'D4', campo: 'kpi valor font-size', prod: pv.length ? pv[0] + 'px' : 'não medido', design: dv.length ? dv[0] + 'px' : 'não medido', veredito: 'SEM-DADO' });
  }
  if (!rows.length) rows.push({ dim: 'D4', campo: 'tipografia', prod: 'ok', design: 'ok', veredito: 'IGUAL' });
  return rows;
}

/** oklch/oklab → lightness (1º número) pra comparar sem falso-positivo de tema */
function lightnessOf(color) {
  const m = /ok(?:lch|lab)\(\s*([0-9.]+)/.exec(color || '');
  return m ? parseFloat(m[1]) : null;
}

/** @param {any} prod @param {any} design */
function dimCor(prod, design) { // D6
  const rows = [];
  const a = prod.primary, b = design.primary;
  if (a && b) {
    // compara HUE do accent (o "roxinho"): 3º número do oklch
    const hue = (c) => { const m = /ok(?:lch|lab)\([0-9.]+ [0-9.-]+ ([0-9.]+)/.exec(c || ''); return m ? Math.round(parseFloat(m[1])) : null; };
    const ph = hue(a.bg), dh = hue(b.bg);
    if (ph != null && dh != null && Math.abs(ph - dh) > TOLERANCIAS.matiz.valor) rows.push({ dim: 'D6', campo: 'primary hue', prod: a.bg, design: b.bg, veredito: 'DIVERGE (bug)', detalhe: `Δ ${Math.abs(ph - dh)}° · banda matiz ±${TOLERANCIAS.matiz.valor}°` });
    // lightness do accent (roxo escuro travado × roxinho que clareia no dark)
    const pl = lightnessOf(a.bg), dl = lightnessOf(b.bg);
    const dL = pl != null && dl != null ? Math.abs(pl - dl) : null;
    if (dL != null && dL > TOLERANCIAS.luminancia.valor) rows.push({ dim: 'D6', campo: 'primary lightness', prod: pl, design: dl, veredito: prod.__theme === design.__theme ? 'DIVERGE (bug)' : 'DIVERGE (tema)', detalhe: `Δ ${Number(dL.toFixed(5))} · banda luminancia ±${TOLERANCIAS.luminancia.valor}` });
  }
  // contraste do texto do KPI: lightness do texto vs (heurística) fundo do tema
  const pk = prod.kpi;
  if (pk && pk.items[0]) {
    const tl = lightnessOf(pk.items[0].textColor);
    if (prod.__theme === 'dark' && tl != null && tl < 0.5) rows.push({ dim: 'D6', campo: 'kpi texto (dark)', prod: 'lightness ' + tl + ' (escuro no escuro)', design: '≥0.6', veredito: 'DIVERGE (bug)' });
  }
  if (!rows.length) rows.push({ dim: 'D6', campo: 'cor', prod: 'ok', design: 'ok', veredito: 'IGUAL' });
  return rows;
}

const DIMENSIONS = [dimLayout, dimTipografia, dimCor, dimAlinhamento];

/** @param {any} prodSnap @param {any} designSnap */
export function compare(prodSnap, designSnap) {
  const prod = { ...prodSnap.roles, __theme: prodSnap.theme };
  const design = { ...designSnap.roles, __theme: designSnap.theme };
  const rows = DIMENSIONS.flatMap((fn) => fn(prod, design));
  const bugs = rows.filter((r) => r.veredito === 'DIVERGE (bug)');
  return { rows, bugs: bugs.length, sameTheme: prodSnap.theme === designSnap.theme };
}

/* ─────────────────────────────────────────────────────────────────────────────
 * CLI
 * ─────────────────────────────────────────────────────────────────────────── */
function fmt(rows) {
  return rows.map((r) => {
    const mark = r.veredito === 'IGUAL' ? '✓' : r.veredito === 'SEM-DADO' ? '⬜' : r.veredito.includes('tema') ? '🟡' : '✗';
    return `  ${mark} [${r.dim}] ${r.campo}: prod=${r.prod} · design=${r.design} → ${r.veredito}${r.detalhe ? ' (' + r.detalhe + ')' : ''}`;
  }).join('\n');
}

function runCompare(argv) {
  const files = argv.filter((a) => !a.startsWith('--'));
  if (files.length < 2) { console.error('uso: --compare <prod.json> <design.json>'); process.exit(2); }
  const prodSnap = JSON.parse(readFileSync(files[0], 'utf8'));
  const designSnap = JSON.parse(readFileSync(files[1], 'utf8'));
  const res = compare(prodSnap, designSnap);
  if (argv.includes('--json')) { console.log(JSON.stringify(res, null, 2)); }
  else {
    console.log(`\n  DESIGN-DIFF — prod(${prodSnap.theme}) × design(${designSnap.theme})${res.sameTheme ? '' : '  ⚠ TEMAS DIFERENTES — compare no mesmo tema (regra do protocolo)'}\n`);
    console.log(fmt(res.rows));
    console.log(`\n  ✗ DIVERGE(bug): ${res.bugs}\n`);
  }
  if (argv.includes('--check') && res.bugs > 0) process.exit(1);
}

function selftest() {
  // FIXTURE HERMÉTICO — reproduz o incidente 2026-07-07 (center×left) + dark-mode.
  const prod = { theme: 'dark', roles: {
    kpi: { count: 5, tag: 'BUTTON', overflowX: true, items: Array(5).fill(0).map((_, i) => ({ label: 'kpi' + i, textAlign: 'center', alignItems: 'normal', textColor: 'oklch(0.374 0.01 67)', smallAlign: 'center', valueFontPx: 26 })) },
    title: { fontPx: 22, weight: '600', color: 'oklch(0.984 0 0)' },
    primary: { bg: 'oklch(0.55 0.15 295)', color: 'oklch(0.99 0 0)', border: 'oklch(0.45 0.15 295)' },
    filterRows: 1,
  } };
  const design = { theme: 'dark', roles: {
    kpi: { count: 5, tag: 'DIV', overflowX: false, items: Array(5).fill(0).map((_, i) => ({ label: 'kpi' + i, textAlign: 'start', alignItems: 'normal', textColor: 'oklch(0.965 0 0)', smallAlign: 'start', valueFontPx: 22 })) },
    title: { fontPx: 22, weight: '600', color: 'oklch(0.965 0 0)' },
    primary: { bg: 'oklch(0.72 0.15 295)', color: 'oklch(0.99 0 0)', border: 'oklch(0.62 0.15 295)' },
    filterRows: 2,
  } };
  const res = compare(prod, design);
  const has = (dim, campo) => res.rows.some((r) => r.dim === dim && r.campo.includes(campo) && r.veredito.startsWith('DIVERGE'));
  const checks = [
    ['D8 pega center×left (o erro de 07/07)', has('D8', 'text-align')],
    ['D8 pega button×div (a causa)', has('D8', 'tag')],
    ['D2 pega overflow-x (A PAGAR cortado)', has('D2', 'overflowX')],
    ['D2 pega filtro 1×2 linhas', has('D2', 'filtro')],
    ['D6 pega roxo escuro×roxinho (lightness)', has('D6', 'lightness')],
    ['D6 pega texto KPI escuro no dark', has('D6', 'kpi texto')],
    ['D4 pega VALOR do KPI (campo que a sonda media e o compare ignorava)', has('D4', 'kpi valor')],
    ['--check sairia 1 (tem bug)', res.bugs > 0],
  ];
  // controle: dois lados IGUAIS não acusam bug
  const eq = compare(design, design);
  checks.push(['design×design = 0 bug (não mente)', eq.bugs === 0]);

  // ── FRONTEIRA das bandas declaradas (chip G8, 2026-08-14) ────────────────────
  // As bandas destas dimensões vêm de TOLERANCIAS (style-fingerprint.mjs). Sem um par
  // abaixo/acima, o número é decorativo: aqui cada eixo prova que absorve o que deve absorver
  // e morde no primeiro passo além. Fixture MÍNIMO (1 KPI alinhado, mesmo tema) pra isolar o eixo.
  const base = (over = {}) => ({ theme: 'dark', roles: {
    kpi: { count: 1, tag: 'DIV', overflowX: false, items: [{ label: 'k', textAlign: 'start', alignItems: 'normal', textColor: 'oklch(0.965 0 0)', smallAlign: 'start', valueFontPx: 22 }] },
    title: { fontPx: 22, weight: '600', color: 'oklch(0.965 0 0)' },
    primary: { bg: 'oklch(0.55 0.15 295)', color: 'oklch(0.99 0 0)', border: 'oklch(0.45 0.15 295)' },
    filterRows: 1,
    ...over,
  } });
  const titulo = (px) => base({ title: { fontPx: px, weight: '600', color: 'oklch(0.965 0 0)' } });
  const accent = (bg) => base({ primary: { bg, color: 'oklch(0.99 0 0)', border: 'oklch(0.45 0.15 295)' } });
  const acusa = (a, b, campo) => compare(a, b).rows.some((r) => r.campo.includes(campo) && r.veredito.startsWith('DIVERGE'));
  const T = TOLERANCIAS;
  checks.push(
    // controle NEGATIVO do fixture mínimo: sem estímulo, zero bug (senão a fronteira mediria ruído)
    ['fronteira: fixture mínimo × ele mesmo = 0 bug', compare(base(), base()).bugs === 0],
    // D4 título — banda 1px (o artefato máximo do Math.round da própria sonda)
    [`fronteira D4: título Δ${T.tituloPx.valor}px (na banda) → não acusa`, !acusa(titulo(22), titulo(22 + T.tituloPx.valor), 'título')],
    ['fronteira D4: título Δ2px → acusa', acusa(titulo(22), titulo(24), 'título')],
    // D6 matiz — banda 8°
    [`fronteira D6: matiz Δ${T.matiz.valor}° (na banda) → não acusa`, !acusa(accent('oklch(0.55 0.15 295)'), accent(`oklch(0.55 0.15 ${295 + T.matiz.valor})`), 'hue')],
    ['fronteira D6: matiz Δ9° → acusa', acusa(accent('oklch(0.55 0.15 295)'), accent('oklch(0.55 0.15 304)'), 'hue')],
    // D6 luminância — banda 0.1 (sondada em 0.09/0.11: a fronteira nominal cai no binário)
    ['fronteira D6: luminância Δ0.09 (na banda) → não acusa', !acusa(accent('oklch(0.55 0.15 295)'), accent('oklch(0.64 0.15 295)'), 'lightness')],
    ['fronteira D6: luminância Δ0.11 → acusa', acusa(accent('oklch(0.55 0.15 295)'), accent('oklch(0.66 0.15 295)'), 'lightness')],
  );
  let ok = true;
  for (const [label, pass] of checks) { console.log(`  [${pass ? 'PASS' : 'FAIL'}] ${label}`); if (!pass) ok = false; }
  console.log(ok ? '\nSELFTEST OK — mede o que o olho perdeu em 07/07 (D8 align + D2 overflow + D6 dark).' : '\nSELFTEST FALHOU');
  process.exit(ok ? 0 : 1);
}

const argv = process.argv.slice(2);
if (argv.includes('--selftest')) selftest();
else if (argv.includes('--probe')) console.log(PROBE_SOURCE);
else if (argv.includes('--compare')) runCompare(argv);
else { console.error('uso: --probe | --compare <prod.json> <design.json> [--check|--json] | --selftest'); process.exit(2); }
