---
module: Jana
purpose: "Chat IA do business — conversa, sugere metas, monitora execução. Tenancy híbrida (business_id nullable). Inclui custos LLM, qualidade RAG, alertas, períodos. Renomeado de Copiloto em Fase 3.7 PR-2 (2026-05-06). URLs/permissions/config keys mantêm prefixo legacy `copiloto.*` por compatibilidade."
contains:
  # Chat IA core
  - "ChatController — UI chat principal"
  - "DashboardController — resumo executivo"
  - "PainelController — Cockpit Analista IA (Jana V2) — visual canon chat-jana.jsx · US-JANA-PAINEL-001 ondas A1-D"
  - "Services/Memoria/* — recall hybrid (Hyde, Reranker, Meilisearch)"
  - "Entities/jana_memoria_* — memória persistente do business (rename ADR 0092)"
  # Custos / Qualidade
  - "Admin/CustosController — dashboard custos LLM"
  - "Admin/JanaProController — brief diário invocável (US-COPI-203)"
  - "Admin/QualidadeController — qualidade RAG/memória (RAGAS)"
  - "Admin/RoadmapController — timeline Gantt do cycle ativo (Onda 5 V1)"
  # Metas / Períodos
  - "MetasController — metas do business"
  - "PeriodosController — períodos de apuração"
  # Alertas
  - "AlertasController — alerts gerenciados pela IA"
  # Ghosts canon hub IA (stubs ADR 0182 + GUIA-SIDEBAR-V3)
  - "BriefController — stub /jana/brief (UI dedicada Onda C; brief gerado por BriefDiarioAgent)"
  - "RegrasController — stub /jana/regras (UI dedicada futura; policies PolicyEngine ADS + governance MCP)"
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
permission_prefix: jana.*
charter_adr: 0080
related_adrs:
  - 0035-laravel-ai-canonical-no-vizra
  - 0036-meilisearch-hybrid-driver
  - 0048-vizra-rejected-laravel-ai-canonical
  - 0052-contexto-negocio-3-angulos
  - 0053-mcp-server-governanca-como-produto
url_prefixes:
  - /jana/* (canônico Fase 2b 2026-05-09 — /copiloto/* mantido via 301 redirect generic)
db_tables_owned:
  - jana_memoria_facts
  - jana_memoria_metricas
  - jana_memoria_gabarito
  - jana_metas
  - jana_meta_periodos
  - jana_meta_fontes
  - jana_meta_apuracoes
  - jana_conversas
  - jana_mensagens
  - jana_sugestoes
  - jana_cache_semantico
  - jana_business_profile
  - jana_negative_cache
db_tables_legacy_views:
  # Views compat 30d criadas pela migration 2026_05_06_120000_rename_copiloto_tables_to_jana
  # Drop planejado: 2026-06-05 (ADR 0092)
  - copiloto_metas (view)
  - copiloto_meta_periodos (view)
  - copiloto_meta_fontes (view)
  - copiloto_meta_apuracoes (view)
  - copiloto_conversas (view)
  - copiloto_mensagens (view)
  - copiloto_sugestoes (view)
  - copiloto_memoria_facts (view)
  - copiloto_memoria_metricas (view)
  - copiloto_memoria_gabarito (view)
  - copiloto_cache_semantico (view)
  - copiloto_business_profile (view)
  - copiloto_negative_cache (view)
drift_alerts:
  # Fase 3.7 PR-1 (2026-05-06): 5 drift controllers movidos pros donos corretos.
  # MemoriaController + FontesController → Modules/KB
  # Mcp/CcIngest + Mcp/Health + Mcp/SyncMemoryWebhook → Modules/TeamMcp
  # URLs mantidas (/jana/memoria, /jana/metas/{id}/fonte, /api/mcp/*, /api/cc/*)
  # via tuple [Class::class, 'method'] e namespace prefix dos route groups.
  - controller: "Admin/GovernancaController"
    pertence_a: "Modules/Governance (NOVO)"
    motivo: "Governança consolidada vai pra módulo dedicado"
    eta_migracao: "Fase 5"
---

# Modules/Jana — Chat IA do business (ex-Copiloto)

## Missão

Jana é o **chat IA conversacional** que conhece o business do cliente. Acessa memória (Meilisearch hybrid), gera respostas contextualizadas, sugere metas, monitora execução. Multi-tenant via `business_id` (nullable pra superadmin).

Renomeada de **Copiloto → Jana** em Fase 3.7 PR-2 (2026-05-06). Rename PHP-only — fachada user-visible (URLs `/copiloto/*`, permissions `copiloto.*`, config keys, log channel `copiloto-ai`, Pages React `Pages/Jana/`, lang `copiloto::`) **mantida** por compatibilidade. Trust L2 PRODUCT.

## Quando este módulo é tocado

| Trigger | Quem | Ação |
|---|---|---|
| Cliente abre `/jana/chat` | Larissa, etc. | conversar com Jana |
| Wagner abre `/copiloto/admin/custos` | L1 | dashboard custos LLM |
| Wagner abre `/copiloto/admin/qualidade` | L1 | qualidade memória/RAGAS |
| Cron `apurar:metas` | sistema | apura metas do business |
| ExtrairFatosAgent roda | sistema | popula `jana_memoria_facts` (legado: view `copiloto_memoria_facts`) |

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

Rename Copiloto→Jana **completado em Fase 3.7 PR-2** (2026-05-06). Tabelas DB renomeadas em **PR-9 (2026-05-06)** (ADR 0092): 13 tabelas `copiloto_*` → `jana_*` com views legacy 30d (drop planejado 2026-06-05).

---

## Histórico

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Drift atual documentado. Rename Jana mapeado pra Fase 3.7.
- **v1.1.0** (2026-05-06) — Fase 3.7 PR-1: 5 drift controllers movidos pros donos corretos (KB/TeamMcp). URLs mantidas pra zero break.
- **v1.2.0** (2026-05-06) — Fase 3.7 PR-2: rename PHP-only Copiloto→Jana. Pasta + namespace + ServiceProvider class + module.json + composer.json renomeados. URLs, permissions, config keys, log channels, Pages React e lang mantidos legacy `copiloto.*` por compatibilidade.
- **v1.3.0** (2026-05-06) — Fase 3.7 PR-9 (ADR 0092): rename DB tables `copiloto_*` → `jana_*` (13 tabelas) + classe Eloquent `CopilotoMemoriaFato` → `MemoriaFato`. Views legacy `copiloto_*` criadas como fallback ad-hoc 30 dias (drop 2026-06-05). FKs intra-Jana preservadas pelo `RENAME TABLE`. Migrations originais não tocadas (append-only). Pós-deploy: `composer dump-autoload` + `php artisan scout:import "Modules\Jana\Entities\MemoriaFato"`.
