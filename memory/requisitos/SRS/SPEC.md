---
module: SRS
na_justified:
  D5: "Módulo backend interno doc generation cross-business sem cliente externo direto. Serve geração/ingestão de SPEC/PRD/evidências pra TODOS módulos do oimpresso (uso Wagner backoffice). Cliente piloto ROTA LIVRE não interage com SRS — é tooling interno ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §SoC brutal + [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) §cliente sinal qualificado: dormente até cliente externo reportar dor). Sucessor MCP server canon já cobre o caso."
  D6.b: "SRS é ferramenta interna Wagner — uso raro, sem tráfego que justifique medir p99 OTel <500ms. Instrumentação OTel project-wide pendente. Sucessor natural é MCP server canon (`mcp.oimpresso.com`) — SRS pode ser descontinuado em ADR futura."
related_adrs: [0093, 0094, 0105, 0153, 0154, 0155]
---

# SPEC — Modules/SRS

> **Atualizado 2026-05-16:** placeholder de 2026-05-04 substituído por SPEC realista do estado atual. Hipóteses A/B/C/D do placeholder foram resolvidas na prática — módulo virou ferramenta interna Wagner pra ingestão+search de documentação (versão lite do que o MCP server faz hoje).
>
> **SRS = Software Requirements System.** Módulo legado de uso interno raro, ingere documentação (PDF/Markdown/HTML), indexa em `docs_evidences` (FULLTEXT MySQL), permite chat assistido sobre o corpus e gera relatórios de validação de requisitos. Usa prefix `memcofre` em algumas tabelas/views por herança da fase anterior (cofre de docs).

## Contexto

- **Stack:** Laravel 13.6 + Blade legacy
- **Tabelas:** `docs_sources`, `docs_evidences` (FULLTEXT), `docs_requirements`, `docs_links`, `docs_chat_messages`, `docs_pages`, `docs_validation_runs`
- **Owner:** Wagner (uso interno) — não exposto pra cliente final
- **Pré-requisito Tier 0:** todas as tabelas têm `business_id` indexado + FK ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- **Sucessor natural:** MCP server canon (`mcp.oimpresso.com`) — SRS pode ser descontinuado em ADR futura

## User Stories

### US-SRS-001 — Cadastrar fonte de documentação (Doc Source)
**Como** Wagner, **quero** registrar uma fonte de docs (URL/arquivo/pasta), **pra** o sistema saber de onde puxar evidências.
- Entity: `DocSource` (campos `name`, `kind`, `uri`, `business_id`)
- Controller: `IngestController::storeSource`
- Aceite: fonte com `kind=url` aceita HTTPS; `kind=file` aceita PDF/MD/HTML; `business_id` automático

### US-SRS-002 — Cadastrar requisito (Requirement) e linkar evidências
**Como** Wagner, **quero** criar um requirement (texto curto, ex: "LGPD Art. 7º opt-in") e linkar evidências do corpus que o sustentam, **pra** rastrear cobertura.
- Entity: `DocRequirement` + `DocLink` (M2M com evidence)
- Aceite: link tem `confidence` (0-100); permite múltiplas evidências por requirement

### US-SRS-003 — Ingest job (process source → evidences)
**Como** Wagner, **quero** rodar `php artisan srs:ingest <source_id>`, **pra** o sistema baixar/parsear/indexar uma fonte e popular `docs_evidences`.
- Command: `SyncMemoriesCommand` ou `SyncPagesCommand`
- Service: `RequirementsFileReader` + `MemoryReader`
- Aceite: ingest idempotente (re-run não duplica); registra run em `docs_validation_runs`; FULLTEXT index aplicado pós-ingest

### US-SRS-004 — Search hybrid (FULLTEXT + chat)
**Como** Wagner, **quero** buscar evidências por termo natural via FULLTEXT, **pra** encontrar trecho que sustenta um requirement.
- Controller: `ChatController::search` + `MemoriaController`
- Service: `ChatAssistant` (consome `docs_evidences` via FULLTEXT `MATCH ... AGAINST`)
- Aceite: retorna top-K com `score` MySQL native; respeita `business_id` scope

### US-SRS-005 — Audit log SRS (validation runs)
**Como** Wagner, **quero** ver histórico de ingest/validate runs, **pra** auditar quando foi ingerido o quê e se houve erro.
- Entity: `DocValidationRun` (audit trail)
- Tela: `DashboardController::index`
- Aceite: append-only; campos `status`, `started_at`, `finished_at`, `error`, `payload`

### US-SRS-006 — Memcofre legacy prefix (compat layer)
**Como** dev migrando docs antigos, **quero** que rotas/views legadas com prefixo `memcofre` continuem respondendo, **pra** não quebrar bookmarks Wagner.
- Resource: `Resources/lang/pt/memcofre.php`
- Routes: alias em `Http/routes.php`
- Aceite: rotas `memcofre.*` redirecionam pra `srs.*` mantendo querystring

## Anti-padrões (NÃO fazer)

- ❌ `withoutGlobalScopes()` em queries de docs — vaza evidência cross-tenant
- ❌ Apagar `docs_validation_runs` (audit append-only)
- ❌ DDL direto em `docs_evidences` FULLTEXT — usar migration + `validate-fulltext.sql`
- ❌ Expor SRS pra cliente externo sem ADR (escopo backoffice Wagner)
- ❌ Investir em features novas — sucessor MCP server canon já cobre o caso

## Testes existentes (Wave B)

- `Tests/Feature/MultiTenantIsolationTest.php`
- `Tests/Feature/ScaffoldTest.php`
- `Tests/Feature/SmokeRoutesTest.php`

## D7 LGPD + D8 Security (Wave 11)

- **D7.a PiiRedactor** — `Services/ChatAssistant.php` sanitiza `$e->getMessage()` + pergunta antes de `Log::warning` (vetor: erros OpenAI podem ecoar payload com PII)
- **D7.b LogsActivity** — `Entities/DocSource` (logFillable) + `Entities/DocChatMessage` (logOnly metadata, sem content) via Spatie Activitylog
- **D7.c Retention** — `Config/retention.php` declara janelas (generated_docs 1825d / drafts 90d / generation_logs 365d / chat_messages 365d)
- **D8.a Throttle** — `Http/routes.php` middleware `throttle:60,1` em todos 2 grupos (memcofre/* + srs/install/*). CLI commands fora do escopo (não passam por route).
- **D8.c FormRequest** — `Http/Requests/StoreIngestRequest` (MIME whitelist upload) + `Http/Requests/ChatAskRequest` (regex session_id + cap 2000 chars LLM cost)

## Histórico

- 2026-05-04: SPEC criada como placeholder (hipóteses A/B/C/D pendentes)
- 2026-05-16: substituída por SPEC realista do estado atual — módulo virou tool interna Wagner (US-SRS-001..006 cobrem o código existente)
- 2026-05-16 (Wave 11): D7 LGPD (PiiRedactor + LogsActivity + retention) + D8 Security (throttle + 2 FormRequests) — SRS 52→60 esperado
