#!/usr/bin/env node
/* sells-cowork-dead-css.mjs — DETECTOR de CSS morto em resources/css/sells-cowork.css.
 *
 * Contexto (DARK-BACKFILL-SWEEP · 2026-06-04): sells-cowork.css (292KB) foi copiado
 * VERBATIM do protótipo Cowork `sells-index/styles.css` (score 9.75). O /sells real
 * (Inertia/React) porta só um SUBCONJUNTO das classes — o drawer de detalhe virou o
 * shadcn `SaleSheet.tsx`, e o "Create POS" foi APOSENTADO. Resultado: ~metade das
 * regras são código morto (selector cuja classe nunca aparece no JS/TSX). O
 * conformance-gate conta a cor-crua DESSAS regras mortas como se fosse dívida viva
 * ("cor crua fantasma"). Este detector identifica as regras mortas pra:
 *   (1) o gate parar de contar morto como cor crua (excluir/baseline), e
 *   (2) guiar a remoção INCREMENTAL (1 família/PR, visual-regression como rede).
 *
 * NÃO remove nada — só RELATA. Determinístico, sem browser, sem dependência.
 *
 * Método (conservador — falso-NEGATIVO preferível a falso-positivo na tela do piloto):
 *   - "usada" = a classe aparece como TOKEN INTEIRO no JS/TSX (delimitada por
 *     não-[a-z0-9-]), então `vd-pay` NÃO casa `vd-payment` (evita falso-vivo)
 *     mas TAMBÉM captura uso em template-literal `b<biz>.${'vd-pay'}` quando o
 *     token literal existe. Classe construída 100% dinamicamente (`'vd-'+x`) NÃO
 *     é detectável — por isso a remoção é incremental + gated por visual-regression.
 *   - uma REGRA top-level é morta só se TODA classe do seu seletor está ausente.
 *   - SKIP total de @media/@keyframes/@supports/@container (at-rules aninhadas),
 *     das famílias de IMPRESSÃO/papel (vd-trans, vd-pres — branco proposital) e
 *     de classes de ESTADO genéricas (active/open/selected/...).
 *
 * Uso:
 *   node scripts/sells-cowork-dead-css.mjs                  resumo humano
 *   node scripts/sells-cowork-dead-css.mjs --json           ranges mortos (JSON)
 *   node scripts/sells-cowork-dead-css.mjs --report <file>  escreve relatório .md
 *
 * Refs: DARK-BACKFILL-SWEEP · conformance-gate.mjs · foundation-guard.mjs · ADR UI-0013.
 */
import { readFileSync, readdirSync, statSync, writeFileSync } from "node:fs";
import { join } from "node:path";

const CSS_FILE = "resources/css/sells-cowork.css";
const JS_DIR = "resources/js";
const WRAPPER = "sells-cowork";
const PRINT_FAMILIES = [/^vd-trans/, /^vd-pres/]; // papel/impressão — branco proposital
const STATE_CLASSES = new Set([
  "active", "done", "danger", "open", "selected", "disabled", "hidden", "show",
  "hide", "warn", "error", "ok", "muted", "urgent", "current", "expanded",
  "collapsed", "dragging", "drop", "over", "loading", "empty", "small", "large",
  "left", "right", "center", "first", "last", "odd", "even", "primary", "on",
]);

function readJsSource(dir) {
  let src = "";
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    const s = statSync(p);
    if (s.isDirectory()) src += readJsSource(p);
    else if (/\.(tsx?|jsx?)$/.test(name)) src += readFileSync(p, "utf8") + "\n";
  }
  return src;
}

function makeUsedFn(src) {
  const cache = new Map();
  return (cls) => {
    if (cache.has(cls)) return cache.get(cls);
    const re = new RegExp("(^|[^a-z0-9-])" + cls.replace(/-/g, "\\-") + "($|[^a-z0-9-])", "i");
    const r = re.test(src);
    cache.set(cls, r);
    return r;
  };
}

// Varre o CSS em statements top-level; pula corpos de at-rules inteiros.
function topLevelRules(css) {
  const rules = [];
  const n = css.length;
  let j = 0, stmtStart = 0;
  const lineAt = (idx) => css.slice(0, idx).split("\n").length;
  while (j < n) {
    if (css[j] === "{") {
      const prelude = css.slice(stmtStart, j).trim();
      let d = 1, k = j + 1;
      while (k < n && d > 0) { if (css[k] === "{") d++; else if (css[k] === "}") d--; k++; }
      if (!prelude.startsWith("@")) {
        rules.push({ sel: prelude, l0: lineAt(stmtStart), l1: lineAt(k) });
      } // at-rule body é pulado por completo (k já está depois do fecha-chaves)
      j = k; stmtStart = k; continue;
    }
    j++;
  }
  return rules;
}

