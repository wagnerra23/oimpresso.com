#!/usr/bin/env node
// @ts-check
/**
 * component-registry-check.mjs — sentinela de DRIFT do registro de componentes (Onda O2).
 *
 * Valida prototipo-ui/component-registry.json contra o código React REAL: o handoff
 * Cowork → Inertia consome o registry pra traduzir bloco-de-protótipo → componente; se
 * o registry aponta pra componente/import que não existe mais, a tradução regride. Esta
 * sentinela pega esse drift ANTES do handoff.
 *
 * Pra cada entrada com status 'mapped':
 *   1. o arquivo (`file`, repo-relative) existe?
 *   2. o `import_path` resolve pro mesmo arquivo? (alias @/ → resources/js/)
 *   3. cada símbolo em `exports` é REALMENTE exportado pelo arquivo? (parse leve do .tsx/.ts —
 *      cobre `export { A, B }`, `export { A } from './x'`, `export function A`, `export const A`,
 *      `export default`, `export type { T }`)
 * Entradas com status 'gap' (bloco de protótipo sem React equivalente) são puladas — não
 * podem ter file/import/exports (M-AP-6: não fabricar componente).
 *
 * ADVISORY (gate novo nasce advisory — ADR 0271/0275): --check imprime o relatório e
 * SAI 0 SEMPRE no modo advisory (default). Use --strict pra exit 1 quando houver drift
 * (usado pelo self-test e por promoção futura a required). Determinístico, sem deps, sem rede.
 *
 * Uso:
 *   node scripts/governance/component-registry-check.mjs [--check] [--strict] [--registry <path>] [--root <path>]
 */
import { readFileSync, existsSync, statSync } from 'node:fs';
import { join, resolve, relative } from 'node:path';

// existsSync casa diretório também; aqui só queremos ARQUIVO (senão '@/Components/layout'
// casaria a pasta antes de chegar no /index.ts barril).
function isFile(p) {
  try { return statSync(p).isFile(); } catch { return false; }
}

const args = process.argv.slice(2);
function argVal(flag, def) {
  const i = args.indexOf(flag);
  return i >= 0 && args[i + 1] ? args[i + 1] : def;
}
const STRICT = args.includes('--strict');
const ROOT = resolve(argVal('--root', process.cwd()));
const REGISTRY = resolve(argVal('--registry', join(ROOT, 'prototipo-ui/component-registry.json')));

// alias @/ → resources/js/ (vite.config / tsconfig do projeto)
function resolveImport(importPath) {
  if (!importPath) return null;
  let rel = importPath;
  if (rel.startsWith('@/')) rel = join('resources/js', rel.slice(2));
  const base = join(ROOT, rel);
  // tenta .ts, .tsx, /index.ts, /index.tsx (barril)
  for (const cand of [base, `${base}.tsx`, `${base}.ts`, join(base, 'index.ts'), join(base, 'index.tsx')]) {
    if (isFile(cand)) return cand;
  }
  return null;
}

// Parse leve: coleta TODOS os símbolos exportados de um arquivo (não roda TS).
function collectExports(src) {
  const found = new Set();
  // export { A, B as C, type D } [from '...']  — pega o NOME PÚBLICO (após "as" se houver)
  for (const m of src.matchAll(/export\s+(?:type\s+)?\{([^}]*)\}/g)) {
    for (let part of m[1].split(',')) {
      part = part.trim();
      if (!part) continue;
      part = part.replace(/^type\s+/, '');
      const asMatch = part.match(/\bas\s+([A-Za-z0-9_$]+)/);
      const name = asMatch ? asMatch[1] : part.split(/\s+/)[0];
      if (name) found.add(name.trim());
    }
  }
  // export function|const|class|let|var|type|interface|enum NAME
  for (const m of src.matchAll(/export\s+(?:async\s+)?(?:function|const|class|let|var|type|interface|enum)\s+([A-Za-z0-9_$]+)/g)) {
    found.add(m[1]);
  }
  // export default ...
  if (/export\s+default\b/.test(src)) found.add('default');
  return found;
}

function main() {
  if (!existsSync(REGISTRY)) {
    console.error(`[ERRO] registry não encontrado: ${REGISTRY}`);
    process.exit(STRICT ? 1 : 0);
  }
  /** @type {{ entries: any[] }} */
  let reg;
  try {
    reg = JSON.parse(readFileSync(REGISTRY, 'utf8'));
  } catch (e) {
    console.error(`[ERRO] registry inválido (JSON): ${e.message}`);
    process.exit(STRICT ? 1 : 0);
  }
  const entries = Array.isArray(reg.entries) ? reg.entries : [];
  const drift = [];
  let mapped = 0, gaps = 0;

  for (const e of entries) {
    const tag = e.bloco_prototipo || e.componente_react || '(sem-nome)';
    if (e.status === 'gap') {
      gaps++;
      // gap NÃO pode ter file/import/exports — isso seria fabricação (M-AP-6)
      if (e.file || e.import_path || (Array.isArray(e.exports) && e.exports.length)) {
        drift.push({ tag, motivo: `status 'gap' não pode declarar file/import_path/exports (fabricação)` });
      }
      continue;
    }
    if (e.status !== 'mapped') {
      drift.push({ tag, motivo: `status desconhecido: '${e.status}' (esperado mapped|gap)` });
      continue;
    }
    mapped++;

    // 1. file existe?
    if (!e.file) { drift.push({ tag, motivo: `entrada 'mapped' sem campo 'file'` }); continue; }
    const filePath = join(ROOT, e.file);
    if (!existsSync(filePath)) {
      drift.push({ tag, motivo: `file não existe: ${e.file}` });
      continue;
    }

    // 2. import_path resolve pro mesmo arquivo?
    const resolved = resolveImport(e.import_path);
    if (!resolved) {
      drift.push({ tag, motivo: `import_path não resolve: '${e.import_path}'` });
    } else if (relative(resolved, filePath) !== '') {
      drift.push({ tag, motivo: `import_path '${e.import_path}' resolve pra ${relative(ROOT, resolved).replaceAll('\\', '/')} != file ${e.file}` });
    }

    // 3. exports existem? (icon-registry e barris podem ter exports:[] → só checa file)
    const wantExports = Array.isArray(e.exports) ? e.exports : [];
    if (wantExports.length) {
      const src = readFileSync(filePath, 'utf8');
      const have = collectExports(src);
      const missing = wantExports.filter((x) => !have.has(x));
      if (missing.length) {
        drift.push({ tag, motivo: `exports ausentes em ${e.file}: ${missing.join(', ')}` });
      }
    }
  }

  // relatório
  console.log(`component-registry-check — ${entries.length} entradas (${mapped} mapped · ${gaps} gap)`);
  if (drift.length === 0) {
    console.log(`[OK] registro íntegro: todo 'mapped' bate com arquivo/export real; todo 'gap' sem fabricação.`);
  } else {
    console.log(`[DRIFT] ${drift.length} problema(s):`);
    for (const d of drift) console.log(`  - ${d.tag}: ${d.motivo}`);
    if (!STRICT) {
      console.log(`\n(advisory — exit 0; rode com --strict pra falhar o build)`);
    }
  }

  process.exit(STRICT && drift.length ? 1 : 0);
}

main();
