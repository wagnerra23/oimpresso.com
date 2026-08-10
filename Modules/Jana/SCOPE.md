---
module: Jana
purpose: "Chat IA do business servido em /ia — conversa com memória, metas, períodos, alertas, custos LLM e qualidade RAG. ATENÇÃO fronteira em aberto: este módulo hospeda hoje também a plataforma MCP do time (servidor JSON-RPC /api/mcp, 44 tabelas mcp_* e 30 entidades Entities/Mcp/), cujo destino declarado em not_contains é Modules/Forja — movimento ainda não executado."
migracao_ui: "pendente — tem Blade servido, sem duvida de escopo; fila em module-surface --migracao"
contains:
  # Chat IA core
  - "ChatController — UI chat principal"
  - "IndexController — Painel (raiz /ia): brief · KPIs · análises · ações · metas"
  # PainelController: removido 2026-08-06 [W] — onda 1 da fusão das telas da Jana.
  #   Era hub de 3 links + `buildMockPayload()`; a capacidade (brief · KPIs ·
  #   análises · ações) já vive em IndexController com dado real do
  #   SellsCockpitAggregator. A `US-JANA-PAINEL-001` que esta linha citava NUNCA
  #   existiu no SPEC do módulo — era id fantasma, vivo só aqui, no charter e no
  #   teste (e daqui vazava pro catalog.json, que é derivado deste arquivo).
  - "Services/Memoria/* — recall hybrid (Hyde, Reranker, Meilisearch)"
  - "Entities/jana_memoria_* — memória persistente do business (rename ADR 0092)"
  # Custos / Qualidade — SAÍRAM pra Modules/Governance em 2026-08-05 (ADR 0366 §D-B).
  # Admin/CustosController e Admin/QualidadeController não moram mais aqui; os
  # Services (CustosService, MemoriaMetrica) FICARAM — mudou o dono da tela, não o do dado.
  - "Admin/JanaProController — brief diário invocável (US-COPI-203)"
  - "ProController — paywall Jana Pro (/ia/pro), tela de conversão F3 design (#2069)"
  # Admin/RoadmapController SAIU pra Modules/Forja em 2026-08-05 (ADR 0366 §D-B +
  # ADR 0367 D4): usa TaskCrudService/McpTask — é tasks, e tasks é Forja.
  # Metas / Períodos
  - "MetasController — metas do business"
  - "PeriodosController — períodos de apuração"
  # Alertas
  - "AlertasController — alerts gerenciados pela IA"
  # Ghosts canon hub IA (stubs ADR 0182 + GUIA-SIDEBAR-V3) — os DOIS foram apagados.
  # BriefController: removido 2026-06-15 [W] (stub redundante com brief-fetch/chat).
  #   ⚠️ Ficou listado aqui por ~7 semanas depois de deixar de existir — nenhuma
  #   máquina compara `contains` com a árvore, então o SCOPE apodreceu calado.
  # RegrasController: removido 2026-08-04 [W] — cobria policies do PolicyEngine ADS
  #   + governance MCP cross-team, DOIS domínios fora da Jana (núcleo do ADS foi pra
  #   Modules/Forja em jul/2026; este SCOPE declara só tabelas jana_*). Lia zero tabela.
  # Boilerplate
  - "DataController — sidebar/permissions"
  - "InstallController — install/uninstall hooks"
  - "SuperadminController — superadmin package"
  # Aprendizado com erro / Reflexion runtime
  - "LICOES-OPERACAO.md — ledger append-only dos erros de OPERAÇÃO da Jana (≠ saída, que golden/RAGAS cobrem); cada lição gradua MEC→check no jana:health-check ou JULG→regra sempre-lida. Proposta §10.4 (aguarda [W])"
  - "Console/Commands/HealthCheckCommand — check jana_lesson_ledger_graduation valida o loop de graduação do ledger (advisory)"
  # Recebido do Modules/ADS em 2026-07-31 (ADR 0363 — o módulo deixou de existir; skills→Jana, #5129).
  - "Services/SkillsService — LÊ o catálogo de skills (mcp_skills + fallback filesystem .claude/skills/<slug>/SKILL.md, ADR 0076). Só leitura: EDITAR skill é ato de arquivo+git, não deste módulo"
not_contains:
  - "Mcp/SyncMemoryWebhookController → Modules/Forja (MCP é plataforma, [W] 2026-07-30)"
  - "Mcp/HealthController → Modules/Forja (idem)"
  - "MemoriaController (browser KB) → Modules/KB"
  - "FontesController (knowledge sources) → Modules/KB"
  - "Mcp/CcIngestController → Modules/Forja"
  - "Admin/GovernancaController → Modules/Governance (NOVO Fase 5)"
  # A linha "Skills governance → Modules/ADS" saiu em 2026-08-10: o ADS foi REMOVIDO
  # em 2026-07-31 (ADR 0363) e a capacidade veio PRA CÁ — `Services/SkillsService.php`
  # (#5129), agora declarado em `contains`. Excluir skills daqui apontando pra um módulo
  # que não existe mentia DUAS vezes (destino morto + nega o que o módulo contém).
  # Destino era o ADS até a remoção dele em 2026-07-31 (ADR 0363); a política foi pro Governance (#5128).
  - "Decision flow (Risk/Confidence/Policy Engine) → Modules/Governance (Services/PolicyEngine.php, #5128)"
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
  # Mcp/CcIngest + Mcp/Health + Mcp/SyncMemoryWebhook → Modules/Forja
  # URLs mantidas (/jana/memoria, /jana/metas/{id}/fonte, /api/mcp/*, /api/cc/*)
  # via tuple [Class::class, 'method'] e namespace prefix dos route groups.
  # E2b (2026-07-30): Mcp/Health + Mcp/SyncMemoryWebhook passaram por aqui e
  # seguiram pra Modules/Forja no MESMO dia ([W] "MCP vai para Forja").
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
- ❌ Admin de tokens MCP → use Modules/Forja
- ❌ Editar skill → arquivo `.claude/skills/<slug>/SKILL.md` + git (este módulo só **LÊ**, via `SkillsService`)
- ❌ Triagem de tasks Jira-style → use Modules/Forja

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
