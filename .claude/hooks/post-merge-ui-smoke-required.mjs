#!/usr/bin/env node
// post-merge-ui-smoke-required.mjs — PostToolUse:Bash + PreToolUse:Bash|browser-MCP
// (PORTE cross-plataforma do .ps1). Smoke visual pós-merge UI obrigatório (R1).
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// proibicoes.md §"Claim sem evidência", bullet Tier 0 pós-merge UI + PROTOCOLO-WAGNER R1
// (smoke real, não narração): APÓS qualquer merge de PR que mexa em UI (.tsx/.css/
// .blade.php), Claude OBRIGATORIAMENTE abre browser MCP + screenshot ANTES de declarar
// "pronto"/"deployed"/"funcionando"/"ao vivo"/"live em prod". Sem screenshot post-deploy
// = não está pronto. Wagner (reincidente): "sempre estou tendo que fazer isso, os metodos
// de memória ainda não estão sendo garantidos".
//
// MECÂNICA (4 casos):
//   1. PostToolUse Bash: `gh pr merge --admin` de PR que tocou UI → grava flag com
//      timestamp (tmpdir/oimpresso-ui-merge-pending.flag — mesmo nome que grade.mjs limpa).
//   2. PreToolUse browser MCP (screenshot/navigate/read_page/js/get_page_text/find):
//      Claude está olhando de verdade → marca `viu=1` (e limpa a flag quando o PR não
//      tocou tela nenhuma com âncora de design — o caminho de sempre).
//   3. PostToolUse Bash `design-diff.mjs --compare`: a comparação com a âncora foi MEDIDA
//      → marca `comp=1`.
//   4. PreToolUse Bash: flag existe + fresca (<5min) + comando carrega claim
//      ("pronto|deployed|funcionando|ao vivo|live em prod|smoke ok|…") → BLOQUEIA (exit 2)
//      enquanto faltar `viu` OU (havendo tela com âncora) faltar `comp`.
// Bloqueio é legítimo pela ADR 0224: gatilho determinístico (flag mecânica + tool_name),
// não regex semântica solta — a flag só existe se um merge UI real acabou de acontecer.
//
// ── A PERNA DA ÂNCORA (2026-08-28) ───────────────────────────────────────────
// O hook cobrava UM screenshot e liberava. `grep -nE "prototipo|design-diff|ancora"` no
// corpo dele devolvia ZERO: a comparação com o protótipo — que o
// memory/requisitos/_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md §"Regra 0" (Tier 0) já
// manda fazer pós-deploy sem o [W] pedir — não tinha máquina nenhuma atrás. Custo medido:
// o PR #6385 mergeou com 6 regressões de fidelidade (ordenação ausente, busca morta,
// painel Pendências ausente, sparkline ausente, rodapé ausente, Alert factualmente
// errado) e o hook liberou com 1 screenshot. Screenshot prova que a página SUBIU; não
// prova que ela é a tela que o design declara (é a lição LC-06: comparação é MEDIDA).
//
// FP MEDIDO ANTES DE ARMAR (regra "LIGUE A MÁQUINA" item 4 · proibicoes.md §Sempre fazer).
// Corpus real = 4.103 PRs mergeados em 90d (`git log origin/main --since=90d --name-only`,
// clone completo conferido com `--is-shallow-repository`) × porta viva `ancora.mjs
// --list --json` (217 telas com charter):
//   • 55 telas têm âncora RESOLVÍVEL (arquivo existe no disco); 119 declaram `n/a`
//     (nascem do DS — cobrar comparação delas seria FP por construção) e 42 não declaram.
//   • 561 PRs (13,7%) o hook já marca hoje. Dentro deles: 163 tocaram tela com âncora,
//     106 só tela `n/a`, 292 só css/blade/componente.
//   • Sem a perna do diff a cobrança pegaria 176 PRs com 20,5% de FP (partial-reload,
//     lint, rename de import, movimentação de arquivo — mexem no .tsx sem mexer na tela).
//   • COM a perna `tocaMarcacao` (o diff do .tsx mexe em marcação/estilo): 140 PRs/90d =
//     1,56 PR-dia, FP residual 1,4% (2 de 140 são perf/chore). É o predicado armado.
// Todas as pernas são MECÂNICAS (arquivo tocado × charter irmão × arquivo de âncora existe
// × linha de diff × comando executado) — nada infere intenção a partir de prosa, que é a
// família de guard sintático com 8 lápides no §5.
//
// CONTRAFACTUAL no caso que motivou (medido 2026-08-28, `git show 01bee7581e` × telasComAncora):
// o #6385 tocou 12 arquivos, entre eles `resources/js/Pages/Home/Index.tsx`, cujo charter declara
// `prototipo-ui/cowork/dash-legacy-page.jsx` (existe) e cujo diff mexeu em marcação → o predicado
// TERIA bloqueado o "pronto" até o `--compare` rodar. Controle negativo no mesmo dia: um PR de
// governança (dbb4f740b1) não marca flag nem cobra nada. A prova hermética das duas pontas está
// nos bite-tests do selftest; esta linha é o recibo do caso real, com o comando que a reproduz.
//
// Colateral MEDIDO no mesmo corpus: `isUiFile` era cego a `Modules/<X>/Resources/js/**`
// (só conhecia `Resources/views/*.blade.php`). 10 das 55 telas com âncora vivem lá desde a
// ADR 0375 — pra elas o hook não marcava flag nenhuma, nem a do screenshot. Perna somada.
//
// Escape valves (todas IMPLEMENTADAS — a promessa e o código são o mesmo lugar, LC-15):
//   · PR body `<!-- no-ui-smoke: <razão> -->` → não grava flag (dispensa tudo).
//   · PR body `<!-- no-ancora-compare: <razão> -->` → grava flag SEM telas: mantém o
//     screenshot, dispensa só a comparação.
//   · env OIMPRESSO_UI_SMOKE_OVERRIDE=1 → desativa o hook inteiro.
//   · env OIMPRESSO_ANCORA_COMPARE_OVERRIDE=1 → desativa só a perna da âncora.
// Env de teste: OIMPRESSO_UI_SMOKE_FLAG=<path> isola a flag em selftest (nunca em prod).
//
// NÃO FICA MUDO quando não consegue medir (regra "LIGUE A MÁQUINA" item 5): se `gh pr diff`
// falhar ou o import de `ancora.mjs` quebrar, a perna nova degrada pro comportamento
// anterior (screenshot obrigatório) e IMPRIME que não mediu — nunca finge que mediu.
//
// ── POR QUE .mjs (leva Tier-0 .ps1→.mjs, SPEC US-GOV-052 / P24) ──────────────
// O .ps1 só roda no Windows do Wagner ($env:TEMP nem existe no Mac/Linux do time MCP —
// lá o R1 evaporava em silêncio). Node os.tmpdir() é cross-plataforma. Mantém o fix F7
// 2026-07-08 (tool real é mcp__claude-in-chrome__* minúscula/hífen; [-_] cobre ambos).
// Supersede post-merge-ui-smoke-required.ps1 (pattern: #4025).
//
// Fail-open: qualquer erro/parse-fail/gh-fail → exit 0 (NUNCA trava sessão).
// Selftest: node .claude/hooks/post-merge-ui-smoke-required.test.mjs
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync, unlinkSync, existsSync } from 'node:fs';
import { join, resolve, dirname } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath, pathToFileURL } from 'node:url';

