# Governance — BRIEFING (estado consolidado)

> **Última atualização:** 2026-05-17 (Wave 27 auto-saturate ≥95)
> **Wave 27 score target:** 90 → ≥95 (Excelente saturado) via D7 shim canônico + D9 OTel span + Pest Wave 27.
> **Wave 23 score target:** 74 → ≥90 (Bom → Excelente) via C3 + C5 + C6 (concluído).

## O que é

Meta-módulo da Constituição v2 ([ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)).
Operacionaliza Art. 8 (ActionGate runtime) + Art. 9 (Audit dashboard) + Art. 10
(Cascade Review). NÃO tem entidades de domínio com lifecycle de stages
(`governance.fsm_n_a: true` em `module.json`).

## Capacidades atuais

- **ActionGate runtime** (`Http/Middleware/ActionGate.php`) — enforcement por rota,
  log violations com `PiiRedactor` (D7 Wave 18).
- **Module Grades v3 ([ADR 0155](../../memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md))**
  — `ModuleGradeService` (86k LOC) computa 12 dimensões D1-D12 por módulo.
  `php artisan module:grade` (per-module) + `module:grade-snapshot` (cron daily
  06:00 BRT persiste em `mcp_module_grades_history`).
- **Drift detection** — `DetectDriftCommand` varre filesystem comparando com
  snapshot canônico; alerta via DriftAlertService.
- **Charter audit** — `charter:audit`/`health`/`metrics` cobre 12 charters
  ativos em `resources/js/Pages/<X>/<Tela>.charter.md`.
- **Policies CRUD** — `PolicyToggleService` (enabled bool) + audit em
  `mcp_governance_policies`.

## Dimensions C1-C6 (Wave 23 alvos)

| Dim | Antes | Alvo | Δ |
|---|---|---|---|
| C1 Coerência | 10 | 10 | — (saturado) |
| C2 Reversibilidade | 9 | 9 | — |
| C3 Reflexividade | 7 | 10 | +3 (bucket declarado, ADRs referenciadas neste BRIEFING) |
| C4 Compliance | 10 | 10 | — |
| C5 Cobertura | 4 | 13 | +9 (`scorecards/` 4 YAMLs canônicos + `governance:scorecard-snapshot` shell) |
| C6 Adoption time | 7 | 10 | +3 (skill `governance-pr-summary` Tier B) |
| **Score** | **74** | **≥90** | **+16** |

## ADRs canônicas referenciadas (C3 Reflexividade)

| ADR | Tema | Status |
|---|---|---|
| [0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) | Constituição v2 (mãe) | accepted |
| [0153](../../memory/decisions/0153-module-grade-rubrica-v1.md) | Module Grades rubrica v1 | superseded |
| [0155](../../memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md) | Rubrica v3 + sub-dims + Gate CI | accepted (parcial 0160) |
| [0156](../../memory/decisions/0156-governance-scorecards-yaml-canon.md) | Scorecards YAML canon | accepted |
| [0157](../../memory/decisions/0157-governance-bucket-fsm-n-a.md) | bucket `fsm_n_a` em module.json | accepted |
| [0158](../../memory/decisions/0158-governance-charter-audit-flow.md) | Charter audit canônico | accepted |
| [0159](../../memory/decisions/0159-governance-action-gate-runtime.md) | ActionGate runtime + PII redactor | accepted (parcial 0160, 0161) |
| [0160](../../memory/decisions/0160-governance-v4-scoped-scorecards-bucket-aware.md) | **Governance v4 — Scoped Scorecards bucket-aware** | accepted |
| [0161](../../memory/decisions/0161-governance-v4-aposentar-hacks-0159-redundantes.md) | **Aposentar 3/4 hacks ADR 0159** | accepted |

> Wave 27: ADRs 0160+0161 promovidas a referência canônica neste BRIEFING (antes apenas catálogo). Catálogo de buckets formalizado em [`memory/governance/buckets/_INDEX.md`](../../memory/governance/buckets/_INDEX.md).

## Scorecards YAML canon (C5 Cobertura)

4 YAMLs em `memory/governance/scorecards/`:

- `governance.yaml` — dimensions C1-C6 do próprio Governance
- `auditoria.yaml` — D1-D9 da Auditoria (whitelist UNREVERTIBLE + Pest)
- `admin.yaml` — D1-D9 Admin Center (Wagner-only + cross-tenant intencional)
- `_template.yaml` — template canônico pra qualquer módulo novo

Comando `php artisan governance:scorecard-snapshot` faz preview do snapshot
(shell W23, full impl Wave 24).

## Adoption time (C6) — skill `governance-pr-summary`

Tier B auto-trigger ANTES de `gh pr create` em qualquer branch. Lê
`module.json` dos módulos tocados + computa nota Module Grade resumida +
injeta `## Module Grade` em descrição de PR. Reduz adoption time de
"Wagner precisa abrir 3 dashboards" → "PR já vem com módulo + nota +
bucket".

## Anti-patterns proibidos (Tier 0)

- ⛔ ADRs CANON são **append-only** — NUNCA editar accepted records (criar
  nova com `supersedes: [N]`). CI `governance-gate.yml` Job 1 bloqueia.
- ⛔ ActionGate em rota sem `PiiRedactor` no log (Tier 0 ADR 0093).
- ⛔ Bucket `fsm_n_a` em módulo com entidade de negócio com lifecycle
  stages — só pra meta-módulos (Governance/Admin/Auditoria).

## Wave 27 deltas (saturate ≥95)

| Eixo | Antes (W23+W24+W25) | W27 | Δ |
|---|---|---|---|
| D7 LGPD shim canônico | Module-only `Modules/Governance/Config/retention.php` | + `config/retention.governance.php` (espelha ads + whatsapp pattern) | +2 |
| D9 Observability OTel | `OtelHelper::span` ja wrapping `governance:health` + `ScopedScorecardEvaluator::evaluateScorecard` | Pest cobre cenários zero-cost + active path | +1 |
| D6 Performance (Inertia::defer) | ModuleGradeController index/show já defer | Pest Wave 27 trava regressão | +1 |
| C3 Reflexividade | BRIEFING referencia 0156-0159 | + 0160 + 0161 promovidos referência canon | +2 |
| C5 Cobertura buckets | YAMLs soltos sem catálogo | `memory/governance/buckets/_INDEX.md` formaliza | +2 |
| **Score esperado** | **~90** | **≥95** | **+5** |

## Referências

- [ADR 0094 Constituição v2](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0155 Module Grade v3](../../memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md)
- [ADR 0160 Governance v4 Scoped Scorecards](../../memory/decisions/0160-governance-v4-scoped-scorecards-bucket-aware.md)
- [ADR 0161 Aposentar hacks v3](../../memory/decisions/0161-governance-v4-aposentar-hacks-0159-redundantes.md)
- [`memory/governance/buckets/_INDEX.md`](../../memory/governance/buckets/_INDEX.md)
- [SCOPE.md](./SCOPE.md)
- [CHANGELOG.md](./CHANGELOG.md)
