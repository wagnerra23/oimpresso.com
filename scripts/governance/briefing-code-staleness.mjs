#!/usr/bin/env node
// @ts-check
/**
 * briefing-code-staleness.mjs — sentinela: a PORTA (BRIEFING.md) ficou atrás do CÓDIGO?
 *
 * O EIXO QUE FALTAVA. Os sentinelas de staleness que já existem medem a porta
 * contra DOCS IRMÃOS (doc-vs-doc), nunca contra o CÓDIGO:
 *   · knowledge-drift.mjs  → `stale` = BRIEFING.md mais velho que o doc .md mais
 *                            novo de memory/requisitos/<Mod>/ (>30d).
 *   · sdd-scorecard.mjs measureDistillerFreshness → `distilled_at:` da porta vs
 *                            data-git do doc mais novo do módulo (>7d).
 * Ambos comparam PORTA × DOCS. Nenhum compara PORTA × CÓDIGO — e é exatamente
 * esse eixo que apodrece calado: quando o código anda e NEM a porta NEM os docs
 * irmãos são atualizados, o comparativo doc-vs-doc fica verde mentindo.
 *
 * INCIDENTE-ÂNCORA (2026-07-03, #3714): o BRIEFING do Compras ficou "scaffold
 * 05-21" por 41 dias / 18 commits enquanto o módulo virou grade 59 + cockpit
 * live. A regra "✅ BRIEFING atualizado em todo PR" + a skill brief-update (Tier
 * B soft) não dispararam, e o eixo doc-vs-doc não pegou (docs irmãos também
 * congelados). Este sentinela mede a derivada certa: porta × superfície de código.
 *
 * O QUE ISTO NÃO É (proibicoes.md §5 — não re-propor padrões mortos):
 *   · NÃO é presence-gate ("BRIEFING tem que estar no diff do PR") — padrão
 *     REJEITADO 2026-07-01 (charter-sync-gate) + L-24 "presença ≠ correção".
 *     Presença prova que o arquivo mudou, não que está certo. Aqui MEDIMOS a
 *     derivada temporal (porta parada enquanto código anda), não exigimos edição.
 *   · NÃO é required — ADR 0314: required = só Tier 0 (dinheiro/PII/multi-tenant/
 *     fiscal). Frescor de BRIEFING é HIGIENE → advisory/reporter, nunca bloqueia.
 *
 * SINAL (determinístico, sem LLM, sem deps):
 *   doorDate = data DECLARADA no BRIEFING (frontmatter `updated_at:`/`distilled_at:`/
 *              `reviewed_at:` ou rodapé `**Atualizado:**`; fallback data-git só se
 *              nenhuma existir) — o último refresh que um humano/destilador afirmou.
 *   codeDate = data-git (committer %cs) do último commit que tocou a SUPERFÍCIE do
 *              módulo = Modules/<Mod>/  ∪  resources/js/Pages/<Mod>/. (Código não
 *              declara data própria; a data-git é o "quando o código andou" honesto.)
 *   stale ⇔ (codeDate − doorDate) > N dias (default 30).
 *
 *   Por que SUPERFÍCIE = backend ∪ frontend, e não só Modules/<Mod>/ (como a
 *   descrição literal da tarefa dizia)? Prova no incidente: comparando SÓ
 *   Modules/Compras/ o atraso era 18d (05-21→06-08) e passaria batido; incluindo
 *   resources/js/Pages/Compras/ (onde vive o cockpit) o atraso é 41d (05-21→
 *   07-01) — o número real dos "6 semanas". O cockpit é uma Page, então a porta
 *   tem que refletir o front também.
 *
 *   doorDate vem da DATA DECLARADA da porta (frontmatter `updated_at:`/
 *   `distilled_at:`/`reviewed_at:` ou rodapé `**Atualizado:**`), com fallback pra
 *   data-git só quando NÃO há data declarada. Aprendido no ground-truth do próprio
 *   incidente: a data-git do BRIEFING do Compras foi carimbada em 06-08 por commits
 *   MECÂNICOS (restore de squash + geração de MapaTelas) que NÃO refrescaram o
 *   conteúdo — o texto seguia "scaffold 05-21". Usar data-git dava atraso 23d (<30,
 *   passa batido); a data declarada `updated_at: 2026-05-21` dá 41d (morde). Ou
 *   seja: a data-git mente pra CIMA (commit mecânico), a data declarada é a
 *   verdade do último refresh humano. Esquecer de bumpar a data declarada mantém a
 *   porta velha → o sentinela marca corretamente (é o próprio incidente).
 *
 * USO:
 *   node scripts/governance/briefing-code-staleness.mjs           (tabela; exit 0 — reporter)
 *   node scripts/governance/briefing-code-staleness.mjs --json    (JSON pro Daily Brief)
 *   node scripts/governance/briefing-code-staleness.mjs --strict  (exit 1 se stale — opt-in local, NUNCA required)
 *   node scripts/governance/briefing-code-staleness.mjs --strict-coverage  (exit 1 se MÓDULO BACKEND sem BRIEFING — cobertura de existência, advisory-first)
 *   OIMPRESSO_BRIEFING_CODE_STALE_DAYS=21 node …                  (limiar tunável)
 *
 * Refs: ADR 0256 (Knowledge Survival) · ADR 0314 (required = só Tier 0) ·
 *       knowledge-drift.mjs (eixo doc-vs-doc irmão) · sdd-scorecard.mjs
 *       measureDistillerFreshness · proibicoes.md §5 (charter-sync-gate rejeitado).
 */
