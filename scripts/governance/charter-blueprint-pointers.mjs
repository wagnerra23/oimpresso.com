#!/usr/bin/env node
// @ts-check
/**
 * charter-blueprint-pointers.mjs — auditoria de PONTEIROS DE PROTÓTIPO dos Page Charters.
 *
 * Complementa charter-refs.mjs (que cobre refs markdown + frontmatter component/runbook/
 * parent_capterra). ESTE foca no elo que o loop design→code quebrava silenciosamente: o
 * ponteiro de PROTÓTIPO/BLUEPRINT do charter (`mwart_pattern_reuse.blueprint_cowork`,
 * `blueprint_cowork`, e Refs apontando pra `prototipo-ui/**` ou `ui_kits/**`). Quando esse
 * ponteiro aponta pro VÁCUO, o gate 3-way (reconcile-triplet) não tem com o que comparar a
 * coluna do meio → o protótipo some e o conflito charter×produção fica invisível.
 *
 * Caso real provado: Produto tem 3 ponteiros órfãos —
 *   prototipo-ui/cowork/Wagner/legado/produto-cockpit/  (frontmatter blueprint_cowork)
 *   prototipo-ui/cowork/Felipe/legado/produto/          (cowork-map / charter)
 *   ui_kits/cowork-2026-05-09/prod-page.jsx   (Refs)
 *
 * Determinístico, sem deps, sem LLM. case-SENSITIVE (espelha CI Linux/Hostinger).
 *
 * Uso:
 *   node scripts/governance/charter-blueprint-pointers.mjs            (texto)
 *   node scripts/governance/charter-blueprint-pointers.mjs --json
 *   node scripts/governance/charter-blueprint-pointers.mjs --strict   (exit 1 se houver órfão)
 */
