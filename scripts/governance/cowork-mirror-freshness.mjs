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

import { readFileSync, writeFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { homedir } from 'node:os';
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
export function ledgerEntry(rows, dateIso) {
  const n = (v) => rows.filter((r) => r.veredito === v).length;
  return {
    date: dateIso,
    files: rows.length,
    sync: n('SYNC'),
    stale: n('STALE'),
    liveAbsent: n('LIVE-ABSENT'),
    unchecked: n('UNCHECKED'),
    staleList: rows.filter((r) => r.veredito === 'STALE').map((r) => r.cowork),
  };
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
export function defaultShellPath(root = ROOT) {
  const noRepo = join(root, 'prototipo-ui', 'cowork', 'oimpresso.com.html');
  if (existsSync(noRepo)) return noRepo;
  const base = join(homedir(), 'Downloads', '_cowork-handoff-staging');
  if (!existsSync(base)) return null;
  for (const e of readdirSync(base)) {
    const p = join(base, e, 'project', 'oimpresso.com.html');
    if (existsSync(p)) return p;
  }
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

/** Imprime o ABSENT-LOCAL. ADVISORY por desenho (ADR 0275 "nasce advisory" · 0336
 *  "promoção só por mordida provada"): o `--check` segue mordendo SÓ em STALE, que é o
 *  sinal hash-provado. Promover isto a bloqueio é decisão [W], não do script.
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
      ? `  Pra versionar: DesignSync.get_file de cada → salve os JSON num dir → --export-from <dir>.\n  (advisory — o --check morde só em STALE; promover é decisão [W])\n`
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
    const manifest = buildManifest(ROOT, { all: true, shellHtml: defaultShellPath() });
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
    console.log('  ⚠️ advisory por desenho: o que merece descer é decisão [W], não da máquina.\n');
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
    for (const { relPath, content, bytes } of plano) {
      const abs = join(ROOT, relPath);
      const antes = existsSync(abs) ? contentHash(readFileSync(abs, 'utf8')) : null;
      writeFileSync(abs, content, 'utf8');
      const depois = contentHash(content);
      const nota = antes === null ? 'NOVO' : antes === depois ? 'inalterado' : 'ATUALIZADO';
      tally[nota]++;
      // chave = path RELATIVO ao espelho (o mesmo que o manifesto usa)
      snapshotEmitido[relPath.replace(/^prototipo-ui\/cowork\//, '')] = depois;
      console.log(`  ${nota.padEnd(11)} ${relPath}  (${bytes} bytes · ${depois.slice(0, 12)})`);
    }
    console.log(`\n✓ ${plano.length} arquivo(s) escritos do JSON — fiel por construção, sem transcrição.`);
    console.log(`  ${tally.ATUALIZADO} atualizado(s) · ${tally.NOVO} novo(s) · ${tally.inalterado} inalterado(s)`);
    if (snapOut) {
      writeFileSync(snapOut, JSON.stringify(snapshotEmitido, null, 2) + '\n');
      console.log(`  snapshot emitido em ${snapOut} (${Object.keys(snapshotEmitido).length} entrada(s)) — sem re-baixar.`);
      console.log(`  Feche o ciclo: --compare ${snapOut} --check --ledger`);
    } else {
      console.log('  Dica: --emit-snapshot <arq> emite o snapshot AQUI e evita re-baixar tudo pro --compare.');
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
  console.log(`\n  ⛔ stale: ${stale.length} · 🟡 live-absent: ${absent.length} · ⬜ unchecked: ${unchecked.length} · ✓ sync: ${sync.length}\n`);
  reportAbsentLocal(shellHtml);

  // --ledger: registra a rodada (datada, append-only) — é o que o --sla audita depois.
  if (argv.includes('--ledger')) {
    const lp = join(ROOT, LEDGER_REL);
    let entries = [];
    try { entries = existsSync(lp) ? JSON.parse(readFileSync(lp, 'utf8')) : []; } catch { entries = []; }
    entries.push(ledgerEntry(rows, new Date().toISOString()));
    writeFileSync(lp, JSON.stringify(entries, null, 2) + '\n');
    console.log(`  ledger: rodada registrada em ${LEDGER_REL} (${entries.length} entrada(s)). Commite o ledger.`);
  }

  if (strict && shouldFail(rows.map((r) => r.veredito))) {
    console.error(`✗ ${stale.length} arquivo(s) do espelho STALE — o vivo avançou e o espelho ficou. Re-exporte do Cowork.`);
    process.exit(1);
  }
  console.log('✓ sem espelho STALE (divergência hash-provada).');
}

if (process.argv[1] && process.argv[1].replace(/\\/g, '/').endsWith('cowork-mirror-freshness.mjs')) main();