function classesOf(selector) {
  return [...selector.matchAll(/\.([a-z][a-z0-9-]+)/gi)]
    .map((m) => m[1])
    .filter((c) => c !== WRAPPER && !STATE_CLASSES.has(c));
}

function analyze() {
  const src = readJsSource(JS_DIR);
  const used = makeUsedFn(src);
  const css = readFileSync(CSS_FILE, "utf8");
  const rules = topLevelRules(css);

  const dead = [];
  const families = {};
  let deadLines = 0;
  for (const r of rules) {
    const cls = classesOf(r.sel);
    if (cls.length === 0) continue;
    if (cls.some((c) => PRINT_FAMILIES.some((re) => re.test(c)))) continue;
    if (cls.some((c) => used(c))) continue; // qualquer classe viva → mantém a regra
    dead.push({ sel: r.sel.replace(/\s+/g, " ").slice(0, 120), l0: r.l0, l1: r.l1 });
    deadLines += r.l1 - r.l0 + 1;
    const fam = cls[0].split("-").slice(0, 2).join("-");
    families[fam] = (families[fam] || 0) + 1;
  }
  return { totalRules: rules.length, dead, deadLines, families };
}

function main() {
  const args = process.argv.slice(2);
  const { totalRules, dead, deadLines, families } = analyze();
  const famSorted = Object.entries(families).sort((a, b) => b[1] - a[1]);

  if (args.includes("--json")) {
    console.log(JSON.stringify({ totalRules, deadRules: dead.length, deadLines, ranges: dead.map((d) => [d.l0, d.l1]) }, null, 2));
    return;
  }

  const reportIdx = args.indexOf("--report");
  if (reportIdx !== -1) {
    const out = args[reportIdx + 1];
    const md = renderReport({ totalRules, dead, deadLines, famSorted });
    writeFileSync(out, md);
    console.log(`[dead-css] relatório escrito em ${out} (${dead.length} regras mortas · ~${deadLines} linhas)`);
    return;
  }

  console.log(`[dead-css] sells-cowork.css: ${totalRules} regras top-level · MORTAS ${dead.length} (~${deadLines} linhas)`);
  console.log(`[dead-css] famílias mortas (prefixo = N regras), top 25:`);
  for (const [k, v] of famSorted.slice(0, 25)) console.log(`  ${k.padEnd(20)} ${v}`);
  console.log(`\n[dead-css] remoção = INCREMENTAL, 1 família/PR, visual-regression como rede (DARK-BACKFILL-SWEEP).`);
}

function renderReport({ totalRules, dead, deadLines, famSorted }) {
  const lines = [];
  lines.push("# Relatório — CSS morto em `resources/css/sells-cowork.css`");
  lines.push("");
  lines.push("> Gerado por `node scripts/sells-cowork-dead-css.mjs --report <este-arquivo>`. NÃO editar à mão.");
  lines.push("> Determinístico (sem browser/dependência). Reproduzível — re-rode pra atualizar.");
  lines.push("");
  lines.push("## Resumo");
  lines.push("");
  lines.push(`- Regras top-level analisadas: **${totalRules}**`);
  lines.push(`- Regras **mortas** (toda classe do seletor ausente no JS/TSX): **${dead.length}** (~**${deadLines}** linhas)`);
  lines.push(`- Critério: token-inteiro no \`resources/js\`; pula @media/@keyframes/@container + famílias de impressão (vd-trans/vd-pres) + classes de estado.`);
  lines.push("");
  lines.push("## Famílias mortas (prefixo → nº de regras)");
  lines.push("");
  lines.push("| Família | Regras mortas |");
  lines.push("|---|---|");
  for (const [k, v] of famSorted) lines.push(`| \`${k}\` | ${v} |`);
  lines.push("");
  lines.push("## Como remover (seguro)");
  lines.push("");
  lines.push("1. **Incremental, 1 família/PR** — começar pela família não-portada maior (OS detail-drawer: `os-drawer`/`os-new`/`os-art`/`os-decision`).");
  lines.push("2. **Visual-regression como rede** — className 100% dinâmico (`'os-'+x`) e sub-view rara não são detectáveis estaticamente; o gate visual pega regressão. `/sells` é a tela do cliente-piloto.");
  lines.push("3. **Re-baseline do conformance-gate** após cada remoção (a cor-crua cai → teto desce).");
  lines.push("");
  lines.push("## Regras mortas (seletor · linhas)");
  lines.push("");
  lines.push("<details><summary>Listar as " + dead.length + " regras</summary>");
  lines.push("");
  for (const d of dead) lines.push(`- L${d.l0}-${d.l1} · \`${d.sel}\``);
  lines.push("");
  lines.push("</details>");
  lines.push("");
  return lines.join("\n");
}

main();
