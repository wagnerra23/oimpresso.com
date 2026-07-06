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
 * Uso:
 *   node scripts/governance/cowork-mirror-freshness.mjs --manifest          # âncoras dos charters
 *   node scripts/governance/cowork-mirror-freshness.mjs --manifest --all    # todo .jsx/.html do espelho
 *   node scripts/governance/cowork-mirror-freshness.mjs --compare snap.json            # relatório
 *   node scripts/governance/cowork-mirror-freshness.mjs --compare snap.json --check    # exit 1 se STALE
 *   node scripts/governance/cowork-mirror-freshness.mjs --compare snap.json --check --ledger  # + registra a rodada
 *   node scripts/governance/cowork-mirror-freshness.mjs --sla               # headless: rotina rodou ≤14d? última limpa?
 */

import { readFileSync, writeFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join } from 'node:path';
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
 *  (a última rodada achou divergência e nenhuma rodada posterior limpou). */
export function slaVerdict(entries, nowIso, days = SLA_DAYS) {
  if (!Array.isArray(entries) || entries.length === 0) return { veredito: 'NEVER-RAN', last: null, ageDays: null };
  const last = entries[entries.length - 1];
  const ageDays = Math.floor((Date.parse(nowIso) - Date.parse(last.date)) / 86400000);
  if (ageDays > days) return { veredito: 'OVERDUE', last, ageDays };
  if (last.stale > 0) return { veredito: 'LAST-STALE', last, ageDays };
  return { veredito: 'FRESH', last, ageDays };
}

/** Enumera os arquivos-âncora do espelho, keyed por PATH RELATIVO COMPLETO (nunca basename).
 *  Mesmo conjunto de âncoras que o anchor-content-check enxerga (reusa anchorRelPath). */
export function buildManifest(root = ROOT, { all = false } = {}) {
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
  return [...seen.values()].sort((a, b) => a.cowork.localeCompare(b.cowork));
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
    else if (/\.(jsx|html)$/i.test(e)) acc.push(f.slice(base.length + 1).split('\\').join('/'));
  }
  return acc;
}

// ── CLI ───────────────────────────────────────────────────────────────────────
function main() {
  const argv = process.argv.slice(2);
  const all = argv.includes('--all');
  const cmpIdx = argv.indexOf('--compare');

  // --sla: modo headless-safe (lê SÓ o ledger — nada de rede/auth). Mede CADÊNCIA da rotina
  // + último resultado; NÃO mede frescor (isso só o dispatch logado mede).
  if (argv.includes('--sla')) {
    const lp = join(ROOT, LEDGER_REL);
    let entries = [];
    try { entries = existsSync(lp) ? JSON.parse(readFileSync(lp, 'utf8')) : []; } catch { entries = []; }
    const r = slaVerdict(entries, new Date().toISOString());
    const detail = r.last ? `última rodada ${r.last.date} (há ${r.ageDays}d): ${r.last.sync} sync · ${r.last.stale} stale · ${r.last.unchecked} unchecked` : 'nenhuma rodada registrada';
    if (r.veredito === 'FRESH') {
      console.log(`✓ rotina de frescor dentro do SLA (${SLA_DAYS}d) — ${detail}.`);
      return;
    }
    const msg = {
      'NEVER-RAN': () => `rotina de frescor NUNCA rodou (ledger vazio) — rode o dispatch logado (--manifest → DesignSync.get_file → --compare snap.json --check --ledger).`,
      'OVERDUE': () => `rotina de frescor FORA do SLA (${SLA_DAYS}d) — ${detail}. Rode o dispatch logado.`,
      'LAST-STALE': () => `última rodada achou STALE não-resolvido — ${detail} (${(r.last.staleList || []).join(', ')}). Re-exporte do Cowork e rode de novo.`,
    }[r.veredito]();
    console.error(`✗ ${msg}`);
    process.exit(1);
  }

  const manifest = buildManifest(ROOT, { all });

  if (cmpIdx === -1) {
    process.stdout.write(JSON.stringify({ generated: 'cowork-mirror-freshness manifest v2 (path completo · sha256 normalizado)', files: manifest }, null, 2) + '\n');
    process.stderr.write(
      `\n  ${manifest.length} arquivo(s)-âncora do espelho pra conferir contra o vivo (Cowork 019dcfd3).\n` +
      `  Próximo passo (agente logado): DesignSync.get_file por path → snapshot {relPath: contentHash} → --compare snap.json.\n` +
      `  ATENÇÃO: o snapshot DEVE usar a MESMA normalização (importe contentHash/normalize deste módulo).\n\n`,
    );
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
