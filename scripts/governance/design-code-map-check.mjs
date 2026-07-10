#!/usr/bin/env node
// @ts-check
/**
 * design-code-map-check.mjs — sentinela da ponte design↔código PERSISTENTE (<tela>.map.json).
 *
 * Contraparte de verificação do gerador `prototipo-ui/gerar-map.mjs` (mesma separação
 * gerar-contrato.mjs × contrato-de-tela.mjs --contract: um DERIVA o esqueleto, o outro VALIDA
 * o artefato preenchido). Sem esta sentinela o `<tela>.map.json` é só um JSON solto que ninguém
 * garante que continua batendo com o disco (RUNBOOK-aplicar-prototipo-orquestracao.md, Fase 1 —
 * o gap canônico #1 do estado-da-arte 2026-06-22).
 *
 * EIXO (deconflito 2026-07-09 — RUNBOOK Fase 1 §"Deconflito dos 3 eixos"): o <tela>.map.json é
 * ANCHOR-MAP POR REGIÃO de tela (âncora por range de linha, zero reuso entre telas) — NÃO o
 * "Code Connect" do projeto (esse é component-registry.json, eixo componente, verificado por
 * component-registry-check.mjs).
 *
 * ÂNCORA ESTÁVEL do lado VIVO (PR-B do deconflito, 2026-07-09) — fecha o limite "refactor
 * desloca linhas em silêncio": range de linha é INFORMATIVO; a âncora verificável é o atributo
 * data-contract="<id>" no vivo.arquivo (o MESMO id — gerar-map e gerar-contrato derivam ambos
 * slug(parte) do mesmo gap.md; a parte do map e a seção do contrato-de-tela nomeiam a mesma
 * região). Contrato do campo opcional `vivo.ancora` (mesma filosofia do component-registry
 * 'mapped'×'gap': declarado tem que ser REAL; ausência declarada não é punida):
 *   - `ancora: true` (ou string com id custom) → o checker EXIGE data-contract="<id>" presente
 *     no vivo.arquivo; sumiu = DRIFT (refactor removeu a âncora — exatamente o rot silencioso).
 *   - ausente/false → parte é "linha-only" (frágil): contada e reportada no resumo, nunca DRIFT
 *     (o backfill dos maps antigos não vira punição). Se o data-contract="<id>" JÁ existe no
 *     .tsx mas não foi declarado, o checker avisa (nudge pra travar de graça).
 *
 * Pra cada `*.map.json` encontrado sob `memory/requisitos/**`:
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
 * existe OU data-contract declarado que sumiu do .tsx), ou sha que ficou pra trás.
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
import { shaAtualPara, shaIndeterminado } from '../../prototipo-ui/gerar-map.mjs';

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

// id da âncora estável declarada pela parte: `vivo.ancora: true` usa o próprio p.id;
// string não-vazia = id custom; ausente/false/'' = parte linha-only (não declarada).
export function ancoraDeclarada(p) {
  const a = p?.vivo?.ancora;
  if (a === true) return p.id;
  if (typeof a === 'string' && a.trim() && a !== 'TODO') return a.trim();
  return null;
}

/**
 * Verifica um map.json já parseado contra o disco (âncoras + staleness + âncora estável).
 * @returns {{drift: string[], warn: string[], pendentes: number, totalPartes: number, estaveis: number, linhaOnly: number}}
 */