import { readdirSync, existsSync, realpathSync, readFileSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = process.cwd();
const REQ = join(ROOT, 'memory', 'requisitos');

// Limiar: 30 dias — alinhado ao STALE_DAYS de knowledge-drift.mjs (eixo irmão),
// pra não introduzir mais um número mágico. BRIEFING é "1 página, atualizada por
// PR significativo" (skill brief-update): 30 dias de código andando com a porta
// parada = porta apodrecendo. Tunável via env (ex: 21 = mais lead-time / +ruído).
const DEFAULT_STALE_DAYS = Number(process.env.OIMPRESSO_BRIEFING_CODE_STALE_DAYS) || 30;

/**
 * classifyCodeStaleness — NÚCLEO PURO e determinístico (sem git/FS). É o que o
 * self-test exercita (prova bite/release sem depender do disco nem do git).
 *
 * @param {{ hasDoor:boolean, moduleCodeExists:boolean, doorDate:(string|null), codeDate:(string|null), staleDays?:number }} p
 *   doorDate/codeDate no formato ISO `YYYY-MM-DD` (committer %cs) ou null.
 * @returns {{ evaluated:boolean, stale:boolean, gapDays:(number|null) }}
 */
export function classifyCodeStaleness({ hasDoor, moduleCodeExists, doorDate, codeDate, staleDays = DEFAULT_STALE_DAYS }) {
  // Sem porta, sem código no disco, ou sem alguma das datas → não dá pra medir a
  // derivada. NÃO é "stale" (evita falso-positivo). Porta ausente é um sinal de
  // COBERTURA distinto (já reportado por knowledge-drift `door: NÃO`), não staleness.
  if (!hasDoor || !moduleCodeExists || !doorDate || !codeDate) {
    return { evaluated: false, stale: false, gapDays: null };
  }
  const gapDays = Math.round(
    (Date.parse(codeDate + 'T00:00:00Z') - Date.parse(doorDate + 'T00:00:00Z')) / 86400000,
  );
  // stale ⇔ código estritamente MAIS de `staleDays` à frente da porta.
  // Porta ≥ código (gap ≤ 0, porta refrescada depois do último commit de código) → fresco.
  return { evaluated: true, stale: gapDays > staleDays, gapDays };
}

/**
 * isBriefingCoverageGap — sinal de COBERTURA DE EXISTÊNCIA (não frescor): um
 * MÓDULO DE BACKEND real (`Modules/<X>/` no disco) que NÃO tem `BRIEFING.md` = gap.
 *
 * Por que só BACKEND (hasBackend), não `Pages/<X>` só-frontend: uma área só-tela
 * (ex: `User/Perfil`, sem `Modules/User`) NÃO é módulo de negócio e é coberta pelo
 * trio charter/casos da tela — exigir BRIEFING dela seria falso-positivo. `scan()`
 * já escopa a área a quem tem código no disco; aqui refinamos pra o backend.
 *
 * Por que isto é ENFORÇÁVEL (diferente do frescor): o sinal é a EXISTÊNCIA de um
 * dir de módulo + um arquivo — NÃO-gameável por uma data auto-escrita (o furo que
 * bane o frescor de virar gate required, proibicoes §5 charter-sync-gate). É a
 * mesma classe aceita do casos-gate G-1 (tela nova sem trio). Nasce ADVISORY
 * (ADR 0314 — required = só Tier-0; promover = emenda + flip [W], nunca no calado).
 *
 * @param {{ hasBackend:boolean, hasDoor:boolean }} p
 * @returns {boolean} true se é gap de cobertura de briefing.
 */
export function isBriefingCoverageGap({ hasBackend, hasDoor }) {
  return !!hasBackend && !hasDoor;
}

/**
 * declaredDoorDate — extrai a data DECLARADA do conteúdo do BRIEFING (o último
 * refresh que um humano/destilador afirmou), NÚCLEO PURO e testável. Pega o MAIOR
 * (mais recente) entre os carimbos conhecidos; ignora datas malformadas.
 *   · frontmatter `updated_at:` / `distilled_at:` / `reviewed_at:` (quoted ou não)
 *   · rodapé `**Atualizado:** YYYY-MM-DD` (estilo legado sem frontmatter)
 * @param {string} content
 * @returns {string|null} `YYYY-MM-DD` ou null se nenhum carimbo válido.
 */
export function declaredDoorDate(content) {
  if (!content) return null;
  const dates = [];
  const push = (m) => { if (m && /^\d{4}-\d{2}-\d{2}$/.test(m[1])) dates.push(m[1]); };
  for (const key of ['updated_at', 'distilled_at', 'reviewed_at']) {
    push(new RegExp(`^${key}:\\s*["']?(\\d{4}-\\d{2}-\\d{2})`, 'm').exec(content));
  }
  push(/\*\*Atualizado:\*\*\s*(\d{4}-\d{2}-\d{2})/.exec(content));
  if (!dates.length) return null;
  return dates.sort().at(-1); // ISO ordena lexicograficamente = cronologicamente
}

// ── camada git/FS (impura — só no run real, nunca no self-test) ──────────────
function gitDate(relPath) {
  try {
    return execSync(`git log -1 --format=%cs -- "${relPath}"`, {
      cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'],
    }).toString().trim() || null;
  } catch { return null; }
}
function commitsSince(dateStr, relPaths) {
  if (!dateStr || !relPaths.length) return 0;
  try {
    const paths = relPaths.map((p) => `"${p}"`).join(' ');
    // --since inclui o dia da porta; o commit da própria porta não toca a
    // superfície, então não infla a contagem de "commits de código à frente".
    const out = execSync(`git log --oneline --since="${dateStr} 00:00:00" -- ${paths}`, {
      cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'],
    }).toString().trim();
    return out ? out.split('\n').length : 0;
  } catch { return 0; }
}

/**
 * scan — varre memory/requisitos/<Mod>/ e classifica cada módulo com código no disco.
 * Retorna linhas cruas (o run() decide formato/exit).
 */
export function scan(staleDays = DEFAULT_STALE_DAYS) {
  const rows = [];
  if (!existsSync(REQ)) return rows;
  for (const ent of readdirSync(REQ, { withFileTypes: true })) {
    if (!ent.isDirectory()) continue;
    const mod = ent.name;
    const briefingRel = `memory/requisitos/${mod}/BRIEFING.md`;
    const backendRel = `Modules/${mod}`;
    const frontendRel = `resources/js/Pages/${mod}`;

    const hasDoor = existsSync(join(ROOT, briefingRel));
    const hasBackend = existsSync(join(ROOT, backendRel));
    const hasFrontend = existsSync(join(ROOT, frontendRel));
    const moduleCodeExists = hasBackend || hasFrontend;

    // requisitos dir SEM módulo no disco (_DesignSystem, Infra, _Governanca…) → fora de escopo.
    if (!moduleCodeExists) continue;

    const surface = [hasBackend && backendRel, hasFrontend && frontendRel].filter(Boolean);

    // doorDate = data DECLARADA (frontmatter/rodapé); fallback data-git só se ausente.
    let doorDate = null, doorSource = null;
    if (hasDoor) {
      let content = '';
      try { content = readFileSync(join(ROOT, briefingRel), 'utf8'); } catch { /* ilegível */ }
      doorDate = declaredDoorDate(content);
      doorSource = doorDate ? 'declarado' : null;
      if (!doorDate) { doorDate = gitDate(briefingRel); doorSource = doorDate ? 'git-fallback' : null; }
    }

    let codeDate = null;
    for (const p of surface) { const d = gitDate(p); if (d && (!codeDate || d > codeDate)) codeDate = d; }

    const { evaluated, stale, gapDays } = classifyCodeStaleness({ hasDoor, moduleCodeExists, doorDate, codeDate, staleDays });
    rows.push({
      mod, hasDoor, hasBackend, doorDate, doorSource, codeDate, evaluated, stale, gapDays,
      commitsAhead: stale ? commitsSince(doorDate, surface) : 0,
      surface,
    });
  }
  return rows;
}

// ── run (CLI) ────────────────────────────────────────────────────────────────
function run() {
  const JSON_OUT = process.argv.includes('--json');
  const STRICT = process.argv.includes('--strict');
  // --strict-coverage é independente de --strict (argv.includes é match exato:
  // ['--strict-coverage'] NÃO casa '--strict'). Morde só a cobertura, não o frescor.
  const STRICT_COVERAGE = process.argv.includes('--strict-coverage');
  const staleDays = DEFAULT_STALE_DAYS;
  const rows = scan(staleDays);
  const stale = rows.filter((r) => r.stale).sort((a, b) => (b.gapDays ?? 0) - (a.gapDays ?? 0));
  const noDoor = rows.filter((r) => !r.hasDoor).map((r) => r.mod);
  // Gap de COBERTURA = módulo BACKEND sem BRIEFING (subconjunto de noDoor que exclui
  // áreas só-frontend tipo User/Perfil). É o que --strict-coverage morde; hoje = 0 (36/36).
  const coverageGaps = rows.filter((r) => isBriefingCoverageGap(r)).map((r) => r.mod);

  if (JSON_OUT) {
    console.log(JSON.stringify({
      gate: 'briefing-code-staleness',
      axis: 'BRIEFING.md data declarada (updated_at/distilled_at/Atualizado; fallback git) vs Modules/<Mod>/ ∪ resources/js/Pages/<Mod>/ data-git',
      staleDays,
      evaluated: rows.length,
      stale: stale.map((r) => ({ mod: r.mod, gapDays: r.gapDays, commitsAhead: r.commitsAhead, doorDate: r.doorDate, doorSource: r.doorSource, codeDate: r.codeDate })),
      noDoor,
      coverageGaps,
    }, null, 2));
    return ((stale.length && STRICT) || (coverageGaps.length && STRICT_COVERAGE)) ? 1 : 0;
  }

  console.log(`\n  BRIEFING × CÓDIGO — porta atrás da superfície do módulo (limiar ${staleDays}d)`);
  console.log(`  eixo: memory/requisitos/<Mod>/BRIEFING.md  vs  Modules/<Mod>/ ∪ resources/js/Pages/<Mod>/`);
  console.log('  ' + '─'.repeat(74));
  if (!stale.length) {
    console.log('  🟢 nenhuma porta atrás do código além do limiar.');
  } else {
    console.log(`  ${'MÓDULO'.padEnd(20)} ${'porta(git)'.padEnd(12)} ${'código(git)'.padEnd(12)} atraso`);
    for (const r of stale) {
      console.log(`  🟡 ${r.mod.padEnd(18)} ${(r.doorDate || '—').padEnd(12)} ${(r.codeDate || '—').padEnd(12)} ${r.gapDays}d / ${r.commitsAhead} commits`);
    }
  }
  console.log('  ' + '─'.repeat(74));
  console.log(`  ${stale.length} porta(s) stale · ${rows.length} módulos avaliados · ${noDoor.length} sem porta (${noDoor.join(', ') || '—'})`);
  console.log(`  COBERTURA: ${coverageGaps.length} módulo(s) BACKEND sem BRIEFING (${coverageGaps.join(', ') || '— 0, cobertura completa 36/36'}) · --strict-coverage morde isto`);
  console.log('  ADVISORY (ADR 0314 — higiene, nunca required). Ação: skill brief-update no módulo.');
  console.log('  NÃO é presence-gate: mede a derivada porta×código (frescor) e existência de módulo-backend (cobertura); nunca "BRIEFING no diff" (proibicoes §5 + L-24).\n');

  // Anotações GitHub — visíveis no PR (amarelo, non-blocking). Só em CI.
  if (process.env.GITHUB_ACTIONS === 'true') {
    for (const r of stale) {
      console.log(`::warning title=BRIEFING atrás do código (${r.mod})::${r.mod}: BRIEFING.md ${r.gapDays} dias atrás do código (porta ${r.doorDate} vs código ${r.codeDate}, ${r.commitsAhead} commits na superfície). Rode a skill brief-update pra reconciliar memory/requisitos/${r.mod}/BRIEFING.md.`);
    }
    for (const mod of coverageGaps) {
      console.log(`::warning title=Módulo BACKEND sem BRIEFING (${mod})::${mod}: Modules/${mod}/ existe mas memory/requisitos/${mod}/BRIEFING.md não. Crie o BRIEFING (skill brief-update / template).`);
    }
  }

  // Reporter: exit 0 SEMPRE (advisory). --strict / --strict-coverage (opt-in) saem 1 pra scripts.
  return ((stale.length && STRICT) || (coverageGaps.length && STRICT_COVERAGE)) ? 1 : 0;
}

// ── main (só quando executado direto; importável p/ self-test sem rodar) ──────
const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();

if (isMain) process.exit(run());
