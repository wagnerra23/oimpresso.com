#!/usr/bin/env node
/**
 * palette-generate.mjs — GERADOR determinístico da página de paleta de cor.
 *
 * Fonte única: resources/css/cockpit.css (a Camada Fundações/Shell — ADR UI-0013).
 * Lê os tokens de cor governados (chrome roxo + status + origem + etapa), claro e
 * escuro, e desenha memory/requisitos/_DesignSystem/PALETA.html — a referência visual
 * que NÃO PODE divergir, porque é gerada do CSS, não mantida à mão.
 *
 * Uso:
 *   node scripts/governance/palette-generate.mjs            (dry-run: imprime resumo)
 *   node scripts/governance/palette-generate.mjs --write    (grava PALETA.html)
 *   node scripts/governance/palette-generate.mjs --check     (CI: exit 1 se gerado != commitado)
 *
 * O --check é gate OBRIGATÓRIO no Governance Gate (umbrella) desde 2026-06-08: mexer no
 * cockpit.css sem rodar --write trava o merge. A paleta não pode divergir da fonte.
 * Determinístico: sem Date.now()/aleatoriedade, ordem fixa → --check estável claro/escuro.
 * Refs: ADR 0263 (identidade = gate), 0235/0249 (cor/nome DS v6), MANUAL-IDENTIDADE.md.
 */
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, resolve } from "node:path";

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), "..", "..");
const SRC = resolve(ROOT, "resources/css/cockpit.css");
const OUT = resolve(ROOT, "memory/requisitos/_DesignSystem/PALETA.html");

// ── Estrutura curada da paleta (ordem + rótulos "quando usar") ──────────────
// Os VALORES vêm do cockpit.css; aqui só o significado humano. Token novo no CSS
// que não esteja listado aqui aparece na seção "Não catalogado" (sinaliza drift de doc).
const GROUPS = [
  {
    id: "chrome", title: "Chrome — identidade", accent: true,
    desc: "A cor da marca. Única e travada. É o que diz “é o mesmo produto”. Nunca redefinir por módulo.",
    tokens: [
      { name: "--accent", use: "Cor principal — botão, link, estado ativo, primary" },
      { name: "--accent-2", use: "Hover / pressionado" },
      { name: "--accent-soft", use: "Fundo suave — seleção, chip, linha ativa" },
      { name: "--accent-fg", use: "Texto/ícone sobre o roxo" },
    ],
  },
  {
    id: "status", title: "Status — recado de estado",
    desc: "O que aconteceu, num relance. Verde ok, vermelho erro, âmbar atenção. Cada um tem o par -soft (fundo).",
    tokens: [
      { name: "--pos", use: "Sucesso / pago / concluído" }, { name: "--pos-soft", use: "Fundo de sucesso" },
      { name: "--neg", use: "Erro / falha / vencido" }, { name: "--neg-soft", use: "Fundo de erro" },
      { name: "--warn", use: "Atenção / pendente" }, { name: "--warn-soft", use: "Fundo de atenção" },
    ],
  },
  {
    id: "origin", title: "Origem — de onde a tarefa veio",
    desc: "Wayfinding por módulo (não decoração). Sempre par fundo + texto.",
    tokens: [
      { name: "--origin-OS-bg", use: "Ordem de Serviço — fundo" }, { name: "--origin-OS-fg", use: "Ordem de Serviço — texto" },
      { name: "--origin-CRM-bg", use: "CRM — fundo" }, { name: "--origin-CRM-fg", use: "CRM — texto" },
      { name: "--origin-FIN-bg", use: "Financeiro — fundo" }, { name: "--origin-FIN-fg", use: "Financeiro — texto" },
      { name: "--origin-PNT-bg", use: "Ponto — fundo" }, { name: "--origin-PNT-fg", use: "Ponto — texto" },
      { name: "--origin-MFG-bg", use: "Oficina / Produção — fundo" }, { name: "--origin-MFG-fg", use: "Oficina / Produção — texto" },
    ],
  },
  {
    id: "stage", title: "Etapa — fase do kanban",
    desc: "Escala categórica de pipeline (Recepção→Pronto). Reusável por qualquer kanban/FSM.",
    tokens: [
      { name: "--stage-slate", use: "Neutro / a fazer" },
      { name: "--stage-blue", use: "Em fila / locada" },
      { name: "--stage-indigo", use: "Diagnóstico" },
      { name: "--stage-violet", use: "Em manutenção" },
      { name: "--stage-rose", use: "Aguardando peças / bloqueio" },
      { name: "--stage-emerald", use: "Em execução" },
      { name: "--stage-green", use: "Pronto / concluído" },
    ],
  },
];

