#!/usr/bin/env node
// @ts-check
/**
 * visual-comparison-staleness.mjs — sentinela: o `<tela>-visual-comparison.md` ficou atrás da TELA?
 *
 * O ÓRFÃO DO FRESCOR do loop de design. Os outros artefatos do loop têm sentinela:
 *   · BRIEFING.md  → briefing-code-staleness.mjs (porta × superfície do módulo)
 *   · charter      → "charters apodrecendo" (Daily Brief)
 *   · SPEC.md      → anchor-lint (fidelidade spec↔código)
 * O `<tela>-visual-comparison.md` — artefato OBRIGATÓRIO da skill `mwart-comparative`
 * (insumo da Fase 1 do RUNBOOK-aplicar-prototipo) — NÃO tinha nenhum. Ele é append-only
 * por rounds e só atualiza quando alguém roda a skill de novo; a Fase 5 (fechar o loop)
 * atualizava charter/SPEC/BRIEFING mas ESQUECIA este. Resultado: envelhece calado.
 *
 * INCIDENTE-ÂNCORA (2026-07-09, Wagner: "acho que já está velho, quem atualiza?"): o
 * `financeiro-unificado-visual-comparison.md` parou em `last_updated: 2026-07-07`; a tela
 * `Financeiro/Unificado/Index.tsx` mudou em 2026-07-09 (#3988, "header e footer sem tint
 * bg-muted") — mudança visual que o doc não reflete, e NADA alertou.
 *
 * O QUE ISTO NÃO É (proibicoes.md §5 — não re-propor padrões mortos):
 *   · NÃO é presence-gate ("o comparison tem que estar no diff do PR") — padrão REJEITADO
 *     2026-07-01 (charter-sync-gate) + L-24 "presença ≠ correção". Aqui MEDIMOS a derivada
 *     temporal (doc parado enquanto a tela anda), não exigimos edição.
 *   · NÃO é required — ADR 0314: required = só Tier 0 (dinheiro/PII/multi-tenant/fiscal).
 *     Frescor de comparativo visual é HIGIENE → advisory/reporter, nunca bloqueia.
 *
 * SINAL (determinístico, sem LLM, sem deps novas):
 *   doorDate = data DECLARADA no doc (`last_updated:`/`date:` do frontmatter; fallback data-git).
 *   codeDate = data-git do `inertia_target:` (a tela `.tsx` que o doc compara).
 *   stale ⇔ (codeDate − doorDate) > N dias (default 30).
 *   Reusa `classifyCodeStaleness` do briefing-code-staleness (MESMA derivada, alvo diferente —
 *   por-tela em vez de por-módulo). Docs sem `inertia_target` ou sem data → não-avaliados
 *   (cobertura, não staleness — reportados à parte, nunca falso-positivo).
 *
 * USO:
 *   node scripts/governance/visual-comparison-staleness.mjs           (tabela; exit 0 — reporter)
 *   node scripts/governance/visual-comparison-staleness.mjs --json    (JSON pro Daily Brief)
 *   node scripts/governance/visual-comparison-staleness.mjs --strict  (exit 1 se stale — opt-in local)
 *   node scripts/governance/visual-comparison-staleness.mjs --selftest (bite/release hermético — CI)
 *   OIMPRESSO_VISCOMP_STALE_DAYS=21 node …                            (limiar tunável)
 *
 * Refs: briefing-code-staleness.mjs (eixo irmão · núcleo reusado) · skill mwart-comparative
 *       (quem gera o doc) · RUNBOOK-aplicar-prototipo Fase 1/Fase 5 · ADR 0314 · proibicoes §5.
 */
