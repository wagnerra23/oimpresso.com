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
//   node scripts/governance/feature-lint.mjs --init Mod/slug --us US-MOD-001
//       --sdd auto --cu CU-MOD-01 --screen Mod/Tela
//   node scripts/governance/feature-lint.mjs --init Mod/slug --us US-MOD-001 --dry-run
//                                                               # ADVISORY até promoção (ADR 0271/0275)
// Node puro (fs). Sem deps, sem DB, sem PHP. Idioma: clone de doneness-lint.mjs (ADR 0302).

import { readdirSync, readFileSync, existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { join, dirname, basename, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = process.cwd();
const REQ = join(ROOT, 'memory', 'requisitos');
const TRIO = ['requirements.md', 'plan.md', 'tasks.md'];
const TEMPLATE_DIR = join(REQ, '_TEMPLATE_FEATURE');
const FEATURE_SLUG_RE = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const MODULE_RE = /^[A-Z][A-Za-z0-9]*$/;
const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
const OWNER_RE = /^(?:W|F|M|L|E)(?:\/(?:W|F|M|L|E))*$/;
const PLACEHOLDER_RE = /\{\{[^}\n]+\}\}/g;

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
const CU_ID_RE = /CU-[A-Z][A-Z0-9]*-\d+/g;

function parseInlineList(raw, matcher = null) {
  const values = String(raw || '')
    .replace(/^\s*\[/, '')
    .replace(/\]\s*$/, '')
    .split(',')
    .map((value) => value.trim().replace(/^['"]|['"]$/g, ''))
    .filter(Boolean);
  return [...new Set(matcher ? values.flatMap((value) => value.match(matcher) || []) : values)];
}

export function featureTutorialText() {
  return `
FEATURE INIT — TUTORIAL GUIADO

Quando usar:
  Feature complexa ou multi-sessão (>=3 tarefas, dependência real, regra de negócio,
  integração, fila, multi-tenant, valor ou estoque). Fix pequeno de uma tarefa não usa trio.

Antes de começar:
  1. Confirme o sinal de cliente/métrica.
  2. Crie ou confirme a US em memory/requisitos/<Modulo>/SPEC.md.
  3. Escolha um slug kebab-case. A máquina nunca inventa a US.

Passo 1 — conferir sem escrever:
  npm run sdd:init -- <Modulo>/<slug> --us US-<MOD>-<NNN> --sdd auto
    --cu CU-<MOD>-NN --screen <Modulo>/<Tela> --dry-run

Passo 2 — gerar o contrato:
  npm run sdd:init -- <Modulo>/<slug> --us US-<MOD>-<NNN> --sdd auto
    --cu CU-<MOD>-NN --screen <Modulo>/<Tela>

Resultado:
  memory/requisitos/<Modulo>/features/<slug>/requirements.md
  memory/requisitos/<Modulo>/features/<slug>/plan.md
  memory/requisitos/<Modulo>/features/<slug>/tasks.md

Passo 3 — preencher:
  requirements.md  = user story, Clarifications, AC-N verificáveis e fora de escopo
  plan.md          = plug-points existentes, decisões, dados/contratos e riscos Tier 0
  tasks.md         = T-NN, blocked_by, covers, us e DoD verificável

Passo 4 — validar antes de implementar:
  node scripts/governance/feature-lint.mjs <Modulo>/<slug> --check
  npm run sdd:flow -- <Modulo>/<slug>

Passo 5 — executar e fechar:
  teste falha -> menor implementação -> teste passa -> DoD -> smoke real ->
  atualizar Implementado em: verificado@<sha7> na US do SPEC.

Exemplo completo para leitura (já existe; não tente recriá-lo):
  memory/requisitos/Connector/features/openapi-connector/

Tutorial visual completo:
  /documentacao -> B7.1 Tutorial completo: da US à entrega provada

A máquina recusa destino existente, US ausente, trio incompleto, placeholder não curado,
dependência quebrada/cíclica, AC inexistente e tarefa sem DoD.
`.trim();
}

// ── parsers (exportados pro self-test) ───────────────────────────────────────────────────
export function parseFrontmatter(txt) {
  const m = txt.match(/^(?:<!--[\s\S]*?-->\s*)?---\r?\n([\s\S]*?)\r?\n---/);
  if (!m) return { us: [], sdd: [], screens: [], relatedCus: [], created: null, raw: null };
  const grab = (key) => (m[1].match(new RegExp(`^${key}:\\s*(.*)$`, 'm')) || [])[1] || '';
  return {
    us: [...new Set(grab('us').match(US_ID_RE) || [])],
    sdd: parseInlineList(grab('sdd')),
    screens: parseInlineList(grab('screens')),
    relatedCus: parseInlineList(grab('related_cus'), CU_ID_RE),
    created: grab('created').trim().replace(/^['"]|['"]$/g, '') || null,
    feature: grab('feature').trim(),
    module: grab('module').trim(),
    raw: m[1],
  };
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
function stripTemplateEnvelope(txt) {
  // Templates carregam o id do proprio TEMPLATE e depois o frontmatter da feature.
  // O scaffold descarta o envelope explicativo e materializa somente o contrato vivo.
  return txt
    .replace(/^---\r?\n[\s\S]*?\r?\n---\r?\n/, '')
    .replace(/^\s*<!--[\s\S]*?-->\s*/, '')
    .trimStart();
}

function plusDays(date, days) {
  const d = new Date(`${date}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}

function localToday() {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

function kebabModule(module) {
  return module.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
}

export function renderFeatureTemplate(kind, values, { templateDir = TEMPLATE_DIR } = {}) {
  if (!TRIO.includes(kind)) throw new Error(`template desconhecido: ${kind}`);
  const source = join(templateDir, kind);
  if (!existsSync(source)) throw new Error(`template ausente: ${source}`);

  const type = kind.replace(/\.md$/, '');
  const id = `requisitos-${kebabModule(values.module)}-features-${values.slug}-${type}`;
  let out = stripTemplateEnvelope(readFileSync(source, 'utf8'));
  out = out.replace(/^---\r?\n/, `---\nid: ${id}\n`);
  out = out
    .replaceAll('{{slug-kebab}}', values.slug)
    .replace(/\{\{PascalCase[^}]*\}\}/g, values.module)
    .replaceAll('US-{{MOD}}-{{NNN}}', values.us)
    .replaceAll('{{YYYY-MM-DD+30}}', plusDays(values.date, 30))
    .replaceAll('{{YYYY-MM-DD}}', values.date)
    .replaceAll('{{parent-plan}}', values.parentPlan)
    .replaceAll('{{OWNER}}', values.owner)
    .replaceAll('{{Mod}}', values.module);
  out = out.replace(/^parent_plan:\s*.*$/m, `parent_plan: ${values.parentPlan}`);
  out = out.replace(/^sdd:\s*.*$/m, `sdd: ${JSON.stringify(values.sdds || [])}`);
  out = out.replace(/^screens:\s*.*$/m, `screens: ${JSON.stringify(values.screens || [])}`);
  out = out.replace(/^related_cus:\s*.*$/m, `related_cus: ${JSON.stringify(values.relatedCus || [])}`);
  return out.endsWith('\n') ? out : `${out}\n`;
}

function normalizeScreenRef(module, value) {
  const clean = String(value || '').trim().replaceAll('\\', '/').replace(/^\.\//, '');
  if (!clean) return null;
  if (clean.startsWith('resources/js/Pages/')) return clean.endsWith('.tsx') ? clean : `${clean}.tsx`;
  const page = clean.startsWith(`${module}/`) ? clean : `${module}/${clean}`;
  return `resources/js/Pages/${page.replace(/\.tsx$/, '')}.tsx`;
}

function normalizeSddRef(module, value) {
  const clean = String(value || '').trim().replaceAll('\\', '/').replace(/^\.\//, '');
  if (!clean) return null;
  if (clean.startsWith('memory/requisitos/')) return clean;
  return `memory/requisitos/${module}/${clean}`;
}

export function scaffoldFeature({
  root = ROOT,
  target,
  us,
  date,
  owner = 'W',
  parentPlan,
  sdds = [],
  screens = [],
  relatedCus = [],
  dryRun = false,
  templateDir,
} = {}) {
  const parts = String(target || '').split(/[\\/]/).filter(Boolean);
  if (parts.length !== 2) throw new Error('alvo invalido: use <Modulo>/<slug>');
  const [module, slug] = parts;
  if (!MODULE_RE.test(module)) throw new Error(`modulo invalido: ${module} (esperado PascalCase)`);
  if (!FEATURE_SLUG_RE.test(slug)) throw new Error(`slug invalido: ${slug} (esperado kebab-case)`);
  if (!/^US-[A-Z][A-Z0-9]*-\d+$/.test(String(us || ''))) throw new Error('US invalida/ausente: use --us US-MOD-001');

  const created = date || localToday();
  if (!DATE_RE.test(created)) throw new Error(`data invalida: ${created} (esperado YYYY-MM-DD)`);
  if (!OWNER_RE.test(owner)) throw new Error(`owner invalido: ${owner} (use W/F/M/L/E, combinaveis com /)`);
  const plan = parentPlan || `${kebabModule(module)}-${slug}`;
  if (!FEATURE_SLUG_RE.test(plan)) throw new Error(`parent-plan invalido: ${plan} (esperado kebab-case)`);

  const requisitos = join(root, 'memory', 'requisitos');
  const moduleDir = join(requisitos, module);
  const specPath = join(moduleDir, 'SPEC.md');
  if (!existsSync(moduleDir)) throw new Error(`modulo sem requisitos: memory/requisitos/${module}`);
  if (!existsSync(specPath)) throw new Error(`SPEC ausente: memory/requisitos/${module}/SPEC.md`);
  const specIds = new Set(readFileSync(specPath, 'utf8').match(US_ID_RE) || []);
  if (!specIds.has(us)) throw new Error(`${us} nao existe no SPEC.md; a maquina nao inventa US`);

  let normalizedSdds = [...new Set(sdds.flatMap((value) => String(value).split(',')).map((value) => value.trim()).filter(Boolean))];
  if (normalizedSdds.includes('auto')) {
    if (normalizedSdds.length > 1) throw new Error('sdd auto nao pode ser combinado com outro --sdd');
    const found = readdirSync(moduleDir).filter((name) => /^SDD-.*\.md$/i.test(name)).sort();
    if (found.length !== 1) throw new Error(`sdd auto exige exatamente 1 SDD no modulo; encontrados: ${found.length}`);
    normalizedSdds = [found[0]];
  }
  normalizedSdds = normalizedSdds.map((value) => normalizeSddRef(module, value));
  for (const ref of normalizedSdds) {
    if (!ref.startsWith(`memory/requisitos/${module}/SDD-`) || !ref.endsWith('.md')) {
      throw new Error(`SDD fora do modulo/familia: ${ref}`);
    }
    if (!existsSync(join(root, ref))) throw new Error(`SDD nao resolve: ${ref}`);
  }
  const normalizedScreens = [...new Set(screens
    .flatMap((value) => String(value).split(','))
    .map((value) => normalizeScreenRef(module, value))
    .filter(Boolean))];
  const normalizedCus = [...new Set(relatedCus
    .flatMap((value) => String(value).split(','))
    .flatMap((value) => value.match(CU_ID_RE) || []))];

  const dir = join(moduleDir, 'features', slug);
  if (existsSync(dir)) throw new Error(`destino ja existe; recusado sobrescrever: memory/requisitos/${module}/features/${slug}`);

  const values = {
    module,
    slug,
    us,
    date: created,
    owner,
    parentPlan: plan,
    sdds: normalizedSdds,
    screens: normalizedScreens,
    relatedCus: normalizedCus,
  };
  const sourceDir = templateDir || join(requisitos, '_TEMPLATE_FEATURE');
  const files = TRIO.map((file) => ({ file, path: join(dir, file), content: renderFeatureTemplate(file, values, { templateDir: sourceDir }) }));
  if (!dryRun) {
    mkdirSync(dir, { recursive: true });
    for (const f of files) writeFileSync(f.path, f.content, 'utf8');
  }
  return {
    dir,
    module,
    slug,
    us,
    date: created,
    owner,
    parentPlan: plan,
    sdds: normalizedSdds,
    screens: normalizedScreens,
    relatedCus: normalizedCus,
    dryRun,
    files,
  };
}

export function lintFeature(dir, { specText } = {}) {
  const issues = [];
  const erro = (code, msg) => issues.push({ level: 'erro', code, msg });
  const aviso = (code, msg) => issues.push({ level: 'aviso', code, msg });

  const missing = TRIO.filter((f) => !existsSync(join(dir, f)));
  if (missing.length) erro('trio-incompleto', `faltando: ${missing.join(', ')}`);

  const read = (f) => (existsSync(join(dir, f)) ? readFileSync(join(dir, f), 'utf8') : '');
  const reqTxt = read('requirements.md'), planTxt = read('plan.md'), tasksTxt = read('tasks.md');
  for (const [file, txt] of [['requirements.md', reqTxt], ['plan.md', planTxt], ['tasks.md', tasksTxt]]) {
    const placeholders = [...new Set(txt.match(PLACEHOLDER_RE) || [])];
    if (placeholders.length) erro('placeholder-nao-curado', `${file} ainda contem ${placeholders.slice(0, 3).join(', ')}${placeholders.length > 3 ? ` +${placeholders.length - 3}` : ''}`);
  }

  // requirements: us → existe no SPEC do módulo (substring — a US é ID único no repo)
  const fm = parseFrontmatter(reqTxt);
  if (reqTxt && !fm.us.length) erro('sem-us', 'frontmatter de requirements.md sem `us:` resolvível (US-<MOD>-NNN)');
  const spec = specText ?? (existsSync(join(dir, '..', '..', 'SPEC.md')) ? readFileSync(join(dir, '..', '..', 'SPEC.md'), 'utf8') : null);
  if (spec == null && fm.us.length) erro('spec-ausente', 'SPEC.md do módulo não encontrado (a feature detalha uma US do SPEC — sem SPEC não há o que detalhar)');
  for (const us of fm.us) if (spec != null && !spec.includes(us)) erro('us-fora-do-spec', `${us} não existe no SPEC.md do módulo (a pasta detalha, nunca inventa US)`);

  // Forward-only: features legadas sem esses campos continuam validas. Quando a
  // ligacao e declarada, a maquina prova que SDD/CU/tela resolvem no mesmo modulo.
  const repoRoot = resolve(dir, '..', '..', '..', '..', '..');
  const moduleName = fm.module || basename(dirname(dirname(dir)));
  const linkedSddTexts = [];
  for (const ref of fm.sdd) {
    const normalized = ref.replaceAll('\\', '/').replace(/^\.\//, '');
    if (!normalized.startsWith(`memory/requisitos/${moduleName}/SDD-`) || !normalized.endsWith('.md')) {
      erro('sdd-fora-do-modulo', `${ref} nao e SDD da familia ${moduleName}`);
      continue;
    }
    const path = join(repoRoot, normalized);
    if (!existsSync(path)) { erro('sdd-nao-resolve', `${ref} nao existe`); continue; }
    const text = readFileSync(path, 'utf8');
    const sddModule = (text.match(/^module:\s*([^\r\n]+)/m) || [])[1]?.trim();
    const sddType = (text.match(/^type:\s*([^\r\n]+)/m) || [])[1]?.trim();
    if (sddModule && sddModule !== moduleName) erro('sdd-modulo-divergente', `${ref} declara module=${sddModule}, esperado ${moduleName}`);
    if (sddType && sddType !== 'sdd') erro('sdd-tipo-divergente', `${ref} declara type=${sddType}, esperado sdd`);
    linkedSddTexts.push(text);
  }
  if (fm.raw && /^sdd:\s*\[\s*\]\s*$/m.test(fm.raw)) {
    aviso('feature-sem-sdd', 'nenhum SDD de dominio/familia foi ligado; use --sdd auto quando houver exatamente um');
  }
  for (const cu of fm.relatedCus) {
    if (!linkedSddTexts.some((text) => new RegExp(`\\b${cu}\\b`).test(text))) {
      erro('cu-fora-do-sdd', `${cu} nao aparece em nenhum SDD ligado`);
    }
  }
  if (fm.sdd.length && fm.raw && /^related_cus:\s*\[\s*\]\s*$/m.test(fm.raw)) {
    aviso('feature-sem-cu', 'SDD ligado, mas nenhum CU relacionado foi declarado; a maquina nao inventa esse vinculo');
  }

  for (const ref of fm.screens) {
    const normalized = ref.replaceAll('\\', '/').replace(/^\.\//, '');
    if (!normalized.startsWith(`resources/js/Pages/${moduleName}/`) || !normalized.endsWith('.tsx')) {
      erro('screen-fora-do-modulo', `${ref} nao e Page .tsx de ${moduleName}`);
      continue;
    }
    const page = join(repoRoot, normalized);
    if (!existsSync(page)) { erro('screen-nao-resolve', `${ref} nao existe; crie tela nova com npm run tela:criar`); continue; }
    const charter = page.replace(/\.tsx$/, '.charter.md');
    const casos = page.replace(/\.tsx$/, '.casos.md');
    if (!existsSync(charter)) aviso('tela-sem-charter', `${ref} nao tem charter irmao`);
    if (!existsSync(casos)) aviso('tela-sem-casos', `${ref} nao tem casos irmao`);
    if (existsSync(casos) && fm.sdd.length) {
      const casosText = readFileSync(casos, 'utf8');
      const casosSdd = (casosText.match(/^sdd:\s*([^\r\n]+)/m) || [])[1]?.trim().replace(/^['"]|['"]$/g, '');
      if (!casosSdd) aviso('casos-sem-sdd', `${normalized.replace(/\.tsx$/, '.casos.md')} nao aponta para o SDD ligado pela feature`);
      else if (!fm.sdd.includes(casosSdd)) aviso('casos-sdd-divergente', `${casosSdd} nao coincide com o SDD ligado pela feature`);
    }
  }

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
  for (const t of tasks) {
    if (!t.meta) continue;
    for (const us of t.meta.us) {
      if (!fm.us.includes(us)) erro('task-us-fora-da-feature', `${t.id} aponta ${us}, fora do frontmatter de requirements.md`);
    }
  }
  const cycle = detectCycle(tasks);
  if (cycle) erro('ciclo', `dependência cíclica: ${cycle.join(' → ')}`);

  // buraco: AC sem task que cubra (advisory — o achado nº1 que motivou o lint)
  const covered = new Set(tasks.flatMap((t) => (t.meta ? t.meta.covers : [])));
  for (const ac of acs) if (!covered.has(ac)) aviso('ac-sem-task', `${ac} não é coberto por nenhuma task (buraco de execução)`);

  // toca tela? lembrar o casos-gate (ADR 0264) — advisory
  for (const page of new Set(`${reqTxt}\n${planTxt}`.match(PAGE_RE) || [])) {
    const casos = join(ROOT, page.replace(/\.tsx$/, '.casos.md'));
    if (existsSync(join(ROOT, page)) && !existsSync(casos)) aviso('tela-sem-casos', `toca ${page} sem ${basename(casos)} ao lado (casos-gate, ADR 0264)`);
  }

  return {
    dir,
    feature: fm.feature || basename(dir),
    module: moduleName,
    us: fm.us,
    sdd: fm.sdd,
    screens: fm.screens,
    relatedCus: fm.relatedCus,
    acs: acs.length,
    tasks: tasks.length,
    issues,
  };
}

// ── seleção: full-tree ou diff-aware (args `<Mod>/<slug>` ou paths) — igual doneness-lint ─
const isMain = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isMain) {
  const argv = process.argv.slice(2);
  const JSON_OUT = argv.includes('--json');
  const CHECK = argv.includes('--check');
  const flagValue = (name) => {
    const eq = argv.find((a) => a.startsWith(`${name}=`));
    if (eq) return eq.slice(name.length + 1);
    const i = argv.indexOf(name);
    return i >= 0 ? argv[i + 1] : undefined;
  };
  const flagValues = (name) => {
    const values = [];
    for (let i = 0; i < argv.length; i++) {
      if (argv[i] === name && argv[i + 1]) values.push(argv[i + 1]);
      else if (argv[i].startsWith(`${name}=`)) values.push(argv[i].slice(name.length + 1));
    }
    return values;
  };

  if (argv.includes('--help') || argv.includes('-h')) {
    console.log(`${featureTutorialText()}\n`);
    process.exit(0);
  }

  if (argv.includes('--init') || argv.some((a) => a.startsWith('--init='))) {
    try {
      const result = scaffoldFeature({
        target: flagValue('--init'),
        us: flagValue('--us'),
        date: flagValue('--date'),
        owner: flagValue('--owner') || 'W',
        parentPlan: flagValue('--parent-plan'),
        sdds: flagValues('--sdd'),
        screens: flagValues('--screen'),
        relatedCus: flagValues('--cu'),
        dryRun: argv.includes('--dry-run'),
      });
      const rel = result.dir.slice(ROOT.length + 1).replaceAll('\\', '/');
      console.log(`\n  FEATURE INIT - ${result.dryRun ? 'DRY-RUN' : 'CRIADO'} - ${result.module}/${result.slug} - ${result.us}`);
      for (const f of result.files) console.log(`  ${result.dryRun ? 'criaria' : 'criou'} ${f.path.slice(ROOT.length + 1).replaceAll('\\', '/')}`);
      console.log(`  SDD: ${result.sdds.join(', ') || 'nao ligado'} | telas: ${result.screens.join(', ') || 'nenhuma'}`);
      console.log(`\n  Proximo: cure todos os {{...}} em ${rel} e rode:`);
      console.log(`  node scripts/governance/feature-lint.mjs ${result.module}/${result.slug} --check\n`);
      process.exit(0);
    } catch (e) {
      console.error(`\n  FEATURE INIT RECUSADO - ${e.message}\n`);
      process.exit(2);
    }
  }

  const flagsWithValue = new Set(['--us', '--date', '--owner', '--parent-plan', '--sdd', '--screen', '--cu']);
  const args = [];
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (flagsWithValue.has(a)) { i++; continue; }
    if (!a.startsWith('--')) args.push(a);
  }

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