// ── Parser: extrai --token: valor de um bloco de seletor por casamento de chaves ──
function extractBlock(css, selector) {
  const start = css.indexOf(selector);
  if (start === -1) return {};
  let i = css.indexOf("{", start);
  if (i === -1) return {};
  let depth = 0, end = -1;
  for (let j = i; j < css.length; j++) {
    if (css[j] === "{") depth++;
    else if (css[j] === "}") { depth--; if (depth === 0) { end = j; break; } }
  }
  const body = css.slice(i + 1, end);
  const map = {};
  const re = /(--[a-zA-Z0-9-]+)\s*:\s*([^;]+);/g;
  let m;
  while ((m = re.exec(body)) !== null) map[m[1]] = m[2].trim();
  return map;
}

// Pós-ativação DTCG (#3230): os tokens saíram do cockpit.css e vivem nos @import
// gerados (tokens/_generated-cockpit-*.css). Resolve os @import (1 nível) e normaliza
// ".cockpit {" -> ".cockpit{" pra o extractBlock casar onde os tokens realmente estão.
// Os VALORES são byte-idênticos (dtcg-equivalence prova), então PALETA.html não muda.
function readResolved(file) {
  const dir = dirname(file);
  let css = readFileSync(file, "utf8");
  css = css.replace(/@import\s+["']([^"']+)["']\s*;/g, (_, p) => {
    try { return "\n" + readFileSync(resolve(dir, p), "utf8") + "\n"; } catch { return ""; }
  });
  return css.replace(/(\.cockpit(?:\[[^\]]*\])?)\s+\{/g, "$1{");
}

