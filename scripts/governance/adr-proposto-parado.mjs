#!/usr/bin/env node
// @ts-check
/**
 * adr-proposto-parado.mjs — sentinela: decisão PENDENTE que ninguém vê acaba não sendo feita.
 *
 * O ELO QUE FALTAVA no ciclo de ratificação. Um ADR nasce `proposto` e a ratificação depende de
 * Wagner LEMBRAR — não há máquina que a surfe. Resultado observado (2026-07-09, Wagner: "isso vai
 * acabar não sendo feito"): a decisão apodrece invisível. Pior: existe uma classe de bug ainda mais
 * grave — ADR JÁ DECIDIDO (aceito/recusado) parado em `memory/decisions/proposals/`, onde o sync do
 * MCP nunca varre (glob NÃO-recursivo em IndexarMemoryGitParaDb) → **lei aceita invisível ao
 * decisions-search**. Prova viva no dia em que esta sentinela nasceu: 0320 `aceito` + 1 `accepted`
 * + 1 `decided` + 1 `rejected`, todos presos em proposals/; e 0314/0319 propostos NUMERADOS lá.
 *
 * TRÊS CHECKS (determinísticos, sem LLM):
 *   A (🔴 grave) — status DECIDIDO (`aceito|recusado|accepted|decided|rejected`) dentro de
 *                  proposals/ → lei invisível ao MCP. Conserto: mover pro top-level + `--write`.
 *   B (🟡)       — arquivo NUMERADO (NNNN-) em proposals/ ainda proposto → número reservado +
 *                  invisível; quando aceito vai driftar como o 0320. Conserto: decidir ou mover.
 *   C (🟡 idade) — `status: proposto` no top-level há > N dias (default 14) → ratificação parada.
 *                  `kind: feature-wish` NÃO conta (dormência INTENCIONAL — ADR 0105: hipótese sem
 *                  sinal vira feature-wish, não pendência). `_TEMPLATE.md` fora.
 *
 * O QUE ISTO NÃO É:
 *   · NÃO é presence-gate (L-24) — mede estado+tempo, não exige arquivo no diff.
 *   · NÃO é required — ADR 0314: required = só Tier-0. Ratificação é higiene de governança →
 *     advisory/reporter; o surfacing é o valor (::warning no PR + heartbeat semanal do workflow
 *     de staleness + candidato a FLAG do Daily Brief — follow-up server-side, ver US-GOV-052).
 *
 * USO:
 *   node scripts/governance/adr-proposto-parado.mjs             (tabela; exit 0 — reporter)
 *   node scripts/governance/adr-proposto-parado.mjs --json      (JSON pro Daily Brief)
 *   node scripts/governance/adr-proposto-parado.mjs --strict    (exit 1 se check A acusar — opt-in)
 *   node scripts/governance/adr-proposto-parado.mjs --selftest  (bite/release hermético — CI)
 *   OIMPRESSO_ADR_PROPOSTO_DIAS=7 node …                        (limiar tunável do check C)
 *
 * Refs: ADR 0329 (doutrina — Propriedade 5 "auto-fresca" aplicada à própria governança) ·
 *       ADR 0257 (lifecycle) · ADR 0314 (required só Tier-0) · briefing-code-staleness.mjs e
 *       visual-comparison-staleness.mjs (a família de reporters onde este se pluga).
 */
