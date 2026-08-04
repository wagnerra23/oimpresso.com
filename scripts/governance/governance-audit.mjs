#!/usr/bin/env node
// governance-audit.mjs — DEPRECADO 2026-08-04: agregador SEM invocador e sem casa honesta
// (mantido no repo por decisão de escopo — podar capacidade é ato de [W], não de agente).
//
// ── VEREDITO (o "invocar ou declarar morto" de proibicoes.md §"Sempre fazer" item 2) ──
// A regra diz que máquina sem invocador é BUG, não neutralidade — e manda LIGAR quando é
// medidor. Este é medidor, e mesmo assim o veredito é MORTO, porque não existe lugar onde
// ligá-lo não seja mudo ou duplicado. Medido em origin/main @69039c8cd45, 2026-08-04:
//
//   1. NÃO tem invocador — e nunca teve. `git grep "governance-audit"` → 32 ocorrências;
//      fora de .md, do próprio arquivo e do falso-positivo de substring
//      `governance-auditoria` em doc-id-index.json, sobram 8 — e as 8 são COMENTÁRIO/
//      docblock. Zero em .github/workflows, package.json, .claude/ ou cron. O próprio
//      selftest-registry-check.mjs:215 já registrava o caso por nome.
//   2. Ligar em CI seria 43% MUDO. As 6 entradas `runtime:'php'` precisam de
//      `php artisan` + DB; no runner node elas caem todas em `skip: php indisponível`.
//      Scorecard cujas linhas infra-dependentes são ⊘ por construção é o "gate mudo é pior
//      que gate ausente" (proibicoes §"Sempre fazer" item 5).
//   3. Ligar em CI seria duplicado no resto. O exit code só morde nos 2 `kind:'required'`
//      (memory-health, gate-selftest) — e os DOIS já rodam como check required no
//      Governance Gate. Os outros 6 node (anchor-lint, sdd-scorecard, plan-health,
//      knowledge-drift, integrity-check, ds-guard) também já têm invocador próprio. Segundo
//      juiz pro mesmo tema é o que o §5 chama de "duplica régua consolidada" (2026-07-09).
//   4. Ligar em PROD é frágil e fora de contrato. Node existe no Hostinger só via nvm
//      (`~/.nvm/versions/node/v24.15.0/bin/node`), sem garantia de estar no PATH do cron; e
//      rodar 8 sentinelas node no shared hosting rema contra a separação Hostinger ≠ CT 100
//      (ADR 0062). O painel que o Daily Brief precisa já vem de serviço PHP próprio.
//   5. A única linha que ele carregava SOZINHO era `jana:plan-drift` (L45) — e ela ganhou
//      invocador de 1ª classe em 2026-08-04: PlanDriftChecker, dentro do
//      `governance:audit --all --notify` (PHP) que JÁ roda diário 06:35 em prod.
//
// EFEITO COLATERAL DECLARADO — e é o valor de matar a ficção: com esta bateria fora de
// cena, `mem:audit` e `jana:validate-memory` ficam VISIVELMENTE sem invocador (medido:
// 0 schedules em Kernel.php e 0 no `schedule:list` de prod; a única menção de
// `jana:validate-memory` a um cron é um comentário em memory-schema-gate.yml:5 que afirma
// uma cadência que não existe). Eles JÁ não rodavam — a bateria só os fazia PARECER
// cobertos. Triá-los é trabalho seguinte, com dono humano.
//
// @deprecated 2026-08-04 — não wirar em CI/cron. Se um dia o painel único voltar a ser
// desejado, ele nasce onde o PHP roda (comando artisan agregador), não aqui.
//
// ── Intenção original (2026-06-20), preservada ────────────────────────────────────────
// AGREGADOR da bateria de sentinelas. Resolvia o buraco "não tem um botão": antes a
// verdade da governança vivia espalhada em ~8 comandos soltos. Aqui era UM comando → UM
// scorecard.
//
// Roda a bateria inteira e devolve {ok, results[]}. Cobre os sentinelas Node
// (determinísticos, sem infra) + os health-checks PHP (best-effort: skip gracioso
// se php/app não bootar — eles têm enforcement próprio via cron prod + bite-tests).
//
// EXIT CODE só morde nos sentinelas `required` DETERMINÍSTICOS (node core), pra ser
// confiável em qualquer ambiente (CI, dev, prod). Os PHP entram como advisory aqui
// porque dependem de DB/Langfuse de prod — a mordida real deles é o cron + Pest.
//
// USO (na raiz do repo):
//   node scripts/governance/governance-audit.mjs            # tabela
//   node scripts/governance/governance-audit.mjs --json     # scorecard JSON (Daily Brief)
//   node scripts/governance/governance-audit.mjs --node-only # pula os PHP
//
// Node puro (spawnSync). Sem deps.

