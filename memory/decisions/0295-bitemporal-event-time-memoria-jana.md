---
slug: 0295-bitemporal-event-time-memoria-jana
number: 295
title: "aceitar e implementar bi-temporal event-time na memoria Jana (ratifica desenho 0074) — slice 1: schema + predicado puro"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-20"
module: jana
tags: [jana, memoria, bi-temporal, time-travel, event-time, tier-0]
supersedes: []
superseded_by: []
related:
  - 0074-temporal-validity-bi-temporal-time-travel
  - 0092-tabela-rename-copiloto-para-jana
  - 0093-multi-tenant-isolation-tier-0
  - 0036-replanejamento-meilisearch-first
---

# ADR 0295 — bi-temporal event-time na memoria Jana (aceita 0074; slice 1)

> Origem: auditoria do IA OS 2026-06-20 (gap T4). Wagner delegou ("faca o melhor") e ratificou ("vai"). Esta ADR **aceita o desenho da ADR 0074** (proposto) e registra a implementacao FATIADA. Como 0074 e append-only e tem historia desconexa, nao a edito in-place — esta ADR e a aceitacao + plano.

## Contexto

Hoje a memoria Jana e **uni-temporal**: `valid_from`/`valid_until` em `jana_memoria_facts` modelam SO o system-time (quando o sistema soube do fato). Falta o **event-time** (quando o fato valeu NO MUNDO). A vitoria de manchete de Zep/Graphiti (+18.5% acc em "knowledge updates", onde LLMs caem ~30%) vem justamente do **bi-temporal**: responder "X estava ativo em 15/04?" exige separar os dois eixos.

## Decisao

1. **Aceitar o desenho da ADR 0074** e shipar bi-temporal em **3 slices** (PRs <=300 linhas, todos pra review — Tier-0-adjacente):
   - **Slice 1 (este PR):** schema aditivo (`event_valid_from`, `event_valid_until`, `supersedes_id`) + fillable/casts + **predicado PURO** `BiTemporalResolver::vigenteEm()` (testavel no CI sem DB). **Sem mudanca de comportamento** — colunas nullable, nada as popula ainda.
   - **Slice 2:** tool MCP `memoria-historica` (time-travel `as_of`) + `buscarHistorico()` via **Eloquent/SQL direto** (NUNCA Scout — `shouldBeSearchable()` so indexa `valid_until=NULL`, entao fato superseded fica fora do index). Filtra `business_id`+`user_id` (ADR 0093); tool replica a checagem cross-tenant superadmin de `MemoriaSearchTool`.
   - **Slice 3:** deteccao automatica de update temporal (agent Haiku) **atras de flag OFF default** (`config('jana.memoria.supersede_detection.enabled', false)`) — flag OFF = job byte-identico ao legado; liga so em homolog. `atualizar()` grava `supersedes_id` (link explicito) sem deixar de ser append-only.

2. **Semantica do predicado event-time:** fato e event-valido em `asOf` se `(event_valid_from is null OU <= asOf)` E `(event_valid_until is null OU > asOf)` — inicio inclusivo, fim exclusivo; null-from = "desde sempre", null-until = "ainda vale". Fatos legados (sem event-time) ficam **sempre event-validos** — sem backfill retroativo (nao-decisao deliberada de 0074).

## Consequencias

- **+** Base pronta pra time-travel; predicado isolado e testado no CI (logica pura).
- **+** Append-only preservado: migration so adiciona colunas; `atualizar()` (slice 3) continua marcando `valid_until` + criando novo, so adicionando o link `supersedes_id`.
- **−** 3 PRs + custo LLM novo no slice 3 (mitigado por flag OFF default).
- **Pegadinhas registradas (Tier-0):** tabela e `jana_memoria_facts` (rename 0092, VIEW legacy — `Schema::hasTable` guard); Scout nao indexa superseded (buscarHistorico = Eloquent); indices nomeados <=64 char.

## Alternativas

- **So uni-temporal (status quo):** rejeitada — nao responde knowledge-updates, o gap de maior valor da auditoria de memoria.
- **Backfill de event-time nas linhas legadas:** rejeitada (0074) — sem fonte de verdade do event-time passado; legado = "sempre valido".
- **Grafo (Neo4j/Graphiti nativo):** fora de escopo; frontmatter-grafo-leve deliberado (ADR 0072). Bi-temporal tabular cobre o gap.

## Implementacao (slice 1)

- Migration `2026_06_20_000002_add_event_time_to_jana_memoria_facts` (aditiva, idempotente, `Schema::hasTable` guard).
- `MemoriaFato`: 3 colunas no fillable + casts.
- `BiTemporalResolver` (predicado puro) + teste de logica pura + job CI focado.
