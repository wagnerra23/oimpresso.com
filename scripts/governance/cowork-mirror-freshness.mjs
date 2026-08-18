#!/usr/bin/env node
// @ts-check
/**
 * cowork-mirror-freshness.mjs — comparador de FRESCOR do espelho Cowork (v2, identidade canônica).
 *
 * O QUE É: ferramenta de DISPATCH (agente logado) que compara cada arquivo-âncora do espelho
 * `prototipo-ui/cowork/` com o design VIVO no Cowork (projeto 019dcfd3, lido via
 * `DesignSync.get_file` — método de LEITURA, livre por ADR 0315 Eixo B). Divergiu = o espelho
 * DIVERGE do vivo → investigar. ⚠️ NÃO diz a DIREÇÃO: hash diferente não revela quem avançou.
 * Automatiza o "diffar antes de concluir" que o
 * INDEX-DESIGN-MEMORIAS §0.2 (registro canônico da fonte Cowork) manda fazer.
 *
 * Nota de vocabulário (§0.2): o espelho "não apodrece SOZINHO" — ninguém o edita à toa. O que
 * este comparador detecta é OUTRO evento: o VIVO avançar e o espelho ficar pra trás (drift por
 * não-re-exportar). São coisas diferentes; este mede a segunda.
 *
 * ── IDENTIDADE DE ARQUIVO (v2 — a lição do adversário 2026-07-06) ────────────────
 * v1 morreu no review adversarial por 2 bugs de fundamento (session
 * 2026-07-06-ancora-podre-sentinela-conteudo.md + arte 2026-07-06-arte-design-code-sync-frescor.md):
 *   (a) chaveava por BASENAME → arquivos homônimos em subdirs (md5 diferente) colapsavam;
 *   (b) hasheava BYTES CRUS → CRLF/BOM davam STALE falso (o repo só passava "por sorte" via
 *       .gitattributes eol=lf).
 * v2 usa a identidade que git/Nx/Turborepo/Tokens Studio usam há uma década:
 *   HASH = sha256( normalize(conteúdo) )   keyed por PATH RELATIVO COMPLETO dentro do espelho.
 *   normalize = strip BOM · CRLF/CR→LF · trailing-newline única · UTF-8.
 * O snapshot do vivo DEVE aplicar a MESMA normalização (função exportada aqui — use-a).
 *
 * ── SPLIT (o node não fala MCP) ───────────────────────────────────────────────────
 *   1. LOCAL (puro):   --manifest [--all]  → lista {path relativo, repoHash, telas} (JSON stdout).
 *   2. VIVO (agente):  para cada path do manifesto, `DesignSync.get_file` no projeto vivo →
 *      snapshot `{ "<rel-path>": contentHash(content) }` (null = buscou e não achou).
 *   3. LOCAL (puro):   --compare snap.json [--check] → SYNC · STALE · LIVE-ABSENT · UNCHECKED.
 *
 * Vereditos: STALE = hash normalizado difere (o sinal DURO; --check sai 1 SÓ nele) ·
 * LIVE-ABSENT = buscado e ausente no vivo (rename/delete upstream — warn) · UNCHECKED = não
 * veio no snapshot (NUNCA vira SYNC no silêncio — a suite não mente por omissão).
 *
 * ── STATUS DE WIRING (honestidade — não é gate) ───────────────────────────────────
 * NÃO está wirado em CI. A auth do DesignSync é interativa (/design-login) — sem webhook nem
 * service-token na plataforma —, então isto é ROTINA DE DISPATCH logado, não gate de PR.
 * Wirar como dispatch-com-SLA (ou PR-bot regenerador, modelo Tokens Studio) = ação #3/#5 do
 * estado-da-arte 2026-07-06, pendente de aprovação [W]. Não declare isto "gate" até lá.
 *
 * ── DEPS DE RENDER (furo LC-07, 2026-07-07) ──────────────────────────────────────
 * O manifest por âncora media só o .jsx da TELA — mas o render dela depende de app.jsx,
 * styles.css, ds-v6/tokens.css e os css do módulo. Drift real vazou por aí: [W] mudou o
 * PageHeaderNav pra roxo no app.jsx do Cowork e a rodada "3 SYNC" ficou verde, cega.
 * Agora o --manifest também enumera as DEPS DE RENDER, derivadas MECANICAMENTE dos
 * src/href do shell (oimpresso.com.html do staging fixo) — nunca lista curada na mão.
 * Sem shell disponível → WARN explícito (nunca omissão silenciosa). kind: ancora | dep.
 *
 * Uso:
 *   node scripts/governance/cowork-mirror-freshness.mjs --manifest          # âncoras + deps de render (shell do staging)
 *   node scripts/governance/cowork-mirror-freshness.mjs --manifest --shell <oimpresso.com.html>  # shell explícito
 *   node scripts/governance/cowork-mirror-freshness.mjs --manifest --all    # todo .jsx/.html/.css/.js do espelho
 *   node scripts/governance/cowork-mirror-freshness.mjs --compare snap.json            # relatório
 *   node scripts/governance/cowork-mirror-freshness.mjs --compare snap.json --check    # exit 1 se STALE
 *   node scripts/governance/cowork-mirror-freshness.mjs --compare snap.json --check --ledger  # + registra a rodada
 *   node scripts/governance/cowork-mirror-freshness.mjs --sla               # headless: rotina rodou ≤14d? última limpa?
 *   node scripts/governance/cowork-mirror-freshness.mjs --check-refs        # a poda deste PR quebrou o grafo do espelho? (exit 1 = sim)
 *   node scripts/governance/cowork-mirror-freshness.mjs --check-refs --range <a>..<b>
 *   node scripts/governance/cowork-mirror-freshness.mjs --check-refs --deleted-from <lista.txt>   # fixture/manual
 *
 * ── OS 3 MODOS DE VERIFICAÇÃO, E QUAL PERGUNTA CADA UM RESPONDE ──────────────────
 * Não são redundantes; cada um cobre um flanco que os outros NÃO veem:
 *   --compare      "o espelho está ATRASADO em relação ao vivo?"  (precisa do DesignSync → dispatch logado, não gate)
 *   --live-only    "tem coisa no vivo que NUNCA desceu pro espelho?" (o ponto cego que escondeu jana-merge.jsx)
 *   --check-refs   "a poda deste PR quebrou o grafo INTERNO do espelho?" (100% local → gateável em CI)
 * O ABSENT-LOCAL (dentro do --manifest) é o quarto: "o shell carrega algo que o espelho não tem?".
 * --check-refs e ABSENT-LOCAL se completam: aquele deriva o universo do DIFF, este do SHELL — então
 * apagar o espelho inteiro passa vazio no primeiro e é pego pelo segundo.
 */

