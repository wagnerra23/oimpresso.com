---
id: requisitos-governance-briefing
module: Governance
status: producao
updated_at: "2026-09-06"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Governance (verdade destilada)

## Estado atual

Enforcer + dashboard humano da Constituição v2 (ADR 0094): telas Inertia sob `/governance/*`, middleware `ActionGate` (Art. 8) e leitura consolidada das tabelas `mcp_*`. Cross-tenant **intencional** — exceção formal ao Tier 0 justificada pela Constituição Art. 6+8 (governança é camada transversal, não dado de negócio; `CrossTenantPolicyTest` e `MultiTenantGovernanceTest` fixam isso). Em produção, mas a porta da frente não é dele: `/governance` responde 302 para `/ia` desde 2026-05-22 (hub IA, sidebar v3 — ADR 0180); o dashboard original vive em `/governance/dashboard` (rota `governance.admin.dashboard.legacy` — o grupo prefixa `->name('governance.')`). A entry de sidebar voltou em 2026-08-05 (#5308, que também trouxe a strip `GovernancaSubNav`); os RUNBOOKs `audit`, `policies`, `drift-alerts` e `ds-rollout` vieram logo depois (#5311, 2026-08-05) — todos `status: rascunho` (derivados do código, nenhum passo executado; os de `dashboard`, `custos` e `qualidade-ia` são `ativo`). Custos de IA e Qualidade IA chegaram da Jana em 2026-08-05 (#5309, fronteira da ADR 0366).

O centro de gravidade saiu do PHP: a governança que decide merge roda em `scripts/governance/*.mjs` + gates de CI, e o dono de "o que é required" é `governance/required-checks-baseline.json`. Module grade: dono do número é `governance/module-grades-baseline.json` (recomputar com `php artisan module:grade Governance`); o check `module-grades-gate` foi demovido a advisory em 2026-06-30 (ADR 0314 D-1; registro em `governance/required-checks-baseline.json` `_meta.democoes`). Errata que não volta: a frase "empatado no topo dos 36 módulos com ADS" caducou — o ADS foi removido pela ADR 0363 e o baseline não o lista.

## Capacidades

- Telas vivas em `resources/js/Pages/governance/`: `Dashboard`, `Policies` (listagem + toggle), `Audit`, `DriftAlerts`, `ModuleGrades` (Index + Show), `DsRollout`, `Custos`, `QualidadeIa` — inventário vivo é o diretório; gap/mapa design×código de 5 das 8 telas em `memory/requisitos/Governance/governance-*-gap.md` + `*.map.json` (#6916, 2026-09-06).
- Drift Framework plugável (ADR 0216), com master switch `drift_framework_enabled` (default ligado): `DriftChecker`s registrados no array `drift_checkers` de `Modules/Governance/Config/config.php` — inventário vivo é o array; os mais recentes são `IngestLivenessChecker` (2026-07-18) e `PlanDriftChecker` (2026-08-04, adapter do `jana:plan-drift`, ADR 0294).
- Protocolo GT-G5 de refutação de prosa gerada por IA: `memory/requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md` (§2–§4), com caminho executável em `.claude/workflows/refutador-gt-g5.js` (§7, #6907, 2026-09-06); recibos no ledger `governance/sdd-verification-ledger.json`.
- Crons de governança que estavam mortos em prod foram corrigidos em 2026-08-08 — snapshot SDD agendado 2×, com dono no CT 100 (#5444), e migrations do módulo que nunca entraram no `migrate` do deploy (#5443).
- Injetores do Daily Brief com kill-switch (`Services/*BriefLineService.php` + `AgentOutcomeBriefSectionService`) e família de comandos artisan de governança em `Console/Commands/` (`module:grade`, `governance:audit`, `charter:audit`, snapshots de scorecard, entre outros — inventário vivo é o diretório).
- Module grade v3 (`ModuleGradeCommand`, rubrica ADR 0155) e a tela `ModuleGrades` que a lê.

## Gaps

- `ActionGate` é guard fantasma: o alias `actiongate` é registrado no `GovernanceServiceProvider` e o próprio docblock da classe fala em "uso (futuro)", mas nenhuma rota o aplica (medido 2026-09-06: `git grep -iln actiongate origin/main -- '*routes*.php'` = 0 arquivos) e o modo default é `warn` (`Config/config.php` `actiongate_mode`).
- `AuditDrillDownService` seleciona `business_id` mas não filtra por ele, e a rota `/governance/audit` não aplica `can:` (o `routes.php` do módulo não tem nenhum `can:`), enquanto `Audit.charter.md` afirma query scopada pelo tenant — mexer é Tier 0 (ADR 0093) e decisão [W]; segue ⬜ ABERTO em `RUNBOOK-audit.md` §10 Pegadinhas (item 6).
- Permission `governance.policies.edit` é declarada (`DataController.php`, `topnav.php`) mas não checada em rota nem no `PoliciesController`; o toggle não deixa trilha — a tabela `mcp_governance_rule_history` que o controller e o `PolicyToggleService` citam é prosa (zero migration, zero query), e o `PoliciesController` só tem `index` + `toggle`.
- `DriftAlertService::persistedAlerts()` retorna `[]` por construção — falta `module_drift` no enum de `mcp_alertas`; o card vazio está correto.
- `compliance_pct` do Dashboard é constante no código (`DashboardController`), não medição.
- Os RUNBOOKs de 2026-08-05 seguem `rascunho`: nenhum passo executado (Pest só roda no CT 100, ADR 0062).
- Drift SPEC↔mundo: US-GOV-049 e US-GOV-050 seguem `status: todo` no SPEC com o trabalho já entregue (ADRs 0299/0329 aceitas, programa de ondas na 0320) — o SPEC é o perdedor a corrigir.

## Última mudança

2026-09-06 — dois PRs em `memory/requisitos/Governance/`: o refutador GT-G5 virou máquina (`.claude/workflows/refutador-gt-g5.js`, #6907) e os gap.md + map.json das 5 telas governance (#6916). Última mudança no código PHP do módulo: #6203 (2026-08-24) — tela nova deixa de nascer afirmando validação e teste que não houve. Antes: crons mortos corrigidos (#5443/#5444, 2026-08-08), entry de sidebar + strip (#5308, 2026-08-05) e os 4 RUNBOOKs (#5311, 2026-08-05).

## Proveniência (destilado de)

- session `sessions/2026-08-12-refutacao-lote-pr5675.md` (2026-08-12) — 2026-08-12-refutacao-lote-pr5675.md
- handoff `handoffs/2026-08-08-1804-migracao-blade-3-pecas-e-o-dedup-que-cegava-a-catraca.md` (2026-08-08) — 2026-08-08-1804-migracao-blade-3-pecas-e-o-dedup-que-cegava-a-catraca.md
- handoff `handoffs/2026-08-08-2340-crons-governanca-mortos-em-prod.md` (2026-08-08) — 2026-08-08-2340-crons-governanca-mortos-em-prod.md
