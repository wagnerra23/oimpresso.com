---
id: requisitos-kb-changelog
---

# Módulo KB — CHANGELOG

> Mudanças significativas no Módulo KB Unificado. Entries por Wave/PR. Wave mais recente no topo.
> Para detalhes técnicos, ver `memory/sessions/YYYY-MM-DD-*.md` e ADRs canônicas.

## [Wave 25] — 2026-05-16

**Tema:** saturação dim D9 (observabilidade) + D3 (docs) + D7 (privacidade).

### Adicionado

- **`kb:health-check`** ([Modules/KB/Console/Commands/KbHealthCommand.php](../../../Modules/KB/Console/Commands/KbHealthCommand.php))
  - 4 checks SQL/serviço: `corpus_size`, `bridge_freshness`, `retrieval_latency`, `editable_ratio`
  - Flags: `--business-id` (Tier 0) ou `--all-businesses` (cron daily) + `--json` + `--detail` + `--bridge-threshold-h=24`
  - Output JSON estável para ingestão dashboard Cockpit V2 / alerta cron
  - Exit code agregado (0 ok/warn, 1 fail)
  - Wrap em `OtelHelper::span('kb.health.check', ...)` (zero-cost se `otel.enabled=false`)
  - Compatível com pattern `jana:health-check` (CLAUDE.md §Métricas saúde)
- **OTel spans expandidos** (ADR 0155 D9.a):
  - `kb.article.paginate` em [`KbArticleService::paginate`](../../../Modules/KB/Services/KbArticleService.php) — hot-path do browser KB
  - `kb.bridge_state.mark_run` em [`KbBridgeStateService::markRun`](../../../Modules/KB/Services/KbBridgeStateService.php) — correlate docs/edges com latency cron
- **10 Pest tests** em [`Modules/KB/Tests/Unit/KbHealthCommandTest.php`](../../../Modules/KB/Tests/Unit/KbHealthCommandTest.php) cobrindo:
  - Contract `--business-id` obrigatório
  - Boundaries `corpus_size`: vazio/pequeno/saudável → fail/warn/ok
  - `bridge_freshness`: nunca rodou vs recente
  - `editable_ratio`: mix saudável
  - Multi-tenant isolation cross-tenant (biz=1 vs biz=99) — Tier 0 ADR 0093
  - JSON output shape estável
  - OTel fail-safe `otel.enabled=false`

### Resultado

- 10/10 Pest tests passed (47 assertions, 4.24s)
- 12/12 MultiTenantTraitTest passed sem regressão (16 assertions, 4.54s)
- Total OtelHelper coverage em Services: 4/8 (era 4, mantém — adiciona em 2 services novos)

---

## [Wave 23] — 2026-05-15

**Tema:** RAG Quality (BGE reranker + RAGAS port + drift detector).

### Adicionado

- **`KbBgeRerankerService`** — segunda passada de reranking pós-Meilisearch via BGE/Cohere/local
- **RAGAS-style eval port** — métricas faithfulness/answer_relevancy/context_precision
- **`kb:drift-detector`** ([Modules/KB/Console/Commands/KbDriftDetectorCommand.php](../../../Modules/KB/Console/Commands/KbDriftDetectorCommand.php)) — alerta quando artigos KB referem arquivos deletados/movidos no git nos últimos 30 dias

### Score Bench v2

56 → 75 (+19 pontos).

---

## [Wave 17] — 2026-05-15

**Tema:** LGPD (D7) + retention.php.

### Adicionado

- [`Modules/KB/Config/retention.php`](../../../Modules/KB/Config/retention.php) — política de retenção declarativa per-tabela
- `LogsActivity` (Spatie) em `KbNode` + `KbComment` — audit trail LGPD Art. 37
- `BRIEFING.md` (este documento foi criado nesta wave)

---

## [Wave 11] — 2026-05-14

**Tema:** D2 multi-tenant trait DRY.

### Adicionado

- [`BelongsToBusinessTrait`](../../../Modules/KB/Entities/Concerns/BelongsToBusinessTrait.php) — global scope `business_id` + auto-fill via `creating()` event
- Aplicado em todos os 12 Entities KB (`KbNode`, `KbComment`, `KbFavorite`, `KbEdge`, etc)
- [`MultiTenantTraitTest`](../../../Modules/KB/Tests/Feature/MultiTenantTraitTest.php) — 12 specs covering scope + auto-fill + superadmin path

---

## [ONDA 0-5] — 2026-05-16 (PR #934)

**Tema:** Foundation. 132 arquivos, +25.465 LOC, 7 agents paralelos.

