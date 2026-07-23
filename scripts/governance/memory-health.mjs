#!/usr/bin/env node
// @ts-check
/**
 * memory-health.mjs — sentinela de saúde da base de conhecimento (ADR 0256, Onda 1).
 *
 * O "batimento cardíaco" da memória: roda as checagens MECÂNICAS que a auditoria
 * 2026-06-07 fez à mão, agora automáticas. Espelha `jana:health-check` (dados) e
 * `screen-grades-ratchet` (telas), apontado pra a própria base de conhecimento.
 * Determinístico, sem LLM, sem dependência — roda em CI (PR toca memory/**) e local.
 *
 * Checagens:
 *   A · COLISÃO ADR não-registrada — número de ADR duplicado que NÃO consta em
 *       governance/adr-collisions-baseline.json (ADR 0274 §3, antes _INDEX-LIFECYCLE.md).
 *       (🔴 fail — espelha AdrNumberCollisionTest sem vendor.)
 *   B · SCORECARD FANTASMA — scorecard cuja tela (.tsx) foi commitada DEPOIS do
 *       graded_at → nota provavelmente stale. (🟡 warn — é sinal, não bloqueio.)
 *   C · SEGREDO EM memory/ — secret pattern em memory/** sem entry no _INDEX-SECRETS.
 *       (🔴 fail — defense-in-depth do secrets:scan PHP; este pega senha PT-BR/UUID.)
 *   D · DOC STALE — doc canon com "última atualização"/reviewed_at > LIMIAR meses
 *       que se diz canon/estado-atual. (🟡 warn.)
 *   E · ADR ENUM-DRIFT — status/lifecycle de ADR fora do enum canônico
 *       (accepted vs aceito, active vs ativo, canon/feature_wish inválidos).
 *       É o que impede a contagem limpa de "ADRs ativos". (🟡 warn.)
 *   N · COLISÃO US-ID — US-<MOD>-NNN duplicado entre/dentro dos SPEC.md (sibling do
 *       Check A pra histórias). Ratchet-grandfathered (como C/L): 🔴 fail só em dup NOVO
 *       acima do baseline. Use next-id.mjs pra alocar sem colidir. (ADR 0304.)
 *   X · COBERTURA DE AUDITORIA — módulo Tier-0 OU nota module-grade < FLOOR sem NENHUM
 *       AUDIT*.md no dir de requisitos. Responde "isso está auditado?" mecanicamente.
 *       (🟡 warn · determinístico · PLANO-APROFUNDAMENTO-AVALIACOES.md F1/F2.)
 *   P · REF DE AUTOMAÇÃO MORTA — code-span `.claude/**` cujo alvo não existe em disco.
 *       No registry AUTOMATIONS.md = 🔴 fail (é o que o time consome via MCP); no resto
 *       da canon front-facing = 🟡 warn. Sibling do Check V (mesmo defeito, extrator de
 *       code-span em vez de link markdown). Pega o porte .ps1→.mjs que esquece o registry.
 *   Q · AUTORIDADE DOCUMENTAL — uma porta global (`README.md`), sem conteúdo vivo
 *       idêntico e sem colisão de type+slug. (🔴 fail; prevenção local no hook.)
 *
 * Uso:
 *   node scripts/governance/memory-health.mjs            (CI: exit 1 se algum 🔴)
 *   node scripts/governance/memory-health.mjs --json     (saída JSON pra Daily Brief)
 *   node scripts/governance/memory-health.mjs --warn-only (nunca exit 1; só relata)
 *
 * Refs: ADR 0256 (Knowledge Survival) · ADR 0215 (secrets) · ADR 0180 (colisão ADR)
 *       · ADR 0250 (screen-qa) · AUDITORIA-CONFLITOS-MEMORIA-2026-06-07.md
 */
