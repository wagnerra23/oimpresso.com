#!/usr/bin/env node
// @ts-check
/**
 * design-code-map-check.mjs — sentinela da ponte design↔código PERSISTENTE (<tela>.map.json).
 *
 * Contraparte de verificação do gerador `prototipo-ui/gerar-map.mjs` (mesma separação
 * gerar-contrato.mjs × contrato-de-tela.mjs --contract: um DERIVA o esqueleto, o outro VALIDA
 * o artefato preenchido). Sem esta sentinela o `<tela>.map.json` é só um JSON solto que ninguém
 * garante que continua batendo com o disco — vira "Code Connect de mentira" (RUNBOOK-aplicar-
 * prototipo-orquestracao.md, Fase 1 — o gap canônico #1 do estado-da-arte 2026-06-22).
 *
 * Pra cada `*.map.json` encontrado sob `memory/requisitos/**`:
 *   1. Schema mínimo: version/tela/prototipo_sha/gerado_em/partes[]; cada parte tem
 *      id/prototipo.arquivo/prototipo.linhas/vivo.arquivo/vivo.linhas/status/acao.
 *   2. Âncora existe? `vivo.arquivo` e `prototipo.arquivo` (quando ≠ 'TODO'/'n/a') precisam
 *      existir no disco — bate com o motor já provado do `anchor-lint`/`component-registry-check`
 *      (existsSync-guard, não promessa).
 *   3. STALENESS — recomputa o sha ATUAL do(s) arquivo(s) de `prototipo.arquivo` referenciados
 *      (reusa `computeGitSha` de gerar-map.mjs, 1 fato = 1 lugar) e compara com `prototipo_sha`
 *      salvo. Divergiu → o protótipo re-exportou e o map ficou pra trás (STALE, o motivo #1 do
 *      design do artefato: "Fase 4 aborta se o sha mudou → regenera").
 *   4. COBERTURA — % de telas que já passaram pela Fase 1 (têm `-gap.md`) e têm `.map.json`
 *      correspondente. É a métrica gradeável que o roadmap pede ("% de componentes/telas com
 *      mapping versionado — contável por arquivo").
 *
 * `arquivo: 'TODO'` (âncora ainda não preenchida pelo agente da Fase 1) NUNCA é DRIFT — é
 * cobertura pendente (reportada, não punida; component-registry.json trata 'gap' do mesmo jeito:
 * ausência declarada ≠ fabricação). DRIFT é só: schema quebrado, âncora que MENTE (path que não
 * existe), ou sha que ficou pra trás.
 *
 * ADVISORY de nascença (ADR 0271/0275, mesmo padrão do component-registry-check): --check imprime
 * o relatório e sai 0 sempre, a menos que --strict (aí sai 1 se houver DRIFT — nunca por TODO
 * pendente).
 *
 * Uso:
 *   node scripts/governance/design-code-map-check.mjs [--check] [--strict] [--root <path>]
 *   node scripts/governance/design-code-map-check.mjs --selftest
 */
