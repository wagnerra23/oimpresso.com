// Style Dictionary v4 config — onda DTCG (ADR 0300 errata 0239)
// ─────────────────────────────────────────────────────────────────────────────
// PAPEL: emitir CSS a partir dos *.tokens.json (DTCG) para arquivos de SAÍDA que
// o build VIVO consome. ATIVAÇÃO (feat/onda-dtcg-ativar): o token passa a ser
// editado no JSON portável e o CSS é a SAÍDA.
//
// EMISSÃO FIEL POR ESCOPO (não-merge): cada token carrega
// `$extensions.com.oimpresso.source` = "arquivo escopo --var" (ex
// "inertia.css @theme --radius-lg" ≠ "cockpit.css .cockpit --radius-lg"). O
// mesmo nome de var vive em escopos diferentes com valores diferentes
// (--font-sans @theme ≠ .cockpit). Por isso emitimos UM BLOCO POR ESCOPO
// CANÔNICO, com o seletor EXATO da fonte — preservando a cascata byte-a-byte.
// Um merge num único `:root, .cockpit` mudaria o computed de :root (regressão).
//
// SAÍDAS (cada uma importada pelo .css canônico correspondente, no lugar das
// linhas `--x: valor;` que antes eram à mão):
//   _generated-inertia-theme.css   → @theme { ... }            (inertia.css)
//   _generated-inertia-dark.css    → .dark, [data-theme=dark]  (inertia.css)
//   _generated-foundations.css     → :root + [data-theme=dark] (foundations.css)
//   _generated-cockpit.css         → .cockpit + dark           (cockpit.css)
//
// FIDELIDADE: o `--name` emitido vem de $extensions...source (1:1 com o canon).
// O $value é verbatim (color-as-string; não reprocessamos OKLCH/hsl/hex/calc).
// Prova: scripts/governance/dtcg-equivalence.mjs + diff de tokens.
//
// Node puro / ESM. UTF-8 sem BOM, LF.
// ─────────────────────────────────────────────────────────────────────────────
import StyleDictionary from 'style-dictionary';
import { fileURLToPath } from 'node:url';
import { dirname } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url)) + '/';

// "inertia.css @theme --radius-lg" → { file:'inertia', scope:'@theme', name:'--radius-lg' }
function parseSource(token) {
  const src =
    token?.$extensions?.['com.oimpresso.source'] ??
    token?.original?.$extensions?.['com.oimpresso.source'];
  if (!src) return null;
  const name = String(src).match(/(--[a-z][a-z0-9-]*)/i)?.[1] ?? null;
  let file = null;
  if (/inertia\.css/i.test(src)) file = 'inertia';
  else if (/foundations\.css/i.test(src)) file = 'foundations';
  else if (/cockpit\.css/i.test(src)) file = 'cockpit';
  let scope = null;
  if (/@theme/.test(src)) scope = '@theme';
  else if (/\.cockpit/.test(src)) scope = '.cockpit';
  else if (/:root/.test(src)) scope = ':root';
  if (!name || !file || !scope) return null;
  return { name, file, scope };
}

export function canonicalVarName(token) {
  return parseSource(token)?.name ?? null;
}

export function darkValue(token) {
  return (
    token?.$extensions?.['com.oimpresso.dark'] ??
    token?.original?.$extensions?.['com.oimpresso.dark'] ??
    null
  );
}

// $value já é string CSS verbatim. Não reprocessamos cor (o JSON é a fonte).
function rawValue(token) {
  return token.$value ?? token.value ?? token.original?.$value;
}

const DO_NOT_EDIT =
  `/* GERADO por Style Dictionary v4 a partir de resources/css/tokens/*.tokens.json (DTCG).\n` +
  `   NÃO EDITAR À MÃO — rode \`npm run tokens:build\` (ou \`node resources/css/tokens/style-dictionary.config.mjs\`).\n` +
  `   ATIVAÇÃO (feat/onda-dtcg-ativar): este arquivo é a CAMADA DE TOKEN consumida pelo build.\n` +
  `   Edite o token no .tokens.json — o CSS é SAÍDA. Equivalência: scripts/governance/dtcg-equivalence.mjs. */\n`;

