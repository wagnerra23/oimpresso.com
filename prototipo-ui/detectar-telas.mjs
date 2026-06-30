#!/usr/bin/env node
// detectar-telas.mjs — Fase 0 + 0.5 do protocolo aplicar-prototipo, como MECANISMO.
//
// Por que existe (incidente 2026-06-24): a detecção de telas vivia em PROSA no
// RUNBOOK ("parseie os charters", "heurística filename+conteúdo") e era executada
// na cabeça do agente. Falhou DUAS vezes pela mesma raiz — o source que não tem
// âncora SOME em silêncio:
//   (1) detecção por diretório perdeu Sells/Index + Sells/Create (prototipos/sells só tinha CRITIQUE);
//   (2) o conserto "charter=índice" perdeu de novo Sells/Create (vendas-create-page.jsx não tem
//       charter no bundle; o charter-alvo Sells/Create.charter.md vive no REPO, fora do find).
// Adversário (red-team) provou: 17/24 mockups órfãos. Mesma classe de bug, nova porta.
//
// Este script faz a RECONCILIAÇÃO BIDIRECIONAL e FALHA (exit 1) se sobrar mockup
// órfão não-resolvido — "0 telas perdidas em silêncio" vira gate, não promessa.
//
//   FASE 0  — enumera todo screen-source do bundle + indexa charters (staging E repo)
//   FASE 0.5— por arquivo: resolve alvo no repo → classifica → diffa o diffável → MANIFESTO
//
// Resolução do alvo (em ordem, primeira que casa vence):
//   (1) path-espelhado: o staging-path já contém `resources/js/Pages/...` (caso prototipo-ui-patch)
//   (2) format-2 `<dir>/Index.tsx` → `component:`/`page:` do `<dir>/Index.charter.md` irmão (path de repo)
//   (3) format-2 sem charter → lookup por sufixo no repo (`/<dir>/Index.tsx` único)
//   (4) mockup → charter cujo `component:` cita o basename .jsx → o `repo_alvo:` daquele charter
//   (5) ALIAS map (dicionário de código) — mockups sem charter (ex: vendas-create)
//   (6) nada casou → ORFAO  → **gate falha**
// Correção de charter STALE: se o alvo resolvido NÃO existe mas um ALIAS aponta pra um que
// existe (ex: charter diz Purchases, real é Compras), o ALIAS corrige. Senão → ALVO-PENDENTE
// (nunca silêncio).
//
// Uso:
//   node prototipo-ui/detectar-telas.mjs --staging <dir-do-project> [--repo <root>] [--json] [--strict]
//   node prototipo-ui/detectar-telas.mjs --selftest      # fixture hermético commitado
//
// --strict: ALVO-PENDENTE também falha (default: só ORFAO/AMBIGUO falham).