function build() {
  const css = readResolved(SRC);
  const light = extractBlock(css, ".cockpit{");
  const dark = extractBlock(css, '.cockpit[data-theme="dark"]{');

  const cataloged = new Set();
  for (const g of GROUPS) for (const t of g.tokens) cataloged.add(t.name);

  // tokens de cor no CSS não catalogados aqui (sinaliza doc desatualizada vs paleta real)
  const colorish = /^(--accent|--pos|--neg|--warn|--origin-|--stage-)/;
  const uncataloged = Object.keys(light)
    .filter((k) => colorish.test(k) && !cataloged.has(k))
    .sort();

  const L = [];
  const esc = (s) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

  const card = (t) => {
    const lv = light[t.name];
    if (!lv) return ""; // token listado mas ausente no CSS — ignora silenciosamente (não inventa)
    const dv = dark[t.name] || lv;
    const inherits = !dark[t.name];
    return [
      `      <div class="sw">`,
      `        <div class="chips"><span class="chip" style="background:${esc(lv)}"></span><span class="chip dk" style="background:${esc(dv)}"></span></div>`,
      `        <div class="meta"><code class="name">${esc(t.name)}</code><span class="use">${esc(t.use)}</span>`,
      `          <span class="val">claro <b>${esc(lv)}</b></span>`,
      `          <span class="val">escuro <b>${esc(dv)}</b>${inherits ? " <i>(herda)</i>" : ""}</span>`,
      `        </div>`,
      `      </div>`,
    ].join("\n");
  };

  const sections = GROUPS.map((g) => {
    const cards = g.tokens.map(card).filter(Boolean).join("\n");
    return [
      `    <section class="grp${g.accent ? " accent" : ""}">`,
      `      <h2>${esc(g.title)}</h2>`,
      `      <p class="gd">${esc(g.desc)}</p>`,
      `      <div class="grid">`,
      cards,
      `      </div>`,
      `    </section>`,
    ].join("\n");
  }).join("\n");

  const total = GROUPS.reduce((n, g) => n + g.tokens.filter((t) => light[t.name]).length, 0);
  const warnUncat = uncataloged.length
    ? `\n    <div class="uncat">⚠ ${uncataloged.length} token(s) de cor no cockpit.css fora do catálogo desta página: <code>${uncataloged.map(esc).join("</code> <code>")}</code>. Atualize GROUPS em palette-generate.mjs.</div>`
    : "";

  const html = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Paleta oficial — oimpresso</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet"/>
<style>
  :root{--ink:oklch(0.22 0.01 80);--dim:oklch(0.50 0.01 80);--mute:oklch(0.65 0.01 80);--line:oklch(0.90 0.004 90);--bg:oklch(0.985 0.003 90);--card:#fff;--roxo:oklch(0.55 0.15 295);--roxo-soft:oklch(0.95 0.04 295);--sans:'IBM Plex Sans',system-ui,sans-serif;--mono:'IBM Plex Mono',ui-monospace,monospace}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--sans);font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased}
  .wrap{max-width:1000px;margin:0 auto;padding:40px 28px 70px}
  .auto{font-family:var(--mono);font-size:11px;color:var(--mute);background:var(--roxo-soft);border:1px solid color-mix(in oklch,var(--roxo) 25%,transparent);border-radius:8px;padding:10px 14px;margin-bottom:24px}
  .auto b{color:var(--roxo)}
  h1{font-size:26px;font-weight:700;letter-spacing:-.02em;margin:0 0 4px}
  .eyebrow{font-family:var(--mono);font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--roxo);font-weight:600}
  .lede{color:var(--dim);max-width:62ch;margin:6px 0 30px}
  .grp{margin:30px 0}
  .grp.accent h2{color:var(--roxo)}
  h2{font-size:13px;font-family:var(--mono);text-transform:uppercase;letter-spacing:.05em;color:var(--mute);font-weight:600;margin:0 0 4px;display:flex;align-items:center;gap:10px}
  h2::after{content:"";flex:1;height:1px;background:var(--line)}
  .gd{font-size:12.5px;color:var(--dim);margin:0 0 14px}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
  .sw{display:flex;gap:12px;align-items:center;background:var(--card);border:1px solid var(--line);border-radius:10px;padding:12px}
  .chips{display:flex;flex-direction:column;gap:4px;flex-shrink:0}
  .chip{width:40px;height:24px;border-radius:6px;border:1px solid rgba(0,0,0,.08)}
  .chip.dk{height:16px;opacity:.95}
  .meta{display:flex;flex-direction:column;gap:1px;min-width:0}
  .name{font-family:var(--mono);font-size:12.5px;font-weight:600}
  .use{font-size:12px;color:var(--dim)}
  .val{font-family:var(--mono);font-size:10.5px;color:var(--mute)}
  .val b{color:var(--dim);font-weight:500}
  .uncat{margin-top:24px;font-size:12.5px;color:oklch(0.45 0.12 70);background:oklch(0.96 0.05 75);border:1px solid oklch(0.85 0.08 75);border-radius:8px;padding:12px 14px}
  .uncat code{font-family:var(--mono);font-size:11px}
  .foot{margin-top:40px;padding-top:16px;border-top:1px solid var(--line);font-size:11.5px;color:var(--mute);font-family:var(--mono)}
</style>
</head>
<body>
  <div class="wrap">
    <div class="auto">⛔ <b>AUTO-GERADO</b> por <code>scripts/governance/palette-generate.mjs</code> a partir de <code>resources/css/cockpit.css</code>. <b>NÃO EDITE À MÃO.</b> Para mudar a paleta: edite os tokens no <code>cockpit.css</code> (Tier 0, só Wagner) e rode <code>--write</code>. O CI (<code>--check</code>) trava se esta página divergir da fonte.</div>
    <div class="eyebrow">Paleta oficial · ${total} cores governadas</div>
    <h1>As cores do oimpresso</h1>
    <p class="lede">Esta é a paleta inteira — e a única permitida. Chrome (roxo, identidade) + semântica governada (status, origem, etapa). Fora daqui não entra: <code>foundation-guard</code> trava cor nova fora do <code>cockpit.css</code> (ADR 0263). Cada cor mostra a versão clara e a escura.</p>
${sections}${warnUncat}
    <div class="foot">Fonte: resources/css/cockpit.css · ADR 0263 (identidade = gate bloqueante) · MANUAL-IDENTIDADE.md (a voz) · regenerar: node scripts/governance/palette-generate.mjs --write</div>
  </div>
</body>
</html>
`;
  return { html, total, uncataloged };
}

const mode = process.argv.includes("--write") ? "write" : process.argv.includes("--check") ? "check" : "dry";
const { html, total, uncataloged } = build();

if (mode === "write") {
  writeFileSync(OUT, html, "utf8");
  console.log(`✓ PALETA.html gerado — ${total} cores governadas${uncataloged.length ? `, ${uncataloged.length} fora do catálogo` : ""}.`);
} else if (mode === "check") {
  let current = "";
  try { current = readFileSync(OUT, "utf8"); } catch { /* arquivo ausente */ }
  if (current !== html) {
    console.error("✗ PALETA.html está DESATUALIZADO vs cockpit.css — rode `node scripts/governance/palette-generate.mjs --write`. (paleta gerada ≠ commitada = drift)");
    process.exit(1);
  }
  console.log(`✓ PALETA.html em dia (${total} cores) vs cockpit.css.`);
} else {
  console.log(`dry-run: ${total} cores governadas${uncataloged.length ? `, fora do catálogo: ${uncataloged.join(", ")}` : ""}. Use --write para gravar.`);
}
