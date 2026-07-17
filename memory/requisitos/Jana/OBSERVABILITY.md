# OBSERVABILITY — Modules/Jana

> Declaração canônica de pontos de hook OTel (D9.a Observability v3 — 2026-05-16).
> **Jana JÁ tem OTel forte** — `Modules/Jana/Services/Memoria/Telemetry/RetrievalSpan*.php` (POPO + Builder + Decorator) está implementado seguindo OTel GenAI semantic conventions 2026. Este doc CATALOGA o que existe + declara spans futuros.

> **Atualização 2026-07-17 (destaleamento — grade de réguas observabilidade-agente):** os spans D9.a Wave 17 foram implementados mas este doc nunca foi atualizado. A tabela "PLANEJADOS" ficou 2 meses afirmando `não-live` sobre 6 spans que estão LIVE — e a grade de mercado leu o doc stale como "spans do agente não instrumentados" (falso-negativo). Corrigido abaixo com **os nomes REAIS de span + file:line** (os nomes antigos do doc — `jana.context.snapshot` etc — nunca bateram com o código: o real é `jana.context.para_business`). Fonte da verdade = o `OtelHelper::spanBiz(...)`/`::span(...)` no código, não esta tabela.

## Spans canônicos JÁ IMPLEMENTADOS (verificados no código · file:line = âncora)

| Service / Método | Span name (REAL) | Status | Âncora |
|---|---|---|---|
| `RetrievalTelemetryDecorator::recall()` | `jana.retrieval.recall` | ✅ live | `business_id`, `query.sha256`, `count.results`, `driver`, `reranker` |
| `MeilisearchDriver::recall()` (decorated) | `jana.retrieval.meilisearch` | ✅ live | `business_id`, `index`, `limit`, `hybrid.enabled` |
| `LlmReranker::rerank()` | `jana.rerank.llm` | ✅ live | `business_id`, `model`, `count.candidates`, `count.kept` |
| `HydeQueryExpander::expand()` | `jana.hyde.expand` | ✅ live | `business_id`, `query.sha256`, `count.expansions` |
| `LangfuseClient::recordSpan()` | (exporter) | ✅ live | `RetrievalSpan` POPO → POST Langfuse self-host CT 100 |
| `ContextSnapshotService::montar()` | `jana.context.para_business` | ✅ live | [ContextSnapshotService.php:21](../../../Modules/Jana/Services/ContextSnapshotService.php#L21) — nome antigo do doc: `jana.context.snapshot` |
| `BriefDiarioService::gerar()` | `jana.brief_diario.snapshot` | ✅ live | [BriefDiarioService.php:46](../../../Modules/Jana/Services/BriefDiarioService.php#L46) — nome antigo: `jana.brief.gerar` |
| `ApuracaoService::apurar()` | `jana.apuracao.run` | ✅ live | [ApuracaoService.php:30](../../../Modules/Jana/Services/ApuracaoService.php#L30) — nome antigo: `jana.apuracao.apurar` |
| `HealthSnapshotService::snapshot()` | `jana.health.snapshot` | ✅ live | [HealthSnapshotService.php:36](../../../Modules/Jana/Services/HealthSnapshotService.php#L36) |
| `ProfileDistiller::distill()` | `jana.profile.distill` | ✅ live | [ProfileDistiller.php:52](../../../Modules/Jana/Services/Memoria/ProfileDistiller.php#L52) |
| `SemanticCacheService::lookup()` | `jana.semantic_cache.buscar` | ✅ live | [SemanticCacheService.php:59](../../../Modules/Jana/Services/Cache/SemanticCacheService.php#L59) (`OtelHelper::span`) — nome antigo: `jana.cache.semantic` |
| `ContextualizerService` (contextual retrieval) | `jana.contextual_retrieval.contextualize` | ✅ live | [ContextualizerService.php:82](../../../Modules/Jana/Services/Memoria/Contextual/ContextualizerService.php#L82) — não estava catalogado |
| `DocumentChunker` (contextual retrieval) | `jana.contextual.chunk` | ✅ live | [DocumentChunker.php:34](../../../Modules/Jana/Services/Memoria/Contextual/DocumentChunker.php#L34) — não estava catalogado |
| `LaravelAiSdkDriver::emitirOtelGenAi()` | `gen_ai.span` (OTel GenAI) | ✅ live | turno LLM: tokens/custo/latência/erro + `gen_ai.business_id`; teste `OtelGenAiEmissionTest` (ADR 0051) |

## Spans canônicos PLANEJADOS (o que ainda NÃO é live)

| Service / Método | Span name | Atributos obrigatórios | Trigger |
|---|---|---|---|
| `RagasJudgeService::judge()` | `jana.ragas.judge` | `business_id`, `metric` (faithfulness/answer_relevancy/context_precision), `score` | **Batch, NÃO online** — decisão ADR 0318 (eval RAGAS roda em staging/gold-set, não sobre tráfego de prod). O `LangfuseClient::recordScore()` (transporte de score online) já existe; falta invocá-lo no caminho servido. É o gap #1 da dimensão observabilidade-agente (grade 2026-07-17). |

## Princípios Tier 0

- **OTel GenAI semantic conventions 2026** — atributos seguem https://opentelemetry.io/docs/specs/semconv/gen-ai/
- **Lightweight bridge atual** — POPO sem dependência `open-telemetry/sdk`; pronto pra SDK full quando CT 100 receber extensão PECL ([config/otel.php](../../../config/otel.php))
- **business_id SEMPRE atributo** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- **PII redaction** — `query` SEMPRE sha256 quando `config('copiloto.telemetry.redact_query')` = true (default true)
- **Zero-cost driver=null** — `NullMemoriaDriver` não cria span; `RetrievalTelemetryDecorator` checa flag antes de wrapping

## Exportadores configurados

| Exportador | Trigger | Onde |
|---|---|---|
| `LangfuseClient` | `recordSpan()` síncrono | CT 100 self-host (`langfuse.oimpresso.com`) |
| `mcp_audit_log` | INSERT 1 linha por query | Hostinger MySQL ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md)) |
| Log channel `copiloto-ai` | debug local | `storage/logs/copiloto-ai.log` |

## Refs
- [config/otel.php](../../../config/otel.php)
- [Modules/Jana/Services/Memoria/Telemetry/RetrievalSpan.php](../../../Modules/Jana/Services/Memoria/Telemetry/RetrievalSpan.php)
- [Modules/Jana/Services/Memoria/Telemetry/RetrievalSpanBuilder.php](../../../Modules/Jana/Services/Memoria/Telemetry/RetrievalSpanBuilder.php)
- [Modules/Jana/Services/Memoria/Telemetry/RetrievalTelemetryDecorator.php](../../../Modules/Jana/Services/Memoria/Telemetry/RetrievalTelemetryDecorator.php)
- ADR canon: 0035 (stack IA), 0052 (3 ângulos), 0053 (MCP), 0093 (multi-tenant)
