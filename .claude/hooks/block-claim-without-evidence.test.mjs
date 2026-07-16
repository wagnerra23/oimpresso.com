#!/usr/bin/env node
// Teste do PORTE cross-plataforma block-claim-without-evidence.mjs (ex-.ps1). Cada caso
// deriva do CONTRATO canônico (proibicoes.md §"Claim sem evidência" — lista de paths de
// runtime crítico + 5 vias de evidência/override — e ADR 0224, que rebaixou o hook a
// ADVISORY: exit 0 SEMPRE), NÃO do output do .ps1 legado. Roda em Linux/CI.
// Complementa scripts/governance/settings-evidence-smoke-registration.test.mjs (REGISTRO).
//
// Rodar: node .claude/hooks/block-claim-without-evidence.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { isTrigger, isInfraPath, extractInlineBody, hasEvidence, findOverride, evaluate, advisoryMessage, isAlvoPath, hasAlvoEvidence, findAlvoPendente, evaluateAlvo, advisoryAlvoMessage } from './block-claim-without-evidence.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-claim-without-evidence.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── triggers (só publicação de PR interessa) ─────────────────────────────────────
check('trigger: gh pr create', isTrigger('gh pr create --title x'));
check('trigger: gh pr merge --admin', isTrigger('gh pr merge 42 --admin'));
check('trigger: gh pr merge --squash', isTrigger('gh pr merge 42 --squash'));
check('não-trigger: git push', !isTrigger('git push origin main'));
check('não-trigger: gh pr view', !isTrigger('gh pr view 42'));

// ── paths de runtime crítico (lista canônica proibicoes §Claim sem evidência) ────
const INFRA = ['.htaccess', 'app/Http/Middleware/Foo.php', 'app/Http/Kernel.php', 'routes/web.php', 'app/Providers/AppServiceProvider.php', 'bootstrap/app.php'];
for (const p of INFRA) check(`infra: ${p}`, isInfraPath(p));
check('não-infra: Modules/Jana/Services/Foo.php', !isInfraPath('Modules/Jana/Services/Foo.php'));
check('não-infra: resources/js/Pages/Sells/Index.tsx', !isInfraPath('resources/js/Pages/Sells/Index.tsx'));
check('não-infra: Modules/X/routes/web.php (routes/ ancorado na raiz)', !isInfraPath('Modules/X/routes/web.php'));

// ── evidência + override (as 5 vias do contrato) ─────────────────────────────────
check('evidência: curl -sv', hasEvidence('rodei curl -sv https://oimpresso.com/x'));
check('evidência: status literal', hasEvidence('< HTTP/1.1 302'));
check('evidência: ## Infra Contract', hasEvidence('## Infra Contract\n...'));
check('sem evidência: prosa "funciona"', !hasEvidence('testei e funciona perfeitamente'));
check('override: HTML comment', findOverride('corpo <!-- evidence-override: hotfix quebra prod --> fim') === 'hotfix quebra prod');
check('override: linha de commit', findOverride('fix x\n# evidence-override: rollback urgente') === 'rollback urgente');
check('sem override: prosa comum', findOverride('commit normal sem valve') === null);
check('extractInlineBody pega --body', extractInlineBody(`gh pr create --body "## Infra Contract"`) === '## Infra Contract');

// ── evaluate: a decisão completa (ADVISORY nunca vira block — ADR 0224) ──────────
const base = { command: 'gh pr create --title x', envOverride: false, diffFiles: ['app/Http/Kernel.php'], commitsText: '', hasRecentEvidenceFile: false };
check('advisory: infra sem evidência', evaluate(base) === 'advisory');
check('silent: diff não toca infra', evaluate({ ...base, diffFiles: ['Modules/Jana/Foo.php'] }) === 'silent');
check('silent: comando não-trigger', evaluate({ ...base, command: 'git status' }) === 'silent');
check('ok: evidência no commit', evaluate({ ...base, commitsText: 'valida: curl -sv https://oimpresso.com/login\n< HTTP/1.1 200' }) === 'ok');
check('ok: evidência no --body inline', evaluate({ ...base, command: `gh pr create --body "## Infra Contract curl -sv"` }) === 'ok');
check('ok: arquivo curl-evidence recente', evaluate({ ...base, hasRecentEvidenceFile: true }) === 'ok');
check('override: env Tier 0 Wagner', evaluate({ ...base, envOverride: true }) === 'override');
check('override: valve no commit', evaluate({ ...base, commitsText: '# evidence-override: hotfix' }) === 'override');
check('silent: comando vazio (fail-open)', evaluate({ ...base, command: '' }) === 'silent');
check('mensagem cita as 5 vias + CI camada A', (() => {
  const m = advisoryMessage(['routes/web.php']);
  return /Infra Contract/.test(m) && /evidence-override/.test(m) && /infra-contract-required/.test(m) && /ADVISORY/.test(m);
})());

