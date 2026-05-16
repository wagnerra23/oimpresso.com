# OBSERVABILITY — Modules/Jana

> Declaração canônica de pontos de hook OTel (D9.a Observability v3 — 2026-05-16).
> **Jana JÁ tem OTel forte** — `Modules/Jana/Services/Memoria/Telemetry/RetrievalSpan*.php` (POPO + Builder + Decorator) está implementado seguindo OTel GenAI semantic conventions 2026. Este doc CATALOGA o que existe + declara spans futuros.

## Spans canônicos JÁ IMPLEMENTADOS (verificáveis no código)

| Service / Método | Span name | Status | Atributos |
|---|---|---|---|
| `RetrievalTelemetryDecorator::recall()` | `jana.retrieval.recall` | ✅ live | `business_id`, `query.sha256`, `count.results`, `driver` (meilisearch/null/mcp), `reranker` |
| `MeilisearchDriver::recall()` (decorated) | `jana.retrieval.meilisearch` | ✅ live | `business_id`, `index`, `limit`, `hybrid.enabled` |
| `LlmReranker::rerank()` | `jana.rerank.llm` | ✅ live | `business_id`, `model`, `count.candidates`, `count.kept` |
| `HydeQueryExpander::expand()` | `jana.hyde.expand` | ✅ live | `business_id`, `query.sha256`, `count.expansions` |
| `LangfuseClient::recordSpan()` | (exporter) | ✅ live | Recebe `RetrievalSpan` POPO → POST Langfuse self-host CT 100 |

## Spans canônicos PLANEJADOS (gaps D9.a)

| Service / Método | Span name | Atributos obrigatórios | Trigger |
|---|---|---|---|
| `ContextSnapshotService::montar()` | `jana.context.snapshot` | `business_id`, `count.facts`, `count.tokens`, `angulos` (3 ângulos faturamento ADR 0052) | Cada chat turn |
| `BriefDiarioService::gerar()` | `jana.brief.gerar` | `business_id`, `data`, `count.sources` (vendas/inadimplencia/tickets/nfe/oportunidades), `provider` (groq/haiku) | Cron daily 06:00 BRT |
| `ApuracaoService::apurar()` | `jana.apuracao.apurar` | `business_id`, `periodo`, `count.metricas` | Comando artisan |
| `HealthSnapshotService::snapshot()` | `jana.health.snapshot` | `count.checks`, `count.failing` | Cron daily |
| `ProfileDistiller::distill()` | `jana.profile.distill` | `business_id`, `count.facts_before`, `count.facts_after`, `drift_detected` | Job weekly |
| `SemanticCacheService::lookup()` | `jana.cache.semantic` | `business_id`, `hit`, `similarity`, `ttl_remaining` | Cada query LLM |
| `RagasJudgeService::judge()` | `jana.ragas.judge` | `business_id`, `metric` (faithfulness/answer_relevancy/context_precision), `score` | Eval batch |

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