import { readFileSync, existsSync } from 'node:fs';
import { readdir } from 'node:fs/promises';
import { join, resolve, relative, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { computeGitSha } from '../../prototipo-ui/gerar-map.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const args = process.argv.slice(2);
function argVal(flag, def) { const i = args.indexOf(flag); return i >= 0 && args[i + 1] ? args[i + 1] : def; }
const STRICT = args.includes('--strict');
const ROOT = resolve(argVal('--root', join(HERE, '..', '..')));

const SKIP_DIRS = new Set(['node_modules', '.git', '_arquivo', '_BACKUP-NAO-USAR', 'scraps']);

async function walk(dir, out = []) {
  let entries;
  try { entries = await readdir(dir, { withFileTypes: true }); } catch { return out; }
  for (const e of entries) {
    if (SKIP_DIRS.has(e.name)) continue;
    const full = join(dir, e.name);
    if (e.isDirectory()) await walk(full, out);
    else out.push(full);
  }
  return out;
}

function isPlaceholder(v) { return v === 'TODO' || v === 'n/a' || v == null || v === ''; }

/** Valida o schema mínimo de um map.json parseado. Devolve lista de motivos (vazia = ok). */
export function validarSchema(mapa) {
  const problemas = [];
  if (!mapa || typeof mapa !== 'object') return ['JSON raiz não é objeto'];
  for (const campo of ['tela', 'prototipo_sha', 'partes']) {
    if (!(campo in mapa)) problemas.push(`campo obrigatório ausente: '${campo}'`);
  }
  if (!Array.isArray(mapa.partes)) { problemas.push(`'partes' não é array`); return problemas; }
  if (mapa.partes.length === 0) problemas.push(`'partes' vazio (map sem conteúdo)`);
  mapa.partes.forEach((p, i) => {
    const tag = p?.id || `partes[${i}]`;
    if (!p.id) problemas.push(`${tag}: sem 'id'`);
    if (!p.prototipo || typeof p.prototipo.arquivo === 'undefined') problemas.push(`${tag}: sem 'prototipo.arquivo'`);
    if (!p.vivo || typeof p.vivo.arquivo === 'undefined') problemas.push(`${tag}: sem 'vivo.arquivo'`);
    if (!p.status) problemas.push(`${tag}: sem 'status'`);
  });
  return problemas;
}

/**
 * Verifica um map.json já parseado contra o disco (âncoras + staleness).
 * @returns {{drift: string[], warn: string[], pendentes: number, totalPartes: number}}
 */
export function verificarMapa(mapa, { root = ROOT } = {}) {
  const drift = [];
  const warn = [];
  const schemaProblemas = validarSchema(mapa);
  if (schemaProblemas.length) return { drift: schemaProblemas.map((m) => `schema: ${m}`), warn, pendentes: 0, totalPartes: 0 };

  let pendentes = 0;
  const arquivosPrototipoReais = new Set();
  for (const p of mapa.partes) {
    const tag = p.id;
    if (!isPlaceholder(p.vivo?.arquivo)) {
      if (!existsSync(join(root, p.vivo.arquivo))) drift.push(`${tag}: vivo.arquivo não existe: ${p.vivo.arquivo}`);
    } else pendentes++;
    if (!isPlaceholder(p.prototipo?.arquivo)) {
      if (!existsSync(join(root, p.prototipo.arquivo))) drift.push(`${tag}: prototipo.arquivo não existe: ${p.prototipo.arquivo}`);
      else arquivosPrototipoReais.add(p.prototipo.arquivo);
    } else pendentes++;
  }

  if (mapa.prototipo_sha && mapa.prototipo_sha !== 'sem-historico' && arquivosPrototipoReais.size) {
    const atual = computeGitSha([...arquivosPrototipoReais], root);
    if (atual === 'sem-historico') {
      warn.push(`prototipo_sha='${mapa.prototipo_sha}' salvo, mas o(s) arquivo(s) de protótipo referenciado(s) não têm histórico git rastreável agora (staleness indeterminada)`);
    } else if (atual !== mapa.prototipo_sha) {
      drift.push(`STALE: prototipo_sha salvo='${mapa.prototipo_sha}' · atual='${atual}' — o protótipo re-exportou (regenerar via gerar-map.mjs)`);
    }
  }

  return { drift, warn, pendentes, totalPartes: mapa.partes.length };
}

async function coletar(root) {
  // map.json/gap.md vivem em memory/requisitos/<Mod>/ (SPEC/CHANGELOG/GAP-SPEC do módulo);
  // charter.md vive AO LADO da tela viva (resources/js/Pages/<Mod>/<Tela>.charter.md) — são
  // árvores DIFERENTES, walk cada uma na raiz certa (bug real pego no smoke: 6≠146).
  const reqTree = await walk(join(root, 'memory', 'requisitos'));
  const pagesTree = await walk(join(root, 'resources', 'js', 'Pages'));
  const maps = reqTree.filter((f) => f.endsWith('.map.json'));
  const gaps = reqTree.filter((f) => f.endsWith('-gap.md'));
  const charters = pagesTree.filter((f) => f.endsWith('.charter.md'));
  return { maps, gaps, charters };
}

// telas com gap.md que TAMBÉM têm map.json (mesmo diretório, prefixo <slug>.map.json p/ <slug>-gap.md)
function cobertura(gaps, maps, root) {
  const mapSet = new Set(maps.map((m) => relative(root, m).replaceAll('\\', '/')));
  let cobertas = 0;
  const semMap = [];
  for (const g of gaps) {
    const esperado = g.replace(/-gap\.md$/, '.map.json');
    const rel = relative(root, esperado).replaceAll('\\', '/');
    if (mapSet.has(rel)) cobertas++; else semMap.push(relative(root, g).replaceAll('\\', '/'));
  }
  return { cobertas, total: gaps.length, semMap };
}

async function main() {
  const { maps, gaps, charters } = await coletar(ROOT);
  const cov = cobertura(gaps, maps, ROOT);

  let totalDrift = 0, totalWarn = 0, totalPendentes = 0, totalPartes = 0;
  const relatorio = [];
  for (const mPath of maps) {
    const rel = relative(ROOT, mPath).replaceAll('\\', '/');
    let mapa;
    try { mapa = JSON.parse(readFileSync(mPath, 'utf8')); }
    catch (e) { relatorio.push({ rel, drift: [`JSON inválido: ${e.message}`], warn: [], pendentes: 0, totalPartes: 0 }); totalDrift++; continue; }
    const r = verificarMapa(mapa, { root: ROOT });
    relatorio.push({ rel, ...r });
    totalDrift += r.drift.length; totalWarn += r.warn.length; totalPendentes += r.pendentes; totalPartes += r.totalPartes;
  }

  console.log(`design-code-map-check — ${maps.length} map.json encontrado(s) sob memory/requisitos/`);
  console.log(`cobertura: ${cov.cobertas}/${cov.total} telas com gap.md têm .map.json versionado (${cov.total ? Math.round((cov.cobertas / cov.total) * 100) : 0}%)`);
  console.log(`alcance amplo: ${maps.length}/${charters.length} charters têm .map.json (denominador maior, inclui telas ainda não analisadas pela Fase 1)`);
  if (cov.semMap.length) {
    console.log(`\ngap.md SEM map.json correspondente (candidatos a 'node prototipo-ui/gerar-map.mjs <gap.md>'):`);
    for (const g of cov.semMap) console.log(`  - ${g}`);
  }

  if (totalDrift === 0) {
    console.log(`\n[OK] nenhum map.json com âncora quebrada ou sha stale. ${totalPendentes} âncora(s) TODO pendente(s) de preenchimento (não é drift).`);
  } else {
    console.log(`\n[DRIFT] ${totalDrift} problema(s):`);
    for (const r of relatorio) if (r.drift.length) { console.log(`  ${r.rel}:`); for (const d of r.drift) console.log(`    - ${d}`); }
    if (!STRICT) console.log(`\n(advisory — exit 0; rode com --strict pra falhar o build)`);
  }
  if (totalWarn) {
    console.log(`\n[WARN] ${totalWarn} aviso(s) (staleness indeterminada, não bloqueia):`);
    for (const r of relatorio) if (r.warn.length) { console.log(`  ${r.rel}:`); for (const w of r.warn) console.log(`    - ${w}`); }
  }

  process.exit(STRICT && totalDrift > 0 ? 1 : 0);
}

const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invokedDirectly) {
  if (args.includes('--selftest')) {
    console.error('selftest deste script vive em design-code-map-check.test.mjs (precisa de git real p/ provar staleness) — rode: node scripts/governance/design-code-map-check.test.mjs');
    process.exit(2);
  }
  main();
}