// ── P15 entrega 2: dimensão AMBIENTE-ALVO (CT100/cron) — paralela, não muda infra ─
check('alvo: scripts/tests/ct100-smoke.sh', isAlvoPath('scripts/tests/ct100-smoke.sh'));
check('alvo: docker/oimpresso-mcp/scripts/backup.sh', isAlvoPath('docker/oimpresso-mcp/scripts/backup.sh'));
check('alvo: app/Console/Kernel.php (schedule)', isAlvoPath('app/Console/Kernel.php'));
check('não-alvo: scripts/tests/outro.sh (só ct100-*)', !isAlvoPath('scripts/tests/outro.sh'));
check('não-alvo: docker/oimpresso-staging/scripts/x.sh', !isAlvoPath('docker/oimpresso-staging/scripts/x.sh'));
check('não-alvo: Modules/X/app/Console/Kernel.php (ancorado na raiz)', !isAlvoPath('Modules/X/app/Console/Kernel.php'));
check('não-alvo: app/Http/Kernel.php (esse é infra, outra dimensão)', !isAlvoPath('app/Http/Kernel.php'));

check('evidência alvo: timestamp + host', hasAlvoEvidence('run 2026-07-13 14:22 em root@ct100-mcp: OK'));
check('sem evidência alvo: host sem timestamp', !hasAlvoEvidence('rodou no ct100 e passou'));
check('sem evidência alvo: timestamp sem host', !hasAlvoEvidence('rodei em 2026-07-13 14:22 na minha máquina'));
check('tag alvo-pendente: HTML comment', findAlvoPendente('x <!-- alvo-pendente: smoke agendado pro deploy de 20/jul --> y') === 'smoke agendado pro deploy de 20/jul');
check('tag alvo-pendente: linha de commit', findAlvoPendente('feat x\n# alvo-pendente: nightly valida') === 'nightly valida');
check('sem tag: prosa comum', findAlvoPendente('commit normal') === null);

const baseAlvo = { command: 'gh pr create --title x', envOverride: false, diffFiles: ['scripts/tests/ct100-smoke.sh'], commitsText: '' };
check('alvo advisory: script CT100 sem evidência nem tag', evaluateAlvo(baseAlvo) === 'advisory');
check('alvo silent: diff não toca paths-alvo', evaluateAlvo({ ...baseAlvo, diffFiles: ['Modules/Jana/Foo.php'] }) === 'silent');
check('alvo silent: comando não-trigger', evaluateAlvo({ ...baseAlvo, command: 'git status' }) === 'silent');
check('alvo ok: output com timestamp+host no commit', evaluateAlvo({ ...baseAlvo, commitsText: 'smoke: 2026-07-13 09:15 root@ct100-mcp exit 0' }) === 'ok');
check('alvo declared: tag alvo-pendente no --body', evaluateAlvo({ ...baseAlvo, command: `gh pr create --body "<!-- alvo-pendente: valida na nightly de 14/jul -->"` }) === 'declared');
check('alvo declared: tag no commit', evaluateAlvo({ ...baseAlvo, commitsText: '# alvo-pendente: smoke pós-deploy' }) === 'declared');
check('alvo override: valve genérica preservada', evaluateAlvo({ ...baseAlvo, commitsText: '# evidence-override: hotfix' }) === 'override');
check('alvo override: env Tier 0 Wagner', evaluateAlvo({ ...baseAlvo, envOverride: true }) === 'override');
check('curl NÃO vale como evidência de alvo (natureza diferente)', evaluateAlvo({ ...baseAlvo, commitsText: 'curl -sv https://oimpresso.com/x\n< HTTP/1.1 200' }) === 'advisory');
check('mensagem alvo cita tag + timestamp/host + origem P15', (() => {
  const m = advisoryAlvoMessage(['scripts/tests/ct100-smoke.sh']);
  return /alvo-pendente/.test(m) && /timestamp \+ host/.test(m) && /P15/.test(m) && /ADVISORY/.test(m);
})());
check('infra evaluate NÃO dispara pra path-alvo (dimensões separadas)', evaluate({ ...baseAlvo, hasRecentEvidenceFile: false }) === 'silent');

// ── E2E: stdin JSON → exit code. Contrato ADR 0224: exit 0 SEMPRE ────────────────
function runHook(stdin, env = {}) {
  return spawnSync(process.execPath, [HOOK], { input: stdin, encoding: 'utf8', env: { ...process.env, ...env } });
}
const j = (cmd) => JSON.stringify({ tool_name: 'Bash', tool_input: { command: cmd } });
check('E2E: não-trigger → exit 0 silencioso', (() => { const r = runHook(j('git status')); return r.status === 0 && !r.stderr; })());
check('E2E: trigger com env override → exit 0', runHook(j('gh pr create --title x'), { OIMPRESSO_EVIDENCE_OVERRIDE: '1' }).status === 0);
check('E2E: tool não-Bash → exit 0', runHook(JSON.stringify({ tool_name: 'Write', tool_input: {} })).status === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('').status === 0);
check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava sessão)', runHook('{lixo').status === 0);
check('E2E: trigger real NUNCA excede exit 0 (ADVISORY ADR 0224, mesmo se advisory disparar)', runHook(j('gh pr merge 1 --admin')).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs classifica trigger/infra/evidência/override pelo contrato + dimensão ambiente-alvo CT100/cron (P15 entrega 2); ADVISORY (exit 0 sempre, ADR 0224) provado em E2E.');
process.exit(fails ? 1 : 0);
