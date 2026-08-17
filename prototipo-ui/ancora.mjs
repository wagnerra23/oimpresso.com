#!/usr/bin/env node
// ancora.mjs — a ÂNCORA de uma tela é COMPUTADA do charter, nunca escolhida no olho.
//
// Por que existe (incidente #7, 2026-06-30): o agente, pra comparar "a tela viva vs o
// design", pegou `audit-financeiro.png` (um PRINT DE AUDITORIA — estado velho sendo
// criticado) e apresentou como "o design". DUAS vezes. O mecanismo de detecção existia,
// mas nada FORÇAVA usá-lo nem IMPEDIA pegar um png solto. Wagner: "já deveria ter uma
// máquina pra isso." Esta é a máquina: dado uma tela, ela resolve, do charter canônico,
// QUAL é a fonte-de-design legítima — e diz explicitamente o que NÃO é âncora.
//
// Regra dura: âncora ∈ { related_prototype do charter, -page.jsx do bundle via charter }.
// audit-*.png / critique / screenshot solto NUNCA é âncora.
//
// Uso:
//   node prototipo-ui/ancora.mjs <tela>            # tela = rota (/financeiro/unificado)
//                                                  #   ou Mod/Tela (Financeiro/Unificado)
//                                                  #   ou caminho .tsx
//   node prototipo-ui/ancora.mjs <tela> --staging <dir>   # + resolve o -page.jsx do bundle
//   node prototipo-ui/ancora.mjs --list            # todas as telas + suas âncoras
//   node prototipo-ui/ancora.mjs --selftest        # fixture hermético
//
// Exit: 0 = âncora resolvida | 1 = sem charter (NÃO invente — registre/pergunte) | 2 = uso

