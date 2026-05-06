---
module: ADS
purpose: "Adaptive Decision System — meta-orquestrador de decisões automatizadas (Risk → Confidence → Policy → Router → Brain A/B → HITL) + governança de Skills"
contains:
  # Decision flow controllers (canônico ADR 0001-0011 do ADS)
  - "Admin/ConfidenceController — Wilson lower bound, padrões aprendidos"
  - "Admin/ConflictsController — conflitos entre policies"
  - "Admin/DecisoesController — decisões registradas"
  - "Admin/LearningController — pattern learning loops"
  - "Admin/MetricasController — métricas de decisão"
  - "Admin/PatternsController — padrões aprendidos"
  - "Admin/PolicyController — Policy Engine (4 outcomes ALLOW/REQUIRE_REVIEW/BLOCK/REQUIRE_BRAIN_B)"
  # Skills governance (Fase 1-4 do 0076)
  - "Admin/SkillsController — UI /ads/admin/skills (lista/detalhe/edit/test/review/approve/publish)"
  - "Admin/MetaSkillsController — governance rules em mcp_governance_rules (promote/archive/escalate)"
  # API endpoints (públicos pra IA propor decisão)
  - "Api/ContextController — context lookup pra decisão"
  - "Api/DecisionController — propor decisão (entry point Risk→Confidence→Policy)"
  - "Api/RecentEventsController — eventos recentes pra ContextSnapshot"
  - "Api/ScopeController — scope lookup
  # Services (não-controllers mas parte do scope)
  - "Risk Engine — classifica risco da decisão"
  - "Confidence Engine — Wilson lower bound, padrões aprendidos"
  - "Decision Router — roteia entre Brain A/B/HITL"
  - "Brain B Service — modelo lento + estruturado"
  - "Reviewer Service — HITL queue"
  - "Pattern Learning Service — aprende de outcomes"
  - "Auto Task Generator Service"
  - "Planner Service / Project Decomposer Service"
  - "Tool Registry — MCP tools internos"
  - "Decision Links Service / User Scope Service / Context for Task Service"
  - "ScaffoldSkillFromMissionService — scaffolder via meta-skill"
  - "SkillScaffoldCommand — artisan skill:scaffold"
not_contains:
  - "Tasks/Cycles/Projects Jira-style → Modules/Project (ex-ProjectMgmt)"
  - "MCP tools registry (canônico) → Modules/TeamMcp"
  - "Knowledge graph / ADRs browsing → Modules/KB"
  - "TeamScopes (RBAC actor capabilities) → Modules/TeamMcp (a migrar)"
  - "ProjectsController (Jira-style) → Modules/Project (ex-ProjectMgmt — a migrar)"
trust_required: L1
owner: wagner
permission_prefix: ads.*
charter_adr: 0080
related_adrs:
  - 0001-arq-decision-engine-dual-brain
  - 0011-arq-policy-engine-4-outcomes
  - 0048-vizra-rejected-laravel-ai-canonical
  - 0073-team-mcp-skills-policies-superseded-by-0075
  - 0075-team-mcp-skills-ui-prompt-management-style
  - 0076-skills-db-primary-git-destino-drift-alert
  - 0078-constituicao-uma-frase-skill-unidade-evolucao
  - 0079-constituicao-oimpresso-7-camadas-governanca
