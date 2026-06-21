---
slug: projectmgmt-triage-analista-visual-comparison
title: "ProjectMgmt — Comparativo visual da Triagem/Analista (Forja PR-5a)"
type: visual-comparison
module: ProjectMgmt
status: approved
approved_by: wagner
approved_at: 2026-06-16
date: 2026-06-16
canon_reference: forja-cowork (.fj-triagem + dossiê)
blade_source: "N/A — evolui Page Inertia existente"
inertia_target: resources/js/Pages/ProjectMgmt/Triage/Index.tsx
pr_branch: feat/forja-pr5a-triagem-analista
---

# ProjectMgmt — Triagem/Analista (Forja PR-5a)

> **F1.5 do MWART V4** · PR-5a da onda **Forja**. Escopo **enxuto** escolhido por [W] (AskUserQuestion 2026-06-16): **evolui** a Triagem existente (decisão prévia "evoluir a existente", não duplicar), **sem mudança de schema/Tier-0**.

## Premissa corrigida vs prompt

O prompt PR-5 pedia tela "genuinamente nova" `/team-mcp/triagem`. A realidade: **já existe** `/project-mgmt/triage` (TriageController + Page + tool MCP `triage`). Decisão [W]: **evoluir a existente** com a camada Analista — sem rota/tela duplicada, sem novo estado de status (Tier-0). "F0/proposto" NÃO vira enum novo no v1.

## O que o PR-5a entrega (sobre a Triagem atual)

A Triagem hoje = fila de tasks órfãs (sem dono/prio/backlog) + atribuição inline. O PR-5a adiciona o **dossiê do Analista** (drawer) por task + **ações [W] aprova**:

- **Dossiê** (`GET /triage/{id}/dossier`, read-only, SÓ dado real):
  - **Valor × esforço** (SUGERIDO — derivado de prioridade + estimate_h; rotulado "sugerido").
  - **Risco Tier-0** (HEURÍSTICA por palavra-chave: multi-tenant/business_id/token/auth/migration/financeiro/lgpd…; rotulado "heurística").
  - **Requisitos do módulo** — link pro `memory/requisitos/<Mod>/SPEC.md`.
  - **Possíveis duplicatas** — `mcp_tasks` mesmo módulo (com ação **Fundir**).
  - **Histórico de decisão** — docs/ADRs do módulo via `mcp_memory_documents` (gated).
  - **Sessões CC** que citam o módulo via `mcp_cc_sessions` (gated).
  - **Atividade** — `mcp_task_events` reais.
- **Ações** ("agente propõe, [W] aprova" — AlertDialog confirma):
  - **Aprovar** → promove pro backlog ativo (status `todo`; exige dono+prio; só transiciona se backlog).
  - **Rejeitar** → `cancelled` (audit preservado).
  - **Fundir** → cancela + registra evento `field_updated` apontando a duplicata.

## Sem dado fantasma (§3)

Tudo projeta o que existe: duplicatas (mcp_tasks), atividade (mcp_task_events), docs (mcp_memory_documents, gated por `Schema::hasTable`), sessões (mcp_cc_sessions, gated). Valor×esforço e risco são **derivações/heurísticas explicitamente rotuladas** — não dados inventados.

## Decisões [W]

1. **Enxuto** — "proposta" sem novo enum/schema (sem ADR Tier-0). Estado F0 real fica pra PR-5b se necessário.
2. **RAG leve** — cross-link por módulo (sem ranking semântico pesado).
3. Evolui `/project-mgmt/triage` (sem duplicar tela).
4. Reusa `TaskCrudService` (mesma via da tool MCP → gera eventos + notifica).

## Restrições Tier 0

- Permissão `copiloto.mcp.usage.all` (construtor). `mcp_tasks`/eventos repo-wide by design (ADR 0070/0093). Ações via `TaskCrudService` respeitam FSM (`McpTask::TRANSITIONS`).

## Pendências / próximos
- Raw-palette residual nas chips de motivo da lista antiga (bg-amber-100…) — débito DS herdado; não regredido. Limpar num polish dedicado.
- **PR-5b** (se [W] quiser): estado F0 real (enum, ADR Tier-0) + RAG semântico rankeado.

## Gates antes do F3
- [x] Padrão Forja aprovado ([W] "pode seguir" + escopo enxuto escolhido).
- [x] Charter `Index.charter.md` (existe; atualizado com a seção Analista).
- [ ] CI: typecheck + eslint/lint-baseline + conformance + foundation + php -l. Smoke pós-merge das ações (aprovar/rejeitar/fundir).

---
**Status:** `approved` — implementado no PR `feat/forja-pr5a-triagem-analista`.
