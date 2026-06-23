/**
 * Oimpresso DS — build do token pipeline (D-06)
 * Style Dictionary v4 · Node 18+ · ESM
 *
 * Fonte única (DTCG):  tokens/primitive.json  +  tokens/semantic[.dark].json
 * Saída:
 *   build/tokens.css   →  :root{…claro…}  +  [data-theme="dark"]{…escuro…}
 *   build/tokens.ts    →  objeto tipado { accent, bg, … } (consumo no React/TS)
 *
 * Rodar:  npm install && npm run build:tokens
 */
import StyleDictionary from 'style-dictionary';
import { readFile, writeFile, rm } from 'node:fs/promises';

// Só a camada semântica vira variável — primitivos (grupo `prim`) ficam internos.
const onlySemantic = (token) => token.path[0] !== 'prim';

function platform(selector, dest) {
  return {
    css: {
      transformGroup: 'css',
      buildPath: 'build/',
      files: [{
        destination: dest,
        format: 'css/variables',
        filter: onlySemantic,
        options: { selector, outputReferences: false },
      }],
    },
  };
}

const light = new StyleDictionary({
  source: ['tokens/primitive.json', 'tokens/semantic.json'],
  platforms: platform(':root', '_light.css'),
  log: { verbosity: 'silent' },
});

const dark = new StyleDictionary({
  source: ['tokens/primitive.json', 'tokens/semantic.dark.json'],
  platforms: platform('[data-theme="dark"]', '_dark.css'),
  log: { verbosity: 'silent' },
});

// TS: objeto { '--accent': 'var(--accent)' } + valores claros resolvidos.
const ts = new StyleDictionary({
  source: ['tokens/primitive.json', 'tokens/semantic.json'],
  platforms: {
    ts: {
      transformGroup: 'js',
      buildPath: 'build/',
      files: [{
        destination: 'tokens.ts',
        format: 'javascript/es6',
        filter: onlySemantic,
      }],
    },
  },
  log: { verbosity: 'silent' },
});

await light.buildAllPlatforms();
await dark.buildAllPlatforms();
await ts.buildAllPlatforms();

// Concatena os dois blocos num único tokens.css canônico.
const head = '/* GERADO por Style Dictionary — NÃO EDITAR À MÃO. Fonte: pipeline/tokens/*.json */\n';
const lightCss = await readFile('build/_light.css', 'utf8');
const darkCss = await readFile('build/_dark.css', 'utf8');
await writeFile('build/tokens.css', head + lightCss + '\n' + darkCss);
await rm('build/_light.css');
await rm('build/_dark.css');

console.log('✓ build/tokens.css  (claro + escuro)');
console.log('✓ build/tokens.ts   (consumo TS/React)');
