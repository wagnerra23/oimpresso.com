#!/usr/bin/env node
// ciclo-completo.mjs — GATE "a tela nasceu (e segue) COMPLETA?" (Constituição UI v2 · UI-0013).
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// Wagner 2026-07-11: "fazer na mão é sorteio e não garante funcionamento". O gerador
// criar-tela.mjs faz a tela NASCER completa (carimbada do Padrão de Tela); este gate é o
// outro lado — GARANTE que toda tela viva TEM o conjunto obrigatório do ciclo, senão é
// "incompleta". É a catraca que impede a tela de degradar depois de nascer.
//
// Conjunto obrigatório por tela (resources/js/Pages/**/*.tsx roteada):
//   1. charter   — <Tela>.charter.md existe (o contrato de design/produto)
//   2. PT declarado — o charter declara qual Padrão de Tela herda (related_prototype: PT-0X)
//   3. pt-conforme — a tela TEM a assinatura do PT que declara (consome pt-conformance --json)
//   4. casos     — <Tela>.casos.md existe (o contrato de teste · ADR 0264 G-1)
//   5. teste     — o casos.md referencia ≥1 teste/E2E (a rastreabilidade caso↔teste)
//   6. golden-live — o golden do PT herdado está `live` (GOLDEN-LIVE: tela não fecha o ciclo
//                    se o golden do padrão dela ainda é draft → força o Design a terminar)
//   7. alcance   — o HUMANO chega na tela: rota nomeada → permission → menu → pacote
//                  (ver o bloco ITEM 7 lá embaixo; escopo forward-only, severidade por status)
// Faltou qualquer um → tela INCOMPLETA.
//
// NÃO DUPLICA: consome pt-conformance --json (#3) e ecoa o trio do casos-guard (#1/#4/#5) numa
// visão POR-TELA unificada + as dimensões que ninguém cobria (#2 PT declarado, #6 golden-live,
// #7 alcance). O #7 chegou aqui, e não num `contrato-de-tela --alcance` novo, porque ESTE é o
// gate que se declara dono de "a tela nasceu (e segue) COMPLETA?" — e uma tela que ninguém
// alcança não está completa. Máquina paralela ao dono é a classe LC-19.
//
// ADVISORY de nascença (ADR 0314/0271 — required = só Tier-0; cobertura de ciclo é quality) +
// CATRACA: o nº de telas COMPLETAS só sobe (regressão bloqueia; débito legado é absorvido).
//
// Uso:
//   node scripts/governance/ciclo-completo.mjs            # relatório (read-only) + lado Design + alcance
//   node scripts/governance/ciclo-completo.mjs --json      # grava baseline (catraca)
//   node scripts/governance/ciclo-completo.mjs --check     # exit 1 se `completo` regrediu OU se tela `live` tem alcance quebrado
//   node scripts/governance/ciclo-completo.mjs --selftest  # fixtures herméticas (bite/release) + controle positivo no golden vivo

import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, readdirSync, statSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import { join, dirname, resolve, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { isPageScreenPath, raizesDePages } from '../qa/page-path.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..', '..');
const PAGES = join(ROOT, 'resources', 'js', 'Pages');
const GOLDEN_DIR = join(ROOT, 'memory', 'requisitos', '_DesignSystem', 'padroes-tela');
const BASELINE = join(ROOT, 'memory', 'governance', 'ciclo-completo-baseline.json');
const PT_FILE = {
  'PT-01': 'PT-01-Lista.md', 'PT-02': 'PT-02-Form-Drawer.md', 'PT-03': 'PT-03-Detalhe.md',
  'PT-04': 'PT-04-Dashboard.md', 'PT-05': 'PT-05-Kanban.md',
  'PT-07': 'PT-07-Feed-Timeline.md',
};

// ─────────────────────────────────────────────────────────────────────────────
// coleta
// ─────────────────────────────────────────────────────────────────────────────
const relRoot = (p, root) => resolve(p).replace(resolve(root), '').replace(/^[\\/]/, '').replace(/\\/g, '/');

// "Página roteada" = o que `isPageScreenPath` (scripts/qa/page-path.mjs) reconhece como Page
// Inertia executável. FONTE ÚNICA compartilhada com casos-coverage-guard + screen-coverage-map.
//
// Reconciliação 2026-07-27 (2ª onda, PR desta branch): este filtro conhecia só `_components`
// e `_partials`, mas o repo usa `_show`(13) `_drawer`(10) `components`(8, sem underscore)
// `_shared`(7) `_form`(4) `_Showcase`(2) `_lib`(1) — contava 280 contra 235 das portas de
// cobertura. Como o gate é "a tela tem charter+PT+casos+teste?", cada NÃO-tela contada
// entrava como tela INCOMPLETA e virava débito legado no baseline — ruído que escondia a
// dívida real. O `walk` agora só PODA `node_modules`-like implicitamente (nada a podar) e
// deixa a decisão de "é tela?" com a fonte única, aplicada ao path relativo a `pagesDir`
// (não a ROOT) — o selftest monta um `pagesDir` temporário e precisa da mesma resposta.
function walkPages(pagesDir, rootDir = pagesDir) {
  const out = [];
  if (!existsSync(pagesDir)) return out;
  for (const e of readdirSync(pagesDir)) {
    const p = join(pagesDir, e);
    if (statSync(p).isDirectory()) out.push(...walkPages(p, rootDir));
    else if (isPageScreenPath(relative(rootDir, p).split(sep).join('/'))) out.push(p);
  }
  return out;
}

const fmField = (content, key) => {
  const m = content.match(/^---\s*\n([\s\S]*?)\n---/);
  const fm = m ? m[1] : '';
  const f = fm.match(new RegExp(`^${key}\\s*:\\s*(.+)$`, 'm'));
  return f ? f[1].trim() : null;
};
const declaredPT = (relProto) => {
  const m = (relProto || '').match(/PT-0[1-9]/i);
  return m ? m[0].toUpperCase() : null;
};
// #5 teste: o casos.md aponta pra um teste/E2E real (path de teste em qualquer linha).
const TESTE_RE = /(tests?\/|Modules\/[^\s]+\/Tests?\/|e2e\/|\.spec\.[tj]s|Test\.php)/i;

