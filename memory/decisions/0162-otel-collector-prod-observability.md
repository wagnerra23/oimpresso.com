---
slug: 0162-otel-collector-prod-observability
number: 162
title: "OpenTelemetry Collector ativo em prod (CT 100) — destrava D6.b + D9.b governance v4"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-17"
accepted_at: 2026-05-17
review_at: 2026-08-17
module: Infra
quarter: 2026-Q2
tags: [observability, opentelemetry, otel-collector, tempo, grafana, governance, ct100, sampling, frankenphp]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0058-reverb-substituido-por-centrifugo-frankenphp, 0062-separacao-runtime-hostinger-ct100, 0094-constituicao-v2-7-camadas-8-principios, 0155-module-grade-v3-sub-dimensoes-gate-ci, 0156-module-grade-v3-errata-otel-helper-na-justified, 0160-governance-v4-scoped-scorecards-buckets, 0161-governance-v4-aposentar-hacks-0159-redundantes]
pii: false
review_triggers:
  - Se >1% overhead p99 detectado em prod (3 dias consecutivos) — reduzir sampling pra 1%
  - Quando 3+ módulos adicionais instrumentados (>5 services total) — revisar collector resources CT 100
  - Quando volume Tempo storage exceder 100MB/dia — ativar retention 7d
  - Se hack D9.b ADR 0159 puder ser aposentado (collector estável 30 dias + ScopedScorecardEvaluator detectOtelQuery retornando valores reais)
---

# ADR 0162 — OpenTelemetry Collector ativo em prod (CT 100) — destrava D6.b + D9.b governance v4

## 1. Contexto

A rubrica `module-grade-v4` ([ADR 0160](0160-governance-v4-scoped-scorecards-buckets.md)) introduziu sub-dimensões **D6.b** (telemetry ready) e **D9.b** (observability prod queries) em 34 módulos. Ambas ainda operam em **placeholder mode** — `ScopedScorecardEvaluator::detectOtelQuery()` retorna pass-through (`true` default) porque não há backend OTel ativo pra consultar.

[ADR 0156](0156-module-grade-v3-errata-otel-helper-na-justified.md) ratificou que oimpresso já tem a facade canônica `App\Util\OtelHelper` ([`app/Util/OtelHelper.php`](../../app/Util/OtelHelper.php)) — wrapper zero-cost (no-op quando SDK ausente, fail-safe sem backend), resolvedor automático de `business_id` Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)). Falta apenas o **collector + backend** ativos em prod pra fechar o loop.

**Estado da arte 2026** (`memory/sessions/2026-05-17-arte-oimpresso-vs-melhores-2026.md`):

- **DORA Elite 19% (2026):** todas operações Elite têm tracing distribuído ativo (Datadog/Honeycomb/Tempo)
- **OpenSSF Scorecard média 5.4/10:** dimensão Observability é #2 pior nota globalmente — oportunidade clara
- **Cortex Wrapped 2026:** customers com OTel ativo reportam **+64% deploy frequency** vs sem
- **Grafana Tempo 2.6+ (out/2025):** maturidade OSS atingiu paridade com Honeycomb/Datadog em queries TraceQL

**Diagnóstico oimpresso:**

- D6.b + D9.b em **placeholder em 15+ módulos** (Sells, Repair, Jana, Whatsapp, Crm, Financeiro, NfeBrasil, RecurringBilling, etc.)
- Salto esperado pós-ativação: **+12-16 pts média** em 34 módulos (76.9 → **82-84**)
- Hack D9.b residual de [ADR 0159](0159-module-grade-v3-errata-meta-97-realismo.md) — mantido por [ADR 0161](0161-governance-v4-aposentar-hacks-0159-redundantes.md) §4 — pode ser aposentado quando collector estabilizar

**Princípio:** loop fechado por métrica ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §4) só fecha se a métrica é REAL — placeholder permanente é dívida de governance.

## 2. Decisão

Subir **OpenTelemetry Collector v0.110+** no **CT 100 Proxmox** ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md)) dentro do docker-compose existente junto com FrankenPHP + Centrifugo ([ADR 0058](0058-reverb-substituido-por-centrifugo-frankenphp.md)).

