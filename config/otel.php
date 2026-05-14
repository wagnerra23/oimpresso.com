<?php

/**
 * OpenTelemetry config — oimpresso (US-WA-083).
 *
 * Escopo nesta fase: lightweight bridge (extrai traceparent + injeta no Log
 * context). Não usa SDK completo (`open-telemetry/sdk`) nem extensão PECL
 * `opentelemetry` — Hostinger shared hosting tem limitações.
 *
 * Evolução futura (`OTEL_FULL_SDK=true` quando migrar pra container):
 *   - composer require open-telemetry/sdk open-telemetry/exporter-otlp
 *   - install PECL ext opentelemetry (auto-instrumentation)
 *   - config exporter aponta pra collector CT 100
 *
 * Por hora basta correlacionar trace_id dos webhooks daemon→Hostinger no
 * Loki/log central, sem propagation interna Laravel.
 */

return [

    /*
    |---------------------------------------------------------------------
    | Enabled flag — pode ser desligado por env (testes locais, dry runs)
    |---------------------------------------------------------------------
    */
    'enabled' => (bool) env('OTEL_ENABLED', true),

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