url_prefixes:
  - /ads/admin/*
  - /api/ads/*
db_tables_owned:
  - mcp_decisions / mcp_decision_patterns / mcp_decision_links / mcp_decision_thresholds
  - mcp_dual_brain_decisions
  - mcp_confidence_scores
  - mcp_skills / mcp_skill_versions / mcp_skill_approvals / mcp_skill_test_runs / mcp_skill_labels
  - mcp_governance_rules (compartilhada com Modules/Governance — Fase 5)
drift_alerts: []
  # Fase 3.7 PR-1 (2026-05-06): 4 drift controllers movidos pros donos corretos.
  # Admin/ProjectsController → Modules/ProjectMgmt
  # Admin/ToolsController + Admin/TeamScopesController → Modules/TeamMcp
  # Admin/GraphController → Modules/KB
  # URLs mantidas em /ads/admin/* via use imports atualizadas em Routes/web.php.
---

# Modules/ADS — Adaptive Decision System

## Missão

ADS é o **cérebro de decisão automatizada** do oimpresso. Decide se uma ação proposta por IA (Brain A rápido / Brain B lento / Human) é executada, requer review, ou é bloqueada — baseado em risco + confiança + policies + padrões aprendidos.

ADS também governa **Skills** (artefatos canônicos de comportamento da IA): criação via `skill:scaffold`, versionamento, testes, aprovação, publicação pra git.

## Trust level

**L1 GOVERNANCE.** Wagner edita PolicyEngine, thresholds, governance rules. ADRs documentam toda mudança. Audit obrigatório.

Razão: ADS decide o que IA pode/não pode fazer. Bug em ADS = blast radius pra TODA IA conectada.

## Quando esta tela/módulo é tocado

| Trigger | Quem | Ação |
|---|---|---|
| Wagner abre `/ads/admin/skills` | L1 | listar 16 skills, ver drift, aprovar versions |
| Wagner roda `php artisan skill:scaffold "..."` | L1 | criar skill nova via meta-skill |
| IA propõe decisão (Brain A) | L2+ via API | Risk → Confidence → Policy decide outcome |
| Skill nova é editada via UI | L2 propõe, L1 aprova | review queue + publish-to-git |
| Pattern Learning roda diariamente | sistema (cron) | atualiza `mcp_decision_patterns` |
| Auto Task Generator | sistema | cria tasks em `mcp_tasks` baseado em padrões |

## Quando ADS NÃO é tocado

- ❌ Criar tarefa Jira-style → use Modules/Project (ex-ProjectMgmt)
- ❌ Editar token MCP → use Modules/TeamMcp
- ❌ Browsing de ADRs/sessions → use Modules/KB
- ❌ Editar configuração de business → use UltimatePOS Superadmin (L0)
- ❌ Decision flow custom por business sem ADR → bloqueado, abrir ADR antes

## Regras invariáveis

1. **Toda decisão da IA passa pelo Risk Engine.** Sem bypass.
2. **Policy outcomes são 4:** ALLOW_BRAIN_A, REQUIRE_BRAIN_B, REQUIRE_HUMAN_REVIEW, BLOCK_ALWAYS. Não estende.
3. **HITL queue não é opcional.** REQUIRE_HUMAN_REVIEW vai pra `mcp_dual_brain_decisions` aguardando Wagner.
4. **Skills são append-only por versão.** Edição = nova `mcp_skill_versions` row. Rollback = label move (não DELETE).
5. **Publish-to-git é separado de approve.** Approve = válido em DB; publish = vai pra `.claude/skills/<slug>/SKILL.md` (drift detection).
6. **Cada skill carrega 4 rationales obrigatórios** (why-now, alternatives-considered, breaking-changes, rollback) ao subir versão.

## Skills auto-load relevantes

| Skill | Quando carrega |
|---|---|
| `ads-decision-flow` | Trabalhar em Modules/ADS/ ou tocar fluxo de decisão |
| `meta-skill-roi-erp-autonomo` | Criar skill nova ou usar `skill:scaffold` |
| `multi-tenant-patterns` | Tocar código com `business_id` (ADS é multi-tenant via tabelas mcp_*) |
| `publication-policy` | Antes de publish-to-git de skill |

## Arquitetura interna

```
Action proposta por IA
    ↓
[RiskEngine] classifica low/medium/high/critical
    ↓
[ConfidenceEngine] consulta mcp_decision_patterns
    ↓
[PolicyEngine] decide outcome (1 dos 4)
    ↓
[DecisionRouter] roteia:
    ├── ALLOW_BRAIN_A → executa fast path
    ├── REQUIRE_BRAIN_B → BrainBService (modelo lento + estruturado)
    ├── REQUIRE_HUMAN_REVIEW → ReviewerService (HITL queue, Wagner aprova)
    └── BLOCK_ALWAYS → reject + log + alert
    ↓
[PatternLearningService] aprende com outcome final pra calibrar próximas decisões
```

## Tabelas DB (canônicas deste módulo)

- `mcp_decisions` — registro de cada decisão (action, risk, confidence, outcome, latency)
- `mcp_decision_patterns` — padrões aprendidos com Wilson lower bound
- `mcp_decision_links` — links cross-decision pra auditoria
- `mcp_decision_thresholds` — thresholds atuais por categoria
- `mcp_dual_brain_decisions` — HITL queue
- `mcp_confidence_scores` — scores históricos
- `mcp_skills` + `mcp_skill_versions` + `mcp_skill_approvals` + `mcp_skill_test_runs` + `mcp_skill_labels` — governance de Skills
- `mcp_governance_rules` — rules editáveis (compartilhada com Modules/Governance Fase 5)

## URLs próprias

- `/ads/admin/skills` (lista)
- `/ads/admin/skills/{slug}` (detalhe)
- `/ads/admin/skills/{slug}/edit` (editor)
- `/ads/admin/skills/{slug}/test` (test runner inline)
- `/ads/admin/skills-review` (approval queue)
- `/ads/admin/meta-skills` (governance rules editor)
- `/api/ads/decision` (endpoint público pra IA propor decisão)

## Drift resolvido (Fase 3.7 PR-1, 2026-05-06)

Os 4 controllers detectados no audit 2026-05-05 (ProjectsController, ToolsController, TeamScopesController, GraphController) foram movidos pros módulos donos corretos. URLs `/ads/admin/*` mantidas inalteradas — só `use` imports apontam pros novos namespaces.

## Como esta SCOPE.md evolui

Mudança nesta SCOPE.md requer:
1. ADR justificando (ex: novo controller, expansão de scope)
2. Cascade review §10.4 — auditar pre-commit hook + mcp_modules cache + Skills referenciando este módulo
3. Wagner aprova (L1 GOVERNANCE)

Mudança em `drift_alerts[]` (resolução do drift) é livre — vira nota de progress.

---

## Histórico

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Seed pattern pros outros 29 módulos. Drift atual documentado.
- **v1.1.0** (2026-05-06) — Fase 3.7 PR-1: 4 drift controllers movidos. drift_alerts vazio.