- **Backend traces:** **Grafana Tempo 2.6+** (OSS, leve, query TraceQL nativo)
- **Backend metrics:** Prometheus existente CT 100 (zero infra nova)
- **Dashboard:** Grafana CT 100 admin (já provisionado pra Centrifugo)
- **PHP SDK:** `open-telemetry/opentelemetry-auto-laravel` + `open-telemetry/sdk` (já listados como opcional no `composer.json`)
- **Sampling prod:** **5% head-based** baseline + **always-on em erros/4xx/5xx + ops >2s + FSM transitions críticos**
- **Hostinger NÃO recebe collector daemon** — Tier 0 IRREVOGÁVEL ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md)). Apps Hostinger fazem **OTLP HTTP push** pra `mcp.oimpresso.com:4318` (collector CT 100)

## 3. Arquitetura

```
┌─────────────────────────────────────────────────────────────┐
│ Laravel apps                                                 │
│  ├── Hostinger (web shared hosting)                          │
│  └── CT 100 (FrankenPHP + workers IA)                        │
│        │                                                     │
│        │ OTel SDK PHP (auto-laravel + sdk)                   │
│        │ Sampling 5% head-based + always-on errors           │
│        ↓ OTLP HTTP push (port 4318)                          │
│                                                              │
│ ┌──────────────────────────────────────────────────────┐    │
│ │ CT 100 Proxmox (mcp.oimpresso.com)                   │    │
│ │  └─ docker-compose:                                  │    │
│ │      ├── frankenphp (web/workers — ADR 0058)         │    │
│ │      ├── centrifugo (broadcast — ADR 0058)           │    │
│ │      ├── otel-collector v0.110+ ← NOVO               │    │
│ │      │     ├── receiver: OTLP HTTP (4318) + gRPC (4317)│  │
│ │      │     ├── processor: batch + memory_limiter     │    │
│ │      │     ├── processor: tail_sampling (errors=100%)│    │
│ │      │     ├── exporter → tempo (traces)             │    │
│ │      │     ├── exporter → prometheus (metrics)       │    │
│ │      │     └── exporter → mysql-write (spans table)  │    │
│ │      ├── tempo 2.6+ ← NOVO (storage local /var/tempo)│    │
│ │      └── grafana (dashboard OTel + Centrifugo)       │    │
│ └──────────────────────────────────────────────────────┘    │
│                          │                                   │
│                          ↓ MySQL write (collector exporter)  │
│ ┌──────────────────────────────────────────────────────┐    │
│ │ Hostinger MySQL (DB oimpresso)                       │    │
│ │  └── mcp_observability_spans (schema canon §5)       │    │
│ │      └── consultado por ScopedScorecardEvaluator     │    │
│ │          ::detectOtelQuery() — D9.b real, não pass   │    │
│ └──────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

## 4. Três services iniciais instrumentados

Wave 26+ aplica `OtelHelper::spanBiz()` em **3 services canônicos** (uma feature por módulo bucket diferente — sinal cross-cutting):

| # | Módulo | Service / Path | Span name padrão | Atributos canônicos |
|---|---|---|---|---|
| 1 | **Modules/Jana** (`ai_central`) | `Modules/Jana/Ai/Agents/*Agent.php` (LLM chain) | `jana.agent.<agent_name>.execute` | `business_id`, `agent`, `model`, `tokens_in`, `tokens_out`, `cost_usd`, `latency_ms` |
| 2 | **Modules/Repair** (`vertical_client_facing` + `functional_horizontal`) | `app/Domain/Fsm/Services/ExecuteStageActionService.php` (Repair JobSheet path) | `repair.fsm.execute_action` | `business_id`, `job_sheet_id`, `action_key`, `from_stage`, `to_stage`, `user_id` |
| 3 | **Modules/Sells** (`functional_horizontal`) | `app/Domain/Fsm/Services/ExecuteStageActionService.php` (Sells Transaction path) | `sells.fsm.execute_action` | `business_id`, `transaction_id`, `action_key`, `from_stage`, `to_stage`, `user_id` |

**Por que esses 3 primeiro:**

- **Jana** valida custo IA tracking (Constituição v2 §4) — span carrega `cost_usd` real por chamada LLM
- **Repair + Sells FSM** já têm `ExecuteStageActionService` canônico ([ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) LIVE prod biz=1) — instrumentar 1 service cobre 2 módulos em buckets distintos
- **PII bloqueada:** atributos só carregam IDs + scalars; nunca CPF/CNPJ/email/telefone (Tier 0 — proibições.md)

## 5. Schema `mcp_observability_spans` (canon)

Migration nova em `database/migrations/2026_05_17_create_mcp_observability_spans_table.php`:

```php
Schema::create('mcp_observability_spans', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('business_id')->index();  // Tier 0 multi-tenant
    $table->string('module', 64)->index();                // 'Jana', 'Repair', 'Sells', ...
    $table->string('span_name', 128)->index();            // 'sells.fsm.execute_action'
    $table->unsignedInteger('duration_ms');               // latência observada
    $table->enum('status', ['ok', 'error', 'timeout'])->default('ok')->index();
    $table->json('attributes_json')->nullable();          // scalars only — sem PII
    $table->timestamp('span_started_at')->index();
    $table->timestamp('created_at')->useCurrent();

    // Indexes compostos pra queries D9.b
    $table->index(['business_id', 'module', 'span_started_at'], 'idx_biz_mod_started');
    $table->index(['module', 'span_name', 'span_started_at'], 'idx_mod_span_started');
});
```

**Materialized view diária** (cron `php artisan otel:rollup-daily` 02:00 BRT):

```sql
CREATE TABLE mcp_observability_rollup_daily (
    rollup_date DATE,
    business_id BIGINT UNSIGNED,
    module VARCHAR(64),
    span_name VARCHAR(128),
    count_total INT UNSIGNED,
    count_error INT UNSIGNED,
    p50_ms INT UNSIGNED,
    p95_ms INT UNSIGNED,
    p99_ms INT UNSIGNED,
    PRIMARY KEY (rollup_date, business_id, module, span_name)
);
```

Consultada por `ScopedScorecardEvaluator::detectOtelQuery($module, $bucket)` — retorna `true` quando >=N spans no último ROLLUP_WINDOW (7 dias) pro módulo/bucket sendo avaliado.

## 6. Sampling strategy

| Cenário | Sampling rate | Como |
|---|---|---|
| **Erros (status_code != ok)** | **100%** | Tail-based no collector (`tail_sampling_processor`) |
| **HTTP 4xx + 5xx** | **100%** | Tail-based (response attribute match) |
| **Ops >2000ms duration** | **100%** | Tail-based (latency_threshold) |
| **FSM transitions críticos** (`is_critical=true` actions ADR 0143) | **100%** | Head-based via `OtelHelper::spanBiz` flag `force_sample=true` |
| **Baseline ops <100ms** | **5%** | Head-based no SDK (`ParentBasedSampler` + `TraceIdRatioBasedSampler(0.05)`) |
| **Dev/test local** | **100%** | Env override `OTEL_TRACES_SAMPLER_ARG=1.0` |

Configurável via env `.env`:

```bash
OTEL_SDK_DISABLED=false                          # default prod
OTEL_TRACES_SAMPLER=parentbased_traceidratio
OTEL_TRACES_SAMPLER_ARG=0.05                     # 5% baseline prod
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_ENDPOINT=https://mcp.oimpresso.com:4318
```

## 7. Custo prod estimado

- **Trace storage Tempo (CT 100 disco local):** ~50MB/dia
  - Premissas: 1000 req/min × 60min × 24h × 5% sampling × 2KB/span médio
  - Retention default 14 dias = ~700MB total
  - Trigger review_triggers: rotaciona pra retention 7d se exceder 100MB/dia
- **MySQL `mcp_observability_spans` (Hostinger):** ~20MB/dia (subset filtrado pra D9.b queries)
- **CPU overhead PHP SDK auto-laravel:** <0.5% medido em benchmarks oficiais (sampling 5%)
- **Network OTLP push (Hostinger→CT 100):** ~5MB/dia (gzip+protobuf comprime ~80%)
- **Custo LLM:** **ZERO** — observability não chama IA

## 8. Backward-compat

- **`OtelHelper` já é canônico** ([`app/Util/OtelHelper.php`](../../app/Util/OtelHelper.php)) desde [ADR 0051](0051-schema-proprio-adapter-otel-genai.md) + ratificado [ADR 0156](0156-module-grade-v3-errata-otel-helper-na-justified.md) — código existente em Sells FSM, Jana embeddings, Whatsapp daemon já usa `OtelHelper::spanBiz()` em zero-cost no-op
- **Wave 26 inverte default** `otel.enabled` de `false` → `true` E ativa exporter OTLP HTTP
- **Módulos sem instrumentation continuam funcionando** — sem span emitido = não aparece em `mcp_observability_spans` = D9.b retorna placeholder pra esses módulos (governance v4 já lida via paired indicator)
- **Nenhuma migração breaking** — composer require opcional, env flag opcional, schema novo (não altera existente)

## 9. Rollback

Em emergência (overhead detectado, collector crashando, Tempo cheio):

```bash
# Hot rollback sem deploy — 1 linha .env
OTEL_SDK_DISABLED=true

# Effects:
# - PHP SDK para de gerar spans (zero overhead PHP)
# - Apps continuam funcionando 100% (OtelHelper é fail-safe)
# - Collector CT 100 fica idle (sem receivers)
# - Reativação: OTEL_SDK_DISABLED=false + reload PHP-FPM/FrankenPHP
```

**Rollback governance v4:**

Se ativação não destravar D9.b real (collector queries não conclusivas após 30 dias), aplicar errata mantendo hack ADR 0159 D9.b permanente — sem retrabalho na rubrica v4.

## 10. Métricas de sucesso

| Métrica | Target 30 dias pós-ativação | Como medir |
|---|---|---|
| **D6.b deixa placeholder** | **>=15 módulos** transitam de pass-through pra real | Snapshot `ModuleGradeServiceV4::detectOtelReady()` antes/depois |
| **D9.b `detectOtelQuery()` retorna real** | **>=10 módulos** com spans no rollup diário | Query `SELECT COUNT(DISTINCT module) FROM mcp_observability_rollup_daily WHERE rollup_date >= NOW() - INTERVAL 7 DAY` |
| **Grafana dashboard p99 por módulo por bucket** | **Painel publicado** + 4 buckets distinguíveis | Screenshot do dashboard `Grafana > Folder Governance > OTel Modules Overview` |
| **Soak antes ativar 100% sampling em erros** | **7 dias** collector estável sem incidente | `kubectl logs otel-collector` zero ERROR + Tempo disk usage estável |
| **Hack ADR 0161 §4 (D9.b residual)** | Pode ser **aposentado** | Quando ScopedScorecardEvaluator retornar valores reais consistentes 30 dias — abrir ADR errata 0163+ |
| **Overhead PHP p99** | **<1%** | Comparar Datadog/NewRelic baseline antes vs depois (ou benchmark local Pest) |

## Tier 0 IRREVOGÁVEIS

- ⛔ **Hostinger NÃO recebe collector daemon** ([ADR 0062](0062-separacao-runtime-hostinger-ct100.md) §"Runtime separado"). Apps Hostinger fazem APENAS OTLP HTTP push pra CT 100. Tentar `docker run otel-collector` no Hostinger = violação Tier 0.
- ⛔ **PII NUNCA em trace attributes.** Atributos `attributes_json` carregam scalars/IDs apenas (`business_id`, `transaction_id`, `action_key`, `tokens_in`). Proibido `customer_name`, `cpf`, `cnpj`, `email`, `phone`, `address` — mesma regra `PiiRedactor` ([proibicoes.md](../proibicoes.md) §Multi-tenant Tier 0)
- ⛔ **Sampling 5% prod evita overhead >1%.** Mudar pra 100% baseline só em dev/test (env override). Em prod, sempre tail-based pra erros/slow ops (100% sample esses casos via processor, não via head sampler) — head 100% prod = violação custo
- ⛔ **`business_id` global scope em `mcp_observability_spans`** ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) — coluna NOT NULL + indexed; query D9.b sempre filtra por `business_id` (ScopedScorecardEvaluator passa contexto)
- ⛔ **PT-BR** em comentários e docs (código/identificadores OTel em inglês mantém compat SDK)

## Referências

- [ADR 0058](0058-reverb-substituido-por-centrifugo-frankenphp.md) — Centrifugo + FrankenPHP CT 100 docker-compose host (collector vira 3º container)
- [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) — Hostinger ≠ CT 100 IRREVOGÁVEL (Tier 0 runtime)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 §4 (loop fechado por métrica) + §6 (multi-tenant Tier 0) + §8 (confiabilidade com fallback — OtelHelper zero-cost no-op)
- [ADR 0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md) — D6.b telemetry ready + D9.b observability prod (sub-dimensões introduzidas)
- [ADR 0156](0156-module-grade-v3-errata-otel-helper-na-justified.md) — OtelHelper canônico ratificado (regex D9.a inclui facade)
- [ADR 0160](0160-governance-v4-scoped-scorecards-buckets.md) — Scoped Scorecards 4 buckets + meta por bucket (consumidor das queries D6.b/D9.b)
- [ADR 0161](0161-governance-v4-aposentar-hacks-0159-redundantes.md) §4 — hack D9.b residual permanece até esta ADR estabilizar 30 dias
- `memory/sessions/2026-05-17-arte-oimpresso-vs-melhores-2026.md` — estado da arte recomendando ativação
- [`app/Util/OtelHelper.php`](../../app/Util/OtelHelper.php) — facade canônica oimpresso (zero-cost no-op)