import { readFileSync, writeFileSync, readdirSync, statSync, existsSync, mkdirSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { join, dirname } from 'node:path';
import { anchorRelPath } from './anchor-content-check.mjs'; // fonte única: como extrair o path do related_prototype

const ROOT = process.cwd();

/** Normalização canônica de conteúdo (a MESMA que o git aplica no index via eol=lf):
 *  strip BOM · CRLF/CR→LF · trailing-newline única. Identidade nunca depende de "sorte de
 *  checkout". String vazia permanece vazia. */
export function normalize(s) {
  if (s === '') return '';
  return s.replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n').replace(/\n*$/, '\n');
}

/** Hash canônico de identidade: sha256(normalize(utf8)). Aceita Buffer ou string. */
export function contentHash(bufOrStr) {
  const s = Buffer.isBuffer(bufOrStr) ? bufOrStr.toString('utf8') : bufOrStr;
  return createHash('sha256').update(normalize(s), 'utf8').digest('hex');
}

/** Classificação 3-vias (pura, testável) a partir dos dois hashes canônicos. */
export function classifyMirror({ repoHash, liveHash }) {
  if (liveHash == null || liveHash === '') return 'LIVE-ABSENT';
  return repoHash === liveHash ? 'SYNC' : 'STALE';
}

/** Veredito de UM path do manifesto contra o snapshot. Object.hasOwn (não `in`) — chave
 *  homônima de membro do prototype (toString etc.) não pode virar veredito fantasma. */
export function verdictFor(relPath, repoHash, snapshot) {
  if (!Object.hasOwn(snapshot, relPath)) return 'UNCHECKED';
  return classifyMirror({ repoHash, liveHash: snapshot[relPath] });
}

/** --check só morde em STALE — o único sinal hash-provado de divergência. */
export function shouldFail(verdicts) {
  return verdicts.some((v) => v === 'STALE');
}

/** Linha de veredito final do --compare. PURA (testável).
 *
 *  DUAS doenças consertadas aqui, medidas no mesmo dia (2026-08-13), ambas no eixo do OUTPUT:
 *
 *  (1) VERDE COM VERMELHO NO CORPO — o `console.log('✓ sem espelho STALE')` vivia FORA do
 *      `if (strict …)`. Com `--check` o `process.exit(1)` saía antes e a linha nunca era
 *      alcançada; SEM `--check` ela sempre imprimia. Reproduzido: corpo com `⛔ stale: 1` e
 *      rodapé `✓`. Família LC-10 (artefato afirmando o próprio estado) — a mesma doença que o
 *      `ancora.mjs` teve carimbando `✓` sobre âncora `n/a`.
 *      Regra que sai disso: o TEXTO é do fato medido; `--check` decide só o EXIT CODE.
 *
 *  (2) VERDE POR NÃO-MEDIÇÃO — com 0 STALE entre 2 arquivos medidos e 119 SEM VEREDITO, a
 *      linha final dizia `✓ sem espelho STALE (divergência hash-provada)`. Nada foi provado
 *      sobre os 119: "não achei divergência" e "não procurei" viravam a mesma frase. Família
 *      LC-13 — o mesmo formato de `0 failed` numa suíte que não rodou.
 *      Regra que sai disso: superlativo ("sem espelho STALE") só pode falar do que foi MEDIDO,
 *      e o denominador anda junto. Cobertura parcial ⇒ INCONCLUSIVO, nunca ✓.
 *
 *  ⚠️ O ENFORCEMENT NÃO MUDA: `--check` continua mordendo SÓ em STALE (contrato de
 *  `shouldFail`). Inconclusivo é um VEREDITO honesto, não um gate novo — promover cobertura a
 *  bloqueio seria decisão [W] (ADR 0336), não efeito colateral de conserto de texto. Por isso
 *  `ok` segue true no caso inconclusivo: ele descreve "não é falha dura", não "está em dia".
 *
 *  `cobertura` é opcional — sem ela o comportamento é o de antes (compat com chamador velho). */
export function veredictoFinal(nStale, cobertura = null) {
  if (nStale > 0) {
    return { ok: false, texto: `✗ ${nStale} arquivo(s) DIVERGEM do vivo. O hash não diz QUEM avançou — antes de re-exportar, rode o dry-run do aplicar-payload: ele compara conteúdo e avisa se o espelho está À FRENTE (perda líquida de linhas).` };
  }
  if (cobertura && cobertura.semVeredito > 0) {
    return {
      ok: true,
      inconclusivo: true,
      texto:
        `⚠ INCONCLUSIVO — 0 STALE entre os ${cobertura.medidos} arquivo(s) MEDIDO(S), mas ` +
        `${cobertura.semVeredito} de ${cobertura.total} seguem SEM VEREDITO. ` +
        `"Não achei divergência" não é "não há divergência": complete o snapshot antes de concluir que o espelho está em dia.`,
    };
  }
  const quantos = cobertura ? `${cobertura.total} arquivo(s) medido(s)` : 'todos os medidos';
  return { ok: true, texto: `✓ sem espelho STALE — ${quantos}, divergência hash-provada.` };
}

// ── LIVE-ONLY: o ponto cego que deixou `jana-merge.jsx` fora do git por dias ──────
// O manifesto monta o universo do lado do ESPELHO (readdir de prototipo-ui/cowork/).
// Consequência estrutural: arquivo que existe no VIVO e nunca foi exportado é invisível
// POR CONSTRUÇÃO — o LIVE-ABSENT cobre o inverso (está no espelho, sumiu do vivo).
// É a mesma forma do §5 2026-08-10 (catraca que itera o lado mutável).
// Incidente 2026-08-11: `jana-merge.jsx` vivia no Cowork, era citado por 21 sites do repo
// (charter, 2 .tsx de produção, workflow, testes) e NÃO estava versionado. Nenhuma
// ferramenta apontou — porque nenhuma olhava para esse lado.
/** Paths que existem no VIVO e não têm contraparte no espelho (puro, testável).
 *  `livePaths`: lista de paths do projeto vivo (DesignSync.list_files).
 *  `manifest`: saída de buildManifest (cada item tem `.cowork`).
 *  Só considera extensões que o espelho versiona — o vivo tem .md/.png/_arquivo que
 *  não são protótipo e acusá-los seria ruído (falso-positivo por construção). */
export function liveOnly(livePaths, manifest, { exts = ['.jsx', '.html', '.css', '.js'] } = {}) {
  const noEspelho = new Set(manifest.map((f) => f.cowork));
  return livePaths
    .filter((p) => exts.some((e) => p.toLowerCase().endsWith(e)))
    .filter((p) => !p.startsWith('_arquivo/'))     // arquivo morto declarado upstream
    .filter((p) => !p.startsWith('prototipo-ui/')) // cópia do próprio espelho dentro do vivo
    .filter((p) => !noEspelho.has(p))
    .sort();
}

// ── EXPORT FIEL: o erro que transcrição manual causa, eliminado na raiz ───────────
// Incidente 2026-08-11: exportei 923 linhas transcrevendo à mão e saiu STALE. Pior — a
// versão errada tinha 20 linhas a menos e me levou a "corrigir" um charter que estava
// CERTO. A escrita tem que sair do JSON do get_file, nunca do olho do agente.
/** Plano de export a partir dos JSONs que o agente salvou do DesignSync.get_file.
 *  Puro: recebe [{path, content}] e devolve [{relPath, content, bytes}].
 *  `path` é o path NO VIVO; o espelho grava em prototipo-ui/cowork/<path>. */
export function exportPlan(arquivosVivos, { prefixo = 'prototipo-ui/cowork/' } = {}) {
  return arquivosVivos.map(({ path: p, content }) => {
    if (typeof content !== 'string') {
      throw new Error(`export: conteúdo ausente para "${p}" — o JSON do get_file precisa ter .content`);
    }
    return { relPath: prefixo + p, content, bytes: Buffer.byteLength(content, 'utf8') };
  });
}

// ── LEDGER + SLA (a metade que o CI headless PODE checar com honestidade) ─────
// O CI não lê o Cowork vivo (auth interativa). Então o CI NÃO mede frescor — mede se a
// ROTINA de dispatch rodou dentro do SLA e qual foi o último resultado. Ledger datado,
// append-only, commitado: prova > promessa (session 2026-07-06-arte-design-code-sync-frescor).
export const LEDGER_REL = 'scripts/governance/.cowork-freshness-ledger.json';
export const SLA_DAYS = 14;

/** Entrada de ledger (pura, testável) a partir das rows do --compare. */
export function ledgerEntry(rows, dateIso, meta = {}) {
  const n = (v) => rows.filter((r) => r.veredito === v).length;
  const e = {
    date: dateIso,
    files: rows.length,
    sync: n('SYNC'),
    stale: n('STALE'),
    liveAbsent: n('LIVE-ABSENT'),
    unchecked: n('UNCHECKED'),
    staleList: rows.filter((r) => r.veredito === 'STALE').map((r) => r.cowork),
    // QUAIS arquivos esta rodada de fato PROVOU idênticos ao vivo. Sem esta lista o ledger
    // sabe "21 sync" mas não sabe 21 de QUEM — e aí não há como responder a pergunta que o
    // remendo à mão de 2026-08-13 escapou por 4 dias: "este arquivo mudou DEPOIS da última
    // vez que alguém provou que ele bate com o vivo?". É o insumo do `--unverified`.
    // path + HASH. Só o path não basta: merge/squash/rebase reescrevem a data de commit de
    // todo arquivo tocado sem mudar um byte, e um detector que compara só DATAS acusa isso como
    // "mexido à mão". Medido 2026-08-17: o squash do #5854 (12:10:47) gerou 6 falsos-positivos
    // sobre arquivos verificados 10min antes (12:00:45). Com o hash, "commitou depois" só vira
    // achado quando o CONTEÚDO também mudou.
    verified: rows.filter((r) => r.veredito === 'SYNC').map((r) => r.cowork),
    verifiedHash: Object.fromEntries(rows.filter((r) => r.veredito === 'SYNC').map((r) => [r.cowork, r.repoHash])),
  };
  // `stale` conta o que DIVERGE AGORA. Numa rodada cujo snapshot veio de `--export-from`,
  // isso é sempre 0 por construção — o export consertou antes de medir. `stalePreExport`
  // é o número que responde "o espelho drifta com que frequência?", que é o que a ADR 0324
  // precisa do ledger. Sem ele a série histórica só sabe dizer 0 e vira carimbo.
  if (meta.origin) e.origin = meta.origin;
  if (typeof meta.stalePreExport === 'number') e.stalePreExport = meta.stalePreExport;
  return e;
}

/** Veredito de SLA (puro): a rotina rodou há ≤ days? E a última rodada estava limpa?
 *  NEVER-RAN e OVERDUE = vermelho de CADÊNCIA; LAST-STALE = vermelho de RESULTADO
 *  (a última rodada achou divergência e nenhuma rodada posterior limpou);
 *  LAST-PARTIAL = vermelho de COBERTURA (a rodada aconteceu, mas mediu pouco).
 *
 *  ── por que LAST-PARTIAL existe (2026-08-13) ──────────────────────────────────
 *  O veredito olhava só `stale > 0`. Consequência medida no ledger REAL: a rodada
 *  de 2026-07-07 registrou `5 sync · 0 stale · 98 unchecked` — mediu 5 de 103, 95%
 *  cega — e o instrumento a classificava como limpa (só reclamou da IDADE). Ou seja:
 *  o detector de frescor tinha, dentro dele, o "verde por não-execução" que o LC-13
 *  descreve — `0 stale` numa rodada que não rodou não é saúde, é ausência de medição
 *  (mesmo formato de `0 failed` em suíte que não executou).
 *
 *  Rodada PARCIAL é legítima por desenho (o --manifest recomenda "âncoras + deps
 *  globais + css do módulo tocado"). O defeito nunca foi ser parcial — era REPORTAR
 *  parcial como se fosse completa. Por isso o critério é binário e derivado
 *  (`unchecked === 0`), não um limiar inventado: o §5 tem 5 lápides de guard com
 *  corte arbitrário. E a cobertura passa a sair SEMPRE na mensagem. */
/**
 * MEXEU-DEPOIS-DE-VERIFICAR (pura, testável) — a defesa que faltava contra remendo à mão.
 *
 * O `--compare` responde "o espelho bate com o vivo AGORA?" e não roda em CI (auth
 * interativa, ADR 0315). Esta função responde outra pergunta, que o CI PODE responder
 * sozinho porque só precisa de git + ledger: **"que arquivo do espelho foi MODIFICADO
 * depois da última vez que alguém provou que ele batia com o vivo?"**.
 *
 * Por que é o predicado certo: o espelho é build-only. Um arquivo dele só deveria mudar
 * por `--export-from`, que grava conteúdo idêntico ao vivo e é seguido de `--compare
 * --ledger`. Commit que mexe no espelho SEM rodada posterior é, por construção, conteúdo
 * sem prova de fidelidade — foi exatamente o caso do remendo de 2026-08-13 (11 sites
 * trocados à mão, 33min depois de uma rodada, e ninguém soube por 4 dias).
 *
 * NÃO é guard sintático: não olha nome de arquivo, pasta, vocabulário nem mensagem de
 * commit — compara DUAS DATAS. Escapa da família das 4 lápides de §5 (allowlist-de-pasta
 * 06-30 · `@scope` 07-09 · vocabulário 130 FP 07-16 · `toHaveKey` 07-26) porque o critério
 * é determinístico e não tenta ler intenção.
 *
 * FORWARD-ONLY (ADR 0275): `NUNCA-VERIFICADO` é categoria SEPARADA e nunca bloqueia — senão
 * puniria os 100 arquivos de legado que a rotina ainda não cobriu, que é o backfill em massa
 * que o §5 2026-07-12 mata. Só `MEXIDO-DEPOIS` morde, e ele nasce em 0.
 *
 * @param entries  ledger (array de ledgerEntry)
 * @param arquivos [{ cowork, lastCommitIso }] — data do último commit de cada arquivo
 */
export function unverifiedSince(entries, arquivos) {
  const runs = (Array.isArray(entries) ? entries : []).filter((e) => Array.isArray(e.verified));
  const ultimaVerificacaoDe = (cowork) => {
    let melhor = null, hash = null;
    for (const r of runs) if (r.verified.includes(cowork) && (!melhor || r.date > melhor)) {
      melhor = r.date; hash = (r.verifiedHash || {})[cowork] || null;
    }
    return { data: melhor, hash };
  };
  const mexidoDepois = [], nuncaVerificado = [];
  let ok = 0;
  for (const a of arquivos) {
    const { data: verificadoEm, hash: hashVerificado } = ultimaVerificacaoDe(a.cowork);
    if (!verificadoEm) { nuncaVerificado.push(a.cowork); continue; }
    // sem data de commit não se afirma nada (arquivo novo não-commitado): não é achado
    const commitouDepois = a.lastCommitIso && a.lastCommitIso > verificadoEm;
    // CONTEÚDO é o desempate. Sem hash gravado (ledger antigo) cai no comportamento de antes,
    // que é o conservador: data sozinha. Com hash, merge/squash/rebase param de gritar.
    const conteudoMudou = hashVerificado == null || a.hashAtual == null
      ? true
      : a.hashAtual !== hashVerificado;
    if (commitouDepois && conteudoMudou) {
      mexidoDepois.push({ cowork: a.cowork, verificadoEm, commitadoEm: a.lastCommitIso,
        motivo: hashVerificado == null ? 'sem hash no ledger — só data' : 'hash mudou' });
    } else ok++;
  }
  return { mexidoDepois, nuncaVerificado, ok, comLedger: runs.length };
}

export function slaVerdict(entries, nowIso, days = SLA_DAYS) {
  if (!Array.isArray(entries) || entries.length === 0) return { veredito: 'NEVER-RAN', last: null, ageDays: null };
  const last = entries[entries.length - 1];
  const ageDays = Math.floor((Date.parse(nowIso) - Date.parse(last.date)) / 86400000);
  const medidos = (last.files || 0) - (last.unchecked || 0);
  const cobertura = { medidos, total: last.files || 0 };
  if (ageDays > days) return { veredito: 'OVERDUE', last, ageDays, cobertura };
  if (last.stale > 0) return { veredito: 'LAST-STALE', last, ageDays, cobertura };
  if ((last.unchecked || 0) > 0) return { veredito: 'LAST-PARTIAL', last, ageDays, cobertura };
  return { veredito: 'FRESH', last, ageDays, cobertura };
}

/** Extrai as DEPS DE RENDER dos src/href de um shell HTML (pura, testável — LC-07).
 *  Regras: strip query/hash (`app.jsx?v=eb2` → `app.jsx`, o cache-bust do exportador) ·
 *  exclui remotos (http/https — CDN React/Babel não é espelho) · só extensões build
 *  (jsx/css/js) · dedup preservando 1ª ocorrência · paths relativos normalizados. */
export function parseShellDeps(html) {
  const out = [];
  const seen = new Set();
  for (const m of String(html).matchAll(/(?:src|href)="([^"]+)"/g)) {
    let p = m[1].split(/[?#]/)[0].trim();
    if (!p || /^(https?:)?\/\//i.test(p) || p.startsWith('data:')) continue;
    if (!/\.(jsx|css|js)$/i.test(p)) continue;
    p = p.replace(/^\.\//, '').split('\\').join('/');
    if (!seen.has(p)) { seen.add(p); out.push(p); }
  }
  return out;
}

// ── ABSENT-LOCAL: a 3ª doença — o espelho INCOERENTE (2026-08-13) ────────────────
// STALE mede "o hash DIVERGE" (direção não medida — ver 2026-08-17); LIVE-ABSENT mede "sumiu do vivo"; liveOnly mede
// "existe no vivo e nunca desceu". Faltava a que quebra o RENDER: dep que o SHELL
// carrega e que não existe no espelho — o app monta e a tela vem vazia/quebrada, sem
// nenhum veredito vermelho. Foi assim que o `app.jsx` de 07-07 (montando o JanaCockpit
// antigo) conviveu por dias com um `jana-merge.jsx` já versionado: chegou o arquivo,
// não chegou a fiação.
//
// Por que NÃO era detectável: `buildManifest.add()` faz `existsSync → return`, então dep
// ausente é DESCARTADA em silêncio. O comentário de lá dizia "ausência upstream é outro
// sinal" — mas esse outro sinal não existia em lugar nenhum.
//
// FP MEDIDO ANTES de escrever (§5 — 5 lápides de guard sintático mataram o contrário):
//   corpus real 2026-08-13, shell vivo: 16 deps ausentes brutas → 3 são `_ds/**`, que o
//   .gitignore do espelho exclui POR DESIGN (bundle do design-system, não é fonte).
//   Excluindo o que o git ignora: 13 sinal real, 0 falso-positivo.
// Por isso o filtro é `git check-ignore` — a REGRA JÁ ESCRITA do repo, não uma denylist
// de nome inventada aqui (que é a família banida: allowlist-de-pasta · guard `@scope`).
/** Deps que o shell CARREGA e que não existem no espelho (puro exceto o check-ignore).
 *  Devolve { faltando, ignorados } — `ignorados` é reportado, nunca escondido. */
export function absentLocal(shellHtml, root = ROOT) {
  if (!shellHtml) return { faltando: [], ignorados: [] };
  const rel = (d) => `prototipo-ui/cowork/${d}`;
  const ausentes = parseShellDeps(shellHtml).filter((d) => !existsSync(join(root, rel(d))));
  const ignorados = ausentes.filter((d) => {
    try { execFileSync('git', ['check-ignore', '-q', rel(d)], { cwd: root, stdio: 'ignore' }); return true; }
    catch { return false; } // exit≠0 = NÃO ignorado (git check-ignore -q usa o código, não a saída)
  });
  return { faltando: ausentes.filter((d) => !ignorados.includes(d)), ignorados };
}

/** Enumera os arquivos-âncora do espelho, keyed por PATH RELATIVO COMPLETO (nunca basename)
 *  + as DEPS DE RENDER do shell (LC-07). `kind`: 'ancora' (tem tela de charter) | 'dep'.
 *  Mesmo conjunto de âncoras que o anchor-content-check enxerga (reusa anchorRelPath). */
export function buildManifest(root = ROOT, { all = false, shellHtml = null } = {}) {
  const PAGES = join(root, 'resources', 'js', 'Pages');
  const COWORK = join(root, 'prototipo-ui', 'cowork');
  const seen = new Map(); // relPath → { cowork, repoPath, repoHash, telas }

  const add = (relPath, telas) => {
    const abs = join(COWORK, relPath);
    if (!existsSync(abs)) return; // âncora sumida é território do anchor-content (MISSING)
    // `_ds/` é BUILD DE PREVIEW (o --preview-ds cria, o .gitignore exclui). Deixá-lo entrar
    // fazia o denominador do --sla variar por MÁQUINA: 124 aqui, 122 num clone limpo — e o
    // "mediu 2/124" publicado não era propriedade do repo (§5 2026-07-27, denominador que
    // nenhuma decisão estabeleceu). Achado do adversário 2026-08-13, reproduzido: escondi os
    // 2 arquivos e o manifesto caiu 124→122. O universo é o que o GIT versiona.
    if (relPath.startsWith('_ds/')) return;
    if (!seen.has(relPath)) {
      seen.set(relPath, {
        cowork: relPath,
        repoPath: `prototipo-ui/cowork/${relPath}`,
        repoHash: contentHash(readFileSync(abs)),
        telas: [],
      });
    }
    for (const t of telas) if (!seen.get(relPath).telas.includes(t)) seen.get(relPath).telas.push(t);
  };

  if (all) {
    walkRel(COWORK).forEach((rel) => add(rel, []));
  }
  for (const charter of walkCharters(PAGES)) {
    const t = readFileSync(charter, 'utf8');
    const m = t.match(/^related_prototype:\s*(.+)$/m);
    if (!m) continue;
    const rel = anchorRelPath(m[1].trim());
    if (!rel) continue; // prosa não-resolvível — mesmo escopo que o anchor-content pula
    const tela = charter.slice(PAGES.length + 1).split('\\').join('/').replace(/\.charter\.md$/, '');
    add(rel.split('\\').join('/'), [tela]);
  }
  // DEPS DE RENDER (LC-07): derivadas do shell, nunca curadas na mão. Dep que não existe
  // no espelho fica FORA daqui — mas NÃO some mais em silêncio: `absentLocal()` a reporta
  // (era o buraco que deixou a fiação nova conviver com componente ausente, 2026-08-13).
  if (shellHtml) for (const dep of parseShellDeps(shellHtml)) add(dep, []);
  // O SHELL entra no próprio manifesto quando versionado: ele é a fiação, e fiação que não
  // é medida é a doença nº1. Assim, [W] mexer no shell do Cowork vira STALE na próxima rodada.
  add('oimpresso.com.html', []);
  return [...seen.values()]
    .map((e) => ({ ...e, kind: e.telas.length ? 'ancora' : 'dep' }))
    .sort((a, b) => a.cowork.localeCompare(b.cowork));
}

/** Shell default. Ordem: (1) o VERSIONADO no espelho; (2) o staging fixo de handoff.
 *  Null se não houver — o CLI avisa em vez de omitir em silêncio.
 *
 *  Por que o repo vem PRIMEIRO (2026-08-13): o universo de deps de render era derivado de
 *  um arquivo em `~/Downloads` — fora do git, na máquina de UMA pessoa, e congelado na data
 *  do último ZIP extraído. Medido: o staging estava em 01/jul e conhecia 103 deps; o shell
 *  vivo já tinha 120. As 17 deps novas (incl. `jana-merge.*` e as 6 telas de cliente)
 *  eram invisíveis POR CONSTRUÇÃO — o manifesto não podia acusar o que não sabia existir.
 *  Versionar o shell põe a fiação sob a mesma catraca dos arquivos que ela carrega: o shell
 *  entra no manifesto, e mudança dele no vivo vira STALE como qualquer outro arquivo. */
/** CONTEÚDO do shell default (null se não houver). Existe porque `buildManifest`/`absentLocal`
 *  querem o HTML e `defaultShellPath` devolve o CAMINHO — passar um pelo outro não dá erro:
 *  o regex de `parseShellDeps` simplesmente não casa nada e o manifesto sai sem deps, calado.
 *  Mordeu em 2026-08-13 (bite-test do nasce-sem-medição acusou um arquivo que ESTAVA no shell)
 *  e estava latente no `--manifest`, onde o `all:true` mascarava o efeito. */
export function lerShellHtml(root = ROOT) {
  const p = defaultShellPath(root);
  return p && existsSync(p) ? readFileSync(p, 'utf8') : null;
}

export function defaultShellPath(root = ROOT) {
  const noRepo = join(root, 'prototipo-ui', 'cowork', 'oimpresso.com.html');
  if (existsSync(noRepo)) return noRepo;
  // Sem fallback pro `~/Downloads/_cowork-handoff-staging` ([W] 2026-08-13: "não existe mais
  // zip, é direto o protocolo"). O staging era o caminho do BUNDLE, e ele morreu duas vezes:
  // a catraca `ancora-guard` já lista `_cowork-handoff-staging` e `Downloads/` como LUGAR
  // PROIBIDO ([W] 2026-07-01 "não pode trocar de lugar nunca. deve ser isso que fica errando"),
  // e a rota canônica hoje é `get_file` → `--export-from` (ADR 0374, ratificada 2026-08-13).
  // Ler o shell de lá reintroduzia exatamente a doença que motivou versioná-lo: o staging
  // estava congelado em 01/jul e conhecia 103 deps quando o vivo já tinha 120.
  // Sem shell no espelho, o certo é dizer que não há — não achar um velho fora do git.
  return null;
}

function walkCharters(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir)) {
    const f = join(dir, e);
    if (statSync(f).isDirectory()) walkCharters(f, acc);
    else if (f.endsWith('.charter.md')) acc.push(f);
  }
  return acc;
}

/** Anda o espelho devolvendo PATHS RELATIVOS (v1 devolvia basenames — arquivos homônimos em
 *  subdirs sumiam do --all; v2 preserva a identidade). */
function walkRel(base, dir = base, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir)) {
    const f = join(dir, e);
    if (statSync(f).isDirectory()) walkRel(base, f, acc);
    // css|js incluídos em 2026-07-07 (LC-07): o --all era cego pra folha de estilo — o
    // vetor exato do drift de design (tokens/accent vivem em .css, runtime em app.jsx).
    else if (/\.(jsx|html|css|js)$/i.test(e)) acc.push(f.slice(base.length + 1).split('\\').join('/'));
  }
  return acc;
}

