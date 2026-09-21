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

import { existsSync, statSync, readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join, resolve, dirname, basename, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { ehPrintSemantico } from '../.claude/hooks/block-ancora-no-olho.mjs';
import { read, frontmatter, walk } from './_lib-charter.mjs';
import { raizesDePages } from '../scripts/qa/page-path.mjs';
import { ultimaVerificacaoDe, KIND_LIVE_ONLY } from '../scripts/governance/cowork-mirror-freshness.mjs';

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

/** O parser de frontmatter do `_lib-charter` NÃO desaspa — devolve `"n/a (…)"` com as aspas.
 *  Medido 2026-08-25: 8 charters declaram `related_prototype` entre aspas, e 4 deles são
 *  `"n/a …"`, que assim escapavam do `ehDeclaracaoNa` e saíam como âncora `⚠️ NÃO MEDIDO`.
 *  Desaspar ANTES de classificar é o que faz o `n/a` valer igual escrito das duas formas. */
export function desasparValor(valor) {
  return String(valor ?? '').trim().replace(/^["']|["']$/g, '').trim();
}

/** `n/a …` em related_prototype é DECLARAÇÃO ("a tela nasce do DS"), não âncora.
 *  Puro e testável. O `anchor-content-check` (required) já pula esses por desenho —
 *  135 dos 158 charters declaram n/a legitimamente (medido 2026-08-11), então tratá-los
 *  como âncora seria falso-positivo em massa. O defeito era só o ✓ no output. */
export function ehDeclaracaoNa(valor) {
  return /^n\/a\b/i.test(desasparValor(valor));
}

// O protótipo tem UM lugar fixo e nunca troca de lugar — quem ENFORÇA isso é o
// `ancora-guard.mjs` (R1, [W] 2026-07-01). Aqui a constante é só de RESOLUÇÃO: charter que
// cita o arquivo pelo nome solto (`fiscal-page.jsx`) resolve nesse lugar, não em qualquer um.
const LUGAR_FIXO = 'prototipo-ui/cowork';

// ── FRESCOR DA ÂNCORA ────────────────────────────────────────────────────────────
//
// O `✓` desta ferramenta sempre significou "abri o arquivo e não achei fantasma". Ele
// NUNCA significou "este arquivo é o design vivo" — e leitor nenhum sabia disso.
//
// Custo medido em 2026-08-26: uma sessão rodou `ancora.mjs Arquivos/Index`, leu
// `âncora ✓`, abriu o `arquivos-page.jsx` do espelho e comparou com produção. O espelho
// era de 24/08; o Cowork vivo tinha 4 mudanças de 26/08 (chips em PT-BR, coluna
// renomeada, selo de prazo, ações de linha). A sessão concluiu que a divergência era
// "copy adaptada de propósito" — atribuiu INTENÇÃO de designer a uma defasagem do
// próprio espelho. Quem pegou foi o [W], no olho, olhando o protótipo vivo.
//
// O fato existia e era consultável: o ledger de frescor registrava `verified:
// ["oimpresso.com.html"]` — UM arquivo de 242. O `arquivos-page.jsx` nunca tinha sido
// verificado. Esta ferramenta tinha como saber e não perguntava.
//
// Aqui o `✓` do conteúdo é preservado (mede outra coisa, e mede bem); o que entra é uma
// linha SEPARADA que diz o estado de verificação. Não bloqueia nada e não muda exit code
// — é reporter. O que ele para de fazer é deixar o leitor supor frescor.
const LEDGER_REL = 'scripts/governance/.cowork-freshness-ledger.json';

/**
 * TODAS as entradas do ledger — o ledger é append-only e cada rodada mede um subconjunto.
 * Existe porque `ultimaRodada()` sozinha respondia "nunca verificado" pra todo arquivo fora
 * da ÚLTIMA linha. Medido 2026-08-27: 20 de 20 âncoras, FALSO em 12 (60%).
 */
export function entradasDoLedger(raizGit = REPO_DEFAULT) {
  try {
    const bruto = JSON.parse(readFileSync(join(raizGit, LEDGER_REL), 'utf8'));
    return Array.isArray(bruto) ? bruto : (bruto.entries || []);
  } catch {
    return [];
  }
}

/** Última rodada **de `--compare`** do ledger de frescor, ou `null` quando não há ledger legível.
 *
 * ⚠️ FILTRA `live-only`, e o motivo é medido. As duas espécies de rodada moram no MESMO
 * array append-only, mas respondem a perguntas diferentes: `--compare` mede "o espelho bate
 * com o vivo?" (arquivo a arquivo, produz `verified`/`staleList`); `--live-only` mede "o que
 * existe no vivo e nunca desceu?" (não olha arquivo do espelho, não produz veredito de
 * frescor de nenhum). Pegar a ÚLTIMA entrada crua faz uma rodada de live-only sequestrar o
 * papel de "última rodada" e DEGRADAR o veredito de todo arquivo: quem estava `STALE`
 * (medido e divergente) vira `SEM VEREDITO NOVO` (não medido) — troca informação por
 * ausência dela, silenciosamente.
 *
 * Medido em 2026-08-27, no próprio repo: uma rodada de `--live-only --ledger` (`14:16:35Z`)
 * empurrou a de compare (`2026-08-26T22:07:08Z`) e o `Jana/Index` saiu de `STALE` para
 * `SEM VEREDITO NOVO` sem que o espelho tivesse mudado um byte.
 *
 * O irmão `cowork-mirror-freshness.mjs` já se protege disso no `slaVerdict` (com selftest
 * "entrada live-only NAO vira veredito do compare"); este consumidor lia o mesmo array sem
 * a mesma guarda. Não é régua nova — é a guarda existente aplicada ao segundo leitor.
 */
export function ultimaRodada(raizGit = REPO_DEFAULT) {
  try {
    const bruto = JSON.parse(readFileSync(join(raizGit, LEDGER_REL), 'utf8'));
    const entradas = Array.isArray(bruto) ? bruto : (bruto.entries || []);
    const deCompare = entradas.filter((e) => e && e.kind !== KIND_LIVE_ONLY);
    return deCompare.length ? deCompare[deCompare.length - 1] : null;
  } catch {
    return null;
  }
}

/**
 * Estado de verificação de UM arquivo do espelho — puro, pra ser testável.
 *
 * `verificado` exige as DUAS pernas: estar na lista `verified` da rodada E o hash local
 * ainda bater com o `verifiedHash` registrado. Sem a segunda, uma edição posterior ao
 * export herdaria o selo de uma medição que já não descreve o arquivo.
 *
 * @returns {{estado:'verificado'|'stale'|'nunca'|'sem-ledger', data?:string}}
 */
export function frescorDoEspelho(relPath, rodada, hashLocal) {
  if (!rodada) return { estado: 'sem-ledger' };
  if ((rodada.staleList || []).includes(relPath)) return { estado: 'stale', data: rodada.date };
  if (!(rodada.verified || []).includes(relPath)) return { estado: 'nunca', data: rodada.date };
  const registrado = (rodada.verifiedHash || {})[relPath];
  if (registrado && hashLocal && registrado !== hashLocal) return { estado: 'stale', data: rodada.date };
  return { estado: 'verificado', data: rodada.date };
}

/** sha256 do arquivo, ou `null` quando não abre (não inventa hash pra não fabricar veredito). */
function hashDoArquivo(caminho) {
  try {
    return createHash('sha256').update(readFileSync(caminho)).digest('hex');
  } catch {
    return null;
  }
}

const RE_TOKEN_ARQUIVO = /[\w.\-/]+\.(?:jsx|html|css|tsx)\b/i;
const ehArquivo = (p) => { try { return statSync(p).isFile(); } catch { return false; } };

/** Primeiro token que NOMEIA um arquivo dentro do valor, ou null se o valor não nomeia nenhum. */
export function tokenDeArquivo(valor) {
  const m = desasparValor(valor).match(RE_TOKEN_ARQUIVO);
  return m ? m[0] : null;
}

/**
 * Caminho (relativo a `raiz`) que a âncora aponta — ou `null` se o valor NÃO NOMEIA arquivo.
 *
 * Existe porque `related_prototype` é campo de texto livre e o valor vem em 4 formatos no
 * corpus real (medido 2026-08-25 sobre os 210 charters que declaram o campo):
 *   1. caminho limpo .................................. 55  → resolve como está
 *   2. caminho + prosa entre parênteses ............... 5   → o parêntese entrava no path
 *   3. prosa ANTES do arquivo (`"F1 Cowork — x.jsx"`) . 4   → nome solto, resolve no LUGAR_FIXO
 *   4. não nomeia arquivo (PT-0X, diretório) .......... 11  → não há o que LER
 * Os formatos 2 e 3 saíam `⚠️ NÃO MEDIDO` — selo honesto sobre uma medição que nunca ia
 * acontecer, e o 4 saía igual, misturando "não consegui" com "não há nada aqui".
 *
 * A ORDEM importa e é defensiva: o valor cru é testado PRIMEIRO, então os 55 que já
 * resolvem hoje não podem regredir por causa do regex (FP medido = 0). A extração é
 * fallback, nunca o caminho principal.
 *
 * ⚠️ Duplicação declarada (§5 2026-08-02 — "ou unifica, ou declara por que as duas existem"):
 * há outros 3 extratores no repo, e nenhum servia aqui:
 *   • `render-proto-baseline::primeiroToken` — pega o 1º token; cego ao formato 3, e importar
 *     de lá seria CICLO (aquele módulo importa este).
 *   • `anchor-content-check::anchorFile` — devolve só o nome do arquivo, perde o diretório.
 *   • `anchor-content-check::anchorRelPath` — corta o prefixo até `cowork/`, devolve caminho
 *     relativo A OUTRA raiz (a do cowork), não à raiz de leitura da âncora.
 * Convergir os 4 num dono só é trabalho de PR próprio: `anchor-content-check` é gate required.
 */
export function caminhoDaAncora(valor, raiz = REPO_DEFAULT) {
  const cru = desasparValor(valor);
  if (cru && ehArquivo(resolve(raiz, cru))) return cru;                    // 1
  const tok = tokenDeArquivo(cru);
  if (!tok) return null;                                                   // 4
  if (ehArquivo(resolve(raiz, tok))) return tok;                           // 2
  if (!tok.includes('/') && ehArquivo(resolve(raiz, LUGAR_FIXO, tok))) return `${LUGAR_FIXO}/${tok}`; // 3
  return tok; // nomeia arquivo mas não abre — devolve o token pro ⚠️ dizer QUAL path falhou
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
  // `raiz` = onde o VALOR da âncora resolve em ARQUIVO, e ela NÃO é a mesma pras duas pernas:
  // a do charter é relativa ao repo, a do bundle é relativa ao staging (linha do `relative`
  // logo abaixo). Antes deste campo o consumidor não tinha como saber, resolvia tudo contra o
  // repo e a perna do bundle nunca era lida — ver o bloco de `defeitosDaAncora`.
  const raizRepo = resolve(repoRoot);
  const ancoras = [];
  // 1) protótipo aprovado declarado no charter (related_prototype)
  if (fm.related_prototype) ancoras.push({ tipo: 'related_prototype (charter)', valor: fm.related_prototype, raiz: raizRepo });
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
    if (cand) ancoras.push({ tipo: `-page.jsx (bundle · ${via})`, valor: relative(stagingDir, cand).replace(/\\/g, '/'), raiz: resolve(stagingDir) });
  }
  const liveTsx = repoTsx(fm.component);
  return {
    ok: true, query, charter: hit.charter,
    telaViva: liveTsx, ancoras, repoRoot: raizRepo,
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

/** Devolve { fantasmas[], naoMedidos[], lido, raiz } para o arquivo de âncora.
 *
 *  DUAS raízes — e conflacioná-las APAGA a medição (defeito de 2026-08-25):
 *    • `raizLeitura` — onde o ARQUIVO da âncora resolve. A âncora de charter é relativa ao
 *      repo; a de bundle (`--staging`) é gravada relativa AO STAGING (`relative(stagingDir,…)`).
 *    • `repoRoot`    — onde o `git grep` procura a classe. É SEMPRE o repo, nunca o staging.
 *
 *  O defeito: o printer chamava com UM argumento só, o default caía em REPO_DEFAULT, e a
 *  âncora de bundle virava `<repo>/<caminho-relativo-ao-staging>` — path que nunca existe.
 *  Toda âncora vinda de bundle saía `⚠️ NÃO MEDIDO` e o P-1 (símbolo fantasma) nunca rodava
 *  nessa perna: o selo era honesto, mas estruturalmente inalcançável — LC-11 no eixo do
 *  CONSUMIDOR, a mesma família do fail-open que o `lido` já tinha matado dentro da função.
 *
 *  E o conserto INGÊNUO (passar o staging como `repoRoot`) só TROCA o buraco de lugar.
 *  Medido 2026-08-25, com controle positivo, mesmo símbolo nas duas raízes:
 *      cwd=<repo>     → status 0   → classeExiste = true
 *      cwd=<staging>  → status 128 `fatal: not a git repository` → classeExiste = null
 *  Ou seja: o P-1 apagaria do mesmo jeito, agora pelo lado do símbolo em vez do da leitura.
 *  Por isso as duas raízes andam SEPARADAS, e não como um parâmetro só.
 */
export async function defeitosDaAncora(ancoraRel, repoRoot = REPO_DEFAULT, raizLeitura = repoRoot) {
  const abs = resolve(raizLeitura, ancoraRel);
  // ⚠️ `read` do _lib-charter devolve NULL em vez de lançar — `try/catch` aqui
  // nunca dispararia, e o "arquivo ausente" viraria "0 fantasmas" (fail-open).
  // Pego no próprio selftest. Vazio só é evidência quando a leitura aconteceu.
  const txt = await read(abs);
  if (txt === null || txt === undefined) return { fantasmas: [], naoMedidos: [], lido: false, raiz: raizLeitura };
  const fantasmas = [], naoMedidos = [];
  for (const s of simbolosCitados(txt)) {
    const ex = await classeExiste(s, repoRoot);
    if (ex === null) naoMedidos.push(s); else if (!ex) fantasmas.push(s);
  }
  return { fantasmas, naoMedidos, lido: true, raiz: raizLeitura };
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
      // DUAS raízes: o `git grep` sempre no repo; a LEITURA na raiz da própria âncora
      // (staging pra âncora de bundle). Passar só uma era o defeito de 2026-08-25.
      const raizGit = r.repoRoot || REPO_DEFAULT;
      const raizLeitura = a.raiz || raizGit;
      // Uma leitura do ledger por âncora — barata, e mantém a função de frescor pura.
      const rodadaFrescor = ultimaRodada(raizGit);
      const entradasFrescor = entradasDoLedger(raizGit);
      // CLASSIFICAR antes de medir. `null` = o valor não nomeia arquivo nenhum: isso não é
      // "não consegui medir", é "não há o que ler" — colapsar os dois num ⚠️ só inflava o
      // balde de não-medidos com 11 charters que nunca teriam arquivo pra abrir.
      const caminho = caminhoDaAncora(a.valor, raizLeitura);
      if (caminho === null) {
        console.log(`  sem arquivo: [${a.tipo}] ${a.valor}`);
        console.log('               (o valor não nomeia arquivo .jsx/.html/.css/.tsx — nada a LER aqui.');
        console.log('                Pode ser declaração de Padrão de Tela, ou related_prototype incompleto.)');
        continue;
      }
      const d = await defeitosDaAncora(caminho, raizGit, raizLeitura);
      // `✓` exige LEITURA. `lido:false` = não consegui abrir o arquivo da âncora (path que
      // não resolve — p.ex. `arquivo.jsx (PT-04 Dashboard)`, onde o parêntese entra no path).
      // Sem esta perna o printer imprimia `✓` com 0 fantasmas por AUSÊNCIA de medição — o
      // fail-open que a própria defeitosDaAncora comenta ter matado na função, e que voltava
      // aqui, no consumidor. É LC-11/§5 2026-07-29 (instrumento afirma verde sem ter medido).
      const selo = !d.lido ? '⚠️' : d.fantasmas.length ? '⚠️' : '✓';
      console.log(`  âncora ${selo}:   [${a.tipo}] ${a.valor}`);
      // O valor é texto livre; quando o caminho medido não é o valor cru, dizer QUAL foi —
      // senão o leitor não sabe se o ✓/⚠️ fala do arquivo que ele acha que declarou.
      if (caminho !== desasparValor(a.valor)) console.log(`              → resolvido em: ${caminho}`);

      // Frescor — eixo INDEPENDENTE do selo acima (ver bloco FRESCOR DA ÂNCORA no topo).
      // Só faz sentido pro espelho do Cowork: `.tsx` do repo e arquivo de fora do espelho
      // não são retrato de nada, e cobrar frescor deles seria alarme falso por construção.
      const relEspelho = relative(join(raizGit, LUGAR_FIXO), caminho).replace(/\\/g, '/');
      if (relEspelho && !relEspelho.startsWith('..')) {
        const f = frescorDoEspelho(relEspelho, rodadaFrescor, hashDoArquivo(caminho));
        if (f.estado === 'verificado') {
          console.log(`              ✓ frescor: verificado contra o Cowork vivo em ${f.data}`);
        } else if (f.estado === 'stale') {
          console.log(`              ✗ frescor: STALE — o Cowork vivo mudou depois da última medição (${f.data}).`);
          console.log('                 O que você abrir aqui NÃO é o design atual. Refresque antes de comparar.');
        } else if (f.estado === 'nunca') {
          // NÃO afirmar "nunca" sem ter varrido o ledger inteiro: `f.estado` só conhece a
          // ÚLTIMA rodada. O oráculo de "quando este arquivo foi verificado" é do dono
          // (cowork-mirror-freshness::ultimaVerificacaoDe), que varre todas as entradas.
          const ant = ultimaVerificacaoDe(entradasFrescor, relEspelho);
          const medidos = (rodadaFrescor?.sync ?? 0) + (rodadaFrescor?.stale ?? 0);
          const totalLedger = rodadaFrescor?.files ?? null;
          const cobertura = totalLedger ? ` (mediu ${medidos} de ${totalLedger})` : '';
          if (ant.data) {
            console.log(`              ⚠️ frescor: SEM VEREDITO NOVO — verificado em ${ant.data}, e a última rodada (${f.data})${cobertura} não o incluiu.`);
            console.log('                 Verificação antiga NÃO prova frescor de hoje — o Cowork vivo pode ter mudado desde então.');
          } else {
            console.log(`              ⚠️ frescor: NUNCA VERIFICADO — nenhuma rodada do ledger mediu este arquivo. Última rodada: ${f.data}${cobertura}.`);
          }
          console.log('                 O `✓` acima fala do CONTEÚDO (li o arquivo, sem fantasma), não do frescor.');
          console.log('                 Este arquivo pode ser uma cópia velha do Cowork vivo, e ninguém mediu.');
          console.log('                 Medir: node scripts/governance/cowork-mirror-freshness.mjs --sla');
        } else {
          console.log('              ⚠️ frescor: SEM LEDGER — nenhuma rodada de medição registrada.');
          console.log('                 Ausência de ledger é ausência de medição, não saúde.');
        }
      }
      if (!d.lido) {
        console.log('              ⚠️ NÃO MEDIDO — o arquivo da âncora não pôde ser LIDO neste path.');
        console.log('                 Zero fantasma aqui é AUSÊNCIA DE MEDIÇÃO, não saúde.');
        // Sem dizer QUAL raiz foi tentada, o ⚠️ é honesto mas cego — foi o que fez o defeito
        // da raiz de staging sobreviver: a mensagem só sugeria a causa dos parênteses.
        console.log(`                 raiz tentada: ${d.raiz}`);
        console.log('                 O valor NOMEIA um arquivo, mas ele não abre nessa raiz —');
        console.log('                 âncora podre, ou raiz errada pra este charter.');
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
    // 2026-08-28: o `--list` lia SÓ o `related_prototype` e reportava "sem protótipo"
    // para 18 charters que declaram a fonte em `bundle_source`/`visual_source` — os mesmos
    // campos que o `resolveAncora` (L266-271) PREFERE. A porta contradizia a regra dura do
    // docblock (L11: âncora ∈ { related_prototype, -page.jsx do bundle via charter }) e
    // sub-reportava em SILÊNCIO: uma auditoria leu os 18 como "fonte ausente" e concluiu
    // que 34 telas ficariam de fora da onda de design. Elas já tinham âncora.
    // Medido: 42 sem `related_prototype` -> 18 salvos por bundle/visual, 24 gap real.
    // `via` é ADITIVO (mesmo critério de `charter`/`isNa` acima): diz QUAL perna resolveu,
    // pro consumidor distinguir design APROVADO de porte de bundle sem re-derivar.
    const doBundle = mockupJsx(fm.bundle_source) || mockupJsx(fm.visual_source);
    const source = fm.related_prototype || doBundle || mockupJsx(fm.component) || null;
    const via = fm.related_prototype ? 'related_prototype'
      : doBundle ? 'bundle_source/visual_source'
      : source ? 'component' : null;
    // hasSource = o charter DECLAROU a fonte de design (protótipo bespoke OU "n/a — segue DS"
    // explícito, que também vem em related_prototype). null = silencioso (gap real).
    // `charter` e `isNa` sao ADITIVOS (2026-08-26): o unico consumidor de `--list --json` e o
    // design-coverage (medido: 1 de 1), e ele precisava saber DE QUAL charter veio a linha e se a
    // fonte e declaracao `n/a` — sem isso ele contava `n/a` como ✅ pra sempre e escondia a tela
    // cuja fonte JA DESCEU pro espelho depois da decisao. `isNa` reusa `ehDeclaracaoNa`, o dono
    // dessa distincao neste mesmo arquivo — nao reimplementar (§5 2026-08-26).
    rows.push({ page: fm.page || relative(repoRoot, cf), source: source || '⚠️ sem protótipo declarado', hasSource: !!source, charter: relative(repoRoot, cf).split(String.fromCharCode(92)).join('/'), isNa: ehDeclaracaoNa(source), via });
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

  // ── BITE da RAIZ DE LEITURA da âncora de bundle (--staging) — 2026-08-25 ───
  // A perna `--staging` grava o valor RELATIVO ao staging, mas o printer media contra o
  // repo: TODA âncora de bundle saía `⚠️ NÃO MEDIDO` e o P-1 nunca rodava ali. O selftest
  // até então só exercitava âncora relativa ao repo — cego justamente nessa perna.
  // HERMÉTICO de propósito: o bundle real vive fora do git e `Downloads/`/`_cowork-handoff-
  // staging` é LUGAR PROIBIDO pro ancora-guard — o teste monta o próprio staging em tmp.
  const { mkdtemp, mkdir, writeFile, rm: apagar } = await import('node:fs/promises');
  const { tmpdir } = await import('node:os');
  const fx = await mkdtemp(join(tmpdir(), 'ancora-fx-'));
  const fxRepo = join(fx, 'repo');
  const fxStaging = join(fx, 'staging');
  await mkdir(join(fxRepo, 'resources', 'js', 'Pages', 'Fixture'), { recursive: true });
  await mkdir(join(fxStaging, 'projeto'), { recursive: true });
  await writeFile(join(fxRepo, 'resources', 'js', 'Pages', 'Fixture', 'Index.charter.md'),
    ['---', 'page: /fixture', 'component: resources/js/Pages/Fixture/Index.tsx',
      'bundle_source: fixture-page.jsx', '---', '# fixture'].join('\n'), 'utf8');
  // Cita UM símbolo que NÃO existe no repo e UM que existe — o detector tem que separar.
  await writeFile(join(fxStaging, 'projeto', 'fixture-page.jsx'),
    'const FONTES = { a: "NaoExisteNoRepoService", b: "SellsCockpitAggregator::build" };\n', 'utf8');

  // Staging SEPARADO só pro controle de ordem. Separado porque a ISCA tem, por construção, o
  // MESMO basename do alvo — e o resolvedor de bundle acha o mockup por basename, então plantar
  // a isca no staging de cima fazia o `find` pegar a isca e quebrava 2 asserções (pego no
  // bite-test, não na revisão). Nome COM espaço e acento de propósito: com um path só de
  // [\w.-/] o regex e o valor cru dão o mesmo resultado e o controle vira carimbo.
  const fxOrdem = join(fx, 'ordem');
  const SUB = 'pro jeto ção';
  const ALVO = `${SUB}/alvo-page.jsx`;
  // ISCA = o caminho que o REGEX casaria neste valor, plantado como arquivo REAL. Sem ela,
  // inverter a ordem de `caminhoDaAncora` mantinha o selftest verde (o regex casava um path
  // inexistente e caía no fallback, dando o mesmo resultado). Medido com bite-test.
  const ISCA = tokenDeArquivo(ALVO);
  await mkdir(join(fxOrdem, SUB), { recursive: true });
  await mkdir(join(fxOrdem, dirname(ISCA)), { recursive: true });
  await writeFile(join(fxOrdem, ALVO), '// ALVO: é o que o valor cru aponta\n', 'utf8');
  await writeFile(join(fxOrdem, ISCA), '// ISCA: o regex casa AQUI — não pode ser escolhida\n', 'utf8');

  const rb = await resolveAncora('Fixture/Index', { repoRoot: fxRepo, stagingDir: fxStaging });
  const ab = rb.ok ? rb.ancoras.find((a) => a.tipo.startsWith('-page.jsx')) : null;
  t('BITE staging: a âncora de bundle é resolvida do charter da fixture',
    !!ab && ab.valor === 'projeto/fixture-page.jsx');
  // `!!ab.raiz` antes do `resolve`: sem a guarda, remover o campo faz o selftest ESTOURAR
  // em vez de falhar — vermelho igual, mas engole as asserções seguintes e não diz o que quebrou.
  t('BITE staging: a âncora carrega a RAIZ de leitura (staging), não o repo',
    !!ab && !!ab.raiz && resolve(ab.raiz) === resolve(fxStaging));
  const dOk = ab ? await defeitosDaAncora(ab.valor, REPO_DEFAULT, ab.raiz) : { lido: false, fantasmas: [] };
  t('BITE staging: com a raiz certa o arquivo do bundle É LIDO (antes: NÃO MEDIDO sempre)',
    dOk.lido === true);
  t('BITE staging: o P-1 roda nessa perna e ACUSA o símbolo fantasma',
    dOk.fantasmas.includes('NaoExisteNoRepoService'));
  // Este é o controle que impede o conserto ingênuo (staging como repoRoot): se o `git grep`
  // rodasse no staging, ele sairia 128 → `naoMedidos`, e o símbolo real viraria não-medido.
  t('CONTROLE staging: o git grep segue no REPO — símbolo REAL não vira fantasma nem não-medido',
    dOk.lido === true && !dOk.fantasmas.includes('SellsCockpitAggregator')
      && !dOk.naoMedidos.includes('SellsCockpitAggregator'));
  // CONTROLE NEGATIVO — reproduz o defeito. Sem ele, o BITE acima passaria mesmo se o
  // conserto não fizesse nada (verde que não pode ficar vermelho = carimbo).
  const dRuim = ab ? await defeitosDaAncora(ab.valor, REPO_DEFAULT, REPO_DEFAULT) : { lido: true };
  t('CONTROLE staging: com a raiz do REPO o MESMO valor não é lido (é o defeito de 2026-08-25)',
    dRuim.lido === false);

  // ── CLASSIFICAÇÃO do valor de related_prototype (texto livre) — 2026-08-25 ─
  // 4 formatos no corpus; 2 deles nunca chegavam a ser lidos e 1 era confundido com
  // "não consegui medir". Cada BITE abaixo cobre um formato + o controle que o isola.
  t('BITE aspas: `"n/a (…)"` COM aspas é declaração (o parser de frontmatter não desaspa)',
    ehDeclaracaoNa('"n/a (herda PT-07 Feed/Timeline; segue o DS)"') === true);
  t('CONTROLE aspas: caminho real entre aspas NÃO vira declaração',
    ehDeclaracaoNa('"prototipo-ui/cowork/jana-merge.jsx"') === false);
  t('CONTROLE aspas: desasparValor não come aspas do MEIO do valor',
    desasparValor('"F1 Cowork — o arquivo "x" aqui"') === 'F1 Cowork — o arquivo "x" aqui');

  t('BITE formato 4: valor que NÃO nomeia arquivo devolve null (não é "não medido")',
    caminhoDaAncora('PT-01 (índice) + PT-02 (drawer de detalhe)') === null);
  t('BITE formato 4: diretório também não é arquivo',
    caminhoDaAncora('prototipo-ui/cowork/venda-menu/') === null);
  t('BITE formato 1: caminho limpo resolve como está (os 55 que já funcionam)',
    caminhoDaAncora('prototipo-ui/cowork/jana-merge.jsx') === 'prototipo-ui/cowork/jana-merge.jsx');
  t('BITE formato 2: caminho + prosa entre parênteses — o parêntese sai do path',
    caminhoDaAncora('prototipo-ui/cowork/jana-merge.jsx (PT-04 Dashboard)') === 'prototipo-ui/cowork/jana-merge.jsx');
  t('BITE formato 3: prosa ANTES, nome solto — resolve no LUGAR_FIXO',
    caminhoDaAncora('"F1 Cowork — fiscal-page.jsx §FxNotasPage"') === 'prototipo-ui/cowork/fiscal-page.jsx');
  // Sem este, o formato 3 poderia estar "resolvendo" contra qualquer diretório do repo.
  t('CONTROLE formato 3: nome solto que NÃO existe no lugar fixo não inventa caminho',
    caminhoDaAncora('"F1 Cowork — __nao-existe__.jsx §X"') === '__nao-existe__.jsx');
  // A ordem é defensiva: valor cru PRIMEIRO. Se o regex passasse na frente, um caminho válido
  // com caractere fora de [\w.\-/] seria truncado — é o que este par prova que não acontece.
  t('CONTROLE ordem: valor cru vence o regex — a ISCA que o regex casaria NÃO é escolhida',
    ISCA !== ALVO                       // a isca precisa ser MESMO um caminho diferente
      && caminhoDaAncora(ALVO, fxOrdem) === ALVO);
  t('CONTROLE: valor vazio não vira o próprio diretório-raiz',
    caminhoDaAncora('') === null && caminhoDaAncora(undefined) === null);

  // ── FRESCOR: os 4 estados + o controle que impede o selo herdado ──────────────
  // A rodada de fixture imita a forma real do ledger (date/verified/verifiedHash/staleList).
  const rodada = {
    date: '2026-08-24T21:57:19.692Z',
    verified: ['oimpresso.com.html'],
    verifiedHash: { 'oimpresso.com.html': 'aaa111' },
    staleList: ['clientes-page.jsx'],
  };

  t('FRESCOR: arquivo medido, hash ainda batendo → verificado',
    frescorDoEspelho('oimpresso.com.html', rodada, 'aaa111').estado === 'verificado');

  t('FRESCOR: arquivo fora da rodada → NUNCA (é o caso do arquivos-page.jsx em 2026-08-26)',
    frescorDoEspelho('arquivos-page.jsx', rodada, 'bbb222').estado === 'nunca');

  t('FRESCOR: arquivo na staleList → stale',
    frescorDoEspelho('clientes-page.jsx', rodada, 'ccc333').estado === 'stale');

  t('FRESCOR: sem ledger não vira verde — ausência de medição não é saúde',
    frescorDoEspelho('qualquer.jsx', null, 'ddd444').estado === 'sem-ledger');

  // O controle que dá sentido ao resto: estar na lista `verified` NÃO basta se o arquivo
  // mudou depois. Sem esta perna, uma edição local herdaria o selo de uma medição velha —
  // que é a forma exata do defeito que este bloco inteiro existe pra impedir.
  t('CONTROLE FRESCOR: medido mas hash MUDOU → stale, não verificado',
    frescorDoEspelho('oimpresso.com.html', rodada, 'HASH-DIFERENTE').estado === 'stale');

  t('CONTROLE FRESCOR: medido e sem hash registrado não inventa stale',
    frescorDoEspelho('x.jsx', { ...rodada, verified: ['x.jsx'], verifiedHash: {} }, 'zzz').estado === 'verificado');

  // ── LEDGER APPEND-ONLY: o oráculo é o dono, e ele varre TODAS as rodadas ──────
  // Regressão de 2026-08-27: este arquivo lia `entradas[entradas.length - 1]` e afirmava
  // "NUNCA VERIFICADO" pra tudo que não estivesse na ÚLTIMA linha. Medido: 20 de 20 âncoras,
  // FALSO em 12 (60%) — `clientes-page.jsx` e `vendas-page.jsx` tinham sido verificados em
  // 2026-08-17 e o reporter dizia que nunca. Cada rodada mede ~1 arquivo, então a última
  // linha nunca descreve o conjunto (§5 2026-08-11: listagem parcial não é prova de ausência).
  const ledgerFx = [
    { date: '2026-08-17T12:41:33.794Z', verified: ['clientes-page.jsx'], verifiedHash: { 'clientes-page.jsx': 'v17' } },
    { date: '2026-08-26T22:07:08.661Z', verified: [], staleList: ['jana-merge.jsx'] },
  ];

  t('BITE LEDGER: verificado em rodada ANTIGA é achado — a última linha não é o ledger',
    ultimaVerificacaoDe(ledgerFx, 'clientes-page.jsx').data === '2026-08-17T12:41:33.794Z');

  t('CONTROLE LEDGER: arquivo que nenhuma rodada mediu segue sem data — não inventa verificação',
    ultimaVerificacaoDe(ledgerFx, 'nunca-medido.jsx').data === null);

  // Sem este, "varrer todas" poderia estar devolvendo a PRIMEIRA em vez da mais recente.
  t('CONTROLE LEDGER: entre duas verificações do mesmo arquivo vence a MAIS RECENTE',
    ultimaVerificacaoDe([
      { date: '2026-01-01T00:00:00.000Z', verified: ['x.jsx'], verifiedHash: { 'x.jsx': 'velho' } },
      { date: '2026-08-01T00:00:00.000Z', verified: ['x.jsx'], verifiedHash: { 'x.jsx': 'novo' } },
    ], 'x.jsx').hash === 'novo');

  // O eixo frescor NUNCA vira ✓ por ter sido medido no passado: verificação antiga é ⚠️.
  t('CONTROLE FRESCOR: verificação antiga NÃO promove a verificado no estado da última rodada',
    frescorDoEspelho('clientes-page.jsx', ledgerFx[1], 'v17').estado === 'nunca');

  await apagar(fx, { recursive: true, force: true });

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