export function verificarMapa(mapa, { root = ROOT } = {}) {
  const drift = [];
  const warn = [];
  const schemaProblemas = validarSchema(mapa);
  if (schemaProblemas.length) return { drift: schemaProblemas.map((m) => `schema: ${m}`), warn, pendentes: 0, totalPartes: 0, estaveis: 0, linhaOnly: 0 };

  let pendentes = 0, estaveis = 0, linhaOnly = 0;
  const arquivosPrototipoReais = new Set();
  const fonteCache = new Map(); // vivo.arquivo → conteúdo (várias partes ancoram no mesmo .tsx)
  const lerVivo = (rel) => {
    if (!fonteCache.has(rel)) { try { fonteCache.set(rel, readFileSync(join(root, rel), 'utf8')); } catch { fonteCache.set(rel, null); } }
    return fonteCache.get(rel);
  };
  const temAncora = (src, id) => src != null && new RegExp(`data-contract=(?:"${escRe(id)}"|'${escRe(id)}'|\\{[\`'"]${escRe(id)}[\`'"]\\})`).test(src);

  for (const p of mapa.partes) {
    const tag = p.id;
    const vivoOk = !isPlaceholder(p.vivo?.arquivo) && existsSync(join(root, p.vivo.arquivo));
    if (!isPlaceholder(p.vivo?.arquivo)) {
      if (!vivoOk) drift.push(`${tag}: vivo.arquivo não existe: ${p.vivo.arquivo}`);
    } else pendentes++;
    if (!isPlaceholder(p.prototipo?.arquivo)) {
      if (!existsSync(join(root, p.prototipo.arquivo))) drift.push(`${tag}: prototipo.arquivo não existe: ${p.prototipo.arquivo}`);
      else arquivosPrototipoReais.add(p.prototipo.arquivo);
    } else pendentes++;

    // ÂNCORA ESTÁVEL do lado vivo (PR-B): declarada tem que ser REAL; não-declarada nunca pune.
    if (vivoOk) {
      const idDeclarado = ancoraDeclarada(p);
      const src = lerVivo(p.vivo.arquivo);
      if (idDeclarado) {
        if (temAncora(src, idDeclarado)) estaveis++;
        else drift.push(`${tag}: âncora estável DECLARADA (vivo.ancora) mas data-contract="${idDeclarado}" NÃO existe em ${p.vivo.arquivo} — refactor removeu a âncora (re-ancorar ou remover a declaração conscientemente)`);
      } else {
        linhaOnly++;
        if (temAncora(src, p.id)) warn.push(`${tag}: data-contract="${p.id}" JÁ existe em ${p.vivo.arquivo} mas o map não declara vivo.ancora — declare true e trave de graça (range de linha é frágil)`);
      }
    }
  }

  // STALENESS dual-formato (PR-C 2026-07-09): `sha256:` = contentHash normalizado (ADR 0324,
  // canônico — pega re-export sem commit, imune a commit que toca path sem mudar conteúdo);
  // legado = git-sha (maps antigos seguem verificados no formato em que nasceram, sem punição
  // retroativa). shaAtualPara roteia (fonte única em gerar-map.mjs).
  if (!shaIndeterminado(mapa.prototipo_sha) && arquivosPrototipoReais.size) {
    const atual = shaAtualPara(mapa.prototipo_sha, [...arquivosPrototipoReais], root);
    if (shaIndeterminado(atual)) {
      warn.push(`prototipo_sha='${mapa.prototipo_sha}' salvo, mas o(s) arquivo(s) de protótipo referenciado(s) não permitem recomputar agora (staleness indeterminada)`);
    } else if (atual !== mapa.prototipo_sha) {
      drift.push(`STALE: prototipo_sha salvo='${mapa.prototipo_sha}' · atual='${atual}' — o protótipo re-exportou (regenerar via gerar-map.mjs --atualizar, preserva o preenchido)`);
    }
  }

  return { drift, warn, pendentes, totalPartes: mapa.partes.length, estaveis, linhaOnly };
}

function escRe(s) { return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

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

  let totalDrift = 0, totalWarn = 0, totalPendentes = 0, totalPartes = 0, totalEstaveis = 0, totalLinhaOnly = 0;
  const relatorio = [];
  for (const mPath of maps) {
    const rel = relative(ROOT, mPath).replaceAll('\\', '/');
    let mapa;
    try { mapa = JSON.parse(readFileSync(mPath, 'utf8')); }
    catch (e) { relatorio.push({ rel, drift: [`JSON inválido: ${e.message}`], warn: [], pendentes: 0, totalPartes: 0 }); totalDrift++; continue; }
    const r = verificarMapa(mapa, { root: ROOT });
    relatorio.push({ rel, ...r });
    totalDrift += r.drift.length; totalWarn += r.warn.length; totalPendentes += r.pendentes; totalPartes += r.totalPartes;
    totalEstaveis += r.estaveis; totalLinhaOnly += r.linhaOnly;
  }

  console.log(`design-code-map-check — ${maps.length} map.json encontrado(s) sob memory/requisitos/`);
  console.log(`cobertura: ${cov.cobertas}/${cov.total} telas com gap.md têm .map.json versionado (${cov.total ? Math.round((cov.cobertas / cov.total) * 100) : 0}%)`);
  console.log(`alcance amplo: ${maps.length}/${charters.length} charters têm .map.json (denominador maior, inclui telas ainda não analisadas pela Fase 1)`);
  const ancoraveis = totalEstaveis + totalLinhaOnly;
  console.log(`âncora estável (data-contract no vivo): ${totalEstaveis}/${ancoraveis} parte(s) com vivo.arquivo real${totalLinhaOnly ? ` — ${totalLinhaOnly} linha-only (frágil: refactor desloca linhas em silêncio; ancore com data-contract="<id>" + vivo.ancora: true)` : ''}`);
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
    console.log(`\n[WARN] ${totalWarn} aviso(s) (não bloqueia — staleness indeterminada / âncora presente não-declarada):`);
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
