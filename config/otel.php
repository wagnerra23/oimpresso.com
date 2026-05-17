<?php

/**
 * OpenTelemetry config — oimpresso (US-WA-083 + Wave 26 OTel collector CT 100).
 *
 * Histórico:
 *  - US-WA-083: lightweight bridge (extrai traceparent + injeta no Log context).
 *  - Wave 26 (ADR 0162 — proposta): SDK completo via `OtelServiceProvider` —
 *    composer require open-telemetry/{sdk,exporter-otlp,opentelemetry-auto-laravel}.
 *
 * Default `enabled=false` na Wave 26: Wagner ativa por env quando collector
 * CT 100 estiver pronto. Kill-switch `sdk_disabled=true` desliga sem mexer flag.
 *
 * Tier 0 multi-tenant ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * NUNCA colocar PII (CPF/CNPJ/email cliente) em ResourceAttributes globais.
 * `business_id` por span via OtelHelper::spanBiz() (não aqui).
 *
 * Sampling: 5% default. Erros sempre amostrados (`always_sample_errors=true`).
 */

return [

    /*
    |---------------------------------------------------------------------
    | Enabled flag — Wave 26 default false (Wagner ativa via env)
    |---------------------------------------------------------------------
    | OBS: até Wave 26 (Hostinger-only US-WA-083) o default era true porque
    | o bridge era zero-cost (só extrair traceparent). Agora que SDK full
    | inicializa TracerProvider, default vira false até CT 100 collector
    | estar pronto. Hostinger: deixar false. CT 100 .env: OTEL_ENABLED=true.
    */
    'enabled' => (bool) env('OTEL_ENABLED', false),

    /*
    |---------------------------------------------------------------------
    | SDK disabled — kill switch emergencial (Wave 26)
    |---------------------------------------------------------------------
    | Quando true, OtelServiceProvider faz early return mesmo se enabled=true.
    | Útil pra desligar instrumentation rapidamente sem mexer na flag principal
    | (ex: collector caiu, latência adicional intolerável, etc).
    */
    'sdk_disabled' => (bool) env('OTEL_SDK_DISABLED', false),

    /*
    |---------------------------------------------------------------------
    | Endpoint OTLP/HTTP (Wave 26)
    |---------------------------------------------------------------------
    | OtelServiceProvider lê esta chave primeiro; cai pra `exporter.endpoint`
    | (US-WA-083 legacy) se ausente. CT 100 OTel collector escuta em 4318.
    */
    'endpoint' => env('OTEL_EXPORTER_OTLP_TRACES_ENDPOINT', 'http://mcp.oimpresso.com:4318/v1/traces'),

    /*
    |---------------------------------------------------------------------
    | Sample rate (Wave 26) — 5% default
    |---------------------------------------------------------------------
    | TraceIdRatioBasedSampler. Erros sempre amostrados via flag abaixo.
    | OtelServiceProvider cai pra `sampling.local_sampler_ratio` (US-WA-083)
    | se esta chave ausente.
    */
    'sample_rate' => (float) env('OTEL_SAMPLE_RATE', 0.05),

    /*
    |---------------------------------------------------------------------
    | Always sample errors (Wave 26)
    |---------------------------------------------------------------------
    | Quando true, spans com erro são sempre exportados independente do
    | sample_rate. Implementação fica nos call-sites (OtelHelper::span já
    | recordException em catch).
    */
    'always_sample_errors' => (bool) env('OTEL_ALWAYS_SAMPLE_ERRORS', true),

    /*
    |---------------------------------------------------------------------
    | Service name top-level (Wave 26)
    |---------------------------------------------------------------------
    | OtelServiceProvider e OtelHelper preferem esta chave; caem pra
    | `service.name` (US-WA-083) se ausente. Mantém ambas pra retrocompat.
    */
    'service_name' => env('OTEL_SERVICE_NAME', 'oimpresso'),

    /*
    |---------------------------------------------------------------------
    | Atributos globais (Wave 26) — Tier 0 NUNCA PII
    |---------------------------------------------------------------------
    | Resource attributes globais. business_id e module são populados
    | por-request via OtelHelper::spanBiz() — NÃO aqui.
    */
    'attributes' => [
        'oimpresso.tenant_id' => null, // populated per-request via OtelHelper
        'oimpresso.module' => null,    // populated per-request
    ],

    /*
    |---------------------------------------------------------------------
    | Enabled flag legacy (US-WA-083) — mantida pra retrocompat
    |---------------------------------------------------------------------
    | Tudo abaixo são chaves originais US-WA-083 preservadas. OtelHelper +
    | OtelServiceProvider leem chaves novas (acima) com fallback pra estas.
    */

    /*
    |---------------------------------------------------------------------
    | Service identification — atributos resource padrão OTel
    |---------------------------------------------------------------------
    */
    'service' => [
        'name' => env('OTEL_SERVICE_NAME', 'oimpresso-laravel'),
        'version' => env('OTEL_SERVICE_VERSION', '1.0.0'),
        'environment' => env('APP_ENV', 'production'),
    ],

    /*
    |---------------------------------------------------------------------
    | Exporter OTLP (futuro — quando ativar SDK full)
    |---------------------------------------------------------------------
    | Aponta pro mesmo collector do daemon CT 100. HTTP/protobuf é o
    | default mais compatível com shared hosting (sem gRPC).
    */
    'exporter' => [
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'https://otel.oimpresso.com'),
        'protocol' => env('OTEL_EXPORTER_OTLP_PROTOCOL', 'http/protobuf'),
        'timeout_seconds' => (int) env('OTEL_EXPORTER_TIMEOUT', 10),
    ],

    /*
    |---------------------------------------------------------------------
    | Sampling — propagação respeita o `sampled` bit do daemon
    |---------------------------------------------------------------------
    | Daemon decide sample em /webhook entry. Laravel só herda. Quando SDK
    | full ativar, este `local_sampler_ratio` aplica se request entrou sem
    | traceparent (origens não-daemon, ex: UI usuário).
    */
    'sampling' => [
        'respect_parent' => true,
        'local_sampler_ratio' => (float) env('OTEL_LOCAL_SAMPLER_RATIO', 0.05), // 5% em rotas sem parent
    ],

    /*
    |---------------------------------------------------------------------
    | Rotas onde extrair traceparent é mandatório (webhook receivers)
    |---------------------------------------------------------------------
    | Listadas só pra documentação — middleware aplicado via Routes/api.php.
    */
    'instrumented_routes' => [
        'atendimento.channels.baileys.webhook',
    ],
];
