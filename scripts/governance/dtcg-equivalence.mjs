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
// ── CINTURÃO DE FORMA: `--schema` (2026-08-14) ──────────────────────────────
// O modo sem flag prova VALOR (DTCG ≡ CSS). Ele não tinha como provar FORMA, e
// dependia dela: um token sem `$extensions.com.oimpresso.source` não tem endereço
// canônico para comparar, então caía num `continue` silencioso e SUMIA do
// denominador — verde indistinguível de "provei". Medido em 2026-08-14 o buraco
// estava vazio (0 pulados em 169 folhas), o que o tornava latente, não ativo:
// o primeiro token novo escrito sem `source` entraria mudo.
// Duas mudanças, ambas de FP zero medido no corpus real:
//   (a) token sem `source` virou ERRO (`sem-source`), não pulo — todo token agora
//       é provado ou acusado, nunca ignorado;
//   (b) `--schema` valida os *.tokens.json contra resources/css/tokens/dtcg.schema.json
//       (JSON Schema 2020-12, via ajv), que exige `source` na origem e barra as
//       formas que quebram o parser deste arquivo antes de virarem valor errado.
// O schema descreve o DTCG QUE ESTE REPO USA (ver o $description dele): `$value`
// sempre string CSS verbatim, `$type` herdável do grupo, `$description` OPCIONAL
// (141 das 169 folhas não têm — exigir seria backfill de legado, proibicoes §5
// 2026-07-12). ESCOPO: só `resources/css/tokens/*.tokens.json`. O
// `_PARCIAL-domain-semantic.tokens.json` do espelho Cowork fica de fora por medição,
// não por esquecimento — 45 das 45 folhas dele não têm `source` (é entrega parcial
// de handoff, não fonte do build) e o espelho é de LEITURA (ADR 0374).
//
// Node puro no modo sem flag. UTF-8 sem BOM, LF. rc0 = todos provados iguais.
// Uso: node scripts/governance/dtcg-equivalence.mjs [--json] [--detail]
//      node scripts/governance/dtcg-equivalence.mjs --schema [--json]
// Exit: 0 ok · 1 violação (valor divergente / forma fora do schema) · 2 não-medido.
// ─────────────────────────────────────────────────────────────────────────────
import { readFileSync, existsSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const JSON_OUT = process.argv.includes('--json');
const DETAIL = process.argv.includes('--detail');
const SCHEMA_MODE = process.argv.includes('--schema');

const TOKENS_DIR = join(ROOT, 'resources', 'css', 'tokens');

// ── ATIVAÇÃO (feat/onda-dtcg-ativar) ────────────────────────────────────────
// Antes da ativação a fonte VIVA das vars era inline em inertia/foundations/
// cockpit.css e este check provava DTCG ≡ esses blocos inline.
// Pós-ativação esses blocos foram movidos pros _generated-*.css (SAÍDA do Style
// Dictionary, importados pelos .css canônicos — é o CSS que o build consome).
// O check segue provando DTCG ≡ CSS-consumido-pelo-build, agora lendo os
// arquivos gerados. Continua mordendo: pega edição manual nos _generated-*.css
// ou regressão do gerador (gerado ≠ JSON). Se os gerados sumirem, FALHA (rc2) —
// o build precisa deles. Cada arquivo gerado tem UM bloco (light XOR dark).
const GEN = (f) => join(TOKENS_DIR, f);
const GENERATED = {
  inertiaTheme: GEN('_generated-inertia-theme.css'),   // @theme (light)
  inertiaDark: GEN('_generated-inertia-dark.css'),     // .dark,[data-theme="dark"]
  foundLight: GEN('_generated-foundations-light.css'), // :root
  foundDark: GEN('_generated-foundations-dark.css'),   // [data-theme="dark"]
  cockpitLight: GEN('_generated-cockpit-light.css'),   // .cockpit
  cockpitDark: GEN('_generated-cockpit-dark.css'),     // .cockpit[data-theme="dark"]
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
// Pós-ativação cada escopo vive num _generated-*.css de bloco único. O `source`
// de cada token referencia o escopo lógico (inertia/foundations/cockpit) —
// mapeamos cada um pro arquivo gerado correspondente.
function buildCanonScopes() {
  const rd = (p) => {
    if (!existsSync(p)) {
      console.error(`FALHA: ${p} ausente — rode \`npm run tokens:build\` (Style Dictionary). O build vivo consome esse arquivo.`);
      process.exit(2);
    }
    return readFileSync(p, 'utf8');
  };

  // inertia: @theme (light) no _generated-inertia-theme.css;
  //          .dark,[data-theme="dark"] (dark) no _generated-inertia-dark.css
  const inertiaThemeLight = defsOf(blockBody(rd(GENERATED.inertiaTheme), '@theme'));
  const inertiaDark = defsOf(blockBody(rd(GENERATED.inertiaDark), '[data-theme="dark"]'));

  // foundations: :root (light) + [data-theme="dark"] (dark) em arquivos separados
  const foundLight = defsOf(blockBody(rd(GENERATED.foundLight), ':root'));
  const foundDark = defsOf(blockBody(rd(GENERATED.foundDark), '[data-theme="dark"]'));

  // cockpit: .cockpit (light) + .cockpit[data-theme="dark"] (dark) em arquivos separados
  const cockpitLight = defsOf(blockBody(rd(GENERATED.cockpitLight), '.cockpit '));
  const cockpitDark = defsOf(blockBody(rd(GENERATED.cockpitDark), '.cockpit[data-theme="dark"]'));

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

// ── modo --schema: valida a FORMA dos *.tokens.json contra o JSON Schema ─────
// Descobre os arquivos por varredura do diretório (não por lista fixa) para que um
// `*.tokens.json` NOVO nasça coberto em vez de nascer invisível.
//
// `--tokens-dir <dir>` é o SEAM do bite-test: só o modo --schema o honra, e ele
// existe para o self-test poder rodar este CLI DE FORA contra fixture boa/ruim sem
// tocar os arquivos versionados (assert sobre helper exportado não prova contrato de
// pipeline · §5 2026-07-30). O CI não passa a flag. Não se usa junction para isso —
// junction em worktree Windows já esvaziou `vendor/` e `node_modules` reais duas
// vezes (CLAUDE.md §Ambiente); o seam é a alternativa sem esse risco.
const dirFlag = (() => {
  const i = process.argv.indexOf('--tokens-dir');
  return i >= 0 ? process.argv[i + 1] : null;
})();
const SCHEMA_DIR = dirFlag || TOKENS_DIR;
const SCHEMA_PATH = join(SCHEMA_DIR, 'dtcg.schema.json');

function listarTokensJson() {
  return readdirSync(SCHEMA_DIR)
    .filter((f) => f.endsWith('.tokens.json'))
    .sort()
    .map((f) => join(SCHEMA_DIR, f));
}

async function mainSchema() {
  if (!existsSync(SCHEMA_DIR)) {
    console.error(`⛔ NÃO MEDIDO — diretório de tokens ausente: ${SCHEMA_DIR}`);
    process.exit(2);
  }
  if (!existsSync(SCHEMA_PATH)) {
    console.error(`⛔ NÃO MEDIDO — schema ausente: ${SCHEMA_PATH}`);
    console.error('   NADA foi validado. Isto é erro de ambiente, não ausência de violação.');
    process.exit(2);
  }
  // ajv é a MESMA engine que scripts/memory-schemas/validate.mjs já usa para os
  // schemas de memory/** — reuso do que existe, não motor novo. Ausência dela é
  // "não consegui medir" (exit 2), NUNCA um verde silencioso (§5 2026-07-29).
  let Ajv, addFormats;
  try {
    ({ default: Ajv } = await import('ajv/dist/2020.js'));
    ({ default: addFormats } = await import('ajv-formats'));
  } catch (e) {
    console.error('⛔ NÃO MEDIDO — ajv/ajv-formats ausentes; nenhum token foi validado.');
    console.error('   Local: `npm i` na raiz. CI: o step `Install deps do validador de schema` cobre.');
    console.error(`   Detalhe: ${e && e.message}`);
    process.exit(2);
  }

  const arquivos = listarTokensJson();
  if (arquivos.length === 0) {
    // Zero arquivo NÃO é "forma conforme": é a varredura não ter encontrado nada.
    console.error(`⛔ NÃO MEDIDO — nenhum *.tokens.json em ${SCHEMA_DIR}.`);
    process.exit(2);
  }

  const ajv = new Ajv({ allErrors: true, strict: false });
  addFormats(ajv);
  const validate = ajv.compile(JSON.parse(readFileSync(SCHEMA_PATH, 'utf8')));

  const violations = [];
  for (const f of arquivos) {
    const rel = (f.startsWith(ROOT) ? f.slice(ROOT.length + 1) : f).replace(/\\/g, '/');
    let data;
    try { data = JSON.parse(readFileSync(f, 'utf8')); } catch (e) {
      violations.push({ file: rel, errors: [`JSON inválido: ${String(e && e.message).split('\n')[0]}`] });
      continue;
    }
    if (validate(data)) continue;
    violations.push({
      file: rel,
      errors: (validate.errors || []).map((e) => `${e.instancePath || '/'} ${e.message}`),
    });
  }

  const summary = { arquivos: arquivos.length, arquivosComViolacao: violations.length, ok: violations.length === 0 };
  if (JSON_OUT) {
    console.log(JSON.stringify({ summary, violations }, null, 2));
  } else {
    console.log('DTCG — forma dos tokens contra resources/css/tokens/dtcg.schema.json');
    console.log(`  arquivos validados : ${arquivos.length} (${arquivos.map((f) => f.split(/[\\/]/).pop()).join(', ')})`);
    console.log(`  com violação       : ${violations.length}`);
    for (const v of violations) {
      console.log(`\n✗ ${v.file}`);
      for (const e of v.errors) console.log(`    ${e}`);
    }
    if (!violations.length) console.log(`\n✓ Forma conforme nos ${arquivos.length} arquivo(s).`);
  }
  process.exit(violations.length ? 1 : 0);
}

function main() {
  const errors = [];
  const proven = [];

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
        // Era `skipped.push(...)` + `continue` — o token saía do denominador em
        // silêncio e o resumo dizia "todos fiéis" tendo deixado de comparar. Um
        // token sem `source` não tem endereço canônico: é IMPROVÁVEL por
        // construção, e improvável ≠ provado. Fica erro. FP medido = 0 (169 de 169
        // folhas do corpus canônico têm `source` em 2026-08-14), e o `$description`
        // dos dois arquivos já mandava citar o escopo em `source` desde a origem —
        // isto passa a cobrar o que a fonte já declarava.
        errors.push({ path, kind: 'sem-source' });
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

  // `skipped` não existe mais: não há terceira categoria. Cada folha DTCG sai deste
  // laço como `proven` ou como `errors` — o denominador é fechado, e é isso que
  // deixa a frase "todos fiéis" abaixo ser verdadeira sobre o corpus inteiro.
  const summary = {
    proven: proven.length,
    divergences: errors.length,
    ok: errors.length === 0,
  };

  if (JSON_OUT) {
    console.log(JSON.stringify({ summary, errors }, null, 2));
  } else {
    console.log(`DTCG ↔ CSS canônico — equivalência escopo-aware`);
    console.log(`  provados iguais : ${proven.length} (light+dark)`);
    console.log(`  divergências    : ${errors.length}`);
    if (errors.length) {
      console.log(`\n✗ DIVERGÊNCIAS (DTCG não bate com a fonte CSS):`);
      for (const e of errors) {
        if (e.kind === 'valor-divergente' || e.kind === 'valor-dark-divergente') {
          console.log(`  ${e.scope} ${e.var} (${e.file}) [${e.path}]\n      DTCG: ${e.dtcg}\n      CSS : ${e.css}`);
        } else if (e.kind === 'sem-source') {
          console.log(`  sem-source [${e.path}] — sem $extensions."com.oimpresso.source", logo sem`);
          console.log(`      endereço canônico para comparar. Declare o escopo (ex "inertia.css`);
          console.log(`      @theme --minha-var") ou rode --schema, que aponta o mesmo na origem.`);
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

if (SCHEMA_MODE) await mainSchema();
else main();