export const FLAG_TTL_MIN = 5;
// TTL maior SÓ quando a flag carrega tela com âncora. O de 5min foi dimensionado pra um
// screenshot; o fluxo do design-diff (2 abas + sonda injetada + 2 JSONs + --compare) passa
// disso com folga, e uma flag que evapora antes do trabalho terminar é um gate que se escapa
// esperando — mudo com cara de cobrança. Não afrouxa nada do que já existia: sem tela com
// âncora o TTL segue 5min (é o caminho provado desde 2026-07).
export const FLAG_TTL_ANCORA_MIN = 45;

const HERE = dirname(fileURLToPath(import.meta.url)); // <repo>/.claude/hooks
const REPO = resolve(HERE, '..', '..');

export function flagPath(env = process.env) {
  return env.OIMPRESSO_UI_SMOKE_FLAG || join(tmpdir(), 'oimpresso-ui-merge-pending.flag');
}

// ── classificadores PUROS (exportados → testáveis sem stdin/gh) ──────────────────

/** é merge admin de PR? (o gatilho do caso 1) */
export function isAdminMerge(cmd) { return /gh\s+pr\s+merge[\s\S]*--admin/.test(cmd); }

/** número do PR no comando de merge (null se não extraível). */
export function extractPrNumber(cmd) {
  const m = /gh\s+pr\s+merge\s+(\d+)/.exec(cmd);
  return m ? m[1] : null;
}

