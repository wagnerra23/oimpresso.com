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
// Regra dura: âncora ∈ { related_prototype do charter, -page.jsx do bundle via charter
// (`bundle_source`/`visual_source`) }. audit-*.png / critique / screenshot solto NUNCA é âncora.
//
// E o que a regra dura NÃO diz — e por isso foi lida como esquecimento em 2026-09-09:
// uma tela declara fonte em CINCO lugares, e os outros DOIS não são âncora **por decisão**,
// não por omissão: `mwart_pattern_reuse.blueprint_cowork` (reuso de pattern, ADR 0149) e
// `canon_reference` (referência de paridade do `*-visual-comparison.md`). Esta máquina os
// LÊ e os REPORTA rotulados, sem promover — medição e razão completas no bloco
// "AS DUAS CHAVES QUE DECLARAM FONTE E NÃO SÃO ÂNCORA", mais abaixo.
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
import { ultimaVerificacaoDe, KIND_LIVE_ONLY, liveOnlyVerdict } from '../scripts/governance/cowork-mirror-freshness.mjs';
import { COWORK_PROJECT_ID } from './protocolo.config.mjs';

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

// ── AS DUAS CHAVES QUE DECLARAM FONTE E **NÃO SÃO ÂNCORA** ───────────────────
//
// Uma tela declara "de onde veio o desenho" em CINCO lugares. Três são âncora e esta
// máquina os lê (`related_prototype` · `bundle_source` · `visual_source`). Os outros dois
// NÃO são, e até aqui o arquivo não dizia isso em lugar nenhum — o silêncio foi lido como
// esquecimento, e um inventário de 2026-09-09 registrou "a cadeia ignora 2 chaves" como
// defeito a corrigir. É LC-15 no eixo da OMISSÃO: quem não declara o que ignora convida a
// próxima sessão a "consertar" o que está certo.
//
// **`mwart_pattern_reuse.blueprint_cowork` (38 charters)** — NÃO é âncora POR DEFINIÇÃO.
// A [ADR 0149](memory/decisions/0149-mwart-screen-pattern-reuse-cowork.md), aceita por [W]
// em 2026-05-15, a cria pra dizer *"esta tela derivada REUSA o pattern visual do Index de
// outra tela"* — o blueprint é de OUTRA tela por construção. Medido 2026-09-09 nos 38:
//   · 38/38 estão sob `mwart_pattern_reuse`, com `derived_screens` declarado
//   · 31/38 declaram `divergence_from_blueprint` REAL — o próprio charter diz que o
//     blueprint NÃO desenha esta tela (ex.: *"datatable multi-row edit — pattern distinto
//     de Index Cockpit"*, *"reuso parcial header+stats apenas"*); só 6 dizem "none/nenhuma"
//   · 37/38 JÁ têm `related_prototype`, e **0** estão sem nenhuma das 3 chaves de âncora —
//     promover não fecharia gap nenhum, só sobrescreveria declaração existente
//   · 5/38 nem resolvem (3 path inexistente, 2 valem literalmente `n/a (form CRUD simples)`)
// E ela NÃO é órfã: `charter-blueprint-pointers.mjs` e `reconcile-triplet.mjs` a leem como
// ponteiro-de-blueprint, e `detectar-telas.test.mjs` tem um teste que asserta que ela NÃO é
// alvo de âncora. A razão mecânica de o `frontmatter()` não a enxergar é outra e é banal:
// o parser compartilhado casa `^([a-z_]+):` (nível raiz), e ela vive indentada.
//
// **`canon_reference` (31 `*-visual-comparison.md`)** — é referência de PARIDADE: registra
// contra o que a tela foi comparada, não de onde ela nasce. Medido nos 31:
//   · 4 apontam Blade legacy (`resources/views/contact/*.blade.php`) e 1 aponta `.tsx` do
//     próprio repo — porte REVERSO, que como âncora é a lápide de 2026-06-05 (derivar do
//     código); 1 aponta tool MCP, que não é design; 1 se declara *"ref expirada"*
//   · só 10 dos 31 nomeiam arquivo que EXISTE (7 apontam um `produto-cockpit-page.jsx` que
//     não resolve)
//   · e o número que decide: nos **19** casos em que o charter tem `related_prototype` E o
//     inventário vinculado tem `canon_reference`, eles apontam o MESMO arquivo **0 vezes**
//     (12 o rp é declaração `n/a`, 6 nomeiam arquivos DIFERENTES, 1 não nomeia arquivo).
//     E onde dá pra julgar, o `related_prototype` é o certo: `Forja/Cockpit` tem
//     rp=`forja-page.jsx` contra cr=`os-page.jsx` (que desenha Ordem de Serviço).
//
// **Decisão (2026-09-09): reportar, nunca promover.** Promover em leva já é lápide —
// §5 2026-08-28 diz, sobre exatamente esta forma, que é *"decisão par-a-par, nunca carimbo"*,
// e que promover porte reverso a "design APROVADO" ancora a tela nela mesma. O que faltava
// não era leitura: era o rótulo. Então a máquina passa a DIZER que a chave existe e por que
// não é âncora. `--list` e `design-coverage` seguem intocados de propósito (a catraca não
// se mexe: 226/221/66 antes e depois).

/** As linhas do bloco de frontmatter, ou null. Split puro — sem regex, de propósito:
 *  a versão com `new RegExp` colapsou os escapes na escrita e virou um casamento errado
 *  que ainda assim passa no `node --check` (LC-26 · §5 2026-08-19). */
export function linhasDoFrontmatter(src) {
  const LF = String.fromCharCode(10);
  const linhas = String(src || '').split(LF).map((l) => l.replace(String.fromCharCode(13), ''));
  if ((linhas[0] || '').trim() !== '---') return null;
  const fim = linhas.findIndex((l, k) => k > 0 && l.trim() === '---');
  return fim < 0 ? null : linhas.slice(1, fim);
}

/** `divergence_from_blueprint` diz "nao diverge"? Substring, sem regex (ver LC-26 abaixo). */
export function ehSemDivergencia(valor) {
  const v = String(valor || '').trim().toLowerCase();
  return v.startsWith('none') || v.startsWith('nenhuma');
}

/** O valor aponta código do PRÓPRIO repo (porte reverso), não desenho? Substring, sem regex. */
export function ehCodigoDoRepo(valor) {
  const v = String(valor || '');
  return v.includes('.blade.php') || (v.includes('resources/js/Pages') && v.includes('.tsx'));
}

/** Valor de uma chave de frontmatter em QUALQUER nível de indentação, ou null.
 *
 *  ⚠️ Duplicação declarada (§5 2026-08-02 — "ou unifica, ou declara por que as duas existem"):
 *  `charter-blueprint-pointers::pointersOf` e `reconcile-triplet::fmScalar` fazem o mesmo, e
 *  nenhum dos dois é `export` — e ambos escopam só `resources/js/Pages`, enquanto esta máquina
 *  tem DUAS raízes (`raizesDePages`). Unificar os três é PR próprio: os dois consumidores são
 *  gates com selftest, e mexer neles aqui misturaria intents. O `frontmatter()` compartilhado
 *  NÃO serve: ele casa `^([a-z_]+):` (nível raiz) e a chave que interessa vive indentada.
 */
export function chaveAninhada(src, chave) {
  if (!src || !chave) return null;
  const linhas = linhasDoFrontmatter(src);
  if (!linhas) return null;
  const prefixo = chave + ':';
  for (const linha of linhas) {
    const t = linha.trim();
    if (t.startsWith(prefixo)) return desasparValor(t.slice(prefixo.length));
  }
  return null;
}

/**
 * As declarações de fonte que NÃO são âncora, com o motivo de cada uma. Pura e testável.
 *
 * Reporter, nunca gate: não muda `ok`, não muda exit code, não entra no `--list`.
 * @param {{charterSrc?: string, canonRefSrc?: string|null}} entrada
 * @returns {{chave:string, valor:string, motivo:string, nota?:string}[]}
 */
export function declaracoesNaoAncora({ charterSrc = '', canonRefSrc = null } = {}) {
  const out = [];
  const bp = chaveAninhada(charterSrc, 'blueprint_cowork');
  if (bp) {
    const div = chaveAninhada(charterSrc, 'divergence_from_blueprint');
    // Substring, nao regex: a versao com `\b` gravou um BACKSPACE literal (0x08) no
    // arquivo -- regex valida que nunca casa, invisivel no grep e verde no `node --check`
    // (LC-26 na forma mais traicoeira: a inspecao visual mente, so o `od -c` mostra).
    const semDivergencia = ehSemDivergencia(div);
    out.push({
      chave: 'mwart_pattern_reuse.blueprint_cowork',
      valor: bp,
      motivo: 'screen-pattern reuse (ADR 0149) — é o blueprint do Index de OUTRA tela, reusado aqui.',
      nota: !div
        ? 'O charter não declara `divergence_from_blueprint` — e o blueprint segue sendo o do Index de outra tela.'
        : semDivergencia
          ? `O charter declara divergence_from_blueprint: ${div} — mesmo assim, reuso de pattern não é fonte.`
          : `O próprio charter declara que DIVERGE do blueprint: ${div}`,
    });
  }
  const cr = canonRefSrc ? chaveAninhada(canonRefSrc, 'canon_reference') : null;
  if (cr) {
    out.push({
      chave: 'canon_reference (visual-comparison)',
      valor: cr,
      motivo: 'referência de PARIDADE — diz contra o que a tela foi comparada, não de onde ela nasce.',
      nota: ehCodigoDoRepo(cr)
        ? 'Este valor aponta código do próprio repo (Blade/.tsx) — porte REVERSO. Como âncora, ancoraria a tela nela mesma (§5 2026-06-05).'
        : undefined,
    });
  }
  return out;
}

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

