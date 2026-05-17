# CHANGELOG — Modules/ADS

> Append-only. Mais novo no topo. Datas YYYY-MM-DD.

## [Wave 25 — Confirmação polish ≥85 Excelente] — 2026-05-16

### Sem alterações de código (saturação confirmada Wave 18 RETRY)
- D6.a `Inertia::defer`: 5 Controllers cobertos (Decisoes/Learning/Metricas/Patterns/Conflicts) — Wave 17/18.
- D8.c FormRequests: **14/15 ratio = 0.93** (3 + 6 + 5 da Wave 18+18RETRY) — Wave 25 NÃO adiciona mais (último 1/15 é Show endpoint sem body — N/A).
- D9.a OTel: `SkillsService::listAll/findBySlug` envoltos em `OtelHelper::span` desde Wave 18.
- Dual-brain pattern Tier 0 preservado: `mcp_dual_brain_decisions` + `mcp_decision_patterns` + `mcp_skills_versions` isolation Pest cobertura Wave 18+18RETRY.

### Status atual confirmado
- **Score 83 (Excelente)** mantido — bucket `internal_governance_active` level (ADR 0159).
- ADS é meta-orquestrador (não state machine domínio) — `governance.fsm_n_a=true` documentado em `module.json` desde Wave 18.

### Referências
- ADR 0093 Multi-tenant Tier 0
- ADR 0155 Module Grade v3
- ADR 0159 Wave 25 polish — sem regressão Wave 18 RETRY

## [Wave 18 RETRY — Saturação ADS+ProjectMgmt full] — 2026-05-16

### Adicionado
- **6 FormRequests novos** em `Http/Requests/` (D8.c — ratio agora 14/15 = 0.93):
  - `ToggleMetaSkillRequest` — POST /admin/meta-skills/{id}/toggle (active boolean opcional)
  - `ValidateMetaSkillRuleRequest` — POST /admin/meta-skills/validate (DSL min:3 max:5000)
  - `MoveSkillLabelRequest` — POST /admin/skills/{slug}/move-label (label in:A,B,C)
  - `PublishSkillVersionRequest` — POST /admin/skills/versions/{id}/publish (force_active opt)
  - `ExecuteToolRequest` — POST /admin/tools/{name}/execute (timeout 1-120s + dry_run)
  - `DecomposeProjectRequest` — POST /admin/projects/{id}/decompose (confirm:accepted custo LLM)
- **Pest `CrossTenantSaturationRetryTest`** (Tests/Feature/) — 8 testes:
  - Cross-tenant em mcp_skills_versions biz=1 vs biz=99 (HiTL skills editáveis)
  - 6 FormRequest sanity (rules + messages PT-BR + autorize)
  - 1 autorize batch check todos novos FormRequests

### Alterado
- `DecisoesController::index` — `Inertia::defer` em decisions (50 rows × DecisionPresenter::explain) + kpis (5 COUNT por destination/outcome) (D6.a — pula re-render em troca de tab)
- `LearningController::index` — `Inertia::defer` em stages (9 COUNT em mcp_dual_brain_decisions + 2 em mcp_decision_patterns) + throughput (24 buckets GROUP BY DATE_FORMAT) + kpis (D6.a — total ~12 aggregations defer)

### Referências
- ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0101 Tests biz=1 (nunca cliente)
- ADR 0155 Module Grade v3 (D6.a saturated 10+ / D8.c 14 FormRequests)
- ADR 0159 Wave 18 internal_governance_active level

## [Wave 18 Saturação] — 2026-05-16

### Adicionado
- **8 FormRequests** em `Http/Requests/` (D8.c — ratio 8/15 = 0.53):
  - `ApproveDecisionRequest` — POST /admin/decisoes/{id}/approve (note opcional max:500)
  - `RejectDecisionRequest` — POST /admin/decisoes/{id}/reject (reason max:2000)
  - `DismissDecisionRequest` — POST /admin/decisoes/{id}/dismiss
  - `StoreSkillRequest` — POST /admin/skills/{slug} (content required max:50000)
  - `ApproveSkillVersionRequest` — POST /admin/skills/versions/{id}/approve
  - `RejectSkillVersionRequest` — POST /admin/skills/versions/{id}/reject (reason required min:5)
  - `StoreMetaSkillRequest` — POST /admin/meta-skills (tier in:A,B,C)
  - `TestSkillRequest` — POST /admin/skills/{slug}/test (timeout 5-120s)
- **Pest `CrossTenantSaturationTest`** (Tests/Feature/) — 9 testes:
  - Cross-tenant em mcp_confidence_scores biz=1 vs biz=99
  - Cross-tenant em mcp_decision_patterns biz=1 vs biz=99
  - 6 FormRequest sanity check (rules + messages PT-BR)
  - 1 autorize check (todos FormRequests retornam true — middleware auth upstream)

### Alterado
- `MetricasController::index` — `Inertia::defer` em kpis/distribuicao/por_dominio/por_event_type (D6.a — 10+ aggregations COUNT/SUM/GROUP BY pulam initial render)
- `PatternsController::index` — `Inertia::defer` em patterns/candidates/drifts/kpis (D6.a — Wilson lower bound + drift detection)
- `ConflictsController::index` — `Inertia::defer` em 3 detectores (file_lock 7d scan + drift + human_ai judgment)
- `SkillsService::listAll() + findBySlug()` — envolvidos em `OtelHelper::span` (D9.a measure DB→filesystem fallback)
- `module.json` — `governance.fsm_n_a: true` + razão documentada (ADS é meta-orquestrador, não state machine domínio Sells/Repair — ADR 0143 N/A)
- `config/governance/module_clients.yaml` ADS: `backlog_hipotese` (3pts) → `biz_1_wagner_active` (10pts) — Wagner usa Inbox HiTL diário; ConfidenceEngine aprende com aprovações reais

### Não alterado (intencional)
- DecisoesController/SkillsController/MetaSkillsController métodos `approve/reject/store` mantêm assinatura `Request $request` — FormRequests novos são opt-in (PR seguinte fará type-hint upgrade gradual)
- `Inertia::defer` NÃO aplicado em ConfidenceController/PolicyController/DecisoesController — payloads enxutos ou paginados (não pesados)

### Referências
- ADR 0093 Multi-tenant Tier 0
- ADR 0101 Tests biz=1 (nunca cliente)
- ADR 0105 Cliente como sinal (ADS ainda dormente Wagner-only)
- ADR 0155 Module Grade v3 (D6.a defer + D8.c FormRequests)
- ADR 0159 Wave 18 internal_governance_active level