/** path é superfície UI (lista canônica da proibicoes §pós-merge UI)?
 *  A perna `Modules/<X>/Resources/js/**` entrou em 2026-08-28: as telas Inertia que a
 *  ADR 0375 moveu pra dentro do módulo dono viviam FORA de todas as pernas anteriores
 *  (medido: 10 das 55 telas com âncora resolvível) — o hook não marcava flag pra elas. */
export function isUiFile(p) {
  const f = String(p).split(String.fromCharCode(92)).join('/');
  return (
    /resources\/(js|css)\/.+\.(tsx?|css)$/.test(f) ||
    /Modules\/[^/]+\/Resources\/(js|css)\/.+\.(tsx?|css)$/.test(f) ||
    /resources\/views\/.+\.blade\.php$/.test(f) ||
    /Modules\/.+\/Resources\/views\/.+\.blade\.php$/.test(f)
  );
}

/** o path é uma Page Inertia (a única superfície que tem charter ao lado)? */
export function isPageTsx(p) {
  const f = String(p).split(String.fromCharCode(92)).join('/');
  return /(^|\/)(resources|Resources)\/js\/Pages\/.+\.tsx$/.test(f);
}

/** tool de browser MCP que prova que Claude está OLHANDO (caso 2).
 *  F7 2026-07-08: nome real é mcp__claude-in-chrome__* (minúscula, hífen); [-_] cobre ambos. */
export function isBrowserSmokeTool(toolName) {
  const t = String(toolName || '');
  return (
    /^mcp__(computer-use|claude[-_]in[-_]chrome|Windows-MCP)__/i.test(t) &&
    /(screenshot|navigate|read_page|javascript_tool|get_page_text|find)/i.test(t)
  );
}

/** comando é a COMPARAÇÃO MEDIDA com a âncora (caso 3)?
 *  Fonte = o campo `command` do input da tool (registro estruturado do evento), NUNCA
 *  prosa no chat — a lápide §5 2026-07-26 mata "evidência por substring em texto corrido".
 *  `--probe` sozinho só imprime a sonda; quem produz VEREDITO é `--compare`. */
export function ehComparacaoMedida(cmd) {
  const c = String(cmd || '');
  return /design-diff(\.mjs)?[\s\S]*--compare/.test(c);
}

/** comando carrega declaração de pronto? (lista canônica: proibicoes bloqueia
 *  "pronto|deployed|funcionando|ao vivo|live em prod" + variantes catalogadas). */
export function isClaim(cmd) {
  return /pronto|deployed|funcionando|ao vivo|live em prod|confirma[cç][aã]o total|smoke ok|merge conclu[ií]do/i.test(String(cmd || ''));
}