import { readdirSync, readFileSync, existsSync, writeFileSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';
import { auditDocumentAuthority, CANONICAL_ENTRYPOINT } from './document-authority.mjs';
import { factAnchorScan } from './fact-anchor.mjs';

const ROOT = process.cwd();
const JSON_OUT = process.argv.includes('--json');
const WARN_ONLY = process.argv.includes('--warn-only');
const STALE_MONTHS = 6; // doc canon parado > 6 meses = candidato a revisão

// GAP 2 (ADR 0258) — baseline ratchet do Check C (segredos): só falha em segredo NOVO
// acima do teto por arquivo. Aceitos (ex: default Firebird "masterkey") ficam no baseline.
const BASELINE_FILE = 'scripts/governance/.memory-health-baseline.json';
const UPDATE_BASELINE = process.argv.includes('--update-baseline');
const baseline = existsSync(join(ROOT, BASELINE_FILE)) ? JSON.parse(readFileSync(join(ROOT, BASELINE_FILE), 'utf8')) : { checkC: {}, checkL: [], checkM: [], checkN: [], checkO: [], checkR: [] };
let checkCByFile = {};
let checkLSlugs = []; // Check L (ADR vivo-mas-proposto): slugs detectados nesta run
let checkMKeys = []; // Check M (teto de governança): keys de workflow do registry nesta run
let checkNIds = []; // Check N (colisão US-ID): IDs duplicados detectados nesta run
let checkOSlugs = []; // Check O (morta-mas-canon): slugs de ADR morta ainda citada como canon
let checkRSlugs = []; // Check R (revisão vencida): slugs de ADR viva com decided_at+TTL vencido

const fails = []; // 🔴 bloqueia CI
const warns = []; // 🟡 só sinaliza

// ── helpers ────────────────────────────────────────────────────────────────
const read = (p) => readFileSync(join(ROOT, p), 'utf8');
const exists = (p) => existsSync(join(ROOT, p));
function gitLastDate(relPath) {
  try {
    return execSync(`git log -1 --format=%cs -- "${relPath}"`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  } catch { return ''; }
}
function listFiles(dir, filterFn) {
  const out = [];
  const walk = (d) => {
    let entries;
    try { entries = readdirSync(join(ROOT, d), { withFileTypes: true }); } catch { return; }
    for (const e of entries) {
      const rel = `${d}/${e.name}`;
      if (e.isDirectory()) walk(rel);
      else if (filterFn(rel)) out.push(rel);
    }
  };
  walk(dir);
  return out;
}

// ── Check A: colisão de número de ADR não-registrada ────────────────────────
function checkAdrCollisions() {
  const dir = 'memory/decisions';
  if (!exists(dir)) return;
  const byNum = {};
  for (const f of readdirSync(join(ROOT, dir))) {
    const m = f.match(/^(\d{4})-.+\.md$/);
    if (m) (byNum[m[1]] ??= []).push(f);
  }
  const dups = Object.entries(byNum).filter(([, fs]) => fs.length > 1);
  // Registro de colisões conhecidas (ratchet append-only, "só encolhe"):
  // governance/adr-collisions-baseline.json (collisions_grandfathered). Fonte
  // machine-readable única — mandato ADR 0274 §3 (aponta o Check A pro alias-map/
  // baseline em vez do _INDEX-LIFECYCLE.md, que estava defasado — total:119 vs disco).
  // baseline ≡ governance/adr-alias-map.json (mesmas 14 colisões). Baseline ilegível
  // ou ausente ⇒ set vazio ⇒ toda colisão morde (fail-safe).
  const baselinePath = 'governance/adr-collisions-baseline.json';
  const grandfathered = new Set();
  if (exists(baselinePath)) {
    try {
      const gj = JSON.parse(read(baselinePath));
      for (const n of gj.collisions_grandfathered ?? []) grandfathered.add(String(n).padStart(4, '0'));
    } catch { /* JSON inválido → set vazio (fail-safe) */ }
  }
  for (const [num, files] of dups) {
    if (!grandfathered.has(num)) {
      fails.push({ check: 'A', kind: 'colisao-adr-nao-registrada', num, files,
        msg: `ADR ${num} colidiu (${files.length} arquivos) e NÃO consta em governance/adr-collisions-baseline.json (collisions_grandfathered) — registre a colisão (ADR 0180/0274) ou referencie por slug.` });
    }
  }
}

// ── Check N: colisão de US-ID (sibling do Check A pra histórias) ────────────
// US-<MOD>-NNN definido (heading `### US-...`) mais de uma vez entre/dentro dos SPEC.md.
// Mesma causa-raiz das colisões de ADR: alocação cega (ADR 0304). Ratchet como C/L: os
// dups LEGADOS ficam no baseline (.checkN); só dup NOVO acima do baseline 🔴 falha — assim
// não quebra a main (4 dups herdados) mas BARRA colisão nova. Promoção/limpeza encolhe o
// baseline à vista. Aloque sem colidir com `node scripts/governance/next-id.mjs us <PREFIXO>`.
function checkUsCollisions() {
  const reqRoot = 'memory/requisitos';
  if (!exists(reqRoot)) return;
  const byId = {};
  for (const d of readdirSync(join(ROOT, reqRoot), { withFileTypes: true })) {
    if (!d.isDirectory()) continue;
    const sp = `${reqRoot}/${d.name}/SPEC.md`;
    if (!exists(sp)) continue;
    let txt; try { txt = read(sp); } catch { continue; }
    for (const m of txt.matchAll(/^###\s+(US-[A-Z]+-\d+)\b/gm)) (byId[m[1]] ??= []).push(d.name);
  }
  const dups = Object.entries(byId).filter(([, locs]) => locs.length > 1);
  checkNIds = dups.map(([id]) => id).sort();
  if (UPDATE_BASELINE) return; // no modo update só capturamos
  const grandfathered = new Set(baseline.checkN || []);
  const novos = dups.filter(([id]) => !grandfathered.has(id));
  if (novos.length) {
    fails.push({ check: 'N', kind: 'colisao-us-id', count: novos.length,
      sample: novos.slice(0, 15).map(([id, locs]) => `${id} (${locs.length}× — ${[...new Set(locs)].join(', ')})`),
      msg: `${novos.length} US-ID(s) duplicado(s) NOVO(s) nos SPEC.md — alocação cega (ADR 0304). Aloque com \`node scripts/governance/next-id.mjs us <PREFIXO>\`. Se legítimo (ex: renumeração em curso), rode --update-baseline. (Check N · sibling do Check A)` });
  }
}

// ── Check B: scorecard fantasma (tela mudou depois da nota) ─────────────────
function checkScorecardFantasma() {
  const dir = 'memory/governance/scorecards/screens';
  if (!exists(dir)) return;
  let stale = 0; const worst = [];
  for (const f of readdirSync(join(ROOT, dir))) {
    if (!f.endsWith('.yaml')) continue;
    const txt = read(`${dir}/${f}`);
    const graded = (txt.match(/^graded_at:\s*["']?([\d-]+)/m) || [])[1];
    const path = (txt.match(/^path:\s*(.+)$/m) || [])[1]?.trim();
    if (!graded || !path) continue;
    const tsxDate = gitLastDate(path);
    if (tsxDate && tsxDate > graded) { stale++; worst.push({ screen: f.replace('.yaml', ''), graded, tsxDate }); }
  }
  if (stale > 0) {
    warns.push({ check: 'B', kind: 'scorecard-fantasma', count: stale,
      sample: worst.slice(0, 8),
      msg: `${stale} scorecard(s) com nota possivelmente STALE (a tela mudou depois do graded_at). Re-gradear pra a nota refletir o código atual.` });
  }
}

// ── Check C: segredo em memory/ sem entry no índice ─────────────────────────
// Só padrões ANCORADOS EM CONTEXTO (prefixo de segredo). Padrões genéricos de
// alta-entropia (base64/uuid soltos) foram REMOVIDOS — davam 3000+ falso-positivos
// (todo hash/ID em doc casava). Como Check C é GATE (🔴), precisa ser preciso.
const SECRET_PATTERNS = {
  bearer: /Authorization:\s*Bearer\s+([A-Za-z0-9_.\-]{20,})/i,
  aws: /AKIA[0-9A-Z]{16}/,
  assign_token: /\b(?:API_KEY|API_TOKEN|SECRET|SECRET_KEY|ADMIN_TOKEN|ACCESS_KEY|MASTER_KEY|CLIENT_SECRET|PRIVATE_KEY)\s*[:=]\s*["']?([A-Za-z0-9!@#$%^&*_.\-]{16,})["']?/i,
  password_assign: /\b(?:PASSWORD|PASSWD|senha)\s*[:=]\s*["']?([^\s"'<>]{6,})["']?/i,
};
// valores que NÃO são segredo (reduz falso-positivo dos padrões ancorados).
const SECRET_FALSE_POSITIVE = /placeholder|example|exemplo|REDACTED|\bxxx|\.\.\.|<[a-z_.]+>|\$\{|\$[A-Z_]+|env\(|getenv|valor no Vault|no Vaultwarden|\*{4,}/i;

function checkSecretsInMemory() {
  const idxPath = 'memory/_INDEX-SECRETS.md';
  const index = exists(idxPath) ? read(idxPath) : '';
  const files = listFiles('memory', (rel) => rel.endsWith('.md') && rel !== idxPath);
  const hits = [];
  for (const rel of files) {
    const lines = read(rel).split('\n');
    lines.forEach((line, i) => {
      if (SECRET_FALSE_POSITIVE.test(line)) return;
      for (const [name, re] of Object.entries(SECRET_PATTERNS)) {
        const m = line.match(re);
        if (!m) continue;
        const value = m[1] || m[0];
        // "coberto pelo índice" = valor OU caminho do arquivo citado no _INDEX-SECRETS.
        const coveredByValue = index.includes(value);
        const coveredByPath = index.includes(rel);
        if (!coveredByValue && !coveredByPath) {
          hits.push({ file: rel, line: i + 1, pattern: name });
        }
        break; // 1 hit por linha basta
      }
    });
  }
  // Ratchet por arquivo (GAP 2): só segredo NOVO acima do baseline falha. Remover é livre.
  for (const h of hits) checkCByFile[h.file] = (checkCByFile[h.file] || 0) + 1;
  if (UPDATE_BASELINE) return; // no modo update só capturamos; nada de fail
  const novos = [];
  for (const [f, n] of Object.entries(checkCByFile)) {
    const teto = (baseline.checkC || {})[f] || 0;
    if (n > teto) novos.push(`${f} (${n - teto} novo acima do teto ${teto})`);
  }
  if (novos.length) {
    fails.push({ check: 'C', kind: 'segredo-novo-em-memory', count: novos.length, sample: novos.slice(0, 15),
      msg: `segredo(s) NOVO(s) em memory/** acima do baseline. Mova pra CT100/Vault + ponteiro (ADR 0061/0215). Se legítimo (ex: default doc), rode --update-baseline.` });
  }
}

// ── Check D: doc canon stale por idade ──────────────────────────────────────
function checkStaleCanon() {
  const today = new Date(gitLastDate('.') || '2026-06-07'); // evita Date.now (determinismo CI)
  const cutoff = new Date(today); cutoff.setMonth(cutoff.getMonth() - STALE_MONTHS);
  const cutoffStr = cutoff.toISOString().slice(0, 10);
  const files = listFiles('memory/reference', (rel) => rel.endsWith('.md'));
  let staleN = 0; const sample = [];
  for (const rel of files) {
    const txt = read(rel);
    const m = txt.match(/(?:reviewed_at|última atualização|ultima atualizacao)["':\s]*([\d]{4}-[\d]{2}-[\d]{2})/i);
    if (m && m[1] < cutoffStr) { staleN++; if (sample.length < 8) sample.push({ file: rel, date: m[1] }); }
  }
  if (staleN > 0) {
    warns.push({ check: 'D', kind: 'doc-stale', count: staleN, sample,
      msg: `${staleN} doc(s) reference/ sem revisão há > ${STALE_MONTHS} meses — revisar ou marcar lifecycle.` });
  }
}

// ── Check S: camada de ENTRADA stale por idade ──────────────────────────────
// Sentinela advisory (ADR 0256 knowledge-survival + 0317 classe TEMPO). A porta de
// entrada humana (README, Guia, ARCHITECTURE, índices) drifa em SILÊNCIO — ninguém
// a lê pra editar, só pra entrar. O Check D só cobre reference/; esta lista explícita
// cobre os docs que um humano/dev novo bate primeiro. Frescor = marcador in-doc se
// houver, senão data do último commit (gitLastDate — determinístico no CI). Warn-only.
const ENTRY_DOCS = [
  'README.md',
  'CLAUDE.md',
  'memory/GUIA-DO-SISTEMA.md',
  'memory/INDEX.md',
  'memory/INDEX_TEMATICO.md',
  'memory/governance/ARCHITECTURE.md',
  'memory/why-oimpresso.md',
  'memory/what-oimpresso.md',
  'memory/how-trabalhar.md',
];
const ENTRY_STALE_MONTHS = 6;
function checkStaleEntryLayer() {
  const today = new Date(gitLastDate('.') || '2026-06-07'); // evita Date.now (determinismo CI)
  const cutoff = new Date(today); cutoff.setMonth(cutoff.getMonth() - ENTRY_STALE_MONTHS);
  const cutoffStr = cutoff.toISOString().slice(0, 10);
  const sample = [];
  for (const rel of ENTRY_DOCS) {
    let txt = ''; try { txt = read(rel); } catch { continue; }
    if (!txt) continue; // ausente = ignora (não inventa)
    const m = txt.match(/(?:last_updated|reviewed_at|última atualização|ultima atualizacao)["':\s]*([\d]{4}-[\d]{2}-[\d]{2})/i);
    const date = m ? m[1] : gitLastDate(rel);
    if (date && date < cutoffStr) sample.push({ file: rel, date });
  }
  if (sample.length) {
    warns.push({ check: 'S', kind: 'entrada-stale', count: sample.length, sample: sample.slice(0, 9),
      msg: `${sample.length} doc(s) da CAMADA DE ENTRADA sem revisão há > ${ENTRY_STALE_MONTHS} meses — a porta de entrada humana drifa em silêncio. Revisar o fato (ou bump last_updated se ainda vale). 🟡 sentinela — não bloqueia.` });
  }
}

// ── Check T: FACT-ANCHOR determinístico (dominio-gate generalizado p/ FATOS) ─
// Check S/D nagam por IDADE; este ancora o FATO afirmado numa FONTE-DE-VERDADE
// versionada (package.json/composer.json/árvore Modules/) e flagra CONTRADIÇÃO.
// Pega o "React 18" (era 19) e "Modules/MemCofre" (renomeado→SRS) — os erros reais
// de 2026-07-04. SOTA 2026: correção-do-fato GERAL não tem solução barata (Dosu:
// "detecta drift, não correção"), mas o SUBCONJUNTO ancorável é 100% determinístico
// e não precisa de LLM. Advisory primeiro (ADR 0275 — promover a fail quando maduro).
// Generaliza o dominio-gate (ADR 0264: enum⇔dicionário) para claims de doc.
// SÓ docs 100% current-state: aqui módulo/versão citados são SEMPRE claim atual.
// ARCHITECTURE (tabela de renames De→Pra), INDEX/INDEX_TEMATICO (seções legadas/
// temáticas) FICAM DE FORA — lá a menção a nome antigo é legítima (senão vira FP,
// calibrado na 1ª rodada 2026-07-04: 18 hits → ~2 reais). Anti-teatro (ADR 0314).
const CURRENT_STATE_DOCS = [
  'README.md',
  'CLAUDE.md',
  'memory/GUIA-DO-SISTEMA.md',
  'memory/what-oimpresso.md',
  'memory/why-oimpresso.md',
  'memory/how-trabalhar.md',
];
function checkFactAnchor() {
  let pkg = {}; try { pkg = JSON.parse(read('package.json')); } catch {}
  let comp = {}; try { comp = JSON.parse(read('composer.json')); } catch {}
  const docs = CURRENT_STATE_DOCS.map((rel) => { let txt = ''; try { txt = read(rel); } catch {} return { rel, txt }; });
  // Lógica pura em fact-anchor.mjs (testável hermético). VERSIONS ampliada (Inertia/
  // Tailwind/Pest/PHPUnit) + regex v? em 2026-07-23 (proposal fatos-derivaveis).
  const hits = factAnchorScan({ docs, pkg, comp, moduleExists: (n) => existsSync(join(ROOT, 'Modules', n)) });
  if (hits.length) {
    fails.push({ check: 'T', kind: 'fato-ancora-drift', count: hits.length, sample: hits.slice(0, 12),
      msg: `${hits.length} FATO(s) na camada de entrada CONTRADIZ(em) a fonte-de-verdade (package.json/composer.json/Modules/). Corrigir o doc — não é idade, é erro. 🔴 required (ADR 0349 — promovido a fail: determinístico major-only, zero contradição viva na promoção). Reversível: FP → volta a warns + PR de demoção.` });
  }
}

// ── Check U: LIMBO — drafts de ADR parados + dirs homônimos ──────────────────
// Único dos 3 buracos 100% determinístico (SOTA). Draft que nunca promoveu apodrece
// silencioso; dir homônimo (dominio/ vs dominios/) racha navegação/recall. Warn-only.
const PROPOSAL_LIMBO_DAYS = 120;
function docDate(rel, txt) {
  const fm = (txt || '').match(/(?:decided_at|date)["':\s]*([\d]{4}-[\d]{2}-[\d]{2})/i);
  if (fm) return fm[1];
  const fn = rel.match(/(\d{4}-\d{2}-\d{2})/); // data no nome do arquivo
  if (fn) return fn[1];
  return gitLastDate(rel);
}
function checkLimbo() {
  const today = new Date(gitLastDate('.') || '2026-06-07'); // determinismo CI
  const cutoff = new Date(today); cutoff.setDate(cutoff.getDate() - PROPOSAL_LIMBO_DAYS);
  const cutoffStr = cutoff.toISOString().slice(0, 10);
  // Idade por git-date é MASCARADA pelo squash-restore #2413 (tudo virou commit de
  // 2026-06-08) → sinal honesto = CONTAGEM do pile, não idade. cutoffStr fica pro
  // ranking (mais antigo por frontmatter/nome primeiro), não pro corte.
  void cutoffStr;
  const props = listFiles('memory/decisions/proposals', (p) => p.endsWith('.md'))
    .map((rel) => { let t = ''; try { t = read(rel); } catch {} return { file: rel.replace('memory/decisions/proposals/', ''), date: docDate(rel, t) }; })
    .sort((a, b) => String(a.date).localeCompare(String(b.date)));
  const PILE = 25;
  if (props.length > PILE) {
    warns.push({ check: 'U', kind: 'proposta-em-limbo', count: props.length, sample: props.slice(0, 10),
      msg: `${props.length} draft(s) acumulado(s) em decisions/proposals/ (saudável ~${PILE}) — pile de limbo sem decaimento; triar: promover (supersede atômico) / arquivar / esquecer (ADR 0316/0270). 🟡 sentinela.` });
  }
  let subs = []; try { subs = readdirSync(join(ROOT, 'memory'), { withFileTypes: true }).filter((e) => e.isDirectory()).map((e) => e.name); } catch {}
  const set = new Set(subs);
  const homon = subs.filter((n) => set.has(n + 's')).map((n) => `${n}/ ⇄ ${n}s/`);
  if (homon.length) {
    warns.push({ check: 'U', kind: 'dir-homonimo', count: homon.length, sample: homon,
      msg: `dir(s) homônimo(s) sob memory/ (confunde navegação/recall): ${homon.join(' · ')} — desambiguar (renomear um). 🟡 sentinela.` });
  }
}

// ── Check Q: autoridade documental única + porta global única ───────────────
// Extensão fail-class do sentinela existente: não cria workflow nem baseline.
// O hook previne Write novo; este check cobre Edit/MultiEdit e o merge.
function checkDocumentAuthority() {
  const audit = auditDocumentAuthority(ROOT);
  for (const files of audit.duplicates) {
    fails.push({ check: 'Q', kind: 'doc-conteudo-duplicado', count: files.length, files,
      msg: `conteúdo documental vivo idêntico em ${files.length} arquivos. Atualize a autoridade existente ou transforme o outro arquivo em ponteiro local.` });
  }
  for (const collision of audit.authorityCollisions) {
    fails.push({ check: 'Q', kind: 'doc-autoridade-duplicada', count: collision.files.length, files: collision.files,
      msg: `type+slug documental '${collision.authorityKey}' aparece em mais de um arquivo. Um assunto deve ter uma autoridade única.` });
  }
  if (exists(CANONICAL_ENTRYPOINT) && (audit.canonicalMarkers.length !== 1 || audit.canonicalMarkers[0] !== CANONICAL_ENTRYPOINT)) {
    fails.push({ check: 'Q', kind: 'porta-documental-canonica', count: audit.canonicalMarkers.length, files: audit.canonicalMarkers,
      msg: `a porta global deve ser única e declarada somente em ${CANONICAL_ENTRYPOINT}; encontradas: ${audit.canonicalMarkers.length}.` });
  }
  if (audit.parallelHeadings.length) {
    fails.push({ check: 'Q', kind: 'porta-documental-paralela', count: audit.parallelHeadings.length, files: audit.parallelHeadings,
      msg: `heading "Comece aqui" fora de ${CANONICAL_ENTRYPOINT} cria navegação paralela. Converta-o em rota local ou referência de catálogo.` });
  }
}

// ── Check V: LINKS internos quebrados na canon front-facing ──────────────────
// Determinístico, ZERO falso-positivo (o alvo existe ou não). SOTA docs-as-code
// (lychee/lint). Escopo: docs que um humano LÊ (raiz + governance + reference);
// exclui sessions/handoffs/decisions/proposals (append-only/histórico — link morto
// lá é registro de época, não bug vivo). Resolve caminho relativo em posix. Warn-only.
const LINK_CANON = (rel) => /^(README|CLAUDE|DESIGN)\.md$/.test(rel)
  || /^memory\/[^/]+\.md$/.test(rel)
  || /^memory\/(governance|reference)\/[^/]+\.md$/.test(rel);
function resolveRel(fromRel, link) {
  const base = fromRel.includes('/') ? fromRel.slice(0, fromRel.lastIndexOf('/')) : '';
  const stack = base ? base.split('/') : [];
  for (const seg of link.split('/')) {
    if (seg === '..') stack.pop();
    else if (seg !== '.' && seg !== '') stack.push(seg);
  }
  return stack.join('/');
}
function checkBrokenLinks() {
  const files = [...listFiles('memory', (p) => p.endsWith('.md')), 'README.md', 'CLAUDE.md', 'DESIGN.md'].filter(LINK_CANON);
  const broken = [];
  for (const rel of files) {
    let txt = ''; try { txt = read(rel); } catch { continue; }
    for (const m of txt.matchAll(/\]\((?!https?:|mailto:|#)([^)]+)\)/g)) {
      const p = m[1].split('#')[0].trim();
      if (!p || p.startsWith('/')) continue; // vazio/âncora/absoluto — fora
      const target = resolveRel(rel, p);
      if (target && !existsSync(join(ROOT, target))) broken.push({ file: rel, link: p });
    }
  }
  if (broken.length) {
    warns.push({ check: 'V', kind: 'link-quebrado', count: broken.length, sample: broken.slice(0, 15),
      msg: `${broken.length} link(s) interno(s) quebrado(s) na canon front-facing (alvo inexistente) — determinístico, sem FP. Corrigir slug/caminho (ADR renomeada? use o slug real; arquivo movido/esquecido? re-apontar). 🟡 sentinela.` });
  }
}

// ── Check P: ref de automação apontando pra arquivo morto ───────────────────
// AUTOMATIONS.md é o inventário canônico de automações — indexado pelo MCP server,
// serve o time como fonte de "quais automações existem e ONDE moram". A coluna
// "Arquivo" é, por contrato do próprio doc (§"Como manter": *"Ao criar ou alterar
// hook em `.claude/hooks/`: atualizar a seção correspondente"*), o path real. Todo
// porte .ps1→.mjs que esquece o registry deixa a linha apontando pra arquivo que não
// existe: o time lê o canon e vai no vazio — vetor "drift entre prod e git canônico"
// da REGRA PRIMÁRIA (proibicoes.md). Medido 2026-07-17: 4 refs mortas em main (2
// portes, PRs #4028/#4035), consertadas à mão no #4416 — o conserto não impede a
// reincidência, este check impede.
//
// POR QUE NÃO É REDUNDANTE COM O CHECK V (lápide §5 2026-07-09 — gate redundante com
// régua consolidada): o V só enxerga link markdown `](path)`; estas refs vivem em
// code-span. MEDIDO (não presumido): na mesma árvore o V acusava 29 links quebrados e
// ZERO era .claude/**. Mesmo defeito (alvo inexistente), outro extrator, MESMO dono
// (memory-health) — extensão, não régua paralela.
//
// É EXISTÊNCIA, não presença: pergunta "o alvo existe em disco?", nunca "o doc foi
// tocado no diff" (presence-gate — lápide §5 2026-07-01).
//
// FILTROS medidos, não adivinhados (2026-07-17: 71 refs concretas · 35 casos de prosa):
// glob/placeholder/template = prosa (a própria AUTOMATIONS.md cita `.claude/worktrees/*`);
// sem-extensão = diretório; gitignored = artefato de runtime (`.claude/run/`, .gitignore:107).
// Sem eles o check nasceria com falso-positivo — a doença do guard `@scope` e do
// allowlist-de-pasta (§5 2026-06-30/07-09): critério sintático que bloqueia o legítimo.
//
// SEVERIDADE espelha o Check G (workflow-fora-do-registry 🔴 · entrada-órfã 🟡):
// no registry = 🔴 (contrato explícito + é o que o time consome); no resto da canon
// front-facing = 🟡 (prosa de referência, contrato frouxo). Nasce 🔴-verde: 0 refs
// mortas no registry em main pós-#4416 — sem baseline/grandfather.
const REGISTRY_DOC = 'memory/governance/AUTOMATIONS.md';
function isGitIgnored(p) {
  try {
    execSync(`git check-ignore -q "${p}"`, { stdio: 'ignore' }); // exit 0 = ignorado
    return true;
  } catch { return false; } // exit 1 = não-ignorado · sem git (sandbox) = fail-open
}
function checkRegistryRefViva() {
  const files = [...listFiles('memory', (p) => p.endsWith('.md')), 'README.md', 'CLAUDE.md', 'DESIGN.md'].filter(LINK_CANON);
  const noRegistry = [], naCanon = [];
  for (const rel of files) {
    let txt = ''; try { txt = read(rel); } catch { continue; }
    txt.split('\n').forEach((line, i) => {
      for (const m of line.matchAll(/`(\.claude\/[^`\s]*)`/g)) {
        const ref = m[1].trim();
        if (/[*?]/.test(ref)) continue;             // glob = padrão em prosa
        if (/[<>]/.test(ref)) continue;             // <nome> = placeholder
        if (/NNNN|YYYY/.test(ref)) continue;        // template
        if (!/\.[a-z0-9]+$/i.test(ref)) continue;   // sem extensão = diretório
        if (existsSync(join(ROOT, ref))) continue;  // alvo vivo
        if (isGitIgnored(ref)) continue;            // artefato de runtime, não versionado
        (rel === REGISTRY_DOC ? noRegistry : naCanon).push({ file: rel, linha: i + 1, ref });
      }
    });
  }
  if (noRegistry.length) {
    fails.push({ check: 'P', kind: 'registry-ref-morta', count: noRegistry.length, sample: noRegistry.slice(0, 10),
      msg: `${REGISTRY_DOC} cita ${noRegistry.length} path(s) de automação que NÃO existe(m) em disco — o registry é indexado pelo MCP e serve o time como fonte de onde mora cada automação; apontar pra arquivo morto serve dado errado. Atualize a coluna "Arquivo" no MESMO PR que portar/renomear/apagar o hook (§"Como manter" do próprio doc).` });
  }
  if (naCanon.length) {
    warns.push({ check: 'P', kind: 'canon-ref-morta', count: naCanon.length, sample: naCanon.slice(0, 15),
      msg: `${naCanon.length} ref(s) .claude/** em canon front-facing apontando pra alvo inexistente (hook/skill/agent portado, renomeado ou apagado) — re-apontar pro path real. 🟡 sentinela: o 🔴 é só no registry (${REGISTRY_DOC}).` });
  }
}

// ── Check W: índice de BACKLOG gerado stale vs SPEC.md ──────────────────────
// _BACKLOG-GENERATED.md é GERADO de memory/requisitos/**/SPEC.md (tasks-index-
// generate, ADR 0070 — o índice nunca à mão). Se um SPEC mudou DEPOIS do índice,
// ele drifta. Sinal barato e determinístico: git-date do índice < git-date do SPEC
// mais novo. Advisory (regen: node scripts/governance/tasks-index-generate.mjs --write).
// Fecha o gap "backlog gerado não-enforçado" (auditoria 2026-07-04). Par do adr-index-gate.
function checkBacklogIndexStale() {
  const idx = 'memory/requisitos/_BACKLOG-GENERATED.md';
  const idxDate = gitLastDate(idx);
  if (!idxDate) return; // não existe = ignora (não inventa)
  let newest = '', newestFile = '';
  for (const s of listFiles('memory/requisitos', (p) => /\/SPEC\.md$/.test(p))) {
    const d = gitLastDate(s);
    if (d && d > newest) { newest = d; newestFile = s; }
  }
  if (newest && newest > idxDate) {
    warns.push({ check: 'W', kind: 'backlog-index-stale', count: 1,
      sample: [{ indice: idxDate, spec_mais_novo: newest, file: newestFile }],
      msg: `_BACKLOG-GENERATED.md (${idxDate}) mais antigo que o SPEC mais novo (${newest} · ${newestFile}) — regenerar: \`node scripts/governance/tasks-index-generate.mjs --write\`. 🟡 sentinela.` });
  }
}

// ── Check E: drift de enum status/lifecycle em ADR ──────────────────────────
// Enums canônicos do scripts/memory-schemas/adr.schema.json. Append-only bloqueia
// editar ADR ratificada in-place — então normalizar é no leitor OU override
// consciente; este check só IMPEDE PIORAR (flagga grafia/enum novo).
const STATUS_OK = new Set(['rascunho', 'proposto', 'aceito', 'recusado', 'deprecated', 'superseded']);
const LIFECYCLE_OK = new Set(['ativo', 'arquivado', 'substituido', 'historical']);
function checkAdrEnumDrift() {
  const dir = 'memory/decisions';
  if (!exists(dir)) return;
  const badStatus = {}, badLifecycle = {};
  let n = 0;
  for (const f of readdirSync(join(ROOT, dir))) {
    if (!/^\d{4}-.+\.md$/.test(f)) continue;
    const txt = read(`${dir}/${f}`);
    const st = (txt.match(/^status:\s*["']?([^\s"'#]+)/m) || [])[1]?.toLowerCase();
    const lc = (txt.match(/^lifecycle:\s*["']?([^\s"'#]+)/m) || [])[1]?.toLowerCase();
    if (st && !STATUS_OK.has(st)) { badStatus[st] = (badStatus[st] || 0) + 1; n++; }
    if (lc && !LIFECYCLE_OK.has(lc)) { badLifecycle[lc] = (badLifecycle[lc] || 0) + 1; n++; }
  }
  if (n > 0) {
    warns.push({ check: 'E', kind: 'adr-enum-drift', count: n,
      sample: [{ status_invalido: badStatus }, { lifecycle_invalido: badLifecycle }],
      msg: `${n} ADR(s) com status/lifecycle fora do enum canônico (adr.schema.json). Normalizar (accepted→aceito, active→ativo; canon/feature_wish não são lifecycle válidos). Append-only bloqueia editar in-place: normalizar no leitor (decisions-search) ou override consciente.` });
  }
}

// ── Check F: anti-ressurreição da auto-mem (GAP 3, ADR 0258/0061) ───────────
// O legado memory/claude/ foi purgado e o cron memcofre:sync-memories desativado
// (PR #2383). Este invariante IMPEDE a volta: se o dir reaparecer OU o schedule
// for re-ativado (linha não-comentada), 🔴 fail. Mata a classe "rebaixei/apaguei
// e voltou" de raiz.
function checkAntiResurrection() {
  if (exists('memory/claude')) {
    fails.push({ check: 'F', kind: 'automem-ressuscitou',
      msg: `memory/claude/ REAPARECEU — auto-mem legada (ADR 0061 proíbe). Apague + investigue o que recriou (cron memcofre? sync manual?).` });
  }
  const kernel = 'app/Console/Kernel.php';
  if (exists(kernel)) {
    const active = read(kernel).split('\n').some((l) => /^\s*\$schedule->command\(\s*['"]memcofre:sync-memories['"]/.test(l));
    if (active) {
      fails.push({ check: 'F', kind: 'cron-automem-reativado',
        msg: `cron memcofre:sync-memories foi RE-ATIVADO no Kernel.php (linha não-comentada) — era a fonte do vazamento/ressurreição (ADR 0258). Só volta via ADR que reverta o 0061.` });
    }
  }
}

// ── Check G: registry canônico de gates (Onda Q5 — o processo se autocobra) ─
// "Regra que ninguém cobra morre" — gate novo entrava em .github/workflows sem
// censo nenhum. TODO workflow DEVE estar em scripts/governance/gates-registry.json
// (nome + classe + propósito). Workflow fora do registry = 🔴 (pega gate novo
// mecanicamente); entrada órfã (workflow apagado) = 🟡.
function checkGatesRegistry() {
  const REGISTRY = 'scripts/governance/gates-registry.json';
  const wfDir = '.github/workflows';
  if (!exists(wfDir)) return;
  if (!exists(REGISTRY)) {
    fails.push({ check: 'G', kind: 'registry-ausente',
      msg: `${REGISTRY} não existe — o censo de gates é obrigatório (Onda Q5). Recriar a partir do main.` });
    return;
  }
  let reg;
  try { reg = JSON.parse(read(REGISTRY)).workflows || {}; } catch {
    fails.push({ check: 'G', kind: 'registry-ilegivel', msg: `${REGISTRY} não parseia como JSON.` });
    return;
  }
  const files = readdirSync(join(ROOT, wfDir)).filter((f) => f.endsWith('.yml') || f.endsWith('.yaml'));
  const fora = files.filter((f) => !(f in reg));
  if (fora.length) {
    fails.push({ check: 'G', kind: 'workflow-fora-do-registry', count: fora.length,
      msg: `workflow(s) NOVO(s) sem registro no censo de gates (${REGISTRY}): ${fora.join(', ')} — registre nome+classe+propósito no MESMO PR.` });
  }
  const orfas = Object.keys(reg).filter((f) => !files.includes(f));
  if (orfas.length) {
    warns.push({ check: 'G', kind: 'registry-entrada-orfa', count: orfas.length,
      msg: `entrada(s) do registry sem workflow correspondente: ${orfas.join(', ')} — remova do censo.` });
  }
}

// ── Check M: teto de governança (anti-proliferação de gates · ADR 0298) ─────
// "A torneira, não o balde": poda manual não vence a taxa de criação (sessão
// 2026-06-22 — removidos 7 workflows, outras sessões criaram ~10 em 24h; contador
// 81→85 APESAR da poda). Workflow NOVO (fora do baseline grandfather) DEVE declarar
// no registry: `terminal` (required|cron|automacao|advisory) + `anchor` (ADR/incidente/
// PR de custo); se advisory, `promote_by` (data — o vencimento ≤14d é cobrado pelo
// ZELADOR, não aqui, pra manter o check determinístico). Os gates pré-existentes ficam
// isentos (baseline checkM, igual ao ratchet dos Checks C/L). ADR 0105 aplicado a gates.
const TERMINAL_VALIDO = new Set(['required', 'cron', 'automacao', 'advisory']);
function checkGovernanceCeiling() {
  const REGISTRY = 'scripts/governance/gates-registry.json';
  if (!exists(REGISTRY)) return; // Check G já trata ausência
  let reg;
  try { reg = JSON.parse(read(REGISTRY)).workflows || {}; } catch { return; } // Check G já trata ilegível
  checkMKeys = Object.keys(reg);
  if (UPDATE_BASELINE) return; // no modo update só capturamos as keys atuais
  const grandfathered = new Set(baseline.checkM || []);
  const violacoes = [];
  for (const [wf, meta] of Object.entries(reg)) {
    if (grandfathered.has(wf)) continue; // gate pré-existente — isento (ratchet)
    const t = String(meta.terminal || '').trim().toLowerCase();
    const faltas = [];
    if (!TERMINAL_VALIDO.has(t)) faltas.push(`terminal∈{required,cron,automacao,advisory} (tem: ${meta.terminal ?? '—'})`);
    if (!meta.anchor || !String(meta.anchor).trim()) faltas.push('anchor (ADR/incidente/PR de custo)');
    if (t === 'advisory' && (!meta.promote_by || !String(meta.promote_by).trim())) faltas.push('promote_by (advisory não nasce eterno — ADR 0275 §5)');
    if (faltas.length) violacoes.push(`${wf}: faltam [${faltas.join(' · ')}]`);
  }
  if (violacoes.length) {
    fails.push({ check: 'M', kind: 'gate-novo-sem-teto', count: violacoes.length, sample: violacoes.slice(0, 15),
      msg: `workflow(s) NOVO(s) sem o teto de governança (ADR 0298): todo gate novo declara terminal+anchor no gates-registry (advisory exige promote_by). "A torneira, não o balde" — só nasce gate com fim-de-vida e sinal de custo. Preencha os campos, ou rode --update-baseline se for grandfather consciente.` });
  }
}

// ── Check H: frescor de doc-cache "✓lido @main <data>" (Onda Q5) ────────────
// Censos/tabelas derivadas carregam carimbo de leitura contra o main. Carimbo
// >14 dias = a "verdade cacheada" provavelmente driftou → 🟡 revalidar.
function checkLidoFreshness() {
  const LIMIT_DAYS = 14;
  const stamps = [];
  for (const dir of ['memory', 'prototipo-ui']) {
    for (const f of listFiles(dir, (p) => p.endsWith('.md'))) {
      let content; try { content = read(f); } catch { continue; }
      for (const m of content.matchAll(/✓\s*lido\s*@?main[^\d]{0,20}(\d{4}-\d{2}-\d{2})/gi)) {
        stamps.push({ file: f, date: m[1] });
      }
    }
  }
  const today = new Date();
  const old = stamps.filter((s) => (today - new Date(s.date)) / 86400000 > LIMIT_DAYS);
  if (old.length) {
    const sample = old.slice(0, 5).map((s) => `${s.file} (${s.date})`).join(' · ');
    warns.push({ check: 'H', kind: 'doc-cache-stale', count: old.length,
      msg: `carimbo(s) "✓lido @main" com mais de ${LIMIT_DAYS} dias: ${sample}${old.length > 5 ? ` … +${old.length - 5}` : ''} — revalidar contra o main e re-carimbar.` });
  }
}

// ── Check I: lição sem asserção (Onda Q5) ───────────────────────────────────
// Lição em memory/LICOES_CC.md que não aponta gate/G#/IT# nem se declara
// `não-mecanizável:` é lição que vai morrer no tempo (DESIGN.md §16.2 provou).
function checkLicaoSemAssercao() {
  const FILE = 'memory/LICOES_CC.md';
  if (!exists(FILE)) return;
  const content = read(FILE);
  const blocks = content.split(/^## (?=L-\d)/m).slice(1);
  const sem = [];
  for (const b of blocks) {
    const id = (b.match(/^L-\d+[a-z]?/) || ['?'])[0];
    if (!/\bG-?\d|\bIT-?\d|gate|guard|ratchet|catraca|não-mecanizável\s*:|nao-mecanizavel\s*:/i.test(b)) sem.push(id);
  }
  if (sem.length) {
    warns.push({ check: 'I', kind: 'licao-sem-assercao', count: sem.length,
      msg: `lição(ões) sem gate/G#/IT# nem marcador \`não-mecanizável:\`: ${sem.slice(0, 8).join(', ')}${sem.length > 8 ? ` … +${sem.length - 8}` : ''} — toda lição aponta o check que a mecaniza OU se declara não-mecanizável.` });
  }
}

// ── Check J: plan-health (ADR 0294 — planos vivos) ─────────────────────────
// Espelha a catraca/sentinela do ADR 0256 apontada pra PLANOs. Warn-only (advisory):
// plano sem `## Status vivo`, sem reviewed_at / stale (>30d), status fora do enum, ou
// `em-execução` sem `parent_plan` (a membrana — task MCP). NUNCA bloqueia (só sinaliza).
const PLAN_STATUS_OK = new Set(['proposto', 'ativo', 'em-execução', 'em-execucao', 'pausado', 'concluído', 'concluido', 'abandonado', 'superseded', 'revisar']);
const PLAN_STALE_DAYS = 30;
function checkPlanHealth() {
  const base = 'memory/requisitos';
  if (!exists(base)) return;
  const isPlan = (rel) => rel.endsWith('.md')
    && /plan/i.test(rel.split('/').pop())
    && !/PLANS-INDEX|_TEMPLATE/i.test(rel)
    && !/\/(adr|arq)\//.test(rel);
  const files = listFiles(base, isPlan);
  if (!files.length) return;
  const today = new Date(gitLastDate('.') || '2026-06-20');
  const cutoff = new Date(today); cutoff.setDate(cutoff.getDate() - PLAN_STALE_DAYS);
  const cutoffStr = cutoff.toISOString().slice(0, 10);
  const issues = [];
  for (const rel of files) {
    let txt; try { txt = read(rel); } catch { continue; }
    if (!/\n##\s*Status vivo/i.test(txt)) { issues.push(`${rel}: sem bloco \`## Status vivo\` (ADR 0294)`); continue; }
    const block = (txt.split(/\n##\s*Status vivo/i)[1] || '').split(/\n##\s/)[0];
    const status = (block.match(/(?:^|\n)[-*\s]*\**status:\**\s*([^\s<·*\n]+)/i) || [])[1]?.toLowerCase();
    const rev = (block.match(/reviewed[_ -]?at:?\**\s*["']?(\d{4}-\d{2}-\d{2})/i) || [])[1];
    const hasParent = /parent_plan\s*[=:]\s*[a-z0-9-]+/i.test(block);
    if (!status) issues.push(`${rel}: Status vivo sem \`status\``);
    else if (!PLAN_STATUS_OK.has(status)) issues.push(`${rel}: status "${status}" fora do enum (ADR 0294)`);
    if (!rev) issues.push(`${rel}: Status vivo sem \`reviewed_at\``);
    else if (rev < cutoffStr) issues.push(`${rel}: reviewed_at ${rev} > ${PLAN_STALE_DAYS}d — revisar + bump`);
    if ((status === 'em-execução' || status === 'em-execucao') && !hasParent) issues.push(`${rel}: \`em-execução\` sem \`parent_plan\` (membrana ADR 0294)`);
  }
  if (issues.length) {
    warns.push({ check: 'J', kind: 'plan-health', count: issues.length, sample: issues.slice(0, 12),
      msg: `${issues.length} achado(s) de plano-vivo (ADR 0294): plano sem \`## Status vivo\` / \`reviewed_at\` stale / \`em-execução\` órfão. Edita o plano no lugar + bump reviewed_at (fonte única).` });
  }
}

// ── Check K: decisão em session log sem âncora (detector dos "155 perdidos") ─
// Adversário 2026-06-20 (memory/sessions/2026-06-20-adversario-convergencia-sistema.md):
// decisão/rollout escrito num session log que NUNCA virou ADR aceito nem entrou num
// BRIEFING "se perde" — converge só pela atenção manual. Este check flagga session log
// >30d com marcador de decisão (`## Decisão`, `US-`, `rollout`, `### Passo`) que NÃO
// referencia nenhum ADR ACEITO nem um BRIEFING. Warn-only (advisory): é fila de triagem,
// não bloqueio — promover a ADR/BRIEFING OU registrar resolução. Complementa Check J
// (que cuida de PLANOs vivos, ADR 0294); aqui o alvo é o session log histórico.
const SESSION_DECISION_STALE_DAYS = 30;
function acceptedAdrNums() {
  const dir = 'memory/decisions';
  const set = new Set();
  if (!exists(dir)) return set;
  for (const f of readdirSync(join(ROOT, dir))) {
    const m = f.match(/^(\d{4})-.+\.md$/);
    if (!m) continue;
    let txt; try { txt = read(`${dir}/${f}`); } catch { continue; }
    const st = (txt.match(/^status:\s*["']?([^\s"'#]+)/mi) || [])[1]?.toLowerCase();
    if (st && /^(aceito|accepted|aceita)/.test(st)) set.add(m[1]);
  }
  return set;
}
function checkSessionDecisionAnchor() {
  const dir = 'memory/sessions';
  if (!exists(dir)) return;
  const accepted = acceptedAdrNums();
  const today = new Date(gitLastDate('.') || '2026-06-20'); // determinismo CI (sem Date.now)
  const cutoff = new Date(today); cutoff.setDate(cutoff.getDate() - SESSION_DECISION_STALE_DAYS);
  const cutoffStr = cutoff.toISOString().slice(0, 10);
  const files = listFiles(dir, (rel) => rel.endsWith('.md') && !/_TEMPLATE|README/i.test(rel));
  const lost = [];
  for (const rel of files) {
    let txt; try { txt = read(rel); } catch { continue; }
    // marcador de decisão/rollout (lista do adversário 2026-06-20)
    const hasDecision = /^##\s*Decis[aã]o\b/im.test(txt)
      || /^###\s*Passo\b/im.test(txt)
      || /\bUS-[A-Z0-9]{2,}/.test(txt)
      || /\brollout\b/i.test(txt);
    if (!hasDecision) continue;
    // idade: nome do arquivo `YYYY-MM-DD-…` é a fonte PRIMÁRIA — ~50% dos logs não têm
    // `date:` no frontmatter, e `gitLastDate` "rejuvenesce" o doc no touch em massa
    // (mascarava ~46 logs antigos como recentes). Ordem: slug → frontmatter `date:` → git.
    const when = (rel.match(/(?:^|\/)(\d{4}-\d{2}-\d{2})-/) || [])[1]
      || (txt.match(/^date:\s*["']?(\d{4}-\d{2}-\d{2})/mi) || [])[1]
      || gitLastDate(rel);
    if (!when || when >= cutoffStr) continue; // só >30d
    // âncora ESTRUTURAL (não menção solta em prosa, que premiava name-dropping):
    //   ADR aceito referenciado no FRONTMATTER (related_adrs/supersedes/superseded_by)
    //   OU link `decisions/NNNN-…` no corpo  ·  BRIEFING pelo arquivo real (BRIEFING.md).
    const fmEnd = txt.startsWith('---') ? txt.indexOf('\n---', 3) : -1;
    const fm = fmEnd === -1 ? '' : txt.slice(0, fmEnd);
    const refs = new Set();
    for (const m of fm.matchAll(/\b(\d{4})-[a-z]{2,}/g)) refs.add(m[1]);         // slug em related_adrs/supersedes
    for (const m of txt.matchAll(/decisions\/(\d{4})-[a-z]/gi)) refs.add(m[1]);  // link pro arquivo do ADR
    const anchoredByAdr = [...refs].some((n) => accepted.has(n));
    const anchoredByBriefing = /BRIEFING\.md/i.test(txt);
    if (!anchoredByAdr && !anchoredByBriefing) lost.push(`${rel} (${when})`);
  }
  if (lost.length) {
    warns.push({ check: 'K', kind: 'session-decisao-sem-ancora', count: lost.length, sample: lost.slice(0, 12),
      msg: `${lost.length} session log(s) >${SESSION_DECISION_STALE_DAYS}d com marcador de decisão (\`## Decisão\`/\`US-\`/\`rollout\`/\`### Passo\`) SEM âncora ESTRUTURAL (related_adrs/link decisions pra ADR aceito, ou BRIEFING.md) — os "planos perdidos" (adversário 2026-06-20). Triagem: promover a ADR/BRIEFING ou registrar resolução.` });
  }
}

// ── Check L: ADR vivo-mas-proposto (proposto vs realizado) ──────────────────
// "Declarado ≠ realizado": ADR com status proposto/rascunho cujo NÚMERO já é citado
// por código que RODA (scripts/** ou .github/workflows/**) — o processo depende da
// decisão, mas a metadata diz que ela não foi aceita. Ratchet (como Check C): os
// offenders conhecidos ficam no baseline (.checkL); só ADR NOVO vivo-mas-proposto
// acima do baseline 🔴 falha. Ratificar (proposto→aceito) tira do offender list
// sozinho — o débito encolhe à vista. É o teste de integridade do proposto vs
// realizado pedido por Wagner (2026-06-21). Refs: ADR 0256/0258.
const UNRATIFIED_STATUS = new Set(['proposto', 'rascunho', 'proposed', 'draft']);
function checkAdrVivoMasProposto() {
  const dir = 'memory/decisions';
  if (!exists(dir)) return;
  // corpus = "código que roda". NÃO inclui memory/** (lá é doc, não execução) nem o
  // próprio baseline (senão o grandfather vira citação circular auto-confirmante).
  const isCode = (rel) => /\.(mjs|js|ts|php|json)$/.test(rel) && !rel.includes('.memory-health-baseline');
  const corpusFiles = [
    ...listFiles('scripts', isCode),
    ...(exists('.github/workflows') ? listFiles('.github/workflows', (p) => /\.ya?ml$/.test(p)) : []),
  ];
  let corpus = '';
  for (const f of corpusFiles) { try { corpus += '\n' + read(f); } catch {} }
  for (const f of readdirSync(join(ROOT, dir))) {
    const m = f.match(/^(\d{4})-.+\.md$/);
    if (!m) continue;
    const num = m[1];
    let txt; try { txt = read(`${dir}/${f}`); } catch { continue; }
    const st = (txt.match(/^status:\s*["']?([^\s"'#]+)/mi) || [])[1]?.toLowerCase();
    if (!st || !UNRATIFIED_STATUS.has(st)) continue;
    // citado como dependência viva: "ADR 0256" · "0256-slug" · "decisions/0256-"
    const cited = new RegExp(`(ADR[ _-]?${num}\\b|\\b${num}-[a-z]|decisions/${num}-)`, 'i').test(corpus);
    if (cited) checkLSlugs.push(f.replace(/\.md$/, ''));
  }
  if (UPDATE_BASELINE) return; // no modo update só capturamos; nada de fail
  const grandfathered = new Set(baseline.checkL || []);
  const novos = checkLSlugs.filter((slug) => !grandfathered.has(slug));
  if (novos.length) {
    fails.push({ check: 'L', kind: 'adr-vivo-mas-proposto', count: novos.length, sample: novos.slice(0, 15),
      msg: `ADR(s) com status proposto/rascunho mas JÁ citado(s) por código que roda (scripts/** ou .github/workflows/**) — "proposto vs realizado": o processo já depende da decisão mas a metadata diz que não foi aceita. Ratifique (proposto→aceito via PR) ou corte a dependência. Se legítimo, rode --update-baseline. (ADR 0256 Check L · Wagner 2026-06-21)` });
  }
}

// ── Check O: morta-mas-canon (ADR 0317 §1 · classe INCONSISTÊNCIA) ──────────
// ADR marcada MORTA (superseded/deprecated OU lifecycle substituido/arquivado) E
// ainda citada como ADR canônica numa FONTE-DE-VERDADE VIVA e curada (primer
// CLAUDE.md + @imports, BRIEFING.md, SPEC.md) — o padrão "0035" (rótulo mente:
// a base segue canon mas a metadata diz morta). NÃO ref-count bruto em memory/**
// (o cético provou 11 falsos no dia 1 — fundacionais têm 45 refs e são superseded
// de verdade); só o corpus curado, e só citação ESTRUTURAL (link/ADR NNNN), não
// 4-dígitos solto. 🟡 sentinela (warn, NUNCA bloqueia): a máquina só enfileira; o
// relabel (0257 supersedes_partially) é humano+adversarial (invariante Tier 0 do
// 0317). Ratchet só-encolhe (.checkO, padrão Checks C/L/M/N): citação histórica
// legítima grandfatherada via --update-baseline; ratificar/relabelar tira sozinho.
const DEAD_STATUS_O = new Set(['superseded', 'deprecated']);
const DEAD_LIFECYCLE_O = new Set(['substituido', 'arquivado']);
function checkMortaMasCanon() {
  const dir = 'memory/decisions';
  if (!exists(dir)) return;
  // Fontes-de-verdade VIVAS: citam só o que é canon corrente (curadas à mão).
  const truthFiles = [
    'CLAUDE.md',
    'memory/what-oimpresso.md', 'memory/why-oimpresso.md',
    'memory/how-trabalhar.md', 'memory/proibicoes.md', 'memory/regras-time.md',
    ...(exists('memory/requisitos') ? listFiles('memory/requisitos', (p) => /\/(BRIEFING|SPEC)\.md$/.test(p)) : []),
  ];
  // Corpus VIVO = só o CORPO (frontmatter `related_adrs`/`supersedes` é relação, não
  // "isto é canon") E só LINHAS que NÃO estão falando da MORTE da ADR (nega o
  // auto-flag "0079 virou superseded" que cita 0079). Reduz o ruído histórico dos
  // SPEC/BRIEFING sem perder o padrão "listada como stack canônica" (0035).
  const stripFm = (t) => { if (t.startsWith('---')) { const e = t.indexOf('\n---', 3); if (e !== -1) return t.slice(e + 4); } return t; };
  const CANON_NEG = /supersed|substitu|deprecat|\bantig|hist[oó]ri|\bmorta|revogad|obsolet|descontinuad|aposentad|removid/i;
  let truth = '';
  for (const f of truthFiles) {
    let t; try { t = stripFm(read(f)); } catch { continue; }
    for (const ln of t.split('\n')) if (!CANON_NEG.test(ln)) truth += '\n' + ln;
  }
  for (const f of readdirSync(join(ROOT, dir))) {
    const m = f.match(/^(\d{4})-.+\.md$/);
    if (!m) continue;
    const num = m[1];
    let txt; try { txt = read(`${dir}/${f}`); } catch { continue; }
    const st = (txt.match(/^status:\s*["']?([^\s"'#]+)/mi) || [])[1]?.toLowerCase();
    const lc = (txt.match(/^lifecycle:\s*["']?([^\s"'#]+)/mi) || [])[1]?.toLowerCase();
    // `status: aceito` NUNCA é morta (decisão aceita segue de pé mesmo se lifecycle:arquivado)
    // — senão flagga falso-positivo por construção (aceito+arquivado ≠ substituído).
    if (st && /^(aceito|accepted|aceita)/.test(st)) continue;
    if (!((st && DEAD_STATUS_O.has(st)) || (lc && DEAD_LIFECYCLE_O.has(lc)))) continue;
    // Citação ESTRUTURAL como ADR (não 4-dígitos solto): "ADR 0035" · "decisions/0035-" · "[…0035-slug…]".
    const cited = new RegExp(`(ADR[ _-]?${num}\\b|decisions/${num}-[a-z]|\\b${num}-[a-z]{2,})`, 'i').test(truth);
    if (cited) checkOSlugs.push(f.replace(/\.md$/, ''));
  }
  if (UPDATE_BASELINE) return; // no modo update só capturamos; nada de warn
  const grandfathered = new Set(baseline.checkO || []);
  const novos = checkOSlugs.filter((slug) => !grandfathered.has(slug));
  if (novos.length) {
    warns.push({ check: 'O', kind: 'morta-mas-canon', count: novos.length, sample: novos.slice(0, 12),
      msg: `ADR(s) marcada(s) MORTA(s) (superseded/deprecated/substituido/arquivado) mas ainda citada(s) como ADR canônica numa fonte-de-verdade VIVA (CLAUDE.md/what-oimpresso/BRIEFING/SPEC) — o padrão "morta-mas-canon" (ADR 0317 §1). Triagem: relabel via 0257 (\`supersedes_partially\` se é emenda, não morte) OU --update-baseline se a citação for histórica legítima. 🟡 sentinela — não bloqueia.` });
  }
}

// ── Check R: revisão vencida por meia-vida (ADR 0317 §1 · classe TEMPO) ──────
// Rede de segurança temporal: ADR VIVA cujo `decided_at + TTL(kind)` já passou —
// ninguém garante que a decisão ainda vale. De `decided_at` (IMUTÁVEL), NUNCA
// git-mtime (o Check K provou que touch-em-massa "rejuvenesce" a git-date). Isenta
// kind:meta + lifecycle:historical (∞) + ADR já morta (superseded/substituido/recusada:
// resolvida, não precisa revisão). 🟡 sentinela. Ratchet só-encolhe (.checkR): revisão
// feita = grandfather via --update-baseline (decided_at é append-only → a ADR não
// "des-vence" sozinha; o grandfather É o registro de "revisei, segue de pé").
const TTL_DAYS = { proposto: 30, rascunho: 30, errata: 180, 'feature-wish': 180 };
const REVIEW_EXEMPT_LC = new Set(['historical', 'substituido', 'arquivado']);
const REVIEW_EXEMPT_ST = new Set(['superseded', 'deprecated', 'recusado']);
function checkStaleReview() {
  const dir = 'memory/decisions';
  if (!exists(dir)) return;
  const today = new Date(gitLastDate('.') || '2026-06-20'); // determinismo CI (sem Date.now)
  for (const f of readdirSync(join(ROOT, dir))) {
    if (!/^\d{4}-.+\.md$/.test(f)) continue;
    let txt; try { txt = read(`${dir}/${f}`); } catch { continue; }
    const fmEnd = txt.startsWith('---') ? txt.indexOf('\n---', 3) : -1;
    const fm = fmEnd === -1 ? '' : txt.slice(0, fmEnd);
    const st = (fm.match(/^status:\s*["']?([^\s"'#]+)/mi) || [])[1]?.toLowerCase();
    const lc = (fm.match(/^lifecycle:\s*["']?([^\s"'#]+)/mi) || [])[1]?.toLowerCase();
    const kind = (fm.match(/^kind:\s*["']?([^\s"'#]+)/mi) || [])[1]?.toLowerCase() || 'decision';
    if (kind === 'meta') continue;
    if (lc && REVIEW_EXEMPT_LC.has(lc)) continue;
    if (st && REVIEW_EXEMPT_ST.has(st)) continue;
    const decided = (fm.match(/^decided_at:\s*["']?(\d{4}-\d{2}-\d{2})/mi) || [])[1];
    if (!decided) continue; // sem data imutável → não computa (não inventa git-mtime)
    // proposto/rascunho 30 · errata/feature-wish 180 · decisão 270 (arquitetura interna).
    // O tier 90d "decisão-toca-dependência-externa" (ADR 0317) precisa de um sinal
    // explícito (campo/tag) que ainda não existe — deferido; default decisão = 270.
    const ttl = TTL_DAYS[st] || TTL_DAYS[kind] || 270;
    if ((today - new Date(decided)) / 86400000 > ttl) checkRSlugs.push(f.replace(/\.md$/, ''));
  }
  if (UPDATE_BASELINE) return; // no modo update só capturamos; nada de warn
  const grandfathered = new Set(baseline.checkR || []);
  const novos = checkRSlugs.filter((slug) => !grandfathered.has(slug));
  if (novos.length) {
    warns.push({ check: 'R', kind: 'revisao-vencida', count: novos.length, sample: novos.slice(0, 12),
      msg: `ADR(s) VIVA(s) com revisão VENCIDA por meia-vida (decided_at + TTL(kind) já passou — proposto/rascunho 30d · errata/feature-wish 180d · decisão 270d): a decisão pode ter drifado do mundo e ninguém revalidou. Triagem: revisar → ratificar/emendar/aposentar, OU --update-baseline se ainda vale (ADR 0317 §1 classe TEMPO). 🟡 sentinela — não bloqueia.` });
  }
}

// ── Check X: cobertura de auditoria (módulo Tier-0 / nota-baixa sem doc de audit) ──
// Responde mecanicamente "isso está auditado?" a cada PR. Determinístico, zero-FP por
// construção (o doc de auditoria existe no dir do módulo ou não). Um módulo QUALIFICA
// pra auditoria profunda se é Tier-0 (toca dinheiro/estoque/fiscal/tenant) OU tem nota
// module-grade < FLOOR. Se qualifica e NÃO tem nenhum `AUDIT*.md`/`AUDITORIA*.md` no
// seu dir de requisitos → 🟡 gap de cobertura. Advisory (nasce advisory — ADR 0271/0275).
// Fonte-de-verdade: governance/module-grades-baseline.json (a mesma do module-grades-gate).
// Ref: memory/requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md (Onda 2/3) · ADR 0155 · ADR 0258.
const AUDIT_TIER0 = new Set(['Compras', 'PaymentGateway', 'Financeiro', 'Fiscal', 'NfeBrasil', 'RecurringBilling']);
const AUDIT_GRADE_FLOOR = 70;
function checkAuditCoverage() {
  const gradesFile = 'governance/module-grades-baseline.json';
  if (!exists(gradesFile)) return; // sem fonte-de-verdade → não inventa (temp-dir safe)
  let grades;
  try { grades = JSON.parse(read(gradesFile)).modules || {}; } catch { return; }
  // Aceita audit no topo (`AUDIT*.md`/`AUDITORIA*.md`) OU numa subpasta `audits/` com
  // qualquer `.md` (o padrão real do repo: NfeBrasil/RecurringBilling têm audits/YYYY-MM-DD.md).
  const hasAuditDoc = (mod) => {
    const dir = `memory/requisitos/${mod}`;
    if (!exists(dir)) return false;
    const hits = listFiles(dir, (rel) =>
      rel.endsWith('.md') && (/\/audits\//i.test(rel) || /\/audit[^/]*\.md$/i.test(rel)));
    return hits.length > 0;
  };
  const gaps = [];
  for (const [mod, grade] of Object.entries(grades)) {
    if (typeof grade !== 'number') continue;
    const tier0 = AUDIT_TIER0.has(mod);
    const low = grade < AUDIT_GRADE_FLOOR;
    if (!tier0 && !low) continue; // não qualifica pra auditoria profunda
    if (hasAuditDoc(mod)) continue; // já tem lente
    gaps.push(`${mod} (nota ${grade}${tier0 ? ' · Tier-0' : ''}) — sem AUDIT*.md em memory/requisitos/${mod}/`);
  }
  if (gaps.length) {
    warns.push({ check: 'X', kind: 'audit-coverage-gap', count: gaps.length, sample: gaps.slice(0, 12),
      msg: `${gaps.length} módulo(s) que QUALIFICAM pra auditoria profunda (Tier-0 OU nota < ${AUDIT_GRADE_FLOOR}) sem NENHUM doc de auditoria no dir. Cobrir via PLANO-APROFUNDAMENTO-AVALIACOES.md (Onda 2/3). 🟡 sentinela — não bloqueia.` });
  }
}

// ── Check Y: watchdog do metabolismo MV (vital-signs stale = máquina morta em silêncio) ──
// Adversário 2026-07-06 V7: o sintoma de morte do mv-metabolismo.yml (cron não roda, PAT
// expira, peter-evans falha) é a AUSÊNCIA de PR de manhã — exatamente o que ninguém nota.
// Selftests dentro do workflow não protegem contra o workflow não rodar. Este check vigia
// de fora: se memory/governance/vital-signs.json tem generated_at > VITAL_STALE_DIAS atrás
// do último commit do repo, o batimento parou. Warn-only (sentinela, lei ADR 0314).
const VITAL_STALE_DIAS = 2;
function checkVitalSignsFreshness() {
  const f = 'memory/governance/vital-signs.json';
  // Ausente → return silencioso (temp-dir safe, padrão checkAuditCoverage: "sem
  // fonte-de-verdade → não inventa"). O gate-selftest roda este script em sandbox sem o
  // arquivo — warnar aqui mataria o "saudável" da fixture good. Limite honesto: deleção
  // do arquivo no repo real fica pro review do PR (o arquivo é versionado).
  if (!exists(f)) return;
  let gen;
  try { gen = JSON.parse(read(f)).generated_at; } catch { gen = null; }
  if (!gen || !/^\d{4}-\d{2}-\d{2}$/.test(gen)) {
    warns.push({ check: 'Y', kind: 'vital-signs-ilegivel',
      msg: `vital-signs.json sem generated_at legível ('${gen}') — snapshot corrompido. 🟡 sentinela.` });
    return;
  }
  // Relógio determinístico: último commit do repo (evita Date.now, padrão dos irmãos).
  const hoje = new Date(gitLastDate('.') || gen);
  const idade = Math.floor((hoje.getTime() - new Date(`${gen}T00:00:00Z`).getTime()) / 86_400_000);
  if (idade > VITAL_STALE_DIAS) {
    warns.push({ check: 'Y', kind: 'metabolismo-parado', count: idade,
      msg: `vital-signs.json com generated_at=${gen} (${idade}d atrás do último commit) — o batimento nightly do MV (mv-metabolismo.yml 06:30 BRT) pode ter parado em silêncio (cron/PAT/peter-evans). Checar gh run list --workflow mv-metabolismo.yml. 🟡 sentinela — não bloqueia.` });
  }
}

// ── Check Z: UC 🧪/⬜ envelhecendo (museu de intenções honestas) ─────────────────────────
// Adversário 2026-07-06 V3: o casos-gate G-7 só policia ✅ — 🧪/⬜ são "não-afirmações
// honestas" PARA SEMPRE, sem relógio. 84% dos UCs da frota estavam nesse estado absorvente
// (✅ custa caro, 🧪 é grátis e eterno). Este check põe o relógio: casos.md cujo ARQUIVO
// não é tocado há > UC_STALE_DIAS e ainda carrega 🧪/⬜ entra no warn. Proxy honesto e
// barato (data do arquivo, não do UC individual — blame por linha custaria caro); o limite
// é declarado: tocar o arquivo por outro motivo reseta o relógio. Warn-only (lei 0314).
const UC_STALE_DIAS = 30;
function checkUcAging() {
  const files = listFiles('resources/js/Pages', (rel) => rel.endsWith('.casos.md'));
  const hoje = new Date(gitLastDate('.') || '2026-07-06');
  const sample = []; let totalUc = 0; let filesN = 0;
  for (const rel of files) {
    const txt = read(rel);
    // Conta UCs declarados (heading ## UC-) com Status 🧪 ou ⬜ no bloco.
    let pend = 0;
    for (const block of txt.split(/^##\s+/m).slice(1)) {
      if (!/^UC-/i.test(block)) continue;
      if (/Status\s*[:：][^\n]*(🧪|⬜)/.test(block)) pend++;
    }
    if (!pend) continue;
    const last = gitLastDate(rel);
    if (!last) continue;
    const idade = Math.floor((hoje.getTime() - new Date(`${last}T00:00:00Z`).getTime()) / 86_400_000);
    if (idade > UC_STALE_DIAS) {
      filesN++; totalUc += pend;
      if (sample.length < 8) sample.push(`${rel} — ${pend} UC(s) 🧪/⬜ · arquivo parado há ${idade}d`);
    }
  }
  if (filesN) {
    warns.push({ check: 'Z', kind: 'uc-museu', count: totalUc, sample,
      msg: `${totalUc} UC(s) 🧪/⬜ em ${filesN} casos.md sem toque há > ${UC_STALE_DIAS}d — contrato declarado que não vira prova (estado absorvente que o G-7 não cobre). Triagem: rodar o teste no CT100/CI e promover a ✅ via manifesto, converter em E2E, ou rebaixar a backlog honesto. 🟡 sentinela — não bloqueia.` });
  }
}

// ── run ─────────────────────────────────────────────────────────────────────
checkAdrCollisions();
checkUsCollisions(); // Check N (fail-class ratchet) — colisão de US-ID, sibling do Check A
checkScorecardFantasma();
checkSecretsInMemory();
checkStaleCanon();
try { checkStaleEntryLayer(); } catch (e) { warns.push({ check: 'S', kind: 'entrada-stale-error', msg: 'entrada-stale falhou (não bloqueia): ' + e.message }); } // Check S (sentinela frescor camada de entrada)
try { checkFactAnchor(); } catch (e) { fails.push({ check: 'T', kind: 'fato-ancora-error', msg: 'fact-anchor falhou em modo fail-safe (guardião de fato crashou): ' + e.message }); } // Check T (fact-anchor determinístico — fail-class, ADR 0349, fail-safe como Check Q)
try { checkLimbo(); } catch (e) { warns.push({ check: 'U', kind: 'limbo-error', msg: 'limbo falhou (não bloqueia): ' + e.message }); } // Check U (limbo: drafts parados + homônimos)
try { checkDocumentAuthority(); } catch (e) { fails.push({ check: 'Q', kind: 'autoridade-documental-error', msg: 'autoridade documental falhou em modo fail-safe: ' + e.message }); } // Check Q (porta/autoridade únicas)
try { checkBrokenLinks(); } catch (e) { warns.push({ check: 'V', kind: 'link-quebrado-error', msg: 'link-quebrado falhou (não bloqueia): ' + e.message }); } // Check V (links internos quebrados)
try { checkBacklogIndexStale(); } catch (e) { warns.push({ check: 'W', kind: 'backlog-index-error', msg: 'backlog-index falhou (não bloqueia): ' + e.message }); } // Check W (backlog gerado stale vs SPEC)
checkAdrEnumDrift();
checkAntiResurrection();
checkGatesRegistry();
checkRegistryRefViva(); // Check P (fail-class no registry) — ref de automação apontando pra arquivo morto
checkGovernanceCeiling(); // Check M (teto de governança — ADR 0298)
checkLidoFreshness();
checkLicaoSemAssercao();
checkAdrVivoMasProposto(); // Check L (fail-class) — proposto vs realizado
try { checkPlanHealth(); } catch (e) { warns.push({ check: 'J', kind: 'plan-health-error', msg: 'plan-health falhou (não bloqueia): ' + e.message }); }
try { checkSessionDecisionAnchor(); } catch (e) { warns.push({ check: 'K', kind: 'session-anchor-error', msg: 'session-anchor falhou (não bloqueia): ' + e.message }); }
try { checkMortaMasCanon(); } catch (e) { warns.push({ check: 'O', kind: 'morta-mas-canon-error', msg: 'morta-mas-canon falhou (não bloqueia): ' + e.message }); } // Check O (sentinela) — ADR 0317
try { checkStaleReview(); } catch (e) { warns.push({ check: 'R', kind: 'revisao-vencida-error', msg: 'revisao-vencida falhou (não bloqueia): ' + e.message }); } // Check R (sentinela) — ADR 0317
try { checkAuditCoverage(); } catch (e) { warns.push({ check: 'X', kind: 'audit-coverage-error', msg: 'audit-coverage falhou (não bloqueia): ' + e.message }); } // Check X (cobertura de auditoria — módulo Tier-0/nota-baixa sem AUDIT*.md)
try { checkVitalSignsFreshness(); } catch (e) { warns.push({ check: 'Y', kind: 'vital-signs-error', msg: 'vital-signs-freshness falhou (não bloqueia): ' + e.message }); } // Check Y (watchdog do metabolismo MV — adversário V7)
try { checkUcAging(); } catch (e) { warns.push({ check: 'Z', kind: 'uc-museu-error', msg: 'uc-aging falhou (não bloqueia): ' + e.message }); } // Check Z (UC 🧪/⬜ >30d — adversário V3)

if (UPDATE_BASELINE) {
  writeFileSync(join(ROOT, BASELINE_FILE), JSON.stringify({ checkC: checkCByFile, checkL: checkLSlugs.slice().sort(), checkM: checkMKeys.slice().sort(), checkN: checkNIds.slice().sort(), checkO: checkOSlugs.slice().sort(), checkR: checkRSlugs.slice().sort() }, null, 2) + '\n');
  console.log(`✓ baseline atualizado: ${BASELINE_FILE} (Check C: ${Object.keys(checkCByFile).length} arquivos · Check L: ${checkLSlugs.length} ADRs vivo-mas-proposto · Check M: ${checkMKeys.length} workflows grandfathered · Check N: ${checkNIds.length} US-IDs dup grandfathered · Check O: ${checkOSlugs.length} morta-mas-canon grandfathered · Check R: ${checkRSlugs.length} revisão-vencida grandfathered)`);
  process.exit(0);
}

if (JSON_OUT) {
  console.log(JSON.stringify({ fails, warns, ok: fails.length === 0 }, null, 2));
  process.exit(fails.length && !WARN_ONLY ? 1 : 0);
}

console.log(`\n🩺 memory-health — ${fails.length} 🔴 fail · ${warns.length} 🟡 warn\n`);
for (const f of fails) {
  console.error(`🔴 [${f.check}] ${f.msg}`);
  if (f.files) f.files.forEach((x) => console.error(`     - ${x}`));
  if (f.sample) f.sample.forEach((x) => console.error(`     - ${JSON.stringify(x)}`));
}
for (const w of warns) {
  console.log(`🟡 [${w.check}] ${w.msg}`);
  if (w.sample) w.sample.forEach((x) => console.log(`     - ${JSON.stringify(x)}`));
}
if (!fails.length && !warns.length) console.log('✓ base de conhecimento saudável (0 fail, 0 warn).');

if (fails.length && !WARN_ONLY) {
  console.error(`\n✗ memory-health: ${fails.length} problema(s) 🔴 — corrija ou justifique. (--warn-only pra não bloquear)`);
  process.exit(1);
}
process.exit(0);
