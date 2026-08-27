#!/usr/bin/env node
// charter-validate.mjs — PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1, advisory).
// AVISA (strict opcional bloqueia) ao Editar Pages/<Mod>/<Tela>.tsx que TEM `.charter.md` vivo
// sem ter chamado charter-fetch antes — reflexo Charter > Spec.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// Princípio #3 da Constituição V2 (Charter > Spec — ADR 0094/0101). Skill charter-first.
// O adversário 2026-07-20 REFUTOU a aposentadoria: block-mwart cobre RUNBOOK, block-ancora
// cobre PNG — NENHUM cobre "editou a Page sem ler o charter" (personas-resolve declara este
// hook como bind de enforcement). Por isso PORTAR, não deletar.
//
// ── POR QUE .mjs (US-GOV-052) ─ o .ps1 só roda no Windows; no Mac/Linux o aviso evapora.
// Supersede charter-validate.ps1 + charter-validate.sh (gêmeo).
// ADVISORY default (allow). Strict (env CHARTER_VALIDATE_STRICT=1) → deny. Fail-open.
// Selftest: node .claude/hooks/charter-validate.mjs --selftest

import { spawnSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const WRITE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit']);
const BACKSLASH = String.fromCharCode(92);
const EXEMPT_MOD = new Set(['_Showcase', '_components', '_internal']);
const EXEMPT_TELA = new Set(['App', 'Layout']);

export function toFwd(p) { return String(p || '').split(BACKSLASH).join('/'); }

/** {modulo, tela} se é uma Page .tsx elegível (top-level ou 1 subdir), senão null. */
export function matchPage(filePath) {
  const m = /resources\/js\/Pages\/([^/_][^/]*)\/(?:[^/]+\/)?([A-Za-z][A-Za-z0-9]*)\.tsx$/.exec(toFwd(filePath));
  if (!m) return null;
  const modulo = m[1];
  const tela = m[2];
  if (EXEMPT_MOD.has(modulo)) return null;
  if (tela.startsWith('_') || EXEMPT_TELA.has(tela)) return null;
  return { modulo, tela };
}

/** caminho do charter irmão (<path sem .tsx>.charter.md). */
export function charterPathFor(filePath) {
  return String(filePath).slice(0, -4) + '.charter.md';
}

/** lê o `status:` do frontmatter do charter (primeiras ~30 linhas). */
export function readCharterStatus(charterPath) {
  try {
    const head = readFileSync(charterPath, 'utf8').split('\n').slice(0, 30);
    for (const ln of head) {
      const m = /^status:\s*(\S+)/.exec(ln);
      if (m) return m[1].replace(/['"]/g, '').trim();
    }
  } catch { /* fall through */ }
  return 'unknown';
}

/**
 * Estado de FRESCOR da âncora que o charter declara — o vínculo tela → charter → protótipo.
 *
 * POR QUE ENTRA AQUI (2026-08-27, [W]: *"a âncora errada gera muita despesa (…) deve separar
 * em um passo único e testar muito bem com garantias de vínculo"*): este hook já é o dono da
 * pergunta *"você leu o vínculo antes de editar?"*. O `related_prototype` vive no MESMO
 * frontmatter que ele já lê. Abrir hook novo seria LC-19 — máquina paralela a tema com dono.
 *
 * O CASO REAL que motivou: numa sessão de 2026-08-27 o `ancora.mjs` imprimiu
 * `✗ frescor: STALE — o que você abrir aqui NÃO é o design atual`, e o agente (eu) editou a
 * tela assim mesmo, derivando um ajuste de DS de um retrato de dois dias antes. Não faltou
 * instrumento — faltou BLOQUEIO. O aviso existia e foi ignorado.
 *
 * ⚠️ CRITÉRIO ESTRITO, e a estreiteza é o ponto. Só acusa âncora **medida e REPROVADA**
 * (presente no `staleList` da última rodada de `--compare`). "Nunca verificado" PASSA.
 * Medido no corpus real antes de escrever: 44 telas com protótipo nomeado →
 *   3 STALE (Jana/Index · Chat · Memoria, todas o mesmo `jana-merge.jsx`)
 *   16 já verificadas · 25 NUNCA verificadas
 * Fosse "não verificado ⇒ bloqueia", travaria 25 telas — a parede que a primeira pessoa
 * desliga, anti-padrão já enterrado no §5. Com 3/44 (6,8%), morde o caso real e some sozinho
 * quando o espelho ressincronizar.
 *
 * ⚠️ LIMITE HONESTO: o `staleList` só é alimentado por sessão logada (o `--compare` precisa do
 * DesignSync, ADR 0315 — o CI não alcança). Se ninguém medir, a lista congela e este check
 * para de morder EM SILÊNCIO. Ele detecta "o instrumento avisou e foi ignorado", NÃO
 * "a âncora está velha e ninguém olhou" — que é o caso mais comum e segue sem dono.
 *
 * Fail-open em tudo: ledger ausente/ilegível, charter sem campo, `n/a`, path irresolvível.
 */
export function ancoraStale(charterPath, raizGit = process.cwd()) {
  try {
    const head = readFileSync(charterPath, 'utf8').split('\n').slice(0, 40);
    const ln = head.find((l) => /^related_prototype:/.test(l));
    if (!ln) return null;
    const val = ln.replace(/^related_prototype:\s*/, '').trim();
    if (/^n\/a/i.test(val)) return null;                       // herda PT — não tem âncora própria
    const proto = (val.match(/prototipo-ui\/[^\s"`)]+/) || [])[0];
    if (!proto) return null;

    const ledgerPath = join(raizGit, 'scripts', 'governance', '.cowork-freshness-ledger.json');
    const bruto = JSON.parse(readFileSync(ledgerPath, 'utf8'));
    const todas = Array.isArray(bruto) ? bruto : (bruto.entries || []);
    // Só rodada de `--compare`. Entrada de `live-only` mede outra pergunta e não produz
    // veredito de frescor de arquivo nenhum — tratá-la como "a última" degrada o veredito
    // (medido em 2026-08-27: uma rodada de live-only fez `STALE` virar `SEM VEREDITO NOVO`).
    const compare = todas.filter((e) => e && e.kind !== 'live-only');
    if (!compare.length) return null;
    const ultima = compare[compare.length - 1];
    const nome = proto.replace(/^prototipo-ui\/cowork\//, '');
    if (!(ultima.staleList || []).includes(nome)) return null;
    return { proto: nome, medidoEm: ultima.date };
  } catch { return null; }                                      // fail-open
}

export function buildOutput({ tool, pathFwd, charterRelative, charterStatus, strict, stale }) {
  if (stale) {
    const m = `[charter-first · ÂNCORA STALE] ${tool} em '${pathFwd}'. O charter declara ` +
      `\`${stale.proto}\` como âncora, e a última rodada de \`--compare\` (${stale.medidoEm}) mediu esse ` +
      `arquivo e o reprovou: o espelho DIVERGE do Cowork vivo. Derivar design daí é decidir sobre um ` +
      `retrato velho — foi assim que um ajuste de DS saiu do eixo errado em 2026-08-27.\n` +
      `Antes de editar: \`node prototipo-ui/ancora.mjs <Mod/Tela>\` pra ver o veredito, e ressincronize ` +
      `pela ROTA PRINCIPAL (bundle emitido do lado Cowork) — \`node prototipo-ui/protocolo.config.mjs\` fase -1.\n` +
      `Isto NÃO é "nunca verificado" (esse passa): é medido e REPROVADO.`;
    if (strict) {
      return { hookSpecificOutput: { hookEventName: 'PreToolUse', permissionDecision: 'deny', permissionDecisionReason: m + ' Modo STRICT — Edit BLOQUEADO.' } };
    }
    return { hookSpecificOutput: { hookEventName: 'PreToolUse', permissionDecision: 'allow', permissionDecisionReason: m + ' Modo warning (advisory) — promoção a deny é flip [W].' } };
  }
  return buildOutputCharter({ tool, pathFwd, charterRelative, charterStatus, strict });
}

function buildOutputCharter({ tool, pathFwd, charterRelative, charterStatus, strict }) {
  let msg = `[charter-first] ${tool} em '${pathFwd}' — esta tela TEM contrato vivo em '${charterRelative}' (status: ${charterStatus}). ` +
    `Constituição V2 #3 (Charter > Spec — ADR 0094/0101): chame a tool MCP charter-fetch ANTES de editar ` +
    `pra carregar Mission/Goals/Non-Goals/UX targets/Anti-hooks. Skill charter-first. `;
  if (strict) {
    msg += 'Modo STRICT (env CHARTER_VALIDATE_STRICT=1) — Edit BLOQUEADO.';
    return { hookSpecificOutput: { hookEventName: 'PreToolUse', permissionDecision: 'deny', permissionDecisionReason: msg } };
  }
  msg += 'Modo warning (P1 — vira bloqueante quando ROI provado em >=5 sessões).';
  return { hookSpecificOutput: { hookEventName: 'PreToolUse', permissionDecision: 'allow', permissionDecisionReason: msg } };
}

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  try {
    let raw;
    try { raw = await readStdin(); } catch { process.exit(0); }
    if (!raw) process.exit(0);
    let tool = '', path = '';
    try {
      const p = JSON.parse(raw);
      tool = String((p && p.tool_name) || '');
      path = String((p && p.tool_input && p.tool_input.file_path) || '');
    } catch { process.exit(0); }
    if (!WRITE_TOOLS.has(tool) || !path) process.exit(0);
    if (!matchPage(path)) process.exit(0);
    const charterPath = charterPathFor(path);
    if (!existsSync(charterPath)) process.exit(0);
    const out = buildOutput({
      tool, pathFwd: toFwd(path), charterRelative: toFwd(charterPath),
      charterStatus: readCharterStatus(charterPath), strict: process.env.CHARTER_VALIDATE_STRICT === '1',
      stale: ancoraStale(charterPath),
    });
    process.stdout.write(JSON.stringify(out) + '\n');
    process.exit(0);
  } catch { process.exit(0); }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./charter-validate.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [fileURLToPath(test)], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