/** o diff daquele .tsx mexeu em MARCAÇÃO/ESTILO? (a perna que derruba o FP de 20,5% → 1,4%)
 *  Recebe as linhas +/- do diff daquele arquivo. Mede o que afirma medir: se a marcação
 *  mudou — não tenta adivinhar se a mudança "é boa". */
export function tocaMarcacao(linhasDiff) {
  const RE = /className|<[A-Za-z][\w.]*[\s/>]|style=|tw-|text-|bg-|flex|grid|px-|py-|gap-/;
  return (linhasDiff || []).some((l) => RE.test(String(l)));
}

/** linhas +/- de UM arquivo dentro de um diff unificado (parse mecânico, testável). */
export function linhasDoArquivo(diffTexto, path) {
  const alvo = String(path).split(String.fromCharCode(92)).join('/');
  const out = [];
  let dentro = false;
  for (const ln of String(diffTexto || '').split(/\r?\n/)) {
    if (ln.startsWith('diff --git ')) { dentro = ln.includes(' b/' + alvo) || ln.endsWith('/' + alvo); continue; }
    if (!dentro) continue;
    if (ln.startsWith('+++') || ln.startsWith('---')) continue;
    if (ln.startsWith('+') || ln.startsWith('-')) out.push(ln);
  }
  return out;
}

/** charter irmão de uma Page (mesmo dir, mesmo basename). */
export function charterDe(tsxPath) {
  return String(tsxPath).split(String.fromCharCode(92)).join('/').replace(/\.tsx$/, '.charter.md');
}

/** `related_prototype` do charter (ou null). Parse mínimo de frontmatter — o hook não
 *  pode depender da cadeia inteira de libs de charter num caminho fail-open. */
export function relatedPrototypeDe(charterAbs) {
  let src;
  try { src = readFileSync(charterAbs, 'utf8'); } catch { return null; }
  const fm = src.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  if (!fm) return null;
  for (const line of fm[1].split(/\r?\n/)) {
    const m = line.match(/^related_prototype:\s*(.+)$/i);
    if (m) return m[1].trim();
  }
  return null;
}

// ── flag: serialização (retro-compatível com o formato "ISO|PR") ─────────────────

export function serializeFlag({ ts, pr, telas = [], viu = false, comp = false }) {
  const t = telas.map((x) => `${x.tsx}::${x.ancora}`).join(',');
  return [new Date(ts).toISOString(), pr, `telas=${t}`, `viu=${viu ? 1 : 0}`, `comp=${comp ? 1 : 0}`].join('|');
}

/** parse da flag. Retorna {ts, pr, telas, viu, comp} ou null se corrompida.
 *  Formato antigo ("ISO|PR") segue válido → telas=[] , viu=false, comp=false. */
export function parseFlag(content) {
  const partes = String(content || '').split('|');
  const ts = Date.parse(partes[0]);
  if (!Number.isFinite(ts)) return null;
  const out = { ts, pr: partes[1] || '?', telas: [], viu: false, comp: false };
  for (const p of partes.slice(2)) {
    const [k, ...rest] = p.split('=');
    const v = rest.join('=');
    if (k === 'telas' && v) out.telas = v.split(',').filter(Boolean).map((s) => {
      const [tsx, ancora] = s.split('::');
      return { tsx, ancora: ancora || '?' };
    });
    else if (k === 'viu') out.viu = v === '1';
    else if (k === 'comp') out.comp = v === '1';
  }
  return out;
}

/** flag ainda vale (idade < TTL)? O TTL é o da PERNA que a flag carrega: 5min pro screenshot
 *  (caminho de sempre), FLAG_TTL_ANCORA_MIN quando há tela com âncora pendente de comparação.
 *  `ttlMin` explícito sobrepõe os dois (usado pelo selftest). */
export function flagIsFresh(content, nowMs = Date.now(), ttlMin = null) {
  const f = parseFlag(content);
  if (f === null) return false;
  const ttl = ttlMin ?? (f.telas.length ? FLAG_TTL_ANCORA_MIN : FLAG_TTL_MIN);
  return (nowMs - f.ts) < ttl * 60 * 1000;
}

