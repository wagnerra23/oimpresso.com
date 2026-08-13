#!/usr/bin/env node
// @ts-check
/**
 * flip-required.mjs — promove UM check advisory a required na branch protection de `main`.
 *
 * =====================================================================================
 * POR QUE ESTE SCRIPT EXISTE (e não um `gh api` na mão)
 * =====================================================================================
 * Promover é a operação de governança mais perigosa do repo: erra e TODO merge trava.
 * O §5 de `memory/proibicoes.md` tem duas lápides que nasceram exatamente aqui:
 *
 *   2026-07-02 — re-postar `contexts` com payload inline no shell Windows: PowerShell/cmd
 *     re-encodam não-ASCII, os nomes chegam com mojibake (`Â·`, `ConstituiÃ§Ã£o`) e viram
 *     contexts que NENHUM check-run satisfaz → `mergeStateStatus BLOCKED` com 54/54 verdes.
 *     Aqui o nome do context é **derivado do `name:` do job no .yml**, nunca digitado — e o
 *     payload sai por arquivo UTF-8 sem BOM, nunca por `-f`/`-F`/heredoc.
 *
 *   2026-08-08 — promover sem atualizar os PRs abertos: quem não tem o check no head SHA
 *     trava com 0 falhas e 0 pendentes. Este script LISTA quem está sem, antes de aplicar.
 *
 * E o PUT de protection SUBSTITUI tudo: qualquer campo omitido é apagado. Por isso o payload
 * é reconstruído campo a campo a partir do GET, e não montado à mão.
 *
 * Uso:
 *   node scripts/governance/flip-required.mjs --workflow module-surface.yml --job namespaces
 *   node scripts/governance/flip-required.mjs ... --apply     # sem isto é DRY-RUN
 *
 * O dry-run é o default de propósito: mostra o antes→depois e quem travaria, e não escreve.
 * Depois de aplicar, atualize `governance/required-checks-baseline.json` no MESMO PR
 * (o `_meta.regra` do baseline manda) e rode `protection-drift.mjs` pra provar string-exata.
 */
