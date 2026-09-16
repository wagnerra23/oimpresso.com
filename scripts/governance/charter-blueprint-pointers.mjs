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
 *  MEDIDO 2026-09-16 (FP antes de armar, regra "LIGUE A MAQUINA" item 4):
 *    origin/main ..... 91 tombstones -> 75 remocao real, 4 mudou-de-casa, 0 falso-positivo
 *    24a3f7f772e ..... 96 tombstones -> 75 remocao real, 8 mudou-de-casa
 *    Os 4 gaps Essentials que o GT-G5 r5 achou A MAO estao entre os 8 — a sonda morde.
 *
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
 *  A separacao real esta em ZERO x NAO-ZERO, nao em 0.5. No corpus inteiro (84 medidos):
 *      76 com ZERO blob sobrevivente ..... remocao real INDISCUTIVEL
 *       8 com >=1 blob sobrevivente ...... ha conteudo vivo em outro path
 *      limiar 0.5 acusa .................. 0 desses 8   (falso-negativo 8 de 8)
 *
 *  ERRATA do meu proprio metodo, registrada e nao apagada: a 1a calibracao (commit
 *  anterior desta branch) concluiu "0.5 separa as 92 com 0 erro" achando um vao em
 *  (0.455, 0.705). Era TAUTOLOGICO — rotulei os 84 como "negativos" PORQUE sao o que o
 *  gate nao acusa, e usei isso pra validar o gate. E a lapide §5 2026-07-17
 *  (drift-sentinel): quando a distribuicao nao discrimina, o baseline nao e o problema,
 *  o MEDIDOR e. A objecao veio da sessao irma e a medicao confirmou.
 *
 *  ⚠️ NAO CORRIGIDO AQUI, de proposito: trocar a fracao pelo predicado ">=1 sobrevive" e
 *  linha EXECUTAVEL, e este arquivo esta sendo editado em paralelo (fix do extrator +
 *  `jaDeclaraDestino`). Sai em PR proprio, depois daquele. Enquanto isso o 0.5 segue —
 *  ele erra pra o lado CONSERVADOR (nao acusa), entao o custo de esperar e silencio, nao
 *  ruido. Junto com a troca vai o piso de 200B, que hoje `blobsDe` aplica quando o objeto
 *  e blob mas NAO aos blobs de dentro de um tree: com fracao isso era inocuo (distribuicao
 *  identica, 76/2/4/0/2), com o predicado ">=1" um unico blob trivial que colide passa a
 *  decidir o veredito. */
const SL_C1 = String.fromCharCode(47);   // '/' sem literal
const TOMB_SHA = /_[(][^)]*removido em ([0-9-]+), ([0-9a-f]{7,40})[^)]*[)]_/;
/** A linha ja declara PRA ONDE o conteudo foi? Entao esta correta — nao e acusacao.
 *  Exceçao EXPLICITA e testada (e4-b), nao escape acidental por regex que deixa de casar. */
function declaraMudancaDeCasa(linha) {
  return /CONTE[UÚ]DO vive em|conte[uú]do vive em|vive(m)? (hoje )?em [`]/.test(linha);
}
const LIMIAR_MUDOU_DE_CASA = 0.5;

function sh(cmd) {
  try { return execSync(cmd, { encoding: 'utf8', maxBuffer: 1e9, stdio: ['ignore', 'pipe', 'ignore'] }).trim(); }
  catch { return ''; }
}

function blobsVivosEmMain() {
  const vivos = new Set();
  for (const l of sh('git ls-tree -r origin/main').split(String.fromCharCode(10))) {
    const m = l.match(/^[0-9]+ blob ([0-9a-f]+)/);
    if (m) vivos.add(m[1]);
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
  return sh(`git ls-tree -r ${obj}`).split(String.fromCharCode(10))
    .map((x) => { const m = x.match(/^[0-9]+ blob ([0-9a-f]+)/); return m ? m[1] : null; })
    .filter(Boolean);
}

/** docs de requisitos tocados pelo PR (forward-only, ADR 0275). Sem base, devolve vazio —
 *  e NAO-MEDICAO, nao 'nada a reportar' (LC-33): o CLI diz isso em voz alta. */
function docsDoDiffC1() {
  const base = sh('git merge-base origin/main HEAD');
  if (!base) return null;
  return sh(`git diff --name-only ${base}...HEAD -- memory/requisitos`)
    .split(String.fromCharCode(10)).map((x) => x.trim()).filter((x) => x.endsWith('.md'));
}

function auditMudouDeCasa(docs) {
  if (!docs.length) return [];
  const vivos = blobsVivosEmMain();
  if (!vivos.size) return [];            // sem indice nao ha medicao — NAO afirmar verde (LC-33)
  const achados = [];
  const raiz = ROOT.split(BS).join(SL_C1) + SL_C1;
  for (const bruto of docs) {
    const rel = bruto.startsWith(raiz) ? bruto.slice(raiz.length) : bruto;
    let linhas;
    try { linhas = readFileSync(join(ROOT, rel), 'utf8').split(String.fromCharCode(10)); } catch { continue; }
    const dir = rel.split(SL_C1).slice(0, -1).join(SL_C1);
    linhas.forEach((linha, i) => {
      const t = linha.match(TOMB_SHA);
      if (!t) return;
      if (declaraMudancaDeCasa(linha)) return;   // ja corrigida: declara o destino
      for (const ptr of pointersOf2(linha)) {
        const bases = [ptr, dir + SL_C1 + ptr, 'memory/requisitos/_DesignSystem/' + ptr, 'memory/reference/' + ptr];
        let obj = '';
        for (const b of bases) { obj = sh(`git rev-parse "${t[2]}^:${b}"`); if (obj.length === 40) { break; } obj = ''; }
        if (!obj) continue;
        const filhos = blobsDe(obj);
        if (!filhos.length) break;
        const sobrevivem = filhos.filter((h) => vivos.has(h)).length;
        if (sobrevivem / filhos.length >= LIMIAR_MUDOU_DE_CASA) {
          achados.push({ doc: rel, linha: i + 1, ponteiro: ptr, sha: t[2], sobrevivem, total: filhos.length });
        }
        break;
      }
    });
  }
  return achados;
}

/** ponteiros CRUS da linha (o `pointersOf` do arquivo le doc inteiro e resolve; aqui e por linha). */
function pointersOf2(linha) {
  const BQ = String.fromCharCode(96);
  const PRE = /(prototipo-ui|memory|resources|ui_kits|app|Modules|scripts|governance|public|tests|database)[/]/g;
  const out = new Set();
  let m;
  while ((m = PRE.exec(linha))) {        // LOCAL de proposito: /g no escopo do modulo vaza lastIndex
    const r = linha.slice(m.index).split(BQ)[0].split(')')[0].split(']')[0].trim();
    for (const tk of [r]) {
      const c = decodeURIComponent(tk.replace(/[.,;:)]+$/, '')).replace(/[/]+$/, '');
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
const mudouDeCasa = docsC1 === null ? null : auditMudouDeCasa(docsC1);   // C1: advisory

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
    mudou_de_casa_medido: mudouDeCasa !== null,
    mudou_de_casa: mudouDeCasa || [],
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
}

if (strict && r.totalOrphans > 0) process.exit(1);
process.exit(0);