// ── NASCE-SEM-MEDIÇÃO: o arquivo entra no espelho e ninguém o mede (2026-08-13) ──
// [W]: "garanta todos os novos sempre checados". O universo da rodada normal deriva do
// que o SHELL carrega — então arquivo que desce e o shell não referencia nasce FORA de
// qualquer `--compare`. Aconteceu nesta própria sessão: `forja-tarefas.jsx` foi exportado
// do vivo e ficou invisível pro frescor.
//
// FP MEDIDO ANTES (60 dias de histórico real, 251 arquivos adicionados):
//   todos os novos ............ 129/251 fora = 51%  ← quase tudo relatório .html: RUÍDO
//   só jsx/css/js ............. 72/192 fora = 38%  ← subdir com shell próprio: RUÍDO
//   + só na RAIZ .............. 20/137 fora = 15%
//   + AINDA EXISTE NO VIVO ....  2/137 fora ≈ 1%   ← SINAL
// Os 3 filtros são por DADO, nunca denylist de nome (§5 tem 5 lápides de guard sintático).
// ⚠️ As razões abaixo foram REESCRITAS em 2026-08-13: as duas primeiras que eu tinha
// escrito eram plausíveis e FALSAS — inventei o motivo em vez de medir (§5 2026-07-15).
//   · RAIZ       — porque o PRODUTOR escreve na raiz. Medido no histórico (clone completo):
//                  os 2 commits de `--export-from` puseram 17 arquivos, **17 na raiz, 0 em
//                  subdir**; todo subdir veio de IMPORT DE BUNDLE (#3259 SSOT 224 arq ·
//                  #5560 preview v3 21 arq), que é outro fluxo, com outro dono e outro
//                  momento. Um arquivo que nasce em subdir não nasceu deste produtor.
//                  ✗ A razão anterior — "subdir tem shell próprio por desenho" — é FALSA:
//                  medido, 19 dos 23 subdirs não têm nenhum .html.
//   · jsx/css/js — porque o FP mandou (129/251 = 51% de ruído). NÃO porque ".html é
//                  relatório": medido, **5 dos 11 .html do espelho RENDERIZAM**
//                  (`createRoot|ReactDOM|text/babel`), incluindo o `Financeiro - Prova Viva
//                  (primitivos).html`. RESIDUAL DECLARADO, não propriedade: protótipo
//                  publicado como .html nasce fora deste detector e ninguém acusa.
//   · NO VIVO    — 18 dos 20 eram resíduo do protótipo `norte-*`, arquivado upstream;
//                  quem sumiu do vivo não é "novo sem medição", é sobra a limpar.
// Diff-aware e forward-only (ADR 0275): cobre o que NASCE, deixa o legado grandfathered.
/** Arquivos que ENTRARAM no espelho e ficaram fora do manifesto (puro, testável).
 *  `adicionados`: paths relativos ao espelho, vindos do diff.
 *  `manifest`: saída de buildManifest. `vivos`: paths do DesignSync.list_files (opcional —
 *  sem ele o filtro mais forte não roda e o resultado sai marcado como `semVivo`). */