// pt-conforme (#3): mapa tsxRel → verdict, do pt-conformance --json (join estável via `tsx`).
// Tolerante ao formato antigo (sem `tsx`): cai pra `page`. Injetável no selftest (não shella).
function loadPtMap() {
  try {
    const raw = execFileSync('node', [join(HERE, 'pt-conformance.mjs'), '--json'], { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
    const j = JSON.parse(raw);
    const map = new Map();
    // Indexa por TODA chave disponível: `tsx` (caminho, join estável) E `page` (rota do
    // charter). Tolera o pt-conformance ANTIGO (só `page`) e o NOVO (com `tsx`) — o gate
    // funciona antes e depois do enhance do pt-conformance chegar no main.
    for (const r of j.rows || []) {
      if (r.tsx) map.set(r.tsx, r.verdict);
      if (r.page) map.set(r.page, r.verdict);
    }
    return map;
  } catch (e) {
    console.error(`ciclo-completo: falha ao consumir pt-conformance --json: ${e.message}`);
    process.exit(2);
  }
}

// golden status por PT (#6): lê o frontmatter `status:` de cada golden. Injetável no selftest.
function loadGoldenStatus(goldenDir) {
  const out = {};
  for (const [pt, file] of Object.entries(PT_FILE)) {
    try {
      const s = fmField(readFileSync(join(goldenDir, file), 'utf8'), 'status');
      out[pt] = (s || 'desconhecido').toLowerCase();
    } catch { out[pt] = 'ausente'; }
  }
  return out;
}

// ─────────────────────────────────────────────────────────────────────────────
// ITEM 7 — ALCANCE (rota → permission → menu → pacote)
//
// A camada que nenhum gate de CÓDIGO vê: como o HUMANO chega na tela. Nasceu do caso
// `/arquivos` (2026-08-25) — trio no main, rota `arquivos.index` registrada, 26 testes
// Feature verdes, e em produção NINGUÉM chegava na tela: o `modifyAdminMenu()` do módulo
// era NO-OP, com um comentário afirmando que o módulo "não tem tela própria" (falso desde
// a ADR 0360). Quem pegou foi o [W] a olho, no smoke do sidebar. Gate nenhum reclamou —
// porque alcance não é código React, então pt-conformance/casos-gate/os 6 itens acima
// passam por cima dele. Golden das 3 camadas de habilitação: PR #6245.
//
// ESCOPO (forward-only · ADR 0275). Só entra a tela cujo charter DECLARA o bloco `alcance:`,
// carimbado pelo `criar-tela.mjs` desde a emenda A (PR #6254). Charter SEM o bloco fica FORA
// (legado grandfathered) — e isso é ISENÇÃO DECLARADA, não silêncio: medido em 2026-08-25,
// 0 de 293 charters do repo declaravam `alcance:`, então o guard nasce medindo ZERO tela e o
// relatório IMPRIME esse denominador. Gate que não diz quantos mediu é o fail-open da lápide
// §5 2026-07-29 ("✓ todos os N" tendo medido zero).
//
// SEVERIDADE POR `status:` DO CHARTER — cobra no momento certo:
//   draft      → WARN (rc=0). Trio novo não nasce quebrando o CI enquanto o autor liga a rota.
//   live       → FAIL (rc≠0). "Declarada pronta" e ninguém alcança é exatamente o /arquivos.
//   deprecated → fora de escopo. Cobrar alcance de tela sendo aposentada é ruído.
//
// LIMITE DECLARADO (o que esta sonda NÃO é): ela lê o REPO, não o runtime. Responde "o repo
// declara a rota / a permission / o menu", nunca "o Laravel registrou a rota". O oráculo de
// runtime seria `Route::has()`/`route:list`, e ele NÃO existe neste job (CI de node, sem PHP
// e sem banco) — a prova de runtime mora no Pest irmão
// `tests/Feature/Sidebar/Biz4RotaLivreSidebarTest.php`, com o Laravel bootado. Declarar isso
// é obrigatório: §5 2026-07-17 proíbe deduzir "quem roda" parseando código quando há oráculo;
// aqui não há, então o limite se declara em vez de se esconder.
//
// FORA DE ESCOPO, e declarado pra não parecer cobertura:
//   • a permission estar LIGADA numa role — `<mod>.access` nasce `default: false`, e isso é
//     dado de runtime POR BUSINESS, não de repo. Nenhum arquivo prova. É smoke real (R1).
//   • o campo `alcance.pacote` — o charter o carimba e NENHUM elo abaixo o mede. A camada 1
//     (módulo no `superadmin_package` do business) é assertada do outro lado, no Pest irmão
//     (`'arquivos_module'` + `hasThePermissionInSubscription`). Dizer isto é obrigatório:
//     campo declarado que ninguém lê vira contrato-fantasma, e "6 elos" soaria como as 4
//     camadas inteiras quando são 3 e meia.
// ─────────────────────────────────────────────────────────────────────────────

/** `n/a (razão)` é DECISÃO declarada do autor — o elo correspondente é PULADO, não verde. */
const naComRazao = (v) => /^n\/a\s*\(.+\)/i.test(String(v || '').trim());

/**
 * Lê o bloco YAML aninhado `alcance:` do frontmatter. Não dá pra usar `fmField`: aquele casa
 * `^chave:` na coluna 0, e os campos daqui são INDENTADOS.
 * Devolve `null` quando a tela não declara alcance (fora de escopo).
 */
function parseAlcance(charterTxt) {
  const fm = (String(charterTxt || '').match(/^---\s*\n([\s\S]*?)\n---/) || [null, ''])[1];
  const linhas = fm.split('\n');
  const i = linhas.findIndex((l) => /^alcance:\s*$/.test(l));
  if (i < 0) return null;
  const out = {};
  for (const l of linhas.slice(i + 1)) {
    if (!/^\s+\S/.test(l)) break;                     // saiu do bloco indentado
    const m = l.match(/^\s+([a-z_]+)\s*:\s*(.*)$/i);
    if (!m) continue;
    out[m[1]] = m[2].replace(/\s+#.*$/, '').trim();   // strip comentário inline YAML
  }
  return Object.keys(out).length ? out : null;
}

/**
 * Corpo executável de um método PHP, por balanceamento de chaves. '' quando não existe.
 * É o que separa MENU REAL de FACHADA: o corpo do `modifyAdminMenu` do `/arquivos` era `{}`
 * com um docblock por cima, e "o método existe" (presence-gate · LC-11) dava verde nisso.
 */
function corpoDoMetodo(src, nome) {
  const m = String(src || '').match(new RegExp(`function\\s+${nome}\\s*\\([^)]*\\)\\s*(?::\\s*[\\w\\\\|?]+\\s*)?\\{`));
  if (!m) return '';
  let i = m.index + m[0].length;
  let prof = 1;
  const ini = i;
  while (i < src.length && prof > 0) {
    if (src[i] === '{') prof++;
    else if (src[i] === '}') prof--;
    i++;
  }
  return src.slice(ini, i - 1);
}

/** Sobra do corpo depois de tirar comentários e espaço — vazio (ou só `return;`) ⇒ fachada. */
function corpoEhFachada(corpo) {
  const limpo = String(corpo || '')
    .replace(/\/\*[\s\S]*?\*\//g, '')     // /* ... */ e docblocks
    .replace(/\/\/[^\n]*/g, '')           // // ...
    .replace(/(^|\s)#[^\n]*/g, '$1')      // # ...
    .trim();
  return limpo === '' || /^return\s*;?$/.test(limpo);
}

/**
 * Os 6 elos do alcance. PURO: recebe o corpus já carregado (injetável no selftest, não shella).
 * Devolve um elo por linha com `ok`/`pulado`/`detalhe` — o relatório NOMEIA qual quebrou.
 */
function verificarAlcance(alcance, pageCharter, corpus) {
  const { rotasSrc, permsSrc, lerArquivo } = corpus;
  const rota = alcance.rota || '';
  const nomeRota = alcance.rota_nome || '';
  const perm = alcance.permission || '';
  const hookRaw = alcance.menu_hook || '';
  const hook = hookRaw.split('::')[0];
  const metodo = hookRaw.split('::')[1] || 'modifyAdminMenu';
  const elos = [];
  const add = (id, ok, detalhe, pulado = false) => elos.push({ id, ok, detalhe, pulado });

  // 1. rota existe — `->name('<rota_nome>')` em Modules/*/Routes/ ou routes/
  if (!nomeRota || naComRazao(nomeRota)) add('rota_registrada', true, `pulado — rota_nome: ${nomeRota || '(ausente)'}`, true);
  else add('rota_registrada', rotasSrc.includes(`->name('${nomeRota}')`) || rotasSrc.includes(`->name("${nomeRota}")`),
    `->name('${nomeRota}') em Modules/*/Routes/ ou routes/`);

  // 2. rota casa com `page:` do charter — divergência = charter mentindo sobre a própria URL
  add('rota_casa_page', rota === pageCharter, `alcance.rota (${rota || '(vazio)'}) === page: (${pageCharter || '(vazio)'})`);

  // 3. rota é protegida pela permission declarada.
  //    DUAS formas aceitas, e isso é MEDIDO, não gosto: em 2026-08-25 só 3 de 52 arquivos de
  //    rota usam `can:` no middleware, contra 112 Controllers que autorizam no corpo
  //    (`$this->authorize` / `->can`). Exigir só a 1ª forma reprovaria a convenção dominante e
  //    legítima do repo — o guard sintático que este §5 já matou 4 vezes (allowlist-de-pasta
  //    2026-06-30 · `@scope` 2026-07-09 · vocabulário 2026-07-16 · `toHaveKey` 2026-07-26).
  //    O elo protege "a rota é gateada POR ESSA permission", não uma sintaxe; o relatório
  //    diz QUAL forma valeu, pra ninguém confundir as duas.
  if (!perm || naComRazao(perm)) add('rota_protegida', true, `pulado — permission: ${perm || '(ausente)'}`, true);
  else {
    const naRota = rotasSrc.includes(`can:${perm}`);
    const noCtrl = !naRota && permsSrc.gate.includes(perm);
    add('rota_protegida', naRota || noCtrl,
      naRota ? `can:${perm} no middleware da rota`
        : noCtrl ? `'${perm}' autorizada no Controller (convenção dominante do repo)`
          : `'${perm}' não gateia nem a rota nem o Controller`);
  }

  // 4. permission declarada em algum DataController::user_permissions
  if (!perm || naComRazao(perm)) add('permission_declarada', true, 'pulado — sem permission', true);
  else add('permission_declarada', permsSrc.declaradas.has(perm), `'${perm}' em DataController::user_permissions`);

  // 5. menu existe — o arquivo do hook tem Menu::modify E url('<rota>')
  if (!hook || naComRazao(hookRaw)) add('menu_publica', true, `pulado — menu_hook: ${hookRaw || '(ausente)'}`, true);
  else {
    const src = lerArquivo(hook);
    if (src === null) add('menu_publica', false, `menu_hook aponta pra arquivo inexistente: ${hook}`);
    else add('menu_publica', src.includes('Menu::modify') && (src.includes(`url('${rota}')`) || src.includes(`url("${rota}")`)),
      `Menu::modify + url('${rota}') em ${hook}`);
  }

  // 6. menu NÃO é fachada — corpo do modifyAdminMenu não é vazio/só comentário.
  //    ESTE é o elo que teria pego o /arquivos: o método existia (presence-gate verde) e o
  //    corpo era `{}` sob um docblock afirmando que o módulo não tinha tela própria.
  if (!hook || naComRazao(hookRaw)) add('menu_nao_fachada', true, 'pulado — sem menu_hook', true);
  else {
    const src = lerArquivo(hook);
    if (src === null) add('menu_nao_fachada', false, `arquivo do hook inexistente: ${hook}`);
    else {
      const corpo = corpoDoMetodo(src, metodo);
      add('menu_nao_fachada', corpo !== '' && !corpoEhFachada(corpo),
        corpo === '' ? `${metodo}() não existe em ${hook}`
          : corpoEhFachada(corpo) ? `${metodo}() é FACHADA (corpo vazio/só comentário) — foi o defeito do /arquivos`
            : `${metodo}() publica menu de verdade`);
    }
  }

  return elos;
}

/** Corpus de repo que os elos consultam. Carregado UMA vez, e só quando há tela em escopo. */
function carregarCorpus(root) {
  const phpsDe = (dirs) => {
    const out = [];
    const walk = (d) => {
      if (!existsSync(d)) return;
      for (const e of readdirSync(d)) {
        const p = join(d, e);
        if (statSync(p).isDirectory()) walk(p);
        else if (p.endsWith('.php')) out.push(p);
      }
    };
    dirs.forEach(walk);
    return out;
  };
  const modulesDir = join(root, 'Modules');
  const mods = existsSync(modulesDir) ? readdirSync(modulesDir).map((m) => join(modulesDir, m)) : [];
  const rotaFiles = [
    ...phpsDe([join(root, 'routes')]),
    ...phpsDe(mods.map((m) => join(m, 'Routes'))),
  ];
  const dataCtrls = mods.map((m) => join(m, 'Http', 'Controllers', 'DataController.php')).filter(existsSync);
  const ctrlFiles = [...phpsDe(mods.map((m) => join(m, 'Http', 'Controllers'))), ...phpsDe([join(root, 'app', 'Http', 'Controllers')])];
  const ler = (f) => { try { return readFileSync(f, 'utf8'); } catch { return ''; } };

  // permissions DECLARADAS: só as de dentro do corpo de `user_permissions()`. Ler o arquivo
  // inteiro daria verde pra permission citada em COMENTÁRIO — o presence-gate de novo.
  const declaradas = new Set();
  for (const f of dataCtrls) {
    for (const m of corpoDoMetodo(ler(f), 'user_permissions').matchAll(/'value'\s*=>\s*'([^']+)'/g)) declaradas.add(m[1]);
  }

  return {
    rotasSrc: rotaFiles.map(ler).join('\n'),
    permsSrc: { declaradas, gate: ctrlFiles.map(ler).join('\n') },
    lerArquivo: (rel) => { const p = join(root, rel); return existsSync(p) ? readFileSync(p, 'utf8') : null; },
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// núcleo — classifica cada página (puro, testável)
// ─────────────────────────────────────────────────────────────────────────────
function classifyPage(tsxAbs, { root, ptMap, goldenStatus, corpus }) {
  const dir = dirname(tsxAbs);
  const base = tsxAbs.replace(/\.tsx$/, '').split(/[\\/]/).pop();
  const charterPath = join(dir, `${base}.charter.md`);
  const casosPath = join(dir, `${base}.casos.md`);
  const tsxRel = relRoot(tsxAbs, root);

  const hasCharter = existsSync(charterPath);
  const charterTxt = hasCharter ? readFileSync(charterPath, 'utf8') : '';
  const pt = hasCharter ? declaredPT(fmField(charterTxt, 'related_prototype')) : null;
  const route = hasCharter ? fmField(charterTxt, 'page') : null; // chave de join alternativa (pt-conformance antigo)
  const hasCasos = existsSync(casosPath);
  const casosTxt = hasCasos ? readFileSync(casosPath, 'utf8') : '';

  // pt-conforme: junta por caminho do tsx (pt-conformance novo) OU pela rota `page:` (antigo).
  const conforme = ptMap.get(tsxRel) === 'CONFORME' || (route && ptMap.get(route) === 'CONFORME');

  // ── ITEM 7: alcance ────────────────────────────────────────────────────────
  // Só entra quem DECLARA o bloco `alcance:` (forward-only) e cuja `rota` não é `n/a (razão)`.
  // `deprecated` fica fora: cobrar alcance de tela sendo aposentada é ruído.
  const status = (hasCharter ? fmField(charterTxt, 'status') : null) || 'draft';
  const alcanceDecl = hasCharter ? parseAlcance(charterTxt) : null;
  const emEscopo = !!alcanceDecl && !naComRazao(alcanceDecl.rota || '') && !!(alcanceDecl.rota || '').trim() && status !== 'deprecated';
  const elos = emEscopo ? verificarAlcance(alcanceDecl, route, corpus) : [];
  const elosQuebrados = elos.filter((e) => !e.ok && !e.pulado).map((e) => e.id);
  const alcance = {
    escopo: emEscopo ? 'medido' : (!alcanceDecl ? 'nao_declara' : status === 'deprecated' ? 'deprecated' : 'n/a_declarado'),
    status,
    elos,
    quebrados: elosQuebrados,
    // severidade por status: só `live` faz o gate morder; `draft` avisa e devolve rc=0.
    severidade: !emEscopo || !elosQuebrados.length ? 'ok' : status === 'live' ? 'fail' : 'warn',
  };

  const checks = {
    charter: hasCharter,
    pt_declarado: !!pt,
    pt_conforme: pt ? !!conforme : false,
    casos: hasCasos,
    teste: hasCasos && TESTE_RE.test(casosTxt),
    golden_live: pt ? goldenStatus[pt] === 'live' : false,
    // Tela FORA de escopo não conta como falta — senão os 214 charters legados (0 com bloco
    // `alcance:` em 2026-08-25) virariam incompletos de uma vez, a catraca `completo` cairia de
    // 10 pra 0 e o `--check` trancaria pra sempre por estado HERDADO. É a lápide §5 2026-08-24
    // (predicado ABSOLUTO em vez de DELTA) e a §5 2026-07-12 (tocar legado em massa).
    alcance: !emEscopo || elosQuebrados.length === 0,
  };
  const faltando = Object.entries(checks).filter(([, v]) => !v).map(([k]) => k);
  return { page: tsxRel, pt, checks, faltando, alcance, completo: faltando.length === 0 };
}

function computeRows(root, pagesDir, deps) {
  // `pagesDir` continua sendo a raiz do NÚCLEO — o selftest passa uma fixture por aqui e
  // depende disso. Em uso real varremos as DUAS raízes (PR #5686): sem isso a porta media
  // 169 telas roteadas onde o screen-coverage-map mede 206.
  const raizes = pagesDir === PAGES ? raizesDePages(root) : [pagesDir];
  // Corpus do item 7 é LAZY: enquanto nenhum charter declarar `alcance:` (0 de 293 em
  // 2026-08-25), os getters nunca disparam e o gate não paga a leitura dos ~200 .php.
  const corpus = deps.corpus ?? corpusLazy(root);
  return raizes.flatMap((raiz) => walkPages(raiz)).map((p) => classifyPage(p, { root, ...deps, corpus })).sort((a, b) => a.page.localeCompare(b.page));
}

/** Envelope preguiçoso do corpus: só lê o disco no primeiro elo que precisar dele. */
function corpusLazy(root) {
  let real = null;
  const get = () => (real ??= carregarCorpus(root));
  return {
    get rotasSrc() { return get().rotasSrc; },
    get permsSrc() { return get().permsSrc; },
    lerArquivo: (rel) => get().lerArquivo(rel),
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// SELFTEST — fixtures herméticas: prova que o gate MORDE (incompleta) e SOLTA (completa),
// e que golden-draft SOZINHO reprova (GOLDEN-LIVE). Anti-fantasma (ADR 0256).
// ─────────────────────────────────────────────────────────────────────────────
if (process.argv.includes('--selftest')) {
  let fails = 0;
  const t = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };
  const tmp = join(HERE, `.ciclo-selftest-${process.pid}`);
  const pagesDir = join(tmp, 'resources', 'js', 'Pages');
  const wr = (rel, content) => { const p = join(tmp, rel); mkdirSync(dirname(p), { recursive: true }); writeFileSync(p, content); };
  const charter = (pt) => `---\ncomponent: x\nrelated_prototype: n/a (herda ${pt} X; segue o Padrão de Tela)\n---\n`;
  const casosCom = `---\nowner: w\n---\n## UC-X-01\n- **Teste:** e2e/x.spec.ts\n`;
  const casosSem = `---\nowner: w\n---\n## UC-X-01\n- sem teste\n`;

  try {
    // Mod/Completa — tudo presente, PT-01 conforme, golden live → COMPLETO
    wr('resources/js/Pages/Mod/Completa.tsx', '<DataTable/>');
    wr('resources/js/Pages/Mod/Completa.charter.md', charter('PT-01'));
    wr('resources/js/Pages/Mod/Completa.casos.md', casosCom);
    // Mod/SemCasos — falta casos.md
    wr('resources/js/Pages/Mod/SemCasos.tsx', '<DataTable/>');
    wr('resources/js/Pages/Mod/SemCasos.charter.md', charter('PT-01'));
    // Mod/SemTeste — casos.md sem ref de teste
    wr('resources/js/Pages/Mod/SemTeste.tsx', '<DataTable/>');
    wr('resources/js/Pages/Mod/SemTeste.charter.md', charter('PT-01'));
    wr('resources/js/Pages/Mod/SemTeste.casos.md', casosSem);
    // Mod/GoldenDraft — TUDO ok mas o golden do PT-02 é draft → só isso reprova (GOLDEN-LIVE)
    wr('resources/js/Pages/Mod/GoldenDraft.tsx', 'useForm');
    wr('resources/js/Pages/Mod/GoldenDraft.charter.md', charter('PT-02'));
    wr('resources/js/Pages/Mod/GoldenDraft.casos.md', casosCom);
    // Mod/SemPT — charter não declara PT
    wr('resources/js/Pages/Mod/SemPT.tsx', '<DataTable/>');
    wr('resources/js/Pages/Mod/SemPT.charter.md', `---\ncomponent: x\n---\n`);
    // Dirs AUXILIARES não são tela (fonte única isPageScreenPath — reconciliação 2026-07-27).
    // Antes só `_components`/`_partials` eram podados: `components/` (sem underscore), `_show`,
    // `_drawer`, `_shared`, `_form`, `_lib` entravam como tela INCOMPLETA e viravam débito
    // fantasma. Estes 4 usam os dirs REAIS do repo — se alguém reintroduzir filtro local, a
    // contagem abaixo passa de 6 e este selftest morde.
    wr('resources/js/Pages/Mod/components/Drawer.tsx', 'export default () => null');
    wr('resources/js/Pages/Mod/_drawer/AuditoriaTab.tsx', 'export default () => null');
    wr('resources/js/Pages/Mod/_shared/SubNav.tsx', 'export default () => null');
    wr('resources/js/Pages/Mod/_form/Field.tsx', 'export default () => null');

    const ptMap = new Map([
      ['resources/js/Pages/Mod/Completa.tsx', 'CONFORME'],
      ['resources/js/Pages/Mod/SemCasos.tsx', 'CONFORME'],
      ['resources/js/Pages/Mod/SemTeste.tsx', 'CONFORME'],
      ['resources/js/Pages/Mod/GoldenDraft.tsx', 'CONFORME'],
    ]);
    const goldenStatus = { 'PT-01': 'live', 'PT-02': 'draft', 'PT-03': 'draft', 'PT-04': 'draft', 'PT-05': 'draft' };
    const rows = computeRows(tmp, pagesDir, { ptMap, goldenStatus });
    const by = (n) => rows.find((r) => r.page.endsWith(`/${n}.tsx`));

    t(by('Completa').completo, 'tela com tudo (charter+PT+conforme+casos+teste+golden-live) = COMPLETA');
    t(!by('SemCasos').completo && by('SemCasos').faltando.includes('casos'), 'sem casos.md = INCOMPLETA (falta casos)');
    t(!by('SemTeste').completo && by('SemTeste').faltando.includes('teste'), 'casos sem ref de teste = INCOMPLETA (falta teste)');
    t(!by('GoldenDraft').completo && by('GoldenDraft').faltando.join() === 'golden_live', 'golden DRAFT sozinho reprova (GOLDEN-LIVE)');
    t(!by('SemPT').completo && by('SemPT').faltando.includes('pt_declarado'), 'charter sem PT declarado = INCOMPLETA');
    t(rows.length === 5, `dirs auxiliares (components/_drawer/_shared/_form) NÃO contam como tela (${rows.length} linhas, esperado 5)`);
    t(!rows.some((r) => /\/(components|_drawer|_shared|_form)\//.test(r.page)), 'nenhum arquivo de dir auxiliar entrou nas linhas');
    const completoAtual = rows.filter((r) => r.completo).length;
    t(completoAtual === 1, 'catraca conta 1 completa nas fixtures');
    // catraca (--check): baseline > atual ⇒ regressão bloqueia. Prova a comparação pura.
    t(completoAtual < 2, 'baseline=2 > atual=1 ⇒ regressão detectável (catraca morde)');

    // ═══════════════════════════════════════════════════════════════════════════
    // ITEM 7 — ALCANCE. Bite + controles. Sem o BITE o guard é decorativo: `menu de
    // fachada` é EXATAMENTE o defeito do /arquivos que nenhum gate pegou.
    // ═══════════════════════════════════════════════════════════════════════════
    const charterAlc = (status, rota, extra = {}) => {
      const a = { rota, rota_nome: 'mod.index', permission: 'mod.access', menu_hook: 'DC.php::modifyAdminMenu', pacote: 'mod_module', ...extra };
      const linhas = Object.entries(a).filter(([, v]) => v !== null).map(([k, v]) => `  ${k}: ${v}`);
      return `---\ncomponent: x\npage: ${rota}\nstatus: ${status}\nrelated_prototype: n/a (herda PT-01 X; segue o Padrão de Tela)\nalcance:\n${linhas.join('\n')}\n---\n`;
    };
    // corpus injetado: um módulo COM menu real e o mesmo módulo com o menu de FACHADA.
    const DC_REAL = `<?php class DC {\n  public function user_permissions(): array { return [['value' => 'mod.access']]; }\n  public function modifyAdminMenu(): void {\n    Menu::modify('admin-sidebar-menu', function ($m) { $m->url(url('/mod'), 'Mod'); });\n  }\n}`;
    const DC_FACHADA = `<?php class DC {\n  public function user_permissions(): array { return [['value' => 'mod.access']]; }\n  /** Módulo não tem tela própria — UI entra via Admin Center. */\n  public function modifyAdminMenu(): void\n  {\n    // no-op\n  }\n}`;
    const corpusCom = (dc) => ({
      rotasSrc: `Route::get('/', [C::class,'index'])->middleware('can:mod.access')->name('mod.index');`,
      permsSrc: { declaradas: new Set(['mod.access']), gate: dc },
      lerArquivo: (rel) => (rel === 'DC.php' ? dc : null),
    });

    const rodar = (charterTxt, dc) => {
      const tmp2 = join(HERE, `.alc-${process.pid}`);
      const pd = join(tmp2, 'resources', 'js', 'Pages');
      mkdirSync(join(pd, 'Mod'), { recursive: true });
      writeFileSync(join(pd, 'Mod', 'T.tsx'), '<DataTable/>');
      writeFileSync(join(pd, 'Mod', 'T.charter.md'), charterTxt);
      writeFileSync(join(pd, 'Mod', 'T.casos.md'), casosCom);
      try {
        return computeRows(tmp2, pd, {
          ptMap: new Map([['resources/js/Pages/Mod/T.tsx', 'CONFORME']]),
          goldenStatus: { 'PT-01': 'live', 'PT-02': 'draft', 'PT-03': 'draft', 'PT-04': 'draft', 'PT-05': 'draft' },
          corpus: corpusCom(dc),
        })[0];
      } finally { try { rmSync(tmp2, { recursive: true, force: true }); } catch { /* ignore */ } }
    };

    // ── BITE: menu de FACHADA + status live → reprova NOMEANDO o elo 6, e é FAIL.
    const bite = rodar(charterAlc('live', '/mod'), DC_FACHADA);
    t(bite.alcance.quebrados.includes('menu_nao_fachada'),
      'BITE: modifyAdminMenu de corpo vazio → elo `menu_nao_fachada` quebra (o defeito do /arquivos)');
    t(bite.alcance.severidade === 'fail', 'BITE: charter `live` com elo quebrado ⇒ severidade FAIL (rc≠0)');
    t(!bite.completo && bite.faltando.includes('alcance'), 'BITE: a tela deixa de ser COMPLETA (alcance é o item 7)');
    t(/FACHADA/.test(bite.alcance.elos.find((e) => e.id === 'menu_nao_fachada').detalhe),
      'BITE: o detalhe do elo NOMEIA a fachada (relatório acionável, não "algo falhou")');

    // ── CN-A: a MESMA fixture com status draft → warn, rc=0 (trio novo não quebra o CI).
    const draft = rodar(charterAlc('draft', '/mod'), DC_FACHADA);
    t(draft.alcance.quebrados.includes('menu_nao_fachada'), 'CN-A: draft detecta o MESMO elo quebrado (o guard não fica cego)');
    t(draft.alcance.severidade === 'warn', 'CN-A: charter `draft` ⇒ severidade WARN (rc=0)');

    // ── CN-B: `alcance.rota: n/a (razão)` → não é assunto do guard, não inventa erro.
    const semRota = rodar(charterAlc('live', 'n/a (sub-tela de drawer)'), DC_FACHADA);
    t(semRota.alcance.escopo === 'n/a_declarado' && semRota.alcance.quebrados.length === 0,
      'CN-B: `rota: n/a (razão)` fica FORA de escopo — zero erro inventado');
    t(semRota.checks.alcance === true, 'CN-B: fora de escopo NÃO conta como falta do ciclo');

    // ── CN-C: charter SEM bloco `alcance:` (o legado, 293 de 293 hoje) → fora de escopo.
    const legado = rodar(`---\ncomponent: x\npage: /mod\nstatus: live\nrelated_prototype: n/a (herda PT-01 X; segue o Padrão de Tela)\n---\n`, DC_FACHADA);
    t(legado.alcance.escopo === 'nao_declara' && legado.checks.alcance === true,
      'CN-C: charter legado (sem bloco alcance) fica FORA — forward-only, catraca não regride');

    // ── RELEASE: tudo ligado → nenhum elo quebrado, e a tela fecha o ciclo.
    const ok = rodar(charterAlc('live', '/mod'), DC_REAL);
    t(ok.alcance.quebrados.length === 0 && ok.alcance.severidade === 'ok', 'RELEASE: rota+perm+menu reais ⇒ alcance OK');
    t(ok.completo, 'RELEASE: com alcance OK a tela fecha o ciclo (7/7)');

    // ── CONTROLE POSITIVO DE CADA SONDA (§5 2026-08-01: um `=== 0` verde também fica verde
    //    se o regex for cego). Cada elo é forçado a QUEBRAR isolado — se um `!regex` nunca
    //    puder dar `false`, o assert correspondente aqui morre.
    const soQuebra = (elo, charterTxt, dc = DC_REAL) => {
      const r = rodar(charterTxt, dc);
      return r.alcance.quebrados.length === 1 && r.alcance.quebrados[0] === elo;
    };
    t(soQuebra('rota_registrada', charterAlc('live', '/mod', { rota_nome: 'mod.inexistente' })),
      'sonda 1 discrimina: rota_nome que não existe em Routes/ quebra SÓ o elo 1');
    t(soQuebra('rota_casa_page', charterAlc('live', '/mod').replace('page: /mod', 'page: /outra')),
      'sonda 2 discrimina: alcance.rota ≠ page: quebra SÓ o elo 2');
    // Sonda 6 NÃO pode quebrar sozinha — corpo de fachada não tem `Menu::modify`, então o elo
    // 5 cai junto (é fato do domínio, não bug). O que precisa ser provado é que ela DISCRIMINA:
    // devolve false pro corpo vazio E true pro corpo real. Isolada na unidade, sem o par 5/6.
    t(corpoEhFachada('') && corpoEhFachada('  // no-op  ') && corpoEhFachada('/** doc */\n  return;'),
      'sonda 6 discrimina (false-side): corpo vazio · só comentário · docblock+return ⇒ FACHADA');
    t(!corpoEhFachada("Menu::modify('x', function (\$m) { \$m->url(url('/mod'), 'Mod'); });"),
      'sonda 6 discrimina (true-side): corpo com Menu::modify NÃO é fachada');
    t(corpoDoMetodo(DC_FACHADA, 'modifyAdminMenu').trim() !== corpoDoMetodo(DC_REAL, 'modifyAdminMenu').trim(),
      'sonda 6: corpoDoMetodo extrai corpos DIFERENTES pro real e pro fachada (não devolve vazio pros dois)');

    // sonda 3 dedicada: permission que não gateia NEM rota NEM controller.
    const semGate = rodar(charterAlc('live', '/mod', { permission: 'mod.orfa' }), DC_REAL);
    t(semGate.alcance.quebrados.includes('rota_protegida') && semGate.alcance.quebrados.includes('permission_declarada'),
      'sonda 3+4 discriminam: permission órfã quebra `rota_protegida` E `permission_declarada`');
    // e o CONTRAPONTO que impede o elo 3 de virar guard sintático: sem `can:` na rota, mas
    // autorizada no Controller (convenção dominante do repo: 112 controllers × 3 rotas), PASSA.
    const viaCtrl = rodar(charterAlc('live', '/mod'), DC_REAL.replace('class DC {', "class DC { public function index() { \$this->authorize('mod.access'); }"));
    const semCanNaRota = computeRows(
      (() => { const d = join(HERE, `.alc3-${process.pid}`); const pd = join(d, 'resources', 'js', 'Pages'); mkdirSync(join(pd, 'Mod'), { recursive: true }); writeFileSync(join(pd, 'Mod', 'T.tsx'), '<DataTable/>'); writeFileSync(join(pd, 'Mod', 'T.charter.md'), charterAlc('live', '/mod')); writeFileSync(join(pd, 'Mod', 'T.casos.md'), casosCom); return d; })(),
      join(HERE, `.alc3-${process.pid}`, 'resources', 'js', 'Pages'),
      {
        ptMap: new Map([['resources/js/Pages/Mod/T.tsx', 'CONFORME']]),
        goldenStatus: { 'PT-01': 'live', 'PT-02': 'draft', 'PT-03': 'draft', 'PT-04': 'draft', 'PT-05': 'draft' },
        corpus: {
          rotasSrc: `Route::get('/', [C::class,'index'])->name('mod.index');`, // SEM can:
          permsSrc: { declaradas: new Set(['mod.access']), gate: "\$this->authorize('mod.access');" },
          lerArquivo: () => DC_REAL,
        },
      },
    )[0];
    try { rmSync(join(HERE, `.alc3-${process.pid}`), { recursive: true, force: true }); } catch { /* ignore */ }
    t(!semCanNaRota.alcance.quebrados.includes('rota_protegida'),
      'sonda 3 NÃO é guard sintático: permission autorizada no Controller (sem `can:` na rota) PASSA');
    t(viaCtrl.alcance.quebrados.length === 0, 'sonda 3: as duas formas de gate coexistem sem falso-positivo');

    // sonda 5+6 dedicada: menu_hook apontando pra arquivo inexistente quebra os dois elos.
    const hookMorto = rodar(charterAlc('live', '/mod', { menu_hook: 'NaoExiste.php::modifyAdminMenu' }), DC_REAL);
    t(hookMorto.alcance.quebrados.includes('menu_publica') && hookMorto.alcance.quebrados.includes('menu_nao_fachada'),
      'sonda 5+6 discriminam: menu_hook morto quebra `menu_publica` E `menu_nao_fachada`');
    // e o `n/a (razão)` no menu_hook (ghost legítimo, ex. Gateway de Pagamento) PULA os dois.
    const ghost = rodar(charterAlc('live', '/mod', { menu_hook: 'n/a (ghost do PageHeader da Cobrança)' }), DC_FACHADA);
    t(ghost.alcance.quebrados.length === 0, 'ghost declarado (`menu_hook: n/a (razão)`) PULA os elos de menu — não pune decisão do autor');

    // ── CONTROLE POSITIVO NO DADO REAL (não-hermético de propósito, e declarado).
    //    Sem ele, `corpoEhFachada` verde é indistinguível de sonda cega: as duas pontas
    //    existem VIVAS no repo — Arquivos publica menu (PR #6245) e PaymentGateway é
    //    fachada deliberada (ghost, com Pest assertando). Se um dos dois sumir, o assert
    //    se declara PULADO em vez de passar calado (fail-open é a lápide §5 2026-07-29).
    const real = (rel) => { const p = join(ROOT, rel); return existsSync(p) ? readFileSync(p, 'utf8') : null; };
    const arqSrc = real('Modules/Arquivos/Http/Controllers/DataController.php');
    const pgSrc = real('Modules/PaymentGateway/Http/Controllers/DataController.php');
    if (arqSrc && pgSrc) {
      t(!corpoEhFachada(corpoDoMetodo(arqSrc, 'modifyAdminMenu')),
        'controle positivo REAL: Arquivos::modifyAdminMenu (golden #6245) NÃO é fachada');
      t(corpoEhFachada(corpoDoMetodo(pgSrc, 'modifyAdminMenu')),
        'controle positivo REAL: PaymentGateway::modifyAdminMenu (ghost deliberado) É fachada');
      t(corpoDoMetodo(arqSrc, 'user_permissions').includes("'arquivos.access'"),
        'controle positivo REAL: corpoDoMetodo extrai user_permissions do golden');
    } else {
      console.log('  ⊘ controle positivo REAL PULADO — DataController de Arquivos/PaymentGateway ausente (não é verde)');
    }
  } finally {
    try { rmSync(tmp, { recursive: true, force: true }); } catch { /* ignore */ }
  }
  console.log(fails
    ? `\nSELFTEST FALHOU (${fails})`
    : '\nSELFTEST OK — gate morde (incompleta) e solta (completa); golden-draft reprova; alcance morde menu de FACHADA em `live` e só avisa em `draft`.');
  process.exit(fails ? 1 : 0);
}

// ─────────────────────────────────────────────────────────────────────────────
// modos de produção
// ─────────────────────────────────────────────────────────────────────────────
const rows = computeRows(ROOT, PAGES, { ptMap: loadPtMap(), goldenStatus: loadGoldenStatus(GOLDEN_DIR) });
const completo = rows.filter((r) => r.completo).length;
const total = rows.length;
const pct = total ? Math.round((completo / total) * 100) : 0;

// `--json` REPORTA em stdout; NAO escreve. Ate 2026-08-26 esta flag GRAVAVA o baseline e
// saia -- e `--json`, em todo o resto de scripts/governance, significa "me da os dados".
// Quem rodasse pra inspecionar UMA tela levava um arquivo versionado mutado sem pedir
// (aconteceu nesta sessao: o `git status` acusou o baseline sujo depois de uma consulta).
// Semear/subir o piso agora e `--update-baseline`, explicito.
//
// De quebra fecha um buraco de diagnostico: o relatorio era so AGREGADO, entao nao havia
// como perguntar "por que a tela X esta incompleta". Agora as linhas vem no JSON.
if (process.argv.includes('--json')) {
  console.log(JSON.stringify({ completo, total, telas: rows }, null, 2));
  process.exit(0);
}

if (process.argv.includes('--update-baseline')) {
  writeFileSync(BASELINE, JSON.stringify({
    completo, total,
    note: 'Catraca do ciclo-de-tela (UI-0013): `completo` (telas com charter+PT+conforme+casos+teste+golden-live) só sobe. Baixar exige decisão consciente. ADVISORY (ADR 0314).',
  }, null, 2) + '\n');
  console.log(`baseline gravado: completo=${completo}/${total}`);
  process.exit(0);
}

// ── ITEM 7: quem está em escopo, quem quebrou, com que severidade ──
const emEscopo = rows.filter((r) => r.alcance.escopo === 'medido');
const alcFail = emEscopo.filter((r) => r.alcance.severidade === 'fail');
const alcWarn = emEscopo.filter((r) => r.alcance.severidade === 'warn');

/** Imprime os elos quebrados NOMEADOS — o pedido é "reprovar nomeando qual elo quebrou". */
function relatarAlcance(lista, rotulo) {
  for (const r of lista) {
    console.log(`  ${rotulo} ${r.page}  (charter status: ${r.alcance.status})`);
    for (const e of r.alcance.elos.filter((x) => !x.ok && !x.pulado)) console.log(`      ✗ elo ${e.id}: ${e.detalhe}`);
  }
}

if (process.argv.includes('--check')) {
  if (!existsSync(BASELINE)) { console.error('ciclo-completo: baseline ausente — rode --update-baseline pra semear.'); process.exit(1); }
  const base = JSON.parse(readFileSync(BASELINE, 'utf8'));
  let falhou = false;

  if (completo < base.completo) {
    console.error(`ciclo-completo: REGREDIU — completo ${completo} < baseline ${base.completo}. Uma tela perdeu peça do ciclo (charter/PT/conforme/casos/teste/golden-live/alcance).`);
    falhou = true;
  } else {
    console.log(`ciclo-completo: OK — completo ${completo} ≥ baseline ${base.completo} (catraca).`);
  }

  // ITEM 7 — severidade por `status:`. O DENOMINADOR sai junto do veredito: dizer "0 quebrados"
  // sem dizer "de 0 medidos" é o fail-open da lápide §5 2026-07-29.
  console.log(`alcance: ${emEscopo.length} tela(s) em escopo (declaram bloco \`alcance:\`) de ${total} roteadas.`);
  if (alcWarn.length) {
    console.log(`alcance: ⚠️  ${alcWarn.length} tela(s) \`draft\` com elo quebrado — AVISO, não bloqueia (rc=0):`);
    relatarAlcance(alcWarn, '⚠️ ');
  }
  if (alcFail.length) {
    console.error(`alcance: ❌ ${alcFail.length} tela(s) \`live\` que NINGUÉM ALCANÇA — charter diz pronta e o caminho até ela está quebrado:`);
    relatarAlcance(alcFail, '❌');
    console.error('   Receita: rota `->name()` em Routes/ · `can:<permission>` (ou authorize no Controller) · permission em DataController::user_permissions · Menu::modify + url() no modifyAdminMenu.');
    console.error('   Golden das 3 camadas: Modules/Arquivos/Http/Controllers/DataController.php (PR #6245).');
    falhou = true;
  }
  if (!alcFail.length && !alcWarn.length) console.log(`alcance: OK — nenhum elo quebrado nas ${emEscopo.length} tela(s) medidas.`);
  process.exit(falhou ? 1 : 0);
}

// ── relatório (read-only) ──
console.log('═══ CICLO-COMPLETO por tela (nasceu/segue completa? · UI-0013) ═══');
console.log(`telas roteadas : ${total}  ·  ✅ completas: ${completo} (${pct}%)  ·  ⚠️ incompletas: ${total - completo}`);

// contagem por peça faltante (onde o ciclo mais fura)
const contPeca = {};
for (const r of rows) for (const f of r.faltando) contPeca[f] = (contPeca[f] || 0) + 1;
console.log('\nonde o ciclo fura (telas faltando cada peça):');
for (const [peca, n] of Object.entries(contPeca).sort((a, b) => b[1] - a[1])) console.log(`  • ${peca.padEnd(13)}: ${n}`);

// ── LADO DESIGN (GOLDEN-LIVE) — quantas telas estão travadas SÓ pelo golden draft ──
const golden = loadGoldenStatus(GOLDEN_DIR);
console.log('\nlado Design — golden de cada Padrão de Tela (draft trava o fechamento do ciclo):');
for (const [pt, st] of Object.entries(golden)) {
  const soGolden = rows.filter((r) => r.pt === pt && r.faltando.join() === 'golden_live').length;
  const flag = st === 'live' ? '✅' : '⚠️ ';
  const extra = st !== 'live' && soGolden ? `  ← ${soGolden} tela(s) fechariam o ciclo se este golden virasse live` : '';
  console.log(`  ${flag} ${pt}: ${st}${extra}`);
}
// ── ITEM 7 — ALCANCE (rota → permission → menu → pacote) ──
// O denominador vem PRIMEIRO e sempre: o guard é forward-only, então "0 quebrados" pode
// significar "0 medidos". Dizer só o numerador é o fail-open da lápide §5 2026-07-29.
console.log('\nitem 7 — alcance (o humano chega na tela? rota → permission → menu → pacote):');
console.log(`  em escopo (charter declara \`alcance:\`) : ${emEscopo.length} de ${total} telas roteadas`);
if (!emEscopo.length) {
  const naDecl = rows.filter((r) => r.alcance.escopo === 'n/a_declarado').length;
  console.log(`  ⊘ NENHUMA tela medida — o bloco \`alcance:\` só é carimbado pelo criar-tela.mjs desde a`);
  console.log(`    emenda A (PR #6254), e o legado fica grandfathered (forward-only · ADR 0275).`);
  console.log(`    Isto é ISENÇÃO DECLARADA, não gate mudo: o guard morde em fixture (--selftest) e as`);
  console.log(`    duas pontas da sonda são conferidas contra o repo vivo (Arquivos × PaymentGateway).`);
  if (naDecl) console.log(`    (${naDecl} tela(s) declaram \`rota: n/a (razão)\` — decisão do autor, fora de escopo.)`);
} else {
  console.log(`  ✅ sem elo quebrado : ${emEscopo.length - alcFail.length - alcWarn.length}`);
  if (alcWarn.length) { console.log(`  ⚠️  draft com elo quebrado (avisa, não bloqueia): ${alcWarn.length}`); relatarAlcance(alcWarn, '⚠️ '); }
  if (alcFail.length) { console.log(`  ❌ LIVE que ninguém alcança (bloqueia no --check): ${alcFail.length}`); relatarAlcance(alcFail, '❌'); }
}

console.log('\ncatraca: `completo` só sobe. Nasça a tela com `criar-tela.mjs` (conjunto completo por construção).');
console.log('GOLDEN-LIVE: pra fechar telas de um PT, o Design precisa levar o golden do PT de draft → live.');
console.log('ALCANCE: charter `draft` com elo quebrado avisa; `live` bloqueia. Golden das 3 camadas: Modules/Arquivos/…/DataController.php (#6245).');