// Coleta { light:{name:value}, dark:{name:value} } por (file, scope).
function collect(dictionary) {
  // chave = `${file}|${scope}` ; valor = { light:Map, dark:Map }
  const groups = new Map();
  for (const token of dictionary.allTokens) {
    const p = parseSource(token);
    if (!p) continue;
    const key = `${p.file}|${p.scope}`;
    if (!groups.has(key)) groups.set(key, { light: [], dark: [] });
    const g = groups.get(key);
    g.light.push(`  ${p.name}: ${rawValue(token)};`);
    const dv = darkValue(token);
    if (dv != null) g.dark.push(`  ${p.name}: ${dv};`);
  }
  return groups;
}

function lightBlock(selectorOpen, lines) {
  return `${selectorOpen} {\n${lines.join('\n')}\n}\n`;
}

// Format helpers — cada destino emite o(s) bloco(s) do seu escopo, com o seletor
// EXATO da fonte canônica (cascata preservada).
function makeFormat(name, builder) {
  StyleDictionary.registerFormat({ name, format: ({ dictionary }) => builder(collect(dictionary)) });
}

// UM ARQUIVO POR BLOCO CANÔNICO (scope+theme) — assim cada @import substitui
// exatamente um bloco no .css, preservando a vizinhança (ex regras
// .cockpit[data-density] que moram ENTRE o bloco light e o dark).

// inertia @theme (light) — Tailwind v4 lê este bloco pra gerar utilities.
makeFormat('oimpresso/inertia-theme', (g) =>
  DO_NOT_EDIT + `\n@theme {\n${(g.get('inertia|@theme')?.light ?? []).join('\n')}\n}\n`);

// inertia dark — seletor canônico ".dark,\n[data-theme=\"dark\"]".
makeFormat('oimpresso/inertia-dark', (g) =>
  DO_NOT_EDIT + `\n.dark,\n[data-theme="dark"] {\n${(g.get('inertia|@theme')?.dark ?? []).join('\n')}\n}\n`);

// foundations :root (light).
makeFormat('oimpresso/foundations-light', (g) =>
  DO_NOT_EDIT + `\n:root {\n${(g.get('foundations|:root')?.light ?? []).join('\n')}\n}\n`);

// foundations [data-theme="dark"] (dark).
makeFormat('oimpresso/foundations-dark', (g) =>
  DO_NOT_EDIT + `\n[data-theme="dark"] {\n${(g.get('foundations|:root')?.dark ?? []).join('\n')}\n}\n`);

// cockpit .cockpit (light).
makeFormat('oimpresso/cockpit-light', (g) =>
  DO_NOT_EDIT + `\n.cockpit {\n${(g.get('cockpit|.cockpit')?.light ?? []).join('\n')}\n}\n`);

// cockpit .cockpit[data-theme="dark"] (dark).
makeFormat('oimpresso/cockpit-dark', (g) =>
  DO_NOT_EDIT + `\n.cockpit[data-theme="dark"] {\n${(g.get('cockpit|.cockpit')?.dark ?? []).join('\n')}\n}\n`);

export const config = {
  source: [`${HERE}*.tokens.json`],
  platforms: {
    css: {
      transforms: ['attribute/cti', 'name/kebab'],
      buildPath: `${HERE}`,
      files: [
        { destination: '_generated-inertia-theme.css', format: 'oimpresso/inertia-theme' },
        { destination: '_generated-inertia-dark.css', format: 'oimpresso/inertia-dark' },
        { destination: '_generated-foundations-light.css', format: 'oimpresso/foundations-light' },
        { destination: '_generated-foundations-dark.css', format: 'oimpresso/foundations-dark' },
        { destination: '_generated-cockpit-light.css', format: 'oimpresso/cockpit-light' },
        { destination: '_generated-cockpit-dark.css', format: 'oimpresso/cockpit-dark' },
      ],
    },
  },
  log: { verbosity: 'silent' },
};

const invokedDirectly =
  process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1];
if (invokedDirectly) {
  const sd = new StyleDictionary(config);
  await sd.buildAllPlatforms();
  console.log('✓ DTCG → _generated-*.css (4 escopos) emitido em resources/css/tokens/');
}

export default config;
