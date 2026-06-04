#!/usr/bin/env node
/* foundation-guard.mjs — GATE ESTRUTURAL da Fundação única (Camada 3 · anti-espalhamento).
 * Determinístico, sem browser, sem dependência. Roda em CI (exit≠0 = bloqueia merge) E local.
 *
 * Irmão do conformance-gate.mjs: aquele protege a COR (cor crua / --accent roxo); este protege a
 * ESTRUTURA da Camada Fundações (Constituição UI v2 · ADR UI-0013). Buraco que aquele deixava:
 * a "fonte única de tokens" (foundations.css) é só CONVENÇÃO — nada impede re-duplicar token num
 * arquivo novo, desfazendo a fundação única sem ninguém ver. Este gate torna isso INVARIANTE.
 *
 * DUAS checagens determinísticas (zero browser):
 *   (A) ratchet de DEFINIÇÃO de token — `@theme` e `--accent*:/--color-*:` (definição, não var())
 *       só podem viver na ALLOWLIST (foundations.css = Fundação · cockpit.css = Shell). Em qualquer
 *       outro arquivo a contagem é congelada num baseline só-desce. Arquivo NOVO definindo token,
 *       ou contagem subindo num já-conhecido → 🔴. Remover é livre (baixe o teto com --update).
 *   (B) allowlist de ARQUIVO CSS — `.foundation-guard-files.json` lista os .css autorizados em
 *       resources/css/. Surgiu um .css fora da lista → 🔴. Pra entrar na lista precisa de edição
 *       versionada (= aprovação humana no diff/PR). É o "sem minha autorização" literal.
 *
 * Por que ALÉM do conformance-gate + stylelint:
 *   - stylelint congela #hex global; conformance-gate trava cor crua de TELA + --accent roxo.
 *   - NENHUM dos dois vê: arquivo CSS novo, nem bloco @theme/token DUPLICADO fora da fundação.
 *     Defense-in-depth: cor (Camada 1/2) + estrutura (Camada 3, este).
 *
 * Provado (controle-negativo em tests/foundationGuard.spec.ts · Camada META — testa o teste):
 *   sensibilidade: token-def num arquivo novo → conta sobe (pega o bug); .css fora da lista → rejeita;
 *   especificidade: `var(--accent)` (consumo) NÃO conta · comentário "@theme" NÃO conta (strip de comentário).
 *
 * Uso:  node scripts/foundation-guard.mjs            (modo CI — checa A+B)
 *       node scripts/foundation-guard.mjs --update   (re-crava baseline A após remoção intencional)
 *
 * Refs: ADR UI-0013 (Camada Fundações) · ADR 0209 (ratchet gêmeo) · ADR 0235/0190 (roxo 295)
 *       · conformance-gate.mjs (irmão Camada 1) · ADR 0238 (soberania tooling, sem ADR nova).
 */
import { readFileSync, writeFileSync, existsSync, readdirSync } from "node:fs";

const CSS_DIR = "resources/css";
const BASELINE_FILE = ".foundation-guard-baseline.json";   // ratchet de token-def (só-desce)
const FILES_FILE = ".foundation-guard-files.json";         // allowlist de arquivo .css

// Camada Fundações + Shell (ADR UI-0013): os ÚNICOS lugares onde definir token é legítimo.
const TOKEN_DEF_ALLOW = new Set(["foundations.css", "cockpit.css"]);

// Tira comentários /* ... */ antes de varrer — senão "migrado pra @theme" num comentário falsa-positiva.
export function stripComments(css) {
  return css.replace(/\/\*[\s\S]*?\*\//g, "");
}

// Conta DEFINIÇÕES de token de marca (não consumo): bloco `@theme` + `--accent*:`/`--color-*:`.
// `var(--accent)` NÃO casa (não tem `:` após o nome) → consumo não é punido.
export function tokenDefCount(css) {
  const clean = stripComments(css);
  const theme = (clean.match(/@theme\b/g) || []).length;
  const tok = (clean.match(/--(accent|color)[\w-]*\s*:/g) || []).length;
  return theme + tok;
}

function loadJson(file, fallback) {
  try { return existsSync(file) ? JSON.parse(readFileSync(file, "utf8")) : fallback; }
  catch { return fallback; }
}

function cssFiles() {
  return readdirSync(CSS_DIR).filter((n) => n.endsWith(".css")).sort();
}

// ── Checagem A: ratchet de token-def fora da allowlist ──────────────────────────────────────
function checkTokenDef(baseline, { update } = {}) {
  const current = {};
  for (const f of cssFiles()) {
    if (TOKEN_DEF_ALLOW.has(f)) continue;
    const n = tokenDefCount(readFileSync(`${CSS_DIR}/${f}`, "utf8"));
    if (n > 0) current[f] = n;
  }

  if (update) {
    writeFileSync(BASELINE_FILE, JSON.stringify(current, null, 2) + "\n");
    const list = Object.entries(current).map(([f, n]) => `  ${f} = ${n}`).join("\n") || "  (vazio — fundação 100% única 🎉)";
    console.log(`[foundation-guard] baseline token-def re-cravado:\n${list}`);
    return true;
  }

  const fails = [];
  for (const [f, n] of Object.entries(current)) {
    const teto = baseline[f];
    if (teto === undefined) fails.push(`🔴 ${f}: ARQUIVO NOVO define ${n} token(s) fora da fundação — token só mora em ${[...TOKEN_DEF_ALLOW].join("/")} (ADR UI-0013). Consuma via var(--accent), não redefina.`);
    else if (n > teto) fails.push(`🔴 ${f}: token-def subiu ${teto}→${n} — re-duplicação de token. Remova a def e use var(--accent).`);
  }
  const known = Object.keys(baseline).length;
  const remaining = Object.keys(current).length;
  if (fails.length) { for (const m of fails) console.error(m); return false; }
  console.log(`[foundation-guard] token-def: ${remaining}/${known} arquivo(s) legado dentro do teto · 0 espalhamento novo ✅`);
  return true;
}

// ── Checagem B: allowlist de arquivo CSS ────────────────────────────────────────────────────
function checkFiles(allow) {
  const allowSet = new Set(allow);
  const novos = cssFiles().filter((f) => !allowSet.has(f));
  if (novos.length) {
    for (const f of novos) {
      console.error(`🔴 ${CSS_DIR}/${f}: arquivo CSS NOVO sem autorização. Pra entrar, adicione "${f}" a ${FILES_FILE} (diff revisado = aprovação humana).`);
    }
    return false;
  }
  console.log(`[foundation-guard] arquivos: ${cssFiles().length} .css, todos na allowlist ✅`);
  return true;
}

function main() {
  const update = process.argv.includes("--update");
  const baseline = loadJson(BASELINE_FILE, {});
  const allow = loadJson(FILES_FILE, null);

  const a = checkTokenDef(baseline, { update });
  if (update) process.exit(a ? 0 : 1);

  if (allow === null) { console.error(`🔴 ${FILES_FILE} ausente — rode com --update? (não: a allowlist é manual). Crie-a com os .css atuais.`); process.exit(2); }
  const b = checkFiles(allow);

  if (a && b) console.log(`\n✅ Fundação única íntegra (token-def + arquivos).`);
  else console.error(`\n🔴 Fundação violada — merge bloqueado. Veja acima.`);
  process.exit(a && b ? 0 : 1);
}

// Só roda o CLI quando invocado direto (não quando importado pelo teste META).
if (process.argv[1]?.endsWith("foundation-guard.mjs")) main();