import { readFileSync, writeFileSync, unlinkSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';

const REPO = 'wagnerra23/oimpresso.com';
const args = process.argv.slice(2);
const val = (f) => { const i = args.indexOf(f); return i >= 0 ? args[i + 1] : null; };
const APPLY = args.includes('--apply');
const WORKFLOW = val('--workflow');
const JOB = val('--job');

if (!WORKFLOW || !JOB) {
  console.error('Uso: node scripts/governance/flip-required.mjs --workflow <arquivo.yml> --job <id> [--apply]');
  process.exit(2);
}

const gh = (a, opts = {}) => execFileSync('gh', a, { encoding: 'utf8', maxBuffer: 32 * 1024 * 1024, ...opts });

// ── 1. O nome do context vem do .yml, NUNCA do argumento ────────────────────────────
// Digitar `Mapa módulo↔Pages == renders reais` num shell Windows é a lápide de 2026-07-02.
const yml = readFileSync(join('.github', 'workflows', WORKFLOW), 'utf8');
const bloco = yml.split(/^  (?=\S)/m).find((b) => b.startsWith(`${JOB}:`));
if (!bloco) { console.error(`✗ job "${JOB}" não encontrado em ${WORKFLOW}`); process.exit(1); }
const mName = bloco.match(/^\s+name:\s*(.+?)\s*$/m);
if (!mName) { console.error(`✗ job "${JOB}" não declara \`name:\` — sem nome não há context`); process.exit(1); }
const CONTEXT = mName[1].replace(/^["']|["']$/g, '');
console.log(`context derivado do .yml: ${JSON.stringify(CONTEXT)}`);

// ── 2. Estado atual ─────────────────────────────────────────────────────────────────
const prot = JSON.parse(gh(['api', `repos/${REPO}/branches/main/protection`]));
const atuais = prot.required_status_checks?.contexts || [];
if (atuais.includes(CONTEXT)) { console.log('✓ já é required — nada a fazer.'); process.exit(0); }
console.log(`contexts: ${atuais.length} → ${atuais.length + 1}`);

// ── 3. Quem travaria (lápide 2026-08-08) ────────────────────────────────────────────
const abertos = JSON.parse(gh(['pr', 'list', '--state', 'open', '--json', 'number', '--limit', '50']));
const semCheck = [];
for (const { number } of abertos) {
  let checks = [];
  try { checks = JSON.parse(gh(['pr', 'checks', String(number), '--json', 'name,state'])); } catch { /* sem checks */ }
  if (!checks.some((c) => c.name === CONTEXT)) semCheck.push(number);
}
if (semCheck.length) {
  console.log(`\n⚠️  ${semCheck.length} PR(s) aberto(s) SEM este check no head SHA — travariam com 0 falhas:`);
  console.log(`      ${semCheck.map((n) => '#' + n).join(' ')}`);
  console.log('      Rode `gh pr update-branch <n>` neles ANTES de aplicar (§5 2026-08-08).');
} else {
  console.log('\n✓ todo PR aberto já tem o check — ninguém trava.');
}

// ── 4. Payload: reconstruído campo a campo (PUT substitui tudo) ──────────────────────
const p = prot;
const payload = {
  required_status_checks: { strict: !!p.required_status_checks?.strict, contexts: [...atuais, CONTEXT].sort() },
  enforce_admins: !!p.enforce_admins?.enabled,
  required_pull_request_reviews: p.required_pull_request_reviews ? {
    dismiss_stale_reviews: !!p.required_pull_request_reviews.dismiss_stale_reviews,
    require_code_owner_reviews: !!p.required_pull_request_reviews.require_code_owner_reviews,
    required_approving_review_count: p.required_pull_request_reviews.required_approving_review_count || 0,
    require_last_push_approval: !!p.required_pull_request_reviews.require_last_push_approval,
  } : null,
  restrictions: p.restrictions ? {
    users: (p.restrictions.users || []).map((u) => u.login),
    teams: (p.restrictions.teams || []).map((t) => t.slug),
    apps: (p.restrictions.apps || []).map((a) => a.slug),
  } : null,
  required_linear_history: !!p.required_linear_history?.enabled,
  allow_force_pushes: !!p.allow_force_pushes?.enabled,
  allow_deletions: !!p.allow_deletions?.enabled,
  block_creations: !!p.block_creations?.enabled,
  required_conversation_resolution: !!p.required_conversation_resolution?.enabled,
  lock_branch: !!p.lock_branch?.enabled,
  allow_fork_syncing: !!p.allow_fork_syncing?.enabled,
};

if (!APPLY) {
  console.log('\n── DRY-RUN (nada escrito) ──');
  console.log(`  enforce_admins=${payload.enforce_admins} · strict=${payload.required_status_checks.strict}`);
  console.log(`  reviews=${JSON.stringify(payload.required_pull_request_reviews)}`);
  console.log('\n  Para aplicar: repita o comando com --apply');
  process.exit(0);
}

// ── 5. Aplica por ARQUIVO (nunca inline — lápide 2026-07-02) ────────────────────────
const tmp = join(process.cwd(), '.flip-required.payload.json');
writeFileSync(tmp, JSON.stringify(payload, null, 2), { encoding: 'utf8' });
if (readFileSync(tmp)[0] === 0xEF) { console.error('✗ payload saiu com BOM — abortado'); unlinkSync(tmp); process.exit(1); }
try {
  gh(['api', '-X', 'PUT', `repos/${REPO}/branches/main/protection`, '--input', tmp], { stdio: ['ignore', 'pipe', 'pipe'] });
} finally { unlinkSync(tmp); }

// ── 6. Validação string-exata (contagem NÃO prova — o mojibake mantém a contagem) ────
const depois = JSON.parse(gh(['api', `repos/${REPO}/branches/main/protection`]));
const gravado = (depois.required_status_checks?.contexts || []).find((c) => c === CONTEXT);
if (!gravado) {
  console.error('\n✗ o context NÃO está lá com a string exata — possível mojibake. REVERTER JÁ.');
  console.error('  Presentes com não-ASCII:');
  for (const c of depois.required_status_checks?.contexts || []) if (/[^\x00-\x7F]/.test(c)) console.error(`      ${JSON.stringify(c)}`);
  process.exit(1);
}
console.log(`\n✓ FLIP OK — ${depois.required_status_checks.contexts.length} contexts · string exata confirmada`);
console.log('  Agora: atualize governance/required-checks-baseline.json no MESMO PR e rode protection-drift.mjs.');