// ── EIXO COBERTURA — o que o `✓ frescor` NÃO diz ────────────────────────────────
//
// O bloco FRESCOR acima nasceu do incidente de 2026-08-26 e fechou o eixo CONTEÚDO:
// "este arquivo do espelho bate com o vivo?". Ficou aberto o eixo vizinho, e ele derrubou
// outra sessão em 2026-09-09 (Patrimonio/Index): o `✓ verificado` foi lido como "a fonte
// está em dia, posso trabalhar pelo espelho" — e o espelho não COBRE o vivo.
//
// As duas perguntas são diferentes, e o próprio sistema já sabia da segunda: no mesmo dia,
// `cowork-mirror-freshness --sla` devolvia ⬜ INCONCLUSIVO com "98 arquivo(s) existem no
// VIVO e não estão no espelho" — enquanto esta ferramenta imprimia um `✓` limpo. Régua cujo
// universo vem do lado que a gente controla mede a NOSSA diligência, não a realidade.
//
// Não é régua nova: o dono do eixo é `liveOnlyVerdict` (cowork-mirror-freshness), e aqui só
// se LÊ o veredito dele. Reporter puro — não bloqueia, não muda exit code. E imprime a rota
// da fonte viva, porque foi ela que resolveu o caso: ir direto no projeto por ID.
//
/**
 * Linhas de aviso do eixo COBERTURA, ou `[]` quando não há o que dizer.
 * Puro (recebe as entradas e o instante) pra ser testável pelo `--selftest`.
 *
 * @param {Array} entradasLedger todas as entradas do ledger (append-only)
 * @param {string} relEspelho    caminho do arquivo, relativo ao espelho
 * @returns {string[]}
 */