import { readFile, readdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join, resolve, dirname, basename, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

const HERE = dirname(fileURLToPath(import.meta.url)); // prototipo-ui/
const REPO_DEFAULT = resolve(HERE, '..');             // raiz do repo

// ---- ALIAS map (CÓDIGO, não prosa) ----------------------------------------
// Mockups do bundle que NÃO têm charter-âncora. Aterrado no repo real 2026-06-24
// (compras→Compras NÃO Purchases; clientes/crm→Cliente, CRM/Index não existe).
// Extensível: mockup novo sem alvo → ORFAO (gate falha) → adicione a entrada aqui
// CONSCIENTEMENTE (o ponto do mecanismo: nada some sem decisão).
const ALIAS = [
  { re: /^vendas-create-page\.jsx$/i, alvo: 'resources/js/Pages/Sells/Create.tsx',            tela: 'Venda (Sells/Create)' },
  { re: /^vendas-page\.jsx$/i,        alvo: 'resources/js/Pages/Sells/Index.tsx',             tela: 'Lista de Venda (Sells/Index)' },
  { re: /^compras-page\.jsx$/i,       alvo: 'resources/js/Pages/Compras/Index.tsx',           tela: 'Compras' },
  { re: /^(clientes|crm)-page\.jsx$/i,alvo: 'resources/js/Pages/Cliente/Index.tsx',           tela: 'Clientes/CRM' },
  { re: /^cobranca-recorrente-page\.jsx$/i, alvo: 'resources/js/Pages/RecurringBilling/Index.tsx', tela: 'Cobrança Recorrente' },
  // alvo vivo verificado (nome↔Page inequívoco) — 2026-06-30:
  { re: /^produtos-page\.jsx$/i,      alvo: 'resources/js/Pages/Produto/Index.tsx',           tela: 'Produtos' },
  { re: /^kb-page\.jsx$/i,            alvo: 'resources/js/Pages/kb/Index.tsx',                tela: 'Base de Conhecimento' },
];

// ---- A_CRIAR (CÓDIGO) — mockups CONSCIENTEMENTE registrados como "tela ainda não existe" -----
// NÃO é alvo inventado: é o oposto do órfão-cego. Reconhece "este mockup é de um módulo
// nascente, target a-criar via MWART" → classifica A-CRIAR (NÃO trava o gate), em vez de ORFAO
// (trava) ou de um repo_alvo chutado (LICOES_F3). Quando a tela for criada, ganha charter e
// graduа sozinha pra SEMANTICO. Mockup NOVO fora desta lista E sem charter ainda → ORFAO (gate
// falha) — a fail-closed continua: só some o que foi reconhecido à mão. Origem: Q5 2026-06-30.
const A_CRIAR = [
  /^boletos-page\.jsx$/i, /^equipe-page\.jsx$/i, /^financeiro-page\.jsx$/i, /^forja-page\.jsx$/i,
  /^inbox-page\.jsx$/i, /^orc-page\.jsx$/i, /^os-page\.jsx$/i, /^perfil-page\.jsx$/i,
  /^pg-cobranca-page\.jsx$/i, /^pg-payment-gateways-page\.jsx$/i, /^producao-page\.jsx$/i,
  /^cobranca-page\.jsx$/i, /^payment-gateways-page\.jsx$/i, /^usuarios-page\.jsx$/i,
];
export function isACriar(b) { return A_CRIAR.some((re) => re.test(b)); }

const read = async (p) => { try { return await readFile(p, 'utf8'); } catch { return null; } };

async function walk(dir, out = []) {
  let entries;
  try { entries = await readdir(dir, { withFileTypes: true }); } catch { return out; }
  const skip = new Set(['node_modules', '.git', '_arquivo', '_BACKUP-NAO-USAR', 'scraps', 'screenshots', 'uploads', 'assets']);
  for (const e of entries) {
    if (skip.has(e.name)) continue;
    const full = join(dir, e.name);
    if (e.isDirectory()) await walk(full, out);
    else out.push(full);
  }
  return out;
}

function resolveStaging(p) {
  if (!p) return null;
  if (existsSync(join(p, 'project'))) return join(p, 'project');
  return p;
}

// extrai o token de path-de-repo de um texto livre (component:/page:/repo_alvo:)
function extractRepoPath(text) {
  if (!text) return null;
  let m = text.match(/resources\/js\/Pages\/[\w./-]+\.tsx/);
  if (m) return m[0];
  m = text.match(/\b([A-Z][\w]+(?:\/[A-Z][\w]+)+)\b/); // "Sells/Index", "Purchases/Index"
  if (m) return `resources/js/Pages/${m[1]}.tsx`;
  return null;
}
// tokens de MOCKUP citados em component: — só .jsx, e NUNCA o genérico Index.jsx,
// nem .jsx que faça parte de um path de repo (por isso removo os paths antes).
function extractMockupFiles(text) {
  if (!text) return [];
  const cleaned = text.replace(/resources\/js\/Pages\/[\w./-]+/g, ' ');
  return [...cleaned.matchAll(/[\w.-]+\.jsx/g)].map((m) => m[0]).filter((t) => !/^index\.jsx$/i.test(t));
}
function frontmatter(src) {
  if (!src) return {};
  const m = src.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  if (!m) return {};
  const fm = {};
  for (const line of m[1].split(/\r?\n/)) {
    const mm = line.match(/^([a-z_]+):\s*(.*)$/i);
    if (mm) fm[mm[1].toLowerCase()] = mm[2].trim();
  }
  return fm;
}

function isScreenSource(relPath) {
  const b = basename(relPath);
  if (/-page\.jsx$/i.test(b)) return 'mockup';
  if (/^Index\.tsx$/i.test(b)) return 'format2';
  return null;
}

// ---- núcleo: monta o manifesto (usado por run E selftest, sem drift) ------
async function buildManifest({ staging, repoRoot }) {
  const stagingFiles = await walk(staging);
  const pagesRoot = join(repoRoot, 'resources', 'js', 'Pages');
  const repoFiles = await walk(pagesRoot);

  // índice de charters dos DOIS universos (mockup .jsx → repo_alvo)
  const repoCharters = repoFiles.filter((f) => f.endsWith('.charter.md'));
  const byMockup = new Map();
  for (const cf of [...stagingFiles.filter((f) => f.endsWith('.charter.md')), ...repoCharters]) {
    const fm = frontmatter(await read(cf));
    const alvo = extractRepoPath(fm.repo_alvo) || extractRepoPath(fm.component) || extractRepoPath(fm.page);
    if (!alvo) continue;
    for (const mk of extractMockupFiles(fm.component)) if (!byMockup.has(mk)) byMockup.set(mk, alvo);
  }

  // índice de sufixo do repo: "<dir>/index.tsx" (lower) → [rel paths]
  const suffixIdx = new Map();
  for (const f of repoFiles) {
    if (basename(f).toLowerCase() !== 'index.tsx') continue;
    const r = relative(repoRoot, f).replace(/\\/g, '/');
    const key = (basename(dirname(f)) + '/index.tsx').toLowerCase();
    (suffixIdx.get(key) || suffixIdx.set(key, []).get(key)).push(r);
  }

  const aliasFor = (b) => ALIAS.find((x) => x.re.test(b));

  const rows = [];
  for (const f of stagingFiles) {
    const relStaging = relative(staging, f).replace(/\\/g, '/');
    const kind = isScreenSource(relStaging);
    if (!kind) continue;
    const b = basename(f);
    let alvo = null, via = null, ambiguo = false;

    // (1) path-espelhado (prototipo-ui-patch/resources/js/Pages/...)
    const mIdx = relStaging.match(/resources\/js\/Pages\/[\w./-]+\.tsx$/);
    if (mIdx) { alvo = mIdx[0]; via = 'path-espelhado'; }

    // (2)/(3) format-2
    if (!alvo && kind === 'format2') {
      const sib = join(dirname(f), 'Index.charter.md');
      if (existsSync(sib)) {
        const fm = frontmatter(await read(sib));
        alvo = extractRepoPath(fm.component) || extractRepoPath(fm.page) || extractRepoPath(fm.repo_alvo);
        if (alvo) via = 'charter-irmão';
      }
      if (!alvo) {
        const key = (basename(dirname(f)) + '/index.tsx').toLowerCase();
        const cand = suffixIdx.get(key) || [];
        if (cand.length === 1) { alvo = cand[0]; via = 'repo-suffix'; }
        else if (cand.length > 1) { ambiguo = true; via = 'repo-suffix(ambíguo)'; }
      }
    }

    // (4) mockup via charter.component
    if (!alvo && kind === 'mockup' && byMockup.has(b)) { alvo = byMockup.get(b); via = 'charter.component'; }
    // (5) ALIAS
    if (!alvo) { const a = aliasFor(b); if (a) { alvo = a.alvo; via = 'alias'; } }

    // correção de charter STALE: alvo não existe mas o ALIAS aponta pra um que existe
    if (alvo && !existsSync(join(repoRoot, alvo))) {
      const a = aliasFor(b);
      if (a && existsSync(join(repoRoot, a.alvo))) { alvo = a.alvo; via = (via || '') + '→alias(corrige stale)'; }
    }

    // status + classe + tarefa
    let status, classe = '—', tarefa, diff = '';
    if (ambiguo) {
      status = 'AMBIGUO';
      tarefa = 'desambiguar: >1 alvo `/<dir>/Index.tsx` no repo — fixe via charter component/repo_alvo';
    } else if (!alvo && isACriar(b)) {
      status = 'A-CRIAR'; via = 'registro a-criar';
      tarefa = 'tela nascente registrada (sem tela viva ainda) — vira SEMANTICO quando criada via MWART';
    } else if (!alvo) {
      status = 'ORFAO'; via = 'nenhum';
      tarefa = 'RESOLVER: adicionar ALIAS ou charter com repo_alvo (gate falha até resolver)';
    } else {
      const alvoAbs = join(repoRoot, alvo);
      classe = /\.jsx$/i.test(b) ? 'semântico' : 'diffável';
      if (!existsSync(alvoAbs)) {
        status = 'ALVO-PENDENTE';
        tarefa = 'criar via MWART (alvo declarado não existe — ex: "a criar na F3" / nome errado)';
      } else if (classe === 'semântico') {
        status = 'SEMANTICO';
        tarefa = 'Fase 1 → <tela>.map.json (mockup .jsx ≠ .tsx, diff textual = ruído)';
      } else {
        const A = ((await read(alvoAbs)) ?? '').replace(/\r/g, '');
        const C = ((await read(f)) ?? '').replace(/\r/g, '');
        if (A === C) { status = 'IDENTICO'; diff = '+0 -0'; tarefa = 'no-op'; }
        else {
          diff = numstat(alvoAbs, f);
          status = 'ALTERADO';
          tarefa = 'inspecionar delta — pode ser REGRIDE-SE-APLICAR (delta piora o vivo)';
        }
      }
    }
    rows.push({ arquivo: relStaging, alvo: alvo || '—', via, classe, status, diff, tarefa });
  }
  rows.sort((a, b) => a.status.localeCompare(b.status) || a.arquivo.localeCompare(b.arquivo));
  return rows;
}

function numstat(a, b) {
  // existsSync já garantido antes; git diff sai 1 quando difere (normal, vem no stdout)
  try {
    const out = execFileSync('git', ['diff', '--no-index', '--numstat', a, b], { encoding: 'utf8' }).trim();
    const m = out.split(/\s+/); return out ? `+${m[0]} -${m[1]}` : '+0 -0';
  } catch (e) {
    const out = (e.stdout || '').toString().trim(); const m = out.split(/\s+/);
    return out ? `+${m[0]} -${m[1]}` : '+? -?';
  }
}
function tally(rows) { const t = {}; for (const r of rows) t[r.status] = (t[r.status] || 0) + 1; return t; }

async function run({ stagingArg, repoRoot, json, strict }) {
  const staging = resolveStaging(stagingArg);
  if (!staging || !existsSync(staging)) { console.error(`[detectar-telas] staging inválido: ${stagingArg}`); process.exit(2); }
  const rows = await buildManifest({ staging, repoRoot });
  const orfaos = rows.filter((r) => r.status === 'ORFAO' || r.status === 'AMBIGUO');
  const pendentes = rows.filter((r) => r.status === 'ALVO-PENDENTE');

  if (json) {
    console.log(JSON.stringify({ rows, resumo: tally(rows), orfaos: orfaos.length, pendentes: pendentes.length }, null, 2));
  } else {
    console.log(`# detectar-telas — manifesto · staging=${relative(repoRoot, staging).replace(/\\/g, '/') || staging}\n`);
    for (const r of rows) {
      console.log(`  [${r.status.padEnd(13)}] ${r.arquivo}`);
      console.log(`      → ${r.alvo}  (${r.classe} · via ${r.via}${r.diff ? ' · ' + r.diff : ''})`);
      console.log(`      tarefa: ${r.tarefa}`);
    }
    console.log('\n  resumo: ' + Object.entries(tally(rows)).map(([k, v]) => `${k}=${v}`).join(' · '));
  }

  if (orfaos.length) {
    console.error(`\nGATE FALHOU: ${orfaos.length} screen-source(s) ÓRFÃO/AMBÍGUO — telas perdidas em silêncio se ignorar:`);
    for (const r of orfaos) console.error(`  ✗ [${r.status}] ${r.arquivo} — resolva no ALIAS de detectar-telas.mjs ou via charter repo_alvo.`);
  }
  if (strict && pendentes.length) console.error(`\nGATE (--strict): ${pendentes.length} ALVO-PENDENTE.`);
  const fail = orfaos.length + (strict ? pendentes.length : 0);
  if (!fail) console.log('\nOK — 0 telas órfãs. Todo screen-source do bundle resolve a um alvo.');
  return fail ? 1 : 0;
}

// ---- self-test (fixture hermético commitado) ------------------------------
async function selftest() {
  const fx = join(HERE, 'fixtures', 'detectar-telas');
  if (!existsSync(join(fx, 'staging')) || !existsSync(join(fx, 'repo'))) {
    console.error('[selftest] fixture ausente em prototipo-ui/fixtures/detectar-telas/'); process.exit(2);
  }
  const rows = await buildManifest({ staging: join(fx, 'staging'), repoRoot: join(fx, 'repo') });
  const by = (re) => rows.find((r) => re.test(r.arquivo));
  // contrato esperado = o que "fazer direito" significa neste fixture
  const checks = [
    ['vendas-create (charter-less → ALIAS, P0 LOCK)', by(/vendas-create-page\.jsx$/), 'SEMANTICO'],
    ['vendas-page (via charter.component)',            by(/(^|\/)vendas-page\.jsx$/),  'SEMANTICO'],
    ['mistero (sem charter nem alias → não some)',     by(/mistero-page\.jsx$/),       'ORFAO'],
    ['Conciliacao format-2 idêntico',                  by(/Conciliacao\/Index\.tsx$/), 'IDENTICO'],
    ['Caixa format-2 alterado',                        by(/Caixa\/Index\.tsx$/),       'ALTERADO'],
  ];
  // Q5: registro a-criar é não-cego (forja registrado ≠ mistero desconhecido)
  if (isACriar('forja-page.jsx') !== true) { console.log('  [FAIL] forja-page.jsx deveria ser A-CRIAR registrado'); }
  if (isACriar('mistero-page.jsx') !== false) { console.log('  [FAIL] mistero-page.jsx NÃO pode ser A-CRIAR (segue órfão-cego)'); }
  let fails = (isACriar('forja-page.jsx') ? 0 : 1) + (isACriar('mistero-page.jsx') ? 1 : 0);
  for (const [label, row, exp] of checks) {
    const got = row ? row.status : '(ausente)';
    const ok = got === exp; if (!ok) fails++;
    console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${label} → esperado ${exp}, obtido ${got}`);
  }
  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — P0 lock + órfão detectado + diff diffável correto.');
  process.exit(fails ? 1 : 0);
}

// ---- main -----------------------------------------------------------------
const argv = process.argv.slice(2);
const has = (f) => argv.includes(f);
const val = (f, d) => { const i = argv.indexOf(f); return i >= 0 && argv[i + 1] ? argv[i + 1] : d; };
if (has('--selftest')) await selftest();
else process.exit(await run({
  stagingArg: val('--staging', null),
  repoRoot: resolve(val('--repo', REPO_DEFAULT)),
  json: has('--json'),
  strict: has('--strict'),
}));
