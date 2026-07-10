#!/usr/bin/env node
// feature-lint.mjs — valida o TRIO de feature (requirements.md + plan.md + tasks.md) em
// `memory/requisitos/<Mod>/features/<slug>/` (template canônico: memory/requisitos/_TEMPLATE_FEATURE/).
//
// POR QUE EXISTE: a US do SPEC diz O QUÊ (âncora ADR 0273/0302) e o MCP diz QUEM/QUANDO
// (workflow, ADR 0070) — o trio é o COMO executável (régua Spec Kit specify→plan→tasks /
// Kiro EARS+deps; importação delta-spec+EARS já decidida na ADR 0306). Sem lint, o trio
// degrada nos dois buracos clássicos:
//   1. acceptance sem task  → AC declarado que nenhuma task prova (buraco de execução);
//   2. blocked_by irresolvível/cíclico → "grafo" de dependência que nenhuma sessão consegue
//      ordenar (ref quebrada ou ciclo) — o plano vira prosa.
//
// CONTRATO validado (ver _TEMPLATE_FEATURE/BRIEFING.md):
//   requirements.md  frontmatter `us:` aponta pra US EXISTENTE no ../../SPEC.md (detalha,
//                    nunca duplica) + ≥1 acceptance criteria `- **AC-N**` únicos (EARS).
//   plan.md          presente (conteúdo é revisão humana — lint só exige o arquivo).
//   tasks.md         blocos `### T-NN · título` únicos, cada um com blockquote de metadados
//                    (`> blocked_by: … · covers: … · us: …`) + linha `**DoD:**`.
//                    blocked_by: `—` = raiz; senão lista de T-NN existentes, grafo ACÍCLICO.
//
// VEREDITOS: ERRO (morde em --check): trio incompleto · us fora do SPEC · sem AC/AC duplicado ·
//   sem task/T duplicado · task sem metadados/sem DoD · blocked_by quebrado · ciclo ·
//   covers→AC inexistente. AVISO (nunca morde): AC sem task que cubra (buraco) · task sem
//   covers · toca Pages/<Tela>.tsx sem <Tela>.casos.md ao lado (casos-gate, ADR 0264).
//
// USO (na raiz do repo):
//   node scripts/governance/feature-lint.mjs                    # full-tree, tabela humana
//   node scripts/governance/feature-lint.mjs --json             # JSON determinístico
//   node scripts/governance/feature-lint.mjs RecurringBilling/gateway-ativacao   # 1 feature
//   node scripts/governance/feature-lint.mjs --check            # exit 1 se houver ERRO
//                                                               # ADVISORY até promoção (ADR 0271/0275)
// Node puro (fs). Sem deps, sem DB, sem PHP. Idioma: clone de doneness-lint.mjs (ADR 0302).

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join, dirname, basename, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = process.cwd();
const REQ = join(ROOT, 'memory', 'requisitos');
const TRIO = ['requirements.md', 'plan.md', 'tasks.md'];

// ── regexes do contrato (fonte: _TEMPLATE_FEATURE) ───────────────────────────────────────
const US_ID_RE = /US-[A-Z][A-Za-z0-9]*-\d+/g;
const AC_DEF_RE = /^-\s+\*\*(AC-\d+)\*\*/;          // definição: `- **AC-1** — QUANDO ...`
const TASK_HEAD_RE = /^###\s+(T-\d+)\s+·\s*(.+)$/;  // `### T-01 · título`
const TASK_META_RE = /^>\s*blocked_by:/;            // blockquote de metadados da task
const DOD_RE = /^\*\*DoD:\*\*/;
const AC_REF_RE = /AC-\d+/g;
const T_REF_RE = /T-\d+/g;
const ROOT_DEP_RE = /^(—|-|nenhum|n\/a)?$/i;        // blocked_by vazio/travessão = raiz
const PAGE_RE = /resources\/js\/Pages\/[A-Za-z0-9_\-/]+\.tsx/g;

