// Style Dictionary v4 config — onda DTCG (ADR 0300 errata 0239)
// ─────────────────────────────────────────────────────────────────────────────
// PAPEL: emitir CSS a partir dos *.tokens.json (DTCG) para um arquivo de SAÍDA
// NOVO (`_generated.css`). NÃO sobrescreve inertia/foundations/cockpit.css —
// estes seguem sendo a FONTE que o build vivo consome (app.tsx / AppShellV2.tsx
// intactos). Este pipeline é fonte PARALELA provada, pronta pra virar fonte num
// PR futuro do Wagner.
//
// FIDELIDADE: o nome canônico da CSS var de cada token vem do campo
// `$extensions.com.oimpresso.source` (ex "inertia.css @theme --color-border"),
// NÃO de transform de path do SD. Assim o `--name` emitido bate 1:1 com o CSS
// canônico — o que o check de equivalência (dtcg-equivalence.mjs) verifica.
//
// Node puro / ESM. UTF-8 sem BOM, LF.
// ─────────────────────────────────────────────────────────────────────────────
import StyleDictionary from 'style-dictionary';
import { fileURLToPath } from 'node:url';
import { dirname } from 'node:path';

// Diretório deste config (cross-platform — fileURLToPath trata o drive do Windows).
const HERE = dirname(fileURLToPath(import.meta.url)) + '/';

// Extrai o nome canônico `--xxx` do campo source de um token DTCG.
// Ex: "inertia.css @theme --color-border" → "--color-border".
export function canonicalVarName(token) {
  const src = token?.$extensions?.['com.oimpresso.source']
    ?? token?.extensions?.['com.oimpresso.source']
    ?? token?.original?.$extensions?.['com.oimpresso.source'];
  if (!src) return null;
  const m = String(src).match(/(--[a-z][a-z0-9-]*)/i);
  return m ? m[1] : null;
}

// Valor dark, se a fonte canônica define variante dark pro token.
export function darkValue(token) {
  return token?.$extensions?.['com.oimpresso.dark']
    ?? token?.original?.$extensions?.['com.oimpresso.dark']
    ?? null;
}

// Valor literal (já é string CSS verbatim — cor OKLCH/hsl/hex, px, rem, cubic-bezier,
// shadow multi-layer, gradient). Não reprocessamos cor: o build não consome este JSON.
function rawValue(token) {
  return token.$value ?? token.value ?? token.original?.$value;
}

// Formato custom: dois blocos (light + dark) usando os nomes canônicos.
StyleDictionary.registerFormat({
  name: 'oimpresso/dtcg-css',
  format: ({ dictionary }) => {
    const light = [];
    const dark = [];
    for (const token of dictionary.allTokens) {
      const name = canonicalVarName(token);
      if (!name) continue; // token sem mapeamento canônico (não deve acontecer)
      light.push(`  ${name}: ${rawValue(token)};`);
      const dv = darkValue(token);
      if (dv != null) dark.push(`  ${name}: ${dv};`);
    }
    const header =
      `/* GERADO por Style Dictionary v4 a partir de resources/css/tokens/*.tokens.json (DTCG).\n` +
      `   NÃO EDITAR À MÃO — rode \`node resources/css/tokens/style-dictionary.config.mjs\`.\n` +
      `   FONTE PARALELA provada (ADR 0300). O build vivo NÃO importa este arquivo:\n` +
      `   inertia.css/foundations.css/cockpit.css seguem sendo a fonte. */\n`;
    return (
      header +
      `\n/* ── light (default) ── */\n:root, .cockpit {\n${light.join('\n')}\n}\n` +
      `\n/* ── dark ([data-theme="dark"] / .dark) ── */\n.dark, [data-theme="dark"], .cockpit[data-theme="dark"] {\n${dark.join('\n')}\n}\n`
    );
  },
});

export const config = {
  source: [`${HERE}*.tokens.json`],
  platforms: {
    css: {
      // Sem transformGroup de cor: preservamos o $value verbatim (color-as-string).
      transforms: ['attribute/cti', 'name/kebab'],
      buildPath: `${HERE}`,
      files: [
        {
          destination: '_generated.css',
          format: 'oimpresso/dtcg-css',
        },
      ],
    },
  },
  log: { verbosity: 'silent' },
};

// Permite rodar diretamente: `node resources/css/tokens/style-dictionary.config.mjs`
const invokedDirectly =
  process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1];
if (invokedDirectly) {
  const sd = new StyleDictionary(config);
  await sd.buildAllPlatforms();
  console.log('✓ DTCG → _generated.css emitido em resources/css/tokens/_generated.css');
}

export default config;