export function nasceSemMedicao(adicionados, manifest, vivos = null) {
  const medidos = new Set(manifest.map((f) => f.cowork));
  const noVivo = vivos ? new Set(vivos) : null;
  const base = (adicionados || [])
    .map((p) => p.replace(/^prototipo-ui\/cowork\//, ''))
    .filter((p) => !p.includes('/'))                   // raiz: subdir tem shell próprio
    .filter((p) => /\.(jsx|css|js)$/i.test(p))         // .html no espelho é relatório
    .filter((p) => !medidos.has(p));
  if (!noVivo) return { semVivo: true, acusados: base, residuo: [] };
  return {
    semVivo: false,
    acusados: base.filter((p) => noVivo.has(p)),       // existe no vivo → é protótipo real sem medição
    residuo: base.filter((p) => !noVivo.has(p)),       // sumiu do vivo → sobra, não é este alarme
  };
}

// ── PREVIEW-DS: o espelho é versionado, mas NÃO RENDERIZA sozinho (2026-08-13) ────
// O shell do Cowork faz <link> de `_ds/<id>/colors_and_type.css` + `cockpit_domains.css`
// e <script> do `_ds_bundle.js`. O `.gitignore` do espelho exclui `_ds/` — correto, porque
// o Design System tem dono próprio em git (ADR 0239) e duplicá-lo criaria 2º armazém.
// CONSEQUÊNCIA MEDIDA: quem clona o repo e abre o protótipo vê `--pos`/`--neg`/`--warn`
// VAZIOS — a tela renderiza sem as cores de status. Isso contradiz o motivo da ADR 0374
// ("vai ter computadores que não vão ter acesso ao design dessa máquina e vão trabalhar
// só com o git"): o time recebe o protótipo, mas não o vê como ele é.
//
// O conteúdo JÁ ESTÁ versionado em `scripts/design-sync/mirror-snapshot/` (dono:
// ds-mirror-build.mjs + gate ds-mirror-drift) — só não no path que o shell procura.
// Este modo REPÕE, derivando o id do DS do PRÓPRIO SHELL (nunca hardcode: o shell é
// versionado, então o id acompanha quando [W] trocar de design system).
// O `_ds/` continua gitignored: isto é build de preview, não versionamento.
//
// Não é detector — é ação determinística (copiar), então não tem FP a medir.
export function previewDsPlan(shellHtml, root = ROOT) {
  if (!shellHtml) return { erro: 'sem shell — não dá pra derivar o id do design system', arquivos: [] };
  // o id sai dos próprios <link>/<script> do shell
  const ids = [...new Set([...String(shellHtml).matchAll(/_ds\/([^/"]+)\//g)].map((m) => m[1]))];
  if (ids.length !== 1) return { erro: `esperava 1 design system no shell, achei ${ids.length}`, arquivos: [] };
  const id = ids[0];
  const querUsar = [...new Set([...String(shellHtml).matchAll(/_ds\/[^/"]+\/([^"?]+)/g)].map((m) => m[1]))];
  const origem = join(root, 'scripts', 'design-sync', 'mirror-snapshot');
  // ── 2ª CAMADA: o que os CSS repostos pedem POR DENTRO (2026-08-14) ───────────
  // Derivar o plano só do SHELL deixa de fora tudo que está a uma indireção: o
  // shell não menciona fonte alguma — quem as pede é o `colors_and_type.css`, com
  // `url('assets/fonts/…woff2')` (7 refs únicas). MEDIDO no preview antes deste
  // conserto: 7 `@font-face` com status `error` e a tipografia caindo pro fallback
  // do sistema — falha 404 SILENCIOSA, que é pior que ausência declarada, porque
  // o `--preview-ds` dizia "2 reposto(s)" e dava a impressão de plano completo.
  // Só refs RELATIVAS entram (data:/http(s):/protocol-relative/âncora ficam fora —
  // não são arquivo do espelho). Isto não inventa origem: o que não existir no
  // mirror-snapshot sai como "SEM FONTE", igual ao `_ds_bundle.js`.
  const deCss = new Set();
  for (const f of querUsar) {
    if (!/\.css$/i.test(f)) continue;
    const src = join(origem, f);
    if (!existsSync(src)) continue;
    for (const m of String(readFileSync(src, 'utf8')).matchAll(/url\(\s*['"]?([^)'"]+?)['"]?\s*\)/g)) {
      const ref = String(m[1]).trim();
      if (!ref || /^(data:|https?:|\/\/|#)/i.test(ref)) continue;
      deCss.add(ref.replace(/^\.\//, '').split(/[?#]/)[0]);
    }
  }
  const todos = [...new Set([...querUsar, ...deCss])];
  return {
    id,
    destino: `prototipo-ui/cowork/_ds/${id}`,
    arquivos: todos.map((f) => ({
      nome: f,
      de: join(origem, f),
      para: join(root, 'prototipo-ui', 'cowork', '_ds', id, f),
      temNoRepo: existsSync(join(origem, f)),
    })),
  };
}

/** Imprime o ABSENT-LOCAL. Não afeta o exit code: `shouldFail()` morde SÓ em STALE, que é o
 *  sinal hash-provado — isso é comportamento DESTE script, provado no self-test, não promessa.
 *  Se esta lane bloqueia merge ou não, quem sabe é `governance/required-checks-baseline.json`
 *  (dono único, vigiado por `protection-drift.mjs`): script não afirma o próprio enforcement
 *  em presente (LC-10). Desenho de nascimento em ADR 0275; promoção por mordida em ADR 0336.
 *
 *  `stream`: no --manifest o STDOUT é o JSON do manifesto — relatório humano ali dentro
 *  quebra `--manifest | jq` / `require()` (peguei isso na 1ª vez que consumi por pipe).
 *  Regra: modo que emite dado → relatório vai pro stderr; modo que emite relatório → stdout. */
function reportAbsentLocal(shellHtml, stream = process.stdout) {
  if (!shellHtml) return;
  const { faltando, ignorados } = absentLocal(shellHtml);
  if (!faltando.length && !ignorados.length) return;
  const w = (s) => stream.write(s + '\n');
  w(`  ── ABSENT-LOCAL — o shell CARREGA e o espelho NÃO TEM (render quebra sem veredito)\n`);
  for (const d of faltando) w(`  ⛔ FALTA        ${d}`);
  if (ignorados.length) {
    w(`\n  (${ignorados.length} fora por .gitignore do espelho — esperado, não é achado: ${ignorados.map((d) => d.split('/')[0]).filter((v, i, a) => a.indexOf(v) === i).join(', ')})`);
  }
  w(
    `\n  ⛔ ausentes: ${faltando.length} · ⬜ ignorados por design: ${ignorados.length}\n` +
    (faltando.length
      ? `  Pra versionar: DesignSync.get_file de cada → salve os JSON num dir → --export-from <dir>.\n  (não muda o exit code: o --check morde só em STALE)\n`
      : `  ✓ toda dep do shell existe no espelho.\n`),
  );
}

// ── INTEGRIDADE REFERENCIAL NA PODA (--check-refs) ────────────────────────────
// O QUE DEFENDE: podar o espelho é operação legítima e recorrente (4 commits na história
// removeram 296 arquivos). O risco não é podar — é podar um arquivo que OUTRO arquivo do
// espelho ainda carrega, e o protótipo parar de renderizar sem ninguém notar. O ABSENT-LOCAL
// (acima) já cobre as deps do SHELL; este cobre o resto do grafo: qualquer arquivo do espelho
// apontando pra qualquer outro.
//
// POR QUE ESTE PREDICADO E NÃO "toda referência resolve": medido 2026-08-13 no espelho real,
// 30 das 171 referências não resolvem POR DESENHO — alias de bundler (`@/Layouts/…`), pacote
// npm (`@inertiajs/react`), rota de API (`/api/…`) e o `_ds/` que é gitignored. Gatear nisso
// seria 30 falso-positivos no dia 1, a doença das 4 lápides de guard sintático do §5. O
// predicado que importa é diff-aware: "sobrou alguém apontando pro que ESTE diff apagou?".
//
// FP MEDIDO ANTES DE INSTALAR (§5 "ligar ≠ criar"): rodado contra os 4 commits da história que
// deletaram do espelho (152 · 42 · 6 · 96 arquivos) → 0 disparo. Não reprova poda legítima.
// MORDIDA PROVADA: removendo `app.jsx` e `styles.css` (deps reais do shell) o predicado acusa
// as 2, nominalmente. Fixture boa/ruim em tests/governance-fixtures/cowork-refs/, wirada no
// gate-selftest — o par existe porque "0 disparos em 4 commits" também é o perfil de um
// detector cego, e sem controle positivo não dá pra distinguir os dois.
//
// LIMITE HONESTO (§5 2026-08-10, catraca que itera o lado mutável): se o arquivo QUE REFERENCIA
// também é apagado no mesmo diff, o par some junto e isto passa em silêncio — corretamente,
// mas significa que apagar o espelho inteiro passa vazio. Quem cobre esse flanco é o
// ABSENT-LOCAL, que deriva o universo do shell, não do diff.
const REF_PAT = {
  html: [/<script[^>]+src=["']([^"']+)["']/gi, /<link[^>]+href=["']([^"']+)["']/gi,
         /<img[^>]+src=["']([^"']+)["']/gi, /<iframe[^>]+src=["']([^"']+)["']/gi],
  js: [/\bimport\s+[^"'()]*?from\s*["']([^"']+)["']/g, /\bimport\s*\(\s*["']([^"']+)["']/g,
       /\brequire\s*\(\s*["']([^"']+)["']/g],
  css: [/@import\s+(?:url\()?["']?([^"')\n]+)["']?\)?/g, /url\(\s*["']?([^"')\n]+)["']?\s*\)/g],
};
const refKind = (f) => (/\.html?$/i.test(f) ? 'html' : /\.css$/i.test(f) ? 'css'
  : /\.(m?jsx?|tsx?)$/i.test(f) ? 'js' : null);
// Externo = protocolo/âncora. Alias = `@…` (alias de bundler ou escopo npm) ou token sem
// barra e sem extensão (pacote). Nenhum dos dois é caminho de arquivo — ignorar não é
// leniência, é não confundir universos.
const refExterno = (r) => /^(https?:|data:|blob:|mailto:|#|\/\/)/i.test(r) || !r.trim();
const refAlias = (r) => r.startsWith('@') || /^[a-z0-9_-]+$/i.test(r);

/** Referências do espelho que apontam pra path DELETADO (pura, testável).
 *  `vivos`: [{path, texto}] dos arquivos do espelho que SOBRARAM (path relativo à raiz do repo).
 *  `deletados`: Set de paths (relativos à raiz) removidos pelo diff.
 *  Devolve [{de, ref, alvo}] — vazio = a poda não quebrou nada. */
export function refsParaDeletado(vivos, deletados) {
  const out = [];
  for (const { path: f, texto } of vivos) {
    const kind = refKind(f);
    if (!kind || !texto) continue;
    const dir = posixDirname(f);
    for (const re of REF_PAT[kind]) {
      re.lastIndex = 0;
      let m;
      while ((m = re.exec(texto))) {
        const ref = m[1];
        if (refExterno(ref) || refAlias(ref)) continue;
        const limpo = ref.split('?')[0].split('#')[0];
        const alvo = limpo.startsWith('/')
          ? posixJoin(MIRROR_REL, limpo.slice(1))
          : posixJoin(dir, limpo);
        // extensão implícita: `import './x'` resolve `x.jsx`
        for (const e of ['', '.jsx', '.js', '.mjs', '.css', '.html']) {
          if (deletados.has(alvo + e)) { out.push({ de: f, ref, alvo: alvo + e }); break; }
        }
      }
    }
  }
  return out;
}

// posix path sem depender de node:path (o resto do arquivo também é self-contained)
function posixDirname(p) { const i = p.lastIndexOf('/'); return i === -1 ? '.' : p.slice(0, i); }
function posixJoin(base, rel) {
  const partes = `${base}/${rel}`.split('/');
  const pilha = [];
  for (const seg of partes) {
    if (!seg || seg === '.') continue;
    if (seg === '..') pilha.pop(); else pilha.push(seg);
  }
  return pilha.join('/');
}
export const MIRROR_REL = 'prototipo-ui/cowork';

// ── CLI ───────────────────────────────────────────────────────────────────────
function main() {
  const argv = process.argv.slice(2);
  const all = argv.includes('--all');
  const cmpIdx = argv.indexOf('--compare');

  // Teste deste script vive no IRMÃO `cowork-mirror-freshness.test.mjs` (wirado em
  // design-memory-gate.yml:239) — que já cobria 9 funções puras. Em 2026-08-11 eu cheguei a
  // escrever um `--selftest` embutido aqui e o REMOVI: seria um 2º dono do mesmo teste, que é
  // o anti-padrão que este repo cataloga (§5 "duplica régua consolidada" · LC-19). As funções
  // novas (liveOnly · exportPlan) foram para o irmão.

  // --absent-local: "o shell CARREGA e o espelho NÃO TEM?" — o 4º flanco do cabeçalho, que até
  // aqui era MEDIDO e INCAPAZ DE REPROVAR: `reportAbsentLocal` só imprime, e o exit code do
  // --manifest vem do `shouldFail()`, que morde SÓ em STALE. Gate mudo com cara de cobertura.
  //
  // 100% LOCAL — lê o shell do próprio espelho, zero DesignSync — e por isso É gateável, ao
  // contrário do --compare/--live-only, que precisam da auth interativa (ADR 0315). A ressalva
  // do cabeçalho ("rotina de dispatch logado, não gate") vale pro flanco que fala com o VIVO;
  // este não fala. Responde literalmente "pegou todos os componentes, subpáginas, CSS e JS?"
  // ([W] 2026-08-14). NÃO substitui o --live-only: aquele vê o que NUNCA desceu; este vê o que
  // o shell precisa e não está — universos diferentes (LIVE-ONLY, §5 2026-08-11).
  if (argv.includes('--absent-local')) {
    const shellHtml = lerShellHtml();
    if (!shellHtml) {
      // FAIL-CLOSED: "não consegui medir" NUNCA vira verde (§5 2026-07-29 — instrumento não
      // afirma saúde do que não percorreu). Sem shell não há universo, logo não há veredito.
      console.error('✗ --absent-local: shell do espelho não encontrado — sem universo, sem veredito.');
      process.exit(2);
    }
    const { faltando, ignorados } = absentLocal(shellHtml);
    reportAbsentLocal(shellHtml, process.stdout);
    console.log(`\n  ABSENT-LOCAL — ⛔ ausentes: ${faltando.length} · ⬜ ignorados por design: ${ignorados.length}`);
    if (faltando.length) {
      console.error(`✗ espelho INCOMPLETO — ${faltando.length} dep(s) que o shell carrega não existe(m) no espelho.`);
      process.exit(1);
    }
    console.log('  ✓ toda dep do shell existe no espelho.');
    process.exit(0);
  }

  // --check-refs: a poda quebrou o grafo interno do espelho? Diff-aware, 100% LOCAL — por
  // isso ISTO é gateável e o --compare não é (aquele precisa da auth interativa do DesignSync).
  // Universo dos deletados: `git diff --diff-filter=D <range>` (default origin/main...HEAD) ou
  // --deleted-from <arquivo> (uma linha por path) pra fixture rodar sem git.
  if (argv.includes('--check-refs')) {
    const delIdx = argv.indexOf('--deleted-from');
    let deletados;
    if (delIdx !== -1) {
      const dp = argv[delIdx + 1];
      if (!dp || !existsSync(dp)) {
        console.error('✗ --deleted-from exige um arquivo com um path por linha.');
        process.exit(2);
      }
      deletados = new Set(readFileSync(dp, 'utf8').split('\n').map((s) => s.trim()).filter(Boolean));
    } else {
      const rIdx = argv.indexOf('--range');
      const range = rIdx !== -1 && argv[rIdx + 1] ? argv[rIdx + 1] : 'origin/main...HEAD';
      // O range é UM token (`a..b`/`a...b`). `--range HEAD~1 HEAD` pareceria funcionar e o git
      // compararia `HEAD~1` contra a WORKING TREE — universo diferente, veredito diferente, sem
      // aviso. Medir a coisa errada calado é pior que não medir (§5 2026-08-13).
      if (!range.includes('..')) {
        console.error(`✗ --check-refs: --range espera UM token de range ("a..b" ou "a...b"), recebi "${range}".`);
        console.error('  Passar duas revisões separadas faz o git diffar contra a working tree.');
        process.exit(2);
      }
      let saida;
      try {
        saida = execFileSync('git', ['diff', '--name-only', '--diff-filter=D', range, '--', `${MIRROR_REL}/`],
          { encoding: 'utf8', maxBuffer: 1 << 28 });
      } catch (e) {
        // Vazio de comando que FALHOU não é "nada deletado" (§5 2026-07-31 · 2026-08-11).
        console.error(`✗ --check-refs: git diff falhou no range "${range}" — sem universo, sem veredito.`);
        console.error(`  ${String(e.message || e).split('\n')[0]}`);
        process.exit(2);
      }
      deletados = new Set(saida.split('\n').map((s) => s.trim()).filter(Boolean));
    }

    const raizEspelho = join(ROOT, MIRROR_REL);
    const vivos = [];
    if (existsSync(raizEspelho)) {
      (function walk(rel) {
        for (const e of readdirSync(join(ROOT, rel), { withFileTypes: true })) {
          const p = `${rel}/${e.name}`;
          if (e.isDirectory()) walk(p);
          else if (refKind(p)) { try { vivos.push({ path: p, texto: readFileSync(join(ROOT, p), 'utf8') }); } catch { /* binário */ } }
        }
      })(MIRROR_REL);
    }

    const quebras = refsParaDeletado(vivos, deletados);
    console.log(`  espelho: ${vivos.length} arquivo(s) textual(is) · deletados no diff: ${deletados.size}`);
    if (!deletados.size) {
      console.log('  ✓ nenhuma deleção no espelho neste diff — nada a verificar.');
      process.exit(0);
    }
    if (!quebras.length) {
      console.log(`  ✓ integridade referencial preservada: nenhum arquivo do espelho aponta pros ${deletados.size} deletado(s).`);
      process.exit(0);
    }
    console.error(`\n  ⛔ ${quebras.length} referência(s) apontam pra arquivo que este diff APAGA:`);
    for (const q of quebras) console.error(`     ${q.de}\n        -> "${q.ref}"  = ${q.alvo}`);
    console.error('\n  A poda quebrou o render do protótipo. Restaure o arquivo OU remova a referência no mesmo PR.');
    process.exit(1);
  }

  // --lista-download <list_files.json>: A LISTA, derivada — nunca um .md commitado.
  //
  // Responde as DUAS perguntas com o MESMO insumo (1 chamada de `list_files`, sem conteúdo,
  // barata — ao contrário do `--compare`, que precisa de N `get_file`):
  //   1. "o que falta baixar?"        → manifesto − já verificados − o que não existe no vivo
  //   2. "apareceu arquivo novo?"     → `liveOnly()` (reusa o dono, não reimplementa)
  //
  // POR QUE É COMANDO E NÃO DOCUMENTO: uma lista de 121 arquivos com hash e status apodrece no
  // próximo export — é escrito+lembrado (ADR 0256). O `.md` que eu gerei à mão em 2026-08-17
  // listava 3 arquivos que dão HTTP 404, porque foi derivado do ESPELHO sem cruzar com o dono
  // do inventário do VIVO. Aqui o cruzamento é obrigatório: sem o `list_files` o modo recusa.
  const ldIdx = argv.indexOf('--lista-download');
  if (ldIdx !== -1) {
    const lp = argv[ldIdx + 1];
    if (!lp || !existsSync(lp)) {
      console.error('✗ --lista-download exige o JSON do DesignSync.list_files (é ele que diz o que existe no vivo).');
      console.error('  Sem ele a lista mente: em 2026-08-17 ela recomendou 3 arquivos que voltaram 404.');
      process.exit(2);
    }
    const raw = JSON.parse(readFileSync(lp, 'utf8'));
    const vivos = new Set(Array.isArray(raw) ? raw : (raw.paths || []));
    const manifest = buildManifest(ROOT, { shellHtml: lerShellHtml() });
    const ledgerRaw = existsSync(join(ROOT, LEDGER_REL)) ? JSON.parse(readFileSync(join(ROOT, LEDGER_REL), 'utf8')) : [];
    const runs = Array.isArray(ledgerRaw) ? ledgerRaw : (ledgerRaw.runs || []);
    const verificados = new Set();
    for (const r of runs) (r.verified || []).forEach((v) => verificados.add(v));

    const linhas = manifest.map((f) => {
      const abs = join(ROOT, f.repoPath);
      const bytes = existsSync(abs) ? statSync(abs).size : 0;
      const estado = !vivos.has(f.cowork) ? 'SO-NO-ESPELHO'
        : verificados.has(f.cowork) ? 'VERIFICADO' : 'A-BAIXAR';
      return { ...f, bytes, estado };
    }).sort((a, b) => b.bytes - a.bytes);

    const g = (e) => linhas.filter((l) => l.estado === e);
    // ⚠️ liveOnly PRECISA do manifesto COMPLETO (all:true) — o do shell tem só as âncoras+deps,
    // e usá-lo aqui acusaria como "novo no vivo" todo arquivo do espelho fora do shell.
    // Medido: com o manifesto do shell dava dezenas de FP (`prototipo-ui-patch/**`); com o
    // completo dá 10, o mesmo número do `--live-only`, que é o dono desta pergunta.
    const novos = liveOnly([...vivos], buildManifest(ROOT, { all: true, shellHtml: lerShellHtml() }));
    const telaNova = novos.filter((p) => /-page\.(jsx|css)$/.test(p) || /^[^/]+-(page|merge)\.jsx$/.test(p));

    console.log(`\n  LISTA DE DOWNLOAD — manifesto do shell × vivo × ledger\n`);
    console.log(`  ${'ARQUIVO'.padEnd(52)} ${'BYTES'.padStart(8)}  HASH(espelho)`);
    for (const l of g('A-BAIXAR')) console.log(`  ${l.cowork.padEnd(52)} ${String(l.bytes).padStart(8)}  ${l.repoHash.slice(0, 16)}`);
    console.log(`\n  ⬇ A BAIXAR: ${g('A-BAIXAR').length}  ·  ✓ verificados: ${g('VERIFICADO').length}  ·  ❌ só-no-espelho (baixar dá 404): ${g('SO-NO-ESPELHO').length}`);
    for (const l of g('SO-NO-ESPELHO')) console.log(`     ❌ ${l.cowork}  — origem externa ou poda; ver FORA_DESTA_CONTA em protocolo.config.mjs`);

    console.log(`\n  🆕 NOVO NO VIVO (nunca desceu): ${novos.length}${telaNova.length ? ` — dos quais ${telaNova.length} parecem PROTÓTIPO DE TELA` : ''}`);
    for (const p of novos) console.log(`     ${telaNova.includes(p) ? '🔴' : '·'} ${p}`);
    if (!novos.length) console.log('     (nenhum — o espelho cobre tudo que o vivo tem)');

    if (argv.includes('--json')) {
      console.log('\n' + JSON.stringify({ aBaixar: g('A-BAIXAR'), verificados: g('VERIFICADO'), soNoEspelho: g('SO-NO-ESPELHO'), novoNoVivo: novos, telaNova }, null, 2));
    }
    // Morde só no caso que ESCONDEU o jana-merge.jsx: protótipo de tela novo no vivo,
    // invisível pro espelho. O resto é lista pro humano — decidir o que versionar é do [W].
    if (argv.includes('--check') && telaNova.length) { console.log('\n  ⛔ protótipo de tela novo no vivo e ausente do espelho — foi assim que o jana-merge.jsx ficou 3 meses invisível.'); process.exit(1); }
    console.log('');
    return;
  }

  // --live-only <lista.json>: o LADO CEGO. Recebe a saída do DesignSync.list_files
  // ({paths:[…]} ou array cru) e mostra o que existe no VIVO e nunca desceu pro espelho.
  // Advisory por desenho: decidir o que merece versionar é do [W]; a máquina só deixa de
  // esconder. Medido 2026-08-11 no corpus real: 25 de 1310 paths, 14 deles protótipo de tela.
  const loIdx = argv.indexOf('--live-only');
  if (loIdx !== -1) {
    const lp = argv[loIdx + 1];
    if (!lp || !existsSync(lp)) {
      console.error('✗ --live-only exige o JSON do DesignSync.list_files.');
      process.exit(2);
    }
    const raw = JSON.parse(readFileSync(lp, 'utf8'));
    const paths = Array.isArray(raw) ? raw : (raw.paths || []);
    const manifest = buildManifest(ROOT, { all: true, shellHtml: lerShellHtml() });
    const faltando = liveOnly(paths, manifest);
    // Classifica pra o humano decidir sem ler 25 linhas iguais. NÃO é filtro — tudo é
    // listado; filtro escondido aqui recriaria o ponto cego que este modo existe pra abrir.
    const ehTela = (p) => !p.includes('/') && /\.(jsx|css)$/.test(p);
    const telas = faltando.filter(ehTela);
    const outros = faltando.filter((p) => !ehTela(p));
    console.log(`\n  LIVE-ONLY — existe no Cowork vivo e NÃO está no espelho (${faltando.length} de ${paths.length} paths)\n`);
    console.log(`  ── protótipo de tela (${telas.length}) — candidatos reais a versionar:`);
    for (const p of telas) console.log(`     + ${p}`);
    console.log(`\n  ── outros (${outros.length}) — shell, uploads, bundle de DS, docs:`);
    for (const p of outros) console.log(`     · ${p}`);
    console.log('\n  Para versionar: DesignSync.get_file de cada → salve os JSON num dir → --export-from <dir>.');
    console.log('  ⚠️ lista, não veredito: o que merece descer é decisão [W], não da máquina.\n');
    return;
  }

  // --check-novos <base> [--vivos <list.json>]: arquivo que ENTROU no espelho e nasceu
  // fora de qualquer rodada de frescor. Diff-aware/forward-only (ADR 0275).
  const novIdx = argv.indexOf('--check-novos');
  if (novIdx !== -1) {
    const base = argv[novIdx + 1] || 'origin/main';
    let adicionados = [];
    try {
      const out = execFileSync('git', ['diff', '--diff-filter=A', '--name-only', `${base}...HEAD`, '--', 'prototipo-ui/cowork'], { cwd: ROOT, encoding: 'utf8' });
      adicionados = out.split('\n').map((s) => s.trim()).filter(Boolean);
    } catch (e) {
      // ⚠️ vazio de comando que FALHOU não é "nenhum arquivo novo" (§5 2026-07-31/08-01)
      console.error(`✗ git diff falhou contra "${base}" — sem base não dá pra saber o que é novo. ${e.message.split('\n')[0]}`);
      process.exit(2);
    }
    const vIdx = argv.indexOf('--vivos');
    let vivos = null;
    if (vIdx !== -1 && existsSync(argv[vIdx + 1])) {
      const raw = JSON.parse(readFileSync(argv[vIdx + 1], 'utf8'));
      vivos = Array.isArray(raw) ? raw : (raw.paths || []);
    }
    const shellIdx1 = argv.indexOf('--shell');
    const sp1 = shellIdx1 !== -1 ? argv[shellIdx1 + 1] : defaultShellPath();
    const man = buildManifest(ROOT, { shellHtml: sp1 && existsSync(sp1) ? readFileSync(sp1, 'utf8') : null });
    const r = nasceSemMedicao(adicionados, man, vivos);
    console.log(`\n  NASCE-SEM-MEDIÇÃO — entrou no espelho e ficou fora do frescor (base: ${base})\n`);
    console.log(`  arquivos novos no diff: ${adicionados.length}`);
    if (r.semVivo) {
      console.log(`  ⚠ sem --vivos: o filtro mais forte (existe no vivo?) NÃO rodou — FP medido sobe de ~1% pra ~15%.`);
    } else if (r.residuo.length) {
      console.log(`  (${r.residuo.length} sumiram do vivo — resíduo a limpar, não é este alarme)`);
    }
    if (!r.acusados.length) { console.log(`\n  ✓ todo arquivo novo do espelho está sob medição.\n`); return; }
    console.log('');
    for (const f of r.acusados) console.log(`  ⛔ SEM MEDIÇÃO  ${f}  (o shell não carrega → nenhum --compare olha)`);
    console.log(
      `\n  ${r.acusados.length} arquivo(s) nasceram fora do frescor.\n` +
      `  Pra medir: o shell precisa carregá-lo, OU rode a rodada com --manifest --all.\n` +
      `  (não muda o exit code: o --check morde só em STALE)\n`,
    );
    return;
  }

  // --unverified: quem MEXEU no espelho depois da última prova de fidelidade.
  // Só git + ledger — roda no CI headless, ao contrário do --compare (auth interativa).
  if (argv.includes('--unverified')) {
    // Lápide §5 2026-07-24: data vinda de `git log` num clone RASO mede o PISO, não a história.
    let raso = false;
    try { raso = execFileSync('git', ['rev-parse', '--is-shallow-repository']).toString().trim() === 'true'; } catch { /* sem git */ }

    const versionados = execFileSync('git', ['ls-files', MIRROR_REL]).toString().split('\n').filter(Boolean);
    // UMA passada de log pra todo o diretório: primeira data que aparece por path = a mais recente.
    const bruto = execFileSync('git', ['log', '--format=@%cI', '--name-only', '--', MIRROR_REL]).toString().split('\n');
    const dataDe = new Map();
    let atual = null;
    for (const ln of bruto) {
      if (ln.startsWith('@')) { atual = ln.slice(1); continue; }
      if (ln && atual && !dataDe.has(ln)) dataDe.set(ln, atual);
    }
    const arquivos = versionados.map((p) => ({
      cowork: p.replace(`${MIRROR_REL}/`, ''),
      lastCommitIso: dataDe.get(p) || null,
      hashAtual: existsSync(join(ROOT, p)) ? contentHash(readFileSync(join(ROOT, p), 'utf8')) : null,
    }));

    const ledger = existsSync(join(ROOT, LEDGER_REL)) ? JSON.parse(readFileSync(join(ROOT, LEDGER_REL), 'utf8')) : [];
    const r = unverifiedSince(Array.isArray(ledger) ? ledger : (ledger.runs || []), arquivos);

    console.log(`\n  MEXEU-DEPOIS-DE-VERIFICAR — espelho vs ledger (${arquivos.length} arquivo(s) versionado(s))\n`);
    if (raso) console.log('  ⚠ clone RASO — as datas de commit medem o piso da história, não a história (§5 2026-07-24).\n');
    if (!r.comLedger) {
      console.log('  ⬜ nenhuma rodada do ledger registra QUAIS arquivos mediu (campo `verified` nasceu em 2026-08-17).');
      console.log('     Este modo só tem sinal a partir da próxima `--compare --ledger`. Não é verde: é SEM DADO.\n');
      process.exit(0);
    }
    for (const m of r.mexidoDepois) console.log(`  🔴 MEXIDO-DEPOIS  ${m.cowork}\n       verificado ${m.verificadoEm} · commitado ${m.commitadoEm}`);
    console.log(`\n  🔴 mexido-depois: ${r.mexidoDepois.length} · ✓ intactos desde a verificação: ${r.ok} · ⬜ nunca verificados: ${r.nuncaVerificado.length}`);
    if (r.nuncaVerificado.length) console.log('     (nunca-verificado NÃO bloqueia — forward-only, ADR 0275: puni-lo seria backfill em massa do legado)');
    if (r.mexidoDepois.length) console.log('\n  Conteúdo do espelho mudou sem prova de que bate com o vivo. Rode o ciclo e re-verifique,\n  ou reverta o commit se foi remendo à mão (o espelho é build-only — não tem autor local legítimo).');
    if (argv.includes('--check') && r.mexidoDepois.length) process.exit(1);
    console.log('');
    return;
  }

  // --preview-ds: repõe o `_ds/` do espelho a partir do mirror-snapshot versionado.
  // Sem isso o protótipo abre COM os tokens de status vazios (medido 2026-08-13).
  if (argv.includes('--preview-ds')) {
    const shellIdx0 = argv.indexOf('--shell');
    const sp = shellIdx0 !== -1 ? argv[shellIdx0 + 1] : defaultShellPath();
    const html = sp && existsSync(sp) ? readFileSync(sp, 'utf8') : null;
    const plano = previewDsPlan(html);
    if (plano.erro) { console.error(`✗ ${plano.erro}`); process.exit(2); }
    console.log(`\n  PREVIEW-DS — repondo o _ds/ do espelho (id derivado do shell, não hardcode)\n`);
    console.log(`  design system: ${plano.id}`);
    let ok = 0, faltando = [];
    for (const a of plano.arquivos) {
      if (!a.temNoRepo) { faltando.push(a.nome); console.log(`  ⚠ SEM FONTE   ${a.nome}  (não existe em scripts/design-sync/mirror-snapshot/)`); continue; }
      mkdirSync(dirname(a.para), { recursive: true });
      writeFileSync(a.para, readFileSync(a.de));
      ok++;
      console.log(`  ✓ reposto     ${a.nome}`);
    }
    console.log(`\n  ${ok} reposto(s) · ${faltando.length} sem fonte no repo${faltando.length ? ` (${faltando.join(', ')})` : ''}`);
    console.log(`  destino: ${plano.destino}/ — segue gitignored (build de preview, não versionamento).`);
    // A frase precisa distinguir os dois tipos, porque a consequência é diferente:
    // JS compilado (`_ds_bundle.js`) o protótipo contorna; FONTE não se contorna —
    // ela cai no fallback do sistema e MUDA o render (medido 2026-08-14).
    if (faltando.length) {
      const fontes = faltando.filter((f) => /\.(woff2?|ttf|otf|eot)$/i.test(f));
      const resto = faltando.filter((f) => !/\.(woff2?|ttf|otf|eot)$/i.test(f));
      if (resto.length) console.log(`  ⚠ o repo NÃO TEM ${resto.length} (${resto.join(', ')}) — componentes compilados do DS; o código do protótipo tem fallback pra eles.`);
      if (fontes.length) console.log(`  ⚠ o repo NÃO TEM ${fontes.length} FONTE(s) — elas NÃO têm fallback equivalente: o preview renderiza com a fonte do sistema, então a tipografia diverge do Cowork vivo. Não é cosmético.`);
    }
    console.log('');
    return;
  }

  // ── --snapshot-from <dir>: MEDIR SEM CONSERTAR (2026-08-13) ──────────────────
  // A tautologia consertada no PR #5754 tinha um resíduo: `--emit-snapshot` só existia
  // ACOPLADO ao `--export-from`, que escreve o espelho antes de medir. Enquanto medir e
  // consertar forem o mesmo comando, a pergunta "o vivo mudou?" é irrespondível — a
  // resposta é SYNC por construção, e o `_stalePreExport` é só um consolo a posteriori.
  // Aqui o snapshot sai dos MESMOS JSONs, com os MESMOS hashes, e o espelho não é tocado:
  // o `--compare` seguinte dá o veredito REAL. Ordem certa do ciclo:
  //   1) get_file → JSONs      2) --snapshot-from  (mede)      3) --compare --check
  //   4) só então --export-from (conserta), se o veredito pedir.
  const snapFromIdx = argv.indexOf('--snapshot-from');
  if (snapFromIdx !== -1) {
    const dir = argv[snapFromIdx + 1];
    if (!dir || !existsSync(dir)) {
      console.error('✗ --snapshot-from exige um diretório com os JSONs do get_file.');
      process.exit(2);
    }
    const outIdx = argv.indexOf('--emit-snapshot');
    const out = outIdx !== -1 ? argv[outIdx + 1] : null;
    if (!out) {
      console.error('✗ --snapshot-from exige --emit-snapshot <arquivo> (é o que ele produz).');
      process.exit(2);
    }
    const snap = {};
    let n = 0, tocaria = 0;
    for (const j of readdirSync(dir).filter((f) => f.endsWith('.json') || f.endsWith('.txt'))) {
      const raw = JSON.parse(readFileSync(join(dir, j), 'utf8'));
      if (!raw.path || typeof raw.content !== 'string') {
        console.error(`✗ ${j}: JSON do get_file precisa ter .path e .content — pulado.`);
        process.exit(2);
      }
      const h = contentHash(raw.content);
      snap[raw.path] = h;
      n++;
      // pré-visão honesta: compara com o disco SEM escrever, só pra o log não mentir
      const abs = join(ROOT, 'prototipo-ui', 'cowork', raw.path);
      const local = existsSync(abs) ? contentHash(readFileSync(abs, 'utf8')) : null;
      const nota = local === null ? 'AUSENTE' : local === h ? 'igual' : 'DIVERGE';
      if (nota !== 'igual') tocaria++;
      console.log(`  ${nota.padEnd(8)} ${raw.path}  (${h.slice(0, 12)})`);
    }
    writeFileSync(out, JSON.stringify({ _origin: 'medicao', ...snap }, null, 2) + '\n');
    console.log(`\n✓ snapshot de MEDIÇÃO em ${out} (${n} entrada(s)) — o espelho NÃO foi tocado.`);
    console.log(`  ${tocaria} arquivo(s) divergem ou faltam. Veredito formal: --compare ${out} --check --ledger`);
    if (tocaria) console.log(`  Pra consertar DEPOIS de ver o veredito: --export-from ${dir}`);
    return;
  }

  // --export-from <dir>: escreve o espelho a partir dos JSONs do DesignSync.get_file.
  // O agente busca (só ele tem o MCP) e o SCRIPT escreve — nunca o agente transcrevendo.
  const expIdx = argv.indexOf('--export-from');
  if (expIdx !== -1) {
    const dir = argv[expIdx + 1];
    if (!dir || !existsSync(dir)) {
      console.error('✗ --export-from exige um diretório com os JSONs do get_file.');
      process.exit(2);
    }
    const jsons = readdirSync(dir).filter((f) => f.endsWith('.json') || f.endsWith('.txt'));
    const vivos = [];
    for (const j of jsons) {
      const raw = JSON.parse(readFileSync(join(dir, j), 'utf8'));
      if (!raw.path || typeof raw.content !== 'string') {
        console.error(`✗ ${j}: JSON do get_file precisa ter .path e .content — pulado.`);
        process.exit(2);
      }
      vivos.push({ path: raw.path, content: raw.content });
    }
    // ── DESTINO — Cowork por default, Design System por `--ds` ────────────────
    //
    // POR QUE (medido 2026-08-18): o `--export-from` é o DONO do papel "baixar fonte
    // de design com fidelidade de byte" — o agente busca (só ele fala MCP) e o SCRIPT
    // escreve o `raw.content`, então o hash é idêntico POR CONSTRUÇÃO. Mas ele só
    // conhecia o espelho Cowork. Consequência real: em 2026-08-17 o
    // `templates/pt-05-dashboard/Pt05Dashboard.dc.html` foi puxado do Design System pra
    // responder uma pergunta e NÃO teve onde pousar — `git ls-files | grep -c
    // pt-05-dashboard` = 0. A fonte morreu com a sessão.
    //
    // ⚠️ O conserto anterior estava ERRADO e foi revertido no mesmo PR: eu tinha posto
    // uma flag `--ds` no `aplicar-payload.mjs`, que é dono de OUTRA coisa (aplicar
    // payload servido). Máquina paralela a tema que já tem dono é a classe LC-19 — e eu
    // a citei no commit enquanto a cometia. A pergunta que faltou é a que o canon manda
    // fazer primeiro: "quem já é dono deste tema?". Era este bloco.
    //
    // O `exportPlan` já aceitava `prefixo` desde sempre; só o chamador hardcodava.
    const PREFIXOS = { cowork: 'prototipo-ui/cowork/', ds: 'prototipo-ui/design-system/' };
    const destinoNome = argv.includes('--ds') ? 'ds' : 'cowork';
    const prefixo = PREFIXOS[destinoNome];
    const plano = exportPlan(vivos, { prefixo });
    // O SNAPSHOT sai daqui de graça (2026-08-13). Antes o ciclo pedia DOIS downloads
    // por arquivo: um pro --export-from, outro pro snapshot do --compare — e o agente
    // é o único que fala MCP, então esse 2º download custava contexto dele. Mas o
    // conteúdo do vivo já está na mão AQUI: emitir o snapshot no mesmo passo corta o
    // ciclo pela metade. ([W] 2026-08-13: "arrumar as máquinas sempre melhor que fazer mão".)
    const snapIdx2 = argv.indexOf('--emit-snapshot');
    const snapOut = snapIdx2 !== -1 ? argv[snapIdx2 + 1] : null;
    const snapshotEmitido = {};
    const tally = { NOVO: 0, ATUALIZADO: 0, inalterado: 0 };
    const nascidos = []; // os NOVO desta rodada, pra alimentar o nasce-sem-medição no fim
    for (const { relPath, content, bytes } of plano) {
      const abs = join(ROOT, relPath);
      const antes = existsSync(abs) ? contentHash(readFileSync(abs, 'utf8')) : null;
      // `mkdir -p` antes de escrever: o bloco nunca criou diretório e funcionava por
      // sorte — as pastas do espelho Cowork já existiam (200 arquivos versionados).
      // No primeiro destino NOVO (`--ds`) isso estourou ENOENT no 1º arquivo, medido
      // 2026-08-18. Vale pros dois destinos: subpasta nova no Cowork tinha o mesmo
      // buraco, só nunca tinha sido exercitada.
      mkdirSync(dirname(abs), { recursive: true });
      writeFileSync(abs, content, 'utf8');
      const depois = contentHash(content);
      const nota = antes === null ? 'NOVO' : antes === depois ? 'inalterado' : 'ATUALIZADO';
      tally[nota]++;
      // chave = path RELATIVO ao espelho (o mesmo que o manifesto usa)
      // chave RELATIVA ao destino escolhido — com `--ds` o prefixo é outro, e cortar
      // o do Cowork deixaria a chave com o caminho inteiro dentro.
      const rel = relPath.startsWith(prefixo) ? relPath.slice(prefixo.length) : relPath;
      if (nota === 'NOVO') nascidos.push(rel);
      snapshotEmitido[rel] = depois;
      console.log(`  ${nota.padEnd(11)} ${relPath}  (${bytes} bytes · ${depois.slice(0, 12)})`);
    }
    console.log(`\n✓ ${plano.length} arquivo(s) escritos do JSON — fiel por construção, sem transcrição.`);
    console.log(`  ${tally.ATUALIZADO} atualizado(s) · ${tally.NOVO} novo(s) · ${tally.inalterado} inalterado(s)`);
    if (snapOut) {
      // ⚠️ TAUTOLOGIA (achado do adversário 2026-08-13, provado em sandbox): este snapshot sai
      // do conteúdo que ACABOU de ser escrito, então o `--compare` seguinte SEMPRE dá SYNC —
      // inclusive quando o arquivo estava STALE e o export foi justamente o conserto. O ledger
      // gravava `stale: 0` numa rodada que consertou N arquivos: é o drift-sentinel tautológico
      // do §5 2026-07-17 ("se todos os pontos são idênticos, o MEDIDOR é o problema"), e a ADR
      // 0324 trata o ledger como EVIDÊNCIA de aceite.
      // O número que faltava já existia aqui (`tally.ATUALIZADO`). Agora ele viaja no snapshot,
      // o --compare o repassa e o ledger o grava como `stale_pre_export` — a rodada passa a
      // distinguir "estava em dia" de "acabei de arrumar".
      writeFileSync(snapOut, JSON.stringify({ _origin: 'export', _stalePreExport: tally.ATUALIZADO, ...snapshotEmitido }, null, 2) + '\n');
      console.log(`  snapshot emitido em ${snapOut} (${Object.keys(snapshotEmitido).length} entrada(s)) — sem re-baixar.`);
      if (tally.ATUALIZADO) {
        console.log(`  ⚠ ${tally.ATUALIZADO} arquivo(s) ESTAVAM stale e foram consertados por este export.`);
        console.log(`    O --compare a seguir vai dar SYNC — não porque estava em dia, mas porque acabou de ser arrumado.`);
        console.log(`    O snapshot carrega isso (_stalePreExport) e o ledger registra.`);
      }
      console.log(`  Feche o ciclo: --compare ${snapOut} --check --ledger`);
    } else {
      console.log('  Dica: --emit-snapshot <arq> emite o snapshot AQUI e evita re-baixar tudo pro --compare.');
    }

    // ── NASCE-SEM-MEDIÇÃO, LIGADO NO CHOKEPOINT ([W]: "ligue as máquinas") ────────
    // O `--check-novos` nasceu ÓRFÃO: media certo e ninguém o invocava — que é bug, não
    // neutralidade (proibicoes §"Sempre fazer" 2). Ligá-lo no CI não serve: ele precisa da
    // lista do VIVO, e a auth do DesignSync é interativa (ADR 0324). Mas AQUI o fluxo real
    // já tem as duas pontas na mão — os arquivos que acabaram de nascer e os paths do vivo,
    // que vieram no mesmo JSON. Chokepoint provado, não inventado (§5 2026-07-09: guard em
    // comando fantasma). O aviso é advisory: quem decide se o arquivo deve entrar no shell
    // é [W], e o export não é lugar de reprovar.
    if (nascidos.length) {
      const r = nasceSemMedicao(nascidos, buildManifest(ROOT, { shellHtml: lerShellHtml() }), vivos.map((v) => v.path));
      if (r.acusados.length) {
        console.log(`\n⚠ ${r.acusados.length} arquivo(s) NASCERAM sem entrar no manifesto — o frescor não vai medi-los:`);
        for (const a of r.acusados) console.log(`    ${a}`);
        console.log(`  Pra medir: referencie no shell do espelho ou aponte um charter pra ele.`);
      } else {
        console.log(`\n✓ nasce-sem-medição: os ${nascidos.length} arquivo(s) novos entram no manifesto.`);
      }
    }
    return;
  }

  // --sla: modo headless-safe (lê SÓ o ledger — nada de rede/auth). Mede CADÊNCIA da rotina
  // + último resultado; NÃO mede frescor (isso só o dispatch logado mede).
  if (argv.includes('--sla')) {
    const lp = join(ROOT, LEDGER_REL);
    let entries = [];
    try { entries = existsSync(lp) ? JSON.parse(readFileSync(lp, 'utf8')) : []; } catch { entries = []; }
    const r = slaVerdict(entries, new Date().toISOString());
    // COBERTURA sai sempre: "0 stale" só significa alguma coisa junto de quantos foram medidos.
    const cob = r.cobertura ? ` · mediu ${r.cobertura.medidos}/${r.cobertura.total}` : '';
    const detail = r.last ? `última rodada ${r.last.date} (há ${r.ageDays}d): ${r.last.sync} sync · ${r.last.stale} stale · ${r.last.unchecked} unchecked${cob}` : 'nenhuma rodada registrada';
    if (r.veredito === 'FRESH') {
      console.log(`✓ rotina de frescor dentro do SLA (${SLA_DAYS}d) e COMPLETA — ${detail}.`);
      return;
    }
    const msg = {
      'NEVER-RAN': () => `rotina de frescor NUNCA rodou (ledger vazio) — rode o dispatch logado (--manifest → DesignSync.get_file → --export-from <dir> --emit-snapshot snap.json → --compare snap.json --check --ledger).`,
      'OVERDUE': () => `rotina de frescor FORA do SLA (${SLA_DAYS}d) — ${detail}. Rode o dispatch logado.`,
      'LAST-STALE': () => `última rodada achou DIVERGÊNCIA não-resolvida — ${detail} (${(r.last.staleList || []).join(', ')}). Direção não medida: confira com o dry-run do aplicar-payload ANTES de re-exportar — o espelho pode estar À FRENTE (caso qa-conformance.js, 2026-08-17).`,
      'LAST-PARTIAL': () => `última rodada foi PARCIAL — ${detail}. Rodada parcial é legítima, mas "${r.last.stale} stale" só cobre o que foi medido: ${r.last.unchecked} arquivo(s) seguem sem veredito. Pra fechar, exporte o resto (--export-from <dir> --emit-snapshot) e rode --compare --ledger.`,
    }[r.veredito]();
    console.error(`✗ ${msg}`);
    process.exit(1);
  }

  // Shell das deps de render (LC-07): --shell <path> explícito, senão o do staging fixo.
  const shellIdx = argv.indexOf('--shell');
  const shellPath = shellIdx !== -1 ? argv[shellIdx + 1] : defaultShellPath();
  const shellHtml = shellPath && existsSync(shellPath) ? readFileSync(shellPath, 'utf8') : null;

  const manifest = buildManifest(ROOT, { all, shellHtml });

  if (cmpIdx === -1) {
    const nA = manifest.filter((m) => m.kind === 'ancora').length;
    const nD = manifest.filter((m) => m.kind === 'dep').length;
    process.stdout.write(JSON.stringify({ generated: 'cowork-mirror-freshness manifest v3 (path completo · sha256 normalizado · âncoras + deps de render)', files: manifest }, null, 2) + '\n');
    process.stderr.write(
      `\n  ${manifest.length} arquivo(s) do espelho pra conferir contra o vivo (Cowork 019dcfd3): ${nA} âncora(s) + ${nD} dep(s) de render${shellHtml ? ` (shell: ${shellPath})` : ''}.\n` +
      (shellHtml ? '' : `  ⚠ DEPS DE RENDER NÃO ENUMERADAS — shell ausente (staging vazio e sem --shell). Rodada só de âncoras é CEGA pra app.jsx/styles.css/tokens (furo LC-07 — foi por aí que o drift do PageHeader roxo passou).\n`) +
      `  Rodada mínima recomendada: âncoras + deps globais (app.jsx · styles.css · ds-v6/tokens.css) + css do módulo tocado. Parcial = UNCHECKED (honesto).\n` +
      `  Próximo passo (agente logado): DesignSync.get_file por path → snapshot {relPath: contentHash} → --compare snap.json.\n` +
      `  ATENÇÃO: o snapshot DEVE usar a MESMA normalização (importe contentHash/normalize deste módulo).\n\n`,
    );
    reportAbsentLocal(shellHtml, process.stderr); // stdout aqui é o JSON do manifesto
    return;
  }

  const snapPath = argv[cmpIdx + 1];
  if (!snapPath || !existsSync(snapPath)) {
    console.error(`✗ --compare exige um snapshot.json existente (do DesignSync.get_file). Recebi: ${snapPath || '(nada)'}`);
    process.exit(2);
  }
  let snapshot;
  try {
    snapshot = JSON.parse(readFileSync(snapPath, 'utf8'));
  } catch (e) {
    console.error(`✗ snapshot inválido (JSON malformado): ${snapPath} — ${e.message}`);
    process.exit(2);
  }
  const strict = argv.includes('--check');

  const rows = manifest.map((f) => ({ ...f, veredito: verdictFor(f.cowork, f.repoHash, snapshot) }));
  const stale = rows.filter((r) => r.veredito === 'STALE');
  const absent = rows.filter((r) => r.veredito === 'LIVE-ABSENT');
  const unchecked = rows.filter((r) => r.veredito === 'UNCHECKED');
  const sync = rows.filter((r) => r.veredito === 'SYNC');

  console.log(`\n  ESPELHO COWORK — frescor vs vivo (${rows.length} arquivo(s)-âncora · hash normalizado por path completo)\n`);
  // NÃO afirma DIREÇÃO: este modo só tem HASHES, e hash diferente não diz quem avançou.
  // Medido 2026-08-17: o `qa-conformance.js` foi rotulado "espelho ficou atrás — re-exportar",
  // e era o INVERSO (espelho v2.5/G15 × vivo v2.4/G13). Obedecer teria apagado os gates
  // G14/G15 do #4597. Quem sabe a direção é quem tem CONTEÚDO: `aplicar-payload.mjs`.
  for (const r of stale) console.log(`  ⛔ DIVERGE     ${r.cowork}  (hash difere do vivo — direção NÃO medida aqui)`);
  for (const r of absent) console.log(`  🟡 LIVE-ABSENT ${r.cowork}  (não achado no vivo — rename/delete upstream ou mapa errado)`);
  for (const r of unchecked) console.log(`  ⬜ UNCHECKED   ${r.cowork}  (agente não buscou — snapshot incompleto)`);
  console.log(`\n  ⛔ stale: ${stale.length} · 🟡 live-absent: ${absent.length} · ⬜ unchecked: ${unchecked.length} · ✓ sync: ${sync.length}`);
  // Snapshot vindo de export: o SYNC acima é consequência do export, não prova de que estava
  // em dia. Dizer isso na cara do resultado — senão o número engana quem lê (§5 2026-07-17).
  if (snapshot._origin === 'export') {
    const pre = snapshot._stalePreExport || 0;
    console.log(
      `  ⚠ snapshot de EXPORT: o SYNC acima não prova frescor — o export escreveu o conteúdo do vivo antes de medir.\n` +
      `    Divergência REAL desta rodada (antes do export): ${pre} arquivo(s).`,
    );
  }
  console.log('');
  reportAbsentLocal(shellHtml);

  // --ledger: registra a rodada (datada, append-only) — é o que o --sla audita depois.
  if (argv.includes('--ledger')) {
    const lp = join(ROOT, LEDGER_REL);
    let entries = [];
    try { entries = existsSync(lp) ? JSON.parse(readFileSync(lp, 'utf8')) : []; } catch { entries = []; }
    entries.push(ledgerEntry(rows, new Date().toISOString(), {
      origin: snapshot._origin, stalePreExport: snapshot._stalePreExport,
    }));
    writeFileSync(lp, JSON.stringify(entries, null, 2) + '\n');
    console.log(`  ledger: rodada registrada em ${LEDGER_REL} (${entries.length} entrada(s)). Commite o ledger.`);
  }

  // O VEREDITO é do fato medido E do denominador; o `--check` decide só o exit code.
  // Ver docblock de `veredictoFinal` — as duas doenças que isto fecha.
  const vf = veredictoFinal(stale.length, {
    total: rows.length,
    medidos: rows.length - unchecked.length,
    semVeredito: unchecked.length,
  });
  (vf.ok ? console.log : console.error)(vf.texto);
  if (strict && shouldFail(rows.map((r) => r.veredito))) process.exit(1);
}

if (process.argv[1] && process.argv[1].replace(/\\/g, '/').endsWith('cowork-mirror-freshness.mjs')) main();