// ── parsers (exportados pro self-test) ───────────────────────────────────────────────────
export function parseFrontmatter(txt) {
  const m = txt.match(/^(?:<!--[\s\S]*?-->\s*)?---\r?\n([\s\S]*?)\r?\n---/);
  if (!m) return { us: [], raw: null };
  const grab = (key) => (m[1].match(new RegExp(`^${key}:\\s*(.*)$`, 'm')) || [])[1] || '';
  return { us: [...new Set(grab('us').match(US_ID_RE) || [])], feature: grab('feature').trim(), module: grab('module').trim(), raw: m[1] };
}

export function parseAcs(txt) {
  const ids = [], dups = [];
  for (const line of txt.split('\n')) {
    const m = line.trimEnd().match(AC_DEF_RE);
    if (m) (ids.includes(m[1]) ? dups : ids).push(m[1]);
  }
  return { ids, dups };
}

// segmenta o blockquote de metadados por `·` e extrai cada campo do SEU segmento —
// evita covers: capturar os T-NN de blocked_by ou vice-versa.
export function parseTaskMeta(line) {
  const seg = (name) => (line.split('·').find((s) => s.includes(`${name}:`)) || '').split(`${name}:`)[1] || '';
  const rawDeps = seg('blocked_by').trim();
  return {
    deps: ROOT_DEP_RE.test(rawDeps) ? [] : [...new Set(rawDeps.match(T_REF_RE) || [])],
    depsUnparsed: !ROOT_DEP_RE.test(rawDeps) && !(rawDeps.match(T_REF_RE) || []).length ? rawDeps : null,
    covers: [...new Set(seg('covers').match(AC_REF_RE) || [])],
    us: [...new Set(seg('us').match(US_ID_RE) || [])],
  };
}

export function parseTasks(txt) {
  const tasks = [], dups = [];
  let cur = null;
  for (const raw of txt.split('\n')) {
    const line = raw.trimEnd();
    const head = line.match(TASK_HEAD_RE);
    if (head) {
      cur = { id: head[1], title: head[2].trim(), meta: null, dod: false };
      (tasks.some((t) => t.id === cur.id) ? dups : tasks).push(cur);
      continue;
    }
    if (!cur) continue;
    if (TASK_META_RE.test(line) && !cur.meta) cur.meta = parseTaskMeta(line);
    if (DOD_RE.test(line)) cur.dod = true;
  }
  return { tasks, dups };
}

// DFS 3-cores; retorna o caminho do 1º ciclo achado (determinístico: ordem do arquivo) ou null.
export function detectCycle(tasks) {
  const deps = new Map(tasks.map((t) => [t.id, t.meta ? t.meta.deps : []]));
  const color = new Map(); // undefined=branco, 1=cinza (na pilha), 2=preto
  const path = [];
  const visit = (id) => {
    color.set(id, 1); path.push(id);
    for (const d of deps.get(id) || []) {
      if (!deps.has(d)) continue; // ref quebrada é OUTRO erro, não ciclo
      if (color.get(d) === 1) return [...path.slice(path.indexOf(d)), d];
      if (!color.get(d)) { const c = visit(d); if (c) return c; }
    }
    color.set(id, 2); path.pop();
    return null;
  };
  for (const t of tasks) if (!color.get(t.id)) { const c = visit(t.id); if (c) return c; }
  return null;
}

