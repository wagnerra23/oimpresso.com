#!/usr/bin/env node
// ─────────────────────────────────────────────────────────────────────────────
// dtcg-equivalence.mjs — onda DTCG (ancora: ADR 0239 DS git SSOT + ADR 0249 DS v6 +
//   auditoria D3; fonte real dos .css confirmada pela errata ao 0239 — proposta, por
//   isso NAO citada como dependencia-de-runtime aqui, so descritivamente)
//
// CORAÇÃO da onda: PROVA que cada token DTCG (resources/css/tokens/*.tokens.json)
// tem o MESMO VALOR que o token correspondente no CSS canônico que o build vivo
// consome (inertia.css / foundations.css / cockpit.css). Parse de AMBOS os lados,
// compara valor-a-valor no ESCOPO correto, FALHA (rc!=0) se divergir.
//
// Por que escopo-aware: o mesmo nome de CSS var existe com valores diferentes em
// escopos diferentes (ex `--font-sans` no @theme do Tailwind ≠ `--font-sans` no
// .cockpit IBM Plex; `--radius-lg` rem no @theme ≠ `--radius-lg` px no .cockpit).
// Comparar num saco achatado daria falso-positivo. Cada token DTCG carrega
// `$extensions.com.oimpresso.source` ("arquivo escopo --var") que diz EXATAMENTE
// qual bloco canônico é a verdade.
//
// Node puro, ESM, sem deps. UTF-8 sem BOM, LF. rc0 = todos provados iguais.
// Uso: node scripts/governance/dtcg-equivalence.mjs [--json] [--detail]
// ─────────────────────────────────────────────────────────────────────────────
import { readFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const JSON_OUT = process.argv.includes('--json');
const DETAIL = process.argv.includes('--detail');

const TOKENS_DIR = join(ROOT, 'resources', 'css', 'tokens');
const CSS = {
  inertia: join(ROOT, 'resources', 'css', 'inertia.css'),
  foundations: join(ROOT, 'resources', 'css', 'foundations.css'),
  cockpit: join(ROOT, 'resources', 'css', 'cockpit.css'),
};

// ── parser de bloco por casamento de chaves ─────────────────────────────────
// Acha o cabeçalho do seletor (string literal) e captura o corpo {...} balanceado.
function blockBody(text, headerLiteral) {
  const at = text.indexOf(headerLiteral);
  if (at < 0) return null;
  const open = text.indexOf('{', at);
  if (open < 0) return null;
  let depth = 0;
  for (let i = open; i < text.length; i++) {
    if (text[i] === '{') depth++;
    else if (text[i] === '}') {
      depth--;
      if (depth === 0) return text.slice(open + 1, i);
    }
  }
  return null;
}

// Extrai um mapa { '--token': 'value' } de um corpo de bloco CSS.
function defsOf(body) {
  const map = {};
  if (!body) return map;
  const re = /(--[a-z][a-z0-9-]*)\s*:\s*([^;]+);/gi;
  let m;
  while ((m = re.exec(body)) !== null) map[m[1]] = m[2].trim();
  return map;
}

// ── escopos canônicos (light + dark) por arquivo ────────────────────────────
// O `source` de cada token referencia um destes escopos.
function buildCanonScopes() {
  const inertia = readFileSync(CSS.inertia, 'utf8');
  const foundations = readFileSync(CSS.foundations, 'utf8');
  const cockpit = readFileSync(CSS.cockpit, 'utf8');

  // inertia.css: @theme (light) + ".dark,\n[data-theme=\"dark\"] {" (dark)
  const inertiaThemeLight = defsOf(blockBody(inertia, '@theme'));
  // o bloco dark começa em ".dark," seguido de "[data-theme..." — ancorar em "[data-theme=\"dark\"] {"
  const inertiaDark = defsOf(blockBody(inertia, '[data-theme="dark"] {'));

  // foundations.css: ":root{" (light) + "[data-theme=\"dark\"]{" (dark)
  const foundLight = defsOf(blockBody(foundations, ':root{'));
  const foundDark = defsOf(blockBody(foundations, '[data-theme="dark"]{'));

  // cockpit.css: ".cockpit{" (light, primeiro com tokens) + ".cockpit[data-theme=\"dark\"]{" (dark)
  const cockpitLight = defsOf(blockBody(cockpit, '.cockpit{'));
  const cockpitDark = defsOf(blockBody(cockpit, '.cockpit[data-theme="dark"]{'));

  return {
    light: {
      inertia: inertiaThemeLight,
      foundations: foundLight,
      cockpit: cockpitLight,
    },
    dark: {
      inertia: inertiaDark,
      foundations: foundDark,
      cockpit: cockpitDark,
    },
  };
}

// Qual arquivo o `source` referencia? ("inertia.css ...", "foundations.css ...", "cockpit.css ...")
function fileOfSource(src) {
  if (/inertia\.css/i.test(src)) return 'inertia';
  if (/foundations\.css/i.test(src)) return 'foundations';
  if (/cockpit\.css/i.test(src)) return 'cockpit';
  return null;
}
function varOfSource(src) {
  const m = String(src).match(/(--[a-z][a-z0-9-]*)/i);
  return m ? m[1] : null;
}

// Achata um arquivo DTCG em lista de tokens folha com path + $value + $extensions.
function flattenTokens(obj, path = [], out = []) {
  if (obj && typeof obj === 'object' && '$value' in obj) {
    out.push({ path: path.join('.'), token: obj });
    return out;
  }
  if (obj && typeof obj === 'object') {
    for (const [k, v] of Object.entries(obj)) {
      if (k.startsWith('$')) continue;
      if (v && typeof v === 'object') flattenTokens(v, [...path, k], out);
    }
  }
  return out;
}

function normalize(v) {
  // comparação tolerante a espaços-em-branco internos colapsados (CSS é
  // whitespace-insensitive entre tokens). Não toca em maiúsculas (hex/oklch
  // são case-significativos por convenção do projeto — comparar literal).
  return String(v).replace(/\s+/g, ' ').trim();
}

function main() {
  const errors = [];
  const proven = [];
  const skipped = [];

  if (!existsSync(TOKENS_DIR)) {
    console.error(`FALHA: ${TOKENS_DIR} não existe.`);
    process.exit(2);
  }

  const canon = buildCanonScopes();
  const tokenFiles = ['base.tokens.json', 'semantic.tokens.json']
    .map((f) => join(TOKENS_DIR, f))
    .filter(existsSync);

  if (tokenFiles.length === 0) {
    console.error('FALHA: nenhum *.tokens.json encontrado.');
    process.exit(2);
  }

  for (const tf of tokenFiles) {
    const data = JSON.parse(readFileSync(tf, 'utf8'));
    const leaves = flattenTokens(data);
    for (const { path, token } of leaves) {
      const ext = token.$extensions || {};
      const src = ext['com.oimpresso.source'];
      if (!src) {
        skipped.push({ path, reason: 'sem com.oimpresso.source' });
        continue;
      }
      const file = fileOfSource(src);
      const varName = varOfSource(src);
      if (!file || !varName) {
        errors.push({ path, kind: 'source-ilegivel', src });
        continue;
      }

      // ── LIGHT ──
      const canonLight = canon.light[file][varName];
      if (canonLight === undefined) {
        errors.push({ path, kind: 'var-ausente-no-css', var: varName, file, scope: 'light' });
      } else if (normalize(canonLight) !== normalize(token.$value)) {
        errors.push({
          path, kind: 'valor-divergente', var: varName, file, scope: 'light',
          dtcg: token.$value, css: canonLight,
        });
      } else {
        proven.push({ path, var: varName, file, scope: 'light' });
      }

      // ── DARK (só se o DTCG declara variante dark) ──
      const dtcgDark = ext['com.oimpresso.dark'];
      if (dtcgDark != null) {
        const canonDark = canon.dark[file][varName];
        if (canonDark === undefined) {
          errors.push({ path, kind: 'var-dark-ausente-no-css', var: varName, file, scope: 'dark' });
        } else if (normalize(canonDark) !== normalize(dtcgDark)) {
          errors.push({
            path, kind: 'valor-dark-divergente', var: varName, file, scope: 'dark',
            dtcg: dtcgDark, css: canonDark,
          });
        } else {
          proven.push({ path, var: varName, file, scope: 'dark' });
        }
      }
    }
  }

  const summary = {
    proven: proven.length,
    divergences: errors.length,
    skipped: skipped.length,
    ok: errors.length === 0,
  };

  if (JSON_OUT) {
    console.log(JSON.stringify({ summary, errors, skipped: DETAIL ? skipped : undefined }, null, 2));
  } else {
    console.log(`DTCG ↔ CSS canônico — equivalência escopo-aware`);
    console.log(`  provados iguais : ${proven.length} (light+dark)`);
    console.log(`  divergências    : ${errors.length}`);
    console.log(`  pulados         : ${skipped.length}`);
    if (DETAIL && skipped.length) {
      for (const s of skipped) console.log(`    · pulado ${s.path}: ${s.reason}`);
    }
    if (errors.length) {
      console.log(`\n✗ DIVERGÊNCIAS (DTCG não bate com a fonte CSS):`);
      for (const e of errors) {
        if (e.kind === 'valor-divergente' || e.kind === 'valor-dark-divergente') {
          console.log(`  ${e.scope} ${e.var} (${e.file}) [${e.path}]\n      DTCG: ${e.dtcg}\n      CSS : ${e.css}`);
        } else {
          console.log(`  ${e.kind}: ${e.var ?? ''} (${e.file ?? ''}) [${e.path}] ${e.src ?? ''}`);
        }
      }
    } else {
      console.log(`\n✓ Todos os ${proven.length} valores DTCG são FIÉIS à fonte CSS canônica.`);
    }
  }

  process.exit(errors.length ? 1 : 0);
}

main();