### Adicionado

- 12 tabelas `kb_*` (categories, subcategories, nodes, edges, paths, path_steps, decision_trees, decision_tree_steps, node_versions, favorites, comments, bridge_state)
- `Modules/KB/Entities/*` (12 models) + Factories + Seeders
- `Modules/KB/Http/Controllers/*` (11 controllers) + Requests
- `Modules/KB/Services/KbRagService` + `KbCorpusBuilder` + `KbArticleService` + `KbBridgeStateService`
- `Modules/KB/Jobs/KbBridgeFromMcpJob` + `KbEdgeAutoDeriverJob`
- Frontend tri-pane Inertia (port `kb-page.jsx` → React 19/TS)
- `kb:reindex` artisan command
- [ADR 0150 KB Unificado](../../decisions/0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md) ACEITA

### Score Bench v2

0 → 56 (foundation completa).

---

## Implementação (histórico movido de `Modules/KB/CHANGELOG.md`)

> Movido em 2026-08-10. Os dois changelogs registravam eventos DIFERENTES — acima as
> decisões/requisitos, aqui o que foi de fato mergeado. Medido antes de fundir:
> sobreposição de datas entre os dois era 0-2 de 2-7, logo nenhum era cópia do outro
> e escolher um lado perderia registro. Conteúdo preservado na íntegra.

# CHANGELOG — Modules/KB

Append-only. Cada PR mergeado que toca `Modules/KB/` deve adicionar 1 linha na entrada do Wave/data.

## Wave 28 — 2026-05-17 (SATURATION FINAL functional+AI → ≥95)

- `Tests/Feature/Wave28SaturationTest.php` — D2 KbRagService contract source-level (3 casos): `ask()` exige `$businessId` parâmetro obrigatório Tier 0 (não session()) + PiiRedactor + assertBusinessId defesa duas camadas + `askCacheKey` inclui businessId+corpusHash (anti-vazamento cross-tenant). D9 catalog confirmação 4 spans canônicos hot-path RAG (`kb.rag.ask` + `kb.corpus.retrieve` + `kb.rerank.bge_v2_m3` + `kb.health.check`).
- Notes: Tier 0 ADR 0093 §"Services com $businessId param explícito" reforçado. KbRagService cobre 3 capacidades canônicas (ask/summarize/suggestMeta) — Wave 28 focou ask() hot-path por ser único método que chama LLM real (custo + latência crítica). Pattern alinhado Wave 26 (file_get_contents + reflexão, zero hit prod sem MySQL).

## Wave 18 RETRY — 2026-05-16 (governança meta-97)

- `module.json`: declarado `fsm_n_a: true` + razão — KB é read-mostly, sem ciclo transacional (vs Sells/Repair ADR 0143). Fecha gap fsm-coverage falso-positivo.
- `CHANGELOG.md` (este arquivo) + `README.md` criados — preencher D3 "docs internas" boost.
- `Tests/Feature/MultiTenantTraitTest.php`: datasets cross-tenant ampliados (KbNode/KbComment/KbFavorite × biz 1↔99).
- `Tests/Unit/KbArticleServiceUnitTest.php` criado — extract Service paginate/show coverage isolado.

## Wave 17 — 2026-05-16 (D4 service extraction)

- `Services/KbArticleService.php`: extraído de `KbNodeController` (thin extraction sem regressão de payload).

## Wave 11 — 2026-05-15 (D2 saturation)

- `Entities/Concerns/BelongsToBusinessTrait.php`: trait auto-fill + global scope (defense-in-depth nível Model).
- `Tests/Feature/MultiTenantTraitTest.php`: 10+ cenários cross-tenant biz=1 vs biz=99.
- `Tests/Feature/LgpdComplianceTest.php`: audit trail Spatie + retention windows.
- `Tests/Feature/GovernanceInvariantsTest.php`: bridge invariants (is_editable=false ⇒ body_blocks IS NULL).

## ONDA 1 — 2026-05-15 (skeleton inicial)

- Skeleton 12 entities + 12 migrations + 6 controllers + permissions.
- Charter `Pages/kb/Index.charter.md` + Sync Cowork v5 commitado em `prototipo-ui/prototipos/kb/`.

---

**Como atualizar este CHANGELOG:** ao terminar uma feature PR-merge, adicionar 1 entrada datada no TOPO da seção Wave ativa. Skill `brief-update` (Tier B) auto-trigger lembra. NUNCA editar entradas antigas (append-only — drift = vetor #1 incidente catalogado).