// ── lint de UMA feature-pasta ────────────────────────────────────────────────────────────
export function lintFeature(dir, { specText } = {}) {
  const issues = [];
  const erro = (code, msg) => issues.push({ level: 'erro', code, msg });
  const aviso = (code, msg) => issues.push({ level: 'aviso', code, msg });

  const missing = TRIO.filter((f) => !existsSync(join(dir, f)));
  if (missing.length) erro('trio-incompleto', `faltando: ${missing.join(', ')}`);

  const read = (f) => (existsSync(join(dir, f)) ? readFileSync(join(dir, f), 'utf8') : '');
  const reqTxt = read('requirements.md'), tasksTxt = read('tasks.md');

  // requirements: us → existe no SPEC do módulo (substring — a US é ID único no repo)
  const fm = parseFrontmatter(reqTxt);
  if (reqTxt && !fm.us.length) erro('sem-us', 'frontmatter de requirements.md sem `us:` resolvível (US-<MOD>-NNN)');
  const spec = specText ?? (existsSync(join(dir, '..', '..', 'SPEC.md')) ? readFileSync(join(dir, '..', '..', 'SPEC.md'), 'utf8') : null);
  if (spec == null && fm.us.length) erro('spec-ausente', 'SPEC.md do módulo não encontrado (a feature detalha uma US do SPEC — sem SPEC não há o que detalhar)');
  for (const us of fm.us) if (spec != null && !spec.includes(us)) erro('us-fora-do-spec', `${us} não existe no SPEC.md do módulo (a pasta detalha, nunca inventa US)`);

  const { ids: acs, dups: acDups } = parseAcs(reqTxt);
  if (reqTxt && !acs.length) erro('sem-ac', 'requirements.md sem acceptance criteria (`- **AC-N** — ...` EARS)');
  for (const d of acDups) erro('ac-duplicado', `${d} definido mais de uma vez`);

  // tasks: parse + DoD + deps + covers
  const { tasks, dups: tDups } = parseTasks(tasksTxt);
  if (tasksTxt && !tasks.length) erro('sem-task', 'tasks.md sem nenhuma task (`### T-NN · título`)');
  for (const d of tDups) erro('task-duplicada', `${d.id} definida mais de uma vez`);
  const known = new Set(tasks.map((t) => t.id));
  for (const t of tasks) {
    if (!t.meta) { erro('task-sem-meta', `${t.id} sem blockquote de metadados (\`> blocked_by: ...\`) — dependência irresolvível`); continue; }
    if (t.meta.depsUnparsed) erro('blocked-by-quebrado', `${t.id}: blocked_by "${t.meta.depsUnparsed}" não resolve pra T-NN nem é raiz (—)`);
    for (const d of t.meta.deps) if (!known.has(d)) erro('blocked-by-quebrado', `${t.id} depende de ${d}, que não existe`);
    for (const c of t.meta.covers) if (!acs.includes(c)) erro('covers-ac-inexistente', `${t.id} cobre ${c}, que não está definido em requirements.md`);
    if (!t.dod) erro('task-sem-dod', `${t.id} sem linha \`**DoD:**\` (prova verificável por task é obrigatória)`);
    if (!t.meta.covers.length) aviso('task-sem-covers', `${t.id} não declara \`covers:\` — não prova nenhum AC`);
  }
  const cycle = detectCycle(tasks);
  if (cycle) erro('ciclo', `dependência cíclica: ${cycle.join(' → ')}`);

  // buraco: AC sem task que cubra (advisory — o achado nº1 que motivou o lint)
  const covered = new Set(tasks.flatMap((t) => (t.meta ? t.meta.covers : [])));
  for (const ac of acs) if (!covered.has(ac)) aviso('ac-sem-task', `${ac} não é coberto por nenhuma task (buraco de execução)`);

  // toca tela? lembrar o casos-gate (ADR 0264) — advisory
  for (const page of new Set(`${reqTxt}\n${read('plan.md')}`.match(PAGE_RE) || [])) {
    const casos = join(ROOT, page.replace(/\.tsx$/, '.casos.md'));
    if (existsSync(join(ROOT, page)) && !existsSync(casos)) aviso('tela-sem-casos', `toca ${page} sem ${basename(casos)} ao lado (casos-gate, ADR 0264)`);
  }

  return { dir, feature: fm.feature || basename(dir), us: fm.us, acs: acs.length, tasks: tasks.length, issues };
}