export function blockMessage(pr, ageSec, cmd) {
  return `[BLOCKED: Smoke visual pos-merge UI obrigatorio Tier 0 — R1]

PR #${pr} mergeado ha ${ageSec}s tocou arquivos UI (.tsx/.css/.blade.php).
Wagner regra IRREVOGAVEL (proibicoes §Claim sem evidencia, pos-merge UI):
  Apos merge UI, OBRIGATORIO browser MCP + screenshot ANTES de declarar
  'pronto/deployed/funcionando/ao vivo/live em prod/smoke ok'.

Comando bloqueado: ${cmd}

A FAZER (ordem):
  1. mcp__claude-in-chrome__navigate pra rota afetada (https://oimpresso.com/...)
  2. mcp__claude-in-chrome__* ou mcp__computer-use__screenshot
  3. Relatar o que viu no chat
  4. AI sim declarar 'pronto' / 'deployed'

Escape valve: OIMPRESSO_UI_SMOKE_OVERRIDE=1 (justifique no chat)
ou PR body com '<!-- no-ui-smoke: <razao> -->'.

Refs: memory/proibicoes.md §Claim sem evidencia · PROTOCOLO-WAGNER R1
      memory/reference/feedback-brave-mcp-primeiro-sempre.md`;
}

export function blockMessageComparacao(pr, ageSec, cmd, telas) {
  const lista = telas.map((t) => `    · ${t.tsx}\n        ancora: ${t.ancora}`).join('\n');
  return `[BLOCKED: Comparacao com a ANCORA de design ainda nao foi MEDIDA]

Screenshot: OK (voce olhou). Falta a outra metade.
PR #${pr} mergeado ha ${ageSec}s tocou tela(s) que DECLARAM ancora de design no charter,
e o diff mexeu em marcacao/estilo:

${lista}

Screenshot prova que a pagina SUBIU. Nao prova que ela e a tela que o design declara —
foi o PR #6385: 1 screenshot liberou 6 regressoes de fidelidade (ordenacao ausente,
busca morta, painel ausente, sparkline ausente, rodape ausente, Alert falso).
PROTOCOLO-COMPARACAO-RUNTIME §"Regra 0" (Tier 0) ja manda comparar pos-deploy sem o [W]
pedir. Comparacao e MEDIDA, nunca no olho (LC-06).

Comando bloqueado: ${cmd}

A FAZER (o fluxo do design-diff):
  1. node prototipo-ui/ancora.mjs <Mod/Tela>          # confirma QUAL e a ancora
  2. node prototipo-ui/design-diff.mjs --probe        # imprime a sonda canonica
  3. injete a MESMA sonda nas DUAS abas (prod + render da ancora) via
     mcp__claude-in-chrome__javascript_tool, salve prod.json e design.json
  4. node prototipo-ui/design-diff.mjs --compare prod.json design.json --check
  5. relatar o veredito por dimensao — AI sim declarar 'pronto'

Escape valve: PR body com '<!-- no-ancora-compare: <razao> -->'
ou env OIMPRESSO_ANCORA_COMPARE_OVERRIDE=1 (dispensa SO esta perna; o screenshot fica)
ou OIMPRESSO_UI_SMOKE_OVERRIDE=1 (dispensa o hook inteiro).

Refs: memory/requisitos/_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md §Regra 0
      memory/LICOES_CODE.md LC-06 · prototipo-ui/design-diff.mjs`;
}

// ── resolução das telas com âncora (o predicado novo) ────────────────────────────

