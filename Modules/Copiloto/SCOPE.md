---
module: Copiloto
will_rename_to: Jana
will_rename_at_phase: 3.7
purpose: "Chat IA do business (`Jana`) — conversa, sugere metas, monitora execução. Tenancy híbrida (business_id nullable). Inclui custos LLM, qualidade RAG, alertas, períodos."
contains:
  # Chat IA core
  - "ChatController — UI chat principal"
  - "DashboardController — resumo executivo"
  - "Services/Memoria/* — recall hybrid (Hyde, Reranker, Meilisearch)"
  - "Entities/copiloto_memoria_* — memória persistente do business"
  # Custos / Qualidade
  - "Admin/CustosController — dashboard custos LLM"
  - "Admin/QualidadeController — qualidade RAG/memória (RAGAS)"
  # Metas / Períodos
  - "MetasController — metas do business"
  - "PeriodosController — períodos de apuração"
  # Alertas
  - "AlertasController — alerts gerenciados pela IA"
  # Boilerplate
  - "DataController — sidebar/permissions"
  - "InstallController — install/uninstall hooks"
  - "SuperadminController — superadmin package"
not_contains:
  - "MemoriaController (browser KB) → Modules/KB"
  - "FontesController (knowledge sources) → Modules/KB"
  - "Mcp/CcIngestController → Modules/TeamMcp"
  - "Mcp/HealthController → Modules/TeamMcp"
  - "Mcp/SyncMemoryWebhookController → Modules/TeamMcp"
  - "Admin/GovernancaController → Modules/Governance (NOVO Fase 5)"
  - "Skills governance → Modules/ADS"
  - "Decision flow → Modules/ADS"
trust_required: L2
owner: wagner
permission_prefix: copiloto.*
charter_adr: 0080
related_adrs:
  - 0035-laravel-ai-canonical-no-vizra
  - 0036-meilisearch-hybrid-driver
  - 0048-vizra-rejected-laravel-ai-canonical
  - 0052-contexto-negocio-3-angulos
  - 0053-mcp-server-governanca-como-produto
url_prefixes:
  - /copiloto/* (vai virar /jana/* na Fase 3.7 com 301 redirect)
db_tables_owned:
  - copiloto_memoria_facts
  - copiloto_memoria_metricas
  - copiloto_memoria_gabarito
  - copiloto_metas
  - copiloto_periodos
  - copiloto_custos_llm
  - copiloto_alertas
drift_alerts:
  # Fase 3.7 PR-1 (2026-05-06): 5 drift controllers movidos pros donos corretos.
  # MemoriaController + FontesController → Modules/KB
  # Mcp/CcIngest + Mcp/Health + Mcp/SyncMemoryWebhook → Modules/TeamMcp
  # URLs mantidas (/copiloto/memoria, /copiloto/metas/{id}/fonte, /api/mcp/*, /api/cc/*)
  # via tuple [Class::class, 'method'] e namespace prefix dos route groups.
  - controller: "Admin/GovernancaController"
    pertence_a: "Modules/Governance (NOVO)"
    motivo: "Governança consolidada vai pra módulo dedicado"
    eta_migracao: "Fase 5"
---

# Modules/Copiloto — Chat IA do business (futuro: Jana)

## Missão

Copiloto é o **chat IA conversacional** que conhece o business do cliente. Acessa memória (Meilisearch hybrid), gera respostas contextualizadas, sugere metas, monitora execução. Multi-tenant via `business_id` (nullable pra superadmin).

Renomeação **Copiloto → Jana** prevista pra Fase 3.7 do ADR 0079. Trust L2 PRODUCT.

## Quando este módulo é tocado

| Trigger | Quem | Ação |
|---|---|---|
| Cliente abre `/copiloto/chat` | Larissa, etc. | conversar com Jana |
| Wagner abre `/copiloto/admin/custos` | L1 | dashboard custos LLM |
| Wagner abre `/copiloto/admin/qualidade` | L1 | qualidade memória/RAGAS |
| Cron `apurar:metas` | sistema | apura metas do business |
| ExtrairFatosAgent roda | sistema | popula `copiloto_memoria_facts` |

## Quando este módulo NÃO é tocado

- ❌ Browse de ADRs/sessions/specs canônicos → use Modules/KB
- ❌ Admin de tokens MCP → use Modules/TeamMcp
- ❌ Editar skill → use Modules/ADS
- ❌ Triagem de tasks Jira-style → use Modules/ProjectMgmt (futuro Project)

## Skills auto-load relevantes

- `copiloto-arch` — arquitetura canônica
- `memoria-recall-flow` — recall hybrid + 14 gotchas
- `multi-tenant-patterns` — business_id integrity

## Drift atual e plano

1 controller a migrar (Admin/GovernancaController vai pra Modules/Governance em Fase 5). Os 5 drift controllers anteriores (MemoriaController, FontesController, Mcp/CcIngest, Mcp/Health, Mcp/SyncMemoryWebhook) foram **resolvidos em Fase 3.7 PR-1** (2026-05-06).

Rename Copiloto→Jana fica pra PR-2 separado.

---

## Histórico

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Drift atual documentado. Rename Jana mapeado pra Fase 3.7.
- **v1.1.0** (2026-05-06) — Fase 3.7 PR-1: 5 drift controllers movidos pros donos corretos (KB/TeamMcp). URLs mantidas pra zero break.