import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const JSON_OUT = process.argv.includes('--json');
const NODE_ONLY = process.argv.includes('--node-only');
const stripAnsi = (s) => s.replace(/\x1b\[[0-9;]*m/g, '');

// Banner de depreciação em STDERR (nunca stdout — `--json` precisa continuar parseável).
// Existe para que quem RODAR veja o veredito, não só quem abrir o arquivo: cabeçalho é
// lido por quem já desconfia; banner alcança quem ia wirar sem ler.
process.stderr.write(
  '\n  ⚠ governance-audit.mjs está DEPRECADO (2026-08-04) — agregador sem invocador.\n' +
  '    Não wirar em CI (as 6 entradas PHP ficam ⊘) nem em cron (node só via nvm no Hostinger).\n' +
  '    jana:plan-drift, a única linha exclusiva daqui, agora roda via PlanDriftChecker\n' +
  '    dentro do `governance:audit --all --notify` (cron diário 06:35, prod). Ver cabeçalho.\n\n',
);

// runtime: node = process.execPath <script>; php = php artisan <cmd>
// kind: required (entra no exit) | advisory (só reporta)
const BATTERY = [
  { id: 'memory-health',       runtime: 'node', kind: 'required', cmd: ['scripts/governance/memory-health.mjs', '--json'] },
  { id: 'gate-selftest',       runtime: 'node', kind: 'required', cmd: ['scripts/governance/gate-selftest.mjs', '--json'] },
  { id: 'integrity-check',     runtime: 'node', kind: 'advisory', cmd: ['prototipo-ui/integrity-check.mjs'] },
  { id: 'knowledge-drift',     runtime: 'node', kind: 'advisory', cmd: ['scripts/governance/knowledge-drift.mjs', '--check'] },
  { id: 'ds-guard',            runtime: 'node', kind: 'advisory', cmd: ['prototipo-ui/ds-guard.mjs', '--all'] },
  { id: 'plan-health',         runtime: 'node', kind: 'advisory', cmd: ['scripts/governance/plan-health.mjs', '--json'] },
  // Scorecards de cobertura (Onda C audit 2026-06-24): trazem a foto da SPEC-viva pro mesmo painel.
  // anchor-lint SEM --check = modo report full-tree (exit 0, não morde legado) → só a cobertura.
  // sdd-scorecard --json = as 10 métricas do scorecard SDD. Ambos advisory (são fotos, não gates).
  { id: 'anchor-coverage',     runtime: 'node', kind: 'advisory', cmd: ['scripts/governance/anchor-lint.mjs', '--json'] },
  { id: 'sdd-scorecard',       runtime: 'node', kind: 'advisory', cmd: ['scripts/governance/sdd-scorecard.mjs', '--json'] },
  // PHP health-checks: advisory aqui (dependem de infra prod) — enforcement = cron + Pest bite-tests.
  { id: 'jana:plan-drift',     runtime: 'php',  kind: 'advisory', cmd: ['jana:plan-drift', '--json'] }, // ADR 0294 Onda 2 — drift status-do-plano ≠ tasks MCP (par do plan-health node)
  { id: 'jana:health-check',   runtime: 'php',  kind: 'advisory', cmd: ['jana:health-check', '--json'] },
  { id: 'jana:system-audit',   runtime: 'php',  kind: 'advisory', cmd: ['jana:system-audit', '--json'] },
  { id: 'jana:validate-memory',runtime: 'php',  kind: 'advisory', cmd: ['jana:validate-memory', '--json'] },
  { id: 'mem:audit',           runtime: 'php',  kind: 'advisory', cmd: ['mem:audit', '--candidates-only'] },
  // drift-sentinel: probe --status (armed vs dormant) — NÃO roda o canary pago real
  // (esse é o cron semanal). Sem chave OPENAI sai dormant → ⊘ aqui (honesto, não silêncio).
  { id: 'jana:drift-sentinel', runtime: 'php',  kind: 'advisory', cmd: ['jana:drift-sentinel', '--status', '--json'] },
];

function run(entry) {
  if (entry.runtime === 'node') {
    const scriptPath = join(ROOT, entry.cmd[0]);
    if (!existsSync(scriptPath)) return { status: 'skip', summary: `script ausente: ${entry.cmd[0]}` };
    const r = spawnSync(process.execPath, [scriptPath, ...entry.cmd.slice(1)], {
      cwd: ROOT, encoding: 'utf8', env: { ...process.env, GITHUB_STEP_SUMMARY: '' },
    });
    return interpret(entry, r);
  }
  // php
  if (NODE_ONLY) return { status: 'skip', summary: '--node-only' };
  // shell no Windows pra resolver php.bat (Herd); Linux/CI tem php binário no PATH.
  const r = spawnSync('php', ['artisan', ...entry.cmd], {
    cwd: ROOT, encoding: 'utf8', shell: process.platform === 'win32',
  });
  if (r.error) return { status: 'skip', summary: `php indisponível (${r.error.code || r.error.message})` };
  return interpret(entry, r);
}

function interpret(entry, r) {
  const out = stripAnsi((r.stdout || '') + (r.stderr || ''));
  // Prefere o campo ok do JSON quando o sentinela o emite; senão usa exit code.
  let ok = r.status === 0;
  let summary = '';
  let dormant = false;
  const jStart = out.indexOf('{');
  if (jStart !== -1) {
    try {
      const j = JSON.parse(out.slice(jStart));
      if (typeof j.ok === 'boolean') ok = j.ok;
      // Estado DORMANT (ex: drift-sentinel sem OPENAI_API_KEY) → ⊘, nem pass nem fail.
      if (j.status === 'dormant') {
        dormant = true;
        summary = `dormant: ${j.reason || 'sem chave'}`;
      } else if (j.status === 'armed') {
        summary = 'armado (OPENAI_API_KEY presente)';
      } else if (Array.isArray(j.fails) || Array.isArray(j.warns)) {
        summary = `${(j.fails || []).length} fail · ${(j.warns || []).length} warn`;
      } else if (Array.isArray(j.cases)) {
        const bad = j.cases.filter((c) => !c.ok).length;
        summary = `${j.cases.length} casos · ${bad} falhos`;
      } else if (Array.isArray(j.checks)) {
        const bad = j.checks.filter((c) => !c.ok && !(c.advisory)).length;
        summary = `${j.checks.length} checks · ${bad} duros falhos`;
      } else if (Array.isArray(j.findings)) {
        summary = `${j.planos ?? '?'} planos · ${j.fail ?? 0} fail · ${j.warn ?? 0} warn`;
      } else if (j.summary && typeof j.summary.anchor_coverage_pct === 'number') {
        // anchor-lint --json (report full-tree): foto de cobertura da SPEC-viva (ADR 0273).
        const bs = j.summary.by_state || {};
        summary = `cobertura ${j.summary.anchor_coverage_pct}% · ${bs.anchored_ok || 0} ok · ${bs.anchored_dead || 0} dead · ${bs.anchored_zombie || 0} zombie`;
      } else if (j.metrics && typeof j.metrics === 'object') {
        // sdd-scorecard --json: as métricas do scorecard SDD (foto, não gate).
        summary = `scorecard SDD · ${Object.keys(j.metrics).length} métricas`;
      }
    } catch { /* não era JSON — cai no fallback */ }
  }
  if (dormant) return { status: 'skip', summary: summary.slice(0, 90), exit: r.status };
  if (!summary) {
    const lines = out.split('\n').map((l) => l.trim()).filter(Boolean);
    summary = (lines[lines.length - 1] || '').slice(0, 90);
  }
  return { status: ok ? 'pass' : 'fail', summary, exit: r.status };
}

const results = BATTERY.map((e) => ({ id: e.id, kind: e.kind, runtime: e.runtime, ...run(e) }));

// Exit morde só nos required determinísticos que falharam (não skip).
const blocking = results.filter((r) => r.kind === 'required' && r.status === 'fail');
const ok = blocking.length === 0;

if (JSON_OUT) {
  console.log(JSON.stringify({ ok, ran_at: new Date().toISOString(), results }, null, 2));
  process.exit(ok ? 0 : 1);
}

const icon = { pass: '✅', fail: '❌', skip: '⊘' };
console.log(`\n  GOVERNANCE-AUDIT — bateria de sentinelas (${results.length} sentinelas)\n`);
for (const r of results) {
  const tag = r.kind === 'required' ? 'REQ' : 'adv';
  console.log(`  ${icon[r.status]} ${r.id.padEnd(22)} [${tag}] ${r.summary}`);
}
console.log('');
if (!ok) {
  console.error(`  ✗ ${blocking.length} sentinela(s) REQUIRED falharam: ${blocking.map((r) => r.id).join(', ')}\n`);
  process.exit(1);
}
console.log(`  ✓ core determinístico verde. (sentinelas PHP são best-effort — verdade de prod via cron + Pest.)\n`);
process.exit(0);