/**
 * Dado os arquivos do PR + o diff, devolve as telas que EXIGEM comparação medida.
 * Três pernas, todas mecânicas e cumulativas:
 *   (a) o path é Page .tsx e tem charter irmão;
 *   (b) o charter declara `related_prototype` que NÃO é `n/a` e o arquivo dela EXISTE;
 *   (c) o diff daquele .tsx tocou marcação/estilo.
 * `caminhoDaAncora`/`ehDeclaracaoNa` vêm de `prototipo-ui/ancora.mjs` — o DONO da
 * resolução de âncora (§5: estender o dono, nunca reimplementar ao lado). Import
 * dinâmico: se ele quebrar, esta perna degrada e o hook segue cobrando o screenshot.
 * `repoRoot` é só a raiz de LEITURA dos arquivos (o selftest aponta pra fixture); a lib
 * vem sempre do repo onde este hook vive.
 */
export async function telasComAncora(files, diffTexto, repoRoot = REPO) {
  let ancoraLib;
  try {
    ancoraLib = await import(pathToFileURL(join(REPO, 'prototipo-ui', 'ancora.mjs')).href);
  } catch {
    return { telas: [], mediu: false, motivo: 'import de prototipo-ui/ancora.mjs falhou' };
  }
  const { caminhoDaAncora, ehDeclaracaoNa } = ancoraLib;
  if (typeof caminhoDaAncora !== 'function' || typeof ehDeclaracaoNa !== 'function') {
    return { telas: [], mediu: false, motivo: 'ancora.mjs sem caminhoDaAncora/ehDeclaracaoNa' };
  }
  const telas = [];
  for (const f of files) {
    if (!isPageTsx(f)) continue;
    const charter = charterDe(f);
    const declarado = relatedPrototypeDe(join(repoRoot, charter));
    if (!declarado || ehDeclaracaoNa(declarado)) continue;
    const rel = caminhoDaAncora(declarado, repoRoot);
    if (!rel || !existsSync(resolve(repoRoot, rel))) continue;
    if (!tocaMarcacao(linhasDoArquivo(diffTexto, f))) continue;
    telas.push({ tsx: f, ancora: rel });
  }
  return { telas, mediu: true, motivo: null };
}

// ── casos (side-effects isolados; cada passo fail-open) ──────────────────────────