import { execSync } from 'node:child_process';
import { existsSync, readFileSync, realpathSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { classifyCodeStaleness } from './briefing-code-staleness.mjs';

const ROOT = process.cwd();
const DEFAULT_STALE_DAYS = Number(process.env.OIMPRESSO_VISCOMP_STALE_DAYS) || 30;

/**
 * declaredComparisonDate — data DECLARADA no doc (`last_updated:`/`date:`/`updated_at:`; a maior).
 * NÚCLEO PURO e testável. Ignora datas malformadas.
 * @param {string} content
 * @returns {string|null} `YYYY-MM-DD` ou null.
 */
export function declaredComparisonDate(content) {
  if (!content) return null;
  const dates = [];
  const push = (m) => { if (m && /^\d{4}-\d{2}-\d{2}$/.test(m[1])) dates.push(m[1]); };
  for (const key of ['last_updated', 'date', 'updated_at']) {
    push(new RegExp(`^${key}:\\s*["']?(\\d{4}-\\d{2}-\\d{2})`, 'm').exec(content));
  }
  if (!dates.length) return null;
  return dates.sort().at(-1); // ISO ordena lexicograficamente = cronologicamente
}

/**
 * comparisonTarget — a tela `.tsx` que o doc compara (campo `inertia_target:`). NÚCLEO PURO.
 * Tolera sufixos ("(charter draft)", "(charter)") — pega só o path até `.tsx`.
 * @param {string} content
 * @returns {string|null} path repo-relativo ou null.
 */
export function comparisonTarget(content) {
  if (!content) return null;
  const m = /^inertia_target:\s*["']?(\S+?\.tsx)\b/m.exec(content);
  return m ? m[1] : null;
}

// ── camada git/FS (impura — só no run real, nunca no self-test) ──────────────
function gitDate(relPath) {
  try {
    return execSync(`git log -1 --format=%cs -- "${relPath}"`, {
      cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'],
    }).toString().trim() || null;
  } catch { return null; }
}
function listDocs() {
  try {
    // lista tudo em memory/requisitos e filtra em JS (robusto a quirks de glob do git ls-files).
    return execSync('git ls-files memory/requisitos', { cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'] })
      .toString().trim().split('\n')
      .filter((p) => p.endsWith('-visual-comparison.md'));
  } catch { return []; }
}

/**
 * scan — 1 linha por `<tela>-visual-comparison.md` com alvo declarado. run() decide formato/exit.
 */
export function scan(staleDays = DEFAULT_STALE_DAYS) {
  const rows = [];
  for (const docRel of listDocs()) {
    let content = '';
    try { content = readFileSync(join(ROOT, docRel), 'utf8'); } catch { /* ilegível */ }
    const doorDeclared = declaredComparisonDate(content);
    const doorDate = doorDeclared || gitDate(docRel);
    const doorSource = doorDeclared ? 'declarado' : (doorDate ? 'git-fallback' : null);
    const targetRel = comparisonTarget(content);
    const targetExists = !!(targetRel && existsSync(join(ROOT, targetRel)));
    const codeDate = targetExists ? gitDate(targetRel) : null;
    const { evaluated, stale, gapDays } = classifyCodeStaleness({
      hasDoor: true, moduleCodeExists: targetExists, doorDate, codeDate, staleDays,
    });
    rows.push({ docRel, targetRel, targetExists, doorDate, doorSource, codeDate, evaluated, stale, gapDays });
  }
  return rows;
}

// ── run (CLI) ────────────────────────────────────────────────────────────────
function run() {
  const JSON_OUT = process.argv.includes('--json');
  const STRICT = process.argv.includes('--strict');
  const staleDays = DEFAULT_STALE_DAYS;
  const rows = scan(staleDays);
  const stale = rows.filter((r) => r.stale).sort((a, b) => (b.gapDays ?? 0) - (a.gapDays ?? 0));
  const semAlvo = rows.filter((r) => !r.targetRel).map((r) => r.docRel);

  if (JSON_OUT) {
    console.log(JSON.stringify({
      gate: 'visual-comparison-staleness',
      axis: '<tela>-visual-comparison.md data declarada (last_updated/date) vs inertia_target .tsx data-git',
      staleDays,
      evaluated: rows.filter((r) => r.evaluated).length,
      stale: stale.map((r) => ({ doc: r.docRel, tela: r.targetRel, gapDays: r.gapDays, doorDate: r.doorDate, doorSource: r.doorSource, codeDate: r.codeDate })),
      semAlvo,
    }, null, 2));
    return stale.length && STRICT ? 1 : 0;
  }

  console.log(`\n  VISUAL-COMPARISON × TELA — comparativo atrás da tela viva (limiar ${staleDays}d)`);
  console.log(`  eixo: memory/requisitos/**/<tela>-visual-comparison.md  vs  inertia_target .tsx (data-git)`);
  console.log('  ' + '─'.repeat(76));
  if (!stale.length) {
    console.log('  🟢 nenhum comparativo atrás da tela além do limiar.');
  } else {
    for (const r of stale) {
      console.log(`  🟡 ${r.targetRel}`);
      console.log(`     doc ${r.doorDate} (${r.doorSource}) · tela ${r.codeDate} · atraso ${r.gapDays}d  [${r.docRel}]`);
    }
  }
  console.log('  ' + '─'.repeat(76));
  console.log(`  ${stale.length} comparativo(s) stale · ${rows.filter((r) => r.evaluated).length} avaliados · ${semAlvo.length} sem inertia_target`);
  console.log('  ADVISORY (ADR 0314 — higiene, nunca required). Ação: rode a skill mwart-comparative na tela (novo round).');
  console.log('  NÃO é presence-gate: mede a derivada doc×tela, não exige o comparison no diff (proibicoes §5 + L-24).\n');

  if (process.env.GITHUB_ACTIONS === 'true') {
    for (const r of stale) {
      console.log(`::warning title=Visual-comparison atrás da tela (${r.targetRel})::${r.docRel} está ${r.gapDays} dias atrás da tela (doc ${r.doorDate} vs tela ${r.codeDate}). Rode a skill mwart-comparative pra abrir um novo round.`);
    }
  }

  return stale.length && STRICT ? 1 : 0;
}

// ── self-test hermético (bite/release do núcleo puro — prova que morde, sem git/FS) ──
function selftest() {
  const fails = [];
  const eq = (nome, got, exp) => { if (JSON.stringify(got) !== JSON.stringify(exp)) fails.push(`${nome}: got ${JSON.stringify(got)} exp ${JSON.stringify(exp)}`); };

  // declaredComparisonDate — pega o MAIOR; null quando ausente/malformado.
  eq('data last_updated', declaredComparisonDate('---\nlast_updated: 2026-07-07\ndate: 2026-05-09\n---'), '2026-07-07');
  eq('data só date', declaredComparisonDate('---\ndate: 2026-05-21\n---'), '2026-05-21');
  eq('data ausente', declaredComparisonDate('---\nfoo: bar\n---'), null);
  eq('data malformada', declaredComparisonDate('---\nlast_updated: ontem\n---'), null);

  // comparisonTarget — extrai o .tsx, tolera sufixo; null sem campo.
  eq('alvo simples', comparisonTarget('inertia_target: resources/js/Pages/Financeiro/Unificado/Index.tsx'), 'resources/js/Pages/Financeiro/Unificado/Index.tsx');
  eq('alvo com sufixo', comparisonTarget('inertia_target: resources/js/Pages/Cliente/Create.tsx (charter draft)'), 'resources/js/Pages/Cliente/Create.tsx');
  eq('alvo ausente', comparisonTarget('foo: bar'), null);

  // classifyCodeStaleness (importado do irmão) — a derivada MORDE e libera certo.
  const bite = classifyCodeStaleness({ hasDoor: true, moduleCodeExists: true, doorDate: '2026-07-07', codeDate: '2026-07-09', staleDays: 1 });
  eq('MORDE: tela 2d à frente > limiar 1', [bite.evaluated, bite.stale, bite.gapDays], [true, true, 2]);
  const release = classifyCodeStaleness({ hasDoor: true, moduleCodeExists: true, doorDate: '2026-07-09', codeDate: '2026-07-07', staleDays: 1 });
  eq('LIBERA: doc mais novo que a tela', [release.evaluated, release.stale], [true, false]);
  const semAlvo = classifyCodeStaleness({ hasDoor: true, moduleCodeExists: false, doorDate: '2026-07-07', codeDate: null, staleDays: 1 });
  eq('NÃO-AVALIA: sem tela no disco (não vira falso-positivo)', [semAlvo.evaluated, semAlvo.stale], [false, false]);

  if (fails.length) { console.error('SELFTEST FALHOU:\n - ' + fails.join('\n - ')); process.exit(1); }
  console.log(`✓ visual-comparison-staleness selftest OK — extração (data/alvo) + derivada bite/release provadas (${10} casos).`);
  process.exit(0);
}

// ── main (só quando executado direto; importável p/ self-test sem rodar) ──────
const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();

if (isMain) {
  if (process.argv.includes('--selftest')) selftest();
  else process.exit(run());
}