// ── seleção: full-tree ou diff-aware (args `<Mod>/<slug>` ou paths) — igual doneness-lint ─
const isMain = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isMain) {
  const JSON_OUT = process.argv.includes('--json');
  const CHECK = process.argv.includes('--check');
  const args = process.argv.slice(2).filter((a) => !a.startsWith('--'));

  let dirs;
  if (args.length) {
    dirs = args.map((a) => {
      const p = resolve(ROOT, a);
      if (existsSync(join(p, 'requirements.md')) || basename(dirname(p)) === 'features') return p;
      const [mod, slug] = a.split(/[\\/]/);
      return join(REQ, mod, 'features', slug || '');
    }).filter((p) => existsSync(p)).sort();
  } else {
    dirs = readdirSync(REQ, { withFileTypes: true })
      .filter((e) => e.isDirectory() && !e.name.startsWith('_') && existsSync(join(REQ, e.name, 'features')))
      .flatMap((e) => readdirSync(join(REQ, e.name, 'features'), { withFileTypes: true })
        .filter((s) => s.isDirectory())
        .map((s) => join(REQ, e.name, 'features', s.name)))
      .sort();
  }

  const results = dirs.map((d) => {
    const r = lintFeature(d);
    return { ...r, module: basename(dirname(dirname(d))), dir: d.slice(ROOT.length + 1).replaceAll('\\', '/') };
  });
  const erros = results.reduce((a, r) => a + r.issues.filter((i) => i.level === 'erro').length, 0);
  const avisos = results.reduce((a, r) => a + r.issues.filter((i) => i.level === 'aviso').length, 0);

  const report = {
    _meta: {
      lint: 'feature-trio — requirements/plan/tasks com deps blocked_by (template _TEMPLATE_FEATURE · régua Spec Kit/Kiro · delta-spec+EARS via ADR 0306)',
      generator: 'scripts/governance/feature-lint.mjs',
      regra: 'ERRO morde em --check (trio incompleto · us fora do SPEC · deps quebradas/cíclicas · task sem DoD/meta · covers inválido). AVISO nunca morde (ac-sem-task = buraco · task-sem-covers · tela-sem-casos).',
      determinismo: 'sem timestamps/sha no output — re-run sem mudança no repo = diff vazio',
      fase: 'ADVISORY (ADR 0271/0275) — exit 0 nos modos default/--json; --check (exit 1 em ERRO) é o primitivo de enforcement, promovido por calendário',
      scope: args.length ? 'diff-aware (args)' : 'full-tree',
    },
    summary: { features: results.length, erros, avisos },
    features: results,
  };

  if (JSON_OUT) { process.stdout.write(JSON.stringify(report, null, 2) + '\n'); process.exit(0); }

  console.log(`\n  FEATURE LINT — trio requirements/plan/tasks · ${results.length} feature(s) · escopo: ${report._meta.scope}\n`);
  for (const r of results) {
    const nErr = r.issues.filter((i) => i.level === 'erro').length;
    const flag = nErr ? '🔴' : r.issues.length ? '🟡' : '🟢';
    console.log(`  ${flag} ${r.module}/${r.feature} — us: ${r.us.join(', ') || '?'} · ${r.acs} AC · ${r.tasks} task(s)`);
    for (const i of r.issues) console.log(`       ${i.level === 'erro' ? '✗' : '⚠️ '} [${i.code}] ${i.msg}`);
  }
  if (!results.length) console.log('  (nenhuma feature-pasta em memory/requisitos/*/features/ — template em memory/requisitos/_TEMPLATE_FEATURE/)');
  console.log(`\n  ERROS (mordem em --check): ${erros} · AVISOS (advisory): ${avisos}`);
  console.log('  Contrato: _TEMPLATE_FEATURE/BRIEFING.md · done-ness da feature = âncora da US no SPEC (ADR 0273/0302), nunca este arquivo.\n');

  if (CHECK && erros > 0) process.exit(1);
  process.exit(0);
}