async function handlePostMerge(cmd, flag) {
  if (!isAdminMerge(cmd)) return;
  const pr = extractPrNumber(cmd);
  if (!pr) return;
  try {
    const files = (spawnSync('gh', ['pr', 'view', pr, '--json', 'files', '-q', '.files[].path'], { encoding: 'utf8' }).stdout || '')
      .split('\n').map((s) => s.trim()).filter(Boolean);
    if (!files.some(isUiFile)) return;
    const body = spawnSync('gh', ['pr', 'view', pr, '--json', 'body', '-q', '.body'], { encoding: 'utf8' }).stdout || '';
    if (/<!--\s*no-ui-smoke/.test(body)) return;

    let telas = [];
    let aviso = '';
    const dispensaComparacao = /<!--\s*no-ancora-compare/.test(body) ||
      process.env.OIMPRESSO_ANCORA_COMPARE_OVERRIDE === '1';
    if (!dispensaComparacao) {
      const d = spawnSync('gh', ['pr', 'diff', pr], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
      if (d.status !== 0) {
        // NÃO FICA MUDO: declara que não mediu em vez de fingir "nenhuma tela com âncora".
        aviso = ' [perna da ancora NAO MEDIDA: `gh pr diff` falhou — so o screenshot esta sendo cobrado]';
      } else {
        const r = await telasComAncora(files, d.stdout || '');
        if (!r.mediu) aviso = ` [perna da ancora NAO MEDIDA: ${r.motivo} — so o screenshot esta sendo cobrado]`;
        else telas = r.telas;
      }
    }
    writeFileSync(flag, serializeFlag({ ts: Date.now(), pr, telas }));
    const extra = telas.length
      ? ` + comparacao MEDIDA com a ancora em ${telas.length} tela(s): ${telas.map((t) => t.ancora).join(', ')}`
      : aviso;
    process.stdout.write(`[ui-smoke-required] PR #${pr} tocou UI files. Smoke browser MCP obrigatorio antes de declarar 'pronto'${extra}.\n`);
  } catch { /* gh indisponível → fail-open */ }
}

/** caso 3: a comparação foi medida → carimba comp=1 (se houver flag viva). */
function handleComparacaoMedida(cmd, flag) {
  if (!ehComparacaoMedida(cmd)) return;
  let content;
  try { content = readFileSync(flag, 'utf8'); } catch { return; }
  const f = parseFlag(content);
  if (!f) return;
  try {
    writeFileSync(flag, serializeFlag({ ...f, comp: true }));
    process.stdout.write('[ui-smoke-required] comparacao com a ancora MEDIDA (design-diff --compare) — registrada.\n');
  } catch { /* */ }
}

/** caso 2: o olhar. Sem tela com âncora, limpa a flag (caminho de sempre); com tela,
 *  carimba viu=1 e mantém a flag até a comparação ser medida. */
function handleOlhou(flag) {
  if (!existsSync(flag)) return;
  let content;
  try { content = readFileSync(flag, 'utf8'); } catch { return; }
  const f = parseFlag(content);
  if (!f || !f.telas.length) { try { unlinkSync(flag); } catch { /* */ } return; }
  try { writeFileSync(flag, serializeFlag({ ...f, viu: true })); } catch { /* */ }
}

function handleClaimCheck(cmd, flag) {
  let content;
  try { content = readFileSync(flag, 'utf8'); } catch { return 0; }
  const parsed = parseFlag(content);
  if (!parsed || !flagIsFresh(content)) {
    try { unlinkSync(flag); } catch { /* */ }
    return 0;
  }
  if (!isClaim(cmd)) return 0;
  const ageSec = Math.round((Date.now() - parsed.ts) / 1000);
  if (!parsed.viu) {
    process.stderr.write(blockMessage(parsed.pr, ageSec, cmd) + '\n');
    return 2;
  }
  if (parsed.telas.length && !parsed.comp && process.env.OIMPRESSO_ANCORA_COMPARE_OVERRIDE !== '1') {
    process.stderr.write(blockMessageComparacao(parsed.pr, ageSec, cmd, parsed.telas) + '\n');
    return 2;
  }
  try { unlinkSync(flag); } catch { /* */ }
  return 0;
}

// ── stdin wrapper (fail-open em TUDO) ────────────────────────────────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  let raw;
  try { raw = await readStdin(); } catch { process.exit(0); }
  if (!raw) process.exit(0);
  let payload;
  try { payload = JSON.parse(raw); } catch { process.exit(0); }
  if (process.env.OIMPRESSO_UI_SMOKE_OVERRIDE === '1') process.exit(0);

  const tool = String((payload && payload.tool_name) || '');
  const event = String((payload && payload.hook_event_name) || '');
  const cmd = String((payload && payload.tool_input && payload.tool_input.command) || '');
  const flag = flagPath();

  // Caso 1/3 — PostToolUse Bash: merge UI marca a flag · design-diff --compare carimba comp
  if (event === 'PostToolUse' && tool === 'Bash') {
    if (cmd) {
      if (isAdminMerge(cmd)) await handlePostMerge(cmd, flag);
      else handleComparacaoMedida(cmd, flag);
    }
    process.exit(0);
  }
  // Caso 2 — PreToolUse browser MCP: olhar de verdade
  if (event === 'PreToolUse' && isBrowserSmokeTool(tool)) {
    handleOlhou(flag);
    process.exit(0);
  }
  // Caso 4 — PreToolUse Bash: claim com flag fresca → bloqueia
  if (event === 'PreToolUse' && tool === 'Bash' && cmd) {
    process.exit(handleClaimCheck(cmd, flag));
  }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./post-merge-ui-smoke-required.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [fileURLToPath(test)], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