import { readFileSync, readdirSync, realpathSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { execSync } from 'node:child_process';

const ROOT = process.cwd();
const PAGES = join(ROOT, 'resources/js/Pages');

function existsExact(p) {
  if (!p) return false;
  try { return realpathSync.native(p).replaceAll('\\', '/') === p.replaceAll('\\', '/'); }
  catch { return false; }
}
function dirExists(p) {
  try { return statSync(p).isDirectory(); } catch { return false; }
}

const hasUnderscoreSeg = (rel) => rel.split('/').some((s) => s && s[0] === '_');

function charterFiles() {
  const out = [];
  if (!dirExists(PAGES)) return out;
  (function walk(dir) {
    for (const e of readdirSync(dir, { withFileTypes: true })) {
      const p = join(dir, e.name);
      if (e.isDirectory()) walk(p);
      else if (e.isFile() && p.endsWith('.charter.md')) {
        const rel = p.slice(PAGES.length + 1).replaceAll('\\', '/');
        if (!hasUnderscoreSeg(rel)) out.push(rel);
      }
    }
  })(PAGES);
  return out.sort();
}

function splitFrontmatter(content) {
  const m = content.match(/^---\r?\n(.*?)\r?\n---\r?\n?(.*)$/s);
  return m ? [m[1], m[2]] : ['', content];
}

/** Coleta todos os ponteiros de protótipo declarados num charter (com a fonte). */
function pointersOf(absPath) {
  const [fm, body] = splitFrontmatter(readFileSync(absPath, 'utf8'));
  const ptrs = [];

  // 1. frontmatter blueprint_cowork (sob mwart_pattern_reuse ou raiz)
  for (const mm of fm.matchAll(/^\s*blueprint_cowork:\s*["']?(\S+?)["']?\s*$/gm)) {
    ptrs.push({ path: mm[1], src: 'frontmatter:blueprint_cowork' });
  }
  // blueprint_screenshot_approval às vezes referencia path — ignoramos (não é dir/arquivo).

  // 2. Refs no corpo: backtick-paths pra prototipo-ui/** ou ui_kits/**
  for (const mm of body.matchAll(/`(prototipo-ui\/[^`]+|ui_kits\/[^`]+)`/g)) {
    const raw = mm[1].trim();
    if (/\.(jsx|tsx|html|css|md|json)$/.test(raw) || raw.endsWith('/')) {
      ptrs.push({ path: raw, src: 'corpo:Refs' });
    }
  }
  // 3. Refs no corpo via link markdown ](prototipo-ui/...) ou ](../...prototipo-ui...)
  for (const mm of body.matchAll(/\]\(([^)\s]*(?:prototipo-ui|ui_kits)\/[^)\s]+)\)/g)) {
    const raw = mm[1].trim();
    // só os que são caminho de repo (não http)
    if (!/^https?:\/\//.test(raw)) ptrs.push({ path: raw, src: 'corpo:link-md' });
  }

  // dedup por path
  const seen = new Set();
  return ptrs.filter((p) => { const k = p.path; if (seen.has(k)) return false; seen.add(k); return true; });
}

/** Resolve um ponteiro (pode ter ../ relativo ao charter) a um caminho repo-absoluto. */
function resolvePtr(baseDir, ptrPath) {
  if (/^https?:\/\//.test(ptrPath)) return null;
  // limpa âncoras/markdown
  let clean = ptrPath.replace(/[#].*$/, '');
  if (clean.startsWith('../') || clean.startsWith('./')) {
    // relativo ao diretório do doc que declara o ponteiro
    const joined = join(baseDir, clean).replaceAll('\\', '/');
    return joined;
  }
  // repo-relative (prototipo-ui/... ou ui_kits/...)
  return join(ROOT, clean).replaceAll('\\', '/');
}

function isOrphan(absPath, rawPtr) {
  if (!absPath) return false;
  const isFile = /\.(jsx|tsx|html|css|md|json)$/.test(rawPtr);
  return isFile ? !existsExact(absPath) : !dirExists(absPath);
}

/**
 * 2a RAIZ — os docs de `memory/requisitos` (RUNBOOK, SPEC, visual-comparison, gap).
 *
 * Por que existe: e o gap que o #7335 pagou A MAO. O diretorio `prototipo-ui/prototipos`
 * morreu em mai/jun-2026 e 61 docs de requisitos seguiram apontando pro vacuo por MESES sem
 * alarme — esta maquina so olhava os charters de `resources/js/Pages`, e o charter nunca foi
 * o unico lugar onde o ponteiro de prototipo vive.
 *
 * FP MEDIDO ANTES de ligar (disciplina §5 — a familia de guard sintatico ja tem 8 lapides):
 * 1292 docs varridos, 335 ponteiros extraidos, 141 orfaos. Destes, 41 (29%) sao docs que JA
 * DECLARAM a morte na propria linha ("removido em <data>, <sha>", "PATH APAGADO", "apagado
 * em") — registro datado CORRETO, nao divida; cobra-los puniria justamente quem fez a coisa
 * certa. `declaraMorte()` os exclui, e o que sobra (100) e o sinal.
 *
 * ADVISORY, report-only: NAO entra no `--strict`, que segue sendo dos charters. O step do
 * `reconcile-triplet.yml` roda sem `--strict` (exit 0) — isto reporta, nao bloqueia.
 */
const BS = String.fromCharCode(92);  // barra invertida sem literal (colapsa no transporte — LC-26)
const REQ = join(ROOT, 'memory/requisitos');

/** A linha que carrega o ponteiro ja declara que ele morreu? Entao e registro, nao divida. */
/**
 * Placeholder NAO e ponteiro: `cowork-YYYY-MM-DD/` e `prototipo-ui/.../` sao notacao
 * generica que a prosa usa pra falar de um formato, nao caminho que um dia resolveu.
 * Anota-los com data de remocao seria carimbar o que nunca foi arquivo — falso-positivo
 * do extrator, nao divida do doc. (Medido: os 2 unicos casos no corpus, ambos em ADR UI.)
 */
function ehPlaceholder(p) {
  const S = String.fromCharCode(47), D = String.fromCharCode(46);  // / e . sem literal (LC-26)
  const RET = String.fromCharCode(8230);  // U+2026 '...' de path abreviado
  // segmento-template (<tela>, <modulo>) e path abreviado nao sao ponteiros: sao notacao.
  if (/<[^<>]+>/.test(p) || p.includes(RET)) return true;
  return p.includes('YYYY-MM-DD') || p.includes(S + D + D + D + S) || p.endsWith(S + D + D + D);
}

function declaraMorte(linha) {
  // "nunca versionado": o alvo NUNCA existiu no git (artefato externo do Cowork — zip, pasta
  // local). MEDIDO no historico completo, repo nao-raso: 0 commits tocaram esses paths. E
  // declaracao do mesmo tipo — o doc diz por que o ponteiro nao resolve — entao sai da cobranca.
  // "nao resolve no repo — proveniencia nao determinada": o doc declara que o ponteiro nao
  // resolve E que a origem NAO foi medida. E a forma honesta quando a medicao nao fecha:
  // melhor do que inventar data (foi o ERRO 2 que a r1 do GT-G5 pegou neste mesmo lote).
  return /removido em|PATH APAGADO|apagado em|nunca versionado|o resolve no repo|N\u00c3O EXISTE|NAO EXISTE|renomead[ao] (pra|para)|Corrigido 20/i.test(linha);
}

function requisitosDocs() {
  const out = [];
  if (!dirExists(REQ)) return out;
  (function walk(dir) {
    for (const e of readdirSync(dir, { withFileTypes: true })) {
      const p = join(dir, e.name);
      if (e.isDirectory()) walk(p);
      else if (e.isFile() && p.endsWith('.md')) out.push(p.split(BS).join('/'));
    }
  })(REQ);
  return out.sort();
}

/** Orfaos MUDOS nos docs de requisitos (os datados ficam de fora — ver docblock acima). */
function auditRequisitos() {
  const perDoc = [];
  for (const abs of requisitosDocs()) {
    const linhas = readFileSync(abs, 'utf8').split('\n');
    const ptrs = pointersOf(abs);
    const orphans = [];
    for (const p of ptrs) {
      const alvo = resolvePtr(join(abs, '..'), p.path);
      if (ehPlaceholder(p.path)) continue;        // notacao generica, nao caminho
      if (!isOrphan(alvo, p.path)) continue;
      const linha = linhas.find((l) => l.includes(p.path)) || '';
      if (declaraMorte(linha)) continue;
      orphans.push({ path: p.path, src: p.src });
    }
    if (orphans.length) perDoc.push({ doc: abs.slice(ROOT.length + 1).split(BS).join('/'), total: ptrs.length, orphans });
  }
  return { perDoc, totalOrphans: perDoc.reduce((a, d) => a + d.orphans.length, 0) };
}


function audit() {
  const perCharter = [];
  for (const rel of charterFiles()) {
    const ptrs = pointersOf(join(PAGES, rel));
    const orphans = [];
    for (const p of ptrs) {
      const abs = resolvePtr(join(PAGES, rel, '..'), p.path);
      if (isOrphan(abs, p.path)) orphans.push({ path: p.path, src: p.src });
    }
    if (ptrs.length) perCharter.push({ charter: 'resources/js/Pages/' + rel, total: ptrs.length, orphans });
  }
  const withOrphans = perCharter.filter((c) => c.orphans.length > 0);
  const totalOrphans = withOrphans.reduce((a, c) => a + c.orphans.length, 0);
  return { perCharter, withOrphans, totalOrphans };
}

/** Advisory B1 (ponte design↔código): charters com `visual_source:` SEM `visual_source_sha:`.
 *  Sem o sha do export, não dá pra detectar DRIFT quando `cowork/` é sobrescrito no próximo
 *  handoff. Report-only — NÃO afeta --strict (é base do `<tela>.map.json` por-região, B2). */
function shaAdvisory() {
  const missing = [];
  for (const rel of charterFiles()) {
    const [fm] = splitFrontmatter(readFileSync(join(PAGES, rel), 'utf8'));
    if (!/^visual_source:\s*\S/m.test(fm)) continue;
    if (!/^visual_source_sha:\s*\S/m.test(fm)) missing.push('resources/js/Pages/' + rel);
  }
  return missing;
}


/** Advisory C1 — "removido" x "MUDOU DE CASA".
 *  Tombstone `_(removido em <data>, <sha>)_` cujo CONTEUDO sobrevive em origin/main sob OUTRO
 *  path: dizer "removido" ali induz o leitor a erro (o alvo nao sumiu, trocou de endereco) e
 *  ainda blinda a linha contra cobranca futura, porque declaraMorte() a considera resolvida.
 *
 *  O predicado e DETERMINISTICO, nao sintatico: resolve `<sha>^:<path>` e procura o blob (ou
 *  os blobs do tree) no indice de origin/main. Nao e heuristica de similaridade — e igualdade
 *  de hash. Por isso escapa da familia de guard sintatico que o §5 ja enterrou 8x.
 *
 *  Prior art: o git faz deteccao de rename por similaridade (`diff -M`, `log --follow`). Aqui
 *  o caso e mais estreito e mais barato — o blob sobrevive IDENTICO, entao basta lookup.
 *
 *  MEDIDO — e cada numero vem do PROPRIO gate, com o comando ao lado (§5 2026-07-17):
 *    `node scripts/governance/charter-blueprint-pointers.mjs --json --todos`
 *    2026-09-16, origin/main: 98 tombstones no corpus -> 0 acusacoes (as achadas foram pagas).
 *    A prova de que MORDE nao e um ref (que envelhece) e sim o bite-test (e4), deterministico.
 *
 *  ERRATA 2026-09-16 (a redacao anterior deste bloco misturava TRES medicoes como se fossem
 *  uma, e os numeros nao fechavam): ela dizia `91 tombstones -> 75 remocao real, 4 mudou-de-casa`
 *  enquanto o corpo do merge 59d54112e2d dizia `6` — 75+4=79 e 75+6=81, nenhum fecha com 91.
 *  Origem da divergencia, reconstruida: o `4` veio de uma SONDA ad-hoc de investigacao (outras
 *  bases de resolucao), o `6` veio do gate, o `75` da sonda (o gate nao classifica 'remocao
 *  real' — ele so acusa), e o `91` de um regex que o gate ja nao usa (hoje conta 98). Achado
 *  pela sessao irma que calibrou o limiar; o defeito e a §5 2026-07-17 na minha propria mao.
 *  ADVISORY e forward-only (ADR 0275): varre so os docs do diff vs origin/main. `--todos`
 *  varre o corpus inteiro (custa ~2min: e um `git rev-parse` por ponteiro).
 *
 *  ── O LIMIAR DE FRACAO E A CONSTRUCAO ERRADA — MEDIDO 2026-09-16 ───────────────
 *  Era o residuo #1 do #7392 ("0.5 escolhido SEM corpus que o calibre"). Calibrado, e o
 *  resultado NAO foi "0.5 esta bom": foi que a FRACAO nao mede o que este audit pergunta.
 *
 *  A fracao mede QUANTO do tree sobreviveu. A pergunta e SE existe conteudo em outro path.
 *  Um diretorio com 4 de 10 arquivos movidos e 6 apagados da 0.4 — e os 4 mudaram de casa
 *  de verdade. Medido, com a concentracao dos destinos ao lado:
 *      f=0.188 (18/96)  -> 94% dos destinos num so dir   MUDOU DE CASA, coerente
 *      f=0.400 (4/10)   -> 100% num so dir               MUDOU DE CASA, coerente
 *      f=0.705 (320/454)-> 15%, espalhado por 49 dirs
 *      f=0.991 (320/323)-> 15%, os MESMOS 49 dirs (um path e subdir do outro; muda o
 *                          denominador, nao o fenomeno)
 *  Ou seja: 0.5 REPROVA dois casos coerentes e APROVA dois espalhados. Nao ha vale.
 *
 *  A separacao real esta em ZERO x NAO-ZERO, nao em 0.5. RE-MEDIDO com o extrator
 *  CORRIGIDO do #7402 (antes o `pointersOf2` engolia a prosa do tombstone e perdia 35%
 *  dos casos em silencio). Corpus: 1292 docs de `memory/requisitos`, 98 tombstones:
 *       7 dispensados .... a linha ja aponta o destino (`jaDeclaraDestino`)
 *      83 MEDIDOS ........ 75 com ZERO blob sobrevivente (remocao real INDISCUTIVEL)
 *                           8 com >=1 (ha conteudo vivo em outro path)
 *       8 SEM MEDICAO .... nenhum ponteiro da linha resolveu em `<sha>^` (LC-33: contadas,
 *                          nao silenciadas — saem no json como `nao_resolvidos`, 117 pares
 *                          em 35 linhas; nas outras 27 algum outro ponteiro resolveu)
 *      limiar 0.5 acusa ... 0 dos 8   (falso-negativo 8 de 8)
 *  Distribuicao: 75 · 2 · 4 · 0 · 2 · [zero de 0.5 a 1.0]. O limiar esta ACIMA de toda a
 *  populacao viva — nao ha nada pra ele separar.
 *
 *  ⚠️ O denominador ANTERIOR desta nota (84 medidos / 76 zero) estava inflado por erro MEU:
 *  eu varria `memory/requisitos` MAIS os `.charter.md` sob `resources/js/Pages`, e o `--todos`
 *  do gate varre SO `requisitosDocs()`. Medir num universo maior que o do consumidor e a
 *  §5 2026-07-27 (denominador inventado). A conclusao NAO muda com a correcao — muda o
 *  numero, e o numero errado era meu.
 *
 *  ERRATA do meu proprio metodo, registrada e nao apagada: a 1a calibracao (commit
 *  anterior desta branch) concluiu "0.5 separa as 92 com 0 erro" achando um vao em
 *  (0.455, 0.705). Era TAUTOLOGICO — rotulei os 84 como "negativos" PORQUE sao o que o
 *  gate nao acusa, e usei isso pra validar o gate. E a lapide §5 2026-07-17
 *  (drift-sentinel): quando a distribuicao nao discrimina, o baseline nao e o problema,
 *  o MEDIDOR e. A objecao veio da sessao irma e a medicao confirmou.
 *
 *  TROCADO EM 2026-09-16: o predicado agora e `sobrevivem >= 1`, sem fracao nenhuma, e o
 *  achado carrega `sobrevivem/total` + `destino_dirs` + `destino_concentracao`. Em vez de
 *  escolher outro numero redondo, o gate REPORTA a proporcao e quem le decide a redacao —
 *  "movido", "parcialmente movido" ou "removido" mesmo. IMPACTO MEDIDO antes de armar:
 *      acusa por fracao >= 0.5 ... 0 de 83
 *      acusa por >=1 ............. 8 de 83
 *  e os 8 sao VERDADEIROS (FP = 0): em todos o conteudo esta vivo sob `prototipo-ui/cowork/
 *  Wagner`, com concentracao de 13% a 100%. Essa faixa e a prova de que reportar a dispersao
 *  importa — 4/10 num dir so e 15/33 espalhado por 8 dirs sao achados muito diferentes.
 *
 *  O piso de 200B passou a valer TAMBEM dentro de tree (ver `blobsDe`). Medido: nao muda
 *  nada hoje (83 medidos e 8 acusados com e sem ele; `sem blob` = 0). Entra como
 *  consequencia do predicado — com fracao, 1 blob vazio em 96 nao movia a agulha; com
 *  `>=1` um unico blob trivial que colida por conteudo DECIDE a acusacao sozinho.
 *
 *  E o C1 passou a sair no modo TEXTO. Ate aqui ele so existia no `--json`, e a lane do CI
 *  roda o texto: o audit achava e nao contava a ninguem. Com `>=1` seriam 8 achados reais
 *  invisiveis (§5 2026-08-02, gate mudo com cara de cobertura). */
const SL_C1 = String.fromCharCode(47);   // '/' sem literal
const TOMB_SHA = /_[(][^)]*removido em ([0-9-]+), ([0-9a-f]{7,40})[^)]*[)]_/;
/** A linha ja aponta PRA ONDE o conteudo foi? Entao esta correta — nao e acusacao.
 *  DETERMINISTICO: compara com o PATH VIVO do blob, nao com vocabulario. A 1a versao casava
 *  "conteudo vive em" e deixava passar "foi movido para"/"renomeado para" — FP MEDIDO em
 *  OficinaAuto/oficina-os-nova-prototipo-visual-comparison.md:105, que declara o destino CERTO
 *  e seria acusada. Vocabulario cresce a cada verbo novo; o path do destino nao.
 *  (Achado da sessao irma que calibra o limiar — re-medido aqui antes de aceitar.) */
function jaDeclaraDestino(linha, destinos) {
  for (const dst of destinos) {
    if (!dst) continue;
    // o doc costuma citar o destino num nivel mais ALTO que o blob: sobe a arvore ate 3
    // segmentos (abaixo disso vira 'prototipo-ui/' e dispensaria qualquer coisa).
    const seg = dst.split(SL_C1);
    for (let n = seg.length; n >= 3; n--) {
      if (linha.includes(seg.slice(0, n).join(SL_C1))) return true;
    }
  }
  return false;
}
// (o `LIMIAR_MUDOU_DE_CASA = 0.5` morreu aqui em 2026-09-16 — o predicado virou `>=1` e a
//  constante ficaria orfa. Medicao que o enterrou no bloco §LIMIAR do topo.)

function sh(cmd) {
  try { return execSync(cmd, { encoding: 'utf8', maxBuffer: 1e9, stdio: ['ignore', 'pipe', 'ignore'] }).trim(); }
  catch { return ''; }
}

function blobsVivosEmMain() {
  const vivos = new Map();   // blob -> 1o path vivo (alimenta a dispensa por destino)
  for (const l of sh('git ls-tree -r origin/main').split(String.fromCharCode(10))) {
    const m = l.match(/^[0-9]+ blob ([0-9a-f]+)	(.+)$/);
    if (m && !vivos.has(m[1])) vivos.set(m[1], m[2]);
  }
  return vivos;
}

/** blobs sob um objeto: o proprio, se blob com tamanho relevante; os filhos, se tree.
 *  O piso de 200B existe porque blob trivial (vazio, .gitkeep, 1 linha) COLIDE por conteudo
 *  e produziria acusacao sem significado. */
function blobsDe(obj) {
  const tipo = sh(`git cat-file -t ${obj}`);
  if (tipo === 'blob') return Number(sh(`git cat-file -s ${obj}`) || 0) >= 200 ? [obj] : [];
  if (tipo !== 'tree') return [];
  // O piso de 200B vale TAMBEM para os blobs de dentro do tree — a razao declarada dele
  // ("blob trivial COLIDE por conteudo") nao muda por o objeto ser tree. Ate o predicado
  // ser `>=1` a assimetria era inocua (com fracao, 1 blob vazio em 96 nao move a agulha);
  // agora um unico `.gitkeep` que colida DECIDE a acusacao sozinho.
  // MEDIDO no corpus de hoje: aplicar o piso aqui nao muda nada (83 medidos e 8 acusados
  // com e sem ele, `sem blob` = 0) — nenhum tree medido tem blob < 200B. Entra como
  // consequencia da troca de predicado, nao como conserto de defeito observado.
  const out = [];
  for (const x of sh(`git ls-tree -r -l ${obj}`).split(String.fromCharCode(10))) {
    const m = x.match(/^[0-9]+ blob ([0-9a-f]+)\s+([0-9-]+)/);
    if (m && Number(m[2]) >= 200) out.push(m[1]);
  }
  return out;
}

/** docs de requisitos tocados pelo PR (forward-only, ADR 0275). Sem base, devolve vazio —
 *  e NAO-MEDICAO, nao 'nada a reportar' (LC-33): o CLI diz isso em voz alta. */
function docsDoDiffC1() {
  const base = sh('git merge-base origin/main HEAD');
  if (!base) return null;
  return sh(`git diff --name-only ${base}...HEAD -- memory/requisitos`)
    .split(String.fromCharCode(10)).map((x) => x.trim()).filter((x) => x.endsWith('.md'));
}

let c1NaoMedidoMotivo = '';

function auditMudouDeCasa(docs) {
  // ⚠️ os dois early-returns devolvem a MESMA FORMA do return final ({achados, naoResolvidos}).
  // Devolver `[]` aqui crashava o `--json` com "Cannot read properties of undefined": o
  // chamador faz `c1.naoResolvidos.length`, e `[]` e truthy, entao o guard `c1 !== null` nao
  // pega. Pior, batia no caso COMUM — PR que nao toca memory/requisitos deixa `docs` vazio.
  // O modo TEXTO nao usava `c1` e por isso o CI passava: gate verde num modo, quebrado no
  // outro (§5 2026-07-28, agora no eixo MODO do mesmo script).
  const vazio = { achados: [], naoResolvidos: [] };
  if (!docs.length) return vazio;
  const vivos = blobsVivosEmMain();
  if (!vivos.size) {
    // NAO MEDI: o indice de blobs veio vazio (`git ls-tree -r origin/main` sem saida
    // — ref ausente no checkout, fetch parcial, clone raso). Devolver `vazio` aqui
    // fazia `medido: true` e o texto imprimir `nenhum orfao mudo`, INDISTINGUIVEL de medicao
    // real — o oposto do que este proprio comentario prometia (LC-33 + LC-15).
    c1NaoMedidoMotivo = 'indice de blobs de origin/main vazio — a ref existe neste checkout?';
    return null;
  }         // sem indice nao ha medicao — NAO afirmar verde (LC-33)
  const achados = [];
  const naoResolvidos = [];   // LC-33: nao-medicao contada, nunca silenciada
  const raiz = ROOT.split(BS).join(SL_C1) + SL_C1;
  for (const bruto of docs) {
    const rel = bruto.startsWith(raiz) ? bruto.slice(raiz.length) : bruto;
    let linhas;
    try { linhas = readFileSync(join(ROOT, rel), 'utf8').split(String.fromCharCode(10)); } catch { continue; }
    const dir = rel.split(SL_C1).slice(0, -1).join(SL_C1);
    linhas.forEach((linha, i) => {
      const t = linha.match(TOMB_SHA);
      if (!t) return;
      for (const ptr of pointersOf2(linha)) {
        const bases = [ptr, dir + SL_C1 + ptr, 'memory/requisitos/_DesignSystem/' + ptr, 'memory/reference/' + ptr];
        let obj = '';
        // LC-33: `<sha>^:<path>` nao resolver NAO e 'nada a reportar' — e uma de duas coisas,
        // e o codigo antigo colapsava as duas num `continue` mudo (29 de 98 tombstones passavam
        // por aqui): (1) o sha citado nao tem aquele path -> indicio de tombstone falso;
        // (2) o path foi mal extraido da linha. Nao da pra separar as duas sem julgar a prosa,
        // entao NAO acusamos — mas CONTAMOS, pra que o zero de acusacoes nunca se confunda com
        // 'tudo medido'. O contador sai no json como `nao_resolvidos`.
        for (const b of bases) { obj = sh(`git rev-parse "${t[2]}^:${b}"`); if (obj.length === 40) { break; } obj = ''; }
        if (!obj) { naoResolvidos.push({ doc: rel, linha: i + 1, sha: t[2], ponteiro: ptr }); continue; }
        const filhos = blobsDe(obj);
        if (!filhos.length) break;
        const sobrev = filhos.filter((h) => vivos.has(h));
        const sobrevivem = sobrev.length;
        if (jaDeclaraDestino(linha, sobrev.slice(0, 5).map((h) => vivos.get(h)))) break;   // ja aponta o destino
        // PREDICADO: >=1 blob sobrevivente, NAO uma fracao. A fracao media QUANTO do tree
        // sobreviveu; a pergunta e SE existe conteudo vivo em outro path — coisas diferentes,
        // e um dir com 4 de 10 movidos e 6 apagados da 0.4 com os 4 tendo mudado de casa
        // de verdade. Em vez de escolher outro numero redondo, o achado CARREGA a proporcao
        // e a dispersao dos destinos, e quem le decide se reescreve como "movido" ou
        // "parcialmente movido". Ver o bloco §LIMIAR no topo pra medicao que enterrou o 0.5.
        if (sobrevivem >= 1) {
          const destinos = new Set();
          for (const h of sobrev) { const p = vivos.get(h); if (p) destinos.add(p); }
          const porDir = {};
          for (const p of destinos) {
            const d = p.split(SL_C1).slice(0, -1).join(SL_C1);
            porDir[d] = (porDir[d] || 0) + 1;
          }
          const top = Object.entries(porDir).sort((a, b) => b[1] - a[1])[0] || ['', 0];
          achados.push({
            doc: rel, linha: i + 1, ponteiro: ptr, sha: t[2], sobrevivem, total: filhos.length,
            // sem estes 3 o numero vira veredito: 15/33 espalhado por 8 dirs e 4/10 num dir so
            // sao achados MUITO diferentes, e so o autor sabe qual redacao cabe.
            destino_dominante: top[0],
            destino_dirs: Object.keys(porDir).length,
            destino_concentracao: destinos.size ? Number((top[1] / destinos.size).toFixed(2)) : 0,
          });
        }
        break;
      }
    });
  }
  return { achados, naoResolvidos };
}

/** ponteiros CRUS da linha (o `pointersOf` do arquivo le doc inteiro e resolve; aqui e por linha). */
function pointersOf2(linha) {
  const BQ = String.fromCharCode(96);
  const PRE = /(prototipo-ui|memory|resources|ui_kits|app|Modules|scripts|governance|public|tests|database)[/]/g;
  const out = new Set();
  let m;
  while ((m = PRE.exec(linha))) {        // LOCAL de proposito: /g no escopo do modulo vaza lastIndex
    const r = linha.slice(m.index).split(BQ)[0].split(')')[0].split(']')[0].trim();
    const toks = r.split(/[ 	]+/);   // corta no ESPACO: sem isto path sem backtick leva a prosa junto
    for (let k = toks.length; k >= 1; k--) {
      const c = decodeURIComponent(toks.slice(0, k).join(' ').replace(/[.,;:)]+$/, '')).replace(/[/]+$/, '');
      if (c.includes(SL_C1) && !ehPlaceholder(c)) out.add(c);
    }
  }
  return out;
}

// ── CLI ──────────────────────────────────────────────────────────────────────
const json = process.argv.includes('--json');
const strict = process.argv.includes('--strict');
const r = audit();
const shaMiss = shaAdvisory();
const req = auditRequisitos();   // 2a raiz: memory/requisitos (advisory, report-only)
const todosC1 = process.argv.includes('--todos');
const docsC1 = todosC1 ? requisitosDocs() : docsDoDiffC1();
const c1 = docsC1 === null ? null : auditMudouDeCasa(docsC1);   // C1: advisory

if (json) {
  console.log(JSON.stringify({
    tool: 'charter-blueprint-pointers',
    charters_com_ponteiro: r.perCharter.length,
    charters_com_orfao: r.withOrphans.length,
    total_orfaos: r.totalOrphans,
    detalhe: r.withOrphans,
    visual_source_sha_faltando: shaMiss,
    requisitos_docs_com_orfao: req.perDoc.length,
    requisitos_total_orfaos: req.totalOrphans,
    requisitos_detalhe: req.perDoc,
    mudou_de_casa_medido: c1 !== null,
    mudou_de_casa_nao_medido_motivo: c1 === null ? (c1NaoMedidoMotivo || 'sem base pra comparar') : null,
    mudou_de_casa: c1 ? c1.achados : [],
    // LC-33: nao-medicao VISIVEL. UNIDADE: pares (linha x ponteiro), nao linhas distintas —
    // dizer a unidade junto do numero e o conserto do defeito (F) deste mesmo arquivo.
    mudou_de_casa_nao_resolvidos: c1 ? c1.naoResolvidos.length : null,
    mudou_de_casa_nao_resolvidos_linhas: c1 ? new Set(c1.naoResolvidos.map((x) => x.doc + ':' + x.linha)).size : null,
  }, null, 2));
} else {
  console.log('charter-blueprint-pointers — auditoria de ponteiros de protótipo/blueprint dos charters\n');
  console.log(`Charters com ≥1 ponteiro de protótipo: ${r.perCharter.length}`);
  console.log(`Charters com ponteiro ÓRFÃO (aponta pro vácuo): ${r.withOrphans.length}`);
  console.log(`Total de ponteiros órfãos: ${r.totalOrphans}\n`);
  for (const c of r.withOrphans) {
    console.log(`  ${c.charter}  (${c.orphans.length}/${c.total} órfão)`);
    for (const o of c.orphans) console.log(`     ✗ ${o.path}   [${o.src}]`);
  }
  if (!r.withOrphans.length) console.log('  ✓ nenhum ponteiro órfão.');
  console.log(`\n— advisory B1 (ponte design↔código): charters com visual_source: SEM visual_source_sha: = ${shaMiss.length}`);
  console.log('  (sem sha do export, drift não rastreável quando cowork/ é sobrescrito — base do mapa por-região)');
  for (const m of shaMiss) console.log(`     ⚠ ${m}`);

  console.log(`
— 2a raiz (advisory): memory/requisitos com ponteiro de prototipo ORFAO e MUDO = ${req.totalOrphans} em ${req.perDoc.length} doc(s)`);
  console.log("  (orfao cujo doc JA declara a morte na linha nao entra — e registro datado, nao divida)");
  for (const d of req.perDoc) {
    console.log(`     ${d.doc}  (${d.orphans.length}/${d.total})`);
    for (const o of d.orphans) console.log(`        ✗ ${o.path}   [${o.src}]`);
  }
  if (!req.perDoc.length) console.log("     ✓ nenhum orfao mudo em memory/requisitos.");

  // ⚠️ ate 2026-09-16 este bloco NAO EXISTIA: o C1 so saia no `--json`, e a lane do CI roda
  // o modo TEXTO. O audit rodava, achava, e nao contava a ninguem — gate mudo no unico modo
  // que o CI executa (§5 2026-08-02 "gate mudo com cara de cobertura"). Com o predicado em
  // `>=1` o silencio ficaria pior: 8 achados reais no corpus de hoje, zero visiveis.
  if (c1 === null) {
    console.log(`\n— C1 removido x MUDOU DE CASA: NAO MEDIDO (${c1NaoMedidoMotivo || 'sem base pra comparar'})`);
    console.log(`  (nao e 'nada a reportar' — e ausencia de medicao)`);
  } else {
    console.log(`\n— C1 (advisory): tombstone cujo conteudo VIVE em outro path = ${c1.achados.length}`);
    console.log('  (>=1 blob sobrevivente. A proporcao e a dispersao vao no achado: quem le decide');
    console.log('   se a redacao certa e "movido", "parcialmente movido" ou "removido" mesmo.)');
    for (const a of c1.achados) {
      const pct = (a.destino_concentracao * 100).toFixed(0);
      console.log(`     ⚠ ${a.doc}:${a.linha}`);
      console.log(`        ${a.ponteiro}  @${a.sha}`);
      console.log(`        ${a.sobrevivem}/${a.total} blobs vivos · ${a.destino_dirs} dir(s) · ${pct}% em ${a.destino_dominante}`);
    }
    if (!c1.achados.length) console.log('     ✓ nenhum.');
    if (c1.naoResolvidos.length) {
      const linhas = new Set(c1.naoResolvidos.map((x) => x.doc + ':' + x.linha)).size;
      console.log(`  ⓘ NAO-MEDIDOS: ${c1.naoResolvidos.length} par(es) linha×ponteiro em ${linhas} linha(s) —`);
      console.log(`     o \`<sha>^:<path>\` nao resolveu. "0 acusacoes" com nao-medidos != "tudo medido" (LC-33).`);
    }
  }
}

if (strict && r.totalOrphans > 0) process.exit(1);
process.exit(0);
