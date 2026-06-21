---
slug: 0280-postura-multi-tenant-tabelas-mcp-governanca
number: 280
title: "Postura multi-tenant das tabelas mcp_* — governança de plataforma é repo-wide (sem business_id) by-design; não é vazamento"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-06-15"
module: governance
tags: [governanca, multi-tenant, business_id, mcp, plataforma, tier-0, schema]
supersedes: []
superseded_by: []
related:
  - 0070-jira-style-task-management-current-md-removed
  - 0093-multi-tenant-isolation-tier-0
  - 0144-tasks-db-canonico-spec-template
---

# ADR 0280 — Postura multi-tenant das tabelas `mcp_*`: governança é repo-wide by-design

## Contexto

A regra Tier 0 do projeto é absoluta: **toda tabela de negócio** tem `business_id` com
global scope ([ADR 0093](0093-multi-tenant-isolation-tier-0.md) · vazar dado entre tenants
é o pior bug possível). A `.claude/rules/migrations.md` repete isso e prevê uma **exceção
repo-wide** (ex: `users`, `permissions`) que "vira ADR".

O módulo Jana criou ~45 tabelas `mcp_*`. Parte é dado **do cliente** (uso por business,
alertas por business, sessões Claude Code por business). Outra parte é dado **da
plataforma** — o backlog Jira-style, os eventos de task, os leases de coordenação do time.
Essas últimas **não têm** `business_id` e isso é **proposital**, não esquecimento.

O gatilho desta ADR foi a tabela nova `mcp_work_leases` ([ADR 0278](0278-arquitetura-rede-ia-duravel-anti-vazamento.md),
migration `2026_06_15_140000`): ela espelha `mcp_tasks` (sem `business_id`) e justifica a
exceção no próprio docblock. Um auditor de schema repo-wide ("toda tabela precisa de
`business_id`") sinalizaria isso como vazamento Tier 0 — **falso positivo**. Faltava a ADR
canônica que documenta a postura, pra que o gate (e o time MCP entrando: Felipe/Maiara/
Eliana/Luiz) saiba distinguir os dois grupos sem reabrir a discussão a cada migration.

## Decisão

As tabelas `mcp_*` se dividem em **dois grupos**, e a ausência de `business_id` no grupo de
governança é **invariante by-design**, não uma falha de isolamento:

### Grupo A — Governança de plataforma (REPO-WIDE, SEM `business_id`)

São o estado vivo do desenvolvimento de uma **única organização** (oimpresso). Coordenação
de time é cross-business por natureza — uma US, um cycle, um lease pertencem ao **projeto**,
não a um cliente. Citadas no spec, mais o conjunto Jira-style ([ADR 0070](0070-jira-style-task-management-current-md-removed.md) ·
[ADR 0144](0144-tasks-db-canonico-spec-template.md): o DB é canon do estado vivo):

- **`mcp_tasks`** — backlog canônico (US/subtask). Precedente raiz.
- **`mcp_task_events`** — timeline append-only por task.
- **`mcp_work_leases`** — lease "no máx 1 sessão/agente por task ativa" ([ADR 0278](0278-arquitetura-rede-ia-duravel-anti-vazamento.md)).
- Demais do estado Jira-style: `mcp_task_comments`, `mcp_jira_projects`, `mcp_epics`,
  `mcp_cycles`, `mcp_cycle_goals`, `mcp_components`, `mcp_workflows`, `mcp_issue_templates`,
  `mcp_views`, `mcp_inbox_notifications`, `mcp_task_dependencies`, `mcp_task_watchers`,
  `mcp_task_attachments`, `mcp_task_memory_links`, `mcp_git_links`, `mcp_automation_runs`,
  `mcp_memory_documents` / `_history` (cache git-synced do conhecimento canônico),
  `mcp_tokens` / `mcp_quotas` / `mcp_cc_blobs`, `mcp_skill_versions` / `_labels` /
  `_approvals`.

### Grupo B — Dado de cliente / por-tenant (TEM `business_id`, global scope)

Onde existe sinal real de tenant (uso, custo, alertas, sessões e mensagens do cliente),
a regra Tier 0 vale integralmente — `business_id` NOT NULL + index + FK + global scope:

- **`mcp_audit_log`** — TEM `business_id` (coluna + `mcp_al_biz_ts_idx` + FK→`business`).
  É o caso canônico do grupo B: auditoria é segregada por tenant.
- `mcp_scopes`, `mcp_user_scopes`, `mcp_usage_diaria`, `mcp_alertas`, `mcp_alertas_eventos`,
  `mcp_cc_sessions`, `mcp_cc_messages`, `mcp_skills`, `mcp_skill_test_runs`,
  `mcp_handoff_summaries`, `mcp_handoff_diffs`, `mcp_weekly_digests`, `mcp_doc_summaries`,
  `mcp_scorecard_ai_suggestions`, `mcp_automations`.

## Invariante

> **Ausência de `business_id` numa tabela `mcp_*` de governança NÃO é vazamento de tenant** —
> porque (1) é dado de **plataforma** (estado de desenvolvimento), não dado de **cliente**, e
> (2) o MCP serve **1 organização**, então não há fronteira de tenant a cruzar. A fronteira
> Tier 0 existe entre os clientes do ERP (Grupo B), não dentro da governança do próprio
> projeto (Grupo A).

Regras complementares:

1. **O default permanece Grupo B.** Toda tabela `mcp_*` nova nasce tenant-scoped salvo prova
   de que é puramente governança de plataforma. Na dúvida → `business_id` (lado seguro).
2. **A exceção precisa estar escrita no docblock da migration** (precedente `mcp_work_leases`:
   "SEM business_id: espelha mcp_tasks... Exceção a `.claude/rules/migrations.md` justificada
   aqui") **e** referenciar esta ADR — assim o gate de schema não acusa falso positivo.
3. **Esta ADR não cria nem altera coluna alguma** — é puramente declaratória da postura já
   implementada. ADR é append-only ([ADR 0093](0093-multi-tenant-isolation-tier-0.md) §lifecycle).

## Consequências

- O auditor de schema repo-wide pode usar a lista acima como allowlist do Grupo A (ou checar
  a referência a esta ADR no docblock) — para de gerar falso positivo de "tabela sem
  `business_id`" no backlog/leases/eventos.
- Time MCP entrando (Felipe/Maiara/Eliana/Luiz) tem a regra explícita: governança = repo-wide,
  cliente = por-tenant — sem reabrir a discussão por migration.
- Risco: alguém classificar como Grupo A uma tabela que de fato carrega dado de cliente. Mitigado
  pelo default (regra 1: na dúvida é Grupo B) e pela exigência de justificativa escrita + ref ADR
  na migration (regra 2), que vira artefato revisável no PR.
- Esta ADR é D2 (postura de governança Tier 0 multi-tenant) — aceita por Wagner em 2026-06-15.