import { existsSync } from 'node:fs';
import { join, resolve, dirname, basename, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { ehPrintSemantico } from '../.claude/hooks/block-ancora-no-olho.mjs';
import { read, frontmatter, walk } from './_lib-charter.mjs';
import { raizesDePages } from '../scripts/qa/page-path.mjs';

const HERE = dirname(fileURLToPath(import.meta.url)); // prototipo-ui/
const REPO_DEFAULT = resolve(HERE, '..');

// ── helpers de leitura: read/frontmatter/walk vêm da lib compartilhada ────────
// (eram cópias locais idênticas às de detectar-telas — agora _lib-charter.mjs é a fonte única)
export { frontmatter }; // re-exporta pra preservar a API pública de ancora.mjs

// extrai 1º path de repo (.tsx) de um texto livre
export function repoTsx(text) {
  if (!text) return null;
  const m = text.match(/resources\/js\/Pages\/[\w./-]+\.tsx/);
  return m ? m[0] : null;
}
// extrai 1º mockup -page.jsx citado (NUNCA um audit/critique png)
export function mockupJsx(text) {
  if (!text) return null;
  const m = text.match(/[\w.-]*-page\.jsx/);
  return m ? m[0] : null;
}

// ── "print de auditoria não é âncora": FONTE ÚNICA = o hook ────────────────────
// Auditoria 2026-06-30 pegou DUAS denylists divergindo (esta tinha `screenshot`, o hook tinha
// `antig|adversari`). Agora reusa ehPrintSemantico do hook — uma definição só, não evolui à parte.
// É helper de MENSAGEM (o GATE real de âncora é a proveniência por charter, no hook::decidir).
// Auditoria: frontmatter/walk extraídos pra _lib-charter.mjs (fonte única); a denylist segue no hook.
export const ehAncoraIlegitima = ehPrintSemantico;

/** `n/a …` em related_prototype é DECLARAÇÃO ("a tela nasce do DS"), não âncora.
 *  Puro e testável. O `anchor-content-check` (required) já pula esses por desenho —
 *  135 dos 158 charters declaram n/a legitimamente (medido 2026-08-11), então tratá-los
 *  como âncora seria falso-positivo em massa. O defeito era só o ✓ no output. */
export function ehDeclaracaoNa(valor) {
  return typeof valor === 'string' && /^\s*n\/a\b/i.test(valor);
}

// normaliza a query da tela → tokens comparáveis
function norm(s) { return (s || '').toLowerCase().replace(/\\/g, '/').replace(/\.(tsx|charter\.md)$/i, '').replace(/\/index$/i, ''); }

// ── núcleo: resolve a âncora de UMA tela a partir dos charters do repo ────────
export async function resolveAncora(query, { repoRoot = REPO_DEFAULT, stagingDir = null } = {}) {
  // Git Bash (MSYS) mangleia arg iniciado em "/" pra "<raiz-msys>/<rota>" (ex.:
  // "/financeiro/unificado" vira "C:/Program Files/Git/financeiro/unificado") e a máquina
  // responderia "sem charter" FALSO. Detecção: path absoluto Windows que NÃO existe no disco
  // → tenta os sufixos como rota original (pegadinha catalogada 2026-07-01).
  if (/^[a-z]:[\\/]/i.test(query) && !existsSync(query)) {
    const partes = query.replace(/\\/g, '/').split('/').filter(Boolean);
    for (let i = 1; i < partes.length; i++) {
      const cand = '/' + partes.slice(i).join('/');
      const r = await resolveAncora(cand, { repoRoot, stagingDir });
      if (r.ok) return { ...r, query, avisoMangle: `query recebida mangleada pelo MSYS ("${query}") — recuperada como "${cand}". Use MSYS_NO_PATHCONV=1 no Git Bash.` };
    }
  }
  // DUAS raízes desde o PR #5686 (núcleo + `Modules/<X>/Resources/js/Pages`). Varrer só o núcleo
  // fazia a busca por query NUNCA achar charter de tela migrada. E o efeito passa daqui: este
  // arquivo é o dono da resolução de âncora, e `design-coverage`, `ancora-guard` e
  // `integrity-check` derivam DELE — um cego aqui cega os três.
  const charters = (await Promise.all(raizesDePages(repoRoot).map((r) => walk(r))))
    .flat().filter((f) => f.endsWith('.charter.md')); // [busca-por-query]
  const q = norm(query);

  let hit = null;
  for (const cf of charters) {
    const fm = frontmatter(await read(cf));
    const page = norm(fm.page);                 // rota: /financeiro/unificado
    const comp = norm(fm.component);            // resources/js/Pages/Financeiro/Unificado/Index.tsx
    const relc = norm(relative(repoRoot, cf));  // .../Unificado/Index.charter.md
    if ((page && page === q) || (comp && comp.endsWith(q)) || (comp && q.endsWith(comp)) ||
        relc.includes(q) || (q && comp && comp.includes(q))) {
      hit = { charter: relative(repoRoot, cf).replace(/\\/g, '/'), fm };
      if (page === q || comp.endsWith(q)) break; // match forte ganha
    }
  }
  if (!hit) return { ok: false, query, motivo: 'sem charter pra essa tela — NÃO invente âncora; registre ou pergunte' };

  const fm = hit.fm;
  const ancoras = [];
  // 1) protótipo aprovado declarado no charter (related_prototype)
  if (fm.related_prototype) ancoras.push({ tipo: 'related_prototype (charter)', valor: fm.related_prototype });
  // 2) -page.jsx do bundle (se staging dado).
  // PREFERE o campo estruturado `bundle_source:` do charter (determinístico) — musing-elion 2026-06-30:
  // a heurística startsWith(dir) falhava quando o bundle nomeia o mockup pela RAIZ do módulo
  // (financeiro-page) e a tela vive em sub-pasta (Unificado). Só cai na heurística se não houver campo.
  if (stagingDir) {
    const stFiles = await walk(stagingDir);
    const declarado = mockupJsx(fm.bundle_source) || mockupJsx(fm.visual_source);
    let cand = declarado ? stFiles.find((f) => basename(f).toLowerCase() === declarado.toLowerCase()) : null;
    let via = cand ? 'bundle_source' : null;
    if (!cand) {
      const wanted = (basename(dirname(repoTsx(fm.component) || hit.charter)) || '').toLowerCase();
      cand = stFiles.find((f) => /-page\.jsx$/i.test(f) && basename(f).toLowerCase().startsWith(wanted));
      if (cand) via = 'heurística startsWith(dir)';
    }
    if (cand) ancoras.push({ tipo: `-page.jsx (bundle · ${via})`, valor: relative(stagingDir, cand).replace(/\\/g, '/') });
  }
  const liveTsx = repoTsx(fm.component);
  return {
    ok: true, query, charter: hit.charter,
    telaViva: liveTsx, ancoras,
    aviso: 'ÂNCORA = um dos itens acima. audit-*.png / critique / screenshot NUNCA é âncora.',
  };
}

// ── Defeito na âncora (P-1 "símbolo fantasma") ───────────────────────────────
// Resolver a âncora e carimbar `✓` não diz se ela PRESTA. Caso real 2026-08-13:
// `ancora.mjs Jana/Index` devolveu `jana-merge.jsx` com ✓, e aquele build cita 6
// serviços que NÃO EXISTEM no repo — defeito que o próprio [CC] catalogou em
// 2026-08-09 (P-1) num PR de conserto que nunca rodou. Um agente derivou dali.
// É a MESMA família do `n/a` logo abaixo: `✓` sobre algo que não sustenta é
// sinal de saúde falso (LC-10, eixo do OUTPUT).
//
// Escopo deliberadamente ESTREITO — só string literal com sufixo de classe de
// backend. Medido no corpus (108 .jsx de prototipo-ui/cowork): casa 6 símbolos,
// e o controle positivo passa (`SellsCockpitAggregator`/`ApuracaoService` existem
// e NÃO flagram). Não tenta adivinhar "design velho" por nome/pasta — guard
// sintático desse tipo tem 4 lápides no §5.
//
// 2026-08-13 — sufixo `::metodo` passou a ser tolerado. Motivo: o conserto do P-1
// trocou os 6 fantasmas pelo formato REAL do código (`Classe::metodo`, igual ao
// JANA_DRILL_FONTES), e a v1 do regex exigia a string ser SÓ a classe — ou seja,
// ficaria cega justamente pro formato correto, e um `FakeService::foo` futuro
// passaria batido. O guard teria ficado quieto por não ENXERGAR, não por aprovar
// (LC-13: verde por não-execução). Só a CLASSE é capturada e verificada; o método
// não é conferido (o oráculo barato é `git grep` de classe). FP medido ANTES da
// troca no corpus (116 .jsx/.js de prototipo-ui/cowork): ATUAL casa 0 · NOVO casa
// 1 (`SellsCockpitAggregator`, que EXISTE → não flagra) · zero falso-positivo.
const RE_SIMBOLO_BACKEND = /"([A-Z][A-Za-z0-9]*(?:Service|Aggregator|Job|Repository))(?:::[A-Za-z0-9_]+)?"/g;

/** Extrai os símbolos de backend citados como string literal. Puro = testável. */
export function simbolosCitados(text) {
  return [...new Set([...String(text || '').matchAll(RE_SIMBOLO_BACKEND)].map((m) => m[1]))].sort();
}

/**
 * `null` = NÃO CONSEGUI MEDIR (git ausente/erro) — nunca colapsa em "não existe".
 * Vazio não é evidência quando o comando pode ter falhado (§5 2026-07-31/08-01).
 */
async function classeExiste(nome, repoRoot) {
  const { spawnSync } = await import('node:child_process');
  const r = spawnSync('git', ['grep', '-q', '-E', `(class|interface) ${nome}\\b`, '--', 'Modules/', 'app/'],
    { cwd: repoRoot, encoding: 'utf8' });
  if (r.error || (r.status !== 0 && r.status !== 1)) return null;
  return r.status === 0;
}

/** Devolve { fantasmas[], naoMedidos[] } para o arquivo de âncora. */
export async function defeitosDaAncora(ancoraRel, repoRoot = REPO_DEFAULT) {
  const abs = resolve(repoRoot, ancoraRel);
  // ⚠️ `read` do _lib-charter devolve NULL em vez de lançar — `try/catch` aqui
  // nunca dispararia, e o "arquivo ausente" viraria "0 fantasmas" (fail-open).
  // Pego no próprio selftest. Vazio só é evidência quando a leitura aconteceu.
  const txt = await read(abs);
  if (txt === null || txt === undefined) return { fantasmas: [], naoMedidos: [], lido: false };
  const fantasmas = [], naoMedidos = [];
  for (const s of simbolosCitados(txt)) {
    const ex = await classeExiste(s, repoRoot);
    if (ex === null) naoMedidos.push(s); else if (!ex) fantasmas.push(s);
  }
  return { fantasmas, naoMedidos, lido: true };
}

async function printResolve(r) {
  if (!r.ok) { console.error(`✗ ${r.query}: ${r.motivo}`); return 1; }
  if (r.avisoMangle) console.log(`⚠️ ${r.avisoMangle}`);
  console.log(`ÂNCORA da tela: ${r.query}`);
  console.log(`  charter:    ${r.charter}`);
  console.log(`  tela viva:  ${r.telaViva || '—'}`);
  if (!r.ancoras.length) console.log('  âncora:     ⚠️ charter sem related_prototype nem -page.jsx — registre o protótipo');
  // `✓` só para âncora que RESOLVE em arquivo. `n/a` é uma DECLARAÇÃO ("segue o DS"),
  // legítima, mas não é âncora — imprimir "âncora ✓: n/a" é sinal de saúde falso e foi
  // o que fez uma sessão (2026-08-11) ler "tem âncora" onde não havia nenhuma.
  // Ver LC-10 (artefato afirmando o próprio estado) — aqui no eixo do OUTPUT.
  for (const a of r.ancoras) {
    if (ehDeclaracaoNa(a.valor)) {
      console.log(`  sem âncora: ${a.valor}`);
      console.log('              (declaração legítima — a tela nasce do DS. NÃO entra no anchor-content-check.)');
    } else {
      const d = await defeitosDaAncora(a.valor);
      // `✓` exige LEITURA. `lido:false` = não consegui abrir o arquivo da âncora (path que
      // não resolve — p.ex. `arquivo.jsx (PT-04 Dashboard)`, onde o parêntese entra no path).
      // Sem esta perna o printer imprimia `✓` com 0 fantasmas por AUSÊNCIA de medição — o
      // fail-open que a própria defeitosDaAncora comenta ter matado na função, e que voltava
      // aqui, no consumidor. É LC-11/§5 2026-07-29 (instrumento afirma verde sem ter medido).
      const selo = !d.lido ? '⚠️' : d.fantasmas.length ? '⚠️' : '✓';
      console.log(`  âncora ${selo}:   [${a.tipo}] ${a.valor}`);
      if (!d.lido) {
        console.log('              ⚠️ NÃO MEDIDO — o arquivo da âncora não pôde ser LIDO neste path.');
        console.log('                 Zero fantasma aqui é AUSÊNCIA DE MEDIÇÃO, não saúde.');
        console.log('                 Causa comum: sufixo entre parênteses entrando no path.');
      }
      if (d.fantasmas.length) {
        console.log(`              ⚠️ ÂNCORA COM DEFEITO — ${d.fantasmas.length} símbolo(s) citado(s) que NÃO existem no repo:`);
        for (const s of d.fantasmas) console.log(`                 · ${s}`);
        console.log('              As regras VISUAIS seguem válidas; o que ela diz sobre DADO/FONTE, não.');
        console.log('              Confira a fonte real antes de derivar (o charter costuma nomeá-la no anti-hook).');
      }
      if (d.naoMedidos.length) {
        console.log(`              ⚠️ NÃO MEDIDO (git indisponível) p/ ${d.naoMedidos.length} símbolo(s) — ausência NÃO comprovada.`);
      }
    }
  }
  console.log(`  ⛔ ${r.aviso}`);
  return 0;
}

async function listAll(repoRoot, asJson = false) {
  // idem `resolveAncora`: as duas raízes. Este é o `--list`, consumido por design-coverage,
  // ancora-guard e integrity-check — os três mediam 172 de 209 charters por causa desta linha.
  const charters = (await Promise.all(raizesDePages(repoRoot).map((r) => walk(r))))
    .flat().filter((f) => f.endsWith('.charter.md')); // [listAll]
  const rows = [];
  for (const cf of charters.sort()) {
    const fm = frontmatter(await read(cf));
    const source = fm.related_prototype || mockupJsx(fm.component) || null;
    // hasSource = o charter DECLAROU a fonte de design (protótipo bespoke OU "n/a — segue DS"
    // explícito, que também vem em related_prototype). null = silencioso (gap real).
    rows.push({ page: fm.page || relative(repoRoot, cf), source: source || '⚠️ sem protótipo declarado', hasSource: !!source });
    if (!asJson) console.log(`${(fm.page || relative(repoRoot, cf)).padEnd(40)} → ${source || '⚠️ sem protótipo declarado'}`);
  }
  if (asJson) console.log(JSON.stringify(rows, null, 2));
}

async function selftest() {
  let fails = 0;
  const t = (label, cond) => { const ok = !!cond; if (!ok) fails++; console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${label}`); };
  // contrato puro: audit/critique png nunca é âncora; -page.jsx é
  // n/a é declaração, não âncora — o `✓` aqui era sinal de saúde falso (2026-08-11)
  t('BITE n/a: "n/a (herda PT-04…)" é DECLARAÇÃO, não âncora', ehDeclaracaoNa('n/a (herda PT-04 Dashboard; segue o Padrão de Tela)') === true);
  t('BITE n/a: "n/a" cru também', ehDeclaracaoNa('n/a') === true);
  t('CONTROLE n/a: caminho real NÃO é declaração', ehDeclaracaoNa('prototipo-ui/cowork/jana-merge.jsx') === false);
  t('CONTROLE n/a: nome que só CONTÉM "na" não casa', ehDeclaracaoNa('prototipo-ui/cowork/nao-a-toa.jsx') === false);
  t('CONTROLE n/a: undefined não quebra', ehDeclaracaoNa(undefined) === false);
  t('audit-financeiro.png é ÂNCORA ILEGÍTIMA', ehAncoraIlegitima('audit-financeiro.png') === true);
  t('Tribunal-x.png é ilegítima', ehAncoraIlegitima('Tribunal-x.png') === true);
  t('financeiro-page.jsx NÃO é ilegítima', ehAncoraIlegitima('financeiro-page.jsx') === false);
  t('ph-financeiro2.png (visual aprovado) NÃO casa lista-negra', ehAncoraIlegitima('ph-financeiro2.png') === false);
  t('mockupJsx pega -page.jsx', mockupJsx('component: financeiro-page.jsx (window.X)') === 'financeiro-page.jsx');
  t('repoTsx pega o .tsx', repoTsx('resources/js/Pages/Financeiro/Unificado/Index.tsx ok') === 'resources/js/Pages/Financeiro/Unificado/Index.tsx');
  // resolve real contra os charters do repo (tela conhecida)
  const r = await resolveAncora('/financeiro/unificado');
  t('resolve /financeiro/unificado acha charter', r.ok === true && /Unificado/.test(r.charter || ''));
  // query mangleada pelo MSYS (Git Bash converte "/" inicial) DEVE recuperar a rota
  const rm = await resolveAncora('C:/Program Files/Git/financeiro/unificado');
  t('resolve query mangleada MSYS recupera /financeiro/unificado', rm.ok === true && /Unificado/.test(rm.charter || '') && !!rm.avisoMangle);
  // ── BITE do detector de âncora defeituosa (P-1 símbolo fantasma) ───────────
  // Morde no ruim E fica quieto no bom — sem o segundo, é carimbo, não teste.
  t('BITE fantasma: extrai símbolo de backend citado como string',
    simbolosCitados('const F = { inad: ["x", "y", "AnaliseInadimplenciaService"] }').join() === 'AnaliseInadimplenciaService');
  t('BITE fantasma: pega mais de um e deduplica',
    simbolosCitados('"AJob" "AJob" "BRepository"').join() === 'AJob,BRepository');
  t('CONTROLE fantasma: prosa sem string literal NÃO casa',
    simbolosCitados('usa o AnaliseInadimplenciaService aqui').length === 0);
  t('CONTROLE fantasma: sufixo fora da lista NÃO casa',
    simbolosCitados('"AnaliseInadimplenciaHelper" "FooController"').length === 0);
  t('CONTROLE fantasma: minúscula NÃO casa',
    simbolosCitados('"analiseService"').length === 0);
  t('BITE fantasma: sufixo ::metodo é tolerado e captura só a CLASSE',
    simbolosCitados('"FakeService::calcular"').join() === 'FakeService');
  t('CONTROLE fantasma: ::metodo de classe REAL captura a classe (e o existe() decide)',
    simbolosCitados('"SellsCockpitAggregator::buildInsightsAggregates"').join() === 'SellsCockpitAggregator');
  // contra a árvore REAL. Os 6 fantasmas do P-1 foram consertados em 2026-08-13 (a tabela
  // FONTE passou a citar SellsCockpitAggregator::<metodo>, lido do JANA_DRILL_FONTES). Este
  // par asserta o estado NOVO — e o bite do detector continua provado acima, em fixture, que
  // é onde ele pode morder sem depender da árvore estar suja.
  const dj = await defeitosDaAncora('prototipo-ui/cowork/jana-merge.jsx');
  t('BITE real: jana-merge.jsx foi LIDO (ausência não vira 0 fantasmas)', dj.lido === true);
  t('BITE real: zero fantasma na âncora da Jana (P-1 consertado em 2026-08-13)',
    dj.fantasmas.length === 0);
  t('CONTROLE real: o símbolo REAL citado é visto pelo detector e NÃO vira fantasma',
    !dj.fantasmas.includes('SellsCockpitAggregator'));
  const dc = await defeitosDaAncora('prototipo-ui/cowork/chat-jana.jsx');
  t('CONTROLE real: chat-jana.jsx (regras visuais) NÃO acusa fantasma',
    dc.lido === true && dc.fantasmas.length === 0);
  t('CONTROLE real: caminho inexistente não explode nem inventa fantasma',
    (await defeitosDaAncora('prototipo-ui/cowork/__nao-existe__.jsx')).lido === false);
  // ── BITE do fail-open do PRINTER (2026-08-17) ─────────────────────────────
  // A função já distinguia "não li" de "li e não achou"; o printResolve NÃO usava o
  // `lido` e estampava `✓` nos dois casos. Provado com o path que o sufixo quebra:
  // `…jana-merge.jsx (PT-04 Dashboard)` → resolve num arquivo inexistente → lido:false.
  // Sem estas 2 asserções, declarar `related_prototype: <path> (PT-0X)` silenciava os
  // 6 fantasmas da Jana e o comando reportava saúde. Um `✓` que some quando você mede
  // é pior que um `⚠️` honesto.
  const dSufixo = await defeitosDaAncora('prototipo-ui/cowork/jana-merge.jsx (PT-04 Dashboard)');
  t('BITE printer: path com sufixo entre parênteses NÃO é lido (não vira ✓ por ausência)',
    dSufixo.lido === false && dSufixo.fantasmas.length === 0);
  t('CONTROLE printer: o MESMO arquivo sem o sufixo É lido (o defeito era o path, não o arquivo)',
    (await defeitosDaAncora('prototipo-ui/cowork/jana-merge.jsx')).lido === true);

  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — âncora = charter, png de auditoria barrado, âncora defeituosa acusada.');
  process.exit(fails ? 1 : 0);
}

// ── main ─────────────────────────────────────────────────────────────────────
const argv = process.argv.slice(2);
const has = (f) => argv.includes(f);
const val = (f) => { const i = argv.indexOf(f); return i >= 0 && argv[i + 1] ? argv[i + 1] : null; };
const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);

if (invokedDirectly) {
  if (has('--selftest')) await selftest();
  else if (has('--list')) { await listAll(REPO_DEFAULT, has('--json')); process.exit(0); }
  else {
    const tela = argv.find((a) => !a.startsWith('--') && argv[argv.indexOf(a) - 1] !== '--staging');
    if (!tela) { console.error('uso: node prototipo-ui/ancora.mjs <tela> [--staging <dir>] | --list | --selftest'); process.exit(2); }
    const r = await resolveAncora(tela, { stagingDir: val('--staging') });
    process.exit(await printResolve(r));
  }
}