import { readFileSync, readdirSync, existsSync, realpathSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = process.cwd();
const DEC = join(ROOT, 'memory', 'decisions');
const PROPS = join(DEC, 'proposals');
const DEFAULT_DIAS = Number(process.env.OIMPRESSO_ADR_PROPOSTO_DIAS) || 14;

// Vocabulário real do corpus (o legado mistura EN/PT — a sentinela reconhece os dois,
// mas NÃO valida enum: isso é papel do memory-schema-gate; aqui só classificamos pendência).
const DECIDIDOS = new Set(['aceito', 'accepted', 'decided', 'recusado', 'rejected', 'recusada']);
const PROPOSTOS = new Set(['proposto', 'proposed', 'proposal', 'rascunho', 'draft']);

/** frontmatter mínimo (status/kind/decided_at) — regex tolerante, NÚCLEO PURO.
 *  Corta comentário inline do YAML (`kind: feature-wish # era lifecycle…` — caso real 0125). */
export function lerCampos(content) {
  const grab = (k) => {
    const m = new RegExp(`^${k}:\\s*["']?([^"'#\\n]+)`, 'm').exec(content || '');
    return m ? m[1].trim() : null;
  };
  const dm = /^decided_at:\s*["']?(\d{4}-\d{2}-\d{2})/m.exec(content || '');
  return { status: (grab('status') || '').toLowerCase(), kind: (grab('kind') || '').toLowerCase(), decidedAt: dm ? dm[1] : null };
}

/**
 * classify — NÚCLEO PURO (sem FS/relógio; o selftest injeta `hojeIso`).
 * @param {{ emProposals:boolean, numerado:boolean, status:string, kind:string, decidedAt:(string|null), hojeIso:string, dias?:number }} p
 * @returns {{ check:('A'|'B'|'C'|null), idadeDias:(number|null) }}
 */
export function classify({ emProposals, numerado, status, kind, decidedAt, hojeIso, dias = DEFAULT_DIAS }) {
  if (emProposals && DECIDIDOS.has(status)) return { check: 'A', idadeDias: null };
  if (emProposals && numerado && PROPOSTOS.has(status)) return { check: 'B', idadeDias: null };
  if (!emProposals && status === 'proposto') {
    if (kind === 'feature-wish') return { check: null, idadeDias: null }; // dormência intencional (ADR 0105)
    if (!decidedAt) return { check: null, idadeDias: null };              // sem data → não mede (cobertura, não pendência)
    const idade = Math.round((Date.parse(hojeIso + 'T00:00:00Z') - Date.parse(decidedAt + 'T00:00:00Z')) / 86400000);
    if (idade > dias) return { check: 'C', idadeDias: idade };
    return { check: null, idadeDias: idade };
  }
  return { check: null, idadeDias: null };
}

// ── camada FS (impura — só no run real) ──────────────────────────────────────
export function scan(hojeIso, dias = DEFAULT_DIAS) {
  const rows = [];
  const lerDir = (dir, emProposals) => {
    if (!existsSync(dir)) return;
    for (const f of readdirSync(dir)) {
      if (!f.endsWith('.md') || f.startsWith('_')) continue;
      let content = '';
      try { content = readFileSync(join(dir, f), 'utf8'); } catch { continue; }
      const { status, kind, decidedAt } = lerCampos(content);
      const numerado = /^\d{4}-[a-z]/.test(f); // NNNN-slug (não confundir com data 2026-…: exige letra após o hífen? datas são 2026-05-24 → dígito. ok)
      const { check, idadeDias } = classify({ emProposals, numerado, status, kind, decidedAt, hojeIso, dias });
      if (check) rows.push({ arquivo: (emProposals ? 'proposals/' : '') + f, status, kind: kind || '—', decidedAt, check, idadeDias });
    }
  };
  lerDir(DEC, false);
  lerDir(PROPS, true);
  return rows;
}

// ── run (CLI) ────────────────────────────────────────────────────────────────
function run() {
  const JSON_OUT = process.argv.includes('--json');
  const STRICT = process.argv.includes('--strict');
  const hoje = new Date().toISOString().slice(0, 10);
  const rows = scan(hoje);
  const A = rows.filter((r) => r.check === 'A');
  const B = rows.filter((r) => r.check === 'B');
  const C = rows.filter((r) => r.check === 'C').sort((a, b) => (b.idadeDias ?? 0) - (a.idadeDias ?? 0));

  if (JSON_OUT) {
    console.log(JSON.stringify({ gate: 'adr-proposto-parado', dias: DEFAULT_DIAS, A, B, C: C.slice(0, 15), C_total: C.length }, null, 2));
    return STRICT && A.length ? 1 : 0;
  }

  console.log(`\n  ADR PENDENTE — decisão parada que ninguém vê acaba não sendo feita (limiar C: ${DEFAULT_DIAS}d)`);
  console.log('  ' + '─'.repeat(78));
  if (A.length) {
    console.log(`  🔴 A — DECIDIDO preso em proposals/ (lei INVISÍVEL ao MCP — mover pro top-level):`);
    for (const r of A) console.log(`     ${r.arquivo}  status=${r.status}`);
  }
  if (B.length) {
    console.log(`  🟡 B — numerado em proposals/ ainda proposto (número reservado, invisível):`);
    for (const r of B) console.log(`     ${r.arquivo}`);
  }
  if (C.length) {
    console.log(`  🟡 C — proposto no top-level há >${DEFAULT_DIAS}d sem ratificação (${C.length} no total; 10 mais velhos):`);
    for (const r of C.slice(0, 10)) console.log(`     ${r.arquivo}  ${r.idadeDias}d (decided_at ${r.decidedAt})`);
  }
  if (!A.length && !B.length && !C.length) console.log('  🟢 nenhuma pendência de ratificação detectada.');
  console.log('  ' + '─'.repeat(78));
  console.log(`  A:${A.length} · B:${B.length} · C:${C.length} · feature-wish excluído (dormência intencional, ADR 0105)`);
  console.log('  ADVISORY (ADR 0314). Ação: ratificar (flip status in-place) ou mover de proposals/ + adr-index --write.\n');

  if (process.env.GITHUB_ACTIONS === 'true') {
    for (const r of A) console.log(`::warning title=ADR decidido INVISÍVEL ao MCP (${r.arquivo})::status=${r.status} preso em proposals/ — o sync não varre subpasta; mover pro top-level memory/decisions/ + rodar adr-index-generate --write.`);
  }
  return STRICT && A.length ? 1 : 0;
}

// ── selftest hermético (bite/release do núcleo puro) ─────────────────────────
function selftest() {
  const fails = [];
  const eq = (nome, got, exp) => { if (JSON.stringify(got) !== JSON.stringify(exp)) fails.push(`${nome}: got ${JSON.stringify(got)} exp ${JSON.stringify(exp)}`); };
  const H = '2026-07-09';

  // A MORDE: aceito preso em proposals/ (o caso 0320) — e nas variantes EN do legado.
  eq('A aceito/proposals', classify({ emProposals: true, numerado: true, status: 'aceito', kind: 'decision', decidedAt: '2026-06-01', hojeIso: H }).check, 'A');
  eq('A accepted(EN)/proposals', classify({ emProposals: true, numerado: false, status: 'accepted', kind: '', decidedAt: null, hojeIso: H }).check, 'A');
  eq('A rejected(EN)/proposals', classify({ emProposals: true, numerado: false, status: 'rejected', kind: '', decidedAt: null, hojeIso: H }).check, 'A');
  // B MORDE: numerado proposto em proposals/ (o caso 0314).
  eq('B numerado proposto/proposals', classify({ emProposals: true, numerado: true, status: 'proposto', kind: 'decision', decidedAt: '2026-06-30', hojeIso: H }).check, 'B');
  // C MORDE: proposto velho no top-level (o caso 0299, 17d).
  eq('C proposto 17d top-level', classify({ emProposals: false, numerado: true, status: 'proposto', kind: 'decision', decidedAt: '2026-06-22', hojeIso: H, dias: 14 }), { check: 'C', idadeDias: 17 });
  // LIBERA: feature-wish é dormência intencional; proposto recente; aceito no top-level; kind ausente recente.
  eq('libera feature-wish velho', classify({ emProposals: false, numerado: true, status: 'proposto', kind: 'feature-wish', decidedAt: '2026-04-24', hojeIso: H }).check, null);
  eq('libera proposto recente (0329 hoje)', classify({ emProposals: false, numerado: true, status: 'proposto', kind: 'meta', decidedAt: '2026-07-09', hojeIso: H, dias: 14 }).check, null);
  eq('libera aceito top-level', classify({ emProposals: false, numerado: true, status: 'aceito', kind: 'decision', decidedAt: '2026-05-01', hojeIso: H }).check, null);
  eq('libera sem decided_at (cobertura≠pendência)', classify({ emProposals: false, numerado: true, status: 'proposto', kind: 'decision', decidedAt: null, hojeIso: H }).check, null);
  // lerCampos: extrai status/kind/decided_at com e sem aspas — E corta comentário inline (caso real 0125).
  eq('lerCampos', lerCampos('---\nstatus: proposto\nkind: meta\ndecided_at: "2026-07-09"\n---'), { status: 'proposto', kind: 'meta', decidedAt: '2026-07-09' });
  eq('lerCampos comentário inline (0125)', lerCampos('---\nkind: feature-wish # era lifecycle feature_wish\n---').kind, 'feature-wish');

  if (fails.length) { console.error('SELFTEST FALHOU:\n - ' + fails.join('\n - ')); process.exit(1); }
  console.log('✓ adr-proposto-parado selftest OK — A/B/C mordem e liberam certo (11 casos, ancorados nos ADRs reais 0320/0314/0299/0329/0125).');
  process.exit(0);
}

const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();
if (isMain) {
  if (process.argv.includes('--selftest')) selftest();
  else process.exit(run());
}
