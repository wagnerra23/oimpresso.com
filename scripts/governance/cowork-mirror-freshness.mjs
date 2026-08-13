#!/usr/bin/env node
// @ts-check
/**
 * cowork-mirror-freshness.mjs — comparador de FRESCOR do espelho Cowork (v2, identidade canônica).
 *
 * O QUE É: ferramenta de DISPATCH (agente logado) que compara cada arquivo-âncora do espelho
 * `prototipo-ui/cowork/` com o design VIVO no Cowork (projeto 019dcfd3, lido via
 * `DesignSync.get_file` — método de LEITURA, livre por ADR 0315 Eixo B). Divergiu = o espelho
 * ficou atrás do vivo → re-exportar. Automatiza o "diffar antes de concluir" que o
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
 *  O texto NUNCA pode depender de `--check`: o modo relatório e o modo gate observam o MESMO
 *  fato, e só o EXIT CODE é privilégio do gate. Bug medido 2026-08-13: o
 *  `console.log('✓ sem espelho STALE')` vivia FORA do `if (strict …)`, então uma rodada com
 *  `⛔ stale: 1` impresso no corpo TERMINAVA com a linha verde — e a última linha é o que quem
 *  roda sem `--check` lê como resultado. Reproduzido no dia: `--compare snap.json` com o
 *  `jana-merge.jsx` divergente imprimiu corpo vermelho e rodapé verde.
 *
 *  É a família LC-10 (artefato afirmando o próprio estado) no eixo do OUTPUT — a mesma doença
 *  que o `ancora.mjs` teve carimbando `✓` sobre âncora `n/a`, consertada em 2026-08-11. */
export function veredictoFinal(nStale) {
  return nStale > 0
    ? { ok: false, texto: `✗ ${nStale} arquivo(s) do espelho STALE — o vivo avançou e o espelho ficou. Re-exporte do Cowork.` }
    : { ok: true, texto: '✓ sem espelho STALE (divergência hash-provada).' };
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
// STALE mede "o espelho ficou atrás"; LIVE-ABSENT mede "sumiu do vivo"; liveOnly mede
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
  return {
    id,
    destino: `prototipo-ui/cowork/_ds/${id}`,
    arquivos: querUsar.map((f) => ({
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
    if (faltando.length) console.log(`  ⚠ o que falta o repo NÃO TEM: são componentes compilados do DS, e o código do protótipo tem fallback pra eles.`);
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
    const plano = exportPlan(vivos);
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
      writeFileSync(abs, content, 'utf8');
      const depois = contentHash(content);
      const nota = antes === null ? 'NOVO' : antes === depois ? 'inalterado' : 'ATUALIZADO';
      tally[nota]++;
      // chave = path RELATIVO ao espelho (o mesmo que o manifesto usa)
      const rel = relPath.replace(/^prototipo-ui\/cowork\//, '');
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
      'LAST-STALE': () => `última rodada achou STALE não-resolvido — ${detail} (${(r.last.staleList || []).join(', ')}). Re-exporte do Cowork e rode de novo.`,
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
  for (const r of stale) console.log(`  ⛔ STALE       ${r.cowork}  (espelho ficou atrás do vivo — re-exportar)`);
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

  // O VEREDITO é do fato medido; o `--check` decide só o exit code. Separar os dois é o
  // conserto de 2026-08-13 (ver docblock de `veredictoFinal`).
  const vf = veredictoFinal(stale.length);
  (vf.ok ? console.log : console.error)(vf.texto);
  if (strict && shouldFail(rows.map((r) => r.veredito))) process.exit(1);
}

if (process.argv[1] && process.argv[1].replace(/\\/g, '/').endsWith('cowork-mirror-freshness.mjs')) main();
