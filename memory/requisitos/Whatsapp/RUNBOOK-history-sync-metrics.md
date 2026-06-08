# RUNBOOK — History-sync metrics (US-WA-085)

> 3 counters Loki Hostinger pra observabilidade do Job assíncrono `PersistHistorySyncBatchJob` (PR #828, fila `database/whatsapp-history`).
> Pattern lightweight bridge: log estruturado com `metric_name` único → Loki agrega via logQL → Grafana counter equivalente Prometheus.
> Pareado com [dashboard `whatsapp-baileys.json` paineis 9/10/11](../../../infra/grafana/dashboards/whatsapp-baileys.json) + alertas `WhatsappHistoryChunkFailureRate*` ([whatsapp.yml](../../../infra/prometheus/alerts/whatsapp.yml)).

## O que é

3 contadores emitidos como log estruturado (channel `single` → `storage/logs/laravel.log` → Loki agent):

| Counter | Emitido em | Significado |
|---|---|---|
| `whatsapp_history_chunk_queued` | `ChannelBaileysWebhookController::handleHistorySync()` após dispatch | Daemon entregou chunk; webhook 202'd e enfileirou pro worker async |
| `whatsapp_history_chunk_processed` | `PersistHistorySyncBatchJob::handle()` ao final | Worker consumiu chunk; persisted/skipped/errors contados |
| `whatsapp_history_chunk_failed` | `PersistHistorySyncBatchJob::failed()` após 3 tries | Chunk esgotou retries; entradas vão pra `failed_jobs` |

Labels Prometheus-compatíveis (cardinality controlada — `business_id` é tenant ID inteiro, `channel_id` é inteiro):
`business_id`, `channel_id`, `sync_type`, `chunk_index`, `chunk_total`, `messages_count`, `attempt`, `duration_ms` (só processed).

## Como consultar (Loki logQL)

```logql
# Chunks enfileirados últimos 5min — sanity check daemon → Hostinger
sum by (business_id) (
  count_over_time({app="oimpresso",env="prod"} |= "whatsapp_history_chunk_queued" [5m])
)

# Chunks processados últimos 5min — sanity check worker queue:work
sum by (business_id) (
  count_over_time({app="oimpresso",env="prod"} |= "whatsapp_history_chunk_processed" [5m])
)

# Failure rate 15min — alerta dispara em >5%
sum(count_over_time({app="oimpresso",env="prod"} |= "whatsapp_history_chunk_failed" [15m]))
/
sum(count_over_time({app="oimpresso",env="prod"} |= "whatsapp_history_chunk_queued" [15m]))
```

## Thresholds normais vs anormais

| Sinal | Normal | Anormal |
|---|---|---|
| `queued/min` per biz | 0-30 (busts em pareamento) | sustentado >100/min sem pareamento ativo = bug daemon |
| Lag queued → processed | <60s (worker cron everyMinute) | >5min = worker travado/morto |
| `failed/15min` global | 0 | >0 investigar; >5% rate = alerta warning |
| `duration_ms` p95 | <30000 (chunk 50 msgs) | >120000 = timeout ($timeout=120s) → vai pra retry |

## Quando alerta dispara

- **`WhatsappHistoryChunkFailureRate`** (warning, 15m sustained): >5% de chunks com 3-tries exausto. Causas comuns: MySQL deadlock recorrente, `messages` table indexação ruim em burst, PHP-FPM memory_limit estourado.
- **`WhatsappHistoryChunkFailureRateCritical`** (critical, 5m sustained): >20%. Regressão ampla — acordar Wagner.

## Como investigar

1. `ssh hostinger 'tail -200 storage/logs/laravel.log | grep history-sync-job'` — logs recentes
2. `SELECT * FROM failed_jobs WHERE payload LIKE '%PersistHistorySyncBatchJob%' ORDER BY failed_at DESC LIMIT 20;` — chunks perdidos com stack trace
3. `SELECT COUNT(*) FROM jobs WHERE queue='whatsapp-history';` — backlog atual (>1000 dispara `WhatsappQueueDepthHigh`)
4. Cross-check daemon: dashboard painel "Receive rate (msgs/s) per-instance" — se daemon não está recebendo, problema é upstream (banimento/desconexão)

## Multi-tenant Tier 0

Todos os 3 logs SEMPRE carregam `business_id`. Log sem `business_id` = bug (Pest `R-WA-METRICS-004` enforce). PII redact: zero phone/E.164 cliente em payload — só counts e IDs internos (Pest `R-WA-METRICS-005` enforce whitelist).

## Referências

- [`Modules/Whatsapp/Jobs/PersistHistorySyncBatchJob.php`](../../../Modules/Whatsapp/Jobs/PersistHistorySyncBatchJob.php)
- [`Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php`](../../../Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php) §`handleHistorySync`
- [`Modules/Whatsapp/Tests/Feature/HistorySyncMetricsTest.php`](../../../Modules/Whatsapp/Tests/Feature/HistorySyncMetricsTest.php)
- [`config/otel.php`](../../../config/otel.php) — Decisão arquitetural (sem PECL opentelemetry no Hostinger)
- [Handoff 2026-05-14 0300](../../handoffs/2026-05-14-0300-whatsapp-async-queue-final-fix.md) item 2