export function avisoDeCobertura(entradasLedger, relEspelho, nowIso = new Date().toISOString()) {
  const v = liveOnlyVerdict(entradasLedger, nowIso);
  const rota = `                 Fonte viva: DesignSync.get_file(projectId=${COWORK_PROJECT_ID}, path=${relEspelho})`;

  // Nunca medido / vencido: ausência de medição não é cobertura boa (§5 2026-07-29).
  if (v.veredito === 'NEVER-RAN') {
    return [
      '              ⚠️ cobertura: NUNCA MEDIDA — ninguém mediu o que existe no vivo e não desceu.',
      '                 O ✓ acima fala deste arquivo; ele não prova que a fonte da tela está toda aqui.',
      rota,
    ];
  }
  if (v.veredito === 'OVERDUE') {
    return [
      `              ⚠️ cobertura: MEDIÇÃO VENCIDA — última em ${v.last.date} (há ${v.ageDays}d).`,
      '                 O que entrou no vivo depois disso é invisível para o espelho.',
      rota,
    ];
  }

  const faltando = v.last?.liveOnly ?? 0;
  if (faltando > 0) {
    const denom = v.last?.denom ? ` de ${v.last.denom}` : '';
    return [
      `              ⚠️ cobertura: o espelho NÃO cobre o vivo — ${faltando} arquivo(s)${denom} existem lá e nunca desceram (medido ${v.last.date}).`,
      '                 O ✓ acima fala deste arquivo; ele não prova que a fonte da tela está toda aqui.',
      rota,
    ];
  }
  return [];
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

// Irmão do `norm` que NÃO strippa o `/index` final — existe só pra DESEMPATE.
// O strip é o que faz `Nfse/Index` e `Fiscal/Nfse` colapsarem no mesmo `…/nfse`: os dois
// casam com a mesma força e quem ganha é o primeiro da ordem alfabética. Preservando o
// segmento, só o charter cujo path termina em `nfse/index` casa — e aí não há empate.
function normFull(s) { return (s || '').toLowerCase().replace(/\\/g, '/').replace(/\.(tsx|charter\.md)$/i, ''); }

/** `full` termina no path `qq` (igualdade ou sufixo de segmento inteiro)? Puro, pra ser testado. */
export function casaPathInteiro(full, qq) {
  if (!full || !qq) return false;
  const a = String(full).toLowerCase(), b = String(qq).toLowerCase().replace(/^\/+/, '');
  return a === b || a.endsWith('/' + b);
}

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

  // FORÇA do match — antes, `hit` era SOBRESCRITO a cada candidato e o `break` do "match
  // forte" fazia o primeiro da ordem alfabética ganhar quando dois charters empatavam.
  // Medido 2026-09-09 sobre os 226 atalhos `Mod/Tela`: **14** resolviam num charter DIFERENTE
  // do esperado — e com `âncora ✓` + selo de frescor, ou seja, resposta confiante e errada
  // (o caso citado no inventário: `Nfse/Index` → `Fiscal/Nfse`, porque o `norm` strippa o
  // `/index` e os dois viram `…/nfse`). Agora todo candidato é coletado com uma força:
  //   4 rota exata · 3 path INTEIRO (o `normFull`, que desempata o caso acima)
  //   2 sufixo após o strip (o "match forte" de antes) · 1 substring (fraco)
  // e a resolução é: força máxima com UM candidato resolve; com DOIS ou mais resolve o
  // primeiro **e DECLARA a ambiguidade** (`r.ambiguidade`), que o printer estampa.
  // Deliberadamente NÃO vira `ok:false`: degradar empate pra "sem charter" trocaria
  // informação por silêncio, que é a doença que este bloco existe pra curar.
  // CUSTO declarado: sem o `break`, a varredura sempre percorre os 226 charters. Medido
  // 2026-09-09 no mesmo repo, 20 chamadas: 525ms → 816ms (~26ms → ~41ms por chamada).
  // Aceito de propósito — é CLI de diagnóstico, e o `break` era justamente o que fazia a
  // ordem alfabética decidir empate. Se um dia pesar, o caminho é cachear a leitura dos
  // charters (o `--list` já varre os mesmos), nunca voltar a parar no primeiro match.
  const qFull = normFull(query);
  const candidatos = [];
  for (const cf of charters) {
    const fm = frontmatter(await read(cf));
    const page = norm(fm.page);                 // rota: /financeiro/unificado
    const comp = norm(fm.component);            // resources/js/Pages/Financeiro/Unificado/Index.tsx
    const relc = norm(relative(repoRoot, cf));  // .../Unificado/Index.charter.md
    let forca = 0;
    if (page && page === q) forca = 4;
    else if (casaPathInteiro(normFull(fm.component), qFull) || casaPathInteiro(normFull(relative(repoRoot, cf)), qFull)) forca = 3;
    else if ((comp && comp.endsWith(q)) || (comp && q.endsWith(comp))) forca = 2;
    else if (relc.includes(q) || (q && comp && comp.includes(q))) forca = 1;
    if (forca) candidatos.push({ forca, charter: relative(repoRoot, cf).replace(/\\/g, '/'), fm });
  }
  const maxForca = candidatos.reduce((m, c) => (c.forca > m ? c.forca : m), 0);
  const topo = candidatos.filter((c) => c.forca === maxForca);
  const hit = topo[0] || null;
  const ambiguidade = topo.length > 1 ? topo.map((c) => c.charter) : null;
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
  // 2) -page.jsx do bundle (se staging dado) — SÓ pelo campo estruturado do charter.
  // Havia aqui um fallback `startsWith(dir)` que casava o mockup por NOME DE PASTA. Removido
  // em 2026-09-09 por decisão [W], medido: das 42 telas que ele resolvia, 25 já tinham
  // `related_prototype` (o consumidor pega o [0], ele era supérfluo) e 16 declaravam `n/a` —
  // nessas ele SOBRESCREVIA a decisão do charter e deixava inalcançável o aviso "sem âncora
  // POR DECISÃO" do design-diff-lote. Era o guard sintático que a regra dura do topo proíbe
  // (§5 proibicoes tem 7 lápides da família). Tela sem campo declarado NÃO tem âncora de
  // bundle — é a verdade, e `--list` já sabe dizer isso. Ver o BITE no --selftest.
  if (stagingDir) {
    const stFiles = await walk(stagingDir);
    const declarado = mockupJsx(fm.bundle_source) || mockupJsx(fm.visual_source);
    let cand = declarado ? stFiles.find((f) => basename(f).toLowerCase() === declarado.toLowerCase()) : null;
    let via = cand ? 'bundle_source' : null;
    if (cand) ancoras.push({ tipo: `-page.jsx (bundle · ${via})`, valor: relative(stagingDir, cand).replace(/\\/g, '/'), raiz: resolve(stagingDir) });
  }
  // 3) o MESMO -page.jsx, mas no LUGAR FIXO do repo — SEM precisar de `--staging`.
  //
  // O bloco de cima nasceu preso ao staging, e o arquivo saiu de lá: `bundle_source` aponta
  // pra um `-page.jsx` que hoje está VERSIONADO em `prototipo-ui/cowork/`. Sem a flag, esta
  // função dizia "charter sem related_prototype nem -page.jsx" pra tela cujo desenho está
  // no git — enquanto `fonteDoCharter`/`listAll` liam o mesmo campo e respondiam o contrário.
  // Duas portas do MESMO arquivo discordando sobre a mesma tela é defeito, não escolha.
  //
  // `--staging` VENCE, e o guard é sobre `ancoras` (não um `else`): quem tem staging já
  // empurrou a perna 2 e esta não duplica; quem não tem cai aqui. Amarrar a precedência à
  // SINTAXE do `if` faria a ordem depender de onde o bloco mora no arquivo.
  //
  // `raiz` = o REPO, porque é onde o arquivo está — passar o staging aqui seria reintroduzir
  // o defeito de 2026-08-25 que o docblock de `defeitosDaAncora` cataloga.
  //
  // Só empurra se ABRIR: nome declarado que não está no lugar fixo NÃO vira âncora, e a
  // ausência segue visível no ⚠️. Âncora que aponta pro vazio é pior que âncora ausente.
  if (!ancoras.some((a) => a.tipo.startsWith('-page.jsx'))) {
    const doBundle = mockupJsx(fm.bundle_source);
    const declaradoFixo = doBundle || mockupJsx(fm.visual_source);
    // O campo REAL, não o rótulo fixo: 5 das telas que esta perna resolve declaram por
    // `visual_source`, e chamá-las de `bundle_source` seria o printer mentindo a fonte.
    // (A perna 2 rotula sempre `bundle_source` — divergência conhecida, dela, não tocada aqui.)
    const campo = doBundle ? 'bundle_source' : 'visual_source';
    if (declaradoFixo && ehArquivo(resolve(raizRepo, LUGAR_FIXO, declaradoFixo))) {
      ancoras.push({ tipo: `-page.jsx (bundle · ${campo})`, valor: `${LUGAR_FIXO}/${declaradoFixo}`, raiz: raizRepo });
    }
  }
  // DEDUP por ARQUIVO RESOLVIDO — nunca por tipo de perna. As pernas são de tipos
  // DIFERENTES por construção (`related_prototype (charter)` × `-page.jsx (bundle · …)`),
  // então comparar `tipo` não pegaria nada. O caso real: `Sells/Index` declara os DOIS
  // campos apontando pro mesmo `vendas-page.jsx` e passaria a imprimir a âncora duas vezes.
  // `n/a` não resolve em arquivo (`caminhoDaAncora` devolve null), logo charter com
  // `n/a` + `bundle_source` segue imprimindo as DUAS coisas — a declaração e a âncora.
  // Varre PRA FRENTE e mantém a PRIMEIRA menção: a ordem do array É a precedência
  // (`related_prototype` antes do bundle). Varrer de trás pra frente mantinha a ÚLTIMA e
  // rebaixava o protótipo aprovado do `Sells/Index` a âncora de bundle — pego pelo controle
  // positivo, não pela revisão, que é justamente o que ele existe pra fazer.
  const vistos = new Set();
  const unicas = [];
  for (const a of ancoras) {
    const rel = caminhoDaAncora(a.valor, a.raiz);
    const abs = rel ? resolve(a.raiz, rel) : null; // não nomeia arquivo: nunca colide
    if (abs && vistos.has(abs)) continue;          // 2ª menção do MESMO arquivo: some
    if (abs) vistos.add(abs);
    unicas.push(a);
  }
  ancoras.splice(0, ancoras.length, ...unicas);
  // As duas chaves que declaram fonte e NÃO são âncora (bloco do topo). Duas leituras a
  // mais, só pro charter ESCOLHIDO — nunca pros 226 da varredura. Falha de leitura degrada
  // pra lista vazia: reporter que some é aceitável, reporter que inventa não é.
  const charterSrc = await read(join(repoRoot, hit.charter));
  const vcRel = desasparValor(fm.related_visual_comparison || '');
  const canonRefSrc = vcRel ? await read(join(repoRoot, vcRel)) : null;
  const naoAncora = declaracoesNaoAncora({ charterSrc: charterSrc || '', canonRefSrc });

  const liveTsx = repoTsx(fm.component);
  return {
    ok: true, query, charter: hit.charter,
    telaViva: liveTsx, ancoras, repoRoot: raizRepo,
    // ADITIVO (mesmo critério de `via`/`isNa`): consumidor nenhum quebra por um campo novo,
    // e quem quiser pode decidir por conta. `null` quando a resolução foi única.
    ambiguidade,
    // idem, ADITIVO: qual DEGRAU da escada resolveu (4 rota · 3 path inteiro · 2 sufixo ·
    // 1 substring). O printer usa pra declarar match fraco; consumidor que ignorar não muda.
    forca: maxForca,
    // idem: reporter puro. `[]` quando o charter não declara nenhuma das duas.
    naoAncora,
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

// TETO da lista de candidatos numa query ambígua. O empate real do `Ponto/Index` é de 21;
// despejar 21 linhas afoga a única linha que importa (como sair do empate). 10 basta pra
// reconhecer o módulo, e o resto vira contagem.
const TETO_CANDIDATOS = 10;

async function printResolve(r) {
  if (!r.ok) { console.error(`✗ ${r.query}: ${r.motivo}`); return 1; }
  if (r.avisoMangle) console.log(`⚠️ ${r.avisoMangle}`);
  // AMBIGUIDADE do atalho — RECUSA, não escolha. Duas etapas, e a segunda é decisão [W].
  //
  // #7100 (2026-09-09) montou a escada de força (rota > path inteiro > sufixo > substring) e
  // levou o erro SILENCIOSO de 14 pra 0 nos 226 atalhos. Sobraram os empates sem resposta
  // certa (`Dashboard/Index` são 4 telas; `Ponto/Index`, 21) — e ali ele escolhia o primeiro
  // da varredura, AVISAVA, e saía 0. O aviso era honesto, mas saía colado num `âncora ✓` com
  // selo de frescor, e exit 0 não barra script nem agente com pressa.
  //
  // Decisão [W] 2026-09-09: recusar, e recusar SÓ AQUI. O `resolveAncora` segue devolvendo
  // `ok:true` + `r.ambiguidade` DE PROPÓSITO. Degradar pra `ok:false` faria o
  // `design-diff-lote.mjs` (:419) imprimir "sem charter resolvível pelo ancora.mjs" para uma
  // query que resolve 21 charters — trocaria um `✓` errado por uma negativa errada, o mesmo
  // LC-08 virado do avesso — e faria o `render-proto-baseline.mjs` (:295) LANÇAR, porque ele
  // dá `throw` em `!r.ok` e o `--check` re-resolve a âncora de cada baseline commitado.
  //
  // Medido em 2026-09-09 ANTES de decidir, contra a árvore inteira (226 charters):
  //   · uso canônico (query = `component:` do charter): 226/226 resolvem únicos, 0 ambíguos
  //     — ou seja, esta recusa não alcança o caminho canônico;
  //   · atalhos `Mod/Tela`: 217 distintos, 7 ambíguos (`Dashboard/Index` é o pior, 4);
  //   · baselines commitados que o `--check` re-resolve: 0 de 9 usam query ambígua.
  //
  // Nada de "adivinhar melhor": sem pontuar, sem ordenar por similaridade, sem eleger o mais
  // provável. A saída é a lista + erro; quem sabe qual das N telas quer é o humano.
  if (r.ambiguidade) {
    const lista = [...r.ambiguidade].sort();
    console.error(`✗ ${r.query}: query AMBÍGUA — ${lista.length} charters casam com a mesma força. Não vou sortear um.`);
    for (const c of lista.slice(0, TETO_CANDIDATOS)) console.error(`    ${c}`);
    if (lista.length > TETO_CANDIDATOS) console.error(`    … e mais ${lista.length - TETO_CANDIDATOS}.`);
    console.error('  Desambigue com o caminho .tsx completo (o `component:` do charter) ou com a rota (`page:`).');
    return 2;
  }
  console.log(`ÂNCORA da tela: ${r.query}`);
  console.log(`  charter:    ${r.charter}`);
  // MATCH FRACO declarado — a metade que faltava do desfecho de ambiguidade. Lá, 2+ empatados
  // viram recusa; aqui, UM candidato resolve, mas pelo degrau mais frouxo da escada: casou como
  // SUBSTRING (`relc.includes(q)`), não por rota, path inteiro nem sufixo. Resolver único é o
  // que torna legítimo devolver — e continuar em exit 0; calar QUE foi frouxo é o que faz o
  // leitor tratar palpite como medição, a mesma família do `✓` sorteado (LC-08 no eixo da
  // RESOLUÇÃO). O playbook da thread 02 pedia exatamente isto: "resolve como hoje, exit 0, mas
  // a saída DIZ que foi match fraco e por qual critério".
  //
  // Só a força 1 fala, e o silêncio é MEDIDO, não torcida — sobre a árvore inteira em
  // 2026-09-09: query = `component:` do charter → 226/226 na força 3; query = `page:` →
  // 226/226 na força 4; atalho `Mod/Tela` → 217/217 na força 3. Nenhuma forma legítima de
  // consulta cai na 1, então este aviso não aparece no uso normal: ele existe pro caso em que
  // alguém digitou um pedaço solto e mereceu o alerta.
  if (r.forca === 1) {
    console.log(`  ⚠️  match FRACO — "${r.query}" casou como SUBSTRING do caminho: não bate rota,`);
    console.log('              nem caminho inteiro, nem sufixo. Resolveu ÚNICO, por isso vale — mas');
    console.log('              confira se é esta tela; o preciso é o `component:` ou a rota (`page:`).');
  }
  console.log(`  tela viva:  ${r.telaViva || '—'}`);
  if (!r.ancoras.length) console.log('  âncora:     ⚠️ charter sem related_prototype nem -page.jsx — registre o protótipo');
  // `✓` só para âncora que RESOLVE em arquivo. `n/a` é uma DECLARAÇÃO ("segue o DS"),
  // legítima, mas não é âncora — imprimir "âncora ✓: n/a" é sinal de saúde falso e foi
  // o que fez uma sessão (2026-08-11) ler "tem âncora" onde não havia nenhuma.
  // Ver LC-10 (artefato afirmando o próprio estado) — aqui no eixo do OUTPUT.
  //
  // A PARTIR DA 2ª que resolve em arquivo, o rótulo muda (2026-09-09). Medido: 5 telas
  // declaram os dois campos apontando arquivos DIFERENTES, e em todas as 5 o
  // `related_prototype` é o desenho ESPECÍFICO e o `bundle_source` é o HUB do módulo que a
  // contém — `fiscal-subpages.jsx` ("sub-páginas … Vivo: Pages/Fiscal/{Eventos,Dfe,Config,
  // Sped}.tsx") × `fiscal-page.jsx`; `essenciais-extras.jsx` (base de conhecimento) ×
  // `essenciais-page.jsx`. As duas são declarações VERDADEIRAS, então suprimir a 2ª seria
  // apagar fato do charter — e pioraria o cross-check do `gerar-map`, que fica mais
  // permissivo justamente por enxergar as duas. O defeito era só de APRESENTAÇÃO: duas
  // linhas `âncora ✓` com o mesmo peso, e o leitor sem saber qual vale.
  //
  // Qual vale já estava decidido e os consumidores JÁ respeitam — `design-diff-lote` dá
  // `break` na primeira que resolve (L412) e `render-proto-baseline` só olha
  // `related_prototype` (L297). O printer é que não dizia. Agora diz.
  //
  // A 2ª NÃO é medida (nem selo, nem frescor, nem P-1): medir custa I/O e afirmaria sobre um
  // arquivo que ninguém vai abrir por esta tela. Sem medição, sem selo — é a regra do próprio
  // bloco acima (§5 2026-07-29: instrumento não afirma verde sem ter medido).
  let jaTemEfetiva = false;
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
      if (jaTemEfetiva) {
        console.log(`  também declarado: [${a.tipo}] ${a.valor}`);
        if (caminho !== desasparValor(a.valor)) console.log(`              → resolvido em: ${caminho}`);
        console.log('              (o bundle de ORIGEM do módulo, não o desenho desta tela. A âncora');
        console.log('               efetiva é a de cima — é a que design-diff-lote e proto-baseline abrem.');
        console.log('               Não medido aqui de propósito: sem leitura, sem selo.)');
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
      jaTemEfetiva = true; // as próximas que resolverem viram "também declarado"
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
          console.log(`              ✓ frescor: verificado contra o Cowork vivo em ${f.data} — fala DESTE arquivo`);
          for (const linha of avisoDeCobertura(entradasFrescor, relEspelho)) console.log(linha);
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
  // As chaves que declaram fonte e NÃO são âncora — imprimir é o conserto. Sem isto, quem
  // abre o charter, vê `blueprint_cowork`/`canon_reference` apontando um `-page.jsx` e roda
  // esta máquina sem ver menção nenhuma conclui que a cadeia ESQUECEU de ler — foi o que
  // um inventário concluiu em 2026-09-09. Rotular é mais barato que promover, e é correto.
  if (r.naoAncora && r.naoAncora.length) {
    console.log('  ℹ️  declarações de fonte que NÃO são âncora (lidas de propósito, reportadas, nunca promovidas):');
    for (const d of r.naoAncora) {
      console.log(`     · ${d.chave}: ${d.valor}`);
      console.log(`       ${d.motivo}`);
      if (d.nota) console.log(`       ${d.nota}`);
    }
    console.log('     Promover qualquer uma a âncora é decisão [W], par-a-par — nunca em leva (§5 2026-08-28).');
  }
  console.log(`  ⛔ ${r.aviso}`);
  return 0;
}

/**
 * Precedência de campos do charter → a fonte de design que o `--list` reporta.
 *
 * PURA e EXPORTADA de propósito: o selftest exercita ESTA função, que é a MESMA que o
 * `listAll` chama — asserção sobre cópia paralela fica verde enquanto o pipeline regride
 * (§5 2026-08-14). Documentação da regra: ver o bloco de comentário dentro do `listAll`.
 */
export function fonteDoCharter(fm = {}) {
  const doBundle = mockupJsx(fm.bundle_source) || mockupJsx(fm.visual_source);
  const declaracaoNa = ehDeclaracaoNa(fm.related_prototype) ? fm.related_prototype : null;
  const bespoke = fm.related_prototype && !declaracaoNa ? fm.related_prototype : null;
  // 2026-09-09 — o 4º e ÚLTIMO fallback (`mockupJsx(fm.component)`) MORREU. Ele fazia a
  // âncora cair na PRÓPRIA TELA quando o charter não declarava fonte: tautológico, e é o que
  // o charter de `Repair/Settings` já recusava em prosa ("ancorar aqui seria ancorar a tela
  // nela mesma"). Pior que inútil — dava `hasSource: true` ao `design-coverage` para tela sem
  // design nenhum, escondendo o gap real atrás de um ✅.
  //
  // A remoção estava atrás da decisão D-COMPONENT do playbook da âncora, que pedia UM número:
  // "não medi quantas linhas hoje saem com via='component'; se for >0, alguma tela perde
  // fonte no design-coverage". Medido em 2026-09-09 sobre os 226 charters do `--list`:
  // `related_prototype` 193 · `bundle_source/visual_source` 29 · nenhuma fonte 4 ·
  // **`component` 0**. O ramo não resolvia NADA — era caminho morto esperando pra mentir.
  // Prova de identidade no PR: `--list --json` byte-idêntico antes e depois (88.350 B, 222
  // com fonte). Zero tela perdeu `hasSource`.
  //
  // `mockupJsx` NÃO morre: o `doBundle` acima usa, e o `reconcile-triplet.mjs:50` importa.
  const source = bespoke || doBundle || declaracaoNa || null;
  const via = bespoke ? 'related_prototype'
    : doBundle ? 'bundle_source/visual_source'
    : declaracaoNa ? 'related_prototype'
    : null;
  // o `n/a` que o bundle eclipsou — só existe quando as duas pernas estão no charter
  const naEclipsado = !bespoke && doBundle && declaracaoNa ? declaracaoNa : null;
  return { source, via, declaracaoNa, naEclipsado };
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
    //
    // 2026-09-09 — a PERNA que o fix acima NÃO cobriu (é a mesma lesão, outra porta; por isso
    // esta nota estende a de cima em vez de abrir bloco paralelo). Aquele fix tratou
    // `related_prototype` AUSENTE. O caso `n/a` PRESENTE ficou — e é pior, porque
    // `"n/a (herda PT-01 Lista; segue o Padrão de Tela)"` é uma string TRUTHY: o `||`
    // curto-circuitava nela e o `doBundle` da linha anterior nunca era alcançado.
    // Medido em 14 charters (ComVis · Essentials ×3 · Manufacturing · Produto · Repair ×3 ·
    // Estoque ×4 · Vestuario): o `--list` reportava `isNa: true` / `via: related_prototype`
    // enquanto a porta per-tela resolvia a mesma tela com âncora ✓ do bundle
    // (`ancora.mjs Produto/Index --staging prototipo-ui/cowork` → `produtos-page.jsx`).
    // É a MESMA contradição porta-viva × lista que o fix de 08-28 veio matar, e ela se
    // propagava: `design-coverage` (único consumidor do `--list --json`, medido) herdava a
    // cegueira no 3º balde.
    //
    // A regra: `n/a` NÃO tem precedência sobre campo estruturado que aponta ARQUIVO REAL.
    // Os dois COEXISTEM por desenho (§5 2026-08-28 item c) porque respondem a perguntas
    // diferentes — "qual Padrão de Tela eu herdo?" × "qual é a minha âncora de design". Com
    // ambos no charter, a ÂNCORA é o bundle; o `n/a` continua sendo o PT herdado e é
    // preservado em `declaracaoNa` (aditivo — sem ele o `--list` PERDERIA o PT dos 14, que é
    // o mesmo tipo de sub-reporte silencioso que esta linha existe pra impedir).
    // Onde há SÓ `n/a`, nada muda: 106 charters seguem `isNa: true`, como devem.
    // `ehDeclaracaoNa` é o dono desta distinção neste arquivo — reusado, não reimplementado.
    // A regra mora em `fonteDoCharter` (pura, exportada) pro selftest morder o caminho REAL.
    const { source, via, declaracaoNa, naEclipsado } = fonteDoCharter(fm);
    //
    // 2026-09-09 (terceiro eixo da MESMA lesao das duas notas acima) — o `hasSource`
    // carimbava "tem fonte" sem NUNCA ter aberto arquivo. `!!source` e verdadeiro pra
    // QUALQUER string: um `related_prototype` podre (path velho, arquivo renomeado, prosa
    // sem arquivo) contava como coberto no `design-coverage`, enquanto a porta de 1 tela
    // — que MEDE — diria o contrario sobre o MESMO charter. 08-28 tratou a fonte AUSENTE,
    // a nota acima a fonte `n/a` ECLIPSADA, esta a fonte que NAO ABRE. E presence-gate
    // classico (LC-11): media a PRESENCA da string, nao o comportamento "o valor resolve".
    //
    // ADITIVO. `hasSource` NAO muda de semantica e nao sai do JSON: ele continua sendo
    // "o charter DECLAROU a fonte" (inclusive `n/a` explicito) e o `design-coverage`
    // conta com isso. Os campos abaixo respondem OUTRA pergunta.
    //
    // TRES estados, e `null` != `false` — colapsar seria falso-positivo em massa:
    //   caminho: null  = o valor nao nomeia arquivo (`n/a`, PT-0X, diretorio) ou nao ha fonte
    //   existe : null  = idem — NAO MEDI, porque nao havia alvo a abrir
    //   existe : false = o valor NOMEIA arquivo e ele nao esta la   <- o unico defeito
    // Os 105 `n/a` do corpus sao declaracao legitima (§5 2026-08-28 item c); trata-los como
    // `false` inventaria 105 defeitos — e "nao medi" nunca colapsa num estado do medido
    // (§5 2026-07-29).
    //
    // ZERO extrator novo: `caminhoDaAncora` e o dono, neste mesmo arquivo, e o docblock dele
    // ja declara por que nenhum dos outros 3 do repo serve (seria o 5o). `repoRoot` vai
    // EXPLICITO — a raiz de leitura do `--list` nao muda (segue `REPO_DEFAULT` pelo main);
    // quem precisa da propria raiz e a fixture hermetica do selftest.
    //
    // `ehDeclaracaoNa` PRIMEIRO, e nao e detalhe: MEDIDO nesta sessao, 7 dos 105 `n/a`
    // citam um arquivo NA PROSA justamente pra explicar por que a tela NAO se ancora nele
    // (`Repair/Settings`: «ancorar aqui seria ancorar a tela nela mesma»; `Jana/Pro`: «e
    // RETRATO do Pro.tsx, ancorar seria tautologico»; +2 Whatsapp/Atendimento e +3 Oficina).
    // Sem a guarda, `caminhoDaAncora` acha o token e o `--list` passa a AFIRMAR uma ancora
    // que o charter NEGA em prosa — defeito pior que o consertado aqui, porque parece medido.
    // Reusa o dono da distincao neste mesmo arquivo (o mesmo que decide o `isNa` 3 linhas
    // abaixo), entao `caminho`/`existe` sao consistentes com `isNa` POR CONSTRUCAO.
    const caminho = source && !ehDeclaracaoNa(source) ? caminhoDaAncora(source, repoRoot) : null;
    const existe = caminho === null ? null : ehArquivo(resolve(repoRoot, caminho));
    // hasSource = o charter DECLAROU a fonte de design (protótipo bespoke OU "n/a — segue DS"
    // explícito, que também vem em related_prototype). null = silencioso (gap real).
    // `charter` e `isNa` sao ADITIVOS (2026-08-26): o unico consumidor de `--list --json` e o
    // design-coverage (medido: 1 de 1), e ele precisava saber DE QUAL charter veio a linha e se a
    // fonte e declaracao `n/a` — sem isso ele contava `n/a` como ✅ pra sempre e escondia a tela
    // cuja fonte JA DESCEU pro espelho depois da decisao. `isNa` reusa `ehDeclaracaoNa`, o dono
    // dessa distincao neste mesmo arquivo — nao reimplementar (§5 2026-08-26).
    rows.push({ page: fm.page || relative(repoRoot, cf), source: source || '⚠️ sem protótipo declarado', hasSource: !!source, charter: relative(repoRoot, cf).split(String.fromCharCode(92)).join('/'), isNa: ehDeclaracaoNa(source), via, declaracaoNa, caminho, existe });
    // A saída de TEXTO precisa carregar o que o `via` do JSON já carrega. Medido nesta
    // sessão: 2 dos 14 (`ComunicacaoVisual/Index`, `Vestuario/Etiquetas/Index`) declaram no
    // comentário do próprio `bundle_source` que ele é «porte REVERSO do vivo … fonte de
    // bundle, NÃO design aprovado (§5 2026-08-28)». Sem o rótulo, o leitor humano lê
    // `produtos-page.jsx` e supõe design aprovado — a distinção que o `via` foi criado pra
    // fazer (nota de 08-28 acima) existia só no `--json`. O risco não nasce aqui, mas esta
    // mudança o AMPLIA de 17 para 31 linhas, então o rótulo entra junto.
    if (!asJson) {
      const rotulo = via === 'bundle_source/visual_source' ? '  [bundle]' : '';
      const corte = naEclipsado && naEclipsado.length > 64 ? naEclipsado.slice(0, 63) + '…' : naEclipsado;
      // O defeito novo entra como SUFIXO: so `existe === false` (nem `null`, que e
      // ausencia de alvo), e DEPOIS de tudo que ja se imprimia — as colunas de hoje nao
      // mudam de posicao, senao quem le esta saida por posicao quebra sem regressao real.
      const naoAbre = existe === false ? `   ⚠️ NÃO ABRE: ${caminho}` : '';
      console.log(`${(fm.page || relative(repoRoot, cf)).padEnd(40)} → ${source || '⚠️ sem protótipo declarado'}${rotulo}${naEclipsado ? `   [+ ${corte}]` : ''}${naoAbre}`);
    }
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
  // ── BITE da PRECEDÊNCIA do `--list` (2026-09-09) ───────────────────────────
  // `"n/a (…)"` é string truthy: o `||` do listAll curto-circuitava nela e o `bundle_source`
  // nunca era alcançado — 14 charters saíam `isNa: true` com âncora de bundle resolvível.
  // Exercita `fonteDoCharter`, a MESMA função que o `listAll` chama (não uma cópia).
  const fNaBundle = fonteDoCharter({ related_prototype: 'n/a (herda PT-01 Lista; segue o Padrão de Tela)', bundle_source: 'produtos-page.jsx' });
  t('BITE precedência: n/a + bundle_source → a âncora é o BUNDLE',
    fNaBundle.source === 'produtos-page.jsx' && fNaBundle.via === 'bundle_source/visual_source');
  t('BITE precedência: o n/a eclipsado é PRESERVADO (não some do --list)',
    fNaBundle.naEclipsado === 'n/a (herda PT-01 Lista; segue o Padrão de Tela)' && fNaBundle.declaracaoNa === fNaBundle.naEclipsado);
  t('BITE precedência: com bundle, a linha deixa de ser contada como n/a',
    ehDeclaracaoNa(fNaBundle.source) === false);
  t('BITE precedência: visual_source vale igual a bundle_source',
    fonteDoCharter({ related_prototype: 'n/a (herda PT-04)', visual_source: 'essenciais-page.jsx' }).source === 'essenciais-page.jsx');
  t('CONTROLE precedência: fonte que NÃO é -page.jsx não vira âncora de bundle (mockupJsx manda)',
    fonteDoCharter({ related_prototype: 'n/a (herda PT-04)', visual_source: 'jana-merge.jsx' }).via === 'related_prototype');
  t('BITE precedência: n/a ENTRE ASPAS também cede ao bundle (o parser não desaspa)',
    fonteDoCharter({ related_prototype: '"n/a (herda PT-01 Lista)"', bundle_source: 'estoque-page.jsx' }).source === 'estoque-page.jsx');
  // CONTROLES — o que NÃO pode mudar. Sem eles o bite acima é carimbo.
  const fNaPuro = fonteDoCharter({ related_prototype: 'n/a (herda PT-01 Lista; segue o Padrão de Tela)' });
  t('CONTROLE precedência: n/a SEM bundle segue declaração (106 charters intactos)',
    fNaPuro.source === 'n/a (herda PT-01 Lista; segue o Padrão de Tela)' && fNaPuro.via === 'related_prototype' &&
    ehDeclaracaoNa(fNaPuro.source) === true && fNaPuro.naEclipsado === null);
  t('CONTROLE precedência: protótipo bespoke GANHA do bundle (design aprovado vem 1º)',
    fonteDoCharter({ related_prototype: 'prototipo-ui/cowork/jana-merge.jsx', bundle_source: 'produtos-page.jsx' }).via === 'related_prototype');
  t('CONTROLE precedência: charter sem fonte alguma segue silencioso (gap real)',
    fonteDoCharter({}).source === null && fonteDoCharter({}).via === null);
  // Era `CONTROLE ... fallback por component preservado`, fixando o ramo tautológico. Ele
  // morreu medido em 0 uso (nota em `fonteDoCharter`), e o assert vira o BITE do contrário:
  // quem ressuscitar o fallback encontra vermelho, em vez de um controle que o abençoa.
  const fComp = fonteDoCharter({ component: 'financeiro-page.jsx (window.X)' });
  t('BITE: fallback tautológico por `component` NÃO ressuscita (âncora ≠ a própria tela)',
    fComp.source === null && fComp.via === null);
  // BITE REAL contra a árvore: o charter que o defeito escondia resolve pelo bundle.
  const fmProduto = frontmatter(await read(join(REPO_DEFAULT, 'resources/js/Pages/Produto/Index.charter.md')));
  t('BITE real: Produto/Index declara n/a + bundle — e o --list agora vê o bundle',
    ehDeclaracaoNa(fmProduto.related_prototype) === true && fonteDoCharter(fmProduto).source === 'produtos-page.jsx');
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

  // ── DESEMPATE DE ATALHO (colisão medida em 2026-09-09: 14 dos 226) ──────────
  t('BITE path: casaPathInteiro casa sufixo de segmento inteiro',
    casaPathInteiro('resources/js/pages/nfse/index', 'nfse/index') === true);
  t('CONTROLE path: NÃO casa segmento partido (fiscal/nfse ≠ .../nfse/index)',
    casaPathInteiro('resources/js/pages/fiscal/nfse', 'nfse/index') === false);
  t('CONTROLE path: igualdade também casa (query = component inteiro)',
    casaPathInteiro('resources/js/pages/x/y', 'resources/js/pages/x/y') === true);
  t('CONTROLE path: vazio não casa nada', casaPathInteiro('', 'x') === false && casaPathInteiro('x', '') === false);
  // Contra a árvore REAL — é o caso concreto que o inventário citou. Antes deste PR,
  // `Nfse/Index` devolvia o charter de `Fiscal/Nfse` com `âncora ✓` e selo de frescor.
  const rn = await resolveAncora('Nfse/Index');
  t('BITE real: atalho Nfse/Index resolve o PRÓPRIO charter, não Fiscal/Nfse',
    rn.ok === true && rn.charter === 'resources/js/Pages/Nfse/Index.charter.md');
  // E o que NÃO tem desempate possível precisa DECLARAR o empate, não escolher calado.
  const rd = await resolveAncora('Dashboard/Index');
  t('BITE real: atalho ambíguo (Dashboard/Index) DECLARA os candidatos',
    Array.isArray(rd.ambiguidade) && rd.ambiguidade.length >= 2 && rd.ambiguidade.includes(rd.charter));
  t('CONTROLE real: atalho sem empate NÃO inventa ambiguidade',
    (await resolveAncora('/financeiro/unificado')).ambiguidade === null);

  // ── DESFECHO da CLI (decisão [W] 2026-09-09) — o exit code só existe na FIAÇÃO ────
  // Os `t` acima medem o CAMPO `r.ambiguidade`, que é a API. Nenhum deles prova o que a
  // CLI faz com ele: o exit code nasce em `process.exit(await printResolve(r))`, e um
  // assert que chamasse `printResolve` direto mediria a FUNÇÃO, não a fiação — é a lição
  // §5 2026-07-30 ("assert sobre helper exportado não prova contrato de pipeline"). Por
  // isso estes rodam o CLI DE FORA, em processo próprio, e leem o `status` de verdade.
  const { spawnSync } = await import('node:child_process');
  const CLI_PATH = fileURLToPath(import.meta.url);
  const cli = (...args) => spawnSync(process.execPath, [CLI_PATH, ...args], { encoding: 'utf8' });

  const cAmb = cli('Dashboard/Index');
  const saidaAmb = `${cAmb.stdout}${cAmb.stderr}`;
  t('BITE CLI: query ambígua SAI 2 — não escolhe',
    cAmb.status === 2);
  t('BITE CLI: query ambígua LISTA os candidatos (2+)',
    (saidaAmb.match(/\.charter\.md/g) || []).length >= 2);

  // TETO: o empate real do Ponto é de 21 hoje. O assert NÃO fixa o 21 (a árvore muda e o
  // teste viraria falso-vermelho) — ele cobra a RELAÇÃO: nunca mais de TETO_CANDIDATOS
  // listados, e o excedente declarado em contagem em vez de sumir calado.
  const cTeto = cli('Ponto/Index');
  const listados = (cTeto.stderr.match(/\.charter\.md/g) || []).length;
  const totalTeto = Number((cTeto.stderr.match(/— (\d+) charters casam/) || [])[1] || 0);
  t('BITE CLI: a lista respeita o teto e DECLARA quantos ficaram de fora',
    cTeto.status === 2 && listados <= TETO_CANDIDATOS && (totalTeto <= TETO_CANDIDATOS || /e mais \d+/.test(cTeto.stderr)));

  // O dano que motivou a decisão não era o empate — era o `✓` verde com selo de frescor
  // sobre uma tela sorteada entre N. Se ele voltar a sair, o exit 2 sozinho não conserta:
  // quem bate o olho lê o check e segue em frente.
  //
  // Mede as DUAS saídas de propósito, e a do Ponto é a que MORDE. A primeira versão deste
  // assert olhava só o `Dashboard/Index` e SOBREVIVEU à mutação que apaga o `return 2` —
  // porque o charter que aquela query elege declara `n/a (herda PT-04)`, então ele nunca
  // imprimiria `âncora ✓` nem selo, com defeito ou sem. Era carimbo. O `Ponto/Index` elege
  // um charter COM `related_prototype`, e aí a mutação fica vermelha (provado por mutação
  // em 2026-09-09). Se um dia as duas telas eleitas passarem a declarar `n/a`, este assert
  // volta a ser carimbo — quem mexer aqui re-prova por mutação antes de confiar nele.
  const saidaTeto = `${cTeto.stdout}${cTeto.stderr}`;
  t('BITE CLI: query ambígua NÃO imprime veredito de âncora nem selo de frescor',
    !/âncora ✓/.test(saidaAmb + saidaTeto) && !/frescor/.test(saidaAmb + saidaTeto));

  // Os dois CONTROLES — o que resolvia antes tem que seguir resolvendo, exit 0.
  const cForte = cli('/financeiro/unificado');
  t('CONTROLE CLI: match FORTE segue resolvendo, exit 0',
    cForte.status === 0 && /ÂNCORA da tela/.test(cForte.stdout));
  // Força 1 (substring no meio: não é `page`, não é path inteiro, não é sufixo) e ÚNICO.
  // Se a recusa vazasse pro caso de UM candidato só, este cai.
  const cFraco = cli('Financeiro/Conc');
  t('CONTROLE CLI: 1 match FRACO e único ainda resolve, exit 0',
    cFraco.status === 0 && /Conciliacao\/Index\.charter\.md/.test(cFraco.stdout));
  // ...e agora DIZ que foi fraco. O par completa o de cima: aquele prova que resolve; este,
  // que não resolve calado. Provado por mutação em 2026-09-09 (apagar o bloco deixa vermelho).
  t('BITE CLI: match FRACO se DECLARA fraco e nomeia o critério (substring)',
    cFraco.status === 0 && /match FRACO/.test(cFraco.stdout) && /SUBSTRING/.test(cFraco.stdout));
  // CONTROLES do silêncio: as duas formas legítimas de consulta (rota e caminho do
  // `component:`) resolvem forte e NÃO podem ganhar o aviso — senão ele vira ruído de fundo
  // e para de ser lido, que é como um alerta morre.
  t('CONTROLE CLI: match FORTE por rota NÃO ganha aviso de fraco',
    !/match FRACO/.test(cForte.stdout));
  const cComp = cli('resources/js/Pages/Financeiro/Conciliacao/Index.tsx');
  t('CONTROLE CLI: query canônica (component do charter) resolve forte e SEM aviso',
    cComp.status === 0 && !/match FRACO/.test(cComp.stdout));
  t('CONTROLE API: a força sai no retorno — 4 pra rota, 1 pro substring',
    (await resolveAncora('/financeiro/unificado')).forca === 4 && (await resolveAncora('Financeiro/Conc')).forca === 1);

  // ── AS DUAS CHAVES QUE NÃO SÃO ÂNCORA (reporter, nunca promoção) ────────────
  const FX_CHARTER = [
    '---',
    'page: /x/y',
    'component: resources/js/Pages/X/Y.tsx',
    'mwart_pattern_reuse:',
    '  blueprint_cowork: prototipo-ui/cowork/outra-page.jsx',
    '  divergence_from_blueprint: "wizard substitui o modal do blueprint"',
    '---',
    '# corpo',
    'blueprint_cowork: prototipo-ui/cowork/ISTO-E-PROSA.jsx',
  ].join(String.fromCharCode(10));
  t('BITE fm: chaveAninhada lê chave INDENTADA (o frontmatter() compartilhado não lê)',
    chaveAninhada(FX_CHARTER, 'blueprint_cowork') === 'prototipo-ui/cowork/outra-page.jsx');
  t('CONTROLE fm: chave depois do frontmatter é PROSA, não declaração',
    chaveAninhada(FX_CHARTER, 'blueprint_cowork') !== 'prototipo-ui/cowork/ISTO-E-PROSA.jsx');
  t('CONTROLE fm: chave ausente devolve null, não string vazia',
    chaveAninhada(FX_CHARTER, 'nao_existe') === null);
  t('CONTROLE fm: texto sem frontmatter devolve null', linhasDoFrontmatter('# só corpo') === null);
  t('BITE divergência: "none — …" conta como SEM divergência', ehSemDivergencia('none — Index é o blueprint') === true);
  t('CONTROLE divergência: divergência real NÃO vira none', ehSemDivergencia('wizard substitui o modal') === false);
  t('CONTROLE divergência: ausente não explode', ehSemDivergencia(undefined) === false);
  t('BITE porte reverso: .blade.php é código do repo', ehCodigoDoRepo('resources/views/contact/show.blade.php') === true);
  t('BITE porte reverso: Page .tsx é código do repo', ehCodigoDoRepo('resources/js/Pages/governance/Dashboard.tsx') === true);
  t('CONTROLE porte reverso: -page.jsx do espelho NÃO é código do repo',
    ehCodigoDoRepo('prototipo-ui/cowork/produtos-page.jsx') === false);
  const dna = declaracoesNaoAncora({ charterSrc: FX_CHARTER, canonRefSrc: ['---', 'canon_reference: resources/views/contact/index.blade.php', '---'].join(String.fromCharCode(10)) });
  t('BITE não-âncora: acha as DUAS chaves', dna.length === 2);
  t('BITE não-âncora: a divergência declarada entra na nota',
    /DIVERGE do blueprint/.test((dna[0] || {}).nota || ''));
  t('BITE não-âncora: canon_reference apontando Blade é marcado porte REVERSO',
    /porte REVERSO/.test((dna[1] || {}).nota || ''));
  // Sem este, o bloco inteiro poderia estar imprimindo sempre — e um reporter que fala
  // sobre toda tela é ruído, não sinal.
  t('CONTROLE não-âncora: charter sem as duas chaves devolve LISTA VAZIA',
    declaracoesNaoAncora({ charterSrc: ['---', 'page: /a', '---'].join(String.fromCharCode(10)), canonRefSrc: null }).length === 0);
  t('CONTROLE não-âncora: entrada vazia não explode', declaracoesNaoAncora().length === 0);
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

  // ── BITE do fallback REMOVIDO (heurística startsWith(dir)) — 2026-09-09 ────
  // O ramo removido casava o mockup por NOME DE PASTA: `Pages/<Dir>/X.tsx` + qualquer
  // `<dir>*-page.jsx` no staging viravam âncora. Medido antes de remover (42 telas que ele
  // resolvia): 25 já tinham `related_prototype` — ele era supérfluo, o consumidor pega o [0];
  // 16 declaravam `n/a` — ali ele SOBRESCREVIA a decisão do charter, e o aviso "sem âncora POR
  // DECISÃO" do design-diff-lote ficava inalcançável. Ele viveu meses sem fixture nenhuma: a
  // única do staging usa `bundle_source`, e o selftest ficava VERDE com o ramo apagado. Este
  // par fecha isso — sem ele, quem reintroduzir o fallback não encontra vermelho.
  const fxHeur = join(fx, 'heur');
  await mkdir(join(fxHeur, 'repo', 'resources', 'js', 'Pages', 'SemCampo'), { recursive: true });
  await mkdir(join(fxHeur, 'staging'), { recursive: true });
  // O basename do mockup CASA a pasta do componente — é exatamente o que o fallback exigia.
  await writeFile(join(fxHeur, 'staging', 'semcampo-page.jsx'), '// isca do fallback por nome de pasta\n', 'utf8');
  const charterSemCampo = ['---', 'page: /sem-campo', 'component: resources/js/Pages/SemCampo/Index.tsx', '---', '# sem campo'].join('\n');
  await writeFile(join(fxHeur, 'repo', 'resources', 'js', 'Pages', 'SemCampo', 'Index.charter.md'), charterSemCampo, 'utf8');
  const rSem = await resolveAncora('SemCampo/Index', { repoRoot: join(fxHeur, 'repo'), stagingDir: join(fxHeur, 'staging') });
  t('BITE fallback: charter SEM bundle_source NÃO ganha âncora de bundle, mesmo com o -page.jsx casando a pasta',
    rSem.ok === true && !rSem.ancoras.some((a) => a.tipo.startsWith('-page.jsx')));
  // CONTROLE POSITIVO — sem ele o BITE acima passaria com o resolvedor de bundle QUEBRADO
  // (verde por não-execução: nada resolve, logo o BITE fica verde por acidente).
  await mkdir(join(fxHeur, 'repo2', 'resources', 'js', 'Pages', 'SemCampo'), { recursive: true });
  await writeFile(join(fxHeur, 'repo2', 'resources', 'js', 'Pages', 'SemCampo', 'Index.charter.md'),
    charterSemCampo.replace('---\n# sem campo', 'bundle_source: semcampo-page.jsx\n---\n# sem campo'), 'utf8');
  const rDecl = await resolveAncora('SemCampo/Index', { repoRoot: join(fxHeur, 'repo2'), stagingDir: join(fxHeur, 'staging') });
  t('CONTROLE fallback: o MESMO staging COM bundle_source declarado RESOLVE (o resolvedor está vivo)',
    rDecl.ok === true && rDecl.ancoras.some((a) => a.tipo === '-page.jsx (bundle · bundle_source)' && a.valor === 'semcampo-page.jsx'));

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

  // ── `--list` PROVA O ARQUIVO — os 3 estados de `existe` (2026-09-09) ─────────
  // Fixture HERMETICA propria: o corpus real e movel (226 linhas hoje) e assertar contagem
  // dele aqui seria congelar numero derivado de arvore viva (§5 2026-08-24). A fixture fixa
  // os 3 casos que a mudanca precisa SEPARAR; o numero do corpus vai no corpo do PR.
  // O BITE exercita `listAll` DE FORA (capturando o stdout dela), nao um helper satelite:
  // assert sobre copia paralela fica verde enquanto o pipeline regride (§5 2026-08-14).
  const fxList = join(fx, 'lista');
  const fxListPages = join(fxList, 'resources', 'js', 'Pages', 'Fx');
  await mkdir(join(fxList, 'prototipo-ui', 'cowork'), { recursive: true });
  await mkdir(fxListPages, { recursive: true });
  await writeFile(join(fxList, 'prototipo-ui', 'cowork', 'fx-real.jsx'), '// existe de verdade\n', 'utf8');
  const charterFx = (page, rp) => ['---', `page: ${page}`, `related_prototype: ${rp}`, '---', '# fx'].join('\n');
  await writeFile(join(fxListPages, 'Quebrado.charter.md'), charterFx('/fx/quebrado', 'prototipo-ui/cowork/fx-nao-existe.jsx'), 'utf8');
  await writeFile(join(fxListPages, 'Real.charter.md'), charterFx('/fx/real', 'prototipo-ui/cowork/fx-real.jsx'), 'utf8');
  await writeFile(join(fxListPages, 'Na.charter.md'), charterFx('/fx/na', 'n/a (herda PT-01 Lista; segue o Padrão de Tela)'), 'utf8');
  await writeFile(join(fxListPages, 'NaCita.charter.md'), charterFx('/fx/na-cita', 'n/a (herda PT-01; o fx-real.jsx desenha OUTRA tela — ancorar aqui seria tautologico)'), 'utf8');

  // `listAll` so imprime — capturar o stdout e o unico jeito de assertar o JSON dela sem
  // mudar a assinatura (mudar a API publica esta proibido: o hook post-merge-ui-smoke
  // importa deste arquivo e degrada em SILENCIO se o import quebrar).
  const capturado = [];
  const logOriginal = console.log;
  console.log = (...a) => { capturado.push(a.join(' ')); };
  try { await listAll(fxList, true); } finally { console.log = logOriginal; }
  const linhasFx = JSON.parse(capturado.join('\n'));
  const linhaFx = (p) => linhasFx.find((l) => l.page === p);
  const lQuebrado = linhaFx('/fx/quebrado');
  const lReal = linhaFx('/fx/real');
  const lNa = linhaFx('/fx/na');

  t('BITE list: fonte que nao abre → existe:false, e `hasSource` segue TRUE (a guarda)',
    !!lQuebrado && lQuebrado.existe === false && lQuebrado.hasSource === true
      && lQuebrado.caminho === 'prototipo-ui/cowork/fx-nao-existe.jsx');
  t('CONTROLE list: caminho real da o existe true',
    !!lReal && lReal.existe === true && lReal.caminho === 'prototipo-ui/cowork/fx-real.jsx');
  // Sem este, colapsar `null` em `false` deixaria o BITE acima verde e inventaria 105 defeitos.
  t('CONTROLE list: n/a nao vira existe false — fica null, e `isNa` segue true',
    !!lNa && lNa.existe === null && lNa.caminho === null && lNa.isNa === true && lNa.hasSource === true);
  // O caso que a formula literal errava: `n/a` cuja PROSA cita um arquivo REAL — 7 no corpus,
  // e em todos os 7 a citacao existe pra NEGAR a ancoragem. Sem este controle, tirar o
  // `!ehDeclaracaoNa` da guarda mantem o selftest verde (carimbo) e o `--list` afirma ancora
  // que o charter nega. O arquivo citado EXISTE na fixture de proposito — e o que torna o
  // controle capaz de ficar vermelho.
  const lNaCita = linhaFx('/fx/na-cita');
  t('CONTROLE list: n/a que CITA arquivo real na prosa segue caminho:null (nao vira ancora)',
    !!lNaCita && lNaCita.caminho === null && lNaCita.existe === null && lNaCita.isNa === true);

  // ── BITE do bundle NO LUGAR FIXO, sem `--staging` (2026-09-09) ───────────────
  // Fixture PRÓPRIA, separada da de staging de propósito: lá o charter e o mockup têm o
  // mesmo basename e reusá-la acoplaria os dois casos — mexer num quebraria o outro por
  // motivo que não é o do teste. Aqui o mockup vive no LUGAR_FIXO do repo-fixture, que é
  // exatamente a condição que a perna nova lê.
  const fxB = join(fx, 'bundle');
  const fxBRepo = join(fxB, 'repo');
  const fxBStaging = join(fxB, 'staging');
  const pages = (n) => join(fxBRepo, 'resources', 'js', 'Pages', n);
  await mkdir(join(fxBRepo, 'prototipo-ui', 'cowork'), { recursive: true });
  await mkdir(join(fxBStaging, 'sub'), { recursive: true });
  for (const n of ['Fixo', 'Ausente', 'Dupla', 'Visual']) await mkdir(pages(n), { recursive: true });
  await writeFile(join(fxBRepo, 'prototipo-ui', 'cowork', 'bundle-page.jsx'), '// no lugar fixo do repo\n', 'utf8');
  // MESMO basename no staging: é o que permite provar QUAL das duas pernas ganhou.
  await writeFile(join(fxBStaging, 'sub', 'bundle-page.jsx'), '// no staging\n', 'utf8');
  const chB = (page, linhas) => ['---', `page: ${page}`, ...linhas, '---', '# fx bundle'].join('\n');
  await writeFile(join(pages('Fixo'), 'Index.charter.md'), chB('/fxb/fixo', ['bundle_source: bundle-page.jsx']), 'utf8');
  await writeFile(join(pages('Ausente'), 'Index.charter.md'), chB('/fxb/ausente', ['bundle_source: nao-existe-page.jsx']), 'utf8');
  await writeFile(join(pages('Visual'), 'Index.charter.md'), chB('/fxb/visual', ['visual_source: bundle-page.jsx']), 'utf8');
  await writeFile(join(pages('Dupla'), 'Index.charter.md'),
    chB('/fxb/dupla', ['related_prototype: prototipo-ui/cowork/bundle-page.jsx', 'bundle_source: bundle-page.jsx']), 'utf8');
  const soBundle = (r) => (r.ok ? r.ancoras.filter((a) => a.tipo.startsWith('-page.jsx')) : []);

  const rFixo = await resolveAncora('Fixo/Index', { repoRoot: fxBRepo });
  t('BITE bundle sem staging: `bundle_source` resolve no LUGAR_FIXO, sem a flag',
    soBundle(rFixo).length === 1 && soBundle(rFixo)[0].valor === 'prototipo-ui/cowork/bundle-page.jsx');
  // O defeito de 2026-08-25 catalogado em `defeitosDaAncora` foi passar o staging como raiz:
  // o arquivo está NO GIT, então a raiz de leitura é o repo. Sem esta asserção, trocar a raiz
  // mantém a âncora "resolvida" e o P-1 volta a medir contra o lugar errado.
  t('BITE bundle sem staging: a raiz de leitura é o REPO (o arquivo está no git, não em staging)',
    soBundle(rFixo).length === 1 && resolve(soBundle(rFixo)[0].raiz) === resolve(fxBRepo));
  t('BITE bundle sem staging: o rótulo diz o campo REAL — `visual_source` não vira `bundle_source`',
    soBundle(await resolveAncora('Visual/Index', { repoRoot: fxBRepo }))[0]?.tipo === '-page.jsx (bundle · visual_source)');
  // CONTROLE que impede o "empurra sempre": nome declarado que NÃO abre não pode virar âncora,
  // senão o ⚠️ de ausência some e a tela passa a exibir um ponteiro pro vazio.
  t('CONTROLE bundle sem staging: nome declarado que NÃO está no lugar fixo não vira âncora',
    soBundle(await resolveAncora('Ausente/Index', { repoRoot: fxBRepo })).length === 0);
  // Precedência: `--staging` VENCE. Se o guard virasse `else` (ou sumisse), esta e a de baixo
  // ficariam vermelhas — é o par que fixa a ordem sem depender de onde o bloco mora no arquivo.
  const rStg = await resolveAncora('Fixo/Index', { repoRoot: fxBRepo, stagingDir: fxBStaging });
  t('CONTROLE staging vence o fixo: com a flag, a perna é a DO STAGING (valor e raiz)',
    soBundle(rStg).length === 1 && soBundle(rStg)[0].valor === 'sub/bundle-page.jsx'
      && resolve(soBundle(rStg)[0].raiz) === resolve(fxBStaging));
  t('CONTROLE staging vence o fixo: resolve UMA perna de bundle, não duas',
    soBundle(rStg).length === 1);
  // O caso do `Sells/Index`: os DOIS campos apontam o MESMO arquivo. Sem dedup ele imprime a
  // âncora 2×; deduplicando por TIPO não pegaria nada (os tipos diferem por construção). E a
  // varredura tem que ser PRA FRENTE: de trás pra frente mantém a última e rebaixa o
  // protótipo aprovado a âncora de bundle — foi o bug que este controle pegou.
  const rDup = await resolveAncora('Dupla/Index', { repoRoot: fxBRepo });
  t('CONTROLE dedup: mesmo arquivo nos dois campos → UMA âncora, e é o related_prototype',
    rDup.ok && rDup.ancoras.length === 1 && rDup.ancoras[0].tipo === 'related_prototype (charter)');

  // ── ÂNCORA EFETIVA × "também declarado" (2026-09-09) ────────────────────────
  // Quando os dois campos apontam arquivos DIFERENTES (5 telas no corpus: as 4 sub-páginas
  // do Fiscal + Essentials/Knowledge), as DUAS declarações são verdadeiras e ficam na
  // estrutura — quem some daqui some do cross-check do `gerar-map`. O que muda é o RÓTULO:
  // a 1ª que resolve é a efetiva; a 2ª é o bundle de origem, e sai sem selo porque não é
  // medida. O BITE exercita o `printResolve` DE FORA (capturando stdout), não um helper
  // satélite — assert sobre cópia paralela fica verde enquanto o pipeline regride (§5 2026-08-14).
  await mkdir(pages('Diverge'), { recursive: true });
  await writeFile(join(fxBRepo, 'prototipo-ui', 'cowork', 'especifico-page.jsx'), '// o desenho DESTA tela\n', 'utf8');
  await writeFile(join(pages('Diverge'), 'Index.charter.md'),
    chB('/fxb/diverge', ['related_prototype: prototipo-ui/cowork/especifico-page.jsx', 'bundle_source: bundle-page.jsx']), 'utf8');
  const rDiv = await resolveAncora('Diverge/Index', { repoRoot: fxBRepo });
  t('BITE divergente: arquivos DIFERENTES nos dois campos → as DUAS ficam na estrutura',
    rDiv.ok && rDiv.ancoras.length === 2);
  const capDiv = [];
  const logDiv = console.log;
  console.log = (...a) => { capDiv.push(a.join(' ')); };
  try { await printResolve(rDiv); } finally { console.log = logDiv; }
  const saidaDiv = capDiv.join('\n');
  t('BITE divergente: o printer marca UMA como efetiva (`âncora`) e a outra como `também declarado`',
    (saidaDiv.match(/^ {2}âncora [✓⚠️]/gm) || []).length === 1
      && saidaDiv.includes('também declarado')
      && /âncora [✓⚠️][\s\S]*também declarado/.test(saidaDiv));
  t('BITE divergente: a efetiva é o related_prototype (a específica), não o hub do bundle',
    /âncora [✓⚠️].*especifico-page\.jsx/.test(saidaDiv));
  // Sem este, imprimir "também declarado" pra TODA âncora ficaria verde no BITE acima.
  const capUm = [];
  console.log = (...a) => { capUm.push(a.join(' ')); };
  try { await printResolve(await resolveAncora('Fixo/Index', { repoRoot: fxBRepo })); } finally { console.log = logDiv; }
  t('CONTROLE divergente: com UMA âncora só, o rótulo `também declarado` NÃO aparece',
    !capUm.join('\n').includes('também declarado'));

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

  // ── COBERTURA: o eixo que o `✓ frescor` NÃO cobre (incidente 2026-09-09) ──────
  // Duas rodadas com o MESMO denominador: sem isso o veredito sai SCOPE-CHANGED (não
  // comparável) e o assert mediria outra coisa que não a cobertura.
  const AGORA = '2026-09-09T12:00:00.000Z';
  const loFalta = [
    { date: '2026-09-07T10:00:00.000Z', kind: KIND_LIVE_ONLY, liveOnly: 2, denom: 748, liveOnlyList: ['a.jsx', 'b.jsx'] },
    { date: '2026-09-08T11:47:11.090Z', kind: KIND_LIVE_ONLY, liveOnly: 2, denom: 748, liveOnlyList: ['a.jsx', 'b.jsx'] },
  ];

  t('COBERTURA: espelho não cobre o vivo → avisa, com o número e a rota da fonte viva',
    (() => {
      const l = avisoDeCobertura(loFalta, 'patrimonio-page.jsx', AGORA);
      return l.length === 3
        && l[0].includes('2 arquivo(s) de 748')
        && l[2].includes(COWORK_PROJECT_ID)
        && l[2].includes('patrimonio-page.jsx');
    })());

  // O controle que dá sentido ao assert acima: se o espelho COBRE o vivo, o aviso some.
  // Sem esta perna o aviso seria carimbo — texto que aparece sempre não informa nada.
  t('CONTROLE COBERTURA: nada faltando no vivo → NENHUM aviso (não é carimbo)',
    avisoDeCobertura(
      loFalta.map((e) => ({ ...e, liveOnly: 0, liveOnlyList: [] })),
      'patrimonio-page.jsx',
      AGORA,
    ).length === 0);

  t('COBERTURA: ledger sem nenhuma rodada de live-only → NUNCA MEDIDA (ausência ≠ saúde)',
    avisoDeCobertura([], 'x.jsx', AGORA)[0].includes('NUNCA MEDIDA'));

  // Medição velha não vira silêncio: o que entrou no vivo depois dela é invisível.
  t('COBERTURA: medição fora do SLA → MEDIÇÃO VENCIDA, não OK',
    avisoDeCobertura(
      [{ date: '2026-01-01T00:00:00.000Z', kind: KIND_LIVE_ONLY, liveOnly: 0, denom: 748, liveOnlyList: [] }],
      'x.jsx',
      AGORA,
    )[0].includes('VENCIDA'));

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
