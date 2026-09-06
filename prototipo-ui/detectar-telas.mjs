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
//   (1) path-espelhado: staging contém a raiz de Pages do core OU de `Modules/<X>`
//   (2) format-2 `<dir>/Index.tsx` → `component:`/`page:` do `<dir>/Index.charter.md` irmão (path de repo)
//   (3) format-2 sem charter → lookup por sufixo no repo (`/<dir>/Index.tsx` único)
//   (4) mockup → TODOS os charters que o citam → um alvo por tela (relação 1:N)
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

import { existsSync } from 'node:fs';
import { join, resolve, dirname, basename, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import { read, frontmatter, walk } from './_lib-charter.mjs';
import { pageNamespacePath, raizesDePages } from '../scripts/qa/page-path.mjs';

const HERE = dirname(fileURLToPath(import.meta.url)); // prototipo-ui/
const REPO_DEFAULT = resolve(HERE, '..');             // raiz do repo

// ---- ALIAS map (CÓDIGO, não prosa) ----------------------------------------
// Mockups do bundle que NÃO têm charter-âncora. Aterrado no repo real 2026-06-24
// (compras→Compras NÃO Purchases; clientes/crm→Cliente, CRM/Index não existe).
// Extensível: mockup novo sem alvo → ORFAO (gate falha) → adicione a entrada aqui
// CONSCIENTEMENTE (o ponto do mecanismo: nada some sem decisão).
// 2026-06-30 (musing-elion): ALIAS encolheu de 7→2 entradas. O resto migrou pro CHARTER
// (campo `bundle_source:` — financeiro/vendas/produtos/kb/recurring/clientes — + `related_prototype:`
// do Compras). ALIAS agora é SÓ fallback pra mockup genuinamente SEM charter que o nomeie:
//   - vendas-create: a tela Sells/Create não tem charter no bundle (P0 documentado).
//   - crm: alvo DISPUTADO (é funil de deals → Modules/Crm, não Cliente) — fica fallback até decidir.
// Mockup novo sem charter E sem alias → ORFAO (gate falha) → resolva no charter (preferido) ou aqui.
const ALIAS = [
  { re: /^vendas-create-page\.jsx$/i, alvo: 'resources/js/Pages/Sells/Create.tsx', tela: 'Venda (Sells/Create) — charter-less' },
  { re: /^crm-page\.jsx$/i,           alvo: 'resources/js/Pages/Cliente/Index.tsx', tela: 'CRM (alvo disputado → Modules/Crm)' },
  // 2026-09-06 [C]: suporte-page.jsx é porte REVERSO das telas vivas Pages/Suporte/{Empresas,Visao}.
  // Fica AQUI (e não em `bundle_source:` no charter) porque memory/requisitos/Suporte/SPEC.md não
  // tem nenhuma US-* — tocar o charter acorda o charter-us-lint (no-new-lie) e não existe
  // related_us honesto pra declarar. Quando o SPEC ganhar US, promova pro charter e apague isto.
  { re: /^suporte-page\.jsx$/i,       alvo: 'resources/js/Pages/Suporte/Empresas.tsx', tela: 'Suporte — Empresas (Visao herda o mesmo mockup; SPEC sem US)' },
];

// ---- A_CRIAR (CÓDIGO) — mockups CONSCIENTEMENTE registrados como "tela ainda não existe" -----
// NÃO é alvo inventado: é o oposto do órfão-cego. Reconhece "este mockup é de um módulo
// nascente, target a-criar via MWART" → classifica A-CRIAR (NÃO trava o gate), em vez de ORFAO
// (trava) ou de um repo_alvo chutado (LICOES_F3). Quando a tela for criada, ganha charter e
// graduа sozinha pra SEMANTICO. Mockup NOVO fora desta lista E sem charter ainda → ORFAO (gate
// falha) — a fail-closed continua: só some o que foi reconhecido à mão. Origem: Q5 2026-06-30.
const A_CRIAR = [
  /^boletos-page\.jsx$/i, /^equipe-page\.jsx$/i, /^forja-page\.jsx$/i,
  /^inbox-page\.jsx$/i, /^orc-page\.jsx$/i, /^os-page\.jsx$/i, /^perfil-page\.jsx$/i,
  /^pg-cobranca-page\.jsx$/i, /^pg-payment-gateways-page\.jsx$/i, /^producao-page\.jsx$/i,
  /^cobranca-page\.jsx$/i, /^payment-gateways-page\.jsx$/i, /^usuarios-page\.jsx$/i,
  // 2026-09-06 [C] — 15 órfãos do bundle 5023b274 resolvidos CONSCIENTEMENTE (o `status.mjs
  // --check-mapping` saía rc 1 desde 24/08 por eles). Cada um é mockup de tela que hoje só
  // existe em Blade/legado ou não existe — medido com `ls Pages/**` + `git grep` nas rotas:
  //   catalogo-qr → Modules/ProductCatalogue (Blade) · cms → ADMIN do Modules/Cms (Blade; o
  //   Pages/Site do módulo é o site público, outra tela) · comissionados →
  //   resources/views/sales_commission_agent · comissoes → relatório de comissão (Blade) ·
  //   configuracoes → resources/views/business/settings.blade.php · connector → Modules/Connector
  //   (Blade; EPIC MWART em voo) · documentacao → resources/views/documentacao (migração em voo) ·
  //   funcoes → roles (Blade) · notificacoes → notification-templates (Blade) · patrimonio →
  //   Modules/AssetManagement/Resources/views · planilhas → spreadsheet (Blade) · prefs → só
  //   POST /user/preferences/* (sem tela) · programa-doc → sem tela · relatorios →
  //   resources/views/report/* · voz-do-cliente → Modules/VozDoCliente/Resources/views.
  // Quando cada uma nascer via MWART, ganha charter com `bundle_source:` e gradua sozinha.
  /^catalogo-qr-page\.jsx$/i, /^cms-page\.jsx$/i, /^comissionados-page\.jsx$/i, /^comissoes-page\.jsx$/i,
  /^configuracoes-page\.jsx$/i, /^connector-page\.jsx$/i, /^documentacao-page\.jsx$/i, /^funcoes-page\.jsx$/i,
  /^notificacoes-page\.jsx$/i, /^patrimonio-page\.jsx$/i, /^planilhas-page\.jsx$/i, /^prefs-page\.jsx$/i,
  /^programa-doc-page\.jsx$/i, /^relatorios-page\.jsx$/i, /^voz-do-cliente-page\.jsx$/i,
];
export function isACriar(b) { return A_CRIAR.some((re) => re.test(b)); }

// Guard estrutural (2026-06-30): A_CRIAR é uma válvula de escape do gate fail-closed.
// Quando uma entrada está ERRADA (mockup de módulo VIVO marcado como nascente), ela
// reintroduz o silêncio que a máquina existe pra matar — foi o que aconteceu com
// financeiro-page (alvo vivo Unificado). Defesa: extrai o "stem" do mockup (basename
// menos `-page.jsx`) e cruza com os diretórios vivos de Pages. Bate → ADVISORY (não
// auto-fail: nascente homônimo legítimo existe, ex. cobranca-page pode ser PaymentGateway).
// Sobe pra revisão consciente em vez de sumir. Mesma lição do ORFAO (17/24 órfãos).
export function acriarStem(b) { return basename(b).replace(/-page\.jsx$/i, '').toLowerCase(); }
export function liveDirNames(repoFiles, repoRoot) {
  const names = new Set();
  for (const f of repoFiles) {
    const rel = pageNamespacePath(relative(repoRoot, f).replace(/\\/g, '/'));
    for (const seg of rel.split('/').slice(0, -1)) names.add(seg.toLowerCase());
  }
  return names;
}

// read/walk/frontmatter vêm de _lib-charter.mjs (fonte única com ancora.mjs)

function resolveStaging(p) {
  if (!p) return null;
  if (existsSync(join(p, 'project'))) return join(p, 'project');
  return p;
}

// extrai o token de path-de-repo de um texto livre (component:/page:/repo_alvo:)
function extractRepoPath(text) {
  if (!text) return null;
  const normalized = text.replace(/\\/g, '/');
  // A Page pode morar no núcleo OU no módulo nWidart. O parser antigo só aceitava
  // `resources/js/Pages` e mutilava `Modules/Superadmin/Resources/...` no fallback.
  let m = normalized.match(/(?:Modules\/[^/\s]+\/[Rr]esources|resources)\/js\/Pages\/[\w./-]+\.tsx/);
  if (m) return m[0];
  m = normalized.match(/\b([A-Z][\w]+(?:\/[A-Z][\w]+)+)\b/); // "Sells/Index", "Purchases/Index"
  if (m) return `resources/js/Pages/${m[1]}.tsx`;
  return null;
}
// tokens de MOCKUP citados em component: — só .jsx, e NUNCA o genérico Index.jsx,
// nem .jsx que faça parte de um path de repo (por isso removo os paths antes).
function extractMockupFiles(text) {
  if (!text) return [];
  const cleaned = text.replace(/(?:Modules\/[^/\s]+\/[Rr]esources|resources)\/js\/Pages\/[\w./-]+/g, ' ');
  return [...cleaned.matchAll(/[\w.-]+\.jsx/g)].map((m) => m[0]).filter((t) => !/^index\.jsx$/i.test(t));
}
// frontmatter() — ver _lib-charter.mjs (importado acima, fonte única)

// módulo declarado pelo PRÓPRIO arquivo no header @memcofre (desambigua format-2).
// O bundle usa DUAS convenções coexistentes (2026-07-07, teste de protocolo Financeiro):
//   multi-linha:  `//   module: Financeiro`       (10 arquivos)
//   linha-única:  `// @memcofre tela=… module=Financeiro status=…`  (8 arquivos)
// O regex antigo só via `module:` logo após `//` → os 8 com `module=` no meio da linha
// caíam em null → Caixa/Index.tsx (2 alvos Financeiro/Caixa + Sells/Caixa) virava falso
// AMBIGUO e derrubava o gate inteiro (exit 1). Aceita `:` OU `=`, module em qualquer
// ponto de uma linha de comentário `//`, e para o valor no 1º não-word (não engole `status=`).
export function memcofreModule(src) {
  if (!src) return null;
  const m = src.match(/\/\/[^\n]*\bmodule\s*[:=]\s*([A-Za-z][\w-]*)/i);
  return m ? m[1].toLowerCase() : null;
}

function isScreenSource(relPath) {
  const b = basename(relPath);
  if (/-page\.jsx$/i.test(b)) return 'mockup';
  if (/^Index\.tsx$/i.test(b)) return 'format2';
  return null;
}

// ---- núcleo: monta o manifesto (usado por run E selftest, sem drift) ------
export async function buildManifest({ staging, repoRoot }) {
  const stagingFiles = await walk(staging);
  // Mesmas duas classes de raiz que o resolver Inertia: núcleo + módulos. Sem isto,
  // um protótipo do Superadmin/Officeimpresso não encontrava nem o charter nem o .tsx alvo.
  const repoFiles = (await Promise.all(raizesDePages(repoRoot).map((pagesRoot) => walk(pagesRoot)))).flat();

  // índice de charters dos DOIS universos (mockup .jsx → repo_alvo)
  // 2026-06-30 (musing-elion): o link mockup↔tela mora no CHARTER, mas vinha sendo lido só de
  // `component:` (que carrega o .tsx, não o .jsx). O bundle nomeia o mockup pela RAIZ do módulo
  // (financeiro-page) e a tela vive numa sub-pasta (Unificado) → nem `component`-mining nem a
  // heurística startsWith(dir) do ancora.mjs casavam. Campo estruturado dedicado `bundle_source:`
  // (lido também pelo ancora.mjs) carrega o -page.jsx do bundle SEM tocar o related_prototype
  // (que é o design APROVADO). related_prototype apontando -page.jsx (convenção compras/oficina)
  // também conta. Charter-first de verdade — ALIAS vira só fallback pra mockup SEM charter.
  const repoCharters = repoFiles.filter((f) => f.endsWith('.charter.md'));
  const byMockup = new Map(); // mockup .jsx → list<repo target>; Superadmin/Officeimpresso são 1:N
  for (const cf of [...stagingFiles.filter((f) => f.endsWith('.charter.md')), ...repoCharters]) {
    const fm = frontmatter(await read(cf));
    const alvo = extractRepoPath(fm.repo_alvo) || extractRepoPath(fm.component) || extractRepoPath(fm.page);
    if (!alvo) continue;
    // mockups citados em campos estruturados. visual_source é convenção PRÉ-EXISTENTE (8 charters,
    // ex: CaixaUnificada → inbox-page) que a máquina não lia — mesma raiz do bug financeiro (a verdade
    // estava no charter, no campo errado). bundle_source = alias explícito (musing-elion). Lê os 4.
    const mockupsDeclarados = [
      ...extractMockupFiles(fm.component),
      ...extractMockupFiles(fm.bundle_source),
      ...extractMockupFiles(fm.visual_source),
      ...extractMockupFiles(fm.related_prototype),
    ];
    for (const mk of mockupsDeclarados) {
      const atuais = byMockup.get(mk) || [];
      if (!atuais.includes(alvo)) atuais.push(alvo);
      byMockup.set(mk, atuais);
    }
  }
  // ALVO QUE EXISTE GANHA (musing-elion 2026-06-30), sem destruir o 1:N legítimo:
  // se há alvos vivos, descarta só os stale; se nenhum existe, mantém os pendentes para reportar.
  for (const [mk, alvos] of byMockup) {
    const vivos = alvos.filter((alvo) => existsSync(join(repoRoot, alvo)));
    byMockup.set(mk, vivos.length ? vivos : alvos);
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
    let alvo = null, alvos = null, via = null, ambiguo = false;

    // (1) path-espelhado (núcleo OU Modules/<X>/Resources/js/Pages/...)
    const mIdx = relStaging.match(/(?:Modules\/[^/]+\/[Rr]esources|resources)\/js\/Pages\/[\w./-]+\.tsx$/);
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
        else if (cand.length > 1) {
          // desambigua pelo módulo que o PRÓPRIO arquivo declara (@memcofre), não chuta
          const mod = memcofreModule(await read(f));
          const picked = mod ? cand.filter((c) => c.toLowerCase().includes(`/${mod}/`)) : [];
          if (picked.length === 1) { alvo = picked[0]; via = 'memcofre-disambig'; }
          else { ambiguo = true; via = 'repo-suffix(ambíguo)'; }
        }
      }
    }

    // (4) mockup via charter.component
    if (!alvo && kind === 'mockup' && byMockup.has(b)) { alvos = byMockup.get(b); via = 'charter.component'; }
    // (5) ALIAS
    if (!alvo && !alvos?.length) { const a = aliasFor(b); if (a) { alvo = a.alvo; via = 'alias'; } }

    // correção de charter STALE: alvo não existe mas o ALIAS aponta pra um que existe
    if (alvo && !existsSync(join(repoRoot, alvo))) {
      const a = aliasFor(b);
      if (a && existsSync(join(repoRoot, a.alvo))) { alvo = a.alvo; via = (via || '') + '→alias(corrige stale)'; }
    }

    // Um único mockup pode representar várias views internas. Emite UMA linha por alvo,
    // preservando a lista completa do que precisa receber aplicação semântica.
    const candidatos = alvos?.length ? alvos : [alvo];
    for (const alvoCandidato of candidatos) {
      let status, classe = '—', tarefa, diff = '', viaCandidato = via;
      if (ambiguo) {
        status = 'AMBIGUO';
        tarefa = 'desambiguar: >1 alvo `/<dir>/Index.tsx` no repo — fixe via charter component/repo_alvo';
      } else if (!alvoCandidato && isACriar(b)) {
        status = 'A-CRIAR'; viaCandidato = 'registro a-criar';
        tarefa = 'tela nascente registrada (sem tela viva ainda) — vira SEMANTICO quando criada via MWART';
      } else if (!alvoCandidato) {
        status = 'ORFAO'; viaCandidato = 'nenhum';
        tarefa = 'RESOLVER: adicionar ALIAS ou charter com repo_alvo (gate falha até resolver)';
      } else {
        const alvoAbs = join(repoRoot, alvoCandidato);
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
      rows.push({ arquivo: relStaging, alvo: alvoCandidato || '—', via: viaCandidato, classe, status, diff, tarefa });
    }
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

  // ADVISORY estrutural: A-CRIAR com diretório vivo homônimo → suspeito de mapeamento perdido
  const repoPageFiles = (await Promise.all(raizesDePages(repoRoot).map((root) => walk(root)))).flat();
  const liveDirs = liveDirNames(repoPageFiles, repoRoot);
  const suspeitos = rows
    .filter((r) => r.status === 'A-CRIAR' && liveDirs.has(acriarStem(r.arquivo)))
    .map((r) => ({ arquivo: r.arquivo, stem: acriarStem(r.arquivo) }));

  if (json) {
    console.log(JSON.stringify({ rows, resumo: tally(rows), orfaos: orfaos.length, pendentes: pendentes.length, acriar_suspeitos: suspeitos }, null, 2));
  } else {
    console.log(`# detectar-telas — manifesto · staging=${relative(repoRoot, staging).replace(/\\/g, '/') || staging}\n`);
    for (const r of rows) {
      console.log(`  [${r.status.padEnd(13)}] ${r.arquivo}`);
      console.log(`      → ${r.alvo}  (${r.classe} · via ${r.via}${r.diff ? ' · ' + r.diff : ''})`);
      console.log(`      tarefa: ${r.tarefa}`);
    }
    console.log('\n  resumo: ' + Object.entries(tally(rows)).map(([k, v]) => `${k}=${v}`).join(' · '));
  }

  if (suspeitos.length) {
    console.error(`\n⚠ ADVISORY: ${suspeitos.length} A-CRIAR com módulo VIVO homônimo — revise se não é mapeamento perdido (foi o bug financeiro-page):`);
    for (const s of suspeitos) console.error(`  ⚠ ${s.arquivo} — existe Pages/.../${s.stem}/ vivo. Se mapeia tela viva → mova pro ALIAS; se é tela nova num módulo vivo → confirme A-CRIAR consciente.`);
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
    ['financeiro-page (via charter bundle_source, SEM alias)', by(/financeiro-page\.jsx$/), 'SEMANTICO'],
    ['superadmin-page (charter em Modules/**/Pages)', by(/superadmin-page\.jsx$/), 'SEMANTICO'],
    ['mistero (sem charter nem alias → não some)',     by(/mistero-page\.jsx$/),       'ORFAO'],
    ['Conciliacao format-2 idêntico',                  by(/Conciliacao\/Index\.tsx$/), 'IDENTICO'],
    ['Caixa format-2 alterado',                        by(/Caixa\/Index\.tsx$/),       'ALTERADO'],
  ];
  const superadminTargets = rows.filter((r) => /superadmin-page\.jsx$/.test(r.arquivo)).map((r) => r.alvo).sort();
  const superadmin1nOk = superadminTargets.length === 2
    && superadminTargets.includes('Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx')
    && superadminTargets.includes('Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/Index.tsx');
  if (!superadmin1nOk) console.log(`  [FAIL] superadmin-page 1:N perdeu alvo: ${JSON.stringify(superadminTargets)}`);
  // Q5: registro a-criar é não-cego (forja registrado ≠ mistero desconhecido)
  if (isACriar('forja-page.jsx') !== true) { console.log('  [FAIL] forja-page.jsx deveria ser A-CRIAR registrado'); }
  if (isACriar('mistero-page.jsx') !== false) { console.log('  [FAIL] mistero-page.jsx NÃO pode ser A-CRIAR (segue órfão-cego)'); }
  // 2026-06-30 (musing-elion): financeiro-page NÃO pode voltar pra A_CRIAR (silenciou o ledger vivo).
  // Resolve via CHARTER (bundle_source), NÃO via ALIAS — o caso 'financeiro-page' nos `checks` acima
  // prova a resolução SEMANTICO charter-first. Aqui só travamos que não há regressão pra A_CRIAR/ALIAS.
  const finOutOfACriar = isACriar('financeiro-page.jsx') === false;
  const finSemAlias = !ALIAS.some((a) => a.re.test('financeiro-page.jsx')); // charter-first: sem band-aid
  if (!finOutOfACriar) console.log('  [FAIL] financeiro-page.jsx NÃO pode ser A-CRIAR (mapeia o ledger vivo Unificado)');
  if (!finSemAlias) console.log('  [FAIL] financeiro-page.jsx deve resolver via charter bundle_source, não ALIAS');
  // guard estrutural: stem do mockup cruza com dir vivo homônimo
  const guardOk = acriarStem('financeiro-page.jsx') === 'financeiro'
               && liveDirNames(['/x/resources/js/Pages/Financeiro/Unificado/Index.tsx'], '/x').has('financeiro');
  if (!guardOk) console.log('  [FAIL] guard A-CRIAR×dir-vivo (acriarStem/liveDirNames) quebrado');
  // desambiguação por @memcofre (format-2 ambíguo resolve pelo módulo declarado no arquivo)
  const mcOk = memcofreModule('// @memcofre\n//   tela: /financeiro\n//   module: Financeiro\n') === 'financeiro'
            // linha-única `module=` seguido de `status=` (Caixa/Categorias/… — 8 arquivos do bundle)
            && memcofreModule('// @memcofre tela=/financeiro/caixa module=Financeiro status=x\n') === 'financeiro'
            && memcofreModule('sem header') === null;
  if (!mcOk) console.log('  [FAIL] memcofreModule deveria extrair module de `:` E `=` (Caixa era falso AMBIGUO)');
  let fails = (isACriar('forja-page.jsx') ? 0 : 1) + (isACriar('mistero-page.jsx') ? 1 : 0) + (mcOk ? 0 : 1)
            + (superadmin1nOk ? 0 : 1)
            + (finOutOfACriar ? 0 : 1) + (finSemAlias ? 0 : 1) + (guardOk ? 0 : 1);
  for (const [label, row, exp] of checks) {
    const got = row ? row.status : '(ausente)';
    const ok = got === exp; if (!ok) fails++;
    console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${label} → esperado ${exp}, obtido ${got}`);
  }
  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — P0 lock + órfão detectado + diff diffável correto.');
  process.exit(fails ? 1 : 0);
}

// ---- main -----------------------------------------------------------------
if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
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
}
